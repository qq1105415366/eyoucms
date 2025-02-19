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
 * Date: 2019-3-11
 */
namespace app\admin\model;

use think\Db;
use think\Model;

/**
 * 会员属性
 */
class UsersParameter extends Model
{
    private $lang = 'cn';
    //初始化
    protected function initialize()
    {
        // 需要调用`Model`的`initialize`方法
        parent::initialize();
        $this->lang = get_current_lang();
    }

    /**
     * 校验是否允许非必填
     */
    public function isRequired($id_name='',$id_value='',$field='',$value='')
    {
        $return = true;

        $value = trim($value);
        if (($value == '0' && $field == 'is_required') || ($value == '1' && $field == 'is_hidden')) {
            $where = [
                $id_name => $id_value,
            ];
            $paraData = $this->getInfo('dtype', $where);
            if ($paraData['dtype'] == 'email') {
                $usersData = getUsersConfigData('users.users_verification');
                if ($usersData == '2') {
                    if ($value == '0') {
                        $return = [
                            'msg'   => '您已选择：会员功能设置-注册验证-邮件验证，因此邮箱地址必须为必填！',
                        ];
                    }
                    if ($value == '1') {
                        $return = [
                            'msg'   => '您已选择：会员功能设置-注册验证-邮件验证，因此邮箱地址不可隐藏！',
                        ];
                    }
                    
                }
            }
        } else if ($value == 1 && $field == 'is_reg') {
            $where = [
                $id_name => $id_value,
            ];
            $paraData = $this->getInfo('is_hidden', $where);
            if (1 == $paraData['is_hidden']) {
                $return = [
                    'msg'   => '该属性已被禁用！',
                    'time'  => 2,
                ];
            }
        }

        return $return;
    }

    public function getList($field = '*', $where = [], $index_key = '')
    {
        $map = [];
        if (!empty($where)) {
            $map = array_merge($map, $where);
        }
        if (!isset($map['lang'])) {
            $map['lang'] = $this->lang;
        }
        $result = Db::name('users_parameter')->field($field)->where($map)->cache(true, EYOUCMS_CACHE_TIME, "users_parameter")->order('sort_order asc, para_id asc')->select();
        if (!empty($index_key)) {
            $result = convert_arr_key($result, $index_key);
        }
        
        return $result;
    }

    public function getInfo($field = '*', $where = [])
    {
        $map = [];
        if (!empty($where)) {
            $map = array_merge($map, $where);
        }
        if (!isset($map['lang'])) {
            $map['lang'] = $this->lang;
        }
        $result = Db::name('users_parameter')->field($field)->where($map)->find();
        
        return $result;
    }
}