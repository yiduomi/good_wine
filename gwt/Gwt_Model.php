<?php

/**
* 
*/
defined('IN_SIMPHP') or die('Access Denied');
class Gwt_Model extends Model
{
    /**
     * 检查用户是否存在
     * @param  string $uid [description]
     * @return [type]      [description]
     */
    static function checkUser($uid = '')
    {
        if (empty($uid)) {
            return false;
        }
        $sql = "select * from shp_users where user_id = {$uid}";
        $user = D()->query($sql)->result();
            return $user;
    }

    /**
     * 获取用户地址里列表
     * @param  array  $parameter [description]
     * @return [type]            [description]
     */
    static function getUserAddrList($parameter = array())
    {
        $sql = "select * from wid_user_address where 1 ";
        if (count($parameter) > 0) {
            foreach ($parameter as $key => $value) {
                $sql .= "and {$key} = {$value} ";
            }
        }
        return D()->query($sql)->fetch_array_all();
    }
    
    /**
     * 保存用户wid地址
     * @param  array  $parameter [description]
     * @return [type]            [description]
     */
    static function saveUserAddr($parameter = array())
    {
        $uid = $parameter['user_id'];
        $addr = $parameter['chongzhi_url'];
        $currency_id = $parameter['currency_id'];

        $sql = "select * from wid_user_address where user_id = {$uid}";
        $currency = D()->query($sql)->result();
        if ($currency) {  // 如果用户已经存在记录,则修改记录
            $sql = "update wid_user_address set chongzhi_url = '{$addr}', currency_id = {$currency_id} where user_id = {$uid}";
            return D()->query($sql)->result();
        }
        $sql = "insert into wid_user_address set chongzhi_url = '{$addr}', currency_id = {$currency_id}, user_id = {$uid}";
        return D()->query($sql)->result();
    }

    /**
     * 获取单条提币记录
     * @param  array  $parameter [description]
     * @return [type]            [description]
     */
    static function getTibiLog($parameter = array())
    {
        $sql = "select * from wid_tibi_log where 1 ";
        if (count($parameter) > 0) {
            foreach ($parameter as $key => $value) {
                $sql .= "and {$key} = {$value} ";
            }
        }
        return D()->query($sql)->get_one();
    }

    /**
     * 保存提币记录
     * @param  array  $parameter [description]
     * @return [type]            [description]
     */
    static function saveTibiLog($parameter = array())
    {
        return D()->insert('`wid_tibi_log`', $parameter);
    }

    /**
     * 获取提币记录列表
     * @param  array  $parameter [description]
     * @return [type]            [description]
     */
    static function getPayLogList($parameter = array())
    {
        $sql = "select * from wid_pay_log where 1 ";
        if (count($parameter) > 0) {
            foreach ($parameter as $key => $value) {
                $sql .= "and {$key} = {$value} ";
            }
        }
        return D()->query($sql)->fetch_array_all();
    }

    /**
     * 保存提币日志
     * @param  array  $parameter [description]
     * @return [type]            [description]
     */
    static function savePayLog($parameter = array())
    {
        return D()->insert('`wid_pay_log`', $parameter);
    }
}