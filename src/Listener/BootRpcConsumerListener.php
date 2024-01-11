<?php
/**
 * @description rpc监听
 * @CreateDate 2024-01-11 14:42
 */

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace YaoxyD\PltCommon\Listener;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\BootApplication;
use Hyperf\RpcMultiplex\Constant;
use Hyperf\Stringable\Str;
use Hyperf\Stringable\StrCache;
use Psr\Container\ContainerInterface;
use YaoxyD\PltCommon\Rpc\User\UserServiceInterface;

use function Hyperf\Support\env;

#[Listener(99)]
class BootRpcConsumerListener implements ListenerInterface
{
    public function __construct(protected ContainerInterface $container) {}

    /**
     * @return string[] returns the events that you want to listen
     */
    public function listen(): array
    {
        return [
            BootApplication::class,
        ];
    }

    public function getConsumer(string $interface, string $host, int $prot): array
    {
        $key = Str::upper('RPC_' . StrCache::studly('plt_user', '_'));
        $value = env($key);
        if ($value) {
            // $value = 127.0.0.1:9502
            [$host, $prot] = explode(':', $value);
        }
        return [
            // name 需与服务提供者的 name 属性相同
            'name' => $interface::NAME,
            // 服务接口名，可选，默认值等于 name 配置的值，如果 name 直接定义为接口类则可忽略此行配置，如 name 为字符串则需要配置 service 对应到接口类
            'service' => $interface,
            // 对应容器对象 ID，可选，默认值等于 service 配置的值，用来定义依赖注入的 key
            'id' => $interface,
            // 服务提供者的服务协议，可选，默认值为 jsonrpc-http
            // 可选 jsonrpc-http jsonrpc jsonrpc-tcp-length-check
            'protocol' => Constant::PROTOCOL_DEFAULT,
            // 负载均衡算法，可选，默认值为 random
            'load_balancer' => 'random',
            //            // 这个消费者要从哪个服务中心获取节点信息，如不配置则不会从服务中心获取节点信息
            //            'registry' => [
            //                'protocol' => 'consul',
            //                'address' => 'http://127.0.0.1:8500',
            //            ],
            // 如果没有指定上面的 registry 配置，即为直接对指定的节点进行消费，通过下面的 nodes 参数来配置服务提供者的节点信息
            'nodes' => [
                ['host' => $host, 'port' => (int) $prot],
            ],
            // 配置项，会影响到 Packer 和 Transporter
            'options' => [
                'connect_timeout' => 5.0,
                'recv_timeout' => 5.0,
                'settings' => [
                    // 根据协议不同，区分配置
                    'open_eof_split' => true,
                    'package_eof' => "\r\n",
                    // 'open_length_check' => true,
                    // 'package_length_type' => 'N',
                    // 'package_length_offset' => 0,
                    // 'package_body_offset' => 4,
                ],
                // 重试次数，默认值为 2，收包超时不进行重试。暂只支持 JsonRpcPoolTransporter
                'retry_count' => 2,
                // 重试间隔，毫秒
                'retry_interval' => 100,
                // 使用多路复用 RPC 时的心跳间隔，null 为不触发心跳
                'heartbeat' => 30,
                // 当使用 JsonRpcPoolTransporter 时会用到以下配置
                'pool' => [
                    'min_connections' => 1,
                    'max_connections' => 32,
                    'connect_timeout' => 10.0,
                    'wait_timeout' => 3.0,
                    'heartbeat' => -1,
                    'max_idle_time' => 60.0,
                ],
            ],
        ];
    }

    /**
     * Handle the Event when the event is triggered, all listeners will
     * complete before the event is returned to the EventDispatcher.
     */
    public function process(object $event): void
    {
        $interfaces = [
            UserServiceInterface::class => ['plt-user', 9502],
        ];
        $consumers = [];
        foreach ($interfaces as $interface => $value) {
            $consumers[] = $this->getConsumer($interface, ...$value);
        }
        $this->container->get(ConfigInterface::class)->set('services.consumers', $consumers);
    }
}
