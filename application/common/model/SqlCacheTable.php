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
 * Date: 2021-04-27
 */

namespace app\common\model;

use think\Db;
use think\Model;

/**
 * mysql缓存表
 */
class SqlCacheTable extends Model
{
    //初始化
    protected function initialize()
    {
        // 需要调用`Model`的`initialize`方法
        parent::initialize();
    }

    // 更新已发布的正式缓存数据
    public function UpdateSqlCacheTable($post = [], $opt = '', $table = 'article', $users_release = false)
    {
        $citysiteArr = [];
        $citysite = ['province_id', 'city_id', 'area_id'];
        $attr = ['is_recom', 'is_special', 'is_b', 'is_head', 'is_litpic', 'is_jump', 'is_slide', 'is_roll', 'is_diyattr'];
        if ('add' == $opt) {
            // 添加时操作
            $SqlNameStr = '|' . $table . '|' . $post['channel'] . '|';
            $SqlName_1 = $SqlName_2 = [$SqlNameStr];
            foreach ($post as $key => $value) {
                // 处理属性条件，存在且选中则执行追加数组
                if (in_array($key, $attr) && !empty($value) && 1 == $value) array_push($SqlName_1, $SqlNameStr . $key . '|');
                // 处理栏目条件，查询当前栏目的父级栏目配合属性追加数组
                if (!empty($post['typeid']) && ('typeid' == $key || in_array($key, $attr) || in_array($key, $citysite))) {
                    $AllPid = model('Arctype')->getAllPid($post['typeid']);
                    $AllPid = get_arr_column($AllPid, 'id');
                    if (!empty($AllPid)) {
                        foreach ($AllPid as $vv) {
                            array_push($SqlName_2, $SqlNameStr . $vv . '|');
                            if (in_array($key, $attr) && !empty($value) && 1 == $value) {
                                array_push($SqlName_2, $SqlNameStr . $key . '|' . $vv . '|');
                            }
                            // 城市分站处理
                            if (in_array($key, $citysite)) {
                                if (!empty($value)) {
                                    if (!in_array($value, $citysiteArr)) array_push($citysiteArr, $value);
                                    array_push($SqlName_2, $SqlNameStr . $vv . '|' . 'citysite_' . implode('_', $citysiteArr) . '|');
                                }
                            }
                        }
                    }
                }

            }

            // 合并两个数组 并 去除重复数据 再 重排键序
            $SqlName = array_merge(array_unique(array_merge($SqlName_1, $SqlName_2)), $this->ContentManageCache($post, $attr));

            // 会员投稿时，若设置了自动审核则调用，添加会员列表的数据
            if (!empty($users_release)) {
                $SqlName = array_merge($SqlName, $this->UsersReleaseSqlCache($post['users_id'], $post['typeid']));
            }

            // dump($SqlName);
            // dump(array_unique($SqlName));
            // exit;
            if (!empty($SqlName)) {
                // 更新数据
                $map = [
                    'sql_name' => ['IN', $SqlName],
                ];
                $sql_result = (int)Db::name('sql_cache_table')->where($map)->value('sql_result');
                $Data = [
                    'sql_result' => $sql_result + 1,
                    'update_time' => getTime()
                ];
                // 执行更新
                Db::name('sql_cache_table')->where($map)->update($Data);
            }

        } else if ('edit' == $opt) {
            if ($post['arcrank'] > $post['old_arcrank'] && $post['old_arcrank'] == -1 && !empty($post['users_id'])) {
                // 将文章改为已审核文章
                $this->ReviewArticleHandle($post, $table, true);
            } else if ($post['old_arcrank'] > $post['arcrank'] && $post['arcrank'] == -1 && !empty($post['users_id'])) {
                // 将文章改为未审核文章
                $this->ReviewArticleHandle($post, $table, false);
            } else {
                // 编辑时操作
                $CutsDown = $Increase = $SqlName_1 = $SqlName_2 = $SqlName_3 = $SqlName_4 = $TypeID = [];
                foreach ($post['attr'] as $key => $value) {
                    if (($post[$key] != $value && 0 == $post[$key]) || ($post['typeid'] != $post['attr']['typeid'])) {
                        $SqlNameStr = '|' . $table . '|' . $post['channel'] . '|';
                        // 已取消选择该属性，处理属性条件，存在且选中则执行追加数组
                        if ($post[$key] != $value && 0 == $post[$key] && in_array($key, $attr)) {
                            array_push($SqlName_1, $SqlNameStr . $key . '|');
                        }
                        if ($post['typeid'] != $post['attr']['typeid']) {
                            $AllPid = [];
                            if (!empty($post['old_typeid']) && !empty($users_release)) {
                                $ArctypeModel = $this->GetArctypeModel($post['old_typeid']);
                                $AllPid = $ArctypeModel['AllPid'];
                                $SqlNameStr = $ArctypeModel['SqlNameStr'];
                                array_push($SqlName_2, $SqlNameStr);
                            } else {
                                // 获取 typeid 的所有父级 typeid
                                $AllPid = $this->GetAllPid($TypeID, $post['attr']['typeid']);
                            }

                            // 处理栏目条件，配合属性追加数组
                            if (!empty($AllPid)) {
                                foreach ($AllPid as $vvv) {
                                    array_push($SqlName_2, $SqlNameStr . $vvv . '|');
                                    if ($post[$key] != $value && 0 == $post[$key] && in_array($key, $attr)) {
                                        array_push($SqlName_2, $SqlNameStr . $key . '|' . $vvv . '|');
                                    }
                                }
                            }
                        }
                        $CutsDown = array_merge(array_unique(array_merge($SqlName_1, $SqlName_2)));
                    }

                    if (($post[$key] != $value && 1 == $post[$key]) || ($post['typeid'] != $post['attr']['typeid'])) {
                        $SqlNameStr = '|' . $table . '|' . $post['channel'] . '|';
                        // 已重新选择该属性，处理属性条件，存在且选中则执行追加数组
                        if ($post[$key] != $value && 1 == $post[$key] && in_array($key, $attr)) {
                            array_push($SqlName_3, $SqlNameStr . $key . '|');
                        }
                        if ($post['typeid'] != $post['attr']['typeid']) {
                            // 前台投稿操作时执行处理
                            if (!empty($post['old_typeid']) && !empty($users_release)) array_push($SqlName_4, $SqlNameStr);

                            // 获取 typeid 的所有父级 typeid
                            $AllPid = $this->GetAllPid($TypeID, $post['typeid']);

                            // 处理栏目条件，配合属性追加数组
                            if (!empty($AllPid)) {
                                foreach ($AllPid as $vvv) {
                                    array_push($SqlName_4, $SqlNameStr . $vvv . '|');
                                    if ($post[$key] != $value && 1 == $post[$key] && in_array($key, $attr)) {
                                        array_push($SqlName_4, $SqlNameStr . $key . '|' . $vvv . '|');
                                    }
                                }
                            }
                        }
                        $Increase = array_merge(array_unique(array_merge($SqlName_3, $SqlName_4)));
                    }
                }
                // 减少和增加指定缓存标识的缓存结果数据
                $this->CutsDownAndIncreaseSqlCacheTable($CutsDown, $Increase);
            }

        } else if ('del' == $opt) {
            // 删除时操作
            $SqlName_1 = $SqlName_2 = $TypeID = [];
            $post = array_merge($post);
            foreach ($post as $key => $value) {
                $SqlNameStr = '|' . $value['table'] . '|' . $value['channel'] . '|';
                array_push($SqlName_1, $SqlNameStr);
                $AllPid = [];
                foreach ($value as $kkk => $vvv) {
                    // 处理属性条件，存在且选中则执行追加数组
                    if (in_array($kkk, $attr) && !empty($vvv) && 1 == $vvv) array_push($SqlName_1, $SqlNameStr . $kkk . '|');
                    // 查询当前栏目ID的父级栏目ID
                    if ($kkk == 'typeid' && !empty($value['typeid'])) {
                        // 获取 typeid 的所有父级 typeid
                        $AllPid = $this->GetAllPid($TypeID, $value['typeid']);
                    }
                }
                // 处理栏目条件，配合属性追加数组
                if (!empty($AllPid)) {
                    foreach ($AllPid as $vv) {
                        array_push($SqlName_2, $SqlNameStr . $vv . '|');
                        if (in_array($kkk, $attr) && !empty($vvv) && 1 == $vvv) {
                            array_push($SqlName_2, $SqlNameStr . $kkk . '|' . $vv . '|');
                        }
                    }
                }
            }

            // 合并两个数组
            $SqlName = array_merge($SqlName_1, $SqlName_2, $this->ContentManageCache($post, $attr, 'del'));

            // 若删除会员投稿则执行
            $PostUsersID = !empty($post[0]['users_id']) ? $post[0]['users_id'] : 0;
            if (!empty($PostUsersID)) {
                $PostTypeID = get_arr_column($post, 'typeid');
                $SqlName = array_merge($SqlName, $this->UsersReleaseSqlCache($PostUsersID, $PostTypeID));
            }

            // 根据 SqlName 执行更新指定缓存的文章数量
            $this->CutsDownSqlCacheTable($SqlName);

        }

        // 删除缓存表中首页内容统计和栏目管理文档统计数据
        $this->DeleteSqlCacheTable($table);
    }

    // 更新投稿缓存数据
    public function UpdateDraftSqlCacheTable($post = [], $opt = '', $users_release = false)
    {
        if ('add' == $opt) {
            // 添加时操作
            $Archives = '|archives|draft|';
            $UsersRelease = '|users_release|' . $post['users_id'] . '|';
            $SqlName = array_merge([$UsersRelease . 'all|'], [$Archives]);

            // 处理栏目条件，查询当前栏目的父级栏目配合属性追加数组
            $AllPid = [];
            if (!empty($post['typeid'])) {
                $AllPid = model('Arctype')->getAllPid($post['typeid']);
                $AllPid = get_arr_column($AllPid, 'id');
                if (!empty($AllPid)) {
                    foreach ($AllPid as $vv) {
                        array_push($SqlName, $UsersRelease . $vv . '|');
                        array_push($SqlName, $Archives . $vv . '|');
                    }
                }
            }

            if (!empty($SqlName)) {
                // 更新数据
                $map = [
                    'sql_name' => ['IN', $SqlName],
                ];
                $sql_result = (int)Db::name('sql_cache_table')->where($map)->value('sql_result');
                $Data = [
                    'sql_result' => $sql_result + 1,
                    'update_time' => getTime()
                ];
                // 执行更新
                Db::name('sql_cache_table')->where($map)->update($Data);
            }

        } else if ('edit' == $opt) {
            // 编辑时操作
            $CutsDown = $Increase = [];
            if ($post['old_typeid'] != $post['typeid']) {
                $Archives = '|archives|draft|';
                $UsersID = !empty($post['users_id']) ? $post['users_id'] : session('users_id');
                $UsersRelease = '|users_release|' . $UsersID . '|';

                $TypeID = [];
                $typeids = array_merge([$post['old_typeid']], [$post['typeid']]);
                foreach ($typeids as $key => $value) {
                    $SqlName = [];

                    // 获取 typeid 的所有父级 typeid
                    $AllPid = $this->GetAllPid($TypeID, $value);

                    // 根据 typeid 追加数组
                    if (!empty($AllPid)) {
                        foreach ($AllPid as $vv) {
                            array_push($SqlName, $UsersRelease . $vv . '|');
                            array_push($SqlName, $Archives . $vv . '|');
                        }
                    }

                    // 赋值数组
                    if (0 == $key) {
                        $CutsDown = $SqlName;
                    } else {
                        $Increase = $SqlName;
                    }
                }
            } else {
                if (!empty($users_release)) {
                    $Archives = '|archives|draft|';
                    array_push($Increase, $Archives);
                    array_push($Increase, $Archives . $post['typeid'] . '|');
                }
            }

            // 减少和增加指定缓存标识的缓存结果数据
            $this->CutsDownAndIncreaseSqlCacheTable($CutsDown, $Increase);
            
        } else if ('del' == $opt) {
            // 删除时操作
            $UsersID = session('users_id');
            $Archives = '|archives|draft|';
            $UsersRelease = '|users_release|' . $UsersID . '|';

            $TypeID = $SqlName = [];
            foreach ($post as $key => $value) {
                // 初始化 SqlName
                array_push($SqlName, $UsersRelease . 'all|');
                array_push($SqlName, $Archives);

                // 获取 typeid 的所有父级 typeid
                $AllPid = $this->GetAllPid($TypeID, $value);

                // 根据 typeid 追加数组
                if (!empty($AllPid)) {
                    foreach ($AllPid as $vv) {
                        array_push($SqlName, $UsersRelease . $vv . '|');
                        array_push($SqlName, $Archives . $vv . '|');
                    }
                }
            }

            // 根据 SqlName 执行更新指定缓存的文章数量
            $this->CutsDownSqlCacheTable($SqlName);
            
        } else if ('admin_del' == $opt) {
            // 删除时操作
            $TypeID = $SqlName = [];
            foreach ($post['TypeID'] as $key => $value) {
                $UsersID = $post['UsersID'][$key];
                // 初始化 SqlName
                $Archives = '|archives|draft|';
                $UsersRelease = '|users_release|' . $UsersID . '|';
                array_push($SqlName, $UsersRelease . 'all|');
                array_push($SqlName, $Archives);

                // 获取 typeid 的所有父级 typeid
                $AllPid = $this->GetAllPid($TypeID, $value);

                // 根据 typeid 追加数组
                if (!empty($AllPid)) {
                    foreach ($AllPid as $vv) {
                        array_push($SqlName, $UsersRelease . $vv . '|');
                        array_push($SqlName, $Archives . $vv . '|');
                    }
                }
            }

            // 根据 SqlName 执行更新指定缓存的文章数量
            $this->CutsDownSqlCacheTable($SqlName);

        }
    }

    // 筛选出投稿文档，若为投稿文档则进行审核后续处理
    public function ScreeningArchives($aid = 0, $arcrank = 0)
    {
        $field = 'a.typeid, a.channel, a.users_id, b.table';
        $where = [
            'a.aid' => $aid,
            'a.status' => 1,
            'a.is_del' => 0,
            'a.users_id' => ['>', 0]
        ];
        $Archives = Db::name('archives')->alias('a')->field($field)->join('__CHANNELTYPE__ b', 'a.channel = b.id', 'LEFT')->where($where)->find();
        if (!empty($Archives)) {
            $Archives['old_typeid'] = $Archives['typeid'];
            if (1 == $arcrank) {
                // 文档设置已审核
                $this->ReviewArticleHandle($Archives, $Archives['table'], true);
            } else if (0 == $arcrank) {
                // 文档设置未审核
                $this->ReviewArticleHandle($Archives, $Archives['table'], false);
            }

            // 删除缓存表中首页内容统计和栏目管理文档统计数据
            $this->DeleteSqlCacheTable($table);

            return true;
        } else {
            return false;
        }
    }

    // 文档已审核/未审核处理 handle=true 为已审核处理
    public function ReviewArticleHandle($post = [], $table = '', $handle = true)
    {
        $CutsDown = [];
        $ArchivesDraft = '|archives|draft|';
        array_push($CutsDown, $ArchivesDraft);

        // 只显示允许发布文档的模型，且是开启状态
        $channelIds = Db::name('channeltype')->where('status', 0)->whereOr('id', 'IN', [6,8])->column('id');
        $Increase = [];
        $ArchivesNot = '|archives|!=' . implode(',', $channelIds) . '|';
        $TableChannel = '|' . $table . '|' . $post['channel'] . '|';
        array_push($Increase, $ArchivesNot);
        array_push($Increase, $TableChannel);

        if ($post['old_typeid'] == $post['typeid']) {
            // 获取 typeid 的所有父级 typeid
            $AllPid = model('Arctype')->getAllPid($post['typeid']);
            $AllPid = get_arr_column($AllPid, 'id');

            // 根据 typeid 追加数组
            if (!empty($AllPid)) {
                foreach ($AllPid as $vv) {
                    array_push($CutsDown, $ArchivesDraft . $vv . '|');
                    array_push($Increase, $TableChannel . $vv . '|');
                }
            }

        } else if ($post['old_typeid'] != $post['typeid']) {
            $TypeID = [];
            if (!empty($handle)) {
                $typeids = [$post['old_typeid'], $post['typeid']];
            } else {
                $typeids = [$post['typeid'], $post['old_typeid']];
            }
            foreach ($typeids as $key => $value) {
                // 获取 typeid 的所有父级 typeid
                $AllPid = $this->GetAllPid($TypeID, $value);

                // 根据 typeid 追加数组
                if (!empty($AllPid)) {
                    foreach ($AllPid as $vv) {
                        if (0 == $key) {
                            array_push($CutsDown, $UsersRelease . $vv . '|');
                            array_push($CutsDown, $ArchivesDraft . $vv . '|');
                        } else {
                            array_push($Increase, $TableChannel . $vv . '|');
                        }
                    }
                }
            }

        }

        if (!empty($handle)) {
            // 减少和增加指定缓存标识的缓存结果数据
            $this->CutsDownAndIncreaseSqlCacheTable($CutsDown, $Increase);
        } else {
            // 减少和增加指定缓存标识的缓存结果数据
            $this->CutsDownAndIncreaseSqlCacheTable($Increase, $CutsDown);
        }
    }

    // 获取当前栏目ID的所有父级栏目ID
    private function GetAllPid(&$TypeID = [], $value = 0)
    {
        $AllPid = [];
        if (!empty($TypeID[$value])) {
            // 若已存在则直接读取
            $AllPid = $TypeID[$value];
        } else {
            // 若不存在则查询数据库
            $AllPid = model('Arctype')->getAllPid($value);
            $AllPid = get_arr_column($AllPid, 'id');
            $TypeID[$value] = $AllPid;
        }

        // 返回结果
        return $AllPid;
    }

    // 查询栏目及模型信息
    private function GetArctypeModel($old_typeid = 0)
    {
        $field = 'a.id, a.parent_id, a.topid, b.id as channel_id, b.table';
        $where = [
            'a.is_del' => 0,
            'b.is_del' => 0,
            'a.status' => 1,
            'b.status' => 1,
            'a.is_release' => 1,
            'b.is_release' => 1,
            'a.id' => $old_typeid,
        ];
        $ArctypeModel = Db::name('arctype')->alias('a')->join('__CHANNELTYPE__ b', 'a.current_channel = b.id', 'LEFT')->field($field)->where($where)->find();
        // 处理栏目ID
        $AllPid = [];
        array_push($AllPid, $ArctypeModel['id'], $ArctypeModel['parent_id']);
        if ($ArctypeModel['parent_id'] !== $ArctypeModel['topid']) array_push($AllPid, $ArctypeModel['topid']);
        sort($AllPid);

        // 处理缓存标识
        $SqlNameStr = '|' . $ArctypeModel['table'] . '|' . $ArctypeModel['channel_id'] . '|';
        
        // 返回处理的数据
        return ['AllPid' => $AllPid, 'SqlNameStr' => $SqlNameStr];
    }

    // 减少和增加指定缓存标识的缓存结果数据
    private function CutsDownAndIncreaseSqlCacheTable($CutsDown = [], $Increase = [])
    {
        if (!empty($CutsDown)) {
            // 更新数据
            $CutsDownData = [
                'sql_result' => Db::raw('sql_result-1'),
                'update_time' => getTime()
            ];
            // 执行更新
            Db::name('sql_cache_table')->where('sql_name', 'IN', $CutsDown)->update($CutsDownData);
        }
        if (!empty($Increase)) {
            // 更新数据
            $IncreaseData = [
                'sql_result' => Db::raw('sql_result+1'),
                'update_time' => getTime()
            ];
            // 执行更新
            Db::name('sql_cache_table')->where('sql_name', 'IN', $Increase)->update($IncreaseData);
        }
    }

    // 会员投稿时，文章自动审核则调用
    private function UsersReleaseSqlCache($users_id = 0, $typeid = 0)
    {
        $SqlName = $TypeID = [];
        $UsersRelease = '|users_release|' . $users_id . '|';
        // 处理栏目条件，查询当前栏目的父级栏目配合属性追加数组
        if (!empty($typeid)) {
            $typeid = is_array($typeid) ? $typeid : [$typeid];
            foreach ($typeid as $value) {
                array_push($SqlName, $UsersRelease . 'all|');
                $AllPid = [];
                if (!empty($TypeID[$value])) {
                    // 若已存在则直接读取
                    $AllPid = $TypeID[$value];
                } else {
                    // 若不存在则查询数据库
                    $AllPid = model('Arctype')->getAllPid($value);
                    $AllPid = get_arr_column($AllPid, 'id');
                    $TypeID[$value] = $AllPid;
                }
                if (!empty($AllPid)) {
                    foreach ($AllPid as $vv) {
                        array_push($SqlName, $UsersRelease . $vv . '|');
                    }
                }
            }
        }
        return $SqlName;
    }

    // 内容管理缓存标识统计返回
    private function ContentManageCache($post = [], $attr = [], $opt = 'add')
    {
        // 只显示允许发布文档的模型，且是开启状态
        $channelIds = Db::name('channeltype')->where('status', 0)->whereOr('id', 'IN', [6,8])->column('id');
        $citysiteNew = '';
        $ContentManage = [];
        $ContentManageStr = '|archives|!=' . implode(',', $channelIds) . '|';
        foreach ($post as $key => $value) {
            if ('add' == $opt) {
                array_push($ContentManage, $ContentManageStr);
                if (in_array($key, $attr) && !empty($value) && 1 == $value) array_push($ContentManage, $ContentManageStr.$key.'|');
            }

            if ('del' == $opt) {
                array_push($ContentManage, $ContentManageStr);
                foreach ($value as $kkk => $vvv) {
                    if (in_array($kkk, $attr) && !empty($vvv) && 1 == $vvv) array_push($ContentManage, $ContentManageStr.$kkk.'|');
                }
            }

            // 城市分站处理
            if (in_array($key, ['province_id', 'city_id', 'area_id'])) {
                if (!empty($value)) {
                    $citysiteNew .= !empty($citysiteNew) ? '_' . $value : $value;
                    array_push($ContentManage, $ContentManageStr . 'citysite_' . $citysiteNew . '|');
                }
            }
        }

        return $ContentManage;
    }

    // 添加或更新sql缓存表中记录的文档最大ID值
    public function InsertSqlCacheTable($Insert = false)
    {
        $ArchivesMaxID = Db::name('archives')->max('aid');
        $SqlQuery = Db::name('archives')->fetchSql()->max('aid');
        $SqlCacheTable = [
            'sql_name' => 'ArchivesMaxID',
            'sql_result' => $ArchivesMaxID,
            'sql_md5' => md5($SqlQuery),
            'sql_query' => $SqlQuery,
            'add_time' => getTime(),
            'update_time' => getTime(),
        ];
        if (!empty($Insert)) {
            Db::name('sql_cache_table')->insertGetId($SqlCacheTable);
        } else {
            unset($SqlCacheTable['add_time']);
            Db::name('sql_cache_table')->where('sql_name', 'ArchivesMaxID')->update($SqlCacheTable);
        }
    }

    // 减少指定缓存标识的缓存结果数据
    private function CutsDownSqlCacheTable($SqlName)
    {
        $UpdateData = [];
        if (!empty($SqlName)) {
            // 去除重复数据 再 重排键序 并查询mysql缓存表对应数据
            $ArrayUnique = array_merge(array_unique($SqlName));
            $Cache = Db::name('sql_cache_table')->field('cache_id, sql_name')->where('sql_name', 'IN', $ArrayUnique)->select();
            // 获取 重复数据条数
            $SqlName = array_count_values($SqlName);
            foreach ($Cache as $key => $value) {
                // 更新数据
                $Data = [
                    'cache_id' => $value['cache_id'],
                    'sql_result' => Db::raw('sql_result-'.($SqlName[$value['sql_name']])),
                    'update_time' => getTime()
                ];
                array_push($UpdateData, $Data);
            }
        }
        // 执行更新
        if (!empty($UpdateData)) $this->saveAll($UpdateData);
    }

    // 删除指定缓存标识（首页内容统计、栏目管理文档统计数据、指定模型搜索统计）
    private function DeleteSqlCacheTable($table = null)
    {
        $SqlName = [
            '|model|all|count|',
            '|arctype|all|count|',
        ];
        if (!empty($table) && 'archives' == $table) {
            $SqlName = [
                '|model|all|count|',
                '|arctype|all|count|',
                '|article|keywords|',
                '|product|keywords|',
                '|images|keywords|',
                '|download|keywords|',
                '|media|keywords|',
                '|special|keywords|',
                '|custom|keywords|'
            ];
        } else if (!empty($table) && 'archives' != $table) {
            $SqlName = [
                '|model|all|count|',
                '|arctype|all|count|',
                '|' . $table . '|keywords|'
            ];
        }
        Db::name('sql_cache_table')->where('sql_name', 'IN', $SqlName)->delete();
    }
}