<?php
/**
 * Created by PhpStorm.
 * User: WARP
 * Date: 2018/7/26
 * Time: 10:21
 */

namespace Api;

use Common\RedisUtil;
use GatewayWorker\Lib\Gateway;
use Temp\TempBet;
use Temp\TempRate;
use Temp\TempTeam;

class MatchInfoApi
{
    public static function init($message, $client_id)
    {
        //session丢失
        if (empty($_SESSION['uid'])) {
            return PublicApi::retData(100);
        }

        switch ($message[0]) {
            case 20000://进入房间后此场比赛信息
                return self::jionRoomMatchInfo();
            case 20001:
                return self::sendMatchInfo();
            case 20002:
                return self::nextMatch();
            case 20003:
                return self::teamRank();
            case 20004:
                return self::playerRank();
            case 20005:
                return self::leagueSchedule();
            case 20006:
                return self::matchHistory($message);
            case 20007:
                return self::highJoinRoomInfo();
            case 20011:
                return self::highTeamRank();
            case 20012:
                return self::highPlayerRank();
            case 20013:
                return self::newMatchGold();
            default:
                return 0;
        }
    }

    //进入房间后此场比赛信息
    public static function jionRoomMatchInfo(){


        //凌晨时间戳
        $zero_time = strtotime(date('Y-m-d',time()));
        $utime = time() - $zero_time;//从0点到现在经过了多少秒

        //4点到8点，renturn 1
        if ($utime > 4 * 60 * 60 && $utime < 8 * 60 * 60){
            return PublicApi::retData(20000, 1);
        }elseif ($utime >= 8 * 60 * 60 || $utime <= 4 * 60 * 60){//先return 0
            //当前比赛轮数
            $count_now = PublicApi::getCountByTime(time());

            $match_info = RedisUtil::redisHget('league_match', $count_now);
            //主客队id
            $teamid1 = $match_info[0];
            $teamid2 = $match_info[1];
            //主客队的得分
            $teamid1_score = $match_info[2];
            $teamid2_score = $match_info[3];
            //主客队进攻实力
//            $teamid1_power = RedisUtil::redisHget('league_team' . $teamid1, 'attack_power');
//            $teamid2_power = RedisUtil::redisHget('league_team' . $teamid2, 'attack_power');
            //这轮比赛信息
            $report_infos = RedisUtil::redisHgetall('league_match' . $count_now);
            $rData = [0, time(), $count_now, $teamid1, $teamid2, $teamid1_score, $teamid2_score];

            //战报数组
            $cData = [];
            for($i = 1; $i <= count($report_infos); $i++){
                $report_info = $report_infos[$i];
                $cData[] = $report_info;
            }
            $rData[] = $cData;
            return PublicApi::retData(20000, $rData);
        }
    }

    //定时发送比赛信息
    public static function sendMatchInfo(){
        $uid = $_SESSION['uid'];
        $roomid = RedisUtil::redisHget($uid, 'roomid');
        //凌晨时间戳
        $zero_time = strtotime(date('Y-m-d', time()));
        $utime = time() - $zero_time;//从0点到现在经过了多少秒
        //早上8点后或者早4点前
        if ($utime <= 4 * 60 * 60 || $utime >= 8 * 60 * 60){
            if ($utime % 300 == 0){//是整5分
                //当前第几轮
                $count_now = PublicApi::getCountByTime(time());
                $match_info = RedisUtil::redisHget('league_match', $count_now);
                //主客队id
                $teamid1 = $match_info[0];
                $teamid2 = $match_info[1];
                //主客队的得分
                $teamid1_score = $match_info[2];
                $teamid2_score = $match_info[3];
                $rData = [$teamid1, $teamid2, $teamid1_score, $teamid2_score];
                //这轮比赛信息
                $report_infos = RedisUtil::redisHgetall('league_match' . $count_now);
                //战报数组
                $cData = [];
                for($i = 1; $i <= count($report_infos); $i++){
                    $report_info = $report_infos[$i];
                    $cData[] = $report_info;
                }
                $rData[] = $cData;
                //向所有这个房间的人发送
                Gateway::sendToGroup('room' . $roomid, $rData);
            }
        }

        return;
    }


    //下场比赛信息
    public static function nextMatch(){
        $rate_mode = TempRate::init;
        $uid = $_SESSION['uid'];
        //当前轮次
        $now_count = PublicApi::getCountByTime(time());
        //下场轮次
        $next_count = $now_count + 1;
        if ($next_count >= 241){//下场没有比赛返回0 ，有比赛返回1
            return PublicApi::retData(20002, 0);
        }
        //下场比赛球队id
        $match_info = RedisUtil::redisHget('league_match', $next_count);
        $team_id1 = $match_info[0];
        $team_id2 = $match_info[1];
        //获取下场比赛赔率数组
        $betting_rateid = $team_id1 . '&' . $team_id2;
        $betting_info = $rate_mode[$betting_rateid];

        //获取投注数组（如果存在）
        $aid = PublicApi::uidToAid($uid);
        if (RedisUtil::redisHexists('guess_' . $next_count, $aid)){
            $bet_infos = RedisUtil::redisHget('guess_' . $next_count, $aid);
            //移除第一个数0或者1
            array_shift($bet_infos);
        }else {
            $bet_infos = [];
        }

        return PublicApi::retData(20002, [1, $next_count, $team_id1, $team_id2, $betting_info, $bet_infos]);
    }

    //积分榜
    public static function teamRank(){
        $team_infos = [];
        //for循环取所有队伍积分
        for ($i = 1; $i <= 16; $i++){
            $team_data = RedisUtil::redisHgetall('league_team' . $i);
            //总积分
            $total_score = $team_data['total_score'];
            //进球数
            $total_goal = $team_data['total_goal'];
            //失球数
            $lose_goal = $team_data['lose_goal'];
            //净胜球
            $diff_goal = $total_goal - $lose_goal;
            //胜平负
            $team_match_info = RedisUtil::redisHmget('team_info_' . $i, ['win', 'draw', 'defeat']);
            $win_num = $team_match_info['win'];
            $draw_num = $team_match_info['draw'];
            $defeat_num = $team_match_info['defeat'];
            $team_infos[] = [$i, $win_num, $draw_num, $defeat_num, $total_goal, $lose_goal, $diff_goal, $total_score];
        }
        //所有积分数组
        $key_socre = [];
        //所有净胜球
        $diff_goals = [];
        //所有进球
        $total_goals = [];
        foreach ($team_infos as $team_info){
            $total_goals[] = $team_info[4];
            $diff_goals[] = $team_info[6];
            $key_socre[] = $team_info[7];
        }
        //根据积分降序排序
        array_multisort($key_socre, SORT_DESC, $diff_goals, SORT_DESC, $total_goals, SORT_DESC, $team_infos);
        //判断排名（如果积分，净胜球，进球数都一样的情况下）
//        foreach ($team_infos as $key => $team_info){
//            if ($key != 0){
//                $key_socre = $team_info[7];
//                $diff_goals = $team_info[6];
//                $total_goals = $team_info[4];
//                if ($key_socre == $team_infos[$key - 1][7] && $diff_goals == $team_infos[$key - 1][6] && $total_goals == $team_infos[$key - 1][4]){
//                    //前三个数据相同的队伍id
//                    $team_id1 = $team_info[0];
//                    $team_id2 = $team_infos[$key - 1][0];
//                    //根据id确定相互净胜球
//                    if (RedisUtil::redisHexists('team_info_' . $team_id1, $team_id2) && RedisUtil::redisHexists('team_info_' . $team_id2, $team_id1)){
//                        $team_goal_info1 = RedisUtil::redisHget('team_info_' . $team_id1, $team_id2);
//                        $team_goal_info2 = RedisUtil::redisHget('team_info_' . $team_id2, $team_id1);
//                        if ($team_goal_info1[0] > $team_goal_info2[0]){//后面的相互净胜球多，排名提前,互换位置
//                            $temp_info = $team_infos[$key - 1];
//                            $team_infos[$key - 1] = $team_infos[$key];
//                            $team_infos[$key] = $temp_info;
//                        }elseif($team_goal_info1[0] == $team_goal_info2[0]){//两者净胜球一样，比较进球数
//                            if ($team_goal_info1[1] > $team_goal_info2[0]){//排名靠后的相互进球数多，排名提前
//                                $temp_info = $team_infos[$key - 1];
//                                $team_infos[$key - 1] = $team_infos[$key];
//                                $team_infos[$key] = $temp_info;
//                            }
//                        }
//                    }
//                }
//            }
//        }
        //array_multisort($key_socre, SORT_DESC, $team_infos);
//        var_dump('根据积分降序排序');
//        var_dump($team_infos);
        return PublicApi::retData(20003, $team_infos);
    }

    //射手榜
    public static function playerRank(){
        $player_infos = RedisUtil::redisHgetall('league_shoot_rank');

        $rank_players = [];
        $gold_infos = [];
        $player_ids = [];
        foreach ($player_infos as $key => $goal){
            $player_id = $key;
            $player_ids[] = $key;
            //球队id
            $teamid = ceil($player_id / 11);
            //存入二维数组里
            $rank_players[] = [$player_id, $teamid, $goal];
            //把进球放入数组，排序用
            $gold_infos[] = $goal;
        }
        //根据进球排序
        array_multisort($gold_infos, SORT_DESC, $player_ids, SORT_ASC, $rank_players);
        $goal_rank = $rank_players;

        $pre = 0;
        $a = [];
        foreach ($goal_rank as $key => $info){
            //进球数
            $score = $info[2];
            $playerId = $info[0];
            $team_id = $info[1];

            //安排队员排名
            if($score != $pre){
                $a[] = [$key + 1, $playerId, $team_id, $score];
            }elseif ($score == $pre){
                $rank = $a[$key - 1][0];
                $a[] = [$rank, $playerId, $team_id, $score];
            }
            $pre = $score;
        }
        //截取10个值
        $b = array_slice($a, 0, 10);
        return PublicApi::retData(20004, $b);
    }

    //赛程表
    public static function leagueSchedule(){
        //现在场次
        $now_count = PublicApi::getCountByTime(time());
        //轮次(1-30)
//        $round = ceil($now_count / 8);
        //1-240场比赛信息
        $all_league_info = RedisUtil::redisHgetall('league_match');
        //上一场比赛场次
        $last_count = $now_count - 1;
        $schedule_info = [];
        foreach ($all_league_info as $key => $league_info){
            //本场比赛时间
            $time = self::getTimeByCount($key);
            if ($key <= $last_count){
                //比赛时间， 主队Id, 主队得分,客队得分,客队id
                $schedule_info[] = [$time, $league_info[0], $league_info[2], $league_info[3], $league_info[1]];
            }elseif ($key > $last_count){
                $schedule_info[] = [$time, $league_info[0], 9, 9, $league_info[1]];
            }
        }
//        var_dump($schedule_info);
//        var_dump('赛程表' . $now_count);
        return PublicApi::retData(20005, $schedule_info);
    }


    //交手战绩
    public static function matchHistory($params){
        $count = $params[1];
        $match_info = RedisUtil::redisHget('league_match', $count);

        //需要历史战绩的队伍id
        $team_id1 = $match_info[0];
        $team_id2 = $match_info[1];
        //胜负场次信息
        $team_his_info = RedisUtil::redisHget('team_history_' . $team_id1, $team_id2);
        //交手比分，最多10场
        $team_his_score = RedisUtil::redisHget('team_his_score_' . $team_id1, $team_id2);
        //反转顺序
        $a = array_reverse($team_his_score);
        return PublicApi::retData(20006, [$team_his_info, $a]);

    }


    //高频版本进入房间历史信息 20007
    public static function highJoinRoomInfo(){
        $now_match = RedisUtil::redisHgetall('now_match');
        //当前比赛进行状况
        //$match_info = RedisUtil::redisHget('now_match', 'info');
        $match_info = $now_match['info'];
        $count = $match_info[0];
        $round = $match_info[1];
        $att_type = $match_info[2];

        $next_match = $now_match['next'];
        if ($next_match == 1 || $next_match == 2){
            //下场比赛还没开始，只传下场主客队Id
            if ($count >= 240){
                $next_count = 1;
            }else{
                $next_count = $count + 1;
            }
            $match_team = RedisUtil::redisHget('league_new_match', $next_count);
            $team_1 = $match_team[0];
            $team_2 = $match_team[1];
            return PublicApi::retData(20007, [$team_1, $team_2]);
        }

        //比赛队伍
        $match_team = RedisUtil::redisHget('league_new_match', $count);
        $team1 = $match_team[0];
        $team2 = $match_team[1];

        //比赛结果
        $match_result = RedisUtil::redisHgetall('new_match_result' . $count);

        //此场所有战报
        $match_report = RedisUtil::redisHgetall('league_new_match' . $count);
        //战报数组
        $new_report = [];
        if ($att_type == 1){//此时进行的是突破，不要传射门

            for ($i = 1; $i <= $round; $i++){
                $rData = [];
                $cData = [];
                $bData = [];
                $cData[] = $match_report[$i][0];
                $cData[] = $match_report[$i][1];
                $cData[] = $match_report[$i][2];
                if ($i == $round){//最后一回合
                    //进攻类型，进攻球员，防守球员，战报结果
                    $rData[] = $match_report[$i][3][0][0];
                    $rData[] = $match_report[$i][3][0][1];
                    $rData[] = $match_report[$i][3][0][2];
                    $rData[] = $match_result[$i][0];
                    $cData[] = $rData;
                }else{
                    //直接取战报
                    if (count($match_report[$i][3]) == 1){//只有一个战报
                        $rData[] = $match_report[$i][3][0][0];
                        $rData[] = $match_report[$i][3][0][1];
                        $rData[] = $match_report[$i][3][0][2];
                        $rData[] = $match_result[$i][0];
                        $cData[] = $rData;
                    }elseif(count($match_report[$i][3]) == 2){//有两个战报
                        $rData[] = $match_report[$i][3][0][0];
                        $rData[] = $match_report[$i][3][0][1];
                        $rData[] = $match_report[$i][3][0][2];
                        $rData[] = $match_result[$i][0];
                        $cData[] = $rData;
                        $bData[] = $match_report[$i][3][1][0];
                        $bData[] = $match_report[$i][3][1][1];
                        $bData[] = $match_report[$i][3][1][2];
                        $bData[] = $match_result[$i][1];
                        $cData[] = $bData;
                    }
                }
                //战报

                $new_report[] = $cData;
            }
        }else{//传此回合前的所有战报

            for ($i = 1; $i <= $round; $i++){
                $rData = [];
                $cData = [];
                $bData = [];
                //战报,进攻时间，主客队进攻，进攻类型
                $cData[] = $match_report[$i][0];
                $cData[] = $match_report[$i][1];
                $cData[] = $match_report[$i][2];
                //直接取战报
                if (count($match_report[$i][3]) == 1){//只有一个战报
                    $rData[] = $match_report[$i][3][0][0];
                    $rData[] = $match_report[$i][3][0][1];
                    $rData[] = $match_report[$i][3][0][2];
                    $rData[] = $match_result[$i][0];
                    $cData[] = $rData;
                }elseif(count($match_report[$i][3]) == 2){//有两个战报
                    $rData[] = $match_report[$i][3][0][0];
                    $rData[] = $match_report[$i][3][0][1];
                    $rData[] = $match_report[$i][3][0][2];
                    $rData[] = $match_result[$i][0];
                    $cData[] = $rData;
                    $bData[] = $match_report[$i][3][1][0];
                    $bData[] = $match_report[$i][3][1][1];
                    $bData[] = $match_report[$i][3][1][2];
                    $bData[] = $match_result[$i][1];
                    $cData[] = $bData;
                }
                //战报

                $new_report[] = $cData;
            }
        }
        //var_dump('进入房间后战报');
        //var_dump($new_report);
        return PublicApi::retData(20007, [$team1, $team2, $new_report]);


    }

    //高频模式积分榜
    public static function highTeamRank(){
        $team_infos = [];
        //for循环取所有队伍积分
        for ($i = 1; $i <= 16; $i++){
            $team_data = RedisUtil::redisHgetall('league_new_team' . $i);
            //总积分
            $total_score = $team_data['total_score'];
            //进球数
            $total_goal = $team_data['total_goal'];
            //失球数
            $lose_goal = $team_data['lose_goal'];
            //净胜球
            $diff_goal = $total_goal - $lose_goal;
            //胜平负
            $win_num = $team_data['win'];
            $draw_num = $team_data['draw'];
            $defeat_num = $team_data['defeat'];
            $team_infos[] = [$i, $win_num, $draw_num, $defeat_num, $total_goal, $lose_goal, $diff_goal, $total_score];
        }
        //所有积分数组
        $key_socre = [];
        //所有净胜球
        $diff_goals = [];
        //所有进球
        $total_goals = [];
        foreach ($team_infos as $team_info){
            $total_goals[] = $team_info[4];
            $diff_goals[] = $team_info[6];
            $key_socre[] = $team_info[7];
        }
        //根据积分降序排序
        array_multisort($key_socre, SORT_DESC, $diff_goals, SORT_DESC, $total_goals, SORT_DESC, $team_infos);
        return PublicApi::retData(20011, $team_infos);
    }


    //射手榜
    public static function highPlayerRank(){
        $player_infos = RedisUtil::redisHgetall('new_shoot_rank');

        $rank_players = [];
        $gold_infos = [];
        $playerids = [];
        foreach ($player_infos as $key => $goal){
            $player_id = $key;
            $playerids[] = $key;
            //球队id
            $teamid = ceil($player_id / 11);
            //存入二维数组里
            $rank_players[] = [$player_id, $teamid, $goal];
            //把进球放入数组，排序用
            $gold_infos[] = $goal;
        }
        //根据进球排序
        array_multisort($gold_infos, SORT_DESC, $playerids, SORT_ASC, $rank_players);
        $goal_rank = $rank_players;

        $pre = 0;
        $a = [];
        foreach ($goal_rank as $key => $info){
            //进球数
            $score = $info[2];
            $playerId = $info[0];
            $team_id = $info[1];

            //安排队员排名
            if($score != $pre){
                $a[] = [$key + 1, $playerId, $team_id, $score];
            }elseif ($score == $pre){
                $rank = $a[$key - 1][0];
                $a[] = [$rank, $playerId, $team_id, $score];
            }
            $pre = $score;
        }
        //截取10个值
        $b = array_slice($a, 0, 10);
        //var_dump($b);
        //var_dump($a);
        return PublicApi::retData(20012, $b);
    }


    //20013 高频版本每场比赛开始金币
    public static function newMatchGold(){
        $room_temp = TempBet::init;
        $uid = $_SESSION['uid'];
        $aid = PublicApi::uidToAid($uid);
        //玩家信息
        $user_info = RedisUtil::redisHmget($uid, ['gold', 'roomid', 'room_gold', 'level']);
        $room_gold = $user_info['room_gold'];
        $self_gold = $user_info['gold'];
        $newGold = $self_gold;
        //如果room_gold有金币，加入到gold
        if ($room_gold > 0){
            $new_gold = $room_gold + $self_gold;
            $nData['gold'] = $new_gold;
            $nData['room_gold'] = 0;
            RedisUtil::redisHmset($uid, $nData);
            $newGold = $new_gold;
        }
        $level = $user_info['level'];
        $roomid = $user_info['roomid'];
        //携带金币
        $allow = $room_temp[$roomid]['allow'];
        $carry_gold = $allow * (1 + 0.01 * ($level - 1));
        if ($newGold < $carry_gold){//金币不足
            return PublicApi::retData(20013, [1, $carry_gold, $level]);
        }
        //取出钱，存到room_gold
        $now_gold = $newGold - $carry_gold;
        $data['gold'] = $now_gold;
        $data['room_gold'] = $carry_gold;
        RedisUtil::redisHmset($uid, $data);

        //房间处理，将id存入，后面比赛结束结算用，清除gold_log用
        RedisUtil::redisHset('high_room', $aid, time());

        return PublicApi::retData(20013, [0, $carry_gold, $level]);
    }



    public static function getTimeByCount($count){
        //凌晨时间戳
        $zero_time = strtotime(date('Y-m-d', time()));
        if ($count < 193){//次日0点前
            $time = date("H:i", ($count * 300 + 28500 + $zero_time));
        }elseif ($count >= 193){//零点后
            $a = $count - 193;
            $time = date("H:i", ($a * 300 + $zero_time));
        }
        return $time;
    }



    //15秒战报信息
    public static function newMatchInfo($count, $round, $att_type){

        $matchInfos = RedisUtil::redisHgetall('league_new_match' . $count);
        //上次突破或者射门结果1成功2失败
        $result = RedisUtil::redisHget('new_match_result' . $count, $round);

        $report_num = count($matchInfos);
        if ($att_type == 1 && $result[0] == 1){//上一次战报是突破，并且突破成功,这次传射门
            //获取上次对抗结果
            $report = $matchInfos[$round];
            $new_report = [];
            //进攻时间
            $new_report[] = $report[0];
            //主客场进攻
            $new_report[] = $report[1];
            //进攻类型
            $new_report[] = $report[2];
            //进攻形式，进攻球员，防守球员，成功率，成功赔率，失败赔率
            $new_report[] = $report[3][1][0];
            $new_report[] = $report[3][1][1];
            $new_report[] = $report[3][1][2];
            $new_report[] = $report[3][1][3];
            $new_report[] = $report[3][1][4];
            $new_report[] = $report[3][1][5];
            //新的进攻类型是射门
            $new_att_type = 2;
            RedisUtil::redisHset('now_match', 'info', [$count, $round, $new_att_type]);
            $match_result = $result[1];
            return [$new_report, $match_result];
        }else{//上一次进攻类型是射门或者上次突破失败，下一回合
//            if ($att_type == 1){
//                //上一回合是突破，上次结果是第一个
//                $att_result = $result[0];
//            }else{//上一回合是射门
//                //结果只有一种情况即射门
//                if (count($result) == 1){
//                    $att_result = $result[0];
//                }else{//结果包括突破射门，取射门
//                    $att_result = $result[1];
//                }
//            }
            //判断比赛是否已经结束
            if ($round >= $report_num){//已经结束,进入下一场比赛
                $new_count = $count + 1;
                $new_round = 1;
                if ($new_count > 240){//如果超过240轮，重跑比赛，返回第一轮
                    NewLeagueApi::resetNewLeague();
                    $new_count = 1;
                }
            }else{//本场比赛没有结束，进入本场比赛下一回合
                $new_count = $count;
                $new_round = $round + 1;
            }
            $new_matchInfos = RedisUtil::redisHgetall('league_new_match' . $new_count);
            $report = $new_matchInfos[$new_round];
            //此次进攻类型1突破2射门
            $att_now_type = $report[3][0][0];

            $new_report = [];
            //进攻时间
            $new_report[] = $report[0];
            //主客场进攻
            $new_report[] = $report[1];
            //进攻类型
            $new_report[] = $report[2];
            //进攻形式，进攻球员，防守球员，成功率，成功赔率，失败赔率
            $new_report[] = $report[3][0][0];
            $new_report[] = $report[3][0][1];
            $new_report[] = $report[3][0][2];
            $new_report[] = $report[3][0][3];
            $new_report[] = $report[3][0][4];
            $new_report[] = $report[3][0][5];
            RedisUtil::redisHset('now_match', 'info', [$new_count, $new_round, $att_now_type]);
            $mat_result = RedisUtil::redisHget('new_match_result' . $new_count, $new_round);
            $match_result = $mat_result[0];
            return [$new_report, $match_result];
        }

    }







}