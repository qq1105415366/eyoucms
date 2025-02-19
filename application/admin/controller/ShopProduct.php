<?php
/**
 * 易优CMS
 * ============================================================================
 * 版权所有 2016-2028 海口快推科技有限公司，并保留所有权利。
 * 网站地址: http://www.eyoucms.com
 * ----------------------------------------------------------------------------
 * 如果商业用途务必到官方购买正版授权, 以免引起不必要的法律纠纷.
 * ============================================================================
 * Author: 易而优团队 by 小虎哥 <1105415366@qq.com>
 * Date: 2018-4-3
 */

namespace app\admin\controller;

use think\Page;
use think\Db;
use app\common\logic\ArctypeLogic;
use app\admin\logic\ProductLogic;
use app\admin\logic\ProductSpecLogic; // 用于商品规格逻辑功能处理
use app\admin\logic\ShopLogic;

class ShopProduct extends Base
{
    // 模型标识
    public $nid = 'product';
    // 模型ID
    public $channeltype = '';
    // 表单类型
    public $attrInputTypeArr = array();
    public $ShopLogic;

    public function _initialize()
    {
        parent::_initialize();
        $this->ShopLogic = new ShopLogic;

        if (!preg_match('/^attrlist_/i', ACTION_NAME) && !preg_match('/^attribute_/i', ACTION_NAME) && !preg_match('/^ajax_/i', ACTION_NAME)) {
            $this->language_access(); // 多语言功能操作权限
        }
        
        $channeltype_list  = config('global.channeltype_list');
        $this->channeltype = $channeltype_list[$this->nid];
        empty($this->channeltype) && $this->channeltype = 2;
        $this->attrInputTypeArr = config('global.attr_input_type_arr');
        $this->assign('nid', $this->nid);
        $this->assign('channeltype', $this->channeltype);

        // 商城产品参数表
        $this->shop_product_attrlist_db = Db::name('shop_product_attrlist');

        // 规格业务层
        $this->productSpecLogic = new ProductSpecLogic;
        
        // 列出营销功能里已使用的模块
        $marketFunc = $this->ShopLogic->marketLogic();
        $this->assign('marketFunc', $marketFunc);

        // 返回页面
        $this->callback_url = url('ShopProduct/index', ['lang' => $this->admin_lang]);
        $this->assign('callback_url', $this->callback_url);
    }

    /**
     * 商品列表
     */
    public function index()
    {
        $assign_data = [];
        $condition = [
            'a.merchant_id' => 0
        ];
        // 获取到所有GET参数
        $param = input('param.');
        $typeid = input('typeid/d', 0);

        // 搜索、筛选查询条件处理
        foreach (['keywords', 'typeid', 'flag', 'is_release','province_id','city_id','area_id','arcrank'] as $key) {
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
                } else if ($key == 'arcrank' && !empty($param[$key])) {
                    if (1 === intval($param[$key])) {
                        $condition['a.arcrank'] = ['egt', 0];
                    } else if (-1 === intval($param[$key])) {
                        $condition['a.arcrank'] = ['eq', -1];
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
                // 查询商品是否开启规格
                $goodsSpecArr = Db::name('product_spec_value')->field('aid, value_id')->where('aid', 'in', $aids)->select();
                $goodsSpecArr = group_same_key($goodsSpecArr, 'aid');
                // 处理商品信息
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
                    $row[$val['aid']]['litpic'] = handle_subdir_pic($row[$val['aid']]['litpic']);
                    $row[$val['aid']]['goodsSpec'] = $goodsSpecArr[$val['aid']] ? count($goodsSpecArr[$val['aid']]) : 0;
                    $list[$key] = $row[$val['aid']];
                }
            }
        }

        $assign_data['page'] = $Page->show();
        $assign_data['list'] = $list;
        $assign_data['pager'] = $Page;
        $assign_data['typeid'] = $typeid;
        $assign_data['tab'] = input('param.tab/d', 3);// 选项卡
        $assign_data['arctype_html'] = allow_release_arctype($typeid, array($this->channeltype)); // 允许发布文档列表的栏目
        $assign_data['arctype_info'] = $typeid > 0 ? Db::name('arctype')->field('typename')->find($typeid) : [];// 当前栏目信息
        // 是否存在产品模型的栏目
        $where = ['current_channel' => 2, 'is_del' => 0, 'lang' => get_current_lang()];
        $assign_data['is_product_arctype'] = Db::name('arctype')->where($where)->count();
        $this->assign($assign_data);

        // 返回页面
        $callback_url = url('ShopProduct/index', ['lang' => $this->admin_lang, 'typeid' => $typeid]);
        $this->assign('callback_url', $callback_url);
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
            if (empty($post['litpic'])) {
                $post['litpic'] = get_html_first_imgurl($content);
            }

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
            $is_jump   = isset($post['is_jump']) ? $post['is_jump'] : 0;
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
                'typeid'          => empty($post['typeid']) ? 0 : $post['typeid'],
                'channel'         => $this->channeltype,
                'is_b'            => empty($post['is_b']) ? 0 : $post['is_b'],
                'is_head'         => empty($post['is_head']) ? 0 : $post['is_head'],
                'is_special'      => empty($post['is_special']) ? 0 : $post['is_special'],
                'is_recom'        => empty($post['is_recom']) ? 0 : $post['is_recom'],
                'is_roll'         => empty($post['is_roll']) ? 0 : $post['is_roll'],
                'is_slide'        => empty($post['is_slide']) ? 0 : $post['is_slide'],
                'is_diyattr'      => empty($post['is_diyattr']) ? 0 : $post['is_diyattr'],
                'editor_remote_img_local'=> empty($post['editor_remote_img_local']) ? 0 : $post['editor_remote_img_local'],
                'editor_img_clear_link'  => empty($post['editor_img_clear_link']) ? 0 : $post['editor_img_clear_link'],
                'is_jump'         => $is_jump,
                'is_litpic'       => $is_litpic,
                'jumplinks'       => $jumplinks,
                'origin'      => empty($post['origin']) ? '网络' : $post['origin'],
                'seo_keywords'    => $seo_keywords,
                'seo_description' => $seo_description,
                'admin_id'        => session('admin_info.admin_id'),
                'sales_all'    => $sales_all,
                'stock_show'      => empty($post['stock_show']) ? 0 : $post['stock_show'],
                'crossed_price'     => empty($post['crossed_price']) ? 0 : floatval($post['crossed_price']),
                'users_price'      => empty($post['users_price']) ? 0 : floatval($post['users_price']),
                'old_price'      => empty($post['old_price']) ? 0 : floatval($post['old_price']),
                'lang'            => $this->admin_lang,
                'sort_order'      => 100,
                'add_time'        => getTime(),
                'update_time'     => getTime(),
            );
            $post['logistics_type'] = !empty($post['logistics_type']) && 0 === intval($post['prom_type']) ? implode(',', $post['logistics_type']) : 0;
            $data = array_merge($post, $newData);
            // if (!empty($post['param_type']) && 2 == $post['param_type']) {
            //     $data['attrlist_id'] = 0;
            // }
            if (2 === intval($post['spec_type'])) {
                $data['stock_show'] = 1;
                $data['users_discount_type'] = 0;
            }
            // dump($data);exit;
            $aid = Db::name('archives')->insertGetId($data);
            $_POST['aid'] = $aid;
            if (!empty($aid)) {
                // 单规格 且 选择指定会员级别 则 执行
                if (1 === intval($post['spec_type']) && 1 === intval($post['users_discount_type'])) {
                    model('ShopPublicHandle')->saveUsersDiscountPriceList($post['users_discount'], $aid);
                }

                // ---------后置操作
                model('Product')->afterSave($aid, $data, 'add', true);

                // 添加查询执行语句到mysql缓存表
                model('SqlCacheTable')->InsertSqlCacheTable();

                // 若选择多规格选项，则添加产品规格
                if (!empty($post['spec_type']) && 2 === intval($post['spec_type'])) {
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

                // 保存商品服务标签绑定
                if (!empty($post['goodsLabelID'])) model('ShopGoodsLabel')->saveGoodsLabelBind($aid, $post['goodsLabelID']);

                // 生成静态页面代码
                $successData = [
                    'aid' => $aid,
                    'tid' => $post['typeid'],
                ];
                $this->success("操作成功!", url('ShopProduct/index'), $successData);
            }
            $this->error("操作失败!");
        }

        $typeid = input('param.typeid/d', 0);
        $assign_data['typeid'] = $typeid; // 栏目ID

        $firstrun = input('param.firstrun/d', 0);
        if (empty($typeid) && !empty($firstrun)) {
            $typeid = Db::name('arctype')->where(['current_channel'=>2,'status'=>1,'is_del'=>0,'lang'=>$this->admin_lang])->order('parent_id asc, sort_order asc, id asc')->value('id');
            $url = url('ShopProduct/add', ['typeid'=>$typeid]);
            $this->redirect($url);
            exit;
        }

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
        if (isset($shopConfig['shop_open_spec']) && 1 === intval($shopConfig['shop_open_spec'])) {
            // 删除商品添加时产生的废弃规格
            $del_spec = session('del_spec') ? session('del_spec') : [];
            if (!empty($del_spec)) {
                $del_spec = array_unique($del_spec);
                $where = [
                    'spec_mark_id' => ['IN', $del_spec]
                ];
                Db::name('product_spec_data_handle')->where($where)->delete(true);
                $where = [
                    'aid' => session('handleAID')
                ];
                Db::name('product_spec_value_handle')->where($where)->delete(true);
                // 清除 session
                session('del_spec', null);
            }
            // 清除处理表的aid
            session('handleAID', 0);
            // 预设值名称
            $assign_data['preset_value'] = Db::name('product_spec_preset')->where('lang', $this->admin_lang)->field('preset_id, preset_mark_id, preset_name')->group('preset_mark_id')->order('preset_mark_id desc')->select();
            // 读取规格预设库最大参数标记ID
            $maxPresetMarkID = $assign_data['preset_value'][0]['preset_mark_id'];
            $assign_data['maxPresetMarkID'] = $maxPresetMarkID + 1;
        }

        // 商品参数列表
        $where = [
            'status' => 1,
            'is_del' => 0,
            'lang' => $this->admin_lang,
        ];
        $assign_data['AttrList'] = $this->shop_product_attrlist_db->where($where)->order('sort_order asc, list_id asc')->select();

        // 最大参数属性ID值 +1
        $maxAttrID = Db::name('shop_product_attribute')->max('attr_id');
        $assign_data['maxAttrID'] = ++$maxAttrID;

        // URL模式
        $tpcache = config('tpcache');
        $assign_data['seo_pseudo'] = !empty($tpcache['seo_pseudo']) ? $tpcache['seo_pseudo'] : 1;

        // 文档默认浏览量
        $globalConfig = tpCache('global');
        if (isset($globalConfig['other_arcclick']) && 0 <= $globalConfig['other_arcclick']) {
            $arcclick_arr = explode("|", $globalConfig['other_arcclick']);
            if (count($arcclick_arr) > 1) {
                $assign_data['rand_arcclick'] = mt_rand($arcclick_arr[0], $arcclick_arr[1]);
            } else {
                $assign_data['rand_arcclick'] = intval($arcclick_arr[0]);
            }
        } else {
            $arcclick_config['other_arcclick'] = '500|1000';
            tpCache('other', $arcclick_config);
            $assign_data['rand_arcclick'] = mt_rand(500, 1000);
        }

        // 文档属性
        $assign_data['archives_flags'] = model('ArchivesFlag')->getList();
        $channelRow = Db::name('channeltype')->where('id', $this->channeltype)->find();
        $assign_data['channelRow'] = $channelRow;

        // 来源列表
        $system_originlist = tpSetting('system.system_originlist');
        $system_originlist = json_decode($system_originlist, true);
        $system_originlist = !empty($system_originlist) ? $system_originlist : [];
        $assign_data['system_originlist_str'] = implode(PHP_EOL, $system_originlist);
        $assign_data['system_originlist_0'] = !empty($system_originlist) ? $system_originlist[0] : "";
        // 多站点，当用站点域名访问后台，发布文档自动选择当前所属区域
        model('Citysite')->auto_location_select($assign_data);

        // 获取核销插件数据
        $assign_data['weappVerify'] = model('ShopPublicHandle')->getWeappVerifyInfo();

        // 商品服务标签
        $assign_data['goodsLabel'] = model('ShopGoodsLabel')->getGoodsLabelList();

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

            $typeid  = input('post.typeid/d', 0);
            $content = empty($post['addonFieldExt']['content']) ? '' : htmlspecialchars_decode($post['addonFieldExt']['content']);

            // 根据标题自动提取相关的关键字
            $seo_keywords = $post['seo_keywords'];
            if (!empty($seo_keywords)) {
                $seo_keywords = str_replace('，', ',', $seo_keywords);
            } else {
                // $seo_keywords = get_split_word($post['title'], $content);
            }

            // 自动获取内容第一张图片作为封面图
            if (empty($post['litpic'])) {
                $post['litpic'] = get_html_first_imgurl($content);
            }

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
            $is_jump   = isset($post['is_jump']) ? $post['is_jump'] : 0;
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
            $channel = Db::name('arctype')->where(['id' => $typeid])->getField('current_channel');

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
            if (!empty($post['spec_type']) && 2 === intval($post['spec_type'])) { // 多规格
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
                'typeid'          => $typeid,
                'channel'         => $channel,
                'is_b'            => empty($post['is_b']) ? 0 : $post['is_b'],
                'is_head'         => empty($post['is_head']) ? 0 : $post['is_head'],
                'is_special'      => empty($post['is_special']) ? 0 : $post['is_special'],
                'is_recom'        => empty($post['is_recom']) ? 0 : $post['is_recom'],
                'is_roll'         => empty($post['is_roll']) ? 0 : $post['is_roll'],
                'is_slide'        => empty($post['is_slide']) ? 0 : $post['is_slide'],
                'is_diyattr'      => empty($post['is_diyattr']) ? 0 : $post['is_diyattr'],
                'editor_remote_img_local'=> empty($post['editor_remote_img_local']) ? 0 : $post['editor_remote_img_local'],
                'editor_img_clear_link'  => empty($post['editor_img_clear_link']) ? 0 : $post['editor_img_clear_link'],
                'is_jump'         => $is_jump,
                'is_litpic'       => $is_litpic,
                'jumplinks'       => $jumplinks,
                'seo_keywords'    => $seo_keywords,
                'seo_description' => $seo_description,
                'sales_all'    => $sales_all,
                'stock_show'      => empty($post['stock_show']) ? 0 : $post['stock_show'],
                'crossed_price'     => empty($post['crossed_price']) ? 0 : floatval($post['crossed_price']),
                'users_price'      => empty($post['users_price']) ? 0 : floatval($post['users_price']),
                'old_price'      => empty($post['old_price']) ? 0 : floatval($post['old_price']),
                // 'add_time'        => strtotime($post['add_time']),
                'update_time'     => getTime(),
                'stock_code'     => empty($post['stock_code']) ? '' : $post['stock_code'],
            );
            $post['logistics_type'] = !empty($post['logistics_type']) && 0 === intval($post['prom_type']) ? implode(',', $post['logistics_type']) : 0;
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
                model('Product')->afterSave($data['aid'], $data, 'edit', true);

                // 虚拟商品保存
                if (!empty($post['prom_type']) && in_array($post['prom_type'], [2, 3])) {
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

                // 保存商品服务标签绑定
                if (empty($post['goodsLabelID'])) $post['goodsLabelID'] = [];
                model('ShopGoodsLabel')->saveGoodsLabelBind($data['aid'], $post['goodsLabelID']);

                $_POST['aid'] = $data['aid'];
                // 生成静态页面代码
                $successData = [
                    'aid' => $data['aid'],
                    'tid' => $typeid,
                ];
                $this->success("操作成功!", url('ShopProduct/index'), $successData);
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
        $arctypeInfo = Db::name('arctype')->find($typeid);
        $info['channel'] = $arctypeInfo['current_channel'];
        $info['litpic'] = handle_subdir_pic($info['litpic']);

        // 副栏目
        $stypeid_arr = [];
        if (!empty($info['stypeid'])) {
            $info['stypeid'] = trim($info['stypeid'], ',');
            $stypeid_arr = Db::name('arctype')->field('id,typename')->where(['id'=>['IN', $info['stypeid']],'is_del'=>0])->select();
        }
        $assign_data['stypeid_arr'] = $stypeid_arr;

        // SEO描述
        // if (!empty($info['seo_description'])) {
        //     $info['seo_description'] = @msubstr(checkStrHtml($info['seo_description']), 0, get_seo_description_length(), false);
        // }

        // 物流支持类型
        $info['logistics_type'] = isset($info['logistics_type']) ? explode(',', $info['logistics_type']) : [];
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
            'channel_id' => $this->channeltype,
            'ifmain'     => 1,
            'ifeditable' => 1,
            'ifcontrol'  => 0,
            'status'     => 1,
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


        // URL模式
        $tpcache = config('tpcache');
        $assign_data['seo_pseudo'] = !empty($tpcache['seo_pseudo']) ? $tpcache['seo_pseudo'] : 1;

        // 商品参数列表
        $where = [
            'status' => 1,
            'is_del' => 0,
            'lang' => $this->admin_lang,
        ];
        $assign_data['AttrList'] = $this->shop_product_attrlist_db->where($where)->order('sort_order asc, list_id asc')->select();

        // 商品参数值
        $assign_data['canshu'] = $assign_data['customParam'] = '';
        if (!empty($info['attrlist_id'])) {
            $assign_data['canshu'] = $this->ajax_get_shop_attr_input($typeid, $id, $info['attrlist_id']);
        }

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

        // 文档属性
        $assign_data['archives_flags'] = model('ArchivesFlag')->getList();
        $channelRow = Db::name('channeltype')->where('id', $this->channeltype)->find();
        $assign_data['channelRow'] = $channelRow;

        // 来源列表
        $system_originlist = tpSetting('system.system_originlist');
        $system_originlist = json_decode($system_originlist, true);
        $system_originlist = !empty($system_originlist) ? $system_originlist : [];
        $assign_data['system_originlist_str'] = implode(PHP_EOL, $system_originlist);

        // 获取核销插件数据
        $assign_data['weappVerify'] = model('ShopPublicHandle')->getWeappVerifyInfo();

        // 商品服务标签
        $assign_data['goodsLabel'] = model('ShopGoodsLabel')->getGoodsLabelList($info['aid']);

        // dump($assign_data);exit;
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

    public function goods_spec_detection()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');
            // 验证规格名
            $result = 0;
            foreach ($post['spec_mark_id'] as $key => $value) {
                if (empty($value['spec_name'])) $result = 1;
            }
            !empty($result) && $this->error('请完善规格名');

            // 验证规格值
            $result = 0;
            foreach ($post['spec_value_id'] as $key => $value) {
                if (empty($value['spec_value'])) $result = 1;
            }
            !empty($result) && $this->error('请完善规格值');

            // 验证规格价
            $result = 0;
            foreach ($post['spec_price'] as $key => $value) {
                if (empty($value['users_price']) || 0 >= floatval($value['users_price'])) $result = 1;
            }
            !empty($result) && $this->error('请完善规格价');
            $this->success('验证成功');
        }
    }

    public function goods_quick_edit()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');
            if (empty($post['aid']) || !isset($post['openSpec'])) $this->error('数据异常，刷新重试');
            if (1 == $post['openSpec']){
                $post['sales_all'] = 0;
                foreach ($post['spec_sales'] as $k => $v){
                    $post['sales_all'] += intval($v['spec_sales_num']);
                }
            }else{
                $post['sales_all'] = $post['virtual_sales'];
            }
            // 更新商品表数据
            $where = [
                'aid' => intval($post['aid']),
                'lang' => $this->admin_lang,
            ];
            $update = [
                'stock_count' => empty($post['stock_count']) ? 0 : intval($post['stock_count']),
                'users_price' => empty($post['users_price']) ? 0 : floatval($post['users_price']),
                'users_discount_type'  => empty($post['users_discount_type']) ? 0 : intval($post['users_discount_type']),
                'update_time' => getTime(),
            ];
            if (!empty($post['stock_show'])) $update['stock_show'] = intval($post['stock_show']);
            $update = array_merge($post, $update);
            $result = Db::name('archives')->where($where)->update($update);
            // 后续处理
            if (!empty($result)) {
                // 已开启规格的商品处理
                if (1 === intval($update['openSpec'])) {
                    // 更新规格值及金额数据
                    model('ProductSpecValue')->ProducSpecValueEditSave($update, 'edit');
                }
                // 未开启规格的商品处理
                else if (0 === intval($update['openSpec']) && 1 === intval($update['users_discount_type'])) {
                    // 选择指定会员级别执行
                    model('ShopPublicHandle')->saveUsersDiscountPriceList($update['users_discount'], $update['aid']);
                }
                // 系统商品操作时，积分商品的被动处理
                model('ShopPublicHandle')->pointsGoodsPassiveHandle([$update['aid']]);
                // 成功返回结束
                $this->success("操作成功");
            }
            // 失败返回结束
            $this->error("操作失败");
        }

        // 查询商品信息
        $aid = input('param.aid/d', 0);
        $where = [
            'aid' => intval($aid),
        ];
        $field = 'aid, title, users_price, stock_count, stock_show, virtual_sales, users_discount_type';
        $goods = Db::name('archives')->field($field)->where($where)->find();
        $this->assign('goods', $goods);

        // 查询商品的规格信息
        $where = [
            'aid' => intval($aid),
            'spec_is_select' => 1,
        ];
        $field = 'spec_mark_id, spec_value_id';
        // $order = 'spec_mark_id asc, spec_value_id asc, spec_id asc';
        $order = 'spec_value_id asc, spec_id asc';
        $spec = Db::name('product_spec_data')->where($where)->field($field)->order($order)->select();
        $openSpec = 0;
        $htmlTable = '';
        if (!empty($spec)) {
            $openSpec = 1;
            // 处理规格数组
            $specArray = [];
            foreach ($spec as $key => $value) {
                $specArray[$value['spec_mark_id']][] = $value['spec_value_id'];
            }
            $htmlTable = $this->productSpecLogic->SpecAssemblyEdit($specArray, $aid, true);
        }
        $this->assign('openSpec', $openSpec);
        $this->assign('htmlTable', $htmlTable);

        // 商城配置
        $this->assign('shopConfig', getUsersConfigData('shop'));

        return $this->fetch();
    }

    public function goodsSpecImage()
    {
        if (IS_AJAX_POST) {
            // 规格图片路径
            $aid = input('param.aid/d', 0);
            if (empty($aid)) $aid = session('handleAID');
            $action = input('param.action/s', '');
            $checked = input('param.checked/d', 0);
            $spec_image = input('param.spec_image/s', '');
            $spec_mark_id = input('param.spec_mark_id/d', 0);
            $spec_value_id = input('param.spec_value_id/d', 0);
            if ('open' == $action) {
                // 更新同一类所有规格值为开启规格图片
                $where = [
                    // 'spec_is_select' => 1,
                    'aid' => intval($aid),
                    'spec_mark_id' => intval($spec_mark_id),
                ];
                $update = [
                    'open_image' => intval($checked),
                    'update_time' => getTime(),
                ];
                $result = Db::name('product_spec_data_handle')->where($where)->update($update);
                if (!empty($result)) $this->success("操作成功");
            } else {
                $where = [
                    // 'spec_is_select' => 1,
                    'aid' => intval($aid),
                    'spec_mark_id' => intval($spec_mark_id),
                    'spec_value_id' => intval($spec_value_id),
                ];
                $update = [
                    'spec_image' => $spec_image,
                    'update_time' => getTime(),
                ];
                $result = Db::name('product_spec_data_handle')->where($where)->update($update);
                if (!empty($result)) $this->success("操作成功");
            }
        }
        $this->error("操作失败");
    }

    // 初始化规格信息
    public function initialization_spec()
    {
        if (IS_AJAX_POST) {
            $initialization = input('post.initialization');

            // 刷新或重新进入产品添加页则清除关于产品session
            if (!empty($initialization)) {
                // session('handleAID', 0);
                session('del_spec', null);
                session('spec_arr', null);
                $this->success('初始化完成');
            }
        }
    }

    // 添加商品自定义规格并返回规格表格
    public function add_product_custom_spec()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');

            // 添加自定义规格
            $resultArray = $this->productSpecLogic->addProductCustomSpec($post);
            if (!empty($resultArray['errorMsg'])) $this->error($resultArray['errorMsg']);

            // 获取已选规格进行HTML代码拼装
            if (!empty($post['aid'])) {
                $htmlTable = $this->productSpecLogic->SpecAssemblyEdit($resultArray['spec_array'], $post['aid']);
            } else {
                $htmlTable = $this->productSpecLogic->SpecAssemblyEdit($resultArray['spec_array']);
            }
            // 返回数据
            if (in_array($post['action'], ['specName', 'specValue'])) {
                $specData = $this->productSpecLogic->getProductSpecValueOption($resultArray['spec_mark_id'], $post);
            }
            $returnData = [
                'htmlTable' => !empty($htmlTable) ? $htmlTable : ' ',
                'spec_name' => !empty($specData['spec_name']) ? $specData['spec_name'] : '',
                'spec_value' => !empty($specData['spec_value']) ? $specData['spec_value'] : '',
                'spec_mark_id' => !empty($resultArray['spec_mark_id']) ? $resultArray['spec_mark_id'] : 0,
                'spec_value_id' => !empty($resultArray['spec_value_id']) ? $resultArray['spec_value_id'] : 0,
                'spec_value_option' => !empty($specData['spec_value_option']) ? $specData['spec_value_option'] : '',
                'spec_mark_id_arr' => !empty($resultArray['spec_mark_id_arr']) ? $resultArray['spec_mark_id_arr'] : 0,
                'preset_name_option' => !empty($specData['preset_name_option']) ? $specData['preset_name_option'] : '',
            ];
            
            $this->success('加载成功！', null, $returnData);
        }
    }

    // 添加自定义规格名称并返回规格表
    public function add_product_custom_spec_name()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');

            // 添加自定义规格名称
            $resultArray = $this->productSpecLogic->addProductCustomSpecName($post);
            if (!empty($resultArray['errorMsg'])) $this->error($resultArray['errorMsg']);

            // 获取已选规格进行HTML代码拼装
            if (!empty($post['aid'])) {
                $htmlTable = $this->productSpecLogic->SpecAssemblyEdit($resultArray, $post['aid']);
            } else {
                $htmlTable = $this->productSpecLogic->SpecAssemblyEdit($resultArray);
            }
            // 返回数据
            $returnData = [
                'htmlTable' => !empty($htmlTable) ? $htmlTable : ' ',
            ];
            $this->success('加载成功！', null, $returnData);
        }
    }

    // 添加自定义规格值并返回规格表
    public function add_product_custom_spec_value()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');

            // 添加自定义规格值
            $resultArray = $this->productSpecLogic->addProductCustomSpecValue($post);
            if (!empty($resultArray['errorMsg'])) $this->error($resultArray['errorMsg']);

            // 获取已选规格进行HTML代码拼装
            if (!empty($post['aid'])) {
                $htmlTable = $this->productSpecLogic->SpecAssemblyEdit($resultArray, $post['aid']);
            } else {
                $htmlTable = $this->productSpecLogic->SpecAssemblyEdit($resultArray);
            }
            // 返回数据
            $returnData = [
                'htmlTable' => !empty($htmlTable) ? $htmlTable : ' ',
            ];
            $this->success('加载成功！', null, $returnData);
        }
    }

    // 删除商品自定义规格并返回规格表格
    public function del_product_custom_spec()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');

            // 删除自定义规格
            $resultArray = $this->productSpecLogic->delProductCustomSpec($post);
            // 获取已选规格进行HTML代码拼装
            if (!empty($post['aid'])) {
                $htmlTable = $this->productSpecLogic->SpecAssemblyEdit($resultArray, $post['aid']);
            } else {
                $htmlTable = $this->productSpecLogic->SpecAssemblyEdit($resultArray);
            }
            if (in_array($post['del'], ['specName', 'specValue'])) {
                $specData = $this->productSpecLogic->getProductSpecValueOption(0, $post);
            }
            // 返回数据
            $returnData = [
                'htmlTable' => !empty($htmlTable) ? $htmlTable : ' ',
                'spec_value_option' => !empty($specData['spec_value_option']) ? $specData['spec_value_option'] : '',
                'preset_name_option' => !empty($specData['preset_name_option']) ? $specData['preset_name_option'] : '',
            ];
            $this->success('加载成功！', null, $returnData);
        }
    }

    public function edit_product_spec_price()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');
            $result = model('ProductSpecValueHandle')->editProductSpecPrice($post);
            if (!empty($result)) {
                $htmlTable = $this->productSpecLogic->SpecAssemblyEdit($result['resultArray'], $result['aid']);
                $this->success('操作成功', null, $htmlTable);
            } else {
                $this->error('数据异常，刷新重试');
            }
        }
    }

    // 获取会员折扣价格模板
    public function get_users_discount_price_tpl()
    {
        if (IS_AJAX_POST) {
            // 产品ID
            $aid = input('post.aid/d', 0);
            // 产品单价
            $users_price = input('post.users_price/f', 0);
            // 获取会员折扣价格模板
            $result = model('ShopPublicHandle')->getUsersDiscountPriceTpl($aid, $users_price);
            // 如果存在错误则返回提示
            if (isset($result['code']) && 0 === intval($result['code'])) $this->error($result['data']);
            // 返回数据
            $this->success('加载成功！', null, $result['data']);
        }
    }

    // 选中规格名称值，追加html到页面展示
    public function spec_value_select()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');
            // Post 数据
            $aid = !empty($post['aid']) ? $post['aid'] : 0;
            $spec_mark_id = !empty($post['spec_mark_id']) ? $post['spec_mark_id'] : 0;
            $spec_value_id = !empty($post['spec_value_id']) ? $post['spec_value_id'] : 0;
            if (empty($aid) || empty($spec_mark_id) || empty($spec_value_id)) $this->error('操作异常，请刷新重试...');

            $spec_array = [];
            // 执行更新
            $where = [
                'aid' => $aid,
                'spec_mark_id' => $spec_mark_id,
                'spec_value_id' => $spec_value_id,
            ];
            $update = [
                'spec_is_select' => 1,
                'update_time' => getTime()
            ];
            $Value = Db::name('product_spec_data_handle')->where($where)->getField('spec_value');
            $isResult = Db::name('product_spec_data_handle')->where($where)->update($update);
            if (!empty($isResult)) {
                // 仅ID信息，二维数组形式
                $where = [
                    'aid' => $aid,
                    'spec_is_select' => 1,
                ];
                $order = 'spec_value_id asc, spec_id asc, spec_mark_id asc';
                $data = Db::name('product_spec_data_handle')->field('spec_mark_id, spec_value_id')->where($where)->order($order)->select();
                if (!empty($data)) {
                    foreach ($data as $key => $value) {
                        $spec_array[$value['spec_mark_id']][] = $value['spec_value_id'];
                    }
                }
            }

            // 剔除已选择的规格值查询未选择的规格值组装成下拉返回
            $notInPresetID = !empty($spec_array[$specMarkID]) ? $spec_array[$specMarkID] : [];
            $where = [
                'aid' => $aid,
                'spec_is_select' => 0,
                'spec_mark_id' => ['IN', $spec_mark_id],
            ];
            if (!empty($notInPresetID)) $where['spec_value_id'] = ['NOT IN', $notInPresetID];
            $specData = Db::name('product_spec_data_handle')->where($where)->order('spec_value_id asc')->select();

            // 拼装下拉选项
            $Option = '<option value="0">选择规格值</option>';
            if (!empty($specData)) {
                foreach ($specData as $value) {
                    $Option .= "<option value='{$value['spec_value_id']}'>{$value['spec_value']}</option>";
                }
            }

            $htmlTable = $this->productSpecLogic->SpecAssemblyEdit($spec_array, $aid);

            // 返回数据
            $returnHtml = [
                'Value' => $Value,
                'Option' => $Option,
                'htmlTable' => $htmlTable
            ];
            $this->success('加载成功！', null, $returnHtml);
        }
    }

    // 商品属性列表
    public function attrlist_index()
    {
        // 查询条件
        $Where = [];
        $keywords        = input('keywords/s');
        if (!empty($keywords)) $Where['list_name'] = ['LIKE', "%{$keywords}%"];
        $Where['lang'] = $this->admin_lang;
        $Where['is_del'] = 0;

        // 分页
        $count   = $this->shop_product_attrlist_db->where($Where)->count('list_id');
        $pageObj = new Page($count, config('paginate.list_rows'));
        $pageStr = $pageObj->show();
        $this->assign('pager', $pageObj);
        $this->assign('page', $pageStr);

        // 数据
        $list = $this->shop_product_attrlist_db
            ->where($Where)
            ->order('sort_order asc, list_id asc')
            ->limit($pageObj->firstRow . ',' . $pageObj->listRows)
            ->select();
        $this->assign('list', $list);

        // 内容管理的产品发布/编辑里入口进来
        $oldinlet = input('param.oldinlet/d');
        $this->assign('oldinlet', $oldinlet);

        return $this->fetch();
    }

    // 保存全部参数
    public function attrlist_save()
    {
        function_exists('set_time_limit') && set_time_limit(0);

        if (IS_AJAX_POST) {
            $post = input('post.');
            // 参数名称不可重复
            $ListName = array_unique($post['list_name']);
            if (count($ListName) != count($post['list_name'])) $this->error('参数名称不可重复！');

            // 数据拼装
            $SaveData = [];
            foreach ($ListName as $key => $value) {
                if (!empty($value)) {
                    $list_id   = $post['list_id'][$key];
                    $list_name = trim($value);

                    $SaveData[$key] = [
                        'list_id'     => !empty($list_id) ? $list_id : 0,
                        'list_name'   => $list_name,
                        'desc'        => !empty($post['desc'][$key]) ? $post['desc'][$key] : '',
                        'sort_order'  => !empty($post['sort_order'][$key]) ? $post['sort_order'][$key] : 100,
                        'update_time' => getTime()
                    ];

                    if (empty($list_id)) {
                        $SaveData[$key]['add_time'] = getTime();
                        unset($SaveData[$key]['list_id']);
                    }
                }
            }

            $ReturnId = model('ShopProductAttrlist')->saveAll($SaveData);
            if ($ReturnId) {
                adminLog('新增商品参数：' . implode(',', $post['list_name']));
                $this->success('操作成功', url('Product/attrlist_index'));
            } else {
                $this->error('操作失败');
            }
        }
    }

    /**
     * 新增参数
     * @return [type] [description]
     */
    public function attrlist_add()
    {
        if (IS_AJAX_POST) {
            $post              = input('post.');
            $post['list_name'] = trim($post['list_name']);
            if (empty($post['list_name'])) {
                $this->error('参数名称不能为空！');
            }

            $SaveData = [
                'list_name'   => $post['list_name'],
                'desc'        => trim($post['desc']),
                'sort_order'  => 100,
                'lang'        => $this->admin_lang,
                'add_time'    => getTime(),
                'update_time' => getTime(),
            ];

            $ReturnId = Db::name('shop_product_attrlist')->insertGetId($SaveData);
            if ($ReturnId) {
                adminLog('新增商品参数：' . $post['list_name']);
                // 同步新产品参数分组ID到多语言的模板变量里，添加多语言新产品参数分组
                $this->syn_add_language_attrlist($ReturnId);

                if (!empty($post['attr_name'])) {
                    //数据拼接
                    $saveAttrData = [];
                    foreach ($post['attr_name'] as $k => $v) {
                        $attr_values           = str_replace('_', '', $v); // 替换特殊字符
                        $attr_values           = str_replace('@', '', $attr_values); // 替换特殊字符
                        $attr_values           = trim($attr_values);
                        if (empty($attr_values)) {
                            unset($post['attr_name'][$k]);
                            continue;
                        }
                        $post['attr_name'][$k] = $attr_values;

                        $saveAttrData[] = array(
                            'attr_name'       => !empty($post['attr_name'][$k]) ? $post['attr_name'][$k] : '',
                            'list_id'         => $ReturnId,
                            'attr_input_type' => !empty($post['attr_input_type'][$k]) ? intval($post['attr_input_type'][$k]) : 0,
                            'attr_values'     => !empty($post['attr_values'][$k]) ? trim($post['attr_values'][$k]) : '',
                            'sort_order'      => isset($post['attr_sort_order'][$k]) ? intval($post['attr_sort_order'][$k]) : 100,
                            'status'          => 1,
                            'lang'            => $this->admin_lang,
                            'add_time'        => getTime(),
                            'update_time'     => getTime(),
                        );
                    }

                    if (!empty($saveAttrData)) {
                        $rdata = model('ShopProductAttribute')->saveAll($saveAttrData);
                        if ($rdata !== false) {
                            // 参数值合计增加
                            Db::name('shop_product_attrlist')->where('list_id', $ReturnId)->setInc('attr_count', count($post['attr_name']));
                            /*多语言*/
                            if (is_language()) {
                                foreach ($rdata as $k1 => $v1) {
                                    $attr_data = $v1->getData();
                                    // 同步多语言
                                    $this->syn_add_language_attribute($attr_data['attr_id']);
                                }
                            }
                            /*end*/
                        }
                    }
                }
                $this->success('操作成功', url('ShopProduct/attrlist_index'));
            }
            $this->error('操作失败');
        }
        return $this->fetch();
    }

    /**
     * 编辑参数
     * @return [type] [description]
     */
    public function attrlist_edit()
    {
        if (IS_AJAX_POST) {
            $post              = input('post.');
            $post['list_id'] = intval($post['list_id']);
            $post['list_name'] = trim($post['list_name']);
            if (empty($post['list_name'])) {
                $this->error('参数名称不能为空！');
            }

            $SaveData = [
                'list_name'   => $post['list_name'],
                'desc'        => trim($post['desc']),
                'update_time' => getTime(),
            ];

            $res = Db::name('shop_product_attrlist')->where('list_id', $post['list_id'])->update($SaveData);
            if ($res) {
                adminLog('编辑商品参数：' . $post['list_name']);
                if (!empty($post['attr_name'])) {
                    //数据拼接
                    $saveAttrData = [];
                    $attr_ids     = [];
                    $time = getTime();

                    foreach ($post['attr_name'] as $k => $v) {
                        $attr_values           = str_replace('_', '', $v); // 替换特殊字符
                        $attr_values           = str_replace('@', '', $attr_values); // 替换特殊字符
                        $post['attr_name'][$k] = trim($attr_values);

                        $attrData = array(
                            'attr_name'       => !empty($post['attr_name'][$k]) ? $post['attr_name'][$k] : '',
                            'list_id'         => !empty($post['list_id']) ? intval($post['list_id']) : 0,
                            'attr_input_type' => !empty($post['attr_input_type'][$k]) ? intval($post['attr_input_type'][$k]) : 0,
                            'attr_values'     => !empty($post['attr_values'][$k]) ? trim($post['attr_values'][$k]) : '',
                            'sort_order'      => isset($post['attr_sort_order'][$k]) ? intval($post['attr_sort_order'][$k]) : 100,
                            'update_time'     => $time,
                        );
                        if (!empty($post['attr_id'][$k])) {
                            $attrData['attr_id'] = $post['attr_id'][$k];
                            $attrData['add_time'] = $time;
                            $attr_ids[]          = $post['attr_id'][$k];
                        }
                        $saveAttrData[] = $attrData;
                    }

                    if (!empty($saveAttrData)) {
                        $RId = model('ShopProductAttribute')->saveAll($saveAttrData);
                        if ($RId !== false) {
                            //删除多余的参数
                            Db::name('shop_product_attribute')
                                ->where([
                                    'list_id'   => $post['list_id'],
                                    'attr_id'   => ['NOTIN', $attr_ids],
                                    'update_time'=> ['NEQ', $time],
                                ])
                                ->delete();
                            // 参数值合计增加
                            Db::name('shop_product_attrlist')->where('list_id', $post['list_id'])->update(['attr_count' => count($saveAttrData), 'update_time' => getTime()]);
                        } else {
                            $this->error('操作失败');
                        }
                    }
                }else{
                    //删除多余的参数
                    Db::name('shop_product_attribute')
                        ->where('list_id', $post['list_id'])
                        ->delete();
                    // 参数值合计增加
                    Db::name('shop_product_attrlist')->where('list_id', $post['list_id'])->update(['attr_count' => 0, 'update_time' => getTime()]);
                }
                $this->success('操作成功', url('ShopProduct/attrlist_index'));
            }
            $this->error('操作失败');
        }
        
        $list_id = input('param.list_id');
        $list    = Db::name('shop_product_attrlist')->where('list_id', $list_id)->find();
        if (empty($list)) $this->error('数据不存在，请联系管理员！');
        $list['attr'] = Db::name('shop_product_attribute')->where('list_id', $list_id)->order('sort_order asc, attr_id asc')->select();

        $this->assign('list', $list);
        return $this->fetch();
    }

    // 参数删除
    public function attrlist_del()
    {
        $id_arr = input('del_id/a');
        $id_arr = eyIntval($id_arr);
        if (!empty($id_arr)) {
            $Result = $this->shop_product_attrlist_db->where('list_id', 'IN', $id_arr)->delete();
            if ($Result) {
                Db::name('shop_product_attribute')->where('list_id', 'IN', $id_arr)->delete();
                adminLog('删除商品参数-id：' . implode(',', $id_arr));
                $this->success('删除成功');
            } else {
                $this->error('删除失败');
            }
        } else {
            $this->error('参数有误');
        }
    }

    /**
     * 商品参数值列表
     */
    public function attribute_index()
    {
        $condition = array();
        // 获取到所有GET参数
        $get     = input('get.');
        $list_id = input('list_id/d', 0);

        // 应用搜索条件
        foreach (['keywords', 'list_id'] as $key) {
            if (isset($get[$key]) && $get[$key] !== '') {
                if ($key == 'keywords') {
                    $condition['a.attr_name'] = ['LIKE', "%{$get[$key]}%"];
                } else if ($key == 'list_id') {
                    $condition['a.list_id'] = $list_id;
                } else {
                    $condition['a.' . $key] = ['eq', $get[$key]];
                }
            }
        }
        $condition['a.lang'] = $this->admin_lang;
        $condition['a.is_del'] = 0;

        // 分页
        $count   = Db::name('shop_product_attribute')->alias('a')->where($condition)->count();
        $pageObj = new Page($count, config('paginate.list_rows'));
        $pageStr = $pageObj->show();
        $this->assign('pager', $pageObj);
        $this->assign('page', $pageStr);

        // 数据
        $list = Db::name('shop_product_attribute')
            ->alias('a')
            ->where($condition)
            ->order('a.sort_order asc, a.attr_id asc')
            ->limit($pageObj->firstRow . ',' . $pageObj->listRows)
            ->select();

        $attrInputTypeArr = [
            0 => '手工录入',
            1 => '选取默认值'
        ];
        $this->assign('attrInputTypeArr', $attrInputTypeArr);
        $this->assign('list', $list);
        return $this->fetch();
    }


    /**
     * 新增商品参数
     */
    public function attribute_add()
    {
        //防止php超时
        function_exists('set_time_limit') && set_time_limit(0);

        if (IS_AJAX_POST) {
            $attr_values              = str_replace('_', '', input('attr_values')); // 替换特殊字符
            $attr_values              = str_replace('@', '', $attr_values); // 替换特殊字符
            $attr_values              = trim($attr_values);
            $post_data                = input('post.');
            $post_data['list_id'] = intval($post_data['list_id']);
            $post_data['attr_values'] = $attr_values;

            $SaveData = array(
                'attr_name'       => $post_data['attr_name'],
                'list_id'         => $post_data['list_id'],
                'attr_input_type' => isset($post_data['attr_input_type']) ? $post_data['attr_input_type'] : '',
                'attr_values'     => isset($post_data['attr_values']) ? $post_data['attr_values'] : '',
                'sort_order'      => $post_data['sort_order'],
                'status'          => 1,
                'lang'            => $this->admin_lang,
                'add_time'        => getTime(),
                'update_time'     => getTime(),
            );

            $ReturnId = Db::name('shop_product_attribute')->add($SaveData);
            if ($ReturnId) {
                // 参数值合计增加
                Db::name('shop_product_attrlist')->where('list_id', $post_data['list_id'])->setInc('attr_count');
                adminLog('新增商品参数：' . $SaveData['attr_name']);
                $this->success('操作成功');
            } else {
                $this->error('操作失败');
            }
        }

        $list_id = input('param.list_id/d', 0);
        $list    = $this->shop_product_attrlist_db->where('list_id', $list_id)->find();
        $this->assign('list', $list);
        return $this->fetch();
    }

    /**
     * 编辑商品参数
     */
    public function attribute_edit()
    {
        //防止php超时
        function_exists('set_time_limit') && set_time_limit(0);

        if (IS_AJAX_POST) {
            $attr_values              = str_replace('_', '', input('attr_values')); // 替换特殊字符
            $attr_values              = str_replace('@', '', $attr_values); // 替换特殊字符
            $attr_values              = trim($attr_values);
            $post_data                = input('post.');
            $post_data['list_id'] = intval($post_data['list_id']);
            $post_data['attr_values'] = $attr_values;

            $SaveData = array(
                'attr_name'       => $post_data['attr_name'],
                'list_id'         => $post_data['list_id'],
                'attr_input_type' => isset($post_data['attr_input_type']) ? $post_data['attr_input_type'] : '',
                'attr_values'     => isset($post_data['attr_values']) ? $post_data['attr_values'] : '',
                'sort_order'      => $post_data['sort_order'],
                'update_time'     => getTime(),
            );

            $ReturnId = Db::name('shop_product_attribute')->where(['attr_id'=>$post_data['attr_id'], 'lang'=>$this->admin_lang])->update($SaveData);
            if ($ReturnId) {
                adminLog('编辑商品参数：' . $SaveData['attr_name']);
                $this->success('操作成功');
            } else {
                $this->error('操作失败');
            }
        }

        $id = input('param.id/d');
        $info = Db::name('shop_product_attribute')->where(['attr_id'=>$id, 'lang'=>$this->admin_lang])->find();
        if (empty($info)) $this->error('数据不存在，请联系管理员！');
        $this->assign('field', $info);

        $list = $this->shop_product_attrlist_db->where('list_id', $info['list_id'])->find();
        $this->assign('list', $list);
        return $this->fetch();
    }

    /**
     * 删除商品参数
     */
    public function attribute_del()
    {
        $id_arr = input('del_id/a');
        $id_arr = eyIntval($id_arr);
        if (!empty($id_arr)) {
            $r = Db::name('shop_product_attribute')->where(['attr_id' => ['IN', $id_arr], 'lang'=>$this->admin_lang])->delete();
            if ($r) {
                $IDCount = count($id_arr);
                Db::name('shop_product_attrlist')->where('list_id', input('list_id/d'))->setDec('attr_count', $IDCount);
                adminLog('删除商品参数-id：' . implode(',', $id_arr));
                $this->success('删除成功');
            } else {
                $this->error('删除失败');
            }
        } else {
            $this->error('参数有误');
        }
    }

    /**
     * 动态获取商品参数输入框 根据不同的数据返回不同的输入框类型
     */
    public function ajax_get_shop_attr_input($typeid = '', $aid = '', $list_id = '')
    {
        $typeid       = intval($typeid);
        $aid          = intval($aid);
        $list_id      = intval($list_id);
        $productLogic = new ProductLogic();
        $str          = $productLogic->getShopAttrInput($aid, $typeid, $list_id);
        if (empty($str)) {
            $str = '<div style="font-size: 12px;text-align: center;">提示：该参数还没有参数值，若有需要请点击【<a href="' . url('Product/attribute_index', array('list_id' => $list_id)) . '">商品参数</a>】进行更多操作。</div>';
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
    public function ajax_get_attr_input($typeid = '', $aid = '', $list_id = '')
    {
        $typeid       = intval($typeid);
        $aid          = intval($aid);
        $list_id      = intval($list_id);
        $productLogic = new ProductLogic();
        $str          = $productLogic->getAttrInput($aid, $typeid, $list_id);
        if (empty($str)) {
            $str = '<div style="font-size: 12px;text-align: center;">提示：该参数还没有参数值，若有需要请点击【<a href="' . url('Product/attribute_index', array('list_id' => $list_id)) . '">商品参数</a>】进行更多操作。</div>';
        }
        if (IS_AJAX) {
            exit($str);
        } else {
            return $str;
        }
    }

    /**
     * 发布商品
     */
    public function release()
    {
        $typeid = input('param.typeid/d', 0);
        if (0 < $typeid) {
            $param = input('param.');
            $row   = Db::name('arctype')->field('current_channel')->find($typeid);
            /*针对不支持发布文档的模型*/
            if ($row['current_channel'] != 2) {
                $this->error('该栏目不支持发布商品！', url('ShopProduct/release'));
            }
            /*-----end*/

            $data = [
                'typeid' => $typeid,
                'callback_url' => $this->callback_url,
            ];
            $jumpUrl = url("ShopProduct/add", $data, true, true);
            header('Location: ' . $jumpUrl);
            exit;
        }

        /*允许发布文档列表的栏目*/
        $select_html = allow_release_arctype(0, [2]);
        $this->assign('select_html', $select_html);
        /*--end*/

        return $this->fetch();
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
    
    // 商品服务
    public function goods_label()
    {
        // 商品服务标签列表
        $goodsLabel = Db::name('shop_goods_label')->select();
        foreach ($goodsLabel as $key => $value) {
            $value['label_pic'] = handle_subdir_pic($value['label_pic']);
            $goodsLabel[$key] = $value;
        }
        $this->assign('goodsLabel', $goodsLabel);

        // 最大商品服务标签ID
        $this->assign('maxGoodsLabelID', Db::name('shop_goods_label')->max('label_id'));

        // 商品aid
        $this->assign('aid', input('param.aid/d', 0));

        return $this->fetch();
    }

    // 商品服务删除
    public function goods_label_del()
    {
        if (IS_AJAX_POST) {
            $this->error('删除功能暂停使用');
            // $labelID = input('post.labelID/d', 0);
            // $result = Db::name('shop_goods_label')->where('label_id', $labelID)->delete(true);
            // if (!empty($result)) $this->success('删除成功');
            // $this->error('删除失败');
        }
    }

    // 商品服务保存更新
    public function goods_label_save()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');
            $saveAll = [];
            $labelIds = [];
            foreach ($post['label_id'] as $key => $value) {
                $labelPic = !empty($post['label_pic'][$key]) ? $post['label_pic'][$key] : '';
                $labelMark = !empty($post['label_mark'][$key]) ? $post['label_mark'][$key] : 0;
                $labelTitle = !empty($post['label_title'][$key]) ? $post['label_title'][$key] : '';
                $labelIntro = !empty($post['label_intro'][$key]) ? $post['label_intro'][$key] : '';
                // 检测数据是否填写完整
                if (!empty($labelTitle)) {
                    if (empty($labelPic)) $this->error('请上传商品服务的图片', null, ['id' => '#labelClick_' . $labelMark]);
                    if (empty($labelIntro)) $this->error('请填写商品服务的描述', null, ['id' => '#labelIntro_' . $labelMark]);
                }
                else if (!empty($labelPic)) {
                    if (empty($labelTitle)) $this->error('请填写商品服务的标题', null, ['id' => '#labelTitle_' . $labelMark]);
                    if (empty($labelIntro)) $this->error('请填写商品服务的描述', null, ['id' => '#labelIntro_' . $labelMark]);
                }
                else if (!empty($labelIntro)) {
                    if (empty($labelTitle)) $this->error('请填写商品服务的标题', null, ['id' => '#labelTitle_' . $labelMark]);
                    if (empty($labelPic)) $this->error('请上传商品服务的图片', null, ['id' => '#labelClick_' . $labelMark]);
                }
                if (!empty($labelPic) || !empty($labelTitle) || !empty($labelIntro)) {
                    $saveAll[$key] = [
                        'label_title' => trim($labelTitle),
                        'label_pic' => trim($labelPic),
                        'label_intro' => trim($labelIntro),
                        'add_time' => getTime(),
                        'update_time' => getTime(),
                    ];
                }
                if (!empty($value)) {
                    array_push($labelIds, $value);
                    unset($saveAll[$key]['add_time']);
                    $saveAll[$key] = array_merge(['label_id' => intval($value)], $saveAll[$key]);
                }
            }

            // 商品标签删除处理
            if (!empty($labelIds)) {
                // 删除需要删除的ID
                $labelIds = Db::name('shop_goods_label')->where(['label_id' => ['NOT IN', $labelIds]])->column('label_id');
                if (!empty($labelIds)) {
                    // 删除指定ID的商品标签
                    $where = [
                        'label_id' => ['IN', $labelIds]
                    ];
                    $result = Db::name('shop_goods_label')->where($where)->delete(true);
                    // 删除指定ID的商品标签绑定信息
                    if (!empty($result)) Db::name('shop_goods_label_bind')->where($where)->delete(true);
                }
            }

            // 商品服务保存更新
            if (!empty($saveAll)) {
                // 商品服务保存更新
                model('ShopGoodsLabel')->saveAll($saveAll);
                // 查询商品服务列表
                $data['goodsLabel'] = !empty($post['aid']) ? model('ShopGoodsLabel')->getGoodsLabelList($post['aid']) : [];
                $this->success('更新成功', null, $data);
            } else {
                $this->error('更新失败');
            }
        }
    }

    /**
     * 同步新增新产品参数分组ID到多语言的模板变量里
     */
    private function syn_add_language_attrlist($list_id)
    {
        /*单语言情况下不执行多语言代码*/
        if (!is_language() || tpCache('language.language_split')) {
            return true;
        }
        /*--end*/

        $attr_group = 'shop_product_attrlist';
        $admin_lang = $this->admin_lang;
        $main_lang = $this->main_lang;
        $languageRow = Db::name('language')->field('mark')->order('id asc')->select();
        if (!empty($languageRow) && $admin_lang == $main_lang) { // 当前语言是主体语言，即语言列表最早新增的语言
            $attrlist_db = Db::name('shop_product_attrlist');
            $result = $attrlist_db->find($list_id);
            $attr_name = 'attrlist_'.$list_id;
            $r = Db::name('language_attribute')->save([
                'attr_title'    => $result['list_name'],
                'attr_name'     => $attr_name,
                'attr_group'    => $attr_group,
                'add_time'      => getTime(),
                'update_time'   => getTime(),
            ]);
            if (false !== $r) {
                $data = [];
                foreach ($languageRow as $key => $val) {
                    /*同步新产品参数分组到其他语言新产品参数分组列表*/
                    if ($val['mark'] != $admin_lang) {
                        $addsaveData = $result;
                        $addsaveData['lang']  = $val['mark'];
                        $addsaveData['list_name'] = $val['mark'].$addsaveData['list_name'];
                        unset($addsaveData['list_id']);
                        $list_id = $attrlist_db->insertGetId($addsaveData);
                    }
                    /*--end*/
                    
                    /*所有语言绑定在主语言的ID容器里*/
                    $data[] = [
                        'attr_name' => $attr_name,
                        'attr_value'    => $list_id,
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

    /**
     * 同步新增产品参数值ID到多语言的模板变量里
     */
    private function syn_add_language_attribute($attr_id)
    {
        /*单语言情况下不执行多语言代码*/
        if (!is_language() || tpCache('language.language_split')) {
            return true;
        }
        /*--end*/

        $attr_group = 'shop_product_attribute';
        $admin_lang = $this->admin_lang;
        $main_lang = $this->main_lang;
        $languageRow = Db::name('language')->field('mark')->order('id asc')->select();
        if (!empty($languageRow) && $admin_lang == $main_lang) { // 当前语言是主体语言，即语言列表最早新增的语言
            $attribute_db = Db::name('shop_product_attribute');
            $result = $attribute_db->find($attr_id);
            $attr_name = 'attribute_'.$attr_id;
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
                    /*同步新产品参数值到其他语言产品参数值列表*/
                    if ($val['mark'] != $admin_lang) {
                        $addsaveData = $result;
                        $addsaveData['lang'] = $val['mark'];
                        $new_list_id = Db::name('language_attr')->where([
                                'attr_name' => 'attrlist_'.$result['list_id'],
                                'attr_group'    => 'shop_product_attrlist',
                                'lang'  => $val['mark'],
                            ])->getField('attr_value');
                        $addsaveData['list_id']   = $new_list_id;
                        $addsaveData['attr_name'] = $val['mark'].$addsaveData['attr_name'];
                        unset($addsaveData['attr_id']);
                        $attr_id = $attribute_db->insertGetId($addsaveData);
                    }
                    /*--end*/
                    
                    /*所有语言绑定在主语言的ID容器里*/
                    $data[] = [
                        'attr_name'   => $attr_name,
                        'attr_value'  => $attr_id,
                        'lang'        => $val['mark'],
                        'attr_group'  => $attr_group,
                        'add_time'    => getTime(),
                        'update_time' => getTime(),
                    ];
                    /*--end*/
                }
                if (!empty($data)) {
                    model('LanguageAttr')->saveAll($data);
                }
            }
        }
    }
}