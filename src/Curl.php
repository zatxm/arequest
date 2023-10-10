<?php
/**
 * 封装的curl请求
 */
namespace Zatxm\ARequest;

use Zatxm\ARequest\CurlErr;

class Curl
{
    private static $instance = null; //本实例
    private $url = ''; //请求url
    private $method = 'GET'; //请求方式,默认GET
    private $header = []; //请求头
    private $cookie = []; //请求cookie
    private $params = []; //请求参数
    private $option = []; //额外信息
    private $old = []; //历史数据

    private function __construct() {}

    /**
     * 单实例初始化类
     * @return this
     */
    public static function boot()
    {
        if (self::$instance === null) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    /**
     * 设置请求url
     * @param  string $url 请求url
     * @return this
     */
    public function url($url)
    {
        $this->url = $url;
        return $this;
    }

    /**
     * 设置请求方式
     * @param  string $method 请求方式如GET|POST
     * @return this
     */
    public function method($method = 'GET')
    {
        $this->method = $method;
        return $this;
    }

    /**
     * 设置请求头
     * @param  array $header 请求头数组
     * @return this
     */
    public function header($header = [])
    {
        $this->header = $header;
        return $this;
    }

    /**
     * 设置cookie请求
     * @param  array $cookie cookie数组
     * @return this
     */
    public function cookie($cookie = [])
    {
        $this->cookie = $cookie;
        return $this;
    }

    /**
     * 设置请求参数
     * 一般为POST|PUT|DELETE才需此参数
     * @param  array $params 请求头数组
     * @return this
     */
    public function params($params = [])
    {
        $this->params = $params;
        return $this;
    }

    /**
     * 额外设置
     * @param  array  $option 设置数组
     * @return this
     */
    public function option($option = [])
    {
        $this->option = $option;
        return $this;
    }

    /**
     * 处理原生模拟浏览器TLS/JA3指纹的验证
     * https://github.com/lwthiker/curl-impersonate
     * @return mixed
     */
    private function goCmdopt()
    {
        $command = $this->option['cmdopt'];

        // 请求头
        $method = $this->method ?: ($this->option['method'] ?? ($this->option['p'][CURLOPT_CUSTOMREQUEST] ?? 'GET'));
        $command .= " -X {$method}";

        // 处理参数
        // 如果是get、option不要传此值
        $params = $this->params ?: ($this->option['params'] ?? null);
        if (!empty($params)) {
            if (is_array($params)) {
                $command .= " -d '" . http_build_query($params, '', '&', PHP_QUERY_RFC3986) . "'";
            } elseif (is_string($params)) {
                $command .= " -d '{$params}'";
            }
        } elseif (!empty($this->option['p'][CURLOPT_POSTFIELDS])) {
            $command .= " -d '" . $this->option['p'][CURLOPT_POSTFIELDS] . "'";
        }

        // 处理请求头
        $reqHeaders = $this->header ?: ($this->option['header'] ?? null);
        if ($reqHeaders && is_array($reqHeaders)) {
            foreach ($reqHeaders as $k => $v) {
                switch ($k) {
                    case 'User-Agent': //此模式已经有了无需设置
                        break;
                    case 'Referer':
                        $command .= ' -e "' . $v . '"';
                        break;
                    default:
                        $command .= ' -H "' . $k . ': ' . $v . '"';
                        break;
                }
            }
        } elseif (!empty($this->option['p'][CURLOPT_HTTPHEADER])) {
            foreach ($this->option['p'][CURLOPT_HTTPHEADER] as $v) {
                $command .= ' -H "' . $v . '"';
            }
        }

        // 设置cookie
        $cookies = $this->cookie ?: ($this->option['cookie'] ?? null);
        if ($cookies) {
            if (is_string($cookies) && strpos($cookies, '; ') !== false) {
                $cookies = explode('; ', $cookies);
            }
            if (is_array($cookies)) {
                foreach ($cookies as $k => $v) {
                    $command .= ' -b "' . $k . '=' . $v . '"';
                }
            }
        } elseif (!empty($this->option['p'][CURLOPT_COOKIE])) {
            $command .= ' -b "' . $this->option['p'][CURLOPT_COOKIE] . '"';
        }

        // 是否异步请求并设置超时时间
        $async = false;
        if (!empty($this->option['async'])) {
            $async = true;
        } else {
            $timeout = !empty($this->option['timeout']) ? intval($this->option['timeout']) : 30;
            $command .= " --connect-timeout {$timeout}";
        }

        // 设置代理
        if (!empty($this->option['proxy'])) {
            $command .= ' -x "' . $this->option['proxy'] . '"';
        } elseif (!empty($this->option['p'][CURLOPT_PROXY])) {
            $command .= ' -x "' . $this->option['p'][CURLOPT_PROXY] . '"';
        }

        $command .= ' -k -i "' . $this->url . '"';
        $this->clear();
        $handle = popen($command, 'rb');
        if (!$handle) {
            return new CurlErr(-1000, 'popen error');
        }
        if ($async) {
            pclose($handle);
            return ['async'=>1]; //异步
        }
        $res = stream_get_contents($handle);
        pclose($handle);

        // 处理返回数据
        if (strpos($res, "\r\n\r\n") === false) {
            return new CurlErr(-1001, 'return context error', $res);
        }
        $res = explode("\r\n\r\n", $res, 2);
        $data = ['data'=>['code'=>-1, 'msg'=>$res[1]]];
        $resHeaders = $resCookies = [];
        foreach (explode("\r\n", $res[0]) as $v) {
            if (strpos($v, ': ')) {
                // 过滤掉状态码，如HTTP/2 200
                $v = explode(': ', $v, 2);
                $resHeaders[] = [$v[0]=>$v[1]];
                if ($v[0] == 'set-cookie') {
                    $c = explode('=', $v[1], 2);
                    $resCookies[$c[0]] = $c[1];
                }
            } elseif (strpos($v, 'HTTP/') !== false) {
                $data['data']['code'] = explode(' ', $v, 2)[1];
            }
        }
        $data['cookie'] = $resCookies;
        $data['header'] = $resHeaders;
        return $data;
    }

    /**
     * 请求
     * 返回[
     *         'async'  => 1, //异步返回
     *         'data'   => ['code'=>'状态码', 'msg'=>'内容', 'location'=>'重定向url'],
     *         'cookie' => 'rescookie设置为1时返回响应cookie数组',
     *         'header' => 'resheader设置为1时返回响应header数组'
     *     ]
     * @return array|CurlErr
     */
    public function go()
    {
        set_time_limit(0);

        // 处理原生模拟浏览器TLS/JA3指纹的验证
        if (!empty($this->option['cmdopt'])) {
            return $this->goCmdopt();
        }

        $ch = curl_init($this->url);

        /***处理请求各种curl option***/
        // 支持原生配置只需传个p数组，但可能会被以下参数覆盖
        $options = $this->option['p'] ?? [];

        $options[CURLOPT_RETURNTRANSFER] = true;
        $options[CURLOPT_FAILONERROR] = false;

        // 是否异步请求并设置超时时间
        $async = false;
        if (!empty($this->option['async'])) {
            $async = true;
            if (!defined('CURLOPT_TIMEOUT_MS')) {
                define('CURLOPT_TIMEOUT_MS', 155);
            }
            $options[CURLOPT_NOSIGNAL] = 1;
            $options[CURLOPT_TIMEOUT_MS] = 200;
        } else {
            $timeout = !empty($this->option['timeout']) ? intval($this->option['timeout']) : 30;
            $options[CURLOPT_TIMEOUT] = $timeout;
        }

        // https请求
        if (strlen($this->url) > 5 && strtolower(substr($this->url, 0, 5)) == 'https') {
            $options[CURLOPT_SSL_VERIFYPEER] = false;
            $options[CURLOPT_SSL_VERIFYHOST] = false;
        }

        // 处理请求头
        $reqHeaders = $this->header ?: ($this->option['header'] ?? null);
        if ($reqHeaders && is_array($reqHeaders)) {
            $header = [];
            foreach ($reqHeaders as $k => $v) {
                switch ($k) {
                    case 'User-Agent':
                        $options[CURLOPT_USERAGENT] = $v;
                        break;
                    case 'Referer':
                        $options[CURLOPT_REFERER] = $v;
                        break;
                    default:
                        $header[] = "{$k}: {$v}";
                        break;
                }
            }
            if ($header) {
                $options[CURLOPT_HTTPHEADER] = $header;
            }
        }

        // 处理参数
        $params = $this->params ?: ($this->option['params'] ?? null);
        if (!empty($params)) {
            if (is_array($params)) {
                $options[CURLOPT_POST] = true;
                $options[CURLOPT_POSTFIELDS] = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
            } elseif(is_string($params)) {
                $options[CURLOPT_POST] = true;
                $options[CURLOPT_POSTFIELDS] = $params;
            }
        }

        // 是否返回响应头部或cookie
        $resHeaders = $resCookies = [];
        if (!empty($this->option['resheader']) || !empty($this->option['rescookie'])) {
            $options[CURLOPT_HEADERFUNCTION] = function($ch, $header) use (&$resHeaders, &$resCookies) {
                foreach (explode("\r\n", $header) as $v) {
                    if (strpos($v, ': ')) {
                        // 过滤掉状态码，如HTTP/2 200
                        $v = explode(': ', $v, 2);
                        $resHeaders[] = [$v[0]=>$v[1]];
                        if ($v[0] == 'set-cookie') {
                            $c = explode('=', $v[1], 2);
                            $resCookies[$c[0]] = $c[1];
                        }
                    }
                }
                return strlen($header);
            };
        }

        // 不输出BODY部分
        if (!empty($this->option['nobody'])) {
            $options[CURLOPT_NOBODY] = true;
        }

        // 允许请求的链接跳转
        if (!empty($this->option['followlocation'])) {
            $options[CURLOPT_FOLLOWLOCATION] = true;
        }

        // 设置cookie
        $cookies = $this->cookie ?: ($this->option['cookie'] ?? null);
        if ($cookies) {
            if (is_array($cookies)) {
                $reqCookies = [];
                foreach ($cookies as $k => $v) {
                    $reqCookies[] = $k . '=' . $v;
                }
                $options[CURLOPT_COOKIE] = implode('; ', $reqCookies);
            } elseif (is_string($cookies)) {
                $options[CURLOPT_COOKIE] = $cookies;
            }
        }

        // 处理数据流
        if (!empty($this->option['stream'])) {
            $options[CURLOPT_WRITEFUNCTION] = $this->option['stream'];
        }

        // 设置代理
        if (!empty($this->option['proxy'])) {
            $options[CURLOPT_PROXY] = $this->option['proxy'];
        }

        // 设置请求方式
        $method = $this->method ?: ($this->option['method'] ?? 'GET');
        $options[CURLOPT_CUSTOMREQUEST] = $method;
        /***处理请求各种curl option结束***/

        curl_setopt_array($ch, $options);
        $res = curl_exec($ch);
        $no = curl_errno($ch);
        if ($no) {
            $this->clear();
            if ($async && $no == 28) {
                // 异步的时候不检查错误
                return ['async'=>1];
            }
            return new CurlErr($no, curl_error($ch));
        }
        $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $data = ['data'=>['code'=>$httpStatusCode, 'msg'=>$res]];

        // 处理返回的cookie
        if (!empty($this->option['rescookie'])) {
            $data['cookie'] = $resCookies;
        }

        // 处理返回的header
        if (!empty($this->option['resheader'])) {
            $data['header'] = $resHeaders;
        }

        // 获取最终请求的url地址
        if (!empty($this->option['followlocation'])) {
            $redirectUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
            $data['data']['location'] = $redirectUrl;
        }

        curl_close($ch);
        $this->clear();
        return $data;
    }

    /**
     * 清理请求数据
     * @return this
     */
    public function clear()
    {
        $this->old = [
            'url'    => $this->url,
            'method' => $this->method,
            'header' => $this->header,
            'cookie' => $this->cookie,
            'params' => $this->params,
            'option' => $this->option
        ];
        $this->url = '';
        $this->method = 'GET';
        $this->header = [];
        $this->cookie = [];
        $this->params = [];
        $this->option = [];
        return $this;
    }
}
