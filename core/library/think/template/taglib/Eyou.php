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

namespace think\template\taglib;

use think\template\TagLib;

/**
 * eyou标签库解析类
 * @category   Think
 * @package  Think
 * @subpackage  Driver.Taglib
 * @author    小虎哥 <1105415366@qq.com>
 */
class Eyou extends Taglib
{

    // 标签定义
    protected $tags = [
        // 标签定义： attr 属性列表 close 是否闭合（0 或者1 默认1） alias 标签别名 level 嵌套层次
        'php'        => ['attr' => ''],
        'channel'    => ['attr' => 'modelid,channelid,typeid,notypeid,type,row,loop,currentstyle,currentclass,id,name,key,empty,mod,titlelen,offset,limit', 'alias' => 'models'],
        'channelartlist' => ['attr' => 'modelid,channelid,typeid,type,row,loop,id,key,empty,titlelen,mod,currentstyle,currentclass', 'alias' => 'modelsartlist'],
        'arclist'    => ['attr' => 'modelid,channelid,typeid,notypeid,keyword,row,loop,offset,titlelen,limit,orderby,ordermode,orderway,noflag,flag,bodylen,infolen,empty,mod,name,id,key,addfields,tagid,pagesize,thumb,joinaid,arcrank,release,idlist,idrange,aid,type,siteall', 'alias' => 'artlist'],
        'arcpagelist'=> ['attr' => 'tagid,pagesize,id,tips,loading,callback', 'alias' => 'artpagelist'],
        'list'       => ['attr' => 'modelid,channelid,typeid,notypeid,pagesize,loop,keyword,titlelen,orderby,ordermode,orderway,noflag,flag,bodylen,infolen,empty,mod,id,key,addfields,thumb,arcrank,idlist,idrange,siteall'],
        'pagelist'   => ['attr' => 'listitem,listsize', 'close' => 0],
        'position'   => ['attr' => 'symbol,style', 'alias' => 'crumb', 'close' => 0],
        'type'       => ['attr' => 'typeid,type,empty,dirname,id,addfields,addtable'],
        'arcview'    => ['attr' => 'aid,empty,id,addfields,joinaid'],
        'arcclick'   => ['attr' => 'aid,value,type', 'close' => 0],
        'downcount'  => ['attr' => 'aid', 'close' => 0],
        'collectnum' => ['attr' => 'aid', 'close' => 0],
        'freebuynum' => ['attr' => 'aid,modelid,channelid', 'close' => 0],
        'load'       => ['attr' => 'file,href,type,value,basepath', 'close' => 0, 'alias' => ['import,css,js', 'type']],
        'guestbookform'=> ['attr' => 'typeid,type,formid,empty,id,mod,key,before,beforeSubmit', 'alias' => 'form'],
        'formreply'   => ['attr' => 'aid,key,limit,row'],
        'assign'     => ['attr' => 'name,value', 'close' => 0],
        'empty'      => ['attr' => 'name'],
        'notempty'   => ['attr' => 'name'],
        'foreach'    => ['attr' => 'name,id,item,key,offset,length,mod', 'expression' => true],
        'volist'     => ['attr' => 'name,id,offset,length,key,mod,limit,row,loop', 'alias' => 'iterate'],
        'if'         => ['attr' => 'condition', 'expression' => true],
        'elseif'     => ['attr' => 'condition', 'close' => 0, 'expression' => true],
        'else'       => ['attr' => '', 'close' => 0],
        'switch'     => ['attr' => 'name', 'expression' => true],
        'case'       => ['attr' => 'value,break', 'expression' => true],
        'default'    => ['attr' => '', 'close' => 0],
        'compare'    => ['attr' => 'name,value,type', 'alias' => ['eq,equal,notequal,neq,gt,lt,egt,elt,heq,nheq', 'type']],
        'ad'         => ['attr' => 'aid,id', 'close'=>1], 
        'adv'        => ['attr' => 'pid,row,loop,orderby,where,id,empty,key,mod,currentstyle,currentclass', 'close'=>1],  
        'global'     => ['attr' => 'name', 'close' => 0],
        'static'     => ['attr' => 'file,lang,href,code,site,version', 'close' => 0], 
        'prenext'    => ['attr' => 'get,titlelen,id,empty', 'alias' => 'beafter'],
        'field'      => ['attr' => 'name,addfields,aid', 'close' => 0], 
        'searchurl'  => ['attr' => '', 'close' => 0],
        'searchform' => ['attr' => 'channel,modelid,channelid,typeid,notypeid,flag,noflag,type,empty,id,mod,key', 'close'=>1], 
        'tag'        => ['attr' => 'aid,name,row,loop,id,key,mod,typeid,getall,sort,empty,style,type', 'alias' => 'tags'],
        'tagarclist' => ['attr' => 'keyword,row,loop,offset,titlelen,limit,orderby,ordermode,orderway,noflag,flag,bodylen,infolen,empty,mod,name,id,key,addfields,tagid,pagesize,thumb,arcrank', 'alias' => 'tagsartlist'],
        'flink'      => ['attr' => 'type,groupid,row,loop,id,key,mod,titlelen,empty,limit', 'alias' => 'links'],
        'language'   => ['attr' => 'type,row,loop,id,key,mod,titlelen,empty,limit,currentstyle,currentclass'], 
        'lang'       => ['attr' => 'name,const', 'close' => 0],
        'ui'         => ['attr' => 'open', 'close' => 0],
        'uitext'     => ['attr' => 'e-id,e-page,id'],
        'uihtml'     => ['attr' => 'e-id,e-page,id'],
        'uiupload'   => ['attr' => 'e-id,e-page,id'],
        'uitype'     => ['attr' => 'e-id,e-page,id,typeid'],
        'uiarclist'  => ['attr' => 'e-id,e-page,id,typeid', 'alias' => 'uiartlist'],
        'uichannel'  => ['attr' => 'e-id,e-page,id,typeid', 'alias' => 'uimodels'],
        'uimap'      => ['attr' => 'e-id,e-page,id,width,height'],
        'uicode'     => ['attr' => 'e-id,e-page,id'],
        'uibackground'=> ['attr' => 'e-id,e-page,id,e-img', 'close' => 0],
        'sql'        => ['attr' => 'sql,key,id,mod,cachetime,empty', 'close'=>1, 'level'=>3], // eyou sql 万能标签
        'weapp'      => ['attr' => 'type', 'close' => 0], // 网站应用插件
        'range'      => ['attr' => 'name,value,type', 'alias' => ['in,notin,between,notbetween', 'type']],
        'present'    => ['attr' => 'name'],
        'notpresent' => ['attr' => 'name'],
        'defined'    => ['attr' => 'name'],
        'notdefined' => ['attr' => 'name'],
        'define'     => ['attr' => 'name,value', 'close' => 0],
        'for'        => ['attr' => 'start,end,name,comparison,step'],
        'url'        => ['attr' => 'link,vars,suffix,domain,seo_pseudo,seo_pseudo_format,seo_inlet', 'close' => 0, 'expression' => true],
        'function'   => ['attr' => 'name,vars,use,call'],
        'diyfield'   => ['attr' => 'name,id,key,mod,type,empty,limit'],
        'attribute'  => ['attr' => 'aid,type,row,loop,limit,empty,id,mod,key'],
        'attr'       => ['attr' => 'aid,name', 'close' => 0],
        'user'       => ['attr' => 'type,id,key,mod,empty,currentstyle,currentclass,img,txt,txtid,afterhtml,viewfile'],
        'weapplist'  => ['attr' => 'type,id,key,mod,empty,currentstyle,currentclass'], // 网站应用插件列表
        'usermenu'   => ['attr' => 'row,loop,id,empty,key,mod,currentstyle,currentclass,limit'], 
        // 购物行为标签
        'sppurchase' => ['attr' => 'row,loop,id,key,mod,empty,currentstyle,currentclass'],
        // 购物车大标签
        'spcart'     => ['attr' => 'row,loop,id,key,mod,empty,limit'],
        // 订单明细大标签
        'sporder'    => ['attr' => 'row,loop,id,key,mod,empty,limit'],
        // 订单提交大标签
        'spsubmitorder'=> ['attr' => 'row,loop,id,key,mod,empty,limit'],
        // 订单管理页大标签
        'sporderlist'=> ['attr' => 'row,loop,id,key,mod,empty,limit,pagesize'],
        // 地址标签
        'spaddress'  => ['attr' => 'type,row,loop,id,key,mod,empty,limit'],
        // 订单产品标签
        'spordergoods'=> ['attr' => 'row,loop,id,key,mod,empty,limit,name,titlelen'],
        // 订单状态标签
        'spstatus'   => ['attr' => 'row,loop,id,key,mod,empty,limit'],
        // 订单管理页，分页标签
        'sppageorder'  => ['attr' => 'listitem,listsize,pre_text,next_text', 'close' => 0],
        // 订单管理页搜索标签
        'spsearch' => ['attr' => 'empty,id,mod,key'],
        // 商城支付API列表
        'sppayapilist'  => ['attr' => 'id,key,mod,empty'],

        // 筛选搜索
        'screening' => ['attr' => 'empty,id,mod,key,currentstyle,currentclass,addfields,addfieldids,alltxt,typeid,anchor'],
        // 会员列表
        'memberlist' => ['attr' => 'row,loop,titlelen,limit,empty,mod,id,key,orderby,ordermode,orderway,js', 'alias' => 'userslist'],
        // 会员信息
        'memberinfos' => ['attr' => 'mid,users_id,empty,id,addfields', 'alias' => 'usersinfo'],
        //自定义url
        'diyurl'   => ['attr' => 'type,link,vars,suffix,domain,seo_pseudo,seo_pseudo_format,seo_inlet', 'close' => 0],
        // 相关文档
        'likearticle'    => ['attr' => 'modelid,channelid,limit,row,loop,titlelen,bodylen,infolen,mytypeid,typeid,byabs,empty,mod,name,id,key,thumb', 'alias' => 'relevarticle'],
        // 视频播放
        'videoplay'  => ['attr' => 'aid,empty,id,autoplay'],
        // 视频列表
        'videolist'  => ['attr' => 'aid,empty,id,mod,key,autoplay,player'],
        // 获取网站搜索的热门关键字
        'hotwords'        => ['attr' => 'subday,num,id,key,mod,maxlength,empty,orderby,ordermode,orderway,screen', 'alias' => 'hotkeywords'],
        // 插件标签通用
        'weapptaglib'     => ['attr' => 'name,id,offset,length,key,mod,limit,row,loop'],
        // 问答模型问题列表标签通用
        'asklist'     => ['attr' => 'id,key,mod,titlelen,limit,row,loop,orderby,ordermode,orderway'],
        // 专题节点列表标签
        'specnodelist' => ['attr' => 'id,key,mod,empty'],
        // 专题节点文档标签
        'specnode'    => ['attr' => 'aid,code,title,isauto,aidlist,keyword,typeid,row,loop,limit,bodylen,infolen,titlelen,name,empty,mod,id,key,thumb', 'alias' => 'specialnode'],
        'pagespecnode'   => ['attr' => 'listitem,listsize', 'alias' => 'pagespecialnode', 'close' => 0],
        // 收藏标签
        'collect'   => ['attr' => 'aid,collect,cancel,id,class'],
        // 站内通知标签
        'notice'   => ['attr' => 'id'],

        // 商品评价 -- 调用商品整体内容
        'comment' => ['attr' => 'id, aid'],
        // 商品评价 -- 仅循环评价内容
        'commentlist' => ['attr' => 'name, id, offset, length, key, mod, limit, row,loop'],
        // 区域列表
        'citysite'    => ['attr' => 'siteid,nositeid,pid,type,row,loop,currentstyle,currentclass,id,name,key,empty,mod,titlelen,offset,limit'],
        //文章付费阅读标签
        'articlepay'   => ['attr' => 'id'],
        // 导航标签
        'navigation' => ['attr' => 'position_id,row,loop,id,name,key,empty,mod,titlelen,orderby,ordermode,orderway,alltxt,currentstyle,currentclass'],
        //付费下载标签
        'downloadpay'   => ['attr' => 'id'],
    ];

    /**
     * 自动识别构建变量，传值可以使变量也可以是值
     * @access private
     * @param string $value 值或变量
     * @return string
     */
    private function varOrvalue($value)
    {
        $flag  = substr($value, 0, 1);
        if ('$' == $flag || ':' == $flag) {
            $value = $this->autoBuildVar($value);
        } else {
            $value = str_replace('"', '\"', $value);
            $value = '"' . $value . '"';
        }

        return $value;
    }

    /**
     * 万能的SQL标签
     */
    public function tagSql($tag, $content)
    {
        $sql = $tag['sql']; // sql 语句
        $sql  = $this->varOrvalue($sql);
                                            
        $key    = !empty($tag['key']) ? $tag['key'] : 'i';
        $mod    = !empty($tag['mod']) && is_numeric($tag['mod']) ? $tag['mod'] : '2';
        $id  =  !empty($tag['id']) ? $tag['id'] : 'field';// 返回的变量
        $cachetime  =  !empty($tag['cachetime']) ? $tag['cachetime'] : '';// 缓存时间
        $empty  = isset($tag['empty']) ? $tag['empty'] : '';
        $empty  = htmlspecialchars($empty);

        $parseStr = '<?php ';
        $parseStr .= ' $tagSql = new \think\template\taglib\eyou\TagSql;';
        $parseStr .= ' $_result = $tagSql->getSql('.$sql.', "'.$cachetime.'");';

        $parseStr .= 'if(is_array($_result) || $_result instanceof \think\Collection || $_result instanceof \think\Paginator): $' . $key . ' = 0; $e = 1;';
        $parseStr .= ' $__LIST__ = $_result;';

        $parseStr .= 'if( count($__LIST__)==0 ) : echo htmlspecialchars_decode("' . $empty . '");';
        $parseStr .= 'else: ';
        $parseStr .= 'foreach($__LIST__ as $key=>$' . $id . '): ';
        $parseStr .= '$' . $key . '= intval($key) + 1;?>';
        $parseStr .= '<?php $mod = ($' . $key . ' % ' . $mod . ' ); ?>';
        $parseStr .= $content;
        $parseStr .= '<?php ++$e; ?>';
        $parseStr .= '<?php endforeach; endif; else: echo htmlspecialchars_decode("' . $empty . '");endif; ?>';

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * 重置美化标签的变量，以免相互干扰
     */
    private function resetUiVal()
    {
        return '<?php ?>';
    }

    /**
     * ui 标签解析
     * 是否开启页面装饰
     * 格式： {eyou:ui open="off" /}
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string
     */
    public function tagUi($tag)
    {
        $open  = !empty($tag['open']) ? $tag['open'] : '';

        $parseStr = '<?php ';
        $parseStr .= ' $tagUi = new \think\template\taglib\eyou\TagUi;';
        $parseStr .= ' $__VALUE__ = $tagUi->getUi();';
        $parseStr .= ' echo $__VALUE__;';
        $parseStr .= ' ?>';

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * 美化标签-图片编辑
     */
    public function tagUiupload($tag, $content)
    {
        $e_id = isset($tag['e-id']) ? $tag['e-id'] : '';
        $e_page = isset($tag['e-page']) ? $tag['e-page'] : '';
        $id     = isset($tag['id']) ? $tag['id'] : 'field';

        $parseStr = '';
        $parseStr .= ' <?php ';
        $parseStr .= ' $tagUiupload = new \think\template\taglib\eyou\TagUiupload;';
        $parseStr .= ' $__LIST__ = $tagUiupload->getUiupload("'.$e_id.'", "'.$e_page.'");';
        $parseStr .= ' ?>';

        $parseStr .= '<?php if((is_array($__LIST__)) && (!empty($__LIST__["value"]) || (($__LIST__["value"] instanceof \think\Collection || $__LIST__["value"] instanceof \think\Paginator ) && $__LIST__["value"]->isEmpty()))): ?>';
        $parseStr .= '<?php $'.$id.' = $__LIST__; ?>';
        $parseStr .= $content;
        $parseStr .= '<?php endif; ?>';

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * 美化标签-栏目列表编辑
     */
    public function tagUichannel($tag, $content)
    {
        $typeid = isset($tag['typeid']) ? $tag['typeid'] : '';
        $e_id = isset($tag['e-id']) ? $tag['e-id'] : '';
        $e_page = isset($tag['e-page']) ? $tag['e-page'] : '';
        $id     = isset($tag['id']) ? $tag['id'] : 'field';

        $parseStr = '';
        $parseStr .= ' <?php ';
        $parseStr .= ' $tagUichannel = new \think\template\taglib\eyou\TagUichannel;';
        $parseStr .= ' $__LIST__ = $tagUichannel->getUichannel("'.$typeid.'","'.$e_id.'", "'.$e_page.'"); ?>';

        $parseStr .= '<?php if((is_array($__LIST__)) && (!empty($__LIST__["info"]) || (($__LIST__["info"] instanceof \think\Collection || $__LIST__["info"] instanceof \think\Paginator ) && $__LIST__["info"]->isEmpty()))): ?>';
        $parseStr .= '<?php $'.$id.' = $__LIST__; ';
        $parseStr .= ' $ui_typeid = !empty($'.$id.'["info"]["typeid"]) ? $'.$id.'["info"]["typeid"] : "";';
        // $parseStr .= ' $ui_row = !empty($'.$id.'["info"]["row"]) ? $'.$id.'["info"]["row"] : "";';
        $parseStr .= '?>';
        $parseStr .= $content;
        $parseStr .= '<?php $ui_typeid = $ui_row = ""; ?>';
        $parseStr .= '<?php endif; ?>';

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * 美化标签-栏目编辑
     */
    public function tagUitype($tag, $content)
    {
        $typeid = isset($tag['typeid']) ? $tag['typeid'] : '';
        $e_id = isset($tag['e-id']) ? $tag['e-id'] : '';
        $e_page = isset($tag['e-page']) ? $tag['e-page'] : '';
        $id     = isset($tag['id']) ? $tag['id'] : 'field';

        $parseStr = '';
        $parseStr .= ' <?php ';
        $parseStr .= ' $tagUitype = new \think\template\taglib\eyou\TagUitype;';
        $parseStr .= ' $__LIST__ = $tagUitype->getUitype("'.$typeid.'","'.$e_id.'", "'.$e_page.'"); ?>';

        $parseStr .= '<?php if((is_array($__LIST__)) && (!empty($__LIST__["info"]) || (($__LIST__["info"] instanceof \think\Collection || $__LIST__["info"] instanceof \think\Paginator ) && $__LIST__["info"]->isEmpty()))): ?>';
        $parseStr .= '<?php $'.$id.' = $__LIST__; ';
        $parseStr .= ' $ui_typeid = !empty($'.$id.'["info"]["typeid"]) ? $'.$id.'["info"]["typeid"] : "";';
        $parseStr .= '?>';
        $parseStr .= $content;
        $parseStr .= '<?php $ui_typeid = ""; ?>';
        $parseStr .= '<?php endif; ?>';

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * 美化标签-栏目文章编辑
     */
    public function tagUiarclist($tag, $content)
    {
        $typeid = isset($tag['typeid']) ? $tag['typeid'] : '';
        $e_id = isset($tag['e-id']) ? $tag['e-id'] : '';
        $e_page = isset($tag['e-page']) ? $tag['e-page'] : '';
        $id     = isset($tag['id']) ? $tag['id'] : 'field';

        $parseStr = '';
        $parseStr .= ' <?php ';
        $parseStr .= ' $tagUiarclist = new \think\template\taglib\eyou\TagUiarclist;';
        $parseStr .= ' $__LIST__ = $tagUiarclist->getUiarclist("'.$typeid.'","'.$e_id.'", "'.$e_page.'"); ?>';

        $parseStr .= '<?php if((is_array($__LIST__)) && (!empty($__LIST__["info"]) || (($__LIST__["info"] instanceof \think\Collection || $__LIST__["info"] instanceof \think\Paginator ) && $__LIST__["info"]->isEmpty()))): ?>';
        $parseStr .= '<?php $'.$id.' = $__LIST__; ';
        $parseStr .= ' $ui_typeid = !empty($'.$id.'["info"]["typeid"]) ? $'.$id.'["info"]["typeid"] : "";';
        // $parseStr .= ' $ui_row = !empty($'.$id.'["info"]["row"]) ? $'.$id.'["info"]["row"] : "";';
        $parseStr .= '?>';
        $parseStr .= $content;
        $parseStr .= '<?php $ui_typeid = $ui_row = ""; ?>';
        $parseStr .= '<?php endif; ?>';

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * 美化标签-富文本编辑器
     */
    public function tagUihtml($tag, $content)
    {
        $e_id = isset($tag['e-id']) ? $tag['e-id'] : '';
        $e_page = isset($tag['e-page']) ? $tag['e-page'] : '';
        $id     = isset($tag['id']) ? $tag['id'] : 'field';

        $parseStr = '';
        $parseStr .= ' <?php ';
        $parseStr .= ' $tagUihtml = new \think\template\taglib\eyou\TagUihtml;';
        $parseStr .= ' $__LIST__ = $tagUihtml->getUihtml("'.$e_id.'", "'.$e_page.'");';
        $parseStr .= ' ?>';

        $parseStr .= '<?php if((is_array($__LIST__)) && (!empty($__LIST__["value"]) || (($__LIST__["value"] instanceof \think\Collection || $__LIST__["value"] instanceof \think\Paginator ) && $__LIST__["value"]->isEmpty()))): ?>';
        $parseStr .= '<?php $'.$id.' = $__LIST__; ?>';
        $parseStr .= $content;
        $parseStr .= '<?php endif; ?>';

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * 美化标签-百度地图
     */
    public function tagUimap($tag, $content)
    {
        $e_id = isset($tag['e-id']) ? $tag['e-id'] : '';
        $e_page = isset($tag['e-page']) ? $tag['e-page'] : '';
        $id     = isset($tag['id']) ? $tag['id'] : 'field';
        $width = !empty($tag['width']) ? $tag['width'] : '100%';
        $height = !empty($tag['height']) ? $tag['height'] : 350;

        $parseStr = '';
        $parseStr .= ' <?php ';
        $parseStr .= ' $tagUimap = new \think\template\taglib\eyou\TagUimap;';
        $parseStr .= ' $__LIST__ = $tagUimap->getUimap("'.$e_id.'", "'.$e_page.'", "'.$width.'", "'.$height.'");';
        $parseStr .= ' ?>';

        $parseStr .= '<?php if((is_array($__LIST__)) && (!empty($__LIST__["value"]) || (($__LIST__["value"] instanceof \think\Collection || $__LIST__["value"] instanceof \think\Paginator ) && $__LIST__["value"]->isEmpty()))): ?>';
        $parseStr .= '<?php $'.$id.' = $__LIST__; ?>';
        $parseStr .= $content;
        $parseStr .= '<?php endif; ?>';

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * 美化标签-代码编辑
     */
    public function tagUicode($tag, $content)
    {
        $e_id = isset($tag['e-id']) ? $tag['e-id'] : '';
        $e_page = isset($tag['e-page']) ? $tag['e-page'] : '';
        $id     = isset($tag['id']) ? $tag['id'] : 'field';

        $parseStr = '';
        $parseStr .= ' <?php ';
        $parseStr .= ' $tagUicode = new \think\template\taglib\eyou\TagUicode;';
        $parseStr .= ' $__LIST__ = $tagUicode->getUicode("'.$e_id.'", "'.$e_page.'");';
        $parseStr .= ' ?>';

        $parseStr .= '<?php if((is_array($__LIST__)) && (!empty($__LIST__["value"]) || (($__LIST__["value"] instanceof \think\Collection || $__LIST__["value"] instanceof \think\Paginator ) && $__LIST__["value"]->isEmpty()))): ?>';
        $parseStr .= '<?php $'.$id.' = $__LIST__; ?>';
        $parseStr .= $content;
        $parseStr .= '<?php endif; ?>';

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * 美化标签-纯文本编辑
     */
    public function tagUitext($tag, $content)
    {
        $e_id = isset($tag['e-id']) ? $tag['e-id'] : '';
        $e_page = isset($tag['e-page']) ? $tag['e-page'] : '';
        $id     = isset($tag['id']) ? $tag['id'] : 'field';

        $parseStr = '';
        $parseStr .= ' <?php ';
        $parseStr .= ' $tagUitext = new \think\template\taglib\eyou\TagUitext;';
        $parseStr .= ' $__LIST__ = $tagUitext->getUitext("'.$e_id.'", "'.$e_page.'");';
        // $parseStr .= ' $__LIST__["value"] = ';
        $parseStr .= ' ?>';

        $parseStr .= '<?php if((is_array($__LIST__)) && (!empty($__LIST__["value"]) || (($__LIST__["value"] instanceof \think\Collection || $__LIST__["value"] instanceof \think\Paginator ) && $__LIST__["value"]->isEmpty()))): ?>';
        $parseStr .= '<?php $'.$id.' = $__LIST__; ?>';
        $parseStr .= $content;
        $parseStr .= '<?php endif; ?>';

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * 美化标签-背景图片编辑
     */
    public function tagUibackground($tag, $content)
    {
        $e_id = isset($tag['e-id']) ? $tag['e-id'] : '';
        $e_page = isset($tag['e-page']) ? $tag['e-page'] : '';
        $id     = isset($tag['id']) ? $tag['id'] : 'field';
        $e_img     = isset($tag['e-img']) ? $tag['e-img'] : '';

        $parseStr = ' <?php ';
        $parseStr .= ' $tagUibackground = new \think\template\taglib\eyou\TagUibackground;';
        $parseStr .= ' $__VALUE__ = $tagUibackground->getUibackground("'.$e_id.'", "'.$e_page.'", "'.$e_img.'");';
        $parseStr .= ' echo $__VALUE__;';
        $parseStr .= ' ?>';

        return $parseStr;
    }

    /**
     * load 标签解析 {load file="/static/js/base.js" /}
     * 格式：{load file="/static/css/base.css" /}
     * @access public
     * @param array $tag 标签属性
     * @return string
     */
    public function tagLoad($tag)
    {
        $file     = isset($tag['file']) ? $tag['file'] : $tag['href'];
        $type     = isset($tag['type']) ? strtolower($tag['type']) : '';
        $ver     = !empty($tag['ver']) ? $tag['ver'] : 'on';
        $startStr = '';
        $parseStr = '';
        $endStr   = '';
        // 判断是否存在加载条件 允许使用函数判断(默认为isset)
        if (isset($tag['value'])) {
            $name = $tag['value'];
            $name = $this->autoBuildVar($name);
            $name = 'isset(' . $name . ')';
            $startStr = '<?php if(' . $name . '): ?>';
            $endStr = '<?php endif; ?>';
        }

        $parseStr .= $startStr;
        $parseStr .= ' <? $tagLoad = new \think\template\taglib\eyou\TagLoad;';
        $parseStr .= ' $__VALUE__ = $tagLoad->getLoad("'.$file.'", "'.$ver.'");';
        $parseStr .= ' echo $__VALUE__;';
        $parseStr .= ' ?>';
        $parseStr .= $endStr;

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * static 标签解析 {eyou:static file="/static/js/base.js" /}
     * 格式：{eyou:static file="/static/css/base.css" /}
     * @access public
     * @param array $tag 标签属性
     * @return string
     */
    public function tagStatic($tag)
    {
        $file  = isset($tag['file']) ? $tag['file'] : '';
        $file = $this->varOrvalue($file);
        $href  = isset($tag['href']) ? $tag['href'] : '';
        $href = $this->varOrvalue($href);
        $lang = !empty($tag['lang']) ? $tag['lang'] : '';
        $lang = $this->varOrvalue($lang);
        $site = !empty($tag['site']) ? $tag['site'] : '';
        $site = $this->varOrvalue($site);
        $code = !empty($tag['code']) ? $tag['code'] : '';
        $version = !empty($tag['version']) && $tag['version'] !== 'false' ? true : false;

        $parseStr = '<?php ';

        // 查询数据库获取的数据集
        $parseStr .= ' $tagStatic = new \think\template\taglib\eyou\TagStatic;';
        $parseStr .= ' $__VALUE__ = $tagStatic->getStatic('.$file.','.$lang.','.$href.',"'.$code.'",'.$site.',"'.$version.'");';
        $parseStr .= ' echo $__VALUE__;';
        $parseStr .= ' ?>';

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * channel 标签解析 用于获取栏目列表
     * 格式：type:son表示下级栏目,self表示同级栏目,top顶级栏目
     * {eyou:channel typeid='1' type='son' loop='10' empty='' name='' id='' key='' titlelen='' offset='' mod='' currentclass='active'}
     *  <li><a href='{$field:typelink}'>{$field:typename}</a> </li> 
     * {/eyou:channel}
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string|void
     */
    public function tagChannel($tag, $content)
    {
        $modelid   = isset($tag['modelid']) ? $tag['modelid'] : (isset($tag['channelid']) ? $tag['channelid'] : '');
        $modelid  = $this->varOrvalue($modelid);

        $typeid  = !empty($tag['typeid']) ? $tag['typeid'] : '';
        $typeid  = $this->varOrvalue($typeid);

        $notypeid  = !empty($tag['notypeid']) ? $tag['notypeid'] : '';
        $notypeid  = $this->varOrvalue($notypeid);

        $name   = !empty($tag['name']) ? $tag['name'] : '';
        $type   = !empty($tag['type']) ? $tag['type'] : 'son';
        $currentclass   = !empty($tag['currentclass']) ? $tag['currentclass'] : '';
        if (!isset($tag['currentclass'])) { // 加强兼容性
            $currentclass   = !empty($tag['currentstyle']) ? $tag['currentstyle'] : '';
        }
        $id     = isset($tag['id']) ? $tag['id'] : 'field';
        $key    = !empty($tag['key']) ? $tag['key'] : 'i';
        $empty  = isset($tag['empty']) ? $tag['empty'] : '';
        $empty  = htmlspecialchars($empty);
        $mod    = !empty($tag['mod']) && is_numeric($tag['mod']) ? $tag['mod'] : '2';
        $titlelen = !empty($tag['titlelen']) && is_numeric($tag['titlelen']) ? intval($tag['titlelen']) : 100;
        $offset = !empty($tag['offset']) && is_numeric($tag['offset']) ? intval($tag['offset']) : 0;
        if (isset($tag['loop'])) $tag['row'] = $tag['loop'];
        $row = !empty($tag['row']) && is_numeric($tag['row']) ? intval($tag['row']) : 100;
        if (!empty($tag['limit'])) {
            $limitArr = explode(',', $tag['limit']);
            $offset = !empty($limitArr[0]) ? intval($limitArr[0]) : 0;
            $row = !empty($limitArr[1]) ? intval($limitArr[1]) : 0;
        }

        // 获取最顶级父栏目ID
        // $topTypeId = 0;
        // if ($tid >0 && $type == 'top') {
        //     $result = model('Arctype')->getAllPid($tid);
        //     reset($result);
        //     $firstVal = current($result);
        //     $topTypeId = $firstVal['id'];
        // }

        $parseStr = '<?php ';
        // 声明变量
        /*typeid的优先级别从高到低：装修数据 -> 标签属性值 -> 外层标签channelartlist属性值*/
        $parseStr .= ' if(isset($ui_typeid) && !empty($ui_typeid)) : $typeid = $ui_typeid; else: $typeid = '.$typeid.'; endif;';
        $parseStr .= ' if(isset($ui_modelid) && !empty($ui_modelid)) : $modelid = $ui_modelid; else: $modelid = '.$modelid.'; endif;';
        $parseStr .= ' if(empty($typeid) && isset($channelartlist["id"]) && !empty($channelartlist["id"])) : $typeid = intval($channelartlist["id"]); endif; ';
        /*--end*/
        $parseStr .= ' if(isset($ui_row) && !empty($ui_row)) : $row = $ui_row; else: $row = '.$row.'; endif;';

        if ($name) { // 从模板中传入数据集
            $symbol     = substr($name, 0, 1);
            if (':' == $symbol) {
                $name = $this->autoBuildVar($name);
                $parseStr .= '$_result=' . $name . ';';
                $name = '$_result';
            } else {
                $name = $this->autoBuildVar($name);
            }

            $parseStr .= 'if(is_array(' . $name . ') || ' . $name . ' instanceof \think\Collection || ' . $name . ' instanceof \think\Paginator): $' . $key . ' = 0; $e = 1;';
            // 设置了输出数组长度
            if (0 != $offset || 'null' != $row) {
                $parseStr .= '$__LIST__ = is_array(' . $name . ') ? array_slice(' . $name . ',' . $offset . ',' . $row . ', true) : ' . $name . '->slice(' . $offset . ',' . $row . ', true); ';
            } else {
                $parseStr .= ' $__LIST__ = ' . $name . ';';
            }

        } else { // 查询数据库获取的数据集
            $parseStr .= ' $tagChannel = new \think\template\taglib\eyou\TagChannel;';
            $parseStr .= ' $_result = $tagChannel->getChannel($typeid, "'.$type.'", "'.$currentclass.'", '.$notypeid.', $modelid);';
            $parseStr .= ' if(is_array($_result) || $_result instanceof \think\Collection || $_result instanceof \think\Paginator): $' . $key . ' = 0; $e = 1;';
            // 设置了输出数组长度
            if (0 != $offset || 'null' != $row) {
                $parseStr .= '$__LIST__ = is_array($_result) ? array_slice($_result,' . $offset . ', $row, true) : $_result->slice(' . $offset . ', $row, true); ';
            } else {
                $parseStr .= ' if(intval($row) > 0) :';
                $parseStr .= ' $__LIST__ = is_array($_result) ? array_slice($_result,' . $offset . ', $row, true) : $_result->slice(' . $offset . ', $row, true); ';
                $parseStr .= ' else:';
                $parseStr .= ' $__LIST__ = $_result;';
                $parseStr .= ' endif;';
            }
        }

        $parseStr .= 'if( count($__LIST__)==0 ) : echo htmlspecialchars_decode("' . $empty . '");';
        $parseStr .= 'else: ';
        $parseStr .= 'foreach($__LIST__ as $key=>$' . $id . '): ';
        $parseStr .= '$' . $id . '["typename"] = text_msubstr($' . $id . '["typename"], 0, '.$titlelen.', false);';

        // $parseStr .= ' $' . $id . '["typeurl"] = model("Arctype")->getTypeUrl($' . $id . ');';
        
        // $parseStr .= ' if (strval($'.$id.'["id"]) == strval($typeid) || strval($'.$id.'["id"]) == '.$topTypeId.') :';
        // $parseStr .= ' $'.$id.'["currentclass"] = $'.$id.'["currentstyle"] = "'.$currentclass.'";';
        // $parseStr .= ' else: ';
        // $parseStr .= ' $'.$id.'["currentclass"] = $'.$id.'["currentstyle"] = "";';
        // $parseStr .= ' endif;';

        $parseStr .= ' $__LIST__[$key] = $_result[$key] = $' . $id . ';';
        $parseStr .= '$' . $key . '= intval($key) + 1;?>';
        $parseStr .= '<?php $mod = ($' . $key . ' % ' . $mod . ' ); ?>';
        $parseStr .= $content;
        $parseStr .= '<?php ++$e; ?>';
        $parseStr .= '<?php endforeach; endif; else: echo htmlspecialchars_decode("' . $empty . '");endif; ?>';
        $parseStr .= '<?php $'.$id.' = []; ?>'; // 清除变量值，只限于在标签内部使用

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * channelartlist 标签解析 用于获取栏目列表
     * 格式：type:son表示下级栏目,self表示同级栏目,top顶级栏目
     * {eyou:channelartlist typeid='1' type='son' loop='10'}
     *  <li><a href='{$field:typelink}'>{$field:typename}</a> </li> 
     * {/eyou:channelartlist}
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string|void
     */
    public function tagChannelartlist($tag, $content)
    {
        $modelid   = isset($tag['modelid']) ? $tag['modelid'] : (isset($tag['channelid']) ? $tag['channelid'] : '');
        $modelid  = $this->varOrvalue($modelid);

        $typeid  = !empty($tag['typeid']) ? $tag['typeid'] : '';
        $typeid  = $this->varOrvalue($typeid);

        $type   = !empty($tag['type']) ? $tag['type'] : 'self';
        $id     = 'channelartlist';
        $key    = !empty($tag['key']) ? $tag['key'] : 'i';
        $empty  = isset($tag['empty']) ? $tag['empty'] : '';
        $empty  = htmlspecialchars($empty);
        if (isset($tag['loop'])) $tag['row'] = $tag['loop'];
        $row = !empty($tag['row']) && is_numeric($tag['row']) ? intval($tag['row']) : 10;
        $mod    = !empty($tag['mod']) && is_numeric($tag['mod']) ? $tag['mod'] : '2';
        $titlelen = !empty($tag['titlelen']) && is_numeric($tag['titlelen']) ? intval($tag['titlelen']) : 100;
        $currentclass = isset($tag['currentclass']) ? $tag['currentclass'] : '';
        if (!isset($tag['currentclass'])) { // 加强兼容性
            $currentclass   = !empty($tag['currentstyle']) ? $tag['currentstyle'] : '';
        }
        $parseStr = '<?php ';
        // 声明变量
        $parseStr .= ' if(isset($ui_typeid) && !empty($ui_typeid)) : $typeid = $ui_typeid; else: $typeid = '.$typeid.'; endif;';
        $parseStr .= ' if(isset($ui_modelid) && !empty($ui_modelid)) : $modelid = $ui_modelid; else: $modelid = '.$modelid.'; endif;';
        $parseStr .= ' if(isset($ui_row) && !empty($ui_row)) : $row = $ui_row; else: $row = '.$row.'; endif;';

        // 查询数据库获取的数据集
        $parseStr .= ' $tagChannelartlist = new \think\template\taglib\eyou\TagChannelartlist;';
        $parseStr .= ' $_result = $tagChannelartlist->getChannelartlist($typeid, "'.$type.'","'.$currentclass.'", $modelid);';
        $parseStr .= ' if(is_array($_result) || $_result instanceof \think\Collection || $_result instanceof \think\Paginator): $' . $key . ' = 0; $e = 1;';
        // 设置了输出数组长度
        if ('null' != $row) {
            $parseStr .= '$__LIST__ = is_array($_result) ? array_slice($_result,0, $row, true) : $_result->slice(0, $row, true); ';
        } else {
            $parseStr .= ' if(intval($row) > 0) :';
            $parseStr .= ' $__LIST__ = is_array($_result) ? array_slice($_result,0, $row, true) : $_result->slice(0, $row, true); ';
            $parseStr .= ' else:';
            $parseStr .= ' $__LIST__ = $_result;';
            $parseStr .= ' endif;';
        }

        $parseStr .= 'if( count($__LIST__)==0 ) : echo htmlspecialchars_decode("' . $empty . '");';
        $parseStr .= 'else: ';
        $parseStr .= 'foreach($__LIST__ as $key=>$' . $id . '): ';
        $parseStr .= '$' . $id . '["typename"] = text_msubstr($' . $id . '["typename"], 0, '.$titlelen.', false);';

        $parseStr .= ' $__LIST__[$key] = $_result[$key] = $' . $id . ';';
        $parseStr .= '$' . $key . '= intval($key) + 1;?>';
        $parseStr .= '<?php $mod = ($' . $key . ' % ' . $mod . ' ); ?>';
        $parseStr .= $content;
        $parseStr .= '<?php ++$e; ?>';
        $parseStr .= '<?php endforeach; endif; else: echo htmlspecialchars_decode("' . $empty . '");endif; ?>';
        $parseStr .= ' <?php $typeid = $row = ""; unset($'.$id.'); ?>';

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * arclist标签解析 获取指定文档列表（兼容tp的volist标签语法）
     * 格式：
     * {eyou:arclist modelid='1' typeid='1' loop='10' offset='0' titlelen='30' orderby ='aid desc' flag='' bodylen='160' empty='' id='field' mod='' name=''}
     *  {$field.title}
     *  {$field.typeid}
     * {/eyou:arclist}
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string|void
     */
    public function tagArclist($tag, $content)
    {
        $typeid     = !empty($tag['typeid']) ? $tag['typeid'] : '';
        $typeid  = $this->varOrvalue($typeid);

        $notypeid     = !empty($tag['notypeid']) ? $tag['notypeid'] : '';
        $notypeid  = $this->varOrvalue($notypeid);

        $modelid   = isset($tag['modelid']) ? $tag['modelid'] : (isset($tag['channelid']) ? $tag['channelid'] : '');
        $modelid  = $this->varOrvalue($modelid);

        $addfields     = isset($tag['addfields']) ? $tag['addfields'] : '';
        $addfields  = $this->varOrvalue($addfields);

        $joinaid   = isset($tag['joinaid']) ? $tag['joinaid'] : '';
        $joinaid  = $this->varOrvalue($joinaid);

        $keyword   = isset($tag['keyword']) ? $tag['keyword'] : '';
        $keyword  = $this->varOrvalue($keyword);

        $release   = isset($tag['release']) ? $tag['release'] : 'off';
        $release  = $this->varOrvalue($release);

        $idlist   = isset($tag['idlist']) ? $tag['idlist'] : '';
        $idlist  = $this->varOrvalue($idlist);

        $idrange   = isset($tag['idrange']) ? $tag['idrange'] : '';
        $idrange  = $this->varOrvalue($idrange);
        
        $aid   = isset($tag['aid']) ? $tag['aid'] : '';
        $aid  = $this->varOrvalue($aid);

        $name   = !empty($tag['name']) ? $tag['name'] : '';
        $id     = isset($tag['id']) ? $tag['id'] : 'field';
        $key    = !empty($tag['key']) ? $tag['key'] : 'i';
        $empty  = isset($tag['empty']) ? $tag['empty'] : '';
        $empty  = htmlspecialchars($empty);
        $mod    = !empty($tag['mod']) && is_numeric($tag['mod']) ? $tag['mod'] : '2';
        $orderby    = isset($tag['orderby']) ? $tag['orderby'] : '';
        $ordermode = 'desc';
        if (!empty($tag['ordermode'])) {
            $ordermode = $tag['ordermode'];
        } else {
            if (!empty($tag['orderWay'])) {
                $ordermode = $tag['orderWay'];
            } else {
                $ordermode = !empty($tag['orderway']) ? $tag['orderway'] : $ordermode;
            }
        }
        $flag    = isset($tag['flag']) ? $tag['flag'] : '';
        $noflag    = isset($tag['noflag']) ? $tag['noflag'] : '';
        $tagid    = isset($tag['tagid']) ? $tag['tagid'] : ''; // 标签ID
        $pagesize = !empty($tag['pagesize']) && is_numeric($tag['pagesize']) ? intval($tag['pagesize']) : 0;
        $thumb   = !empty($tag['thumb']) ? $tag['thumb'] : 'on';
        $titlelen = !empty($tag['titlelen']) && is_numeric($tag['titlelen']) ? intval($tag['titlelen']) : 100;
        $bodylen = !empty($tag['bodylen']) && is_numeric($tag['bodylen']) ? intval($tag['bodylen']) : 160;
        if (isset($tag['infolen'])) {
            $bodylen = !empty($tag['infolen']) && is_numeric($tag['infolen']) ? intval($tag['infolen']) : 160;
        }
        $offset = !empty($tag['offset']) && is_numeric($tag['offset']) ? intval($tag['offset']) : 0;
        if (isset($tag['loop'])) $tag['row'] = $tag['loop'];
        $row = !empty($tag['row']) && is_numeric($tag['row']) ? intval($tag['row']) : 10;
        if (!empty($tag['limit'])) {
            $limitArr = explode(',', $tag['limit']);
            $offset = !empty($limitArr[0]) ? intval($limitArr[0]) : 0;
            $row = !empty($limitArr[1]) ? intval($limitArr[1]) : 0;
        }
        $arcrank    = !empty($tag['arcrank']) ? $tag['arcrank'] : 'off';
        $type   = !empty($tag['type']) ? $tag['type'] : '';
        $siteall  = isset($tag['siteall']) ? $tag['siteall'] : null;
        $parseStr = '<?php ';
        // 声明变量
        /*typeid的优先级别从高到低：装修数据 -> 标签属性值 -> 外层标签channelartlist属性值*/
        $parseStr .= ' if(isset($ui_typeid) && !empty($ui_typeid)) : $typeid = $ui_typeid; else: $typeid = '.$typeid.'; endif;';
        $parseStr .= ' if(isset($ui_modelid) && !empty($ui_modelid)) : $modelid = $ui_modelid; else: $modelid = '.$modelid.'; endif;';
        $parseStr .= ' if(empty($typeid) && isset($channelartlist["id"]) && !empty($channelartlist["id"])) : $typeid = intval($channelartlist["id"]); endif; ';
        /*--end*/
        $parseStr .= ' if(isset($ui_row) && !empty($ui_row)) : $row = $ui_row; else: $row = '.$row.'; endif;';

        if ($name) { // 从模板中传入数据集
            $symbol     = substr($name, 0, 1);
            if (':' == $symbol) {
                $name = $this->autoBuildVar($name);
                $parseStr .= '$_result=' . $name . ';';
                $name = '$_result';
            } else {
                $name = $this->autoBuildVar($name);
            }

            $parseStr .= 'if(is_array(' . $name . ') || ' . $name . ' instanceof \think\Collection || ' . $name . ' instanceof \think\Paginator): $' . $key . ' = 0; $e = 1;';
            // 设置了输出数组长度
            if (0 != $offset || 'null' != $row) {
                $parseStr .= '$__LIST__ = is_array(' . $name . ') ? array_slice(' . $name . ',' . $offset . ',' . $row . ', true) : ' . $name . '->slice(' . $offset . ',' . $row . ', true); ';
            } else {
                $parseStr .= ' $__LIST__ = ' . $name . ';';
            }

        } else { // 查询数据库获取的数据集
            $parseStr .= ' $param = array(';
            $parseStr .= '      "typeid"=> $typeid,';
            $parseStr .= '      "notypeid"=> '.$notypeid.',';
            $parseStr .= '      "flag"=> "'.$flag.'",';
            $parseStr .= '      "noflag"=> "'.$noflag.'",';
            $parseStr .= '      "channel"=> $modelid,';
            $parseStr .= '      "joinaid"=> '.$joinaid.',';
            $parseStr .= '      "keyword"=> '.$keyword.',';
            $parseStr .= '      "release"=> '.$release.',';
            $parseStr .= '      "idlist"=> '.$idlist.',';
            $parseStr .= '      "idrange"=> '.$idrange.',';
            $parseStr .= '      "aid"=> '.$aid.',';
            $parseStr .= ' );';
            $parseStr .= ' $tag = '.var_export($tag,true).';';
            $parseStr .= ' $tagArclist = new \think\template\taglib\eyou\TagArclist;';
            $parseStr .= ' $_result = $tagArclist->getArclist($param, $row, "'.$orderby.'", '.$addfields.',"'.$ordermode.'","'.$tagid.'",$tag,"'.$pagesize.'","'.$thumb.'","'.$arcrank.'","'.$type.'","'.$siteall.'");';

            $parseStr .= 'if(!empty($_result["list"]) && (is_array($_result["list"]) || $_result["list"] instanceof \think\Collection || $_result["list"] instanceof \think\Paginator)): $' . $key . ' = 0; $e = 1;';
            // 设置了输出数组长度
            if (0 != $offset || 'null' != $row) {
                $parseStr .= ' $__LIST__ = is_array($_result["list"]) ? array_slice($_result["list"],' . $offset . ', $row, true) : $_result["list"]->slice(' . $offset . ', $row, true); ';
            } else {
                $parseStr .= ' $__LIST__ = $_result["list"];';
            }
            $parseStr .= ' $__TAG__ = $_result["tag"];';
        }
        $parseStr .= 'if( count($__LIST__)==0 ) : echo htmlspecialchars_decode("' . $empty . '");';
        $parseStr .= 'else: ';
        $parseStr .= 'foreach($__LIST__ as $key=>$' . $id . '): ';
        $parseStr .= '$aid = $'.$id.'["aid"];';
        $parseStr .= '$users_id = $'.$id.'["users_id"];';
        $parseStr .= '$' . $id . '["title"] = text_msubstr($' . $id . '["title"], 0, '.$titlelen.', false);';
        $parseStr .= 'if($' . $id . '["is_b"] == 1) : $' . $id . '["title"] = "<strong>".$' . $id . '["title"]."</strong>";endif;';
        $parseStr .= '$' . $id . '["seo_description"] = text_msubstr($' . $id . '["seo_description"], 0, '.$bodylen.', true);';

        $parseStr .= '$' . $key . '= intval($key) + 1;?>';
        $parseStr .= '<?php $mod = ($' . $key . ' % ' . $mod . ' ); ?>';
        $parseStr .= $content;
        $parseStr .= '<?php ++$e; ?>';
        $parseStr .= '<?php $aid = 0; ?>';
        $parseStr .= '<?php $users_id = 0; ?>';
        $parseStr .= '<?php endforeach; endif; else: echo htmlspecialchars_decode("' . $empty . '");endif; ?>';
        $parseStr .= '<?php $'.$id.' = []; ?>'; // 清除变量值，只限于在标签内部使用

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * list 标签解析 获取指定文档分页列表（兼容tp的volist标签语法）
     * 格式：
     * {eyou:list modelid='1' typeid='1' titlelen='30' orderby ='aid desc' flag='' bodylen='160' empty='' id='field' mod='' name=''}
     *  {$field.title}
     *  {$field.typeid}
     * {/eyou:list}
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string|void
     */
    public function tagList($tag, $content)
    {
        $typeid     = !empty($tag['typeid']) ? $tag['typeid'] : '';
        $typeid  = $this->varOrvalue($typeid);

        $notypeid     = !empty($tag['notypeid']) ? $tag['notypeid'] : '';
        $notypeid  = $this->varOrvalue($notypeid);

        $modelid   = isset($tag['modelid']) ? $tag['modelid'] : (isset($tag['channelid']) ? $tag['channelid'] : '');
        $modelid  = $this->varOrvalue($modelid);
        
        $addfields     = isset($tag['addfields']) ? $tag['addfields'] : '';
        $addfields  = $this->varOrvalue($addfields);
        
        $keyword     = isset($tag['keyword']) ? $tag['keyword'] : '';
        $keyword  = $this->varOrvalue($keyword);

        $idlist   = isset($tag['idlist']) ? $tag['idlist'] : '';
        $idlist  = $this->varOrvalue($idlist);

        $idrange   = isset($tag['idrange']) ? $tag['idrange'] : '';
        $idrange  = $this->varOrvalue($idrange);

        $id     = isset($tag['id']) ? $tag['id'] : 'field';
        $key    = !empty($tag['key']) ? $tag['key'] : 'i';
        $empty  = isset($tag['empty']) ? $tag['empty'] : '';
        $empty  = htmlspecialchars($empty);
        $mod    = !empty($tag['mod']) && is_numeric($tag['mod']) ? $tag['mod'] : '2';
        if (isset($tag['loop'])) $tag['pagesize'] = $tag['loop'];
        $pagesize = !empty($tag['pagesize']) && is_numeric($tag['pagesize']) ? intval($tag['pagesize']) : 10;
        $thumb   = !empty($tag['thumb']) ? $tag['thumb'] : 'on';
        $orderby    = isset($tag['orderby']) ? $tag['orderby'] : '';
        $ordermode = 'desc';
        if (!empty($tag['ordermode'])) {
            $ordermode = $tag['ordermode'];
        } else {
            if (!empty($tag['orderWay'])) {
                $ordermode = $tag['orderWay'];
            } else {
                $ordermode = !empty($tag['orderway']) ? $tag['orderway'] : $ordermode;
            }
        }
        $flag    = isset($tag['flag']) ? $tag['flag'] : '';
        $noflag    = isset($tag['noflag']) ? $tag['noflag'] : '';
        $titlelen = !empty($tag['titlelen']) && is_numeric($tag['titlelen']) ? intval($tag['titlelen']) : 100;
        $bodylen = !empty($tag['bodylen']) && is_numeric($tag['bodylen']) ? intval($tag['bodylen']) : 160;
        if (isset($tag['infolen'])) {
            $bodylen = !empty($tag['infolen']) && is_numeric($tag['infolen']) ? intval($tag['infolen']) : 160;
        }
        $arcrank    = !empty($tag['arcrank']) ? $tag['arcrank'] : 'off';
        $siteall  = isset($tag['siteall']) ? $tag['siteall'] : null;
        $parseStr = '<?php ';
        // 声明变量
        /*typeid的优先级别从高到低：装修数据 -> 标签属性值 -> 外层标签channelartlist属性值*/
        $parseStr .= ' $typeid = '.$typeid.'; ';
        $parseStr .= ' if(empty($typeid) && isset($channelartlist["id"]) && !empty($channelartlist["id"])) : $typeid = intval($channelartlist["id"]); endif; ';
        $parseStr .= ' $modelid = '.$modelid.'; ';
        $parseStr .= ' if(empty($modelid) && isset($channelartlist["current_channel"]) && !empty($channelartlist["current_channel"])) : $modelid = intval($channelartlist["current_channel"]); endif; ';
        /*--end*/

        // 查询数据库获取的数据集
        $parseStr .= ' $param = array(';
        $parseStr .= '      "typeid"=> $typeid,';
        $parseStr .= '      "notypeid"=> '.$notypeid.',';
        $parseStr .= '      "flag"=> "'.$flag.'",';
        $parseStr .= '      "noflag"=> "'.$noflag.'",';
        $parseStr .= '      "channel"=> $modelid,';
        $parseStr .= '      "keyword"=> '.$keyword.',';
        $parseStr .= '      "idlist"=> '.$idlist.',';
        $parseStr .= '      "idrange"=> '.$idrange.',';
        $parseStr .= ' );';
        // $parseStr .= ' $orderby = "'.$orderby.'";';
        $parseStr .= ' $tagList = new \think\template\taglib\eyou\TagList;';
        $parseStr .= ' $_result_tmp = $tagList->getList($param, '.$pagesize.', "'.$orderby.'", '.$addfields.', "'.$ordermode.'", "'.$thumb.'","'.$arcrank.'","'.$siteall.'");';

        $parseStr .= 'if(!empty($_result_tmp) && (is_array($_result_tmp) || $_result_tmp instanceof \think\Collection || $_result_tmp instanceof \think\Paginator)): $' . $key . ' = 0; $e = 1;';
        $parseStr .= ' $__LIST__ = $_result = $_result_tmp["list"];';
        $parseStr .= ' $__PAGES__ = $_result_tmp["pages"];';

        $parseStr .= 'if( count($__LIST__)==0 ) : echo htmlspecialchars_decode("' . $empty . '");';
        $parseStr .= 'else: ';
        $parseStr .= 'foreach($__LIST__ as $key=>$' . $id . '): ';
        $parseStr .= '$aid = $'.$id.'["aid"];';
        $parseStr .= '$users_id = $'.$id.'["users_id"];';
        $parseStr .= '$' . $id . '["title"] = text_msubstr($' . $id . '["title"], 0, '.$titlelen.', false);';
        $parseStr .= '$' . $id . '["seo_description"] = text_msubstr($' . $id . '["seo_description"], 0, '.$bodylen.', true);';

        $parseStr .= '$' . $key . '= intval($key) + 1;?>';
        $parseStr .= '<?php $mod = ($' . $key . ' % ' . $mod . ' ); ?>';
        $parseStr .= $content;
        $parseStr .= '<?php ++$e; ?>';
        $parseStr .= '<?php $aid = 0; ?>';
        $parseStr .= '<?php $users_id = 0; ?>';
        $parseStr .= '<?php endforeach; endif; else: echo htmlspecialchars_decode("' . $empty . '");endif; ?>';
        $parseStr .= '<?php $'.$id.' = []; ?>'; // 清除变量值，只限于在标签内部使用

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * pagelist 标签解析
     * 在模板中获取列表的分页
     * 格式： {eyou:pagelist listitem='info,index,end,pre,next,pageno' listsize='2'/}
     * @access public
     * @param array $tag 标签属性
     * @return string
     */
    public function tagPagelist($tag)
    {
        $listitem = !empty($tag['listitem']) ? $tag['listitem'] : '';
        $listsize   = !empty($tag['listsize']) ? intval($tag['listsize']) : '';

        $parseStr = ' <?php ';
        $parseStr .= ' $__PAGES__ = isset($__PAGES__) ? $__PAGES__ : "";';
        $parseStr .= ' $tagPagelist = new \think\template\taglib\eyou\TagPagelist;';
        $parseStr .= ' $__VALUE__ = $tagPagelist->getPagelist($__PAGES__, "'.$listitem.'", "'.$listsize.'");';
        $parseStr .= ' echo $__VALUE__;';
        $parseStr .= ' ?>';

        return $parseStr;
    }

    /**
     * arcpagelist 标签解析
     * 在模板中获取arclist标签列表的ajax分页
     * 格式： {eyou:arcpagelist tagid='' pagesize='2'} {/eyou:arcpagelist}
     * @access public
     * @param array $tag 标签属性
     * @return string
     */
    public function tagArcpagelist($tag, $content)
    {
        $tagid = !empty($tag['tagid']) ? $tag['tagid'] : '';
        $pagesize = !empty($tag['pagesize']) && is_numeric($tag['pagesize']) ? intval($tag['pagesize']) : 0;
        $id     = isset($tag['id']) ? $tag['id'] : 'field';
        $tips     = isset($tag['tips']) ? $tag['tips'] : '';
        $callback     = isset($tag['callback']) ? $tag['callback'] : '';
        $loading     = isset($tag['loading']) ? $tag['loading'] : '';
        $loading  = $this->varOrvalue($loading);

        $parseStr = ' <?php ';

        // 查询数据库获取的数据集
        $parseStr .= ' empty($__TAG__) && $__TAG__ = [];';
        $parseStr .= ' $tagArcpagelist = new \think\template\taglib\eyou\TagArcpagelist;';
        $parseStr .= ' $_result = $tagArcpagelist->getArcpagelist("'.$tagid.'","'.$pagesize.'","'.$tips.'",'.$loading.',"'.$callback.'", $__TAG__);';

        $parseStr .= ' if(!empty($_result) || (($_result instanceof \think\Collection || $_result instanceof \think\Paginator ) && $_result->isEmpty())): ?>';
        $parseStr .= '<?php $'.$id.' = $_result; ?>';
        $parseStr .= $content;
        $parseStr .= '<?php endif; ?>';
        $parseStr .= '<?php if(!empty($_result["js"])):';
        $parseStr .= ' echo $_result["js"]; ';
        $parseStr .= ' endif; ?>';
        $parseStr .= '<?php $'.$id.' = []; ?>'; // 清除变量值，只限于在标签内部使用

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * position 标签解析
     * 在模板中获取列表的分页
     * 格式： {eyou:position typeid="" symbol=" > "/}
     * @access public
     * @param array $tag 标签属性
     * @return string
     */
    public function tagPosition($tag)
    {
        $typeid = !empty($tag['typeid']) ? $tag['typeid'] : '';
        $typeid = $this->varOrvalue($typeid);

        $symbol     = isset($tag['symbol']) ? $tag['symbol'] : '';
        $style   = !empty($tag['style']) ? $tag['style'] : '';

        $parseStr = ' <?php ';

        /*typeid的优先级别从高到低：装修数据 -> 标签属性值 -> 外层标签channelartlist属性值*/
        $parseStr .= ' $typeid = '.$typeid.';';
        $parseStr .= ' if(empty($typeid) && isset($channelartlist["id"]) && !empty($channelartlist["id"])) : $typeid = intval($channelartlist["id"]); endif; ';
        /*--end*/
        
        $parseStr .= ' $tagPosition = new \think\template\taglib\eyou\TagPosition;';
        $parseStr .= ' $__VALUE__ = $tagPosition->getPosition($typeid, "'.$symbol.'", "'.$style.'");';
        $parseStr .= ' echo $__VALUE__;';
        $parseStr .= ' ?>';

        return $parseStr;
    }

    /**
     * searchurl 标签解析
     * 在模板中获取搜索的URL
     * 格式： {eyou:searchurl /}
     * @access public
     * @param array $tag 标签属性
     * @return string
     */
    public function tagSearchurl($tag)
    {
        $parseStr = '<?php ';

        // 查询数据库获取的数据集
        $parseStr .= ' $tagSearchurl = new \think\template\taglib\eyou\TagSearchurl;';
        $parseStr .= ' $__VALUE__ = $tagSearchurl->getSearchurl();';
        $parseStr .= ' echo $__VALUE__';
        $parseStr .= '?>';
        
        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * searchform 搜索表单标签解析 TAG调用
     * {eyou:searchform type='default'}
     * {$field.searchurl}
     * {/eyou:searchform}
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string|void
     */
    public function tagSearchform($tag, $content)
    {
        $modelid   = isset($tag['modelid']) ? $tag['modelid'] : (isset($tag['channelid']) ? $tag['channelid'] : '');
        if (empty($modelid)) {
            $modelid   = !empty($tag['channel']) ? $tag['channel'] : '';
        }
        $modelid  = $this->varOrvalue($modelid);
        $typeid   = !empty($tag['typeid']) ? $tag['typeid'] : '';
        $typeid  = $this->varOrvalue($typeid);
        $notypeid   = !empty($tag['notypeid']) ? $tag['notypeid'] : '';
        $notypeid  = $this->varOrvalue($notypeid);
        $flag   = !empty($tag['flag']) ? $tag['flag'] : '';
        $flag  = $this->varOrvalue($flag);
        $noflag   = !empty($tag['noflag']) ? $tag['noflag'] : '';
        $noflag  = $this->varOrvalue($noflag);
        $type   = !empty($tag['type']) ? $tag['type'] : 'default';
        $id     = isset($tag['id']) ? $tag['id'] : 'field';
        $key    = !empty($tag['key']) ? $tag['key'] : 'i';
        $mod    = !empty($tag['mod']) && is_numeric($tag['mod']) ? $tag['mod'] : '2';
        $empty  = isset($tag['empty']) ? $tag['empty'] : '';
        $empty  = htmlspecialchars($empty);

        $parseStr = '<?php ';

        // 查询数据库获取的数据集
        $parseStr .= ' $tagSearchform = new \think\template\taglib\eyou\TagSearchform;';
        $parseStr .= ' $_result = $tagSearchform->getSearchform('.$typeid.','.$modelid.','.$notypeid.','.$flag.','.$noflag.',"'.$type.'");';
        $parseStr .= ' if(is_array($_result) || $_result instanceof \think\Collection || $_result instanceof \think\Paginator): $' . $key . ' = 0; $e = 1;';
        $parseStr .= ' $__LIST__ = $_result;';

        $parseStr .= 'if( count($__LIST__)==0 ) : echo htmlspecialchars_decode("' . $empty . '");';
        $parseStr .= 'else: ';
        $parseStr .= 'foreach($__LIST__ as $key=>$' . $id . '): ';
        $parseStr .= '$' . $key . '= intval($key) + 1;?>';
        $parseStr .= '<?php $mod = ($' . $key . ' % ' . $mod . ' ); ?>';
        $parseStr .= $content;
        $parseStr .= '<?php ++$e; ?>';
        $parseStr .= '<?php endforeach;';
        $parseStr .= 'endif; else: echo htmlspecialchars_decode("' . $empty . '");endif; ?>';
        $parseStr .= '<?php $'.$id.' = []; ?>'; // 清除变量值，只限于在标签内部使用

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * type标签解析 指定的单个栏目的链接
     * 格式：
     * {eyou:type typeid='' empty=''}
     *  <a href="{$field:typelink}">{$field:typename}</a>
     * {/eyou:type}
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string|void
     */
    public function tagType($tag, $content)
    {
        $typeid  = isset($tag['typeid']) ? $tag['typeid'] : '';
        $typeid  = $this->varOrvalue($typeid);

        $type  = !empty($tag['type']) ? $tag['type'] : 'self';
        $empty  = !empty($tag['empty']) ? $tag['empty'] : '';
        $empty  = htmlspecialchars($empty);
        $id     = isset($tag['id']) ? $tag['id'] : 'field';

        $addfields     = isset($tag['addfields']) ? $tag['addfields'] : '';
        if (!empty($tag['addtable'])) {
            $addfields = $tag['addtable'];
        }
        $addfields  = $this->varOrvalue($addfields);

        $parseStr = '<?php ';
        // 声明变量
        /*typeid的优先级别从高到低：装修数据 -> 标签属性值 -> 外层标签channelartlist属性值*/
        $parseStr .= ' if(isset($ui_typeid) && !empty($ui_typeid)) : $typeid = $ui_typeid; else: $typeid = '.$typeid.'; endif;';
        $parseStr .= ' if(empty($typeid) && isset($channelartlist["id"]) && !empty($channelartlist["id"])) : $typeid = intval($channelartlist["id"]); endif; ';
        /*--end*/
        $parseStr .= ' $tagType = new \think\template\taglib\eyou\TagType;';
        $parseStr .= ' $_result = $tagType->getType($typeid, "'.$type.'", '.$addfields.');';
        $parseStr .= ' ?>';

        /*方式一*/
        /*$parseStr .= '<?php if((!empty($_result) || (($_result instanceof \think\Collection || $_result instanceof \think\Paginator ) && $_result->isEmpty()))): ?>';
        $parseStr .= '<?php $'.$id.' = $_result; ?>';
        $parseStr .= $content;
        $parseStr .= '<?php endif; ?>';*/
        /*--end*/

        /*方式二*/
        $parseStr .= '<?php if(is_array($_result) || $_result instanceof \think\Collection || $_result instanceof \think\Paginator): ';
        $parseStr .= ' $__LIST__ = $_result;';
        $parseStr .= 'if( count($__LIST__)==0 ) : echo htmlspecialchars_decode("' . $empty . '");';
        $parseStr .= 'else: ';
        $parseStr .= '$'.$id.' = $__LIST__;';
        $parseStr .= '?>';
        $parseStr .= $content;
        $parseStr .= '<?php endif; else: echo htmlspecialchars_decode("' . $empty . '");endif; ?>';
        $parseStr .= '<?php $'.$id.' = []; ?>'; // 清除变量值，只限于在标签内部使用
        /*--end*/

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * arcview标签解析 指定的单个栏目的链接
     * 格式：
     * {eyou:arcview aid='' empty=''}
     *  <a href="{$field:arcurl}">{$field:title}</a>
     * {/eyou:arcview}
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string|void
     */
    public function tagArcview($tag, $content)
    {
        $aid_tmp  = isset($tag['aid']) ? $tag['aid'] : '0';
        $aid  = $this->varOrvalue($aid_tmp);

        $empty  = isset($tag['empty']) ? $tag['empty'] : '';
        $empty  = htmlspecialchars($empty);
        $id     = isset($tag['id']) ? $tag['id'] : 'field';
        $addfields     = isset($tag['addfields']) ? $tag['addfields'] : '';
        $addfields  = $this->varOrvalue($addfields);

        $joinaid   = isset($tag['joinaid']) ? $tag['joinaid'] : '';
        $joinaid  = $this->varOrvalue($joinaid);

        $parseStr = '<?php ';
        // 声明变量
        if (!empty($aid_tmp)) {
            $parseStr .= ' $aid = '.$aid.';';
        } else {
            $parseStr .= ' if(!isset($aid) || empty($aid)) : $aid = '.$aid.'; endif;';
        }

        $parseStr .= ' $tagArcview = new \think\template\taglib\eyou\TagArcview;';
        $parseStr .= ' $_result = $tagArcview->getArcview($aid, '.$addfields.','.$joinaid.');';
        $parseStr .= ' ?>';

        /*方式一*/
        /*$parseStr .= '<?php if((!empty($_result) || (($_result instanceof \think\Collection || $_result instanceof \think\Paginator ) && $_result->isEmpty()))): ?>';
        $parseStr .= '<?php $'.$id.' = $_result; ?>';
        $parseStr .= $content;
        $parseStr .= '<?php endif; ?>';*/
        /*--end*/

        /*方式一*/
        $parseStr .= '<?php if(is_array($_result) || $_result instanceof \think\Collection || $_result instanceof \think\Paginator): ';
        $parseStr .= ' $__LIST__ = $_result;';
        $parseStr .= 'if( count($__LIST__)==0 ) : echo htmlspecialchars_decode("' . $empty . '");';
        $parseStr .= 'else: ';
        $parseStr .= '$'.$id.' = $__LIST__;';
        $parseStr .= '$users_id = $'.$id.'["users_id"];';
        $parseStr .= '?>';
        $parseStr .= $content;
        $parseStr .= '<?php endif; else: echo htmlspecialchars_decode("' . $empty . '");endif; ?>';
        $parseStr .= '<?php unset($aid); ?>';
        $parseStr .= '<?php unset($users_id); ?>';
        $parseStr .= '<?php $'.$id.' = []; ?>'; // 清除变量值，只限于在标签内部使用
        /*--end*/

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * tag 标签解析 TAG调用
     * 格式：sort:排序方式 month，rand，week
     *       getall:获取类型 0 为当前内容页TAG标记，1为获取全部TAG标记
     * {eyou:tag loop='1' getall='0' sort=''}
     *  <li><a href='{$field.link}'>{$field.tag}</a> </li> 
     * {/eyou:tag}
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string|void
     */
    public function tagTag($tag, $content)
    {
        $aid   = !empty($tag['aid']) ? $tag['aid'] : '0';
        $aid  = $this->varOrvalue($aid);
        $typeid   = !empty($tag['typeid']) ? $tag['typeid'] : '';
        $typeid  = $this->varOrvalue($typeid);
        $getall   = !empty($tag['getall']) ? $tag['getall'] : '0';
        $style   = !empty($tag['style']) ? $tag['style'] : '';
        $id     = isset($tag['id']) ? $tag['id'] : 'field';
        $key    = !empty($tag['key']) ? $tag['key'] : 'i';
        $empty  = isset($tag['empty']) ? $tag['empty'] : '';
        $empty  = htmlspecialchars($empty);
        $mod    = !empty($tag['mod']) && is_numeric($tag['mod']) ? $tag['mod'] : '2';
        if (isset($tag['loop'])) $tag['row'] = $tag['loop'];
        $row = !empty($tag['row']) && is_numeric($tag['row']) ? intval($tag['row']) : 100;
        $sort   = !empty($tag['sort']) ? $tag['sort'] : 'new';
        $type  = !empty($tag['type']) ? $tag['type'] : '';

        $parseStr = '<?php ';

        /*typeid的优先级别从高到低：装修数据 -> 标签属性值 -> 外层标签channelartlist属性值*/
        $parseStr .= ' $typeid = '.$typeid.';';
        $parseStr .= ' if(empty($typeid) && isset($channelartlist["id"]) && !empty($channelartlist["id"])) : $typeid = intval($channelartlist["id"]); endif; ';
        // 声明变量
        $parseStr .= ' if(!isset($aid) || empty($aid)) : $aid = '.$aid.'; endif;';
        /*--end*/

        // 查询数据库获取的数据集
        $parseStr .= ' $tagTag = new \think\template\taglib\eyou\TagTag;';
        $parseStr .= ' $_result = $tagTag->getTag('.$getall.', $typeid, $aid, '.$row.', "'.$sort.'", "'.$type.'");';
        $parseStr .= ' if(is_array($_result) || $_result instanceof \think\Collection || $_result instanceof \think\Paginator): $' . $key . ' = 0; $e = 1;';
        // 设置了输出数组长度
        if ('null' != $row) {
            $parseStr .= '$__LIST__ = is_array($_result) ? array_slice($_result,0, '.$row.', true) : $_result->slice(0, '.$row.', true); ';
        } else {
            $parseStr .= ' $__LIST__ = $_result;';
        }

        $parseStr .= 'if( count($__LIST__)==0 ) : echo htmlspecialchars_decode("' . $empty . '");';
        $parseStr .= 'else: ';
        $parseStr .= 'foreach($__LIST__ as $key=>$' . $id . '): ';
        $parseStr .= '$' . $key . '= intval($key) + 1;?>';
        $parseStr .= '<?php $mod = ($' . $key . ' % ' . $mod . ' ); ?>';
        $parseStr .= $content;
        $parseStr .= '<?php ++$e; ?>';
        $parseStr .= '<?php endforeach; endif; else: echo htmlspecialchars_decode("' . $empty . '");endif; ?>';
        $parseStr .= '<?php unset($aid); ?>';
        $parseStr .= '<?php $'.$id.' = []; ?>'; // 清除变量值，只限于在标签内部使用

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * tagarclist标签解析 获取指定tag标签的文档列表（兼容tp的volist标签语法）
     * 格式：
     * {eyou:tagarclist typeid='1' loop='10' offset='0' titlelen='30' orderby ='aid desc' flag='' bodylen='160' empty='' id='field' mod='' name=''}
     *  {$field.title}
     *  {$field.typeid}
     * {/eyou:tagarclist}
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string|void
     */
    public function tagTagarclist($tag, $content)
    {
        $addfields     = isset($tag['addfields']) ? $tag['addfields'] : '';
        $addfields  = $this->varOrvalue($addfields);

        $keyword   = isset($tag['keyword']) ? $tag['keyword'] : '';
        $keyword  = $this->varOrvalue($keyword);

        $name   = !empty($tag['name']) ? $tag['name'] : '';
        $id     = isset($tag['id']) ? $tag['id'] : 'field';
        $key    = !empty($tag['key']) ? $tag['key'] : 'i';
        $empty  = isset($tag['empty']) ? $tag['empty'] : '';
        $empty  = htmlspecialchars($empty);
        $mod    = !empty($tag['mod']) && is_numeric($tag['mod']) ? $tag['mod'] : '2';
        $orderby    = isset($tag['orderby']) ? $tag['orderby'] : '';
        $ordermode = 'desc';
        if (!empty($tag['ordermode'])) {
            $ordermode = $tag['ordermode'];
        } else {
            if (!empty($tag['orderWay'])) {
                $ordermode = $tag['orderWay'];
            } else {
                $ordermode = !empty($tag['orderway']) ? $tag['orderway'] : $ordermode;
            }
        }
        $flag    = isset($tag['flag']) ? $tag['flag'] : '';
        $noflag    = isset($tag['noflag']) ? $tag['noflag'] : '';
        $tagid    = isset($tag['tagid']) ? $tag['tagid'] : ''; // 标签ID
        if (isset($tag['loop'])) $tag['pagesize'] = $tag['loop'];
        $pagesize = !empty($tag['pagesize']) && is_numeric($tag['pagesize']) ? intval($tag['pagesize']) : 0;
        $thumb   = !empty($tag['thumb']) ? $tag['thumb'] : 'on';
        $titlelen = !empty($tag['titlelen']) && is_numeric($tag['titlelen']) ? intval($tag['titlelen']) : 100;
        $bodylen = !empty($tag['bodylen']) && is_numeric($tag['bodylen']) ? intval($tag['bodylen']) : 160;
        if (isset($tag['infolen'])) {
            $bodylen = !empty($tag['infolen']) && is_numeric($tag['infolen']) ? intval($tag['infolen']) : 160;
        }
        $arcrank    = !empty($tag['arcrank']) ? $tag['arcrank'] : 'off';
        $limit = !empty($tag['limit']) ? trim($tag['limit']) : 20;
        if (empty($tag['limit'])) {
            $limit = !empty($tag['row']) ? intval($tag['row']) : $limit;
        }

        $parseStr = '<?php ';
        $parseStr .= ' $param = array(';
        $parseStr .= '      "flag"=> "'.$flag.'",';
        $parseStr .= '      "noflag"=> "'.$noflag.'",';
        $parseStr .= '      "keyword"=> '.$keyword.',';
        $parseStr .= ' );';
        $parseStr .= ' $tag = '.var_export($tag,true).';';
        $parseStr .= ' $tagTagarclist = new \think\template\taglib\eyou\TagTagarclist;';
        $parseStr .= ' $_result = $tagTagarclist->getTagarclist($param, "'.$limit.'", "'.$orderby.'", '.$addfields.',"'.$ordermode.'","'.$tagid.'",$tag,"'.$pagesize.'","'.$thumb.'","'.$arcrank.'");';

        $parseStr .= 'if(is_array($_result["list"]) || $_result["list"] instanceof \think\Collection || $_result["list"] instanceof \think\Paginator): $' . $key . ' = 0; $e = 1;';
        $parseStr .= ' $__TAGLIST__ = $_result["list"];';
        $parseStr .= ' $__TAGTAG__ = $_result["tag"];';

        $parseStr .= 'if( count($__TAGLIST__)==0 ) : echo htmlspecialchars_decode("' . $empty . '");';
        $parseStr .= 'else: ';
        $parseStr .= 'foreach($__TAGLIST__ as $key=>$' . $id . '): ';
        $parseStr .= '$aid = $'.$id.'["aid"];';
        $parseStr .= '$users_id = $'.$id.'["users_id"];';
        $parseStr .= '$' . $id . '["title"] = text_msubstr($' . $id . '["title"], 0, '.$titlelen.', false);';
        $parseStr .= '$' . $id . '["seo_description"] = text_msubstr($' . $id . '["seo_description"], 0, '.$bodylen.', true);';

        $parseStr .= '$' . $key . '= intval($key) + 1;?>';
        $parseStr .= '<?php $mod = ($' . $key . ' % ' . $mod . ' ); ?>';
        $parseStr .= $content;
        $parseStr .= '<?php ++$e; ?>';
        $parseStr .= '<?php $aid = 0; ?>';
        $parseStr .= '<?php $users_id = 0; ?>';
        $parseStr .= '<?php endforeach; endif; else: echo htmlspecialchars_decode("' . $empty . '");endif; ?>';
        $parseStr .= '<?php $'.$id.' = []; ?>'; // 清除变量值，只限于在标签内部使用

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * flink 标签解析 TAG调用
     * 格式：sort:排序方式 month，rand，week
     *       getall:获取类型 0 为当前内容页TAG标记，1为获取全部TAG标记
     * {eyou:flink loop='1' titlelen='20'}
     *  <li><a href='{$field:url}'>{$field:title}</a> </li> 
     * {/eyou:flink}
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string|void
     */
    public function tagFlink($tag, $content)
    {
        $type   = !empty($tag['type']) ? $tag['type'] : 'text';
        $groupid   = !empty($tag['groupid']) ? $tag['groupid'] : "";
        $groupid  = $this->varOrvalue($groupid);
        $id     = isset($tag['id']) ? $tag['id'] : 'field';
        $key    = !empty($tag['key']) ? $tag['key'] : 'i';
        $empty  = isset($tag['empty']) ? $tag['empty'] : '';
        $empty  = htmlspecialchars($empty);
        $mod    = !empty($tag['mod']) && is_numeric($tag['mod']) ? $tag['mod'] : '2';
        $titlelen = !empty($tag['titlelen']) && is_numeric($tag['titlelen']) ? intval($tag['titlelen']) : 100;
        if (isset($tag['loop'])) $tag['row'] = $tag['loop'];
        $row = !empty($tag['row']) ? intval($tag['row']) : 0;
        $limit   = !empty($tag['limit']) ? $tag['limit'] : '';
        if (empty($limit) && !empty($row)) {
            $limit = "0,{$row}";
        }

        $parseStr = '<?php ';

        // 查询数据库获取的数据集
        $parseStr .= ' $tagFlink = new \think\template\taglib\eyou\TagFlink;';
        $parseStr .= ' $_result = $tagFlink->getFlink("'.$type.'", "'.$limit.'", '.$groupid.');';
        $parseStr .= ' if(is_array($_result) || $_result instanceof \think\Collection || $_result instanceof \think\Paginator): $' . $key . ' = 0; $e = 1;';
        $parseStr .= ' $__LIST__ = $_result;';

        $parseStr .= 'if( count($__LIST__)==0 ) : echo htmlspecialchars_decode("' . $empty . '");';
        $parseStr .= 'else: ';
        $parseStr .= 'foreach($__LIST__ as $key=>$' . $id . '): ';
        $parseStr .= '$' . $id . '["title"] = text_msubstr($' . $id . '["title"], 0, '.$titlelen.', false);';
        $parseStr .= ' $__LIST__[$key] = $_result[$key] = $' . $id . ';';
        $parseStr .= '$' . $key . '= intval($key) + 1;?>';
        $parseStr .= '<?php $mod = ($' . $key . ' % ' . $mod . ' ); ?>';
        $parseStr .= $content;
        $parseStr .= '<?php ++$e; ?>';
        $parseStr .= '<?php endforeach; endif; else: echo htmlspecialchars_decode("' . $empty . '");endif; ?>';
        $parseStr .= '<?php $'.$id.' = []; ?>'; // 清除变量值，只限于在标签内部使用

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * language 标签解析 TAG调用
     * {eyou:language loop='1' type='default'}
     *  <li><a href='{$field:url}'>{$field:name}</a> </li> 
     * {/eyou:language}
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string|void
     */
    public function tagLanguage($tag, $content)
    {
        $type   = !empty($tag['type']) ? $tag['type'] : '';
        $id     = isset($tag['id']) ? $tag['id'] : 'field';
        $key    = !empty($tag['key']) ? $tag['key'] : 'i';
        $currentclass   = !empty($tag['currentclass']) ? $tag['currentclass'] : '';
        if (!isset($tag['currentclass'])) { // 加强兼容性
            $currentclass   = !empty($tag['currentstyle']) ? $tag['currentstyle'] : '';
        }
        $empty  = isset($tag['empty']) ? $tag['empty'] : '';
        $empty  = htmlspecialchars($empty);
        $mod    = !empty($tag['mod']) && is_numeric($tag['mod']) ? $tag['mod'] : '2';
        $titlelen = !empty($tag['titlelen']) && is_numeric($tag['titlelen']) ? intval($tag['titlelen']) : 100;
        if (isset($tag['loop'])) $tag['row'] = $tag['loop'];
        $row = !empty($tag['row']) ? intval($tag['row']) : 0;
        $limit   = !empty($tag['limit']) ? $tag['limit'] : '';
        if (empty($limit) && !empty($row)) {
            $limit = "0,{$row}";
        }

        $parseStr = '<?php ';

        // 查询数据库获取的数据集
        $parseStr .= ' $tagLanguage = new \think\template\taglib\eyou\TagLanguage;';
        $parseStr .= ' $_result = $tagLanguage->getLanguage("'.$type.'", "'.$limit.'", "'.$currentclass.'");';
        $parseStr .= ' if(is_array($_result) || $_result instanceof \think\Collection || $_result instanceof \think\Paginator): $' . $key . ' = 0; $e = 1;';
        $parseStr .= ' $__LIST__ = $_result;';

        $parseStr .= 'if( count($__LIST__)==0 ) : echo htmlspecialchars_decode("' . $empty . '");';
        $parseStr .= 'else: ';
        $parseStr .= 'foreach($__LIST__ as $key=>$' . $id . '): ';
        $parseStr .= '$' . $id . '["title"] = text_msubstr($' . $id . '["title"], 0, '.$titlelen.', false);';
        $parseStr .= ' $__LIST__[$key] = $_result[$key] = $' . $id . ';';
        $parseStr .= '$' . $key . '= intval($key) + 1;?>';
        $parseStr .= '<?php $mod = ($' . $key . ' % ' . $mod . ' ); ?>';
        $parseStr .= $content;
        $parseStr .= '<?php ++$e; ?>';
        $parseStr .= '<?php endforeach; endif; else: echo htmlspecialchars_decode("' . $empty . '");endif; ?>';
        $parseStr .= '<?php $'.$id.' = []; ?>'; // 清除变量值，只限于在标签内部使用

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * lang 标签解析
     * 在模板中获取多语言模板变量值
     * 格式： {eyou:lang name="" /}
     * @access public
     * @param array $tag 标签属性
     * @return string
     */
    public function tagLang($tag)
    {
        $param = [];
        $name     = isset($tag['name']) ? $tag['name'] : '';
        !empty($name) && $param['name'] = preg_replace('/([^\w\-]+)/i', '', $name);

        $const     = isset($tag['const']) ? $tag['const'] : '';
        !empty($const) && $param['const'] = preg_replace('/([^\w\-]+)/i', '', $const);

        $parseStr = '<?php ';

        // 查询数据库获取的数据集
        $parseStr .= ' $tagLang = new \think\template\taglib\eyou\TagLang;';
        $parseStr .= ' $__VALUE__ = $tagLang->getLang(\''.serialize($param).'\');';
        $parseStr .= ' echo $__VALUE__;';
        $parseStr .= ' ?>';

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * ad标签解析 指定的单个广告的信息
     * 格式：
     * {eyou:ad aid=''}
     *  <a href="{$field:links}">{$field:title}</a>
     * {/eyou:ad}
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string|void
     */
    public function tagAd($tag, $content)
    {
        $aid  = isset($tag['aid']) ? $tag['aid'] : '0';
        $aid  = $this->varOrvalue($aid);

        $id     = isset($tag['id']) ? $tag['id'] : 'field';

        $parseStr = '<?php ';
        // 声明变量
        $parseStr .= ' $tagAd = new \think\template\taglib\eyou\TagAd;';
        $parseStr .= ' $_result = $tagAd->getAd('.$aid.');';
        $parseStr .= ' ?>';

        $parseStr .= '<?php if(is_array($_result) || $_result instanceof \think\Collection || $_result instanceof \think\Paginator): ';
        $parseStr .= ' $__LIST__ = $_result;';
        $parseStr .= 'if( count($__LIST__)==0 ) : echo "";';
        $parseStr .= 'else: ';
        $parseStr .= '$'.$id.' = $__LIST__;';
        $parseStr .= '?>';
        $parseStr .= $content;
        $parseStr .= '<?php endif; else: echo "";endif; ?>';
        $parseStr .= '<?php $'.$id.' = []; ?>'; // 清除变量值，只限于在标签内部使用

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * adv 广告标签
     * 在模板中给某个变量赋值 支持变量赋值
     * 格式：
     * {eyou:adv pid='' limit=''}
     *  <a href="{$field:links}" {$field.target}>{$field:title}</a>
     * {/eyou:adv}
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string
     */
    public function tagAdv($tag, $content)
    {
        $pid  =  !empty($tag['pid']) ? $tag['pid'] : '0';// 返回的变量pid
        $pid  = $this->varOrvalue($pid);
        $id     = isset($tag['id']) ? $tag['id'] : 'field';
        $orderby = !empty($tag['orderby']) ? $tag['orderby'] : ''; //排序
        if (isset($tag['loop'])) $tag['row'] = $tag['loop'];
        $row = !empty($tag['row']) ? $tag['row'] : 10;
        $where = !empty($tag['where']) ? $tag['where'] : ''; //查询条件
        $key  =  !empty($tag['key']) ? $tag['key'] : 'key';// 返回的变量key
        $key    = !empty($tag['key']) ? $tag['key'] : 'i';
        $empty  = isset($tag['empty']) ? $tag['empty'] : '';
        $empty  = htmlspecialchars($empty);
        $mod    = !empty($tag['mod']) && is_numeric($tag['mod']) ? $tag['mod'] : '2';
        $currentclass   = !empty($tag['currentclass']) ? $tag['currentclass'] : '';
        if (!isset($tag['currentclass'])) { // 加强兼容性
            $currentclass   = !empty($tag['currentstyle']) ? $tag['currentstyle'] : '';
        }

        $parseStr = '<?php ';

        // 查询数据库获取的数据集
        $parseStr .= ' $tagAdv = new \think\template\taglib\eyou\TagAdv;';
        $parseStr .= ' $_result = $tagAdv->getAdv('.$pid.', "'.$where.'", "'.$orderby.'");';
        $parseStr .= ' if(is_array($_result) || $_result instanceof \think\Collection || $_result instanceof \think\Paginator): $' . $key . ' = 0; $e = 1;';
        // 设置了输出数组长度
        if ('null' != $row) {
            $parseStr .= '$__LIST__ = is_array($_result) ? array_slice($_result,0, '.$row.', true) : $_result->slice(0, '.$row.', true); ';
        } else {
            $parseStr .= ' $__LIST__ = $_result;';
        }
        $parseStr .= 'if( count($__LIST__)==0 ) : echo htmlspecialchars_decode("' . $empty . '");';
        $parseStr .= 'else: ';
        $parseStr .= 'foreach($__LIST__ as $key=>$' . $id . '): ';

        $parseStr .= ' if ($' . $key . ' == 0) :';
        $parseStr .= ' $'.$id.'["currentclass"] = $'.$id.'["currentstyle"] = "'.$currentclass.'";';
        $parseStr .= ' else: ';
        $parseStr .= ' $'.$id.'["currentclass"] = $'.$id.'["currentstyle"] = "";';
        $parseStr .= ' endif;';

        $parseStr .= '$' . $key . '= intval($key) + 1;?>';
        $parseStr .= '<?php $mod = ($' . $key . ' % ' . $mod . ' ); ?>';
        $parseStr .= $content;
        $parseStr .= '<?php ++$e; ?>';
        $parseStr .= '<?php endforeach; endif; else: echo htmlspecialchars_decode("' . $empty . '");endif; ?>';
        $parseStr .= '<?php $'.$id.' = []; ?>'; // 清除变量值，只限于在标签内部使用

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * prenext 标签解析
     * 在模板中获取内容页的上下篇
     * 格式：
     * {eyou:prenext get='pre'}
     *  <a href="{$field:arcurl}">{$field:title}</a>
     * {/eyou:prenext}
     * @access public
     * @param array $tag 标签属性
     * @return string
     */
    public function tagPrenext($tag, $content)
    {
        $get  =  !empty($tag['get']) ? $tag['get'] : 'pre';
        $titlelen = !empty($tag['titlelen']) && is_numeric($tag['titlelen']) ? intval($tag['titlelen']) : 100;
        $id     = isset($tag['id']) ? $tag['id'] : 'field';

        if (isset($tag['empty'])) {
            $style = 1; // 第一种默认标签写法，带属性empty
        } else {
            $style = 2; // 第二种支持判断写法，可以 else
        }

        if (1 == $style) {
            $empty     = isset($tag['empty']) ? $tag['empty'] : '暂无';
            $empty  = htmlspecialchars($empty);
            
            $parseStr = '<?php ';
            $parseStr .= ' $tagPrenext = new \think\template\taglib\eyou\TagPrenext;';
            $parseStr .= ' $_result = $tagPrenext->getPrenext("'.$get.'");';
            $parseStr .= 'if(is_array($_result) || $_result instanceof \think\Collection || $_result instanceof \think\Paginator): ';
            $parseStr .= ' $__LIST__ = $_result;';
            $parseStr .= 'if( empty($__LIST__) ) : echo htmlspecialchars_decode("' . $empty . '");';
            $parseStr .= 'else: ';
            $parseStr .= '$'.$id.' = $__LIST__;';
            $parseStr .= '$' . $id . '["title"] = text_msubstr($' . $id . '["title"], 0, '.$titlelen.', false);';

            $parseStr .= '?>';
            $parseStr .= $content;
            $parseStr .= '<?php endif; else: echo htmlspecialchars_decode("' . $empty . '");endif; ?>';
        
        } else {
            $parseStr = '<?php ';
            $parseStr .= ' $tagPrenext = new \think\template\taglib\eyou\TagPrenext;';
            $parseStr .= ' $_result = $tagPrenext->getPrenext("'.$get.'");';
            $parseStr .= '?>';

            $parseStr .= '<?php if(!empty($_result) || (($_result instanceof \think\Collection || $_result instanceof \think\Paginator ) && $_result->isEmpty())): ?>';
            $parseStr .= '<?php $'.$id.' = $_result; ?>';
            $parseStr .= '<?php $' . $id . '["title"] = text_msubstr($' . $id . '["title"], 0, '.$titlelen.', false); ?>';
            $parseStr .= $content;
            $parseStr .= '<?php endif; ?>';
        }

        $parseStr .= '<?php $'.$id.' = []; ?>'; // 清除变量值，只限于在标签内部使用

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * guestbookform 留言表单标签解析 TAG调用
     * {eyou:guestbookform type='default'}
     * {$field.value}
     * {/eyou:guestbookform}
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string|void
     */
    public function tagGuestbookform($tag, $content)
    {
        if (!empty($tag['o']) && $tag['o'] == 'eyou:form') {
            $form_type = 1;
            //表单id
            $typeid   = !empty($tag['formid']) ? $tag['formid'] : 0;
            $typeid  = $this->varOrvalue($typeid);
        } else {
            $form_type = 0;
            //留言栏目id
            $typeid   = !empty($tag['typeid']) ? $tag['typeid'] : 0;
            $typeid  = $this->varOrvalue($typeid);
        }

        $type   = !empty($tag['type']) ? $tag['type'] : 'default';
        $id     = isset($tag['id']) ? $tag['id'] : 'field';
        $key    = !empty($tag['key']) ? $tag['key'] : 'i';
        $mod    = !empty($tag['mod']) && is_numeric($tag['mod']) ? $tag['mod'] : '2';
        $empty  = isset($tag['empty']) ? $tag['empty'] : '';
        $empty  = htmlspecialchars($empty);
        $beforeSubmit     = !empty($tag['before']) ? $tag['before'] : '';
        if (empty($beforeSubmit)) {
            $beforeSubmit     = !empty($tag['beforeSubmit']) ? $tag['beforeSubmit'] : '';
        }

        $parseStr = '<?php ';

        /*typeid的优先级别从高到低：装修数据 -> 标签属性值 -> 外层标签channelartlist属性值*/
        $parseStr .= ' $typeid = '.$typeid.';';
        $parseStr .= ' if(empty($typeid) && isset($channelartlist["id"]) && !empty($channelartlist["id"])) : $typeid = intval($channelartlist["id"]); endif; ';
        /*--end*/

        // 查询数据库获取的数据集
        $parseStr .= ' $tagGuestbookform = new \think\template\taglib\eyou\TagGuestbookform;';
        $parseStr .= ' $_result = $tagGuestbookform->getGuestbookform($typeid, "'.$type.'", "'.$beforeSubmit.'", '.$form_type.');';
        $parseStr .= ' if(is_array($_result) || $_result instanceof \think\Collection || $_result instanceof \think\Paginator): $' . $key . ' = 0; $e = 1;';
        $parseStr .= ' $__LIST__ = $_result;';

        $parseStr .= 'if( count($__LIST__)==0 ) : echo htmlspecialchars_decode("' . $empty . '");';
        $parseStr .= 'else: ';
        $parseStr .= 'foreach($__LIST__ as $key=>$' . $id . '): ';
        $parseStr .= '$' . $key . '= intval($key) + 1;?>';
        $parseStr .= '<?php $mod = ($' . $key . ' % ' . $mod . ' ); ?>';
        $parseStr .= $content;
        $parseStr .= '<?php ++$e; ?>';
        $parseStr .= '<?php endforeach;';
        $parseStr .= 'endif; else: echo htmlspecialchars_decode("' . $empty . '");endif; ?>';
        $parseStr .= '<?php $'.$id.' = []; ?>'; // 清除变量值，只限于在标签内部使用

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * field 标签解析
     * 在模板中获取变量值，只适用于标签channelartlist
     * 格式： {eyou:field name="typename|html_msubstr=###,0,2" /}
     * @access public
     * @param array $tag 标签属性
     * @return string
     */
    public function tagField($tag)
    {
        $aid_tmp  = isset($tag['aid']) ? $tag['aid'] : '0';
        $aid  = $this->varOrvalue($aid_tmp);

        $name  = isset($tag['name']) ? $tag['name'] : '';
        $addfields    = isset($tag['addfields']) ? $tag['addfields'] : '';

        $parseStr = '';

        if (!empty($name)) {
            $arr = explode('|', $name);
            $name = $arr[0];

            // 查询数据库获取的数据集
            $parseStr .= '<?php ';
            $parseStr .= ' $__VALUE__ = isset($channelartlist["'.$name.'"]) ? $channelartlist["'.$name.'"] : "变量名不存在";';

            if (1 < count($arr)) {
                $funcArr = explode('=', $arr[1]);
                $funcName = $funcArr[0]; // 函数名
                $funcParam = !empty($funcArr[1]) ? $funcArr[1] : ''; // 函数参数
                if (!empty($funcParam)) {
                    $funcParamStr = '';
                    foreach (explode(',', $funcParam) as $key => $val) {
                        if ('###' == $val) {
                            $val = '$__VALUE__';
                        }
                        if (0 < $key) {
                            $funcParamStr .= ', ';
                        }
                        $funcParamStr .= $val;
                    }
                    $parseStr .= '$__VALUE__ = '.$funcName.'('.$funcParamStr.');';
                }
            }

            $parseStr .= ' echo $__VALUE__;';
            $parseStr .= ' ?>';

        } else if (!empty($addfields)) {

            $addfieldsArr = explode('|', $addfields);

            $parseStr .= '<?php ';

            // 声明变量
            if (!empty($aid_tmp)) {
                $parseStr .= ' $aid = '.$aid.';';
            } else {
                $parseStr .= ' if(!isset($aid) || empty($aid)) : $aid = '.$aid.'; endif;';
            }

            // 查询数据库获取的数据集
            $parseStr .= ' $tagField = new \think\template\taglib\eyou\TagField;';
            $parseStr .= ' $__VALUE__ = $tagField->getField("'.$addfieldsArr[0].'", $aid);';

            // 字段指定的函数
            if (!empty($addfieldsArr[1])) {
                $funcArr = explode('=', $addfieldsArr[1]);
                $funcName = $funcArr[0]; // 函数名
                $funcParam = !empty($funcArr[1]) ? $funcArr[1] : ''; // 函数参数
                if (!empty($funcParam)) {
                    $funcParamStr = '';
                    foreach (explode(',', $funcParam) as $key => $val) {
                        if ('###' == $val) {
                            $val = '$__VALUE__';
                        }
                        if (0 < $key) {
                            $funcParamStr .= ', ';
                        }
                        $funcParamStr .= $val;
                    }
                    $parseStr .= '$__VALUE__ = '.$funcName.'('.$funcParamStr.');';
                }
            }

            $parseStr .= ' echo $__VALUE__;';
            $parseStr .= ' ?>';
            $parseStr .= '<?php unset($aid); ?>';
        }

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * empty标签解析
     * 如果某个变量为empty 则输出内容
     * 格式： {eyou:empty name="" }content{/eyou:empty}
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string
     */
    public function tagEmpty($tag, $content)
    {
        $name     = $tag['name'];
        $name     = $this->autoBuildVar($name);
        $parseStr = '<?php if(empty(' . $name . ') || ((' . $name . ' instanceof \think\Collection || ' . $name . ' instanceof \think\Paginator ) && ' . $name . '->isEmpty())): ?>' . $content . '<?php endif; ?>';
        return $parseStr;
    }

    /**
     * notempty 标签解析
     * 如果某个变量不为empty 则输出内容
     * 格式： {eyou:notempty name="" }content{/eyou:notempty}
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string
     */
    public function tagNotempty($tag, $content)
    {
        $name     = $tag['name'];
        $name     = $this->autoBuildVar($name);
        $parseStr = '<?php if(!(empty(' . $name . ') || ((' . $name . ' instanceof \think\Collection || ' . $name . ' instanceof \think\Paginator ) && ' . $name . '->isEmpty()))): ?>' . $content . '<?php endif; ?>';
        return $parseStr;
    }

    /**
     * assign标签解析
     * 在模板中给某个变量赋值 支持变量赋值
     * 格式： {eyou:assign name="" value="" /}
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string
     */
    public function tagAssign($tag, $content)
    {
        $name = $this->autoBuildVar($tag['name']);
        $flag = substr($tag['value'], 0, 1);
        if ('$' == $flag || ':' == $flag) {
            $value = $this->autoBuildVar($tag['value']);
        } else {
            $value = '\'' . $tag['value'] . '\'';
        }
        $parseStr = '<?php ' . $name . ' = ' . $value . '; ?>';
        return $parseStr;
    }

    /**
     * foreach标签解析 循环输出数据集
     * 格式：
     * {eyou:foreach name="userList" id="user" key="key" index="i" mod="2" offset="3" length="5" empty=""}
     * {user.username}
     * {/eyou:foreach}
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string|void
     */
    public function tagForeach($tag, $content)
    {
        // 直接使用表达式
        if (!empty($tag['expression'])) {
            $expression = ltrim(rtrim($tag['expression'], ')'), '(');
            $expression = $this->autoBuildVar($expression);
            $parseStr   = '<?php foreach(' . $expression . '): ?>';
            $parseStr .= $content;
            $parseStr .= '<?php endforeach; ?>';
            return $parseStr;
        }
        $name   = $tag['name'];
        $key    = !empty($tag['key']) ? $tag['key'] : 'key';
        $item   = !empty($tag['id']) ? $tag['id'] : $tag['item'];
        $empty  = isset($tag['empty']) ? $tag['empty'] : '';
        $empty  = htmlspecialchars($empty);
        $offset = !empty($tag['offset']) && is_numeric($tag['offset']) ? intval($tag['offset']) : 0;
        $length = !empty($tag['length']) && is_numeric($tag['length']) ? intval($tag['length']) : 'null';

        $parseStr = '<?php ';
        // 支持用函数传数组
        if (':' == substr($name, 0, 1)) {
            $var  = '$_' . uniqid();
            $name = $this->autoBuildVar($name);
            $parseStr .= $var . '=' . $name . '; ';
            $name = $var;
        } else {
            $name = $this->autoBuildVar($name);
        }
        $parseStr .= 'if(is_array(' . $name . ') || ' . $name . ' instanceof \think\Collection || ' . $name . ' instanceof \think\Paginator): ';
        // 设置了输出数组长度
        if (0 != $offset || 'null' != $length) {
            if (!isset($var)) {
                $var = '$_' . uniqid();
            }
            $parseStr .= $var . ' = is_array(' . $name . ') ? array_slice(' . $name . ',' . $offset . ',' . $length . ', true) : ' . $name . '->slice(' . $offset . ',' . $length . ', true); ';
        } else {
            $var = &$name;
        }

        $parseStr .= 'if( count(' . $var . ')==0 ) : echo htmlspecialchars_decode("' . $empty . '");';
        $parseStr .= 'else: ';

        // 设置了索引项
        if (isset($tag['index'])) {
            $index = $tag['index'];
            $parseStr .= '$' . $index . '=0; $e = 1;';
        }
        $parseStr .= 'foreach(' . $var . ' as $' . $key . '=>$' . $item . '): ';
        // 设置了索引项
        if (isset($tag['index'])) {
            $index = $tag['index'];
            if (!empty($tag['mod']) && is_numeric($tag['mod'])) {
                $mod = (int) $tag['mod'];
                $parseStr .= '$mod = ($e % ' . $mod . '); ';
            }
            $parseStr .= '++$' . $index . ';';
        }
        $parseStr .= '?>';
        // 循环体中的内容
        $parseStr .= $content;
        $parseStr .= '<?php ++$e; ?>';
        $parseStr .= '<?php endforeach; endif; else: echo htmlspecialchars_decode("' . $empty . '");endif; ?>';

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * if标签解析
     * 格式：
     * {eyou:if condition=" $a eq 1"}
     * {eyou:elseif condition="$a eq 2" /}
     * {eyou:else /}
     * {/eyou:if}
     * 表达式支持 eq neq gt egt lt elt == > >= < <= or and || &&
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string
     */
    public function tagIf($tag, $content)
    {
        $condition = !empty($tag['expression']) ? $tag['expression'] : $tag['condition'];
        $condition = $this->dangerReplace($condition);
        $condition = $this->parseCondition($condition);
        $parseStr  = '<?php if(' . $condition . '): ?>' . $content . '<?php endif; ?>';
        return $parseStr;
    }

    /**
     * elseif标签解析
     * 格式：见if标签
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string
     */
    public function tagElseif($tag, $content)
    {
        $condition = !empty($tag['expression']) ? $tag['expression'] : $tag['condition'];
        $condition = $this->dangerReplace($condition);
        $condition = $this->parseCondition($condition);
        $parseStr  = '<?php elseif(' . $condition . '): ?>';
        return $parseStr;
    }

    /**
     * else 标签解析
     * 格式：见if标签
     * @access public
     * @param array $tag 标签属性
     * @return string
     */
    public function tagElse($tag)
    {
        $parseStr = '<?php else: ?>';
        return $parseStr;
    }

    /**
     * 替换if condition 条件表达式里的危险函数成intval
     * @return [type] [description]
     */
    private function dangerReplace($condition)
    {
        $pattern = 'basename|copy|dirname|disk_free_space|disk_total_space|file_put_contents|file_get_contents|glob|mkdir|move_uploaded_file|parse_ini_file|rename|realpath|tempnam|tmpfile|unlink|chmod|fopen|opendir|closedir|readdir|fwrite|is_writable|fread|stat|phpinfo|passthru|exec|system|chroot|scandir|chgrp|chown|shell_exec|proc_open|proc_get_status|error_log|ini_alter|ini_set|ini_restore|dl|pfsockopen|syslog|readlink|symlink|popen|stream_socket_server|putenv';
        $condition = preg_replace("/(\;)?(\s*)({$pattern})(\s*)\(/i", 'intval(', $condition);
        $condition = preg_replace("/^(\s*)({$pattern})(\s*)\(/i", 'intval(', $condition);

        return $condition;
    }

    /**
     * switch标签解析
     * 格式：
     * {eyou:switch name="a.name"}
     * {eyou:case value="1" break="false"}1{/case}
     * {eyou:case value="2" }2{/case}
     * {eyou:default /}other
     * {/eyou:switch}
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string
     */
    public function tagSwitch($tag, $content)
    {
        $name     = !empty($tag['expression']) ? $tag['expression'] : $tag['name'];
        $name     = $this->autoBuildVar($name);
        $parseStr = '<?php switch(' . $name . '): ?>' . $content . '<?php endswitch; ?>';
        return $parseStr;
    }

    /**
     * case标签解析 需要配合switch才有效
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string
     */
    public function tagCase($tag, $content)
    {
        $value = !empty($tag['expression']) ? $tag['expression'] : $tag['value'];
        $flag  = substr($value, 0, 1);
        if ('$' == $flag || ':' == $flag) {
            $value = $this->autoBuildVar($value);
            $value = 'case ' . $value . ':';
        } elseif (strpos($value, '|')) {
            $values = explode('|', $value);
            $value  = '';
            foreach ($values as $val) {
                $value .= 'case "' . addslashes($val) . '":';
            }
        } else {
            $value = 'case "' . $value . '":';
        }
        $parseStr = '<?php ' . $value . ' ?>' . $content;
        $isBreak  = isset($tag['break']) ? $tag['break'] : '';
        if ('' == $isBreak || $isBreak) {
            $parseStr .= '<?php break; ?>';
        }
        return $parseStr;
    }

    /**
     * default标签解析 需要配合switch才有效
     * 使用： {eyou:default /}
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string
     */
    public function tagDefault($tag)
    {
        $parseStr = '<?php default: ?>';
        return $parseStr;
    }

    /**
     * compare标签解析
     * 用于值的比较 支持 eq neq gt lt egt elt heq nheq 默认是eq
     * 格式： {eyou:compare name="" type="eq" value="" }content{/eyou:compare}
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string
     */
    public function tagCompare($tag, $content)
    {
        $name  = isset($tag['name']) ? $tag['name'] : '';
        $value = isset($tag['value']) ? $tag['value'] : '';
        $type  = isset($tag['type']) ? $tag['type'] : 'eq'; // 比较类型
        $name  = $this->autoBuildVar($name);
        $flag  = substr($value, 0, 1);
        if ('$' == $flag || ':' == $flag) {
            $value = $this->autoBuildVar($value);
        } else {
            $value = '\'' . $value . '\'';
        }
        switch ($type) {
            case 'equal':
                $type = 'eq';
                break;
            case 'notequal':
                $type = 'neq';
                break;
        }
        $type     = $this->parseCondition(' ' . $type . ' ');
        $parseStr = '<?php if(' . $name . ' ' . $type . ' ' . $value . '): ?>' . $content . '<?php endif; ?>';
        return $parseStr;
    }

    /**
     * volist标签解析 循环输出数据集
     * 格式：
     * {eyou:volist name="userList" id="user" empty=""}
     * {user.username}
     * {user.email}
     * {/eyou:volist}
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string|void
     */
    public function tagVolist($tag, $content)
    {
        $name   = $tag['name'];
        $id  = isset($tag['id']) ? $tag['id'] : 'field';
        $empty  = isset($tag['empty']) ? $tag['empty'] : '';
        $empty  = htmlspecialchars($empty);
        $key    = !empty($tag['key']) ? $tag['key'] : 'i';
        $mod    = !empty($tag['mod']) && is_numeric($tag['mod']) ? $tag['mod'] : '2';
        $offset = !empty($tag['offset']) && is_numeric($tag['offset']) ? intval($tag['offset']) : 0;
        $length = !empty($tag['length']) && is_numeric($tag['length']) ? intval($tag['length']) : 'null';
        if (isset($tag['loop'])) $tag['row'] = $tag['loop'];
        if (!empty($tag['row'])) {
            $length = !empty($tag['row']) && is_numeric($tag['row']) ? intval($tag['row']) : 'null';
        }
        if (!empty($tag['limit'])) {
            $limitArr = explode(',', $tag['limit']);
            $offset = !empty($limitArr[0]) ? intval($limitArr[0]) : 0;
            $length = !empty($limitArr[1]) ? intval($limitArr[1]) : 'null';
        }
        // 允许使用函数设定数据集 <volist name=":fun('arg')" id="vo">{$vo.name}</volist>
        $parseStr = '<?php ';
        $flag     = substr($name, 0, 1);
        if (':' == $flag) {
            $name = $this->autoBuildVar($name);
            $parseStr .= '$_result=' . $name . ';';
            $name = '$_result';
        } else {
            $name = $this->autoBuildVar($name);
        }

        $parseStr .= 'if(is_array(' . $name . ') || ' . $name . ' instanceof \think\Collection || ' . $name . ' instanceof \think\Paginator): $' . $key . ' = 0; $e = 1;';
        // 设置了输出数组长度
        if (0 != $offset || 'null' != $length) {
            $parseStr .= '$__LIST__ = is_array(' . $name . ') ? array_slice(' . $name . ',' . $offset . ',' . $length . ', true) : ' . $name . '->slice(' . $offset . ',' . $length . ', true); ';
        } else {
            $parseStr .= ' $__LIST__ = ' . $name . ';';
        }
        $parseStr .= 'if( count($__LIST__)==0 ) : echo htmlspecialchars_decode("' . $empty . '");';
        $parseStr .= 'else: ';
        $parseStr .= 'foreach($__LIST__ as $key=>$' . $id . '): ';
        $parseStr .= '$' . $key . '= intval($key) + 1;?>';
        $parseStr .= '<?php $mod = ($' . $key . ' % ' . $mod . ' ); ?>';
        $parseStr .= $content;

        /*用于下载模型的ajax下载文件*/
        $parseStr .= '<?php echo isset($'.$id.'["ey_1563185380"])?$'.$id.'["ey_1563185380"]:""; ?>';
        $parseStr .= '<?php echo (1 == $e && isset($'.$id.'["ey_1563185376"]))?$'.$id.'["ey_1563185376"]:""; ?>';
        /*end*/
        
        $parseStr .= '<?php ++$e; ?>';
        $parseStr .= '<?php endforeach; endif; else: echo htmlspecialchars_decode("' . $empty . '");endif; ?>';
        $parseStr .= '<?php $'.$id.' = []; ?>'; // 清除变量值，只限于在标签内部使用

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * global 标签解析
     * 在模板中获取系统的变量值
     * 格式： {eyou:global name="" /}
     * @access public
     * @param array $tag 标签属性
     * @return string
     */
    public function tagGlobal($tag)
    {
        $name = $tag['name'];
        $name  = $this->varOrvalue($name);

        $parseStr = '<?php ';

        // 查询数据库获取的数据集
        $parseStr .= ' $tagGlobal = new \think\template\taglib\eyou\TagGlobal;';
        $parseStr .= ' $__VALUE__ = $tagGlobal->getGlobal('.$name.');';
        $parseStr .= ' echo $__VALUE__;';
        $parseStr .= ' ?>';

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * arcclick 标签解析
     * 在内容页模板追加显示浏览量
     * 格式： {eyou:arcclick aid='' /}
     * @access public
     * @param array $tag 标签属性
     * @return string
     */
    public function tagArcclick($tag)
    {
        $aid_tmp  = isset($tag['aid']) ? $tag['aid'] : 0;
        $aid  = $this->varOrvalue($aid_tmp);

        $value = isset($tag['value']) ? $tag['value'] : '';
        $value  = $this->varOrvalue($value);

        $type = isset($tag['type']) ? $tag['type'] : '';

        $parseStr = '<?php ';
        // 声明变量
        if (!empty($aid_tmp)) {
            $parseStr .= ' $aid = '.$aid.';';
        } else {
            $parseStr .= ' if(!isset($aid) || empty($aid)) : $aid = '.$aid.'; endif;';
        }

        // 查询数据库获取的数据集
        $parseStr .= ' $tagArcclick = new \think\template\taglib\eyou\TagArcclick;';
        $parseStr .= ' $__VALUE__ = $tagArcclick->getArcclick($aid, '.$value.', "'.$type.'");';
        $parseStr .= ' echo $__VALUE__;';
        $parseStr .= ' ?>';

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * downcount 标签解析
     * 在内容页模板追加显示下载量
     * 格式： {eyou:downcount aid='' /}
     * @access public
     * @param array $tag 标签属性
     * @return string
     */
    public function tagDowncount($tag)
    {
        $aid_tmp  = isset($tag['aid']) ? $tag['aid'] : 0;
        $aid  = $this->varOrvalue($aid_tmp);

        $parseStr = '<?php ';
        // 声明变量
        if (!empty($aid_tmp)) {
            $parseStr .= ' $aid = '.$aid.';';
        } else {
            $parseStr .= ' if(!isset($aid) || empty($aid)) : $aid = '.$aid.'; endif;';
        }

        // 查询数据库获取的数据集
        $parseStr .= ' $tagDowncount = new \think\template\taglib\eyou\TagDowncount;';
        $parseStr .= ' $__VALUE__ = $tagDowncount->getDowncount($aid);';
        $parseStr .= ' echo $__VALUE__;';
        $parseStr .= ' ?>';

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * collectnum 标签解析
     * 在内容页模板追加显示收藏数
     * 格式： {eyou:collectnum aid='' /}
     * @access public
     * @param array $tag 标签属性
     * @return string
     */
    public function tagCollectnum($tag)
    {
        $aid_tmp  = isset($tag['aid']) ? $tag['aid'] : 0;
        $aid  = $this->varOrvalue($aid_tmp);

        $parseStr = '<?php ';
        // 声明变量
        if (!empty($aid_tmp)) {
            $parseStr .= ' $aid = '.$aid.';';
        } else {
            $parseStr .= ' if(!isset($aid) || empty($aid)) : $aid = '.$aid.'; endif;';
        }

        // 查询数据库获取的数据集
        $parseStr .= ' $tagCollectnum = new \think\template\taglib\eyou\TagCollectnum;';
        $parseStr .= ' $__VALUE__ = $tagCollectnum->getCollectnum($aid);';
        $parseStr .= ' echo $__VALUE__;';
        $parseStr .= ' ?>';

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * freebuynum 标签解析
     * 在内容页模板追加显示付费文档订单数
     * 格式： {eyou:freebuynum aid='' /}
     * @access public
     * @param array $tag 标签属性
     * @return string
     */
    public function tagFreebuynum($tag)
    {
        $aid_tmp  = isset($tag['aid']) ? $tag['aid'] : 0;
        $aid  = $this->varOrvalue($aid_tmp);
        $modelid   = isset($tag['modelid']) ? $tag['modelid'] : (isset($tag['channelid']) ? $tag['channelid'] : 0);

        $parseStr = '<?php ';
        // 声明变量
        if (!empty($aid_tmp)) {
            $parseStr .= ' $aid = '.$aid.';';
        } else {
            $parseStr .= ' if(!isset($aid) || empty($aid)) : $aid = '.$aid.'; endif;';
        }

        // 查询数据库获取的数据集
        $parseStr .= ' $tagFreebuynum = new \think\template\taglib\eyou\TagFreebuynum;';
        $parseStr .= ' $__VALUE__ = $tagFreebuynum->getFreebuynum($aid, '.$modelid.');';
        $parseStr .= ' echo $__VALUE__;';
        $parseStr .= ' ?>';

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * php标签解析
     * 格式：
     * {eyou:php}echo $name{/eyou:php}
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string
     */
    public function tagPhp($tag, $content)
    {
        $parseStr = '<?php ' . $content . ' ?>';
        return $parseStr;
    }

    /**
     * weapp标签解析
     * 安装网站应用插件时自动在页面上追加代码
     * 格式： {eyou:weapp type='default'}content{/eyou:weapp}
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string
     */
    public function tagWeapp($tag, $content)
    {
        $type     = isset($tag['type']) ? $tag['type'] : 'default';

        $parseStr = ' <?php ';
        $parseStr .= ' $tagWeapp = new \think\template\taglib\eyou\TagWeapp;';
        $parseStr .= ' $__VALUE__ = $tagWeapp->getWeapp("'.$type.'");';
        $parseStr .= ' echo $__VALUE__;';
        $parseStr .= ' ?>';

        return $parseStr;
    }

    /**
     * range标签解析
     * 如果某个变量存在于某个范围 则输出内容 type= in 表示在范围内 否则表示在范围外
     * 格式： {eyou:range name="var|function"  value="val" type='in|notin' }content{/eyou:range}
     * example: {eyou:range name="a"  value="1,2,3" type='in' }content{/eyou:range}
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string
     */
    public function tagRange($tag, $content)
    {
        $name  = $tag['name'];
        $value = $tag['value'];
        $type  = isset($tag['type']) ? $tag['type'] : 'in'; // 比较类型

        $name = $this->autoBuildVar($name);
        $flag = substr($value, 0, 1);
        if ('$' == $flag || ':' == $flag) {
            $value = $this->autoBuildVar($value);
            $str   = 'is_array(' . $value . ')?' . $value . ':explode(\',\',' . $value . ')';
        } else {
            $value = '"' . $value . '"';
            $str   = 'explode(\',\',' . $value . ')';
        }
        if ('between' == $type) {
            $parseStr = '<?php $_RANGE_VAR_=' . $str . ';if(' . $name . '>= $_RANGE_VAR_[0] && ' . $name . '<= $_RANGE_VAR_[1]):?>' . $content . '<?php endif; ?>';
        } elseif ('notbetween' == $type) {
            $parseStr = '<?php $_RANGE_VAR_=' . $str . ';if(' . $name . '<$_RANGE_VAR_[0] || ' . $name . '>$_RANGE_VAR_[1]):?>' . $content . '<?php endif; ?>';
        } else {
            $fun      = ('in' == $type) ? 'in_array' : '!in_array';
            $parseStr = '<?php if(' . $fun . '((' . $name . '), ' . $str . ')): ?>' . $content . '<?php endif; ?>';
        }
        return $parseStr;
    }

    /**
     * present标签解析
     * 如果某个变量已经设置 则输出内容
     * 格式： {eyou:present name="" }content{/eyou:present}
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string
     */
    public function tagPresent($tag, $content)
    {
        $name     = $tag['name'];
        $name     = $this->autoBuildVar($name);
        $parseStr = '<?php if(isset(' . $name . ')): ?>' . $content . '<?php endif; ?>';
        return $parseStr;
    }

    /**
     * notpresent标签解析
     * 如果某个变量没有设置，则输出内容
     * 格式： {eyou:notpresent name="" }content{/eyou:notpresent}
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string
     */
    public function tagNotpresent($tag, $content)
    {
        $name     = $tag['name'];
        $name     = $this->autoBuildVar($name);
        $parseStr = '<?php if(!isset(' . $name . ')): ?>' . $content . '<?php endif; ?>';
        return $parseStr;
    }

    /**
     * 判断是否已经定义了该常量
     * {eyou:defined name='TXT'}已定义{/eyou:defined}
     * @param array $tag
     * @param string $content
     * @return string
     */
    public function tagDefined($tag, $content)
    {
        $name     = $tag['name'];
        $parseStr = '<?php if(defined("' . $name . '")): ?>' . $content . '<?php endif; ?>';
        return $parseStr;
    }

    /**
     * for标签解析
     * 格式：
     * {eyou:for start="" end="" comparison="" step="" name=""}
     * content
     * {/eyou:for}
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string
     */
    public function tagFor($tag, $content)
    {
        //设置默认值
        $start      = 0;
        $end        = 0;
        $step       = 1;
        $comparison = 'lt';
        $name       = 'i';
        $rand       = rand(); //添加随机数，防止嵌套变量冲突
        //获取属性
        foreach ($tag as $key => $value) {
            $value = trim($value);
            $flag  = substr($value, 0, 1);
            if ('$' == $flag || ':' == $flag) {
                $value = $this->autoBuildVar($value);
            }

            switch ($key) {
                case 'start':
                    $start = $value;
                    break;
                case 'end':
                    $end = $value;
                    break;
                case 'step':
                    $step = $value;
                    break;
                case 'comparison':
                    $comparison = $value;
                    break;
                case 'name':
                    $name = $value;
                    break;
            }
        }

        $parseStr = '<?php $__FOR_START_' . $rand . '__=' . $start . ';$__FOR_END_' . $rand . '__=' . $end . ';';
        $parseStr .= 'for($' . $name . '=$__FOR_START_' . $rand . '__;' . $this->parseCondition('$' . $name . ' ' . $comparison . ' $__FOR_END_' . $rand . '__') . ';$' . $name . '+=' . $step . '){ ?>';
        $parseStr .= $content;
        $parseStr .= '<?php } ?>';
        return $parseStr;
    }

    /**
     * url函数的tag标签
     * 格式：{eyou:url link="模块/控制器/方法" vars="参数" suffix="true或者false 是否带有后缀" domain="true或者false 是否携带域名" /}
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string
     */
    public function tagUrl($tag, $content)
    {
        $link    = isset($tag['link']) ? $tag['link'] : '';
        $vars   = isset($tag['vars']) ? $tag['vars'] : '';
        $suffix = isset($tag['suffix']) ? $tag['suffix'] : 'true';
        $domain = isset($tag['domain']) ? $tag['domain'] : 'false';
        $seo_pseudo = isset($tag['seo_pseudo']) ? $tag['seo_pseudo'] : 'null';
        $seo_pseudo_format = isset($tag['seo_pseudo_format']) ? $tag['seo_pseudo_format'] : 'null';
        $seo_inlet = isset($tag['seo_inlet']) ? $tag['seo_inlet'] : 'null';
        return '<?php echo url("' . $link . '","' . $vars . '",' . $suffix . ',' . $domain . ',' . $seo_pseudo . ',' . $seo_pseudo_format . ',' . $seo_inlet . ');?>';
    }

    /**
     * function标签解析 匿名函数，可实现递归
     * 使用：
     * {eyou:function name="func" vars="$data" call="$list" use="&$a,&$b"}
     *      {eyou:if is_array($data)}
     *          {eyou:foreach $data as $val}
     *              {~func($val) /}
     *          {/eyou:foreach}
     *      {eyou:else /}
     *          {$data}
     *      {/eyou:if}
     * {/eyou:function}
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string
     */
    public function tagFunction($tag, $content)
    {
        $name = !empty($tag['name']) ? $tag['name'] : 'func';
        $vars = !empty($tag['vars']) ? $tag['vars'] : '';
        $call = !empty($tag['call']) ? $tag['call'] : '';
        $use  = ['&$' . $name];
        if (!empty($tag['use'])) {
            foreach (explode(',', $tag['use']) as $val) {
                $use[] = '&' . ltrim(trim($val), '&');
            }
        }
        $parseStr = '<?php $' . $name . '=function(' . $vars . ') use(' . implode(',', $use) . ') {';
        $parseStr .= ' ?>' . $content . '<?php }; ';
        $parseStr .= $call ? '$' . $name . '(' . $call . '); ?>' : '?>';
        return $parseStr;
    }

    /**
     * diyfield标签解析 循环输出自定义字段图集
     * 格式：
     * {eyou:diyfield type="default" name="$eyou.field.imgs" id="field"}
     * <img src="{$field.image_url}" />
     * {/eyou:diyfield}
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string|void
     */
    public function tagDiyfield($tag, $content)
    {
        $name   = $tag['name'];
        $id  = isset($tag['id']) ? $tag['id'] : 'field';
        $key    = !empty($tag['key']) ? $tag['key'] : 'i';
        $mod    = !empty($tag['mod']) && is_numeric($tag['mod']) ? $tag['mod'] : '2';
        $type    = isset($tag['type']) ? $tag['type'] : 'default';
        $empty  = isset($tag['empty']) ? $tag['empty'] : '';
        $empty  = htmlspecialchars($empty);
        $offset = 0;
        $length = 'null';
        if (!empty($tag['limit'])) {
            $limitArr = explode(',', $tag['limit']);
            $offset = !empty($limitArr[0]) ? intval($limitArr[0]) : 0;
            $length = !empty($limitArr[1]) ? intval($limitArr[1]) : 'null';
        }

        $parseStr = '<?php ';
        $flag     = substr($name, 0, 1);
        if (':' == $flag) {
            $name = $this->autoBuildVar($name);
            $parseStr .= '$_result=' . $name . ';';
            $name = '$_result';
        } else {
            $name = $this->autoBuildVar($name);
        }

        // 查询数据库获取的数据集
        $parseStr .= ' $tagDiyfield = new \think\template\taglib\eyou\TagDiyfield;';
        $parseStr .= ' $_result = $tagDiyfield->getDiyfield('.$name.', "'.$type.'");';

        $parseStr .= 'if(is_array($_result) || $_result instanceof \think\Collection || $_result instanceof \think\Paginator): $' . $key . ' = 0; $e = 1;';
        // 设置了输出数组长度
        if (0 != $offset || 'null' != $length) {
            $parseStr .= '$__LIST__ = is_array($_result) ? array_slice($_result,' . $offset . ',' . $length . ', true) : $_result->slice(' . $offset . ',' . $length . ', true); ';
        } else {
            $parseStr .= ' $__LIST__ = $_result;';
        }
        $parseStr .= 'if( count($__LIST__)==0 ) : echo htmlspecialchars_decode("' . $empty . '");';
        $parseStr .= 'else: ';
        $parseStr .= 'foreach($__LIST__ as $key=>$' . $id . '): ';
        $parseStr .= '$' . $key . '= intval($key) + 1;?>';
        $parseStr .= '<?php $mod = ($' . $key . ' % ' . $mod . ' ); ?>';
        $parseStr .= $content;
        $parseStr .= '<?php ++$e; ?>';
        $parseStr .= '<?php endforeach; endif; else: echo htmlspecialchars_decode("' . $empty . '");endif; ?>';
        $parseStr .= '<?php $'.$id.' = []; ?>'; // 清除变量值，只限于在标签内部使用

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * videoplay标签解析 指定播放视频
     * 格式：
     * {eyou:videoplay aid='' empty=''}
     *  <a href="{$field:arcurl}">{$field:title}</a>
     * {/eyou:videoplay}
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string|void
     */
    public function tagVideoplay($tag, $content)
    {
        $aid    = !empty($tag['aid']) ? $tag['aid'] : '';
        $aid    = $this->varOrvalue($aid);
        $id     = isset($tag['id']) ? $tag['id'] : 'field';
        $empty  = isset($tag['empty']) ? $tag['empty'] : '';
        $empty  = htmlspecialchars($empty);
        $autoplay    = !empty($tag['autoplay']) ? $tag['autoplay'] : 'off';

        $parseStr = '<?php ';

        /*aid的优先级别从高到低：标签属性值 -> 外层标签list/arclist属性值*/
        $parseStr .= ' if(empty($aid)) : $aid_tmp = '.$aid.'; endif; ';
        $parseStr .= ' $taid = 0; ';
        $parseStr .= ' if(!empty($aid_tmp)) : $taid = $aid_tmp; elseif(!empty($aid)) : $taid = $aid; endif;';
        /*--end*/

        $parseStr .= ' $tagVideoplay = new \think\template\taglib\eyou\TagVideoplay;';
        $parseStr .= ' $_result = $tagVideoplay->getVideoplay($taid, "'.$autoplay.'");';
        $parseStr .= ' ?>';

        $parseStr .= '<?php if(is_array($_result) || $_result instanceof \think\Collection || $_result instanceof \think\Paginator): ';
        $parseStr .= ' $__LIST__ = $_result;';
        $parseStr .= 'if( count($__LIST__)==0 ) : echo htmlspecialchars_decode("' . $empty . '");';
        $parseStr .= 'else: ';
        $parseStr .= '$'.$id.' = $__LIST__;';
        $parseStr .= '?>';
        $parseStr .= $content;
        $parseStr .= '<?php endif; else: echo htmlspecialchars_decode("' . $empty . '");endif; ?>';
        $parseStr .= '<?php unset($aid); ?>';
        $parseStr .= '<?php $'.$id.' = []; ?>'; // 清除变量值，只限于在标签内部使用

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * videolist 视频列表标签解析
     * {eyou:videolist type='default'}
     * url地址:{$field.file_url} 名称:{$field.file_title}  时长:{$field.file_time}
     * {/eyou:videolist}
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string|void
     */
    public function tagVideolist($tag, $content)
    {
        $aid    = !empty($tag['aid']) ? $tag['aid'] : '';
        $aid    = $this->varOrvalue($aid);
        $id     = isset($tag['id']) ? $tag['id'] : 'field';
        $key    = !empty($tag['key']) ? $tag['key'] : 'i';
        $mod    = !empty($tag['mod']) && is_numeric($tag['mod']) ? $tag['mod'] : '2';
        $empty  = isset($tag['empty']) ? $tag['empty'] : '';
        $empty  = htmlspecialchars($empty);
        $autoplay    = !empty($tag['autoplay']) ? $tag['autoplay'] : '';
        $player     = isset($tag['player']) ? $tag['player'] : 'default'; // default 默认播放在文档页(比如：demo)，list 文档页以目录结构展示(比如：易小优)，play 在独立播放页展示(比如：易小优)

        $parseStr = '<?php ';

        /*aid的优先级别从高到低：标签属性值 -> 外层标签list/arclist属性值*/
        $parseStr .= ' if(empty($aid)) : $aid_tmp = '.$aid.'; endif; ';
        $parseStr .= ' $taid = 0; ';
        $parseStr .= ' if(!empty($aid_tmp)) : $taid = $aid_tmp; elseif(!empty($aid)) : $taid = $aid; endif;';
        /*--end*/

        // 查询数据库获取的数据集
        $parseStr .= ' $tagVideolist = new \think\template\taglib\eyou\TagVideolist;';
        $parseStr .= ' $_result = $tagVideolist->getVideolist($taid, "'.$autoplay.'", "'.$player.'");';
        $parseStr .= ' if(is_array($_result) || $_result instanceof \think\Collection || $_result instanceof \think\Paginator): $' . $key . ' = 0; $e = 1;';
        $parseStr .= ' $__LIST__ = $_result;';
        $parseStr .= 'if( count($__LIST__)==0 ) : echo htmlspecialchars_decode("' . $empty . '");';
        $parseStr .= 'else: ';
        $parseStr .= 'foreach($__LIST__ as $key=>$' . $id . '): ';
        $parseStr .= '$' . $key . '= intval($key) + 1;?>';
        $parseStr .= '<?php $mod = ($' . $key . ' % ' . $mod . ' ); ?>';
        $parseStr .= $content;
        $parseStr .= '<?php ++$e; ?>';
        $parseStr .= '<?php endforeach;';
        $parseStr .= 'endif; else: echo htmlspecialchars_decode("' . $empty . '");endif; ?>';
        $parseStr .= '<?php $'.$id.' = []; ?>'; // 清除变量值，只限于在标签内部使用

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * attribute 栏目属性标签解析 TAG调用
     * {eyou:attribute type='default'}
     * {$field.itemname_2}:{$field.attr_2}
     * {/eyou:attribute}
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string|void
     */
    public function tagAttribute($tag, $content)
    {
        $aid    = !empty($tag['aid']) ? $tag['aid'] : '';
        $aid    = $this->varOrvalue($aid);
        $attrid = !empty($tag['attrid']) ? $tag['attrid'] : '';
        $attrid = $this->varOrvalue($attrid);
        $type   = !empty($tag['type']) ? $tag['type'] : 'default';
        $id     = isset($tag['id']) ? $tag['id'] : 'attr';
        $key    = !empty($tag['key']) ? $tag['key'] : 'i';
        $mod    = !empty($tag['mod']) && is_numeric($tag['mod']) ? $tag['mod'] : '2';
        $empty  = isset($tag['empty']) ? $tag['empty'] : '';
        $empty  = htmlspecialchars($empty);
        $offset = !empty($tag['offset']) && is_numeric($tag['offset']) ? intval($tag['offset']) : 0;
        if (isset($tag['loop'])) $tag['row'] = $tag['loop'];
        $row = !empty($tag['row']) && is_numeric($tag['row']) ? intval($tag['row']) : 100;
        if (!empty($tag['limit'])) {
            $limitArr = explode(',', $tag['limit']);
            $offset = !empty($limitArr[0]) ? intval($limitArr[0]) : 0;
            $row = !empty($limitArr[1]) ? intval($limitArr[1]) : 0;
        }

        $parseStr = '<?php ';

        /*aid的优先级别从高到低：标签属性值 -> 外层标签list/arclist属性值*/
        $parseStr .= ' if(empty($aid)) : $aid_tmp = '.$aid.'; endif; ';
        $parseStr .= ' $taid = 0; ';
        $parseStr .= ' if(!empty($aid_tmp)) : $taid = $aid_tmp; elseif(!empty($aid)) : $taid = $aid; endif;';
        /*--end*/

        // 查询数据库获取的数据集
        $parseStr .= ' $tagAttribute = new \think\template\taglib\eyou\TagAttribute;';
        $parseStr .= ' $_result = $tagAttribute->getAttribute($taid, "'.$type.'", '.$attrid.');';
        $parseStr .= ' if(is_array($_result) || $_result instanceof \think\Collection || $_result instanceof \think\Paginator): $' . $key . ' = 0; $e = 1;';
        // 设置了输出数组长度
        if (0 != $offset || 'null' != $row) {
            $parseStr .= ' $__LIST__ = is_array($_result) ? array_slice($_result,' . $offset . ', '.$row.', true) : $_result->slice(' . $offset . ', '.$row.', true); ';
        } else {
            $parseStr .= ' $__LIST__ = $_result;';
        }

        $parseStr .= 'if( count($__LIST__)==0 ) : echo htmlspecialchars_decode("' . $empty . '");';
        $parseStr .= 'else: ';
        $parseStr .= 'foreach($__LIST__ as $key=>$' . $id . '): ';
        $parseStr .= '$' . $key . '= intval($key) + 1;?>';
        $parseStr .= '<?php $mod = ($' . $key . ' % ' . $mod . ' ); ?>';
        $parseStr .= $content;
        $parseStr .= '<?php ++$e; ?>';
        $parseStr .= '<?php endforeach;';
        $parseStr .= 'endif; else: echo htmlspecialchars_decode("' . $empty . '");endif; ?>';
        $parseStr .= '<?php $'.$id.' = []; ?>'; // 清除变量值，只限于在标签内部使用

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * attr 标签解析
     * 在模板中获取栏目属性值
     * 格式： {eyou:attr name="" /}
     * @access public
     * @param array $tag 标签属性
     * @return string
     */
    public function tagAttr($tag)
    {
        $aid   = !empty($tag['aid']) ? $tag['aid'] : '';
        $aid  = $this->varOrvalue($aid);
        $name     = isset($tag['name']) ? $tag['name'] : '';

        $parseStr = '<?php ';

        /*aid的优先级别从高到低：标签属性值 -> 外层标签list/arclist属性值*/
        $parseStr .= ' $aid_tmp = '.$aid.'; ';
        $parseStr .= ' if(!empty($aid_tmp)) : $taid = $aid_tmp; else : $taid = $aid; endif;';
        /*--end*/

        // 查询数据库获取的数据集
        $parseStr .= ' $tagAttr = new \think\template\taglib\eyou\TagAttr;';
        $parseStr .= ' $__VALUE__ = $tagAttr->getAttr($taid,"'.$name.'");';
        $parseStr .= ' echo $__VALUE__;';
        $parseStr .= ' ?>';
        $parseStr .= '<?php unset($aid_tmp); ?>';

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * user 标签解析
     * 在模板中获取会员登录入口
     * 格式：
     * {eyou:user type='default'}
     *  <a href="{$field.url}">{$field.username}</a>
     * {/eyou:user}
     * @access public
     * @param array $tag 标签属性
     * @return string
     */
    public function tagUser($tag, $content)
    {
        $type  =  !empty($tag['type']) ? $tag['type'] : 'default';
        $id     = isset($tag['id']) ? $tag['id'] : 'field';
        $key     = isset($tag['key']) ? $tag['key'] : 'i';
        $txt  =  !empty($tag['txt']) ? $tag['txt'] : '';
        $txt  = $this->varOrvalue($txt);
        $txtid  =  !empty($tag['txtid']) ? $tag['txtid'] : '';
        $img  =  !empty($tag['img']) ? $tag['img'] : 'off';
        $currentclass   = !empty($tag['currentclass']) ? $tag['currentclass'] : '';
        if (!isset($tag['currentclass'])) { // 加强兼容性
            $currentclass   = !empty($tag['currentstyle']) ? $tag['currentstyle'] : '';
        }
        $afterhtml  =  !empty($tag['afterhtml']) ? $tag['afterhtml'] : '';
        $afterhtml  = $this->varOrvalue($afterhtml);
        $viewfile  =  !empty($tag['viewfile']) ? $tag['viewfile'] : '';

        $parseStr = '<?php ';
        $parseStr .= ' $tagUser = new \think\template\taglib\eyou\TagUser;';
        $parseStr .= ' $__LIST__ = $tagUser->getUser("'.$type.'", "'.$img.'", "'.$currentclass.'", '.$txt.', "'.$txtid.'", '.$afterhtml.', "'.$viewfile.'");';
        $parseStr .= '?>';

        $parseStr .= '<?php if(!empty($__LIST__) || (($__LIST__ instanceof \think\Collection || $__LIST__ instanceof \think\Paginator ) && $__LIST__->isEmpty())): ?>';
        $parseStr .= '<?php $'.$id.' = $__LIST__; ?>';
        $parseStr .= $content;
        $parseStr .= '<?php endif; ?>';
        $parseStr .= '<?php $'.$id.' = []; ?>'; // 清除变量值，只限于在标签内部使用

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * weapplist标签解析
     * 安装网站应用插件时自动在页面上追加代码
     * 格式： {eyou:weapplist type='default'}content{/eyou:weapplist}
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string
     */
    public function tagWeapplist($tag, $content)
    {
        $type     = isset($tag['type']) ? $tag['type'] : 'default';
        $id     = isset($tag['id']) ? $tag['id'] : 'field';
        $key     = isset($tag['key']) ? $tag['key'] : 'i';
        $empty  = isset($tag['empty']) ? $tag['empty'] : '';
        $mod    = !empty($tag['mod']) && is_numeric($tag['mod']) ? $tag['mod'] : '2';
        $currentclass   = !empty($tag['currentclass']) ? $tag['currentclass'] : '';
        if (!isset($tag['currentclass'])) { // 加强兼容性
            $currentclass   = !empty($tag['currentstyle']) ? $tag['currentstyle'] : '';
        }

        $parseStr = '<?php ';

        // 查询数据库获取的数据集
        $parseStr .= ' $tagWeapplist = new \think\template\taglib\eyou\TagWeapplist;';
        $parseStr .= ' $_result = $tagWeapplist->getWeapplist("'.$type.'","'.$currentclass.'");';
        $parseStr .= ' if(is_array($_result) || $_result instanceof \think\Collection || $_result instanceof \think\Paginator): $' . $key . ' = 0; $e = 1;';
        $parseStr .= ' $__LIST__ = $_result;';

        $parseStr .= 'if( count($__LIST__)==0 ) : echo htmlspecialchars_decode("' . $empty . '");';
        $parseStr .= 'else: ';
        $parseStr .= 'foreach($__LIST__ as $key=>$' . $id . '): ';
        $parseStr .= '$' . $key . '= intval($key) + 1;?>';
        $parseStr .= '<?php $mod = ($' . $key . ' % ' . $mod . ' ); ?>';
        $parseStr .= $content;
        $parseStr .= '<?php ++$e; ?>';
        $parseStr .= '<?php endforeach; endif; else: echo htmlspecialchars_decode("' . $empty . '");endif; ?>';
        $parseStr .= '<?php $'.$id.' = []; ?>'; // 清除变量值，只限于在标签内部使用

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * usermenu 会员左侧菜单
     * 格式：
     * {eyou:usermenu currentclass=''}
     *  <a href="{$field:url}">{$field:title}</a>
     * {/eyou:usermenu}
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string
     */
    public function tagUsermenu($tag, $content)
    {
        $id     = isset($tag['id']) ? $tag['id'] : 'field';
        $key    = !empty($tag['key']) ? $tag['key'] : 'i';
        $empty  = isset($tag['empty']) ? $tag['empty'] : '';
        $empty  = htmlspecialchars($empty);
        $mod    = !empty($tag['mod']) && is_numeric($tag['mod']) ? $tag['mod'] : '2';
        $currentclass   = !empty($tag['currentclass']) ? $tag['currentclass'] : '';
        if (!isset($tag['currentclass'])) { // 加强兼容性
            $currentclass   = !empty($tag['currentstyle']) ? $tag['currentstyle'] : '';
        }
        if (isset($tag['loop'])) $tag['row'] = $tag['loop'];
        $row = !empty($tag['row']) ? intval($tag['row']) : 0;
        $limit   = !empty($tag['limit']) ? $tag['limit'] : '';
        if (empty($limit) && !empty($row)) {
            $limit = "0,{$row}";
        }

        $parseStr = '<?php ';

        // 查询数据库获取的数据集
        $parseStr .= ' $tagUsermenu = new \think\template\taglib\eyou\TagUsermenu;';
        $parseStr .= ' $_result = $tagUsermenu->getUsermenu("'.$currentclass.'", "'.$limit.'");';
        $parseStr .= ' if(is_array($_result) || $_result instanceof \think\Collection || $_result instanceof \think\Paginator): $' . $key . ' = 0; $e = 1;';
        $parseStr .= ' $__LIST__ = $_result;';
        $parseStr .= 'if( count($__LIST__)==0 ) : echo htmlspecialchars_decode("' . $empty . '");';
        $parseStr .= 'else: ';
        $parseStr .= 'foreach($__LIST__ as $key=>$' . $id . '): ';
        $parseStr .= '$' . $key . '= intval($key) + 1;?>';
        $parseStr .= '<?php $mod = ($' . $key . ' % ' . $mod . ' ); ?>';
        $parseStr .= $content;
        $parseStr .= '<?php ++$e; ?>';
        $parseStr .= '<?php endforeach; endif; else: echo htmlspecialchars_decode("' . $empty . '");endif; ?>';
        $parseStr .= '<?php $'.$id.' = []; ?>'; // 清除变量值，只限于在标签内部使用

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * sppurchase 标签解析
     * 购物行为标签
     * 格式：
     * {eyou:sppurchase id='field'}
     *  <li><a href='{$field:url}'>{$field:title}</a> </li> 
     * {/eyou:sppurchase}
     * @access public
     * @param array $tag 标签属性
     * @return string
     */
    public function tagSppurchase($tag, $content)
    {
        $type  =  !empty($tag['type']) ? $tag['type'] : 'default';
        $id     = isset($tag['id']) ? $tag['id'] : 'field';
        $key     = isset($tag['key']) ? $tag['key'] : 'i';
        $txt  =  !empty($tag['txt']) ? $tag['txt'] : '';
        $txt  = $this->varOrvalue($txt);
        $img  =  !empty($tag['img']) ? $tag['img'] : 'off';
        $currentclass   = !empty($tag['currentclass']) ? $tag['currentclass'] : '';
        if (!isset($tag['currentclass'])) { // 加强兼容性
            $currentclass   = !empty($tag['currentstyle']) ? $tag['currentstyle'] : '';
        }

        $parseStr = '<?php ';
        $parseStr .= ' $tagSppurchase = new \think\template\taglib\eyou\TagSppurchase;';
        $parseStr .= ' $__LIST__ = $tagSppurchase->getSppurchase("'.$currentclass.'");';
        $parseStr .= '?>';

        $parseStr .= '<?php if(!empty($__LIST__) || (($__LIST__ instanceof \think\Collection || $__LIST__ instanceof \think\Paginator ) && $__LIST__->isEmpty())): ?>';
        $parseStr .= '<?php $'.$id.' = $__LIST__; ?>';
        $parseStr .= $content;
        $parseStr .= '<?php endif; ?>';
        $parseStr .= '<?php $'.$id.' = []; ?>'; // 清除变量值，只限于在标签内部使用

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * spcart 标签解析 TAG调用
     * 格式：sort:排序方式 month，rand，week
     *       getall:获取类型 0 为当前内容页TAG标记，1为获取全部TAG标记
     * {eyou:spcart loop='1' titlelen='20'}
     *  <li><a href='{$field:url}'>{$field:title}</a> </li> 
     * {/eyou:spcart}
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string|void
     */
    public function tagSpcart($tag, $content)
    {
        $id     = isset($tag['id']) ? $tag['id'] : 'field';
        $key    = !empty($tag['key']) ? $tag['key'] : 'i';
        $empty  = isset($tag['empty']) ? $tag['empty'] : '';
        $empty  = htmlspecialchars($empty);
        $mod    = !empty($tag['mod']) && is_numeric($tag['mod']) ? $tag['mod'] : '2';
        if (isset($tag['loop'])) $tag['row'] = $tag['loop'];
        $row    = !empty($tag['row']) ? intval($tag['row']) : 0;
        $limit  = !empty($tag['limit']) ? $tag['limit'] : '';
        if (empty($limit) && !empty($row)) {
            $limit = "0,{$row}";
        }

        $parseStr = '<?php ';
        $parseStr .= ' $tagSpcart = new \think\template\taglib\eyou\TagSpcart;';
        $parseStr .= ' $_result = $tagSpcart->getSpcart("'.$limit.'");';
        $parseStr .= '?>';

        $parseStr .= '<?php if(!empty($_result["list"]) || (($_result["list"] instanceof \think\Collection || $_result["list"] instanceof \think\Paginator ) && $_result["list"]->isEmpty())): ?>';
        $parseStr .= '<?php $'.$id.' = $_result; ?>';
        $parseStr .= '<?php $__SHOPCART_LIST__ = $_result["list"]; ?>';
        $parseStr .= $content;
        $parseStr .= '<?php endif; ?>';
        $parseStr .= '<?php $__SHOPCART_LIST__ = ""; ?>';
        $parseStr .= '<?php $'.$id.' = []; ?>'; // 清除变量值，只限于在标签内部使用
        
        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * sporder 标签解析 TAG调用
     * 格式：sort:排序方式 month，rand，week
     *       getall:获取类型 0 为当前内容页TAG标记，1为获取全部TAG标记
     * {eyou:sporder loop='1' titlelen='20'}
     *  <li><a href='{$field:url}'>{$field:title}</a> </li> 
     * {/eyou:sporder}
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string|void
     */
    public function tagSporder($tag, $content)
    {
        $name   = isset($tag['name']) ? $tag['name'] : '';
        $id     = isset($tag['id']) ? $tag['id'] : 'field';
        $key    = !empty($tag['key']) ? $tag['key'] : 'i';
        $empty  = isset($tag['empty']) ? $tag['empty'] : '';
        $empty  = htmlspecialchars($empty);
        $mod    = !empty($tag['mod']) && is_numeric($tag['mod']) ? $tag['mod'] : '2';
        if (isset($tag['loop'])) $tag['row'] = $tag['loop'];
        $row    = !empty($tag['row']) ? intval($tag['row']) : 0;
        $limit  = !empty($tag['limit']) ? $tag['limit'] : '';
        $order_id  = !empty($tag['order_id']) ? $tag['order_id'] : '';
        $order_id  = $this->varOrvalue($order_id);

        // 查询数据库获取的数据集
        $parseStr = '<?php ';
        $parseStr .= ' $tagSporder = new \think\template\taglib\eyou\TagSporder;';
        $parseStr .= ' $_result = $tagSporder->getSporder('.$order_id.');';
        $parseStr .= '?>';

        // 赋值数据
        $parseStr .= '<?php if(!empty($_result["OrderData"]) || (($_result["OrderData"] instanceof \think\Collection || $_result["OrderData"] instanceof \think\Paginator ) && $_result["OrderData"]->isEmpty())): ?>';
        $parseStr .= '<?php $'.$id.' = $_result["OrderData"]; ?>';
        $parseStr .= '<?php $__SHOPCART_LIST__ = $_result["DetailsData"]; ?>';
        $parseStr .= $content;
        $parseStr .= '<?php endif; ?>';
        $parseStr .= '<?php $__SHOPCART_LIST__ = ""; ?>';
        $parseStr .= '<?php $'.$id.' = []; ?>'; // 清除变量值，只限于在标签内部使用

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * spsubmitorder 标签解析 TAG调用
     * 格式：sort:排序方式 month，rand，week
     *       getall:获取类型 0 为当前内容页TAG标记，1为获取全部TAG标记
     * {eyou:spsubmitorder loop='1' titlelen='20'}
     *  <li><a href='{$field:url}'>{$field:title}</a> </li> 
     * {/eyou:spsubmitorder}
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string|void
     */
    public function tagSpsubmitorder($tag, $content)
    {
        $name   = isset($tag['name']) ? $tag['name'] : '';
        $id     = isset($tag['id']) ? $tag['id'] : 'field';
        $key    = !empty($tag['key']) ? $tag['key'] : 'i';
        $empty  = isset($tag['empty']) ? $tag['empty'] : '';
        $empty  = htmlspecialchars($empty);
        $mod    = !empty($tag['mod']) && is_numeric($tag['mod']) ? $tag['mod'] : '2';
        if (isset($tag['loop'])) $tag['row'] = $tag['loop'];
        $row    = !empty($tag['row']) ? intval($tag['row']) : 0;
        $limit  = !empty($tag['limit']) ? $tag['limit'] : '';

        // 查询数据库获取的数据集
        $parseStr = '<?php ';
        $parseStr .= ' $tagSpsubmitorder = new \think\template\taglib\eyou\TagSpsubmitorder;';
        $parseStr .= ' $_result = $tagSpsubmitorder->getSpsubmitorder();';
        $parseStr .= '?>';

        // 赋值数据
        $parseStr .= '<?php if(!empty($_result["data"]) || (($_result["data"] instanceof \think\Collection || $_result["data"] instanceof \think\Paginator ) && $_result["data"]->isEmpty())): ?>';
        $parseStr .= '<?php $'.$id.' = $_result["data"]; ?>';
        $parseStr .= '<?php $__SHOPCART_LIST__ = $_result["list"]; ?>';
        $parseStr .= $content;
        $parseStr .= '<?php endif; ?>';
        $parseStr .= '<?php $__SHOPCART_LIST__ = ""; ?>';
        $parseStr .= '<?php $'.$id.' = []; ?>'; // 清除变量值，只限于在标签内部使用

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * sporderlist 标签解析 TAG调用
     * 格式：sort:排序方式 month，rand，week
     *       getall:获取类型 0 为当前内容页TAG标记，1为获取全部TAG标记
     * {eyou:sporderlist loop='1' titlelen='20'}
     *  <li><a href='{$field:url}'>{$field:title}</a> </li> 
     * {/eyou:sporderlist}
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string|void
     */
    public function tagSporderlist($tag, $content)
    {
        $name   = isset($tag['name']) ? $tag['name'] : '';
        $id     = isset($tag['id']) ? $tag['id'] : 'field';
        $key    = !empty($tag['key']) ? $tag['key'] : 'i';
        $empty  = isset($tag['empty']) ? $tag['empty'] : '';
        $empty  = htmlspecialchars($empty);
        $mod    = !empty($tag['mod']) && is_numeric($tag['mod']) ? $tag['mod'] : '2';
        if (isset($tag['loop'])) $tag['row'] = $tag['loop'];
        $row    = !empty($tag['row']) ? intval($tag['row']) : 0;
        $limit  = !empty($tag['limit']) ? $tag['limit'] : '';
        $pagesize = !empty($tag['pagesize']) && is_numeric($tag['pagesize']) ? intval($tag['pagesize']) : 10;
        if (empty($limit) && !empty($row)) {
            $limit = "0,{$row}";
        }

        $parseStr = '<?php ';
        // 查询数据库获取的数据集
        $parseStr .= ' $tagSporderlist = new \think\template\taglib\eyou\TagSporderlist;';
        $parseStr .= ' $_result = $tagSporderlist->getSporderlist("'.$pagesize.'");';

        $parseStr .= ' if(is_array($_result) || $_result instanceof \think\Collection || $_result instanceof \think\Paginator): $' . $key . ' = 0; $e = 1;';
        $parseStr .= ' $__LIST__ = $_result["list"];';
        $parseStr .= ' $__PAGES_ORDER__ = $_result["pages"];';
        $parseStr .= 'if( count($__LIST__)==0 ) : echo htmlspecialchars_decode("' . $empty . '");';
        $parseStr .= 'else: ';
        // 遍及数据
        $parseStr .= 'foreach($__LIST__ as $key=>$' . $id . '): ';
        $parseStr .= ' $__SHOPCART_LIST__ = $' . $id . '["details"];';
        $parseStr .= ' $mod = ($e % ' . $mod . ' );';
        $parseStr .= ' $' . $key . '= intval($key) + 1;?>';
        $parseStr .= $content;
        $parseStr .= '<?php ++$e; ?>';
        $parseStr .= '<?php $__SHOPCART_LIST__ = ""; ?>';
        $parseStr .= '<?php endforeach; endif; else: echo htmlspecialchars_decode("' . $empty . '");endif; ?>';
        $parseStr .= '<?php $'.$id.' = []; ?>'; // 清除变量值，只限于在标签内部使用

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * sppageorder 标签解析
     * 在模板中获取列表的分页
     * 格式： {eyou:sppageorder listitem='info,index,end,pre,next,pageno' listsize='2'/}
     * @access public
     * @param array $tag 标签属性
     * @return string
     */
    public function tagSppageorder($tag)
    {
        $listitem  = !empty($tag['listitem']) ? $tag['listitem'] : '';
        $listsize  = !empty($tag['listsize']) ? intval($tag['listsize']) : '';
        $pre_text  = !empty($tag['pre_text']) ? $tag['pre_text'] : '';
        $next_text  = !empty($tag['next_text']) ? $tag['next_text'] : '';

        $parseStr  = ' <?php ';
        $parseStr .= ' $__PAGES_ORDER__ = isset($__PAGES_ORDER__) ? $__PAGES_ORDER__ : "";';
        $parseStr .= ' $tagPagelist = new \think\template\taglib\eyou\TagPagelist;';
        $parseStr .= ' $__VALUE__ = $tagPagelist->getPagelist($__PAGES_ORDER__, "'.$listitem.'", "'.$listsize.'", "'.$pre_text.'", "'.$next_text.'");';
        $parseStr .= ' echo $__VALUE__;';
        $parseStr .= ' ?>';

        return $parseStr;
    }

    /**
     * spordergoods 标签解析 TAG调用
     * 格式：sort:排序方式 month，rand，week
     *       getall:获取类型 0 为当前内容页TAG标记，1为获取全部TAG标记
     * {eyou:spordergoods loop='1' titlelen='20'}
     *  <li><a href='{$field:url}'>{$field:title}</a> </li> 
     * {/eyou:spordergoods}
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string|void
     */
    public function tagSpordergoods($tag, $content)
    {
        $name   = isset($tag['name']) ? $tag['name'] : '';
        $id     = isset($tag['id']) ? $tag['id'] : 'field';
        $key    = !empty($tag['key']) ? $tag['key'] : 'i';
        $empty  = isset($tag['empty']) ? $tag['empty'] : '';
        $empty  = htmlspecialchars($empty);
        $mod    = !empty($tag['mod']) && is_numeric($tag['mod']) ? $tag['mod'] : '2';
        if (isset($tag['loop'])) $tag['row'] = $tag['loop'];
        $row = !empty($tag['row']) ? intval($tag['row']) : 0;
        // $titlelen = !empty($tag['titlelen']) && is_numeric($tag['titlelen']) ? intval($tag['titlelen']) : 100;
        $offset = !empty($tag['offset']) && is_numeric($tag['offset']) ? intval($tag['offset']) : 0;
        $row = !empty($tag['row']) && is_numeric($tag['row']) ? intval($tag['row']) : 1000;
        if (!empty($tag['limit'])) {
            $limitArr = explode(',', $tag['limit']);
            $offset = !empty($limitArr[0]) ? intval($limitArr[0]) : 0;
            $row = !empty($limitArr[1]) ? intval($limitArr[1]) : 0;
        }

        $parseStr = '<?php ';

        if ($name) { // 从模板中传入数据集
            $symbol     = substr($name, 0, 1);
            if (':' == $symbol) {
                $name = $this->autoBuildVar($name);
                $parseStr .= '$_result=' . $name . ';';
                $name = '$_result';
            } else {
                $name = $this->autoBuildVar($name);
            }

            $parseStr .= 'if(is_array(' . $name . ') || ' . $name . ' instanceof \think\Collection || ' . $name . ' instanceof \think\Paginator): $' . $key . ' = 0; $e = 1;';
            // 设置了输出数组长度
            if (0 != $offset || 'null' != $row) {
                $parseStr .= '$__LIST__ = is_array(' . $name . ') ? array_slice(' . $name . ',' . $offset . ',' . $row . ', true) : ' . $name . '->slice(' . $offset . ',' . $row . ', true); ';
            } else {
                $parseStr .= ' $__LIST__ = ' . $name . ';';
            }

        } else { // 查询数据库获取的数据集
            $parseStr .= ' if(isset($__SHOPCART_LIST__) && !empty($__SHOPCART_LIST__)) : $_result = $__SHOPCART_LIST__; else : $_result = []; endif;';
            $parseStr .= ' if(is_array($_result) || $_result instanceof \think\Collection || $_result instanceof \think\Paginator): $' . $key . ' = 0; $e = 1;';
            // 设置了输出数组长度
            if (0 != $offset || 'null' != $row) {
                $parseStr .= '$__LIST__ = is_array($_result) ? array_slice($_result,' . $offset . ',' . $row . ', true) : $_result->slice(' . $offset . ',' . $row . ', true); ';
            } else {
                $parseStr .= ' $__LIST__ = $_result;';
            }
        }

        // 查询数据库获取的数据集
        $parseStr .= 'if( count($__LIST__)==0 ) : echo htmlspecialchars_decode("' . $empty . '");';
        $parseStr .= 'else: ';
        $parseStr .= 'foreach($__LIST__ as $key=>$' . $id . '): ';
        // $parseStr .= '$' . $id . '["title"] = text_msubstr($' . $id . '["title"], 0, '.$titlelen.', false);';
        $parseStr .= ' $__LIST__[$key] = $_result[$key] = $' . $id . ';';
        $parseStr .= '$' . $key . '= intval($key) + 1;?>';
        $parseStr .= '<?php $mod = ($' . $key . ' % ' . $mod . ' ); ?>';
        $parseStr .= $content;
        $parseStr .= '<?php ++$e; ?>';
        $parseStr .= '<?php endforeach; endif; else: echo htmlspecialchars_decode("' . $empty . '");endif; ?>';
        $parseStr .= '<?php $'.$id.' = []; ?>'; // 清除变量值，只限于在标签内部使用
        
        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * spaddress 标签解析 TAG调用
     * 格式：sort:排序方式 month，rand，week
     *       getall:获取类型 0 为当前内容页TAG标记，1为获取全部TAG标记
     * {eyou:spaddress loop='1' titlelen='20'}
     *  <li><a href='{$field:url}'>{$field:title}</a> </li> 
     * {/eyou:spaddress}
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string|void
     */
    public function tagSpaddress($tag, $content)
    {
        $name   = isset($tag['name']) ? $tag['name'] : '';
        $id     = isset($tag['id']) ? $tag['id'] : 'field';
        $key    = !empty($tag['key']) ? $tag['key'] : 'i';
        $empty  = isset($tag['empty']) ? $tag['empty'] : '';
        $empty  = htmlspecialchars($empty);
        $mod    = !empty($tag['mod']) && is_numeric($tag['mod']) ? $tag['mod'] : '2';
        if (isset($tag['loop'])) $tag['row'] = $tag['loop'];
        $row    = !empty($tag['row']) ? intval($tag['row']) : 0;
        $limit  = !empty($tag['limit']) ? $tag['limit'] : '';
        $opt   = !empty($tag['type']) ? $tag['type'] : 'data';

        $parseStr = '<?php ';
        // 查询数据库获取的数据集
        $parseStr .= ' $tagSpaddress = new \think\template\taglib\eyou\TagSpaddress;';
        $parseStr .= ' $_result = $tagSpaddress->getSpaddress("'.$opt.'");';
        $parseStr .= ' if(is_array($_result) || $_result instanceof \think\Collection || $_result instanceof \think\Paginator): $' . $key . ' = 0; $e = 1;';
        $parseStr .= ' $__LIST__ = $_result;';
        $parseStr .= 'if( count($__LIST__)==0 ) : echo htmlspecialchars_decode("' . $empty . '");';
        $parseStr .= 'else: ';
        // 遍及数据
        $parseStr .= 'foreach($__LIST__ as $key=>$' . $id . '): ';
        $parseStr .= '$' . $key . '= intval($key) + 1;?>';
        $parseStr .= '<?php $mod = ($' . $key . ' % ' . $mod . ' ); ?>';
        $parseStr .= $content;
        $parseStr .= '<?php ++$e; ?>';
        $parseStr .= '<?php endforeach; endif; else: echo htmlspecialchars_decode("' . $empty . '");endif; ?>';
        $parseStr .= '<?php $'.$id.' = []; ?>'; // 清除变量值，只限于在标签内部使用

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }


    /**
     * spsearch 订单搜索标签解析 TAG调用
     * {eyou:spsearch id='field'}
     * {$field.searchurl}
     * {/eyou:spsearch}
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string|void
     */
    public function tagSpsearch($tag, $content)
    {
        $id     = isset($tag['id']) ? $tag['id'] : 'field';
        $key    = !empty($tag['key']) ? $tag['key'] : 'i';
        $mod    = !empty($tag['mod']) && is_numeric($tag['mod']) ? $tag['mod'] : '2';
        $empty  = isset($tag['empty']) ? $tag['empty'] : '';
        $empty  = htmlspecialchars($empty);

        $parseStr = '<?php ';

        // 查询数据库获取的数据集
        $parseStr .= ' $tagSpsearch = new \think\template\taglib\eyou\TagSpsearch;';
        $parseStr .= ' $_result = $tagSpsearch->getSpsearch();';
        $parseStr .= ' if(is_array($_result) || $_result instanceof \think\Collection || $_result instanceof \think\Paginator): $' . $key . ' = 0; $e = 1;';
        $parseStr .= ' $__LIST__ = $_result;';

        $parseStr .= 'if( count($__LIST__)==0 ) : echo htmlspecialchars_decode("' . $empty . '");';
        $parseStr .= 'else: ';
        $parseStr .= 'foreach($__LIST__ as $key=>$' . $id . '): ';
        $parseStr .= '$' . $key . '= intval($key) + 1;?>';
        $parseStr .= '<?php $mod = ($' . $key . ' % ' . $mod . ' ); ?>';
        $parseStr .= $content;
        $parseStr .= '<?php ++$e; ?>';
        $parseStr .= '<?php endforeach;';
        $parseStr .= 'endif; else: echo htmlspecialchars_decode("' . $empty . '");endif; ?>';
        $parseStr .= '<?php $'.$id.' = []; ?>'; // 清除变量值，只限于在标签内部使用

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * sppayapilist 支付API列表
     * {eyou:sppayapilist id='field'}
     * {$field.pay_name}
     * {/eyou:sppayapilist}
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string|void
     */
    public function tagSppayapilist($tag, $content)
    {
        $id     = isset($tag['id']) ? $tag['id'] : 'field';
        $key    = !empty($tag['key']) ? $tag['key'] : 'i';
        $mod    = !empty($tag['mod']) && is_numeric($tag['mod']) ? $tag['mod'] : '2';
        $empty  = isset($tag['empty']) ? $tag['empty'] : '';
        $empty  = htmlspecialchars($empty);

        $parseStr = '<?php ';

        // 查询数据库获取的数据集
        $parseStr .= ' $tagSppayapilist = new \think\template\taglib\eyou\TagSppayapilist;';
        $parseStr .= ' $_result = $tagSppayapilist->getSppayapilist();';
        $parseStr .= ' if(is_array($_result) || $_result instanceof \think\Collection || $_result instanceof \think\Paginator): $' . $key . ' = 0; $e = 1;';
        $parseStr .= ' $__LIST__ = $_result;';

        $parseStr .= 'if( count($__LIST__)==0 ) : echo htmlspecialchars_decode("' . $empty . '");';
        $parseStr .= 'else: ';
        $parseStr .= 'foreach($__LIST__ as $key=>$' . $id . '): ';
        $parseStr .= '$' . $key . '= intval($key) + 1;?>';
        $parseStr .= '<?php $mod = ($' . $key . ' % ' . $mod . ' ); ?>';
        $parseStr .= $content;
        $parseStr .= '<?php ++$e; ?>';
        $parseStr .= '<?php endforeach;';
        $parseStr .= 'endif; else: echo htmlspecialchars_decode("' . $empty . '");endif; ?>';
        $parseStr .= '<?php $'.$id.' = []; ?>'; // 清除变量值，只限于在标签内部使用

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * screening 筛选搜索标签解析 TAG调用
     * {eyou:screening id='field'}
     * {$field.searchurl}
     * {/eyou:screening}
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string|void
     */
    public function tagScreening($tag, $content)
    {
        $id     = isset($tag['id']) ? $tag['id'] : 'field';
        $key    = !empty($tag['key']) ? $tag['key'] : 'i';
        $mod    = !empty($tag['mod']) && is_numeric($tag['mod']) ? $tag['mod'] : '2';
        $empty  = isset($tag['empty']) ? $tag['empty'] : '';
        $empty  = htmlspecialchars($empty);
        $anchor    = !empty($tag['anchor']) ? $tag['anchor'] : '';

        // 自定义class
        $currentclass = !empty($tag['currentclass']) ? $tag['currentclass'] : '';
        if (!isset($tag['currentclass'])) { // 加强兼容性
            $currentclass = !empty($tag['currentstyle']) ? $tag['currentstyle'] : '';
        }

        // 自定义字段名
        $addfields = isset($tag['addfields']) ? $tag['addfields'] : '';
        $addfields = $this->varOrvalue($addfields);

        // 自定义字段ID
        $addfieldids = isset($tag['addfieldids']) ? $tag['addfieldids'] : '';
        $addfieldids = $this->varOrvalue($addfieldids);

        // 全部字样
        $alltxt = isset($tag['alltxt']) ? $tag['alltxt'] : '';
        $alltxt = $this->varOrvalue($alltxt);

        // 指定栏目ID，若有数据则优先展示指定栏目内容
        $typeid = isset($tag['typeid']) ? $tag['typeid'] : '';
        $typeid = $this->varOrvalue($typeid);

        $parseStr = '<?php ';

        // 查询数据库获取的数据集
        $parseStr .= ' $tagScreening = new \think\template\taglib\eyou\TagScreening;';
        $parseStr .= ' $_result = $tagScreening->getScreening("'.$currentclass.'", '.$addfields.', '.$addfieldids.', '.$alltxt.', '.$typeid.', "'.$anchor.'");';
        $parseStr .= '?>';

        $parseStr .= '<?php if(!empty($_result["list"]) || (($_result["list"] instanceof \think\Collection || $_result["list"] instanceof \think\Paginator ) && $_result["list"]->isEmpty())): ?>';
        $parseStr .= '<?php $'.$id.' = $_result; ?>';
        $parseStr .= $content;
        $parseStr .= '<?php endif; ?>';
        $parseStr .= '<?php $'.$id.' = []; ?>'; // 清除变量值，只限于在标签内部使用

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * spstatus 
     * 格式：
     * {eyou:spstatus }
     *  <em>{$field3.PendingPayment}</em> 
     * {/eyou:spstatus}
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     */
    public function tagSpstatus($tag, $content)
    {
        $id     = isset($tag['id']) ? $tag['id'] : 'field';
        $key    = !empty($tag['key']) ? $tag['key'] : 'i';
        $empty  = isset($tag['empty']) ? $tag['empty'] : '';
        $empty  = htmlspecialchars($empty);
        $mod    = !empty($tag['mod']) && is_numeric($tag['mod']) ? $tag['mod'] : '2';
        if (isset($tag['loop'])) $tag['row'] = $tag['loop'];
        $row    = !empty($tag['row']) ? intval($tag['row']) : 0;

        $parseStr = '<?php ';
        $parseStr .= ' $tagSporderlist = new \think\template\taglib\eyou\TagSporderlist;';
        $parseStr .= ' $_result = $tagSporderlist->getSpstatus();';
        $parseStr .= '?>';

        $parseStr .= '<?php if(!empty($_result) || (($_result instanceof \think\Collection || $_result instanceof \think\Paginator ) && $_result->isEmpty())): ?>';
        $parseStr .= '<?php $'.$id.' = $_result; ?>';
        $parseStr .= $content;
        $parseStr .= '<?php endif; ?>';
        $parseStr .= '<?php $'.$id.' = []; ?>'; // 清除变量值，只限于在标签内部使用
        
        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * memberlist 调用会员列表
     * {eyou:memberlist loop='1' titlelen='20'}
     *  <li><a href='{$field:userid}'>{$field:username}</a> </li> 
     * {/eyou:memberlist}
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string|void
     */
    public function tagMemberlist($tag, $content)
    {
        $id     = isset($tag['id']) ? $tag['id'] : 'field';
        $key    = !empty($tag['key']) ? $tag['key'] : 'i';
        $empty  = isset($tag['empty']) ? $tag['empty'] : '';
        $empty  = htmlspecialchars($empty);
        $mod    = !empty($tag['mod']) && is_numeric($tag['mod']) ? $tag['mod'] : '2';
        $titlelen = !empty($tag['titlelen']) && is_numeric($tag['titlelen']) ? intval($tag['titlelen']) : 100;
        if (isset($tag['loop'])) $tag['row'] = $tag['loop'];
        $row = !empty($tag['row']) ? intval($tag['row']) : 20;
        $limit   = !empty($tag['limit']) ? $tag['limit'] : '';
        if (empty($limit) && !empty($row)) {
            $limit = "0,{$row}";
        }
        $orderby   = !empty($tag['orderby']) ? $tag['orderby'] : 'logintime';
        $ordermode    = !empty($tag['ordermode']) ? $tag['ordermode'] : (!empty($tag['orderway']) ? $tag['orderway'] : 'desc');
        $js    = !empty($tag['js']) ? $tag['js'] : '';

        $parseStr = '<?php ';
        $parseStr .= ' $attarray = "'.base64_encode(json_encode($tag)).'";';
        $parseStr .= ' $tagMemberlist = new \think\template\taglib\eyou\TagMemberlist;';
        $parseStr .= ' $__LIST__ = $tagMemberlist->getMemberlist("'.$limit.'", "'.$orderby.'", "'.$ordermode.'", "'.$js.'", $attarray);';
        $parseStr .= '?>';

        if (empty($js)) {
            $parseStr .= '<?php if(!empty($__LIST__) || (($__LIST__ instanceof \think\Collection || $__LIST__ instanceof \think\Paginator ) && $__LIST__->isEmpty())): ?>';
            $parseStr .= '<?php $'.$id.' = $__LIST__; ?>';
            $parseStr .= $content;
            $parseStr .= '<?php endif; ?>';
        }
        else 
        {
            $parseStr .= '<?php ';
            $parseStr .= ' if(is_array($__LIST__) || $__LIST__ instanceof \think\Collection || $__LIST__ instanceof \think\Paginator): $' . $key . ' = 0; $e = 1;';

            $parseStr .= 'if( count($__LIST__)==0 ) : echo htmlspecialchars_decode("' . $empty . '");';
            $parseStr .= 'else: ';
            $parseStr .= 'foreach($__LIST__ as $key=>$' . $id . '): ';
            $parseStr .= '$' . $id . '["username"] = text_msubstr($' . $id . '["username"], 0, '.$titlelen.', false);';
            $parseStr .= ' $__LIST__[$key] = $' . $id . ';';
            $parseStr .= '$' . $key . '= intval($key) + 1;?>';
            $parseStr .= '<?php $mod = ($' . $key . ' % ' . $mod . ' ); ?>';
            $parseStr .= $content;
            $parseStr .= '<?php ++$e; ?>';
            $parseStr .= '<?php endforeach; endif; else: echo htmlspecialchars_decode("' . $empty . '");endif; ?>';
        }
        $parseStr .= '<?php $'.$id.' = []; ?>'; // 清除变量值，只限于在标签内部使用

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * memberinfos标签解析 指定播放视频
     * 格式：
     * {eyou:memberinfos mid=''}
     *  {$field:nickname}
     * {/eyou:memberinfos}
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string|void
     */
    public function tagMemberinfos($tag, $content)
    {
        if (!empty($tag['users_id'])) {
            $tag['mid'] = $tag['users_id'];
        }
        $users_id    = !empty($tag['mid']) ? $tag['mid'] : '';
        $users_id    = $this->varOrvalue($users_id);
        $id     = isset($tag['id']) ? $tag['id'] : 'field';
        $empty  = isset($tag['empty']) ? $tag['empty'] : '';
        $empty  = htmlspecialchars($empty);
        $addfields     = isset($tag['addfields']) ? $tag['addfields'] : '';
        $addfields  = $this->varOrvalue($addfields);

        $parseStr = '<?php ';

        /*aid的优先级别从高到低：标签属性值 -> 外层标签list/arclist属性值*/
        $parseStr .= ' if(empty($aid)) : $aid_tmp = 0; endif; ';
        $parseStr .= ' $taid = 0; ';
        $parseStr .= ' if(!empty($aid_tmp)) : $taid = $aid_tmp; elseif(!empty($aid)) : $taid = $aid; endif;';

        $parseStr .= ' if(empty($users_id)) : $users_id_tmp = '.$users_id.'; endif; ';
        $parseStr .= ' $tusers_id = 0; ';
        $parseStr .= ' if(!empty($users_id_tmp)) : $tusers_id = $users_id_tmp; elseif(!empty($users_id)) : $tusers_id = $users_id; endif;';
        /*--end*/

        $parseStr .= ' $tagMemberinfos = new \think\template\taglib\eyou\TagMemberinfos;';
        $parseStr .= ' $_result = $tagMemberinfos->getMemberinfos($taid, $tusers_id, '.$addfields.');';
        $parseStr .= ' ?>';

        $parseStr .= '<?php if(is_array($_result) || $_result instanceof \think\Collection || $_result instanceof \think\Paginator): ';
        $parseStr .= ' $__LIST__ = $_result;';
        $parseStr .= 'if( count($__LIST__)==0 ) : echo htmlspecialchars_decode("' . $empty . '");';
        $parseStr .= 'else: ';
        $parseStr .= '$'.$id.' = $__LIST__;';
        $parseStr .= '?>';
        $parseStr .= $content;
        $parseStr .= '<?php endif; else: echo htmlspecialchars_decode("' . $empty . '");endif; ?>';
        $parseStr .= '<?php unset($aid); ?>';
        $parseStr .= '<?php unset($users_id); ?>';
        $parseStr .= '<?php $'.$id.' = []; ?>'; // 清除变量值，只限于在标签内部使用

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * diyurl 标签解析
     * 内置URL
     * 格式： {eyou:diyurl type='tags' /}
     * @access public
     * @param array $tag 标签属性
     * @return string
     */
    public function tagDiyurl($tag)
    {
        $type = isset($tag['type']) ? $tag['type'] : '';
        $type  = $this->varOrvalue($type);
        $link    = isset($tag['link']) ? $tag['link'] : '';
        $vars   = isset($tag['vars']) ? $tag['vars'] : '';
        $suffix = isset($tag['suffix']) ? $tag['suffix'] : '';
        $domain = isset($tag['domain']) ? $tag['domain'] : '';
        $seo_pseudo = isset($tag['seo_pseudo']) ? $tag['seo_pseudo'] : '';
        $seo_pseudo_format = isset($tag['seo_pseudo_format']) ? $tag['seo_pseudo_format'] : '';
        $seo_inlet = isset($tag['seo_inlet']) ? $tag['seo_inlet'] : '';
        $class = isset($tag['class']) ? $tag['class'] : 'ey_active';

        $parseStr = '<?php ';

        // 查询数据库获取的数据集
        $parseStr .= ' $tagDiyurl = new \think\template\taglib\eyou\TagDiyurl;';
        $parseStr .= ' $__VALUE__ = $tagDiyurl->getDiyurl('.$type.', "'.$link.'", "'.$vars.'", "'.$suffix.'", "'.$domain.'", "'.$seo_pseudo.'", "'.$seo_pseudo_format.'", "'.$seo_inlet.'", "'.$class.'");';
        $parseStr .= ' echo $__VALUE__;';
        $parseStr .= ' ?>';

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * likearticle标签解析 获取指定相关文档列表
     * 格式：
     * {eyou:likearticle mytypeid='0' limit='0,10' titlelen='30' bodylen='160' id='field'}
     * <a href="{$field:arcurl}">{$field:title}</a>
     * {/eyou:likearticle}
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string|void
     */
    public function tagLikearticle($tag, $content)
    {
        $modelid   = isset($tag['modelid']) ? intval($tag['modelid']) : (isset($tag['channelid']) ? intval($tag['channelid']) : '');
        $modelid = $this->varOrvalue($modelid);

        $mytypeid = !empty($tag['mytypeid']) ? $tag['mytypeid'] : '';
        $mytypeid = $this->varOrvalue($mytypeid);

        if (empty($tag['mytypeid'])) {
            $typeid = !empty($tag['typeid']) ? $tag['typeid'] : '';
            $mytypeid = $this->varOrvalue($typeid);
        }

        $name    = !empty($tag['name']) ? $tag['name'] : '';
        $id      = isset($tag['id']) ? $tag['id'] : 'field';
        $key     = !empty($tag['key']) ? $tag['key'] : 'i';
        $empty   = isset($tag['empty']) ? $tag['empty'] : '';
        $empty   = htmlspecialchars($empty);
        $mod     = !empty($tag['mod']) && is_numeric($tag['mod']) ? $tag['mod'] : '2';
        $byabs = !empty($tag['byabs']) ? $tag['byabs'] : 0;
        $titlelen = !empty($tag['titlelen']) && is_numeric($tag['titlelen']) ? intval($tag['titlelen']) : 100;
        $bodylen  = !empty($tag['bodylen']) && is_numeric($tag['bodylen']) ? intval($tag['bodylen']) : 160;
        if (isset($tag['infolen'])) {
            $bodylen  = !empty($tag['infolen']) && is_numeric($tag['infolen']) ? intval($tag['infolen']) : 160;
        }
        $thumb    = !empty($tag['thumb']) ? $tag['thumb'] : 'on';
        if (isset($tag['loop'])) $tag['row'] = $tag['loop'];
        $row      = !empty($tag['row']) && is_numeric($tag['row']) ? intval($tag['row']) : 12;
        $limit    = !empty($tag['limit']) ? $tag['limit'] : '';
        if (empty($limit) && !empty($row)) {
            $limit = "0,{$row}";
        }
        $parseStr = '<?php ';
        if ($name) { // 从模板中传入数据集
            $symbol = substr($name, 0, 1);
            if (':' == $symbol) {
                $name     = $this->autoBuildVar($name);
                $parseStr .= '$_result=' . $name . ';';
                $name     = '$_result';
            } else {
                $name = $this->autoBuildVar($name);
            }

            $parseStr .= 'if(is_array(' . $name . ') || ' . $name . ' instanceof \think\Collection : $' . $key . ' = 0; $e = 1;';
            // 设置了输出数组长度
            if ( 'null' != $row) {
                $parseStr .= '$__LIST__ = is_array(' . $name . ') ? array_slice(' . $name . ',' . $row . ', true) : ' . $name . '->slice(' . $row . ', true); ';
            } else {
                $parseStr .= ' $__LIST__ = ' . $name . ';';
            }

        } else { // 查询数据库获取的数据集
            $parseStr .= ' $tagLikearticle = new \think\template\taglib\eyou\TagLikearticle;';
            $parseStr .= ' $_result = $tagLikearticle->getLikearticle('.$modelid.','.$mytypeid.', "'.$limit.'", "'.$byabs.'", "'.$thumb.'");';
            $parseStr .= 'if(is_array($_result) || $_result instanceof \think\Collection || $_result instanceof \think\Paginator): $' . $key . ' = 0; $e = 1;';
            // 设置了输出数组长度
            $parseStr .= ' $__LIST__ = $_result;';
        }
        $parseStr .= 'if( count($__LIST__)==0 ) : echo htmlspecialchars_decode("' . $empty . '");';
        $parseStr .= 'else: ';
        $parseStr .= 'foreach($__LIST__ as $key=>$' . $id . '): ';
        $parseStr .= '$aid = $' . $id . '["aid"];';
        $parseStr .= '$' . $id . '["title"] = text_msubstr($' . $id . '["title"], 0, ' . $titlelen . ', false);';
        $parseStr .= '$' . $id . '["seo_description"] = text_msubstr($' . $id . '["seo_description"], 0, ' . $bodylen . ', true);';

        $parseStr .= '$' . $key . '= intval($key) + 1;?>';
        $parseStr .= '<?php $mod = ($' . $key . ' % ' . $mod . ' ); ?>';
        $parseStr .= $content;
        $parseStr .= '<?php ++$e; ?>';
        $parseStr .= '<?php $aid = 0; ?>';
        $parseStr .= '<?php endforeach; endif; else: echo htmlspecialchars_decode("' . $empty . '");endif; ?>';
        $parseStr .= '<?php $' . $id . ' = []; ?>'; // 清除变量值，只限于在标签内部使用
        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * hotwords 获取网站搜索的热门关键字
     * {eyou:hotwords num='6' subday='365' maxlength='20'}
     *  <li><a href='{$field.url}'>{$field.word}</a> </li> 
     * {/eyou:hotwords}
     * @access public
     * @param array $hotwords 标签属性
     * @param string $content 标签内容
     * @return string|void
     */
    public function tagHotwords($tag, $content)
    {
        $num    = !empty($tag['num']) && is_numeric($tag['num']) ? $tag['num'] : '6';
        $subday    = !empty($tag['subday']) && is_numeric($tag['subday']) ? $tag['subday'] : '365';
        $maxlength    = !empty($tag['maxlength']) && is_numeric($tag['maxlength']) ? $tag['maxlength'] : '20';
        $id     = isset($tag['id']) ? $tag['id'] : 'field';
        $key    = !empty($tag['key']) ? $tag['key'] : 'i';
        $empty  = isset($tag['empty']) ? $tag['empty'] : '';
        $empty  = htmlspecialchars($empty);
        $mod    = !empty($tag['mod']) && is_numeric($tag['mod']) ? $tag['mod'] : '2';
        $orderby    = isset($tag['orderby']) ? $tag['orderby'] : '';
        $ordermode = 'desc';
        if (!empty($tag['ordermode'])) {
            $ordermode = $tag['ordermode'];
        } else {
            if (!empty($tag['orderWay'])) {
                $ordermode = $tag['orderWay'];
            } else {
                $ordermode = !empty($tag['orderway']) ? $tag['orderway'] : $ordermode;
            }
        }
        $screen    = isset($tag['screen']) ? $tag['screen'] : '';

        $parseStr = '<?php ';

        // 查询数据库获取的数据集
        $parseStr .= ' $tagHotwords = new \think\template\taglib\eyou\TagHotwords;';
        $parseStr .= ' $_result = $tagHotwords->getHotwords("'.$num.'", "'.$subday.'", "'.$maxlength.'", "'.$orderby.'", "'.$ordermode.'", "'.$screen.'");';
        $parseStr .= ' if(is_array($_result) || $_result instanceof \think\Collection || $_result instanceof \think\Paginator): $' . $key . ' = 0; $e = 1;';
        // 设置了输出数组长度
        if ('null' != $num) {
            $parseStr .= '$__LIST__ = is_array($_result) ? array_slice($_result,0, '.$num.', true) : $_result->slice(0, '.$num.', true); ';
        } else {
            $parseStr .= ' $__LIST__ = $_result;';
        }

        $parseStr .= 'if( count($__LIST__)==0 ) : echo htmlspecialchars_decode("' . $empty . '");';
        $parseStr .= 'else: ';
        $parseStr .= 'foreach($__LIST__ as $key=>$' . $id . '): ';
        $parseStr .= '$' . $id . '["word"] = text_msubstr($' . $id . '["word"], 0, '.$maxlength.', false);';
        $parseStr .= '$' . $key . '= intval($key) + 1;?>';
        $parseStr .= '<?php $mod = ($' . $key . ' % ' . $mod . ' ); ?>';
        $parseStr .= $content;
        $parseStr .= '<?php ++$e; ?>';
        $parseStr .= '<?php endforeach; endif; else: echo htmlspecialchars_decode("' . $empty . '");endif; ?>';
        $parseStr .= '<?php $'.$id.' = []; ?>'; // 清除变量值，只限于在标签内部使用

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * weapptaglib标签解析 循环输出数据集
     * 格式：
     * {eyou:weapptaglib name="userList" id="user" empty=""}
     * {user.username}
     * {user.email}
     * {/eyou:weapptaglib}
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string|void
     */
    public function tagWeapptaglib($tag, $content)
    {
        $name   = !empty($tag['name']) ? ":weapptaglib".$tag['name'] : $tag['name'];
        $id  = isset($tag['id']) ? $tag['id'] : 'field';
        $empty  = isset($tag['empty']) ? $tag['empty'] : '';
        $empty  = htmlspecialchars($empty);
        $key    = !empty($tag['key']) ? $tag['key'] : 'i';
        $mod    = !empty($tag['mod']) && is_numeric($tag['mod']) ? $tag['mod'] : '2';
        $offset = !empty($tag['offset']) && is_numeric($tag['offset']) ? intval($tag['offset']) : 0;
        $length = !empty($tag['length']) && is_numeric($tag['length']) ? intval($tag['length']) : 'null';
        if (isset($tag['loop'])) $tag['row'] = $tag['loop'];
        if (!empty($tag['row'])) {
            $length = !empty($tag['row']) && is_numeric($tag['row']) ? intval($tag['row']) : 'null';
        }
        if (!empty($tag['limit'])) {
            $limitArr = explode(',', $tag['limit']);
            $offset = !empty($limitArr[0]) ? intval($limitArr[0]) : 0;
            $length = !empty($limitArr[1]) ? intval($limitArr[1]) : 'null';
        }
        // 允许使用函数设定数据集 <weapptaglib name=":fun('arg')" id="vo">{$vo.name}</volist>
        $parseStr = '<?php ';
        $flag     = substr($name, 0, 1);
        if (':' == $flag) {
            $name = $this->autoBuildVar($name);
            $parseStr .= '$_result=' . $name . ';';
            $name = '$_result';
        } else {
            $name = $this->autoBuildVar($name);
        }

        $parseStr .= 'if(is_array(' . $name . ') || ' . $name . ' instanceof \think\Collection || ' . $name . ' instanceof \think\Paginator): $' . $key . ' = 0; $e = 1;';
        // 设置了输出数组长度
        if (0 != $offset || 'null' != $length) {
            $parseStr .= '$__LIST__ = is_array(' . $name . ') ? array_slice(' . $name . ',' . $offset . ',' . $length . ', true) : ' . $name . '->slice(' . $offset . ',' . $length . ', true); ';
        } else {
            $parseStr .= ' $__LIST__ = ' . $name . ';';
        }
        $parseStr .= 'if( count($__LIST__)==0 ) : echo htmlspecialchars_decode("' . $empty . '");';
        $parseStr .= 'else: ';
        $parseStr .= 'foreach($__LIST__ as $key=>$' . $id . '): ';
        $parseStr .= '$' . $key . '= intval($key) + 1;?>';
        $parseStr .= '<?php $mod = ($' . $key . ' % ' . $mod . ' ); ?>';
        $parseStr .= $content;
        $parseStr .= '<?php ++$e; ?>';
        $parseStr .= '<?php endforeach; endif; else: echo htmlspecialchars_decode("' . $empty . '");endif; ?>';
        $parseStr .= '<?php $'.$id.' = []; ?>'; // 清除变量值，只限于在标签内部使用

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * specnodelist 标签解析 获取专题文章节点列表
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string|void
     */

    public function tagSpecnodelist($tag, $content)
    {
        $id     = isset($tag['id']) ? $tag['id'] : 'field';
        $key    = !empty($tag['key']) ? $tag['key'] : 'i';
        $mod    = !empty($tag['mod']) && is_numeric($tag['mod']) ? $tag['mod'] : '2';
        $empty  = isset($tag['empty']) ? $tag['empty'] : '';
        $empty  = htmlspecialchars($empty);

        $parseStr = '<?php ';
        // 查询数据库获取的数据集
        $parseStr .= ' $tagSpecnodelist = new \think\template\taglib\eyou\TagSpecnodelist;';
        $parseStr .= ' $_result = $tagSpecnodelist->getSpecnodelist();';
        $parseStr .= ' if(is_array($_result) || $_result instanceof \think\Collection || $_result instanceof \think\Paginator): $' . $key . ' = 0; $e = 1;';
        $parseStr .= ' $__LIST__ = $_result;';
        $parseStr .= 'if( count($__LIST__)==0 ) : echo htmlspecialchars_decode("' . $empty . '");';
        $parseStr .= 'else: ';
        $parseStr .= 'foreach($__LIST__ as $key=>$' . $id . '): ';
        $parseStr .= '$' . $key . '= intval($key) + 1;?>';
        $parseStr .= '<?php $mod = ($' . $key . ' % ' . $mod . ' ); ?>';
        $parseStr .= $content;
        $parseStr .= '<?php ++$e; ?>';
        $parseStr .= '<?php endforeach;';
        $parseStr .= 'endif; else: echo htmlspecialchars_decode("' . $empty . '");endif; ?>';
        $parseStr .= '<?php $'.$id.' = []; ?>'; // 清除变量值，只限于在标签内部使用

        if (!empty($parseStr)) return $parseStr;
        return false;
    }

    /**
     * specnode标签解析 获取指定专题节点文档列表
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string|void
     */
    public function tagSpecnode($tag, $content)
    {
        $aid   = !empty($tag['aid']) ? $tag['aid'] : '0';
        $aid  = $this->varOrvalue($aid);

        $title   = !empty($tag['title']) ? $tag['title'] : '';
        $title  = $this->varOrvalue($title);

        $code    = !empty($tag['code']) ? $tag['code'] : '';
        $code  = $this->varOrvalue($code);

        $typeid     = !empty($tag['typeid']) ? $tag['typeid'] : '';
        $typeid  = $this->varOrvalue($typeid);

        $keyword     = !empty($tag['keyword']) ? $tag['keyword'] : '';
        $keyword  = $this->varOrvalue($keyword);

        $aidlist    = !empty($tag['aidlist']) ? $tag['aidlist'] : '';
        $isauto    = !empty($tag['isauto']) && is_numeric($tag['isauto']) ? $tag['isauto'] : '0';
        $id     = isset($tag['id']) ? $tag['id'] : 'field';
        $key    = !empty($tag['key']) ? $tag['key'] : 'i';
        $empty  = isset($tag['empty']) ? $tag['empty'] : '';
        $empty  = htmlspecialchars($empty);
        $mod    = !empty($tag['mod']) && is_numeric($tag['mod']) ? $tag['mod'] : '2';
        $thumb   = !empty($tag['thumb']) ? $tag['thumb'] : 'on';
        $titlelen = !empty($tag['titlelen']) && is_numeric($tag['titlelen']) ? intval($tag['titlelen']) : 100;
        $bodylen = !empty($tag['bodylen']) && is_numeric($tag['bodylen']) ? intval($tag['bodylen']) : 160;
        if (isset($tag['infolen'])) {
            $bodylen = !empty($tag['infolen']) && is_numeric($tag['infolen']) ? intval($tag['infolen']) : 160;
        }

        $limit = !empty($tag['limit']) ? trim($tag['limit']) : 10;
        if (empty($tag['limit'])) {
            if (isset($tag['loop'])) $tag['row'] = $tag['loop'];
            if (isset($tag['row'])) {
                $tag['limit'] = "0,{$tag['row']}";
            }
            $limit = !empty($tag['row']) ? intval($tag['row']) : $limit;
        }

        $parseStr = '<?php ';
        // 声明变量
        $parseStr .= ' if(!isset($aid) || empty($aid)) : $aid = '.$aid.'; endif;';
        $parseStr .= ' $tag = '.var_export($tag,true).';';
        /*--end*/

        // 查询数据库获取的数据集
        $parseStr .= ' $tagSpecnode = new \think\template\taglib\eyou\TagSpecnode;';
        $parseStr .= ' $_result_tmp = $tagSpecnode->getSpecnode($tag, $aid,'.$title.','.$code.','.$typeid.',"'.$aidlist.'","'.$isauto.'",'.$keyword.',"'.$titlelen.'","'.$bodylen.'","'.$limit.'","'.$thumb.'");';
        $parseStr .= ' if(is_array($_result_tmp) || $_result_tmp instanceof \think\Collection || $_result_tmp instanceof \think\Paginator): $' . $key . ' = 0; $e = 1;';
        $parseStr .= ' $__SPECN_NODE_LIST__ = $_result_tmp["list"];';
        $parseStr .= ' $__SPECN_NODE_PAGES__ = $_result_tmp["pages"];';

        $parseStr .= 'if( count($__SPECN_NODE_LIST__)==0 ) : echo htmlspecialchars_decode("' . $empty . '");';
        $parseStr .= 'else: ';
        $parseStr .= 'foreach($__SPECN_NODE_LIST__ as $key=>$' . $id . '): ';
        $parseStr .= '$' . $key . '= intval($key) + 1;?>';
        $parseStr .= '<?php $mod = ($' . $key . ' % ' . $mod . ' ); ?>';
        $parseStr .= $content;
        $parseStr .= '<?php ++$e; ?>';
        $parseStr .= '<?php endforeach; endif; else: echo htmlspecialchars_decode("' . $empty . '");endif; ?>';
        $parseStr .= '<?php unset($aid); ?>';
        $parseStr .= '<?php $'.$id.' = []; ?>'; // 清除变量值，只限于在标签内部使用

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * pagespecnode 标签解析
     * 在模板中获取专题节点的分页
     * 格式： {eyou:pagespecnode listitem='info,index,end,pre,next,pageno' listsize='2'/}
     * @access public
     * @param array $tag 标签属性
     * @return string
     */
    public function tagPagespecnode($tag)
    {
        $listitem = !empty($tag['listitem']) ? $tag['listitem'] : '';
        $listsize   = !empty($tag['listsize']) ? intval($tag['listsize']) : '';

        $parseStr = ' <?php ';
        $parseStr .= ' $__SPECN_NODE_PAGES__ = isset($__SPECN_NODE_PAGES__) ? $__SPECN_NODE_PAGES__ : "";';
        $parseStr .= ' $tagPagespecnode = new \think\template\taglib\eyou\TagPagespecnode;';
        $parseStr .= ' $__VALUE__ = $tagPagespecnode->getPagespecnode($__SPECN_NODE_PAGES__, "'.$listitem.'", "'.$listsize.'");';
        $parseStr .= ' echo $__VALUE__;';
        $parseStr .= ' ?>';

        return $parseStr;
    }

    /**
     * collect
     * 文档收藏
     * 格式： {eyou:collect aid='' /}{/eyou:collect}
     * class属性 默认关闭off
     * @access public
     * @param array $tag 标签属性
     * @return string
     */
    public function tagCollect($tag,$content)
    {
        $aid_tmp  = isset($tag['aid']) ? $tag['aid'] : 0;
        $aid  = $this->varOrvalue($aid_tmp);

        $collect = isset($tag['collect']) ? $tag['collect'] : '已收藏';
        $collect  = $this->varOrvalue($collect);

        $cancel = isset($tag['cancel']) ? $tag['cancel'] : '加入收藏';
        $cancel  = $this->varOrvalue($cancel);

        $id     = isset($tag['id']) ? $tag['id'] : 'field';
        $class     = isset($tag['class']) ? $tag['class'] : 'off';

        $parseStr = '<?php ';

        // 声明变量
        if (!empty($aid_tmp)) {
            $parseStr .= ' $aid = '.$aid.';';
        } else {
            $parseStr .= ' if(!isset($aid) || empty($aid)) : $aid = '.$aid.'; endif;';
        }
        $parseStr .= ' $tagCollect = new \think\template\taglib\eyou\TagCollect;';
        $parseStr .= ' $_result = $tagCollect->getCollect($aid, '.$collect.', '.$cancel.', "'.$class.'");';
        $parseStr .= ' ?>';

        $parseStr .= '<?php if(is_array($_result) || $_result instanceof \think\Collection || $_result instanceof \think\Paginator): ';
        $parseStr .= ' $__LIST__ = $_result;';
        $parseStr .= 'if( count($__LIST__)==0 ) : echo "";';
        $parseStr .= 'else: ';
        $parseStr .= '$'.$id.' = $__LIST__;';
        $parseStr .= '?>';
        $parseStr .= $content;
        $parseStr .= '<?php endif; else: echo "";endif; ?>';
        $parseStr .= '<?php unset($aid); ?>';
        $parseStr .= '<?php $'.$id.' = []; ?>'; // 清除变量值，只限于在标签内部使用

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * notice
     * 站内通知
     * 格式： {eyou:collect aid='' /}{/eyou:collect}
     * @access public
     * @param array $tag 标签属性
     * @return string
     */
    public function tagNotice($tag,$content)
    {
        $id  = isset($tag['id']) ? $tag['id'] : 'field';

        $parseStr = '<?php ';
        $parseStr .= ' $tagNotice = new \think\template\taglib\eyou\TagNotice;';
        $parseStr .= ' $_result = $tagNotice->getNotice();';
        $parseStr .= ' ?>';

        $parseStr .= '<?php if(is_array($_result) || $_result instanceof \think\Collection || $_result instanceof \think\Paginator): ';
        $parseStr .= ' $__LIST__ = $_result;';
        $parseStr .= 'if( count($__LIST__)==0 ) : echo "";';
        $parseStr .= 'else: ';
        $parseStr .= '$'.$id.' = $__LIST__;';
        $parseStr .= '?>';
        $parseStr .= $content;
        $parseStr .= '<?php endif; else: echo "";endif; ?>';
        $parseStr .= '<?php unset($aid); ?>';
        $parseStr .= '<?php $'.$id.' = []; ?>'; // 清除变量值，只限于在标签内部使用

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * asklist 标签解析 问答模型问题列表标签通用
     * 格式：
     * {eyou:asklist id="user" empty=""}
     * {user.username}
     * {user.email}
     * {/eyou:asklist}
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string|void
     */
    public function tagAsklist($tag, $content)
    {
        $id  = isset($tag['id']) ? $tag['id'] : 'field';
        $empty  = isset($tag['empty']) ? $tag['empty'] : '';
        $empty  = htmlspecialchars($empty);
        $key    = !empty($tag['key']) ? $tag['key'] : 'i';
        $mod    = !empty($tag['mod']) && is_numeric($tag['mod']) ? $tag['mod'] : '2';
        $titlelen = !empty($tag['titlelen']) && is_numeric($tag['titlelen']) ? intval($tag['titlelen']) : 100;
        $orderby    = isset($tag['orderby']) ? $tag['orderby'] : '';
        $ordermode = !empty($tag['ordermode']) ? $tag['ordermode'] : (!empty($tag['orderway']) ? $tag['orderway'] : 'desc');
        $limit = !empty($tag['limit']) ? trim($tag['limit']) : 20;
        if (empty($tag['limit'])) {
            if (isset($tag['loop'])) $tag['row'] = $tag['loop'];
            $limit = !empty($tag['row']) ? intval($tag['row']) : $limit;
        }
        // 允许使用函数设定数据集
        $parseStr = '<?php ';

        $parseStr .= ' $tagAsklist = new \think\template\taglib\eyou\TagAsklist;';
        $parseStr .= ' $_result = $tagAsklist->getAsklist('.$limit.',"'.$orderby.'","'.$ordermode.'");';
        $parseStr .= ' $'.$id.' = $__LIST__;';
        $parseStr .= ' ?>';

        $parseStr .= '<?php if(is_array($_result) || $_result instanceof \think\Collection || $_result instanceof \think\Paginator): $' . $key . ' = 0; $e = 1;';
        $parseStr .= ' $__LIST__ = $_result;';
        $parseStr .= 'if( count($__LIST__)==0 ) : echo htmlspecialchars_decode("' . $empty . '");';
        $parseStr .= 'else: ';
        $parseStr .= 'foreach($__LIST__ as $key=>$' . $id . '): ';
        $parseStr .= '$' . $id . '["ask_title"] = text_msubstr($' . $id . '["ask_title"], 0, '.$titlelen.', false);';
        $parseStr .= '$' . $key . '= intval($key) + 1;?>';
        $parseStr .= '<?php $mod = ($' . $key . ' % ' . $mod . ' ); ?>';
        $parseStr .= $content;
        $parseStr .= '<?php ++$e; ?>';

        /*隐藏域*/
        $parseStr .= '<?php echo (count($__LIST__) == $e && isset($'.$id.'["ey_hidden"]))?$'.$id.'["ey_hidden"]:""; ?>';
        /*end*/
        
        $parseStr .= '<?php endforeach; endif; else: echo htmlspecialchars_decode("' . $empty . '");endif; ?>';
        $parseStr .= '<?php $'.$id.' = []; ?>'; // 清除变量值，只限于在标签内部使用

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * comment
     * 调用商品评价
     * 格式： {eyou:comment aid='' /}{/eyou:comment}
     * @access public
     * @param array $tag 标签属性
     * @return string
     */
    public function tagComment($tag,$content)
    {
        $aid = !empty($tag['aid']) ? $tag['aid'] : 0;
        $aid = $this->varOrvalue($aid);
        $id = isset($tag['id']) ? $tag['id'] : 'field';

        $parseStr = '<?php ';
        $parseStr .= ' $aid = '.$aid.';';
        $parseStr .= ' $tagComment = new \think\template\taglib\eyou\TagComment;';
        $parseStr .= ' $_result = $tagComment->getComment($aid);';
        $parseStr .= ' ?>';

        $parseStr .= '<?php if(is_array($_result) || $_result instanceof \think\Collection || $_result instanceof \think\Paginator): ';
        $parseStr .= ' $__LIST__ = $_result;';
        $parseStr .= 'if( count($__LIST__)==0 ) : echo "";';
        $parseStr .= 'else: ';
        $parseStr .= '$'.$id.' = $__LIST__;';
        $parseStr .= '?>';
        $parseStr .= $content;
        $parseStr .= '<?php endif; else: echo "";endif; ?>';
        $parseStr .= '<?php unset($aid); ?>';
        $parseStr .= '<?php $'.$id.' = []; ?>'; // 清除变量值，只限于在标签内部使用

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * commentlist标签解析 循环输出数据集，用于商品评价
     * 格式：
     * {eyou:commentlist name="comment_data" id="field"}
     * {user.nickname}
     * {user.level_name}
     * {/eyou:commentlist}
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string|void
     */
    public function tagCommentlist($tag, $content)
    {
        $name   = $tag['name'];
        $id  = isset($tag['id']) ? $tag['id'] : 'field';
        $empty  = isset($tag['empty']) ? $tag['empty'] : 'false';
        $empty  = htmlspecialchars($empty);
        $key    = !empty($tag['key']) ? $tag['key'] : 'i';
        $mod    = !empty($tag['mod']) && is_numeric($tag['mod']) ? $tag['mod'] : '2';
        $offset = !empty($tag['offset']) && is_numeric($tag['offset']) ? intval($tag['offset']) : 0;
        $length = !empty($tag['length']) && is_numeric($tag['length']) ? intval($tag['length']) : 'null';
        if (isset($tag['loop'])) $tag['row'] = $tag['loop'];
        if (!empty($tag['row'])) {
            $length = !empty($tag['row']) && is_numeric($tag['row']) ? intval($tag['row']) : 'null';
        }
        if (!empty($tag['limit'])) {
            $limitArr = explode(',', $tag['limit']);
            $offset = !empty($limitArr[0]) ? intval($limitArr[0]) : 0;
            $length = !empty($limitArr[1]) ? intval($limitArr[1]) : 'null';
        }
        // 允许使用函数设定数据集 <volist name=":fun('arg')" id="vo">{$vo.name}</volist>
        $parseStr = '<?php ';
        $flag     = substr($name, 0, 1);
        if (':' == $flag) {
            $name = $this->autoBuildVar($name);
            $parseStr .= '$_result=' . $name . ';';
            $name = '$_result';
        } else {
            $name = $this->autoBuildVar($name);
        }

        $parseStr .= 'if(is_array(' . $name . ') || ' . $name . ' instanceof \think\Collection || ' . $name . ' instanceof \think\Paginator): $' . $key . ' = 0; $e = 1;';
        // 设置了输出数组长度
        if (0 != $offset || 'null' != $length) {
            $parseStr .= '$__LIST__ = is_array(' . $name . ') ? array_slice(' . $name . ',' . $offset . ',' . $length . ', true) : ' . $name . '->slice(' . $offset . ',' . $length . ', true); ';
        } else {
            $parseStr .= ' $__LIST__ = ' . $name . ';';
        }
        $parseStr .= 'if( count($__LIST__)==0 ) : echo htmlspecialchars_decode('.$empty.');';
        $parseStr .= 'else: ';
        $parseStr .= 'foreach($__LIST__ as $key=>$' . $id . '): ';
        $parseStr .= '$mod = ($e % ' . $mod . ' );';
        $parseStr .= '$' . $key . '= intval($key) + 1;?>';
        $parseStr .= $content;
        $parseStr .= '<?php ++$e; ?>';
        $parseStr .= '<?php endforeach; endif; else: echo htmlspecialchars_decode('.$empty.');endif; ?>';
        $parseStr .= '<?php $'.$id.' = []; ?>'; // 清除变量值，只限于在标签内部使用

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    /**
     * citysite 标签解析 用于获取区域列表
     * 格式：type:son表示下级区域,self表示同级区域,top顶级区域
     * {eyou:citysite type='son' loop='10' pid='0' empty='' name='' id='' key='' titlelen='' offset='' mod='' currentclass='active'}
     *  <li><a href='{$field:typelink}'>{$field:typename}</a> </li> 
     * {/eyou:citysite}
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string|void
     */
    public function tagCitysite($tag, $content)
    {
        $siteid  = !empty($tag['siteid']) ? $tag['siteid'] : '';
        $siteid  = $this->varOrvalue($siteid);

        $nositeid  = !empty($tag['nositeid']) ? $tag['nositeid'] : '';
        $nositeid  = $this->varOrvalue($nositeid);

        $name   = !empty($tag['name']) ? $tag['name'] : '';
        $type   = !empty($tag['type']) ? $tag['type'] : 'son';
        $currentclass   = !empty($tag['currentclass']) ? $tag['currentclass'] : '';
        if (!isset($tag['currentclass'])) { // 加强兼容性
            $currentclass   = !empty($tag['currentstyle']) ? $tag['currentstyle'] : '';
        }
        $id     = isset($tag['id']) ? $tag['id'] : 'field';
        $key    = !empty($tag['key']) ? $tag['key'] : 'i';
        $empty  = isset($tag['empty']) ? $tag['empty'] : '';
        $empty  = htmlspecialchars($empty);
        $mod    = !empty($tag['mod']) && is_numeric($tag['mod']) ? $tag['mod'] : '2';
        $titlelen = !empty($tag['titlelen']) && is_numeric($tag['titlelen']) ? intval($tag['titlelen']) : 100;
        $offset = !empty($tag['offset']) && is_numeric($tag['offset']) ? intval($tag['offset']) : 0;
        if (isset($tag['loop'])) $tag['row'] = $tag['loop'];
        $row = !empty($tag['row']) && is_numeric($tag['row']) ? intval($tag['row']) : 100;
        if (!empty($tag['limit'])) {
            $limitArr = explode(',', $tag['limit']);
            $offset = !empty($limitArr[0]) ? intval($limitArr[0]) : 0;
            $row = !empty($limitArr[1]) ? intval($limitArr[1]) : 0;
        }

        $parseStr = '<?php ';
        if ($name) { // 从模板中传入数据集
            $symbol     = substr($name, 0, 1);
            if (':' == $symbol) {
                $name = $this->autoBuildVar($name);
                $parseStr .= '$_result=' . $name . ';';
                $name = '$_result';
            } else {
                $name = $this->autoBuildVar($name);
            }

            $parseStr .= 'if(is_array(' . $name . ') || ' . $name . ' instanceof \think\Collection || ' . $name . ' instanceof \think\Paginator): $' . $key . ' = 0; $e = 1;';
            // 设置了输出数组长度
            if (0 != $offset || 'null' != $row) {
                $parseStr .= '$__LIST__ = is_array(' . $name . ') ? array_slice(' . $name . ',' . $offset . ',' . $row . ', true) : ' . $name . '->slice(' . $offset . ',' . $row . ', true); ';
            } else {
                $parseStr .= ' $__LIST__ = ' . $name . ';';
            }

        } else { // 查询数据库获取的数据集
            $parseStr .= ' $tagCitysite = new \think\template\taglib\eyou\TagCitysite;';
            $parseStr .= ' $_result = $tagCitysite->getCitysite('.$siteid.', "'.$type.'", "'.$currentclass.'", '.$nositeid.');';
            $parseStr .= ' if(is_array($_result) || $_result instanceof \think\Collection || $_result instanceof \think\Paginator): $' . $key . ' = 0; $e = 1;';
            // 设置了输出数组长度
            if (0 != $offset || 'null' != $row) {
                $parseStr .= '$__LIST__ = is_array($_result) ? array_slice($_result,' . $offset . ',' . $row . ', true) : $_result->slice(' . $offset . ',' . $row . ', true); ';
            } else {
                $parseStr .= ' $__LIST__ = $_result;';
            }
        }

        $parseStr .= 'if( count($__LIST__)==0 ) : echo htmlspecialchars_decode("' . $empty . '");';
        $parseStr .= 'else: ';
        $parseStr .= 'foreach($__LIST__ as $key=>$' . $id . '): ';
        $parseStr .= '$' . $id . '["name"] = text_msubstr($' . $id . '["name"], 0, '.$titlelen.', false);';

        $parseStr .= ' $__LIST__[$key] = $_result[$key] = $' . $id . ';';
        $parseStr .= '$' . $key . '= intval($key) + 1;?>';
        $parseStr .= '<?php $mod = ($' . $key . ' % ' . $mod . ' ); ?>';
        $parseStr .= $content;
        $parseStr .= '<?php ++$e; ?>';
        $parseStr .= '<?php endforeach; endif; else: echo htmlspecialchars_decode("' . $empty . '");endif; ?>';
        $parseStr .= '<?php $'.$id.' = []; ?>'; // 清除变量值，只限于在标签内部使用

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }

    //articlepay  文章付费阅读标签
    public function tagArticlepay($tag,$content)
    {
        $id     = isset($tag['id']) ? $tag['id'] : 'field';

        $parseStr = '<?php ';
        $parseStr .= ' $tagArticlepay = new \think\template\taglib\eyou\TagArticlepay;';
        $parseStr .= ' $_result = $tagArticlepay->getArticlepay();';
        $parseStr .= '$'.$id.' = $_result;';
        $parseStr .= '?>';

        $parseStr .= $content;
        $parseStr .= '<?php $'.$id.' = []; ?>'; // 清除变量值，只限于在标签内部使用

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }
    //downloadpay  付费下载标签
    public function tagDownloadpay($tag,$content)
    {
        $id     = isset($tag['id']) ? $tag['id'] : 'field';

        $parseStr = '<?php ';
        $parseStr .= ' $tagDownloadpay = new \think\template\taglib\eyou\TagDownloadpay;';
        $parseStr .= ' $_result = $tagDownloadpay->getDownloadpay();';
        $parseStr .= '$'.$id.' = $_result;';
        $parseStr .= '?>';

        $parseStr .= $content;
        $parseStr .= '<?php $'.$id.' = []; ?>'; // 清除变量值，只限于在标签内部使用

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }
    /**
     * navigation标签解析 获取导航列表
     * 格式：
     * {ebuy:navigation titlelen='30' bodylen='160' id='navig'}
     *  <li><a href="{$navig.navig_url}">{$navig.navig_name}</a></li>
     * {/ebuy:navigation}
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string|void
     */
    public function tagNavigation($tag, $content)
    {
        $name   = isset($tag['name']) ? $tag['name'] : '';
        $id     = isset($tag['id']) ? $tag['id'] : 'field';
        $key    = !empty($tag['key']) ? $tag['key'] : 'i';
        $empty  = isset($tag['empty']) ? $tag['empty'] : '';
        $empty  = htmlspecialchars($empty);
        $mod    = !empty($tag['mod']) && is_numeric($tag['mod']) ? $tag['mod'] : '2';
        if (isset($tag['loop'])) $tag['row'] = $tag['loop'];
        $row    = !empty($tag['row']) ? intval($tag['row']) : 0;
        $orderby    = isset($tag['orderby']) ? $tag['orderby'] : 'sort_order';
        $ordermode = !empty($tag['ordermode']) ? $tag['ordermode'] : (!empty($tag['orderway']) ? $tag['orderway'] : 'asc');
        $titlelen = !empty($tag['titlelen']) && is_numeric($tag['titlelen']) ? intval($tag['titlelen']) : 100;
        $position_id  = !empty($tag['position_id']) ? $tag['position_id'] : '';
        $position_id  = $this->varOrvalue($position_id);
        $nav_id  = !empty($tag['nav_id']) ? $tag['nav_id'] : '';
        $nav_id  = $this->varOrvalue($nav_id);
        $currentclass   = !empty($tag['currentclass']) ? $tag['currentclass'] : '';
        if (!isset($tag['currentclass'])) { // 加强兼容性
            $currentclass   = !empty($tag['currentstyle']) ? $tag['currentstyle'] : '';
        }

        $parseStr = '<?php ';
        if ($name) { // 从模板中传入数据集
            $symbol     = substr($name, 0, 1);
            if (':' == $symbol) {
                $name = $this->autoBuildVar($name);
                $parseStr .= '$_result=' . $name . ';';
                $name = '$_result';
            } else {
                $name = $this->autoBuildVar($name);
            }

            $parseStr .= 'if(is_array(' . $name . ') || ' . $name . ' instanceof \think\Collection || ' . $name . ' instanceof \think\Paginator): $' . $key . ' = 0; $e = 1;';
            $parseStr .= ' $__LIST__ = ' . $name . ';';

        } else { // 查询数据库获取的数据集
            // 查询数据库获取的数据集
            $parseStr .= ' $tagNavigation = new \think\template\taglib\eyou\TagNavigation;';
            $parseStr .= ' $_result = $tagNavigation->getNavigation(' . $position_id . ', "' . $orderby . '", "' . $ordermode . '", "' . $currentclass . '", ' . $nav_id . ');';
            $parseStr .= ' if(is_array($_result) || $_result instanceof \think\Collection || $_result instanceof \think\Paginator): $' . $key . ' = 0; $e = 1;';
            $parseStr .= ' $__LIST__ = $_result;';
        }
        $parseStr .= 'if( count($__LIST__)==0 ) : echo htmlspecialchars_decode("' . $empty . '");';
        $parseStr .= 'else: ';
        $parseStr .= 'foreach($__LIST__ as $key=>$' . $id . '): ';

        $parseStr .= ' $__LIST__[$key] = $_result[$key] = $' . $id . ';';

        $parseStr .= '$' . $key . '= intval($key) + 1;?>';
        $parseStr .= '<?php $mod = ($' . $key . ' % ' . $mod . ' ); ?>';


        $parseStr .= $content;
        $parseStr .= '<?php ++$e; ?>';
        $parseStr .= '<?php endforeach;';
        $parseStr .= 'endif; else: echo htmlspecialchars_decode("' . $empty . '");endif; ?>';
        $parseStr .= '<?php $'.$id.' = []; ?>';

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return false;
    }

    /**
     * formreply 循环输出数据集，用于商品评价
     * 格式：
     * {eyou:formreply name="comment_data" id="field"}
     * {user.nickname}
     * {user.level_name}
     * {/eyou:formreply}
     * @access public
     * @param array $tag 标签属性
     * @param string $content 标签内容
     * @return string|void
     */
    public function tagFormreply($tag, $content)
    {
        $typeid = !empty($tag['typeid']) ? $tag['typeid'] : '';
        $typeid = $this->varOrvalue($typeid);

        $id  = isset($tag['id']) ? $tag['id'] : 'field';
        $empty  = isset($tag['empty']) ? $tag['empty'] : '';
        $empty  = htmlspecialchars($empty);
        $key    = !empty($tag['key']) ? $tag['key'] : 'i';
        $mod    = !empty($tag['mod']) && is_numeric($tag['mod']) ? $tag['mod'] : '2';
        $ordermode = !empty($tag['ordermode']) ? $tag['ordermode'] : 'desc';
        $pagesize = !empty($tag['pagesize']) ? trim($tag['pagesize']) : 10;
        $page = !empty($tag['page']) ? trim($tag['page']) : 1;
        // 允许使用函数设定数据集
        $parseStr = '<?php ';

        $parseStr .= ' $tagFormreply = new \think\template\taglib\eyou\TagFormreply;';
        $parseStr .= ' $_result = $tagFormreply->getFormreply('.$typeid.','.$page.',"'.$pagesize.'","'.$ordermode.'");';
        $parseStr .= ' $'.$id.' = $__LIST__;';
        $parseStr .= ' ?>';

        $parseStr .= '<?php if(is_array($_result) || $_result instanceof \think\Collection || $_result instanceof \think\Paginator): $' . $key . ' = 0; $e = 1;';
        $parseStr .= ' $__LIST__ = $_result;';
        $parseStr .= 'if( count($__LIST__)==0 ) : echo htmlspecialchars_decode("' . $empty . '");';
        $parseStr .= 'else: ';
        $parseStr .= 'foreach($__LIST__ as $key=>$' . $id . '): ';
        $parseStr .= '$' . $key . '= intval($key) + 1;?>';
        $parseStr .= '<?php $mod = ($' . $key . ' % ' . $mod . ' ); ?>';
        $parseStr .= $content;
        $parseStr .= '<?php ++$e; ?>';

        /*隐藏域*/
        $parseStr .= '<?php echo (count($__LIST__) == $e && isset($'.$id.'["ey_hidden"]))?$'.$id.'["ey_hidden"]:""; ?>';
        /*end*/

        $parseStr .= '<?php endforeach; endif; else: echo htmlspecialchars_decode("' . $empty . '");endif; ?>';
        $parseStr .= '<?php $'.$id.' = []; ?>'; // 清除变量值，只限于在标签内部使用

        if (!empty($parseStr)) {
            return $parseStr;
        }
        return;
    }
}
