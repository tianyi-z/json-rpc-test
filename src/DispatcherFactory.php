<?php
namespace YuanxinHealthy\JsonRpcTest;
class DispatcherFactory extends \Hyperf\HttpServer\Router\DispatcherFactory
{
    public function __construct()
    {
        $this->routes = [__DIR__.DIRECTORY_SEPARATOR.'routes.php'];
        parent::__construct();
    }
}