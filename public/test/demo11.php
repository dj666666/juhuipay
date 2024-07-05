

<!doctype html>
<!--[if lt IE 7]>
<html class="no-js lt-ie9 lt-ie8 lt-ie7" lang=""> <![endif]-->
<!--[if IE 7]>
<html class="no-js lt-ie9 lt-ie8" lang=""> <![endif]-->
<!--[if IE 8]>
<html class="no-js lt-ie9" lang=""> <![endif]-->
<!--[if gt IE 8]><!-->

<html class="no-js" lang=""> <!--<![endif]-->
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>接口网关测试demo</title>
    <meta name="description" content="DingJie">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="stylesheet" href="/static/layui-v2.4.5/layui/css/layui.css">
    <link rel="stylesheet" href="/static/bootstrap/css/bootstrap.css">
	
    <script src="/static/layui-v2.4.5/layui/layui.js"></script>
    <script src="https://code.jquery.com/jquery-3.1.1.min.js"></script>
    <script src="/static/bootstrap/js/bootstrap.js"></script>
	
	<style>
    
      .layui-tab-item{
        font-size:15px;
      }
  	
    </style>
    
</head>
<body>
<div class="container" style="margin-top:50px">
	
		
		
	<h1 align = 'center'>测试demo</h1>





	<form action="gateway.php" method="post" class="basic-grey">	
		<div class="form-group ">
		    <h3>金额/元</h3>
		    <input type="text" class="form-control" name="amount" value="1" id="money" placeholder="请输入金额">
		</div>
		<button type="submit" id="sub" class="btn btn-default" style="width:100%;height:50px;margin-top:50px;">发起支付</button>
	</form>
		  
		
	
		

</div>


</body>
<script>
	layui.use('layer', function(){
         var $ = layui.jquery, layer = layui.layer;
    	})
	
	
  $('#sub').click(function(){
                 
        var re =   $("#money").val();  
        if(re < 0.01){
        		layer.msg('测试金额不得小于1元！',{time:1000});
          
          return false; 
        }else{
           return true; 
          
        }
                    
  });
  
</script>
</html>