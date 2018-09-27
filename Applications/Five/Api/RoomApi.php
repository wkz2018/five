<?php
/**
 * Created by PhpStorm.
 * User: WARP
 * Date: 2018/7/16
 * Time: 15:17
 */

namespace Api;

use Common\RedisUtil;
use GatewayWorker\Lib\Gateway;
use Temp\TempRate;
use Temp\TempBet;


class RoomApi
{
    public static function init($message, $client_id){
        //session丢失
        if (empty($_SESSION['uid'])){
            return PublicApi::retData(100);
        }

        switch ($message[0]){
            case 30000://进入房间
                return self::joinRoom($message, $client_id);
            case 30001://房间信息
                return self::roomInfo($message);
            case 30003://发送房间消息
                return self::sendMsg($message);
            case 30005://进入高频房间
                return self::joinHighRoom($message, $client_id);
            case 30006://退出房间
                return self::quitRoom($message, $client_id);
            default:
                return 0;
        }
    }

    //进入房间
    public static function joinRoom($params, $client_id){
        $uid = $_SESSION['uid'];
        $cmd = $params[0];
        $roomid = $params[1];
//        var_dump('进入房间id' . $roomid);

        $room_temp = TempBet::init;
        if (empty($roomid)){
            return PublicApi::retData(101);
        }

        //无效房间
        if (!RedisUtil::redisExists('chat_room' . $roomid)){
            return PublicApi::retData($cmd, 1);
        }
        //获取玩家货币
        $self_gold = RedisUtil::redisHget($uid, 'gold');
        //判断是否有权限进入
//        if ($roomid == 1){
//            if ($self_gold < 50000){
//                return PublicApi::retData($cmd, 2);
//            }elseif ($self_gold > 2000000){
//                return PublicApi::retData($cmd, 3);
//            }
//        }elseif ($roomid == 2){
//            if ($self_gold < 150000){
//                return PublicApi::retData($cmd, 2);
//            }elseif ($self_gold > 4000000){
//                return PublicApi::retData($cmd, 3);
//            }
//        }elseif ($roomid == 3){
//            if ($self_gold < 400000){
//                return PublicApi::retData($cmd, 2);
//            }elseif ($self_gold > 10000000){
//                return PublicApi::retData($cmd, 3);
//            }
//        }elseif ($roomid == 4 && $self_gold < 1000000){
//            return PublicApi::retData($cmd, 2);
//        }
        //最新权限
//        $room_info = $room_temp[$roomid];
//        if ($roomid != 4){
//            if ($self_gold < $room_info['allow']){
//                return PublicApi::retData($cmd, 2);
//            }elseif ($self_gold > $room_info['ban']){
//                return PublicApi::retData($cmd, 3);
//            }
//        }else{
//            if ($self_gold < $room_info['allow']){
//                return PublicApi::retData($cmd, 2);
//            }
//        }




        // 最近加入房间
        RedisUtil::redisHset($uid, 'roomid', $roomid);
        $aid = PublicApi::uidToAid($uid);
        //加入房间
        $joinTime = time();
        RedisUtil::redisHset('chat_users_room' . $roomid, $aid, $joinTime);

        // 加入房间组
        Gateway::joinGroup($client_id, 'room' . $roomid);

        //加入者id,name
        $join_name = RedisUtil::redisHget($uid, 'wxname');
        if (empty($join_name)){
            $join_name = RedisUtil::redisHget($uid, 'name');
        }
        $rData = PublicApi::retData(30002, [$aid, $join_name]);
        //向房间里所有人发送进入 房间
        Gateway::sendToGroup('room' . $roomid, $rData);

        //展示历史信息
        $msgInfos = RedisUtil::redisLrange('chat_msg_room' . $roomid);
        $history_msgs = [];
        foreach ($msgInfos as $msgInfo){
            $msg = $msgInfo[0];
            $send_id = $msgInfo[1];
            $send_uid = PublicApi::aidToUid($send_id);
            $send_userinfo = RedisUtil::redisHmget($send_uid, ['wxname', 'name', 'header']);
            $msg_name = $send_userinfo['wxname'];
            if (empty($msg_name)){
                $msg_name = $send_userinfo['name'];
            }
            $history_msgs[] = [$send_id, $msg_name, $send_userinfo['header'], $msg];
        }
        Gateway::sendToClient($client_id, PublicApi::retData(30004, $history_msgs));

        //房间名
        $roomName = RedisUtil::redisHget('chat_room' . $roomid, 'name');
        //获取房间用户人数
        $user_nums = RedisUtil::redisHlen('chat_users_room' . $roomid);
        //距离下场比赛时间
        //凌晨时间戳
        $zero_time = strtotime(date('Y-m-d',time()));
        $utime = time() - $zero_time;//从0点到现在经过了多少秒
        if ($utime > 4 * 60 * 60 && $utime < 8 * 60 * 60){//在凌晨4点到早8点之间
            $next_time = 8 * 60 * 60 - $utime;
        }else{
            $after_time = $utime % (5 * 60); //此场比赛已经用时
            $next_time = 5 * 60 - $after_time; //距离下场比赛时间
        }
        //房间内最低投注

        $betting = $room_temp[$roomid]['betting'];
        //高频版本房间


        return PublicApi::retData($cmd, [0, $user_nums, $roomName, $self_gold, $next_time, $betting]);
    }

    //房间内用户信息
    public static function roomInfo($param){
        $roomid = $param[1];
        $uid = $_SESSION['uid'];
//        //获取所在房间id
//        $roomid = RedisUtil::redisHget($uid, 'roomid');
        //房间名
        $roomName = RedisUtil::redisHget('chat_room' . $roomid, 'name');
        //获取房间所有aid
        $room_aids = RedisUtil::redisHkeys('chat_users_room' . $roomid);

        $resultData = [];
        foreach ($room_aids as $room_aid){
            //获取uid
            $room_uid = PublicApi::aidToUid($room_aid);
            //根据uid获取信息
            $peopleInfo = RedisUtil::redisHmget($room_uid , ['id', 'wxname', 'name', 'header']);
            $m_name = $peopleInfo['wxname'];
            if (empty($m_name)){
                $m_name = $peopleInfo['name'];
            }
            $rData = [$peopleInfo['id'], $m_name, $peopleInfo['header']];
            $resultData[] = $rData;
        }
        $backData = [$roomName, $resultData];
        return PublicApi::retData(30001, $backData);
    }


    //发送房间消息
    public static function sendMsg($params){
        $uid = $_SESSION['uid'];
        $cmd = $params[0];
        $msg = $params[1];
        //获取id
        $aid = PublicApi::uidToAid($uid);
        //获取name
        $name = RedisUtil::redisHget($uid, 'wxname');
        if (empty($name)){
            $name = RedisUtil::redisHget($uid, 'name');
        }
        //获取房间
        $roomid = RedisUtil::redisHget($uid, 'roomid');
        RedisUtil::redisRpush('chat_msg_room' . $roomid, [$msg, $aid]);
        //存20条
        if (RedisUtil::redisLlen('chat_msg_room' . $roomid) >= 21){
            RedisUtil::redisLpop('chat_msg_room' . $roomid);
        }

        $rData = PublicApi::retData($cmd, [$aid, $name, $msg]);
        Gateway::sendToGroup('room' . $roomid, $rData);
        return;
    }


    //高频版本加入房间
    public static function joinHighRoom($params, $client_id){
        $uid = $_SESSION['uid'];
        $roomid = $params[1];
        $room_temp = TempBet::init;

        if (empty($roomid)){
            return PublicApi::retData(101);
        }

        //无效房间
        if (!RedisUtil::redisExists('chat_room' . $roomid)){
            return PublicApi::retData(30005, 1);
        }

        // 最近加入房间
        RedisUtil::redisHset($uid, 'roomid', $roomid);
        $aid = PublicApi::uidToAid($uid);
        //加入房间
        $joinTime = time();
        RedisUtil::redisHset('chat_users_room' . $roomid, $aid, $joinTime);

        // 加入房间组
        Gateway::joinGroup($client_id, 'room' . $roomid);
        $user_nums = Gateway::getClientCountByGroup('room' . $roomid);
        //房间名
        $roomName = RedisUtil::redisHget('chat_room' . $roomid, 'name');
        //房间人数
        //$user_nums = RedisUtil::redisHlen('chat_users_room' . $roomid);
        //获取玩家货币,等级
        $userInfo = RedisUtil::redisHmget($uid, ['gold', 'level', 'room_gold']);
        $self_gold = $userInfo['gold'];
        $level = $userInfo['level'];
        //如果room_gold里面有钱，先加到总钱里面
        $room_gold = $userInfo['room_gold'];
        $last_gold = $self_gold;
        if ($room_gold > 0){
            $last_gold = $self_gold + $room_gold;
            $bData['gold'] = $last_gold;
            $bData['room_gold'] = 0;
            RedisUtil::redisHmset($uid, $bData);
        }
        $betting = $room_temp[$roomid]['betting'];
        //历史金币
        $gold_log = RedisUtil::redisHget($uid, 'gold_log_' . $roomid);
        if (!empty($gold_log)){//存在历史金币
            $new_gold = $last_gold - $gold_log;
            $data['gold'] = $new_gold;
            $data['room_gold'] = $gold_log;
            RedisUtil::redisHmset($uid, $data);
            $carry_gold = $gold_log;
            //1本场第一次进入房间  2非第一次进
            $i = 2;
        }else{//不存在历史金币
            $i = 1;
            //携带金币
            $allow = $room_temp[$roomid]['allow'];
            $carry_gold = $allow * (1 + 0.01 * ($level - 1));
            if ($last_gold < $carry_gold){//金币不足
                return PublicApi::retData(30005, 2);
            }
            //取出钱，存到room_gold
            $now_gold = $last_gold - $carry_gold;
            $data['gold'] = $now_gold;
            $data['room_gold'] = $carry_gold;
            RedisUtil::redisHmset($uid, $data);
            RedisUtil::redisHset($uid, 'gold_log_' . $roomid, $carry_gold);
        }
        //加入房间,存，结算后删除
        RedisUtil::redisHset('high_room', $aid, time());

        return PublicApi::retData(30005, [0, $user_nums, $roomName, $carry_gold, $betting, $level, $i]);
    }


    public static function quitRoom($params, $client_id){
        $roomid = $params[1];
        $uid = $_SESSION['uid'];
        $aid = PublicApi::uidToAid($uid);
        if (empty($roomid)){
            return PublicApi::retData(101);
        }
        //无效房间
        if (!RedisUtil::redisExists('chat_room' . $roomid)){
            return PublicApi::retData(30006, 1);
        }

        //提前退出房间加金币
        $user_info = RedisUtil::redisHmget($uid, ['gold', 'room_gold']);
        $gold = $user_info['gold'];
        $room_gold = $user_info['room_gold'];
        if ($room_gold > 0){
            $new_gold = $gold + $room_gold;
            $bData['gold'] = $new_gold;
            $bData['room_gold'] = 0;
            RedisUtil::redisHmset($uid, $bData);
        }

        //改变房间金币Log
        RedisUtil::redisHset($uid, 'gold_log_' . $roomid, $room_gold);

        // 退出房间组
        Gateway::leaveGroup($client_id, 'room' . $roomid);

        RedisUtil::redisHdel('chat_users_room' . $roomid, $aid);
        return PublicApi::retData(30006, 0);
    }

}