<?php

defined('IN_SIMPHP') or die('Access Denied');
require (__DIR__.'./../../jsonRPCClient.php');


/**
 * 酒币接口
 * @author maliang
 *
 */
class Gwt_Controller extends Controller {

    public $client = null;
    public $decimals = 18;

    //布署合约的账户地址 或者主账户地址
    public $coinbase = '';
    public $password = '';
    //合约地址
    public $contract = '0x7f0f05d1a4d5bdeaa35aae5a765c600e46e0b14e';
    //etherscan.io网站 API 地址
    public $etherscan_api = 'https://api.etherscan.io/api';

    public function __construct() {
        /**
         * "1": Ethereum Mainnet
         * "2": Morden Testnet (deprecated)
         * "3": Ropsten Testnet
         * "4": Rinkeby Testnet
         * "42": Kovan Testnet
         */
        if ($this->net_version() != 1) {
            //布署合约的账户地址
            $this->coinbase = '';
            $this->password = '';
            $this->contract = '';
            $this->decimals = 4;
            $this->etherscan_api = 'https://api-ropsten.etherscan.io/api';
        }
    }

    public function __call($method, $params)
    {
        $params = count($params) < 1 ? [] : $params[0];
        try {
            if (is_null($this->client)) {
                    $this->client = new jsonRPCClient('http://127.0.0.1:8545');
            }
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
        return call_user_func([$this->client, $method], $params);
    }

    /**
     * 请求方法
     * @param  [type] $url  [description]
     * @param  [type] $data [description]
     * @return [type]       [description]
     */
    function https_request($url,$data = []){
        $curl = curl_init();
        $data = http_build_query($data);
        curl_setopt($curl,CURLOPT_URL,$url);
        curl_setopt($curl,CURLOPT_SSL_VERIFYPEER,FALSE);
        curl_setopt($curl,CURLOPT_SSL_VERIFYHOST,FALSE);
        if(!empty($data)){
            curl_setopt($curl,CURLOPT_POST,1);
            curl_setopt($curl,CURLOPT_POSTFIELDS,$data);
        }
        curl_setopt($curl,CURLOPT_RETURNTRANSFER,1);
        $output = curl_exec($curl);
        curl_close($curl);
        return $output;
    }

    /**
     * 十进制转换十六进制
     * @param  [type] $decimal [description]
     * @return [type]          [description]
     */
    public function bc_dechex($decimal)
    {
        $result = [];
        while ($decimal != 0) {
            $mod = $decimal % 16;
            $decimal = floor($decimal / 16);
            array_push($result, dechex($mod));        
        }
        return join(array_reverse($result));
    }

    /**
     * 字符串转换为十六进制
     * @param  [type] $string [description]
     * @return [type]         [description]
     */
    function string2Hex($string){
        $hex='';
        for ($i=0; $i < strlen($string); $i++){
            $hex .= dechex(ord($string[$i]));
        }
        return $hex;
    }

    /**
     * 返回指定数据的Keccak-256哈希值
     * @param  string $value [description]
     * @return [type]        [description]
     */
    public function sha3($value='')
    {
        return $this->web3_sha3(['0x'.$this->string2Hex($value)]);
    }

    /**
     * 返回指定方法哈希值的前4位
     * @param  string $value [description]
     * @return [type]        [description]
     */
    public function methodHash($value='')
    {
        return substr($this->sha3($value), 0, 10);
    }

    /**
     * 转账方法
     * @param  [type] $from   [description]
     * @param  [type] $to     [description]
     * @param  [type] $amount [description]
     * @return [type]         [description]
     */
    public function sendTransaction($from, $to, $amount)
    {
        $eth = hexdec($this->eth_getBalance([$from, 'latest'])) / (pow(10, $this->decimals));
        if ($eth <= 0) {
            return 'eth不能为0。';
        }
        $gwt = $this->balance($from);
        if ($gwt <= $amount) {
            return 'gwt数量不够。';
        }

        $gas = $this->getGas($from, $to, $amount);
        $amount = bcpow(10, $this->decimals) * $amount;
        $method_hash = '0xa9059cbb';  // 方法hex后的前4位
        $method_param1_hex = $this->addressHex64($to);
        $method_param2_hex = str_pad(strval($this->bc_dechex($amount)), 64, '0', STR_PAD_LEFT);

        $data = $method_hash . $method_param1_hex . $method_param2_hex;
        $params = [
            'from' => $from,
            'to' => $this->contract,
            'gas' => $gas,  //'0x30d40',  // 200000
            'gasPrice' => $this->gasPrice(),  //'0x3b9aca00',  // 1000000000
            'value' => '0x0',
            'data' => $data
        ];

        return $this->eth_sendTransaction([$params]);
    }

    /**
     * 解锁用户
     * @param  [type] $address  [description]
     * @param  [type] $password [description]
     * @return [type]           [description]
     */
    public function unlockAccount($address, $password)
    {
        return $this->personal_unlockAccount([$address, $password, 3600]);
    }

    /**
     * 地址转化为64位
     * @param  [type] $address [description]
     * @return [type]          [description]
     */
    public function addressHex64($address)
    {
        return str_pad(substr($address, 2), 64, '0', STR_PAD_LEFT);
    }

    /**
     * 验证地址
     * @param  [type] $addres [description]
     * @return [type]         [description]
     */
    public function checkAddress($address)
    {
        return preg_match("/0x[a-fA-F0-9]{40}/", $address);
    }

    /**
     * 转化64位地址为40位地址
     * @param  [type] $address [description]
     * @return [type]          [description]
     */
    public function getAddressFrom64($address)
    {
        if (preg_match("/0x[a-fA-F0-9]{64}/", $address)) {
            return '0x'.substr($address, 26);
        }
        return false;
    }

    /**
     * 16位币值转化为10位币值
     * @param  [type] $amount [description]
     * @return [type]         [description]
     */
    public function amountToDec($amount)
    {
        return hexdec($amount) / pow(10, $this->decimals);
    }

    /**
     * 获取gas估算量
     * @return [type] [description]
     */
    public function getGas($from, $to, $amount)
    {
        $amount = bcpow(10, $this->decimals) * $amount;
        $method_hash = '0xa9059cbb';
        $method_param1_hex = $this->addressHex64($to);
        $method_param2_hex = str_pad(strval($this->bc_dechex($amount)), 64, '0', STR_PAD_LEFT);

        $data = $method_hash . $method_param1_hex . $method_param2_hex;
        $params = [
            'from' => $from,
            'to' => $this->contract,
            'gas' => '0x30d40',  // 200000
            'gasPrice' => '0x3b9aca00',  // 1000000000
            'value' => '0x0',
            'data' => $data
        ];
        $val = $this->eth_estimateGas([$params]);  // 获取gas估算量
        $val = '0x'.$this->bc_dechex(ceil(hexdec($val) / 10000) * 10000);  // 万位取整增加预估量
        return $val;
    }

    /**
     * 获取当前的gas价格
     * @return [type] [description]
     */
    public function gasPrice()
    {
        $val = $this->eth_gasPrice();
        return $val;
    }

    /**
     * 查询余额
     * @param  [type] $address [description]
     * @return [type]          [description]
     */
    public function balance($address)
    {
        $method_hash = '0x70a08231';
        $method_param1_hex = $this->addressHex64($address);
        $data = $method_hash . $method_param1_hex;

        $params = ['from' => $address, 'to' => $this->contract, 'data' => $data];
        $total_balance = $this->eth_call([$params, "latest"]);

        return hexdec($total_balance) / (pow(10, $this->decimals));
    }

    public function index(Request $request, Response $response)
    {
        echo 'GWT api';
    }

    public function version(Request $request, Response $response)
    {
        return $this->net_version();
    }

    /**
     * 获取主钱包地址
     * @param  Request  $request  [description]
     * @param  Response $response [description]
     * @return [type]             [description]
     */
    public function getMainAddress(Request $request, Response $response)
    {
        echo $this->coinbase;
    }

    /**
     * 查看余额
     * @param  Request  $request  [description]
     * @param  Response $response [description]
     * @return [type]             [description]
     */
    public function getBalance(Request $request, Response $response)
    {
        $address = $request->post('address');
        echo $this->balance($address);
    }

    /**
     * 发放代币
     * @param  Request  $request  [description]
     * @param  Response $response [description]
     * @return [type]             [description]
     */
    public function transfer(Request $request, Response $response)
    {
        $to = $request->post('to');
        $amount = $request->post('amount');

        $this->unlockAccount($this->coinbase, $this->password);
        echo $this->sendTransaction($this->coinbase, $to, $amount);
    }

    /**
     * 将代币从一个地址转到另一个地址
     * @param  Request  $request  [description]
     * @param  Response $response [description]
     * @return [type]             [description]
     */
    public function transferFrom(Request $request, Response $response)
    {
        $from = $request->post('from');
        $to = $request->post('to');
        $amount = $request->post('amount');

        $this->unlockAccount($from, "ma3liang9");
        echo $this->sendTransaction($from, $to, $amount);
    }

    /**
     * 查看指定交易的收据，使用哈希指定交易
     * @param  Request  $request  [description]
     * @param  Response $response [description]
     * @return [type]             [description]
     */
    public function receipt(Request $request, Response $response)
    {
        $tx = $request->post('tx');
        echo json_encode($this->eth_getTransactionReceipt([$tx]));die();
    }

    /**
     * 查看指定哈希对应的交易
     * @param  Request  $request  [description]
     * @param  Response $response [description]
     * @return [type]             [description]
     */
    public function transactionByHash(Request $request, Response $response)
    {
        $tx = $request->post('tx');
        echo json_encode($this->eth_getTransactionByHash([$tx]));die();
    }

    /**
     * 创建新用户
     * @param  Request  $request  [description]
     * @param  Response $response [description]
     * @return [type]             [description]
     */
    public function newAccount(Request $request, Response $response)
    {
        $psw = strval(mt_rand(100000, 999999));
        $address = $this->personal_newAccount([$psw]);
        if ($this->checkAddress($address)) {
            $data = [
                'password' => $psw,
                'address' => $address
            ];
            echo json_encode($data);
            die();
        }
        echo '0';
    }

    /**
     * 用户地址转账纪录
     * @param  Request  $request  [description]
     * @param  Response $response [description]
     * @return [type]             [description]
     */
    public function accountTokentx(Request $request, Response $response)
    {
        $address = $request->post('address');
        $startblock = $request->post('startblock') ?: 0;
        $params = [
            'module' => 'account',
            'action' => 'tokentx',
            'address' => $address,  //  用户地址
            'startblock' => $startblock,  //  起始区块
            'sort' => 'desc'  // 'asc'
        ];
        $result = $this->https_request($this->etherscan_api, $params);
        echo $result;
        // json_decode($result, true);
    }

    /**
     * 查看转账日志
     * @param  Request  $request  [description]
     * @param  Response $response [description]
     * @return [type]             [description]
     */
    public function getLogs(Request $request, Response $response)
    {
        if($request->method() == 'post'){

        }
            $key = $request->post('gwt');  // 货币类型限制
            if(!$key=="gwt"){
                $data['code']=0;
                $data['error']="gwt is error ";
                die(json_encode($data));
            }

            // 获取上次区块链高度
            $fp = fopen(__DIR__.'./../../wid_height.txt','r');
            $oldheight = fgets($fp);
            fclose($fp);

            $address = $this->contract;  // token合约地址
            $startblock = $oldheight;  // 上次区块链高度
            $topic1 = $request->post('topic1');
            $topic2 = $this->coinbase;  // 主账户地址
            $params = [
                'module' => 'logs',
                'action' => 'getLogs',
                'fromBlock' => $startblock,  //  起始区块
                'toBlock' => 'latest',
                'address' => $address,  //  token合约
                'topic0' => '',
            ];

            if (!empty($topic1)) {  // 转账地址
                $params['topic0_1_opr'] = 'and';
                $params['topic1'] = '0x'.$this->addressHex64($topic1);
            }
            if (!empty($topic2)) {  // 收账地址
                $params['topic0_2_opr'] = 'and';
                $params['topic2'] = '0x'.$this->addressHex64($topic2);
            }
            // 获取交易日志
            $result = json_decode($this->https_request($this->etherscan_api, $params), true);
            if ($result['message'] == 'OK') {
                // 用户地址列表
                $arr_address = D()->query("select * from gwt_user_into_address")->fetch_array_all();
                $arr_address = array_column($arr_address, 'uid', 'address');

                $arr_wallet = array();
                foreach ($result['result'] as $key => $item) {
                    if (isset($arr_address[$this->getAddressFrom64($item['topics'][1])])) {  // 判断数据库是否有此地址
                        $user_id = $arr_address[$this->getAddressFrom64($item['topics'][1])];

                        //查询是否已充值
                        $myzr = Gwt_Model::getTibiLog( array( 'ti_id' => "'".$item['transactionHash']."'", 'currency_id' => 60) );
                        if ( $myzr ) {
                            continue;
                        }

                        //写充值记录，加用户钱
                        $amount = $this->amountToDec($item['data']);
                        $from   = $this->getAddressFrom64($item['topics'][1]);
                        $to   = $this->getAddressFrom64($item['topics'][2]);

                        $rs2 = Gwt_Model::saveTibiLog( array(
                            'user_id'      => $user_id,
                            'chongzhi_url' => $to,
                            'url'          => $from,
                            'currency_id'  => 60,
                            'fee'          => 0,
                            'ti_id'        => $item['transactionHash'],
                            'num'          => $amount,
                            'actual'       => 0,
                            'add_time'     => hexdec($item['timeStamp']),
                            'status'       => 3
                        ));
                        
                        $arr_wallet[$key]['user_id'] = $user_id;
                        $arr_wallet[$key]['amount'] = $amount;
                    }
                    $newheight = $item['blockNumber'];
                }

                $fp = fopen(__DIR__.'./../../wid_height.txt','w');
                fwrite($fp, $newheight);
                fclose($fp);
                $data['code'] = 1;
                $data['result'] = $arr_wallet;
                die(json_encode($data));
            }
    }

}








