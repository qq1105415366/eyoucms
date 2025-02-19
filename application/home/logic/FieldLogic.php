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

namespace app\home\logic;

use think\Model;
use think\Db;
/**
 * 字段逻辑定义
 * Class CatsLogic
 * @package home\Logic
 */
class FieldLogic extends Model
{
    /**
     * 查询解析模型数据用以页面展示
     * @param array $data 表数据
     * @param intval $channel_id 模型ID
     * @param array $batch 是否批量列表
     * @author 小虎哥 by 2018-7-25
     */
    public function getChannelFieldList($data, $channel_id = '', $batch = false, $is_minipro = false)
    {
        if (!empty($data) && !empty($channel_id)) {
            /*获取模型对应的附加表字段信息*/
            $map = array(
                'channel_id'    => $channel_id,
            );
            $fieldInfo = model('Channelfield')->getListByWhere($map, '*', 'name');
            /*--end*/
            $data = $this->handleAddonFieldList($data, $fieldInfo, $batch, $is_minipro);
        } else {
            $data = array();
        }
        
        return $data;
    }

    /**
     * 查询解析单个数据表的数据用以页面展示
     * @param array $data 表数据
     * @param intval $channel_id 模型ID
     * @param array $batch 是否批量列表
     * @author 小虎哥 by 2018-7-25
     */
    public function getTableFieldList($data, $channel_id = '', $batch = false)
    {
        if (!empty($data) && !empty($channel_id)) {
            /*获取自定义表字段信息*/
            $map = array(
                'channel_id'    => $channel_id,
            );
            $fieldInfo = model('Channelfield')->getListByWhere($map, '*', 'name');
            /*--end*/
            $data = $this->handleAddonFieldList($data, $fieldInfo, $batch);
        } else {
            $data = array();
        }

        return $data;
    }

    /**
     * 处理自定义字段的值
     * @param array $data 表数据
     * @param array $fieldInfo 自定义字段集合
     * @param array $batch 是否批量列表
     * @author 小虎哥 by 2018-7-25
     */
    public function handleAddonFieldList($data, $fieldInfo, $batch = false, $is_minipro = false)
    {
        if (false !== $batch) {
            return $this->handleBatchAddonFieldList($data, $fieldInfo);
        }

        if (!empty($data) && !empty($fieldInfo)) {
            static $city_list = null;
            static $province_list = null;
            static $area_list = null;
            $baseTag = new \think\template\taglib\api\Base;
            foreach ($data as $key => $val) {
                $dtype = !empty($fieldInfo[$key]) ? $fieldInfo[$key]['dtype'] : '';
                if (empty($dtype)) {
                    continue;
                }
                $dfvalue_unit = !empty($fieldInfo[$key]) ? $fieldInfo[$key]['dfvalue_unit'] : '';
                switch ($dtype) {
                    case 'int':
                    case 'float':
                    case 'text':
                    {
                        if (preg_match('/^(\/[\/\w]+)?(\/public\/static\/|\/uploads\/)(.*)\.(\w+)$/i', $val)) {
                            $val = handle_subdir_pic($val);
                        }
                        !empty($dfvalue_unit) && $data[$key.'_unit'] = $dfvalue_unit;
                        break;
                    }

                    case 'imgs':
                    {
                        if (!is_array($val)) {
                            $eyou_imgupload_list = @unserialize($val);
                            if (false === $eyou_imgupload_list) {
                                $eyou_imgupload_list = [];
                                $eyou_imgupload_data = explode(',', $val);
                                foreach ($eyou_imgupload_data as $k1 => $v1) {
                                    if (!empty($v1)) {
                                        $eyou_imgupload_list[$k1] = [
                                            'image_url' => (true === $is_minipro) ? $baseTag->get_default_pic($v1) : handle_subdir_pic($v1),
                                            'intro'     => '',
                                        ];
                                    }
                                }
                            }
                        } else {
                            $eyou_imgupload_list = [];
                            $eyou_imgupload_data = $val;
                            foreach ($eyou_imgupload_data as $k1 => $v1) {
                                if (!empty($v1['image_url'])) {
                                    $v1['image_url'] = (true === $is_minipro) ? $baseTag->get_default_pic($v1['image_url']) : handle_subdir_pic($v1['image_url']);
                                    isset($v1['intro']) && $v1['intro'] = htmlspecialchars_decode($v1['intro']);
                                    $eyou_imgupload_list[$k1] = $v1;
                                }
                            }
                        }
                        !empty($eyou_imgupload_list) && $eyou_imgupload_list = array_values($eyou_imgupload_list);
                        $val = $eyou_imgupload_list;
                        break;
                    }
                    case 'img':
                        {
                            $val = (true === $is_minipro) ? $baseTag->get_default_pic($val) : handle_subdir_pic($val);
                            break;
                        }
                    case 'media':
                    {
                        $val = handle_subdir_pic($val,'media');
                        break;
                    }
                    case 'file':
                        {
                            $data[$key.'_filename'] = '';
                            if (stristr($val, '|')) {
                                $arr = explode('|', $val);
                                $val = $arr[0];
                                $data[$key.'_filename'] = empty($arr[1]) ? '' : $arr[1];
                                $val = ROOT_DIR . "/index.php?m=home&c=View&a=custom_download_file&aid={$data['aid']}&field=".mchStrCode($key, 'ENCODE', $data['aid'].get_auth_code());
                            } else {
                                $val = handle_subdir_pic($val);
                            }
                            break;
                        }
                    case 'checkbox':
                    case 'files':
                    {
                        if (!is_array($val)) {
                            $val = !empty($val) ? explode(',', $val) : array();
                        }
                        foreach ($val as $k1 => $v1) {
                            $val[$k1] = handle_subdir_pic($v1);
                        }
                        break;
                    }

                    case 'htmltext':
                    {
                        $val = htmlspecialchars_decode($val);
                        
                        // 多城市分站的内容变量替换
                        if (config('city_switch_on')) {
                            $searchs = ['{site_name}'];
                            $site_info = cookie('site_info');
                            $site_info = empty($site_info) ? [] : (array)json_decode($site_info);
                            if (!empty($site_info)) {
                                $replaces = [$site_info['name']];
                            } else {
                                $replaces = [''];
                            }
                            $val = str_replace($searchs, $replaces, $val);
                        }

                        /*追加指定内嵌样式到编辑器内容的img标签，兼容图片自动适应页面*/
                        $titleNew = !empty($data['title']) ? $data['title'] : '';
                        $val = img_style_wh($val, $titleNew);
                        /*--end*/

                        /*支持子目录*/
                        $val = handle_subdir_pic($val, 'html');
                        /*--end*/

                        if (true === $is_minipro) {
                            $val = $baseTag->html_httpimgurl($val);
                        }

                        break;
                    }

                    case 'decimal':
                    {
                        $val = floatval($val);
                        // $val = number_format($val,'2','.',',');
                        break;
                    }

                    case 'region':
                    {
                        // 先在默认值里寻找是否存在对应区域ID的名称
                        $dfvalue = !empty($fieldInfo[$key]['dfvalue']) ? $fieldInfo[$key]['dfvalue'] : '';
                        if (!empty($dfvalue)) {
                            $dfvalue_tmp = unserialize($dfvalue);
                            $region_ids = !empty($dfvalue_tmp['region_ids']) ? explode(',', $dfvalue_tmp['region_ids']) : [];
                            if (!empty($region_ids)) {
                                $arr_index = array_search($val, $region_ids);
                                if (false !== $arr_index && 0 <= $arr_index) {
                                    $dfvalue_tmp['region_names'] = str_replace('，', ',', $dfvalue_tmp['region_names']);
                                    $region_names = explode(',', $dfvalue_tmp['region_names']);
                                    $val = $region_names[$arr_index];
                                }
                            }
                        }
                        // 默认值里不存在，则去区域表里获取
                        if (!empty($val) && preg_match('/^([\d\,]+)$/i', $val)) {
                            $val_new = '';
                            $val_arr = explode(',', $val);
                            foreach ($val_arr as $_k => $_v) {
                                null === $city_list && $city_list = get_city_list();
                                if (!empty($city_list[$_v])) {
                                    $val_new .= $city_list[$_v]['name'];
                                } else {
                                    null === $province_list && $province_list = get_province_list();
                                    if (!empty($province_list[$_v])) {
                                        $val_new .= $province_list[$_v]['name'];
                                    } else {
                                        null === $area_list && $area_list = get_area_list();
                                        $val_new .= !empty($area_list[$_v]) ? $area_list[$_v]['name'] : '';
                                    }
                                }
                            }
                            $val = $val_new;
                        }
                        break;
                    }
                    
                    default:
                    {
                        /*支持子目录*/
                        if (is_string($val)) {
                            $val = handle_subdir_pic($val, 'html');
                            $val = handle_subdir_pic($val);
                        }
                        /*--end*/
                        break;
                    }
                }
                $data[$key] = $val;
            }
            //移动端详情
            if (isMobile() && isset($data['content']) && !empty($data['content_ey_m'])) {
                $data['content'] = $data['content_ey_m'];
            }
        }
        return $data;
    }

    /**
     * 列表批量处理自定义字段的值
     * @param array $data 表数据
     * @param array $fieldInfo 自定义字段集合
     * @author 小虎哥 by 2018-7-25
     */
    public function handleBatchAddonFieldList($data, $fieldInfo)
    {
        if (!empty($data) && !empty($fieldInfo)) {
            foreach ($data as $key => $subdata) {
                $data[$key] = $this->handleAddonFieldList($subdata, $fieldInfo);
            }
        }
        return $data;
    }
}
