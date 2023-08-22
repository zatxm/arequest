<?php
/**
 * 实用的错误类
 */
namespace Zatxm\ARequest;

class CurlErr
{
    public $code;
    public $message;
    public $data;

    /**
     * 构造方法
     * @param integer $code    错误代码
     * @param string  $message 错误信息
     * @param array   $data    附加数据
     */
    public function __construct($code = 0, $message = '', $data = [])
    {
        $this->code = $code;
        $this->message = $message;
        $this->data = $data;
    }

    /**
     * 静态初始化此类
     * @param integer $code    错误代码
     * @param string  $message 错误信息
     * @param array   $data    附加数据
     */
    public static function r($code = 0, $message = '', $data = [])
    {
        $error = new self;
        $error->code = $code;
        $error->message = $message;
        $error->data = $data;
        return $error;
    }

    /**
     * 判断对象是不是一个错误
     * @param  mixd $var 要判断的对象
     * @return boolean      
     */
    public static function is($var)
    {
        $class = get_called_class();
        return ($var instanceof $class);
    }
}
