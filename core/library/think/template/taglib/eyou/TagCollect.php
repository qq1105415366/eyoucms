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

/**
 * 在内容页模板追加显示浏览量
 */
class TagCollect extends Base
{
    public $users_id = 0;
    public $collect = '';
    public $cancel = '';

    //初始化
    protected function _initialize()
    {
        parent::_initialize();
        $this->collect = input('param.collect/s', '已收藏');
        $this->cancel = input('param.cancel/s', '加入收藏');
        $this->users_id = session('users_id');
        $this->users_id = !empty($this->users_id) ? $this->users_id : 0;
    }

    /**
     * 在内容页模板追加显示浏览量
     * @author wengxianhu by 2018-4-20
     */
    public function getCollect($aid = 0,$collect='',$cancel='',$class='off')
    {
        $aid = !empty($aid) ? intval($aid) : $this->aid;
        if (empty($aid)) {
            return '标签collect报错：缺少属性 aid 值。';
        }

        $version = getCmsVersion();
        $loginurl = url('user/Users/login');
        $result['cancel'] = $cancel;
        $result['onclick'] = ' href="javascript:void(0);" id="ey_v378141_'.$aid.'" data-aid="'.$aid.'" data-name="eyou_collect" data-loginurl="'.$loginurl.'" data-class_value="'.$class.'" data-collected="'.$collect.'" data-cancel="'.$cancel.'" onclick="ey_v378141('.$aid.',\''.$class.'\',this);" ';
        $result['numId'] = ' id="ey_cnum_v379494_'.$aid.'" ';
        $srcurl = get_absolute_url("{$this->root_dir}/public/static/common/js/tag_collection_list.js?v={$version}");
        $result['hidden'] = <<<EOF
        <script type="text/javascript">
        var collected_v379494_{$aid} = "{$collect}";
        var cancel_v379494_{$aid} = "{$cancel}";
        var root_dir_v379494 = '{$this->root_dir}';
        var loginurl_v379494 = '{$loginurl}';
</script>

    <script>window.tag_collection_list || document.write('<script src="{$srcurl}" type="text/javascript"><\/script>')</script>
    <script type="text/javascript">ey_v377550("{$aid}","{$class}");</script>
EOF;
        // $result['hidden'] = '';
        return $result;
    }
}