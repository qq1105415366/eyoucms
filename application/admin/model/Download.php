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
 * 下载文章
 */
class Download extends Model
{
    // 模型标识
    public $nid = 'download';
    // 模型ID
    public $channeltype = '';

    //初始化
    protected function initialize()
    {
        // 需要调用`Model`的`initialize`方法
        parent::initialize();
        $channeltype_list = config('global.channeltype_list');
        $this->channeltype = $channeltype_list[$this->nid];
    }

    /**
     * 后置操作方法
     * 自定义的一个函数 用于数据保存后做的相应处理操作, 使用时手动调用
     * @param int $aid 产品id
     * @param array $post post数据
     * @param string $opt 操作
     */
    public function afterSave($aid, $post, $opt)
    {
        $post['aid'] = $aid;
        $addonFieldExt = !empty($post['addonFieldExt']) ? $post['addonFieldExt'] : array();
        $FieldModel = new \app\admin\model\Field;
        $FieldModel->dealChannelPostData($post['channel'], $post, $addonFieldExt);
        
        // 处理外贸链接
        if (is_dir('./weapp/Waimao/')) {
            $waimaoLogic = new \weapp\Waimao\logic\WaimaoLogic;
            $waimaoLogic->update_htmlfilename($aid, $post, $opt);
        } else {
            $foreignLogic = new \app\admin\logic\ForeignLogic;
            $foreignLogic->update_htmlfilename($aid, $post, $opt);
        }

        // ---------多文件
        model('DownloadFile')->savefile($aid, $post);
        // ---------end

        // --处理TAG标签
        model('Taglist')->savetags($aid, $post['typeid'], $post['tags'], $post['arcrank'], $opt);

        if ('edit' == $opt) {
            // 清空sql_cache_table数据缓存表 并 添加查询执行语句到mysql缓存表
            Db::execute('TRUNCATE TABLE '.config('database.prefix').'sql_cache_table');
            model('SqlCacheTable')->InsertSqlCacheTable(true);
        } else {
            // 处理mysql缓存表数据
            if (isset($post['arcrank']) && -1 == $post['arcrank'] /*&& -1 == $post['old_arcrank']*/ && !empty($post['users_id'])) {
                // 待审核
                model('SqlCacheTable')->UpdateDraftSqlCacheTable($post, $opt);
            } else if (isset($post['arcrank'])) {
                // 已审核
                $post['old_typeid'] = intval($post['attr']['typeid']);
                model('SqlCacheTable')->UpdateSqlCacheTable($post, $opt, 'download');
            }
        }
        model('Arctype')->hand_type_count(['aid'=>[$aid]]);//统计栏目文档数量
    }

    /**
     * 获取单条记录
     * @author wengxianhu by 2017-7-26
     */
    public function getInfo($aid, $field = '', $isshowbody = true)
    {
        $result = array();
        $field = !empty($field) ? $field : '*';
        $result = Db::name('archives')->field($field)
            ->where([
                'aid'   => $aid,
                'lang'  => get_admin_lang(),
            ])
            ->find();
        if ($isshowbody) {
            $tableName = Db::name('channeltype')->where('id','eq',$result['channel'])->getField('table');
            $result['addonFieldExt'] = Db::name($tableName.'_content')->where('aid',$aid)->find();
        }

        // 文章TAG标签
        if (!empty($result)) {
            $typeid = isset($result['typeid']) ? $result['typeid'] : 0;
            $tags = model('Taglist')->getListByAid($aid, $typeid);
            $result['tags'] = $tags['tag_arr'];
            $result['tag_id'] = $tags['tid_arr'];
        }

        return $result;
    }
    

    /**
     * 获取多条记录
     * @author lindaoyun by 2017-9-18
     */
    public function getListByLimit($map = array(),  $limit = 15, $field = '*', $order = 'a.aid desc')
    {
        $data = array();
        $field_arr = explode(',', $field);
        foreach ($field_arr as $key => $val) {
            array_push($data, 'a.'.trim($val));
        }
        $field = implode(',', $data);
        $field .= ', b.typename, b.dirname';

        if (!empty($map) && is_array($map)) {
            foreach ($map as $key => $val) {
                if (preg_match("/^(a\.)/i", $val) == 0) {
                    $map['a.'.$key] = $val;
                    unset($map[$key]);
                }
            }
        }
        $map['a.channel'] = $this->channeltype;

        $result = Db::name('archives')
            ->field($field)
            ->alias('a')
            ->join('__ARCTYPE__ b', 'b.id = a.typeid', 'LEFT')
            ->where($map)
            ->order($order)
            ->limit($limit)
            ->select();

        return $result;
    }   

    /**
     * 获取多条记录
     * @author wengxianhu by 2017-7-26
     */
    public function getListByClick($limit = 10, $map = array(), $field = '*')
    {
        $map['channel'] = $this->channeltype;
        $map['status'] = 1;
        $result = Db::name('archives')
            ->field($field)
            ->where($map)
            // ->cache(true,EYOUCMS_CACHE_TIME)
            ->order('click desc')
            ->limit($limit)
            ->select();

        return $result;
    }   

    /**
     * 获取当前文章的所有上下级分类
     */
    public function getAllCateByTypeid($typeid)
    {
        $result = Db::name('arctype')->field('id,parent_id,typename')
            ->where(array('id|parent_id'=>$typeid, 'status'=>1))
            ->order('parent_id asc, sort_order asc')
            ->getAllWithIndex('id');   

        return $result;     
    }

    /**
     * 删除的后置操作方法
     * 自定义的一个函数 用于数据删除后做的相应处理操作, 使用时手动调用
     * @param int $aid
     */
    public function afterDel($aidArr = array())
    {
        if (is_string($aidArr)) {
            $aidArr = explode(',', $aidArr);
        }
        // 同时删除内容
        Db::name('download_content')->where(
                array(
                    'aid'=>array('IN', $aidArr)
                )
            )
            ->delete();
        // 同时删除软件
        $result = Db::name('download_file')->field('file_url')
            ->where(
                array(
                    'aid'=>array('IN', $aidArr)
                )
            )
            ->select();
        if (!empty($result)) {
            foreach ($result as $key => $val) {
                $file_url = preg_replace('#^(/[/\w\-]+)?(/public/upload/|/uploads/)#i', '$2', $val['file_url']);
                if (!is_http_url($file_url) && file_exists('.'.$file_url) && preg_match('#^(/uploads/|/public/upload/)(.*)/([^/]+)\.([a-z]+)$#i', $file_url)) {
                    @unlink(realpath('.'.$file_url));
                }
            }
            $r = Db::name('download_file')->where(
                array(
                    'aid'=>array('IN', $aidArr)
                )
            )->delete();
            if ($r !== false) {
                Db::name('download_log')->where(array('aid'=>array('IN', $aidArr)))->delete();
            }
        }
        // 同时删除TAG标签
        model('Taglist')->delByAids($aidArr);
    }
}