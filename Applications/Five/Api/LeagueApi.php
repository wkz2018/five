<?php
/**
 * Created by PhpStorm.
 * User: WARP
 * Date: 2018/7/20
 * Time: 14:58
 */

namespace Api;

use Common\RedisUtil;
use Temp\TempMode;
use Temp\TempPlayer;


class LeagueApi
{
    //联赛赛程
    public static $league_list = [
        [1, 9, 2, 10, 3, 11, 4, 12, 5, 13, 6, 14, 7, 15, 8, 16],
        [8, 11, 1, 12, 5, 15, 2, 16, 7, 9, 13, 6, 14, 10, 3, 4],
        [2, 11, 8, 4, 1, 3, 5, 9, 6, 10, 7, 12, 13, 15, 14, 16],
        [8, 1, 10, 16, 15, 6, 14, 11, 5, 12, 2, 4, 7, 3, 13, 9],
        [2, 1, 7, 8, 14, 4, 5, 3, 10, 11, 13, 12, 6, 16, 15, 9],
        [5, 8, 14, 1, 9, 6, 2, 7, 13, 3, 10, 4, 16, 11, 15, 12],
        [10, 1, 5, 2, 16, 4, 15, 3, 9, 12, 13, 8, 14, 7, 11, 6],
        [6, 12, 16, 1, 15, 8, 10, 7, 13, 2, 11, 4, 9, 3, 14, 5],
        [11, 1, 12, 3, 4, 6, 9, 8, 10, 5, 16, 7, 15, 2, 13, 14],
        [12, 8, 4, 1, 6, 3, 11, 7, 9, 2, 16, 5, 15, 14, 10, 13],
        [12, 2, 3, 8, 4, 7, 1, 6, 11, 5, 9, 14, 16, 13, 15, 10],
        [1, 7, 6, 8, 3, 2, 4, 5, 12, 14, 11, 13, 9, 10, 16, 15],
        [8, 2, 1, 5, 4, 13, 3, 14, 12, 10, 7, 6, 11, 15, 9, 16],
        [7, 5, 6, 2, 8, 14, 1, 13, 3, 10, 4, 15, 12, 16, 11, 9],
        [5, 6, 2, 14, 7, 13, 8, 10, 1, 15, 3, 16, 4, 9, 12, 11],
        [9, 1, 10, 2, 11, 3, 12, 4, 13, 5, 14, 6, 15, 7, 16, 8],
        [11, 8, 12, 1, 15, 5, 16, 2, 9, 7, 6, 13, 10, 14, 4, 3],
        [11, 2, 4, 8, 3, 1, 9, 5, 10, 6, 12, 7, 15, 13, 16, 14],
        [1, 8, 16, 10, 6, 15, 11, 14, 12, 5, 4, 2, 3, 7, 9, 13],
        [1, 2, 8, 7, 4, 14, 3, 5, 11, 10, 12, 13, 16, 6, 9, 15],
        [8, 5, 1, 14, 6, 9, 7, 2, 3, 13, 4, 10, 11, 16, 12, 15],
        [1, 10, 2, 5, 4, 16, 3, 15, 12, 9, 8, 13, 7, 14, 6, 11],
        [12, 6, 1, 16, 8, 15, 7, 10, 2, 13, 4, 11, 3, 9, 5, 14],
        [1, 11, 3, 12, 6, 4, 8, 9, 5, 10, 7, 16, 2, 15, 14, 13],
        [8, 12, 1, 4, 3, 6, 7, 11, 2, 9, 5, 16, 14, 15, 13, 10],
        [2, 12, 8, 3, 7, 4, 6, 1, 5, 11, 14, 9, 13, 16, 10, 15],
        [7, 1, 8, 6, 2, 3, 5, 4, 14, 12, 13, 11, 10, 9, 15, 16],
        [2, 8, 5, 1, 13, 4, 14, 3, 10, 12, 6, 7, 15, 11, 16, 9],
        [5, 7, 2, 6, 14, 8, 13, 1, 10, 3, 15, 4, 16, 12, 9, 11],
        [6, 5, 14, 2, 13, 7, 10, 8, 15, 1, 16, 3, 9, 4, 11, 12]
    ];


    public static function cleanBetting(){
        //结算上一赛季未结算投注单
        if (RedisUtil::redisExists('guess_users')){
            $bet_aids = RedisUtil::redisHkeys('guess_users');
            foreach ($bet_aids as $bet_aid){
                $bet_uid = PublicApi::aidToUid($bet_aid);
                $bet_counts = RedisUtil::redisHget($bet_uid, 'betting');
                //遍历今日所投数组
                foreach ($bet_counts as $bet_count){
                    $bet_info = RedisUtil::redisHget('guess_' . $bet_count, $bet_aid);
                    if ($bet_info[0] == 0){//需要结算，给结算，不需要登陆游戏
                        //写个方法，传投注单[1/0，[赔率id名称，赔率，金额，预计奖金]]，比赛轮次，获得奖金
                        $bet_reward = PublicApi::getRewardByCountBet($bet_count, $bet_info, $bet_aid);
                        //金币获得log
                        if ($bet_reward > 0){
                            PublicApi::goldGetLog($bet_aid, $bet_count, $bet_reward, 1, time());
                        }
                        //将奖励加入uid里gold
                        $gold = RedisUtil::redisHget($bet_uid, 'gold');
                        RedisUtil::redisHset($bet_uid, 'gold', ($gold + $bet_reward));
                    }
                }
                RedisUtil::redisHdel($bet_uid, 'betting');
            }
        }
        //重置 投注
        for ($i = 1; $i <= 240; $i++){
            RedisUtil::redisDel('guess_' . $i);
        }
        RedisUtil::redisDel('guess_users');
    }

    /**
     * 重置联赛
     */
    public static function resetLeague(){



        // 重置球队积分数据
        $league_team_data = [];
        $team_info_data = [];
        $league_team_data['total_score'] = 0;
        $league_team_data['total_goal'] = 0;
        $league_team_data['lose_goal'] = 0;
        //联赛积分，进失球数
        for ($i = 1; $i <= 16; $i++){
            RedisUtil::redisDel('team_info_' . $i);
            // 球队实力
            $team_power = PublicApi::getTeamPower($i);

            $league_team_data['attack_power'] = $team_power[0];
            $league_team_data['defense_power'] = $team_power[1];
            //胜平负
            $team_info_data['win'] = 0;
            $team_info_data['draw'] = 0;
            $team_info_data['defeat'] = 0;

            RedisUtil::redisHmset('league_team' . $i, $league_team_data);
            RedisUtil::redisHmset('team_info_' . $i, $team_info_data);
        }
        //射手榜
        RedisUtil::redisDel('league_shoot_rank');
        //联赛
        RedisUtil::redisDel('league_match');
        //重置 战报
        for ($i = 1; $i <= 240; $i++){
            RedisUtil::redisDel('league_match' . $i);
        }
        // 比赛赛程
        self::matchSchedule();
    }

    /**
     * 安排赛程
     */
    public static function matchSchedule(){

        //获取赛程安排
        $pvp_list = self::$league_list;
        $match_count = 0;//比赛场次
        for ($i = 0;$i < count($pvp_list); $i++){
            for ($j = 0; $j < count($pvp_list[$i]); $j = $j + 2){
                $match_count ++;
                $team_id1 = $pvp_list[$i][$j];
                $team_id2 = $pvp_list[$i][$j + 1];
                //获取两队信息
                //联赛需要的信息 主队ID 客队ID  主队得分  客队得分
                $score_info = [$team_id1, $team_id2, 0, 0];
                RedisUtil::redisHset('league_match', $match_count, $score_info);
                //计算进攻机会
                self::matchChance($team_id1, $team_id2, $match_count);
            }
        }
        return;
    }



    //根据两队Id计算进攻机会

    /**
     * @param $team_id1 主队
     * @param $team_id2 客队
     * @param $count 回合
     */
    public static function matchChance($team_id1, $team_id2, $match_count){

        //阵型
        //计算两队进攻机会
        //随机进攻
        //A队进攻时：按权重随机进攻方式
        //		   按权重随机A进攻球员
        //		   按权重随机B防守球员
        //		   进攻结果
        //
        //B队进攻时：按权重随机进攻方式
        //		   按权重随机B进攻球员
        //		   按权重随机A防守球员
        //		   进攻结果
        //
        //所有进攻机会结束

        // 球队进攻/防守实力
        $team1_attck = RedisUtil::redisHget('league_team' . $team_id1, 'attack_power');
        $team2_attck = RedisUtil::redisHget('league_team' . $team_id2, 'attack_power');
        $team1_defense = RedisUtil::redisHget('league_team' . $team_id1, 'defense_power');
        $team2_defense = RedisUtil::redisHget('league_team' . $team_id2, 'defense_power');

        //总进攻机会=MIN(12,5+INT(5*(A1+B1)/(A2+B2)))
        $total_chances = min(12,5 + floor(5 * (($team1_attck + $team2_attck) / ($team1_defense + $team2_defense))));
        //A队进攻次数=MAX(INT(总进攻机会*A1/(A1+B1)),1),进攻实力小的为a队
        if ($team1_attck < $team2_attck){
            $team1_chance = max(floor(($total_chances * $team1_attck) / ($team1_attck + $team2_attck)), 1);
            //B队进攻次数=总进攻机会-A队进攻机会
            $team2_chance = $total_chances - $team1_chance;
        }else{
            $team2_chance = max(floor(($total_chances * $team2_attck) / ($team2_attck + $team1_attck)), 1);
            $team1_chance = $total_chances - $team2_chance;
        }

        //安排比赛
        self::startMatch($team_id1, $team_id2, $team1_chance, $team2_chance, $match_count);

        return;
    }

    /** 比赛
     * @param $team_id1
     * @param $team_id2
     * @param $team1_chance 进攻机会
     * @param $team2_chance 进攻机会
     * @param $match_count 比赛场次
     */
    public static function startMatch($team_id1, $team_id2, $team1_chance, $team2_chance, $match_count){
        //总进攻机会
        $total_chance = $team1_chance + $team2_chance;
        //进攻时间
        $time_chances = [];
        if ($total_chance <= 9){
            for ($i = 1; $i <= $total_chance; $i++){
                if ($i == 1){
                    $time_chances[] = PublicApi::getRandNums(1, 6, 1)[0];
                }elseif($i == $total_chance){
                    $a = $time_chances[$i - 2] + 8;
                    $b = $time_chances[$i - 2] + 11;
                    $c = PublicApi::getRandNums($a, $b, 1)[0];
                    if ($c > 90){
                        $c = 90;
                    }
                    $time_chances[] = $c;
                }else{
                    $a = $time_chances[$i - 2] + 8;
                    $b = $time_chances[$i - 2] + 11;
                    $time_chances[] = PublicApi::getRandNums($a, $b, 1)[0];
                }
            }
        }elseif ($total_chance == 10){
            for ($i = 1; $i <= $total_chance; $i++){
                if ($i == 1){
                    $time_chances[] = PublicApi::getRandNums(1, 5, 1)[0];
                }elseif($i == $total_chance){
                    $a = $time_chances[$i - 2] + 7;
                    $b = $time_chances[$i - 2] + 10;
                    $c = PublicApi::getRandNums($a, $b, 1)[0];
                    if ($c > 90){
                        $c = 90;
                    }
                    $time_chances[] = $c;
                }else{
                    $a = $time_chances[$i - 2] + 7;
                    $b = $time_chances[$i - 2] + 10;
                    $time_chances[] = PublicApi::getRandNums($a, $b, 1)[0];
                }
            }
        }else{
            for ($i = 1; $i <= $total_chance; $i++){
                if ($i == 1){
                    $time_chances[] = PublicApi::getRandNums(1, 4, 1)[0];
                }elseif($i == $total_chance){
                    $a = $time_chances[$i - 2] + 6;
                    $b = $time_chances[$i - 2] + 8;
                    $c = PublicApi::getRandNums($a, $b, 1)[0];
                    if ($c > 90){
                        $c = 90;
                    }
                    $time_chances[] = $c;
                }else{
                    $time_chances[] = PublicApi::getRandNums($time_chances[$i - 2] + 7, $time_chances[$i - 2] + 8, 1)[0];
                }
            }
        }

//         //1-22分钟
//        $half1_chance = floor(($total_chance) / 4);
//        //23-45分钟
//        $half2_chance = floor(($total_chance) / 4);
//        //45-67分钟
//        $half3_chance = floor(($total_chance) / 4);
//        // 67-90分钟
//        $half4_chance = $total_chance - ($half1_chance + $half2_chance + $half3_chance);
//
//        //进攻时间
//        $time_chances1 = PublicApi::getRandNums(1, 22, $half1_chance);
//        //进攻时间
//        $time_chances2 = PublicApi::getRandNums(23, 45, $half2_chance);
//        $time_chances3 = PublicApi::getRandNums(46, 60, $half3_chance);
//        $time_chances4 = PublicApi::getRandNums(61, 90, $half4_chance);
//
//        //所有机会时间的数组
//        $time_chances = array_merge($time_chances1, $time_chances2, $time_chances3, $time_chances4);

        $score1 = 0;
        $score2 = 0;
        //轮换进攻
        $team1_attck_num = 1;
        $team2_attck_num = 1;
        for ($j = 1; $j <= $total_chance; $j++){
            //进攻时间
            $att_time = $time_chances[$j - 1];
//            if (($j % 2 != 0 && $team1_attck_num <= $team1_chance) || ($team2_attck_num > $team2_chance)){
//                //主队进攻
//                $shoot_result = self::attackPlayer($team_id1, $team_id2, $j, 1, $match_count, $att_time);
//                if($shoot_result === 1){
//                    $score1 ++;
//                }
//                $team1_attck_num ++;
//            }
//            if (($j % 2 == 0 && $team2_attck_num <= $team2_chance) || ($team1_attck_num > $team1_chance)){//B队，客队进攻
//
//            }

            if (($j % 2 != 0)){
                if ($team1_attck_num <= $team1_chance){
                    //主队进攻
                    $shoot_result = self::attackPlayer($team_id1, $team_id2, $j, 1, $match_count, $att_time);
                    if($shoot_result === 1){
                        $score1 ++;
                    }
                    $team1_attck_num ++;
                }else{
                    // 客队进攻
                    $shoot_result = self::attackPlayer($team_id2, $team_id1, $j, 2, $match_count, $att_time);
                    if($shoot_result === 1){
                        $score2 ++;
                    }
                    $team2_attck_num ++;
                }

            }else{
                if ($team2_attck_num <= $team2_chance){
                    // 客队进攻
                    $shoot_result = self::attackPlayer($team_id2, $team_id1, $j, 2, $match_count, $att_time);
                    if($shoot_result === 1){
                        $score2 ++;
                    }
                    $team2_attck_num ++;
                }else{
                    //主队进攻
                    $shoot_result = self::attackPlayer($team_id1, $team_id2, $j, 1, $match_count, $att_time);
                    if($shoot_result === 1){
                        $score1 ++;
                    }
                    $team1_attck_num ++;
                }

            }
        }

        //var_dump($match_count . ' ' .$score1. ' '.$score2 );
        // 比分信息
        RedisUtil::redisHset('league_match', $match_count, [$team_id1, $team_id2, $score1, $score2]);

        PublicApi::matchlog($team_id1, $team_id2, $score1, $score2);
        //先跑几次，把历史交手战绩跑完
        //PublicApi::updateTeamHistory($team_id1, $team_id2, $score1, $score2);

//        // 更新球队积分(已移至定时任务)
//        $scoreInfo = RedisUtil::redisHget('league_match', $match_count);
//        PublicApi::updateTeamScore($scoreInfo[0], $scoreInfo[2], $scoreInfo[3]);
//        PublicApi::updateTeamScore($scoreInfo[1], $scoreInfo[3], $scoreInfo[2]);
    }


    /**
     * @param $teamid1
     * @param $teamid2
     * @param $round 回合
     * @param $att_team 1 主队 2客队
     * @param $match_count 比赛场次
     * @param $total_chance 总回合数
     * @param $att_time 比赛时间(分钟)
     */
    public static function attackPlayer($teamid1, $teamid2, $round, $att_team, $match_count, $att_time) {

        $mode_temps = TempMode::init;
        //按权重产生进攻方式
        //随机进攻模式id
        $mode_id = PublicApi::getWeightRandByName($mode_temps);
        //根据进攻模式id获取进攻模式
        $attack_mode =  $mode_temps[$mode_id];

        $s1o_num = $attack_mode['s1o_num'];//突破进攻de人数
        $s1d_num = $attack_mode['s1d_num'];//突破防守de人数
        //进攻球员权重
        $attack_weights = ['pos2' => $attack_mode['o2_weight'], 'pos3' => $attack_mode['o3_weight'], 'pos4' => $attack_mode['o4_weight'], 'pos5' => $attack_mode['o5_weight'],  'pos6' => $attack_mode['o6_weight']];
        //防守球员权重
        $defense_weights = ['pos2' => $attack_mode['d2_weight'], 'pos3' => $attack_mode['d3_weight'], 'pos4' => $attack_mode['d4_weight']];
        //进攻球队所有球员
        $attplayers_info = PublicApi::getPlayersByTeamid($teamid1);
        //防守球队所有球员
        $defplayers_info = PublicApi::getPlayersByTeamid($teamid2);

        // 已选择球员
        $break_selected = [];
        $break_def_selected = [];

        // 进攻球员
        $attack_players = [];
        // 防守球员
        $defense_players = [];

        //先取进攻射门球员
        $shootplayerid = self::getPlayerIdByWeight($attplayers_info, $break_selected, $attack_weights);
        $break_selected[] = $shootplayerid;

        //突破射门战报集合
        $round_report = [];

        //先取第一个
        if ($s1o_num == 2 && $s1d_num == 2){//短传渗透

            //先取第一个突破球员
            $break_player_id1 = self::getPlayerIdByWeight($attplayers_info, $break_selected, $attack_weights);
            $break_selected[] =  $break_player_id1;
            $attack_players[] = $break_player_id1;

            //第二个突破球员
            $break_player_id2 = self::getPlayerIdByWeight($attplayers_info, $break_selected, $attack_weights);
            $break_selected[] =  $break_player_id2;
            $attack_players[] = $break_player_id2;


            //第一个防守球员
            $break_def_playerid1 = self::getDefPlayerIdByWeight($defplayers_info, $break_def_selected, $defense_weights);
            $break_def_selected[] = $break_def_playerid1;
            $defense_players[] = $break_def_playerid1;

            //第二个防守球员
            $break_def_playerid2 = self::getDefPlayerIdByWeight($defplayers_info, $break_def_selected, $defense_weights);
            $break_def_selected[] = $break_def_playerid2;
            $defense_players[] = $break_def_playerid2;
            //突破结果
            $result_sucrate = self::getAttackResult($attack_players, $defense_players);
            $attack_result = $result_sucrate[0];
            $attsuc_rate = floor($result_sucrate[1] * 100);

            $round_report[] = [1, $attack_players, $defense_players, $attack_result, $attsuc_rate];

        }elseif ($s1o_num == 1 && $s1d_num == 1){//一条龙
            //进攻球员 就是射门球员
            $attack_players[] = $shootplayerid;
            //第一个防守球员
            $break_def_playerid1 = self::getDefPlayerIdByWeight($defplayers_info, $break_def_selected, $defense_weights);
            $defense_players[] = $break_def_playerid1;
            //突破结果
            $result_sucrate = self::getAttackResult($attack_players, $defense_players);
            $attack_result = $result_sucrate[0];
            $attsuc_rate = floor($result_sucrate[1] * 100);
            $round_report[] = [1, $attack_players, $defense_players, $attack_result, $attsuc_rate];
        }else{//没有突破球员，直接执行射门
            $attack_result = 1;
        }

        $shoot_result = 2;

        //射门信息
        if($attack_result == 1){//选取射门球员id
            // 防守方守门员
            $keeper_id = ($teamid2 - 1) * 11 + 1;

            // 射门方球员
            $shoot_players = [$shootplayerid];
            // 守门方球员
            $keeper_players = [$keeper_id];
            $s2o_num = $attack_mode['s2o_num'];//射门进攻de人数
            $s2d_num = $attack_mode['s2d_num'];//射门防守de人数
            if ($s2o_num == 2 && $s2d_num == 2){//传中射门

                //助攻
                $shoot_player_id = self::getPlayerIdByWeight($attplayers_info, $break_selected, $attack_weights);
                $shoot_players[] = $shoot_player_id;

                //除守门员外的防守球员
                $shoot_def_playerid1 = self::getDefPlayerIdByWeight($defplayers_info, $break_def_selected, $defense_weights);
                $keeper_players[] = $shoot_def_playerid1;
            }

            $shoot_result_rate = self::getShootResult($shoot_players, $keeper_players);
            $shoot_result = $shoot_result_rate[0];
            $shoot_suc_rate = floor($shoot_result_rate[1] * 100);
//            // 射门成功
//            if ($shoot_result == 1){
//             PublicApi::updateShootRank($shootplayerid);
//            }
            //射门战报
            $round_report[] = [2, $shoot_players, $keeper_players, $shoot_result, $shoot_suc_rate];

            //射门信息战报
            //self::shootReport($assist_player_id, $shoot_player_id, $shoot_def_playerid1, $keeper_id, $teamid1, $teamid2, $round, $team_id1, $count, $chance_num);
        }

        // 保存战报信息
        $report_info = [$att_time, $att_team, $mode_id, $round_report];
        RedisUtil::redisHset('league_match' . $match_count, $round, $report_info);

        return $shoot_result;

    }


    /** 获取结果
     * @param $attackPlayers
     * @param $defensePlayers
     * @return int
     */
    public static function getAttackResult($attackPlayers, $defensePlayers){
        $temp_players = TempPlayer::init;

        //成功率=50%+MAX(-30%,MIN(45%,（攻防属性和-守方属性和）/（攻方属性和+守方属性和）*0.5）)

        // 进攻属性和
        $attack_sum = 0;
        foreach ($attackPlayers as $id){
            if ($id > 0){
                $player_info = $temp_players[$id];
                $attack_sum += $player_info['attack'];
            }
        }

        // 防守属性和
        $defense_sum = 0;
        foreach ($defensePlayers as $id){
            if ($id > 0){
                $player_info = $temp_players[$id];
                $defense_sum += $player_info['defense'];
            }
        }

        $success_rate = 0.5 + max(-0.3, min(0.45, ($attack_sum - $defense_sum) / (($attack_sum+ $defense_sum) * 0.5)));
        if ((0 + mt_rand()/mt_getrandmax() * (1-0)) <= $success_rate){
            //成功
            return [1, $success_rate];
        }else{//失败
            return [2, $success_rate];
        }
    }

    public static function getShootResult($shootPlayers, $defensePlayers){
        $temp_players = TempPlayer::init;

        //成功率=50%+MAX(-30%,MIN(45%,（攻防属性和-守方属性和）/（攻方属性和+守方属性和）*0.5）)

        // 进攻属性和
        $attack_sum = 0;
        $attack_num = 1;
        foreach ($shootPlayers as $id){
            if ($id > 0){
                $player_info = $temp_players[$id];
                // 第一个球员做为射门球员
                if($attack_num == 1){
                    $attack_sum += $player_info['shoot'];
                }else{
                    $attack_sum += $player_info['attack'];
                }

            }
            $attack_num ++;
        }

        // 防守属性和
        $defense_sum = 0;
        foreach ($defensePlayers as $id){
            if ($id > 0){
                $player_info = $temp_players[$id];
                if ($player_info['position'] == 1){
                    // 守门员取shoot属性
                    $defense_sum += $player_info['shoot'];
                }else{
                    // 防守人员取防守属性
                    $defense_sum += $player_info['defense'];
                }

            }
        }

        $success_rate = 0.5 + max(-0.3, min(0.45, ($attack_sum - $defense_sum) / (($attack_sum+ $defense_sum) * 0.5)));
        if ((0 + mt_rand()/mt_getrandmax() * (1-0)) <= $success_rate){
            return [1, $success_rate];
        }else{
            return [2, $success_rate];
        }
    }





    /** 随机选取球员
     * @param $players 球队所有球员
     * @param $select_list 不能被选的球员
     * @param $pos_weights ['pos2' => 10, 'pos3' => 10, 'pos4' => 10]
     * @return int 球员ID
     */
    public static function getPlayerIdByWeight($players, $select_list, $pos_weights){

        $pos2_players = []; // 位置2可选球员
        $pos3_players = []; // 位置3可选球员
        $pos4_players = []; // 位置4可选球员
        $pos5_players = []; // 位置5可选球员
        $pos6_players = []; // 位置6可选球员
        //筛选可以选择球员
        foreach ($players as $playerinfo){
            $id = $playerinfo['id'];
            // 已经选择球员和守门员过滤掉
            if (in_array($id, $select_list) || $playerinfo['position'] == 1){
                continue;
            }

            if($playerinfo['position'] == 2){
                $pos2_players[] = $id;
            }elseif ($playerinfo['position'] == 3){
                $pos3_players[] = $id;
            }elseif ($playerinfo['position'] == 4){
                $pos4_players[] = $id;
            }elseif ($playerinfo['position'] == 5){
                $pos5_players[] = $id;
            }elseif ($playerinfo['position'] == 6){
                $pos6_players[] = $id;
            }

        }

        // 如果没有可选球员则把权重删除
        if(count($pos2_players) == 0){
            unset($pos_weights['pos2']);
        }

        if(count($pos3_players) == 0){
            unset($pos_weights['pos3']);
        }

        if(count($pos4_players) == 0){
            unset($pos_weights['pos4']);
        }

        if(count($pos5_players) == 0){
            unset($pos_weights['pos5']);
        }

        if(count($pos6_players) == 0){
            unset($pos_weights['pos6']);
        }

        // 根据权重获取球员位置
        $select_pos = PublicApi::getRandByWeight($pos_weights);
        // 根据球员位置随机一个球员
        if($select_pos == 'pos2'){
            $r_idx = array_rand($pos2_players);
            return $pos2_players[$r_idx];
        }elseif ($select_pos == 'pos3'){
            $r_idx = array_rand($pos3_players);
            return $pos3_players[$r_idx];

        }elseif ($select_pos == 'pos4'){
            $r_idx = array_rand($pos4_players);
            return $pos4_players[$r_idx];
        }elseif ($select_pos == 'pos5'){
            $r_idx = array_rand($pos5_players);
            return $pos5_players[$r_idx];
        }elseif ($select_pos == 'pos6'){
            $r_idx = array_rand($pos6_players);
            return $pos6_players[$r_idx];
        }

        return 0;
    }
    public static function getDefPlayerIdByWeight($players, $select_list, $pos_weights){

        $pos2_players = []; // 位置2可选球员
        $pos3_players = []; // 位置3可选球员
        $pos4_players = []; // 位置4可选球员
        //筛选可以选择球员
        foreach ($players as $playerinfo){
            $id = $playerinfo['id'];
            // 已经选择球员和守门员过滤掉
            if (in_array($id, $select_list) || $playerinfo['position'] == 1){
                continue;
            }

            if($playerinfo['position'] == 2){
                $pos2_players[] = $id;
            }elseif ($playerinfo['position'] == 3){
                $pos3_players[] = $id;
            }elseif ($playerinfo['position'] == 4){
                $pos4_players[] = $id;
            }elseif ($playerinfo['position'] == 5){
                $pos2_players[] = $id;
            }elseif ($playerinfo['position'] == 6){
                $pos3_players[] = $id;
            }

        }

        // 如果没有可选球员则把权重删除
        if(count($pos2_players) == 0){
            unset($pos_weights['pos2']);
        }

        if(count($pos3_players) == 0){
            unset($pos_weights['pos3']);
        }

        if(count($pos4_players) == 0){
            unset($pos_weights['pos4']);
        }

        // 根据权重获取球员位置
        $select_pos = PublicApi::getRandByWeight($pos_weights);
        // 根据球员位置随机一个球员
        if($select_pos == 'pos2'){
            $r_idx = array_rand($pos2_players);
            return $pos2_players[$r_idx];
        }elseif ($select_pos == 'pos3'){
            $r_idx = array_rand($pos3_players);
            return $pos3_players[$r_idx];

        }elseif ($select_pos == 'pos4'){
            $r_idx = array_rand($pos4_players);
            return $pos4_players[$r_idx];
        }

        return 0;
    }






}