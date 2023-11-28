<?php
/**
 * 同session的curl请求
 * 合并cookie带入下次请求中
 * 重定向也实现功能
 */
namespace Zatxm\ARequest;

use Zatxm\ARequest\Curl;
use Zatxm\ARequest\CurlErr;

class CurlOfSession
{
    private static $instances = null; //本实例
    private $curl = null; //curl实例
    private $url = ''; //请求url
    private $method = 'GET'; //请求方式,默认GET
    private $header = []; //请求头
    private $cookie = []; //请求cookie
    private $params = []; //请求参数
    private $option = []; //额外信息
    private $maxRedirs = -8; //重定向次数
    private $allowRedirect = false; //是否重定向
    private $location = ''; //重定向url
    private $old = []; //历史数据
    private $shareOption = []; //全局option
    private $shareHeaders = [];

    private function __construct() {}

    /**
     * 单实例初始化类
     * @return this
     */
    public static function boot($key = 'one')
    {
        if (isset(self::$instances[$key])) {
            return self::$instances[$key];
        }
        $instance = new self;
        $instance->curl = Curl::boot();
        self::$instances[$key] = $instance;
        return $instance;
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
        $this->cookie = array_merge($this->cookie, $cookie);
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
     * 获取请求后cookie
     * @return array
     */
    public function getCookie()
    {
        return $this->cookie;
    }

    /**
     * 删除某个cookie
     * @param  string $key cookie的key值
     * @return this
     */
    public function removeOneCookie($key)
    {
        if (isset($this->cookie[$key])) {
            unset($this->cookie[$key]);
        }
        return $this;
    }

    /**
     * 设置无限制性cookie
     * @return this
     */
    public function setCookieAllLimit()
    {
        if ($this->cookie) {
            foreach ($this->cookie as $k => $v) {
                if (strpos($v, '; ') !== false) {
                    $this->cookie[$k] = explode('; ', $v)[0];
                }
            }
        }
        return $this;
    }

    /**
     * 设置全局option
     * @param  array  $option 全局option
     * @return this
     */
    public function shareOption($option = [])
    {
        $this->shareOption = $option;
        return $this;
    }

    /**
     * 获取全局option
     * @return array  $option 全局option
     */
    public function getShareOption()
    {
        return $this->shareOption;
    }

    /**
     * 设置全局header
     * @param  array  $option 全局option
     * @return this
     */
    public function shareHeaders($headers = [])
    {
        $this->shareHeaders = $headers;
        return $this;
    }

    /**
     * 请求
     * 返回[
     *         'data'   => ['code'=>'状态码', 'msg'=>'内容', 'location'=>'重定向url'],
     *         'cookie' => 'rescookie设置为1时返回响应cookie数组',
     *         'header' => 'resheader设置为1时返回响应header数组'
     *     ]
     * @return array|CurlErr
     */
    public function go()
    {
        $this->option = array_merge($this->shareOption, $this->option);
        $this->header = array_merge($this->shareHeaders, $this->header);
        // 总是返回header和cookie,cookie会带入下次请求
        $this->option['resheader'] = $this->option['rescookie'] = 1;
        // 是否有重定向
        $this->allowRedirect = $this->allowRedirect ?: (!empty($this->option['followlocation']) ? true : false);
        if ($this->allowRedirect) {
            unset($this->option['followlocation']);
            if (!empty($this->option['maxredirs'])) {
                unset($this->option['maxredirs']);
                $maxredirs = intval($this->option['maxredirs']);
                if ($maxredirs > 0) {
                    $this->maxRedirs = $maxredirs;
                }
            }
        }

        // 请求
        $res = $this->curl
            ->url($this->url)
            ->method($this->method)
            ->params($this->params)
            ->header($this->header)
            ->cookie($this->cookie)
            ->option($this->option)
            ->go();
        if (CurlErr::is($res)) {
            $this->clear();
            return $res;
        }

        // 处理返回的cookie
        $this->cookie = array_merge($this->cookie, $res['cookie']);

        // 处理重定向
        while ($this->allowRedirect &&
            ($this->maxRedirs == -8 || $this->maxRedirs > 0) &&
            isset($res['data']['code'], $res['header']) &&
            in_array($res['data']['code'], [301, 302])
        ) {
            $location = '';
            foreach ($res['header'] as $v) {
                if (isset($v['location'])) {
                    $location = $v['location'];
                    break;
                }
            }
            if (!$location) {
                break;
            }
            $url = $this->url;
            $this->clear(1);
            $urlParse = parse_url($location);
            if (!isset($urlParse['host'])) {
                $urlParse = parse_url($url);
                $location = $urlParse['scheme'] . '://' . $urlParse['host'] . $location;
            }
            $res = $this->url($location)->method('GET')->go();
            if (CurlErr::is($res)) {
                break;
            }
            $res['data']['location'] = $location;
            if ($this->maxRedirs != -8) {
                -- $this->maxRedirs;
            }
        }
        
        $this->clear();
        return $res;
    }

    /**
     * 清理请求数据
     * @param  integer $type 处理类型0全部 1重定向部分清理
     * @return this
     */
    public function clear($type = 0)
    {
        $this->old = [
            'url'    => $this->url,
            'method' => $this->method,
            'header' => $this->header,
            'cookie' => $this->cookie,
            'params' => $this->params,
            'option' => $this->option,
            'maxRedirs' => $this->maxRedirs,
            'allowRedirect' => $this->allowRedirect,
            'location'      => $this->location
        ];
        $this->url = '';
        $this->method = 'GET';
        $this->params = [];
        switch ($type) {
            case 1: //重定向部分清理
                if ($this->allowRedirect) {
                    if ($this->maxRedirs == 0) {
                        $this->allowRedirect = false;
                        $this->maxRedirs = -8;
                    }
                } else {
                    $this->maxRedirs = -8;
                }
                break;
            default:
                $this->allowRedirect = false;
                $this->maxRedirs = -8;
                $this->header = [];
                $this->location = '';
                $this->option = [];
                break;
        }
        return $this;
    }
}
