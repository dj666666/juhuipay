<?php

namespace app\common\library;



class Rsa{

    private $public_key_resource  = ''; //公钥资源
    private $private_key_resource = ''; //私钥资源
    
    /**
     * 构造函数
     * @param [string] $public_key  [公钥数据字符串]
     * @param [string] $private_key [私钥数据字符串]
     */
    public function __construct($public_key, $private_key) {
        
           $this->public_key_resource = !empty($public_key) ? openssl_pkey_get_public($this->get_public_key($public_key)) : false;
		   $this->private_key_resource = !empty($private_key) ? openssl_pkey_get_private($this->get_private_key($private_key)) : false;
           //$this->public_key_resource = !empty($public_key) ? openssl_pkey_get_public($this->get_public_keyV2($public_key)) : false;
		   //$this->private_key_resource = !empty($private_key) ? openssl_pkey_get_private($this->get_private_keyV2($private_key)) : false;
    }

	/**
	 * 获取私有key字符串 重新格式化  为保证任何key都可以识别
	 */
	public function get_private_key($private_key){
		$search = [
			"-----BEGIN RSA PRIVATE KEY-----",
			"-----END RSA PRIVATE KEY-----",
			"\n",
			"\r",
			"\r\n"
		];

		$private_key = str_replace($search,"",$private_key);
		
		return $search[0] . PHP_EOL . wordwrap($private_key, 64, "\n", true) . PHP_EOL . $search[1];
		
		//return  "-----BEGIN RSA PRIVATE KEY-----\n" .$private_key."\n-----END RSA PRIVATE KEY-----";
		
	}


	/**
	 * 
	 * 获取公共key字符串  重新格式化 为保证任何key都可以识别
	 */
	public function get_public_key($public_key){
		$search = [
			"-----BEGIN PUBLIC KEY-----",
			"-----END PUBLIC KEY-----",
			"\n",
			"\r",
			"\r\n"
		];
		$public_key=str_replace($search,"",$public_key);
		return $search[0] . PHP_EOL . wordwrap($public_key, 64, "\n", true) . PHP_EOL . $search[1];
	}

    public function get_private_keyV2($private_key){
        $private_key        = chunk_split($private_key, 64, "\n");
        $private_key = "-----BEGIN PRIVATE KEY-----\n$private_key-----END PRIVATE KEY-----\n";
        return $private_key;
    }
    
    public function get_public_keyV2($public_key){
        $public_key        = chunk_split($public_key, 64, "\n");
        $public_key = "-----BEGIN PUBLIC KEY-----\n$public_key-----END PUBLIC KEY-----\n";
        //$public_key = "-----BEGIN PUBLIC KEY-----\n" . wordwrap($public_key, 64, "\n", true) . "\n-----END PUBLIC KEY-----";
        return $public_key;
    }
    
    /**
     * 生成一对公私钥 成功返回 公私钥数组 失败 返回 false
     */
    public function create_key() {
        
        $res = openssl_pkey_new();
        
        if($res == false){
            return false;
        }
        
        openssl_pkey_export($res, $private_key);
        
        $public_key = openssl_pkey_get_details($res);
        
        return array('public_key'=>$public_key["key"],'private_key'=>$private_key);
    }
    
    /**
     * 用私钥加密
     */
    public function private_encrypt($input) {
        
        if (strlen($input) > 117) {
            foreach (str_split($input, 117) as $chunk) {
                openssl_private_encrypt($chunk, $output, $this->private_key_resource);
                $output .= $output;
            }
        }else{
            openssl_private_encrypt($input,$output,$this->private_key_resource);
        }
        
        $sign = base64_encode($output);
        return $sign;
    }
    
    /**
     * 解密 私钥加密后的密文
     */
    public function public_decrypt($input) {
        openssl_public_decrypt(base64_decode($input),$output,$this->public_key_resource);
        return $output;
    }
    
    /**
     * 公钥加密
     */
    public function public_encrypt($text) {
        
        $result = '';
        $data   = str_split($text, 117);
        foreach ($data as $item) {
            openssl_public_encrypt($item, $encrypted, $this->public_key_resource);
            $result .= $encrypted;
        }
        
        return base64_encode($result);
    }
    
    /**
     * 解密 公钥加密后的密文
     */
    public function private_decrypt($encrypted) {
        
        $encrypted = base64_decode($encrypted);
        $result    = '';
        $data      = str_split($encrypted, 128);
        foreach ($data as $item) {
            openssl_private_decrypt($item, $decrypted, $this->private_key_resource);
            $result .= $decrypted;
        }
        
        return $result;
    }
    
    /**
     * 公钥加密
     */
    public function public_encryptV2($str, $signature_alg = OPENSSL_ALGO_SHA1) {
        openssl_sign($str, $signature, $this->public_key_resource, $signature_alg);
        
        $signature = base64_encode($signature);
        openssl_free_key($this->public_key_resource);
        return $signature;
    }
    
    /**
     * 私钥解密
     */
    public function private_decryptV2($my_sign_str, $sign, $signature_alg = OPENSSL_ALGO_SHA1) {
        $flag = openssl_verify($my_sign_str, base64_decode($sign), $this->private_key_resource, $signature_alg);
        openssl_free_key($this->private_key_resource);
        return $flag == 1 ? true : false;
    }
    
    //私钥加密
    public function private_encryptV2($str,$signature_alg = OPENSSL_ALGO_SHA1){
        openssl_sign($str, $signature, $this->private_key_resource, $signature_alg);
        $signature = base64_encode($signature);
        openssl_free_key($this->private_key_resource);
        return $signature;
    }
    
    //公钥验签
    public function public_verifyV2($my_sign_str, $sign, $signature_alg = OPENSSL_ALGO_SHA1){
        $flag = openssl_verify($my_sign_str, base64_decode($sign), $this->public_key_resource, $signature_alg);
        openssl_free_key($this->public_key_resource);
        return $flag == 1 ? true : false;
    }
    

}