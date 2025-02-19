<?php
/**
 * 易优CMS
 * ============================================================================
 * 版权所有 2016-2028 海口快推科技有限公司，并保留所有权利。
 * 网站地址: http://www.eyoucms.com
 * ----------------------------------------------------------------------------
 * 如果商业用途务必到官方购买正版授权, 以免引起不必要的法律纠纷.
 * ============================================================================
 * Author: 易而优团队 by 陈风任 <491085389@qq.com>
 * Date: 2021-01-14
 */

namespace app\common\logic;
use think\Model;
use think\Db;

/**
 * 商城公共逻辑业务层
 * @package common\Logic
 */
class ShopCommonLogic extends Model
{
    /**
     * 初始化操作
     */
    public function initialize() {
        parent::initialize();
        $this->users_db              = Db::name('users');               // 会员数据表
        $this->users_money_db        = Db::name('users_money');         // 会员余额明细表
        $this->shop_order_db         = Db::name('shop_order');          // 订单主表
        $this->shop_order_details_db = Db::name('shop_order_details');  // 订单明细表
    }

    public function GetOrderStatusIfno($Where = array())
    {   
        // 查询订单信息
        if (empty($Where)) return '条件错误！';
        $OrderData = $this->shop_order_db->where($Where)->field('order_code, use_balance, use_point, order_status')->find();
        if (empty($OrderData)) return '订单不存在！';

        // 返回处理
        switch ($OrderData['order_status']) {
            case '-1':
                return '订单已取消，不可取消订单！';
            break;
            
            case '1':
                return '订单已支付，不可取消订单！';
            break;

            case '2':
                return '订单已发货，不可取消订单！';
            break;

            case '3':
                return '订单已完成，不可取消订单！';
            break;

            case '4':
                return '订单已过期，不可取消订单！';
            break;

            default:
                // 订单仅在未支付时返回数组
                return $OrderData;
            break;
        }
    }

    public function UpdateUsersProcess($GetOrder = array(), $GetUsers = array())
    {
        // 如果没有传入会员信息则获取session
        $Users = $GetUsers ? $GetUsers : session('users');
        // 若数据为空则返回false
        if (empty($Users) || empty($GetOrder)) return false;
        // 当前时间
        $time = getTime();

        /*返还余额支付的金额*/
        if (!empty($GetOrder['use_balance']) && $GetOrder['use_balance'] > 0) {
            $UsersMoney['users_money'] = Db::raw('users_money+'.($GetOrder['use_balance']));
            $this->users_db->where('users_id', $Users['users_id'])->update($UsersMoney);

            /*使用余额支付时，同时添加一条记录到金额明细表*/
            $AddMoneyData = [
                'users_id'     => $Users['users_id'],
                'money'        => $GetOrder['use_balance'],
                'users_old_money' => $Users['users_money'],
                'users_money'  => $Users['users_money'] + $GetOrder['use_balance'],
                'cause'        => '订单取消，退还使用余额，订单号：' . $GetOrder['order_code'],
                'cause_type'   => 2,
                'status'       => 3,
                'order_number' => $GetOrder['order_code'],
                'add_time'     => $time,
                'update_time'  => $time,
            ];
            $this->users_money_db->add($AddMoneyData);
            /* END */
        }
        /* END */
    }

    // 前后台通过，记录商品退换货服务单信息
    public function AddOrderServiceLog($param = [], $IsUsers = 1)
    {
        if (empty($param)) return false;
        // 维权类型
        $service_type = '';
        if (!empty($param['service_type']) && 1 === intval($param['service_type'])) {
            $service_type = '换货';
        } else if (!empty($param['service_type']) && 2 === intval($param['service_type'])) {
            $service_type = '退货退款';
        } else if (!empty($param['service_type']) && 3 === intval($param['service_type'])) {
            $service_type = '退款';
        }
        // 操作事由
        $LogNote = '';
        if (2 === intval($param['status'])) {
            $LogNote = '同意' . $service_type . '申请！';
        } else if (3 === intval($param['status'])) {
            $LogNote = '拒绝' . $service_type . '申请！';
        } else if (4 === intval($param['status'])) {
            $LogNote = '已退货，商家待收货！';
        } else if (5 === intval($param['status']) && 1 === intval($param['service_type'])) {
            $LogNote = '已收到退货，待重新发货！';
        } else if (5 === intval($param['status']) && 2 === intval($param['service_type'])) {
            $LogNote = '已收到退货，待转账！';
        } else if (6 === intval($param['status'])) {
            $LogNote = '已重新发货，请买家注意查收新商品，维权完成！';
        } else if (7 === intval($param['status']) && 1 === intval($param['service_type'])) {
            $LogNote = '买家已确认收货，' . $service_type . '维权完成！';
        } else if (7 === intval($param['status']) && in_array($param['service_type'], [2, 3]) && 1 === intval($param['refund_way'])) {
            $LogNote = '商家已退款到余额，' . $service_type . '维权完成！';
        } else if (7 === intval($param['status']) && in_array($param['service_type'], [2, 3]) && 2 === intval($param['refund_way'])) {
            $LogNote = '商家已线下退款，' . $service_type . '维权完成！';
        } else if (7 === intval($param['status']) && in_array($param['service_type'], [2, 3]) && 3 === intval($param['refund_way'])) {
            $LogNote = '商家已原路退回(微信)，' . $service_type . '维权完成！';
        } else if (8 === intval($param['status'])) {
            $LogNote = '关闭' . $service_type . '申请！';
        } else if (9 === intval($param['status'])) {
            $LogNote = '已拒绝收货，请与买家联系处理！';
        }
        // 手动退款
        if (!empty($param['manual_refund']) && 1 === intval($param['manual_refund'])) {
            $LogNote = '商家手动退款完成服务，手动退款原因:' . $param['refund_note'];
        }
        // 操作人
        if (1 == $IsUsers) {
            $users_id = $param['users_id'];
            $admin_id = 0;
        } else {
            $admin_id = session('admin_id');
            $users_id = 0;
        }
        OrderServiceLog($param['service_id'], $param['order_id'], $users_id, $admin_id, $LogNote);
    }
}