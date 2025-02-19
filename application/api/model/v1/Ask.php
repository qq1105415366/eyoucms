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

namespace app\api\model\v1;

use think\Db;
use think\Cache;
use app\api\logic\v1\AskLogic;

load_trait('controller/Jump');

class Ask extends Base
{
    use \traits\controller\Jump;
    public $weapp_ask_db;
    public $weapp_ask_answer_db;
    public $weapp_ask_answer_like_db;
    public $weapp_ask_users_level_db;
    public $weapp_ask_type_db;
    public $users = [];
    public $users_id = 0;
    public $AskLogic;

    //初始化
    protected function initialize()
    {
        // 需要调用`Model`的`initialize`方法
        parent::initialize();
        $this->weapp_ask_db = Db::name('weapp_ask');
        $this->weapp_ask_answer_db = Db::name('weapp_ask_answer');
        $this->weapp_ask_answer_like_db = Db::name('weapp_ask_answer_like');
        $this->weapp_ask_users_level_db = Db::name('weapp_ask_users_level');
        $this->weapp_ask_type_db = Db::name('weapp_ask_type');
        $this->AskLogic = new AskLogic;
    }
    //获取栏目
    public function getTypeList(){
        $TypeData = $this->weapp_ask_type_db->order('sort_order asc, type_id asc')->select();
        $TypeData = array_merge([["type_id"=>0,"type_name"=>"全部"]],$TypeData);
        return $TypeData;
    }
    public function getAskList()
    {
        $param = input('param.');
        $limit = !empty($param['limit']) ? $param['limit'] : 15;
        $page = !empty($param['page']) ? $param['page'] : 1;
        $field = !empty($param['field']) ? $param['field'] : "";
        // 查询条件
        $where = [
            'a.status' => ['IN', [0, 1]],// 0未解决，1已解决
            // 问题是否审核，1是，0否
            'a.is_review' => 1,
        ];
        //未回答
        if (1 == $param['replies']) {
            $where['a.replies'] = 0;
        }
        if (!empty($param['type_id'])) {
            $where['a.type_id'] = $param['type_id'];
        }

        // 返回参数
        $result = [];
        // 没有传入则默认查询这些字段
        if (empty($field)) {
            $field = 'a.*, b.username, b.nickname, b.head_pic, b.sex, b.level, b.scores, c.type_name';
        }

        $paginate = ['page' => $page];
        $pages = $this->weapp_ask_db->field($field)
            ->alias('a')
            ->join('users b', 'a.users_id = b.users_id', 'LEFT')
            ->join('__WEAPP_ASK_TYPE__ c', 'a.type_id = c.type_id', 'LEFT')
            ->where($where)
            ->order('a.is_top desc, a.add_time desc')
            ->paginate($limit, false, $paginate);
        $res = $pages->toArray();

        $askData = $res['data'];

        $ask_ids = get_arr_column($askData, 'ask_id');
        //问题回答人查询
        $RepliesData = $this->weapp_ask_answer_db->field('a.ask_id, a.users_id, b.head_pic, b.nickname, b.username, b.sex')
            ->alias('a')
            ->join('__USERS__ b', 'a.users_id = b.users_id', 'LEFT')
            ->where('ask_id', 'IN', $ask_ids)
            ->select();

        $RepliesDataNew = [];
        foreach ($RepliesData as $key => $value) {
            // 将二维数组以ask_id值作为键，并归类数组，效果同等group_same_key
            $RepliesDataNew[$value['ask_id']][] = $value;
        }
        foreach ($askData as $key => $value) {
            /*头像处理*/
            $value['head_pic'] = handle_subdir_pic(get_head_pic($value['head_pic'], false, $value['sex']));
            $askData[$key]['head_pic'] = get_absolute_url($value['head_pic'],'default',true);
            /* END */
            // 时间友好显示处理
            $askData[$key]['add_time'] = friend_date($value['add_time']);
            $content = htmlspecialchars_decode($value['content']);
            $askData[$key]['no_format_content'] = checkStrHtml($content);
//            $askData[$key]['no_format_content'] = @msubstr(checkStrHtml($value['content']), 0, get_seo_description_length(), false);
        }
        $res['data'] = $askData;
        $typeList = $this->getTypeList();
        $res['typeList'] = $typeList;

        return $res;
    }

    //问题详情
    public function GetAskDetails($users = [])
    {
        $param = input('param.');
        $users_id = !empty($users['users_id']) ? intval($users['users_id']) : 0;

        $info = $this->weapp_ask_db
            ->field('a.*,b.username, b.nickname, b.head_pic, b.sex, b.level, b.scores, c.type_name')
            ->alias('a')
            ->join('__WEAPP_ASK_TYPE__ c', 'c.type_id = a.type_id', 'LEFT')
            ->join('__USERS__ b', 'a.users_id = b.users_id', 'LEFT')
            ->where('ask_id', $param['ask_id'])
            ->find();
        if (empty($info)) $this->error('浏览的问题不存在！');

        if (0 == $info['is_review'] && $info['users_id'] !== $users_id) $this->error('问题未审核通过，暂时不可浏览！');
        //如果是小程序端发布的,要看看有没有单独发布问题图片
        if (!empty($info['is_minipro'])) {
            $img_url = Db::name('weapp_ask_img')->where('ask_id', $param['ask_id'])->column('url');
            if (!empty($img_url)) {
                foreach ($img_url as $k => $v) {
                    $img_url[$k] = get_absolute_url($v,"url",true);
                }
                $info['img_url'] = $img_url;
            }
        }

        // 头像处理
        $info['head_pic'] = get_absolute_url(get_head_pic($info['head_pic'], false, $info['sex']),'default',true);

        // 时间友好显示处理
        $info['add_time'] = friend_date($info['add_time']);

        // 处理格式
        $info['content'] = stripslashes($info['content']);
        $info['content'] = htmlspecialchars_decode(handle_subdir_pic($info['content'], 'html'));


        // seo信息
        $info['seo_title'] = $info['ask_title'] . ' - ' . $info['type_name'];
        $info['seo_keywords'] = $info['ask_title'];
        $info['seo_description'] = @msubstr(checkStrHtml($info['content']), 0, get_seo_description_length(), false);

        $info['answer'] = $this->GetAskReplyData($param);
        return $info;
    }
// 问题回答数据
    public function GetAskReplyData($param = array())
    {
        /*查询条件*/
        $bestanswer_id = $this->weapp_ask_db->where('ask_id', $param['ask_id'])->getField('bestanswer_id');
        $RepliesWhere = ['ask_id' => $param['ask_id'], 'is_review' => 1];
        $WhereOr = [];
        if (!empty($param['answer_id'])) {
            $RepliesWhere = ['answer_id' => $param['answer_id'], 'is_review' => 1];
            $WhereOr = ['answer_pid' => $param['answer_id']];
        }

        /*评论读取条数*/
        $firstRow = !empty($param['firstRow']) ? $param['firstRow'] : 0;
        $listRows = !empty($param['listRows']) ? $param['listRows'] : 5;
        $result['firstRow'] = $firstRow;
        $result['listRows'] = $listRows;
        /* END */

        /*排序*/
        // 按点赞量排序
        if (!empty($param['click_like'])) {
            $OrderBy = "a.click_like {$param['click_like']}, a.answer_id asc";
            $result['likeSortOrder'] = 'desc' == $param['click_like'] ? 'asc' : 'desc';
        } else if (!empty($param['new_answer'])) {
            $OrderBy = "a.answer_id {$param['new_answer']}";
            $result['newSortOrder'] = 'desc' == $param['new_answer'] ? 'asc' : 'desc';
        } else {
            $OrderBy = 'a.answer_id asc';
        }
        /* END */

        /*评论回答*/
        $RepliesData = $this->weapp_ask_answer_db->field('a.*, b.head_pic, b.nickname, b.username as u_username, b.sex,c.nickname as `at_usersname`')
            ->alias('a')
            ->join('__USERS__ b', 'a.users_id = b.users_id', 'LEFT')
            ->join('__USERS__ c', 'a.at_users_id = c.users_id', 'LEFT')
            ->order($OrderBy)
            ->where($RepliesWhere)
            ->WhereOr($WhereOr)
            ->select();
        if (empty($RepliesData)) return [];
        /* END */

        /*点赞数据*/
        $AnswerIds = get_arr_column($RepliesData, 'answer_id');
        $AnswerLikeData = $this->weapp_ask_answer_like_db->field('a.*, b.nickname, b.username')
            ->alias('a')
            ->join('__USERS__ b', 'a.users_id = b.users_id', 'LEFT')
            ->order('like_id desc')
            ->where('answer_id', 'IN', $AnswerIds)
            ->select();
        $AnswerLikeData = group_same_key($AnswerLikeData, 'answer_id');
        /* END */

        /*回答处理*/
        $PidData = $AnswerData = [];
        foreach ($RepliesData as $key => $value) {
            // 友好显示时间
            $value['add_time'] = friend_date($value['add_time']);
            // 处理格式
            $value['content'] = stripslashes($value['content']);
            $value['content'] = htmlspecialchars_decode(handle_subdir_pic($value['content'], 'html'));
            // 头像处理
            $value['head_pic'] = get_absolute_url(get_head_pic($value['head_pic'], false, $value['sex']),'default',true);
            if (!is_http_url($value['head_pic'])) $value['head_pic'] = get_absolute_url(handle_subdir_pic($value['head_pic'], 'img', true),'default',true);
            // 会员昵称
            $value['nickname'] = !empty($value['nickname']) ? $value['nickname'] : $value['u_username'];

            // 是否上一级回答
            if ($value['answer_pid'] == 0) {
                $PidData[] = $value;
            } else {
                $AnswerData[] = $value;
            }
        }
        /* END */

        /*一级回答*/
        foreach ($PidData as $key => $PidValue) {
            $result['AnswerData'][] = $PidValue;
            // 子回答
            $result['AnswerData'][$key]['AnswerSubData'] = [];
            // 点赞数据
            $result['AnswerData'][$key]['AnswerLike'] = [];

            /*所属子回答处理*/
            foreach ($AnswerData as $AnswerValue) {
                if ($AnswerValue['answer_pid'] == $PidValue['answer_id']) {
                    array_push($result['AnswerData'][$key]['AnswerSubData'], $AnswerValue);
                }
            }
            /* END */

            /*读取指定数据*/
            // 以是否审核排序，审核的优先
            array_multisort(get_arr_column($result['AnswerData'][$key]['AnswerSubData'], 'is_review'), SORT_DESC, $result['AnswerData'][$key]['AnswerSubData']);
            // 读取指定条数
            $result['AnswerData'][$key]['AnswerSubData'] = array_slice($result['AnswerData'][$key]['AnswerSubData'], $firstRow, $listRows);
            /* END */

            $result['AnswerData'][$key]['AnswerLike']['LikeNum'] = null;
            $result['AnswerData'][$key]['AnswerLike']['LikeName'] = null;
            /*点赞处理*/
            foreach ($AnswerLikeData as $LikeKey => $LikeValue) {
                if ($PidValue['answer_id'] == $LikeKey) {
                    // 点赞总数
                    $LikeNum = count($LikeValue);
                    $result['AnswerData'][$key]['AnswerLike']['LikeNum'] = $LikeNum;
                    for ($i = 0; $i < $LikeNum; $i++) {
                        // 获取前三个点赞人处理后退出本次for
                        if ($i > 2) break;
                        // 点赞人用户名\昵称
                        $LikeName = $LikeValue[$i]['nickname'];
                        // 在第二个数据前加入顿号，拼装a链接
//                        if ($i != 0) {
//                            $LikeName = ' 、<a href="' . diy_useridhomeurl($LikeValue[$i]['users_id']) . '" target="_blank">' . $LikeName . '</a>';
//                        } else {
//                            $LikeName = '<a href="' . diy_useridhomeurl($LikeValue[$i]['users_id']) . '" target="_blank">' . $LikeName . '</a>';
//                        }
                        $result['AnswerData'][$key]['AnswerLike']['LikeName'] .= $LikeName;
                    }
                }
            }
            /* END */
        }
        /* END */

        /*最佳答案数据*/
        foreach ($result['AnswerData'] as $key => $value) {
            if ($bestanswer_id == $value['answer_id']) {
                $result['BestAnswer'][$key] = $value;
                unset($result['AnswerData'][$key]);
            }
        }
        $result['AnswerData'] = array_merge($result['AnswerData']);
        /* NED */

        // 统计回答数
        $result['AnswerCount'] = count($RepliesData);
        return $result;
    }


    /**
     * 清除页面缓存
     * @return [type] [description]
     */
    private function clear_htmlcache()
    {
        delFile(HTML_PATH . 'plugins/ask/');
    }

    // 点赞
    public function askLike($users = [])
    {
        if (IS_AJAX_POST) {
            $ask_id    = input('param.ask_id/d');
            $answer_id = input('param.answer_id/d');
            if (empty($answer_id) || empty($ask_id)) $this->error('请选择点赞信息！');

            $Where = [
                'ask_id'    => $ask_id,
                'users_id'  => $users['users_id'],
                'answer_id' => $answer_id,
            ];
            $IsCount = $this->weapp_ask_answer_like_db->where($Where)->count();
            if (!empty($IsCount)) {
                $this->error('您已赞过！');
            }else{
                // 添加新的点赞记录
                $AddData = [
                    'click_like'  => 1,
                    'users_ip'    => clientIP(),
                    'add_time'    => getTime(),
                    'update_time' => getTime(),
                ];
                $AddData = array_merge($Where, $AddData);
                $ResultId = $this->weapp_ask_answer_like_db->add($AddData);

                if (!empty($ResultId)) {
                    unset($Where['users_id']);
                    // 点赞数
                    $LikeCount = $this->weapp_ask_answer_like_db->where($Where)->count();
                    // 同步点赞次数到答案表
                    $UpdataNew = [
                        'click_like'  => $LikeCount,
                        'update_time' => getTime(),
                    ];
                    $this->weapp_ask_answer_db->where($Where)->update($UpdataNew);
                    $this->success('点赞成功！', null, $LikeCount);
                }
            }
        }

        $this->error('请求失败，请刷新重试！');
    }

    //添加回答
    public function addAnswer($users = [])
    {
        if (IS_POST) {
            $param = input('param.');
            $this->users = $users;
            $this->users_id = $users['users_id'];
            // 是否登录、是否允许发布问题、数据判断及处理，返回内容数据
            $content = $this->AnswerDealWith($param, true);

            /*添加数据*/
            $AddAnswer = [
                'ask_id'      => $param['ask_id'],
                // 如果这个会员组属于需要审核的，则追加。 默认1为已审核
                'is_review'   => 1 == $this->users['ask_is_review'] ? 0 : 1,
                'type_id'     => $param['type_id'],
                'users_id'    => $this->users_id,
                'username'    => $this->users['username'],
                'users_ip'    => clientIP(),
                'content'     => $content,
                // 若是回答答案则追加数据
                'answer_pid'  => !empty($param['answer_id']) ? $param['answer_id'] : 0,
                // 用户则追加数据
                'at_users_id' => !empty($param['at_users_id']) ? $param['at_users_id'] : 0,
                'at_answer_id'=> !empty($param['at_answer_id']) ? $param['at_answer_id'] : 0,
                'add_time'    => getTime(),
                'update_time' => getTime(),
            ];
            $ResultId = $this->weapp_ask_answer_db->add($AddAnswer);
            /* END */

            if (!empty($ResultId)) {
                // 增加问题回复数
                $this->UpdateAskReplies($param['ask_id'], true);
                if (1 == $this->users['ask_is_review']) {
                    $this->success('回答成功，但你的回答需要管理员审核！', null, ['review' => true]);
                }else{
                    $AddAnswer['answer_id'] = $ResultId;
                    $AddAnswer['head_pic']  = $this->users['head_pic'];
                    $AddAnswer['at_usersname'] = '';
                    if (!empty($AddAnswer['at_users_id'])) {
                        $FindData = Db::name('users')->field('nickname, username')->where('users_id', $AddAnswer['at_users_id'])->find();
                        $AddAnswer['at_usersname'] = empty($FindData['nickname']) ? $FindData['username'] : $FindData['nickname'];
                    }
                    $ResultData = $this->AskLogic->GetReplyHtml($AddAnswer);
                    $this->success('回答成功！', null, $ResultData);
                }
            }else{
                $this->error('提交信息有误，请刷新重试！');
            }
        }
        $this->error('请求失败，请刷新重试！');
    }
    
    // 评论回复数据处理，返回评论回复内容数据
    private function AnswerDealWith($param = [], $is_add = true)
    {
        // 是否允许发布、编辑
        $this->IsAnswer($is_add);
        //限制提交频率
        $rate = $this->AskLogic->GetRateData();
        if (!empty($rate['is_open']) && !empty($rate['duration'])){
            $map = [
                'users_id'    => $this->users_id,
                'update_time'  => array('gt', getTime() - $rate['duration']),
            ];
            $have = $this->weapp_ask_answer_db->where($map)->find();
            if($have){
                $this->error('您提交得太频繁了，请歇会再发布！');
            }
        }
        if (!empty($is_add)) {    /*数据判断*/
            //统计回答次数
            $count = Db::name('weapp_ask_answer')->where(['users_id'=>$this->users_id])->whereTime('add_time','today')->count();
            if (!empty($this->users['answer_count']) && $count >= $this->users['answer_count']) {
                $this->error("【{$this->users['level_name']}】每天最多回答{$this->users['answer_count']}次");
            }
            // 添加时执行判断
            if (empty($param) || empty($param['ask_id']) ) $this->error('提交信息有误，请刷新重试！');
        }else{
            // 编辑时执行判断
            if (empty($is_add) && empty($param['ask_id'])) $this->error('请确认编辑问题！');
        }

        $content = '';
        $content = $this->AskLogic->ContentDealWith($param);
        if (empty($content)) $this->error('请写下你的回答！');
        /* END */
        $content = $this->check_sensitive($content);

        return $content;
    }

    //敏感词过滤
    private function check_sensitive($content){
        $sensitive = $this->AskLogic->GetSensitiveData();
        if (!empty($sensitive['sensitive_switch']) && !empty($sensitive['sensitive_data'])){
            list($count,$sensitiveWord,$content) = $this->AskLogic->sensitive($sensitive['sensitive_data'],$content);
            if ($count > 0  && !empty($sensitiveWord)){     //存在敏感词
                switch ($sensitive['sensitive_switch']){
                    case "1":   //替换敏感词为 *** ， 已经替换过，不需要做其他任何处理
                        break;
                    case "2":   //进入审核
                        $this->users['ask_is_review'] = 1;
                        break;
                    case "3":   //禁止发帖
                        $this->users['is_lock'] = -1;
                        session('users',$this->users);
                        Db::name("users")->where(['users_id'=>$this->users_id])->update(["is_lock"=>-1]);
                        $this->error('您发布的内容存在敏感词，暂停发布信息权限！');
                        break;
                    case "4":   //自动拉黑用户
                        $this->users['is_lock'] = -99;
                        session('users',$this->users);
                        Db::name("users")->where(['users_id'=>$this->users_id])->update(["is_lock"=>-99]);
                        $this->error('您发布的内容存在敏感词！');
                        break;
                }
            }
        }

        return $content;
    }
    // 是否允许发布、编辑评论回复
    private function IsAnswer($is_add = true)
    {
        if (empty($this->users['ask_is_release'])) {
            if (!empty($is_add)) {
                $this->error($this->users['level_name'].'不允许回复答案！');
            }else{
                $this->error($this->users['level_name'].'不允许编辑答案！');
            }
        }
    }
    // 操作问题表回复数
    public function UpdateAskReplies($ask_id = null, $IsAdd = true, $DelNum = 0)
    {
        if (empty($ask_id)) return false;
        if (!empty($IsAdd)) {
            $Updata = [
                'replies' => Db::raw('replies+1'),
                'update_time' => getTime(),
            ];
        }else{
            $Updata = [
                'replies' => Db::raw('replies-1'),
                'update_time' => getTime(),
            ];
            if ($DelNum > 0) $Updata['replies'] = Db::raw('replies-'.$DelNum);
        }
        $this->weapp_ask_db->where('ask_id', $ask_id)->update($Updata);
    }

    // 提交问题
    public function addAsk($users = [])
    {
        if (IS_POST) {
            $param = input('param.');
            $this->users = $users;
            $this->users_id = $users['users_id'];
            // 是否登录、是否允许发布问题、数据判断及处理，返回内容数据
            $content = $this->ParamDealWith($param, $this->users);
            $param['title'] = $this->check_sensitive($param['title']);
            /*添加数据*/
            $AddAsk = [
                'type_id'     => $param['ask_type_id'],
                'users_id'    => $this->users_id,
                'ask_title'   => $param['title'],
                'content'     => $content,
                'users_ip'    => clientIP(),
                'add_time'    => getTime(),
                'update_time' => getTime(),
                'money' => !empty($param['money']) ? $param['money'] : 0,
            ];
            // 如果这个会员组属于需要审核的，则追加
            if (1 == $this->users['ask_is_review']) $AddAsk['is_review'] = 0;
            /* END */

            $ResultId = $this->weapp_ask_db->add($AddAsk);
            if (!empty($ResultId)) {
                if (0 < $param['money'] && $this->reward_switch == 1){
                    //悬赏 扣余额/积分并插入记录
                    $this->AskModel->setUb($this->reward_type,$this->users_id,$ResultId,$param['money']);
                }

                $url = $this->AskLogic->GetUrlData($param, 'NewDateUrl');
                if (1 == $this->users['ask_is_review']) {
                    $this->success('回答成功，但你的回答需要管理员审核！', $url, ['review' => true]);
                }else{
                     $this->success('发布成功！', $url, ['resultId' => $ResultId]);
                }
            }else{
                $this->error('发布的信息有误，请检查！');
            }
        }
        $this->error('请求错误');

    }

    // 问题数据判断及处理，返回问题内容数据
    private function ParamDealWith($param = [], $users = [], $is_add = true)
    {
        // 是否允许该发布、编辑
        $this->IsRelease($users, $is_add);
        //限制提交频率
        $rate = $this->AskLogic->GetRateData();
        if (!empty($rate['is_open']) && !empty($rate['duration'])){
            $map = [
                'users_id'    => $users['users_id'],
                'update_time'  => array('gt', getTime() - $rate['duration']),
            ];
            $have = $this->weapp_ask_db->where($map)->find();
            if($have){
                $this->error('您提交得太频繁了，请歇会再发布！');
            }
        }

        if($is_add){
            //统计提问次数
            $count = $this->weapp_ask_db->where(['users_id'=>$this->users_id])->whereTime('add_time','today')->count();
            if (!empty($users['ask_count']) && $count >= $users['ask_count']) {
                $this->error("【{$users['level_name']}】每天最多提问{$users['ask_count']}次");
            }
        }
        /*数据判断*/
        $content = '';
        if (empty($param)) $this->error('请提交完整信息！');
        if (empty($param['title'])) $this->error('请填写问题标题！');
        if (empty($param['ask_type_id'])) $this->error('请选择问题分类！');
        $content = $this->AskLogic->ContentDealWith($param);
        if (empty($content)) $this->error('请填写问题描述！');
        // 编辑时执行判断
        if (empty($is_add) && empty($param['ask_id'])) $this->error('请确认编辑问题！');
        /* END */
        //敏感词过滤规则
        $content = $this->check_sensitive($content);

        return $content;
    }
    
    // 是否允许发布、编辑问题
    private function IsRelease($users = [], $is_add = true)
    {
        if (empty($users['ask_is_release'])) {
            if (!empty($is_add)) {
                $this->error($users['level_name'].'不允许发布问题！');
            }else{
                $this->error($users['level_name'].'不允许编辑问题！');
            }
        }
    }

}