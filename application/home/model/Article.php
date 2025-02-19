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

namespace app\home\model;

use think\Model;
use think\Page;
use think\Db;

/**
 * 文章
 */
class Article extends Model
{
    //初始化
    protected function initialize()
    {
        // 需要调用`Model`的`initialize`方法
        parent::initialize();
    }

    /**
     * 获取单条记录
     * @author wengxianhu by 2017-7-26
     */
    public function getInfo($aid, $field = '', $isshowbody = true)
    {
        $data = array();
        if (!empty($field)) {
            $field_arr = explode(',', $field);
            foreach ($field_arr as $key => $val) {
                $val = trim($val);
                if (preg_match('/^([a-z]+)\./i', $val) == 0) {
                    array_push($data, 'a.'.$val);
                } else {
                    array_push($data, $val);
                }
            }
            $field = implode(',', $data);
        }

        $map = [];
        if (!is_numeric($aid) || strval(intval($aid)) !== strval($aid)) {
            $map['a.htmlfilename'] = $aid;
        } else {
            $map['a.aid'] = intval($aid);
        }

        $result = array();
        if ($isshowbody) {
            $field = !empty($field) ? $field : 'b.*, a.*';
            $result = Db::name('archives')->field($field)
                ->alias('a')
                ->join('__ARTICLE_CONTENT__ b', 'b.aid = a.aid', 'LEFT')
                ->where($map)
                ->find();
            if (is_dir('./weapp/CompressContent/')) {                                
                try {                                                    
                    $compressLogic = new \weapp\CompressContent\logic\CompressContentLogic;                
                    $result = $compressLogic->decrypt_base64_encode(2,$result);                    
                } catch (Exception $e) {
                }                
            }
        } else {
            $field = !empty($field) ? $field : 'a.*';
            $result = Db::name('archives')->field($field)
                ->alias('a')
                ->where($map)
                ->find();
        }

        // 文章TAG标签
        if (!empty($result)) {
            $typeid = isset($result['typeid']) ? $result['typeid'] : 0;
            $tags = model('Taglist')->getListByAid($aid, $typeid);
            $result['tags'] = $tags;
        }

        return $result;
    }
}