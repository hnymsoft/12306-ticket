<?php

//定义时间戳全局变量
defined('GTIME') or define('GTIME', time());

/**
 * 域名函数
 */
if (!function_exists('domain')) {
    function domain() {
        return \Yii::$app->params['TicketUrl']['domain'];
    }
}

/**
 * 拼接路径
 */
if (!function_exists('urlp')) {
    function urlp($group = 'user',$path = 'init') {
        if(isset(Yii::$app->params['TicketUrl'][$group][$path])){
            return domain() . Yii::$app->params['TicketUrl'][$group][$path];
        }
    }
}

/**
 * dd调试函数
 * @param type $var
 */
if (!function_exists('dd')) {
    function dd($var) {
        if ($var === null) {
            exit('null');
        }
        if (is_bool($var)) {
            if ($var == true) {
                exit('true');
            } else {
                exit('false');
            }
        } else {
            header('Content-Type:text/html;charset=utf-8 ');
            echo '<pre>';
            print_r($var);
            echo '</pre>';
            exit();
        }
    }
}

/**
 * 成功状态
 */
if (!function_exists('ajaxReturnSuccess')) {

    function ajaxReturnSuccess($errmsg = 'success', $data = []) {
        return ajaxReturn(1, $errmsg, $data);
    }

}

/**
 * 失败数组
 */
if (!function_exists('ajaxReturnFailure')) {
    function ajaxReturnFailure($errmsg = 'failure', $data = array()) {
        return ajaxReturn(0, $errmsg, $data);
    }
}

/**
 * 获取ip
 */
if (!function_exists('getip')) {
    function getip() {
        if (!empty($_SERVER["HTTP_CLIENT_IP"])) {
            $cip = $_SERVER["HTTP_CLIENT_IP"];
        } elseif (!empty($_SERVER["HTTP_X_FORWARDED_FOR"])) {
            $cip = $_SERVER["HTTP_X_FORWARDED_FOR"];
        } elseif (!empty($_SERVER["REMOTE_ADDR"])) {
            $cip = $_SERVER["REMOTE_ADDR"];
        } else {
            $cip = "无法获取！";
        }
        return $cip;
    }
}

//将 xml 标签转换成数组
if (!function_exists('xml2Array')) {
    function xml2Array($xml) {
        $objXml = @simplexml_load_string($xml);
        $arrRet = obj2Array($objXml);
        return $arrRet;
    }
}

if (!function_exists('obj2Array')) {
    function obj2Array($objXml) {
        if (!is_object($objXml)) {
            return false;
        }
        if (count($objXml) > 0) {
            $keys = $result = array();
            foreach ($objXml as $key => $val) {
                isset($keys[$key]) ? $keys[$key] += 1 : $keys[$key] = 1;
                if ($keys[$key] == 1) {

                    $result[$key] = obj2Array($val);
                } elseif ($keys[$key] == 2) {

                    $result[$key] = array($result, obj2Array($val));
                } elseif ($keys[$key] > 2) {

                    $result[$key][] = obj2Array($val);
                }
            }
            return $result;
        } else {
            return (string) $objXml;
        }
    }
}

/**
 * 加载环境变量
 */
if (!function_exists('loadEnvConfig')) {
    function loadEnvConfig() {
        $envPath = dirname(dirname(dirname(__FILE__))) . '/.env';
        if (!file_exists($envPath)) {
            return;
        }
        $string = file_get_contents($envPath);
        if (!$string) {
            return;
        }
        $arr = explode("\n", $string);
        if (!count($arr)) {
            return;
        }
        foreach ($arr as $val) {
            if (!$val) {
                continue;
            }
            putenv($val);
        }
    }
}

//直接加载
loadEnvConfig();

/**
 * 读取环境变量
 * @param $key
 * @param null $default
 * @return array|bool|false|mixed|null|string|void
 */
if (!function_exists('env')) {
    function env($key, $default = null) {
        $value = getenv($key);
        if ($value === false) {
            return $default;
        }
        $value = str_replace("\r", "", str_replace("\n", "", $value));
        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'empty':
            case '(empty)':
                return '';
            case 'null':
            case '(null)':
                return;
        }
        return $value;
    }
}

/**
 * 循环创建目录创建目录 递归创建多级目录
 * @param $dir
 * @param int $mode
 * @return bool
 */
if (!function_exists("CreateFolder")) {
    function CreateFolder($dir, $mode = 0777){
        if (is_dir($dir) || @mkdir($dir, $mode))
            return true;
        if (!CreateFolder(dirname($dir), $mode))
            return false;
        return @mkdir($dir, $mode);
    }
}

/**
 * 写日志
 * $file 是 保存的日志名称
 * file = game 或者 game-debug
 * 请勿自定义文件名称
 */
if (!function_exists("writeLog")) {
    function writeLog($log, $file = 'out') {
        $dir = dirname(__DIR__) . '\..\out\\';
        CreateFolder($dir);
        $of = @fopen($dir . "{$file}-" . date("Y-m-d-H") . ".txt", 'a+');
        @fwrite($of, $log . "\r\n");
        @fclose($of);
    }
}

/**
 * 公共curl方法
 * @param string $url
 * @param bool $get
 * @param array $data
 * @param bool $follow
 * @param array $cookieArray
 * @param string $body
 * @param string $head
 * @return mixed|string
 */
if (!function_exists('curlRequest')) {
    function curlRequest($url = '',$get = false,$data = [],$follow = false,&$cookieArray = [],&$body = '',&$head = '') {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLINFO_HEADER_OUT, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30); //单位 秒，也可以使用
        if (count($cookieArray) > 0) {
            $cookieStr = "";
            foreach ($cookieArray as $key => $value) {
                $cookieStr .= "{$key}={$value};";
            }
            curl_setopt($curl, CURLOPT_COOKIE, $cookieStr);
        }
        if ($get) {
            $getData = '';
            foreach ($data as $key => $value) {
                $getData .= "&{$key}=" . urlencode($value);
            }
            $getData = '?' . substr($getData, 1);
            curl_setopt($curl, CURLOPT_URL, $url . $getData);
            curl_setopt($curl, CURLOPT_HTTPGET, true);
        } else {
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
        }
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, $follow);
        curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/62.0.3202.94 Safari/537.36');
        $res = curl_exec($curl);
        if (curl_getinfo($curl, CURLINFO_HTTP_CODE) != 200) {
            curl_close($curl);
            $body = '';
            $head = '';
            return '';
        } else {
            $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
            $head = substr($res, 0, $headerSize);
            $body = substr($res, $headerSize);
            preg_match_all("/Set\-Cookie:\h([^\r\n]*);/", $head, $matches);
            if (count($matches) == 2) {
                foreach ($matches[1] as $match) {
                    $cok = explode('=', $match);
                    $cookieArray[$cok[0]] = $cok[1];
                }
            }
            curl_close($curl);
            return $res;
        }
    }
}

if (!function_exists('loadConfig')) {

    function loadConfig($fileName) {
        return require dirname(__DIR__) . '/../config/' . $fileName . '.php';
    }

}

if (!function_exists('isEmptyorNull')) {
    function isEmptyorNull($params)
    {
        return ($params === '' || $params === '无' || $params === '0') ? 0 : $params;
    }
}

if(!function_exists('sendMail')){
    function sendMail($subject = '',$body = ''){
        $mail_cfg = \Yii::$app->params['TicketNotify']['Email'];
        if(!isset($mail_cfg['from']) || !isset($mail_cfg['to'])){
            return false;
        }
        $mailer = Yii::$app->mailer->compose();
        $mailer->setFrom($mail_cfg['from']);
        $mailer->setTo($mail_cfg['to']);
        $mailer->setSubject($subject);
        $mailer->setHtmlBody($body);
        $res = $mailer->send();
        return $res;
    }
}

//返回当前的毫秒时间戳
if(!function_exists('millisecond')){
    function millisecond() {
        list($msec, $sec) = explode(' ', microtime());
        $milli = (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
        return $milli;
    }
}

//标准输入
if(!function_exists('sdt_input')) {
    function sdt_input($msg = '')
    {
        return \yii\helpers\Console::input($msg);
    }
}

//标准输出
if(!function_exists('sdt_output')) {
    function sdt_output($msg = '')
    {
        return \yii\helpers\Console::output($msg);
    }
}

//弹窗提示
if(!function_exists('alert')) {
    function alert($order = ''){
        $subject = iconv('utf-8','gb2312','温馨提示');
        $body = "恭喜您订票成功，订单号为：{$order}, 请立即打开浏览器登录12306，访问‘未完成订单’，在30分钟内完成支付！";
        $body = iconv('utf-8','gb2312',$body);
        exec('mshta vbscript:msgbox("'.$body.'",64,"'.$subject.'")(window.close)');
    }
}


