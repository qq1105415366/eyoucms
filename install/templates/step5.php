<!doctype html>
<html>
<head>
  <meta charset="UTF-8" />
  <meta name="renderer" content="webkit">
  <meta http-equiv="X-UA-Compatible" content="IE=Edge,chrome=1" >
  <meta http-equiv="Content-Language" content="zh-cn"/>
  <meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0" name="viewport" />
  <title><?php echo $Title; ?> - <?php echo $Powered; ?></title>
  <link rel="stylesheet" href="./css/install.css?v=v1.6.4" />
  <script src="./js/jquery.js?v=v1.6.4"></script> 
  <?php 
    $uri = $_SERVER['REQUEST_URI'];
    $root = substr($uri, 0,strpos($uri, "install"));
    $admin = $root."../login.php";
  ?>
</head>
<body>
  <div class="wrap">
    <?php require './templates/header.php';?>
    <section class="section">
      <div class="blank10"></div>
      <div class="blank30"></div>
      <div class="go go4"></div>
      <div class="blank10"></div>
      <div class="blank30"></div>
      <div class="">
        <div class="result cc"> 
          <h1>恭喜您，EyouCms已经成功安装完成！</h1>
          <h5>基于安全考虑，安装完成后正式运营时请将后台密码设置复杂一些！</h5>
        </div>
  	        <div class="bottom tac"> 
            <center>
  	        <a href="../index.php" class="btn_b" style="color: #fff"> 访问网站首页 </a>
  	        <a id="next_submit" href="../login.php" class="btn_a btn_submit J_install_btn"> 登陆网站后台 </a>	
            </center>
        </div>
        <div class=""> </div>
      </div>
    </section>
  </div>
  <div class="blank30"></div>
  <?php require './templates/footer.php';?>
  
  <script>
  $(function(){
      $.ajax({
          type: "POST",
          url: "<?php echo $service_ey.base64_decode($ajax_url);?>",
          data: {domain:'<?php echo $host;?>',last_domain:'<?php echo $host;?>',key_num:'<?php echo $cms_version;?>',install_time:'<?php echo $time;?>',serial_number:'<?php echo $mt_rand_str;?>',ip:'<?php echo $ip;?>',phpv:'<?php echo $phpv;?>',web_server:'<?php echo $web_server;?>'},
          dataType: 'jsonp',
          jsonp: "jsonpCallback",//服务端用于接收callback调用的function名的参数 
          success: function(){}
      });
      $('#next_submit').focus();
  });
  </script>
</body>
</html>