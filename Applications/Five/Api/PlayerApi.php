<?php
/**
 * Created by PhpStorm.
 * User: song
 * Date: 2018/2/26
 * Time: 11:15
 */

namespace Api;


use Common\RedisUtil;
use Temp\Equip;
use Temp\Item;

class PlayerApi
{
    public static function init($message, $client_id){
        //session丢失请登录
        if(empty($_SESSION['uid'])){
            return PublicApi::retData(100);
        }

        switch ($message[0]) {
            case 10000: //玩家信息
                return self::getPlayerInfo($message);
            case 10002: //玩家
                return self::changeHeader($message);
            case 10003: //微信存储
                return self::changeInfo($message);
            case 10004: //进入游戏后玩家信息
                return self::getUserInfo();
            case 10005: //点击加金币
                return self::addGold();
            default:
                return 0;
        }
    }

    /**用户信息
     * @return string
     */
    public static function getPlayerInfo($params){
        $uid = $_SESSION['uid'];
        $cmd = $params[0];
        if(!empty($params[1])){
            //保存名字
            RedisUtil::redisHset('com_usernames', $params[1], $uid);
        }

        $user_data = RedisUtil::redisHmget($uid, ['id', 'name', 'header','gold']);

        return PublicApi::retData($cmd, [$user_data['id'],
            $user_data['name'],
            $user_data['header'],
            $user_data['gold'],]);
    }

    public static function userInfo(){
        $uid = $_SESSION['uid'];
        $user_name = RedisUtil::redisHget($uid, 'name');

        $aid = PublicApi::uidToAid($uid);
        RedisUtil::redisHset('com_usernames', $user_name, $aid);
    }

    //微信昵称头像上传
    Public static function changeInfo($params){
        $uid = $_SESSION['uid'];
        $wxname = $params[1];
        $wxheader = $params[2];
        $sex = $params[3];
        $city = $params[4];
        $province = $params[5];
        $country = $params[6];

        $aid = PublicApi::uidToAid($uid);
        //传入信息为空
        if (empty($wxname) || empty($wxheader)){
            return PublicApi::retData(10003, 2);
        }
        $user_info = RedisUtil::redisHmget($uid, ['wxname', 'header', 'sex', 'city', 'province', 'country']);
        $old_wxname = $user_info['wxname'];
        $old_header = $user_info['header'];
        if (empty($old_wxname) || $old_wxname != $wxname || $old_header != $wxheader){
            //存入redis
            $data['wxname'] = $wxname;
            $data['header'] = $wxheader;
            RedisUtil::redisHmset($uid, $data);
            //存入mysql
            PublicApi::wxlog($aid, $wxheader, $wxname, time());
        }
        //性别，城市，省份，国家
        if (empty($user_info['city']) || empty($user_info['province']) || empty($user_info['country'])){
            $cdata['sex'] = $sex;
            $cdata['city'] = $city;
            $cdata['province'] = $province;
            $cdata['country'] = $country;
            RedisUtil::redisHmset($uid, $cdata);
        }
        return PublicApi::retData(10003, 1);
    }

    public static function getUserInfo(){
        $uid = $_SESSION['uid'];
        $aid = PublicApi::uidToAid($uid);
        $userInfo = RedisUtil::redisHmget($uid, ['gold', 'level']);
        return PublicApi::retData(10004, [$aid, $userInfo['gold'], $userInfo['level']]);
    }

    //10005, 点击加金币
    public static function addGold(){
        $uid = $_SESSION['uid'];
        $old_gold = RedisUtil::redisHget($uid, 'gold');
        //加10W
        $new_gold = $old_gold + 100000;
        RedisUtil::redisHset($uid, 'gold', $new_gold);
        return PublicApi::retData(10005, [1, $new_gold]);
    }


//    /**选择种族 职业
//     * @param $params
//     * @return string
//     */
//    public static function setPlayerInfo($params){
//        $uid = $_SESSION['uid'];
//        //验证参数
//        if (empty($params[1]) || empty($params[2]) || empty($params[3]) || !is_numeric($params[1]) || !is_numeric($params[2])
//            || !is_numeric($params[3])){
//            return PublicApi::retData(101);
//        }
//
//        RedisUtil::redisHmset($uid, ['race' => $params[1], 'job' => $params[2], 'sex' => $params[3]]);
//        return PublicApi::retData($params[0], 0);
//    }
//
//    public static function getPlayerEquip(){
//        $uid = $_SESSION['uid'];
//        //装备
//        $equip_infos = RedisUtil::redisHvals($uid . '_equip');
//        $take_equips = RedisUtil::redisHvals($uid . '_take_equip');
//        $resultData = [];
//        for ($i = 0; $i < count($equip_infos); $i++){
//            $equip_data = $equip_infos[$i];
//            if (!empty($take_equips) && in_array($equip_data[0], $take_equips)){
//                $equip_data[6] = 1;
//            }else{
//                $equip_data[6] = 0;
//            }
//            $equip_data[6] =
//            $resultData[] = $equip_data;
//        }
//
//        return PublicApi::retData(10003, $resultData);
//    }
//
//    /**获取玩家道具
//     * @return string
//     */
//    public static function getPlayerItems(){
//        $uid = $_SESSION['uid'];
//        $items = [];
//        for($i = 1; $i <= 13; $i++){
//            $num = RedisUtil::redisHget($uid . '_items', $i);
//            if (empty($num)){
//                $items[] = 0;
//            }else{
//                $items[] = $num;
//            }
//        }
//
//        return PublicApi::retData("10007", $items);
//    }
//
//    /**出售道具
//     * @param $params
//     * @return string
//     */
//    public static function sellItems($params){
//        $uid = $_SESSION['uid'];
//        if (empty($params[1])|| !is_numeric($params[1])){
//            return PublicApi::retData(101);
//        }
//
//        //出售
//        $nums = RedisUtil::redisHget($uid . '_items', $params[1]);
//        if (empty($nums)){
//            return PublicApi::retData($params[0], 1);
//        }
//
//        var_dump('出售道具' . $params[1]);
//        $item_temps = Item::init;
//        $item_temp = $item_temps[$params[1]];
//        if ($item_temp['sold'] > 0){
//            PublicApi::updatePlayerAttr($uid, 'gold', $item_temp['sold'], 'add');
//        }
//        RedisUtil::redisHset($uid . '_items', $params[1], $nums - 1);
//
//        return PublicApi::retData($params[0], [0, $params[1]]);
//    }
//
//    /**使用道具
//     * @param $params
//     * @return string
//     */
//    public static function useItem($params){
//        $uid = $_SESSION['uid'];
//        if (empty($params[1])|| !is_numeric($params[1])){
//            return PublicApi::retData(101);
//        }
//
//        $nums = RedisUtil::redisHget($uid . '_items', $params[1]);
//        if (empty($nums)){
//            return PublicApi::retData($params[0], 1);
//        }
//
//        $item_temps = Item::init;
//        $item_temp = $item_temps[$params[1]];
//        //扣除一个道具
//        RedisUtil::redisHset($uid . '_items', $params[1], $nums - 1);
//        //道具buff可用场数
//        if($item_temp['last'] > 0){
//            RedisUtil::redisHset($uid, 'use_item', $params[1]);
//            RedisUtil::redisHset($uid, 'item_last', $item_temp['last']);
//        }else{
//            RedisUtil::redisHset($uid, 'use_item', 0);
//            RedisUtil::redisHset($uid, 'item_last', 0);
//        }
//
//        return PublicApi::retData($params[0], [0, $params[1]]);
//    }
//
//
//    /** 添加一个装备
//     * @param $uid
//     * @param $equip_info
//     * @return array
//     */
//    public static function addEquip($uid, $equip_info){
//        $id = RedisUtil::redisIncr('com_equipid');
//        $equip = [$id, $equip_info[0],$equip_info[1],$equip_info[2],$equip_info[3],$equip_info[4]];
//        RedisUtil::redisHset($uid . '_equip', $id, [$id, $equip_info[0],$equip_info[1],$equip_info[2],$equip_info[3],$equip_info[4]]);
//        return $equip;
//    }
//
//    public static function calcLevel($uid){
//        //升级所需的经验值=100+50*（当前等级-1）^3
//        $level = RedisUtil::redisHget($uid, 'level');
//        $exp = RedisUtil::redisHget($uid, 'exp');
//        $need_exp = 100 + 50 * pow(($level - 1), 3);
//        //升级
//        if($need_exp <= $exp){
//             RedisUtil::redisHset($uid, 'level', $level + 1);
//             PlayerApi::calcLevel($uid);
//        }
//    }
}