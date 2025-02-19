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
 * Date: 2019-07-08
 */

namespace app\admin\logic;

use think\Model;
use think\Db;

/**
 * 逻辑定义
 * 用于产品规格逻辑功能处理
 * @package admin\Logic
 */
class ProductSpecLogic extends Model
{
    /**
     * 初始化操作
     */
    public function _initialize() {
        parent::_initialize();
    }

    // 数组降级返回
    public function ArrayDowngrade($GetData = array())
    {
        $ReturnData = [];
        foreach ($GetData as $value){
            foreach ($value as $vv){
                $ReturnData[] = $vv;
            }
        }
        return $ReturnData;
    }

    // 一维数组转为二级数组，携带子数组
    public function GetPresetData($GetData = array())
    {
        $GetData = group_same_key($GetData, 'preset_mark_id');
        $ReturnData  = [];
        foreach ($GetData as $key => $value) {
            $ReturnData[] = [
                'preset_id'      => $value[0]['preset_id'],
                'preset_mark_id' => $value[0]['preset_mark_id'],
                'preset_name'    => $value[0]['preset_name'],
                'spec_sync'      => $value[0]['spec_sync'],
                'sort_order'     => $value[0]['sort_order'],
                'preset_value'   => $value,
            ];
        }
        return $ReturnData;
    }

    // 删除规格名称\规格值条件
    public function GetDeleteSpecWhere($GetData = array())
    {
        $where = [];
        // 删除规格值
        if (isset($GetData['preset_id']) && !empty($GetData['preset_id'])) {
            $where = [
                'lang'      => get_admin_lang(),
                'preset_id' => $GetData['preset_id'],
            ];
        }
        // 删除规格名称，包扣规格名称下的所有规格值
        if (isset($GetData['preset_mark_id']) && !empty($GetData['preset_mark_id'])) {
            $where = [
                'lang'      => get_admin_lang(),
                'preset_mark_id' => ['IN', $GetData['preset_mark_id']],
            ];
        }
        return $where;
    }

    // 拼装预设值下拉选项
    public function GetPresetValueOption($GetData = array(), $mark_id = null, $aid = null, $type = null)
    {   
        $result = $PresetValueOption = '';
        if (!empty($mark_id)) {
            $where = [
                'lang' => get_admin_lang(),
                'aid'  => $aid,
                'spec_is_select' => 0, // 未选中的
                'spec_mark_id' => ['IN', $mark_id],
            ];
            $GetData = Db::name('product_spec_data_handle')->where($where)->field('spec_value_id, spec_name, spec_value')->select();
            $PresetValueOption .= "<option value='0'>选择规格值</option>";
            foreach ($GetData as $value) {
                $PresetValueOption .= "<option value='{$value['spec_value_id']}'>{$value['spec_value']}</option>";
            }
            if (2 == $type) {
                $result = [
                    'PresetName' => $GetData[0]['spec_name'],
                    'PresetValueOption' => $PresetValueOption,
                ];
            } else {
                $result = $PresetValueOption;
            }
        } else {
            $result .= "<option value='0'>选择规格值</option>";
            if (!empty($GetData)) {
                foreach ($GetData as $value) {
                    $result .= "<option value='{$value['preset_id']}'>{$value['preset_value']}</option>";
                }
            }
        }
        return $result;
    }

    // 预设规格标记ID数组
    public function GetPresetMarkIdArray($GetData = array())
    {
        $result = '';
        if (isset($GetData['aid']) && !empty($GetData['aid'])) {
            $spec_mark_id_arr = $GetData['spec_mark_id_arr'];
            if (empty($spec_mark_id_arr)) {
                $result = $GetData['spec_mark_id'];
            } else {
                $return_spec_mark_id = $spec_mark_id_arr . ',' . $GetData['spec_mark_id'];
                if (3 < count(explode(',', $return_spec_mark_id))) {
                    $result = false;
                } else {
                    $result = $return_spec_mark_id;
                }
            }
        } else {
            $preset_mark_id_arr = $GetData['preset_mark_id_arr'];
            if (empty($preset_mark_id_arr)) {
                $result = $GetData['preset_mark_id'];
            } else {
                $return_preset_mark_id = $preset_mark_id_arr . ',' . $GetData['preset_mark_id'];
                if (3 < count(explode(',', $return_preset_mark_id))) {
                    $result = false;
                } else {
                    $result = $return_preset_mark_id;
                }
            }
        }
        return $result;
    }

    // 拼装规格名称下拉选项
    public function GetPresetNameOption($preset_mark_id_arr = null)
    {
        $MarkIdWhere = explode(',', $preset_mark_id_arr);
        $where = [
            'lang' => get_admin_lang(),
            'preset_mark_id' => ['NOT IN', $MarkIdWhere],
        ];
        $PresetName  = Db::name('product_spec_preset')->where($where)->field('preset_id, preset_mark_id, preset_name')->group('preset_mark_id')->order('preset_mark_id desc')->select();
        if (!empty($PresetName)) {
            // 拼装下拉选项
            foreach ($PresetName as $value) {
                $Option .= "<option value='{$value['preset_mark_id']}'>{$value['preset_name']}</option>";
            }
        }
        $result = [
            'Option' => $Option,
            'MarkId' => implode(',', $MarkIdWhere),
        ];
        return $result;
    }

    public function GetPresetSpecAssembly($GetData = array())
    {
        $preset_mark_id = $GetData['preset_mark_id'];
        if (isset($GetData['aid']) && !empty($GetData['aid'])) {
            $PresetMarkIdArray = $GetData['spec_mark_id_arr'];
        }else{
            $PresetMarkIdArray = $GetData['preset_mark_id_arr'];
        }
        
        $i = 0;
        if (!empty($PresetMarkIdArray) && !empty($preset_mark_id)) {
            $mark_id_arr = explode(',', $PresetMarkIdArray);
            foreach ($mark_id_arr as $key => $value) {
                if ($value == $preset_mark_id) {
                    unset($mark_id_arr[$key]);
                    $i++;
                }
            }
            if (!empty($i)) {
                $PresetMarkIdArray = implode(',', $mark_id_arr);
            }
        }

        $HtmlTable = '';
        if (!empty($i) && !empty($preset_mark_id)) {
            $spec_arr_ses = session('spec_arr');
            unset($spec_arr_ses[$preset_mark_id]);
            session('spec_arr', $spec_arr_ses);
            $HtmlTable = $this->SpecAssembly($spec_arr_ses);
        }

        $return = [
            'HtmlTable' => $HtmlTable,
            'PresetMarkIdArray' => $PresetMarkIdArray,
        ];        
        return $return;
    }

    public function GetResetPresetNameOption($GetData = array())
    {
        $del_mark_id = $GetData['del_mark_id'];
        $return = [
            'Option' => '',
            'MarkId' => '',
        ];
        if (isset($del_mark_id) && !empty($del_mark_id) && !isset($GetData['del_preset_id'])) {
            // 删除商品规格处理库的规格数据
            if (isset($GetData['aid']) && !empty($GetData['aid'])) {
                $WhereData = [
                    'aid' => $GetData['aid'],
                    'spec_mark_id' => $del_mark_id,
                ];
                Db::name('product_spec_data_handle')->where($WhereData)->delete(true);
                // $Update = [
                //     'spec_is_select' => 0,
                //     'update_time' => getTime(),
                // ];
                // Db::name('product_spec_data_handle')->where($WhereData)->update($Update);
            } else {
                // 删除 session 数据并覆盖原数据
                $spec_arr_ses = session('spec_arr');
                unset($spec_arr_ses[$del_mark_id]);
                session('spec_arr', $spec_arr_ses);
            }

            // 获取提交的规格标记ID数组并处理生成预设规格下拉信息
            $PresetMarkIdArray = !empty($GetData['preset_mark_id_arr']) ? $GetData['preset_mark_id_arr'] : $GetData['spec_mark_id_arr'];
            if (!empty($PresetMarkIdArray)) {
                $mark_id_arr = explode(',', $PresetMarkIdArray);
                foreach ($mark_id_arr as $key => $value) {
                    if ($value == $del_mark_id) unset($mark_id_arr[$key]);
                }
                $PresetMarkIdArray = implode(',', $mark_id_arr);
            }
            $return = $this->GetPresetNameOption($PresetMarkIdArray);
        }

        return $return;
    }

    public function ClearSpecValueID($GetData = array())
    {
        // 清除单个规格值
        $del_mark_id = $GetData['del_mark_id'];
        $return = [
            'Option' => '',
        ];
        if (isset($del_mark_id) && !empty($del_mark_id) && isset($GetData['del_preset_id'])) {
            // 删除指定规格值并覆盖原数据
            $spec_arr_ses = session('spec_arr');
            foreach ($spec_arr_ses[$del_mark_id] as $key => $value) {
                if ($value == $GetData['del_preset_id']) unset($spec_arr_ses[$del_mark_id][$key]);
            }
            // 如果规格大类下的规格值都删完了则删除规格大类
            if (empty($spec_arr_ses[$del_mark_id])) unset($spec_arr_ses[$del_mark_id]);
            // 覆盖原数据
            session('spec_arr', $spec_arr_ses);

            // 处理生成预设规格下拉信息
            $preset_id = $spec_arr_ses[$del_mark_id];
            if (empty($GetData['aid'])) {
                $WhereNew = [
                    'preset_mark_id' => $del_mark_id,
                    'preset_id' => ['NOT IN', $preset_id],
                ];
                // 加载选中的规格值
                $PresetValue = Db::name('product_spec_preset')->where($WhereNew)->select();
                $return['Option'] = $this->GetPresetValueOption($PresetValue);
            } else {
                // 更新规格值
                $Where = [
                    'aid' => $GetData['aid'],
                    'spec_mark_id' => $del_mark_id,
                    'spec_value_id' => $GetData['del_preset_id'],
                    'spec_is_select'=> 1,
                ];
                Db::name('product_spec_data_handle')->where($Where)->update(['spec_is_select'=>0, 'update_time'=>getTime()]);

                // 拼装新的下拉框数据
                $WhereNew = [
                    'aid' => $GetData['aid'],
                    'spec_mark_id' => $del_mark_id,
                    'spec_is_select' => 0,
                ];
                $Data = Db::name('product_spec_data_handle')->where($WhereNew)->field('spec_value_id,spec_value')->select();
                $return['Option'] .= "<option value='0'>选择规格值</option>";
                foreach ($Data as $value) {
                    $return['Option'] .= "<option value='{$value['spec_value_id']}'>{$value['spec_value']}</option>";
                }
            }
        }

        return $return;
    }

    public function GetSessionPostArrayMerge($GetData = array())
    {
        $preset_id = $GetData['preset_id'];
        $preset_mark_id = $GetData['preset_mark_id'];
        $spec_arr_ses = session('spec_arr');

        if (!empty($preset_mark_id) && !empty($preset_id)) {
            $spec_arr_new = [
                $preset_mark_id => [
                    $preset_id,
                ],
            ];
            foreach ($spec_arr_new as $key => $value) {
                if (isset($spec_arr_ses[$key])) {
                    if (in_array($value[0], $spec_arr_ses[$key])) {
                        $msg['error'] = '已有相同规格值，无需重复添加！';
                        return $msg;
                    } else {
                        // session存在数据，但选中参数在数据中还没有，合并数据
                        $spec_arr_ses[$key] = array_merge($spec_arr_ses[$key], $value);
                    }
                } else {
                    if (!empty($spec_arr_ses)) {
                        // 不存在
                        $spec_arr_ses = $spec_arr_ses + $spec_arr_new;
                    } else {
                        $spec_arr_ses = $spec_arr_new;
                    }
                }
            }
        }
        session('spec_arr', $spec_arr_ses);
        return $spec_arr_ses;
    }

    public function SpecAssembly($GetData = array())
    {
        if (empty($GetData)) return ' ';

        // 参数处理
        foreach ($GetData as $k => $v) {
            $spec_arr_sort[$k] = count($v);
        }
        asort($spec_arr_sort);        
        foreach ($spec_arr_sort as $key =>$val) {
            $spec_arr2[$key] = $GetData[$key];
        }
        // 获取KEY值
        $clo_name  = array_keys($spec_arr2);
        // 数据组合
        $spec_arr2 = $this->DataCombination($spec_arr2);
        // 查询预设名称
        $spec = Db::name('product_spec_preset')->group('preset_mark_id')->getField('preset_mark_id,preset_name');
        // 查询预设值
        $specItem = Db::name('product_spec_preset')->getField('preset_id,preset_value,preset_mark_id');
        // 组合HTML
        $ReturnHtml  = "<table class='table table-bordered' id='spec_input_tab' border='1' cellpadding='10' cellspacing='10'><thead><tr>";
        // 显示第一行的数据
        foreach ($clo_name as $k => $v) {
            $ReturnHtml .= "<td><b><input type='hidden' class='prese_name_input_{$v}' name='preset_mark_id[$v][preset_name]' value='{$spec[$v]}'><span class='preset_name_span_{$v}'>{$spec[$v]}</span></b></td>";
        }
        $ReturnHtml .= "<td><b>价格 <a href=\"javascript:void(0);\" onclick=\"BulkSetPrice(this);\">批量设置 </a></b></td>";
        $ReturnHtml .= "<td><b>库存 <a href=\"javascript:void(0);\" onclick=\"BulkSetStock(this);\">批量设置 </a></b></td>";
        $ReturnHtml .= "<td><b>虚拟销量</b></td>";
        $ReturnHtml .= "</tr></thead><tbody>";

       // 显示第二行开始
       foreach ($spec_arr2 as $k => $v) {
            $ReturnHtml .= "<tr id='preset_value_tr_{$v[0]}'>";
            $preset_key_name = array();
            foreach($v as $k2 => $v2) {
                $ReturnHtml .= "<td><input type='hidden' class='preset_value_input_{$v2}' name='preset_id[$v2][preset_value]' value='{$specItem[$v2]['preset_value']}'><span class='preset_value_span_{$v2}'>{$specItem[$v2]['preset_value']}</span></td>";                
                $preset_key_name[$v2] = $spec[$specItem[$v2]['preset_mark_id']].':'.$specItem[$v2]['preset_value'];
            }
            ksort($preset_key_name);
            $preset_key = implode('_', array_keys($preset_key_name));

            $ReturnHtml .="<td><input class='users_price' name='preset_price[$preset_key][users_price]' value='0' onkeyup='this.value=this.value.replace(/[^\d.]/g,\"\");UpPrice(this);' onpaste='this.value=this.value.replace(/[^\d.]/g,\"\")'/></td>";

            $ReturnHtml .="<td><input class='stock_count' name='preset_stock[$preset_key][stock_count]' value='0' onkeyup='this.value=this.value.replace(/[^\d.]/g,\"\");UpStock(this);' onpaste='this.value=this.value.replace(/[^\d.]/g,\"\")' data-old_stock='0'/></td>";

            $ReturnHtml .="<td><input type='text' name='preset_sales[$preset_key][spec_sales_num]' value='0' onkeyup='this.value=this.value.replace(/[^\d.]/g,\"\")' onpaste='this.value=this.value.replace(/[^\d.]/g,\"\")'></td>";

            $ReturnHtml .="</tr>";
        }
        $ReturnHtml .= "</tbody>";
        $ReturnHtml .= "</table>";
        // $ReturnHtml .= '批量设置：<a href="javascript:void(0);" onclick="BulkSetPrice(this);" >价格</a> <a href="javascript:void(0);" onclick="BulkSetStock(this);" >库存</a>';

        return $ReturnHtml;
    }

    public function SpecAssemblyEdit($GetData = array(), $aid = 0, $quick = false)
    {
        if (empty($GetData)) return ' ';
        // 参数处理
        foreach ($GetData as $k => $v) {
            $spec_arr_sort[$k] = count($v);
        }
        asort($spec_arr_sort);
        foreach ($spec_arr_sort as $key =>$val) {
            $spec_arr2[$key] = $GetData[$key];
        }
        // 获取KEY值
        $clo_name = array_keys($spec_arr2);
        // 数据组合
        $spec_arr2 = $this->DataCombination($spec_arr2);
        $aid = !empty($aid) ? $aid : session('handleAID');
        $where = [
            'aid' => $aid,
            'spec_is_select' => 1,
        ];
        // 快速编辑时执行
        if (!empty($quick)) {
            // 查询规格名称
            $order1 = 'spec_mark_id asc, spec_value_id asc';
            $spec = Db::name('product_spec_data')->where($where)->order($order1)->group('spec_mark_id')->getField('spec_mark_id, spec_name');
            // 查询规格值
            $order2 = 'spec_value_id desc';
            $specItem = Db::name('product_spec_data')->where($where)->order($order2)->getField('spec_value_id, spec_value, spec_mark_id');
            // 查询规格价格
            $specPrice = Db::name('product_spec_value')->where('aid', $aid)->order('spec_price asc')->getField('spec_value_id, value_id, spec_price, spec_crossed_price, spec_stock, spec_sales_num');
        } else {
            // 查询规格名称
            $order1 = 'spec_mark_id asc, spec_value_id asc';
            $spec = Db::name('product_spec_data_handle')->where($where)->order($order1)->group('spec_mark_id')->getField('spec_mark_id, spec_name');
            // 查询规格值
            $order2 = 'spec_value_id desc';
            $specItem = Db::name('product_spec_data_handle')->where($where)->order($order2)->getField('spec_value_id, spec_value, spec_mark_id');
            // 查询规格价格
            $specPrice = Db::name('product_spec_value_handle')->where('aid', $aid)->order('spec_price asc')->getField('spec_value_id, handle_id, spec_price, spec_crossed_price, spec_stock, spec_sales_num');
        }
        // 组合HTML
        $ReturnHtml  = "<table class='table table-bordered' id='spec_input_tab' border='1' cellpadding='10' cellspacing='10'><thead><tr>";
        // 显示第一行的数据
        foreach ($clo_name as $k => $v) {
            $ReturnHtml .= "<td><b><input type='hidden' class='spec_name_input_{$v}' name='spec_mark_id[$v][spec_name]' value='{$spec[$v]}'><span class='spec_name_span_{$v}'>{$spec[$v]}</span></b></td>";
        }
        $ReturnHtml .= "<td><b>价格 <a href=\"javascript:void(0);\" onclick=\"BulkSetPrice(this);\">批量设置 </a></b></td>";
        $ReturnHtml .= "<td><b>划线价 <a href=\"javascript:void(0);\" onclick=\"BulkSetCrossedPrice(this);\">批量设置 </a></b></td>";
        $ReturnHtml .= "<td><b>库存 <a href=\"javascript:void(0);\" onclick=\"BulkSetStock(this);\">批量设置 </a></b></td>";
        $ReturnHtml .= "<td><b>虚拟销量</b></td>";
        $ReturnHtml .= "</tr></thead><tbody>";

       // 显示第二行开始
       foreach ($spec_arr2 as $k => $v) {
            $ReturnHtml .= "<tr>";
            $spec_key_name = array();
            foreach($v as $k2 => $v2) {
                $ReturnHtml .= "<td><input type='hidden' class='spec_value_input_{$v2}' name='spec_value_id[$v2][spec_value]' value='{$specItem[$v2]['spec_value']}'><span class='spec_value_span_{$v2}'>{$specItem[$v2]['spec_value']}</span></td>";
                $spec_key_name[$v2] = $spec[$specItem[$v2]['spec_mark_id']].':'.$specItem[$v2]['spec_value'];
            }   
            ksort($spec_key_name);
            $spec_key = implode('_', array_keys($spec_key_name));

            if (!empty($quick)) {
                $ReturnHtml .="<input type='hidden' name='value_ids[$spec_key][value_id]' value='{$specPrice[$spec_key]['value_id']}'/></td>";
            } else {
                $ReturnHtml .="<input type='hidden' name='handle_ids[$spec_key][handle_id]' value='{$specPrice[$spec_key]['handle_id']}'/></td>";
            }

            $ReturnHtml .="<td><input type='text' class='users_price' name='spec_price[$spec_key][users_price]' value='{$specPrice[$spec_key]['spec_price']}'  onpaste='this.value=this.value.replace(/^\D*([0-9]\d*\.?\d{0,2})?.*$/, \"$1\");' onkeyup='this.value=this.value.replace(/^\D*([0-9]\d*\.?\d{0,2})?.*$/, \"$1\"); UpPrice(this);' onchange='setProductSpecPrice();'/></td>";

            $ReturnHtml .="<td><input type='text' class='crossed_price' name='spec_crossed_price[$spec_key][crossed_price]' value='{$specPrice[$spec_key]['spec_crossed_price']}'  onpaste='this.value=this.value.replace(/^\D*([0-9]\d*\.?\d{0,2})?.*$/, \"$1\");' onkeyup='this.value=this.value.replace(/^\D*([0-9]\d*\.?\d{0,2})?.*$/, \"$1\"); UpPrice(this);' onchange='setProductSpecPrice();'/></td>";

            $ReturnHtml .="<td><input type='text' class='stock_count' name='spec_stock[$spec_key][stock_count]' value='{$specPrice[$spec_key]['spec_stock']}' onkeyup='this.value=this.value.replace(/[^\d]/g,\"\");UpStock(this);' onpaste='this.value=this.value.replace(/[^\d]/g,\"\")' data-old_stock='{$specPrice[$spec_key]['spec_stock']}' onchange='setProductSpecPrice();'/></td>";

            $ReturnHtml .="<td><input type='text' name='spec_sales[$spec_key][spec_sales_num]' value='{$specPrice[$spec_key]['spec_sales_num']}' onkeyup='this.value=this.value.replace(/[^\d]/g,\"\")' onpaste='this.value=this.value.replace(/[^\d]/g,\"\")'></td>";

            $ReturnHtml .="</tr>";
        }
        $ReturnHtml .= "</tbody>";
        $ReturnHtml .= "</table>";
        // $ReturnHtml .= '批量设置：<a href="javascript:void(0);" onclick="BulkSetPrice(this);" >批量设置</a> <a href="javascript:void(0);" onclick="BulkSetStock(this);" >批量设置</a>';

        return $ReturnHtml;
    }

    /**
     * 2020/12/18 大黄 秒杀 多规格设置
     */
    public function SharpSpecAssemblyEdit($GetData = array(), $aid = null)
    {
        if (empty($GetData)) return ' ';
        // 参数处理
        foreach ($GetData as $k => $v) {
            $spec_arr_sort[$k] = count($v);
        }
        asort($spec_arr_sort);
        foreach ($spec_arr_sort as $key =>$val) {
            $spec_arr2[$key] = $GetData[$key];
        }
        // 排序
        $order = 'spec_value_id asc, spec_id asc, spec_mark_id asc';
        // 获取KEY值
        $clo_name  = array_keys($spec_arr2);
        // 数据组合
        $spec_arr2 = $this->DataCombination($spec_arr2);
        // 查询规格名称
        $spec = Db::name('product_spec_data')->where('aid',$aid)->order($order)->group('spec_mark_id')->getField('spec_mark_id, spec_name');
        // 查询规格值
        $specItem = Db::name('product_spec_data')->where('aid',$aid)->order($order)->getField('spec_value_id, spec_value, spec_mark_id');
        // 查询规格价格
        $specPrice = Db::name('product_spec_value')->where('aid', $aid)->order('spec_price asc')->getField('spec_value_id, spec_price, spec_stock, seckill_price, seckill_stock');
        // 组合HTML
        $ReturnHtml  = "<table class='table table-bordered' id='spec_input_tab' border='1' cellpadding='10' cellspacing='10'><thead><tr>";
        // 显示第一行的数据
        foreach ($clo_name as $k => $v) {
            $ReturnHtml .= "<td><b><input type='hidden' class='spec_name_input_{$v}' name='spec_mark_id[$v][spec_name]' value='{$spec[$v]}'><span class='spec_name_span_{$v}'>{$spec[$v]}</span></b></td>";
        }
        $ReturnHtml .= "<td><b>价格 </b></td>";
        $ReturnHtml .= "<td><b>库存 </b></td>";
        $ReturnHtml .= "<td><b><em class='red'>*</em>秒杀价格  <a href=\"javascript:void(0);\" onclick=\"BulkSetPrice(this);\">批量设置 </a></b></td>";
        $ReturnHtml .= "<td><b><em class='red'>*</em>秒杀库存  <a href=\"javascript:void(0);\" onclick=\"BulkSetStock(this);\" >批量设置 </a></b></td>";
        $ReturnHtml .= "</tr></thead><tbody>";

        // 显示第二行开始
        foreach ($spec_arr2 as $k => $v) {
            $ReturnHtml .= "<tr>";
            $spec_key_name = array();
            foreach($v as $k2 => $v2) {
                $ReturnHtml .= "<td><input type='hidden' class='spec_value_input_{$v2}' name='spec_value_id[$v2][spec_value]' value='{$specItem[$v2]['spec_value']}'><span class='spec_value_span_{$v2}'>{$specItem[$v2]['spec_value']}</span></td>";
                $spec_key_name[$v2] = $spec[$specItem[$v2]['spec_mark_id']].':'.$specItem[$v2]['spec_value'];
            }
            ksort($spec_key_name);
            $spec_key = implode('_', array_keys($spec_key_name));

            $ReturnHtml .="<td>{$specPrice[$spec_key]['spec_price']}<input type='hidden' class='users_price' name='spec_price[$spec_key][users_price]' value='{$specPrice[$spec_key]['spec_price']}' onpaste='this.value=this.value.replace(/[^\d.]/g,\"\")' onkeyup='this.value=this.value.replace(/[^\d.]/g,\"\");'/></td>";

            $ReturnHtml .="<td>{$specPrice[$spec_key]['spec_stock']}<input type='hidden' class='stock_count' name='spec_stock[$spec_key][stock_count]' value='{$specPrice[$spec_key]['spec_stock']}' onkeyup='this.value=this.value.replace(/[^\d.]/g,\"\");' onpaste='this.value=this.value.replace(/[^\d.]/g,\"\")' data-old_stock='{$specPrice[$spec_key]['spec_stock']}'/></td>";

            if ($specPrice[$spec_key]['seckill_price'] > 0){
                $seckill_price = $specPrice[$spec_key]['seckill_price'];
            }else{
                $seckill_price = '';
            }
            $ReturnHtml .="<td><input class='spec_seckill_price' name='seckill_price[$spec_key][spec_seckill_price]' value='{$seckill_price}' onpaste='this.value=this.value.replace(/[^\d.]/g,\"\")' onkeyup='this.value=this.value.replace(/[^\d.]/g,\"\");'/></td>";
            if ($specPrice[$spec_key]['seckill_stock'] > 0){
                $seckill_stock = $specPrice[$spec_key]['seckill_stock'];
            }else{
                $seckill_stock = '';
            }
            $ReturnHtml .="<td><input class='spec_seckill_stock' name='seckill_stock[$spec_key][spec_seckill_stock]' value='{$seckill_stock}' onkeyup='this.value=this.value.replace(/[^\d.]/g,\"\");' onpaste='this.value=this.value.replace(/[^\d.]/g,\"\")' data-old_stock='{$specPrice[$spec_key]['seckill_stock']}'/></td>";

            $ReturnHtml .="</tr>";
        }
        $ReturnHtml .= "</tbody>";
        $ReturnHtml .= "</table>";
        // $ReturnHtml .= '批量设置：<a href="javascript:void(0);" onclick="BulkSetPrice(this);" >批量设置</a> <a href="javascript:void(0);" onclick="BulkSetStock(this);" >批量设置</a>';

        return $ReturnHtml;
    }

    /**
     * 2022/03/08 大黄 限时折扣 多规格设置
     */
    public function DiscountSpecAssemblyEdit($GetData = array(), $aid = null)
    {
        if (empty($GetData)) return ' ';
        // 参数处理
        foreach ($GetData as $k => $v) {
            $spec_arr_sort[$k] = count($v);
        }
        asort($spec_arr_sort);
        foreach ($spec_arr_sort as $key =>$val) {
            $spec_arr2[$key] = $GetData[$key];
        }
        // 排序
        $order = 'spec_value_id asc, spec_id asc, spec_mark_id asc';
        // 获取KEY值
        $clo_name  = array_keys($spec_arr2);
        // 数据组合
        $spec_arr2 = $this->DataCombination($spec_arr2);
        // 查询规格名称
        $spec = Db::name('product_spec_data')->where('aid',$aid)->order($order)->group('spec_mark_id')->getField('spec_mark_id, spec_name');
        // 查询规格值
        $specItem = Db::name('product_spec_data')->where('aid',$aid)->order($order)->getField('spec_value_id, spec_value, spec_mark_id');
        // 查询规格价格
        $specPrice = Db::name('product_spec_value')->where('aid', $aid)->order('spec_price asc')->getField('spec_value_id, spec_price, spec_stock, discount_price, discount_stock');
        // 组合HTML
        $ReturnHtml  = "<table class='table table-bordered' id='spec_input_tab' border='1' cellpadding='10' cellspacing='10'><thead><tr>";
        // 显示第一行的数据
        foreach ($clo_name as $k => $v) {
            $ReturnHtml .= "<td><b><input type='hidden' class='spec_name_input_{$v}' name='spec_mark_id[$v][spec_name]' value='{$spec[$v]}'><span class='spec_name_span_{$v}'>{$spec[$v]}</span></b></td>";
        }
        $ReturnHtml .= "<td><b>价格 </b></td>";
        $ReturnHtml .= "<td><b>库存 </b></td>";
        $ReturnHtml .= "<td><b><em class='red'>*</em>限时折扣价格  <a href=\"javascript:void(0);\" onclick=\"BulkSetPrice(this);\">批量设置 </a></b></td>";
        $ReturnHtml .= "<td><b><em class='red'>*</em>限时折扣库存  <a href=\"javascript:void(0);\" onclick=\"BulkSetStock(this);\" >批量设置 </a></b></td>";
        $ReturnHtml .= "</tr></thead><tbody>";

        // 显示第二行开始
        foreach ($spec_arr2 as $k => $v) {
            $ReturnHtml .= "<tr>";
            $spec_key_name = array();
            foreach($v as $k2 => $v2) {
                $ReturnHtml .= "<td><input type='hidden' class='spec_value_input_{$v2}' name='spec_value_id[$v2][spec_value]' value='{$specItem[$v2]['spec_value']}'><span class='spec_value_span_{$v2}'>{$specItem[$v2]['spec_value']}</span></td>";
                $spec_key_name[$v2] = $spec[$specItem[$v2]['spec_mark_id']].':'.$specItem[$v2]['spec_value'];
            }
            ksort($spec_key_name);
            $spec_key = implode('_', array_keys($spec_key_name));

            $ReturnHtml .="<td>{$specPrice[$spec_key]['spec_price']}<input type='hidden' class='users_price' name='spec_price[$spec_key][users_price]' value='{$specPrice[$spec_key]['spec_price']}' onpaste='this.value=this.value.replace(/[^\d.]/g,\"\")' onkeyup='this.value=this.value.replace(/[^\d.]/g,\"\");'/></td>";

            $ReturnHtml .="<td>{$specPrice[$spec_key]['spec_stock']}<input type='hidden' class='stock_count' name='spec_stock[$spec_key][stock_count]' value='{$specPrice[$spec_key]['spec_stock']}' onkeyup='this.value=this.value.replace(/[^\d.]/g,\"\");' onpaste='this.value=this.value.replace(/[^\d.]/g,\"\")' data-old_stock='{$specPrice[$spec_key]['spec_stock']}'/></td>";

            if ($specPrice[$spec_key]['discount_price'] > 0){
                $discount_price = $specPrice[$spec_key]['discount_price'];
            }else{
                $discount_price = '';
            }
            $ReturnHtml .="<td><input class='spec_discount_price' name='discount_price[$spec_key][spec_discount_price]' value='{$discount_price}' onpaste='this.value=this.value.replace(/[^\d.]/g,\"\")' onkeyup='this.value=this.value.replace(/[^\d.]/g,\"\");'/></td>";
            if ($specPrice[$spec_key]['discount_stock'] > 0){
                $discount_stock = $specPrice[$spec_key]['discount_stock'];
            }else{
                $discount_stock = '';
            }
            $ReturnHtml .="<td><input class='spec_discount_stock' name='discount_stock[$spec_key][spec_discount_stock]' value='{$discount_stock}' onkeyup='this.value=this.value.replace(/[^\d.]/g,\"\");' onpaste='this.value=this.value.replace(/[^\d.]/g,\"\")' data-old_stock='{$specPrice[$spec_key]['discount_stock']}'/></td>";

            $ReturnHtml .="</tr>";
        }
        $ReturnHtml .= "</tbody>";
        $ReturnHtml .= "</table>";
        // $ReturnHtml .= '批量设置：<a href="javascript:void(0);" onclick="BulkSetPrice(this);" >批量设置</a> <a href="javascript:void(0);" onclick="BulkSetStock(this);" >批量设置</a>';

        return $ReturnHtml;
    }

    // 数据组合
    private function DataCombination()
    {
        $data   = func_get_args();
        $data   = current($data);
        $result = array();
        $arr1   = array_shift($data);
        foreach($arr1 as $key => $item) {
            $result[] = array($item);
        }       

        foreach($data as $key => $item) {                                
            $result = $this->DataCombinationArray($result, $item);
        }
        return $result;
    }

    private function DataCombinationArray($arr1, $arr2)
    {
        $result = array();
        foreach ($arr1 as $item1) {
            foreach ($arr2 as $item2) {
                $temp     = $item1;
                $temp[]   = $item2;
                $result[] = $temp;
            }
        }
        return $result;
    }

    // 添加自定义规格
    public function addProductCustomSpec($post = [])
    {
        // 最大ID值
        // $spec_mark_id = Db::name('product_spec_data_handle')->max('spec_mark_id');
        // if (empty($spec_mark_id)) $spec_mark_id = Db::name('product_spec_data')->max('spec_mark_id');
        $spec_mark_id = date('s') . rand(0, 9);
        $spec_value_id = Db::name('product_spec_data_handle')->max('spec_value_id');
        if (empty($spec_value_id)) $spec_value_id = Db::name('product_spec_data')->max('spec_value_id');

        // 数据处理
        $time = getTime();
        $handleAID = $handleSpecID = date('His');
        if (session('handleAID')) $handleAID = session('handleAID');
        $handleSpecID = $handleSpecID . rand(0, 9);
        $handleAID = !empty($post['aid']) ? $post['aid'] : $handleAID;
        if ('value' === strval($post['action']) && !empty($post['spec_mark_id'])) {
            // 添加的数据
            $where = [
                'aid' => $handleAID,
                'spec_mark_id' => $post['spec_mark_id'],
            ];
            $specName = Db::name('product_spec_data_handle')->where($where)->getField('spec_name');
            $insert = [
                'spec_id'        => intval($handleSpecID),
                'aid'            => $handleAID,
                'spec_mark_id'   => $post['spec_mark_id'],
                'spec_name'      => $specName,
                'spec_value_id'  => ++$spec_value_id,
                'spec_value'     => '',
                'spec_is_select' => 1,
                'lang'           => get_admin_lang(),
                'add_time'       => $time,
                'update_time'    => $time,
            ];
            $spec_mark_id = $post['spec_mark_id'];
        } else if ('name' === strval($post['action'])) {
            // 添加的数据
            $insert = [
                'spec_id'        => intval($handleSpecID),
                'aid'            => $handleAID,
                'spec_mark_id'   => ++$spec_mark_id,
                'spec_name'      => '',
                'spec_value_id'  => ++$spec_value_id,
                'spec_value'     => '',
                'spec_is_select' => 1,
                'lang'           => get_admin_lang(),
                'add_time'       => $time,
                'update_time'    => $time,
            ];
        } else if ('specName' === strval($post['action'])) {
            // 添加的数据
            $where = [
                'preset_mark_id' => $post['preset_mark_id'],
            ];
            $specPreset = Db::name('product_spec_preset')->where($where)->select();
            $insertAll = [];
            foreach ($specPreset as $key => $value) {
                $handleSpecID++;
                // 添加的数据
                $insertAll[] = [
                    'spec_id'        => intval($handleSpecID),
                    'aid'            => $handleAID,
                    'spec_mark_id'   => $value['preset_mark_id'],
                    'spec_name'      => $value['preset_name'],
                    'spec_value_id'  => $value['preset_id'],
                    'spec_value'     => $value['preset_value'],
                    'spec_is_select' => 0,
                    'lang'           => get_admin_lang(),
                    'add_time'       => $time,
                    'update_time'    => $time,
                ];
            }
            $spec_mark_id = $post['preset_mark_id'];
        } else if ('specValue' === strval($post['action'])) {
            $where = [
                'aid' => $handleAID,
                'spec_mark_id' => intval($post['spec_mark_id']),
                'spec_value_id' => intval($post['spec_value_id']),
            ];
            $update = [
                'spec_is_select' => 1,
                'update_time' => $time,
            ];
            $spec_mark_id = $post['spec_mark_id'];
        }

        // 执行添加
        if (!empty($insertAll)) {
            $resultID = Db::name('product_spec_data_handle')->insertAll($insertAll);
        } else if (!empty($insert)) {
            $resultID = Db::name('product_spec_data_handle')->insert($insert);
        } else if (!empty($where) && !empty($update)) {
            $resultID = Db::name('product_spec_data_handle')->where($where)->update($update);
        }
        // 添加后续操作
        if (!empty($resultID)) {
            // 查询商品的规格信息
            $where = [
                'aid' => $handleAID,
                'spec_is_select' => 1,
            ];
            $field = 'spec_mark_id, spec_value_id';
            $order = 'spec_value_id asc, spec_id asc';
            $data = Db::name('product_spec_data_handle')->where($where)->field($field)->order($order)->select();

            // 处理规格数组
            $spec_array = [];
            if (!empty($data)) {
                foreach ($data as $key => $value) {
                    $spec_array[$value['spec_mark_id']][] = $value['spec_value_id'];
                }
            }

            // 处理规格标记ID串
            $spec_mark_id_arr = implode(',', array_keys($spec_array));

            // 规格数据库表的自定义aid存入
            session('handleAID', $handleAID);

            // 处理废弃规格ID，刷新商品添加页时执行删除废弃规格
            $del_spec = session('del_spec') ? session('del_spec') : [];
            array_push($del_spec, $spec_mark_id);
            session('del_spec', $del_spec);

            // 返回结束
            return [
                'spec_array' => $spec_array,
                'spec_mark_id' => $spec_mark_id,
                'spec_value_id' => $spec_value_id,
                'spec_mark_id_arr' => $spec_mark_id_arr
            ];
        }
    }

    // 获取商品规格名称值下拉框
    public function getProductSpecValueOption($specMarkID = 0, $post = [])
    {
        $handleAID = session('handleAID');
        $handleAID = !empty($post['aid']) ? $post['aid'] : $handleAID;
        if (!empty($post['del'])) {
            if ('specName' === strval($post['del'])) {
                $where = [
                    'aid' => $handleAID,
                    'lang' => get_admin_lang(),
                ];
                $specMarkIDarr = Db::name('product_spec_data_handle')->where($where)->column('spec_mark_id');
                $specMarkIDarr = array_unique($specMarkIDarr);
                $where = [
                    'lang' => get_admin_lang(),
                    'preset_mark_id' => ['NOT IN', $specMarkIDarr],
                ];
                $field = 'preset_id, preset_mark_id, preset_name';
                $PresetName = Db::name('product_spec_preset')->where($where)->field($field)->group('preset_mark_id')->order('preset_mark_id desc')->select();
                $preset_name_option .= "<option value='0'>从规格库提取</option>";
                if (!empty($PresetName)) {
                    // 拼装下拉选项
                    foreach ($PresetName as $value) {
                        $preset_name_option .= "<option value='{$value['preset_mark_id']}'>{$value['preset_name']}</option>";
                    }
                }
            } else if ('specValue' === strval($post['del'])) {
                $where = [
                    'aid' => $handleAID,
                    'lang' => get_admin_lang(),
                    'spec_mark_id' => intval($post['spec_mark_id']),
                ];
                $specDataHandle = Db::name('product_spec_data_handle')->where($where)->field('spec_is_select, spec_value_id, spec_name, spec_value')->select();
                $spec_value_option .= "<option value='0'>选择规格值</option>";
                foreach ($specDataHandle as $value) {
                    if (0 === intval($value['spec_is_select'])) $spec_value_option .= "<option value='{$value['spec_value_id']}'>{$value['spec_value']}</option>";
                }
            }
            return [
                'spec_value_option' => !empty($spec_value_option) ? $spec_value_option : '',
                'preset_name_option' => !empty($preset_name_option) ? $preset_name_option : '',
            ];
        } else if (!empty($post['action'])) {
            $where = [
                'aid' => $handleAID,
                'lang' => get_admin_lang(),
                'spec_mark_id' => intval($specMarkID),
            ];
            $specDataHandle = Db::name('product_spec_data_handle')->where($where)->field('spec_is_select, spec_value_id, spec_name, spec_value')->select();
            $spec_value = '';
            $spec_value_option .= "<option value='0'>选择规格值</option>";
            foreach ($specDataHandle as $value) {
                if (0 === intval($value['spec_is_select'])) $spec_value_option .= "<option value='{$value['spec_value_id']}'>{$value['spec_value']}</option>";
                if (1 === intval($value['spec_is_select']) && intval($post['spec_value_id']) === intval($value['spec_value_id'])) {
                    $spec_value = $value['spec_value'];
                }
            }

            if ('specName' === strval($post['action'])) {
                $where = [
                    'aid' => $handleAID,
                    'lang' => get_admin_lang(),
                ];
                $specMarkIDarr = Db::name('product_spec_data_handle')->where($where)->column('spec_mark_id');
                $specMarkIDarr = array_unique($specMarkIDarr);
                $where = [
                    'lang' => get_admin_lang(),
                    'preset_mark_id' => ['NOT IN', $specMarkIDarr],
                ];
                $field = 'preset_id, preset_mark_id, preset_name';
                $PresetName = Db::name('product_spec_preset')->where($where)->field($field)->group('preset_mark_id')->order('preset_mark_id desc')->select();
                $preset_name_option .= "<option value='0'>从规格库提取</option>";
                if (!empty($PresetName)) {
                    // 拼装下拉选项
                    foreach ($PresetName as $value) {
                        $preset_name_option .= "<option value='{$value['preset_mark_id']}'>{$value['preset_name']}</option>";
                    }
                }
            }

            return [
                'spec_value' => !empty($spec_value) ? $spec_value : '',
                'spec_value_option' => !empty($spec_value_option) ? $spec_value_option : '',
                'preset_name_option' => !empty($preset_name_option) ? $preset_name_option : '',
                'spec_name' => !empty($specDataHandle[0]['spec_name']) ? $specDataHandle[0]['spec_name'] : '',
            ];
        }
    }

    // 添加自定义规格名称
    public function addProductCustomSpecName($post = [])
    {
        if (!empty($post['spec_mark_id']) && !empty($post['set_spec_name'])) {
            $time = getTime();
            $handleAID = session('handleAID');
            $handleAID = !empty($post['aid']) ? $post['aid'] : $handleAID;
            $where = [
                'aid' => $handleAID,
                'spec_mark_id' => intval($post['spec_mark_id']),
            ];
            $update = [
                'spec_name' => strval($post['set_spec_name']),
                'update_time' => $time,
            ];
            $updateID = Db::name('product_spec_data_handle')->where($where)->update($update);
            if (!empty($updateID)) {
                // 查询商品的规格信息
                $where = [
                    'aid' => $handleAID,
                    'spec_is_select' => 1,
                ];
                $field = 'spec_mark_id, spec_value_id';
                $order = 'spec_value_id asc, spec_id asc';
                $data = Db::name('product_spec_data_handle')->field($field)->where($where)->order($order)->select();

                // 处理规格数组
                $spec_array = [];
                if (!empty($data)) {
                    foreach ($data as $key => $value) {
                        $spec_array[$value['spec_mark_id']][] = $value['spec_value_id'];
                    }
                }

                // 返回结束
                return $spec_array;
            }
        }
    }

    // 添加自定义规格值
    public function addProductCustomSpecValue($post = [])
    {
        if (!empty($post['spec_mark_id']) && !empty($post['spec_value_id']) && !empty($post['set_spec_value'])) {
            $time = getTime();
            $handleAID = session('handleAID');
            $handleAID = !empty($post['aid']) ? $post['aid'] : $handleAID;
            $where = [
                'aid' => $handleAID,
                'spec_mark_id' => intval($post['spec_mark_id']),
                'spec_value_id' => intval($post['spec_value_id']),
            ];
            $update = [
                'spec_value' => strval($post['set_spec_value']),
                'update_time' => $time,
            ];
            $updateID = Db::name('product_spec_data_handle')->where($where)->update($update);
            if (!empty($updateID)) {
                // 查询商品的规格信息
                $where = [
                    'aid' => $handleAID,
                    'spec_is_select' => 1,
                ];
                $field = 'spec_mark_id, spec_value_id';
                $order = 'spec_value_id asc, spec_id asc';
                $data = Db::name('product_spec_data_handle')->field($field)->where($where)->order($order)->select();

                // 处理规格数组
                $spec_array = [];
                if (!empty($data)) {
                    foreach ($data as $key => $value) {
                        $spec_array[$value['spec_mark_id']][] = $value['spec_value_id'];
                    }
                }

                // 返回结束
                return $spec_array;
            }
        }
    }

    // 删除自定义规格
    public function delProductCustomSpec($post = [])
    {
        $handleAID = !empty($post['aid']) ? $post['aid'] : session('handleAID');
        // 清除整条规格信息
        if (in_array($post['del'], ['name', 'specName'])) {
            if (!empty($post['spec_mark_id'])) {
                $where = [
                    'aid' => $handleAID,
                    'spec_mark_id' => intval($post['spec_mark_id']),
                ];
                $deleteID = Db::name('product_spec_data_handle')->where($where)->delete(true);
            }
        }
        // 清除单个规格信息
        else if (in_array($post['del'], ['value'])) {
            if (!empty($post['spec_mark_id']) && !empty($post['spec_value_id'])) {
                $where = [
                    'aid' => $handleAID,
                    'spec_mark_id' => intval($post['spec_mark_id']),
                    'spec_value_id' => intval($post['spec_value_id']),
                ];
                $deleteID = Db::name('product_spec_data_handle')->where($where)->delete(true);
            }
        }
        // 清除单个规格信息
        else if (in_array($post['del'], ['specValue'])) {
            if (!empty($post['spec_mark_id']) && !empty($post['spec_value_id'])) {
                $where = [
                    'aid' => $handleAID,
                    'spec_mark_id' => intval($post['spec_mark_id']),
                    'spec_value_id' => intval($post['spec_value_id']),
                ];
                $update = [
                    'spec_is_select' => 0,
                    'update_time' => getTime(),
                ];
                $deleteID = Db::name('product_spec_data_handle')->where($where)->update($update);
            }
        }

        if (!empty($deleteID)) {
            // 查询商品的规格信息
            $where = [
                'aid' => $handleAID,
                'spec_is_select' => 1,
            ];
            $field = 'spec_mark_id, spec_value_id';
            $order = 'spec_value_id asc, spec_id asc';
            $data = Db::name('product_spec_data_handle')->field($field)->where($where)->order($order)->select();

            // 处理规格数组
            $spec_array = [];
            if (!empty($data)) {
                foreach ($data as $key => $value) {
                    $spec_array[$value['spec_mark_id']][] = $value['spec_value_id'];
                }
            }

            // 返回结束
            return $spec_array;
        }
    }
}
