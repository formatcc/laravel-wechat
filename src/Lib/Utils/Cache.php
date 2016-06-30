<?php
/**
 * Created by PhpStorm.
 * User: yangtao
 * Date: 16/6/29
 * Time: 下午3:51
 */

namespace Formatcc\LaravelWechat\Lib\Utils;


class Cache
{
    /**
     * 设置缓存
     * @param string $cachename
     * @param mixed $value
     * @param int $expired
     * @return boolean
     */
    public static function setCache($cachename,$value,$expired=null){
        $data = array(
            'value' => $value
        );

        if($expired){
            $data['expires_at'] = time()+$expired;

        }
        \Cache::forever($cachename, json_encode($data));
    }


    /**
     * 获取缓存
     * @param string $cachename
     * @return mixed
     */
    public static function getCache($cachename){

        if($token =\Cache::get($cachename)){
            $token = json_decode($token, true);
            if(isset($token['expires_at'])){
                if ($token['expires_at'] > time()){
                    return $token['value'];
                }else{
                    return false;
                }
            }else{
                return $token['value'];
            }
        }

        return false;
    }

    /**
     * 清除缓存
     * @param string $cachename
     * @return boolean
     */
    public static function removeCache($cachename){
        //TODO: remove cache implementation
        return \Cache::pull($cachename);
    }
}