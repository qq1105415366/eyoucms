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

namespace app\admin\logic;

use think\Model;
use think\Db;

/**
 * 文档逻辑定义
 * Class CatsLogic
 * @package admin\Logic
 */
load_trait('controller/Jump');
class ArchivesLogic extends Model
{
    use \traits\controller\Jump;
    
    private $admin_lang = 'cn';

    /**
     * 析构函数
     */
    function  __construct() {
        $this->admin_lang = get_admin_lang();
        $this->langcopylog =Db::name('language_archives_copy_log');
    }

    /**
     * 删除文档
     */
    public function del($del_id = array(), $thorough = 0, $table = '')
    {
        $del_id = !empty($del_id) ? $del_id : input('del_id/a');
        $id_arr = eyIntval($del_id);
        $_POST['aids'] = $id_arr;
        $thorough = !empty($thorough) ? $thorough : input('thorough/d');
        if (is_dir('./weapp/CompressContent/')){                
            try {
               $compressLogic = new \weapp\CompressContent\logic\CompressContentLogic;                                    
            } catch (Exception $e){
            }                
        }  
        if (!empty($id_arr)) {
            /*分离并组合相同模型下的文档ID*/
            $field = 'a.aid, a.typeid, a.channel, a.arcrank, a.is_recom, a.is_special, a.is_b, a.is_head, a.is_litpic, a.is_jump, a.is_slide, a.is_roll, a.is_diyattr, a.users_id, b.table, b.ctl_name, b.ifsystem';
            $row = Db::name('archives')
                ->alias('a')
                ->field($field)
                ->join('__CHANNELTYPE__ b', 'a.channel = b.id', 'LEFT')
                ->where([
                    'a.aid' => ['IN', $id_arr],
                    'a.lang'    => $this->admin_lang,
                ])
                ->select();

            $data = array();
            foreach ($row as $key => $val) {
                $data[$val['channel']]['aid'][] = $val['aid'];
                $data[$val['channel']]['table'] = $val['table'];
                if (empty($val['ifsystem'])) {
                    $ctl_name = 'Custom';
                } else {
                    $ctl_name = $val['ctl_name'];
                }
                $data[$val['channel']]['ctl_name'] = $ctl_name;
            }
            /*--end*/

            // 删除静态文件
            $buildhtmlLogic = new \app\common\logic\BuildhtmlLogic;
            $buildhtmlLogic->delViewHtml($id_arr);
            $suc_del_typeid = model('Arctype')->get_arc_type(['aid'=>$id_arr]);

            if (1 == $thorough) { // 直接删除，跳过回收站
                $err = 0;
                foreach ($data as $key => $val) {
                    $r = Db::name('archives')->where('aid','IN',$val['aid'])->delete();
                    if ($r !== false) { 
                        if (!empty($compressLogic)) {
                            try {                                
                                $compressLogic->copydata(5,$val['ctl_name'],'',$val['table'], $val['aid']);   
                            } catch (Exception $e) {
                            }                
                        }
                        if (empty($val['ifsystem'])) {
                            model($val['ctl_name'])->afterDel($val['aid'], $val['table']);
                        } else {
                            model($val['ctl_name'])->afterDel($val['aid']);
                        }
                        adminLog('删除文档-id：'.implode(',', $val['aid']));
                    } else {
                        $err++;
                    }
                }
            } else {
                $info['is_del']     = 1; // 伪删除状态
                $info['update_time']= getTime(); // 更新修改时间
                $info['del_method'] = 1; // 恢复删除方式为默认

                $err = 0;
                foreach ($data as $key => $val) {
                    $r = Db::name('archives')->where('aid','IN',$val['aid'])->update($info);
                    if ($r) {
                        adminLog('删除文档-id：'.implode(',', $val['aid']));
                    } else {
                        $err++;
                    }
                }
            }

            if (0 == $err) {
                // 处理mysql缓存表数据
                $DraftData = [
                    'TypeID' => [],
                    'UsersID' => [],
                ];
                foreach ($row as $key => $value) {
                    if (-1 === $value['arcrank'] && !empty($value['users_id'])) {
                        array_push($DraftData['TypeID'], $value['typeid']);
                        array_push($DraftData['UsersID'], $value['users_id']);
                        unset($row[$key]);
                    }
                }
                if (!empty($row)) model('SqlCacheTable')->UpdateSqlCacheTable($row, 'del', $table);
                if (!empty($DraftData)) model('SqlCacheTable')->UpdateDraftSqlCacheTable($DraftData, 'admin_del');

                // 系统商品操作时，积分商品的被动处理
                model('ShopPublicHandle')->pointsGoodsPassiveHandle($id_arr);
                if (!empty($suc_del_typeid)) model('Arctype')->hand_type_count(['typeid'=>$suc_del_typeid]); //统计栏目文档数量

                $this->success('删除成功！');
            } else if ($err < count($data)) {
                // 系统商品操作时，积分商品的被动处理
                model('ShopPublicHandle')->pointsGoodsPassiveHandle($id_arr);

                $this->success('删除部分成功！');
            } else {
                $this->error('删除失败！');
            }
        }else{
            $this->error('文档不存在！');
        }
    }

    /**
     * 获取文档模板文件列表
     */
    public function getTemplateList($nid = 'article')
    {   
        $planPath = 'template/'.TPL_THEME.'pc';
        $dirRes   = opendir($planPath);
        $view_suffix = config('template.view_suffix');

        /*模板PC目录文件列表*/
        $templateArr = array();
        while($filename = readdir($dirRes))
        {
            if (in_array($filename, array('.','..'))) {
                continue;
            }
            array_push($templateArr, $filename);
        }
        /*--end*/

        /*多语言全部标识*/
        $markArr = Db::name('language_mark')->column('mark');
        /*--end*/

        $templateList = array();
        foreach ($templateArr as $k2 => $v2) {
            $v2 = iconv('GB2312', 'UTF-8', $v2);
            preg_match('/^(view)_'.$nid.'(_(.*))?(_'.$this->admin_lang.')?\.'.$view_suffix.'/i', $v2, $matches1);
            $langtpl = preg_replace('/\.'.$view_suffix.'$/i', "_{$this->admin_lang}.{$view_suffix}", $v2);
            if (file_exists(realpath($planPath.DS.$langtpl))) {
                continue;
            } else if (preg_match('/^(.*)_([a-zA-z]{2,2})\.'.$view_suffix.'$/i',$v2,$matches2)) {
                if (in_array($matches2[2], $markArr) && $matches2[2] != $this->admin_lang) {
                    continue;
                }
            }

            if (!empty($matches1)) {
                if ('view' == $matches1[1]) {
                    array_push($templateList, $v2);
                }
            }
        }

        return $templateList;
    }

    /**
     * 复制文档
     */
    public function batch_copy($aids = [], $typeid = 0, $channel = 0, $num = 1)
    {
        // 获取复制栏目的模型ID
        $channeltypeRow = Db::name('channeltype')->field('nid,table')
            ->where([
                'id'    => $channel,
            ])->find();
        if (!empty($channeltypeRow)) {
            // 主表数据
            $archivesRow = Db::name('archives')->where(['aid'=>['IN', $aids]])->select();
            // 内容扩展表数据
            $tableExt = $channeltypeRow['table']."_content";
            $contentRow = Db::name($tableExt)->field('id', true)->where(['aid'=>['IN', $aids]])->getAllWithIndex('aid');

            // 拥有特性模型的其他数据处理
            if ('images' == $channeltypeRow['nid']) { // 图集模型的特性表数据
                $imgUploadRow = Db::name('images_upload')->field('img_id', true)->where(['aid'=>['IN', $aids]])->select();
                $imgUploadRow = group_same_key($imgUploadRow, 'aid');
            } 
            else if ('download' == $channeltypeRow['nid']) { // 下载模型的特性表数据
                // 附件表
                $downloadFileRow = Db::name('download_file')->field('file_id', true)->where(['aid'=>['IN', $aids]])->select();
                $downloadFileRow = group_same_key($downloadFileRow, 'aid');
            }
            else if ('product' == $channeltypeRow['nid']) { // 产品模型的特性表数据
                // 属性值表
                $productAttrRow = Db::name('product_attr')->field('product_attr_id', true)->where(['aid'=>['IN', $aids]])->select();
                $productAttrRow = group_same_key($productAttrRow, 'aid');
                // 产品图集表
                $productImgRow = Db::name('product_img')->field('img_id', true)->where(['aid'=>['IN', $aids]])->select();
                $productImgRow = group_same_key($productImgRow, 'aid');
                // 产品虚拟表
                $productNetdiskRow = Db::name('product_netdisk')->field('nd_id', true)->where(['aid'=>['IN', $aids]])->select();
                $productNetdiskRow = group_same_key($productNetdiskRow, 'aid');
                // 产品规格数据表
                $productSpecRow = Db::name('product_spec_data')->field('spec_id', true)->where(['aid'=>['IN', $aids]])->select();
                $productSpecRow = group_same_key($productSpecRow, 'aid');
                // 产品多规格组装表
                $productSpecValueRow = Db::name('product_spec_value')->field('value_id', true)->where(['aid'=>['IN', $aids]])->select();
                $productSpecValueRow = group_same_key($productSpecValueRow, 'aid');
                // 新商品参数属性值表
                $shopProductAttrRow = Db::name('shop_product_attr')->field('product_attr_id', true)->where(['aid'=>['IN', $aids]])->select();
                $shopProductAttrRow = group_same_key($shopProductAttrRow, 'aid');
            }
            else if ('media' == $channeltypeRow['nid']) { // 视频模型的特性表数据
                // 附件表
                $mediaFileRow = Db::name('media_file')->field('file_id', true)->where(['aid'=>['IN', $aids]])->select();
                $mediaFileRow = group_same_key($mediaFileRow, 'aid');
            }
            else if ('special' == $channeltypeRow['nid']) { // 专题模型的特性表数据
                // 节点表
                $specialNodeRow = Db::name('special_node')->field('node_id', true)->where(['aid'=>['IN', $aids]])->select();
                $specialNodeRow = group_same_key($specialNodeRow, 'aid');
            }

            if (is_dir('./weapp/CompressContent/')) {                                            
                $compressLogic = new \weapp\CompressContent\logic\CompressContentLogic;  
            }   

            foreach ($archivesRow as $key => $val) {
                // 原先数据的栏目ID
                $typeid_old = $val['typeid'];

                // 同步数据
                $archivesData = [];
                for ($i = 0; $i < $num; $i++) {
                    // 主表
                    $archivesInfo = $val;
                    unset($archivesInfo['aid']);
                    $archivesInfo['typeid'] = $typeid;
                    $archivesInfo['add_time'] = getTime();
                    $archivesInfo['update_time'] = getTime();
                    $archivesData[] = $archivesInfo;
                }
                if (!empty($archivesData)) {
                    $rdata = model('Archives')->saveAll($archivesData);
                    if ($rdata) {
                        // 内容扩展表的数据
                        $contentData = [];
                        $contentInfo = $contentRow[$val['aid']];

                        // 拥有特性模型的其他数据处理
                        $imgUploadInfo = $imgUploadData = [];
                        $downloadFileInfo = $downloadFileData = [];
                        $mediaFileInfo = $mediaFileData = [];
                        $specialNodeInfo = $specialNodeData = [];
                        $productAttrInfo = $productImgInfo = $productNetdiskInfo = $productSpecInfo = $productSpecValueInfo = $shopProductAttrInfo = [];
                        $productAttrData = $productImgData = $productNetdiskData = $productSpecData = $productSpecValueData = $shopProductAttrData = [];
                        if ('images' == $channeltypeRow['nid']) { // 图集模型的特性表数据
                            $imgUploadInfo = !empty($imgUploadRow[$val['aid']]) ? $imgUploadRow[$val['aid']] : [];
                        } else if ('download' == $channeltypeRow['nid']) { // 下载模型的特性表数据
                            $downloadFileInfo = !empty($downloadFileRow[$val['aid']]) ? $downloadFileRow[$val['aid']] : [];
                        } else if ('product' == $channeltypeRow['nid']) { // 新房模型的特性表数据
                            // 属性值表 - 只复制同栏目的属性值
                            if ($typeid_old == $typeid) {
                                $productAttrInfo = !empty($productAttrRow[$val['aid']]) ? $productAttrRow[$val['aid']] : [];
                            }
                            // 新商品参数属性值表
                            $shopProductAttrInfo = !empty($shopProductAttrRow[$val['aid']]) ? $shopProductAttrRow[$val['aid']] : [];
                            // 产品图集表
                            $productImgInfo = !empty($productImgRow[$val['aid']]) ? $productImgRow[$val['aid']] : [];
                            // 产品虚拟表
                            $productNetdiskInfo = !empty($productNetdiskRow[$val['aid']]) ? $productNetdiskRow[$val['aid']] : [];
                            // 产品规格数据表
                            $productSpecInfo = !empty($productSpecRow[$val['aid']]) ? $productSpecRow[$val['aid']] : [];
                            // 产品多规格组装表
                            $productSpecValueInfo = !empty($productSpecValueRow[$val['aid']]) ? $productSpecValueRow[$val['aid']] : [];
                        } else if ('media' == $channeltypeRow['nid']) { // 视频模型的特性表数据
                            $mediaFileInfo = !empty($mediaFileRow[$val['aid']]) ? $mediaFileRow[$val['aid']] : [];
                        } else if ('special' == $channeltypeRow['nid']) { // 专题模型的特性表数据
                            $specialNodeInfo = !empty($specialNodeRow[$val['aid']]) ? $specialNodeRow[$val['aid']] : [];
                        }

                        // 需要复制的数据与新产生的文档ID进行关联
                        foreach ($rdata as $k1 => $v1) {
                            $aid_new = $v1->getData('aid');

                            // 内容扩展表的数据
                            $contentInfo['aid'] = $aid_new;
                            $contentData[] = $contentInfo;

                            // 图集模型
                            if ('images' == $channeltypeRow['nid']) {
                                foreach ($imgUploadInfo as $img_k => $img_v) {
                                    $img_v['aid'] = $aid_new;
                                    $imgUploadData[] = $img_v;
                                }
                            } else if ('download' == $channeltypeRow['nid']) {
                                // 附件表
                                foreach ($downloadFileInfo as $file_k => $file_v) {
                                    $file_v['aid'] = $aid_new;
                                    $downloadFileData[] = $file_v;
                                }
                            } else if ('product' == $channeltypeRow['nid']) {
                                // 属性值表
                                foreach ($productAttrInfo as $attr_k => $attr_v) {
                                    $attr_v['aid'] = $aid_new;
                                    $productAttrData[] = $attr_v;
                                }
                                // 产品图集表
                                foreach ($productImgInfo as $img_k => $img_v) {
                                    $img_v['aid'] = $aid_new;
                                    $productImgData[] = $img_v;
                                }
                                // 产品虚拟表
                                foreach ($productNetdiskInfo as $nd_k => $nd_v) {
                                    $nd_v['aid'] = $aid_new;
                                    $productNetdiskData[] = $nd_v;
                                }
                                // 产品规格数据表
                                foreach ($productSpecInfo as $spec_k => $spec_v) {
                                    $spec_v['aid'] = $aid_new;
                                    $productSpecData[] = $spec_v;
                                }
                                // 产品多规格组装表
                                foreach ($productSpecValueInfo as $specv_k => $specv_v) {
                                    $specv_v['aid'] = $aid_new;
                                    $productSpecValueData[] = $specv_v;
                                }
                                // 新商品参数属性值表
                                foreach ($shopProductAttrInfo as $attr_k => $attr_v) {
                                    $attr_v['aid'] = $aid_new;
                                    $shopProductAttrData[] = $attr_v;
                                }
                            } else if ('media' == $channeltypeRow['nid']) {
                                // 附件表
                                foreach ($mediaFileInfo as $file_k => $file_v) {
                                    $file_v['aid'] = $aid_new;
                                    $mediaFileData[] = $file_v;
                                }
                            } else if ('special' == $channeltypeRow['nid']) {
                                // 附件表
                                foreach ($specialNodeInfo as $node_k => $node_v) {
                                    $node_v['aid'] = $aid_new;
                                    $specialNodeData[] = $node_v;
                                }
                            }
                        }

                        // 批量写入内容扩展表
                        if (!empty($contentData)) {
                            if (!empty($compressLogic)) {
                                $compressLogic->copydata(2,$channeltypeRow,'',$tableExt, $contentData);           
                            }                            
                            Db::name($tableExt)->insertAll($contentData);
                        }
                        // 批量写入图集模型的图片表
                        if ('images' == $channeltypeRow['nid']) {
                            !empty($imgUploadData) && model('ImagesUpload')->saveAll($imgUploadData);
                        } else if ('download' == $channeltypeRow['nid']) {
                            // 附件表
                            !empty($downloadFileData) && model('DownloadFile')->saveAll($downloadFileData);
                        } else if ('product' == $channeltypeRow['nid']) {
                            // 属性值表
                            !empty($productAttrData) && Db::name('product_attr')->insertAll($productAttrData);
                            // 产品图集表
                            !empty($productImgData) && model('ProductImg')->saveAll($productImgData);
                            // 产品虚拟表
                            !empty($productNetdiskData) && model('ProductNetdisk')->saveAll($productNetdiskData);
                            // 产品规格数据表
                            !empty($productSpecData) && model('ProductSpecData')->saveAll($productSpecData);
                            // 产品多规格组装表
                            !empty($productSpecValueData) && model('ProductSpecValue')->saveAll($productSpecValueData);
                            // 新商品参数属性值表
                            !empty($shopProductAttrData) && Db::name('shop_product_attr')->insertAll($shopProductAttrData);
                        } else if ('media' == $channeltypeRow['nid']) {
                            // 附件表
                            !empty($mediaFileData) && model('MediaFile')->saveAll($mediaFileData);
                        } else if ('special' == $channeltypeRow['nid']) {
                            // 附件表
                            !empty($specialNodeData) && model('SpecialNode')->saveAll($specialNodeData);
                        }
                    }
                    else {
                        $this->error('复制失败！');
                    }
                }
            }
            /*清空sql_cache_table数据缓存表 并 添加查询执行语句到mysql缓存表*/
            Db::execute('TRUNCATE TABLE '.config('database.prefix').'sql_cache_table');
            model('SqlCacheTable')->InsertSqlCacheTable(true);

            model('Arctype')->hand_type_count(['typeid'=>[$typeid]]);//统计栏目文档数量

            /* END */
            $this->success('复制成功！');
        } else {
            $this->error('模型不存在！');
        }
    }

    /**
     * 复制全部文档
     * 新增语言是复制使用
     * 从中文复制到新增的语言
     * $is_jump = 0 不跳过重复文章 1 - 跳过
     */
    public function batch_copy_all($lang = '',$is_jump = 0)
    {
        if (empty($lang)) return false;
        //删除已有数据 开始
        $lang_channel_data = Db::name('archives')
            ->alias('a')
            ->field('a.channel,b.nid,b.table')
            ->join('channeltype b','a.channel = b.id','left')
            ->where(['a.lang' => $lang, 'a.is_del' => 0,'a.channel'=>['neq',6]])
            ->group('a.channel')
            ->getAllWithIndex('channel');
        if (!empty($lang_channel_data)){
            foreach ($lang_channel_data as $k => $v){
                $aids = Db::name('archives')->where(['lang' => $lang, 'is_del' => 0,'channel'=>$v['channel']])->column('aid');
                Db::name('archives')->where(['aid' => ['IN', $aids]])->delete();

                //删除内容扩展表
                $tableExt = $v['table'] . "_content";
                Db::name($tableExt)->where(['aid' => ['IN', $aids]])->delete();

                // 拥有特性模型的其他数据处理
                if ('images' == $v['nid']) {
                    $imgUploadRow = Db::name('images_upload')->where(['aid' => ['IN', $aids]])->delete();
                } else if ('download' == $v['nid']) { // 下载模型的特性表数据
                    // 附件表
                    $downloadFileRow = Db::name('download_file')->where(['aid' => ['IN', $aids]])->delete();
                } else if ('product' == $v['nid']) { // 产品模型的特性表数据
                    // 属性值表
                    $productAttrRow = Db::name('product_attr')->where(['aid' => ['IN', $aids]])->delete();
                    // 产品图集表
                    $productImgRow = Db::name('product_img')->where(['aid' => ['IN', $aids]])->delete();
                    // 产品虚拟表
                    $productNetdiskRow = Db::name('product_netdisk')->where(['aid' => ['IN', $aids]])->delete();
                    // 产品规格数据表
                    $productSpecRow = Db::name('product_spec_data')->where(['aid' => ['IN', $aids]])->delete();
                    // 产品多规格组装表
                    $productSpecValueRow = Db::name('product_spec_value')->where(['aid' => ['IN', $aids]])->delete();
                    // 新商品参数属性值表
                    $shopProductAttrRow = Db::name('shop_product_attr')->where(['aid' => ['IN', $aids]])->delete();
                } else if ('media' == $v['nid']) { // 视频模型的特性表数据
                    // 附件表
                    $mediaFileRow = Db::name('media_file')->where(['aid' => ['IN', $aids]])->delete();
                } else if ('special' == $v['nid']) { // 专题模型的特性表数据
                    // 节点表
                    $specialNodeRow = Db::name('special_node')->where(['aid' => ['IN', $aids]])->delete();
                }
            }
        }
        //删除已有数据 结束
        //给专题关联的文档用
        $compare_aid = [];
        //新建数据开始
        $channel_ids = Db::name('archives')->where(['lang' => 'cn', 'is_del' => 0,'channel'=>['neq',6]])->group('channel')->column('channel');
        // 获取复制栏目的模型ID
        $channeltypeRow = Db::name('channeltype')->field('id,nid,table')
            ->where([
                'id' => ['in', $channel_ids],
            ])->getAllWithIndex('id');
        if (!empty($channeltypeRow)) {
            $error_aid = [];
            foreach ($channeltypeRow as $ck => $cv) {
                $archivesRow = Db::name('archives')->where(['lang' => 'cn', 'is_del' => 0,'channel'=>$ck])->select();
                if (!empty($is_jump)){
                    $titles = get_arr_column($archivesRow,'title');
                    $repeat_title_arr = Db::name('archives')->where(['lang' => $lang, 'is_del' => 0,'channel'=>$ck])->where('title','in',$titles)->column('title');
                    if (!empty($repeat_title_arr)){
                        foreach ($archivesRow as $m => $n){
                            if (in_array($n['title'],$repeat_title_arr)) unset($archivesRow[$m]);
                        }
                        if (!empty($archivesRow)) {
                            $archivesRow = array_merge($archivesRow);
                        }
                    }
                }
                if (empty($archivesRow)) continue;
                $aids = $typeids = [];
                foreach ($archivesRow as $key => $val) {
                    if (!in_array($val['aid'], $aids)) $aids[] = $val['aid'];
                    if (!in_array($val['typeid'], $typeids)) $typeids[] = $val['typeid'];
                }
                // 内容扩展表数据
                $tableExt = $cv['table'] . "_content";
                $contentRow = Db::name($tableExt)->field('id', true)->where(['aid' => ['IN', $aids]])->getAllWithIndex('aid');
                // 原语言栏目
                $typeids_arr = [];
                foreach ($typeids as $k => $v){
                    $typeids_arr[] = 'tid' . $v;
                }
                // 源语言关联的多语言栏目
                $lang_typeids = Db::name('language_attr')->where(['attr_group' => 'arctype', 'attr_name' => ['in',$typeids_arr],'lang'=>$lang])->field('attr_value,attr_name')->getAllWithIndex('attr_name');
                // 拥有特性模型的其他数据处理

                if ('images' == $channeltypeRow[$val['channel']]['nid']) { // 图集模型的特性表数据
                    $imgUploadRow = Db::name('images_upload')->field('img_id', true)->where(['aid' => ['IN', $aids]])->select();
                    $imgUploadRow = group_same_key($imgUploadRow, 'aid');
                } else if ('download' == $channeltypeRow[$val['channel']]['nid']) { // 下载模型的特性表数据
                    // 附件表
                    $downloadFileRow = Db::name('download_file')->field('file_id', true)->where(['aid' => ['IN', $aids]])->select();
                    $downloadFileRow = group_same_key($downloadFileRow, 'aid');
                } else if ('product' == $channeltypeRow[$val['channel']]['nid']) { // 产品模型的特性表数据
                    // 属性值表
                    $productAttrRow = Db::name('product_attr')->field('product_attr_id', true)->where(['aid' => ['IN', $aids]])->select();
                    $productAttrRow = group_same_key($productAttrRow, 'aid');
                    // 产品图集表
                    $productImgRow = Db::name('product_img')->field('img_id', true)->where(['aid' => ['IN', $aids]])->select();
                    $productImgRow = group_same_key($productImgRow, 'aid');
                    // 产品虚拟表
                    $productNetdiskRow = Db::name('product_netdisk')->field('nd_id', true)->where(['aid' => ['IN', $aids]])->select();
                    $productNetdiskRow = group_same_key($productNetdiskRow, 'aid');
                    // 产品规格数据表
                    $productSpecRow = Db::name('product_spec_data')->field('spec_id', true)->where(['aid' => ['IN', $aids]])->select();
                    $productSpecRow = group_same_key($productSpecRow, 'aid');
                    // 产品多规格组装表
                    $productSpecValueRow = Db::name('product_spec_value')->field('value_id', true)->where(['aid' => ['IN', $aids]])->select();
                    $productSpecValueRow = group_same_key($productSpecValueRow, 'aid');
                    // 新商品参数属性值表
                    $shopProductAttrRow = Db::name('shop_product_attr')->field('product_attr_id', true)->where(['aid' => ['IN', $aids]])->select();
                    $shopProductAttrRow = group_same_key($shopProductAttrRow, 'aid');
                    // 多语言关联
                    $language_attr = [];
                    $row = Db::name('language_attr')->where(['lang'=>$lang])->select();
                    foreach ($row as $_atk => $_atv) {
                        $language_attr[$_atv['attr_group']][$_atv['attr_name']] = $_atv['attr_value'];
                    }
                } else if ('media' == $channeltypeRow[$val['channel']]['nid']) { // 视频模型的特性表数据
                    // 附件表
                    $mediaFileRow = Db::name('media_file')->field('file_id', true)->where(['aid' => ['IN', $aids]])->select();
                    $mediaFileRow = group_same_key($mediaFileRow, 'aid');
                } else if ('special' == $channeltypeRow[$val['channel']]['nid']) { // 专题模型的特性表数据
                    // 节点表
                    $specialNodeRow = Db::name('special_node')->field('node_id', true)->where(['aid' => ['IN', $aids]])->select();
                    $specialNodeRow = group_same_key($specialNodeRow, 'aid');
                }

                foreach ($archivesRow as $key => $val) {
                    // 原先数据的栏目ID
                    $typeid_old = $val['typeid'];
                    //多语言栏目id
                    $typeid =  $lang_typeids['tid'.$val['typeid']]['attr_value'];
                    // 只同步相关联的栏目
                    if (empty($typeid)) {
                        continue;
                    }
                    $aid = $val['aid'];
                    $archivesInfo = $val;
                    unset($archivesInfo['aid']);
                    $archivesInfo['typeid'] = $typeid;
                    $archivesInfo['lang'] = $lang;
                    // $archivesInfo['add_time'] = getTime();
                    // $archivesInfo['update_time'] = getTime();
                    if (!empty($language_attr['shop_product_attrlist']['attrlist_'.$archivesInfo['attrlist_id']])) {
                        $archivesInfo['attrlist_id'] = $language_attr['shop_product_attrlist']['attrlist_'.$archivesInfo['attrlist_id']];
                    }
                    if (!empty($archivesInfo)) {
                        $new_aid = Db::name('archives')->insertGetId($archivesInfo);
                        if ($new_aid) {
                            if(!$this->langcopylog->where(['oldid'=>$aid,'lang'=>$lang])->find()){
                                // 记录更新log
                                $this->langcopylog->insert(['channel'=>$ck,'typeid'=>$typeid_old,'new_typeid'=>$typeid,'oldid'=>$aid,'newid'=>$new_aid,'lang'=>$lang,'add_time'=>time()]);
                            }  
                            $compare_aid[$aid] = $new_aid;
                            $channelfield_bind_list = Db::name('channelfield_bind')->where('typeid',$typeid_old)->select();
                            if (!empty($channelfield_bind_list)){
                                $field_ids = get_arr_column($channelfield_bind_list,'field_id');
                                //查询已经写入的
                                $bind_field_ids = Db::name('channelfield_bind')->where('typeid',$typeid)->where('field_id','in',$field_ids)->column('field_id');
                                $channelfield_bind_insert = [];
                                foreach ($channelfield_bind_list as $k => $v) {
                                    //已经写入的不在写入
                                    if (!in_array($v['field_id'], $bind_field_ids)){
                                        $channelfield_bind_insert[] = [
                                            'typeid' => $typeid,
                                            'field_id' => $v['field_id'],
                                            'add_time' => getTime(),
                                            'update_time' => getTime()
                                        ];
                                    }
                                }
                                //写入绑定自定义字段
                                Db::name('channelfield_bind')->insertAll($channelfield_bind_insert);
                            }

                            // 内容扩展表的数据
                            $contentInfo = $contentRow[$val['aid']];
                            $contentInfo['aid'] = $new_aid;

                            // 拥有特性模型的其他数据处理
                            $imgUploadInfo = $imgUploadData = [];
                            $downloadFileInfo = $downloadFileData = [];
                            $mediaFileInfo = $mediaFileData = [];
                            $specialNodeInfo = $specialNodeData = [];
                            $productAttrInfo = $productImgInfo = $productNetdiskInfo = $productSpecInfo = $productSpecValueInfo = $shopProductAttrInfo = [];
                            $productAttrData = $productImgData = $productNetdiskData = $productSpecData = $productSpecValueData = $shopProductAttrData = [];
                            if ('images' == $channeltypeRow[$val['channel']]['nid']) { // 图集模型的特性表数据
                                $imgUploadInfo = !empty($imgUploadRow[$val['aid']]) ? $imgUploadRow[$val['aid']] : [];
                            } else if ('download' == $channeltypeRow[$val['channel']]['nid']) { // 下载模型的特性表数据
                                $downloadFileInfo = !empty($downloadFileRow[$val['aid']]) ? $downloadFileRow[$val['aid']] : [];
                            } else if ('product' == $channeltypeRow[$val['channel']]['nid']) { // 新房模型的特性表数据
                                // 属性值表 - 只复制同栏目的属性值
                                if ($typeid_old == $typeid) {
                                    $productAttrInfo = !empty($productAttrRow[$val['aid']]) ? $productAttrRow[$val['aid']] : [];
                                }
                                // 新商品参数属性值表
                                $shopProductAttrInfo = !empty($shopProductAttrRow[$val['aid']]) ? $shopProductAttrRow[$val['aid']] : [];
                                // 产品图集表
                                $productImgInfo = !empty($productImgRow[$val['aid']]) ? $productImgRow[$val['aid']] : [];
                                // 产品虚拟表
                                $productNetdiskInfo = !empty($productNetdiskRow[$val['aid']]) ? $productNetdiskRow[$val['aid']] : [];
                                // 产品规格数据表
                                $productSpecInfo = !empty($productSpecRow[$val['aid']]) ? $productSpecRow[$val['aid']] : [];
                                // 产品多规格组装表
                                $productSpecValueInfo = !empty($productSpecValueRow[$val['aid']]) ? $productSpecValueRow[$val['aid']] : [];
                            } else if ('media' == $channeltypeRow[$val['channel']]['nid']) { // 视频模型的特性表数据
                                $mediaFileInfo = !empty($mediaFileRow[$val['aid']]) ? $mediaFileRow[$val['aid']] : [];
                            } else if ('special' == $channeltypeRow[$val['channel']]['nid']) { // 专题模型的特性表数据
                                $specialNodeInfo = !empty($specialNodeRow[$val['aid']]) ? $specialNodeRow[$val['aid']] : [];
                            }

                            // 图集模型
                            if ('images' == $channeltypeRow[$val['channel']]['nid']) {
                                foreach ($imgUploadInfo as $img_k => $img_v) {
                                    $img_v['aid'] = $new_aid;
                                    $imgUploadData[] = $img_v;
                                }
                            } else if ('download' == $channeltypeRow[$val['channel']]['nid']) {
                                // 附件表
                                foreach ($downloadFileInfo as $file_k => $file_v) {
                                    $file_v['aid'] = $new_aid;
                                    $downloadFileData[] = $file_v;
                                }
                            } else if ('product' == $channeltypeRow[$val['channel']]['nid']) {
                                // 属性值表
                                foreach ($productAttrInfo as $attr_k => $attr_v) {
                                    $attr_v['aid'] = $new_aid;
                                    $productAttrData[] = $attr_v;
                                }
                                // 产品图集表
                                foreach ($productImgInfo as $img_k => $img_v) {
                                    $img_v['aid'] = $new_aid;
                                    $productImgData[] = $img_v;
                                }
                                // 产品虚拟表
                                foreach ($productNetdiskInfo as $nd_k => $nd_v) {
                                    $nd_v['aid'] = $new_aid;
                                    $productNetdiskData[] = $nd_v;
                                }
                                // 产品规格数据表
                                foreach ($productSpecInfo as $spec_k => $spec_v) {
                                    $spec_v['aid'] = $new_aid;
                                    $productSpecData[] = $spec_v;
                                }
                                // 产品多规格组装表
                                foreach ($productSpecValueInfo as $specv_k => $specv_v) {
                                    $specv_v['aid'] = $new_aid;
                                    $productSpecValueData[] = $specv_v;
                                }
                                // 新增商品参数属性值表
                                foreach ($shopProductAttrInfo as $attr_k => $attr_v) {
                                    $attr_v['aid'] = $new_aid;
                                    if (!empty($language_attr['shop_product_attribute']['attribute_'.$attr_v['attr_id']])) {
                                        $attr_v['attr_id'] = $language_attr['shop_product_attribute']['attribute_'.$attr_v['attr_id']];
                                    }
                                    $shopProductAttrData[] = $attr_v;
                                }
                            } else if ('media' == $channeltypeRow[$val['channel']]['nid']) {
                                // 附件表
                                foreach ($mediaFileInfo as $file_k => $file_v) {
                                    $file_v['aid'] = $new_aid;
                                    $mediaFileData[] = $file_v;
                                }
                            } else if ('special' == $channeltypeRow[$val['channel']]['nid']) {
                                // 附件表
                                foreach ($specialNodeInfo as $node_k => $node_v) {
                                    $node_v['aid'] = $new_aid;
                                    $node_v['lang'] = $lang;
                                    $specialNodeData[] = $node_v;
                                }
                            }

                            // 批量写入内容扩展表
                            if (!empty($contentInfo)) {
                                Db::name($tableExt)->insert($contentInfo);
                            }
                            // 批量写入图集模型的图片表
                            if ('images' == $channeltypeRow[$val['channel']]['nid']) {
                                !empty($imgUploadData) && model('ImagesUpload')->saveAll($imgUploadData);
                            } else if ('download' == $channeltypeRow[$val['channel']]['nid']) {
                                // 附件表
                                !empty($downloadFileData) && model('DownloadFile')->saveAll($downloadFileData);
                            } else if ('product' == $channeltypeRow[$val['channel']]['nid']) {
                                // 属性值表
                                !empty($productAttrData) && Db::name('product_attr')->insertAll($productAttrData);
                                // 产品图集表
                                !empty($productImgData) && model('ProductImg')->saveAll($productImgData);
                                // 产品虚拟表
                                !empty($productNetdiskData) && model('ProductNetdisk')->saveAll($productNetdiskData);
                                // 产品规格数据表
                                !empty($productSpecData) && model('ProductSpecData')->saveAll($productSpecData);
                                // 产品多规格组装表
                                !empty($productSpecValueData) && model('ProductSpecValue')->saveAll($productSpecValueData);
                                // 新商品参数属性值表
                                !empty($shopProductAttrData) && Db::name('shop_product_attr')->insertAll($shopProductAttrData);
                            } else if ('media' == $channeltypeRow[$val['channel']]['nid']) {
                                // 附件表
                                !empty($mediaFileData) && model('MediaFile')->saveAll($mediaFileData);
                            } else if ('special' == $channeltypeRow[$val['channel']]['nid']) {
                                // 专题节点表
                                !empty($specialNodeData) && model('SpecialNode')->saveAll($specialNodeData);
                            }
                        } else {
                            $error_aid[] = $aid;
                            adminLog("文档{$aid}同步失败");
                        }
                    }
                }
            }

            //处理专题节点
            $specialNodeList = Db::name('special_node')->where('lang',$lang)->field('node_id,aidlist')->select();
            if (!empty($specialNodeList)){
                $updateSpecialNodeList = [];
                foreach ($specialNodeList as $k => $v){
                    if (!empty($v['aidlist'])) {
                        $aidlist = [];
                        $v['aidlist'] = explode(',',$v['aidlist']);
                        foreach ($v['aidlist'] as $m => $n){
                            if (!empty($compare_aid[$n])) $aidlist[] = $compare_aid[$n];
                        }
                        if (!empty($aidlist)) {
                            $aidlist = implode(',',$aidlist);
                        }else{
                            $aidlist = '';
                        }
                        $updateSpecialNodeList[] = ['node_id'=>$v['node_id'],'aidlist'=>$aidlist];
                    }
                }
                !empty($updateSpecialNodeList) && model('SpecialNode')->saveAll($updateSpecialNodeList);
            }

            //同步栏目内容
            $single_arc = Db::name('archives')->where(['lang' => 'cn', 'is_del' => 0,'channel'=>6])->select();
            $lang_single_arc = Db::name('archives')->where(['lang' => $lang, 'is_del' => 0,'channel'=>6])->field('aid,typeid')->getAllWithIndex('typeid');
            if (!empty($single_arc)){
                $aids = get_arr_column($single_arc,'aid');
                $typeids = get_arr_column($single_arc,'typeid');
                $contentRow = Db::name('single_content')->field('id', true)->where(['aid' => ['IN', $aids]])->getAllWithIndex('aid');
                $typeids_arr = [];
                foreach ($typeids as $key => $val){
                    $typeids_arr[] = 'tid' . $val;
                }
                $lang_typeids = Db::name('language_attr')->where(['attr_group' => 'arctype', 'attr_name' => ['in',$typeids_arr],'lang'=>$lang])->field('attr_value,attr_name')->getAllWithIndex('attr_name');

                foreach ($contentRow as $key => $val) {
                    //多语言栏目id
                    $typeid = $lang_typeids['tid' . $val['typeid']]['attr_value'];
                    $lang_aid = $lang_single_arc[$typeid]['aid'];
                    Db::name('single_content')->where('aid',$lang_aid)->update(['content'=>$val['content'],'content_ey_m'=>$val['content_ey_m']]);
                }
            }

            /*清空sql_cache_table数据缓存表 并 添加查询执行语句到mysql缓存表*/
            Db::execute('TRUNCATE TABLE ' . config('database.prefix') . 'sql_cache_table');
            model('SqlCacheTable')->InsertSqlCacheTable(true);
            /* END */
        }
        return true;
    }

    public function getRestricTypeText($restricType = 0)
    {
        $restricTypeText = '免费';
        if (1 === intval($restricType)) {
            $restricTypeText = '付费';
        } else if (2 === intval($restricType)) {
            $restricTypeText = '指定会员';
        } else if (3 === intval($restricType)) {
            $restricTypeText = '会员付费';
        }
        return $restricTypeText;
    }
    /**
     * 同步新文档
     * @param $lang 操作语言
     * @param $is_jump 
     * 20241119
     */
    public function batch_copy_part($lang = '',$is_jump = 0){
        if (empty($lang)) return false;
        //新建数据开始
        $channel_ids = Db::name('archives')->where(['lang' => 'cn', 'is_del' => 0,'channel'=>['neq',6]])->group('channel')->column('channel');
        // 获取复制栏目的模型ID
        $channeltypeRow = Db::name('channeltype')->field('id,nid,table')
            ->where([
                'id' => ['in', $channel_ids],
            ])->order('id asc')->getAllWithIndex('id');
        if (!empty($channeltypeRow)) {
            $error_aid = [];
            foreach ($channeltypeRow as $ck => $cv) {                
                $out_oldids = $this->langcopylog->field('oldid')->where(['lang'=>$lang,'typeid'=>$cv['id']])->column('oldid');
                $archivesRow = Db::name('archives')->where(['lang' => 'cn', 'is_del' => 0,'channel'=>$ck,'aid'=>['not in',$out_oldids]])->select();
                unset($out_oldids);
                if (!empty($is_jump)){
                    $titles = get_arr_column($archivesRow,'title');
                    $repeat_title_arr = Db::name('archives')->where(['lang' => $lang, 'is_del' => 0,'channel'=>$ck])->where('title','in',$titles)->column('title');
                    if (!empty($repeat_title_arr)){
                        foreach ($archivesRow as $m => $n){
                            if (in_array($n['title'],$repeat_title_arr)) unset($archivesRow[$m]);
                        }
                        if (!empty($archivesRow)) {
                            $archivesRow = array_merge($archivesRow);
                        }
                    }
                }
                if (empty($archivesRow)) continue;
                $aids = $typeids = [];
                foreach ($archivesRow as $key => $val) {
                    if (!in_array($val['aid'], $aids)) $aids[] = $val['aid'];
                    if (!in_array($val['typeid'], $typeids)) $typeids[] = $val['typeid'];
                }
                // 内容扩展表数据
                $tableExt = $cv['table'] . "_content";
                $contentRow = Db::name($tableExt)->field('id', true)->where(['aid' => ['IN', $aids]])->getAllWithIndex('aid');
                // 原语言栏目
                $typeids_arr = [];
                foreach ($typeids as $k => $v){
                    $typeids_arr[] = 'tid' . $v;
                }
                // 源语言关联的多语言栏目
                $lang_typeids = Db::name('language_attr')->where(['attr_group' => 'arctype', 'attr_name' => ['in',$typeids_arr],'lang'=>$lang])->field('attr_value,attr_name')->getAllWithIndex('attr_name');
                // 拥有特性模型的其他数据处理

                if ('images' == $channeltypeRow[$val['channel']]['nid']) { // 图集模型的特性表数据
                    $imgUploadRow = Db::name('images_upload')->field('img_id', true)->where(['aid' => ['IN', $aids]])->select();
                    $imgUploadRow = group_same_key($imgUploadRow, 'aid');
                } else if ('download' == $channeltypeRow[$val['channel']]['nid']) { // 下载模型的特性表数据
                    // 附件表
                    $downloadFileRow = Db::name('download_file')->field('file_id', true)->where(['aid' => ['IN', $aids]])->select();
                    $downloadFileRow = group_same_key($downloadFileRow, 'aid');
                } else if ('product' == $channeltypeRow[$val['channel']]['nid']) { // 产品模型的特性表数据
                    // 属性值表
                    $productAttrRow = Db::name('product_attr')->field('product_attr_id', true)->where(['aid' => ['IN', $aids]])->select();
                    $productAttrRow = group_same_key($productAttrRow, 'aid');
                    // 产品图集表
                    $productImgRow = Db::name('product_img')->field('img_id', true)->where(['aid' => ['IN', $aids]])->select();
                    $productImgRow = group_same_key($productImgRow, 'aid');
                    // 产品虚拟表
                    $productNetdiskRow = Db::name('product_netdisk')->field('nd_id', true)->where(['aid' => ['IN', $aids]])->select();
                    $productNetdiskRow = group_same_key($productNetdiskRow, 'aid');
                    // 产品规格数据表
                    $productSpecRow = Db::name('product_spec_data')->field('spec_id', true)->where(['aid' => ['IN', $aids]])->select();
                    $productSpecRow = group_same_key($productSpecRow, 'aid');
                    // 产品多规格组装表
                    $productSpecValueRow = Db::name('product_spec_value')->field('value_id', true)->where(['aid' => ['IN', $aids]])->select();
                    $productSpecValueRow = group_same_key($productSpecValueRow, 'aid');
                    // 新商品参数属性值表
                    $shopProductAttrRow = Db::name('shop_product_attr')->field('product_attr_id', true)->where(['aid' => ['IN', $aids]])->select();
                    $shopProductAttrRow = group_same_key($shopProductAttrRow, 'aid');
                    // 多语言关联
                    $language_attr = [];
                    $row = Db::name('language_attr')->where(['lang'=>$lang])->select();
                    foreach ($row as $_atk => $_atv) {
                        $language_attr[$_atv['attr_group']][$_atv['attr_name']] = $_atv['attr_value'];
                    }
                } else if ('media' == $channeltypeRow[$val['channel']]['nid']) { // 视频模型的特性表数据
                    // 附件表
                    $mediaFileRow = Db::name('media_file')->field('file_id', true)->where(['aid' => ['IN', $aids]])->select();
                    $mediaFileRow = group_same_key($mediaFileRow, 'aid');
                } else if ('special' == $channeltypeRow[$val['channel']]['nid']) { // 专题模型的特性表数据
                    // 节点表
                    $specialNodeRow = Db::name('special_node')->field('node_id', true)->where(['aid' => ['IN', $aids]])->select();
                    $specialNodeRow = group_same_key($specialNodeRow, 'aid');
                }
                foreach ($archivesRow as $key => $val) {
                    // 原先数据的栏目ID
                    $typeid_old = $val['typeid'];
                    //多语言栏目id
                    $typeid =  $lang_typeids['tid'.$val['typeid']]['attr_value'];
                    // 只同步相关联的栏目
                    if (empty($typeid)) {
                        continue;
                    }
                    $aid = $val['aid'];
                    $archivesInfo = $val;
                    unset($archivesInfo['aid']);
                    $archivesInfo['typeid'] = $typeid;
                    $archivesInfo['lang'] = $lang;
                    // $archivesInfo['add_time'] = getTime();
                    // $archivesInfo['update_time'] = getTime();
                    if (!empty($language_attr['shop_product_attrlist']['attrlist_'.$archivesInfo['attrlist_id']])) {
                        $archivesInfo['attrlist_id'] = $language_attr['shop_product_attrlist']['attrlist_'.$archivesInfo['attrlist_id']];
                    }
                    if (!empty($archivesInfo)){
                        if(!$this->langcopylog->field('id')->where(['oldid'=>$aid,'lang'=>$lang])->find()){
                            $new_aid = Db::name('archives')->insertGetId($archivesInfo);                        
                            if ($new_aid) {                            
                                // 记录更新log
                                $this->langcopylog->insert(['channel'=>$ck,'typeid'=>$typeid_old,'new_typeid'=>$typeid,'oldid'=>$aid,'newid'=>$new_aid,'lang'=>$lang,'add_time'=>time()]);
                                $compare_aid[$aid] = $new_aid;
                                $channelfield_bind_list = Db::name('channelfield_bind')->where('typeid',$typeid_old)->select();
                                if (!empty($channelfield_bind_list)){
                                    $field_ids = get_arr_column($channelfield_bind_list,'field_id');
                                    //查询已经写入的
                                    $bind_field_ids = Db::name('channelfield_bind')->where('typeid',$typeid)->where('field_id','in',$field_ids)->column('field_id');
                                    $channelfield_bind_insert = [];
                                    foreach ($channelfield_bind_list as $k => $v) {
                                        //已经写入的不在写入
                                        if (!in_array($v['field_id'], $bind_field_ids)){
                                            $channelfield_bind_insert[] = [
                                                'typeid' => $typeid,
                                                'field_id' => $v['field_id'],
                                                'add_time' => getTime(),
                                                'update_time' => getTime()
                                            ];
                                        }
                                    }
                                    //写入绑定自定义字段
                                    Db::name('channelfield_bind')->insertAll($channelfield_bind_insert);
                                }

                                // 内容扩展表的数据
                                $contentInfo = $contentRow[$val['aid']];
                                $contentInfo['aid'] = $new_aid;

                                // 拥有特性模型的其他数据处理
                                $imgUploadInfo = $imgUploadData = [];
                                $downloadFileInfo = $downloadFileData = [];
                                $mediaFileInfo = $mediaFileData = [];
                                $specialNodeInfo = $specialNodeData = [];
                                $productAttrInfo = $productImgInfo = $productNetdiskInfo = $productSpecInfo = $productSpecValueInfo = $shopProductAttrInfo = [];
                                $productAttrData = $productImgData = $productNetdiskData = $productSpecData = $productSpecValueData = $shopProductAttrData = [];
                                if ('images' == $channeltypeRow[$val['channel']]['nid']) { // 图集模型的特性表数据
                                    $imgUploadInfo = !empty($imgUploadRow[$val['aid']]) ? $imgUploadRow[$val['aid']] : [];
                                } else if ('download' == $channeltypeRow[$val['channel']]['nid']) { // 下载模型的特性表数据
                                    $downloadFileInfo = !empty($downloadFileRow[$val['aid']]) ? $downloadFileRow[$val['aid']] : [];
                                } else if ('product' == $channeltypeRow[$val['channel']]['nid']) { // 新房模型的特性表数据
                                    // 属性值表 - 只复制同栏目的属性值
                                    if ($typeid_old == $typeid) {
                                        $productAttrInfo = !empty($productAttrRow[$val['aid']]) ? $productAttrRow[$val['aid']] : [];
                                    }
                                    // 新商品参数属性值表
                                    $shopProductAttrInfo = !empty($shopProductAttrRow[$val['aid']]) ? $shopProductAttrRow[$val['aid']] : [];
                                    // 产品图集表
                                    $productImgInfo = !empty($productImgRow[$val['aid']]) ? $productImgRow[$val['aid']] : [];
                                    // 产品虚拟表
                                    $productNetdiskInfo = !empty($productNetdiskRow[$val['aid']]) ? $productNetdiskRow[$val['aid']] : [];
                                    // 产品规格数据表
                                    $productSpecInfo = !empty($productSpecRow[$val['aid']]) ? $productSpecRow[$val['aid']] : [];
                                    // 产品多规格组装表
                                    $productSpecValueInfo = !empty($productSpecValueRow[$val['aid']]) ? $productSpecValueRow[$val['aid']] : [];
                                } else if ('media' == $channeltypeRow[$val['channel']]['nid']) { // 视频模型的特性表数据
                                    $mediaFileInfo = !empty($mediaFileRow[$val['aid']]) ? $mediaFileRow[$val['aid']] : [];
                                } else if ('special' == $channeltypeRow[$val['channel']]['nid']) { // 专题模型的特性表数据
                                    $specialNodeInfo = !empty($specialNodeRow[$val['aid']]) ? $specialNodeRow[$val['aid']] : [];
                                }

                                // 图集模型
                                if ('images' == $channeltypeRow[$val['channel']]['nid']) {
                                    foreach ($imgUploadInfo as $img_k => $img_v) {
                                        $img_v['aid'] = $new_aid;
                                        $imgUploadData[] = $img_v;
                                    }
                                } else if ('download' == $channeltypeRow[$val['channel']]['nid']) {
                                    // 附件表
                                    foreach ($downloadFileInfo as $file_k => $file_v) {
                                        $file_v['aid'] = $new_aid;
                                        $downloadFileData[] = $file_v;
                                    }
                                } else if ('product' == $channeltypeRow[$val['channel']]['nid']) {
                                    // 属性值表
                                    foreach ($productAttrInfo as $attr_k => $attr_v) {
                                        $attr_v['aid'] = $new_aid;
                                        $productAttrData[] = $attr_v;
                                    }
                                    // 产品图集表
                                    foreach ($productImgInfo as $img_k => $img_v) {
                                        $img_v['aid'] = $new_aid;
                                        $productImgData[] = $img_v;
                                    }
                                    // 产品虚拟表
                                    foreach ($productNetdiskInfo as $nd_k => $nd_v) {
                                        $nd_v['aid'] = $new_aid;
                                        $productNetdiskData[] = $nd_v;
                                    }
                                    // 产品规格数据表
                                    foreach ($productSpecInfo as $spec_k => $spec_v) {
                                        $spec_v['aid'] = $new_aid;
                                        $productSpecData[] = $spec_v;
                                    }
                                    // 产品多规格组装表
                                    foreach ($productSpecValueInfo as $specv_k => $specv_v) {
                                        $specv_v['aid'] = $new_aid;
                                        $productSpecValueData[] = $specv_v;
                                    }
                                    // 新增商品参数属性值表
                                    foreach ($shopProductAttrInfo as $attr_k => $attr_v) {
                                        $attr_v['aid'] = $new_aid;
                                        if (!empty($language_attr['shop_product_attribute']['attribute_'.$attr_v['attr_id']])) {
                                            $attr_v['attr_id'] = $language_attr['shop_product_attribute']['attribute_'.$attr_v['attr_id']];
                                        }
                                        $shopProductAttrData[] = $attr_v;
                                    }
                                } else if ('media' == $channeltypeRow[$val['channel']]['nid']) {
                                    // 附件表
                                    foreach ($mediaFileInfo as $file_k => $file_v) {
                                        $file_v['aid'] = $new_aid;
                                        $mediaFileData[] = $file_v;
                                    }
                                } else if ('special' == $channeltypeRow[$val['channel']]['nid']) {
                                    // 附件表
                                    foreach ($specialNodeInfo as $node_k => $node_v) {
                                        $node_v['aid'] = $new_aid;
                                        $node_v['lang'] = $lang;
                                        $specialNodeData[] = $node_v;
                                    }
                                }

                                // 批量写入内容扩展表
                                if (!empty($contentInfo)) {
                                    Db::name($tableExt)->insert($contentInfo);
                                }
                                // 批量写入图集模型的图片表
                                if ('images' == $channeltypeRow[$val['channel']]['nid']) {
                                    !empty($imgUploadData) && model('ImagesUpload')->saveAll($imgUploadData);
                                } else if ('download' == $channeltypeRow[$val['channel']]['nid']) {
                                    // 附件表
                                    !empty($downloadFileData) && model('DownloadFile')->saveAll($downloadFileData);
                                } else if ('product' == $channeltypeRow[$val['channel']]['nid']) {
                                    // 属性值表
                                    !empty($productAttrData) && Db::name('product_attr')->insertAll($productAttrData);
                                    // 产品图集表
                                    !empty($productImgData) && model('ProductImg')->saveAll($productImgData);
                                    // 产品虚拟表
                                    !empty($productNetdiskData) && model('ProductNetdisk')->saveAll($productNetdiskData);
                                    // 产品规格数据表
                                    !empty($productSpecData) && model('ProductSpecData')->saveAll($productSpecData);
                                    // 产品多规格组装表
                                    !empty($productSpecValueData) && model('ProductSpecValue')->saveAll($productSpecValueData);
                                    // 新商品参数属性值表
                                    !empty($shopProductAttrData) && Db::name('shop_product_attr')->insertAll($shopProductAttrData);
                                } else if ('media' == $channeltypeRow[$val['channel']]['nid']) {
                                    // 附件表
                                    !empty($mediaFileData) && model('MediaFile')->saveAll($mediaFileData);
                                } else if ('special' == $channeltypeRow[$val['channel']]['nid']) {
                                    // 专题节点表
                                    !empty($specialNodeData) && model('SpecialNode')->saveAll($specialNodeData);
                                }
                            } else {
                                $error_aid[] = $aid;
                                adminLog("文档{$aid}同步失败");
                            }
                        }
                    }
                }                
            }            
        }
        /*清空sql_cache_table数据缓存表 并 添加查询执行语句到mysql缓存表*/
        Db::execute('TRUNCATE TABLE ' . config('database.prefix') . 'sql_cache_table');
        model('SqlCacheTable')->InsertSqlCacheTable(true);
        /* END */
    }   
}
