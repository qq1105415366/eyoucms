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

use think\Page;
use think\Db;
use app\common\logic\ArctypeLogic;
use app\admin\logic\ProductLogic;
use app\admin\logic\ProductSpecLogic; // 用于产品规格逻辑功能处理

class Product extends Base
{
    // 模型标识
    public $nid = 'product';
    // 模型ID
    public $channeltype = '';
    // 表单类型
    public $attrInputTypeArr = array();

    public function _initialize()
    {
        parent::_initialize();
        $channeltype_list  = config('global.channeltype_list');
        $this->channeltype = $channeltype_list[$this->nid];
        empty($this->channeltype) && $this->channeltype = 2;
        $this->attrInputTypeArr = config('global.attr_input_type_arr');
        $this->assign('nid', $this->nid);
        $this->assign('channeltype', $this->channeltype);
        // 商城中心开关
        $shopOpen = isset($this->usersConfig['shop_open']) ? intval($this->usersConfig['shop_open']) : 0;
        $this->assign('shopOpen', $shopOpen);

        // 返回页面
        $paramTypeid = input('param.typeid/d', 0);
        $this->callback_url = url('Product/index', ['lang' => $this->admin_lang, 'typeid' => $paramTypeid]);
        $this->assign('callback_url', $this->callback_url);
    }

   //产品列表
    public function index()
    {
        $assign_data = $condition = [];

        // 获取到所有GET参数
        $param = input('param.');
        $typeid = input('typeid/d', 0);

        // 搜索、筛选查询条件处理
        foreach (['keywords', 'typeid', 'flag', 'is_release','province_id','city_id','area_id'] as $key) {
            if ($key == 'typeid' && empty($param['typeid'])) {
                $typeids = Db::name('arctype')->where('current_channel', $this->channeltype)->column('id');
                $condition['a.typeid'] = array('IN', $typeids);
            }
            if (isset($param[$key]) && $param[$key] !== '') {
                if ($key == 'keywords') {
                    $keywords = $param[$key];
                    $condition['a.title'] = array('LIKE', "%{$param[$key]}%");
                } else if ($key == 'typeid') {
                    $typeid = $param[$key];
                    $hasRow = model('Arctype')->getHasChildren($typeid);
                    $typeids = get_arr_column($hasRow, 'id');
                    // 权限控制 by 小虎哥
                    $admin_info = session('admin_info');
                    if (0 < intval($admin_info['role_id'])) {
                        $auth_role_info = $admin_info['auth_role_info'];
                        if (!empty($typeid) && !empty($auth_role_info) && !empty($auth_role_info['permission']['arctype'])) {
                            $typeids = array_intersect($typeids, $auth_role_info['permission']['arctype']);
                        }
                    }
                    $condition['a.typeid'] = array('IN', $typeids);
                } else if ($key == 'flag') {
                    if ('is_release' == $param[$key]) {
                        $condition['a.users_id'] = array('gt', 0);
                    } else {
                        $FlagNew = $param[$key];
                        $condition['a.'.$param[$key]] = array('eq', 1);
                    }
                } else if (in_array($key, ['province_id','city_id','area_id'])) {
                    if (!empty($param['area_id'])) {
                        $condition['a.area_id'] = $param['area_id'];
                    } else if (!empty($param['city_id'])) {
                        $condition['a.city_id'] = $param['city_id'];
                    } else if (!empty($param['province_id'])) {
                        $condition['a.province_id'] = $param['province_id'];
                    }
                } else {
                    $condition['a.'.$key] = array('eq', $param[$key]);
                }
            }
        }

        // 权限控制 by 小虎哥
        $admin_info = session('admin_info');
        if (0 < intval($admin_info['role_id'])) {
            $auth_role_info = $admin_info['auth_role_info'];
            if (!empty($auth_role_info) && isset($auth_role_info['only_oneself']) && 1 == $auth_role_info['only_oneself']) {
                $condition['a.admin_id'] = $admin_info['admin_id'];
            }
        }

        // 时间检索条件
        $begin = strtotime(input('add_time_begin'));
        $end = strtotime(input('add_time_end'));
        if ($begin > 0 && $end > 0) {
            $condition['a.add_time'] = array('between', "$begin, $end");
        } else if ($begin > 0) {
            $condition['a.add_time'] = array('egt', $begin);
        } else if ($end > 0) {
            $condition['a.add_time'] = array('elt', $end);
        }

        // 必要条件
        $condition['a.channel'] = array('eq', $this->channeltype);
        $condition['a.lang'] = array('eq', $this->admin_lang);
        $condition['a.is_del'] = array('eq', 0);
        $conditionNew = "(a.users_id = 0 OR (a.users_id > 0 AND a.arcrank >= 0))";

        // 自定义排序
        $orderby = input('param.orderby/s');
        $orderway = input('param.orderway/s');
        if (!empty($orderby) && !empty($orderway)) {
            $orderby = "a.{$orderby} {$orderway}, a.aid desc";
        } else {
            $orderby = "a.aid desc";
        }

        // 数据查询，搜索出主键ID的值
        $SqlQuery = Db::name('archives')->alias('a')->where($condition)->where($conditionNew)->fetchSql()->count('aid');
        $count = Db::name('sql_cache_table')->where(['sql_md5'=>md5($SqlQuery)])->getField('sql_result');
        $count = ($count < 0) ? 0 : $count;
        if (empty($count)) {
            $count = Db::name('archives')->alias('a')->where($condition)->where($conditionNew)->count('aid');
            /*添加查询执行语句到mysql缓存表*/
            $SqlCacheTable = [
                'sql_name' => '|product|' . $this->channeltype . '|',
                'sql_result' => $count,
                'sql_md5' => md5($SqlQuery),
                'sql_query' => $SqlQuery,
                'add_time' => getTime(),
                'update_time' => getTime(),
            ];
            if (!empty($FlagNew)) $SqlCacheTable['sql_name'] = $SqlCacheTable['sql_name'] . $FlagNew . '|';
            if (!empty($typeid)) $SqlCacheTable['sql_name'] = $SqlCacheTable['sql_name'] . $typeid . '|';
            if (!empty($keywords)) $SqlCacheTable['sql_name'] = '|product|keywords|';
            Db::name('sql_cache_table')->insertGetId($SqlCacheTable);
            /*END*/
        }

        $Page = new Page($count, config('paginate.list_rows'));
        $list = [];
        if (!empty($count)) {
            $limit = $count > config('paginate.list_rows') ? $Page->firstRow.','.$Page->listRows : $count;
            $list = Db::name('archives')
                ->field("a.aid")
                ->alias('a')
                ->where($condition)
                ->where($conditionNew)
                ->order($orderby)
                ->limit($limit)
                ->getAllWithIndex('aid');
            // 在数据量大的情况下，经过优化的搜索逻辑，先搜索出主键ID，再通过ID将其他信息补充完整
            if (!empty($list)) {
                $aids = array_keys($list);
                $fields = "b.*, a.*, a.aid as aid";
                $row = Db::name('archives')
                    ->field($fields)
                    ->alias('a')
                    ->join('__ARCTYPE__ b', 'a.typeid = b.id', 'LEFT')
                    ->where('a.aid', 'in', $aids)
                    ->getAllWithIndex('aid');
                $cityRow = get_citysite_list();
                foreach ($list as $key => $val) {
                    $row[$val['aid']]['areas']  = '';
                    if($row[$val['aid']]['area_id']>0){
                        if (isset($cityRow[$row[$val['aid']]['area_id']]['name'])) {
                            $row[$val['aid']]['areas']  = $cityRow[$row[$val['aid']]['area_id']]['name'];     
                        }
                    }elseif($row[$val['aid']]['city_id']>0){
                        if (isset($cityRow[$row[$val['aid']]['city_id']]['name'])) {
                            $row[$val['aid']]['areas']  = $cityRow[$row[$val['aid']]['city_id']]['name'];     
                        }
                    }elseif($row[$val['aid']]['province_id']>0){                        
                        if (isset($cityRow[$row[$val['aid']]['province_id']]['name'])) {
                            $row[$val['aid']]['areas']  = $cityRow[$row[$val['aid']]['province_id']]['name'];     
                        }
                    } else{
                        $row[$val['aid']]['areas']  = '全国';
                    }
                    $row[$val['aid']]['arcurl'] = get_arcurl($row[$val['aid']]);
                    $row[$val['aid']]['litpic'] = handle_subdir_pic($row[$val['aid']]['litpic']); // 支持子目录
                    $list[$key] = $row[$val['aid']];
                }
            }
        }

        $show = $Page->show();
        $assign_data['page'] = $show;
        $assign_data['list'] = $list;
        $assign_data['pager'] = $Page;
        $assign_data['typeid'] = $typeid;
        $assign_data['tab'] = input('param.tab/d', 3);// 选项卡
        $assign_data['archives_flags'] = model('ArchivesFlag')->getList();// 文档属性
        $assign_data['arctype_info'] = $typeid > 0 ? Db::name('arctype')->field('typename')->find($typeid) : [];// 当前栏目信息
        $this->assign($assign_data);
        return $this->fetch();
    }

    /**
     * 添加
     */
    public function add()
    {
        $admin_info = session('admin_info');
        $auth_role_info = $admin_info['auth_role_info'];
        $this->assign('auth_role_info', $auth_role_info);
        $this->assign('admin_info', $admin_info);
        if (IS_POST) {
            $post = input('post.');
            model('Archives')->editor_auto_210607($post);
            /* 处理TAG标签 */
            if (!empty($post['tags_new'])) {
                $post['tags'] = !empty($post['tags']) ? $post['tags'] . ',' . $post['tags_new'] : $post['tags_new'];
                unset($post['tags_new']);
            }
            $post['tags'] = explode(',', $post['tags']);
            $post['tags'] = array_unique($post['tags']);
            $post['tags'] = implode(',', $post['tags']);
            /* END */

            $content = empty($post['addonFieldExt']['content']) ? '' : htmlspecialchars_decode($post['addonFieldExt']['content']);

            // 根据标题自动提取相关的关键字
            $seo_keywords = $post['seo_keywords'];
            if (!empty($seo_keywords)) {
                $seo_keywords = str_replace('，', ',', $seo_keywords);
            } else {
                // $seo_keywords = get_split_word($post['title'], $content);
            }

            // 自动获取内容第一张图片作为封面图
            $litpic = $post['litpic'];
            if (empty($litpic)) {
                $litpic = get_html_first_imgurl($content);
            }
            $post['litpic'] = $litpic;

            /*是否有封面图*/
            if (empty($post['litpic'])) {
                $is_litpic = 0; // 无封面图
            } else {
                $is_litpic = 1; // 有封面图
            }

            // SEO描述
            $seo_description = '';
            if (empty($post['seo_description']) && !empty($content)) {
                $seo_description = @msubstr(checkStrHtml($content), 0, get_seo_description_length(), false);
            } else {
                $seo_description = $post['seo_description'];
            }

            // 外部链接跳转
            $jumplinks = '';
            $is_jump = isset($post['is_jump']) ? $post['is_jump'] : 0;
            if (intval($is_jump) > 0) {
                $jumplinks = $post['jumplinks'];
            }

            // 模板文件，如果文档模板名与栏目指定的一致，默认就为空。让它跟随栏目的指定而变
            if ($post['type_tempview'] == $post['tempview']) {
                unset($post['type_tempview']);
                unset($post['tempview']);
            }
            
            //处理自定义文件名,仅由字母数字下划线和短横杆组成,大写强制转换为小写
            $htmlfilename = trim($post['htmlfilename']);
            if (!empty($htmlfilename)) {
                $htmlfilename = preg_replace("/[^\x{4e00}-\x{9fa5}\w\-]+/u", "-", $htmlfilename);
                // $htmlfilename = strtolower($htmlfilename);
                //判断是否存在相同的自定义文件名
                $map = [
                    'htmlfilename'  => $htmlfilename,
                    'lang'  => $this->admin_lang,
                ];
                if (!empty($post['typeid'])) {
                    $map['typeid'] = array('eq', $post['typeid']);
                }
                $filenameCount = Db::name('archives')->where($map)->count();
                if (!empty($filenameCount)) {
                    $this->error("同栏目下，自定义文件名已存在！");
                } else if (preg_match('/^(\d+)$/i', $htmlfilename)) {
                    $this->error("自定义文件名不能纯数字，会与文档ID冲突！");
                }
            } else {
                // 处理外贸链接
                if (is_dir('./weapp/Waimao/')) {
                    $waimaoLogic = new \weapp\Waimao\logic\WaimaoLogic;
                    $waimaoLogic->get_new_htmlfilename($htmlfilename, $post, 'add', $this->globalConfig);
                } else {
                    $foreignLogic = new \app\admin\logic\ForeignLogic;
                    $foreignLogic->get_new_htmlfilename($htmlfilename, $post, 'add', $this->globalConfig);
                }
            }
            $post['htmlfilename'] = $htmlfilename;

            // 产品类型
            if (!empty($post['prom_type'])) {
                if ($post['prom_type_vir'] == 2) {
                    $post['netdisk_url'] = trim($post['netdisk_url']);
                    if (empty($post['netdisk_url'])) {
                        $this->error("网盘地址不能为空！");
                    }
                    $post['prom_type'] = 2;
                } else if ($post['prom_type_vir'] == 3) {
                    $post['text_content'] = trim($post['text_content']);
                    if (empty($post['text_content'])) {
                        $this->error("虚拟文本内容不能为空！");
                    }
                    $post['prom_type'] = 3;
                }
            }

            //做自动通过审核判断
            if ($admin_info['role_id'] > 0 && $auth_role_info['check_oneself'] < 1) {
                $post['arcrank'] = -1;
            }

            // 副栏目
            if (isset($post['stypeid'])) {
                $post['stypeid'] = preg_replace('/([^\d\,\，]+)/i', ',', $post['stypeid']);
                $post['stypeid'] = str_replace('，', ',', $post['stypeid']);
                $post['stypeid'] = trim($post['stypeid'], ',');
                $post['stypeid'] = str_replace(",{$post['typeid']},", ',', ",{$post['stypeid']},");
                $post['stypeid'] = trim($post['stypeid'], ',');
            }

            // 虚拟销量和总虚拟销量
            $post['virtual_sales'] = empty($post['virtual_sales']) ? 0 : intval($post['virtual_sales']);
            if (!empty($post['spec_type']) && $post['spec_type'] == 2) { // 多规格
                $sales_all = 0;
                $post['virtual_sales'] = 0; // 多规格不加上虚拟销量
                foreach ($post['spec_sales'] as $key => $val) {
                    $sales_all += intval($val['spec_sales_num']); // + $post['virtual_sales'];
                }
            } else { // 单规格
                $sales_all = $post['virtual_sales'];
            }

            // --存储数据
            $newData = array(
                'typeid'=> empty($post['typeid']) ? 0 : $post['typeid'],
                'channel'   => $this->channeltype,
                'is_b'      => empty($post['is_b']) ? 0 : $post['is_b'],
                'is_head'      => empty($post['is_head']) ? 0 : $post['is_head'],
                'is_special'      => empty($post['is_special']) ? 0 : $post['is_special'],
                'is_recom'      => empty($post['is_recom']) ? 0 : $post['is_recom'],
                'is_roll'      => empty($post['is_roll']) ? 0 : $post['is_roll'],
                'is_slide'      => empty($post['is_slide']) ? 0 : $post['is_slide'],
                'is_diyattr'      => empty($post['is_diyattr']) ? 0 : $post['is_diyattr'],
                'editor_remote_img_local'=> empty($post['editor_remote_img_local']) ? 0 : $post['editor_remote_img_local'],
                'editor_img_clear_link'  => empty($post['editor_img_clear_link']) ? 0 : $post['editor_img_clear_link'],
                'is_jump'     => $is_jump,
                'is_litpic'     => $is_litpic,
                'jumplinks' => $jumplinks,
                'origin'      => empty($post['origin']) ? '网络' : $post['origin'],
                'seo_keywords'     => $seo_keywords,
                'seo_description'     => $seo_description,
                'admin_id'  => session('admin_info.admin_id'),
                'sales_all'    => $sales_all,
                'stock_show'    => empty($post['stock_show']) ? 0 : $post['stock_show'],
                'crossed_price'     => empty($post['crossed_price']) ? 0 : floatval($post['crossed_price']),
                'users_price'      => empty($post['users_price']) ? 0 : floatval($post['users_price']),
                'old_price'      => empty($post['old_price']) ? 0 : floatval($post['old_price']),
                'lang'  => $this->admin_lang,
                'sort_order'    => 100,
                'add_time'     => strtotime($post['add_time']),
                'update_time'  => strtotime($post['add_time']),
            );
            $data = array_merge($post, $newData);
            // if (!empty($post['param_type']) && 2 == $post['param_type']) {
            //     $data['attrlist_id'] = 0;
            // }
            if (2 === intval($post['spec_type'])) {
                $data['stock_show'] = 1;
                $data['users_discount_type'] = 0;
            }
            $aid = Db::name('archives')->insertGetId($data);
            $_POST['aid'] = $aid;
            if (!empty($aid)) {
                // 单规格 且 选择指定会员级别 则 执行
                if (1 === intval($post['spec_type']) && 1 === intval($post['users_discount_type'])) {
                    model('ShopPublicHandle')->saveUsersDiscountPriceList($post['users_discount'], $aid);
                }

                // ---------后置操作
                $newAttr = !empty($post['is_old_product_attr']) ? false : true;
                model('Product')->afterSave($aid, $data, 'add', $newAttr);

                // 添加查询执行语句到mysql缓存表
                model('SqlCacheTable')->InsertSqlCacheTable();

                // 若选择多规格选项，则添加产品规格
                if (!empty($post['spec_type']) && 2 == $post['spec_type']) {
                    // 更新规格名称数据
                    $data['aid'] = $aid;
                    model('ProductSpecData')->ProducSpecNameEditSave($data, 'add');
                    // 更新规格值及金额数据
                    model('ProductSpecValue')->ProducSpecValueEditSave($data, 'add');
                    // model('ProductSpecPreset')->ProductSpecInsertAll($aid, $data);
                }

                // 若选择自定义参数则执行
                if (!empty($post['attr_name']) && !empty($post['attr_value'])) {
                    // 新增商品参数
                    $attrName = !empty($post['attr_name']) ? $post['attr_name'] : [];
                    $attrValue = !empty($post['attr_value']) ? $post['attr_value'] : [];
                    $sortOrder = !empty($post['sort_order']) ? $post['sort_order'] : 100;
                    $productAttribute = $productAttr = [];
                    $time = getTime();
                    foreach ($attrName as $key => $value) {
                        if (!empty($value)) {
                            $productAttribute = [
                                'aid' => $aid,
                                'attr_name' => trim($value),
                                'attr_values' => '',
                                'sort_order' => 100,//intval($sortOrder[$key]),
                                'lang' => $this->admin_lang,
                                'is_custom' => 1,
                                'add_time' => $time,
                                'update_time' => $time,
                            ];
                            $attrID = Db::name('shop_product_attribute')->insertGetId($productAttribute);
                            if (!empty($attrValue[$key])) {
                                $productAttr = [
                                    'aid' => $aid,
                                    'attr_id' => $attrID,
                                    'attr_value' => $attrValue[$key],
                                    'is_custom' => 1,
                                    'sort_order' => intval($sortOrder[$key]),
                                    'add_time' => $time,
                                    'update_time' => $time,
                                ];
                                Db::name('shop_product_attr')->insertGetId($productAttr);
                            }
                        }
                    }
                }

                adminLog('新增产品：' . $data['title']);

                // 虚拟商品保存
                if (!empty($post['prom_type']) && in_array($post['prom_type'], [2, 3])) {
                    model('ProductNetdisk')->saveProductNetdisk($aid, $data);
                }

                // 生成静态页面代码
                $successData = [
                    'aid' => $aid,
                    'tid' => $post['typeid'],
                ];
                $this->success("操作成功!", null, $successData);
            }
            $this->error("操作失败!");
        }

        $typeid = input('param.typeid/d', 0);
        $assign_data['typeid'] = $typeid; // 栏目ID

        // 栏目信息
        $arctypeInfo = Db::name('arctype')->find($typeid);

        // 允许发布文档列表的栏目
        $assign_data['arctype_html'] = allow_release_arctype($typeid, array($this->channeltype));

        // 可控制的字段列表
        $assign_data['ifcontrolRow'] = Db::name('channelfield')->field('id,name')->where([
            'channel_id' => $this->channeltype,
            'ifmain'     => 1,
            'ifeditable' => 1,
            'ifcontrol'  => 0,
            'status'     => 1,
        ])->getAllWithIndex('name');

        // 阅读权限
        $assign_data['arcrank_list'] = get_arcrank_list();

        // 产品参数
        $assign_data['canshu'] = $this->ajax_get_attr_input($typeid);

        // 模板列表
        $archivesLogic = new \app\admin\logic\ArchivesLogic;
        $assign_data['templateList'] = $archivesLogic->getTemplateList($this->nid);

        // 默认模板文件
        $tempview = 'view_' . $this->nid . '.' . config('template.view_suffix');
        !empty($arctypeInfo['tempview']) && $tempview = $arctypeInfo['tempview'];
        $assign_data['tempview'] = $tempview;

        // 商城配置
        $shopConfig = getUsersConfigData('shop');
        $assign_data['shopConfig'] = $shopConfig;

        // 商品规格
        if (isset($shopConfig['shop_open_spec']) && 1 == $shopConfig['shop_open_spec']) {
            // 清除处理表的aid
            session('handleAID', 0);
            // 删除商品添加时产生的废弃规格
            $del_spec = session('del_spec') ? session('del_spec') : [];
            if (!empty($del_spec)) {
                $del_spec = array_unique($del_spec);
                $where = [
                    'spec_mark_id' => ['IN', $del_spec]
                ];
                Db::name('product_spec_data_handle')->where($where)->delete(true);
                // 清除 session
                session('del_spec', null);
            }
            // 预设值名称
            $assign_data['preset_value'] = Db::name('product_spec_preset')->where('lang', $this->admin_lang)->field('preset_id, preset_mark_id, preset_name')->group('preset_mark_id')->order('preset_mark_id desc')->select();
            // 读取规格预设库最大参数标记ID
            $maxPresetMarkID = $assign_data['preset_value'][0]['preset_mark_id'];
            $assign_data['maxPresetMarkID'] = $maxPresetMarkID + 1;
        }

        /*产品参数 - 新旧兼容*/
        $is_old_product_attr = tpSetting('system.system_old_product_attr', [], 'cn');
        if (!empty($is_old_product_attr)) { // 旧产品参数
            $assign_data['canshu'] = $this->ajax_get_attr_input($typeid);
        } else { // 新产品参数
            $where = [
                'lang'  => $this->admin_lang,
                'status' => 1,
                'is_del' => 0,
            ];
            $assign_data['AttrList'] = Db::name('shop_product_attrlist')->where($where)->order('sort_order asc, list_id asc')->select();
        }
        $assign_data['is_old_product_attr'] = $is_old_product_attr;
        /*--end*/

        // 最大参数属性ID值 +1
        $maxAttrID = Db::name('shop_product_attribute')->max('attr_id');
        $assign_data['maxAttrID'] = ++$maxAttrID;

        // 文档默认浏览量
        $globalConfig = tpCache('global');
        if (isset($globalConfig['other_arcclick']) && 0 <= $globalConfig['other_arcclick']) {
            $arcclick_arr = explode("|", $globalConfig['other_arcclick']);
            if (count($arcclick_arr) > 1) {
                $assign_data['rand_arcclick'] = mt_rand($arcclick_arr[0], $arcclick_arr[1]);
            } else {
                $assign_data['rand_arcclick'] = intval($arcclick_arr[0]);
            }
        }else{
            $arcclick_config['other_arcclick'] = '500|1000';
            tpCache('other', $arcclick_config);
            $assign_data['rand_arcclick'] = mt_rand(500, 1000);
        }

        // URL模式
        $tpcache = config('tpcache');
        $assign_data['seo_pseudo'] = !empty($tpcache['seo_pseudo']) ? $tpcache['seo_pseudo'] : 1;

        /*文档属性*/
        $assign_data['archives_flags'] = model('ArchivesFlag')->getList();
        $channelRow = Db::name('channeltype')->where('id', $this->channeltype)->find();
        $assign_data['channelRow'] = $channelRow;

        // 来源列表
        $system_originlist = tpSetting('system.system_originlist');
        $system_originlist = json_decode($system_originlist, true);
        $system_originlist = !empty($system_originlist) ? $system_originlist : [];
        $assign_data['system_originlist_0'] = !empty($system_originlist) ? $system_originlist[0] : "";
        $assign_data['system_originlist_str'] = implode(PHP_EOL, $system_originlist);

        // 多站点，当用站点域名访问后台，发布文档自动选择当前所属区域
        model('Citysite')->auto_location_select($assign_data);

        $this->assign($assign_data);

        return $this->fetch();
    }
    
    /**
     * 编辑
     */
    public function edit()
    {
        $admin_info = session('admin_info');
        $auth_role_info = $admin_info['auth_role_info'];
        $this->assign('auth_role_info', $auth_role_info);
        $this->assign('admin_info', $admin_info);

        if (IS_POST) {
            $post = input('post.');
            model('Archives')->editor_auto_210607($post);
            $post['aid'] = intval($post['aid']);

            /* 处理TAG标签 */
            if (!empty($post['tags_new'])) {
                $post['tags'] = !empty($post['tags']) ? $post['tags'] . ',' . $post['tags_new'] : $post['tags_new'];
                unset($post['tags_new']);
            }
            $post['tags'] = explode(',', $post['tags']);
            $post['tags'] = array_unique($post['tags']);
            $post['tags'] = implode(',', $post['tags']);
            /* END */

            $typeid = input('post.typeid/d', 0);
            $content = empty($post['addonFieldExt']['content']) ? '' : htmlspecialchars_decode($post['addonFieldExt']['content']);

            // 根据标题自动提取相关的关键字
            $seo_keywords = $post['seo_keywords'];
            if (!empty($seo_keywords)) {
                $seo_keywords = str_replace('，', ',', $seo_keywords);
            } else {
                // $seo_keywords = get_split_word($post['title'], $content);
            }

            // 自动获取内容第一张图片作为封面图
            $litpic = $post['litpic'];
            if (empty($litpic)) {
                $litpic = get_html_first_imgurl($content);
            }
            $post['litpic'] = $litpic;

            /*是否有封面图*/
            if (empty($post['litpic'])) {
                $is_litpic = 0; // 无封面图
            } else {
                $is_litpic = !empty($post['is_litpic']) ? $post['is_litpic'] : 0; // 有封面图
            }

            // 勾选后SEO描述将随正文内容更新
            $basic_update_seo_description = empty($post['basic_update_seo_description']) ? 0 : 1;
            if (is_language()) {
                $langRow = \think\Db::name('language')->order('id asc')
                    ->cache(true, EYOUCMS_CACHE_TIME, 'language')
                    ->select();
                foreach ($langRow as $key => $val) {
                    tpCache('basic', ['basic_update_seo_description'=>$basic_update_seo_description], $val['mark']);
                }
            } else {
                tpCache('basic', ['basic_update_seo_description'=>$basic_update_seo_description]);
            }
            /*--end*/

            // SEO描述
            $seo_description = '';
            if (!empty($basic_update_seo_description) || empty($post['seo_description'])) {
                $seo_description = @msubstr(checkStrHtml($content), 0, get_seo_description_length(), false);
            } else {
                $seo_description = $post['seo_description'];
            }

            // --外部链接
            $jumplinks = '';
            $is_jump = isset($post['is_jump']) ? $post['is_jump'] : 0;
            if (intval($is_jump) > 0) {
                $jumplinks = $post['jumplinks'];
            }

            // 模板文件，如果文档模板名与栏目指定的一致，默认就为空。让它跟随栏目的指定而变
            if ($post['type_tempview'] == $post['tempview']) {
                unset($post['type_tempview']);
                unset($post['tempview']);
            }

            // 产品类型
            if (!empty($post['prom_type']) && !in_array($post['prom_type'],[0,4])) {
                if ($post['prom_type_vir'] == 2) {
                    $post['netdisk_url'] = trim($post['netdisk_url']);
                    if (empty($post['netdisk_url'])) {
                        $this->error("网盘地址不能为空！");
                    }
                    $post['prom_type'] = 2;
                } else if ($post['prom_type_vir'] == 3) {
                    $post['text_content'] = trim($post['text_content']);
                    if (empty($post['text_content'])) {
                        $this->error("虚拟文本内容不能为空！");
                    }
                    $post['prom_type'] = 3;
                }
            }

            //处理自定义文件名,仅由字母数字下划线和短横杆组成,大写强制转换为小写
            $htmlfilename = trim($post['htmlfilename']);
            if (!empty($htmlfilename)) {
                $htmlfilename = preg_replace("/[^\x{4e00}-\x{9fa5}\w\-]+/u", "-", $htmlfilename);
                // $htmlfilename = strtolower($htmlfilename);
                //判断是否存在相同的自定义文件名
                $map = [
                    'aid'   => ['NEQ', $post['aid']],
                    'htmlfilename'  => $htmlfilename,
                    'lang'  => $this->admin_lang,
                ];
                if (!empty($post['typeid'])) {
                    $map['typeid'] = array('eq', $post['typeid']);
                }
                $filenameCount = Db::name('archives')->where($map)->count();
                if (!empty($filenameCount)) {
                    $this->error("同栏目下，自定义文件名已存在！");
                } else if (preg_match('/^(\d+)$/i', $htmlfilename)) {
                    $this->error("自定义文件名不能纯数字，会与文档ID冲突！");
                }
            } else {
                // 处理外贸链接
                if (is_dir('./weapp/Waimao/')) {
                    $waimaoLogic = new \weapp\Waimao\logic\WaimaoLogic;
                    $waimaoLogic->get_new_htmlfilename($htmlfilename, $post, 'edit', $this->globalConfig);
                } else {
                    $foreignLogic = new \app\admin\logic\ForeignLogic;
                    $foreignLogic->get_new_htmlfilename($htmlfilename, $post, 'edit', $this->globalConfig);
                }
            }
            $post['htmlfilename'] = $htmlfilename;

            // 同步栏目切换模型之后的文档模型
            $channel = Db::name('arctype')->where(['id'=>$typeid])->getField('current_channel');

            //做未通过审核文档不允许修改文档状态操作
            if ($admin_info['role_id'] > 0 && $auth_role_info['check_oneself'] < 1) {
                $old_archives_arcrank = Db::name('archives')->where(['aid' => $post['aid']])->getField("arcrank");
                if ($old_archives_arcrank < 0) {
                    unset($post['arcrank']);
                }
            }

            // 副栏目
            if (isset($post['stypeid'])) {
                $post['stypeid'] = preg_replace('/([^\d\,\，]+)/i', ',', $post['stypeid']);
                $post['stypeid'] = str_replace('，', ',', $post['stypeid']);
                $post['stypeid'] = trim($post['stypeid'], ',');
                $post['stypeid'] = str_replace(",{$typeid},", ',', ",{$post['stypeid']},");
                $post['stypeid'] = trim($post['stypeid'], ',');
            }

            // 虚拟销量和总虚拟销量
            $post['virtual_sales'] = empty($post['virtual_sales']) ? 0 : intval($post['virtual_sales']);
            if (!empty($post['spec_type']) && $post['spec_type'] == 2) { // 多规格
                $sales_all = 0;
                $post['virtual_sales'] = 0; // 多规格不加上虚拟销量
                foreach ($post['spec_sales'] as $key => $val) {
                    $sales_all += intval($val['spec_sales_num']); // + $post['virtual_sales'];
                }
            } else { // 单规格
                $sales_all = $post['virtual_sales'];
            }
            
            // --存储数据
            $newData = array(
                'typeid'=> $typeid,
                'channel'   => $channel,
                'is_b'      => empty($post['is_b']) ? 0 : $post['is_b'],
                'is_head'      => empty($post['is_head']) ? 0 : $post['is_head'],
                'is_special'      => empty($post['is_special']) ? 0 : $post['is_special'],
                'is_recom'      => empty($post['is_recom']) ? 0 : $post['is_recom'],
                'is_roll'      => empty($post['is_roll']) ? 0 : $post['is_roll'],
                'is_slide'      => empty($post['is_slide']) ? 0 : $post['is_slide'],
                'is_diyattr'      => empty($post['is_diyattr']) ? 0 : $post['is_diyattr'],
                'editor_remote_img_local'=> empty($post['editor_remote_img_local']) ? 0 : $post['editor_remote_img_local'],
                'editor_img_clear_link'  => empty($post['editor_img_clear_link']) ? 0 : $post['editor_img_clear_link'],
                'is_jump'   => $is_jump,
                'is_litpic'     => $is_litpic,
                'jumplinks' => $jumplinks,
                'seo_keywords'     => $seo_keywords,
                'seo_description'     => $seo_description,
                'sales_all'    => $sales_all,
                'stock_show'    => empty($post['stock_show']) ? 0 : $post['stock_show'],
                'crossed_price'     => empty($post['crossed_price']) ? 0 : floatval($post['crossed_price']),
                'users_price'      => empty($post['users_price']) ? 0 : floatval($post['users_price']),
                'old_price'      => empty($post['old_price']) ? 0 : floatval($post['old_price']),
                'add_time'     => strtotime($post['add_time']),
                'update_time'     => getTime(),
            );
            $data = array_merge($post, $newData);
            // if (!empty($post['param_type']) && 2 == $post['param_type']) {
            //     $data['attrlist_id'] = 0;
            // }
            // 更新商品信息
            $where = [
                'aid'  => $data['aid'],
                'lang' => $this->admin_lang,
            ];
            if (2 === intval($post['spec_type'])) {
                $data['stock_show'] = 1;
                $data['users_discount_type'] = 0;
            }
            $result = Db::name('archives')->where($where)->update($data);
            if ($result !== false) {
                // 单规格 且 选择指定会员级别 则 执行
                if (1 === intval($post['spec_type']) && 1 === intval($post['users_discount_type'])) {
                    model('ShopPublicHandle')->saveUsersDiscountPriceList($post['users_discount'], $data['aid']);
                }
                
                // ---------后置操作
                $newAttr = !empty($post['is_old_product_attr']) ? false : true;
                model('Product')->afterSave($data['aid'], $data, 'edit', $newAttr);

                //虚拟商品保存
                if (!empty($post['prom_type']) && in_array($post['prom_type'], [2,3])) {
                    model('ProductNetdisk')->saveProductNetdisk($data['aid'], $data);
                }

                // 若选择单规格则清理多规格数据
                if (!empty($post['spec_type']) && 1 == $post['spec_type']) {
                    // 产品规格数据表
                    Db::name("product_spec_data")->where('aid', $data['aid'])->delete();
                    // 产品多规格组装表
                    Db::name("product_spec_value")->where('aid', $data['aid'])->delete();
                    // 产品规格数据处理表
                    Db::name("product_spec_data_handle")->where('aid', $data['aid'])->delete();
                }
                // 若选择多规格选项，则添加产品规格
                else if (!empty($post['spec_type']) && 2 == $post['spec_type']) {
                    // 更新规格名称数据
                    model('ProductSpecData')->ProducSpecNameEditSave($data);
                    // 更新规格值及金额数据
                    model('ProductSpecValue')->ProducSpecValueEditSave($data);
                }

                // 若选择自定义参数则执行
                if (!empty($post['attr_name']) && !empty($post['attr_value'])) {
                    // 新增商品参数
                    $attrName = !empty($post['attr_name']) ? $post['attr_name'] : [];
                    $attrValue = !empty($post['attr_value']) ? $post['attr_value'] : [];
                    $sortOrder = !empty($post['sort_order']) ? $post['sort_order'] : 100;
                    $productAttribute = $productAttr = [];
                    $time = getTime();
                    foreach ($attrName as $key => $value) {
                        if (!empty($value)) {
                            $productAttribute = [
                                'aid' => $post['aid'],
                                'attr_name' => trim($value),
                                'attr_values' => '',
                                'sort_order' => 100,//intval($sortOrder[$key]),
                                'lang' => $this->admin_lang,
                                'is_custom' => 1,
                                'add_time' => $time,
                                'update_time' => $time,
                            ];
                            $attrID = Db::name('shop_product_attribute')->insertGetId($productAttribute);
                            if (!empty($attrValue[$key])) {
                                $productAttr = [
                                    'aid' => $post['aid'],
                                    'attr_id' => $attrID,
                                    'attr_value' => $attrValue[$key],
                                    'is_custom' => 1,
                                    'sort_order' => intval($sortOrder[$key]),
                                    'add_time' => $time,
                                    'update_time' => $time,
                                ];
                                Db::name('shop_product_attr')->insertGetId($productAttr);
                            }
                        }
                    }
                }
                // 删除指定的商品参数
                if (!empty($post['del_attr_id'])) {
                    $delAttrID = explode(',', $post['del_attr_id']);
                    $where = [
                        'is_custom' => 1,
                        'attr_id' => ['IN', $delAttrID]
                    ];
                    Db::name('shop_product_attr')->where($where)->delete(true);
                    Db::name('shop_product_attribute')->where($where)->delete(true);
                }

                adminLog('编辑产品：' . $data['title']);

                // 系统商品操作时，积分商品的被动处理
                model('ShopPublicHandle')->pointsGoodsPassiveHandle([$data['aid']]);

                $_POST['aid'] = $data['aid'];
                // 生成静态页面代码
                $successData = [
                    'aid' => $data['aid'],
                    'tid' => $typeid,
                ];
                $this->success("操作成功!", null, $successData);
            }
            $this->error("操作失败!");
        }

        $assign_data = array();

        $id = input('id/d', 0);
        $info = model('Product')->getInfo($id);
        if (empty($info)) $this->error('数据不存在，请联系管理员！');

        // 获取规格数据信息
        // 包含：SpecSelectName、HtmlTable、spec_mark_id_arr、preset_value
        $assign_data = model('ProductSpecData')->GetProductSpecData($id);
        // 兼容采集没有归属栏目的文档
        if (empty($info['channel'])) {
            $channelRow = Db::name('channeltype')->field('id as channel')->where('id', $this->channeltype)->find();
            $info = array_merge($info, $channelRow);
        }

        // 栏目ID及栏目信息
        $typeid = $info['typeid'];
        $assign_data['typeid'] = $typeid;
        
        // 副栏目
        $stypeid_arr = [];
        if (!empty($info['stypeid'])) {
            $info['stypeid'] = trim($info['stypeid'], ',');
            $stypeid_arr = Db::name('arctype')->field('id,typename')->where(['id'=>['IN', $info['stypeid']],'is_del'=>0])->select();
        }
        $assign_data['stypeid_arr'] = $stypeid_arr;

        // 栏目信息
        $arctypeInfo = Db::name('arctype')->find($typeid);

        $info['channel'] = $arctypeInfo['current_channel'];
        // 封面图
        $info['litpic'] = handle_subdir_pic($info['litpic']);
    
        // SEO描述
        // if (!empty($info['seo_description'])) {
        //     $info['seo_description'] = @msubstr(checkStrHtml($info['seo_description']), 0, get_seo_description_length(), false);
        // }

        $assign_data['field'] = $info;
        // 产品相册
        $proimg_list = model('ProductImg')->getProImg($id);
        foreach ($proimg_list as $key => $val) {
            $proimg_list[$key]['image_url'] = handle_subdir_pic($val['image_url']); // 支持子目录
        }
        $assign_data['proimg_list'] = $proimg_list;

        // 允许发布文档列表的栏目，文档所在模型以栏目所在模型为主，兼容切换模型之后的数据编辑
        $assign_data['arctype_html'] = allow_release_arctype($typeid, array($info['channel']));

        // 可控制的主表字段列表
        $assign_data['ifcontrolRow'] = Db::name('channelfield')->field('id,name')->where([
                'channel_id'    => $this->channeltype,
                'ifmain'        => 1,
                'ifeditable'    => 1,
                'ifcontrol'     => 0,
                'status'        => 1,
            ])->getAllWithIndex('name');

        // 虚拟商品内容读取
        $assign_data['netdisk'] = Db::name("product_netdisk")->where('aid', $id)->find();

        // 阅读权限
        $assign_data['arcrank_list'] = get_arcrank_list();

        // 模板列表
        $archivesLogic = new \app\admin\logic\ArchivesLogic;
        $templateList  = $archivesLogic->getTemplateList($this->nid);
        $assign_data['templateList'] = $templateList;

        // 默认模板文件
        $tempview = $info['tempview'];
        empty($tempview) && $tempview = $arctypeInfo['tempview'];
        $assign_data['tempview'] = $tempview;

        // 商城配置
        $shopConfig = getUsersConfigData('shop');
        $assign_data['shopConfig'] = $shopConfig;

        // 产品参数 - 新旧兼容
        $is_old_product_attr = tpSetting('system.system_old_product_attr', [], 'cn');
        // 旧产品参数
        if (!empty($is_old_product_attr)) {
            $assign_data['canshu'] = $this->ajax_get_attr_input($typeid, $id);
        }
        // 新产品参数
        else {
            // 商品参数列表
            $where = [
                'lang'  => $this->admin_lang,
                'status' => 1,
                'is_del' => 0,
            ];
            $assign_data['AttrList'] = Db::name('shop_product_attrlist')->where($where)->order('sort_order asc, list_id asc')->select();

            // 商品参数值
            $assign_data['canshu'] = $assign_data['customParam'] = '';
            if (!empty($info['attrlist_id'])) {
                $assign_data['canshu'] = $this->ajax_get_shop_attr_input($typeid, $id, $info['attrlist_id']);
            }
        }
        $assign_data['is_old_product_attr'] = $is_old_product_attr;

        // 自定义参数
        $where = [
            'a.is_custom' => 1,
            'b.is_custom' => 1,
            'a.aid' => $info['aid'],
        ];
        $field = 'a.*, b.attr_name';
        $order = 'a.sort_order asc, b.attr_id sac, a.product_attr_id asc';
        $productAttr = Db::name('shop_product_attr')
            ->alias('a')
            ->where($where)
            ->field($field)
            ->order($order)
            ->join('__SHOP_PRODUCT_ATTRIBUTE__ b', 'a.attr_id = b.attr_id', 'LEFT')
            ->select();

        $assign_data['customParam'] = $productAttr;
        $delAttrID = get_arr_column($productAttr, 'attr_id');
        $assign_data['delAttrID'] = !empty($delAttrID) ? implode(',', $delAttrID) : '';

        // 最大参数属性ID值 +1
        $maxAttrID = Db::name('shop_product_attribute')->max('attr_id');
        $maxAttrID++;
        $assign_data['maxAttrID'] = $maxAttrID;

        // URL模式
        $tpcache = config('tpcache');
        $assign_data['seo_pseudo'] = !empty($tpcache['seo_pseudo']) ? $tpcache['seo_pseudo'] : 1;

        /*文档属性*/
        $assign_data['archives_flags'] = model('ArchivesFlag')->getList();
        $channelRow = Db::name('channeltype')->where('id', $this->channeltype)->find();
        $assign_data['channelRow'] = $channelRow;

        // 来源列表
        $system_originlist = tpSetting('system.system_originlist');
        $system_originlist = json_decode($system_originlist, true);
        $system_originlist = !empty($system_originlist) ? $system_originlist : [];
        $assign_data['system_originlist_str'] = implode(PHP_EOL, $system_originlist);

        $this->assign($assign_data);

        return $this->fetch();
    }
    
    /**
     * 删除
     */
    public function del()
    {
        if (IS_POST) {
            $archivesLogic = new \app\admin\logic\ArchivesLogic;
            $archivesLogic->del([], 0, 'product');
        }
    }

    /**
     * 删除商品相册图
     */
    public function del_proimg()
    {
        if (IS_POST) {
            $filename= input('filename/s');
            $aid = input('aid/d');
            if (!empty($filename) && !empty($aid)) {
                Db::name('product_img')->where('image_url','like','%'.$filename)->where('aid',$aid)->delete();
            }

        }
    }

    //产品参数
    public function attribute_index()
    {
        $assign_data = array();
        $condition = array();
        $get = input('get.');
        $typeid = input('typeid/d', 0);
        foreach (['keywords','typeid'] as $key) {
            if (isset($get[$key]) && $get[$key] !== '') {
                if ($key == 'keywords') {
                    $condition['a.attr_name'] = array('LIKE', "%{$get[$key]}%");
                } else if ($key == 'typeid') {
                    $typeids = model('Arctype')->getHasChildren($get[$key]);
                    $condition['a.typeid'] = array('IN', array_keys($typeids));
                } else {
                    $condition['a.'.$key] = array('eq', $get[$key]);
                }
            }
        }

        $condition['a.is_del'] = 0;
        $condition['a.lang'] = $this->admin_lang;

        $count = Db::name('product_attribute')->alias('a')->where($condition)->count();
        $Page = new Page($count, config('paginate.list_rows'));
        $list = Db::name('product_attribute')
            ->field("a.attr_id")
            ->alias('a')
            ->where($condition)
            ->order('a.sort_order asc, a.attr_id asc')
            ->limit($Page->firstRow.','.$Page->listRows)
            ->getAllWithIndex('attr_id');

        if ($list) {
            $attr_ids = array_keys($list);
            $fields = "b.*, a.*";
            $row = Db::name('product_attribute')
                ->field($fields)
                ->alias('a')
                ->join('__ARCTYPE__ b', 'a.typeid = b.id', 'LEFT')
                ->where('a.attr_id', 'in', $attr_ids)
                ->getAllWithIndex('attr_id');

            // $row = model('LanguageAttr')->getBindValue($row, 'product_attribute', $this->main_lang);//获取多语言关联绑定的值

            foreach ($row as $key => $val) {
                $val['fieldname'] = 'attr_'.$val['attr_id'];
                $row[$key] = $val;
            }
            foreach ($list as $key => $val) {
                $list[$key] = $row[$val['attr_id']];
            }
        }
        $show = $Page->show();
        $assign_data['page'] = $show;
        $assign_data['list'] = $list;
        $assign_data['pager'] = $Page;

        /*多语言模式下，参数ID显示主体语言的ID和属性title名称*/
        $main_attribute_list = [];
        if ($this->admin_lang != $this->main_lang && empty($this->globalConfig['language_split'])) {
            $attr_values = get_arr_column($list, 'attr_id');
            $languageAttrRow = Db::name('language_attr')->field('attr_name,attr_value')->where([
                    'attr_value'    => ['IN', $attr_values],
                    'attr_group'    => 'product_attribute',
                    'lang'          => $this->admin_lang,
                ])->getAllWithIndex('attr_value');
            $attr_ids = [];
            foreach ($languageAttrRow as $key => $val) {
                $tid_tmp = str_replace('attr_', '', $val['attr_name']);
                array_push($attr_ids, intval($tid_tmp));
            }
            $main_attributeRow = Db::name('product_attribute')->field("attr_id,attr_name as title,CONCAT('attr_', attr_id) AS attr_name")
                ->where([
                    'attr_id'    => ['IN', $attr_ids],
                    'lang'  => $this->main_lang,
                ])->getAllWithIndex('attr_name');
            foreach ($list as $key => $val) {
                $key_tmp = !empty($languageAttrRow[$val['attr_id']]['attr_name']) ? $languageAttrRow[$val['attr_id']]['attr_name'] : '';
                $main_attribute_list[$val['attr_id']] = [
                    'id'        => !empty($main_attributeRow[$key_tmp]['attr_id']) ? $main_attributeRow[$key_tmp]['attr_id'] : '',
                    'attr_name'  => !empty($main_attributeRow[$key_tmp]['title']) ? $main_attributeRow[$key_tmp]['title'] : '',
                ];
            }
        }
        $this->assign('main_attribute_list', $main_attribute_list);
        /*end*/

        // 获取当前模型栏目
        $selected = $typeid;
        $arctypeLogic = new ArctypeLogic();
        $map = array(
            'channeltype'   => $this->channeltype,
            'is_del'        => 0,
        );
        $arctype_max_level = intval(config('global.arctype_max_level'));
        $select_html = $arctypeLogic->arctype_list(0, $selected, true, $arctype_max_level, $map);
        $this->assign('select_html',$select_html);

        $assign_data['typeid'] = $typeid;
        // 当前栏目信息
        $arctype_info = array();
        if ($typeid > 0) {
            $arctype_info = Db::name('arctype')->field('typename')->find($typeid);
        }
        $assign_data['arctype_info'] = $arctype_info;
        $tab = input('param.tab/d', 3);//选项卡
        $assign_data['tab'] = $tab;
        $assign_data['attrInputTypeArr'] = $this->attrInputTypeArr; // 表单类型
        $this->assign($assign_data);
        return $this->fetch();
    }

    /**
     * 新增产品参数
     */
    public function attribute_add()
    {
        //防止php超时
        function_exists('set_time_limit') && set_time_limit(0);
        
        if(IS_AJAX && IS_POST)//ajax提交验证
        {
            $model = model('ProductAttribute');

            $attr_values = str_replace('_', '', input('attr_values')); // 替换特殊字符
            $attr_values = str_replace('@', '', $attr_values); // 替换特殊字符            
            $attr_values = trim($attr_values);
            
            $post_data = input('post.');
            $post_data['attr_values'] = $attr_values;

            $savedata = array(
                'attr_name' => $post_data['attr_name'],
                'typeid'    => $post_data['typeid'],
                'attr_input_type'   => isset($post_data['attr_input_type']) ? $post_data['attr_input_type'] : '',
                'attr_values'   => isset($post_data['attr_values']) ? $post_data['attr_values'] : '',
                'sort_order'    => $post_data['sort_order'],
                'lang'  => $this->admin_lang,
                'add_time'  => getTime(),
                'update_time'   => getTime(),
            );

            // 数据验证            
            $validate = \think\Loader::validate('ProductAttribute');
            if(!$validate->batch()->check($savedata))
            {
                $error = $validate->getError();
                $error_msg = array_values($error);
                $return_arr = array(
                    'status' => -1,
                    'msg' => $error_msg[0],
                    'data' => $error,
                );
                respose($return_arr);
            } else {
                $model->data($savedata,true); // 收集数据
                $model->save(); // 写入数据到数据库
                $insertId = $model->getLastInsID();

                /*同步产品属性ID到多语言的模板变量里*/
                $this->syn_add_language_attribute($insertId);
                /*--end*/

                $return_arr = array(
                     'status' => 1,
                     'msg'   => '操作成功',                        
                     'data'  => array('url'=>url('Product/attribute_index', array('typeid'=>$post_data['typeid']))),
                );
                adminLog('新增产品参数：'.$savedata['attr_name']);
                respose($return_arr);
            }  
        }

        $typeid = input('param.typeid/d', 0);
        $assign_data = array();

        /*允许发布文档列表的栏目*/
        $arctype_html = allow_release_arctype($typeid, array($this->channeltype));
        $assign_data['arctype_html'] = $arctype_html;
        /*--end*/

        $this->assign($assign_data);
        return $this->fetch();
    }

    /**
     * 编辑产品参数
     */
    public function attribute_edit()
    {
        if(IS_AJAX && IS_POST)//ajax提交验证
        {
            $model = model('ProductAttribute');

            $attr_values = str_replace('_', '', input('attr_values')); // 替换特殊字符
            $attr_values = str_replace('@', '', $attr_values); // 替换特殊字符            
            $attr_values = trim($attr_values);
            
            $post_data = input('post.');
            $post_data['attr_id'] = intval($post_data['attr_id']);
            $post_data['attr_values'] = $attr_values;

            $savedata = array(
                'attr_id'   => $post_data['attr_id'],
                'attr_name' => $post_data['attr_name'],
                'typeid'    => $post_data['typeid'],
                'attr_input_type'   => isset($post_data['attr_input_type']) ? $post_data['attr_input_type'] : '',
                'attr_values'   => isset($post_data['attr_values']) ? $post_data['attr_values'] : '',
                'sort_order'    => $post_data['sort_order'],
                'update_time'   => getTime(),
            );

            // 数据验证            
            $validate = \think\Loader::validate('ProductAttribute');
            if(!$validate->batch()->check($savedata))
            {
                $error = $validate->getError();
                $error_msg = array_values($error);
                $return_arr = array(
                    'status' => -1,
                    'msg' => $error_msg[0],
                    'data' => $error,
                );
                respose($return_arr);
            } else {
                $model->data($savedata,true); // 收集数据
                $model->isUpdate(true, [
                        'attr_id'   => $post_data['attr_id'],
                        'lang'  => $this->admin_lang,
                    ])->save(); // 写入数据到数据库     
                $return_arr = array(
                     'status' => 1,
                     'msg'   => '操作成功',                        
                     'data'  => array('url'=>url('Product/attribute_index', array('typeid'=>$post_data['typeid']))),
                );
                adminLog('编辑产品参数：'.$savedata['attr_name']);
                respose($return_arr);
            }  
        }  

        $assign_data = array();

        $id = input('id/d');
        /*获取多语言关联绑定的值*/
        $new_id = model('LanguageAttr')->getBindValue($id, 'product_attribute'); // 多语言
        !empty($new_id) && $id = $new_id;
        /*--end*/
        $info = Db::name('ProductAttribute')->where([
                'attr_id'    => $id,
                'lang'  => $this->admin_lang,
            ])->find();
        if (empty($info)) {
            $this->error('数据不存在，请联系管理员！');
            exit;
        }
        $assign_data['field'] = $info;

        /*允许发布文档列表的栏目*/
        $arctype_html = allow_release_arctype($info['typeid'], array($this->channeltype));
        $assign_data['arctype_html'] = $arctype_html;
        /*--end*/

        $this->assign($assign_data);
        return $this->fetch();
    }
    
    /**
     * 删除产品参数
     */
    public function attribute_del()
    {
        $id_arr = input('del_id/a');
        $thorough = input('thorough/d');
        $id_arr = eyIntval($id_arr);
        if(!empty($id_arr)){
            // 多语言
            if (is_language()) {
                $attr_name_arr = [];
                foreach ($id_arr as $key => $val) {
                    $attr_name_arr[] = 'attr_'.$val;
                }
                $new_id_arr = Db::name('language_attr')->where([
                        'attr_name' => ['IN', $attr_name_arr],
                        'attr_group'    => 'product_attribute',
                    ])->column('attr_value');
                !empty($new_id_arr) && $id_arr = $new_id_arr;
            }
            if (1 == $thorough){//彻底删除
                $r = Db::name('ProductAttribute')->where([
                    'attr_id'   => ['IN', $id_arr],
                ])->delete();
            }else{
                $r = Db::name('ProductAttribute')->where([
                    'attr_id'   => ['IN', $id_arr],
                ])->update([
                    'is_del'    => 1,
                    'update_time'   => getTime(),
                ]);
            }
            if($r){
                adminLog('删除产品参数-id：'.implode(',', $id_arr));
                $this->success('删除成功');
            }else{
                $this->error('删除失败');
            }
        }else{
            $this->error('参数有误');
        }
    }

    /**
     * 动态获取产品参数输入框 根据不同的数据返回不同的输入框类型
     */
    public function ajax_get_attr_input($typeid = '', $aid = '')
    {
        $productLogic = new ProductLogic();
        $str = $productLogic->getAttrInput($aid, $typeid);
        if (empty($str)) {
            $str = '<div style="font-size: 12px;text-align: center;">提示：该主栏目还没有参数值，若有需要先确认提交，再点击【<a href="javascript:void(0);" data-href="'.url('Product/attribute_index', array('typeid'=>$typeid)).'" onclick="openFullframe(this, \'产品参数\', \'100%\', \'100%\');">产品参数</a>】进行更多操作。</div>';
        }

        if (IS_AJAX) {
            exit($str);
        } else {
            return $str;
        }
    }

    /**
     * 动态获取商品参数输入框 根据不同的数据返回不同的输入框类型
     */
    public function ajax_get_shop_attr_input($typeid = '', $aid = '', $list_id = '')
    {
        $typeid = intval($typeid);
        $aid = intval($aid);
        $list_id = intval($list_id);
        $productLogic = new ProductLogic();
        $str = $productLogic->getShopAttrInput($aid, $typeid, $list_id);
        if (empty($str)) {
            $str = '<div style="font-size: 12px;text-align: center;">提示：该参数还没有参数值，若有需要请点击【<a href="'.url('Product/attribute_index', array('list_id'=>$list_id)).'">商品参数</a>】进行更多操作。</div>';
        }
        if (IS_AJAX) {
            exit($str);
        } else {
            return $str;
        }
    }

    /**
     * 获取新版产品参数分组
     */
    public function ajax_get_shop_attrlist()
    {
        $list_id = input('param.list_id/d');
        $html = "";
        $where = [
            'lang'  => $this->admin_lang,
            'status' => 1,
            'is_del' => 0,
        ];
        $AttrList = Db::name('shop_product_attrlist')->where($where)->order('sort_order asc, list_id asc')->select();
        foreach ($AttrList as $key => $val) {
            $selected = ($list_id == $val['list_id']) ? " selected='true' " : '';
            $html .= "<option value='{$val['list_id']}' {$selected}>{$val['list_name']}</option>";
        }
        exit($html);
    }

    /**
     * 同步新增产品属性ID到多语言的模板变量里
     */
    private function syn_add_language_attribute($attr_id)
    {
        /*单语言情况下不执行多语言代码*/
        if (!is_language()) {
            return true;
        }
        /*--end*/
        
        $attr_group = 'product_attribute';
        $admin_lang = $this->admin_lang;
        $main_lang = $this->main_lang;
        $languageRow = Db::name('language')->field('mark')->order('id asc')->select();
        if (!empty($languageRow) && $admin_lang == $main_lang) { // 当前语言是主体语言，即语言列表最早新增的语言
            $result = Db::name('product_attribute')->find($attr_id);
            $attr_name = 'attr_'.$attr_id;
            $r = Db::name('language_attribute')->save([
                'attr_title'    => $result['attr_name'],
                'attr_name'     => $attr_name,
                'attr_group'    => $attr_group,
                'add_time'      => getTime(),
                'update_time'   => getTime(),
            ]);
            if (false !== $r) {
                $data = [];
                foreach ($languageRow as $key => $val) {
                    /*同步新产品属性到其他语言产品属性列表*/
                    if ($val['mark'] != $admin_lang) {
                        $addsaveData = $result;
                        $addsaveData['lang'] = $val['mark'];
                        $newTypeid = Db::name('language_attr')->where([
                                'attr_name' => 'tid'.$result['typeid'],
                                'attr_group'    => 'arctype',
                                'lang'  => $val['mark'],
                            ])->getField('attr_value');
                        $addsaveData['typeid'] = $newTypeid;
                        unset($addsaveData['attr_id']);
                        $attr_id = Db::name('product_attribute')->insertGetId($addsaveData);
                    }
                    /*--end*/
                    
                    /*所有语言绑定在主语言的ID容器里*/
                    $data[] = [
                        'attr_name' => $attr_name,
                        'attr_value'    => $attr_id,
                        'lang'  => $val['mark'],
                        'attr_group'    => $attr_group,
                        'add_time'      => getTime(),
                        'update_time'   => getTime(),
                    ];
                    /*--end*/
                }
                if (!empty($data)) {
                    model('LanguageAttr')->saveAll($data);
                }
            }
        }
    }
    //帮助
    public function help()
    {
        $system_originlist = tpSetting('system.system_originlist');
        $system_originlist = json_decode($system_originlist, true);
        $system_originlist = !empty($system_originlist) ? $system_originlist : [];
        $assign_data['system_originlist_str'] = implode(PHP_EOL, $system_originlist);
        $this->assign($assign_data);
    
        return $this->fetch();
    }
}