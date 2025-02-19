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

use think\Db;

/**
 * 自定义字段
 */
class TagDiyfield extends Base
{
    //初始化
    protected function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 自定义字段
     * @author wengxianhu by 2018-4-20
     */
    public function getDiyfield($data = '', $type = 'default')
    {
        if (!empty($data)) {
            switch ($type) {
                case 'imgs': // 多图
                    {
                        $list = [];
                        foreach ($data as $key => $val) {
                            $val['image_url'] = handle_subdir_pic($val['image_url']);
                            $list[$key] = $val;
                        }
                        $data = $list;
                    }
                    break;

                case 'files': // 多文件
                    {
                        $list = [];
                        foreach ($data as $key => $val) {
                            $list[$key]['downurl'] = handle_subdir_pic($val);
                            $list[$key]['title'] = '';
                        }
                        $data = $list;
                    }
                    break;
                
                case 'radio': // 单选项
                case 'select': // 下拉框
                case 'checkbox': // 多选项
                    {
                        $list = [];
                        $row = [];
                        if (is_array($data)) {
                            $row = $data;
                        } else {
                            $row = explode(',', $data);
                        }
                        foreach ($row as $key => $val) {
                            $list[$key]['value'] = $val;
                        }
                        $data = $list;
                    }
                    break;

                default:
                    {
                        $list = [];
                        $row = [];
                        if (is_array($data)) {
                            $row = $data;
                        } else {
                            $row[] = $data;
                        }
                        foreach ($row as $key => $val) {
                            $list[$key]['value'] = $val;
                        }
                        $data = $list;
                    }
                    break;
            }
        }

        return $data;
    }
}