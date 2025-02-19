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
 * Date: 2019-03-26
 */

namespace app\admin\controller;

use think\Page;
use think\Db;
use think\Config;
use app\common\logic\ShopCommonLogic;

class ShopService extends Base {

    /**
     * 构造方法
     */
    public function __construct(){
        parent::__construct();

        // 验证功能版授权
        $functionLogic = new \app\common\logic\FunctionLogic;
        $functionLogic->check_authorfile(1.5);
        
        $this->language_access(); // 多语言功能操作权限

        $this->users_db              = Db::name('users');                   // 会员信息表
        $this->shop_order_service_db  = Db::name('shop_order_service');     // 订单退换明细表

        // common商城业务层，前后台共用
        $this->shop_common = new ShopCommonLogic();
        
        // 模型是否开启
        $channeltype_row = \think\Cache::get('extra_global_channeltype');
        $this->assign('channeltype_row', $channeltype_row);

        $this->shopOrderServiceModel = model('ShopOrderService');
    }

    // 退换货服务数据列表
    public function after_service()
    {   
        $param = input('param.');

        // 获取退换货服务信息
        $Result = $this->shopOrderServiceModel->GetAllServiceInfo($param);
        $this->assign('Service', $Result['Service']);
        $this->assign('page', $Result['pageStr']);
        $this->assign('pager', $Result['pageObj']);
        $this->assign('pay_name', $Result['pay_name']);
        $this->assign('order_terminal', $Result['order_terminal']);
        $this->assign('serviceJsonArr', $Result['serviceJsonArr']);
        // 售后状态
        $ServiceStatus = Config::get('global.order_service_status');
        $this->assign('ServiceStatus', $ServiceStatus);
        // 订单状态
        $admin_order_status_arr = Config::get('global.admin_order_status_arr');
        $this->assign('admin_order_status_arr', $admin_order_status_arr);

        // 是否开启文章付费
        $channelRow = Db::name('channeltype')->where('nid', 'in',['article','download'])->getAllWithIndex('nid');
        foreach ($channelRow as &$val){
            if (!empty($val['data'])) $val['data'] = json_decode($val['data'], true);
        }
        $this->assign('channelRow', $channelRow);

        // 是否开启货到付款
        $shopOpenOffline = 1;
        if (0 === intval($this->usersConfig['shop_open_offline']) || !isset($this->usersConfig['shop_open_offline'])) {
            $shopOpenOffline = 0;
        }
        $this->assign('shopOpenOffline', $shopOpenOffline);
        
        // 是否开启微信、支付宝支付
        $where = [
            'status' => 1,
            'pay_mark' => ['IN', ['wechat', 'alipay']]
        ];
        $payApiConfig = Db::name('pay_api_config')->where($where)->select();
        $openWeChat = $openAliPay = 1;
        foreach ($payApiConfig as $key => $value) {
            $payInfo = unserialize($value['pay_info']);
            if (!empty($payInfo) && isset($payInfo['is_open_wechat']) && 0 === intval($payInfo['is_open_wechat'])) {
                $openWeChat = 0;
            }
            if (!empty($payInfo) && isset($payInfo['is_open_alipay']) && 0 === intval($payInfo['is_open_alipay'])) {
                $openAliPay = 0;
            }
        }
        $this->assign('openWeChat', $openWeChat);
        $this->assign('openAliPay', $openAliPay);
        
        // 是否安装 可视化微信小程序（商城版），未安装开启则不显示小程序支付
        $where = [
            'status' => 1,
            'code' => 'DiyminiproMall'
        ];
        $openMall = Db::name('weapp')->where($where)->count();
        $this->assign('openMall', $openMall);

        // 手机端后台管理插件特定使用参数
        $isMobile = input('param.isMobile/d', 0);
        // 如果安装手机端后台管理插件并且在手机端访问时执行
        if (is_dir('./weapp/Mbackend/') && !empty($isMobile)) {
            $mbPage = input('param.p/d', 1);
            $nullShow = intval($Result['pageObj']->totalPages) === intval($mbPage) ? 1 : 0;
            $this->assign('nullShow', $nullShow);
            if ($mbPage >= 2) {
                return $this->display('shop/after_service_list');
            } else {
                return $this->display('shop/after_service');
            }
        } else {
            return $this->fetch('after_service');
        }
    }

    // 退换货服务数据详情
    public function after_service_details()
    {
        $service_id = input('param.service_id/d');
        if (!empty($service_id)) {
            // 查询服务信息
            $Result = $this->shopOrderServiceModel->GetFieldServiceInfo($service_id);
            $this->assign('Log', $Result['Log']);
            $this->assign('Users', $Result['Users']);
            $this->assign('Order', $Result['Order']);
            $this->assign('Details', $Result['Details']);
            $this->assign('Service', $Result['Service']);
            $this->assign('weappVerifyLog', $Result['weappVerifyLog']);
            $this->assign('iframe', input('param.iframe/d', 0));

            // 如果安装手机端后台管理插件并且在手机端访问时执行
            $isMobile = input('param.isMobile/d', 0);
            if (is_dir('./weapp/Mbackend/') && !empty($isMobile)) {
                return $this->display('shop/after_service_details');
            } else {
                return $this->fetch('after_service_details');
            }
        }else{
            $this->error('非法访问！');
        }
    }

    // 更新退换货信息
    public function after_service_handle()
    {
        if (IS_AJAX_POST) {
            $post = input('post.');
            if (empty($post['status']) || empty($post['service_id'])) $this->error('请选择审核意见！');
            if (empty($post['users_id']) || empty($post['order_id']) || empty($post['details_id'])) $this->error('数据错误，刷新重试！');

            // 更新服务单数据
            $result = $this->shopOrderServiceModel->afterServiceHandle($post);
            if (!empty($result)) {
                $this->success('操作成功！', url('ShopService/after_service'));
            } else {
                $this->error('操作失败！');
            }
        }
    }

    // 退款页面信息
    public function after_service_refund()
    {
        // 查询维权订单信息
        $service_id = input('param.service_id/d', 0);
        $result = $this->shopOrderServiceModel->GetFieldServiceInfo($service_id);
        if (empty($result)) $this->error('维权订单不存在');
        $this->assign($result);

        return $this->fetch();
    }

    // 退款查询(微信原路退回\...)
    public function after_service_inquire()
    {
        // 查询维权订单信息
        $service_id = input('param.service_id/d', 0);
        $where = [
            'status' => 7,
            'refund_way' => 3,
            'service_id' => intval($service_id),
        ];
        $service = $this->shop_order_service_db->field('refund_code, refund_remark, update_time')->where($where)->find();
        if (empty($service)) $this->error('维权订单不存在');

        // 查询微信退款信息
        $orderTerminal = input('param.order_terminal/d', 0);
        $result = model('PayApi')->inquireWeChatPayOrderRefund($service, $orderTerminal);
        $this->assign('result', $result);

        // 加载页面
        return $this->fetch('after_service_inquire');
    }

    // 维权订单重新发货
    public function after_service_resend()
    {
        // 查询维权订单信息
        $service_id = input('param.service_id/d', 0);
        $result = $this->shopOrderServiceModel->GetFieldServiceInfo($service_id);
        if (empty($result)) $this->error('维权订单不存在');
        $this->assign($result);

        $where = [
            'is_choose' => 1,
        ];
        $express = Db::name('shop_express')->where($where)->order('sort_order asc, express_id asc')->select();
        $this->assign('express', $express);

        return $this->fetch();
    }

    // 更新退换货信息
    public function after_service_deal_with()
    {
        if (IS_AJAX) {
            $param = input('param.');
            if (empty($param)) $this->error('请正确操作！');
            if (empty($param['status'])) $this->error('请选择审核意见！');
            $param['manual_refund'] = !empty($param['manual_refund']) ? $param['manual_refund'] : 0;

            // 换货时，卖家发货需判断快递公司及快递单号是否为空
            if (6 == $param['status']) {
                // if (empty($param['delivery']['name'])) $this->error('请填写快递公司！', null, ['id'=>'name']);
                // if (empty($param['delivery']['code'])) $this->error('请填写快递单号！', null, ['id'=>'code']);
            }

            // 更新服务单数据
            $where = [
                'users_id'   => $param['users_id'],
                'service_id' => $param['service_id']
            ];
            $update = [
                'update_time' => getTime(),
                'status' => $param['status']
            ];
            if (!empty($param['admin_note'])) $update['admin_note'] = $param['admin_note'];
            if (!empty($param['refund_price'])) $update['refund_balance'] = $param['refund_price'];
            if (!empty($param['delivery'])) $update['admin_delivery'] = serialize($param['delivery']);
            $ResultID = $this->shop_order_service_db->where($where)->update($update);

            if (!empty($ResultID)) {
                $ResultData['status'] = $param['status'];

                // 退款回会员
                if (7 == $param['status']) {
                    if (!isset($param['is_refund']) || 1 == $param['is_refund']) {
                        // 查询会员信息
                        $field = 'users_id, username, nickname, email, mobile, users_money';
                        $Users = $this->users_db->field($field)->where('users_id', $param['users_id'])->find();

                        // 退款操作
                        $UpDate = [
                            'users_money' => Db::raw('users_money+'.($param['refund_price'])),
                        ];
                        $ResultID = $this->users_db->where('users_id', $param['users_id'])->update($UpDate);
                        if (!empty($ResultID)) {
                            // 如果没有传入订单号则查询订单号
                            if (empty($param['order_code'])) {
                                $param['order_code'] = Db::name('shop_order')->where('order_id', $param['order_id'])->getField('order_code');
                            }
                            // 添加余额记录
                            UsersMoneyRecording($param['order_code'], $Users, $param['refund_price'], '商品退换货');
                        }
                    }
                }

                // 售后服务手动完成服务单并自行退款
                if (in_array($param['status'], [6, 7]) && 0 == $param['is_refund'] && 1 == $param['manual_refund']) {
                    $where = [
                        'users_id' => $param['users_id'],
                        'service_id' => $param['service_id']
                    ];
                    $update = [
                        'manual_refund' => 1,
                        'manual_time' => getTime(),
                        'refund_note' => trim($param['refund_note']),
                        'update_time' => getTime(),
                    ];
                    $this->shop_order_service_db->where($where)->update($update);
                }

                // 添加退换货服务记录
                $this->shop_common->AddOrderServiceLog($param, 0);

                // 返回结束
                $this->success('操作成功！', null, $ResultData);
            } else {
                $this->error('操作失败！');
            }
        }
    }

    // 退换货服务数据删除
    public function after_service_del()
    {
        $service_id = input('del_id/a');
        $service_id = eyIntval($service_id);
        if (IS_AJAX_POST && !empty($service_id)) {
            // 条件数组
            $Where = [
                'lang' => $this->admin_lang,
                'service_id' => ['IN', $service_id]
            ];
            // 查询数据
            $result = $this->shop_order_service_db->field('order_code')->where($Where)->select();
            $order_code_list = get_arr_column($result, 'order_code');

            // 删除数据
            $ResultID = $this->shop_order_service_db->where($Where)->delete();
            if (!empty($ResultID)) {
                // 同步删除订单下的操作记录
                Db::name('shop_order_service_log')->where($Where)->delete();

                // 存在adminlog日志
                adminLog('删除订单：'.implode(',', $order_code_list));
                $this->success('删除成功');
            } else {
                $this->error('删除失败');
            }
        }
        $this->error('参数有误');
    }

    // 会员编辑 退货数据列表
    public function users_edit_after_service()
    {
        $param = input('param.');

        // 获取退换货服务信息
        $Result = $this->shopOrderServiceModel->GetUserAllServiceInfo($param);

        // 获取订单状态
        $ServiceStatus = Config::get('global.order_service_status');

        $this->assign('Service', $Result['Service']);
        $this->assign('page', $Result['pageStr']);
        $this->assign('pager', $Result['pageObj']);
        $this->assign('ServiceStatus', $ServiceStatus);

        // 是否开启文章付费
        $channelRow = Db::name('channeltype')->where('nid', 'article')->find();
        $channelRow['data'] = json_decode($channelRow['data'], true);
        $this->assign('channelRow', $channelRow);

        // 是否开启货到付款
        $shopOpenOffline = 1;
        if (0 === intval($this->usersConfig['shop_open_offline']) || !isset($this->usersConfig['shop_open_offline'])) {
            $shopOpenOffline = 0;
        }
        $this->assign('shopOpenOffline', $shopOpenOffline);

        // 是否开启微信、支付宝支付
        $where = [
            'status' => 1,
            'pay_mark' => ['IN', ['wechat', 'alipay']]
        ];
        $payApiConfig = Db::name('pay_api_config')->where($where)->select();
        $openWeChat = $openAliPay = 1;
        foreach ($payApiConfig as $key => $value) {
            $payInfo = unserialize($value['pay_info']);
            if (!empty($payInfo) && isset($payInfo['is_open_wechat']) && 0 === intval($payInfo['is_open_wechat'])) {
                $openWeChat = 0;
            }
            if (!empty($payInfo) && isset($payInfo['is_open_alipay']) && 0 === intval($payInfo['is_open_alipay'])) {
                $openAliPay = 0;
            }
        }
        $this->assign('openWeChat', $openWeChat);
        $this->assign('openAliPay', $openAliPay);

        // 是否安装 可视化微信小程序（商城版），未安装开启则不显示小程序支付
        $where = [
            'status' => 1,
            'code' => 'DiyminiproMall'
        ];
        $openMall = Db::name('weapp')->where($where)->count();
        $this->assign('openMall', $openMall);

        return $this->fetch('member/edit/refund_index');
    }
}