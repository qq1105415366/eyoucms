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

use think\Db;
use think\Request;

/**
 * 留言表单回复
 */
class TagFormreply extends Base
{
    public $form_type = 0;

    //初始化
    protected function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 获取分页列表
     * @author wengxianhu by 2018-4-20
     */
    public function getFormreply($typeid = 0, $page = 1,$pagesize = 10, $ordermode = 'desc')
    {
        // 给排序字段加上表别名
        $orderby = "aid {$ordermode}";
        $where['typeid'] = $typeid;
        $where['examine'] = 1; //审核通过的才显示

        $paginate = array(
            'page'  => $page,
        );
        $pages = Db::name('guestbook')
            ->where($where)
            ->order($orderby)
            ->paginate($pagesize, false, $paginate);
        $pages = $pages->toArray();
        $result = $pages['data'];
        if (!empty($result)) {
            $aids = get_arr_column($result,'aid');

            $where = [
                'b.aid'     => ['IN', $aids],
                'a.is_del'  => 0,
            ];
            $row       = Db::name('guestbook_attribute')
                ->field('a.attr_name, a.typeid, b.attr_value, b.aid, b.attr_id,a.attr_input_type')
                ->alias('a')
                ->join('__GUESTBOOK_ATTR__ b', 'b.attr_id = a.attr_id', 'LEFT')
                ->where($where)
                ->order('b.aid desc, a.sort_order asc, a.attr_id asc')
                ->select();
            $attr_list = array();
            foreach ($row as $key => $val) {
                if (9 == $val['attr_input_type']){
                    //如果是区域类型,转换名称
                    $val['attr_value'] = Db::name('region')->where('id','in',$val['attr_value'])->column('name');
                    $val['attr_value'] = implode('',$val['attr_value']);
                }else if(10 == $val['attr_input_type']){
                    $val['attr_value'] = date('Y-m-d H:i:s',$val['attr_value']);
                }else if(in_array($val['attr_input_type'], [5,11])){
                    $val['attr_value'] = str_replace(['|',PHP_EOL], ',', $val['attr_value']);
                    $attr_values = explode(',', $val['attr_value']);
                    foreach ($attr_values as $_k => $_v) {
                        $_v = handle_subdir_pic($_v);
                        $_v = "<i class='fa fa-picture-o color_z curpoin' onclick=\"Images('{$_v}', 900, 600);\"></i>";
                        $attr_values[$_k] = $_v;
                    }
                    $val['attr_value'] = implode('&nbsp;', $attr_values);
                }else if(8 == $val['attr_input_type']){
                    $val['attr_value'] = handle_subdir_pic($val['attr_value']);
                    $val['attr_value'] = "<img src='{$this->root_dir}/public/static/admin/images/addon.gif' width='14' /><a href='{$val['attr_value']}' target='_blank'>下载附件</a>";
                }
                $attr_list[$val['aid']]['attr'][$val['attr_id']] = $val;
            }
            if (!empty($attr_list)){
                $users_ids = get_arr_column($result,'users_id');
                $users_ids = array_filter($users_ids);
                $users = [];
                if (!empty($users_ids)) $users = Db::name('users')->where('users_id','in',$users_ids)->field('users_id,username,nickname,head_pic')->getAllWithIndex('users_id');

                foreach ($result as $k => $v){
                    $attr_list[$v['aid']]['reply'] = $v['reply'];
                    $attr_list[$v['aid']]['add_time'] = $v['add_time'];
                    $attr_list[$v['aid']]['reply_time'] = $v['reply_time'];
                    if (!empty($users[$v['users_id']])){
                        $attr_list[$v['aid']]['nickname'] = !empty($users[$v['users_id']]['nickname'])  ? $users[$v['users_id']]['nickname'] : $users[$v['users_id']]['username'];
                        $attr_list[$v['aid']]['head_pic'] = get_head_pic($users[$v['users_id']]['head_pic']);
                    }else{
                        $attr_list[$v['aid']]['nickname'] = '匿名';
                        $attr_list[$v['aid']]['head_pic'] = get_head_pic();
                    }
                }
                $attr_list = array_values($attr_list);
                foreach ($attr_list as $k => $v){
                    if (count($attr_list) == $k + 1){
                        $v['last_one'] = 1;
                        $v['onclick'] = " onclick='get_formreply_list(this);' data-typeid='{$typeid}' data-page='1' data-totalpage='{$pages['last_page']}' data-pagesize='{$pagesize}' data-ordermode='{$ordermode}' ";
                        if ($pages['last_page'] == 1){
                            $v['onclick'] .= ' style="display:none;" ';
                        }
                        $version = getCmsVersion();
                        $srcurl = get_absolute_url("{$this->root_dir}/public/static/common/js/tag_formreply.js?v={$version}");
                        $v['hidden'] = <<<EOF
                        <!-- #formreply{$typeid}# -->
                                <script type="text/javascript">var root_dir_v379494 = '{$this->root_dir}';</script>
<script language="javascript" type="text/javascript" src="{$srcurl}"></script>
EOF;
                    }else{
                        $v['last_one'] = 0;
                    }
                    $attr_list[$k] = $v;
                }
            }
        }
        return $attr_list;
    }

}