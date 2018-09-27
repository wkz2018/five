<?php
/**
 * Created by PhpStorm.
 * User: WARP
 * Date: 2018/7/30
 * Time: 14:37
 */

namespace Api;

use Common\RedisUtil;
use Temp\TempBet;



class BettingApi
{
    public static function init($message, $client_id)
    {
        //session丢失
        if (empty($_SESSION['uid'])) {
            return PublicApi::retData(100);
        }

        switch ($message[0]) {
            case 40000://投注成功与否
                //return NewLeagueApi::resetNewLeague();
//                $message = [40003, 18888, 2];
//                return self::newbettingInfo($message);
                //return self::newBetAllRank();
                //return self::newBetAllResult();
                //return self::add();
                //return MatchInfoApi::highPlayerRank();
                //return MatchInfoApi::highTeamRank();
                //return LeagueApi::resetLeague();
                return self::bettingInfo($message);
            case 40001://竞猜结果
                return self::bettingResult();
            case 40002://本场比赛订单
                return self::bettingList($message);
            case 40003://高频模式下投注
                return self::newbettingInfo($message);
            case 40004://新模式投注结算
                return self::newBettingResult();
            case 40005://榜单
                return self::newBetAllResult();
            case 40006:
                return self::newBetAllRank();
            default:
                return 0;
        }
    }



    public  static function add(){
        for ($i = 1; $i <= 87; $i++){
            RedisUtil::redisHset('pt_u' . $i, 'room_gold', 0);
        }
        var_dump('ok');
    }



    /**竞猜投注
     * @param $params
     */
    public static function bettingInfo($params){
        $room_temps = TempBet::init;
        $uid = $_SESSION['uid'];
        //投注赔率id数组
        $betting_ids = $params[1];
        //倍数
        $multiple = $params[2];
        //投注个数
        $betting_num = count($betting_ids);

        //场次,投注的是下一场的
        $count = PublicApi::getOddsCountByTime(time()) + 1;

        //已封盘2（暂定下场比赛开始前30秒）
        //凌晨时间戳
        $zero_time = strtotime(date('Y-m-d',time()));
        $utime = time() - $zero_time;//从0点到现在经过了多少秒
        $after_time = $utime % (5 * 60); //此场比赛已经用时
        $next_time = 5 * 60 - $after_time; //距离下场比赛时间
        if ($next_time <= 5){
            //已封盘
            return PublicApi::retData(40000, 2);
        }
        if(empty($betting_ids) || $multiple == 0){//传过来的变量为空
            return PublicApi::retData(40000, 3);
        }

        //房间id
        $roomid = RedisUtil::redisHget($uid, 'roomid');
        //投注货币 = 倍数 * 最低投注 * 投注的项目个数
        $least_betting = $room_temps[$roomid]['betting'];
        $total_betting = $multiple * $least_betting * $betting_num;
        //货币是否充足
        $self_gold = RedisUtil::redisHget($uid, 'gold');

        if ($total_betting > $self_gold){//货币不足
            return PublicApi::retData(40000, 1);
        }else{//货币充足，投注成功
            //将竞猜投注存入redis,
            //此场比赛赔率集合
            $odds_infos = PublicApi::getOddsByCount($count);
            //取比赛球队，存mysql用
            $match_team = $odds_infos['id'];

            $aid = PublicApi::uidToAid($uid);
            //单个项目投注金额 = 最低投注 * 倍数
            $betting_gold = $least_betting * $multiple;
            //遍历传过来的投注项目数组
            foreach ($betting_ids as $betting_id){

                //此次投注内容所对应的赔率
                $odds = $odds_infos[$betting_id];
               // var_dump('此次投注' . $betting_id . '所对应的赔率' . $odds);
                //预计奖金 = 投注金额 * 赔率
                $reward = $betting_gold * $odds;
                //将投注信息存入redis
                if (RedisUtil::redisHexists('guess_' . $count, $aid)){//已经存在投注单,先取,合并,再存
                    $betting_infos = RedisUtil::redisHget('guess_' . $count, $aid);
                    //先去出单子第一个元素0
                    array_shift($betting_infos);
                    //遍历取出来的投注，判断是否有相同的,放入新的数组,第一个为0
                    $new_betting_infos = [];
                    $new_betting_infos[] = 0;
                    $old_betting_ids = [];
                    foreach ($betting_infos as $key => $betting_info){
                        //已存在的投注单的名称id  和 金币 和 预计将金
                        $old_betting_id = $betting_info[0];
                        $old_betting_gold = $betting_info[2];
                        $old_reward = $betting_info[3];
                        //新建名称集合，将已经存在的投注名称放入
                        $old_betting_ids[] = $old_betting_id;
                        //如果投注内容相同,合并
                        if ($betting_id == $old_betting_id){
                            //新的投注金币 = 已存在投注金币 + 现在投注的货币
                            $new_betting_gold = $old_betting_gold + $betting_gold;
                            $new_reward = $old_reward + $reward;
                            $new_betting_infos[] = [$betting_id, $odds, $new_betting_gold, $new_reward];
                        }else{//如果不相同，不变,在已经取得单后面加上新单
                            $new_betting_infos[] = $betting_info;
                        }

                    }
                    if (in_array($betting_id, $old_betting_ids)){//已投注里有
                        $betting_now_info = $new_betting_infos;
                    }else{//投注里面没有,在后面加入新的投注
                        $new_betting_infos[] = [$betting_id, $odds, $betting_gold, $reward];
                        $betting_now_info = $new_betting_infos;
                    }

                    //[赔率id名称，赔率，金额，预计奖金]
                    RedisUtil::redisHset('guess_' . $count, $aid, $betting_now_info);
                }else{//本场没有投注过,第一单投注
                    $betting_info = [];
                    //未投注，数组前面第一个元素为0
                    $betting_info[] = 0;
                    $betting_info[] = [$betting_id, $odds, $betting_gold, $reward];
                    RedisUtil::redisHset('guess_' . $count, $aid, $betting_info);
                }


                //$bet_infos = [[$betting_id, $odds, $betting_gold, $reward]];

                //存入mysql
                PublicApi::bettinglog($aid, $count, $match_team, $betting_id, $odds, $betting_gold, time());
            }

            //投注后货币 = 自己所有货币 - 总投注货币
            $self_gold_now = $self_gold - $total_betting;
            RedisUtil::redisHset($uid, 'gold', $self_gold_now);
            //存入uid
            if (RedisUtil::redisHexists($uid, 'betting')){//存在
                $betting_uid_info = RedisUtil::redisHget($uid, 'betting');
                if (!in_array($count, $betting_uid_info)){
                    $betting_uid_info[] = $count;
                }
            }else{//不存在
                $betting_uid_info = [];
                $betting_uid_info[] = $count;
            }
            //竞猜轮数存入redis里的uid中betting
            RedisUtil::redisHset($uid, 'betting', $betting_uid_info);
            //存入赛季所有投注玩家
            RedisUtil::redisHset('guess_users', $aid, time());

            return  PublicApi::retData(40000, [0, $self_gold_now]);
        }
    }


    /**竞猜结果
     * @param $params
     * @return string
     */
    public static function bettingResult(){
        //比赛场次
        $count = PublicApi::getCountByTime(time());
        $uid = $_SESSION['uid'];
        $aid = PublicApi::uidToAid($uid);

        //根据场次获取比赛结果
        $match_info = RedisUtil::redisHget('league_match', $count);
        $team_id1 = $match_info[0];
        $team_id2 = $match_info[1];
        $score1 = $match_info[2];
        $score2 = $match_info[3];
        $match_team = $team_id1 . 'vs' . $team_id2;

        if (!RedisUtil::redisHexists('guess_' . $count, $aid)){
            return PublicApi::retData(40001, [1, $team_id1, $team_id2]);
        }
        //根据场次获取竞猜信息
        $betting_infos = RedisUtil::redisHget('guess_' . $count, $aid);
        if ($betting_infos[0] == 0){//后面判断是否结算用
            $is_result = 0;
        }else{
            $is_result = 1;
        }
        //去除第一个元素0
        array_shift($betting_infos);

        //赔率数组
        $odds_infos = PublicApi::getOddsByCount($count);
        //让球数
        $concede_num = $odds_infos['concede'];

        //竞猜成功奖励货币
        $betting_suc_gold = 0;
        //竞猜成功总货币
        $all_suc_gold = 0;
        $rDdata = [];
        //修改redis竞猜后订单状态，前面加个1
        $betting_list = [];
        $betting_list[] = 1;
        //遍历竞猜信息
        foreach ($betting_infos as $betting_info){
            $betting_list[] = $betting_info;

            $betting_id = $betting_info[0];//投注赔率id名称
            $odds = $betting_info[1];
            $betting_gold = $betting_info[2];
            $reward = $betting_info[3];//预计奖励
            //竞猜成功与否写一个方法，传入竞猜ID，比分，让球，获得竞猜成功与否
            $betting_result = PublicApi::getResultByidAndScore($betting_id, $score1, $score2, $concede_num);

            if ($betting_result == 0){//竞猜成功
                $betting_suc_gold = $reward;
                $all_suc_gold += $reward;
                if ($is_result == 0){//未结算
                    PublicApi::orderList($aid, $count, $match_team, $betting_id, $odds, $betting_gold, $reward, time());
                }
            }else{
                $betting_suc_gold = 0;
                if ($is_result == 0){//未结算
                    PublicApi::orderList($aid, $count, $match_team, $betting_id, $odds, $betting_gold, 0, time());
                }
            }

            //竞猜成功0  竞猜失败1   获得货币
            $rDdata[] = [$betting_result, $betting_id, $odds, $betting_gold, $betting_suc_gold];
        }
        //总货币
        $self_gold = RedisUtil::redisHget($uid, 'gold');

        if ($is_result == 0){//未结算
            $self_gold_now = $self_gold + $all_suc_gold;
            //redis更新货币
            RedisUtil::redisHset($uid, 'gold', $self_gold_now);
            //修改订单状态，前面加个1
            RedisUtil::redisHset('guess_' . $count, $aid, $betting_list);
            //金币获得log
            if ($all_suc_gold > 0){
                PublicApi::goldGetLog($aid, $count, $all_suc_gold, 1, time());
            }
            return PublicApi::retData(40001, [0, $team_id1, $team_id2, $self_gold_now, $rDdata]);
        }else{//已经结算
            return PublicApi::retData(40001, [2, $team_id1, $team_id2, $self_gold, $rDdata]);
        }

    }


    /**投注列表(存在跨比赛场次的情况)
     * @param $params
     * @return string
     */
    public static function bettingList($params){

        //场次,投注的是下一场的
        $count = $params[1];

        $self_count = PublicApi::getOddsCountByTime(time()) + 1;
        $match_info = RedisUtil::redisHget('league_match', $count);
        //主客队id
        $team_id1 = $match_info[0];
        $team_id2 = $match_info[1];

        $uid = $_SESSION['uid'];
        $aid = PublicApi::uidToAid($uid);
        //竞猜信息
        if (RedisUtil::redisHexists('guess_' . $count, $aid)){
            $betting_info = RedisUtil::redisHget('guess_' . $count, $aid);
            //移除竞猜单第个元素0
            array_shift($betting_info);
            return PublicApi::retData(40002, [0, $team_id1, $team_id2, $betting_info]);
        }else{
            $betting_info = [];
            return PublicApi::retData(40002, [1, $team_id1, $team_id2, $betting_info]);
        }


//        var_dump($betting_info);
//        var_dump('本场投注订单');
//        var_dump($aid);

    }


    /*
     * 高频模式下投注
     */
    public static function newbettingInfo($params){
        $now_match = RedisUtil::redisHgetall('now_match');
        $match_info = $now_match['info'];
        $count = $match_info[0];
        $round = $match_info[1];
        //突破or射门
        $att_type = $match_info[2];
        $bet_gold = $params[1];
        //投注成功or失败
        $bet_type = $params[2];

//        $bet_gold = $params[1];
//        $bet_type = $params[2];
        $uid = $_SESSION['uid'];
        $aid = PublicApi::uidToAid($uid);
        //判断时间结束投注,已经封盘
        $time = $now_match['time'];
        $now_time = time();
        if (($now_time - $time) >= 10){
            //var_dump('时间不行');
            return PublicApi::retData(40003, 2);
        }
        //货币是否充足
        $self_info = RedisUtil::redisHmget($uid, ['room_gold', 'roomid']);
        $self_gold = $self_info['room_gold'];
        $room_id = $self_info['roomid'];
        //$self_gold = RedisUtil::redisHget($uid, 'room_gold');
        if ($bet_gold > $self_gold){
            return PublicApi::retData(40003, 1);
        }

        if (RedisUtil::redisHexists('guess_new_' . $count, $aid)){
            $bet_infos = RedisUtil::redisHget('guess_new_' . $count, $aid);
            if ($bet_infos[0] == 1){//已经结算
                //var_dump('先删除已经结算');
                RedisUtil::redisHdel('guess_new_' . $count, $aid);
                $bet_now_info = [];
                $bet_now_info[] = 0;
                $bet_now_info[] = $room_id;
                $odds = self::getoddsbyinfo($count, $round, $att_type, $bet_type);
                $bet_now_info[] = [$bet_type, $odds, $bet_gold, floor($bet_gold * $odds)];
                RedisUtil::redisHset('guess_new_' . $count, $aid, $bet_now_info);
            }else{
                //移除前两个元素（0/1，h1）
                array_shift($bet_infos);
                array_shift($bet_infos);

                $new_bet_info = [];
                $new_bet_info[] = 0;
                $new_bet_info[] = $room_id;
                $old_betting_ids = [];
                //遍历已经投注数组
                foreach ($bet_infos as $bet_info){
                    $old_betting_ids[] = $bet_info[0];
                    $odds = $bet_info[1];
                    //此次投注预计奖金
                    $reward = floor($bet_gold * $odds);
                    if ($bet_info[0] == $bet_type){//投注的内容以前投注过
                        $new_bet_gold = $bet_info[2] + $bet_gold;
                        $new_reward = $bet_info[3] + $reward;
                        $new_bet_info[] = [$bet_type, $odds, $new_bet_gold, $new_reward];
                    }else{
                        $new_bet_info[] = $bet_info;
                    }
                }
                if (in_array($bet_type, $old_betting_ids)){
                    $betting_now_info = $new_bet_info;
                }else{//投注里面没有,在后面加入新的投注
                    $odds = self::getoddsbyinfo($count, $round, $att_type, $bet_type);
                    $new_bet_info[] = [$bet_type, $odds, $bet_gold, floor($bet_gold * $odds)];
                    $betting_now_info = $new_bet_info;
                }
                RedisUtil::redisHset('guess_new_' . $count, $aid, $betting_now_info);
            }
        }else{//第一次投注
            $bet_now_info = [];
            $bet_now_info[] = 0;
            $bet_now_info[] = $room_id;
            $odds = self::getoddsbyinfo($count, $round, $att_type, $bet_type);
            $bet_now_info[] = [$bet_type, $odds, $bet_gold, floor($bet_gold * $odds)];
            RedisUtil::redisHset('guess_new_' . $count, $aid, $bet_now_info);
        }
        //PublicApi::highBettinglog($aid, $count, $round, $att_type, $bet_type, $odds, $bet_gold, time());
        //打印log
        $betting_id = $round . '&' . $att_type . '&' . $bet_type;
        $team_info = RedisUtil::redisHget('league_new_match', $count);
        $match_team = $team_info[0] . '&' . $team_info[1];
        PublicApi::bettinglog($aid, $count, $match_team, $betting_id, $odds, $bet_gold, time());
        //投注后货币 = 自己所有货币 - 投注货币
        $self_gold_now = $self_gold - $bet_gold;
        RedisUtil::redisHset($uid, 'room_gold', $self_gold_now);
        //已经投注,取总投注
        $bet_last_infos = RedisUtil::redisHget('guess_new_' . $count, $aid);
        //移除前两个元素
        array_shift($bet_last_infos);
        array_shift($bet_last_infos);
        $suc_bet = 0;
        $fail_bet = 0;
        foreach ($bet_last_infos as $bet_last_info){
            if ($bet_last_info[0] == 1){//成功
                $suc_bet = $bet_last_info[2];
            }elseif ($bet_last_info[0] == 2){//失败
                $fail_bet = $bet_last_info[2];
            }
        }
        //var_dump('投注成功');
        return PublicApi::retData(40003, [0, $self_gold_now, $suc_bet, $fail_bet]);
    }


    //高频模式 投注订单结账
    public static function bettingCheckOut(){
        $match_info = RedisUtil::redisHget('now_match', 'info');
        $count = $match_info[0];
        $round = $match_info[1];
        $att_type = $match_info[2];
        //此回合所有投注信息
        $betting_infos = RedisUtil::redisHgetall('guess_new_' . $count);
        //如果不为空
        if (!empty($betting_infos)){
            //比赛结果
            $match_result = RedisUtil::redisHget('new_match_result' . $count, $round);
            //遍历投注
            foreach ($betting_infos as $key => $betting_info) {
                $aid = $key;
                $uid = PublicApi::aidToUid($aid);


                //$betting_info = RedisUtil::redisHget('guess_new_' . $count, $aid);
                if ($betting_info[0] == 1){//已经结算
                    RedisUtil::redisHdel('guess_new_' . $count, $aid);
                }else{//未结算

                    if ($att_type == 1) {
                        //进攻形式是突破，结果是第一个
                        $report_result = $match_result[0];
                    } else {//上一回合是射门
                        //结果只有一种情况即射门
                        if (count($match_result) == 1) {
                            $report_result = $match_result[0];
                        } else {//结果包括突破射门，取射门
                            $report_result = $match_result[1];
                        }
                    }

                    //获取投注房间
                    $bet_room = $betting_info[1];
                    //var_dump('投注房间' . $bet_room);
                    //移除第一个元素0和h1
                    array_shift($betting_info);
                    array_shift($betting_info);
                    $new_bet_info = [];
                    $new_bet_info[] = 1;
                    $reward = 0;
                    $all_bet = 0;
                    foreach ($betting_info as $bet_info) {
                        $bet_type = $bet_info[0];
                        $odds = $bet_info[1];
                        $bet_gold = $bet_info[2];
                        $all_bet += $bet_gold;
                        if ($bet_type == $report_result) {//投注类型和战报结果 一致
                            //经验处理 1是系数
                            self::expUp($uid, 1);
                            //奖金
                            $reward = $bet_info[3];
                            $new_bet_info[] = [0, $bet_type, $odds, $bet_gold, $reward];
                            PublicApi::highOrderList($aid, $count, $round, $att_type, $bet_type, $bet_gold, $odds, $reward, time());
                        } else {//投注没有中奖
                            self::expUp($uid, 0.7);
                            $new_bet_info[] = [1, $bet_type, $odds, $bet_gold, 0];
                            PublicApi::highOrderList($aid, $count, $round, $att_type, $bet_type, $bet_gold, $odds,0, time());
                        }
                    }
                    //盈亏 = 奖金 - 总投注
                    $profit_loss = $reward - $all_bet;

                    //加入到此场比赛盈亏,总奖金
                    $all_reward = RedisUtil::redisHget('all_new_guess_' . $bet_room, 'id' . $aid);
                    if (empty($all_reward)){
                        RedisUtil::redisHset('all_new_guess_' . $bet_room, 'id' . $aid, [$profit_loss, $reward]);
                    }else{
                        $new_profit = $all_reward[0] + $profit_loss;
                        $new_reward = $all_reward[1] + $reward;
                        RedisUtil::redisHset('all_new_guess_' . $bet_room, 'id' . $aid, [$new_profit, $new_reward]);
                    }

                    if ($reward > 0){
                        $self_info = RedisUtil::redisHmget($uid, ['room_gold', 'roomid', 'gold']);
                        //房间Id
                        $room_id = $self_info['roomid'];
                        //添加奖金
                        if ($bet_room == $room_id){//投注房间与现在所在房间一致，加入房间金币
                            $self_gold = $self_info['room_gold'];
                            $new_gold = $self_gold + $reward;
                            RedisUtil::redisHset($uid, 'room_gold', $new_gold);
                        }else {//不一致，金币加入总金币
                            $self_gold = $self_info['gold'];
                            $new_gold = $self_gold + $reward;
                            RedisUtil::redisHset($uid, 'gold', $new_gold);
                        }
                        //奖金获得log
                        PublicApi::goldGetLog($aid, $count, $reward, 2, time());
                    }
                    //改变投注状态
                    RedisUtil::redisHset('guess_new_' . $count, $aid, $new_bet_info);
                }
            }
        }

    }



    //高频模式投注结算 40004
    public static function newBettingResult(){
        $match_info = RedisUtil::redisHget('now_match', 'info');
        $count = $match_info[0];

        $uid = $_SESSION['uid'];
        $aid = PublicApi::uidToAid($uid);

        //投注信息
        $bet_info = RedisUtil::redisHget('guess_new_' . $count, $aid);
        //不存在订单
        if (!RedisUtil::redisHexists('guess_new_' . $count, $aid)){
            return PublicApi::retData(40004, 1);
        }elseif ($bet_info[0] != 1){//没有结算
            return PublicApi::retData(40004, 1);
        }
        //移除第一个元素
        array_shift($bet_info);

        $match_team = RedisUtil::redisHget('league_new_match', $count);
        $team1 = $match_team[0];
        $team2 = $match_team[1];

        //改变投注状态
        RedisUtil::redisHdel('guess_new_' . $count, $aid);

        $self_info = RedisUtil::redisHmget($uid, ['room_gold', 'gold_info']);
        $room_gold = $self_info['room_gold'];
        if ($room_gold == 0){//房间内金币为0
            $gold_info = $self_info['gold_info'];
            $report_result = PublicApi::judgeLastReport();
            if ($report_result[0] == 1){//最后一轮
                $self_gold = $gold_info;
            }else{
                $self_gold = $room_gold;
            }
        }else{
            $self_gold = $room_gold;
        }
        return PublicApi::retData(40004, [0, $team1, $team2, $self_gold, $bet_info]);
    }


    //40005，单场比赛结算,榜单
    public static function newBetAllResult(){

        $uid = $_SESSION['uid'];
        //$uid = 'pt_u53';
        $aid = PublicApi::uidToAid($uid);
        //回合信息
//        $match_info = RedisUtil::redisHget('now_match', 'info');
//        $count = $match_info[0];
        $user_info = RedisUtil::redisHmget($uid, ['name', 'wxname', 'header', 'top_reward', 'roomid']);
        $room_id = $user_info['roomid'];

        $all_rewards = RedisUtil::redisHgetall('all_new_guess_' . $room_id);
        if (!empty($all_rewards)){
            if (!empty($all_rewards['id' . $aid])){
                $all_data = $all_rewards['id' . $aid];
                $all_profit = $all_data[0];
                $all_reward = $all_data[1];
            }
        }
//        if (empty($all_rewards)){//如果没有人投注，超过0的玩家
//            $rate = 0;
//        }


        if (!empty($all_rewards)){
            //此场比赛盈亏
            if (empty($all_reward)){
                $all_reward = 0;
            }
        }else{
            $all_reward = 0;
        }

        //历史最高奖金

        $name = $user_info['wxname'];
        if (empty($user_info['wxname'])){
            $name = $user_info['name'];
        }
        $header = $user_info['header'];
        $top_reward = $user_info['top_reward'];
        //$top_reward = RedisUtil::redisHget($uid, 'top_reward');
        if (empty($top_reward)){
            $top_reward = 0;
        }
        //如果进的奖金比历史纪录高，重新存入。全部玩家排行
        if ($all_reward > $top_reward){
            $new_top = $all_reward;
            RedisUtil::redisHset($uid, 'top_reward', $new_top);
            //去比较全部玩家排行榜,写一个方法，传入id，name,历史最高奖金,更新排行榜
            self::guessRank($aid, $name, $header, $new_top);
        }

        //判断自己排名
        if(!empty($all_rewards)){//投注不为空
            //根据盈亏排序,降序
            array_multisort(array_column($all_rewards, 0), SORT_DESC, $all_rewards);

            if (empty($all_rewards['id' . $aid])){
                $rank = -1;
            }else{
                //取出所有键名，即id . aid
                $rank_aids = array_keys($all_rewards);
                //反转键名和aid
                $b = array_flip($rank_aids);
                $key = $b['id' . $aid];
                //排名
                $rank = $key + 1;
            }
            //截取前5个玩家
            if (count($all_rewards) > 5){
                $new_arrays = array_slice($all_rewards, 0, 5, true);
            }else{
                $new_arrays = $all_rewards;
            }
            //遍历取出玩家信息
            $cData = [];
            foreach ($new_arrays as $key => $new_array){
                $aid = substr($key, 2);
                $userInfo = RedisUtil::redisHmget('pt_u' . $aid, ['wxname', 'name', 'header']);
                $name = $userInfo['wxname'];
                if (empty($name)){
                    $name = $userInfo['name'];
                }
                $cData[] = [$name, $userInfo['header'], $new_array[0]];
            }
        }else{
            $rank = -1;
            $cData = [];
        }
        if (empty($all_profit)){
            $all_profit = 0;
        }
        return PublicApi::retData(40005, [$rank, $all_profit, $cData]);
    }

    //全部玩家竞猜排行榜
    public static function newBetAllRank(){
        $uid = $_SESSION['uid'];
        //自己的最高奖金
        $top_reward = RedisUtil::redisHget($uid, 'top_reward');
        if (empty($top_reward)){
            $top_reward = 0;
        }

        //100个玩家
        $all_ranks = RedisUtil::redisHgetall('guess_rank');
        //取最高奖励数组
        $all_rewards = [];
        $all_ids = [];
        foreach ($all_ranks as $all_rank){
            $all_ids[] = $all_rank[0];
            $all_rewards[] = $all_rank[2];
        }
        //排序，降序
        array_multisort($all_rewards, SORT_DESC, $all_ids, SORT_ASC, $all_ranks);
//        var_dump('100玩家排序');
//        var_dump($all_ranks);
        return PublicApi::retData(40006, [$top_reward, $all_ranks]);
    }



    /**根据本场最高奖金，比较排行榜玩家
     * @param $aid
     * @param $name
     * @param $reward
     */
    public static function guessRank($aid, $name, $header, $reward){
        //所有的排名
        $guess_ranks = RedisUtil::redisHgetall('guess_rank');
        //$guess_low = $guess_rinfo['low'];
        $rank_num = count($guess_ranks);
        if ($rank_num < 100){
            //获取所有的Id
            $all_ids = array_keys($guess_ranks);
            if (in_array($aid, $all_ids)){//如果此前有排名
                //以前的最高
                $old_reward = $guess_ranks[$aid][2];
                   //比较前后奖金大小
                if ($reward > $old_reward){//最新的比前面的高，存进去
                    RedisUtil::redisHset('guess_rank', $aid, [$aid, $name, $reward, $header]);
                }
            }else{//此前没有排名
                RedisUtil::redisHset('guess_rank', $aid, [$aid, $name, $reward, $header]);
            }
            $new_ranks = RedisUtil::redisHgetall('guess_rank');
            $all_rewards = [];
            foreach ($new_ranks as $new_rank){
                $all_rewards[] = $new_rank[2];
            }
            //排行榜里最小奖金
            $low = min($all_rewards);
            RedisUtil::redisHset('guess_low', 'low', $low);
        }else{//已经有100个人了
            $low = RedisUtil::redisHget('guess_low', 'low');
            if ($reward >= $low){//此场奖金比最低的高，操作一下
                //获取所有的Id
                $all_ids = array_keys($guess_ranks);
                if (in_array($aid, $all_ids)){//如果此前有排名
                    //以前的最高奖金
                    $old_reward = $guess_ranks[$aid][2];
                    //比较前后奖金大小
                    if ($reward > $old_reward){//最新的比前面的高，存进去
                        RedisUtil::redisHset('guess_rank', $aid, [$aid, $name, $reward, $header]);
                    }
                }else{//此前没有排名，存进，删除最小的
                    //先删除最小的
                    $all_rewards = [];
                    foreach ($guess_ranks as $guess_rank){
                        $all_rewards[] = $guess_rank[2];
                    }
                    //升序排序
                    array_multisort($all_rewards, SORT_ASC, $guess_ranks);
                    $low_aid = $guess_ranks[0][0];
                    //最低奖金,更新
                    $low_reward = $guess_ranks[1][2];
                    RedisUtil::redisHset('guess_low', 'low', $low_reward);
                    RedisUtil::redisHdel('guess_rank', $low_aid);
                    //存入最新的
                    RedisUtil::redisHset('guess_rank', $aid, [$aid, $name, $reward, $header]);
                }
            }
        }

    }



    //高频模式获取赔率
    public static function getoddsbyinfo($count, $round, $att_type, $bet_type){
        $match_info = RedisUtil::redisHget('league_new_match' . $count, $round);
        $reports = $match_info[3];
        //遍历战报
        foreach ($reports as $report){
            if ($att_type == $report[0]){//突破还是射门
                if ($bet_type == 1){//成功的赔率
                    $odds = $report[4];
                }elseif ($bet_type == 2){//失败
                    $odds = $report[5];
                }
            }
        }
        return $odds;
    }

    //经验处理
    Public static function expUp($uid, $i){
        $room_temps = TempBet::init;
        $user_info_now = RedisUtil::redisHmget($uid, ['level', 'exp', 'roomid']);
        $room_id = $user_info_now['roomid'];
        $level = $user_info_now['level'];
        if ($level < 100){
            //经验
            $exp = $user_info_now['exp'];
            if (empty($exp)){
                $exp = 0;
            }
            //房间系数
            $coe = $room_temps[$room_id]['coe'];
            //获取经验
            $get_exp = 50 * $coe * $i;
            //升级需要经验
            $exp_up = 200 + 100 * floor(pow(($level - 1), 1.5));
            if (($exp + $get_exp) >= $exp_up){//经验满足升级
                $new_level = $level + 1;
                $new_exp = ($exp + $get_exp) - $exp_up;

                $data['level'] = $new_level;
                $data['exp'] = $new_exp;
                RedisUtil::redisHmset($uid, $data);
            }else{//不满足升级
                $new_exp = $exp + $get_exp;
                RedisUtil::redisHset($uid, 'exp', $new_exp);
            }
        }
    }

    //高频模式比赛结束，将rooom_gold加入gold
    public static function getGoldByRoom(){
        //$match_info = RedisUtil::redisHget('now_match', 'info');
        //$count = $match_info[0];
        //获取本场投注人的id
        $ids = RedisUtil::redisHkeys('high_room');

        //$ids = RedisUtil::redisHkeys('all_new_guess' . $count);
        //遍历本场比赛投注的玩家
        foreach ($ids as $id){
            //$aid = substr($id, 2);
            $uid = PublicApi::aidToUid($id);
            //获取房间内金币， 所有金币
            $user_info = RedisUtil::redisHmget($uid, ['gold', 'room_gold']);
            //如果房间内金币>0，将金币加入总金币
            if ($user_info['room_gold'] > 0){
                $new_gold = $user_info['gold'] + $user_info['room_gold'];
                $data['gold'] = $new_gold;
                $data['room_gold'] = 0;
                $data['gold_info'] = $user_info['room_gold'];
                RedisUtil::redisHmset($uid, $data);
            }
            for ($i = 1; $i <= 4; $i++){//删除4个房间内金币Log
                $roomid = 'h'.$i;
                RedisUtil::redisHdel($uid, 'gold_log_' . $roomid);
            }
        }
        //删除high_room里面的Ids
        RedisUtil::redisDel('high_room');
    }




}