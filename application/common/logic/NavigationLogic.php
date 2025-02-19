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
use think\Model;
use app\common\logic\ArctypeLogic;

/**
 * 菜单逻辑定义
 * @package common\Logic
 */
class NavigationLogic extends Model
{
    /**
     * 构造方法
     */
    public function initialize(){
        parent::initialize();
        $this->arctypeLogic = new ArctypeLogic();
    }

    /**
     * 全部菜单
     */
    public function GetAllArctype($type_id = 0)
    {
        $where = [
            'is_del' => 0,
            'status' => 1,
            'lang'   => get_admin_lang(),
        ];
        $field = 'id, parent_id, typename, dirname, litpic,grade';

        // 查询所有可投稿的菜单
        $ArcTypeData = Db::name('arctype')->field($field)->where($where)->select();
        $oneLevel    = $twoLevel = $threeLevel = [];
        foreach ($ArcTypeData as $k => $v) {
            if (0 == $v['grade']) {
                $oneLevel[] = $v;
            } elseif (1 == $v['grade']) {
                $twoLevel[] = $v;
            } elseif (2 == $v['grade']) {
                $threeLevel[] = $v;
            }
        }
        static $seo_pseudo = null;
        if (null === $seo_pseudo) {
            $seoConfig  = tpCache('seo');
            $seo_pseudo = !empty($seoConfig['seo_pseudo']) ? $seoConfig['seo_pseudo'] : config('ey_config.seo_pseudo');
        }

        // 下拉框拼装
        $HtmlCode = '<select name="type_id" id="type_id" onchange="SyncData(this);">';
        $HtmlCode .= '<option id="arctype_default" value="0">请选择菜单</option>';
        foreach ($oneLevel as $yik => $yiv) {
            /*菜单路径*/
            if (2 == $seo_pseudo) {
                // 生成静态
                $typeurl = ROOT_DIR . "/index.php?m=home&c=Lists&a=index&tid={$yiv['id']}";
            } else {
                // 动态或伪静态
                $typeurl = typeurl("home/Lists/index", $yiv, true, false, $seo_pseudo, null);
                $typeurl = auto_hide_index($typeurl);
            }

            $style1 = $type_id == $yiv['id'] ? 'selected' : '';//是否选中
            //一级下拉框
            $HtmlCode .= '<option value="' . $yiv['id'] . '" data-typeurl="' . $typeurl . '" data-typename="' . $yiv['typename'] . '" ' . $style1 . '>';
            if ($yiv['grade'] > 0)
            {
                $HtmlCode .= str_repeat('&nbsp;', $yiv['grade'] * 4);
            }
            $HtmlCode .= htmlspecialchars_decode(addslashes($yiv['typename'])) . '</option>';

            foreach ($twoLevel as $key => $val) {
                if ($yiv['id'] == $val['parent_id']) {
                    if (2 == $seo_pseudo) {
                        // 生成静态
                        $typeurl = ROOT_DIR . "/index.php?m=home&c=Lists&a=index&tid={$val['id']}";
                    } else {
                        // 动态或伪静态
                        $typeurl = typeurl("home/Lists/index", $val, true, false, $seo_pseudo, null);
                        $typeurl = auto_hide_index($typeurl);
                    }

                    $style1 = $type_id == $val['id'] ? 'selected' : '';//是否选中
                    //二级下拉框
                    $HtmlCode .= '<option value="' . $val['id'] . '" data-typeurl="' . $typeurl . '" data-typename="' . $val['typename'] . '" ' . $style1 . '>';
                    if ($val['grade'] > 0)
                    {
                        $HtmlCode .= str_repeat('&nbsp;', $val['grade'] * 4);
                    }
                    $HtmlCode .= htmlspecialchars_decode(addslashes($val['typename'])) . '</option>';
                    foreach ($threeLevel as $k => $v) {
                        if ($val['id'] == $v['parent_id']) {
                            if (2 == $seo_pseudo) {
                                // 生成静态
                                $typeurl = ROOT_DIR . "/index.php?m=home&c=Lists&a=index&tid={$v['id']}";
                            } else {
                                // 动态或伪静态
                                $typeurl = typeurl("home/Lists/index", $v, true, false, $seo_pseudo, null);
                                $typeurl = auto_hide_index($typeurl);
                            }

                            $style1 = $type_id == $v['id'] ? 'selected' : '';//是否选中
                            //三级下拉框
                            $HtmlCode .= '<option value="' . $v['id'] . '" data-typeurl="' . $typeurl . '" data-typename="' . $v['typename'] . '"' . $style1 . '>';
                            if ($v['grade'] > 0)
                            {
                                $HtmlCode .= str_repeat('&nbsp;', $v['grade'] * 4);
                            }
                            $HtmlCode .= htmlspecialchars_decode(addslashes($v['typename'])) . '</option>';
                            unset($threeLevel[$k]);
                        }
                    }
                    unset($twoLevel[$key]);
                }
            }
        }
        $HtmlCode .= '</select>';
        return $HtmlCode;
    }

    // 获取全部可用于快速生成菜单的栏目列表
    public function getAllArctypeList($type_id = 0)
    {
        // 栏目最大层级数
        $arctype_max_level = intval(config('global.arctype_max_level'));
        // 获取全部可用栏目
        $options = $this->arctypeLogic->arctype_list(0, 0, false, $arctype_max_level - 1, ['is_del' => 0]);

        // URL模式
        static $seo_pseudo = null;
        if (null === $seo_pseudo) {
            $seoConfig  = tpCache('seo');
            $seo_pseudo = !empty($seoConfig['seo_pseudo']) ? $seoConfig['seo_pseudo'] : config('ey_config.seo_pseudo');
        }

        // 栏目选择内容
        $arctypeHtml = '<select name="type_id" id="type_id" onchange="SyncData(this);">';
        $arctypeHtml .= '<option id="arctype_default" value="0">请选择菜单</option>';
        foreach ($options as $var) {
            // 是否选中
            $isSelected = $type_id == $var['id'] ? 'selected' : '';
            // 菜单URL
            if (2 == $seo_pseudo) {
                // 生成静态
                $typeurl = ROOT_DIR . "/index.php?m=home&c=Lists&a=index&tid={$var['id']}";
            } else {
                // 动态或伪静态
                $typeurl = auto_hide_index(typeurl("home/Lists/index", $var, true, false, $seo_pseudo, null));
            }
            // 拼装选项
            $arctypeHtml .= '<option value="' . $var['id'] . '" data-typeurl="' . $typeurl . '" data-typename="' . $var['typename'] . '" ' . $isSelected . '>';
            if ($var['level'] > 0) {
                $arctypeHtml .= str_repeat('&nbsp;', $var['level'] * 4);
            }
            $arctypeHtml .= htmlspecialchars_decode(addslashes($var['typename'])) . '</option>';
        }
        $arctypeHtml .= '</select>';

        // 返回内容
        // dump($arctypeHtml);exit;
        return $arctypeHtml;
    }

    // 获取全部导航菜单列表
    public function getAllNavList($position_id = 0, $nav_id = 0)
    {
        // 查询所有可投稿的菜单的顶级菜单
        $where = [
            'c.is_del' => 0,
            'c.status' => 1,
            'c.position_id' => $position_id
        ];
        $navList = $this->nav_list(0, 0, false, 0, $where, false);
        $navHtml = '<select onchange="selectNav(this);">';
        $navHtml .= '<option value="0">请选择菜单</option>';
        foreach ($navList as $var) {
            $navHtml .= '<option value="' . $var['nav_id'] . '" data-topid="' . $var['topid'] . '"';
            $navHtml .= (intval($nav_id) === intval($var['nav_id'])) ? " selected='true' " : '';
            $navHtml .= '>';
            if (intval($var['level']) > 0) {
                $navHtml .= str_repeat('&nbsp;', intval($var['level']) * 4);
            }
            $navHtml .= htmlspecialchars_decode(addslashes($var['nav_name'])) . '</option>';
        }
        $navHtml .= '</select>';
        // dump($navHtml);exit;
        return $navHtml;
    }

    /**
     * 获得指定菜单下的子菜单的数组
     *
     * @access  public
     * @param   int $id 菜单的ID
     * @param   int $selected 当前选中菜单的ID
     * @param   boolean $re_type 返回的类型: 值为真时返回下拉列表,否则返回数组
     * @param   int $level 限定返回的级数。为0时返回所有级数
     * @param   array $map 查询条件
     * @return  mix
     */
    public function nav_list($id = 0, $selected = 0, $re_type = true, $level = 0, $map = array(), $is_cache = true)
    {
        $fields = "c.*, c.nav_id as typeid, count(s.nav_id) as has_children, '' as children";
        $args = [$fields, $map, $is_cache];
        $cacheKey = 'nav_list-'.md5(__CLASS__.__FUNCTION__.json_encode($args));
        $res = cache($cacheKey);
        if (empty($res) || empty($is_cache)) {
            $res = Db::name('nav_list')
                ->field($fields)
                ->alias('c')
                ->join('nav_list s', 's.parent_id = c.nav_id', 'LEFT')
                ->where($map)
                ->group('c.nav_id')
                ->order('c.parent_id asc, c.sort_order asc, c.nav_id')
                // ->cache($is_cache, EYOUCMS_CACHE_TIME, "nav_list")
                ->select();
            cache($cacheKey, $res, null, 'nav_list');
        }
        if (empty($res) == true) {
            return $re_type ? '' : array();
        }

        $options = $this->nav_options($id, $res); // 获得指定菜单下的子菜单的数组

        /* 截取到指定的缩减级别 */
        if ($level > 0) {
            if ($id == 0) {
                $end_level = $level;
            } else {
                $first_item = reset($options); // 获取第一个元素
                $end_level  = $first_item['level'] + $level;
            }

            /* 保留level小于end_level的部分 */
            foreach ($options AS $key => $val) {
                if ($val['level'] >= $end_level) {
                    unset($options[$key]);
                }
            }
        }

        $pre_key = 0;
        $select = '';
        foreach ($options AS $key => $value) {
            $options[$key]['has_children'] = 0;
            if ($pre_key > 0) {
                if ($options[$pre_key]['nav_id'] == $options[$key]['parent_id']) {
                    $options[$pre_key]['has_children'] = 1;
                }
            }
            $pre_key = $key;

            if ($re_type == true) {
                $select .= '<option value="' . $value['nav_id'] . '" ';
                $select .= ($selected == $value['nav_id']) ? "selected='true'" : '';
                $select .= '>';
                if ($value['level'] > 0) {
                    $select .= str_repeat('&nbsp;', $value['level'] * 4);
                }
                $select .= htmlspecialchars_decode(addslashes($value['nav_name'])) . '</option>';
            }
        }

        if ($re_type == true) {
            return $select;
        } else {
            return $options;
        }
    }

    /**
     * 过滤和排序所有菜单，返回一个带有缩进级别的数组
     *
     * @access  private
     * @param   int $id 上级菜单ID
     * @param   array $arr 含有所有菜单的数组
     * @param   int $level 级别
     * @return  void
     */
    public function nav_options($spec_id, $arr)
    {
        $cat_options = array();

        if (isset($cat_options[$spec_id])) {
            return $cat_options[$spec_id];
        }

        if (!isset($cat_options[0])) {
            $level   = $last_id = 0;
            $options = $id_array = $level_array = array();
            while (!empty($arr)) {
                foreach ($arr AS $key => $value) {
                    $id = $value['nav_id'];
                    if ($level == 0 && $last_id == 0) {
                        if ($value['parent_id'] > 0) {
                            break;
                        }

                        $options[$id]             = $value;
                        $options[$id]['level']    = $level;
                        $options[$id]['nav_id']   = $id;
                        $options[$id]['nav_name'] = htmlspecialchars_decode($value['nav_name']);
                        unset($arr[$key]);

                        if ($value['has_children'] == 0) {
                            continue;
                        }
                        $last_id               = $id;
                        $id_array              = array($id);
                        $level_array[$last_id] = ++$level;
                        continue;
                    }

                    if ($value['parent_id'] == $last_id) {
                        $options[$id]             = $value;
                        $options[$id]['level']    = $level;
                        $options[$id]['nav_id']   = $id;
                        $options[$id]['nav_name'] = htmlspecialchars_decode($value['nav_name']);
                        unset($arr[$key]);

                        if ($value['has_children'] > 0) {
                            if (end($id_array) != $last_id) {
                                $id_array[] = $last_id;
                            }
                            $last_id               = $id;
                            $id_array[]            = $id;
                            $level_array[$last_id] = ++$level;
                        }
                    } elseif ($value['parent_id'] > $last_id) {
                        break;
                    }
                }

                $count = count($id_array);
                if ($count > 1) {
                    $last_id = array_pop($id_array);
                } elseif ($count == 1) {
                    if ($last_id != end($id_array)) {
                        $last_id = end($id_array);
                    } else {
                        $level    = 0;
                        $last_id  = 0;
                        $id_array = array();
                        continue;
                    }
                }

                if ($last_id && isset($level_array[$last_id])) {
                    $level = $level_array[$last_id];
                } else {
                    $level = 0;
                    break;
                }
            }
            $cat_options[0] = $options;
        } else {
            $options = $cat_options[0];
        }

        if (!$spec_id) {
            return $options;
        } else {
            if (empty($options[$spec_id])) {
                return array();
            }

            $spec_id_level = $options[$spec_id]['level'];

            foreach ($options AS $key => $value) {
                if ($key != $spec_id) {
                    unset($options[$key]);
                } else {
                    break;
                }
            }

            $spec_id_array = array();
            foreach ($options AS $key => $value) {
                if (($spec_id_level == $value['level'] && $value['nav_id'] != $spec_id) ||
                    ($spec_id_level > $value['level'])) {
                    break;
                } else {
                    $spec_id_array[$key] = $value;
                }
            }
            $cat_options[$spec_id] = $spec_id_array;

            return $spec_id_array;
        }
    }

    // 前台功能列表
    public function ForegroundFunction()
    {
        return $ReturnData = [
            0  => [
                'title' => '网站首页',
                'url'   => "web_cmsurl"
            ],
            1  => [
                'title' => '个人中心',
                'url'   => "index"
            ],
            2  => [
                'title' => '我的信息',
                'url'   => "user_info"
            ],
            3  => [
                'title' => '我的收藏',
                'url'   => "my_collect"
            ],
            4  => [
                'title' => '财务明细',
                'url'   => "consumer_details"
            ],
            5  => [
                'title' => '购物车',
                'url'   => 'shop_cart_list'
            ],
            6  => [
                'title' => '收货地址',
                'url'   => "shop_address_list"
            ],
            7  => [
                'title' => '我的订单',
                'url'   => "shop_centre"
            ],
            8  => [
                'title' => '我的评价',
                'url'   => "my_comment"
            ],
            9  => [
                'title' => '投稿列表',
                'url'   => "release_centre"
            ],
            10 => [
                'title' => '我要投稿',
                'url'   => "article_add"
            ],
            11 => [
                'title' => '外部链接',
                'url'   => "external_link"
            ],

        ];
    }
}