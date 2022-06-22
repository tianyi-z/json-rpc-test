<?php
declare(strict_types=1);
namespace YuanxinHealthy\JsonRpcTest;

class RpcTestController
{
    protected $request;
    protected $response;
    protected $classmap = [];
    public function __construct() {
        $this->request = \Hyperf\Utils\ApplicationContext::getContainer()->get(\Hyperf\HttpServer\Contract\RequestInterface::class);
        $this->response = \Hyperf\Utils\ApplicationContext::getContainer()->get(\Hyperf\HttpServer\Contract\ResponseInterface::class);
    }
    /**
     * 测试.
     *
     * @return ResponseInterface.
     */
    function test()
    {
        $this->classmap = [];
        $server = $this->request->input('server', '127.0.0.1');
        $port = $this->request->input('port', 9503);
        $method = $this->request->input('method', 'encyclopediasSearch');
        $class = $this->request->input('servername', 'Search');
        $par = trim($this->request->input('fucontionname', "[\n\n]"));
        if ($this->request->getMethod() == 'POST') {
            //请求数据.
            return $this->rpcRe($this->request->all());
        }
        $this->getClass();
        $servernameoptions = ['<option>...</option>'];
        foreach ($this->classmap as $k => $v) {
            $v['funlistoptions'] = ['<option>...</option>'];
            foreach ($v['funlist'] as $f => $pars) {
                $v['funlistoptions'][] = str_replace([
                    '-parameter-',
                    '-placeholder-'
                ], [implode("\n", $pars), $f], '<option parametertip="-parameter-" value="-placeholder-">-placeholder-</option>');
            }
            $v['funlistoptions'] = implode("\n", $v['funlistoptions']);
            $servernameoptions[] = str_replace('-placeholder-', $k, '<option value="-placeholder-">-placeholder-</option>');
            $this->classmap[$k] = $v;
        }
        $servernameoptions = implode("\n", $servernameoptions);
        $classmap = json_encode(array_values($this->classmap));
        $html = <<<html
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<script src="https://libs.baidu.com/jquery/1.9.1/jquery.min.js"></script>
<script type="text/javascript">
var clasmap = $classmap;
$(document).ready(function(){
    $(".button").click(function(){
        $('.res').text('请求中...');
        $.post("",$("#from").serialize(),function(result){
            $('.res').text(result);
        });
    });
    $(".servername").change(function(){
        for (var x in clasmap) {
            if ($(this).val() == clasmap[x].sname) {
                $('.fucontionname').html(clasmap[x].funlistoptions);
                break;
            }
        }
    });
    $('.fucontionname').change(function(){
        $('.parametertip').text($('.fucontionname option:selected').attr('parametertip'));
    })
});
</script>
</head>

<body>
<div style="width:100px;float:left;">
<form id="from" method="get">
  <p>server: <input type="text" value="$server" name="server" /></p>
  <p>port: <input type="text" value="$port" name="port" /></p>
  <p>class: <select class="servername" name='servername'>$servernameoptions</select></p>
  <p>method: <select class='fucontionname' name='fucontionname'></select></p>
  <p>参数: <br /><span class='parametertip'></span><textarea rows="10" cols="30" name="par">$par</textarea></p>
  <input class="button" type="button" value="提交" />
</form>
</div>
<div style="float:left; width:800px; margin-left: 200px;" >结果:<textarea style="display:block;min-height:80%;min-width:80%" class="res"></textarea></div>
</body>
</html> 
html;
        return $this->response->write($html);
    }


    /**
     * 执行请求
     * @param array $data
     * @return bool|string
     */
    private function rpcRe(array $data)
    {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (false == socket_connect($socket, $data['server'], $data['port'])) {
            return $this->response->write('链接失败:' . socket_strerror(socket_last_error($socket)));
        }
        $params = json_decode($data['par'], true);
        $data = [
            "jsonrpc" => '2.0',
            'method' => \Hyperf\Utils\ApplicationContext::getContainer()->get(\Hyperf\Rpc\PathGenerator\PathGenerator::class)->generate($data['servername'], $data['fucontionname']),
            'params' => $params,
            'id' => uniqid() . 'ttt',
            'context' => [],
        ];
        $dat = json_encode($data) . "\r\n";
        socket_write($socket, $dat, strlen($dat));
        $res = socket_read($socket, 1000000, PHP_NORMAL_READ);
        socket_close($socket);
        $res = json_decode(trim($res), true);
        return (json_encode($res, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        //驼峰命名转下划线命名
    }
    private function getClass($path = null)
    {
        $files = [];
        is_null($path) && ($path = BASE_PATH.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Service'.DIRECTORY_SEPARATOR.'JsonRpc');
        $directoryIterator = new \DirectoryIterator($path);
        foreach ($directoryIterator as $fileInfo) {
            if($fileInfo->isDot()) {
                continue;
            }
            $fullname = $path.DIRECTORY_SEPARATOR.$fileInfo->getFilename();
            if ($fileInfo->isDir()) {
                $this->getClass($fullname);
            } elseif ($fileInfo->isFile() && $fileInfo->getExtension() == 'php') {
                $this->parseFile($fullname);
            }
        }
    }
    
    private function parseFile($file)
    {
        $strlen = BASE_PATH.'/app/';
        $class = substr(str_replace('/', '\\', '\App\\'.substr($file, strlen($strlen))), 0, -4);
        if (!class_exists($class)) {
            return [];
        }
        $reflect = new \ReflectionClass($class);
        $d = $reflect->getDocComment();
        if (!$d) {
            return [];
        }
        $d = str_replace([' ', "\t","\n", "\r"], '', $d);
        $match = [];
        preg_match('/RpcService\(name="(.*)",/U', $d, $match);
        if (empty($match[1])) {
            return [];
        }
        $sername = $match[1];
        $methods = $reflect->getMethods(\ReflectionMethod::IS_PUBLIC);
        $this->classmap[$sername] = [
            'sname' => $sername,
            'funlist' => [],
        ];
        foreach ($methods as $method) {
            $name = $method->getName();
            if (in_array($name, ['__construct'])) {
                continue;
            }
            $parameters = $method->getParameters();
            $tmpPar = [];
            foreach ($parameters as $parameter) {
                $tmpPar[] = $parameter->getName().($parameter->getType() ? ':'.$parameter->getType() : '');
            }
            $this->classmap[$sername]['funlist'][$name] = $tmpPar;
        }
    }
}
