<?php
/**
 * Created by PhpStorm.
 * User: song
 * Date: 2018/2/10
 * Time: 9:29
 */

namespace Api;

use Common\RedisUtil;
use Temp\TempPlayer;
use Temp\TempRate;

class PublicApi
{

    /** 对一个范围随机取固定数量的数字
     * @param $start_num
     * @param $end_num
     * @param $count
     * @return array
     */
    public static function getRandNums($start_num, $end_num, $count){
        $arr_nums = range($start_num, $end_num);
        // 打乱数组
        shuffle($arr_nums);
        $rNums = [];

        for($i = 0; $i < $count; $i++){
            $rNums[] = $arr_nums[$i];
        }

        // 排序
        sort($rNums);

        return $rNums;
    }

    public static function aidToUid($aid){
        return 'pt_u' . $aid;
    }

    public static function uidToAid($uid)
    {
        $idArr = explode('_', $uid);
        return substr($idArr[1], 1);
    }

    /**返回
     * @param $code
     * @param $data
     * @return string
     */
    public static function retData($code, $data = []){
        if (!is_array($data)){
            $data = [$data];
        }

        $rData = [$code, $data];
//        if ($code == 40002){
//            var_dump($rData);
//        }
        return json_encode($rData);
    }

    //按照权重从数组中取一个值
    public static function getRandByWeight($arr){
        $sum = array_sum($arr);
        $r = mt_rand(1, $sum);
        $preWeight = 0;
        foreach ($arr as $key => $weight){
            if ($r > $preWeight && $r <= $preWeight + $weight){
                return $key;
            }
            $preWeight += $weight;
        }
    }

    public static function getWeightRandByName($temp, $name = 'weight'){
        $arr = [];
        foreach ($temp as $id => $item){
            $arr[$id] = $item[$name];
        }
        $sum = array_sum($arr);
        $r = mt_rand(1, $sum);
        $preWeight = 0;
        foreach ($arr as $key => $weight){
            if ($r > $preWeight && $r <= $preWeight + $weight){
                return $key;
            }
            $preWeight += $weight;
        }
    }

    public static function updatePlayerAttr($uid, $attr, $value, $event)
    {
        if($event == 'change'){
            $count = (int)$value;
        }else{
            $old_attr = RedisUtil::redisHget($uid, $attr);

            if (empty($old_attr)) {
                $old_attr = 0;
            }
            $count = (int)$value;
            switch ($event) {
                case 'add' ://如果是加
                    $add_value = $count;
                    $count = (int)$old_attr + $add_value;
                    break;
                case 'minus'://减
                    $count = (int)$old_attr - $count;
                    break;
                case 'change'://变更
                    $count = $value;
                    break;
                default:
                    return;
            }
        }

        RedisUtil::redisHset($uid, $attr, $count);

    }

    /**统计
     * @param $key
     * @param $num
     */
    public static function addDayStatistics($key, $num = 1){
        try{
            $day = date('Ymd');
            if ($key == 'online'){
                RedisUtil::redisHset('com_login_history' . $day, $key, $num);
                $top_online = RedisUtil::redisHget('com_login_history' . $day, 'toponline');
                //最高在线
                if (empty($top_online) || $num > $top_online){
                    RedisUtil::redisHset('com_login_history' . $day, 'toponline', $num);
                }
                //平均在线
                $avg_online = RedisUtil::redisHget('com_login_history' . $day, 'avgonline');
                if(empty($avg_online)){
                    RedisUtil::redisHset('com_login_history' . $day, 'avgonline', $num);
                }else{
                    $avg_online = ceil(($avg_online + $num) / 2);
                    RedisUtil::redisHset('com_login_history' . $day, 'avgonline', $avg_online);
                }
            }else{
                $val = RedisUtil::redisHget('com_login_history' . $day, $key);
                if (empty($val)){
                    $val = 1;
                }else{
                    $val = (int)$val + 1;
                }
                RedisUtil::redisHset('com_login_history' . $day, $key, $val);
            }

        }catch (Exception $e){
            var_dump($e->getMessage());
        }

    }

    /**
     * 每日统计
     */
    public static function dayStatistics(){
        try{
            $day = date('Ymd');
            $login_data = RedisUtil::redisHgetall('com_login_history' . $day);

            $history_data = [];
            $history_data['day'] = $day;
            $history_data['new_reg'] = empty($login_data['newreg'])?0:$login_data['newreg'];
            $history_data['toponline'] = empty($login_data['toponline'])?0:$login_data['toponline'];
            $history_data['avgonline'] = empty($login_data['avgonline'])?0:$login_data['avgonline'];

            $plats = ['all'];
            foreach ($plats as $plat){
                //当日总登录
                $all_login = RedisUtil::redisHlen("com_statistics_alllogin_" . $plat);
                $history_data['all_login'] = $all_login;

                //当日老登录
                $old_login = RedisUtil::redisHlen("com_statistics_ologin_" . $plat);
                $history_data['old_login'] = $old_login;
                $history_data['plat'] = $plat;
                RedisUtil::redisDel('com_statistics_ologin_' . $plat);

                global $db;

                var_dump($history_data);
                $db->insert('login_history')->cols($history_data)->query();

                var_dump('删除登陆历史');
                if (RedisUtil::redisExists('com_login_history' . $day)){
                    RedisUtil::redisDel('com_login_history' . $day);
                }

                var_dump('开始统计留存');
                //留存统计
                self::remainStatistics($plat);

            }


        }catch (Exception $e){
            var_dump($e);
        }
    }

    /**更新射手榜
     * @param $playerid
     */
    public static function updateShootRank($playerid){
        $goal = RedisUtil::redisHget('league_shoot_rank', $playerid);
        if (empty($goal)){
            $goal = 1;
        }else{
            $goal = $goal + 1;
        }
        //更新射手榜
        RedisUtil::redisHset('league_shoot_rank', $playerid, $goal);
    }

    /**更新球队积分，胜平负
     * @param $teamid
     * @param $goal
     * @param $lose
     */
    public static function updateTeamScore($teamid, $goal, $lose){
        $team_info = RedisUtil::redisHmget('league_team' . $teamid, ['total_score', 'total_goal', 'lose_goal']);
        $team_info_data = RedisUtil::redisHmget('team_info_' . $teamid, ['win', 'draw', 'defeat']);
        if ($goal > $lose){
            $team_info['total_score'] += 3;
            $team_info_data['win'] += 1;
        }elseif ($goal == $lose){
            $team_info['total_score'] += 1;
            $team_info_data['draw'] += 1;
        }elseif ($goal < $lose){
            $team_info_data['defeat'] += 1;
        }

        $team_info['total_goal'] += $goal;
        $team_info['lose_goal'] += $lose;

        RedisUtil::redisHmset('league_team' . $teamid, $team_info);
        RedisUtil::redisHmset('team_info_' . $teamid, $team_info_data);

    }


    /**更新高频球队积分，胜平负
     * @param $teamid
     * @param $goal
     * @param $lose
     */
    public static function updateHighTeamScore($teamid, $goal, $lose){
        $team_info = RedisUtil::redisHgetall('league_new_team' . $teamid);
        if ($goal > $lose){
            $team_info['total_score'] += 3;
            $team_info['win'] += 1;
        }elseif ($goal == $lose){
            $team_info['total_score'] += 1;
            $team_info['draw'] += 1;
        }elseif ($goal < $lose){
            $team_info['defeat'] += 1;
        }

        $team_info['total_goal'] += $goal;
        $team_info['lose_goal'] += $lose;

        RedisUtil::redisHmset('league_new_team' . $teamid, $team_info);

    }

    /**更新高频射手榜
     * @param $playerid
     */
    public static function updateHighShootRank($playerid){
        $goal = RedisUtil::redisHget('new_shoot_rank', $playerid);
        if (empty($goal)){
            $goal = 1;
        }else{
            $goal = $goal + 1;
        }
        //更新射手榜
        RedisUtil::redisHset('new_shoot_rank', $playerid, $goal);
    }

    /**更新交手记录，相互净胜球
     * @param $team_id1主队Id
     * @param $team_id2客队id
     * @param $score1主队得分
     * @param $score2客队得分
     */
    public static function updateTeamHistory($team_id1, $team_id2, $score1, $score2){
        if (RedisUtil::redisHexists('team_his_score_' . $team_id1, $team_id2)){//存在，先取再存
            $team_his_infos1 = RedisUtil::redisHget('team_his_score_' . $team_id1, $team_id2);
            if (count($team_his_infos1) >= 10){//交手记录大于等于10条，删除第一条
                array_shift($team_his_infos1);
            }
            $team_his_infos1[] = [$team_id1, $score1, $score2, $team_id2];
        }else{//不存在，这是第一次交手
            $team_his_infos1 = [];
            $team_his_infos1[] = [$team_id1, $score1, $score2, $team_id2];
        }

        //存到redis
        RedisUtil::redisHset('team_his_score_' . $team_id1, $team_id2, $team_his_infos1);
        //更新交手胜负场数
        $win_num = 0;
        $draw_num = 0;
        $defeat_num = 0;
        foreach ($team_his_infos1 as $team_his_info1){
            $teamid1 = $team_his_info1[0];
            $teamid2 = $team_his_info1[3];
            //根据球队id获得分数
            if ($teamid1 == $team_id1 && $teamid2 == $team_id2){
                $score_1 = $team_his_info1[1];
                $score_2 = $team_his_info1[2];
            }elseif ($teamid1 == $team_id2 && $teamid2 == $team_id1){
                $score_1 = $team_his_info1[2];
                $score_2 = $team_his_info1[1];
            }


            if ($score_1 > $score_2){//主队胜
                $win_num++;
            }elseif ($score_1 == $score_2){//平
                $draw_num++;
            }elseif($score_1 < $score_2){
                $defeat_num++;
            }
        }
        //存入Redis
        RedisUtil::redisHset('team_history_' . $team_id1, $team_id2, [$team_id1, $team_id2, $win_num, $draw_num, $defeat_num]);

        if (RedisUtil::redisHexists('team_his_score_' . $team_id2, $team_id1)){//存在，先取再存
            $team_his_infos2 = RedisUtil::redisHget('team_his_score_' . $team_id2, $team_id1);
            if (count($team_his_infos2) >= 10){//交手记录大于等于10条，删除第一条
                array_shift($team_his_infos2);
            }
            $team_his_infos2[] = [$team_id1, $score1, $score2, $team_id2];
        }else{//不存在，这是第一次交手
            $team_his_infos2 = [];
            $team_his_infos2[] = [$team_id1, $score1, $score2, $team_id2];
        }
        //存到redis
        RedisUtil::redisHset('team_his_score_' . $team_id2, $team_id1, $team_his_infos2);
        //更新交手胜负场数
        $win_num2 = 0;
        $draw_num2 = 0;
        $defeat_num2 = 0;
        foreach ($team_his_infos2 as $team_his_info2){

            //根据球队id获得分数
            if ($team_id2 == $team_his_info2[3]){
                $scores1 = $team_his_info2[1];
                $scores2 = $team_his_info2[2];
            }elseif ($team_id2 == $team_his_info2[0]){
                $scores1 = $team_his_info2[2];
                $scores2 = $team_his_info2[1];
            }

            //var_dump($scores1. '客队比分' . $scores2);
            if ($scores1 > $scores2){//主队胜
                $defeat_num2++;
            }elseif ($scores1 == $scores2){//平
                $draw_num2++;
            }elseif($scores1 < $scores2){
                $win_num2++;
            }
        }
        //存入Redis
        RedisUtil::redisHset('team_history_' . $team_id2, $team_id1, [$team_id2, $team_id1, $win_num2, $draw_num2, $defeat_num2]);

//        //相互净胜球，相互进球数
//        if (RedisUtil::redisHexists('team_info_' . $team_id1, $team_id2)){//存在
//            $team_goal_info1 = RedisUtil::redisHget('team_info_' . $team_id1, $team_id2);
//            $diff_goal1 = $score1 - $score2 + $team_goal_info1[0];
//            $team_goal1 = $score1 + $team_goal_info1[1];
//        }else{
//            $diff_goal1 = $score1 - $score2;//相互净胜球
//            $team_goal1 = $score1;//相互进球
//        }
//        RedisUtil::redisHset('team_info_' . $team_id1, $team_id2, [$diff_goal1, $team_goal1]);
//        if (RedisUtil::redisHexists('team_info_' . $team_id2, $team_id1)){//存在
//            $team_goal_info2 = RedisUtil::redisHget('team_info_' . $team_id2, $team_id1);
//            $diff_goal2 = $score2 - $score1 + $team_goal_info2[0];
//            $team_goal2 = $score2 + $team_goal_info2[1];
//        }else{
//            $diff_goal2 = $score2 - $score1;//相互净胜球
//            $team_goal2 = $score2;//相互进球
//        }
//        RedisUtil::redisHset('team_info_' . $team_id2, $team_id1, [$diff_goal2, $team_goal2]);

    }






    public static function remainStatistics($plat){
        $day = date("Ymd", time());
        $oneday = 0;
        $twoday = 0;
        $threeday= 0;
        $fourday = 0;
        $fiveday = 0;
        $sixday = 0;
        $sevenday= 0;
        $twoweek = 0;
        $threeweek = 0;
        $month = 0;

        //在线时长
        $fivemin = 0;
        $tenmin = 0;
        $halfhour = 0;
        $hour = 0;
        $twohour = 0;
        $more =0;

        // 留存信息
        $day2 = date("Ymd", time() - 86400 * 1);
        $day3 = date("Ymd", time() - 86400 * 2);
        $day4 = date("Ymd", time() - 86400 * 3);
        $day5 = date("Ymd", time() - 86400 * 4);
        $day6 = date("Ymd", time() - 86400 * 5);
        $day7 = date("Ymd", time() - 86400 * 6);
        $day14 = date("Ymd", time() - 86400 * 13);
        $day21 = date("Ymd", time() - 86400 * 20);
        $day30 = date("Ymd", time() - 86400 * 29);

        $day_login_users = RedisUtil::redisHkeys("com_statistics_alllogin_" . $plat);
        var_dump($day_login_users);
        for($i = 0; $i < count($day_login_users); $i++){
            $uid = $day_login_users[$i];
            $online_time = RedisUtil::redisHget("com_statistics_alllogin_" . $plat, $uid);

            if ($online_time < 300){
                $fivemin ++;
            }elseif($online_time >= 300 && $online_time < 600){
                $tenmin ++;
            }elseif($online_time >= 600 && $online_time < 1800){
                $halfhour ++;
            }elseif($online_time >= 1800 && $online_time < 3600){
                $hour ++;
            }elseif($online_time >= 3600 && $online_time < 7200){
                $twohour ++;
            }else{
                $more ++;
            }

            $ctime = RedisUtil::redisHget($uid, 'ctime');
            $diff_day = self::getdiffday(time(), $ctime) + 1;
            switch ($diff_day)
            {
                case 1:
                    $oneday ++;
                    break;
                case 2:
                    $twoday ++;
                    break;
                case 3:
                    $threeday ++;
                    break;
                case 4:
                    $fourday ++;
                    break;
                case 5:
                    $fiveday ++;
                    break;
                case 6:
                    $sixday ++;
                    break;
                case 7:
                    $sevenday ++;
                    break;
                case 14:
                    $twoweek ++;
                    break;
                case 21:
                    $threeweek ++;
                    break;
                case 30:
                    $month ++;
                    break;
                default:
                    break;
            }
        }

        global $db;
        if($plat == 'all'){
            $online_time_sql = "replace into online_time ( cdate, five, ten, halfhour, hour, twohour, more) values('" .$day . "'," .
                $fivemin ."," . $tenmin ."," . $halfhour.",". $hour ."," . $twohour ."," . $more . ")";
            $db->query($online_time_sql);
        }

        //首登
        $sql = "replace into remain (plat, cdate, one) values('" . $plat . "', '" . $day . "'," .$oneday  .")";
        $db->query($sql);

        if($twoday > 0){
            self::updateDayRemain('two', $twoday, $day2, $plat);
        }

        if($threeday > 0){
            self::updateDayRemain('three', $threeday, $day3, $plat);
        }

        if($fourday > 0){
            self::updateDayRemain('four', $fourday, $day4, $plat);
        }

        if($fiveday > 0){
            self::updateDayRemain('five', $fiveday, $day5, $plat);
        }

        if($sixday > 0){
            self::updateDayRemain('six', $sixday, $day6, $plat);
        }

        if($sevenday > 0){
            self::updateDayRemain('seven', $sevenday, $day7, $plat);
        }
        if($twoweek > 0){
            self::updateDayRemain('fourteen', $twoweek, $day14, $plat);
        }

        if($threeweek > 0){
            self::updateDayRemain('threeteen', $threeweek, $day21, $plat);
        }

        if($month > 0){
            self::updateDayRemain('month', $month, $day30, $plat);
        }

        //删除数据
        RedisUtil::redisDel("com_statistics_alllogin_" . $plat);

    }

    public static function updateDayRemain($key, $login_num, $date, $plat){
        $sql = "update remain set ". $key . " = " . $login_num . " where cdate =  '" . $date . "' and plat = '" . $plat ."'";
        global $db;
        $row_count = $db->query($sql);
        if ($row_count == 0){
            //更新失败插入
            $insert_sql = "insert into remain (plat, cdate, " . $key .") values('" . $plat . "', '" . $date . "'," . $login_num  . ")";
            $db->query($insert_sql);
        }
    }

    /**
     * 获取间隔天数
     * @param $endtime
     * @param $begintime
     * @return 间隔天数
     */
    public static function getdiffday($endtime, $begintime)
    {
        $a_dt = getdate($endtime);
        $b_dt = getdate($begintime);
        $a_new = mktime(12, 0, 0, $a_dt['mon'], $a_dt['mday'], $a_dt['year']);
        $b_new = mktime(12, 0, 0, $b_dt['mon'], $b_dt['mday'], $b_dt['year']);
        return round(abs($a_new - $b_new) / 86400);
    }


    /** 根据球队id获取球员
     * @param $teamid
     * @return array
     */
    public static function getPlayersByTeamid($teamid){
        $tempplayers = TempPlayer::init;
        //获取球员id集合
        $maxid = $teamid * 11;
        //获取所在队伍球员集合
        $playerdata = [];
        for ($i = $maxid - 10; $i <= $maxid; $i ++){
            $playerdata[] = $tempplayers[$i];
        }

        return $playerdata;
    }

    /**获取球队实力
     * @param $teamid
     * @return int
     */
    public static function getTeamPower($teamid){
        //球队进攻实力 球队防守实力
        $attck = 0;
        $defense_sum = 0;
        $players = self::getPlayersByTeamid($teamid);
        for ($i = 1; $i < 11; $i++){
            $shoot_data = $players[$i]['shoot'];
            $defense_data = $players[$i]['defense'];
            $attck += $shoot_data;
            $defense_sum += $defense_data;
        }
        $defense = ($defense_sum + $players[0]['shoot']) * 10/11;

        return [$attck, $defense];
    }

    /**根据时间获取当前比赛轮数
     * @param $nowtime 现在时间
     * @return float|int   比赛第几轮
     */
    public static function  getCountByTime($nowtime){
        //凌晨时间戳
        $zero_time = strtotime(date('Y-m-d', $nowtime));
        $utime = $nowtime - $zero_time;//从0点到现在经过了多少秒
        //早上8点后或者早4点前
        if ($utime <= 4 * 60 * 60){//在凌晨0点4点
            $count_now = floor(($utime + 86400 - 28800) / 300) + 1;
        }elseif($utime >= 8 * 60 * 60){//早8点以后
            //当前正在进行第几轮比赛
            $count_now = floor(($utime - 28800) / 300) + 1;
        }
        return $count_now;
    }

    /**根据下载时间获取轮次，只限制投注用，7.30到8点是第0场
     * @param $nowtime 现在时间
     * @return float|int
     */
    public static function getOddsCountByTime($nowtime){
        //凌晨时间戳
        $zero_time = strtotime(date('Y-m-d', $nowtime));
        $utime = $nowtime - $zero_time;//从0点到现在经过了多少秒
        //早上8点后或者早4点前
        if ($utime <= 4 * 60 * 60){//在凌晨0点4点
            $count_now = floor(($utime + 86400 - 28800) / 300) + 1;
        }elseif ($utime >= 7.5 * 60 * 60 && $utime < 8 * 60 * 60){//早上7.30到8点，是第一场的赔率
            $count_now = 0;
        } elseif($utime >= 8 * 60 * 60){//早8点以后
            //当前正在进行第几轮比赛
            $count_now = floor(($utime - 28800) / 300) + 1;
        }
        return $count_now;
    }


    /**
     * @param $count 当前比赛轮数
     * @return mixed 当前比赛所有赔率的数组
     */
    public static function getOddsByCount($count){
        $rate_mode = TempRate::init;
        $match_info = RedisUtil::redisHget('league_match', $count);
        $team_id1 = $match_info[0];
        $team_id2 = $match_info[1];
        //获取此场比赛赔率信息集合
        $betting_rateid = $team_id1 . '&' . $team_id2;
        $betting_info = $rate_mode[$betting_rateid];
        return $betting_info;
    }



    public static function matchlog($team1, $team2, $score1, $score2){
        global $db;
        $data = [];
        $data['team1'] = $team1;
        $data['team2'] = $team2;
        $data['score1'] = $score1;
        $data['score2'] = $score2;
        $db->insert('match')->cols($data)->query();
    }

    public static function wxlog($aid, $header, $wxname, $time){
        global $db;
        $data = [];
        $data['aid'] = $aid;
        $data['header'] = $header;
        $data['wxname'] = $wxname;
        $data['time'] = $time;
        $db->insert('wxusers')->cols($data)->query();
    }

    public static function highMatchlog($count, $team1, $team2, $score1, $score2, $time){
        global $db;
        $data = [];
        $data['count'] = $count;
        $data['team1'] = $team1;
        $data['team2'] = $team2;
        $data['score1'] = $score1;
        $data['score2'] = $score2;
        $data['time'] = $time;
        $db->insert('high_match_log')->cols($data)->query();
    }


    public static function newmatchlog($team1, $team2, $score1, $score2){
        global $db;
        $data = [];
        $data['team1'] = $team1;
        $data['team2'] = $team2;
        $data['score1'] = $score1;
        $data['score2'] = $score2;
        $data['time'] = date("Ymd", time());
        $db->insert('newmatch')->cols($data)->query();
    }


    //投注log,两个版本都存入，主要用来记录金币消耗
    public static function bettinglog($aid, $count, $match_team, $betting_id, $odds, $betting_gold, $day){
        global $db;
        $data = [];
        $data['aid'] = $aid;
        $data['count'] = $count;
        $data['match_team'] = $match_team;
        $data['betting_id'] = $betting_id;
        $data['odds'] = $odds;
        $data['betting_gold'] = $betting_gold;
        $data['day'] = $day;
        $db->insert('betting')->cols($data)->query();
    }

    //高频版本投注
    public static function highBettinglog($aid, $count, $round, $att_type, $bet_type, $odds, $betting_gold, $day){
        global $db;
        $data = [];
        $data['aid'] = $aid;
        $data['count'] = $count;
        $data['round'] = $round;
        $data['att_type'] = $att_type;
        $data['bet_type'] = $bet_type;
        $data['odds'] = $odds;
        $data['betting_gold'] = $betting_gold;
        $data['day'] = $day;
        $db->insert('high_betting')->cols($data)->query();
    }

    //高频版本订单
    public static function highOrderList($aid, $count, $round, $att_type, $bet_type, $betting_gold, $odds, $num, $time){
        global $db;
        $data = [];
        $data['aid'] = $aid;
        $data['count'] = $count;
        $data['round'] = $round;
        $data['att_type'] = $att_type;
        $data['bet_type'] = $bet_type;
        $data['betting_gold'] = $betting_gold;
        $data['odds'] = $odds;
        $data['num'] = $num;
        $data['time'] = $time;
        $db->insert('high_order_list')->cols($data)->query();
    }

    //普通版本版本订单
    public static function orderList($aid, $count, $match_team, $betting_id, $odds, $betting_gold, $num,$day){
        global $db;
        $data = [];
        $data['aid'] = $aid;
        $data['count'] = $count;
        $data['match_team'] = $match_team;
        $data['betting_id'] = $betting_id;
        $data['odds'] = $odds;
        $data['betting_gold'] = $betting_gold;
        $data['num'] = $num;
        $data['day'] = $day;
        $db->insert('order_list')->cols($data)->query();
    }

    //金币获得log
    public static function goldGetLog($aid, $count, $gold, $type, $time){
//        $match_info = RedisUtil::redisHget('league_match', $count);
//        $match_team = $match_info[0] . 'vs' . $match_info[1];
//        $match_score = $match_info[2] . ':' . $match_info[3];
        global $db;
        $data = [];
        $data['aid'] = $aid;
        $data['count'] = $count;
//        $data['match_team'] = $match_team;
//        $data['match_score'] = $match_score;
        $data['num'] = $gold;
        $data['type'] = $type;
        $data['time'] = $time;
        $db->insert('gold_get_log')->cols($data)->query();
    }

    //投注结果，传入轮次，投注单,获得奖金
    public static function getRewardByCountBet($count, $bet_infos, $aid){
        //根据场次获取比赛结果
        $match_info = RedisUtil::redisHget('league_match', $count);
        $team_id1 = $match_info[0];
        $team_id2 = $match_info[1];
        $score1 = $match_info[2];
        $score2 = $match_info[3];
        $match_team = $team_id1 . 'vs' . $team_id2;
        //赔率数组
        $odds_infos = PublicApi::getOddsByCount($count);
        //让球数
        $concede_num = $odds_infos['concede'];
        //移除第一个元素0
        array_shift($bet_infos);
        $all_suc_gold = 0;
        foreach ($bet_infos as $bet_info){
            $betting_id = $bet_info[0];//投注赔率id名称
            $odds = $bet_info[1];
            $betting_gold = $bet_info[2];
            $reward = $bet_info[3];//预计奖励
            //竞猜成功与否写一个方法，传入竞猜ID，比分，让球，获得竞猜成功与否
            $betting_result = self::getResultByidAndScore($betting_id, $score1, $score2, $concede_num);

            if ($betting_result == 0){//竞猜成功
                $all_suc_gold += $reward;
                self::orderList($aid, $count, $match_team, $betting_id, $odds, $betting_gold, $reward, time());
            }else{//竞猜失败
                self::orderList($aid, $count, $match_team, $betting_id, $odds, $betting_gold, 0, time());
            }
        }

        return $all_suc_gold;
    }


    //判断是否是此场比赛最后一回合
    public static function judgeLastReport(){
        $match_info = RedisUtil::redisHget('now_match', 'info');
        $count = $match_info[0];
        $round = $match_info[1];
        $att_type = $match_info[2];
        $league_infos = RedisUtil::redisHgetall('league_new_match' . $count);

        //1是此场比赛结束 2不是
        if ($round >= count($league_infos)){//此时是最后1回合
            //战报
            $report = $league_infos[$round];
            //战报长度 1个或者两个
            $report_num = count($report[3]);
            if ($att_type == 1 && $report_num == 2){//战报是突破射门,此时是突破
                return [2, $count];
            }else{
                return [1, $count];
            }
        }else{
            return [2, $count];
        }
    }


    //计算比赛结果,存入mysql
    public static function matchResult($count){
        $match_reports = RedisUtil::redisHgetall('league_new_match' . $count);

        $score1 = 0;
        $score2 = 0;
        foreach ($match_reports as $key => $match_report){
            //结果
            $match_result = RedisUtil::redisHget('new_match_result' . $count, $key);
            if ($match_report[1] == 1){//主队进攻
                $reports = $match_report[3];
                if (count($reports) == 1){
                    $att_type = $reports[0][0];
                    if ($att_type == 2){//射门
                        $result = $match_result[0];
                        if ($result == 1){//射门成功
                            $score1 ++;
                        }
                    }
                }elseif (count($reports) == 2){
                    $result = $match_result[1];
                    if ($result == 1){//射门成功
                        $score1 ++;
                    }
                }
            }else{//客队进攻
                $reports = $match_report[3];
                if (count($reports) == 1){
                    $att_type = $reports[0][0];
                    if ($att_type == 2){//射门
                        $result = $match_result[0];
                        if ($result == 1){//射门成功
                            $score2 ++;
                        }
                    }
                }elseif (count($reports) == 2){
                    $result = $match_result[1];
                    if ($result == 1){//射门成功
                        $score2 ++;
                    }
                }
            }
        }
        $team_info = RedisUtil::redisHget('league_new_match', $count);
        $team1 = $team_info[0];
        $team2 = $team_info[1];
        //存入mysql
        self::highMatchlog($count, $team1, $team2, $score1, $score2, time());
        return [$team1, $team2, $score1, $score2];

    }



    //竞猜结果
    public static function getResultByidAndScore($betting_id, $score1, $score2, $concede_num){//成功返回0，失败返回1
        if ($betting_id == 'win'){//胜
            if ($score1 > $score2){
                return 0;
            }else{
                return 1;
            }
        }elseif ($betting_id == 'draw'){//平
            if ($score1 == $score2){
                return 0;
            }else{
                return 1;
            }
        }elseif ($betting_id == 'defeat'){//负
            if ($score1 < $score2){
                return 0;
            }else{
                return 1;
            }
        }elseif ($betting_id == 'c_win'){//让球赢
            if (($score1 + $concede_num) > $score2){
                return 0;
            }else{
                return 1;
            }
        }elseif ($betting_id == 'c_draw'){//让球平
            if (($score1 + $concede_num) == $score2){
                return 0;
            }else{
                return 1;
            }
        }elseif ($betting_id == 'c_defeat'){//让球负
            if (($score1 + $concede_num) < $score2){
                return 0;
            }else{
                return 1;
            }
        }elseif($betting_id == 'other'){
            if ($score1 >= 5 || $score2 >= 5){
                return 0;
            }else{
                return 1;
            }
        } else{
            $scores = explode(':', $betting_id);
            if(count($scores) < 2){
                var_dump($betting_id);
            }
            if ($scores[0] == $score1 && $scores[1] == $score2) {
                return 0;
            } else {
                return 1;
            }
        }
    }


    public static function getOddsBySucRate($suc_rate){
        $rate = $suc_rate / 100;
        $suc_odds = ((1 / $rate) * 0.91);

        $a = $suc_odds * 100;
        $suc_new_odds = floor($a) / 100;

        $fail_odds = ((1 / (1 - $rate)) * 0.91);
        $b = $fail_odds * 100;
        $fail_new_odds = floor($b) / 100;

//        $rate = $suc_rate / 100;
//        $suc_odds = (1 / ($rate * 0.91));
//        $suc_new_odds = round($suc_odds, 2);
//        $fail_odds = (1 / ((1 - $rate) * 0.91));
//        $fail_new_odds = round($fail_odds, 2);

        return [$suc_new_odds, $fail_new_odds];
    }





}