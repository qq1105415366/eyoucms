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

namespace app\api\logic\v1;

use think\Config;
use think\Model;
use think\Db;
use think\Request;

/**
 * 业务逻辑
 */
class ApiLogic extends Model
{
    private $request = null; // 当前Request对象实例
    private $current_lang = 'cn'; // 当前多语言标识
    public $taglib = ['apiType','apiChannel','apiList','apiArclist','apiArcview','apiPrenext','apiGuestbookform',
        'apiAdv','apiAd','apiFlink','apiGlobal','apiCollect','apiDiscount','apiLikearticle','apiLike','apiForm',
        'apiCommentlist','apiChannellist','apiDownloadpayList','apiSpecnode','apiNavigation','apiUsersTypeManage',
        'apiMediaOrderList','apiMediaOrderDetails','apiPlayList','apiUpgradeLevelOrderList',
        'apiWeappSeckillList','apiWeappSeckillView','apiCouponList','apiCouponGoodList','apiHotSearch'];

    /**
     * 析构函数
     */
    function  __construct() {
        null === $this->request && $this->request = Request::instance();
        $this->current_lang = get_current_lang();
    }

    public function taglibData($users = [])
    {
        $result = [];
        $params = input('param.');
        $aid = input('param.aid/d');
        $provider = input('param.provider/s', 'weixin');
        $typeid = input('param.typeid/d');
        $channelid = input('param.channelid/d');
        foreach ($params as $key => $val) {
            $key = preg_replace('/_(\d+)$/i', '', $key);
            if (!in_array($key, $this->taglib)) { // 排除不是标签的参数
                continue;
            }
            $val = htmlspecialchars_decode($val);
            parse_str($val, $parse);
            foreach ($parse as $_k => $_v) {
                $parse[$_k] = trim($_v);
            }
            $parse['provider'] = $provider;
            $ekey = isset($parse['ekey']) ? intval($parse['ekey']) : 1; // 多个相同标签对应的每份不同数据
            $aid = !empty($parse['aid']) ? intval($parse['aid']) : $aid;
            $typeid = !empty($parse['typeid']) ? intval($parse['typeid']) : $typeid;

            if ($key == 'apiType') { // 单个栏目标签
                $type = !empty($parse['type']) ? $parse['type'] : 'self';
                $addfields = !empty($parse['addfields']) ? $parse['addfields'] : '';
                $infolen = !empty($parse['infolen']) ? intval($parse['infolen']) : '';
                $suffix = !empty($parse['suffix']) ? $parse['suffix'] : true;
                $tagType = new \think\template\taglib\api\TagType;
                $result[$key][$ekey] = $tagType->getType($typeid, $type, $addfields, $infolen,$suffix);
            }
            else if ($key == 'apiChannel') { // 栏目列表标签
                $type = !empty($parse['type']) ? $parse['type'] : 'top';
                $currentstyle = !empty($parse['currentstyle']) ? $parse['currentstyle'] : '';
                $showalltext = !empty($parse['showalltext']) ? $parse['showalltext'] : 'off';
                if (!empty($parse['limit'])) {
                    $limit = !empty($parse['limit']) ? $parse['limit'] : 10;
                } else {
                    $limit = !empty($parse['row']) ? intval($parse['row']) : 10;
                }
                $notypeid = !empty($parse['notypeid']) ? $parse['notypeid'] : '';
                if (!empty($parse['channelid'])){
                    $channelid = $parse['channelid'];
                }
                $tagChannel = new \think\template\taglib\api\TagChannel;
                $result[$key][$ekey] = $tagChannel->getChannel($typeid, $type, $currentstyle, $showalltext, $channelid, $notypeid);
                if (!empty($result[$key][$ekey]['data'])) {
                    /*指定获取的条数*/
                    $limitarr = explode(',', $limit);
                    $offset = (1 == count($limitarr)) ? 0 : $limitarr[0];
                    $length = (1 == count($limitarr)) ? $limitarr[0] : end($limitarr);
                    $data = $result[$key][$ekey]['data'];
                    if ('off' == $showalltext) {
                        $data = array_slice($data, $offset, $length, true);
                        $data = array_merge($data);
                    } else {
                        $firstData = current($data);
                        $data = array_slice($data, $offset + 1, $length, true);
                        empty($data) && $data = [];
                        $data = array_merge([$firstData], $data);
                    }
                    /*end*/
                    $result[$key][$ekey]['data'] = $data;
                }
            }
            else if ($key == 'apiChannellist') { // 栏目列表分页标签
                $tagChannellist = new \think\template\taglib\api\TagChannellist;
                if (!empty($parse['channelid'])){
                    $channelid = $parse['channelid'];
                }
                $result[$key][$ekey] = $tagChannellist->getChannellist($parse, $typeid, $channelid);
            }
            else if ($key == 'apiList') { // 文档分页列表标签
                $parse['typeid'] = $typeid;
                $parse['channelid'] = $channelid;
                $tagList = new \think\template\taglib\api\TagList;
                $result[$key][$ekey] = $tagList->getList($parse);
            }
            else if ($key == 'apiArclist') { // 文档块列表标签
                $parse['typeid'] = $typeid;
                $tagArclist = new \think\template\taglib\api\TagArclist;
                $result[$key][$ekey] = $tagArclist->getArclist($parse);
            }
            else if ($key == 'apiArcview') { // 文档详情页
                $typeid = !empty($parse['typeid']) ? intval($parse['typeid']) : $typeid;
                $titlelen = !empty($parse['titlelen']) ? intval($parse['titlelen']) : 100;
                $addfields = !empty($parse['addfields']) ? $parse['addfields'] : '';
                $tagArcview = new \think\template\taglib\api\TagArcview;
                $result[$key][$ekey] = $tagArcview->getArcview($aid, $typeid, $addfields, $titlelen);
            }
            else if ($key == 'apiPrenext') { // 上下篇
                $typeid = !empty($parse['typeid']) ? intval($parse['typeid']) : $typeid;
                $get = !empty($parse['get']) ? $parse['get'] : 'all';
                $titlelen = !empty($parse['titlelen']) ? intval($parse['titlelen']) : 100;
                $tagPrenext = new \think\template\taglib\api\TagPrenext;
                $result[$key][$ekey] = $tagPrenext->getPrenext($aid, $typeid, $get, $titlelen);
            }
            else if ($key == 'apiGuestbookform') { // 留言表单
                $typeid = !empty($parse['typeid']) ? intval($parse['typeid']) : $typeid;
                $tagGuestbookform = new \think\template\taglib\api\TagGuestbookform;
                $result[$key][$ekey] = $tagGuestbookform->getGuestbookform($typeid);
            }
            else if ($key == 'apiAdv') { // 广告位置
                $pid = !empty($parse['pid']) ? intval($parse['pid']) : 0;
                $orderby = !empty($parse['orderby']) ? $parse['orderby'] : '';
                if (!empty($parse['limit'])) {
                    $parse['limit'] = preg_replace('/[^\d\,]/i', '', str_replace('，', ',', $parse['limit']));
                    $limit = !empty($parse['limit']) ? trim($parse['limit']) : 10;
                } else {
                    $limit = !empty($parse['row']) ? intval($parse['row']) : 10;
                }
                $tagAdv = new \think\template\taglib\api\TagAdv;
                $result[$key][$ekey] = $tagAdv->getAdv($pid, $orderby, $limit);
            }
            else if ($key == 'apiAd') { // 单个广告
                $aid = !empty($parse['aid']) ? intval($parse['aid']) : 0;
                $tagAd = new \think\template\taglib\api\TagAd;
                $result[$key][$ekey] = $tagAd->getAd($aid);
            }
            else if ($key == 'apiFlink') { // 友情链接
                $type = !empty($parse['type']) ? $parse['type'] : 'text';
                $groupid = !empty($parse['groupid']) ? intval($parse['groupid']) : 1;
                if (!empty($parse['limit'])) {
                    $parse['limit'] = preg_replace('/[^\d\,]/i', '', str_replace('，', ',', $parse['limit']));
                    $limit = !empty($parse['limit']) ? trim($parse['limit']) : 10;
                } else {
                    $limit = !empty($parse['row']) ? intval($parse['row']) : 10;
                }
                $titlelen = !empty($parse['titlelen']) ? intval($parse['titlelen']) : 100;
                $tagFlink = new \think\template\taglib\api\TagFlink;
                $result[$key][$ekey] = $tagFlink->getFlink($type, $limit, $groupid, $titlelen);
            }
            else if ($key == 'apiGlobal') { // 全局变量\自定义变量
                $name = !empty($parse['name']) ? $parse['name'] : '';
                $tagGlobal = new \think\template\taglib\api\TagGlobal;
                $result[$key][$ekey] = $tagGlobal->getGlobal($name);
            }
            else if ($key == 'apiCollect') { // 文档是否收藏标签
                $type = !empty($parse['type']) ? $parse['type'] : 'default';
                $tagCollect = new \think\template\taglib\api\TagCollect;
                $result[$key][$ekey] = $tagCollect->getCollect($aid, $type, $users);
            }
            else if ($key == 'apiLike') { // 文档是否喜欢(点赞)标签
                $type = !empty($parse['type']) ? $parse['type'] : 'default';
                $tagLike = new \think\template\taglib\api\TagLike;
                $result[$key][$ekey] = $tagLike->getLike($aid, $type, $users);
            }
            else if ($key == 'apiDiscount') { // 限时折扣
                $result[$key][$ekey] = model('v1.Shop')->getOneDiscount($parse);
            }
            else if ($key == 'apiCommentlist') { // 文档评论列表
                $tagCommentlist = new \think\template\taglib\api\TagCommentlist;
                $result[$key][$ekey] = $tagCommentlist->getCommentlist($parse, $aid);
            }
            else if ($key == 'apiLikearticle') { // 相关文档列表
                // 分页条数
                if (!empty($parse['limit'])) {
                    $parse['limit'] = preg_replace('/[^\d\,]/i', '', str_replace('，', ',', $parse['limit']));
                    $limit = !empty($parse['limit']) ? $parse['limit'] : 10;
                } else {
                    $limit = !empty($parse['row']) ? intval($parse['row']) : 10;
                }
                if (!stristr($limit, ',')) $limit = "0, {$limit}";
                
                // 排序设置
                $byabs = !empty($parse['byabs']) ? intval($parse['byabs']) : 0;
                // 是否使用缩略图
                $thumb = !empty($parse['thumb']) ? $parse['thumb'] : 'on';
                if (!empty($parse['typeid'])){
                    $typeid = $parse['typeid'];
                }
                if (!empty($parse['channelid'])){
                    $channelid = $parse['channelid'];
                }
                // 查询数据
                $tagLikearticle = new \think\template\taglib\api\TagLikearticle;
                $result[$key][$ekey] = $tagLikearticle->getLikearticle($parse,$channelid, $typeid, $limit, $byabs, $thumb);
            }
            else if($key == 'apiDownloadpayList'){      //获取下载文档下载信息
                empty($aid) && $aid = !empty($parse['aid']) ? intval($parse['aid']) : 0;
                $tagDownloadlist = new \think\template\taglib\api\TagDownloadlist;
                $result[$key][$ekey] = $tagDownloadlist->getDownloadlist($aid,$users);
            }
            else if($key == 'apiSpecnode'){  //获取指定专题节点文档列表
                empty($aid) && $aid = !empty($parse['aid']) ? intval($parse['aid']) : 0;
                $title = !empty($parse['title']) ? intval($parse['title']) : '';
                $code = !empty($parse['code']) ? intval($parse['code']) : '';
                $is_list = !empty($parse['is_list']) ? intval($parse['is_list']) : 0;
                $addfields = !empty($parse['addfields']) ? $parse['addfields'] : '';
                $tagSpecnode = new \think\template\taglib\api\TagSpecnode;
                $result[$key][$ekey] = $tagSpecnode->getSpecnode($aid,$title, $code,'',$is_list,$addfields);
            }
            else if($key == 'apiNavigation'){  //获取导航列表
                $position_id = !empty($parse['position_id']) ? intval($parse['position_id']) : null;
                $nav_id = !empty($parse['nav_id']) ? intval($parse['nav_id']) : null;
                $tagNavigation = new \think\template\taglib\api\TagNavigation;
                $result[$key][$ekey] = $tagNavigation->getNavigation($position_id,$nav_id);
            }else if ('apiUsersTypeManage' == $key){ //会员升级类型
                $manage_data = Db::name('users_type_manage')
                    ->alias('a')
                    ->field('a.*,b.level_name')
                    ->join('users_level b','a.level_id = b.level_id')
                    ->order('a.activity asc')
                    ->select();
                if (!empty($manage_data))
                $limit_arr = Config::get('global.admin_member_limit_arr');
                foreach ($manage_data as $k => $v){
                    $manage_data[$k]['time_name'] = $limit_arr[$v['limit_id']];
                }
                $result[$key][$ekey] = $manage_data;
            }else if ('apiMediaOrderList' == $key){//视频订单列表
                $users_id = session('users_id');
                $result[$key][$ekey] = false;
                if (!empty($users_id)){
                    $result[$key][$ekey] = model('v1.Order')->mediaOrderList();
                }
            }else if ('apiMediaOrderDetails' == $key){//视频订单详情
                $users_id = session('users_id');
                $result[$key][$ekey] = false;
                if (!empty($users_id)){
                    $result[$key][$ekey] = model('v1.Order')->mediaOrderDetails();
                }
            }else if ('apiPlayList' == $key){//视频播放列表
                $users_id = session('users_id');
                $result[$key][$ekey] = false;
                if (!empty($users_id)){
                    $result[$key][$ekey] = model('v1.Order')->playList();
                }
            }else if ('apiUpgradeLevelOrderList' == $key){//会员升级订单列表
                $users_id = session('users_id');
                $result[$key][$ekey] = false;
                if (!empty($users_id)){
                    $result[$key][$ekey] = model('v1.Order')->getUpgradeLevelOrderList();
                }
            } else if ($key == 'apiForm') {
                $formid = input('param.formid/d', 0);
                $formid = !empty($parse['formid']) ? intval($parse['formid']) : $formid;
                $tagGuestbookform = new \think\template\taglib\api\TagGuestbookform;
                $result[$key][$ekey] = $tagGuestbookform->getGuestbookform($formid, 1);
            }else if ($key == 'apiWeappSeckillList') {
                $page = !empty($parse['page']) ? intval($parse['page']) : 1;
                $pagesize = !empty($parse['pagesize']) ? intval($parse['pagesize']) : 10;
                if (file_exists('./core/library/think/template/taglib/api/TagWeappSeckill.php')) {
                    //获取秒杀列表
                    $tagWeappSeckill = new \think\template\taglib\api\TagWeappSeckill;
                    $result[$key][$ekey] = $tagWeappSeckill->getList($page,$pagesize);
                } else {
                    $result[$key][$ekey] = false;
                }
            }else if ($key == 'apiWeappSeckillView') {
                $goods_id = !empty($parse['goods_id']) ? intval($parse['goods_id']) : 0;
                if (file_exists('./core/library/think/template/taglib/api/TagWeappSeckill.php')) {
                    //获取秒杀商品详情
                    $tagWeappSeckill = new \think\template\taglib\api\TagWeappSeckill;
                    $result[$key][$ekey] = $tagWeappSeckill->getProductView($goods_id,$users);
                } else {
                    $result[$key][$ekey] = false;
                }
            }else if ('apiCouponList' == $key){ //优惠券列表
                $page = !empty($parse['page']) ? intval($parse['page']) : 1;
                $pagesize = !empty($parse['pagesize']) ? intval($parse['pagesize']) : 10;
                $users_id = session('users_id');

                $tagCoupon = new \think\template\taglib\api\TagCoupon;
                $result[$key][$ekey] = $tagCoupon->getList($page,$pagesize,$users_id,$parse);
            }else if ('apiCouponGoodList' == $key){ //某优惠券可使用的商品列表
                $tagCoupon = new \think\template\taglib\api\TagCoupon;
                $result[$key][$ekey] = $tagCoupon->getGoodsList($parse);
            }else if ($key == 'apiHotSearch') { // 搜索列表
                $pagesize = !empty($parse['pagesize']) ? $parse['pagesize'] : 10;
                $keyword = !empty($parse['keyword']) ? $parse['keyword'] : '';
                $tagList = new \think\template\taglib\api\TagSearch;
                $result[$key][$ekey] = $tagList->apiHotSearch($pagesize, $keyword);
            }
        }

        return $result;
    }

    // 验证微信商户配置的正确性
    public function GetWechatAppletsPay($appid = '', $mch_id = '', $apikey = '')
    {
        // 当前时间戳
        $time = time();

        // 当前时间戳 + OpenID 经 MD5加密
        $nonceStr = $out_trade_no = md5($time);

        // 调用支付接口参数
        $params = [
            'appid'            => $appid,
            'attach'           => "微信小程序支付",
            'body'             => "商品支付",
            'mch_id'           => $mch_id,
            'nonce_str'        => $nonceStr,
            'notify_url'       => url('api/Api/wxpay_notify', [], true, true, 1, 2),
            'out_trade_no'     => $out_trade_no,
            'spbill_create_ip' => clientIP(),
            'total_fee'        => 1,
            'trade_type'       => 'JSAPI'
        ];

        // 生成参数签名
        $params['sign'] = $this->ParamsSign($params, $apikey);

        // 生成参数XML格式
        $ParamsXml = $this->ParamsXml($params);

        // 调用接口返回数据
        $url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
        $result = httpRequest($url, 'POST', $ParamsXml);

        // 解析XML格式
        $ResultData = $this->ResultXml($result);

        // 数据返回
        if ($ResultData['return_code'] == 'SUCCESS' && $ResultData['return_msg'] == 'OK') {
            return ['code'=>1, 'msg'=>'验证通过'];
        } else if ($ResultData['return_code'] == 'FAIL') {
            return ['code'=>0, 'msg'=>'支付商户号或支付密钥不正确！'];
        }
    }

    private function ParamsSign($values, $apikey)
    {
        //签名步骤一：按字典序排序参数
        ksort($values);
        $string = $this->ParamsUrl($values);
        //签名步骤二：在string后加入KEY
        $string = $string . '&key=' . $apikey;
        //签名步骤三：MD5加密
        $string = md5($string);
        //签名步骤四：所有字符转为大写
        $result = strtoupper($string);
        return $result;
    }

    private function ParamsUrl($values)
    {
        $Url = '';
        foreach ($values as $k => $v) {
            if ($k != 'sign' && $v != '' && !is_array($v)) {
                $Url .= $k . '=' . $v . '&';
            }
        }
        return trim($Url, '&');
    }

    private function ParamsXml($values)
    {
        if (!is_array($values)
            || count($values) <= 0
        ) {
            return false;
        }

        $xml = "<xml>";
        foreach ($values as $key => $val) {
            if (is_numeric($val)) {
                $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
            } else {
                $xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
            }
        }
        $xml .= "</xml>";
        return $xml;
    }

    private function ResultXml($xml)
    {
        // 禁止引用外部xml实体
        @libxml_disable_entity_loader(true);
        return json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
    }
}
