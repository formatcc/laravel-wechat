# Laravel 微信开发库
---
### 安装
	composer require formatcc/laravel-wechat

### 配置
在laravel的.env环境配置文件中添加一下配置项

```
#微信公众号相关
WX_APPID="微信公众号APPID"
WX_APPSECRET="微信公众号APPSECRET"
WX_TOKEN="微信公众号TOKEN"
WX_ENCODINGAESKEY="微信公众号ENCODINGAESKEY"
#微信支付相关
WX_PAY_MCHID="微信支付商户号"
WX_PAY_KEY="微信支付KEY"
WX_PAY_SSLCERT="微信支付cert证书绝对地址"
WX_PAY_SSLKEY="微信支付key证书绝对地址"
WX_PAY_CAINFO="微信支付ca证书绝对地址"
#微信第三方平台
WX_OPEN_APPID = "微信第三方平台APPID"
WX_OPEN_APPSECRET = "微信第三方平台APPSECRET"
WX_OPEN_TOKEN = "微信第三方平台ATOKEN"
WX_OPEN_AESKEY = "微信第三方平台AESKEY"	
```

# 使用
注册provider

	\Formatcc\LaravelWechat\Providers\WechatServiceProvider::class,


## 公众号直接调用接口
通过容器获取微信操作示例

```
//获取微信实例
$wechat = app("Wechat");
//验证消息前面并解密
$wechat->valid();

//接收用户发送到微信的数据
$receive = $wechat->getRev()->getRevData();

//回复消息
return $wechat->text("hello".$appid)->reply(null, true);
```

## 公众号第三平台代公众号实现接口
通过容器获取微信操作示例

```
$wechat = app("OpenWechat");
//验证消息前面并解密
$wechat->valid();

//接收用户发送到微信的数据
$receive = $wechat->getRev()->getRevData();

//回复消息
return $wechat->text("hello".$appid)->reply(null, true);

```

### 1、生成授权链接地址

```
Route::get("/oauth/openwechat/getUrl", function(){
	$openWechat = app("OpenWechat");

	$url = $openWechat->getOauthUrl(url("/oauth/openwechat/auth"));
	if($url){
		echo "<a href='{$url}'>点击授权</a>";
	}else{
		echo "授权链接生成失败";
	}

});

```

### 2、认证回调地址 获取认证公众号信息

```
Route::get("/oauth/openwechat/auth", function(){
	$openWechat = app("OpenWechat");
	$token = $openWechat->getAuthorizerToken();

	header("text/html; charset=utf8");
	echo "<h1>Token信息</h1>";
	echo "authorizer_appid: ".$token['authorizer_appid']."<br/>";
	echo "authorizer_access_token: ".$token['authorizer_access_token']."<br/>";
	echo "expires_in: ".$token['expires_in']."<br/>";
	echo "authorizer_refresh_token: ".$token['authorizer_refresh_token']."<br/>";


	$rules = array(
			"未知",
			"消息管理权限",
			"用户管理权限",
			"帐号服务权限",
			"网页服务权限",
			"微信小店权限",
			"微信多客服权限",
			"群发与通知权限",
			"微信卡券权限",
			"微信扫一扫权限",
			"微信连WIFI权限",
			"素材管理权限",
			"微信摇周边权限",
			"微信门店权限",
			"微信支付权限",
			"自定义菜单权限"
	);
	echo "<h1>已获得权限信息</h1>";
	foreach($token['func_info'] as $scope){
		$id = $scope['funcscope_category']['id'];
		echo $rules[$id]."<br/>";
	}
	
	$info = $openWechat->getAuthorizerInfo($token['authorizer_appid']);

	if ($info && !empty($info['user_name'])) {
		echo "<h1>公众号信息</h1>";
		echo '公众号('.$info['user_name'].') 授权成功！';
		echo '您的公众号信息如下：<br /><br />';
		echo '原始ID：'.$info['user_name'].'<br />';
		echo '昵称：'.$info['nick_name'].'<br />';
		echo '别名：'.$info['alias'].'<br />';
		echo '头像：<img width=100 height=100 src="'.$info['head_img'].'"/><br />';
		echo '头像URL：'.$info['head_img'].'<br />';
		echo '二维码：<img width=100 height=100 src="'.$info['qrcode_url'].'"/><br />';
		echo '二维码URL：'.$info['qrcode_url'].'<br />';
	}

});


```

### 3、授权通知地址

```
	$openWechat = app("OpenWechat");
	//自定义接收ticket事件
	$openWechat->handleTicket(function($data){
		return true;
	});

	//自定义授权通知事件
	$openWechat->handleAuthorized(function($data){
//		$AuthorizerAppid = $data['AuthorizerAppid'];
//		echo "授权通知<br/>";
//		echo "AuthorizerAppid:".$AuthorizerAppid;
		return true;
	});

	//自定义授权更新事件
	$openWechat->handleUpdateAuthorized(function($data){
//		$AuthorizerAppid = $data['AuthorizerAppid'];
//		echo "授权更新通知<br/>";
//		echo "AuthorizerAppid:".$AuthorizerAppid;
		return true;
	});

	//自定义授权取消事件
	$openWechat->handleUnAuthorized(function($data){
//		$AuthorizerAppid = $data['AuthorizerAppid'];
//		echo "授权取消通知<br/>";
//		echo "AuthorizerAppid:".$AuthorizerAppid;
		return true;
	});

	return $openWechat->notify();
```