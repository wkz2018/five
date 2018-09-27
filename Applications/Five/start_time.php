<?php
/**
 * Created by PhpStorm.
 * User: song
 * Date: 2018/3/19
 * Time: 16:49
 */

use Workerman\Worker;
use Workerman\Lib\Timer;
use GatewayWorker\Lib\Gateway;
use Workerman\MySQL\Connection;

$task = new Worker();

// 开启多少个进程运行定时任务，注意多进程并发问题
$task->count = 5;
$task->name = 'Timer_Task'; //联赛任务
$task->onWorkerStart = function ($task) {
    global $db;
    //$db = new Connection('10.45.22.17', '3306', 'iogame', '53f2f6cee8ba4d14', 'noob');
    $db = new Connection('172.16.207.123', '3306', 'zyhy', 'Xyz4768@', 'five');
    if($task->id == 0){
        //定时任务
        var_dump('启动定时任务');
        Timer::add(60, 'dayTask', array(), true);
    }elseif($task->id == 1){
        var_dump('每分钟统计在线人数');
        Timer::add(60, 'onlineTask', array(), true);
    }elseif($task->id == 2){
        var_dump('每5分钟更新射手榜');
        Timer::add(30, 'shootTask', array(), true);
    }elseif ($task->id == 3){
        var_dump('每5分钟更新积分进球榜');
        Timer::add(30, 'leagueTeamTask', array(), true);
    }elseif ($task->id == 4){
        var_dump('每15秒推送比赛信息');
        $teamid = Timer::add(15, 'highMatchTask', array(), true);

    }
};


Gateway::$registerAddress = '127.0.0.1:1138';

//在线人数
function onlineTask(){
    \Api\PublicApi::addDayStatistics('online', Gateway::getAllClientCount());
}

function dayTask(){
    $now_time = (int)date('Hi', time()); //获取当天的时间
    switch ($now_time) {
        case 2358:
            \Api\PublicApi::dayStatistics();
            break;
        case 730:
            \Api\LeagueApi::resetLeague();
        case 400:
            \Api\LeagueApi::cleanBetting();
            break;
    }
}

//射手榜
function shootTask(){
    //凌晨时间戳
    $zero_time = strtotime(date('Y-m-d', time()));
    $utime = time() - $zero_time;//从0点到现在经过了多少秒
    if ($utime <= 4 * 60 * 60 || $utime >= 8 * 60 * 60){
        $pass_time = time() % 300;
        if ($pass_time >= 270 && $pass_time < 299){//经过时间在4.30-4.59
            $count = \Api\PublicApi::getCountByTime(time());//当前第几轮
            if ($count > 0){//count存在
                $match_infos = \Common\RedisUtil::redisHgetall('league_match' . $count);
                //var_dump('当前比赛轮数' . $count);
                foreach ($match_infos as $match_info){//获取每回合战报信息
                    $report_infos = $match_info[3];
                    if (is_array($report_infos)){
                        foreach ($report_infos as $report_info){//获取射门战报
                            if ($report_info[0] == 2 && $report_info[3] == 1){//战报是射门，并且结果为1进球
                                $shoot_playerid = $report_info[1][0];//取进攻球员数组里第一个球员
                                //var_dump('射手' . $shoot_playerid . '更新');
                                \Api\PublicApi::updateShootRank($shoot_playerid);
                            }
                        }
                    }
                }
            }
        }
    }
}

//积分进球榜,胜平负
function leagueTeamTask(){

    //凌晨时间戳
    $zero_time = strtotime(date('Y-m-d', time()));
    $utime = time() - $zero_time;//从0点到现在经过了多少秒
    if ($utime <= 4 * 60 * 60 || $utime >= 8 * 60 * 60){
        $pass_time = time() % 300;
        if ($pass_time >= 270 && $pass_time < 299){//经过时间在4.30-4.59
            $count = \Api\PublicApi::getCountByTime(time());//当前第几轮
            if ($count > 0){
                $league_match = \Common\RedisUtil::redisHget('league_match', $count);
                $team_id1 = $league_match[0];//主队id
                $team_id2 = $league_match[1];//客队id
                $score1 = $league_match[2];//主队进球
                $score2 = $league_match[3];//客队进球

                //var_dump($team_id1 . '和' .$team_id2 . '更新');
                \Api\PublicApi::updateTeamScore($team_id1, $score1, $score2);//更新主队比分，进失球
                \Api\PublicApi::updateTeamScore($team_id2, $score2, $score1);//更新客队

                //更新交手记录
                //var_dump($team_id1 . '更新交手战绩' . $team_id2 . '轮次' . $count);
                \Api\PublicApi::updateTeamHistory($team_id1, $team_id2, $score1, $score2);
            }
        }
    }
}

//高频版本十五秒推送比赛
function highMatchTask(){
    $next_match = \Common\RedisUtil::redisHget('now_match', 'next');
    if ($next_match == 1){//是新回合
        //var_dump('新回合');
        \Common\RedisUtil::redisHset('now_match', 'next', 2);
    }elseif ($next_match == 2 || $next_match == 3){
        if ($next_match == 2){
            //删除榜单
            for ($i = 1; $i <= 4; $i++){
                $room_id = 'h' . $i;
                \Common\RedisUtil::redisDel('all_new_guess_' . $room_id);
            }
            \Common\RedisUtil::redisHset('now_match', 'next', 3);
        }
        $match_info = \Common\RedisUtil::redisHget('now_match', 'info');
        $time = time();
        \Common\RedisUtil::redisHset('now_match', 'time', $time);
        $count = $match_info[0];
        $round = $match_info[1];
        $att_type = $match_info[2];


        $mat_report = \Api\MatchInfoApi::newMatchInfo($count, $round, $att_type);
        //战报及结果
        $report = $mat_report[0];
        $result = $mat_report[1];
        $att_type = $report[3];
        $playerid = 0;
        if ($att_type == 2){//进攻类型是射门
            if ($result == 1){//射门成功
                $playerid = $report[4][0];
            }
        }

        //var_dump('15s战报');
        //var_dump($report);
        $rData = \Api\PublicApi::retData(20008, $report);

        for ($i = 1; $i <= 4; $i++){//向4个房间推送战报
            $roomid = 'h'.$i;
            Gateway::sendToGroup('room' . $roomid, $rData);
        }
        Timer::add(12, 'resultTask', array([$result, $playerid]), false);
    }

}

//高频版本12s推送结果
function resultTask($array){
    $result = $array[0];
    $playerid = $array[1];
//    var_dump('12s推结果');
//    var_dump($result);
//    var_dump($playerid);
    $rData = \Api\PublicApi::retData(20009, $result);
    for ($i = 1; $i <= 4; $i++){//向4个房间推送结果
        $roomid = 'h'.$i;
        Gateway::sendToGroup('room' . $roomid, $rData);
    }
    //结算所有投注
    \Api\BettingApi::bettingCheckOut();

    $report_result = \Api\PublicApi::judgeLastReport();
    $count = $report_result[1];
    if ($report_result[0] == 1){//此场比赛结束
        if ($count >= 240){
            $next_count = 1;
        }else{
            $next_count = $count + 1;
        }
        $team_info = \Common\RedisUtil::redisHget('league_new_match', $next_count);
        $team1 = $team_info[0];
        $team2 = $team_info[1];
        //var_dump('新的比赛队伍Id');
        //var_dump([$team1, $team2]);
        $rData = \Api\PublicApi::retData(20010, [$team1, $team2]);
        for ($i = 1; $i <= 4; $i++){//向4个房间推送下场比赛队伍
            $roomid = 'h'.$i;
            Gateway::sendToGroup('room' . $roomid, $rData);
        }

        //所有人room_gold加入gold
        \Api\BettingApi::getGoldByRoom();

        //改变next状态
        \Common\RedisUtil::redisHset('now_match', 'next', 1);
        //计算比赛结果,存入mysql
        $team_data = \Api\PublicApi::matchResult($count);
        //更新积分榜,主队，客队：传入队伍id，进球，失球
        \Api\PublicApi::updateHighTeamScore($team_data[0], $team_data[2], $team_data[3]);
        \Api\PublicApi::updateHighTeamScore($team_data[1], $team_data[3], $team_data[2]);

    }
    //更新射手榜
    if ($playerid != 0){
        \Api\PublicApi::updateHighShootRank($playerid);
    }


}



// 如果不是在根目录启动，则运行runAll方法
if (!defined('GLOBAL_START')) {
    Worker::runAll();
}
