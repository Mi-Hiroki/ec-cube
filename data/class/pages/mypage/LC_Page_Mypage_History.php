<?php
/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) 2000-2007 LOCKON CO.,LTD. All Rights Reserved.
 *
 * http://www.lockon.co.jp/
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 */

// {{{ requires
require_once(CLASS_PATH . "pages/LC_Page.php");

/**
 * 購入履歴 のページクラス.
 *
 * @package Page
 * @author LOCKON CO.,LTD.
 * @version $Id$
 */
class LC_Page_Mypage_History extends LC_Page {

    // }}}
    // {{{ functions

    /**
     * Page を初期化する.
     *
     * @return void
     */
    function init() {
        parent::init();
        $this->tpl_mainpage = TEMPLATE_DIR . 'mypage/history.tpl';
        $this->tpl_title = 'MYページ';
        $this->tpl_subtitle = '購入履歴詳細';
        $this->tpl_navi = TEMPLATE_DIR . 'mypage/navi.tpl';
        $this->tpl_column_num = 1;
        $this->tpl_mainno = 'mypage';
        $this->tpl_mypageno = 'index';
        $this->httpCacheControl('nocache');
        $masterData = new SC_DB_MasterData_Ex();
        $this->arrMAILTEMPLATE = $masterData->getMasterData("mtb_mail_template");
        $this->arrPref = $masterData->getMasterData("mtb_pref", array("pref_id", "pref_name", "rank"));
   }

    /**
     * Page のプロセス.
     *
     * @return void
     */
    function process() {
        $objView = new SC_SiteView();
        $objQuery = new SC_Query();
        $objCustomer = new SC_Customer();
        $objDb = new SC_Helper_DB_Ex();

        // レイアウトデザインを取得
        $objLayout = new SC_Helper_PageLayout_Ex();
        $objLayout->sfGetPageLayout($this, false, "mypage/index.php");

        // FIXME 他の画面と同様のバリデーションを行なう
        if (!SC_Utils_Ex::sfIsInt($_GET['order_id'])) {
            SC_Utils_Ex::sfDispException();
        }

        $orderId = $_GET['order_id'];

        //不正アクセス判定
        $from = "dtb_order";
        $where = "del_flg = 0 AND customer_id = ? AND order_id = ? ";
        $arrval = array($objCustomer->getValue('customer_id'), $orderId);
        //DBに情報があるか判定
        $cnt = $objQuery->count($from, $where, $arrval);
        //ログインしていない、またはDBに情報が無い場合
        if (!$objCustomer->isLoginSuccess() || $cnt == 0){
            SC_Utils_Ex::sfDispSiteError(CUSTOMER_ERROR);
        }

        //受注詳細データの取得
        $this->arrDisp = $this->lfGetOrderData($orderId);
        // 支払い方法の取得
        $this->arrPayment = $objDb->sfGetIDValueList("dtb_payment", "payment_id", "payment_method");
        // お届け時間の取得
        $arrRet = $objDb->sfGetDelivTime($this->arrDisp['payment_id']);
        $this->arrDelivTime = SC_Utils_Ex::sfArrKeyValue($arrRet, 'time_id', 'deliv_time');

        //マイページトップ顧客情報表示用
        $this->CustomerName1 = $objCustomer->getvalue('name01');
        $this->CustomerName2 = $objCustomer->getvalue('name02');
        $this->CustomerPoint = $objCustomer->getvalue('point');

        // 受注商品明細の取得
        $this->tpl_arrOrderDetail = $this->lfGetOrderDetail($orderId);

        // 受注メール送信履歴の取得
        $this->tpl_arrMailHistory = $this->lfGetMailHistory($orderId);

        $objView->assignobj($this);
        $objView->display(SITE_FRAME);
    }

    /**
     * デストラクタ.
     *
     * @return void
     */
    function destroy() {
        parent::destroy();
    }

    /**
     * モバイルページを初期化する.
     *
     * @return void
     */
    function mobileInit() {
        $this->tpl_mainpage = MOBILE_TEMPLATE_DIR . 'mypage/history.tpl';
        $this->tpl_title = 'MYページ/購入履歴一覧';
        $this->httpCacheControl('nocache');
    }

    /**
     * Page のプロセス(モバイル).
     *
     * @return void
     */
    function mobileProcess() {
        define ("HISTORY_NUM", 5);

        $objView = new SC_MobileView();
        $objQuery = new SC_Query();
        $objCustomer = new SC_Customer();
        $pageNo = isset($_GET['pageno']) ? (int) $_GET['pageno'] : 0; // TODO

        // ログインチェック
        if(!isset($_SESSION['customer'])) {
            SC_Utils_Ex::sfDispSiteError(CUSTOMER_ERROR);
        }

        $col = "order_id, create_date, payment_id, payment_total";
        $from = "dtb_order";
        $where = "del_flg = 0 AND customer_id=?";
        $arrval = array($objCustomer->getvalue('customer_id'));
        $order = "order_id DESC";

        $linemax = $objQuery->count($from, $where, $arrval);
        $this->tpl_linemax = $linemax;

        // 取得範囲の指定(開始行番号、行数のセット)
        $objQuery->setlimitoffset(HISTORY_NUM, $pageNo);
        // 表示順序
        $objQuery->setorder($order);

        //購入履歴の取得
        $this->arrOrder = $objQuery->select($col, $from, $where, $arrval);

        // next
        if ($pageNo + HISTORY_NUM < $linemax) {
            $next = "<a href='history.php?pageno=" . ($pageNo + HISTORY_NUM) . "'>次へ→</a>";
        } else {
            $next = "";
        }

        // previous
        if ($pageNo - HISTORY_NUM > 0) {
            $previous = "<a href='history.php?pageno=" . ($pageNo - HISTORY_NUM) . "'>←前へ</a>";
        } elseif ($pageNo == 0) {
            $previous = "";
        } else {
            $previous = "<a href='history.php?pageno=0'>←前へ</a>";
        }

        // bar
        if ($next != '' && $previous != '') {
            $bar = " | ";
        } else {
            $bar = "";
        }

        $this->tpl_strnavi = $previous . $bar . $next;
        $objView->assignobj($this);				//$objpage内の全てのテンプレート変数をsmartyに格納
        $objView->display(SITE_FRAME);				//パスとテンプレート変数の呼び出し、実行
    }

    /**
     * 受注の取得
     *
     * @param integer $orderId 注文番号
     * @return array 受注の内容
     */
    function lfGetOrderData($orderId) {
        // DBから受注情報を読み込む
        $objQuery = new SC_Query();
        $col = "order_id, create_date, payment_id, subtotal, tax, use_point, add_point, discount, ";
        $col .= "deliv_fee, charge, payment_total, deliv_name01, deliv_name02, deliv_kana01, deliv_kana02, ";
        $col .= "deliv_zip01, deliv_zip02, deliv_pref, deliv_addr01, deliv_addr02, deliv_tel01, deliv_tel02, deliv_tel03, deliv_time_id, deliv_date ";
        $from = "dtb_order";
        $where = "order_id = ?";
        $arrRet = $objQuery->select($col, $from, $where, array($orderId));
        return $arrRet[0];
    }

    /**
     * 受注商品明細の取得
     *
     * @param integer $orderId 注文番号
     * @return array 受注商品明細の内容
     */
    function lfGetOrderDetail($orderId) {
        $objQuery = new SC_Query();
        $col = "product_id, product_code, product_name, classcategory_name1, classcategory_name2, price, quantity, point_rate";
        $col .= ",CASE WHEN EXISTS(SELECT * FROM dtb_products WHERE product_id = dtb_order_detail.product_id AND del_flg = 0 AND status = 1) THEN '1' ELSE '0' END AS enable";
        $where = "order_id = ?";
        $objQuery->setorder("classcategory_id1, classcategory_id2");
        $arrRet = $objQuery->select($col, "dtb_order_detail", $where, array($orderId));
        return $arrRet;
    }

    /**
     * 受注メール送信履歴の取得
     *
     * @param integer $orderId 注文番号
     * @return array 受注メール送信履歴の内容
     */
    function lfGetMailHistory($orderId) {
        $objQuery = new SC_Query();
        $col = 'send_date, subject, template_id, send_id';
        $where = 'order_id = ?';
        $objQuery->setorder('send_date DESC');
        $this->arrMailHistory = $objQuery->select($col, 'dtb_mail_history', $where, array($orderId));
    }
}
?>
