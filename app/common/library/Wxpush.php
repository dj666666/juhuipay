<?php
namespace app\common\library;


use think\facade\Db;
use fast\Http;

/*
    微信消息推送类
*/

class Wxpush 
{
    
    //wxpusher平台
    public static function pushMsg($content, $contentType, $topicIds = [], $uids = [], $url = null){

        $sendData = [
            'appToken'    => 'AT_fTSbVz055Tr9Ol3kmQiC41jFYOaUmOjK',
            'content'     => $content,
            'summary'     => '',
            'contentType' => $contentType,
            'topicIds'    => $topicIds,
            'uids'        => $uids,
            'url'         => $url,
        ];

        $options = [
            CURLOPT_HTTPHEADER =>[
                'Content-Type:application/json;charset=UTF-8',
            ]
        ];

        $url = "http://wxpusher.zjiecode.com/api/send/message";
        $sendData = json_encode($sendData);
        $result = json_decode(Http::post($url,$sendData,$options),true);
       
        //Db::name('callback_log')->insert(['trade_no'=>11,'out_trade_no'=>11,'data'=>json_encode($result),'createtime'=>time()]);
        
    }
    
    //pushplus平台
    public static function pushplusMsg($token, $title, $content, $topic, $template = 'html'){
        
        $sendData = [
            'token'   => $token,
            'title'   => $title,
            'content' => $content,
            'topic'   => $topic,
        ];
         
        $headerArr = [
            'Content-Type:application/json;charset=UTF-8',
        ];
        $options = [];//扩展参数
        $url = "http://www.pushplus.plus/send";
        $sendData = $sendData;
        $result = json_decode(Http::post($url,$sendData,$options),true);
        
    }
    
}