<?php
declare(strict_types=1);

namespace App\Common;

use Hyperf\Redis\Redis;
use Psr\Container\ContainerInterface;

class Functions
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * Functions constructor.
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * 订单号生成器
     * @return string
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \TypeError
     */
    public function orderNo(): string
    {
        $expire = 25*60*60;
        $date = date('YmdHi');
        $key = "order_sign_generator_{$date}";
        $redis = $this->container->get(Redis::class);
        if(!$redis->exists($key)){
            $baseIncrement = mt_rand(100000,500000);
            $redis->set($key,$baseIncrement,$expire);
        }
        $stepNumber = mt_rand(5,50);
        $id = $redis->incrBy($key,$stepNumber);
        $orderSn = "{$date}{$id}";
        return $orderSn;
    }

    /**
     * 商户订单号生成器(支付)
     * @return string
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \TypeError
     */
    public function outTradeNo(): string
    {
        $expire = 25*60*60;
        $date = date('Ymd');
        $key = "order_payment_sign_generator_{$date}";
        $redis = $this->container->get(Redis::class);
        if(!$redis->exists($key)){
            $redis->set($key,0,$expire);
        }
        $id = $redis->incr($key);
        $id = str_pad((string)$id, 10, "0", STR_PAD_LEFT);
        $outerOrderSn = "{$date}{$id}";
        return $outerOrderSn;
    }

    /**
     * 原子锁
     * @param string $key
     * @param int $expire
     * @return bool
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \TypeError
     */
    public function atomLock(string $key, int $expire): bool
    {
        $redis = $this->container->get(Redis::class);
        $extend[0] = 'nx';
        if ($expire !== 0) {
            $extend['ex'] = $expire;
        }
        $result = $redis->set($key, 1, $extend);
        return $result;
    }

    /**
     * 删除原子锁
     * @param string $key
     * @return int
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \TypeError
     */
    public function delAtomLock(string $key): int
    {
        $redis = $this->container->get(Redis::class);
        $result = $redis->del($key);
        return $result;
    }

    /**
     * 获取客户端ip地址
     * @author WangWenBin
     * @param $serverParams
     * @return mixed
     */
    public function getHyperfIp(array $serverParams)
    {
        if (isset($serverParams['http_client_ip'])) {
            return $serverParams['http_client_ip'];
        } elseif (isset($serverParams['http_x_real_ip'])) {
            return $serverParams['http_x_real_ip'];
        } elseif (isset($serverParams['http_x_forwarded_for'])) {
            // 部分CDN会获取多层代理IP，所以转成数组取第一个值
            $arr = explode(',', $serverParams['http_x_forwarded_for']);

            return $arr[0];
        } else {
            return $serverParams['remote_addr'];
        }
    }

    /**
     * 数组非递归分组
     * @param array $array
     * @param string $pkey
     * @param string|null $ckey
     * @return array
     */
    public function arrayGroupBy(array $array, string $pkey, string $ckey = null): array
    {
        if(empty($array)){
            return [];
        }
        $grouped = [];
        foreach ($array as $value) {
            if($ckey === null){
                $grouped[$value[$pkey]][] = $value;
            }else{
                $grouped[$value[$pkey]][$value[$ckey]] = $value;
            }
        }
        return $grouped;
    }

}


