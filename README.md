# arequest
arequest是php的curl简单封装请求，支持类似python的requests库支持session上下文关联请求，重定向也支持此功能

## 体验地址
* 简单的通信请求
```
 $curl = Curl::boot();
 $url = 'http://xxxx';
 $params = ['a'=>'aaa', 'b'=>'bbb'];
 $headers = ['a'=>'aaa', 'b'=>'bbb'];
 $cookies = ['a'=>'aaa', 'b'=>'bbb'];
 // option目前支持选项如下：
 // async=1异步
 // nobody=1不返回响应内容
 // resheader=1返回响应头部
 // rescookie=1返回响应cookie
 // timeout=60设置通信超时时间秒数，默认30
 // followlocation=1是否重定向
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
