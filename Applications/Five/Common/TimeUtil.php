<?php

namespace Common;
/**
 * 时间相关工具类
 * User: song
 * Date: 2017/2/4
 * Time: 14:29
 */
class TimeUtil
{
    /**获取毫秒时间戳
     * @return float
     */
    public static function msectime() {
        list($tmp1, $tmp2) = explode(' ', microtime());
        return (float)sprintf('%.0f', (floatval($tmp1) + floatval($tmp2)) * 1000);
    }

}