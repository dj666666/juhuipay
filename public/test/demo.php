<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>接口网关DEMO演示</title>
    <script src="https://lib.baomitu.com/jquery/3.3.1/jquery.min.js"></script>
<style type="text/css">
/* Basic Grey */
.basic-grey {
margin-left:auto;
margin-right:auto;
max-width: 500px;
background: #F7F7F7;
padding: 25px 15px 25px 10px;
font: 12px Georgia, "Times New Roman", Times, serif;
color: #888;
text-shadow: 1px 1px 1px #FFF;
border:1px solid #E4E4E4;
}
.basic-grey h1 {
font-size: 25px;
padding: 0px 0px 10px 40px;
display: block;
border-bottom:1px solid #E4E4E4;
margin: -10px -15px 30px -10px;;
color: #888;
}
.basic-grey h1>span {
display: block;
font-size: 11px;
}
.basic-grey label {
display: block;
margin: 0px;
}
.basic-grey label>span {
float: left;
width: 20%;
text-align: right;
padding-right: 10px;
margin-top: 10px;
color: #888;
}
.basic-grey input[type="text"], .basic-grey input[type="email"], .basic-grey textarea, .basic-grey select {
border: 1px solid #DADADA;
color: #888;
height: 30px;
margin-bottom: 16px;
margin-right: 6px;
margin-top: 2px;
outline: 0 none;
padding: 3px 3px 3px 5px;
width: 70%;
font-size: 12px;
line-height:15px;
box-shadow: inset 0px 1px 4px #ECECEC;
-moz-box-shadow: inset 0px 1px 4px #ECECEC;
-webkit-box-shadow: inset 0px 1px 4px #ECECEC;
}
.basic-grey textarea{
padding: 5px 3px 3px 5px;
}
.basic-grey select {
background: #FFF url('down-arrow.png') no-repeat right;
background: #FFF url('down-arrow.png') no-repeat right);
appearance:none;
-webkit-appearance:none;
-moz-appearance: none;
text-indent: 0.01px;
text-overflow: '';
width: 70%;
height: 35px;
line-height: 25px;
}
.basic-grey textarea{
height:100px;
}
.basic-grey .button {
background: #E27575;
border: none;
padding: 10px 25px 10px 25px;
color: #FFF;
box-shadow: 1px 1px 5px #B6B6B6;
border-radius: 3px;
text-shadow: 1px 1px 1px #9E3F3F;
cursor: pointer;
}
.basic-grey .button:hover {
background: #CF7A7A
}
</style>
</head>
<body>
<form action="gateway.php" method="post" class="basic-grey">
<h1>商户请求网关获得支付二维码DEMO
</h1>
<label>
<span>商户编号 :</span> 
<input id="merno" type="text" name="merno" />

<span>商户密钥 :</span>
<input id="merkey" type="text" name="merkey" />

<span>支付金额 :</span>
<input id="name" type="text" name="amount" value="100" />
  <span>支付类型 :</span>
  <select name="paytype" id="select">
    <!--<option value="1001">微信群红包</option>-->
    <!--<option value="1002">微信个人红包</option>-->
    <option value="1018">转战码</option>
    <option value="1007">uid手动</option>
    <option value="1052">当面付</option>
    <option value="1062">支付宝固定金额</option>
    <option value="1050">支付宝app</option>
    <option value="1003">抖音红包</option>
    <option value="1008">uid(小额)</option>
    <option value="1009">小荷包</option>
    <!--<option value="1004">口令红包</option>-->
    <!--<option value="1005">淘宝代付</option>-->
    <!--<option value="1006">京东代付</option>-->
    <!--<option value="1007">支付宝个码</option>-->
    <!--<option value="1021">支付宝h5</option>-->
    <option value="1025">支付宝扫码(中额)</option>
    <option value="1031">淘宝直付</option>
    <!--<option value="1031">支付宝</option>-->
    <option value="1036">数字人名币</option>

    <!--<option value="1029">迅雷1</option>-->
    <!--<option value="alipay_auto">个人转账</option>
    <option value="alipay_hb">支付宝红包</option>
    <option value="alipay_bank">银行卡转账</option>
    <option value="alipay_group">支付宝群红包</option>
    <option value="alipay_st">支付宝陌生人转账</option>
    <option value="alipay_aiyue">原生红包</option>
    <option value="alipay_xiaoxin">小信红包</option>
    <option value="alipay_gd">支付宝固定</option>-->
  </select>
</label>
<label>
<!--这个参数为服务版专用,公开版无需附带此参数-- <span>支付方式 :</span><select name="type">
<option value="2">支付宝</option>
<option value="1">微信</option>
</select> -->
</label>
<label>
<span>&nbsp;</span>
<input type="submit" id="sub" class="button" value="发起支付" />
</label>
</form>
  
 <!-- <div>
  
  	<h1>文件下载</h1>
  	<a href= http://203.195.140.245/361/callback_log.txt.tar.gz>点击下载文件</a>  
    <img src="1558338783.png">
  </div>-->
</body>
 

 <script>
  $('#sub').click(function(){
        var merno =   $("#merno").val();  
        var merkey =   $("#merkey").val();
        
        if(merno.length == 0|| merkey.length == 0){
            alert("请填写接入信息");
            return false;
        }
        
        var re =   $("#name").val();  
        if(re < 0.01){
          alert("测试金额不得小于1元！")
          return false; 
        }else{
           return true; 
          
        }
                    
  });
  
  </script>
</html>