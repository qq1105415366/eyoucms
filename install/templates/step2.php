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
  <style type="text/css">
    .btn_a{ width: 58px; }
    #table td{ text-align: center; }
    #table td.first{ text-align: left; }
  </style>
</head>
<body>
  <div class="wrap">
    <?php require './templates/header.php';?>
    <section class="section">
      <div class="blank30"></div>
      <div class="go go2"></div>
      <div class="blank30"></div>
      <div class="server">
        <table width="100%" id="table" cellspacing="1">
          <tr>
            <td class="td1">环境检测</td>
            <td class="td1" width="23%">推荐配置</td>
            <td class="td1" width="46%">当前状态</td>
          </tr>
          <tr>
            <td class="first">服务器环境</td>
            <td>apache2.0以上/nginx1.6以上/IIS</td>
            <td><?php echo $_SERVER['SERVER_SOFTWARE']; ?></td>
          </tr>
          <tr>
            <td class="first">PHP版本</td>
            <td>7.0及7.0以上<br/>(支持php5、7、8系列)</td>
            <td><?php echo $phpvStr; ?></td>
          </tr>
          <tr>
            <td class="first">safe_mode</td>
            <td><font title="影响缓存清除、系统升级、模板管理等功能">基础配置</font></td>
            <td><?php echo $safe_mode; ?></td>
          </tr>
          <tr>
            <td class="first">GD库</td>
            <td><font title="影响验证码是否显示、图片水印、以及图像处理等问题">必须开启</font></td>
            <td><?php echo $gd; ?></td>
          </tr>
  <!--         <tr>
            <td class="first">session</td>
            <td><font title="影响系统安装、后台登录等功能">必须开启</font></td>
            <td><?php echo $session; ?></td>
          </tr> -->
          <tr>
            <td class="first">mysqli</td>
            <td><font title="影响数据库的连接和一系列读、写、删、改操作">必须开启</font></td>
            <td><?php echo $mysql; ?></td>
          </tr>
          <tr>
            <td class="first">pdo</td>
            <td><font title="影响数据库的连接和一系列读、写、删、改操作">必须开启</font></td>
            <td><?php echo $pdo; ?></td>
          </tr>
          <tr>
            <td class="first">pdo_mysql</td>
            <td><font title="影响数据库的连接和一系列读、写、删、改操作">必须开启</font></td>
            <td><?php echo $pdo_mysql; ?></td>
          </tr>
        </table>

        <table width="100%" id="table" cellspacing="1">
          <tr>
            <td class="td1">函数检测</td>
            <td class="td1" width="23%">推荐配置</td>
            <td class="td1" width="46%">是否通过</td>
          </tr>
          <tr>
            <td class="first">curl_init</td>
            <td><font title="影响插件功能、伪静态、系统升级、采集文章等功能">必须扩展</font></td>
            <td><?php echo $curl; ?></td>
          </tr>
          <tr>
            <td class="first">file_put_contents</td>
            <td><font title="影响系统安装、文件上传、数据库备份、百度地图xml等功能">必须扩展</font></td>
            <td><?php echo $file_put_contents; ?></td>
          </tr>
  <!--         <tr>
            <td class="first">scandir</td>
            <td><a href="http://www.eyoucms.com/bbs/823.html" target="_blank">必须支持</a></td>
            <td><?php echo $scandir; ?></td>
          </tr> -->
        </table>

        <table width="100%" id="table" cellspacing="1">
          <tr>
              <td class="td1">目录、文件权限检查</td>
              <td class="td1" width="23%">推荐配置</td>
              <td class="td1" width="46%">是否通过</td>
          </tr>
          <?php
          foreach($folder as $dir){
              $is_write = false;
              $Testdir = SITEDIR.$dir;
              if (file_exists($Testdir) && is_file($Testdir)) {
                  $is_write = is_writable($Testdir);
                  !empty($is_write) && $is_write = is_readable($Testdir);
              } else {
                  dir_create($Testdir);
                  $is_write = testwrite($Testdir);
                  !empty($is_write) && $is_write = is_readable($Testdir);
              }
              
              if($is_write){
                  $w = '<img src="images/ok.png">';
              }else{
                  $w = '<img src="images/del.png">';
                  $err++;
              }
          ?>
          <tr>
              <td class="first"><?php echo $dir; ?></td>
              <td>读写</td>
              <td><?php echo $w; ?></td>
          </tr>
          <?php
          }
          ?>                              
        </table>
        
      </div>
      <div class="bottom tac"> 
        <div class="blank20"></div>
        <center>
          <a href="<?php echo $_SERVER['PHP_SELF']; ?>?step=2" class="btn_b">重新检测</a>
          <?php if($err>0){?>
          <a id="next_submit" href="javascript:void(0)" onClick="javascript:layer.alert('安装环境检测未通过，请检查', {icon: 5, title: false})" class="btn_a" style="background: gray;">下一步</a> 
          <?php }else{?>
          <a id="next_submit" href="<?php echo $_SERVER['PHP_SELF']; ?>?step=3" class="btn_a">下一步</a> 
          <?php }?>
        </center>
      </div>
    </section>
  </div>
  <div class="blank20"></div>
  <?php require './templates/footer.php';?>

  <script type="text/javascript">
    $(function(){
      $('#next_submit').focus();
    });
  </script>
</body>
</html>