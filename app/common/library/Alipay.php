<?php

namespace app\common\library;

use think\facade\Db;
use fast\Http;

/**
 * 支付公共类
 */
class Alipay {

    public function BaoHuo($cookie) {
        $beat = $this->alipayCurl('https://my.alipay.com/portal/i.htm?src=yy_taobao_gl_01&sign_from=3000&sign_account_no=&guide_q_control=top', $cookie);
        return;
    }

    public function GetOrder($qq, $cookie) {
        $beat = $this->QQpayCurl('https://myun.tenpay.com/cgi-bin/clientv1.0/qwallet_record_list.cgi?limit=15&offset=0&s_time=2019-04-20&ref_param=&source_type=7&time_type=0&bill_type=3&uin=' . $qq, $cookie);
        return $beat;
    }

    public function GetOrderDetail($billno, $qq, $cookie, $pskey, $skey) {
        $beat = $this->QQpayCurl('https://mqq.tenpay.com/cgi-bin/qwallet_app/qpayment_trans_detail.cgi?listid=' . $billno . '&_t=' . time() . '&uin=' . $qq . '&pskey=' . $pskey . '&skey_type=2&g_tk=&skey=' . $skey, $cookie);
        return $beat;
    }

    /****************获取账号余额***************/
    public function GetMoney($cookie) {
        preg_match('/ctoken=(.*?);/', $cookie, $uin);
        $res = $this->alipayCurl('https://lab.alipay.com/user/assets/queryBalance.json?ctoken=' . $uin[1], $cookie);
        $res = json_decode($res, true);
        return $res;
    }

    protected function QQpayCurl($api, $cookie) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api);
        curl_setopt($ch, CURLOPT_COOKIE, $cookie);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT,
            "Mozilla/5.0 (Linux; U; Android 4.4.1; zh-cn; R815T Build/JOP40D) AppleWebKit/533.1 (KHTML, like Gecko)Version/4.0 MQQBrowser/4.5 Mobile Safari/533.1");
        $res = curl_exec($ch);
        curl_close($ch);
        return $res;
    }

    /****************获取我的账号余额***************/
    public function GetMyMoney($Cookie) {
        switch (rand(1, 9)) {
            case 1:
                $data = $this->GetNewMoney('https://personalweb.alipay.com/portal/i.htm', $Cookie);
                break;
            case 2:
                $data = $this->GetNewMoney('https://my.alipay.com/wealth/index.html', $Cookie);
                break;
            case 3:
                $data = $this->GetNewMoney('https://110.alipay.com/sc/index.htm', $Cookie);
                break;
            case 4:
                $data = $this->GetNewMoney('https://my.alipay.com/portal/i.htm', $Cookie);
                break;
            case 5:
                $data = $this->GetNewMoney('https://shanghu.alipay.com/home/switchPersonal.htm', $Cookie);
                break;
            case 6:
                $data = $this->GetNewMoney('https://cshall.alipay.com/lab/question.htm', $Cookie);
                break;
            case 7:
                $data = $this->GetNewMoney('https://cshall.alipay.com/lab/cateQuestion.htm', $Cookie);
                break;
            case 8:
                $data = $this->GetNewMoney('https://cshall.alipay.com/lab/help_detail.htm', $Cookie);
                break;
            case 9:
                $data = $this->GetNewMoney('https://egg.alipay.com/egg/index.htm', $Cookie);
                break;
            default:
                $data = $this->GetNewMoney('http://egg.alipay.com/egg/advice.htm', $Cookie);
                break;
        }
        $trStr[1][0] = $data;
        if ($trStr[1][0] == "-1") {
            $money  = -1;
            $status = false;
        } else {
            $money  = $trStr[1][0];
            $status = true;
        }
        return array("status" => $status, "money" => $money, "time" => time());
    }

    public function GetMyMoney_2($Cookie) {
        switch (rand(1, 9)) {
            case 1:
                $data = $this->Get_Alipay_Cookie('https://personalweb.alipay.com/portal/i.htm', $Cookie);
                break;
            case 2:
                $data = $this->Get_Alipay_Cookie('https://my.alipay.com/wealth/index.html', $Cookie);
                break;
            case 3:
                $data = $this->Get_Alipay_Cookie('https://110.alipay.com/sc/index.htm', $Cookie);
                break;
            case 4:
                $data = $this->Get_Alipay_Cookie('https://my.alipay.com/portal/i.htm', $Cookie);
                break;
            case 5:
                $data = $this->Get_Alipay_Cookie('https://shanghu.alipay.com/home/switchPersonal.htm', $Cookie);
                break;
            case 6:
                $data = $this->Get_Alipay_Cookie('https://cshall.alipay.com/lab/question.htm', $Cookie);
                break;
            case 7:
                $data = $this->Get_Alipay_Cookie('https://cshall.alipay.com/lab/cateQuestion.htm', $Cookie);
                break;
            case 8:
                $data = $this->Get_Alipay_Cookie('https://cshall.alipay.com/lab/help_detail.htm', $Cookie);
                break;
            case 9:
                $data = $this->Get_Alipay_Cookie('https://egg.alipay.com/egg/index.htm', $Cookie);
                break;
            default:
                $data = $this->Get_Alipay_Cookie('http://egg.alipay.com/egg/advice.htm', $Cookie);
                break;
        }
        $trStr[1][0] = $data;
        if ($trStr[1][0] == "-1") {
            $money  = -1;
            $status = false;
        } else {
            $money  = $trStr[1][0];
            $status = true;
            if (strlen($money) > 50) {
                $money  = -1;
                $status = false;
            }
        }
        return array("status" => $status, "money" => $money, "time" => time());
    }

    /****************获取账号余额***************/
    public function GetNewMoney($Url_Referer, $Cookie = null) {
        $ctoken  = $this->getSubstr($Cookie, "ctoken=", ";");
        $Url     = "https://shenghuo.alipay.com/transfercore/withdraw/apply.htm";
        $referer = $Url_Referer . '?&t=' . time();
        $ua      = 'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8
		accept-encoding: gzip, deflate, br
		accept-language: zh-CN,zh;q=0.9
		cache-control: max-age=0
		Cookie: ' . @$Cookie . '
		referer: ' . $Url . '?referer=' . $referer . '
		upgrade-insecure-requests: 1
		user-agent: Mozilla/5.0 (Linux; Android 10.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.101 Safari/537.36 360Browser/9.2.5584.400';
        $result  = $this->Get_Money_curl_intl($Url_Referer, 0, $referer, $Cookie, 0, $ua);
        $result  = mb_convert_encoding($this->Get_Money_curl($Url, 0, $referer, $Cookie, 0, $ua), "UTF-8", "GB2312");
        if (!strstr($result, '提取余额到银行卡')) $result = "-1"; else $result = $this->getSubstr($result, '<span class="number">', '</span>');
        return $result;
    }

    /****************获取会员信息***************/
    protected function alipayCurl($api, $cookie) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api);
        curl_setopt($ch, CURLOPT_COOKIE, $cookie);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_REFERER, 'https://business.alipay.com/user/home');
        curl_setopt($ch, CURLOPT_USERAGENT,
            "Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/65.0.3325.181 Safari/537.36");
        $res  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $res;
    }

    /************获取⽀付宝账单请求*************/
    public function getAliOrder($cookie, $pid, $time) {
        $startDateInput = rawurlencode(date("Y-m-d H:i:s", (time() - $time)));//获取10分钟之内的订单
        $endDateInput   = rawurlencode(date("Y-m-d H:i:s", strtotime('now')));
        preg_match('/ctoken=(.*?);/', $cookie, $uin);
        $str  = 'endDateInput=' . $endDateInput . '0&precisionQueryKey=tradeNo&precisionQueryValue=&showType=1&startDateInput=' . $startDateInput . '&billUserId=' . $pid . '&pageNum=1&pageSize=100&startTime=' . $startDateInput . '&endTime=' . $endDateInput . '&status=1&queryEntrance=1&sortTarget=tradeTime&activeTargetSearchItem=tradeNo&accountType=&sortType=0&startAmount&endAmount&targetMainAccount&precisionValue&goodsTitle&total=0&_input_charset=gbk';
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL            => 'https://mbillexprod.alipay.com/enterprise/fundAccountDetail.json?ctoken=' . $uin[1],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => $str,
            CURLOPT_HTTPHEADER     => array(
                'authority: mbillexprod.alipay.com',
                'sec-ch-ua: "Google Chrome";v="93", " Not;A Brand";v="99", "Chromium";v="93"',
                'accept: application/json, text/javascript, */*; q=0.01',
                'content-type: application/x-www-form-urlencoded; charset=UTF-8',
                'x-requested-with: XMLHttpRequest',
                'sec-ch-ua-mobile: ?0',
                'user-agent: Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/62.0.3202.89 Mobile Safari/537.36',
                'origin: https://mbillexprod.alipay.com',
                'sec-fetch-site: same-origin',
                'sec-fetch-mode: cors',
                'sec-fetch-dest: empty',
                'referer: https://business.alipay.com/user/mbillexprod/account/detail',
                'accept-language: zh-CN,zh;q=0.9,en;q=0.8',
                'cookie: ' . $cookie
            ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        $res = json_decode($response, true);
        return empty($res) ? json_decode(mb_convert_encoding($response, 'UTF-8', 'GBK'), true) : $res;
    }
    
    protected function Get_Money_curl($url, $post = 0, $referer = 0, $cookie = 0, $header = 0, $ua = 0, $nobaody = 0) {
        if (is_array($cookie)) {
            $str = '';
            foreach ($cookie as $key => $value) {
                $str .= $key . '=' . $value . '; ';
            }
            $cookie = substr($str, 0, -1);
        }
        $opts    = array(
            'http' => array(
                'method'  => ($post ? 'POST' : 'GET'),
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n" .
                    "Content-length:" . strlen($post) . "\r\n" .
                    "Cookie: " . @$cookie . "\r\n" .
                    "\r\n" . $ua .
                    "\r\n",
                'content' => $post,
            )
        );
        $context = stream_context_create($opts);
        $ret     = file_get_contents($url, false, $context);
        return $ret;
    }

    protected function Get_Money_curl_intl($url, $post = 0, $referer = 0, $cookie = 0, $header = 0, $ua = 0, $nobaody = 0) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $httpheader[] = "Accept:*/*";
        $httpheader[] = "Accept-Encoding:gzip,deflate,sdch";
        $httpheader[] = "Accept-Language:zh-CN,zh;q=0.8";
        $httpheader[] = "Connection:close";
        curl_setopt($ch, CURLOPT_HTTPHEADER, $httpheader);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        if ($post) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        }
        if ($header) {
            curl_setopt($ch, CURLOPT_HEADER, TRUE);
        }
        if ($cookie) {
            curl_setopt($ch, CURLOPT_COOKIE, $cookie);
        }
        if ($referer) {
            if ($referer == 1) {
                curl_setopt($ch, CURLOPT_REFERER, 'http://m.qzone.com/infocenter?g_f=');
            } else {
                curl_setopt($ch, CURLOPT_REFERER, $referer);
            }
        }
        if ($ua) {
            curl_setopt($ch, CURLOPT_USERAGENT, $ua);
        } else {
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Linux; U; Android 4.4.1; zh-cn; R815T Build/JOP40D) AppleWebKit/533.1 (KHTML, like Gecko)Version/4.0 MQQBrowser/4.5 Mobile Safari/533.1');
        }
        if ($nobaody) {
            curl_setopt($ch, CURLOPT_NOBODY, 1);
        }
        curl_setopt($ch, CURLOPT_ENCODING, "gzip");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $ret = curl_exec($ch);
        curl_close($ch);
        return $ret;
    }

    protected function getSubstr($str, $leftStr, $rightStr) {
        $left = strpos($str, $leftStr);
        //echo '左边:'.$left;
        $right = strpos($str, $rightStr, $left);
        //echo '<br>右边:'.$right;
        if ($left < 0 or $right < $left) return '';
        return substr($str, $left + strlen($leftStr), $right - $left - strlen($leftStr));
    }

    public function Get_pay_money($cookie) {
        $opts    = array(
            'http' => array(
                'method' => 'GET',
                'header' => 'cookie: ' . $cookie . '
                accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9
                accept-encoding: gzip, deflate, br
                accept-language: zh-CN,zh;q=0.9,en;q=0.8,en-GB;q=0.7,en-US;q=0.6    
                cache-control: max-age=0
                sec-ch-ua-platform: "Windows"
                sec-fetch-dest: document
                sec-fetch-mode: navigate
                sec-fetch-site: none
                user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/99.0.4844.51 Safari/537.36 Edg/99.0.1150.39'
            )
        );
        $context = stream_context_create($opts);
        switch (rand(1, 12)) {
            case 1:
                $url = 'https://my.alipay.com/portal/i.htm';
                break;
            case 2:
                $url = 'https://yebprod.alipay.com/yeb/cashierSignDeposit.htm';
                break;
            case 3:
                $url = 'https://110.alipay.com/sc/index.htm';
                break;
            case 4:
                $url = 'https://110.alipay.com/sc/products.htm';
                break;
            case 5:
                $url = 'https://110.alipay.com/sc/partner/browser.htm';
                break;
            case 6:
                $url = 'https://110.alipay.com/sc/study/index.htm';
                break;
            case 7:
                $url = 'https://110.alipay.com/safety/emergency/index.htm';
                break;
            case 8:
                $url = 'https://shenghuo.alipay.com/transfercore/withdraw/apply.htm';
                break;
            case 9:
                $url = 'https://shenghuo.alipay.com/send/payment/fill.htm';
                break;
            case 10:
                $url = 'https://consumeprod.alipay.com/record/advanced.htm';
                break;
            case 11:
                $url = 'https://help.alipay.com/lab/question.htm';
                break;
            case 12:
                $url = 'https://cshall.alipay.com/lab/selfHelp.htm';
                break;
        }
        $money_url = 'https://yebprod.alipay.com/yeb/purchase.htm';
        $result    = file_get_contents($money_url, false, $context);
        $ck_data   = file_get_contents($url, false, $context);
        $result    = iconv("GBK", "UTF-8", $result);
        if (strstr($result, '余额宝资金受支付宝特别保护，安全可靠，请放心操作。')) {
            $money = $this->getSubstr($result, '<p class="ui-form-text"><span class="ft-orange ft-bold">', '</span>');
            if (strlen($money) > 20) {
                $money  = -1;
                $status = false;
            } else {
                $status = true;
            }

        } else {
            $money  = -1;
            $status = false;
        }
        return ["status" => $status, "money" => $money, "time" => time()];
    }

    protected function Get_Alipay_Cookie($Url_Referer, $Cookie = null) {
        $ctoken = $this->getSubstr($Cookie, "ctoken=", ";");
        //$Url = 'https://mrchportalweb.alipay.com/user/asset/queryData?_ksTS='.time().'_'.rand(10,99).'&_input_charset=utf-8&ctoken='.$ctoken;
        $Url     = "https://shanghu.alipay.com/user/myAccount/index.htm";
        $referer = $Url_Referer . '?&t=' . time();
        $ua      = 'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8
        accept-encoding: gzip, deflate, br
        accept-language: zh-CN,zh;q=0.9
        cache-control: max-age=0
        Cookie: ' . @$Cookie . '
        referer: ' . $Url . '?referer=' . $referer . '
        upgrade-insecure-requests: 1
        user-agent: Mozilla/5.0 (Linux; Android 10.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.101 Safari/537.36 360Browser/9.2.5584.400';
        $result  = $this->Get_Money_curl_intl($Url_Referer, 0, $referer, $Cookie, 0, $ua);
        $result  = mb_convert_encoding($this->Get_Money_curl($Url, 0, $referer, $Cookie, 0, $ua), "UTF-8", "GB2312");
        if (!strstr($result, '修改商家LOGO')) {
            $result = "-1";
        } else {
            $result = $this->getSubstr($result, '<em class="aside-available-amount">', '</em>元</span></li>');
        }
        return $result;
    }

}