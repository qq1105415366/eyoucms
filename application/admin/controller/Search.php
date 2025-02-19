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

namespace app\admin\controller;

use think\Db;
use think\Page;
use think\Cache;

class Search extends Base
{
    private $searchword_db;

    public function _initialize() {
        parent::_initialize();
        $this->searchword_db = Db::name('search_word');
    }

    /**
     * 搜索主页
     */
    public function index()
    {
        $list = array();
        $param = input('param.');
        $keywords = input('keywords/s');
        $keywords = trim($keywords);
        $condition = [];

        foreach (['keywords'] as $key) {
            if (isset($param[$key]) && $param[$key] !== '') {
                if ($key == 'keywords') {
                    $condition['word'] = array('LIKE', "%{$keywords}%");
                } else {
                    $condition[''.$key] = array('eq', trim($param[$key]));
                }
            }
        }

        $condition['lang'] = array('eq', $this->admin_lang);

        // 自定义排序
        $orderby = input('param.orderby/s');
        $orderway = input('param.orderway/s');
        if (!empty($orderby) && !empty($orderway)) {
            $orderby = "{$orderby} {$orderway}, id desc";
        } else {
            $orderby = "is_hot desc, id desc";
        }

        $count = $this->searchword_db->where($condition)->count('id');
        $Page = $pager = new Page($count, config('paginate.list_rows'));
        $list = $this->searchword_db->where($condition)->order($orderby)->limit($Page->firstRow.','.$Page->listRows)->select();

        $show = $Page->show();
        $this->assign('page',$show);
        $this->assign('list',$list);
        $this->assign('pager',$pager);
        return $this->fetch();
    }

    public function edit()
    {
        if (IS_AJAX_POST){
            $param = input('param.');
            if (empty($param['id'])){
                $this->error('缺少id');
            }
            $update[$param['field']] = $param['value'];
            $where['lang'] = array('eq', $this->admin_lang);
            $where['id'] = $param['id'];
            $r = $this->searchword_db->where($where)->update($update);
            if (false !== $r){
                $this->success('操作成功');
            }
        }
        $this->error('操作失败');
    }

    public function del()
    {
        if (IS_POST) {
            $id_arr = input('del_id/a');
            $id_arr = eyIntval($id_arr);
            if(!empty($id_arr)){
                $result = $this->searchword_db->field('word')
                    ->where([
                        'id'    => ['IN', $id_arr],
                        'lang'  => $this->admin_lang,
                    ])->select();
                $title_list = get_arr_column($result, 'word');

                $r = $this->searchword_db->where([
                        'id'    => ['IN', $id_arr],
                        'lang'  => $this->admin_lang,
                    ])
                    ->cache(true, null, "search_word")
                    ->delete();
                if($r !== false){
                    adminLog('删除搜索关键词：'.implode(',', $title_list));
                    $this->success('删除成功');
                }
            }
        }
        $this->error('删除失败');
    }

    public function conf()
    {
        if (IS_POST) {
            $param = input('param.');
            $search_tabu_words = htmlspecialchars_decode($param['search_tabu_words']);
            $search_tabu_words = explode(PHP_EOL, $search_tabu_words);
            foreach ($search_tabu_words as $key => $val) {
                $val = trim($val);
                if (!empty($val)) {
                    $search_tabu_words[$key] = $val;
                } else {
                    unset($search_tabu_words[$key]);
                }
            }
            $search_tabu_words = implode(PHP_EOL, $search_tabu_words);

            $data = [
                'search_model'=>$param['search_model'],
                'search_second'=>intval($param['search_second']),
                'search_maxnum'=>intval($param['search_maxnum']),
                'search_locking'=>intval($param['search_locking']),
                'title_word_model'=>intval($param['title_word_model']),
                'search_tabu_words'=>$search_tabu_words,
            ];
            /*多语言*/
            if (is_language()) {
                $langRow = \think\Db::name('language')->order('id asc')
                    ->cache(true, EYOUCMS_CACHE_TIME, 'language')
                    ->select();
                foreach ($langRow as $key => $val) {
                    tpCache('search', $data, $val['mark']);
                }
            } else {
                tpCache('search', $data);
            }
            /*--end*/
            $this->success('操作成功');
        }
        $search = tpCache('search');
        if (!isset($search['search_second'])) {
            $search['search_second'] = 60;
        }
        if (!isset($search['search_maxnum'])) {
            $search['search_maxnum'] = 5;
        }
        if (!isset($search['search_locking'])) {
            $search['search_locking'] = 120;
        }
        if (!isset($search['search_tabu_words'])) {
            $search_tabu_words = ['<','>','"',';',',','@','&'];
            $search['search_tabu_words'] = implode(PHP_EOL, $search_tabu_words);
        }
        $this->assign('search',$search);

        $foreign_is_status = tpSetting('foreign.foreign_is_status', [], 'cn');
        $this->assign('foreign_is_status',$foreign_is_status);

        return $this->fetch();
    }

    public function batch_add()
    {
        if (IS_POST) {
            $post = input('post.');

            $word = trim($post['word']);
            if (empty($word)) {
                $this->error('关键词列表不能为空！');
            }

            $wordArr = explode("\r\n", $word);
            $wordArr = array_filter($wordArr);//去除数组空值
            $wordArr = array_unique($wordArr); //去重
            foreach ($wordArr as $key => $val) {
                $wordArr[$key] = trim($val);
            }

            $addData = [];
            $wordList = Db::name('search_word')->where([
                    'word'  => ['IN', $wordArr],
                    'lang'      => $this->admin_lang,
                ])->column('word');
            foreach ($wordArr as $key => $val) {
                if(empty($val) || in_array($val, $wordList)) continue;

                $addData[] = [
                    'word'               => $val,
                    'searchNum'            => intval($post['searchNum']),
                    'is_hot'            => intval($post['is_hot']),
                    'lang'              => $this->admin_lang,
                    'add_time'          => getTime(),
                    'update_time'       => getTime(),
                ];
            }
            if (!empty($addData)) {
                $r = Db::name('search_word')->insertAll($addData);
                if ($r !== false) {
                    Cache::clear('search_word');
                    adminLog('批量新增关键词：'.get_arr_column($addData, 'word'));
                    $this->success('操作成功！');
                } else {
                    $this->error('操作失败');
                }
            } else {
                $this->success('操作成功！');
            }
        }
        return $this->fetch();
    }

    // AJAX导出搜索关键词Excel文档
    public function ajax_excel_export()
    {
        // 设置最大内存
        ini_set("memory_limit", "-1");

        // 防止php超时
        function_exists('set_time_limit') && set_time_limit(0);

        if (file_exists('./vendor/PHPExcel.zip') && !is_dir('./vendor/PHPExcel/')) {
            $zip = new \ZipArchive();//新建一个ZipArchive的对象
            if ($zip->open(ROOT_PATH.'vendor'.DS.'PHPExcel.zip') === true) {
                $zip->extractTo(ROOT_PATH.'vendor'.DS.'PHPExcel'.DS);
                $zip->close();//关闭处理的zip文件
                if (is_dir('./vendor/PHPExcel/')) {
                    @unlink('./vendor/PHPExcel.zip');
                }
            }
        }

        // 执行操作
        if (IS_AJAX_POST) {
            // 查询条件
            $condition['lang'] = $this->admin_lang;
            $orderby = "is_hot desc, id desc";

            $list = $this->searchword_db->where($condition)->order($orderby)->select();

            if (empty($list)) $this->error('没有导出的数据');

            // 处理订单导出数据
            $ExcelData = [];
            foreach ($list as $key => $value) {
                // 拼装追加数据
                $PushData = [
                    // 订单信息
                    'id'   => $value['id'],
                    'word'    => $value['word'],
                    'searchNum'    => $value['searchNum'],
                    'is_hot' => !empty($value['is_hot']) ? '是' : '否',
                    'resultNum'     => $value['resultNum'],
                    'update_time' => date('Y-m-d H:i:s', $value['update_time']),
                ];
                // 追加数据，用于导出
                array_push($ExcelData, $PushData);
            }
            // 导出字段设置
            $ExcelField = ['id', 'word', 'searchNum', 'is_hot', 'resultNum', 'update_time'];
            // 导出标题设置
            $ExcelTitle = ['ID', '关键词', '搜索次数', '是否热搜', '搜索结果数量', '最后搜索时间'];

            $ResultUrl = $this->PerformExport($ExcelData, $ExcelField, $ExcelTitle);
        }

        if (!empty($ResultUrl)) {
            $this->success('正在下载', $ResultUrl);
        } else {
            $this->error('导出失败');
        }
    }

    private function PerformExport($ExcelData = [], $ExcelField = [], $ExcelTitle = [])
    {
        // 引入SDK
        vendor("PHPExcel.Classes.PHPExcel");

        // Excel表格坐标
        $cell_arr = ['A','B','C','D','E','F','G','H','I','J','K','L','M', 'N','O','P','Q','R','S','T','U','V','W','X','Y','Z'];

        // 执行导出
        $objPHPExcel = new \PHPExcel();
        $objPHPExcel->getDefaultStyle()->getFont()->setName('Arial')->setSize(12);

        // 设置表格标题栏长度
        $objActSheet = $objPHPExcel->getActiveSheet();
        $objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(8);
        $objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(30);
        $objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(8);
        $objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(8);
        $objPHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(8);
        $objPHPExcel->getActiveSheet()->getColumnDimension('F')->setWidth(18);
        // 设置导出表格名称
        $FileName = 'search-word-'.date("YmdHis");

        // 循环设置表格标题栏数据
        $startRow = 1;
        if(!empty($ExcelTitle) || count($ExcelTitle) > 0) {
            foreach($ExcelTitle as $k => $v) {
                $objActSheet->setCellValue($cell_arr[$k] . $startRow, $v);
            }
            $startRow = 2;
        }

        // 循环设置表格字段内容数据
        foreach($ExcelData as $v) {
            foreach($ExcelField as $key => $value) {
                $objActSheet->setCellValue($cell_arr[$key] . $startRow, $v[$value]);
            }
            $startRow++;
        }

        $objPHPExcel->setActiveSheetIndex(0);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $FileName . '.xlsx"');
        header('Cache-Control: max-age=0');
        header('Cache-Control: max-age=1');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
        header('Cache-Control: cache, must-revalidate');
        header('Pragma: public');
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');

        // 文件目录
        $ExcelPath = UPLOAD_PATH . 'excel/';
        // 保存前清空删除原先的excel
        delFile(UPLOAD_PATH . 'excel/', true);
        // 创建文件夹
        @mkdir($ExcelPath, 0777, true);
        // excel文件路径
        $filePath = $ExcelPath . $FileName . '.xlsx';
        // 保存excel文件
        $objWriter->save($filePath);
        // 返回excel文件路径到AJAX下载
        return request()->domain() . ROOT_DIR . '/' . $filePath;
    }
}

