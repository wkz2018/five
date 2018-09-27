<?php
/**
 * This file is part of workerman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link http://www.workerman.net/
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */

/**
 * 用于检测业务代码死循环或者长时间阻塞等问题
 * 如果发现业务卡死，可以将下面declare打开（去掉//注释），并执行php start.php reload
 * 然后观察一段时间workerman.log看是否有process_timeout异常
 */
//declare(ticks=1);

use \GatewayWorker\Lib\Gateway;
use Common\RedisUtil;
use Api\UserApi;
use Api\PlayerApi;
use Api\MatchInfoApi;
use Api\PublicApi;
use Api\RoomApi;
use Api\LeagueApi;
use Api\BettingApi;
use Workerman\MySQL\Connection;


/**
 * 主逻辑
 * 主要是处理 onConnect onMessage onClose 三个方法
 * onConnect 和 onClose 如果不需要可以不用实现并删除
 */
class Events
{
    public static function onWorkerStart($businessWorker){
        if($businessWorker->id == 1){
        }
        global $db;
        $db = new Connection('172.16.207.123', '3306', 'zyhy', 'Xyz4768@', 'five');
    }
    /**
     * 当客户端连接时触发
     * 如果业务不需此回调可以删除onConnect
     * 
     * @param int $client_id 连接id
     */
    public static function onConnect($client_id) {
        var_dump($client_id . 'connect');
        // 向当前client_id发送数据 
        //Gateway::sendToClient($client_id, "Hello $client_id\n");
        // 向所有人发送
        //Gateway::sendToAll("$client_id login\n");
    }
    
   /**
    * 当客户端发来消息时触发
    * @param int $client_id 连接id
    * @param mixed $message 具体消息
    */
   public static function onMessage($client_id, $message) {
       var_dump($client_id . '-  '.$message);
       $msg = json_decode($message, true);
       //判定接口号是不是有效
       if (empty($msg[0]) || strlen($msg[0]) < 2) {
           return;
       }
       $cmd = substr($msg[0], 0, 2);
       switch ($cmd) {
           case 60: // 登录注册
               $backdata = UserApi::init($msg, $client_id);
               break;
           case 10:
               $backdata = PlayerApi::init($msg, $client_id);
               break;
           case 20://比赛信息
               $backdata = MatchInfoApi::init($msg, $client_id);
               break;
           case 30: //房间
               $backdata = RoomApi::init($msg, $client_id);
               break;
           case 40: //投注
               $backdata = BettingApi::init($msg, $client_id);
               break;
//           case 50: //商城
//               $backdata = MallApi::init($msg, $client_id);
//               break;
           case 62:
//               LeagueApi::matchChance(15, 1, 1);
//               for ($i = 0;$i < 1000;$i++){
//                   //LeagueApi::matchChance(1, 2, 1);
//                   if ($i == 999){
//                       var_dump('跑完了');
//                   }
//                   LeagueApi::resetLeague();
//               }
               LeagueApi::resetLeague();
               break;
           default:
               return;
       }


       if(!empty($backdata)){
           Gateway::sendToClient($client_id, $backdata);
       }


   }

   /**
    * 当用户断开连接时触发
    * @param int $client_id 连接id
    */
   public static function onClose($client_id) {
       // 向所有人发送 
       //GateWay::sendToAll("$client_id logout");
       $uid = $_SESSION['uid'];
       var_dump('断线' . $uid);
       if (!empty($uid)){
           // 下线退出房间

           //下线时统计时长
           $add_time = time() - RedisUtil::redisHget($uid, 'etime');
           $online_time = RedisUtil::redisHget('com_statistics_alllogin_all', $uid);
           if (empty($online_time)){
               $online_time = $add_time;
           }else{
               $online_time += $online_time;
           }
           RedisUtil::redisHset('com_statistics_alllogin_all', $uid, $online_time);
           //下线将room_gold加入gold
           $user_info = RedisUtil::redisHmget($uid, ['gold', 'room_gold', 'roomid']);
           $gold = $user_info['gold'];
           $room_gold = $user_info['room_gold'];
           if ($room_gold > 0){
               $new_gold = $gold + $room_gold;
               $bData['gold'] = $new_gold;
               $bData['room_gold'] = 0;
               RedisUtil::redisHmset($uid, $bData);
               //下线房间金币log
               $roomid = $user_info['roomid'];
               RedisUtil::redisHset($uid, 'gold_log_' . $roomid, $room_gold);
           }

           //$aid = PublicApi::uidToAid($uid);
           //下线删除房间内人
           //RedisUtil::redisHdel('high_room', $aid);
       }
   }
}
