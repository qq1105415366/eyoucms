<?php
/**
 * 易优CMS
 * ============================================================================
 * 版权所有 2016-2028 海口快推科技有限公司，并保留所有权利。
 * 网站地址: http://www.eyoucms.com
 * ----------------------------------------------------------------------------
 * 如果商业用途务必到官方购买正版授权, 以免引起不必要的法律纠纷.
 * ============================================================================
 * Author: 小虎哥 <1105415366@qq.com>
 * Date: 2018-4-3
 */

namespace think\template\taglib\eyou;

//文章付费阅读标签
use think\Db;

class TagArticlepay extends Base
{
    public $users_id = 0;
    public $usersTplVersion = '';

    //初始化
    protected function _initialize()
    {
        parent::_initialize();
        $this->users_id = session('users_id');
        $this->users_id = !empty($this->users_id) ? $this->users_id : 0;
        $this->usersTplVersion = getUsersTplVersion();
    }

    /**
     * 文章付费阅读标签
     * @author hln by 2021-04-20
     */
    public function getArticlepay()
    {
        $aid = $this->aid;
        if (empty($aid)) {
            return '标签articlepay报错：缺少属性 aid 值。';
        }
        $artData = Db::name('archives')
            ->alias('a')
            ->field('a.restric_type, b.content,b.content_ey_m,a.channel')
            ->join('article_content b','a.aid = b.aid')
            ->where('a.aid',$aid)
            ->find();
        if (is_dir('./weapp/CompressContent/')) {                            
            try {                                                
                $compressLogic = new \weapp\CompressContent\logic\CompressContentLogic;                
                $artData = $compressLogic->decrypt_base64_encode(2,$artData);
            } catch (Exception $e) {
            }                
        }        
        $result['displayId'] = ' id="article_display_'.$aid.'_v061972" style="display:none;" ';
        $result['vipDisplayId'] = ' id="article_vipDisplay_'.$aid.'_v061972" style="display:none;" ';

        if (isMobile() && !empty($artData['content_ey_m'])){
            $artData['content'] = $artData['content_ey_m'];
        }
        if (empty($artData['restric_type'])) { // 不限免费
            $result['content'] = !empty($artData['content']) ? $artData['content'] : '';
        }
        else { // 其他
            /*预览内容*/
            $free_content = '';
            $pay_data = Db::name('article_pay')->field('part_free,free_content')->where('aid',$aid)->find();
            if (!empty($pay_data['part_free'])) {
                $free_content = !empty($pay_data['free_content']) ? $pay_data['free_content'] : '';
            }
            /*end*/
            
            $result['content'] = $free_content;
        }

        $result['content'] = htmlspecialchars_decode($result['content']);
        $titleNew = !empty($data['title']) ? $data['title'] : '';
        $result['content'] = img_style_wh($result['content'], $titleNew);
        $result['content'] = handle_subdir_pic($result['content'], 'html');
        if (is_dir('./weapp/Linkkeyword')){
            $LinkkeywordModel = new \weapp\Linkkeyword\model\LinkkeywordModel();
            if (method_exists($LinkkeywordModel, 'handle_content')) {
                $result['content'] = $LinkkeywordModel->handle_content($result['content']);
            }
        }
        $result['contentId'] = ' id="article_content_'.$aid.'_v061972" ';
        if (isMobile()){
            $result['onclick'] = ' href="javascript:void(0);" onclick="ey_article_v968479('.$aid.');" ';//第一种跳转页面支付
        }else{
            $result['onclick'] = ' href="javascript:void(0);" onclick="ArticleBuyNow('.$aid.');" ';//第二种弹框页支付
        }
        $result['onBuyVipClick'] = ' href="javascript:void(0);" onclick="BuyVipClick();" id="vipBuy230816"';
        $version = getCmsVersion();
        $get_content_url = "{$this->root_dir}/index.php?m=api&c=Ajax&a=ajax_get_content";
        $buy_url = ROOT_DIR . "/index.php?m=user&c=Article&a=buy";
        $buy_vip_url = url('user/Level/level_centre', ['aid'=>$aid]);
        $srcurl = get_absolute_url("{$this->root_dir}/public/static/common/js/tag_articlepay.js?v={$version}");

        $result['hidden'] = <<<EOF
<script type="text/javascript">
    var buy_url_v968479 = '{$buy_url}';
    var aid_v968479 = {$aid};
    var root_dir_v968479 = '{$this->root_dir}';
    var buy_vip_url_v968479 = '{$buy_vip_url}';
</script>
<script language="javascript" type="text/javascript" src="{$srcurl}"></script>
<script type="text/javascript">
    ey_ajax_get_content_v968479({$aid},'{$get_content_url}');
</script>
EOF;
        return $result;
    }
}