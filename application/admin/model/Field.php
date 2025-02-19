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
namespace app\admin\model;

use think\Db;
use think\Model;

/**
 * 字段
 */
class Field extends Model
{
    //初始化
    protected function initialize()
    {
        // 需要调用`Model`的`initialize`方法
        parent::initialize();
    }

    /**
     * 获取全部字段类型
     * @author 小虎哥 by 2018-7-25
     */
    public function getFieldTypeAll($field = '*', $index_key = '')
    {
        $cacheKey = md5("admin-Field-getFieldTypeAll-{$field}-{$index_key}");
        $result = cache($cacheKey);
        if (!empty($result)) {
            return $result;
        }

        $result = Db::name('FieldType')->field($field)->order('sort_order asc')->select();

        if (!empty($index_key)) {
            $result = convert_arr_key($result, $index_key);
        }
        cache($cacheKey, $result, null, 'field_type');

        return $result;
    }

    /**
     * 查询解析模型数据用以构造from表单
     * @param intval $channel_id 模型ID
     * @param intval $ifmain 是否主表、附加表
     * @param intval $aid 表主键ID
     * @param array $archivesInfo 主表数据
     * @author 小虎哥 by 2018-7-25
     */
    public function getChannelFieldList($channel_id, $ifmain = false, $aid = '', $archivesInfo = [], $where = [])
    {
        $hideField = array('id','aid','add_time','update_time'); // 不显示在发布表单的字段
        $channel_id = intval($channel_id);
        $map = array(
            'channel_id'    => array('eq', $channel_id),
            'name'          => array('notin', $hideField),
            'ifmain'        => 0,
            'ifeditable'    => 1,
        );
        if (false !== $ifmain) {
            $map['ifmain'] = $ifmain;
        }
        $map = array_merge($map, $where);
        $row = model('Channelfield')->getListByWhere($map, '*');

        /*编辑时显示的数据*/
        $addonRow = array();
        if (0 < intval($aid)) {
            if (1 == $channel_id) {
                $tableExt = 'article';
            } else if (2 == $channel_id) {
                $tableExt = 'product';
            } else if (3 == $channel_id) {
                $tableExt = 'images';
            } else if (4 == $channel_id) {
                $tableExt = 'download';
            } else if (5 == $channel_id) {
                $tableExt = 'media';
            } else if (6 == $channel_id) {
                $tableExt = 'single';
                $aid = Db::name('archives')->where(array('typeid'=>$aid, 'channel'=>$channel_id))->getField('aid');
            } else if (7 == $channel_id) {
                $tableExt = 'special';
            } else {
                $tableExt = Db::name('channeltype')->where('id', $channel_id)->getField('table');
            }
            $tableExt .= '_content';
            $addonRow = Db::name($tableExt)->field('*')->where('aid', $aid)->find();
        }
        /*--end*/

        $list = $this->showViewFormData($row, 'addonFieldExt', $addonRow, $archivesInfo);
        return $list;
    }

    /**
     * 查询解析数据表的数据用以构造from表单
     * @param intval $channel_id 模型ID
     * @param intval $id 表主键ID
     * @author 小虎哥 by 2018-7-25
     */
    public function getTabelFieldList($channel_id, $id = '')
    {
        $hideField = array('id','aid','add_time','update_time'); // 不显示在发布表单的字段
        $channel_id = intval($channel_id);
        $map = array(
            'channel_id'    => array('eq', $channel_id),
            'name'          => array('notin', $hideField),
            'ifsystem'      => 0,
        );
        $row = model('Channelfield')->getListByWhere($map, '*');

        /*编辑时显示的数据*/
        $addonRow = array();
        if (0 < intval($id)) {
            if (config('global.arctype_channel_id') == $channel_id) {
                $addonRow = Db::name('arctype')->field('*')->where('id', $id)->find();
            }
        }
        /*--end*/

        $list = $this->showViewFormData($row, 'addonField', $addonRow);
        return $list;
    }

    /**
     * 处理页面显示自定义字段的表单数据
     * @param array $list 自定义字段列表
     * @param array $formFieldStr 表单元素名称的统一数组前缀
     * @param array $addonRow 自定义字段的数据
     * @param array $archivesInfo 主表数据
     * @author 小虎哥 by 2018-7-25
     */
    public function showViewFormData($list, $formFieldStr, $addonRow = array(), $archivesInfo = [])
    {
        if (!empty($list)) {
            if (is_dir('./weapp/CompressContent/')) {                                            
                $compressLogic = new \weapp\CompressContent\logic\CompressContentLogic;                
            }   
            foreach ($list as $key => $val) {
                $val['fieldArr'] = $formFieldStr;
                switch ($val['dtype']) {
                    case 'int':
                    {
                        if (array_key_exists($val['name'], $addonRow)) {
                            $val['dfvalue'] = $addonRow[$val['name']];
                        } else {
                            if(preg_match("#[^0-9]#", $val['dfvalue']))
                            {
                                $val['dfvalue'] = "";
                            }
                        }
                        break;
                    }

                    case 'float':
                    case 'decimal':
                    {
                        if (array_key_exists($val['name'], $addonRow)) {
                            $val['dfvalue'] = $addonRow[$val['name']];
                        } else {
                            if(preg_match("#[^0-9\.]#", $val['dfvalue']))
                            {
                                $val['dfvalue'] = "";
                            }
                        }
                        break;
                    }

                    case 'select':
                    case 'radio':
                    {
                        $dfvalue = $val['dfvalue'];
                        $dfvalueArr = explode(',', $dfvalue);
                        $val['dfvalue'] = $dfvalueArr;
                        if (array_key_exists($val['name'], $addonRow)) {
                            $val['trueValue'] = explode(',', $addonRow[$val['name']]);
                        } else {
                            $dfTrueValue = !empty($dfvalueArr[0]) ? $dfvalueArr[0] : '';
                            $val['trueValue'] = array($dfTrueValue);
                        }
                        break;
                    }

                    case 'region':
                    {
                        $dfvalue    = unserialize($val['dfvalue']);
                        $RegionData = [];
                        $region_ids = explode(',', $dfvalue['region_ids']);
                        foreach ($region_ids as $id_key => $id_value) {
                            $RegionData[$id_key]['id'] = $id_value;
                        }
                        $region_names = explode('，', $dfvalue['region_names']);
                        foreach ($region_names as $name_key => $name_value) {
                            $RegionData[$name_key]['name'] = $name_value;
                        }

                        $val['dfvalue'] = $RegionData;
                        if (array_key_exists($val['name'], $addonRow)) {
                            $val['trueValue'] = explode(',', $addonRow[$val['name']]);
                        } else {
                            if ( !empty($val['set_type']) && 1 == $val['set_type']){
                                $val['trueValue'] = [];
                            }else{
                                $dfTrueValue = !empty($RegionData[0]) ? $RegionData[0]['id'] : '';
                                $val['trueValue'] = array($dfTrueValue);
                            }
                        }
                        if ( !empty($val['set_type']) && 1 == $val['set_type']){
                            //三级联动的需要处理
                            $rid = $val['dfvalue'][0]['id'];
                            $region_data = Db::name('region')->where('id',$rid)->find();//这里查出来可能是省级1或者市级2,区域3
                            $val['region_level'] = $region_data['level'];
                            $region_arr = [['id'=>-1,'name'=>'请选择']];
                            if (2 == $region_data['level']){
                                $province_list = get_province_list();
                                $val['city_list'] = array_merge($region_arr,$val['dfvalue']);
                                $val['trueValue'][0] = $region_data['parent_id'];
                                $val['dfvalue'] = $province_list;
                            }elseif (3 == $region_data['level']){
                                $province_list = get_province_list();
                                $province_id = Db::name('region')->where('id',$region_data['parent_id'])->value('parent_id');
                                $val['area_list'] = array_merge($region_arr,$val['dfvalue']);
                                $val['dfvalue'] = $province_list;
                                $val['trueValue'][0] = $province_id;
                                $val['trueValue'][1] = $region_data['parent_id'];
                                if (empty($val['trueValue'][2])) $val['trueValue'][2] = -1;
                            }
                            if (!empty($val['trueValue'][1])){
                                $field_region_type = config('global.field_region_type');
                                //如果是4个特殊的直辖市,市的数据直接显示到区
                                if (in_array($val['trueValue'][0],$field_region_type)){
                                    $city_ids = Db::name('region')->where(['level'=>2,'parent_id'=>$val['trueValue'][0]])->column('id');
                                    $city_list = Db::name('region')->where(['level'=>3])->where('parent_id','in',$city_ids)->select();
                                }else{
                                    $city_list = Db::name('region')->where(['level'=>2,'parent_id'=>$val['trueValue'][0]])->select();
                                }
                                $val['city_list'] = array_merge($region_arr,$city_list);
                            }
                            if (!empty($val['trueValue'][2]) && $val['trueValue'][2] > 0){
                                $area_list = Db::name('region')->where(['level'=>3,'parent_id'=>$val['trueValue'][1]])->select();
                                $val['area_list'] = array_merge($region_arr,$area_list);
                            }
                        }

                        break;
                    }

                    case 'checkbox':
                    {
                        $dfvalue = $val['dfvalue'];
                        $dfvalueArr = explode(',', $dfvalue);
                        $val['dfvalue'] = $dfvalueArr;
                        if (array_key_exists($val['name'], $addonRow)) {
                            $val['trueValue'] = explode(',', $addonRow[$val['name']]);
                        } else {
                            $val['trueValue'] = array();
                        }
                        break;
                    }

                    case 'img':
                    {
                        $val[$val['name'].'_eyou_is_remote'] = 0;
                        $val[$val['name'].'_eyou_remote'] = '';
                        $val[$val['name'].'_eyou_local'] = '';
                        if (array_key_exists($val['name'], $addonRow)) {
                            if (is_http_url($addonRow[$val['name']])) {
                                $val[$val['name'].'_eyou_is_remote'] = 1;
                                $val[$val['name'].'_eyou_remote'] = handle_subdir_pic($addonRow[$val['name']]);
                            } else {
                                $val[$val['name'].'_eyou_is_remote'] = 0;
                                $val[$val['name'].'_eyou_local'] = handle_subdir_pic($addonRow[$val['name']]);
                            }
                        }
                        break;
                    }

                    case 'imgs':
                    {
                        /*将多图字段类型varchar改为text*/
                        try {
                            $channelfieldRow = Db::name('channelfield')->field('id,title,maxlength')
                                ->where([
                                    'name'          => $val['name'],
                                    'channel_id'    => $val['channel_id'],
                                ])->find();
                            if (!empty($channelfieldRow) && 1001 == $channelfieldRow['maxlength']) {
                                $tableExt = Db::name('channeltype')->where('id', $val['channel_id'])->getField('table');
                                $tableExt = PREFIX.$tableExt.'_content';
                                $fieldComment = $channelfieldRow['title'];
                                empty($fieldComment) && $fieldComment = '图集';
                                $sql = " ALTER TABLE `{$tableExt}` MODIFY COLUMN `{$val['name']}`  varchar(10001) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '' COMMENT '{$fieldComment}|10001' ";
                                if (@Db::execute($sql)) {
                                    Db::name('channelfield')->where([
                                            'id'          => $channelfieldRow['id'],
                                        ])->update([
                                            'define'        => 'varchar(10001)',
                                            'maxlength'     => 10001,
                                            'update_time'   => getTime(),
                                        ]);
                                }
                            }
                        } catch (\Exception $e) {}
                        /*end*/

                        $val[$val['name'].'_eyou_imgupload_list'] = array();
                        if (array_key_exists($val['name'], $addonRow) && !empty($addonRow[$val['name']])) {
                            $eyou_imgupload_list = @unserialize($addonRow[$val['name']]);
                            if (false === $eyou_imgupload_list) {
                                $eyou_imgupload_list = [];
                                $eyou_imgupload_data = explode(',', $addonRow[$val['name']]);
                                foreach ($eyou_imgupload_data as $k1 => $v1) {
                                    $eyou_imgupload_list[$k1] = [
                                        'image_url' => handle_subdir_pic($v1),
                                        'intro'     => '',
                                    ];
                                }
                            }
                            $val[$val['name'].'_eyou_imgupload_list'] = $eyou_imgupload_list;
                        }
                        break;
                    }

                    case 'datetime':
                    {
                        $val['dfvalue'] = !empty($addonRow[$val['name']]) ? date('Y-m-d H:i:s', $addonRow[$val['name']]) : '';
                        break;
                    }

                    case 'file':
                    {
                        $val[$val['name'].'_eyou_is_remote'] = 0;
                        $val[$val['name'].'_eyou_remote'] = '';
                        $val[$val['name'].'_eyou_local'] = '';
                        $val[$val['name'].'_filename'] = '';
                        if (array_key_exists($val['name'], $addonRow)) {
                            if (is_http_url($addonRow[$val['name']])) {
                                $val[$val['name'].'_eyou_is_remote'] = 1;
                                $val[$val['name'].'_eyou_remote'] = handle_subdir_pic($addonRow[$val['name']]);
                            } else {
                                $val[$val['name'].'_eyou_is_remote'] = 0;
                                if (!empty($addonRow[$val['name']]) && stristr($addonRow[$val['name']], '|')) {
                                    $arr = explode('|', $addonRow[$val['name']]);
                                    $addonRow[$val['name']] = $arr[0];
                                    $val[$val['name'].'_filename'] = empty($arr[1]) ? '' : $arr[1];
                                }
                                $val[$val['name'].'_eyou_local'] = handle_subdir_pic($addonRow[$val['name']]);
                            }
                        }
                        $val['dfvalue'] = handle_subdir_pic($addonRow[$val['name']]);
                        $val['upload_flag'] = 'local';
                        $WeappConfig = Db::name('weapp')->field('code, status')->where('code', 'IN', ['Qiniuyun', 'AliyunOss', 'Cos', 'AwsOss'])->where('status',1)->select();
                        foreach ($WeappConfig as $value) {
                            if ('Qiniuyun' == $value['code']) {
                                $val['upload_flag'] = 'qny';
                            } else if ('AliyunOss' == $value['code']) {
                                $val['upload_flag'] = 'oss';
                            } else if ('Cos' == $value['code']) {
                                $val['upload_flag'] = 'cos';
                            } else if ('AwsOss' == $value['code']) {
                                $val['upload_flag'] = 'aws';
                            }
                        }
                        $val['ext'] = tpCache('basic.file_type');
                        $val['filesize'] = upload_max_filesize();
                        break;
                    }
                    case 'media':
                    {
                        $val['dfvalue'] = $addonRow[$val['name']];
                        $val['upload_flag'] = 'local';
                        $WeappConfig = Db::name('weapp')->field('code, status')->where('code', 'IN', ['Qiniuyun', 'AliyunOss', 'Cos', 'AwsOss'])->where('status',1)->select();
                        foreach ($WeappConfig as $value) {
                            if ('Qiniuyun' == $value['code']) {
                                $val['upload_flag'] = 'qny';
                            } else if ('AliyunOss' == $value['code']) {
                                $val['upload_flag'] = 'oss';
                            } else if ('Cos' == $value['code']) {
                                $val['upload_flag'] = 'cos';
                            } else if ('AwsOss' == $value['code']) {
                                $val['upload_flag'] = 'aws';
                            }
                        }
                        $ext = tpCache('basic.media_type');
                        $val['ext'] = !empty($ext) ? $ext : config('global.media_ext');
                        $val['filesize'] = upload_max_filesize();
                        break;
                    }

                    case 'htmltext':
                    {
                        $val['dfvalue'] = isset($addonRow[$val['name']]) ? $addonRow[$val['name']] : $val['dfvalue'];
                        if (!empty($compressLogic)) {
                            try {                                
                                $val = $compressLogic->decrypt_base64_encode(1,$val);
                            } catch (Exception $e) {
                            }                
                        }                      
                        /*追加指定内嵌样式到编辑器内容的img标签，兼容图片自动适应页面*/
                        $title = '';
                        if (!empty($archivesInfo['title'])) {
                            $title = $archivesInfo['title'];
                        } else {
                            $title = !empty($archivesInfo['typename']) ? $archivesInfo['typename'] : '';
                        }
                        $content = htmlspecialchars_decode($val['dfvalue']);
                        $val['dfvalue'] = htmlspecialchars(img_style_wh($content, $title));
                        /*--end*/

                        /*支持子目录*/
                        $val['dfvalue'] = handle_subdir_pic($val['dfvalue'], 'html');
                        /*--end*/
                        break;
                    }

                    default:
                    {
                        $val['dfvalue'] = array_key_exists($val['name'], $addonRow) ? $addonRow[$val['name']] : $val['dfvalue'];
                        /*支持子目录*/
                        if (is_string($val['dfvalue'])) {
                            $val['dfvalue'] = handle_subdir_pic($val['dfvalue'], 'html');
                            $val['dfvalue'] = handle_subdir_pic($val['dfvalue']);
                        }
                        /*--end*/
                        break;
                    }
                }
                $list[$key] = $val;
            }
        }
        return $list;
    }

    /**
     * 查询解析模型数据用以构造from表单
     * @author 小虎哥 by 2018-7-25
     */
    public function dealChannelPostData($channel_id, $data = array(), $dataExt = array())
    {
        if (!empty($channel_id)) {

            $nowDataExt = array();
            $fieldTypeList = model('Channelfield')->getListByWhere(array('channel_id'=>$channel_id), 'name,dtype', 'name');
            foreach ($dataExt as $key => $val) {
                
                /*处理复选框取消选中的情况下*/
                if (preg_match('/^(.*)(_eyempty)$/', $key) && empty($val)) {
                    $key = preg_replace('/^(.*)(_eyempty)$/', '$1', $key);
                    $nowDataExt[$key] = '';
                    continue;
                }
                /*end*/

                $key = preg_replace('/^(.*)(_eyou_is_remote|_eyou_remote|_eyou_local)$/', '$1', $key);
                $dtype = !empty($fieldTypeList[$key]) ? $fieldTypeList[$key]['dtype'] : '';
                switch ($dtype) {

                    case 'checkbox':
                    case 'checkboxs':
                    {
                        $val = implode(',', $val);
                        break;
                    }
                    case 'region':
                    {
                        if (!is_numeric($val)) { // 三级联动
                            //选择全国的时候干掉城市区域的值
                            if ($val[0] == 0){
                                if (isset($val[1])) unset($val[1]);
                                if (isset($val[2])) unset($val[2]);
                            }else{
                                $parent_data = Db::name('region')->where('id',$val[0])->find();
                                if (!empty($parent_data) && !empty($parent_data['parent_id'])){
                                    //只有市级和区域能选择
                                    array_unshift($val,$parent_data['parent_id']);
                                    //只有区域能选择
                                    if (3 == $parent_data['level']){
                                        $parent_id = Db::name('region')->where('id',$val[0])->value('parent_id');
                                        array_unshift($val,$parent_id);
                                    }

                                }
                            }
                            //三级联动的需要选择
                            $val = implode(',', $val);
                        } else {
                            if (is_array($val)) {
                                $new_val = [];
                                foreach ($val as $_k => $_v) {
                                    $_v = trim($_v);
                                    if (!empty($_v)) {
                                        $new_val[] = $_v;
                                    }
                                }
                                $val = $new_val;
                            } else {
                                $val = trim($val);
                            }
                        }
                        break;
                    }

                    case 'switch':
                    case 'int':
                    {
                        $val = intval($val);
                        break;
                    }

                    case 'img':
                    {
                        $is_remote = !empty($dataExt[$key.'_eyou_is_remote']) ? $dataExt[$key.'_eyou_is_remote'] : 0;
                        if (1 == $is_remote) {
                            $val = $dataExt[$key.'_eyou_remote'];
                        } else {
                            $val = $dataExt[$key.'_eyou_local'];
                        }
                        break;
                    }

                    case 'imgs':
                    {
                        $imgData = [];
                        $imgsIntroArr = !empty($dataExt[$key.'_eyou_intro']) ? $dataExt[$key.'_eyou_intro'] : [];
                        foreach ($val as $k2 => $v2) {
                            $v2 = trim($v2);
                            if (!empty($v2)) {
                                $intro = !empty($imgsIntroArr[$k2]) ? $imgsIntroArr[$k2] : '';
                                $imgData[] = [
                                    'image_url' => $v2,
                                    'intro'     => $intro,
                                ];
                            }
                        }
                        $val = !empty($imgData) ? serialize($imgData) : '';
                        break;
                    }

                    case 'file':
                    {
                        $is_remote = !empty($dataExt[$key.'_eyou_is_remote']) ? $dataExt[$key.'_eyou_is_remote'] : 0;
                        if (1 == $is_remote) {
                            $val = $dataExt[$key.'_eyou_remote'];
                        } else {
                            $val = $dataExt[$key.'_eyou_local'];
                            if (!empty($dataExt[$key.'_filename'])) {
                                $val .= '|' . $dataExt[$key.'_filename'];
                            }
                        }
                        break;
                    }

                    // case 'files':
                    // {
                    //     foreach ($val as $k2 => $v2) {
                    //         if (empty($v2)) {
                    //             unset($val[$k2]);
                    //             continue;
                    //         }
                    //         $val[$k2] = trim($v2);
                    //     }
                    //     $val = implode(',', $val);
                    //     break;
                    // }

                    case 'datetime':
                    {
                        $val = !empty($val) ? strtotime($val) : getTime();
                        break;
                    }

                    case 'decimal':
                    {
                        $moneyArr = explode('.', $val);
                        $money1 = !empty($moneyArr[0]) ? intval($moneyArr[0]) : '0';
                        $money2 = !empty($moneyArr[1]) ? intval(msubstr($moneyArr[1], 0, 2)) : '00';
                        $val = $money1.'.'.$money2;
                        break;
                    }
                    
                    case 'htmltext':
                    {
                        if (!empty($val)) {
                            $val = preg_replace("/^&amp;nbsp;/i", "", $val);
                        }
                        $val = preg_replace("/&lt;script[\s\S]*?script&gt;/i", "", $val);
                        $val = trim($val);

                    //     /*追加指定内嵌样式到编辑器内容的img标签，兼容图片自动适应页面*/
                    //     $title = '';
                    //     if (!empty($data['title'])) {
                    //         $title = $data['title'];
                    //     } else {
                    //         $title = !empty($data['typename']) ? $data['typename'] : '';
                    //     }
                    //     $content = htmlspecialchars_decode($val);
                    //     $val = htmlspecialchars(img_style_wh($content, $title));
                    //     /*--end*/
                    //     break;
                    }
                    
                    default:
                    {
                        if (is_array($val)) {
                            $new_val = [];
                            foreach ($val as $_k => $_v) {
                                $_v = trim($_v);
                                if (!empty($_v)) {
                                    $new_val[] = $_v;
                                }
                            }
                            $val = $new_val;
                        } else {
                            $val = trim($val);
                        }
                        break;
                    }
                }
                $nowDataExt[$key] = $val;
            }
            
            /*if (isset($nowDataExt['content']) && empty($nowDataExt['content'])) {
                $nowDataExt['content'] = '';
            }
            if (isset($nowDataExt['content_ey_m']) && empty($nowDataExt['content_ey_m'])) {
                $nowDataExt['content_ey_m'] = '';
            }*/

            $nowData = array(
                'aid'   => $data['aid'],
                'update_time'   => getTime(),
            );
            !empty($nowDataExt) && $nowData = array_merge($nowDataExt, $nowData);
            $tableExt = Db::name('channeltype')->where('id', $channel_id)->getField('table');
            $tableExt .= '_content';
            $count = Db::name($tableExt)->where('aid', $data['aid'])->count();
            // 处理内容压缩
            if (is_dir('./weapp/CompressContent/')){                
                try {
                   $compressLogic = new \weapp\CompressContent\logic\CompressContentLogic;                                    
                   $compressLogic->copydata(1,$channel_id,$count,$tableExt, $nowData);                    
                   $nowData = $compressLogic->encrypt_base64_encode($channel_id,$nowData);                
                } catch (Exception $e){
                }                
            }                       
            if (empty($count)) {
                $nowData['add_time'] = getTime();
                Db::name($tableExt)->insert($nowData);
            } else {
                Db::name($tableExt)->where('aid', $data['aid'])->save($nowData);
            }
        }
    }
}