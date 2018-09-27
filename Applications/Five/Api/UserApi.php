<?php
/**
 * Created by PhpStorm.
 * User: song
 * Date: 2018/2/9
 * Time: 17:53
 */

namespace Api;


use Common\RedisUtil;
use GatewayWorker\Lib\Gateway;
use Temp\BasicGear;
use Workerman\Lib\Timer;

class UserApi
{
    public static function init($message, $client_id)
    {
        switch ($message[0]) {
            case 60002:
                return self::wifi_login($message, $client_id);
            case 60003:
                return self::quick_login($message, $client_id);
            case 60004:
                return self::notice();
            case 60005:
                return self::wxLogin($message, $client_id);
            default:
                return 0;
        }
    }

    public static function wxLogin($message, $client_id){
        if (empty($message[1])) {
            //参数异常
            return PublicApi::retData(101);
        }
        //
        $url = 'https://api.weixin.qq.com/sns/jscode2session?appid=wxe7c0e09318ecc91a&secret=719afb12a954643c52c6d8bcecaac343&js_code='
        .$message[1].'&grant_type=authorization_code';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);//绕过ssl验证
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $ret = curl_exec($ch);

//        var_dump('打印openid~~~~~~~~~~~');
//        var_dump($ret);

        //开关
        $hexie = RedisUtil::redisHget('sys_info', 'off');
        if(empty($hexie)){
            $hexie = 0;
        }
//        var_dump('hexie', $hexie);
        $r = json_decode($ret, true);
        if (isset($r['openid'])){
            $uid = self::login($r['openid'], $client_id);
            $aid = PublicApi::uidToAid($uid);

            $user_name = RedisUtil::redisHget($uid, 'wxname');
            if (empty($user_name)){
                $user_name = RedisUtil::redisHget($uid, 'name');
            }
            return PublicApi::retData(60003, [0, $aid, $user_name, $r['openid'], $hexie]);
        }else{
            return PublicApi::retData(101);
        }

    }

    public static function notice(){
        $notices = RedisUtil::redisHvals('com_notice');
        //$notices = ["官方QQ群：<color=#ffff00>609869272</color>小白战纪封测，欢迎加QQ群反馈BUG建议，谢谢您的参与"];

        $hexie = RedisUtil::redisHget('sys_info', 'off');
        if(empty($hexie)){
            $hexie = 0;
        }
        if ($hexie){
            return PublicApi::retData(60004, []);
        }else{
            return PublicApi::retData(60004, $notices);
        }

    }

    public static function wifi_login($message, $client_id)
    {
        if (empty($message[1])) {
            //参数异常
            return PublicApi::retData(101);
        }
        $checkToken = self::vertfyWifi($message[1]);
        var_dump($checkToken);
        if($checkToken['code'] != '0000'){
            return PublicApi::retData(60003, 2);
        }
        $username = $checkToken['data']['open_id'];

        $uid = self::login($username, $client_id);
        $race = RedisUtil::redisHget($uid, 'race');

        return PublicApi::retData(60003, [empty($race) ? 0 : 1, $username]);
    }

    /**登录
     * @param $username
     * @param $client_id
     * @return string
     */
    public static function login($username, $client_id){
        $aid_info = RedisUtil::redisHget('com_quick_login', $username);
        //没有账号
        if (empty($aid_info)) {
            //先插入
            $mkaid = RedisUtil::redisIncr('com_aid_incr'); //自增aid
            //写入
            RedisUtil::redisHset('com_quick_login', $username, ['aid' => $mkaid, 'ctime' => time()]);
            Gateway::updateSession($client_id, array('aid' => $mkaid, 'quicklogin' => $username));
            $aid = $mkaid;
        } else {
            $aid = $aid_info['aid'];
        }
        $uid = PublicApi::aidToUid($aid);

        //投注金额结算（已经跨赛季）
//        $bet_reward = RedisUtil::redisHget($uid, 'bet_reward');
//        if ($bet_reward > 0){
//            $old_gold = RedisUtil::redisHget($uid, 'gold');
//            $new_gold = $old_gold + $bet_reward;
//            RedisUtil::redisHset($uid, 'gold', $new_gold);
//            //删除bet_reward
//            RedisUtil::redisHdel($uid, 'bet_reward');
//        }


        //绑定账号处理
        $bindid = RedisUtil::redisHget('sys_bind', $aid);
        if (!empty($bindid)){
            $aid = $bindid;
            $uid = PublicApi::aidToUid($aid);
        }

        //登录后结算投注
        if (RedisUtil::redisHexists($uid, 'betting')){
            $bet_counts = RedisUtil::redisHget($uid, 'betting');
            foreach ($bet_counts as $bet_count){
                //根据轮数查看投注是否已经结算
                $betting_info = RedisUtil::redisHget('guess_' . $bet_count, $aid);
                if ($betting_info[0] == 0){//此轮未结算
                    //根据时间判断现在轮数
                    $now_count = PublicApi::getCountByTime(time());
                    //此轮比赛已经过时间
                    $zero_time = strtotime(date('Y-m-d',time()));
                    $utime = time() - $zero_time;//从0点到现在经过了多少秒
                    $after_time = $utime % (5 * 60); //此场比赛已经用时
                    if ($now_count > $bet_count){//未结算，且那一轮比赛已经过了，需要结算
                        $reward = PublicApi::getRewardByCountBet($bet_count, $betting_info, $aid);
                        //改变投注状态，第一个元素变为1
                        $betting_info[0] = 1;
                        //金币获得log
                        if ($reward > 0){
                            PublicApi::goldGetLog($aid, $bet_count, $reward, 1, time());
                        }

                    }elseif ($bet_count == $now_count && $after_time >= 180){//未结算，且当前轮数等于投注轮数，且此轮比赛经过了3分钟
                        $reward = PublicApi::getRewardByCountBet($bet_count, $betting_info, $aid);
                        //改变投注状态，第一个元素变为1
                        $betting_info[0] = 1;
                        //金币获得log
                        if ($reward > 0){
                            PublicApi::goldGetLog($aid, $bet_count, $reward, 1, time());
                        }

                    }else{
                        $reward = 0;
                    }
                    $gold = RedisUtil::redisHget($uid, 'gold');
                    $new_gold = $gold + $reward;
                    RedisUtil::redisHset($uid, 'gold', $new_gold);
                }
                RedisUtil::redisHset('guess_' . $bet_count, $aid, $betting_info);
            }
        }




        //更新session
        Gateway::updateSession($client_id, array('uid' => $uid, 'aid' => $aid));
        //绑定
        Gateway::bindUid($client_id, $uid);
        //初始化数据
        self::initUid($uid, $_SESSION['aid'], $username);


        return $uid;
    }

    /**快速登录
     * @param $message
     * @param $client_id
     * @return string
     */
    public static function quick_login($message, $client_id)
    {
        if (empty($message[1])) {
            //参数异常
            return PublicApi::retData(101);
        }

        $username = $message[1];
        $uid = self::login($username, $client_id);
        $aid = PublicApi::uidToAid($uid);




        //封号判断处理
//        if (RedisUtil::redisHexists($uid, 'isban')){
//            $ban_type = RedisUtil::redisHget($uid, 'isban');
//            if ($ban_type == 1){
//                return PublicApi::retData(60003, 4);
//            }
//        }
//        $race = RedisUtil::redisHget($uid, 'race');
        $user_name = RedisUtil::redisHget($uid, 'wxname');
        if (empty($user_name)){
            $user_name = RedisUtil::redisHget($uid, 'name');
        }

        return PublicApi::retData(60003, [0, $_SESSION['aid'], $user_name]);
    }

    /**初始化玩家数据
     * @param $uid
     * @param $aid
     */
    public static function initUid($uid, $aid, $oid = "")
    {
        if (!RedisUtil::redisExists($uid)) {
            $name = chr(rand(97, 122)) . $aid;

            $data['id'] = $aid;
            $data['name'] = $name;
            $data['sex'] = 1;
            $data['header'] = '';
            $data['level'] = 1;
            $data['exp'] = 0;
            $data['gold'] = 1000000;  //金币
            $data['room_gold'] = 0;
            $data['oid'] = $oid;
            $data['ctime'] = time();
            $data['etime'] = time();
            RedisUtil::redisHmset($uid, $data);

            RedisUtil::redisHset('com_aids', $aid, time());
            //存入
            RedisUtil::redisHset('com_usernames', $name, $aid);
            //统计信息
            PublicApi::addDayStatistics('newreg');
        } else {
            RedisUtil::redisHset($uid, 'etime', time());
            $ctime = RedisUtil::redisHget($uid, 'ctime');
            if (PublicApi::getdiffday(time(), $ctime) > 0){
                //老登录
                RedisUtil::redisHset('com_statistics_ologin_all', $aid, time());
            }
        }

        //更新体力
//        self::updateStamina($uid);
//        self::userResetData($uid);

        //统计
        if(!RedisUtil::redisHexists("com_statistics_alllogin_all", $uid)){
            RedisUtil::redisHset("com_statistics_alllogin_all", $uid, 0);
        }
    }

    //玩家更新数据
    public static function userResetData($uid){
        $now_day = (int)date('Ymd', time()); //获取当天
        $reset_day = RedisUtil::redisHget($uid, 'reset_day');
        if($now_day != $reset_day){
            RedisUtil::redisHset($uid, 'share_stamina', 0);

            //更新
            RedisUtil::redisHset($uid, 'reset_day', $now_day);
        }
    }

    /**更新体力
     * @param $uid
     */
    public static function updateStamina($uid, $is_next = 1)
    {
        $time = time();
        $stamina = RedisUtil::redisHget($uid, 'stamina');
        if ($stamina < 400) {
            $stamina_time = RedisUtil::redisHget($uid, 'stamina_time');
            if ($time - $stamina_time >= 300) {
                $add_stamina = floor(($time - $stamina_time) / 300) * 3;
                $left_time = floor(($time - $stamina_time) % 300);

                $stamina = min(400, $stamina + $add_stamina);
                RedisUtil::redisHset($uid, 'stamina', $stamina);
                if ($stamina == 400) {
                    RedisUtil::redisHset($uid, 'stamina_time', $time);
                } else {
                    RedisUtil::redisHset($uid, 'stamina_time', $time - $left_time);
                }
            }

        } else {
            RedisUtil::redisHset($uid, 'stamina_time', $time);
        }

        //推送体力
        Gateway::sendToUid($uid, PublicApi::retData(10001, $stamina));

        //五秒钟之后更新体力
        if (Gateway::isUidOnline($uid) && $is_next == 1) {
            Timer::add(300, array('\Api\UserApi', 'updateStamina'), array($uid), false);
        }

    }

    public static function vertfyWifi($token)
    {

        $url = 'http://act1.lianwifi.com/h5/user/get_info';
        $param['token'] = $token;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $param);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $ret = curl_exec($ch);
        $r = json_decode($ret, true);

        return $r;

    }

}