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

namespace think\template\taglib\api;

use think\Db;

/**
 * 文档评论列表
 */
class TagCommentlist extends Base
{
    public $users_id = 0;

    //初始化
    protected function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 获取文档评论列表
     */
    public function getCommentlist($param = [], $aid = '')
    {
      
        if (!is_dir('./weapp/Comment/')) {
            return [
                'data' => '请先安装评论插件',
            ];
        }
    
        if (empty($aid)) {
            return false;
        }
    
        $page = empty($param['page']) ? 1 : $param['page'];
        $limit = empty($param['limit']) ? 10 : $param['limit'];
       
        // $provider = empty($param['provider']) ? 'weixin' : $param['provider'];   //此站设置读全部端的数据   
        $where_str = [
            'a.aid' => $aid,
            'a.is_review' => 1,
            // 'a.provider' => $provider,
        ];
        // 获取所有评论
        $allComments = Db::name('weapp_comment')
        ->alias('a')
        ->field('a.*, b.head_pic, b.nickname, b.sex')
        ->join('users b', 'a.users_id = b.users_id', 'left')
        ->where($where_str)
        ->order('a.add_time DESC') // 转换为时间戳并按时间倒序排列
        ->select();
         $topLevelComments = [];
        $childComments = [];
        // 格式化数据
        foreach ($allComments as &$comment) {
            $comment['head_pic'] = $this->get_head_pic($comment['head_pic'], false, $comment['sex']);
            $comment['add_time_format'] = $this->time_format($comment['add_time']);
            $comment['add_time'] = date('Y-m-d', $comment['add_time']);
            if ($comment['pid'] == 0) {
                // 父评论，添加到顶级评论数组
                $topLevelComments[] = $comment;
            } else {
                // 子评论，添加到子评论数组
                $childComments[$comment['pid']][] = $comment;
            }
        }
       
        // 分离父评论（pid == 0）和子评论（pid > 0）
       
       
        usort($topLevelComments, function($a, $b) {
            return $b['add_time'] - $a['add_time']; // 时间倒序排列
        });
        // 分页顶级评论
        $offset = ($page - 1) * $limit;
        $pagedComments = array_slice($topLevelComments, $offset, $limit);
    
        // 为每个顶级评论添加 `answerList`
        foreach ($pagedComments as &$comment) {
            // 为每个父评论添加其对应的子评论（`answerList`）
            $comment['answerList'] = isset($childComments[$comment['comment_id']]) 
                ? $childComments[$comment['comment_id']] 
                : [];
        }
    
        // 返回分页结构
        return [
            'data' => $pagedComments,
            'current_page' => $page,
            'last_page' => ceil(count($topLevelComments) / $limit),
            'per_page' => $limit,
            'total' => count($topLevelComments),
        ];
    }
    
    
    
    

}