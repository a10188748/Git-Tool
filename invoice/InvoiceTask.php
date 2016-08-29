<?php
//===== 盟立取號機制 =====
define("_CRONJOB_", true);
define("PATH", dirname(dirname(dirname(dirname(__FILE__)))));
require(PATH . "/public/index.php");


//===== 取config =====
if (!empty($_SERVER["argc"][1])) {
    if ($_SERVER["argc"][1] == "local") {
        $settings = new Zend_Config_Ini(APPLICATION_PATH."/configs/application.ini", "stasettings");
    } elseif ($_SERVER["argc"][1] == "test") {
        $settings = new Zend_Config_Ini(APPLICATION_PATH."/configs/application.ini", "testsettings");
    }
} else {
    $settings = new Zend_Config_Ini(APPLICATION_PATH."/configs/application.ini", "settings");
}
$settings = $settings->toArray();
$sellerId = $settings["invoicenew"]["sellerid"];
$posId = $settings["invoicenew"]["posId"];
$posSn = $settings["invoicenew"]["posSn"];
$url551 = $settings["invoicenew"]["url"];
$checkinvoice = new Table_InvoiceNew();
$invoiceobj = $checkinvoice->checkUsefulInvoice();
if($invoiceobj['invoice_number']>=600)
{
	echo "發票張數足夠";
	exit;
}

//===== 取發票開始 =====
$failCount = 0;
$failMsg = array();
$okCount = 0; // 每次排程取十二個區間  一個區間50張  共600張
while($failCount <= 10 && $okCount < 12){
	//==== 湊出aryxml ====
	$arrXml = array(
		"FUNCTIONCODE" => "A01",
		"SELLERID" => $sellerId,
		"POSID" => $posId,
		"POSSN" => $posSn,	
		"SYSTIME" => DATE("Y-m-d H:i:s")
	);

	//====湊出xml====
	$strXml ="<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    $strXml .= "<INDEX>\n";
    if(is_array($arrXml)){
        foreach($arrXml as $key => $value) {
            $strXml .= "<".$key.">".$value."</".$key.">"."\n";
        }
    }
    $strXml .="</INDEX>";

    //====call api====
    $objCurl = curl_init($url551);
	curl_setopt($objCurl,CURLOPT_POST,1);
	curl_setopt($objCurl,CURLOPT_RETURNTRANSFER,1);
	curl_setopt($objCurl,CURLOPT_HTTP_VERSION,CURL_HTTP_VERSION_1_0);
	curl_setopt($objCurl,CURLOPT_POSTFIELDS,$strXml);
	$arrRtnXml = curl_exec($objCurl);
	unset($objCurl);
	$arrRtnXml = trim($arrRtnXml);
	$arrRtnXml = str_replace(array("\n", "\r"), "", $arrRtnXml);
	$xml = simplexml_load_string($arrRtnXml);

	// 若配號失敗，則不允許再做後續事項
	if($xml->REPLY != 1){
		if(strpos($xml->MESSAGE, "無發票號碼可使用，請洽加值中心管理人員.") === false){
			// return array("status" => false, "message" => $arrRtnXml["INDEX"]["MESSAGE"]);
			$failMsg[] = $xml->MESSAGE;
			$failCount++;
            continue;
		}
        break;
	}		

	$instart = (int)$xml->INVOICESTART;
	$inend = (int)$xml->INVOICEEND;
	$period = $xml->TAXMONTH + 191100;
	$period = sprintf("%d",$period);
	$param = "";
	$insinvoicestatus = "";
	for($i = $instart; $i <= $inend; $i++){
		$invoice = $xml->INVOICEHEADER . str_pad($i, 8, "0", STR_PAD_LEFT );
		$param[] = array("invoice_number" => $invoice, "period" => $period);
	}

	//==== 存入db ====
	try{
		for ($i=0;$i<=count($param)-1;$i++) {
			$insinvoice = $checkinvoice->insertInvoice($param[$i]);
		}
	}
	catch(Exception $ex){
		$insinvoicestatus = "false";
	}

	if($insinvoicestatus == "false"){
		$failMsg[] = "字軌[" . $xml->INVOICEHEADER . "]配發票號[" . $xml->INVOICESTART . "-" . $xml->INVOICEEND . "]更新資料庫時失敗";
		$failCount++;
		continue;
	}
	unset($iyw);
    $okCount++;
}

// 若失敗訊息不為空代表此次執行有問題，寄信
if(count($failMsg) > 0){
	$errmsg = "";
	foreach ($failMsg as $key => $value) {
		$errmsg .= $value."<br>";
	}
	$emailAry=array("sean_yeh@hiiir.com");
	$nameAry=array("電商開發部 葉軒豪(sean_yeh)");
	$subject = "Garce Gift 發票取號錯誤";
	$content = "<html><head><meta http-equiv='Content-Type' content='text/html; charset=UTF-8' /></head><body><div>
		發票取號發生問題,問題如下:<br>{msg}</div></body></html>";
	$mialcontent = str_replace("{msg}",$errmsg,$content);
	Mail_Sendmail::sendMultiMail($emailAry, $nameAry, $subject, $mialcontent);

}
?>