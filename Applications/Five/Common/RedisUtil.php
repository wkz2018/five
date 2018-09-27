<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 16/2/19
 * Time: 下午5:27
 */

namespace Common;

use Config\RedisConnect;

class RedisUtil
{
    /**
     * redis
     * 公用链接
     */
    public static function redis()
    {
        //        --多操作的管道实例
        //    $pipe = PublicRedis::redis()->multi(\Redis::PIPELINE);
        // $pipe->exec();
        return RedisConnect::getRedisInstance()->getRedisConn();
    }


    public static function redisMulti()
    {
        //  --多操作的管道实例
        return RedisConnect::getRedisInstance()->getRedisConn()->multi(\Redis::PIPELINE);
    }

    public static function redisExpire($key, $time)
    {
        return RedisConnect::getRedisInstance()->getRedisConn()->expire($key, $time);
    }

    public static function redisKeys($pre = '*')
    {
        return RedisConnect::getRedisInstance()->getRedisConn()->keys($pre);
    }


    /**
     * Hexists 判断键是否存在
     * 检测键是否存在
     * 1, 如果键存在。
     * 0, 如果键不存在。
     */
    public static function redisHexists($key, $key1)
    {
        return RedisConnect::getRedisInstance()->getRedisConn()->hexists($key, $key1);
    }

    /**
     * Exists 判断键是否存在
     * 检测键是否存在
     * 1, 如果键存在。
     * 0, 如果键不存在。
     */
    public static function redisExists($key)
    {
        return RedisConnect::getRedisInstance()->getRedisConn()->exists($key);
    }


    /**
     * Hset 写入数据
     * @param $key
     * @param $key1
     * @param $val
     * @return mixed
     */
    public static function redisHset($key, $key1, $val)
    {
        return RedisConnect::getRedisInstance()->getRedisConn()->hset($key, $key1, $val);
    }

    /**
     * Hmset 写入数据
     * @param $key
     * @param $keyarray
     * @return mixed
     */
    public static function redisHmset($key, $keyarray)
    {

        return RedisConnect::getRedisInstance()->getRedisConn()->hmset($key, $keyarray);

    }

    /**
     * Hget 读取数据
     * @param $key $key
     * @param $key1
     * @return mixed
     */
    public static function redisHget($key, $key1)
    {
        return RedisConnect::getRedisInstance()->getRedisConn()->hget($key, $key1);
    }

    public static function redisGet($key)
    {
        return RedisConnect::getRedisInstance()->getRedisConn()->get($key);
    }


    /**
     * incr 自增
     * @param $key
     * @param $key1
     * @return mixed
     */

    public static function redisIncr($key)
    {
        return RedisConnect::getRedisInstance()->getRedisConn()->incr($key);
    }

    /**
     * Hgetall
     *  获取全部数据
     * @param type $key 表名
     * @return int
     */
    public static function redisHgetall($key)
    {
        return RedisConnect::getRedisInstance()->getRedisConn()->hgetall($key);
    }

    /**
     * hkeys 查健
     */
    public static function redisHkeys($key)
    {

        return RedisConnect::getRedisInstance()->getRedisConn()->hkeys($key);
    }

    public static function redisHvals($key)
    {

        return RedisConnect::getRedisInstance()->getRedisConn()->hvals($key);
    }

    /**
     * hlen 查键长度
     */
    public static function redisHlen($key)
    {
        return RedisConnect::getRedisInstance()->getRedisConn()->hlen($key);
    }

    /**
     * hmget
     *  获取批量数据
     * @param type $key 表名
     * @param type $keyarray 键名  array('$key1','$key2',...)
     * @return int
     */
    public static function redisHmget($key, $keyarray)
    {

        return RedisConnect::getRedisInstance()->getRedisConn()->hmget($key, $keyarray);
    }


    /**
     * Hget 读取数据
     * @param $key $key
     * @param $key1
     * @return mixed
     */
    public static function redisHExistsget($key, $key1)
    {
        if(RedisConnect::redisHexists($key,$key1)){
            return RedisConnect::getRedisInstance()->getRedisConn()->hget($key, $key1);
        }else{
            return 0;
        }

    }

    /**
     * hdel  删除
     * @param $key
     * @param $key1
     * @return mixed
     */

    public static function redisHdel($key, $key1)
    {

        return RedisConnect::getRedisInstance()->getRedisConn()->hdel($key, $key1);
    }

    /**
     * del  删除 表
     * @param $key
     * @return mixed
     */

    public static function redisDel($key)
    {
        return RedisConnect::getRedisInstance()->getRedisConn()->del($key);
    }

    /**获取列表中元素个数
     * @param $key
     * @return mixed
     */
    public static function redisLlen($key)
    {
        return RedisConnect::getRedisInstance()->getRedisConn()->llen($key);
    }

    /**
     * @param $key
     * @param $val
     * @return mixed
     */
    public static function redisLpush($key, $val)
    {
        return RedisConnect::getRedisInstance()->getRedisConn()->lpush($key, $val);
    }

    public static function redisRpush($key, $val)
    {
        return RedisConnect::getRedisInstance()->getRedisConn()->rpush($key, $val);
    }

    public static function redisLpop($key)
    {
        return RedisConnect::getRedisInstance()->getRedisConn()->lpop($key);
    }

    public static function redisRpop($key)
    {
        return RedisConnect::getRedisInstance()->getRedisConn()->rpop($key);
    }

    //链表中元素
    public static function redisLrange($key, $start = 0, $stop = -1)
    {
        return RedisConnect::getRedisInstance()->getRedisConn()->lrange($key, $start, $stop);
    }

    //从链表中删除
    public static function redisLrem($key, $value, $count = 1)
    {
        return RedisConnect::getRedisInstance()->getRedisConn()->lrem($key, $value, $count);
    }


}