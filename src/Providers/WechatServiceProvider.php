<?php

namespace Formatcc\LaravelWechat\Providers;

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
            'appsecret' => env("WX_APPSECRET")
        );

        app()->singleton('Wechat', function() use($config){
            return new Wechat($config);
        });

    }
}
