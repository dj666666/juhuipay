<?php
namespace app\common\library;

use Rtgm\sm\RtSm2;
use think\facade\Log;

class HandpaySignUtil
{
    public $PrivateKey = "";
    public $PublicKey = "";

    public function generateSignature($template, $params){
        $data = $this->generatorTemplateData($params, $template);
        $sign = $this->sm2($data);
        return $sign;
    }

    public function sm2($data) {
        $key = $this->PrivateKey;
        
        try {
            $sm2 = new RtSm2('base64');
            $sign = $sm2->doSign($data, $key, '1234567812345678');
            return bin2hex(base64_decode($sign));
        } catch (\Exception $e) {
            Log::writeLog("sm2 error=>".$e->getMessage());
            echo $e->getMessage();
            
        }
        return '';
    }

    public function verifySign($signature, $data){
        $key = $this->PublicKey;
        try {
            $sm2 = new RtSm2('base64', false);
            return $sm2->verifySign($data, base64_encode(hex2bin($signature)), $key, '1234567812345678');
        } catch (\Exception $e) {
            Log::writeLog("verifySign error=>".$e->getMessage(), "DEBUG");
        }
        return false;
    }

    public function checkEmpty($value) {
        if (!isset($value))
            return true;
        if ($value === null)
            return true;
        if (trim($value) === "")
            return true;
        return false;
    }

    public function get_array_value($data, $key){
        if (isset($data[$key])){
            return $data[$key];
        }
        return "";
    }

    function createLinkstring($params)
    {
        $arg = "";
        $keys = array_keys($params);
        sort($keys);
        foreach ($keys as $key){
            $val = $params[$key];
            if($val){
                $arg .= $key . "=" . $val . "&";
            }
        }
        $arg = substr($arg,0, -1);
        return $arg;
    }

    /**
     * @param $params
     * @param $template
     * @return string
     */
    public function generatorTemplateData($params, $template)
    {
        if (is_array($params)) {
            $Parameters = array();
            $keys = explode('|', $template);
            $arrLength = count($keys);
            for ($x = 0; $x < $arrLength; $x++) {
                $Parameters[$x] = $this->get_array_value($params, $keys[$x]);
            }
            $data = join('|', $Parameters);
        } else {
            $data = $params;
        }
        return $data;
    }
}