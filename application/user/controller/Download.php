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
 * Date: 2019-7-3
 */

namespace app\user\controller;

use think\Cookie;
use think\Db;
use think\Page;

/**
 * 我的下载
 */
class Download extends Base
{
    public function _initialize() {
        parent::_initialize();

        $status = Db::name('channeltype')->where([
                'nid'   => 'download',
                'is_del'    => 0,
            ])->getField('status');
        if (empty($status)) {
            $this->error('下载模型已关闭，该功能被禁用！');
        }
        $this->download_order_db = Db::name('download_order');
    }

    public function index()
    {
        $where = [
            'users_id' => $this->users_id,
        ];
        $count = Db::name('download_log')->where($where)->count('log_id');
        $Page = $pager = new Page($count, config('paginate.list_rows'));
        $list = Db::name('download_log')->where($where)->group('aid')->order('log_id desc')->limit($Page->firstRow.','.$Page->listRows)->select();
        $aids = [];
        foreach ($list as $key => $val) {
            array_push($aids, $val['aid']);
        }
        $where = [
            'a.aid' => ['IN', $aids],
            'a.lang' => $this->home_lang,
        ];
        $archivesList = DB::name('archives')
            ->field("b.*, a.*, a.aid as aid")
            ->alias('a')
            ->join('__ARCTYPE__ b', 'a.typeid = b.id', 'LEFT')
            ->where($where)
            ->getAllWithIndex('aid');
        $channeltype_row = \think\Cache::get('extra_global_channeltype');
        foreach ($archivesList as $key => $val) {
            $controller_name = $channeltype_row[$val['channel']]['ctl_name'];
            $val['arcurl'] = arcurl('home/'.$controller_name.'/view', $val);
            $val['litpic'] = handle_subdir_pic($val['litpic']);

            $archivesList[$key] = $val;
        }
        $this->assign('archivesList', $archivesList);

        $show = $Page->show();
        $this->assign('page', $show);
        $this->assign('list', $list);
        $this->assign('pager', $pager);
        $this->assign('delurl', url('user/Download/download_log_del'));
        return $this->fetch('users/download_index');
    }

    // 删除下载记录
    public function download_log_del()
    {
        if (IS_AJAX_POST) {
            $id_arr = input('del_id/a');
            $id_arr = eyIntval($id_arr);
            if (!empty($id_arr)) {
                $where = [
                    'log_id' => ['IN', $id_arr],
                    'users_id' => $this->users_id,
                ];
                $result = Db::name('download_log')->where($where)->delete(true);
                if (!empty($result)) $this->success('删除成功');
            }
        }
        $this->error('删除失败');
    }

    public function search_servername()
    {
        if (IS_AJAX_POST) {
            $post = input('param.');
            $keyword = $post['keyword'];

            $servernames = unserialize(tpCache('download.download_select_servername'));

            $search_data = $servernames;
            if (!empty($keyword)) {
                $search_data = [];
                if ($servernames) {
                    foreach ($servernames as $k => $v) {
                        if (preg_match("/$keyword/s", $v)) $search_data[] = $v;
                    }
                }
            }
            $this->success("获取成功",null,$search_data);
        }
    }

    public function get_template()
    {
        if (IS_AJAX_POST) {
            //$list = Db::name('download_attr_field')->where('field_use',1)->select();
            $list = Db::name('download_attr_field')->select();
            $this->success("查询成功！", null, $list);
        }
    }

    //购买
    public function buy()
    {
        if (IS_AJAX_POST) {
            // 提交的订单信息判断
            $post = input('param.');
            if (empty($post['aid'])) $this->error('操作异常，请刷新重试');

            // 查询是否已购买
            $where = [
                'order_status' => 1,
                'product_id' => intval($post['aid']),
                'users_id' => $this->users_id
            ];
            $count = $this->download_order_db->where($where)->count();
            if (!empty($count)) $this->error('已购买过');

            // 查看是否已生成过订单
            $where['order_status'] = 0;
            $order = $this->download_order_db->where($where)->order('order_id desc')->find();

            // 查询文档内容
            $where = [
                'is_del' => 0,
                'status' => 1,
                'aid' => $post['aid'],
                'arcrank' => ['>', -1]
            ];
            $list = Db::name('archives')->where($where)->find();
            if (empty($list)) $this->error('操作异常，请刷新重试');
            $list['users_price'] = get_discount_price($this->users, $list['users_price']);

            // 订单生成规则
            $time = getTime();
            $orderCode = date('Y') . $time . rand(10, 100);
            if (!empty($order)) {
                $OrderID = $order['order_id'];
                // 更新订单信息
                $OrderData = [
                    'order_code'      => $orderCode,
                    'users_id'        => $this->users_id,
                    'order_status'    => 0,
                    'order_amount'    => $list['users_price'],
                    'product_id'      => $list['aid'],
                    'product_name'    => $list['title'],
                    'product_litpic'  => get_default_pic($list['litpic']),
                    'lang'            => $this->home_lang,
                    'add_time'        => $time,
                    'update_time'     => $time
                ];
                $this->download_order_db->where('order_id', $OrderID)->update($OrderData);
            } else {
                // 生成订单并保存到数据库
                $OrderData = [
                    'order_code'      => $orderCode,
                    'users_id'        => $this->users_id,
                    'order_status'    => 0,
                    'order_amount'    => $list['users_price'],
                    'pay_time'        => '',
                    'pay_name'        => '',
                    'wechat_pay_type' => '',
                    'pay_details'     => '',
                    'product_id'      => $list['aid'],
                    'product_name'    => $list['title'],
                    'product_litpic'  => get_default_pic($list['litpic']),
                    'lang'            => $this->home_lang,
                    'add_time'        => $time,
                    'update_time'     => $time
                ];
                $OrderID = $this->download_order_db->insertGetId($OrderData);
            }

            // 保存成功
            if (!empty($OrderID)) {
                // 支付结束后返回的URL
                $ReturnUrl = !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : $post['return_url'];
                cookie($this->users_id . '_' . $post['aid'] . '_EyouDownloadViewUrl', $ReturnUrl);
                // 对ID和订单号加密，拼装url路径
                $paydata = [
                    'type' => 10,
                    'order_id' => $OrderID,
                    'order_code' => $OrderData['order_code'],
                ];
                // 先 json_encode 后 md5 加密信息
                $paystr = md5(json_encode($paydata));
                // 清除之前的 cookie
                Cookie::delete($paystr);
                // 存入 cookie
                cookie($paystr, $paydata);
                // 跳转链接
                // if (isMobile()) {
                //     $PaymentUrl = urldecode(url('user/Pay/pay_recharge_detail',['paystr'=>$paystr]));//第一种支付
                // } else {
                //     $PaymentUrl = urldecode(url('user/Download/pay_recharge_detail',['paystr'=>$paystr]));//第二种支付,弹框支付
                // }
                $this->success('订单已生成！', urldecode(url('user/Download/pay_recharge_detail', ['paystr' => $paystr])));
            }
        } else {
            abort(404);
        }
    }

    // 购买
    public function pay_recharge_detail()
    {
        $url = url('user/Download/index');
        $channelData = Db::name('channeltype')->where(['nid'=>'download','status'=>1])->value('data');
        if (!empty($channelData)) $channelData = json_decode($channelData,true);
        if (empty($channelData['is_download_pay'])){
            $this->error('请先开启下载付费模式');
        }
        // 接收数据读取解析
        $paystr = input('param.paystr/s', '');
        $paydata = !empty($paystr) ? cookie($paystr) : [];
        if (!empty($paydata['order_id']) && !empty($paydata['order_code'])) {
            // 订单信息
            $order_id   = !empty($paydata['order_id']) ? intval($paydata['order_id']) : 0;
            $order_code = !empty($paydata['order_code']) ? $paydata['order_code'] : '';
        } else {
            $this->error('订单不存在或已变更', $url);
        }

        // 处理数据
        if (is_array($paydata) && (!empty($order_id) || !empty($order_code))) {
            $data = [];
            if (!empty($order_id)) {
                /*余额开关*/
                $pay_balance_open = getUsersConfigData('pay.pay_balance_open');
                if (!is_numeric($pay_balance_open) && empty($pay_balance_open)) {
                    $pay_balance_open = 1;
                }
                /*end*/

                if(!empty($paydata['type']) && 10 == $paydata['type']){
                    //下载模型支付订单
                    $where = [
                        'order_id'   => $order_id,
                        'order_code' => $order_code,
                        'users_id'   => $this->users_id,
                        'lang'       => $this->home_lang
                    ];
                    $data = $this->download_order_db->where($where)->find();
                    if (empty($data)) $this->error('订单不存在或已变更', $url);
                    if (in_array($data['order_status'], [1])) $this->error('订单已支付，即将跳转！', $url);

                    // 组装数据返回
                    $data['transaction_type'] = 10; // 交易类型，10为购买下载模型
                    $data['unified_id']       = $data['order_id'];
                    $data['unified_number']   = $data['order_code'];
                    $data['cause']            = $data['product_name'];
                    $data['pay_balance_open'] = $pay_balance_open;
                    $this->assign('data', $data);
                }
            }

            return $this->fetch('system/download_pay');

        }
        $this->error('参数错误！');
    }
}