<?php
use Hyperf\HttpServer\Router\Router;
Router::get('/', '\YuanxinHealthy\JsonRpcTest\RpcTestController@test'); // 一些下拉列表.
Router::post('/', '\YuanxinHealthy\JsonRpcTest\RpcTestController@test'); // 一些下拉列表.