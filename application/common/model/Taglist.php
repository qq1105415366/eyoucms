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

namespace app\common\model;

use think\Db;
use think\Model;

/**
 * 文章Tag标签
 */
class Taglist extends Model
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
    public function getInfo($tid, $field = '*')
    {
        $result = Db::name('Taglist')->field($field)->where('tid', $tid)->find();

        return $result;
    }

    /**
     * 获取单篇文章的标签
     * @author wengxianhu by 2017-7-26
     */
    public function getListByAid($aid = '', $typeid = 0, $field = 'tid, tag')
    {
        $str = [
            'tag_arr'   => '',
            'tid_arr'   => '',
        ];
        $result = Db::name('Taglist')->field($field)
            ->where(array('aid'=>$aid))
            ->order('aid asc, update_time asc')
            ->select();
        if ($result) {
            $tag_arr = get_arr_column($result, 'tag');
            $str['tag_arr'] = implode(',', $tag_arr);
            $id_arr = get_arr_column($result, 'tid');
            $str['tid_arr'] = implode(',', $id_arr);
        }

        return $str;
    }

    /**
     * 获取多篇文章的标签
     * @author wengxianhu by 2017-7-26
     */
    public function getListByAids($aids = array(), $field = '*')
    {
        $data = array();
        $result = Db::name('Taglist')->field($field)
            ->where(array('aid'=>array('IN', $aids)))
            ->order('aid asc')
            ->select();
        if ($result) {
            foreach ($result as $key => $val) {
                if (!isset($data[$val['aid']])) $data[$val['aid']] = array();
                array_push($data[$val['aid']], $val);
            }
        }

        return $data;
    }

    /**
     *  插入Tags
     *
     * @access    public
     * @param     int  $aid  文档AID
     * @param     int  $typeid  栏目ID
     * @param     string  $tag  tag标签
     * @return    void
     */
    public function savetags($aid = 0, $typeid = 0, $tag = '', $arcrank = 0, $opt = 'add')
    {
        $rs = true;
        $tag = strip_tags(htmlspecialchars_decode($tag));
        if ($opt == 'add') {
            $tag = str_replace('，', ',', $tag);
            $tags = explode(',', $tag);
            $tags = array_map('trim', $tags);
            $tags = array_unique($tags);
            foreach($tags as $tag)
            {
                $tag = trim($tag);
                if ($tag != stripslashes($tag)) {
                    continue;
                }

                $r = $this->InsertOneTag($tag, $aid, $typeid,$arcrank);
                if ($rs !== false) {
                    $rs = $r;
                }
            }
        } else if ($opt == 'edit') {
            $rs = $this->UpdateOneTag($aid, $typeid, $tag,$arcrank);
        }
        \think\Cache::clear('taglist');

        return $rs;
    }

    /**
     *  插入一个tag
     *
     * @access    public
     * @param     string  $tag  标签
     * @param     int  $aid  文档AID
     * @param     int  $typeid  栏目ID
     * @return    void
     */
    private function InsertOneTag($tag, $aid, $typeid = 0, $arcrank = 0)
    {
        $tag = trim($tag);
        if (empty($tag)) {
            return true;
        }
        if (empty($typeid)) {
            $typeid = 0;
        }
        $rs = false;
        $addtime = getTime();
        $row = Db::name('tagindex')->where([
                'tag'   => $tag,
                'lang'  => get_admin_lang(),
            ])->find();
        if (empty($row)) {
            $rs = $tid = Db::name('tagindex')->insertGetId([
                'tag' => $tag,
                'typeid' => $typeid,
                'seo_title' => '',
                'seo_keywords' => '',
                'seo_description' => '',
                'total' => 1,
                'weekup' => $addtime,
                'monthup' => $addtime,
                'lang' => get_admin_lang(),
                'add_time' => $addtime,
                'update_time' => $addtime,
            ]);
        } else {
            $rs = Db::name('tagindex')->where([
                    'tag' => $tag,
                    'lang' => get_admin_lang(),
                ])->update([
                    'total' => Db::raw('total + 1'),
                    'update_time' => $addtime,
                    'lang' => get_admin_lang(),
                ]);
            $tid = $row['id'];
        }

        if ($rs !== false) {
            $rs = Db::name('taglist')->insert([
                'tid' => $tid,
                'aid' => $aid,
                'typeid' => $typeid,
                'tag' => $tag,
                'arcrank' => $arcrank,
                'lang' => get_admin_lang(),
                'add_time' => $addtime,
                'update_time' => $addtime,
            ]);
        }

        return $rs;
    }

    /**
     *  更新Tag
     *
     * @access    public
     * @param     int  $aid  文档ID
     * @param     int  $typeid  栏目ID
     * @param     string  $tags  tag标签
     * @return    string
     */
    private function UpdateOneTag($aid, $typeid, $tags='', $arcrank = 0)
    {
        $rs = true;
        $lang = get_admin_lang();
        $oldtag = $this->GetTags($aid);
        $oldtags = explode(',', $oldtag);
        $tags = str_replace('，', ',', $tags);
        $new_tags = explode(',', $tags);
        if(!empty($tags) || !empty($oldtags))
        {
            // 原来的tag转成小写，匹配不区分大小写
            $lower_oldtags = [];
            foreach ($oldtags as $key => $val) {
                $lower_oldtags[$key] = strtolower($val);
            }
            // 新的tag转成小写，匹配不区分大小写
            $lower_newtags = [];
            foreach ($new_tags as $key => $val) {
                $lower_newtags[$key] = strtolower($val);
            }

            foreach($new_tags as $tag)
            {
                $tag = trim($tag);
                if (empty($tag) || $tag != stripslashes($tag)) {
                    continue;
                }
                if (!in_array(strtolower($tag), $lower_oldtags)) {
                    $r = $this->InsertOneTag($tag, $aid, $typeid,$arcrank);
                    if ($rs !== false) {
                        $rs = $r;
                    }
                }
            }

            $taglistRow = Db::name('taglist')->field('count(tid) as total, tag')->where([
                    'tag'       => ['IN', $oldtags],
                    'lang'      => $lang,
                ])->group('tag')->select();
            foreach ($taglistRow as $key => $val) {
                $taglistRow[md5($val['tag'])] = $val;
                unset($taglistRow[$key]);
            }

            $time = getTime();
            foreach ($oldtags as $key => $tag)
            {
                $update_time = $time + (false === array_search(strtolower($tag), $lower_newtags) ? 0 : array_search(strtolower($tag), $lower_newtags));
                if(!in_array(strtolower($tag), $lower_newtags))
                {
                    Db::name('taglist')->where(['aid'=>$aid,'tag'=>$tag])->delete();
                    $total = !empty($taglistRow[md5($tag)]) ? $taglistRow[md5($tag)]['total'] - 1 : 0;
                    if (0 < $total) {
                        $r = Db::name('tagindex')->where(['tag'=>$tag,'lang'=>$lang])->update([
                                'total' => $total,
                                'update_time'  => $update_time,
                            ]);
                    } else {
                        $r = Db::name('tagindex')->where(['tag'=>$tag,'lang'=>$lang])->delete();
                    }
                }
                else
                {
                    $r = Db::name('taglist')->where(['aid'=>$aid,'tag'=>$tag,'lang'=>$lang])->update([
                            'typeid' => $typeid,
                            'update_time'  => $update_time,
                        ]);
                    if ($r !== false) {
                        Db::name('taglist')->where(['aid'=>$aid])->update([
                                'arcrank' => $arcrank,
                            ]);
                    }
                }

                if ($rs !== false) {
                    $rs = $r;
                }
            }
        }

        return $rs;
    }

    /**
     *  获得某文档的所有tag
     *
     * @param     int     $aid  文档id
     * @return    string
     */
    public function GetTags($aid)
    {
        $tags = '';
        $row = Db::name('taglist')->field('tag')->where(['aid'=>$aid])->select();
        foreach ($row as $key => $val) {
            $tags .= (empty($tags) ? $val['tag'] : ','.$val['tag']);
        }
        return $tags;
    }

    /**
     * 删除文章标签
     */
    public function delByAids($aids = array())
    {
        if (!empty($aids)) {
            $tags = Db::name('taglist')->where(['aid'=>['IN', $aids]])->column('tag');
            if (!empty($tags)) {
                Db::name('taglist')->where(['aid'=>['IN', $aids]])->delete();
                $tagsgroup = Db::name('taglist')->field('tag')->where(['tag'=>['IN', $tags]])->group('tag')->getAllWithIndex('tag');
                // 更新标签的文档总数
                foreach ($tags as $key => $tag) {
                    if (empty($tagsgroup[$tag])) {
                        Db::name('tagindex')->where(['tag'=>$tag])->update([
                            'total' => 0,
                            'typeid' => 0,
                            'update_time' => getTime(),
                        ]);
                        // Db::name('tagindex')->where(['tag'=>$tag])->delete(); // 此逻辑作废
                    } else {
                        $total = Db::name('taglist')->where(['tag'=>$tag])->count();
                        Db::name('tagindex')->where([
                                'tag' =>$tag,
                                'total' =>['gt', 0],
                                'lang' =>get_admin_lang()
                            ])
                            ->update([
                                'total' => $total,
                                'update_time' => getTime(),
                            ]);
                    }
                }
                \think\Cache::clear('taglist');
            }
        }
    }
}