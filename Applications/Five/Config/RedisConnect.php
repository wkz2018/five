<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 16/2/19
 * Time: 下午4:37
 */

namespace Config;


class RedisConnect
{
    const REDISHOSTNAME = "127.0.0.1";

    /**
     * Redis的port
     * @var int
     */
    const REDISPORT = 6381;

    /**
     * Redis的超时时间
     * @var int
     */
    const REDISTIMEOUT = 0;

    /**
     * Redis的password
     * @var unknown_type
     */
    const REDISPASSWORD = '';

    /**
     * Redis的DBname
     * @var int
     */
    const REDISDBNAME = 1;

    /**
     * 类单例
     * @var object
     */
    private static $instance;

    /**
     * Redis的连接句柄
     * @var object
     */
    private $redis;

    /**
     * 私有化构造函数，防止类外实例化
     * @param unknown_type $dbnumber
     */
    private function __construct()
    {
        // 链接数据库
        $this->redis = new \Redis();
        $this->redis->pconnect(self::REDISHOSTNAME, self::REDISPORT, self::REDISTIMEOUT);
        $this->redis->setOption(\Redis::SERIALIZER_PHP, \Redis::SERIALIZER_PHP);
        //$this->redis->auth(self::REDISPASSWORD);
    }

    /**
     * 私有化克隆函数，防止类外克隆对象
     */
    private function __clone()
    {
    }

    /**
     * 类的唯一公开静态方法，获取类单例的唯一入口
     * @return object
     */
    public static function getRedisInstance()
    {
        if (!(self::$instance instanceof self)) {
            self::$instance = new self();
        }
        return self::$instance;
    }


    /**
     * 获取redis的连接实例
     * @return Redis
     */
    public function getRedisConn()
    {
        return $this->redis;
    }

    /**
     * 需要在单例切换的时候做清理工作
     */
    public function __destruct()
    {
        self::$instance->redis->close();
        self::$instance = NULL;
    }

}