<?php
/**
 * 易优CMS
 * ============================================================================
 * 版权所有 2016-2028 海口快推科技有限公司，并保留所有权利。
 * 网站地址: http://www.eyoucms.com
 * ----------------------------------------------------------------------------
 * 如果商业用途务必到官方购买正版授权, 以免引起不必要的法律纠纷.
 * ============================================================================
 * Author: 陈风任 <491085389@qq.com>
 * Date: 2019-4-13
 */

namespace think\template\taglib\eyou;

use think\Config;
use think\Db;

/**
 * 地址管理列表
 */
class TagSpaddress extends Base
{
    public $usersTplVersion    = '';
    
    //初始化
    protected function _initialize()
    {
        parent::_initialize();
        $this->usersTplVersion = getUsersTplVersion();
        $this->usersTpl2xVersion = getUsersTpl2xVersion();
    }

    /**
     * 获取地址管理数据
     */
    public function getSpaddress($opt = '')
    {
        $sourceType = input('param.type/s', 'list');
        if ($opt == 'add') {
            $UlHtmlId = 'UlHtml';
            // 封装添加收货地址JS
            $AddressData[0]['UlHtmlId'] = " id='{$UlHtmlId}' ";
            $AddressData[0]['ShopAddAddr'] = " onclick='ShopAddAddress(this);' ";
            // 查询条件
            $AddressWhere = [
                'users_id' => (int)session('users_id'),
            ];
            $addressCount = Db::name("shop_address")->where($AddressWhere)->select();
            $AddressData[0]['addressCount'] = $addressCount;

            // 传入JS参数
            $data['is_wap'] = 0;
            $data['addr_width']  = '460px';
            $data['addr_height'] = '460px';
            if (isWeixin() || isMobile()) {
                $data['is_wap'] = 1;
                $data['addr_width']  = '100%';
                $data['addr_height'] = '100%';
            } else if ($this->usersTpl2xVersion == 'v2.x' || in_array($this->usersTplVersion, ['v5'])) {
                $data['addr_width']  = '660px';
                $data['addr_height'] = '392px';
            }
            $data['UlHtmlId'] = $UlHtmlId;
            $data['sourceType'] = $sourceType;
            $data['usersTplVersion'] = $this->usersTplVersion;
            $data['usersTpl2xVersion'] = $this->usersTpl2xVersion;
            $data['shop_add_address'] = url('user/Shop/shop_add_address');
            $data['shop_edit_address'] = url('user/Shop/shop_edit_address');
            $data['shop_del_address'] = url('user/Shop/shop_del_address', ['_ajax'=>1], true, false, 1, 1, 0);
            $data['shop_set_default'] = url('user/Shop/shop_set_default_address', ['_ajax'=>1], true, false, 1, 1, 0);
            $data['shop_get_wechat_addr_url'] = isMobile() && isWeixin() ? url('user/Shop/shop_get_wechat_addr') : '';
            $data_json = json_encode($data);
            $version   = getCmsVersion();
            if (empty($this->usersTplVersion) || 'v1' == $this->usersTplVersion) {
                $jsfile = "tag_spaddress.js";
            } else {
                $jsfile = "tag_spaddress_{$this->usersTplVersion}.js";
            }
            // 循环中第一个数据带上JS代码加载
            $srcurl = get_absolute_url("{$this->root_dir}/public/static/common/js/{$jsfile}?v={$version}");
            $AddressData[0]['hidden'] = <<<EOF
<script type="text/javascript">
    var aeb461fdb660da59b0bf4777fab9eea = {$data_json};
</script>
<script language="javascript" type="text/javascript" src="{$srcurl}"></script>
EOF;
            return $AddressData;
            exit;
        }

        // 查询条件
        $AddressWhere = [
            'users_id' => session('users_id'),
        ];
        $AddressData = Db::name("shop_address")->where($AddressWhere)->order('is_default desc, addr_id asc')->select();
        if (empty($AddressData)) return false;

        // 根据地址ID查询相应的中文名字
        foreach ($AddressData as $key => $value) {
            $AddressData[$key]['DefaultHidden'] = '';
            if (!empty($value['is_default'])) {
                $DefaultAddress = $value['addr_id'];
                $AddressData[$key]['DefaultHidden'] = '<input type="hidden" name="addr_id" id="addr_id" value="'.$value['addr_id'].'">';
            }

            $AddressData[$key]['country']  = in_array($this->usersTplVersion, ['v5']) ? get_country_name($value['country']) : '中国';
            $AddressData[$key]['province'] = get_province_name($value['province']);
            $AddressData[$key]['city']     = get_city_name($value['city']);
            $AddressData[$key]['district'] = get_area_name($value['district']);

            // 会员模板版本号
            if ($this->usersTplVersion != 'v1' && isMobile()) {
                // 封装Ul的ID
                $AddressData[$key]['ul_il_id'] = " id=\"{$value['addr_id']}_ul_li\" onclick=\"selectAddress_v201146({$value['addr_id']}, this)\" ";
            } else {
                // 封装Ul的ID
                $AddressData[$key]['ul_il_id'] = " id=\"{$value['addr_id']}_ul_li\" ";
            }

            // 封装设置默认JS
            $AddressData[$key]['SetDefault'] = " onclick=\"SetDefault(this, '{$value['addr_id']}');\" data-is_default=\"{$value['is_default']}\" id=\"{$value['addr_id']}_color\" data-setbtn=\"1\" data-attr_id=\"{$value['addr_id']}\" ";

            // 封装修改收货地址JS
            $AddressData[$key]['ShopEditAddr'] = " onclick=\"ShopEditAddress('{$value['addr_id']}');\" ";

            // 封装删除收货地址JS
            $AddressData[$key]['ShopDelAddr'] = " onclick=\"ShopDelAddress('{$value['addr_id']}');\" ";

            // 封装收货人ID
            $AddressData[$key]['ConsigneeId'] = " id=\"{$value['addr_id']}_consignee\" ";

            // 封装收货人手机号ID
            $AddressData[$key]['MobileId'] = " id=\"{$value['addr_id']}_mobile\" ";

            // 封装收货地址信息
            if ($this->usersTplVersion == 'v5') {
                $AddressData[$key]['Info'] = $AddressData[$key]['country'].' '.$AddressData[$key]['province'].' '.$AddressData[$key]['city'].' '.$AddressData[$key]['district'];
            } else {
                $AddressData[$key]['Info'] = $AddressData[$key]['province'].' '.$AddressData[$key]['city'].' '.$AddressData[$key]['district'];
            }

            // 封装收货地址信息ID
            $AddressData[$key]['InfoId'] = " id=\"{$value['addr_id']}_info\" ";

            // 封装收货地址信息ID
            $AddressData[$key]['AddressId'] = " id=\"{$value['addr_id']}_address\" ";

            // 封装下单页选中JS
            $AddressData[$key]['SelectEd'] = " onclick=\"SelectEd('addr_id','{$value['addr_id']}');\" ";
        }

        // 若没有默认地址，则默认第一条数据为此次订单收货地址
        if (!empty($AddressData) && empty($DefaultAddress)) {
            $AddressData[0]['DefaultHidden'] = '<input type="hidden" name="addr_id" id="addr_id" value="'.$AddressData[0]['addr_id'].'">';
        }

        if (!empty($AddressData)) {
            return $AddressData;
        } else {
            return false;
        }
    }
}