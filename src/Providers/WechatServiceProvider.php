<?php

namespace Formatcc\LaravelWechat\Providers;

use Formatcc\LaravelWechat\Lib\OpenWechat;
use Formatcc\LaravelWechat\Lib\Wechat;
use Illuminate\Support\ServiceProvider;

class WechatServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $config = array(
            'token' => env("WX_TOKEN"),
            'encodingaeskey' => env("WX_ENCODINGAESKEY"),
            'appid' => env("WX_APPID"),
            'appsecret' => env("WX_APPSECRET"),
            'wx_pay_mchid' => env("WX_PAY_MCHID"),
            'wx_pay_key' => env("WX_PAY_KEY"),
            'wx_pay_sslcert' => env("WX_PAY_SSLCERT"),
            'wx_pay_sslkey' => env("WX_PAY_SSLKEY"),
            'wx_pay_cainfo' => env("WX_PAY_CAINFO"),
        );

        app()->singleton('Wechat', function() use($config){
            return new Wechat($config);
        });

        $options = array(
            "appid"=>env("WX_OPEN_APPID"),
            "appsecret"=>env("WX_OPEN_APPSECRET"),
            "token"=>env("WX_OPEN_TOKEN"),
            "aeskey"=>env("WX_OPEN_AESKEY")
        );
        app()->singleton('OpenWechat', function() use($options){
            return new OpenWechat($options);
        });

    }
}
