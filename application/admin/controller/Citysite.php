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

namespace app\admin\controller;

use think\Db;
use think\Page;
use think\Cache;

class Citysite extends Base
{
    public static $top_city = [
        "山东"    => ["青岛","济南","烟台","潍坊","临沂","济宁","淄博","威海","东营","德州","泰安","聊城","滨州","菏泽","枣庄"],
        "江苏"    => ["苏州","南京","无锡","南通","常州","徐州","扬州","盐城","泰州","镇江","淮安","连云港","宿迁"],
        "广东"    => ["深圳","广州","佛山","东莞","惠州","珠海","江门","茂名","中山","湛江"],
        "浙江"    => ["杭州","宁波","温州","绍兴","嘉兴","台州","金华","湖州"],
        "河北"    => ["唐山","石家庄","沧州","邯郸","保定","廊坊"],
        "河南"    => ["郑州","洛阳","南阳","许昌","周口","新乡"],
        "湖南"    => ["长沙","岳阳","常德","衡阳","株洲","郴州"],
        "福建"    => ["福州","泉州","厦门","漳州"],
        "内蒙古"  => ["鄂尔多斯","呼和浩特","包头"],
        "湖北"    => ["武汉","襄阳","宜昌"],
        "辽宁"    => ["大连","沈阳","鞍山"],
        "陕西"    => ["西安","榆林","咸阳"],
        "安徽"    => ["合肥","芜湖"],
        "广西"    => ["南宁","柳州"],
        "贵州"    => ["贵阳","遵义"],
        "黑龙江"  => ["哈尔滨","大庆"],
        "吉林"    => ["长春","吉林"],
        "新疆"    => ["乌鲁木齐"],
        "江西"    => ["南昌"],
        "四川"    => ["成都"],
        "云南"    => ["昆明"],
        "甘肃"    => ["兰州"],
        "山西"    => ["太原"],
        "北京"    => [],
        "天津"    => [],
        "上海"    => [],
        "重庆"    => [],
    ];

    private $web_citysite_open;
    // 禁用的目录名称
    private $disableDirname = [];

    public function _initialize(){
        parent::_initialize();
        $this->disableDirname      = config('global.disable_dirname');

        $functionLogic = new \app\common\logic\FunctionLogic;
        $functionLogic->validate_authorfile(2);

        $this->web_citysite_open = tpCache('global.web_citysite_open');
        $this->assign('web_citysite_open', $this->web_citysite_open);
    }

    public function index()
    {
        $assign_data = array();
        $condition = array();
        // 获取到所有GET参数
        $param = input('param.');
        $parent_id = input('pid/d', 0);

        // 应用搜索条件
        foreach (['keywords','pid'] as $key) {
            $param[$key] = addslashes(trim($param[$key]));
            if (isset($param[$key]) && $param[$key] !== '') {
                if ($key == 'keywords') {
                    $condition['name'] = array('LIKE', "%{$param[$key]}%");
                } else if ($key == 'pid') {
                    $condition['parent_id'] = array('eq', $param[$key]);
                } else {
                    $condition[$key] = array('eq', $param[$key]);
                }
            }
        }

        // 上一级区域名称
        $parentInfo = Db::name('citysite')->where(['id'=>$parent_id])->find();
        $parentLevel = !empty($parentInfo['level']) ? intval($parentInfo['level']) : 0;
        $condition['level'] = $parentLevel + 1;

        $regionM =  Db::name('citysite');
        $count = $regionM->where($condition)->count('id');// 查询满足要求的总记录数
        $Page = $pager = new Page($count, config('paginate.list_rows'));// 实例化分页类 传入总记录数和每页显示的记录数
        $list = $regionM->where($condition)->order('sort_order asc, id asc')->limit($Page->firstRow.','.$Page->listRows)->select();
        foreach ($list as $key => $val) {
            $val['siteurl'] = siteurl($val);
            $list[$key] = $val;
        }

        $show = $Page->show();// 分页显示输出
        $this->assign('page',$show);// 赋值分页输出
        $this->assign('list',$list);// 赋值数据集
        $this->assign('pager',$pager);// 赋值分页对象
        $this->assign('parentInfo',$parentInfo);

        $set_is_open = (int)tpSetting('citysite.citysite_set_is_open', [], 'cn');
        $set_is_open = empty($set_is_open) ? 1 : 0;
        $this->assign('set_is_open',$set_is_open);

        return $this->fetch();
    }

    public function add(){
        if (IS_POST) {
            $post = input('post.');
            $post['name'] = trim($post['name']);
            $post['domain'] = preg_replace("/[^a-zA-Z0-9]+/", "", strtolower($post['domain']));

            // --存储数据
            $nowData = array(
                'initial'   => getFirstCharter($post['name']),
                'seo_description'   => !empty($post['seo_description']) ? $post['seo_description'] : '',
                'sort_order'    => 100,
                'add_time'    => getTime(),
                'update_time'    => getTime(),
            );
            if (!empty($post['city_id'])){
                $nowData['level'] = 3;
                $nowData['parent_id'] = intval($post['city_id']);
                $nowData['topid'] = intval($post['province_id']);
            } else if (!empty($post['province_id'])){
                $nowData['level'] = 2;
                $nowData['parent_id'] = intval($post['province_id']);
                $nowData['topid'] = intval($post['province_id']);
            } else {
                $nowData['level'] = 1;
                $nowData['parent_id'] = 0;
                $nowData['topid'] = 0;
            }
            $data = array_merge($post, $nowData);

            if (empty($data['name'])) {
                $this->error('区域名称不能为空！');
            }

            //  区域名称是否已存在
            $count = Db::name('citysite')->where([
                    'name' => $data['name'],
                    'parent_id' => $data['parent_id'],
                ])->count();
            if (!empty($count)) {
                $this->error('区域名称已存在，请更换！');
            }

            if (!empty($data['domain'])) {
                $count = Db::name('citysite')->where([
                        'domain'    => $data['domain'],
                    ])->count();
                if (!empty($count)) {
                    $this->error('英文名称已存在！');
                }
                // 检测
                if (!empty($data['domain']) && !$this->domain_unique($data['domain'])) {
                    $this->error('英文名称与系统内置冲突，请更改！');
                }
                /*--end*/
            } else {
                $this->error('英文名称不能为空！');
            }

            $insertId = M('citysite')->insertGetId($data);
            if (false !== $insertId) {
                \think\Cache::clear('citysite');
                adminLog('新增区域：'.$data['name']);
                $this->success("操作成功");
            }else{
                $this->error("操作失败");
            }
            exit;
        }

        $pid = input('param.pid/d', 0);
        $region = array_reverse($this->getParentCitysiteId($pid));
        $assign_data['province_id'] = !empty($region[0]) ? $region[0] : 0;
        $assign_data['city_id'] = !empty($region[1]) ? $region[1] : 0;

        // 省份列表
        $province_all = $this->get_site_province_all();
        $assign_data['province_all'] = $province_all;
        $assign_data['rootDomain'] = $this->request->rootDomain().ROOT_DIR;

        $this->assign($assign_data);

        return $this->fetch();
    }

    //批量新增
    public function batch_add(){
        if (IS_POST) {
            $post = input('post.');

            $name = trim($post['name']);
            if (empty($name)) {
                $this->error('区域名称不能为空！');
            }
            $nameArr = explode("\r\n", $name);
            //去除数组空值、左右空格
            foreach ($nameArr as $key => $val) {
                $val = trim($val);
                if (empty($val)) {
                    unset($nameArr[$key]);
                } else {
                    $nameArr[$key] = $val;
                }
            }
            $nameArr = array_unique($nameArr); //去重

            if (!empty($post['city_id'])){
                $level = 3;
                $parent_id = intval($post['city_id']);
                $topid = intval($post['province_id']);
            } else if (!empty($post['province_id'])){
                $level = 2;
                $parent_id = intval($post['province_id']);
                $topid = intval($post['province_id']);
            } else {
                $level = 1;
                $parent_id = 0;
                $topid = 0;
            }
            $have_name = Db::name('citysite')->where(['parent_id' => $parent_id])->column('name');
            $addData = $insert_name = [];
            foreach ($nameArr as $key => $val) {
                if(empty($val) || in_array($val,$have_name))
                {
                    continue;
                }
                $insert_name[] = $val;
                $domain = preg_replace("/[^a-zA-Z0-9]+/", "", get_pinyin($val));
                $domain = $this->rand_domain($domain,$level);
                $addData[] = [
                    'name'  => $val,
                    'domain'  => $domain,
                    'level' => $level,
                    'parent_id' => $parent_id,
                    'topid' => $topid,
                    'is_open' => !empty($post['is_open']) ? intval($post['is_open']) : 0,
                    'showall' => !empty($post['showall']) ? intval($post['showall']) : 1,
                    'seoset'   => !empty($post['seoset']) ? intval($post['seoset']) : 0,
                    'initial'   => getFirstCharter($val),
                    'seo_title' => str_replace(['{region}','{区域}'], $val, $post['seo_title']),
                    'seo_keywords' => str_replace(['{region}','{区域}'], $val, $post['seo_keywords']),
                    'seo_description' => str_replace(['{region}','{区域}'], $val, $post['seo_description']),
                    'sort_order'    => 100,
                    'add_time'    => getTime(),
                    'update_time'    => getTime(),
                ];
            }
            if (empty($addData)) {
                $this->error('区域名称已全部存在！');
            }
            $res = Db::name('citysite')->insertAll($addData);
            if (false !== $res) {
                \think\Cache::clear('citysite');
                adminLog('批量新增区域：'.implode(',',$insert_name));
                $this->success("操作成功");
            }else{
                $this->error("操作失败");
            }
            exit;
        }

        $pid = input('param.pid/d', 0);
        $region = array_reverse($this->getParentCitysiteId($pid));
        $assign_data['province_id'] = !empty($region[0]) ? $region[0] : 0;
        $assign_data['city_id'] = !empty($region[1]) ? $region[1] : 0;

        // 省份列表
        $province_all = $this->get_site_province_all();
        $assign_data['province_all'] = $province_all;
        $assign_data['rootDomain'] = $this->request->rootDomain().ROOT_DIR;

        $this->assign($assign_data);

        return $this->fetch();
    }

    public function edit(){
        if (IS_POST) {
            $post = input('post.');
            if(!empty($post['id'])){
                $post['id'] = intval($post['id']);
                $post['name'] = trim($post['name']);
                $post['domain'] = preg_replace("/[^a-zA-Z0-9]+/", "", strtolower($post['domain']));

                // --存储数据
                $nowData = array(
                    'initial'   => getFirstCharter($post['name']),
                    'seo_description'   => !empty($post['seo_description']) ? $post['seo_description'] : '',
                    'update_time'    => getTime(),
                );

                if (!isset($post['province_id'])) $post['province_id'] = $post['old_province_id'];
                if (!isset($post['city_id'])) $post['city_id'] = $post['old_city_id'];

                if (!empty($post['city_id'])){
                    $nowData['level'] = 3;
                    $nowData['parent_id'] = intval($post['city_id']);
                    $nowData['topid'] = intval($post['province_id']);
                } else if (!empty($post['province_id'])){
                    $nowData['level'] = 2;
                    $nowData['parent_id'] = intval($post['province_id']);
                    $nowData['topid'] = intval($post['province_id']);
                } else {
                    $nowData['level'] = 1;
                    $nowData['parent_id'] = 0;
                    $nowData['topid'] = 0;
                }
                $data = array_merge($post, $nowData);

                if (empty($data['name'])) {
                    $this->error('区域名称不能为空！');
                }

                //  区域名称是否已存在
                $count = Db::name('citysite')->where([
                        'id' => ['NEQ', $data['id']],
                        'name' => $data['name'],
                        'parent_id' => $data['parent_id'],
                    ])->count();
                if (!empty($count)) {
                    $this->error('区域名称已存在，请更换！');
                }

                if (!empty($data['domain'])) {
                    $count = Db::name('citysite')->where([
                            'domain'    => $data['domain'],
                            'id'    => ['NEQ', $data['id']],
                        ])->count();
                    if (!empty($count)) {
                        $this->error('英文名称已存在！');
                    }
                    // 检测
                    if (!empty($data['domain']) && !$this->domain_unique($data['domain'], $data['id'])) {
                        $this->error('英文名称与系统内置冲突，请更改！');
                    }
                    /*--end*/
                } else {
                    $this->error('英文名称不能为空！');
                }

                $r = M('citysite')->where([
                        'id'    => $post['id'],
                    ])
                    ->cache(true, null, "citysite")
                    ->update($data);
                if (false !== $r) {
                    Cache::clear('citysite');
                    // 同步处理子级城市
                    if (empty($post['province_id'])) {
                        Db::name('citysite')->where([
                            'parent_id' => $post['id'],
                        ])->update([
                            'level' => 2,
                            'topid' => $post['id'],
                            'update_time' => getTime(),
                        ]);
                    } else if (!empty($post['province_id']) && empty($post['city_id'])) {
                        Db::name('citysite')->where([
                            'parent_id' => $post['id'],
                        ])->update([
                            'level' => 3,
                            'topid' => $data['topid'],
                            'update_time' => getTime(),
                        ]);
                    }
                    adminLog('编辑区域：'.$data['name']);
                    $this->success("操作成功");
                }
            }
            $this->error("操作失败");
        }

        $id = input('param.id/d', 0);
        $info = model("Citysite")->getInfo($id);
        $assign_data['field'] = $info;
        $region = array_reverse($this->getParentCitysiteId($info['parent_id']));
        $assign_data['province_id'] = !empty($region[0]) ? $region[0] : 0;
        $assign_data['city_id'] = !empty($region[1]) ? $region[1] : 0;

        // 省份列表
        $province_all = $this->get_site_province_all();
        $assign_data['province_all'] = $province_all;
        $assign_data['rootDomain'] = $this->request->rootDomain().ROOT_DIR;

        // 是否有下级以及层级
        $assign_data['childrenLevelCount'] = Db::name('citysite')->field('level')->where(['parent_id|topid'=>$id])->group('level')->count();

        $this->assign($assign_data);

        return $this->fetch();
    }

    public function conf(){
        if (IS_POST) {
            $post = $data = input('post.');
            foreach ($data as $key => $val) {
                $val = trim($val);
                $data[$key] = $val;
            }
            tpCache('site', $data);
            adminLog('多站点功能配置');
            $this->success("操作成功");
        }

        $assign_data = [];
        $row = tpCache('site');
        $assign_data['row'] = $row;
        // 站点区域
        $site_default_home = !empty($row['site_default_home']) ? intval($row['site_default_home']) : 0;
        $citysiteLogic = new \app\common\logic\CitysiteLogic;
        $assign_data['citysite_html'] = $citysiteLogic->citysite_list(0, $site_default_home, true, 0, array(), false);
        $assign_data['site_default_home'] = $site_default_home;

        $this->assign($assign_data);
        return $this->fetch();
    }

    /*
     * 开启关闭启用
     * 开启当前，判断当前是否为唯一开启，如果是，则将当前设置为默认区域
     * 关闭当前，判断当前是否为原来默认区域：如果是，则判断当前同级（相同上级）是否存在开启：如存在，设置为默认，如不存在：判断第一级是否存在开启：如存在，设置第一个为默认，如不存在，继续往下级查找。
     *
     * 至少必须存在一个开启区域
     */
    public function setStatus() {
        $id = input('id/d', 0);
        $status = input('status/d', 0);
        $list = Db::name("citysite")->where("status=1")->getField("id,status");
        if ($status == 0){
            if (count($list) == 1 && !empty($list[$id])){
                $this->error("至少存在一个开启区域！");
            }
        }
        Db::name('citysite')->where(['id'=>$id])->cache(true, null, "citysite")->update(['status'=>$status, 'update_time'=>getTime()]);
        $this->success("设置成功");

/*
        $id = input('id/d', 0);
        $status = input('status/d', 0);
        $list = Db::name("citysite")->where("status=1")->order("level asc")->getField("id,parent_id,status,is_default,level");
        $count = count($list);
        $is_true = true;
        if ($status == 1){
            if ($count == 0 || ($count == 1 && empty($list[$id]))){
                $is_true = $this->setIsDefault($id);
            }
        }else{
            if ($count == 1 && !empty($list[$id])){
                $this->error("至少存在一个开启区域！".$status);
            }
            if (!empty($list[$id]) && $list[$id]['is_default'] == 1){
                $peer_id = $top_id = $any_id = 0;
                foreach ($list as $val){
                    if (empty($peer_id) && $val['id']!= $id && $val['parent_id'] == $list[$id]['parent_id']){
                        $peer_id = $val['id'];
                        break;
                    }
                    if (empty($top_id) && $val['id']!= $id && $val['parent_id'] == 0){
                        $top_id =  $val['id'];
                    }
                    if (empty($any_id) && $val['id']!= $id){
                        $any_id =  $val['id'];
                    }
                }
                if ($peer_id){
                    $default_id = $peer_id;
                }else if($top_id){
                    $default_id = $top_id;
                }else{
                    $default_id = $any_id;
                }
                $is_true = $this->setIsDefault($default_id);
            }
        }
        if (!$is_true){
            $this->error("设置失败，请检查二级域名不能为空！");
        }
        Db::name('citysite')->where(['id'=>$id])->cache(true, null, "citysite")->update(['status'=>$status, 'update_time'=>getTime()]);

        $this->success("设置成功");
        */
    }

    /*
     * 设置默认区域
     */
    // private function setIsDefault($id){
    //     $id = intval($id);
    //     $subdomain = Db::name('citysite')->where(['id'=>$id])->getField('domain');
    //     if ($this->web_citysite_open && empty($subdomain)) { //如果为开启状态，且二级域名为空，不允许设置
    //         return false;
    //     }
    //     $is_true = Db::name('citysite')->where(['id'=>$id])->update(['is_default'=>1, 'update_time'=>getTime()]);
    //     if ($is_true){
    //         Db::name('citysite')->where(['id'=>['neq',$id]])->update(['is_default'=>0, 'update_time'=>getTime()]);
    //         tpCache('site', ['site_default_home'=>$id]);
    //     }
    //     \think\Cache::clear('citysite');

    //     return $is_true;
    // }

    /*
     * 设置是否默认
     */
    // public function setSortOrder(){
    //     $id = input('id/d', 0);
    //     $is_true = $this->setIsDefault($id);
    //     if ($is_true){
    //         $this->success("设置成功");
    //     }else{
    //         $this->error("设置失败，请检查二级域名不能为空！");
    //     }
    // }

    //获取全部省份
    private function get_site_province_all()
    {
        $result = Db::name('citysite')->field('id, name')
            ->where('level',1)
            ->order("sort_order asc, id asc")
            ->getAllWithIndex('id');

        return $result;
    }

    /**
    * 获取子类列表
    */  
    public function ajax_get_region($pid = 0, $level = 2, $siteid = '', $text = '--请选择--')
    {
        \think\Session::pause(); // 暂停session，防止session阻塞机制
        $data = model('Citysite')->getList($pid,'*','',$level);
        $html = "<option value=''>".urldecode($text)."</option>";
        foreach($data as $key=>$val){
            if ($val['id'] == $siteid) {
                unset($data[$key]);
                continue;
            }
            $html.="<option value='".$val['id']."'>".$val['name']."</option>";
        }
        $isempty = 0;
        if (empty($data)){
            $isempty = 1;
        }
        $this->success($html,'',['isempty'=>$isempty]);
    }

    /*
     * 获取区域列表（关联栏目）
     * pid          上级id
     * level        级别
     * relevance    关联模型（表名称），为空时表示不关联
     * text         不选择时显示text
     */
    public function ajax_get_region_arc($pid = 0,$level = 1,$channel = '9', $text = '--请选择--')
    {
        \think\Session::pause(); // 暂停session，防止session阻塞机制
        $regionIds = $this->getAllRegionIds($level,'',$channel);
        $data = Db::name('citysite')->field("*")
            ->where(["id"=>['in',$regionIds],'parent_id'=>$pid])
            ->select();
        if ($level == 1 && count($data) == 1){   //只存在一个省份
            $html = "<input type='hidden' id='province_id' name='province_id' value='".$data[0]['id']."'>";
        }else if ($level == 1){
            $html = "<select name='province_id' id='province_id'>";
            $html .= "<option value=''>".urldecode($text)."</option>";
            foreach($data as $key=>$val){
                $html.="<option value='".$val['id']."'>".$val['name']."</option>";
            }
            $html .= "</select>";
        }else{
            $html = "<select name='city_id' id='city_id'>";
            $html .= "<option value=''>".urldecode($text)."</option>";
            foreach($data as $key=>$val){
                $html.="<option value='".$val['id']."'>".$val['name']."</option>";
            }
            $html .= "</select>";
        }

        $this->success($html);
    }

    /*
     * 获取所有区域（id）集合
     */
    private function getAllRegionIds($level,$typeid = "",$channel = ""){
        $field = "province_id";
        if ($level == 2){
            $field = "city_id";
        }else if ($level == 3){
            $field = "area_id";
        }
        $where['status'] = 1;
        $where['is_del'] = 0;
        if (!empty($typeid)){
            $where['typeid'] = ['in',$typeid];
        }else if (!empty($channel)){
            $where['channel'] = ['in',$channel];
        }
        $regionIds = Db::name('archives')->where($where)->group($field)->getField($field,true);

        return $regionIds;
    }

    /**
     * 删除
     */
    public function del()
    {
        $id_arr = input('del_id/a');
        $id_arr = eyIntval($id_arr);
        if(IS_POST && !empty($id_arr)){

            // $count = Db::name('citysite')->where('parent_id','IN',$id_arr)->count();
            // if ($count > 0){
            //     $this->error('所选区域有下级区域，请先删除下级区域');
            // }

            $result = Db::name('citysite')->where([
                    'id'    => ['IN', $id_arr],
                ])->select();
            $level = 0;
            $name_list = [];
            foreach ($result as $key => $val) {
                empty($level) && $level = $val['level'];
                $name_list[] = $val['name'];
            }

            $r = Db::name('citysite')->where([
                    'id'    => ['IN', $id_arr],
                ])
                ->cache(true, null, "citysite")
                ->delete();
            if($r !== false){
                /*默认区域被删除，自动处理主站默认区域为空*/
                $site_default_home = tpCache('global.site_default_home');
                if (!empty($site_default_home) && in_array($site_default_home, $id_arr)) {
                    tpCache('site', ['site_default_home'=>0]);
                }
                /*end*/

                // 删除所有下级区域
                if (2 == $level) {
                    Db::name('citysite')->where([
                            'parent_id'    => ['IN', $id_arr],
                        ])
                        ->cache(true, null, "citysite")
                        ->delete();
                } else if (1 == $level) {
                    Db::name('citysite')->where([
                            'topid'    => ['IN', $id_arr],
                        ])
                        ->cache(true, null, "citysite")
                        ->delete();
                }

                adminLog('删除区域：'.implode(',', $name_list));
                $this->success('删除成功');
            }
        }
        $this->error('删除失败');
    }

    /**
     * 判断子域名的唯一性
     */
    private function domain_unique($domain = '', $id = 0)
    {
        $result = Db::name('citysite')->field('id,domain')->getAllWithIndex('id');
        if (!empty($result)) {
            if (0 < $id) unset($result[$id]);
            !empty($result) && $result = get_arr_column($result, 'domain');
        }
        empty($result) && $result = [];
        $dirnames = Db::name('arctype')->column('dirname');
        foreach ($dirnames as $key => $val) {
            $dirnames[$key] = strtolower($val);
        }
        $disableDirname = array_merge($this->disableDirname, $dirnames, $result);
        if (in_array(strtolower($domain), $disableDirname)) {
            return false;
        }
        return true;
    }

    /**
     * 生成随机子域名，确保唯一性
     */
    private function rand_domain($domain = '',$level)
    {
        if (empty($domain)) {
            $domain = strtolower(get_rand_str(6, 0, 1));
        }
        if (!$this->domain_unique($domain)) {
            //20241118              
            if($level==1){
                $new_domain = $domain . mt_rand(1,9);
                Db::name('citysite')->where(['domain'=>$domain,'level'=>['neq',1]])->update(['domain'=>$new_domain]);
            }else{
                $domain = $domain . mt_rand(1,9);
            }
            return $this->rand_domain($domain,$level);
        }else{
            return $domain;   
        }
    }

    /*
     * js打开获取子区域列表
     */
    public function ajaxSelectRegion(){
        $list = Db::name("citysite")->where("status=1")->select();
        $this->assign('list', $list);
        $this->assign('json_arctype_list', json_encode($list));
        $func = input('func/s');
        $assign_data['func'] = $func;
        $this->assign($assign_data);

        return $this->fetch();
    }

    /*
     * js获取region
     */
    public function ajaxGetOne($where = ""){
        return Db::name('citysite')->where($where)->find();
    }

    /**
     * 获取城市站点的所有上级区域id
     */
    private function getParentCitysiteId($id){
        $id = intval($id);
        static $regionArr = array();
        static $countnext = 0;
        $countnext++;
        $regionArr[] = $id;
        if(!empty($id)){
            $list = Db::name('citysite')->field('id,parent_id')->where('id',$id)->find();
            if($list && $list['parent_id']!=0){
                $this->getParentCitysiteId($list['parent_id']);
            }
        }
        $countnext--;
        $result = $regionArr;
        if($countnext == 0){
            $regionArr = array();
        }
        return $result;
    }

    /**
     * 获取区域的拼音
     */
    public function ajax_get_name_pinyin($name = '')
    {
        $pinyin = get_pinyin($name);
        $this->success('提取成功', null, ['pinyin'=>$pinyin]);
    }

    /**
     * 快速启用百强市
     */
    public function set_bqs_status()
    {
        $list = Db::name('citysite')->where(['level'=>['IN', [1,2]],'status'=>0])->select();
        if (!empty($list)) {
            $where = [];
            $top_city_arr = [];
            foreach (self::$top_city as $key => $val) {
                $where[] = " `name` LIKE '{$key}%' ";
                $top_city_arr = array_merge($top_city_arr, $val);
            }
            $where_str = implode(' OR ', $where);
            $provinceList = Db::name('citysite')->field('id,name')->where(['level'=>1])->where($where_str)->select();
            $province_ids = [];
            foreach ($provinceList as $key => $val) {
                foreach (self::$top_city as $_k => $_v) {
                    if (stristr($val['name'], $_k)) {
                        $provinceList[$_k] = $val;
                        unset($provinceList[$key]);
                        continue;
                    }
                }
                $province_ids[] = $val['id'];
            }
            // 启用百强市涉及的省份
            Db::name('citysite')->where(['id'=>['IN', $province_ids]])->update(['status'=>1,'update_time'=>getTime()]);
            // 启用百强市
            foreach (self::$top_city as $key => $val) {
                $province_info = !empty($provinceList[$key]) ? $provinceList[$key] : [];
                if (empty($province_info)) {
                    continue;
                }
                foreach ($val as $_k => $_v) {
                    $where = [];
                    foreach ($_v as $_k2 => $_v2) {
                        $where[] = " `name` LIKE '{$_v2}%' ";
                    }
                    $where_str = implode(' OR ', $where);
                    Db::name('citysite')->where([
                            'level' => 2,
                            'parent_id' => $province_info['id'],
                        ])
                        ->where($where_str)
                        ->update(['status'=>1,'update_time'=>getTime()]);
                }
            }
        }
        $this->success('设置成功');
    }

    /**
     * 一键全部启用/禁用
     */
    public function set_city_status()
    {
        if (IS_AJAX_POST) {
            $status = input('param.status/d');
            $r = Db::name('citysite')->where([
                    'status' => empty($status) ? 1 : 0,
                ])->update([
                    'status' => empty($status) ? 0 : 1,
                    'update_time' => getTime(),
                ]);
            if ($r !== false) {
                \think\Cache::clear('citysite');
                delFile(RUNTIME_PATH);
                $this->success('操作成功');
            }
        }
        $this->error('操作失败');
    }

    /**
     * 批量设置地区
     * @return [type] [description]
     */
    public function batch_setcity()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');
            if (empty($post['ids'])) {
                $this->error('请至少勾选一个区域');
            }
            $post['ids'] = str_replace('，', ',', $post['ids']);
            $ids = explode(',', $post['ids']);
            $inherit_province = !empty($post['inherit_province']) ? intval($post['inherit_province']) : 0;
            $inherit_city = !empty($post['inherit_city']) ? intval($post['inherit_city']) : 0;
            $inherit_area = !empty($post['inherit_area']) ? intval($post['inherit_area']) : 0;
            if (empty($inherit_province) && empty($inherit_city) && empty($inherit_area)) {
                $this->error('请勾选要操作的区域级别');
            }

            $updateData = [];
            if (-1 < $post['is_open']) {
                $updateData['is_open'] = !empty($post['is_open']) ? intval($post['is_open']) : 0;
            }

            if (-1 < $post['showall']) {
                $updateData['showall'] = !empty($post['showall']) ? intval($post['showall']) : 0;
            }

            if (-1 < $post['status']) {
                $updateData['status'] = !empty($post['status']) ? intval($post['status']) : 0;
            }

            if (1 == $post['seoset']) {
                $updateData['seoset'] = 0;
            } else if (0 === $post['seoset']) {
                $updateData['seo_title'] = '';
                $updateData['seo_keywords'] = '';
                $updateData['seo_description'] = '';
            }

            if (!empty($updateData)) {
                $updateData['update_time'] = getTime();
            } else { // 都保持原设置了
                $this->success('操作成功');
            }

            $err = 0;
            // 在一级列表时操作区域
            if ($post['level'] == 1) {
                // 设置一级区域
                if (!empty($inherit_province)) {
                    $r = Db::name('citysite')->where([
                            'id'   => ['IN', $ids],
                            'level' => 1,
                        ])->update($updateData);
                    if ($r === false) $err++; 
                }
                // 设置二级区域
                if (!empty($inherit_city)) {
                    $r = Db::name('citysite')->where([
                            'parent_id'   => ['IN', $ids],
                            'level' => 2,
                        ])->update($updateData);
                    if ($r === false) $err++; 
                }
                // 设置三级区域
                if (!empty($inherit_area)) {
                    $cityids = Db::name('citysite')->where([
                            'parent_id'   => ['IN', $ids],
                            'level' => 2,
                        ])->column('id');
                    if (!empty($cityids)) {
                        $r = Db::name('citysite')->where([
                                'parent_id'   => ['IN', $cityids],
                                'level' => 3,
                            ])->update($updateData);
                        if ($r === false) $err++; 
                    }
                }
            }
            // 在二级列表时操作区域
            else if ($post['level'] == 2) {
                // 设置一级区域
                if (!empty($inherit_province)) {
                    $parent_ids = Db::name('citysite')->where([
                            'id'   => ['IN', $ids],
                            'level' => 2,
                        ])->column('parent_id');
                    $r = Db::name('citysite')->where([
                            'id'   => ['IN', $parent_ids],
                            'level' => 1,
                        ])->update($updateData);
                    if ($r === false) $err++; 
                }
                // 设置二级区域
                if (!empty($inherit_city)) {
                    $r = Db::name('citysite')->where([
                            'id'   => ['IN', $ids],
                            'level' => 2,
                        ])->update($updateData);
                    if ($r === false) $err++; 
                }
                // 设置三级区域
                if (!empty($inherit_area)) {
                    $r = Db::name('citysite')->where([
                            'parent_id'   => ['IN', $ids],
                            'level' => 3,
                        ])->update($updateData);
                    if ($r === false) $err++; 
                }
            }
            // 在三级列表时操作区域
            else if ($post['level'] == 3) {
                // 设置一级区域
                if (!empty($inherit_province)) {
                    $topids = Db::name('citysite')->where([
                            'id'   => ['IN', $ids],
                            'level' => 3,
                        ])->column('topid');
                    $r = Db::name('citysite')->where([
                            'id'   => ['IN', $topids],
                            'level' => 1,
                        ])->update($updateData);
                    if ($r === false) $err++; 
                }
                // 设置二级区域
                if (!empty($inherit_city)) {
                    $parent_ids = Db::name('citysite')->where([
                            'id'   => ['IN', $ids],
                            'level' => 3,
                        ])->column('parent_id');
                    $r = Db::name('citysite')->where([
                            'id'   => ['IN', $parent_ids],
                            'level' => 2,
                        ])->update($updateData);
                    if ($r === false) $err++; 
                }
                // 设置三级区域
                if (!empty($inherit_area)) {
                    $r = Db::name('citysite')->where([
                            'id'   => ['IN', $ids],
                            'level' => 3,
                        ])->update($updateData);
                    if ($r === false) $err++; 
                }
            }

            if (empty($err)) {
                adminLog('批量设置区域-id：'.$post['ids']);
                $this->success('操作成功');
            } else {
                $this->error('操作失败');
            }
        }

        $level = input('param.level/d', 0);
        $level++;
        $assign_data = [];
        $assign_data['level'] = $level;

        $this->assign($assign_data);

        return $this->fetch();
    }

    /**
     * 批量设置SEO
     * @return [type] [description]
     */
    public function batch_setcityseo()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');
            if (empty($post['ids'])) {
                $this->error('请至少勾选一个区域');
            }
            $post['ids'] = str_replace('，', ',', $post['ids']);
            $ids = explode(',', $post['ids']);
            $inherit_province = !empty($post['inherit_province']) ? intval($post['inherit_province']) : 0;
            $inherit_city = !empty($post['inherit_city']) ? intval($post['inherit_city']) : 0;
            $inherit_area = !empty($post['inherit_area']) ? intval($post['inherit_area']) : 0;
            if (empty($inherit_province) && empty($inherit_city) && empty($inherit_area)) {
                $this->error('请勾选要操作的区域级别');
            }

            $err = 0;
            // 在一级列表时操作区域
            if ($post['level'] == 1) {
                // 设置一级区域
                if (!empty($inherit_province)) {
                    $list = Db::name('citysite')->field('id,name')->where([
                            'id'   => ['IN', $ids],
                            'level' => 1,
                        ])->select();
                    $this->batch_setcityseo_save($err, $list, $post);
                }
                // 设置二级区域
                if (!empty($inherit_city)) {
                    $list = Db::name('citysite')->field('id,name')->where([
                            'parent_id'   => ['IN', $ids],
                            'level' => 2,
                        ])->select();
                    $this->batch_setcityseo_save($err, $list, $post);
                }
                // 设置三级区域
                if (!empty($inherit_area)) {
                    $cityids = Db::name('citysite')->where([
                            'parent_id'   => ['IN', $ids],
                            'level' => 2,
                        ])->column('id');
                    if (!empty($cityids)) {
                        $list = Db::name('citysite')->field('id,name')->where([
                                'parent_id'   => ['IN', $cityids],
                                'level' => 3,
                            ])->select();
                        $this->batch_setcityseo_save($err, $list, $post);
                    }
                }
            }
            // 在二级列表时操作区域
            else if ($post['level'] == 2) {
                // 设置一级区域
                if (!empty($inherit_province)) {
                    $parent_ids = Db::name('citysite')->where([
                            'id'   => ['IN', $ids],
                            'level' => 2,
                        ])->column('parent_id');
                    $list = Db::name('citysite')->field('id,name')->where([
                            'id'   => ['IN', $parent_ids],
                            'level' => 1,
                        ])->select();
                    $this->batch_setcityseo_save($err, $list, $post);
                }
                // 设置二级区域
                if (!empty($inherit_city)) {
                    $list = Db::name('citysite')->field('id,name')->where([
                            'id'   => ['IN', $ids],
                            'level' => 2,
                        ])->select();
                    $this->batch_setcityseo_save($err, $list, $post);
                }
                // 设置三级区域
                if (!empty($inherit_area)) {
                    $list = Db::name('citysite')->field('id,name')->where([
                            'parent_id'   => ['IN', $ids],
                            'level' => 3,
                        ])->select();
                    $this->batch_setcityseo_save($err, $list, $post);
                }
            }
            // 在三级列表时操作区域
            else if ($post['level'] == 3) {
                // 设置一级区域
                if (!empty($inherit_province)) {
                    $topids = Db::name('citysite')->where([
                            'id'   => ['IN', $ids],
                            'level' => 3,
                        ])->column('topid');
                    $list = Db::name('citysite')->field('id,name')->where([
                            'id'   => ['IN', $topids],
                            'level' => 1,
                        ])->select();
                    $this->batch_setcityseo_save($err, $list, $post);
                }
                // 设置二级区域
                if (!empty($inherit_city)) {
                    $parent_ids = Db::name('citysite')->where([
                            'id'   => ['IN', $ids],
                            'level' => 3,
                        ])->column('parent_id');
                    $list = Db::name('citysite')->field('id,name')->where([
                            'id'   => ['IN', $parent_ids],
                            'level' => 2,
                        ])->select();
                    $this->batch_setcityseo_save($err, $list, $post);
                }
                // 设置三级区域
                if (!empty($inherit_area)) {
                    $list = Db::name('citysite')->field('id,name')->where([
                            'id'   => ['IN', $ids],
                            'level' => 3,
                        ])->select();
                    $this->batch_setcityseo_save($err, $list, $post);
                }
            }

            if (empty($err)) {
                adminLog('批量设置区域-id：'.$post['ids']);
                $this->success('操作成功');
            } else {
                $this->error('操作失败');
            }
        }

        $level = input('param.level/d', 0);
        $level++;
        $assign_data = [];
        $assign_data['level'] = $level;

        $this->assign($assign_data);

        return $this->fetch();
    }

    /**
     * 批量设置SEO - 统一更新数据
     * @param  [type] &$err [description]
     * @param  array  $list [description]
     * @param  array  $post [description]
     * @return [type]       [description]
     */
    private function batch_setcityseo_save(&$err, $list = [], $post = [])
    {
        if (!empty($list)) {
            $seo_title = !empty($post['seo_title']) ? trim($post['seo_title']) : '';
            $seo_keywords = !empty($post['seo_keywords']) ? trim($post['seo_keywords']) : '';
            $seo_description = !empty($post['seo_description']) ? trim($post['seo_description']) : '';

            $updateData = [];
            foreach ($list as $key => $val) {
                $updateData[] = [
                    'id'    => $val['id'],
                    'seo_title' => str_replace(['{region}','{区域}'], $val['name'], $seo_title),
                    'seo_keywords' => str_replace(['{region}','{区域}'], $val['name'], $seo_keywords),
                    'seo_description' => str_replace(['{region}','{区域}'], $val['name'], $seo_description),
                    'update_time' => getTime(),
                ];
            }
            $r = model('Citysite')->saveAll($updateData);
            if ($r === false) $err++; 
        }
    }

    /**
     * 获取地区表ey_region的城市列表
     */  
    public function ajax_get_region_list($pid = 0, $level = 2, $region_id = '', $text = '--请选择--')
    {
        if ($pid == 1) { // 北京市
            $pid = 2;
            $level = 3;
        } else if ($pid == 338) { // 天津市
            $pid = 339;
            $level = 3;
        } else if ($pid == 10543) { // 上海市
            $pid = 10544;
            $level = 3;
        } else if ($pid == 31929) { // 重庆市
            $pid = [31930,32380];
            $level = 3;
        }
        $data = model('Region')->getList($pid,'*','',$level);
        $html = "<option value=''>".urldecode($text)."</option>";
        foreach($data as $key=>$val){
            if ($val['id'] == $region_id) {
                unset($data[$key]);
                continue;
            }
            // 当区域名称大于2两个字是，就去除末尾指定的字
            if (strlen($val['name']) >= 9) {
                $val['name'] = preg_replace('/(省|市|县|区|乡|镇|旗|州|农场)$/i', '', $val['name']);
            }
            $html.="<option value='".$val['id']."'>".$val['name']."</option>";
        }
        $isempty = 0;
        if (empty($data)){
            $isempty = 1;
        }
        $this->success($html,'',['isempty'=>$isempty]);

    }

    /**
     * 处理区域的末尾多余文字
     * @param  string $name [description]
     * @return [type]       [description]
     */
    private function handle_name($name = '')
    {
        // 当区域名称大于2两个字是，就去除末尾指定的字
        if (strlen($name) >= 9) {
            $name = preg_replace('/(地区|壮族自治区|维吾尔自治区|回族自治区|自治区|市辖区|特别行政区)$/i', '', $name);
            $name = preg_replace('/(省|市|县|区|乡|镇|旗|州|盟|行政单位|街道)$/i', '', $name);
        }

        return $name;
    }

    /**
     * 一键导入全国城市
     * @return [type] [description]
     */
    public function import_city()
    {
        //防止数据过程超时
        function_exists('set_time_limit') && set_time_limit(0);
        @ini_set('memory_limit','-1');

        if (IS_POST) {
            $post = input('post.');
            $province_name = '';
            $province_id = !empty($post['province_id']) ? intval($post['province_id']) : 0;
            $cur_province_id = input('param.cur_province_id/d', 0);
            if (!empty($cur_province_id)) {
                $province_id = $cur_province_id;
            }
            $city_name = '';
            $city_id = !empty($post['city_id']) ? intval($post['city_id']) : 0;
            $seo_title = !empty($post['seo_title']) ? trim($post['seo_title']) : '';
            $seo_keywords = !empty($post['seo_keywords']) ? trim($post['seo_keywords']) : '';
            $seo_description = !empty($post['seo_description']) ? trim($post['seo_description']) : '';

            /*---------------------读取要导入的城市列表 start---------------------*/
            if (empty($province_id)) { // 导入全国城市
                $level = 1;
                $parent_id = 0;
                $topid = 0;
                $regionList = Db::name('region')->order('level asc, parent_id asc, id asc')->select();
                $regionList = group_same_key($regionList, 'level');
                // 北京、天津、上海、重庆，这四个省级市的二级删掉、三级区域转成二级
                foreach ($regionList[2] as $key => $val) {
                    if ($val['id'] == 2) { // 北京市
                        unset($regionList[2][$key]);
                        $row = Db::name('region')->field('id,name,2 as level,1 as parent_id,initial')->where(['parent_id'=>2])->order('id asc')->select();
                        $regionList[2] = array_merge($regionList[2], $row);
                    } else if ($val['id'] == 339) { // 天津市
                        unset($regionList[2][$key]);
                        $row = Db::name('region')->field('id,name,2 as level,338 as parent_id,initial')->where(['parent_id'=>339])->order('id asc')->select();
                        $regionList[2] = array_merge($regionList[2], $row);
                    } else if ($val['id'] == 10544) { // 上海市
                        unset($regionList[2][$key]);
                        $row = Db::name('region')->field('id,name,2 as level,10543 as parent_id,initial')->where(['parent_id'=>10544])->order('id asc')->select();
                        $regionList[2] = array_merge($regionList[2], $row);
                    } else if ($val['id'] == 31930) { // 重庆市 - 市
                        unset($regionList[2][0]);
                        $row = Db::name('region')->field('id,name,2 as level,31929 as parent_id,initial')->where(['parent_id'=>31930])->order('id asc')->select();
                        $regionList[2] = array_merge($regionList[2], $row);
                    } else if ($val['id'] == 32380) { // 重庆市 - 县
                        unset($regionList[2][0]);
                        $row = Db::name('region')->field('id,name,2 as level,31929 as parent_id,initial')->where(['parent_id'=>32380])->order('id asc')->select();
                        $regionList[2] = array_merge($regionList[2], $row);
                    }
                }
                // 北京、天津、上海、重庆，这四个省级市的三级删掉
                foreach ($regionList[3] as $key => $val) {
                    if (in_array($val['parent_id'], [2,339,10544,31930,32380])) {
                        unset($regionList[3][$key]);
                    }
                }
            }
            else if (!empty($province_id) && empty($city_id)) { // 导入指定当前省份和下级的全部市县
                // 要导入的省份名称
                $province_name = Db::name('region')->where(['id'=>$province_id])->value('name');
                $province_name = preg_replace('/(省|市)$/i', '', $province_name);

                $level = 2;
                $parent_id = (int)Db::name('citysite')->where(['level'=>1,'name'=>['LIKE', "{$province_name}%"]])->value('id');
                $topid = $parent_id;

                $region_ids = [$province_id];
                $city_ids = Db::name('region')->where(['parent_id'=>$province_id])->column('id');
                if (!empty($city_ids)) {
                    $region_ids = array_merge($region_ids, $city_ids);
                    $area_ids = Db::name('region')->where(['parent_id'=>['IN', $city_ids]])->column('id');
                    !empty($area_ids) && $region_ids = array_merge($region_ids, $area_ids);
                }
                $regionList = Db::name('region')->where(['id'=>['IN', $region_ids]])->order('level asc, parent_id asc, id asc')->select();
                $regionList = group_same_key($regionList, 'level');
                // 北京、天津、上海、重庆，这四个省级市的二级删掉、三级区域转成二级
                foreach ($regionList[2] as $key => $val) {
                    if ($val['id'] == 2) { // 北京市
                        unset($regionList[2][$key]);
                        $row = Db::name('region')->field('id,name,2 as level,1 as parent_id,initial')->where(['parent_id'=>2])->order('id asc')->select();
                        $regionList[2] = array_merge($regionList[2], $row);
                    } else if ($val['id'] == 339) { // 天津市
                        unset($regionList[2][$key]);
                        $row = Db::name('region')->field('id,name,2 as level,338 as parent_id,initial')->where(['parent_id'=>339])->order('id asc')->select();
                        $regionList[2] = array_merge($regionList[2], $row);
                    } else if ($val['id'] == 10544) { // 上海市
                        unset($regionList[2][$key]);
                        $row = Db::name('region')->field('id,name,2 as level,10543 as parent_id,initial')->where(['parent_id'=>10544])->order('id asc')->select();
                        $regionList[2] = array_merge($regionList[2], $row);
                    } else if ($val['id'] == 31930) { // 重庆市 - 市
                        unset($regionList[2][0]);
                        $row = Db::name('region')->field('id,name,2 as level,31929 as parent_id,initial')->where(['parent_id'=>31930])->order('id asc')->select();
                        $regionList[2] = array_merge($regionList[2], $row);
                    } else if ($val['id'] == 32380) { // 重庆市 - 县
                        unset($regionList[2][0]);
                        $row = Db::name('region')->field('id,name,2 as level,31929 as parent_id,initial')->where(['parent_id'=>32380])->order('id asc')->select();
                        $regionList[2] = array_merge($regionList[2], $row);
                    }
                }
                // 北京、天津、上海、重庆，这四个省级市的三级删掉
                foreach ($regionList[3] as $key => $val) {
                    if (in_array($val['parent_id'], [2,339,10544,31930,32380])) {
                        unset($regionList[3][$key]);
                    }
                }
            }
            else if (!empty($province_id) && !empty($city_id)) { // 导入指定当前省份和下级的全部市县
                $row = Db::name('region')->where(['id'=>['IN', [$province_id, $city_id]]])->getAllWithIndex('id');
                // 要导入的省份名称
                $province_name = preg_replace('/(省|市)$/i', '', $row[$province_id]['name']);
                // 要导入的二级城市名称
                $city_name = $this->handle_name($row[$province_id]['name']);
                
                $level = 3;
                $topid = (int)Db::name('citysite')->where(['level'=>1,'name'=>['LIKE', "{$province_name}%"]])->value('id');
                $parent_id = (int)Db::name('citysite')->where(['parent_id'=>$topid,'level'=>2,'name'=>['LIKE', "{$city_name}%"]])->value('id');

                $region_ids = [$province_id, $city_id];
                $area_ids = Db::name('region')->where(['parent_id'=>$city_id])->column('id');
                !empty($area_ids) && $region_ids = array_merge($region_ids, $area_ids);
                $regionList = Db::name('region')->where(['id'=>['IN', $region_ids]])->order('level asc, parent_id asc, id asc')->select();
                $regionList = group_same_key($regionList, 'level');
                // 北京、天津、上海、重庆，这四个省级市的三级删掉
                foreach ($regionList[3] as $key => $val) {
                    if (in_array($val['parent_id'], [2,339,10544,31930,32380])) {
                        unset($regionList[3][$key]);
                    }
                }
            }
            /*---------------------读取要导入的城市列表 end---------------------*/

            /*---------------------多城市站点的树形结构、每个层级的结构 start---------------------*/
            $tree_site = $province_site = $city_site = $area_site = $domainArr = [];
            $arr1 = $arr2 = $arr3 = $arr4 = [];
            $row = Db::name('citysite')->field('id,name,parent_id,topid,level,domain')->order('level asc, parent_id asc, id asc')->select();
            foreach ($row as $key => $val) {
                $domainArr[] = $val['domain'];
                $val['name'] = $this->handle_name($val['name']);
                if ($val['level'] == 1) {
                    $val['child'] = [];
                    $arr1[$val['id']] = $val;
                    $province_site[$val['name']] = $val;
                    $tree_site[$val['name']] = $val;
                } else if ($val['level'] == 2) {
                    $val['child'] = [];
                    $arr2[$val['parent_id']][$val['id']] = $val;
                    $arr4[$val['id']] = $val;
                    $parent_info = $arr1[$val['parent_id']];
                    $city_site[$parent_info['name']][$val['name']] = $val;
                    $tree_site[$parent_info['name']]['child'][$val['name']] = $val;
                } else if ($val['level'] == 3) {
                    $val['child'] = [];
                    $arr3[$val['parent_id']][$val['id']] = $val;
                    $parent_info = $arr4[$val['parent_id']];
                    $top_info = $arr1[$val['topid']];
                    $area_site[$top_info['name']][$parent_info['name']][$val['name']] = $val;
                    $tree_site[$top_info['name']]['child'][$parent_info['name']]['child'][$val['name']] = $val;
                }
            }
            /*---------------------多城市站点的树形结构、每个层级的结构 end---------------------*/

            $err = 0;
            // 批量添加一级区域
            if (!empty($regionList[1])) {
                $nameArr = [];
                foreach ($regionList[1] as $key => $val) {
                    $is_add = true;
                    foreach ($province_site as $_k => $_v) {
                        if (stristr($val['name'], $_v['name'])) {
                            $is_add = false;
                            break;
                        }
                    }
                    if ($is_add) {
                        $nameArr[] = $this->handle_name($val['name']);
                    }
                }
                if (!empty($nameArr)) {
                    $addData = [];
                    foreach ($nameArr as $key => $val) {
                        if($province_id==41877){
                            $domain = 'shaanxi';
                        }else{  
                            $domain = preg_replace("/[^a-zA-Z0-9]+/", "", get_pinyin($val));
                        }
                        if (in_array($domain, $domainArr)) {
                           $domain = $this->rand_domain($domain,1);
                        }
                        $addData[] = [
                            'name'  => $val,
                            'domain'  => $domain,
                            'level' => 1,
                            'parent_id' => 0,
                            'topid' => 0,
                            'is_open' => !empty($post['is_open']) ? intval($post['is_open']) : 0,
                            'showall' => !empty($post['showall']) ? intval($post['showall']) : 1,
                            'seoset'   => !empty($post['seoset']) ? intval($post['seoset']) : 0,
                            'status'   => !empty($post['status']) ? intval($post['status']) : 0,
                            'initial'   => getFirstCharter($val),
                            'seo_title' => str_replace(['{region}','{区域}'], $val, $seo_title),
                            'seo_keywords' => str_replace(['{region}','{区域}'], $val, $seo_keywords),
                            'seo_description' => str_replace(['{region}','{区域}'], $val, $seo_description),
                            'sort_order'    => 100,
                            'add_time'    => getTime(),
                            'update_time'    => getTime(),
                        ];
                    }
                    $r = model('Citysite')->saveAll($addData);
                    if ($r === false) {
                        $err++;
                    } else {
                        foreach ($r as $k1 => $v1) {
                            $arr_new = $v1->getData();
                            $arr_new['child'] = [];
                            $arr1[$arr_new['id']] = $arr_new;
                            $province_site[$arr_new['name']] = $arr_new;
                            $tree_site[$arr_new['name']] = $arr_new;
                        }
                    }
                }
            }

            // 批量添加二级区域
            if (empty($err) && !empty($regionList[2])) {
                $province_region = Db::name('region')->where(['level'=>1])->getAllWithIndex('id');
                $nameArr = $parent_ids_arr = [];
                foreach ($regionList[2] as $key => $val) {
                    $is_add = true;
                    $parent_info = $province_region[$val['parent_id']];
                    $parent_info['name'] = $this->handle_name($parent_info['name']);
                    foreach ($city_site[$parent_info['name']] as $_k => $_v) {
                        if (stristr($val['name'], $_v['name'])) {
                            $is_add = false;
                            break;
                        }
                    }
                    if ($is_add) {
                        $nameArr[] = $this->handle_name($val['name']);
                        $parent_name = $parent_info['name'];
                        $parent_ids_arr[] = $province_site[$parent_name]['id'];
                    }
                }
                if (!empty($nameArr)) {
                    $addData = [];
                    //$domainArr = Db::name('citysite')->where(['id'=>['gt', 0]])->column('domain');
                    foreach ($nameArr as $key => $val) {
                        $domain = preg_replace("/[^a-zA-Z0-9]+/", "", get_pinyin($val));
                        //if (in_array($domain, $domainArr)) {
                        $domain = $this->rand_domain($domain,2);
                        //}
                        $addData = [
                            'name'  => $val,
                            'domain'  => $domain,
                            'level' => 2,
                            'parent_id' => $parent_ids_arr[$key],
                            'topid' => $parent_ids_arr[$key],
                            'is_open' => !empty($post['is_open']) ? intval($post['is_open']) : 0,
                            'showall' => !empty($post['showall']) ? intval($post['showall']) : 1,
                            'seoset'   => !empty($post['seoset']) ? intval($post['seoset']) : 0,
                            'status'   => !empty($post['status']) ? intval($post['status']) : 0,
                            'initial'   => getFirstCharter($val),
                            'seo_title' => str_replace(['{region}','{区域}'], $val, $seo_title),
                            'seo_keywords' => str_replace(['{region}','{区域}'], $val, $seo_keywords),
                            'seo_description' => str_replace(['{region}','{区域}'], $val, $seo_description),
                            'sort_order'    => 100,
                            'add_time'    => getTime(),
                            'update_time'    => getTime(),
                        ];
                        $r = model('Citysite')->insert($addData);
                        unset($domain,$r);
                    }
                    if ($r === false) {
                        $err++; 
                    } else {
                        foreach ($r as $k1 => $v1) {
                            $arr_new = $v1->getData();
                            $arr_new['child'] = [];
                            $arr2[$arr_new['parent_id']][$arr_new['id']] = $arr_new;
                            $parent_info = $arr1[$arr_new['parent_id']];
                            $city_site[$parent_info['name']][$arr_new['name']] = $arr_new;
                            $tree_site[$parent_info['name']]['child'][$arr_new['name']] = $arr_new;
                        }
                    }
                }
            }

            // 批量添加三级区域
            if (empty($err) && !empty($regionList[3])) {
                $province_region = Db::name('region')->where(['level'=>1])->getAllWithIndex('id');
                $city_region = Db::name('region')->where(['level'=>2])->getAllWithIndex('id');
                $nameArr = $topids_arr = $parent_ids_arr = [];
                foreach ($regionList[3] as $key => $val) {
                    $is_add = true;
                    $parent_info = $city_region[$val['parent_id']];
                    $parent_info['name'] = $this->handle_name($parent_info['name']);
                    $top_info = $province_region[$parent_info['parent_id']];
                    $top_info['name'] = $this->handle_name($top_info['name']);
                    foreach ($area_site[$top_info['name']][$parent_info['name']] as $_k => $_v) {
                        if (stristr($val['name'], $_v['name'])) {
                            $is_add = false;
                            break;
                        }
                    }
                    if ($is_add) {
                        $handle_name = $this->handle_name($val['name']);
                        if (!empty($handle_name)) {
                            $nameArr[] = $handle_name;
                            $top_name = $top_info['name'];
                            $topids_arr[] = $province_site[$top_name]['id'];
                            $parent_name = $parent_info['name'];
                            $parent_ids_arr[] = $city_site[$top_name][$parent_name]['id'];
                        }
                    }
                }
                if (!empty($nameArr)) {
                    $batch_num = 500; // 每次写入N条区域，避免写太多报500
                    $nameArr = array_chunk($nameArr, $batch_num);
                    $topids_arr = array_chunk($topids_arr, $batch_num);
                    $parent_ids_arr = array_chunk($parent_ids_arr, $batch_num);
                    foreach ($nameArr as $key_tmp => $val_tmp) {
                        if (!empty($val_tmp)) {
                            $addData = [];
                            //$domainArr = Db::name('citysite')->where(['id'=>['gt', 0]])->column('domain');                            
                            foreach ($val_tmp as $key => $val) {
                                $domain = preg_replace("/[^a-zA-Z0-9]+/", "", get_pinyin($val));
                                //if (in_array($domain, $domainArr)) {
                                $domain = $this->rand_domain($domain,3);
                                //}
                                $addData = [
                                    'name'  => $val,
                                    'domain'  => $domain,
                                    'level' => 3,
                                    'parent_id' => $parent_ids_arr[$key_tmp][$key],
                                    'topid' => $topids_arr[$key_tmp][$key],
                                    'is_open' => !empty($post['is_open']) ? intval($post['is_open']) : 0,
                                    'showall' => !empty($post['showall']) ? intval($post['showall']) : 1,
                                    'seoset'   => !empty($post['seoset']) ? intval($post['seoset']) : 0,
                                    'status'   => !empty($post['status']) ? intval($post['status']) : 0,
                                    'initial'   => getFirstCharter($val),
                                    'seo_title' => str_replace(['{region}','{区域}'], $val, $seo_title),
                                    'seo_keywords' => str_replace(['{region}','{区域}'], $val, $seo_keywords),
                                    'seo_description' => str_replace(['{region}','{区域}'], $val, $seo_description),
                                    'sort_order'    => 100,
                                    'add_time'    => getTime(),
                                    'update_time'    => getTime(),
                                ];
                                $r = model('Citysite')->insert($addData);
                                unset($r,$domain);
                            }
                            if ($r === false) {
                                $err++; 
                            }
                        }
                    }
                }
            }

            if (empty($err)) {
                $data = [];
                // 导入全国的轮询一个个省份导入 start
                if (!empty($cur_province_id)) {
                    $data['progress'] = 0;
                    $data['next_province_id'] = 0;
                    $num = 0;
                    $regionRow = Db::name('region')->where(['level'=>1])->order('id asc')->select();
                    foreach ($regionRow as $key => $val) {
                        $num++;
                        if ($cur_province_id < $val['id']) {
                            $data['next_province_id'] = $val['id'];
                            break;
                        }
                    }
                    $progress = $num / count($regionRow);
                    $progress = sprintf("%.2f", substr(sprintf("%.3f", $progress), 0, -1));
                    if ($progress == intval($progress)) {
                        $progress = intval($progress);
                    }
                    $data['progress'] = intval($progress * 100);
                }
                // 导入全国的轮询一个个省份导入 end

                Cache::clear('citysite');
                adminLog('一键导入全国城市');
                $this->success("操作成功", null, $data);
            }else{
                $this->error("操作失败");
            }
            exit;
        }

        $assign_data = [];

        $province_list = Db::name('region')->field('id, name')
            ->where('level',1)
            ->select();
        foreach ($province_list as $key => $val) {
            $val['name'] = preg_replace('/(省|市)$/i', '', $val['name']);
            $province_list[$key] = $val;
        }

        $assign_data['province_list'] = $province_list;
        $this->assign($assign_data);

        return $this->fetch();
    }

    /**
     * 一键全部启用/关闭  二级域名
     */
    public function batch_set_is_open()
    {
        if (IS_AJAX_POST) {
            $set_is_open = input('param.set_is_open/d', 0);
            $r = Db::name('citysite')->where([
                    'is_open' => empty($set_is_open) ? 1 : 0,
                ])->update([
                    'is_open' => $set_is_open,
                    'update_time' => getTime(),
                ]);
            if ($r !== false) {
                tpSetting('citysite', ['citysite_set_is_open'=>$set_is_open], 'cn');
                \think\Cache::clear('citysite');
                delFile(RUNTIME_PATH);
                $this->success('操作成功');
            }
        }
        $this->error('操作失败');
    }
}