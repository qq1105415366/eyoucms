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
 * Date: 2019-7-30
 */

namespace app\home\model;

use think\Model;
use think\Db;
use think\Page;
use think\Config;
use app\home\logic\AskLogic;

/**
 * 模型
 */
class Ask extends Model
{
    //初始化
    protected function initialize()
    {
        // 需要调用`Model`的`initialize`方法
        parent::initialize();

        $this->users_db           = Db::name('users'); // 会员表
        $this->ask_db             = Db::name('ask'); // 问题表
        $this->ask_answer_db      = Db::name('ask_answer'); // 答案表
        $this->ask_type_db        = Db::name('ask_type'); // 问题栏目分类表
        $this->ask_answer_like_db = Db::name('ask_answer_like'); // 问题回答点赞表
        $this->AskLogic           = new AskLogic;
    }

    // 用户信息及问题回答数据
    public function GetUsersAskCount($view_uid = null)
    {
        // 返回参数
        $result = [];

        // 查询会员信息
        $users = session('users');
        if (!empty($users) && $users['users_id'] == $view_uid) {
            $result = [
                'NickName' => $users['nickname'],
                'HeadPic'  => $users['head_pic'],
                'IsLogin'  => 1,
            ];
        } else {
            $UsersInfo = $this->users_db->field('nickname,head_pic')->where('users_id', $view_uid)->find();
            if (!empty($UsersInfo)) {
                $result = [
                    'NickName' => $UsersInfo['nickname'],
                    'HeadPic'  => $UsersInfo['head_pic'],
                    'IsLogin'  => 0,
                ];
            }
        }

        // 问答数量
        if (!empty($result['NickName'])) {
            // 查询问题数量
            $result['AskCount'] = $this->ask_db->where('users_id', $view_uid)->count();
            // 查询回答数量
            $result['AnswerCount'] = $this->ask_answer_db->where('users_id', $view_uid)->count();
        }

        // 拼装URL
        $result['UsersAskUrl'] = askurl('home/Ask/ask_index', ['view_uid' => $view_uid]);
        // ROOT_DIR.'/index.php?m=home&c=Ask&a=ask_index&view_uid='.$view_uid;
        $result['UsersAnswerUrl'] = askurl('home/Ask/ask_index', ['view_uid' => $view_uid, 'method' => 'answer']);
        // ROOT_DIR.'/index.php?m=home&c=Ask&a=ask_index&view_uid='.$view_uid.'&method=answer';
        return $result;
    }

    // 会员问题回答数据
    public function GetUsersAskData($view_uid = null, $is_ask = true)
    {
        // 返回参数
        $result = [];
        $field  = 'a.ask_id, a.type_id, a.ask_title, a.click, a.replies, a.add_time, a.is_review, b.users_id, b.nickname';
        if (!empty($is_ask)) {
            // 提问问题查询列表
            $where = [
                'a.status'   => ['IN', [0, 1]],
                'a.users_id' => $view_uid,
            ];

            /* 分页 */
            $count             = $this->ask_db->alias('a')->where($where)->count('ask_id');
            $pageObj           = new Page($count, 10);
            $result['pageStr'] = $pageObj->show();
            /* END */

            /*问题表数据(问题表+会员表+问题分类表)*/
            $result['AskData'] = $this->ask_db->field($field)
                ->alias('a')
                ->join('__USERS__ b', 'a.users_id = b.users_id', 'LEFT')
                ->where($where)
                ->order('a.add_time desc')
                ->limit($pageObj->firstRow . ',' . $pageObj->listRows)
                ->select();
            /* END */

            /*问题回答人查询*/
            $ask_id      = get_arr_column($result['AskData'], 'ask_id');
            $RepliesData = $this->ask_answer_db->field('a.ask_id, a.users_id, b.nickname')
                ->alias('a')
                ->join('__USERS__ b', 'a.users_id = b.users_id', 'LEFT')
                ->where('ask_id', 'IN', $ask_id)
                ->select();
            /* END */
        } else {
            // 回答问题查询列表
            /*问题回答人查询*/
            $RepliesData = $this->ask_answer_db->field('a.ask_id, a.users_id, b.nickname')
                ->alias('a')
                ->join('__USERS__ b', 'a.users_id = b.users_id', 'LEFT')
                ->select();
            /* END */

            /* 查询条件 */
            $UsersIds = group_same_key($RepliesData, 'users_id');
            $ask_ids  = get_arr_column($UsersIds[$view_uid], 'ask_id');
            // 按主键去重
            $ask_ids = array_unique($ask_ids, SORT_REGULAR);
            $where   = [
                'a.status' => ['IN', [0, 1]],
                'a.ask_id' => ['IN', $ask_ids],
            ];
            /* END */

            /* 分页 */
            $count             = $this->ask_db->alias('a')->where($where)->count('ask_id');
            $pageObj           = new Page($count, 10);
            $result['pageStr'] = $pageObj->show();
            /* END */

            /*问题表数据(问题表+会员表+问题分类表)*/
            $result['AskData'] = $this->ask_db->field($field)
                ->alias('a')
                ->join('__USERS__ b', 'a.users_id = b.users_id', 'LEFT')
                ->where($where)
                ->order('a.add_time desc')
                ->limit($pageObj->firstRow . ',' . $pageObj->listRows)
                ->select();
            /* END */
        }

        // 取出提问问题的ID作为主键
        $RepliesData = group_same_key($RepliesData, 'ask_id');

        /*数据处理*/
        foreach ($result['AskData'] as $key => $value) {
            // 时间友好显示处理
            $result['AskData'][$key]['add_time'] = friend_date($value['add_time']);
            // 问题内容Url
            $result['AskData'][$key]['AskUrl'] = askurl('home/Ask/details', ['ask_id' => $value['ask_id']]);
            // ROOT_DIR.'/index.php?m=home&c=Ask&a=details&ask_id='.$value['ask_id'];
            // 回复处理
            if (!empty($RepliesData[$value['ask_id']])) {
                $UsersConut                            = array_values(array_unique($RepliesData[$value['ask_id']], SORT_REGULAR));
                $result['AskData'][$key]['UsersConut'] = '等' . count($UsersConut) . '人参与讨论';
            } else {
                $UsersConut                            = ['0' => ['nickname' => $value['nickname']]];
                $result['AskData'][$key]['UsersConut'] = $value['nickname'];
            }
            // 处理参与讨论者的A标签跳转
            foreach ($UsersConut as $kkk => $vvv) {
                $nickname = $vvv['nickname'];
                if (($kkk + 1) == count($UsersConut) || 2 == $kkk) {
                    $result['AskData'][$key]['NickName'] .= '<a href="javascript:void(0);">' . $nickname . '</a>';
                    break;
                } else {
                    $result['AskData'][$key]['NickName'] .= '<a href="javascript:void(0);">' . $nickname . '</a>、';
                }
            }
        }
        /* END */

        return $result;
    }

    // 问题数据
    public function GetNewAskData($where = array(), $limit = 20, $field = null)
    {
        // 返回参数
        $result = [];
        // 没有传入则默认查询这些字段
        if (empty($field)) {
            $field = 'a.*, b.nickname, b.head_pic, c.type_name';
        }

        $result['PendingAsk'] = '';
        if (isset($where['a.replies']) && 0 == $where['a.replies']) {
            $result['PendingAsk'] = '待回答';
        }

        // 提取搜索关键词
        if (!empty($where['SearchName']) || null == $where['SearchName']) {
            $SearchName = $where['SearchName'];
            unset($where['SearchName']);
            $result['SearchName']    = $SearchName;
            $result['SearchNameRed'] = "搜索 <font color='red'>" . $SearchName . "</font> 结果";
        }

        if (!empty($where['a.type_id'])) {
            // 存在栏目ID则执行
            $TypeData = $this->ask_type_db->where('parent_id', $where['a.type_id'])->field('type_id')->select();
            if (!empty($TypeData)) {
                // 将顶级栏目ID合并到新条件中
                $type_id            = get_arr_column($TypeData, 'type_id');
                $type_id            = array_merge($type_id, array(0 => $where['a.type_id']));
                $where['a.type_id'] = ['IN', $type_id];
            }

            // 查询满足要求的总记录数
            $count = $this->ask_db->alias('a')->where($where)->count('ask_id');
            // 实例化分页类 传入总记录数和每页显示的记录数
            $pageObj = new Page($count, $limit);
            /*问题表数据(问题表+会员表+问题分类表)*/
            $result['AskData'] = $this->ask_db->field($field)
                ->alias('a')
                ->join('__USERS__ b', 'a.users_id = b.users_id', 'LEFT')
                ->join('ask_type c', 'a.type_id = c.type_id', 'LEFT')
                ->where($where)
                ->order('a.ask_id desc')
                ->limit($pageObj->firstRow . ',' . $pageObj->listRows)
                ->select();
            // 分页显示输出
            $result['pageStr'] = $pageObj->show();
        } else {
            /*问题表数据(问题表+会员表+问题分类表)*/
            $result['AskData'] = $this->ask_db->field($field)
                ->alias('a')
                ->join('__USERS__ b', 'a.users_id = b.users_id', 'LEFT')
                ->join('ask_type c', 'a.type_id = c.type_id', 'LEFT')
                ->where($where)
                ->order('a.ask_id desc')
                ->limit($limit)
                ->select();
            /* END */
            $result['pageStr'] = '';
        }

        /*问题回答人查询*/
        $ask_id         = get_arr_column($result['AskData'], 'ask_id');
        $RepliesData    = $this->ask_answer_db->field('a.ask_id, a.users_id, b.head_pic, b.nickname')
            ->alias('a')
            ->join('__USERS__ b', 'a.users_id = b.users_id', 'LEFT')
            ->where('ask_id', 'IN', $ask_id)
            ->select();
        $RepliesDataNew = [];
        foreach ($RepliesData as $key => $value) {
            /*头像处理*/
            $value['head_pic'] = get_head_pic($value['head_pic']);
            /*个人主页URL*/
            $value['usershomeurl'] = usershomeurl($value['users_id']);
            // 将二维数组以ask_id值作为键，并归类数组，效果同等group_same_key
            $RepliesDataNew[$value['ask_id']][] = $value;
        }
        /* END */

        /*数据处理*/
        foreach ($result['AskData'] as $key => $value) {
            /*头像处理*/
            $value['head_pic']                   = get_head_pic($value['head_pic']);
            $result['AskData'][$key]['head_pic'] = $value['head_pic'];
            /* END */

            // 若存在搜索关键词则标红关键词
            if (isset($SearchName) && !empty($SearchName)) {
                $result['AskData'][$key]['ask_title'] = $this->AskLogic->GetRedKeyWord($SearchName, $value['ask_title']);
            }

            // 时间友好显示处理
            $result['AskData'][$key]['add_time'] = friend_date($value['add_time']);
            // 栏目分类Url
            $result['AskData'][$key]['TypeUrl'] = askurl('home/Ask/index', ['type_id' => $value['type_id']]);
            // 问题内容Url
            $result['AskData'][$key]['AskUrl'] = askurl('home/Ask/details', ['ask_id' => $value['ask_id']]);
            // 回复处理
            if (!empty($RepliesDataNew[$value['ask_id']])) {
                $UsersConut = array_unique($RepliesDataNew[$value['ask_id']], SORT_REGULAR);
                // 读取前三条数据
                $result['AskData'][$key]['HeadPic']    = array_slice($UsersConut, 0, 3);
                $result['AskData'][$key]['UsersConut'] = '等' . count($UsersConut) . '人参与讨论';
            } else {
                $result['AskData'][$key]['HeadPic']    = ['0' => ['head_pic' => $value['head_pic'], 'usershomeurl'=>usershomeurl($value['users_id'])]];
                $result['AskData'][$key]['UsersConut'] = $value['nickname'];
            }

            // 内容处理
            $preg = '/<img.*?src=[\"|\']?(.*?)[\"|\']?\s.*?>/i';
            $value['content'] = strip_tags(preg_replace($preg, '[图片]', htmlspecialchars_decode($value['content'])));
            $result['AskData'][$key]['content'] = $value['content'];
            // $result['AskData'][$key]['content'] = mb_strimwidth($value['content'], 0, 120, "...");
        }
        /* END */

        // 是否推荐
        if (!empty($where['a.is_recom'])) {
            $result['IsRecom'] = 1;
        } else {
            $result['IsRecom'] = 0;
        }
        if (!empty($where['a.money'])) {
            $result['IsRecom'] = 3;
        }
        return $result;
    }

    // 加急问题列表(默认10条数据)
    public function GetUrgentAskData($limit = 10)
    {
        $field = 'ask_id, type_id, ask_title, money';
        $where = [
            'is_del' => 0,
            'is_review' => 1,
            'money' => ['>', 0]
        ];
        $result['UrgentAsk'] = $this->ask_db->field($field)->where($where)->order('money desc')->limit($limit)->select();
        foreach ($result['UrgentAsk'] as &$value) {
            $value['AskUrl'] = askurl('home/Ask/details', ['ask_id' => $value['ask_id']]);
        }

        return $result;
    }

    // 统计会员提问、回答、被评论、被点赞次数
    public function GetCountAskData($users_id = null)
    {
        // 查询提问数据
        $where = [
            'is_del' => 0,
            'users_id' => $users_id
        ];
        $AskListID = $this->ask_db->field('ask_id')->where($where)->column('ask_id');
        // 统计会员所有提问数
        $CountAsk = count($AskListID);

        // 查询回答、评论、回复数据
        $where = [
            'is_del' => 0
        ];
        $answer_list = $this->ask_answer_db->where($where)->select();
        $CountAnswer = $CountBeLike = $CountBeAnswer = 0;
        $CountBeLikeID = $CountAnswerID = [];
        foreach ($answer_list as $key => $value) {
            // 统计数量
            if (($users_id == $value['users_id'])) {
                // 统计会员所有回答数
                if (empty($value['answer_pid'])) $CountAnswer += 1;

                // 统计会员所有回答、评论、回复被点赞ID，用于查询点赞数据表中符合的点赞数
                if (!empty($value['click_like'])) array_push($CountBeLikeID, $value['answer_id']);

                // 统计会员所有回答、评论、回复ID，用于下一个foreach做被评论、被@回复计算
                array_push($CountAnswerID, $value['answer_id']);
            }
        }

        // 统计会员所有提问、回答、评论、回复被点赞数
        $LikeWhere = '';
        if (!empty($AskListID) && empty($CountBeLikeID)) {
            $AskListID = implode(',', $AskListID);
            $LikeWhere = '(`like_source` = 1 AND `ask_id` IN ('.$AskListID.') AND `users_id` <> '.$users_id.')';
        } else if (empty($AskListID) && !empty($CountBeLikeID)) {
            $CountBeLikeID = implode(',', $CountBeLikeID);
            $LikeWhere = '(`users_id` <> '.$users_id.' AND `answer_id` IN ('.$CountBeLikeID.'))';
        } else if (!empty($AskListID) && !empty($CountBeLikeID)) {
            $AskListID = implode(',', $AskListID);
            $CountBeLikeID = implode(',', $CountBeLikeID);
            $LikeWhere = '(`like_source` IN (2,3) AND `answer_id` IN ('.$CountBeLikeID.') AND `users_id` <> '.$users_id.') OR (`like_source` = 1 AND `ask_id` IN ('.$AskListID.') AND `users_id` <> '.$users_id.')';
        }
        $CountBeLike = !empty($LikeWhere) ? $this->ask_answer_like_db->where($LikeWhere)->count() : $CountBeLike;

        // 统计会员所有回答被、评论、被@回复数量，仅针对会员回答的层级下
        if (!empty($CountAnswerID)) {
            foreach ($answer_list as $value) {
                if (in_array($value['answer_pid'], $CountAnswerID) && $users_id != $value['users_id']) {
                    $CountBeAnswer += 1;
                }
            }
        }

        // 返回统计结果
        $result['CountUsersAsk'] = [
            'CountAsk' => $CountAsk,
            'CountAnswer' => $CountAnswer,
            'CountBeLike' => $CountBeLike,
            'CountBeAnswer' => $CountBeAnswer
        ];
        return $result;
    }

    // 问题栏目分类数据
    public function GetAskTypeData($param = array(), $is_add = null)
    {
        // 数据初始化
        $result = [
            'IsTypeId' => 0,
            'ParentId' => 0,
            'TypeId'   => 0,
            'TypeName' => '',
            'HtmlCode' => '<span style="color: red;">请先让管理员在问答栏目列表中添加分类！</span>',
            'TypeData' => []
        ];

        /*栏目处理*/
        $TypeData = $this->ask_type_db->order('sort_order asc, type_id asc')->select();
        if (empty($TypeData)) return [];

        foreach ($TypeData as $key => $value) {
            /*若存在分类ID且和数据中的值相等则执行*/
            if (!empty($param['type_id']) && $value['type_id'] == $param['type_id']) {
                $result['TypeName'] = $value['type_name'];
                $result['TypeId']   = $value['type_id'];
                $result['ParentId'] = $value['parent_id'];

                // 描点标记焦点
                if (!empty($value['parent_id'])) {
                    $result['IsTypeId'] = $value['parent_id'];
                } else {
                    $result['IsTypeId'] = $value['type_id'];
                }
            }
            /* END */

            // 是否顶级栏目
            if ($value['parent_id'] == 0) {
                $value['SelectType'] = 0;
                $PidData[]           = $value;
            } else {
                $value['SelectSubType'] = 0;
                $TidData[]              = $value;
            }
        }

        // 调用来源
        if (isset($is_add) && 'add_ask' == $is_add) {
            // 问题发布调用
            $return['HtmlCode'] = $this->AskLogic->GetTypeHtmlCode($PidData, $TidData, $param['type_id']);
            return $return;
        } else if (isset($is_add) && 'edit_ask' == $is_add) {
            // 问题编辑调用
            $return['HtmlCode'] = $this->AskLogic->GetTypeHtmlCode($PidData, $TidData, $param['type_id']);
            return $return;
        } else {
            // 列表或内容页调用
            $TidData = !empty($TidData) ? group_same_key($TidData, 'parent_id') : [];
            
            // 一级栏目处理
            foreach ($PidData as $P_key => $PidValue) {
                $result['TypeData'][]              = $PidValue;
                $result['TypeData'][$P_key]['Url'] = askurl('home/Ask/index', ['type_id' => $PidValue['type_id']]);
                // ROOT_DIR.'/index.php?m=home&c=Ask&a=index&type_id='.$PidValue['type_id'];

                $result['TypeData'][$P_key]['SubType'] = [];

                if (!empty($TidData[$PidValue['type_id']])) {
                    // 所属子栏目处理
                    foreach ($TidData[$PidValue['type_id']] as $T_key => $TidValue) {
                        if ($TidValue['parent_id'] == $PidValue['type_id']) {
                            array_push($result['TypeData'][$P_key]['SubType'], $TidValue);
                            $result['TypeData'][$P_key]['SubType'][$T_key]['Url'] = askurl('home/Ask/index', ['type_id' => $TidValue['type_id']]);
                            // ROOT_DIR.'/index.php?m=home&c=Ask&a=index&type_id='.$TidValue['type_id'];
                        }
                    }
                }
            }
        }
        /* END */

        return $result;

    }

    // 周榜
    public function GetAskWeekListData()
    {
        $time1 = strtotime(date('Y-m-d H:i:s', time()));
        $time2 = $time1 - (86400 * 7);
        $where = [
            'a.add_time'  => ['between time', [$time2, $time1]],
            'a.is_review' => 1,
        ];
        $result['WeekList'] = [];
        $WeekList = $this->ask_db->field('a.ask_id, a.type_id, a.ask_title, a.click, a.replies, b.head_pic, c.type_name')
            ->alias('a')
            ->join('__USERS__ b', 'a.users_id = b.users_id', 'LEFT')
            ->join('__ASK_TYPE__ c', 'c.type_id = a.type_id', 'LEFT')
            ->order('click desc, replies desc')
            ->where($where)
            ->limit('0, 10')
            ->select();
        if (empty($WeekList)) return $result;
        foreach ($WeekList as $key => $value) {
            // 内页详情URL
            $result['WeekList'][$key]['AskUrl'] = askurl('home/Ask/details', ['ask_id' => $value['ask_id']]);
            // 问题标题
            $result['WeekList'][$key]['ask_title'] = $value['ask_title'];
            // 问答栏目板块URL
            $result['WeekList'][$key]['type_url'] = askurl('home/Ask/index', ['type_id' => $value['type_id']]);
            // 问答栏目板块名称
            $result['WeekList'][$key]['type_name'] = $value['type_name'];
            // 回答数
            $result['WeekList'][$key]['replies'] = $value['replies'];
        }
        return $result;
    }

    // 总榜
    public function GetAskTotalListData()
    {
        $result['TotalList'] = [];
        $TotalList           = $this->ask_db->field('a.ask_id, a.type_id, a.ask_title, a.click, a.replies, b.head_pic')
            ->alias('a')
            ->join('__USERS__ b', 'a.users_id = b.users_id', 'LEFT')
            ->order('click desc, replies desc')
            ->where('a.is_review', 1)
            ->limit('0, 10')
            ->select();
        if (empty($TotalList)) {
            return $result;
        }

        foreach ($TotalList as $key => $value) {
            $result['TotalList'][$key]['AskUrl'] = askurl('home/Ask/details', ['ask_id' => $value['ask_id']]);
            $result['TotalList'][$key]['ask_title'] = $value['ask_title'];
        }

        return $result;
    }

    // 问题详情数据
    public function GetAskDetailsData($param = array(), $parent_id = null, $users_id = null)
    {
        $info = $this->ask_db->field('a.*, b.username, b.nickname, b.head_pic, c.type_name')
            ->alias('a')
            ->join('ask_type c', 'c.type_id = a.type_id', 'LEFT')
            ->join('__USERS__ b', 'a.users_id = b.users_id', 'LEFT')
            ->where('ask_id', $param['ask_id'])
            ->find();
        if (empty($info)) return ['code' => 0, 'msg' => '浏览的问题不存在'];
        if (0 !== $parent_id && 0 == $info['is_review'] && $info['users_id'] !== $users_id) {
            return ['code' => 0, 'msg' => '问题未审核通过，暂时不可浏览'];
        }
        // 问答栏目板块URL
        $info['type_url'] = askurl('home/Ask/index', ['type_id' => $info['type_id']]);
        // 问题的回答数
        $info['ques_answer_count'] = $this->ask_answer_db->where(['answer_pid'=>0, 'ask_id'=>$info['ask_id']])->count();
        // 问题的点赞数
        $info['ques_like_count'] = $this->ask_answer_like_db->where(['ask_id'=>$info['ask_id'], 'like_source'=>1])->count();
        // 头像处理
        $info['head_pic'] = get_head_pic($info['head_pic']);
        // 时间友好显示处理
        $info['add_time'] = friend_date($info['add_time']);
        // 处理格式
        $info['content'] = htmlspecialchars_decode($info['content']);
        // seo信息
        $info['seo_title'] = $info['ask_title'].' - '.$info['type_name'];
        $info['seo_keywords'] = $info['ask_title'];
        $info['seo_description'] = @msubstr(checkStrHtml($info['content']), 0, get_seo_description_length(), false);
        // 个人主页URL
        $info['usershomeurl'] = usershomeurl($info['users_id']);
        // 返回数据
        $ResultData = [
            'code' => 1,
            'info' => $info,
            // 搜索的内容
            'SearchName' => null,
            // 登录者是否为问题的发布者
            'IsUsers' => $info['users_id'] == session('users_id') ? 1 : 0
        ];
        return $ResultData;
    }

    // 问题回答数据
    public function GetAskReplyData($param = array(), $parent_id = null)
    {
        /*查询条件*/
        $WhereOr = [];
        $RepliesWhere = ['ask_id' => $param['ask_id'], 'is_review' => 1];
        $bestanswer_id = $this->ask_db->where('ask_id', $param['ask_id'])->getField('bestanswer_id');
        if (!empty($param['answer_id'])) {
            $WhereOr = ['answer_pid' => $param['answer_id']];
            $RepliesWhere = ['answer_id' => $param['answer_id'], 'is_review' => 1];
        }
        // 若为则创始人则去除仅查询已审核评论这个条件，$parent_id = 0 表示为创始人
        if (isset($parent_id) && 0 == $parent_id) unset($RepliesWhere['is_review']);
        /* END */
        /*评论读取条数*/
        $firstRow = !empty($param['firstRow']) ? $param['firstRow'] : 0;
        $listRows = !empty($param['listRows']) ? $param['listRows'] : 5;
        $result['firstRow'] = $firstRow;
        $result['listRows'] = $listRows;
        /* END */

        /*排序*/
        $OrderBy = !empty($param['click_like']) ? 'a.click_like ' . $param['click_like'] . ', a.add_time asc' : 'a.add_time asc';
        $click_like = isset($param['click_like']) ? $param['click_like'] : '';
        $result['SortOrder'] = 'desc' == $click_like ? 'asc' : 'desc';
        /* END */

        /*评论回答*/
        $RepliesData = $this->ask_answer_db->field('a.*, b.head_pic, b.nickname, c.nickname as `at_usersname`')
            ->alias('a')
            ->join('__USERS__ b', 'a.users_id = b.users_id', 'LEFT')
            ->join('__USERS__ c', 'a.at_users_id = c.users_id', 'LEFT')
            ->order($OrderBy)
            ->where($RepliesWhere)
            ->whereOr($WhereOr)
            ->select();
        if (empty($RepliesData)) return [];
        /* END */

        /*点赞数据*/
        $AnswerIds = get_arr_column($RepliesData, 'answer_id');
        $AnswerLikeData = $this->ask_answer_like_db->field('a.*, b.nickname')
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
            $value['content'] = htmlspecialchars_decode($value['content']);
            // 头像处理
            $value['head_pic'] = get_head_pic($value['head_pic']);
            // 个人主页URL
            $value['usershomeurl'] = usershomeurl($value['users_id']);
            // 会员昵称
            $value['nickname'] = !empty($value['nickname']) ? $value['nickname'] : $value['username'];

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
            // 回答的评论回复数
            $result['AnswerData'][$key]['CommentReplyNum'] = count($result['AnswerData'][$key]['AnswerSubData']);
            /* END */

            /*读取指定数据*/
            // 以是否审核排序，审核的优先
            array_multisort(get_arr_column($result['AnswerData'][$key]['AnswerSubData'], 'is_review'), SORT_DESC, $result['AnswerData'][$key]['AnswerSubData']);
            // 读取指定条数
            $result['AnswerData'][$key]['AnswerSubData'] = array_slice($result['AnswerData'][$key]['AnswerSubData'], $firstRow, $listRows);
            /* END */

            $result['AnswerData'][$key]['AnswerLike']['LikeNum']  = null;
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
                        if ($i != 0) {
                            $LikeName = ' 、<a href="javascript:void(0);">' . $LikeName . '</a>';
                        } else {
                            $LikeName = '<a href="javascript:void(0);">' . $LikeName . '</a>';
                        }
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
        /* NED */

        // 统计回答数
        $result['AnswerCount'] = count($RepliesData);
        return $result;
    }

    // 操作问题表回复数
    public function UpdateAskReplies($ask_id = null, $IsAdd = true, $DelNum = 0)
    {
        if (empty($ask_id)) return false;
        if (!empty($IsAdd)) {
            $Updata = [
                'replies'     => Db::raw('replies+1'),
                'update_time' => getTime(),
            ];
        } else {
            $Updata = [
                'replies'     => Db::raw('replies-1'),
                'update_time' => getTime(),
            ];
            if ($DelNum > 0) $Updata['replies'] = Db::raw('replies-' . $DelNum);
        }
        $this->ask_db->where('ask_id', $ask_id)->update($Updata);
    }

    // 增加问题浏览点击量
    public function UpdateAskClick($ask_id = null)
    {
        if (empty($ask_id)) return false;
        $Updata = [
            'click'       => Db::raw('click+1'),
            'update_time' => getTime(),
        ];
        $this->ask_db->where('ask_id', $ask_id)->update($Updata);
    }


    // 会员中心--问题中心--我的问题\回复
    public function GetUsersAskDataNew($view_uid = null, $is_ask = true)
    {
        // 返回参数
        $result = [];
        if (!empty($is_ask)) {
            // 提问问题查询列表
            /*查询字段*/
            $field = 'a.ask_id, a.ask_title, a.click, a.replies, a.add_time, a.is_review, b.type_name';
            /* END */

            /*查询条件*/
            $where = [
                'a.status'   => ['IN', [0, 1]],
                'a.users_id' => $view_uid,
            ];
            /* END */

            /* 分页 */
            $count             = $this->ask_db->alias('a')->where($where)->count('ask_id');
            $pageObj           = new Page($count, 10);
            $result['pageStr'] = $pageObj->show();
            /* END */

            /*问题表数据(问题表+会员表+问题分类表)*/
            $result['AskData'] = $this->ask_db->field($field)
                ->alias('a')
                ->join('ask_type b', 'a.type_id = b.type_id', 'LEFT')
                ->where($where)
                ->order('a.add_time desc')
                ->limit($pageObj->firstRow . ',' . $pageObj->listRows)
                ->select();
            /* END */
        } else {
            // 回答问题查询列表
            /*查询字段*/
            $field = 'a.*, b.ask_title';
            /* END */

            /*查询条件*/
            $where = [
                'a.users_id' => $view_uid,
            ];
            /* END */

            /* 分页 */
            $count             = $this->ask_answer_db->alias('a')->where($where)->count('answer_id');
            $pageObj           = new Page($count, 5);
            $result['pageStr'] = $pageObj->show();
            /* END */

            /*问题回答人查询*/
            $result['AskData'] = $this->ask_answer_db->field($field)
                ->alias('a')
                ->join('ask b', 'a.ask_id = b.ask_id', 'LEFT')
                ->where($where)
                ->order('a.add_time desc')
                ->limit($pageObj->firstRow . ',' . $pageObj->listRows)
                ->select();
            /* END */
        }

        /*数据处理*/
        foreach ($result['AskData'] as $key => $value) {
            // 问题内容Url
            $result['AskData'][$key]['AskUrl'] = askurl('home/Ask/details', ['ask_id' => $value['ask_id']]);
            // ROOT_DIR.'/index.php?m=home&c=Ask&a=details&ask_id='.$value['ask_id'];
            if (empty($is_ask)) {
                $result['AskData'][$key]['AskUrl'] .= !empty($value['answer_pid']) ? '#ul_div_li_' . $value['answer_pid'] : '#ul_div_li_' . $value['answer_id'];
            }

            if (isset($value['answer_id']) && !empty($value['answer_id'])) {
                $preg                               = '/<img.*?src=[\"|\']?(.*?)[\"|\']?\s.*?>/i';
                $value['content']                   = htmlspecialchars_decode($value['content']);
                $value['content']                   = preg_replace($preg, '[图片]', $value['content']);
                $value['content']                   = strip_tags($value['content']);
                $result['AskData'][$key]['content'] = $value['content'];
            }
        }
        /* END */

        return $result;
    }

    //贡献榜(积分)score 财富榜(金币)money
    public function GetOrderList($type = 'score')
    {
        if ($type == 'score') {
            $list  = Db::name('users')
                ->field('nickname,head_pic,users_id,scores,devote')
                ->order('scores desc')
                ->limit(10)
                ->select();
            $level = Db::name('ask_score_level')->order('min', 'asc')->select();
            foreach ($list as $key => $val) {
                foreach ($level as $k => $v) {
                    if ($val['scores'] >= $v['min'] && ( $val['scores'] <= $v['max'] || $v['max'] == 0)) {
                        $val['level'] = $v['name'];
                    } else {
                        continue;
                    }
                }
                if (!empty($val['head_pic'])) {
                    $val['head_pic'] = handle_subdir_pic($val['head_pic']);
                } else {
                    $val['head_pic'] = get_head_pic();
                }
                $val['usershomeurl'] = usershomeurl($val['users_id']); // 个人主页
                $list[$key] = $val;
            }
        } elseif ($type == 'money') {
            $list = Db::name('users_score')
                ->field('sum(money) as money,users_id')
                ->where('type', 3)
                ->group('users_id')
                ->order('money desc')
                ->limit(10)
                ->select();
            if (!empty($list)) {
                $ids = [];
                foreach ($list as $k => $v) {
                    $ids[] = $v['users_id'];
                }
                $users_data = Db::name('users')
                    ->field('nickname,head_pic,users_id,devote,scores')
                    ->where('users_id', 'in', $ids)
                    ->getAllWithIndex('users_id');
                $level      = Db::name('ask_score_level')->order('min', 'asc')->select();
                if (!empty($users_data)) {
                    foreach ($list as $key => $val) {
                        $val = array_merge($val, $users_data[$val['users_id']]);
                        if (!empty($v['head_pic'])) {
                            $val['head_pic'] = handle_subdir_pic($val['head_pic']);
                        } else {
                            $val['head_pic'] = get_head_pic();
                        }
                        foreach ($level as $k => $v) {
                            if ($val['scores'] >= $v['min'] && ( $val['scores'] <= $v['max'] || $v['max'] == 0)) {
                                $val['level'] = $v['name'];
                            } else {
                                continue;
                            }
                        }
                        $val['usershomeurl'] = usershomeurl($val['users_id']); // 个人主页
                        $list[$key] = $val;
                    }
                }
            }

        }

        return $list;
    }

    /**
     * 增加余额(设为最佳答案)$act='inc'
     */
    public function setMoney($users_id = 0, $money = 0, $ask_id = 0, $reply_id = 0)
    {
        Db::name('users')->where('users_id', $users_id)->setInc('users_money', $money);
        $data = [
            'ask_id'      => $ask_id,
            'reply_id'    => $reply_id,
            'users_id'    => $users_id,
            'money'       => $money,
            'info'        => '最佳答案',
            'type'        => 3,
            'add_time'    => getTime(),
            'update_time' => getTime(),
        ];
        Db::name('users_score')->insert($data);
    }

    /**
     * 编辑问题增加悬赏金额
     */
    public function updateMoney($users_id = 0, $money = 0, $ori_money = 0, $ask_id = 0)
    {
        Db::name('users')->where('users_id', $users_id)->setDec('users_money', $money - $ori_money);
        $data = [
            'money'       => $money,
            'update_time' => getTime(),
        ];
        Db::name('users_score')->where(['ask_id' => $ask_id, 'users_id' => $users_id, 'type' => 1])->update($data);
    }

    /**
     * 添加赠送积分记录
     * $type 1-提问 2-回答
     */
    public function setScore($users_id = 0, $ask_id = 0, $reply_id = 0, $type = 1, $money = 0)
    {
        //先判断是否开启问答送积分
        $data = getUsersConfigData('score');

        if (empty($data['score_ask_status']) && $money == 0) {
            //未开启问答送积分+没有悬赏
            return false;
        }

        if ($type == 1) {
            $score = $data['score_ask_score'];
            $count = $data['score_ask_count'];
            $info  = '问答提问';
        } elseif ($type == 2) {
            $score = $data['score_reply_score'];
            $count = $data['score_reply_count'];
            $info  = '问答回答';
        }
        if (empty($data['score_ask_status'])) {
            $score = 0;
        }
        if (!empty($count) && 0 < $count) {
            //有次数限制 判断当日次数是是否已用完
            $counts = Db::name('users_score')
                ->where('type', $type)
                ->where('users_id', $users_id)
                ->where('score', '>', 0)
                ->whereTime('add_time', 'today')
                ->count();
            if ($counts >= $count) {
                if ((1 == $type && $money == 0) || 2 == $type) {
                    return false;
                } elseif (1 == $type && !empty($money)) {
                    $score = 0;
                }
            }
        }

        $data = [
            'ask_id'      => $ask_id,
            'reply_id'    => $reply_id,
            'users_id'    => $users_id,
            'money'       => -$money,
            'info'        => $info,
            'score'       => !empty($score) ? '+' . $score : $score,
            'devote'      => $score,
            'type'        => $type,
            'add_time'    => getTime(),
            'update_time' => getTime(),
        ];
        Db::name('users_score')->insert($data);
        //会员表更新
        $update = [];
        if (!empty($score)) {
            $update['scores'] = Db::raw('scores+' . $score);
            $update['devote'] = Db::raw('devote+' . $score);
        }
        if (!empty($money)) {
            $update['users_money'] = Db::raw('users_money-' . $money);
        }
        Db::name('users')->where('users_id', $users_id)->update($update);
    }

    public function RebackDel($ask_id = 0,$ask = [])
    {
        $money = $ask['money'];
        $users_id = $ask['users_id'];
        if ($money > 0) {
            //退钱
            Db::name('users')->where('users_id', $users_id)->setInc('users_money', $money);
            // adminLog($users_id . '删除问题,id:' . $ask_id . ',标题:' . $ask['ask_title']);
        }

        $count_score = Db::name('users_score')->where(['ask_id' => $ask_id, 'users_id' => $users_id])->sum('score');
        Db::name('users_score')->where(['ask_id' => $ask_id, 'users_id' => $users_id])->delete();

        $user_info = Db::name('users')->where('users_id', $users_id)->field('users_money,scores')->find();
        if (!empty($user_info)) {
            //更新
            $update['scores'] = ($user_info['scores'] - $count_score) > 0 ? $user_info['scores'] - $count_score : 0;
            $update['update_time'] = getTime();
            Db::name('users')->where('users_id', $users_id)->update($update);
        }
    }

    // 获取问答设置内容
    public function getAskSetting($action = null)
    {
        $result = [];
        $data = tpSetting('ask');
        if (!empty($data)) {
            $AskQuesSteps = in_array($action, ['add_ask', 'edit_ask']) ? str_replace("\n", "<br/>", $data['ask_ques_steps']) : '';
            $result = [
                // 一键入群设置
                'Qq' => [
                    'ask_qq' => !empty($data['ask_qq']) ? $data['ask_qq'] : '',
                    'ask_intro' => !empty($data['ask_intro']) ? $data['ask_intro'] : '',
                    'ask_qq_link' => !empty($data['ask_qq_link']) ? $data['ask_qq_link'] : '',
                ],
                // 问答提问步骤
                'ask_ques_steps' => $AskQuesSteps,
                // 积分管理设置的积分名称
                'score_name' => getUsersConfigData('score.score_name'),
            ];
        }
        return $result;
    }

}