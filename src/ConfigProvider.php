<?php
namespace YuanxinHealthy\JsonRpcTest;
use Hyperf\Server\Event;
class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                \Hyperf\HttpServer\Router\DispatcherFactory::class => DispatcherFactory::class,
            ],
            'server' => [
                'servers' => [
                    [
                        'name' => 'http',
                        'type' => 1,
                        'host' => '0.0.0.0',
                        'port' => env('RPC_TEST_PORT', 9700),
                        'sock_type' => SWOOLE_SOCK_TCP,
                        'callbacks' => [
                            Event::ON_REQUEST => [\Hyperf\HttpServer\Server::class, 'onRequest'],
                        ],
                    ],
                ]
            ]
        ];
        
    }
}
