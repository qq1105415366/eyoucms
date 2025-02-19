<?php
/**
 * 易优CMS
 * ============================================================================
 * 版权所有 2016-2028 海南赞赞网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.eyoucms.com
 * ----------------------------------------------------------------------------
 * 如果商业用途务必到官方购买正版授权, 以免引起不必要的法律纠纷.
 * ============================================================================
 * Author: 陈风任 <491085389@qq.com>
 * Date: 2022-03-10
 */

namespace app\api\model\v1;

use think\Db;
use think\Cache;
use Grafika\Color;
use Grafika\Grafika;
require_once './vendor/grafika/src/autoloader.php';

/**
 * 微信小程序商品海报模型
 */
load_trait('controller/Jump');

class Poster extends Base
{
    use \traits\controller\Jump;

    //初始化
    protected function initialize()
    {
        // 需要调用`Model`的`initialize`方法
        parent::initialize();

        $this->version = 'v1';//初始海报版本
        $this->aid = 0;
        $this->typeid = 0;
        $this->channel = 1;
        $this->product = [];
        $this->postData = [];
        $this->posterPath = '';
        $this->posterImage = '';
        $this->appletsQrcode = [];
        $this->post = [];
        $this->users = [];
        $this->appletsType = 1;
    }

    // 商品海报生成处理
    // $appletsType: 1=开源小程序，2=可视化小程序，3=微信公众号
    public function getCreateGoodsShareQrcodePoster($post = [], $channel = 1, $appletsType = 1)
    {
        $this->post = $post;
        // 商品ID
        $this->aid = $post['aid'];
        // 商品栏目ID
        $this->typeid = $post['typeid'];
        // 会员ID
        $this->users_id = !empty($post['mid']) ? $post['mid'] : 0;
        // 分销商会员ID
        $this->usersID = !empty($post['users_id']) ? intval($post['users_id']) : 0;
        // 分销商ID
        $this->dealerID = !empty($post['dealer_id']) ? intval($post['dealer_id']) : 0;
        // 图片、海报保存目录
        $this->posterPath = UPLOAD_PATH . 'tmp/poster_' . $this->typeid . '_' . $this->aid . '/';
        // 存在 分销商会员ID 和 分销商ID 则执行
        if (!empty($this->usersID) && !empty($this->dealerID)) {
            $this->posterPath = UPLOAD_PATH . 'tmp/poster_' . $this->typeid . '_' . $this->aid . '_' . $this->usersID . '_' . $this->dealerID . '/';
        }
        // 版本
        if (!empty($post['version'])) $this->version = $post['version'];
        if ('v2' == $this->version) $this->users = $this->getUsersInfo();
        // 模型ID
        $this->channel = intval($channel);
        // 背景图片处理
        if (1 == $this->channel) {
            $this->posterImage = './public/static/common/images/article-bg.png';
        } else if (2 == $this->channel) {
            $this->posterImage = './public/static/common/images/product-bg.png';
        }
        if ('v2' == $this->version){
            $this->posterImage = './public/static/common/images/product-bg-v2.png';
        }

        // 获取商品信息
        $this->product = $this->getProductData();
        
        // 来源类型
        $this->appletsType = !empty($post['appletsType']) ? intval($post['appletsType']) : intval($appletsType);
        if (3 === intval($this->appletsType)) {
            $this->appletsQrcode = $this->weChatGoodsShareQrcodePoster();
        } else {
            $fenbao = input('param.fenbao/d');
            $page = 'pages/';
            if (!empty($fenbao)) $page = 'otherpages/';
            // 生成小程序二维码需携带参数
            if (1 === intval($this->appletsType)) {
                if (!empty($post['seckill_goods_id'])) {
                    $page .= "seckill/detail";
                } else {
                    $page .= "archives/product/view";
                }
            } else if (2 === intval($this->appletsType)) {
                $page .= "article/view";
            } else {
                $page .= "index/index";
            }
            $scene = 'aid=' . $this->aid;
            if (!empty($this->typeid)) {
                //生成二维码scene长度有限制  所有typeid为空就不传了
                $scene .= '&typeid=' . $this->typeid;
            }
            if (!empty($post['seckill_goods_id'])) {
                $scene = 'gid=' . $post['seckill_goods_id'];
            }
            // 存在 分销商会员ID 和 分销商ID 则执行
            if (!empty($this->usersID) && !empty($this->dealerID)) {
                $scene .= '&u_id=' . $this->usersID . '&d_id=' . $this->dealerID;
            }
            $width = '430';
            $this->postData = compact('page', 'scene', 'width');
    
            // 小程序二维码处理
            $this->appletsQrcode = $this->getAppletsQrcode();
        }

        // 组合并返回商品分享海报图片
        if ('v2' == $this->version) {
            return $this->getProductSharePosterImageV2();
        } else {
            return $this->getProductSharePosterImage();
        }
    }

    private function weChatGoodsShareQrcodePoster()
    {
        // 保存图片的完整路径
        $qrCodeSavePath = 'qrcode_' . md5($this->usersID . $this->aid) . '_h5.png';
        if (!empty($this->ajaxGet)) $qrCodeSavePath = 'qrcode_' . md5($this->ajaxGet . $this->aid) . '_h5.png';
        $qrCodeSavePath = $this->posterPath . $qrCodeSavePath;
        // 二维码URL
        $qrCodeSaveUrl = request()->domain() . ROOT_DIR . '/h5/#/otherpages/archives/product/view?aid=' . $this->aid;
        // 生成二维码
        vendor('wechatpay.phpqrcode.phpqrcode');
        $qrcode = new \QRcode;
        $qrcode->png($qrCodeSaveUrl, $qrCodeSavePath);
        return [
            'status' => true,
            'qrcode' => $qrCodeSavePath,
        ];
    }

    public function getUsersInfo()
    {
        $users = Db::name('users')->field('head_pic,nickname')->where('users_id', $this->users_id)->find();
        if (empty($users)) $this->error('请先登录');
        // 商品图片处理
        $users['head_pic'] = handle_subdir_pic($users['head_pic'],'img',false,true);
        if (is_http_url($users['head_pic'])) {
            //打开输出缓冲区并获取远程图片
            ob_start();
            $context = stream_context_create(
                array('http' => array(
                    'follow_location' => false // don't follow redirects
                ))
            );
            readfile($users['head_pic'],false,$context);
            $img = ob_get_contents();
            ob_end_clean();
            preg_match("/[\/]([^\/]*)[\.]?[^\.\/]*$/",$users['head_pic'],$m);

            // 保存图片的完整路径
            $LitpicSavePath = $this->posterPath . 'users_' . md5($this->users_id) . '.png';
            // 若文件夹不存在则创建
            !is_dir($this->posterPath) && tp_mkdir($this->posterPath);
            file_put_contents($LitpicSavePath, $img);

            // 返回数据
            $users['head_pic'] = "./".$LitpicSavePath;
        } else {
            $users['head_pic'] = ".".$users['head_pic'];
        }

        // 如果这张图片已经被删除则执行
        if (!file_exists($users['head_pic'])) $users['head_pic'] = ROOT_PATH . "public/static/admin/images/admint.png";

        return $users;
    }

    // 返回已处理的商品信息
    private function getProductData()
    {
        // 查询商品信息
        $where['aid'] = $this->aid;
        $field = 'aid, title, litpic, users_price, seo_description';
        $Product = Db::name("archives")->where($where)->field($field)->find();

        if (!empty($Product)) {
            if (!empty($this->post['seckill_goods_id'])) {
                //检测是否安装秒杀插件
                if (is_dir('./weapp/Seckill/')) {
                    $SeckillRow = model('Weapp')->getWeappList('Seckill');
                    if (!empty($SeckillRow) && 1 != intval($SeckillRow['status'])) {
                        $this->error('请先安装秒杀插件');
                    }
                } else {
                    $this->error('请先安装秒杀插件');
                }
                $seckill_goods = Db::name('weapp_seckill_archives')->where('goods_id', $this->post['seckill_goods_id'])->find();
                if (!empty($seckill_goods['is_spec'])) {
                    $seckill_goods['seckill_price'] = Db::name('weapp_seckill_product_spec_value')->where('goods_id', $this->post['seckill_goods_id'])->min('seckill_price');
                }
                $Product['crossed_price'] = $Product['users_price'];
                $Product['users_price'] = $seckill_goods['seckill_price'];
            }

            // 商品图片处理
            $ProductLitpic = $this->get_default_pic($Product['litpic'], true);
            // 保存图片的完整路径
            $LitpicSavePath = $this->posterPath . 'product_' . md5($this->aid . $this->typeid) . '.png';
            // 若文件夹不存在则创建
            !is_dir($this->posterPath) && tp_mkdir($this->posterPath);

            // 图片保存到文件处理
            $ch = curl_init($ProductLitpic);
            curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
            curl_setopt($ch,CURLOPT_BINARYTRANSFER,true);
            // https请求 不验证证书和hosts
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);   //重要,源文件链接带https的话就必须使用
            curl_setopt($ch,CURLOPT_TIMEOUT,60);

            $img = curl_exec($ch);
            curl_close($ch);
            $fp = fopen($LitpicSavePath, 'w');
            fwrite($fp, $img);
            fclose($fp);
            // 返回数据
            $Product['litpic'] = $LitpicSavePath;

            return $Product;
        } else {
            $this->error('商品不存在');
        }
    }

    // 返回已处理的小程序二维码
    private function getAppletsQrcode()
    {
        // 保存图片的完整路径
        $qrcodeSavePath = $this->posterPath . 'qrcode_' . md5($this->aid . $this->typeid) . '.png';

        // 若文件夹不存在则创建
        !is_dir($this->posterPath) && tp_mkdir($this->posterPath);

        // 是否配置小程序信息
        if (1 === intval($this->appletsType)) {
            $applets = 'openSource';
        } else if (2 === intval($this->appletsType)) {
            $applets = 'visualization';
        }
        $appletsToken = get_weixin_access_token(true, $applets);
        if (empty($appletsToken['code'])) {
            return [
                'status' => false,
                'msg' => $appletsToken['msg'],
            ];
        }

        // 调用微信接口获取小程序二维码
        return $this->getWeChatAppletsQrcode($appletsToken['access_token'], $qrcodeSavePath);
    }

    // 返回微信小程序商品详情页二维码
    private function getWeChatAppletsQrcode($accessToken = null, $qrcodeSavePath = null)
    {
        // 获取微信小程序二维码
        $postUrl = "https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token=" . $accessToken;
        $appletsQrcode = httpRequest($postUrl, 'POST', json_encode($this->postData, JSON_UNESCAPED_UNICODE));
        $is_fail = strpos($appletsQrcode,"errcode");
        // 保存图片，保存成功则返回图片路径
        if ($is_fail != false){   //报错
            $error_msg = json_decode($appletsQrcode,true);
            $result = [
                'status' => false,
                'errcode' => $error_msg['errcode'],
                'errmsg' => '获取二维码失败，'.$error_msg['errcode'].":".$error_msg['errmsg'],
            ];
        } else if (@file_put_contents($qrcodeSavePath, $appletsQrcode)) {
            $result = [
                'status' => true,
                'qrcode' => $qrcodeSavePath,
            ];
        } else {
            $result = [
                'status' => false,
                'errcode' => 10000,
                'errmsg' => '获取二维码失败，请重试',
            ];
        }

        return $result;
    }

    // 返回商品分享海报图片
    private function getProductSharePosterImage()
    {
        $Grafika = new Grafika;
        $editor = $Grafika::createEditor(['Gd']);
        // 打开海报背景图
        $editor->open($backdropImage, $this->posterImage);
        // 打开商品图片
        $editor->open($ProductLitpic, $this->product['litpic']);
        // 重设商品图片宽高
        $editor->resizeExact($ProductLitpic, 690, 690);
        // 商品图片添加到背景图
        $editor->blend($backdropImage, $ProductLitpic, 'normal', 1.0, 'top-left', 30, 30);

        // 字体文件路径
        $fontPath = Grafika::fontsDir() . '/' . 'st-heiti-light.ttc';
        // 商品名称处理换行
        $fontSize = 30;
        $productName = $this->wrapText($fontSize, 0, $fontPath, $this->product['title'], 680, 2);
        // 写入商品名称
        $editor->text($backdropImage, $productName, $fontSize, 30, 750, new Color('#333333'), $fontPath);

        //写入商品价格
        if (1 == $this->channel) {
            // 字体文件路径
            $fontPath = Grafika::fontsDir() . '/' . 'st-heiti-light.ttc';
            // 文档描述处理换行
            $fontSize = 20;
            $seoDescription = $this->wrapText($fontSize, 0, $fontPath, $this->product['seo_description'], 500, 4);
            // 写入文档描述
            $editor->text($backdropImage, $seoDescription, $fontSize, 30, 920, new Color('#333333'), $fontPath);
        } else if (2 == $this->channel) {
            $editor->text($backdropImage, $this->product['users_price'], 38, 62, 964, new Color('#ff4444'));
        }

        // 打开小程序码
        if (!empty($this->appletsQrcode['status'])){
            $editor->open($qrcodeImage, $this->appletsQrcode['qrcode']);
            // 重设小程序码宽高
            $editor->resizeExact($qrcodeImage, 140, 140);
            // 小程序码添加到背景图
            $editor->blend($backdropImage, $qrcodeImage, 'normal', 1.0, 'top-left', 570, 914);

            // 保存商品海报
            $posterImageName = 'product_poster_' . md5($this->aid . $this->typeid) . '.png';
            $posterImagePath = $this->posterPath . $posterImageName;
            $editor->save($backdropImage, $posterImagePath);

            // 返回商品海报
            $posterImagePath = request()->domain() . ROOT_DIR . '/' . $posterImagePath;
            return [
                'name' => $posterImageName,
                'path' => $this->posterPath,
                'poster' => $posterImagePath
            ];
        } else {
            return $this->appletsQrcode;
        }
    }

    // 处理文字超出长度自动换行
    private function wrapText($fontsize, $angle, $fontface, $string, $width, $max_line = null)
    {
        // 这几个变量分别是 字体大小, 角度, 字体名称, 字符串, 预设宽度
        $content = "";
        // 将字符串拆分成一个个单字 保存到数组 letter 中
        $letter = [];
        for ($i = 0; $i < mb_strlen($string, 'UTF-8'); $i++) {
            $letter[] = mb_substr($string, $i, 1, 'UTF-8');
        }
        $line_count = 0;
        foreach ($letter as $l) {
            $testbox = imagettfbbox($fontsize, $angle, $fontface, $content . ' ' . $l);
            // 判断拼接后的字符串是否超过预设的宽度
            if (($testbox[2] > $width) && ($content !== "")) {
                $line_count++;
                if ($max_line && $line_count >= $max_line) {
                    $content = mb_substr($content, 0, -1, 'UTF-8') . "...";
                    break;
                }
                $content .= "\n";
            }
            $content .= $l;
        }
        return $content;
    }

    // 返回商品分享海报图片 第二套 需要登录,分享商品带用户信息,分享文章应该使用第一套
    private function getProductSharePosterImageV2()
    {
        $Grafika = new Grafika;
        $editor = $Grafika::createEditor(['Gd']);

        // 字体文件路径
        $fontPath = Grafika::fontsDir() . '/' . 'st-heiti-light.ttc';
        // $fontPath = Grafika::fontsDir() . '/' . '联想小新黑体 常规.ttf';
        // 打开海报背景图
        $editor->open($backdropImage, $this->posterImage);
        $this->CircularImage($this->users['head_pic'], $this->users['head_pic']);
        //处理用户头像
        $editor->open($head_pic, $this->users['head_pic']);
        // 重设用户头像宽高
        $editor->resizeExact($head_pic, 88, 92);
        // 用户头像添加到背景图
        $editor->blend($backdropImage, $head_pic, 'normal', 1.0, 'top-left', 20, 30);

        // 用户名
        $editor->text($backdropImage, $this->users['nickname'], 20, 130, 50, new Color('#000'), $fontPath);
        $editor->text($backdropImage, '推荐一个好物给你，请查收！', 17, 130, 90, new Color('#3a3a3a'), $fontPath);

        $this->CircularImageBorder($this->product['litpic'],$this->product['litpic']);
        // 打开商品图片
        $editor->open($ProductLitpic, $this->product['litpic']);
        // 重设商品图片宽高
        $editor->resizeExact($ProductLitpic, 560, 600);

        // 商品图片添加到背景图
        $editor->blend($backdropImage, $ProductLitpic, 'normal', 1.0, 'top-left', 20, 140);

        // 商品名称处理换行
        $fontSize = 18;
        $productName = $this->wrapText($fontSize, 0, $fontPath, $this->product['title'], 565, 2);
        // 写入商品名称
        $editor->text($backdropImage, $productName, $fontSize, 24, 765, new Color('#000'), $fontPath);

        //写入商品价格
        $editor->text($backdropImage, '￥'.$this->product['users_price'], 25, 25, 845, new Color('#FF0000'), $fontPath);
//        if (!empty($this->product['crossed_price'])){
//            //写入商品划线价格
//            $editor->text($backdropImage, '￥'.$this->product['crossed_price'], 16, 180, 845, new Color('#959795'),$fontPath);
//        }

        $editor->text($backdropImage, '长按识别或扫描二维码！', 17, 20, 890, new Color('#464544'), $fontPath);
        $editor->text($backdropImage, '更多品质好货等着你！', 17, 20, 930, new Color('#eaaf49'), $fontPath);

        // 打开小程序码
        if (!empty($this->appletsQrcode['status'])) {
            // 如果不是微信公众号生成的海报则将二维码处理成圆形
            if (!in_array($this->appletsType, [3])) $this->CircularImage($this->appletsQrcode['qrcode'], $this->appletsQrcode['qrcode']);
            $editor->open($qrcodeImage, $this->appletsQrcode['qrcode']);
            // 重设小程序码宽高
            $editor->resizeExact($qrcodeImage, 120, 120);
            // 小程序码添加到背景图
            $editor->blend($backdropImage, $qrcodeImage, 'normal', 1.0, 'top-left', 410, 840);

            // 保存商品海报
            $posterImageName = 'product_poster_' . md5($this->aid . $this->typeid) . '.png';
            $posterImagePath = $this->posterPath . $posterImageName;
            $editor->save($backdropImage, $posterImagePath);

            // 返回商品海报
            $posterImagePath = request()->domain() . ROOT_DIR . '/' . $posterImagePath;
            return [
                'name' => $posterImageName,
                'path' => $this->posterPath,
                'poster' => $posterImagePath
            ];
        } else {
            return $this->appletsQrcode;
        }
    }

    // 生成圆形用户头像
    private function CircularImage($ImagePath = '', $SaveName = '')
    {
        $srcImg = imagecreatefromstring(file_get_contents($ImagePath));
        $w = imagesx($srcImg);
        $h = imagesy($srcImg);
        $w = $h = min($w, $h);
        $newImg = imagecreatetruecolor($w, $h);

        // 这一句一定要有
        imagesavealpha($newImg, true);

        // 拾取一个完全透明的颜色,最后一个参数127为全透明
        $bg = imagecolorallocatealpha($newImg, 255, 255, 255, 127);
        imagefill($newImg, 0, 0, $bg);
        $r = $w / 2; //圆半径
        for ($x = 0; $x < $w; $x++) {
            for ($y = 0; $y < $h; $y++) {
                $rgbColor = imagecolorat($srcImg, $x, $y);
                if (((($x - $r) * ($x - $r) + ($y - $r) * ($y - $r)) < ($r * $r))) {
                    imagesetpixel($newImg, $x, $y, $rgbColor);
                }
            }
        }

        // 输出图片到文件
        imagepng($newImg, $SaveName);

        // 释放空间
        imagedestroy($srcImg);
        imagedestroy($newImg);
    }

    // 处理商品图边框形状 $r 圆角长度
    private function CircularImageBorder($ImagePath = '', $SaveName = '',$r = 20)
    {
        $srcImg = imagecreatefromstring(file_get_contents($ImagePath));
        $w = imagesx($srcImg);
        $h = imagesy($srcImg);
        $w = $h = min($w, $h);
        $newImg = imagecreatetruecolor($w, $h);

        // 这一句一定要有
        imagesavealpha($newImg, true);

        // 拾取一个完全透明的颜色,最后一个参数127为全透明
        $bg = imagecolorallocatealpha($newImg, 255, 255, 255, 127);
        imagefill($newImg, 0, 0, $bg);

        // 创建圆角
        for ($x = 0; $x < $w; $x++) {
            for ($y = 0; $y < $h; $y++) {
                $color = imagecolorat($srcImg, $x, $y);
                if (($x >= $r && $x <= $w - $r) || ($y >= $r && $y <= $h - $r)) {
                    //不在四角范围内,直接画
                    imagesetpixel($newImg, $x, $y, $color);
                } else {
                    //不在四角范围内,直接画
                    //上左
                    if (((($x - $r) * ($x - $r) + ($y - $r) * ($y - $r)) <= ($r * $r))) {
                        imagesetpixel($newImg, $x, $y, $color);
                    }
                    $y_x = $w - $r;
                    //上右
                    if (((($x - $y_x) * ($x - $y_x) + ($y - $r) * ($y - $r)) <= ($r * $r))) {
                        imagesetpixel($newImg, $x, $y, $color);
                    }
                    //下左
                    $y_y = $h - $r;
                    if (((($x - $r) * ($x - $r) + ($y - $y_y) * ($y - $y_y)) <= ($r * $r))) {
                        imagesetpixel($newImg, $x, $y, $color);
                    }
                    //下右
                    $y_y = $h - $r;
                    if (((($x - $y_x) * ($x - $y_x) + ($y - $y_y) * ($y - $y_y)) <= ($r * $r))) {
                        imagesetpixel($newImg, $x, $y, $color);
                    }

                }
            }
        }

        // 输出图片到文件
        imagepng($newImg, $SaveName);

        // 释放空间
        imagedestroy($srcImg);
        imagedestroy($newImg);
    }
}