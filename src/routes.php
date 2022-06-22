<?php
use Hyperf\HttpServer\Router\Router;
Router::get('/rpc-test', function () {
    return 'rpc-test';
});