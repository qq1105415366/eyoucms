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

namespace app\api\logic;

use think\Model;
use think\Db;

/**
 * 逻辑定义
 * Class CatsLogic
 * @package api\Logic
 */
class AjaxLogic extends Model
{
    public $home_lang = '';

    /**
     * 初始化操作
     */
    public function initialize() {
        parent::initialize();
        $this->home_lang = get_home_lang();
    }

    /**
     * 保存足迹
     */
    public function footprint_save($aid)
    {
        $users_id = (int)session('users_id');
        if (!empty($aid) && !empty($users_id)) {
            //查询标题模型缩略图信息
            $arc = Db::name('archives')
                ->field('aid,channel,typeid,title,litpic')
                ->where(['aid'=>$aid])
                ->find();
            if (!empty($arc)) {
                $count = Db::name('users_footprint')->where([
                    'users_id' => $users_id,
                    'aid'      => $aid,
                ])->count();
                if (empty($count)) {
                    // 足迹记录条数限制
                    $user_footprint_limit = config('global.user_footprint_limit');
                    $user_footprint_record = Db::name('users_footprint')->where(['users_id'=>$users_id])->count("id");
                    if ($user_footprint_record == $user_footprint_limit) {
                        Db::name('users_footprint')->where(['users_id' => $users_id])->order("id ASC")->limit(1)->delete();
                    }elseif ($user_footprint_record > $user_footprint_limit) {
                        $del_count = $user_footprint_record-$user_footprint_limit+1;
                        $del_ids = Db::name('users_footprint')->field("id")->where(['users_id' => $this->users_id])->order("id ASC")->limit($del_count)->select();
                        $del_ids = get_arr_column($del_ids,'id');
                        Db::name('users_footprint')->where(['id' => ['IN',$del_ids]])->delete();
                    }

                    $arc['users_id']    = $users_id;
                    $arc['lang']        = $this->home_lang;
                    $arc['add_time']    = getTime();
                    $arc['update_time'] = getTime();
                    Db::name('users_footprint')->add($arc);
                } else {
                    Db::name('users_footprint')->where([
                        'users_id' => $users_id,
                        'aid'      => $aid
                    ])->update([
                        'update_time' => getTime(),
                    ]);
                }
                return true;
            }
        } else if (IS_AJAX && !empty($aid) && empty($users_id)) {
            return true;
        }

        return false;
    }

    /**
     * 检验会员登录
     */
    public function check_userinfo()
    {
        $users = [];
        $users_id = (int)session('users_id');
        if (!empty($users_id)) {
            $users = GetUsersLatestData($users_id);
            // 头像处理
            $head_pic = get_head_pic(htmlspecialchars_decode($users['head_pic']), false, $users['sex']);
            $users['head_pic'] = func_preg_replace(['http://thirdqq.qlogo.cn'], ['https://thirdqq.qlogo.cn'], $head_pic);
            // 注册时间转换时间日期格式
            $users['reg_time'] = MyDate('Y-m-d H:i:s', $users['reg_time']);
            // 购物车数量
            $users['cart_num'] = Db::name('shop_cart')->where(['users_id'=>$users_id])->sum('product_num');
            // 收藏数
            $where = [
                'a.users_id' => $users_id,
                'c.aid' => ['GT', 0],
                'c.lang' => $this->home_lang,
            ];
            $users['collection_num'] = Db::name('users_collection')->alias('a')->where($where)->join('__ARCHIVES__ c', 'a.aid = c.aid', 'LEFT')->count('a.id');

            $data = [
                'ey_is_login'   => 1,
                'root_dir' => ROOT_DIR,
                'is_mobile' => intval($users['is_mobile']),
            ];
        }
        else {
            $data = [
                'ey_is_login'   => 0,
                'ey_third_party_login'  => $this->is_third_party_login(),
                'ey_third_party_qqlogin'  => $this->is_third_party_login('qq'),
                'ey_third_party_wxlogin'  => $this->is_third_party_login('wx'),
                'ey_third_party_wblogin'  => $this->is_third_party_login('wb'),
                'ey_login_vertify'  => $this->is_login_vertify(),
            ];
        }

        // 记录访问足迹
        $aid = input('param.aid/d');
        $this->footprint_save($aid);

        return ['users'=>$users, 'data'=>$data];
    }

    /**
     * 是否启用并开启第三方登录
     * @return boolean [description]
     */
    private function is_third_party_login($type = '')
    {
        static $result = null;
        if (null === $result) {
            $result = Db::name('weapp')->field('id,code,data')->where([
                   'code'  => ['IN', ['QqLogin','WxLogin','Wblogin']],
                   'status'    => 1,
               ])->getAllWithIndex('code');
        }
        $value = 0;
        if (empty($type)) {
           $qqlogin = 0;
           if (!empty($result['QqLogin']['data'])) {
               $qqData = unserialize($result['QqLogin']['data']);
               if (!empty($qqData['login_show'])) {
                   $qqlogin = 1;
               }
           }
           
           $wxlogin = 0;
           if (!empty($result['WxLogin']['data'])) {
               $wxData = unserialize($result['WxLogin']['data']);
               if (!empty($wxData['login_show'])) {
                   $wxlogin = 1;
               }
           }
           
           $wblogin = 0;
           if (!empty($result['Wblogin']['data'])) {
               $wbData = unserialize($result['Wblogin']['data']);
               if (!empty($wbData['login_show'])) {
                   $wblogin = 1;
               }
           }
           
           if ($qqlogin == 1 || $wxlogin == 1 || $wblogin == 1) {
               $value = 1;
           } 
        } else {
            if ('qq' == $type) {
                if (!empty($result['QqLogin']['data'])) {
                   $qqData = unserialize($result['QqLogin']['data']);
                   if (!empty($qqData['login_show'])) {
                       $value = 1;
                   }
                }
            } else if ('wx' == $type) {
                if (!empty($result['WxLogin']['data'])) {
                   $wxData = unserialize($result['WxLogin']['data']);
                   if (!empty($wxData['login_show'])) {
                       $value = 1;
                   }
                }
            } else if ('wb' == $type) {
                if (!empty($result['Wblogin']['data'])) {
                   $wbData = unserialize($result['Wblogin']['data']);
                   if (!empty($wbData['login_show'])) {
                       $value = 1;
                   }
                }
            }
        }
    
        return $value;
    }

    /**
     * 是否开启登录图形验证码
     * @return boolean [description]
     */
    private function is_login_vertify()
    {
        // 默认开启验证码
        $is_vertify          = 1;
        $users_login_captcha = config('captcha.users_login');
        if (!function_exists('imagettftext') || empty($users_login_captcha['is_on'])) {
            $is_vertify = 0; // 函数不存在，不符合开启的条件
        }

        return $is_vertify;
    }
}
