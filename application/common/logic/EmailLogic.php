<?php
/**
 * 易优CMS
 * ============================================================================
 * 版权所有 2016-2028 海南赞赞网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.eyoucms.com
 * ----------------------------------------------------------------------------
 * 如果商业用途务必到官方购买正版授权, 以免引起不必要的法律纠纷.
 * ============================================================================
 * Author: 小虎哥 <1105415366@qq.com>
 * Date: 2018-4-3
 */

namespace app\common\logic;

use think\Db;

/**
 * Description of SmsLogic
 *
 * 邮件类
 */
class EmailLogic 
{
    private $config;
    private $home_lang;
    
    public function __construct($smtp_config = []) 
    {
        $this->config = !empty($smtp_config) ? $smtp_config : tpCache('smtp');
        $this->home_lang = get_home_lang();
    }

    /**
     * 置换邮件模版内容
     * @param intval $scene 应用场景
     */
    private function replaceContent($scene = '', $params = '')
    {
        if (0 == intval($scene)) {
            $msg = $params;
        } else {
            $params_arr = [];
            $emailTemp = Db::name('smtp_tpl')->where([
                    'send_scene'=> $scene,
                    'lang'      => $this->home_lang,
                ])->find();
            if (!empty($emailTemp)) {
                $msg = $emailTemp['tpl_content'];
                preg_match_all('/\${([^\}]+)}/i', $msg, $matchs);
                if (!empty($matchs[1])) {
                    foreach ($matchs[1] as $key => $val) {
                        if (is_array($params)) {
                            $params_arr[$val] = $params[$val];
                        } else {
                            $params_arr[$val] = $params;
                        }
                    }
                }

                //置换邮件模版内容
                foreach ($params_arr as $k => $v) {
                    $msg = str_replace('${' . $k . '}', $v, $msg);
                }
            } else {
                return '';
            }
        }

        return $msg;
    }

    /**
     * 邮件发送
     * @param $to    接收人
     * @param string $subject   邮件标题
     * @param string|array $data   邮件内容(html模板渲染后的内容)
     * @param string $scene   使用场景
     * @throws Exception
     */
    public function send_email($to='', $subject='', $data='', $scene=1, $library = 'phpmailer'){
        // 只保留最近1小时的邮件日志
        try {
            $mtime = strtotime("-1 hour");
            Db::name('smtp_record')->where([
                'add_time'  => ['lt', $mtime],
                ])->delete();
        } catch (\Exception $e) {}

        if (0 < intval($scene)) {
            $smtp_tpl_row = Db::name('smtp_tpl')->where([
                    'send_scene'=> $scene,
                    'lang'      => $this->home_lang,
                ])->find();
            if (empty($smtp_tpl_row)) {
                return ['code'=>0,'msg'=>'找不到相关邮件模板！'];
            } else if (empty($smtp_tpl_row['is_open'])) {
                return ['code'=>0,'msg'=>'该功能待开放，请先启用邮件模板('.$smtp_tpl_row['tpl_name'].')'];
            } else {
                empty($subject) && $subject = $smtp_tpl_row['tpl_title'];
                $subject = htmlspecialchars_decode($subject);
            }
        }

        switch ($library) {
            case 'phpmailer':
                return $this->send_phpmailer($to, $subject, $data, $scene);
                break;

            case 'swiftmailer':
                return $this->send_swiftmailer($to, $subject, $data, $scene);
                break;
            
            default:
                return $this->send_phpmailer($to, $subject, $data, $scene);
                break;
        }
    }

    /**
     * 邮件发送 - swiftmailer第三方库
     * @param $to    接收人
     * @param string $subject   邮件标题
     * @param string|array $data   邮件内容(html模板渲染后的内容)
     * @param string $scene   使用场景
     * @throws Exception
     */
    private function send_swiftmailer($to='', $subject='', $data='', $scene=1){
        vendor('swiftmailer.lib.swift_required');
        // require_once 'vendor/swiftmailer/lib/swift_required.php';
        try {
            //判断openssl是否开启
            $openssl_funcs = get_extension_funcs('openssl');
            if(!$openssl_funcs){
                return array('code'=>0 , 'msg'=>'请先开启php的openssl扩展');
            }

            //判断openssl是否开启
            // $sockets_funcs = get_extension_funcs('sockets');
            // if(!$sockets_funcs){
            //     return array('code'=>0 , 'msg'=>'请先开启php的sockets扩展');
            // }
        
            empty($to) && $to = $this->config['smtp_from_eamil'];
            $to = explode(',', $to);

            //smtp服务器
            $host = $this->config['smtp_server'];
            //端口 - likely to be 25, 465 or 587
            $port = intval($this->config['smtp_port']);
            //用户名
            $user = $this->config['smtp_user'];
            //密码
            $pwd = $this->config['smtp_pwd']; 
            //发送者
            $from = $this->config['smtp_user'];
            //发送者名称
            $from_name = $user;//tpCache('web.web_name');
            // 使用安全协议
            $encryption_type = null;
            switch ($port) {
                case 465:
                    $encryption_type = 'ssl';
                    break;
                
                case 587:
                    $encryption_type = 'tls';
                    break;

                default:
                    # code...
                    break;
            }
            //HTML内容转换
            $body = $this->replaceContent($scene, $data);

            // Create the Transport
            $transport = (new \Swift_SmtpTransport($host, $port, $encryption_type))
                ->setUsername($user)
                ->setPassword($pwd);

            // Create the Mailer using your created Transport
            $mailer = new \Swift_Mailer($transport);

            // Create a message
            $message = (new \Swift_Message($subject))
                ->setFrom([$from=>$from_name])
                // ->setTo([$to, '第二个接收者邮箱' => '别名'])
                ->setTo($to)
                ->setContentType("text/html")
                ->setBody($body);

            // Send the message
            $result = $mailer->send($message);
            if (!$result) {
                return array('code'=>0 , 'msg'=>'发送失败');
            } else {
                return array('code'=>1 , 'msg'=>'发送成功');
            }
        } catch (\Exception $e) {
            return array('code'=>0 , 'msg'=>'发送失败: '.$e->errorMessage());
        }
    }

    /**
     * 邮件发送 - 第三方库phpmailer
     * @param $to    接收人
     * @param string $subject   邮件标题
     * @param string|array $data   邮件内容(html模板渲染后的内容)
     * @param string $scene   使用场景
     * @throws Exception
     */
    private function send_phpmailer($to='', $subject='', $data='', $scene=1){
        vendor('phpmailer.PHPMailerAutoload');
        try {
            //判断openssl是否开启
            $openssl_funcs = get_extension_funcs('openssl');
            if(!$openssl_funcs){
                return array('code'=>0 , 'msg'=>'请先开启php的openssl扩展');
            }

            //判断openssl是否开启
            // $sockets_funcs = get_extension_funcs('sockets');
            // if(!$sockets_funcs){
            //     return array('code'=>0 , 'msg'=>'请先开启php的sockets扩展');
            // }

            $mail = new \PHPMailer;
            $mail->CharSet  = 'UTF-8'; //设定邮件编码，默认ISO-8859-1，如果发中文此项必须设置，否则乱码
            $mail->isSMTP();
            //Enable SMTP debugging
            // 0 = off (for production use)
            // 1 = client messages
            // 2 = client and server messages
            $mail->SMTPDebug = 0;
            //调试输出格式
            //$mail->Debugoutput = 'html';
            //接收者邮件
            empty($to) && $to = $this->config['smtp_from_eamil'];
            $to = explode(',', $to);
            //smtp服务器
            $mail->Host = $this->config['smtp_server'];
            //端口 - likely to be 25, 465 or 587
            $mail->Port = intval($this->config['smtp_port']);
            // 使用安全协议
            switch ($mail->Port) {
                case 465:
                    $mail->SMTPSecure = 'ssl';
                    break;
                
                case 587:
                    $mail->SMTPSecure = 'tls';
                    break;

                default:
                    # code...
                    break;
            }
            //Whether to use SMTP authentication
            $mail->SMTPAuth = true;
            //用户名
            $mail->Username = $this->config['smtp_user'];
            //密码
            $mail->Password = $this->config['smtp_pwd'];
            //Set who the message is to be sent from
            $mail->setFrom($this->config['smtp_user']);
            //回复地址
            //$mail->addReplyTo('replyto@example.com', 'First Last');
            //接收邮件方
            if(is_array($to)){
                foreach ($to as $v){
                    $mail->addAddress($v);
                }
            }else{
                $mail->addAddress($to);
            }

            $mail->isHTML(true);// send as HTML
            //标题
            $mail->Subject = $subject;
            //HTML内容转换
            $content = $this->replaceContent($scene, $data);
            $mail->msgHTML($content);
            //Replace the plain text body with one created manually
            //$mail->AltBody = 'This is a plain-text message body';
            //添加附件
            //$mail->addAttachment('images/phpmailer_mini.png');
            //send the message, check for errors
            $result = $mail->send();
            if (!$result) {
                $msg = $mail->ErrorInfo;
                if (stristr($msg, 'smtp connect() failed')) {
                    if (465 == $mail->Port) {
                        $msg = '<br/>1.请检查配置是否正确<br/>2.空间是否开启函数fsockopen<br/>3.尝试切换PHP不同版本重试';
                    } else {
                        $msg = '请检查SMTP端口填写是否正确，一般默认是465端口，其次25端口，具体请参看各STMP服务商的设置说明。';
                    }
                }
                return array('code'=>0 , 'msg'=>'发送失败:'.$msg);
            } else {
                return array('code'=>1 , 'msg'=>'发送成功');
            }
        } catch (\Exception $e) {
            return array('code'=>0 , 'msg'=>'发送失败: '.$e->errorMessage());
        }
    }
}
