<?php
class newinvoice
{
    // private $sellerid;  //統一編號
    // private $posId;     //pos機id
    // private $posSn;     //pos機金鑰
    // private $url;       //api網址

    public function newinvoice()
    {     
        $invoiceConfig = Zend_Registry::get('invoicenew');
        $this->sellerid = $invoiceConfig["sellerid"];
        $this->posId = $invoiceConfig["posId"];
        $this->posSn = $invoiceConfig["posSn"];
        $this->url = $invoiceConfig["url"];
        define("PATH", dirname(dirname(dirname(__FILE__))));
     }

    /**
    *   @param InvoiceDate = 發票日期 
    *   @param InvoiceTime = 發票時間
    *   @param Buyer[Identifier] = 購買人統編
    *   @param Buyer[Name] = 購買人姓名
    *   @param MainRemark = 備註
    *   @param InvoiceType = 發票二聯or三聯
    *   @param PrintMark = 是否列印
    *   @param ProductItem = 訂單明細
    *   @param NPOBAN = 愛心碼
    *   @param TotalAmount = 訂單金額
    *   @param DisCountAmount = 
    *   @param Description = 商品名稱
    *   @param ProductItem[Quantity] = 商品數量
    *   @param ProductItem[UnitPrice] = 商品單價
    *   @param ProductItem[Amount] = 總價
    *   @param ProductItem[SequenceNumber] = 商品順序，從1開始
    *   @param ProductItem[Remark] = 商品備註
    */

    //==========開立發票==========
    public function sendInvoice($orderNumber,$param)
    {   
        header("Content-Type:text/html; charset=utf-8");
        $updateinvobj = new Table_InvoiceNew();
        $updateinvarr = $updateinvobj->updateInvoice($orderNumber);
        if($updateinvarr=="false")
        {
            return array("statusCode" => 1 ,"errmsg" => ",資料庫發票取號失敗");//資料庫發票取號失敗
        }
        $getinvarr = $updateinvobj->getInvoice($orderNumber);
        if($getinvarr == "")
        {
            return array("statusCode" => 1 ,"errmsg" => ",資料庫發票取號失敗.");//資料庫發票取號失敗
        }
        $param["invoicenum"] = $getinvarr["invoice_number"];
        $param["ProductItem"] = json_decode($param["ProductItem"],true);
        $param["Buyer"] = json_decode($param["Buyer"],true);
        $param["RandomNumber"] = str_pad(rand(0,9999), 4,"0",STR_PAD_LEFT );// 隨機碼
        if(isset($_REQUEST["orderNumber"])){
            $param["orderNumber"] =($param["orderNumber"]);
        }
        else
        {
            $param["orderNumber"]="";
        }
        $param["BuyerName"] = $param["Buyer"]["Name"];
        $param["DonateMark"] = ($param["DonateMark"]);
        if(isset($param["NPOBAN"])&&$param["NPOBAN"]!="")
        {
            $param["NPOBAN"] =($param["NPOBAN"]);//愛心捐贈碼
            $param["Donate"] = "1";//是否捐贈 1捐
        }
        else
        {
            $param["NPOBAN"]="";//愛心捐贈碼
            $param["Donate"] = "0";//是否捐贈 0不捐
        }
        if(isset($param["CarrierType"])){
                $param["CarrierType"] = ($param["CarrierType"]);//>載具類別號碼
        }else{
                $param["CarrierType"]="";
        }
        if(isset($param["CarrierId1"])){
                $param["CarrierId1"] = ($param["CarrierId1"]);//載>具顯碼ID
        }else{
                $param["CarrierId1"]="";
        }
        if(isset($param["CarrierId2"])){
                $param["CarrierId2"] = ($param["CarrierId2"]);//載>具隱碼ID
        }else{
                $param["CarrierId2"]="";
        }
        //JSON型態資料轉換
        // 判斷若二聯式不打統編  以稅內含的方式顯示
        if((($param["InvoiceType"])=="02")&&$param["Buyer"]["Identifier"]=="0000000000"){
            $param["SalesAmount"] = $param["TotalAmount"];
            $param["TaxAmount"] = "0";
        }
        else
        {
            $param["SalesAmount"] = round((float)($param["TotalAmount"]/1.05));
            $param["TaxAmount"] = round($param["TotalAmount"] - $param["SalesAmount"]);
        }

        //3J0002 手機條碼當載具
        //CQ0001 自然人憑證當載具
        // if (!empty($param["CarrierType"])&&(($param["CarrierType"]!="3J0002")&&($param["CarrierType"]!="CQ0001")))
        // {
        //     $Json["actionType"] = 'false';
        //     $Json["returnData"]["errMsg"] = '[CarrierType]參數錯誤!';
        // }

        // if (isset($Json)&&$Json["returnData"]["errMsg"])
        // {
        //         echo json_encode($Json);
        //         exit();
        // }

        $arrB = array();
        foreach($param["ProductItem"] as $row){
            $index = count($arrB) + 1;
            $oneB = array();
            $oneB["B1"] = $index;
            $oneB["B2"] = $row["Description"];
            $oneB["B3"] = $row["Quantity"];
            $oneB["B4"] = "";
            $oneB["B7"] = $index;
            if($param["Buyer"]["Identifier"] == "0000000000"){
                    $oneB["B5"] = $row["UnitPrice"];
                    $oneB["B6"] = $row["Amount"];
                }else{
                // 代表買方為有統編戶
                    $oneB["B5"] = round((float)($row["UnitPrice"]/1.05));;
                    $oneB["B6"] = $oneB["B5"] * $row["Quantity"];;
                }
                $arrB[] = $oneB;
        }

        $arrXml = array();
        $arrXml["A1"] = "C0401";// 訊息類型
        $arrXml["A2"] = $param["invoicenum"];// 發票號碼
        $arrXml["A3"] = $param["InvoiceDate"];// 發票開日期
        $arrXml["A4"] = $param["InvoiceTime"];// 發票開立時間
        $arrXml["A5"] = $param["Buyer"]["Identifier"];// 買方統編
        $arrXml["A6"] = $param["Buyer"]["Name"];//買方名稱
        $arrXml["A19"] = "2013-02-27";// 核准日(
        $arrXml["A20"] = "資國";// 核準文(上傳者的資料，固定為盟立資料)
        $arrXml["A21"] = "1020001054";// 核準號(上傳者的資料，固定為盟立資料)
        $arrXml["A22"] = "07";// 發票類別(電子發票固定類別)
        $arrXml["A24"] = $param["Donate"];// 捐贈1 不捐0
        $arrXml["A25"] = $param["CarrierType"];// 自然人憑證/手機條碼 載具號
        $arrXml["A26"] = $param["CarrierId1"];// 載具顯碼
        $arrXml["A27"] = $param["CarrierId2"];// 載具隱碼
        $arrXml["A28"] = $param["PrintMark"];// 是否列印
        $arrXml["A29"] = $param["NPOBAN"];// 愛心捐贈碼
        $arrXml["A30"] = $param["RandomNumber"];// (四位數随機碼)
        $arrXml["B"] = $arrB; 
        $arrXml["C1"] = $param["SalesAmount"]; // 應稅銷售額(有統編時，金額為C07-C06；無統編時，C01=C07，C06=0)
        $arrXml["C2"] = "0"; // 免費銷售額
        $arrXml["C3"] = "0"; // 零稅率銷售額
        $arrXml["C4"] = "1"; // 課稅別
        $arrXml["C5"] = "0.05"; // 稅率
        $arrXml["C6"] = $param["TaxAmount"]; // 營業稅額(統編有打時才要列)
        $arrXml["C7"] = $param["TotalAmount"]; // 總計
        $arrXml["D1"] = $this->sellerid; // DAF統編
        $arrXml["D2"] = $this->posSn; // POSSN
        $arrXml["D3"] = $this->posId; // POSID
        $arrXml["D4"] = date("Y-m-d H:i:s"); // XML產生時間

        //湊xml
        $strXml ="<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $strXml .= "<INDEX>\n";
        if(is_array($arrXml)){
            foreach($arrXml as $key => $value) {
                if($key =="B")
                {
                    foreach ($value as $value2 =>$value3) {
                        $strXml .= "<B>\n";
                        for($i=1;$i<=count($value3);$i++)
                        {
                            $strXml .= "<B".$i.">".$value3["B".$i]."</B".$i.">\n";
                        }
                        $strXml .= "</B>\n";
                    }
                }
                else
                {    
                    $strXml .= "<".$key.">".$value."</".$key.">\n";
                } 
            }
        }
        $strXml .="</INDEX>";
        
        $file = fopen(PATH."/log/sendinvoice/".date("Ymd")."01".$arrXml["A2"],"w");
        fwrite($file, $strXml);
        fclose($file);

        //call api
        $returnxml = $this->callcurl($strXml,$arrXml["A2"]);
        if(empty($returnxml))
        {
            // $invoiceobj = new Table_InvoiceNew();
            // $invoiceobj->deleteInvoicebyinvnum($orderNumber);
            // return array("statusCode" => 1, "invoice" => $getinvarr["invoice_number"],"errmsg" =>".盟立回檔錯誤");
        }
        $errmsg = $returnxml->MESSAGE;
        if($returnxml->REPLY != 1){
            return array("statusCode" => 1, "invoice" => $getinvarr["invoice_number"],"errmsg" =>$errmsg);
        }
        return array("statusCode" => 0, "invoice" => $getinvarr["invoice_number"],"RandomNumber" =>$param["RandomNumber"]);
    } 


    //==========作廢發票==========
    public function cancelInvoice($param)
    {
        header("Content-Type:text/html; charset=utf-8");
        $arrXml = array();
        $arrXml["INVOICE_CODE"] = "C0501";
        $arrXml["POSSN"] = $this->posSn;
        $arrXml["POSID"] = $this->posId;
        $arrXml["INVOICE_NUMBER"] = $param["INVOICE_NUMBER"];
        $arrXml["INVOICE_DATE"] = $param["INVOICE_DATE"];
        $arrXml["BUYERID"] = $param["BUYERID"];
        $arrXml["SELLERID"] = $this->sellerid;
        $arrXml["CANCEL_DATE"] = $param["CANCEL_DATE"];
        $arrXml["CANCEL_TIME"] = $param["CANCEL_TIME"];
        $arrXml["CANCEL_REASON"] = $param["CANCEL_REASON"];
        $arrXml["SYSTIME"] = date("Y-m-d H:i:s");

        //湊xml
        $strXml ="<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $strXml .= "<INDEX>\n";
        if(is_array($arrXml)){
            foreach($arrXml as $key => $value) {
                $strXml .= "<".$key.">".$value."</".$key.">"."\n";
            }
        }
        $strXml .="</INDEX>";

        //call api
        $returnxml = $this->callcurl($strXml);
        if($returnxml->REPLY != 1){
            return array("statusCode" => 1);
        }
        return array("statusCode" => 0);
    }

    //==========開立折讓==========
    public function sendAllowance($param)
    {   
        //抓最後一筆折讓號碼
        $AllowanceObj = new Table_Allowance();
        $allowancenumber = $AllowanceObj->lastInsertId();
        $allowanceNumber = str_pad($allowancenumber["allowance_number"]+1, 16,'0',STR_PAD_LEFT);
        $buyer = json_decode($param["Buyer"],true);
        $ProductItem = json_decode($param["ProductItem"],true);
        $arrXml = array();
        $arrXml["INVOICE_CODE"] ="D0401";
        $arrXml["SELLERID"] = $this->sellerid;
        $arrXml["POSSN"] = $this->posSn;
        $arrXml["POSID"] = $this->posId;
        $arrXml["SYSTIME"] = date("Y-m-d H:i:s");
        $arrXml["ALLOWANCENUMBER"] = $allowanceNumber;
        $arrXml["ALLOWANCEDATE"] = $param["AllowanceDate"];
        $arrXml["IDENTIFIER"] = $buyer["Identifier"];
        $arrXml["NAME"] = $buyer["Name"];
        $arrXml["ALLOWANCETYPE"] = "2";

        //湊商品detail
        $arrP = array();
        foreach($ProductItem as $row){
            $oneP = array();
            $oneP["ORIGINALINVOICEDATE"] = $row["OriginalInvoiceDate"];
            $oneP["ORIGINALINVOICENUMBER"] = $row["OriginalInvoiceNumber"];
            $oneP["ORIGINALSEQUENCENUMBER"] = $row["OriginalSequenceNumber"];
            $oneP["ORIGINALDESCRIPTION"] = $row["OriginalDescription"];
            $oneP["QUANTITY"] = $row["Quantity"];
            $oneP["UNITPRICE"] = $row["UnitPrice"];
            $oneP["AMOUNT"] = $row["Amount"];
            $oneP["TAX"] = $row["Tax"];
            $oneP["ALLOWANCESEQUENCENUMBER"] = $row["AllowanceSequenceNumber"];
            $arrP[] = $oneP;
        }
        $arrXml["PRODUCTITEM"] = $arrP;
        $arrXml["TAXAMOUNT"] = $param["TaxAmount"];
        $arrXml["TOTALAMOUNT"] = $param["TotalAmount"];

        //湊xml
        $strXml ="<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $strXml .= "<INDEX>\n";
        if(is_array($arrXml)){
            foreach($arrXml as $key => $value) {
                if($key =="PRODUCTITEM")
                {
                    foreach ($value as $value2) {
                        $strXml .= "<PRODUCTITEM>\n";
                        $strXml .= "<ORIGINALINVOICEDATE>".$value2["ORIGINALINVOICEDATE"]."</ORIGINALINVOICEDATE>\n";
                        $strXml .= "<ORIGINALINVOICENUMBER>".$value2["ORIGINALINVOICENUMBER"]."</ORIGINALINVOICENUMBER>\n";
                        $strXml .= "<ORIGINALSEQUENCENUMBER>".$value2["ORIGINALSEQUENCENUMBER"]."</ORIGINALSEQUENCENUMBER>\n";
                        $strXml .= "<ORIGINALDESCRIPTION>".$value2["ORIGINALDESCRIPTION"]."</ORIGINALDESCRIPTION>\n";
                        $strXml .= "<QUANTITY>".$value2["QUANTITY"]."</QUANTITY>\n";
                        $strXml .= "<UNITPRICE>".$value2["UNITPRICE"]."</UNITPRICE>\n";
                        $strXml .= "<AMOUNT>".$value2["AMOUNT"]."</AMOUNT>\n";
                        $strXml .= "<TAX>".$value2["TAX"]."</TAX>";
                        $strXml .= "<ALLOWANCESEQUENCENUMBER>".$value2["ALLOWANCESEQUENCENUMBER"]."</ALLOWANCESEQUENCENUMBER>\n";
                        $strXml .= "<TAXTYPE>"."1"."</TAXTYPE>\n";
                        $strXml .= "</PRODUCTITEM>\n";
                    }
                }
                else
                {    
                    $strXml .= "<".$key.">".$value."</".$key.">\n";
                }  
            }
        }
        $strXml .="</INDEX>";
        $returnxml = $this->callcurl($strXml);

        if($returnxml->REPLY != 1){
            return array("statusCode" => 1);
        }
        return array("statusCode" => 0,"allowanceNumber" =>$allowanceNumber);
    }
    
    //==========作廢折讓==========
    public function delAllowance($param){
        $arrXml = array();
        $arrXml["INVOICE_CODE"] ="D0501";
        $arrXml["POSSN"] = $this->posSn;
        $arrXml["POSID"] = $this->posId;
        $arrXml["CANCELALLOWANCENUMBER"] = $param["CancelAllowanceNumber"];
        $arrXml["ALLOWANCEDATE"] = $param["AllowanceDate"];
        $arrXml["BUYERID"] = $param["BuyerId"];
        $arrXml["SELLERID"] = $this->sellerid;
        $arrXml["CANCELDATE"] = $param["CancelDate"];
        $arrXml["CANCELTIME"] = $param["CancelTime"];
        $arrXml["CANCELREASON"] = $param["CancelReason"];
        $arrXml["SYSTIME"] = date("Y-m-d H:i:s");

        //湊xml
        $strXml ="<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $strXml .= "<Invoice>\n";
        if(is_array($arrXml)){
            foreach($arrXml as $key => $value) {
                $strXml .= "<".$key.">".$value."</".$key.">"."\n";
            }
        }
        $strXml .="</Invoice>";

        //call api
        $returnxml = $this->callcurl($strXml);
        if($returnxml->REPLY != 1){
            return array("statusCode" => 1);
        }
        return array("statusCode" => 0);
    }

    //==========curl處理資訊送出==========
    public function callcurl($xml,$invnum = "")
    {
        $objCurl = curl_init($this->url);
        curl_setopt($objCurl,CURLOPT_POST,1);
        curl_setopt($objCurl,CURLOPT_RETURNTRANSFER,1);
        curl_setopt($objCurl,CURLOPT_HTTP_VERSION,CURL_HTTP_VERSION_1_0);
        curl_setopt($objCurl,CURLOPT_POSTFIELDS,$xml);
        $arrRtnXml = curl_exec($objCurl);
        unset($objCurl);
        if(!empty($invnum))
        {
            $file = fopen(PATH."/log/sendinvoice/".date("Ymd")."02".$invnum,"w");
            fwrite($file, $arrRtnXml);
            fclose($file);
        }
        
        try{
            $arrRtnXml = trim($arrRtnXml);
            $arrRtnXml = str_replace(array("\n", "\r"), "", $arrRtnXml);
            $xml = simplexml_load_string($arrRtnXml);
        }
        catch(Exception $ex){
            $errmsg = $arrRtnXml;
            $emailAry = array("sean_yeh@hiiir.com");
            $nameAry = array("品牌商務部 葉軒豪(sean_yeh)");
            $subject = "Garce Gift 盟立回檔錯誤";
            $content = "<html><head><meta http-equiv='Content-Type' content='text/html; charset=UTF-8' /></head><body><div>
                以下為盟立回檔:<br>{msg}</div></body></html>";
            $mialcontent = str_replace("{msg}",$errmsg,$content);
            Mail_Sendmail::sendMultiMail($emailAry, $nameAry, $subject, $mialcontent);
            $xml = "";
        }
        
        return $xml;
    }
}