<?
/**
* 
*/
class Tool 
{
	var $StartTime = 0;
    var $StopTime = 0;

	function __construct(argument)
	{
		# code...
	}

    function get_microtime()
    {
        list($usec, $sec) = explode(' ', microtime());
        return ((float)$usec + (float)$sec);
    }

    function start()
    {
        $this->StartTime = $this->get_microtime();
    }

    function stop($display)
    {
        $this->StopTime = $this->get_microtime();
        if ($display) {
            echo "花費時間: " . $this->spent()." 毫秒" . PHP_EOL;
        }

        // 計算記憶體使用
        echo "最終: ".$this->sizeFormat(memory_get_usage())." \n";
        echo "巔峰: ".$this->sizeFormat(memory_get_peak_usage())." \n";
    }

    function spent()
    {
        return round(($this->StopTime - $this->StartTime) * 1000, 1);
    }

    function sizeFormat($size)
    {
        $sizeStr='';
        if($size<1024)
        {
            return $size." bytes";
        }
        else if($size<(1024*1024))
        {
            $size=round($size/1024,1);
            return $size." KB";
        }
        else if($size<(1024*1024*1024))
        {
            $size=round($size/(1024*1024),1);
            return $size." MB";
        }
        else
        {
            $size=round($size/(1024*1024*1024),1);
            return $size." GB";
        }
    }
    function sendXlsMail($email, $name, $subject, $content,$filepath)
    {
        require_once ("mailfunction.php");
        require_once ("class.phpmailer.php");
        date_default_timezone_set("Asia/Taipei");
        $mail = new PHPMailer();
        $mail->IsSMTP();
        $mail->SMTPDebug = 0;
        $mail->Encoding = "base64";     
        $mail->CharSet = "UTF-8";     
        $mail->Host = "localhost";
        // 線上使用設定檔
        $mail->AddAttachment($filepath); // 設定附件檔檔名
        $mail->SetFrom("service@services.gracegift.com.tw", "Grace gift 官方購物網站 客服系統");
        $mail->AddReplyTo("service@services.gracegift.com.tw", "Grace gift 官方購物網站 客服系統");
        $mail->Subject = $subject;
        $mail->AltBody = "看到此文字, 表示您的系統不支援 HTML 模式, 請用 HTML 模式閱讀";
        $mail->MsgHTML($content);

        $mail->AddAddress($email, mime_encode_headers($name));

        if (!$mail->Send()) {
            return false;
        }
        $mail->ClearAddresses();
        return true;
    }
    public static function sendMultiMail($emailAry, $nameAry, $subject, $content)
    {
        require_once ("mailfunction.php");
        require_once ("class.phpmailer.php");
        date_default_timezone_set("Asia/Taipei");
        $mail = new PHPMailer();
        $mail->IsSMTP();
        $mail->Host = "msa.hinet.net";
        $mail->SMTPDebug = 0;
        $mail->Host = "localhost";      
        $mail->Port = 25;
        $mail->Encoding = "base64";    
        $mail->CharSet = "UTF-8";       
        $mail->SetFrom("service@services.gracegift.com.tw", "Grace gift 官方購物網站 客服系統");      
        $mail->AddReplyTo("service@services.gracegift.com.tw", "Grace gift 官方購物網站 客服系統");   
        $mail->Subject = $subject;
        $mail->AltBody = "看到此文字, 表示您的系統不支援 HTML 模式, 請用 HTML 模式閱讀";
        $mail->MsgHTML($content);

        foreach($emailAry as $key => $mailAddress) {
            $name = "";
            if (isset($nameAry[$key])) {
                $name = $nameAry[$key];
            }
            $mail->AddBCC($mailAddress, mime_encode_headers($name));
        }

        if (!$mail->Send()) {
            return false;
        }
        $mail->ClearAddresses();
        return true;
    }
}
?>