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
  <script src="./../public/plugins/layer-v3.1.0/layer.js?v=v1.6.4"></script> 
</head>
<body>
  <div class="wrap">
    <?php require './templates/header.php';?>
    <section class="section">
      <div class="blank30"></div>
      <div class="go go3"></div>
      <div class="blank30"></div>
      <div class="main ccaz">
        <div class="wraper">
          <div class="install" id="log">
            <div class="blank10"></div>
            <ul id="loginner">
            </ul>
          </div>
          <div class="bottom tac"> <a href="javascript:;" class="btn_old" id="loading"><img src="./images/loading.gif" align="absmiddle" />&nbsp;正在安装...</a> </div>
        </div>
      </div>
    </section>
    <script type="text/javascript">
      var n=-1;
      var data = <?php echo json_encode($_POST);?>;
      $.ajaxSetup ({ cache: false });
      function reloads(n) {
          var url =  "<?php echo $_SERVER['PHP_SELF']; ?>?step=4&install=1&n="+n;
          $.ajax({
              type: "POST",   
              url: url,
              data: data,
              dataType: 'json',
              beforeSend:function(){
              },
              success: function(msg){
                  if (-1 == msg) {
                      return false;
                  } else if (null == msg) {
                      $('#loading').hide();
                      layer.alert('安装过程中断，请尝试F5刷新！', {icon: 5});
                  }
                  if(msg.n=='999999'){
                      $('#dosubmit').attr("disabled",false);
                      $('#dosubmit').removeAttr("disabled");        
                      $('#dosubmit').removeClass("nonext");
                      setTimeout('gonext()',2000);
                  }
                  if(msg.n>=0){
                      $('#loginner').append(msg.msg); 
                      reloads(msg.n); 
                  }else{
                      $('#loading').hide();
                      $('#loginner').append(msg.msg); 
                      layer.alert(msg.msg, {icon: 5});
                  }
              }
          });
      }
      function gonext(){
          window.location.href='<?php echo $_SERVER['PHP_SELF']; ?>?step=5';
      }
      $(document).ready(function(){
          reloads(n);
      })
  </script> 
  </div>
  <div class="blank30"></div>
  <?php require './templates/footer.php';?>
</body>
</html>