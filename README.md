# arequest
arequest是php的curl简单封装请求，支持类似python的requests库支持session上下文关联请求，重定向也支持此功能，支持原生模拟浏览器TLS/JA3指纹的验证

## 使用说明
* 安装<br>
  composer require zatxm/arequest
* 2.0.0版本开始支持原生模拟浏览器TLS/JA3指纹的验证<br>
  要开启此功能，前往[curl-impersonate](https://github.com/lwthiker/curl-impersonate)下载安装，最简单的就是下载编译号的二进制包直接放到可以直接运行的目录如/usr/local/bin<br>
  $option['cmdopt'] = '/usr/local/bin/curl_edge101'; //此值为curl-impersonate执行脚本位置,根据实际情况调整<br>
  此功能会直接返回响应头部和cookie
* 简单的通信请求
```
 $curl = Curl::boot();
 $url = 'http://xxxx';
 $params = ['a'=>'aaa', 'b'=>'bbb'];
 $headers = ['a'=>'aaa', 'b'=>'bbb'];
 $cookies = ['a'=>'aaa', 'b'=>'bbb'];
 // option目前支持选项如下：
 // async=1异步
 // nobody=1不返回响应内容，cmdopt不支持此配置
 // resheader=1返回响应头部，cmdopt一直返回
 // rescookie=1返回响应cookie，cmdopt一直返回
 // timeout=60设置通信超时时间秒数，默认30
 // followlocation=1是否重定向，cmdopt不支持此配置
 // cmdopt=curl_edge101 支持原生模拟浏览器TLS/JA3指纹的验证
 $option = ['nobody'=>1, 'resheader'=>1];
 $res = $curl->url($url)
 	->method('POST')
 	->params($params)
 	->header($headers)
 	->cookie($cookies)
 	->option($option)
 	->go();
 if (CurlErr::is($res)) {
     print_r($res);
     exit;
 }
 print_r($res);
```

* 支持同个session的通信请求
```
 $curl = CurlOfSession::boot();
 $url1 = 'http://xxxx';
 $params = ['a'=>'aaa', 'b'=>'bbb'];
 $headers = ['a'=>'aaa', 'b'=>'bbb'];
 $cookies = ['a'=>'aaa', 'b'=>'bbb'];
 $option = ['nobody'=>1, 'resheader'=>1];
 $res = $curl->url($url)
 	->method('POST')
 	->params($params)
 	->header($headers)
 	->cookie($cookies)
 	->option($option)
 	->go();
 if (CurlErr::is($res)) {
     print_r($res);
     exit;
 }
 print_r($res);
 $url2 = 'http://xxxx';
 $params = ['a'=>'aaa', 'b'=>'bbb'];
 $headers = ['a'=>'aaa', 'b'=>'bbb'];
 $cookies = ['a'=>'aaa', 'b'=>'bbb'];
 $option = ['nobody'=>1, 'resheader'=>1];
 $res = $curl->url($url)
 	->method('POST')
 	->params($params)
 	->header($headers)
 	->cookie($cookies)
 	->option($option)
 	->go();
 if (CurlErr::is($res)) {
     print_r($res);
     exit;
 }
 print_r($res);
```
