<?php

/**
 * Created by PhpStorm.
 * User: yangtao
 * Date: 16/6/29
 * Time: 上午10:22
 */
namespace Formatcc\LaravelWechat\Lib;
use Formatcc\LaravelWechat\Lib\Utils\Cache;
use Formatcc\LaravelWechat\Lib\Utils\Http;
use Formatcc\LaravelWechat\Lib\Utils\WXBizMsgCrypt;

class OpenWechat extends Wechat
{

    protected $appid;
    protected $appsecret;
    protected $token;
    protected $aeskey;

    //签名参数
    private $_timestamp;
    private $_nonce;
    private $_msg_signature;

    //接收到的数据
    private $_receive;

    private $_component_verify_ticket_handler;
    private $_authorized_handler;
    private $_updateauthorized_handler;
    private $_unauthorized_handler;


    public function __construct($options)
    {
        $this->appid = isset($options['appid'])?$options['appid']:'';
        $this->appsecret = isset($options['appsecret'])?$options['appsecret']:'';
        $this->token = isset($options['token'])?$options['token']:'';
        $this->encodingAesKey = isset($options['aeskey'])?$options['aeskey']:'';
        $this->debug = isset($options['debug'])?$options['debug']:false;

        $this->_msg_signature = isset($_GET['msg_signature'])?$_GET['msg_signature']:'';
        $this->_timestamp = isset($_GET['timestamp'])?$_GET['timestamp']:'';
        $this->_nonce = isset($_GET['nonce'])?$_GET['nonce']:'';
    }


    /**
     * 授权通知url接口
     * @return string
     */
    public function notify(){

        $data = $this->_parseMsg();

        $infoType = $data['InfoType'];
        $ret = true;

        switch($infoType){
            case 'component_verify_ticket':{
                //缓存ticket
                Cache::setCache("component_verify_ticket_".$data['AppId'], $data['ComponentVerifyTicket']);

                if(is_callable($this->_component_verify_ticket_handler)){
                    $ret = call_user_func($this->_component_verify_ticket_handler, $data);
                }
                break;
            }
            case 'authorized':{
                if(is_callable($this->_authorized_handler)){
                    $ret = call_user_func($this->_authorized_handler, $data);
                }
                break;
            }
            case 'updateauthorized':{
                if(is_callable($this->_updateauthorized_handler)){
                    $ret = call_user_func($this->_updateauthorized_handler, $data);
                }
                break;
            }
            case 'unauthorized':{
                if(is_callable($this->_unauthorized_handler)){
                    $ret = call_user_func($this->_unauthorized_handler, $data);
                }
                break;
            }

        }

        return $ret?"success":"error";
    }

    /**
     * 通知ticket处理函数
     * @param callable $callback
     */
    public function handleTicket(callable $callback){
        $this->_component_verify_ticket_handler = $callback;
    }

    /**
     * 授权成功处理函数
     * @param callable $callback
     */
    public function handleAuthorized(callable $callback){
        $this->_authorized_handler = $callback;
    }

    /**
     * 更新授权处理函数
     * @param callable $callback
     */
    public function handleUpdateAuthorized(callable $callback){
        $this->_updateauthorized_handler = $callback;
    }

    /**
     * 取消授权处理函数
     * @param callable $callback
     */
    public function handleUnAuthorized(callable $callback){
        $this->_unauthorized_handler = $callback;
    }

    /**
     * 解密并解析接收到的消息
     * @return bool
     */
    private function _parseMsg(){
        $postStr = file_get_contents('php://input');

        if (!empty($postStr)) {
            $this->_receive = (array)simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
        }

        //加密的数据
        $encrypt = $this->_receive['Encrypt'];

        //解密
        $decrypt_msg = '';
        $encrypt_ing = "<xml><ToUserName><![CDATA[toUser]]></ToUserName><Encrypt><![CDATA[{$encrypt}]]></Encrypt></xml>";

        $pc = new WXBizMsgCrypt($this->token, $this->encodingAesKey, $this->appid);
        $code = $pc->decryptMsg($this->_msg_signature, $this->_timestamp, $this->_nonce, $encrypt_ing, $decrypt_msg);
        if($code){
            throw new \Exception("消息解密失败, code:".$code);
        }

        $data = (array)simplexml_load_string($decrypt_msg, 'SimpleXMLElement', LIBXML_NOCDATA);
        return $data;
    }

    /**
     * 获取第三方平台component_access_token
     * @return bool
     */
    public function getComponentToken() {
        $token = Cache::getCache("component_access_token_".$this->appid);
        if($token){
            return $token;
        }

        $url = 'https://api.weixin.qq.com/cgi-bin/component/api_component_token';
        $ticket = Cache::getCache("component_verify_ticket_".$this->appid);

        if(!$ticket){
            throw new \Exception("component_verify_ticket 获取失败");
        }

        $arg = array(
            'component_appid' => $this->appid,
            'component_appsecret' => $this->appsecret,
            'component_verify_ticket' => $ticket
        );

        $ret = $this->postData($url, $arg);

        if ($ret && !empty($ret['component_access_token'])) {
            Cache::setCache("component_access_token_".$this->appid, $ret['component_access_token'], $ret['expires_in']-120);
            $token = $ret['component_access_token'];
            return $token;
        }
        return false;
    }

    /**
     * 获取预授权码pre_auth_code
     * @return bool
     */
    private function getPreAuthcode() {
        $token = $this->getComponentToken();
        if(!$token){
            throw new \Exception("token 获取失败");
        }

        $url = 'https://api.weixin.qq.com/cgi-bin/component/api_create_preauthcode?component_access_token='.$token;
        $arg = array(
            'component_appid' => $this->appid,
        );
        $ret = $this->postData($url, $arg);

        if ($ret && !empty($ret['pre_auth_code'])) {
            $code = $ret['pre_auth_code'];
            return $code;
        }
        return false;
    }

    /**
     * 获取授权认证URL地址
     * @param $redirect 回调地址
     * @return string
     */
    public function getOauthUrl($redirect){
        $baseUrl = 'https://mp.weixin.qq.com/cgi-bin/componentloginpage?component_appid=%s&pre_auth_code=%s&redirect_uri=%s';
        $precode = $this->getPreAuthcode();
        if(!$precode){
            throw new \Exception("preauthcode 获取失败");
        }
        return sprintf($baseUrl, $this->appid, $precode, urlencode($redirect));
    }

    /**
     * 获取指定已授权公众号的信息
     * @param $appid
     * @return bool
     */
    public function getAuthorizerInfo($appid) {
        $token = $this->getComponentToken();
        if(!$token){
            throw new \Exception("component_token 获取失败");
        }

        $url = 'https://api.weixin.qq.com/cgi-bin/component/api_get_authorizer_info?component_access_token='.$token;
        $arg = array(
            'component_appid' => $this->appid,
            'authorizer_appid' => $appid,
        );

        $ret = $this->postData($url, $arg);

        if ($ret && !empty($ret['authorizer_info'])) {
            return $ret['authorizer_info'];
        }
        return false;
    }

    /**
     * 获取授权公众号的令牌
     * @return bool
     * @throws \Exception
     */
    public function getAuthorizerToken() {
        $auth_code = isset($_GET['auth_code']) ? $_GET['auth_code'] :'';
        $token = $this->getComponentToken();
        if(!$token){
            throw new \Exception("component_token 获取失败");
        }

        $url = 'https://api.weixin.qq.com/cgi-bin/component/api_query_auth?component_access_token='.$token;
        $arg = array(
            'component_appid' => $this->appid,
            'authorization_code' => $auth_code,
        );
        $ret = $this->postData($url, $arg);

        if ($ret && !empty($ret['authorization_info'])) {
            return $ret['authorization_info'];
        }
        return false;
    }

    /**
     * 刷新授权公众号令牌
     * @param $authorizer_appid 授权方appid
     * @param $authorizer_refresh_token 授权方的刷新令牌，刷新令牌主要用于公众号第三方平台获取和刷新已授权用户的access_token，只会在授权时刻提供，请妥善保存。一旦丢失，只能让用户重新授权，才能再次拿到新的刷新令牌
     * @return bool
     * @throws \Exception
     */
    public function refreshAuthorizerToken($authorizer_appid, $authorizer_refresh_token) {
        $token = $this->getComponentToken();
        if(!$token){
            throw new \Exception("component_token 获取失败");
        }

        $url = 'https://api.weixin.qq.com/cgi-bin/component/api_authorizer_token?component_access_token='.$token;
        $arg = array(
            'component_appid' => $this->appid,
            'authorizer_appid' => $authorizer_appid,
            'authorizer_refresh_token'=>$authorizer_refresh_token
        );
        $ret = $this->postData($url, $arg);

        if ($ret) {
            return $ret;
        }
        return false;
    }

    /**
     * 获取授权公众号的选项设置信息
     * @param $authorizer_appid 授权公众号appid
     * @param $option_name 选项名称
     * @return bool|mixed
     * @throws \Exception
     */
    public function getAuthorizerOption($authorizer_appid, $option_name) {
        $token = $this->getComponentToken();
        if(!$token){
            throw new \Exception("component_token 获取失败");
        }

        $url = 'https://api.weixin.qq.com/cgi-bin/component/api_get_authorizer_option?component_access_token='.$token;
        $arg = array(
            'component_appid' => $this->appid,
            'authorizer_appid' => $authorizer_appid,
            'option_name'=>$option_name
        );
        $ret = $this->postData($url, $arg);

        if ($ret) {
            return $ret;
        }
        return false;
    }

    /**
     * 设置授权公众号的选项信息
     * @param $authorizer_appid 授权公众号appid
     * @param $option_name 选项名称
     * @return bool|mixed
     * @throws \Exception
     * 选项参数
     * location_report(地理位置上报选项)
     *     0	无上报
     *     1	进入会话时上报
     *     2	每5s上报
     * voice_recognize（语音识别开关选项）
     *     0	关闭语音识别
     *     1	开启语音识别
     * customer_service（多客服开关选项）
     *     0	关闭多客服
     *     1	开启多客服
     */
    public function setAuthorizerOption($authorizer_appid, $option_name, $option_value) {
        $token = $this->getComponentToken();
        if(!$token){
            throw new \Exception("component_token 获取失败");
        }

        $url = 'https://api.weixin.qq.com/cgi-bin/component/api_set_authorizer_option?component_access_token='.$token;
        $arg = array(
            'component_appid' => $this->appid,
            'authorizer_appid' => $authorizer_appid,
            'option_name'=>$option_name,
            'option_value'=>$option_value
        );
        $ret = $this->postData($url, $arg);

        if ($ret && $ret['errcode'] == 0) {
            return true;
        }
        return false;
    }

    /**
     * post数据
     * @param $url
     * @param $arg
     * @return mixed
     * @throws \Exception
     */
    private function postData($url, $arg){
        $resp = Http::post($url, json_encode($arg));
        $ret = json_decode($resp, true);
        if(isset($ret['errcode']) && $ret['errcode']!=0){
            throw new \Exception($resp);
        }
        return $ret;
    }


}