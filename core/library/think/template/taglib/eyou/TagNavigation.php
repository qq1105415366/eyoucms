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
namespace think\template\taglib\eyou;

use think\Config;
use think\Db;
use think\Cache;
use app\common\logic\BuildhtmlLogic;
use app\common\logic\NavigationLogic;

/**
 * 导航列表
 */
class TagNavigation extends Base
{
    private $arctypeAll = [];
    static $ReturnData = null;
    private $buildhtmlLogic = null;

    protected function _initialize()
    {
        parent::_initialize();
        $this->buildhtmlLogic = new BuildhtmlLogic;
        $this->arctypeAll = $this->buildhtmlLogic->get_arctype_all("id,current_channel,parent_id,dirname,typename,dirpath,diy_dirpath,rulelist,typelink"); // 获取全部的栏目信息
        if (null === self::$ReturnData) {
            self::$ReturnData = [
                'web_cmsurl'        => $this->root_dir."/",
                'index'             => url('user/Users/index'),
                'user_info'         => url('user/Users/info'),
                'my_collect'        => url('user/Users/collection_index'),
                'consumer_details'  => url('user/Pay/pay_consumer_details'),
                'shop_cart_list'    => url('user/Shop/shop_cart_list'),
                'shop_address_list' => url('user/Shop/shop_address_list'),
                'shop_centre'       => url('user/Shop/shop_centre'),
                'my_comment'        => url('user/ShopComment/index'),
                'release_centre'    => url('user/UsersRelease/release_centre'),
                'article_add'       => url('user/UsersRelease/article_add'),
            ];
        }

        $this->navigationlogic = new NavigationLogic;
    }

    public function getNavigation($position_id = null, $orderby = '', $ordermode = '', $currentclass = '', $nav_id = null)
    {
        if (!empty($nav_id)) {
            //获取某菜单下的子菜单
            $res = $this->getSon($nav_id, $orderby, $ordermode, $currentclass);
        } elseif (!empty($position_id)) {
            //获取某导航下所有菜单
            $res = $this->getAll($position_id, $orderby, $ordermode, $currentclass);
        } else {
            return false;
        }
        
        return $res;
    }

    public function getAll($position_id = null, $orderby = '', $ordermode = '', $currentclass = '')
    {
        $tid = $this->tid;
        $url = self::$request->url();
        $args = [$position_id, $orderby, $ordermode, $currentclass, $url, $tid, self::$home_lang];
        $cacheKey = 'taglib-'.md5(__CLASS__.__FUNCTION__.json_encode($args));
        $result = cache($cacheKey);
        if (!empty($result)) return $result;

        $where = [
            'c.status' => 1,
            'c.position_id' => $position_id,
        ];
        $nav_list = $this->navigationlogic->nav_list(0, 0, false, 0, $where, false);
        foreach ($nav_list as $key => $value) {
            $value['target']   = 1 == $value['target'] ? 'target="_blank"' : '';
            $value['nofollow'] = 1 == $value['nofollow'] ? 'rel="nofollow"' : '';
            $value['currentclass'] = $value['currentstyle'] = '';
            $value['nav_pic'] = !empty($value['nav_pic']) ? get_default_pic($value['nav_pic']) : '';
            if  (empty($value['type_id'])) {
                // 固定页面
                $nav_url = $value['nav_url'];//备份原来数据
                $value['nav_url'] = $this->GetNavigUrl($value['nav_url']);
                //如果返回是空说明是外部链接
                if (empty($value['nav_url'])) {
                    $value['nav_url'] = $nav_url;
                } else {
                    //否则是首页等已经定义好的页面
                    if ($url == $value['nav_url']) $value['currentclass'] = $value['currentstyle'] = $currentclass;
                }
            } else {
                //没有栏目同步
                if (empty($value['arctype_sync'])) {
                    if ($tid == $value['type_id']) $value['currentclass'] = $value['currentstyle'] = $currentclass;
                } else {
                    //栏目同步
                    $res = $this->getTypeUrl($value['type_id']);
                    if (1 == $res['ex_link']) {
                        //栏目外部链接
                        $value['nav_url'] = $res['url'];
                        if ($url == $value['nav_url']) $value['currentclass'] = $value['currentstyle'] = $currentclass;

                    } else {
                        $value['nav_url'] = $res['url'];
                        if ($tid == $value['type_id']) $value['currentclass'] = $value['currentstyle'] = $currentclass;
                    }
                }
            }

            $nav_list[$key] = $value;
        }

        // 导航层级归类成阶梯式
        $arr = group_same_key($nav_list, 'parent_id');
        for ($i=0; $i < count($nav_list); $i++) {
            foreach ($arr as $key => $val) {
                foreach ($arr[$key] as $key2 => $val2) {
                    if (!isset($arr[$val2['nav_id']])) continue;
                    $val2['children'] = $arr[$val2['nav_id']];
                    $arr[$key][$key2] = $val2;
                }
            }
        }
        // 重置
        reset($arr);
        // 取出第一个数组
        $result = current($arr);
        // 存入缓存
        cache($cacheKey, $result, null, 'nav_list');
        // 返回数据
        return $result;
    }

    public function getSon($nav_id = null, $orderby = '', $ordermode = '', $currentclass = '')
    {
        $url    = self::$request->url();
        $tid    = $this->tid;

        $args = [$nav_id, $orderby, $ordermode, $currentclass, $url, $tid, self::$home_lang];
        $cacheKey = 'taglib-'.md5(__CLASS__.__FUNCTION__.json_encode($args));
        $result = cache($cacheKey);
        if (!empty($result)) {
            return $result;
        }

        $where['status'] = 1;

        //先判断这个nav_id是一级菜单还是二级菜单
        $nav_info = Db::name('nav_list')->where('nav_id', $nav_id)->cache(true, EYOUCMS_CACHE_TIME, "nav_list")->find();
        //如果parent_id == topid 则是一级栏目,不等则为二级栏目
        if ($nav_info['parent_id'] == $nav_info['topid']) {
            $where['topid'] = $nav_id;
        } else {
            $where['parent_id_id'] = $nav_id;
        }

        $Order = "{$orderby} {$ordermode}";// 排序
        if (empty($orderby)) {
            $Order = 'sort_order asc,nav_id asc';
        }
        $result = Db::name('nav_list')
            ->where($where)
            ->order($Order)
            ->cache(true, EYOUCMS_CACHE_TIME, "nav_list")
            ->getAllWithIndex('nav_id');
        $topArr = [];
        $sonArr = [];
        if (!empty($result)) {
            foreach ($result as $key => &$value) {
                $result[$key]['is_cart']  = 'shop_cart_list' == $result[$key]['nav_url'] ? 1 : 0;
                $result[$key]['target']   = 1 == $result[$key]['target'] ? 'target="_blank"' : '';
                $result[$key]['nofollow'] = 1 == $result[$key]['nofollow'] ? 'rel="nofollow"' : '';
                $result[$key]['nav_pic']  = get_default_pic($result[$key]['nav_pic']);
                $result[$key]['currentclass'] = $result[$key]['currentstyle']    = '';

                if  (empty($result[$key]['type_id'])) {
                    //固定页面
                    $nav_url = $result[$key]['nav_url'];//备份原来数据
                    $result[$key]['nav_url'] = $this->GetNavigUrl($result[$key]['nav_url']);
                    //如果返回是空说明是外部链接
                    if (empty($result[$key]['nav_url'])){
                        $result[$key]['nav_url'] = $nav_url;
                    }else{
                        //否则是首页等已经定义好的页面
                        if ($url == $result[$key]['nav_url']) $result[$key]['currentclass'] = $result[$key]['currentstyle'] = $currentclass;
                    }
                } else{
                    //没有栏目同步
                    if (empty($result[$key]['arctype_sync'])){
                        if ($tid == $result[$key]['type_id']) $result[$key]['currentclass'] = $result[$key]['currentstyle'] = $currentclass;
                    }else{
                        //栏目同步
                        $res = $this->getTypeUrl($result[$key]['type_id']);
                        if (1 == $res['ex_link']){
                            //栏目外部链接
                            $result[$key]['nav_url'] = $res['url'];
                        }else{
                            $result[$key]['nav_url'] = $res['url'];
                            if ($tid == $result[$key]['type_id']) $result[$key]['currentclass'] = $result[$key]['currentstyle'] = $currentclass;
                        }
                    }
                }
            }

            foreach ($result as $k => $v) {
                if ($nav_id == $v['parent_id'] ) {
                    $topArr[$k] = $v;
                } else {
                    $sonArr[$k] = $v;
                }
            }

            foreach ($topArr as $key => $val) {
                foreach ($sonArr as $k => $v) {
                    if ($v['parent_id'] == $val['nav_id']) {
                        $topArr[$key]['children'][] = $v;
                    }
                }
            }
        }
        cache($cacheKey, $topArr, null, 'nav_list');

        return $topArr;
    }

    private function getTypeUrl($typeid = 0)
    {
        $arctypeInfo = !empty($this->arctypeAll[$typeid]) ? $this->arctypeAll[$typeid] : [];
        if (!empty($arctypeInfo['typelink'])){
            //走栏目外部链接
            return ['url'=>$arctypeInfo['typelink'], 'ex_link'=>1];
        } else {
            static $channeltype_row = null;
            if (null === $channeltype_row) {
                $cacheKey = "extra_global_channeltype";
                $channeltype_row = \think\Cache::get($cacheKey);
                if (empty($channeltype_row)) {
                    $channeltype_row = \think\Db::name('channeltype')->field('id,nid,ctl_name,title,ntitle,ifsystem,table,status')
                        ->order('id asc')
                        ->getAllWithIndex('id');
                    \think\Cache::set($cacheKey, $channeltype_row, EYOUCMS_CACHE_TIME, "channeltype");
                }
            }
            $url = '';
            if (!empty($arctypeInfo['current_channel']) && !empty($channeltype_row[$arctypeInfo['current_channel']]['ctl_name'])) {
                $controller_name = $channeltype_row[$arctypeInfo['current_channel']]['ctl_name'];
                $url = typeurl("home/{$controller_name}/lists", $arctypeInfo);
            }
            return ['url'=>$url, 'ex_link'=>0];
        }
    }

    // 获取URL
    private function GetNavigUrl($nav_url)
    {
        if (isset(self::$ReturnData[$nav_url])){
            return self::$ReturnData[$nav_url];
        }else{
            return '';
        }

    }
}