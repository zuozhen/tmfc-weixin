<?php
/**
  * TMFC (Trend Micro Football Club) wechat 
  * Initialized, Zuozhen, 2013/10/23
  * Update, add bm/cx/qx function, Zuozhen, 2013/11/08
  */

//define your token
define("TOKEN", "your-token-here");//zz
define("CALADDR", "your-google-calendar-here");//zz
wechatCallbackapiTest::initArray();//init the id-name map
$wechatObj = new wechatCallbackapiTest();
//$wechatObj->valid();  //only used for valid the url
$wechatObj->responseMsg();

class wechatCallbackapiTest
{
    public static $peopleArray;
    public static $peopleFile;
    static function initArray() {
        self::$peopleArray = array(
        "weixin-openid" => "name1",
        "weixin-opeinid2" => "name2"
        );//zz
    }
	public function valid()
    {
        $echoStr = $_GET["echostr"];

        //valid signature , option
        if($this->checkSignature()){
        	echo $echoStr;
        	exit;
        }
    }

    public function responseMsg()
    {
		//get post data, May be due to the different environments
		$postStr = $GLOBALS["HTTP_RAW_POST_DATA"];

      	//extract post data
		if (!empty($postStr)){
            $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
            $msg_type = trim($postObj->MsgType);
            switch($msg_type) {
                case "text":
                    $resultStr = $this->receiveText($postObj);
                    break;
                case "event":
                    $resultStr = $this->receiveEvent($postObj);
                    break;
                default:
                    $resultStr = "Unknown message type:".$msg_type;
                    break;
            }
            echo $resultStr;
        } else {
            echo "";
            exit;
        }
    }
    private function receiveText($object) {
        $keyword = trim($object->Content);
        if(!empty( $keyword )) {
            if ($keyword == "wyfq")
                $contentStr = '你还真信啊，I服了U。。。';
            else {
              	$contentStr = $this->getCalItem();
                if (is_null($contentStr))
                    $contentStr = '最近一周没有新活动，请联系Someone更新。';//zz
                elseif ($contentStr == "0")
                    $contentStr = '我日，貌似Google又连不上了，请回复wyfq翻墙。';
                elseif (strcasecmp($keyword, "bm")==0)
                    $contentStr = $this->getList($object->FromUserName, 1);
                elseif (strcasecmp($keyword, "qx")==0)
                    $contentStr = $this->getList($object->FromUserName, 0);
                elseif (strcasecmp($keyword, "cx")==0)
                    $contentStr = $this->getList($object->FromUserName, 2);
                else
                    $contentStr .= '您的ID: '.$object->FromUserName."\n"."回复bm报名，qx取消，cx查询";
            }
        }else{
               	$contentStr = "Input something...";
        }
        return $this->structureResponse($object, $contentStr);
    }
    private function getList($userId, $isGo) {
        $file = "./tmp/".self::$peopleFile;
        $userName = self::$peopleArray[trim($userId)];
        if (is_null($userName))
            $userName = trim($userId);
        if ($isGo==1) {
            $fHandle = fopen($file, "a+");
            $buffer = fread($fHandle, filesize($file));
            if (false === strpos($buffer, $userName)) 
                fwrite($fHandle, $userName."\n");
            fclose($fHandle);
        } elseif ($isGo==0) {
            $buffer = file_get_contents($file);
            $buffer = str_replace($userName."\n", '', $buffer);
            file_put_contents($file, $buffer);
        }
        $people = file_get_contents($file);
        if (empty($people))
            $people = "竟然没人报名！";
        else
            $people = self::$peopleFile."活动名单\n".$people;
        return $people;
    }
    private function receiveEvent($object) {
        switch($object->Event) {
        case "subscribe":
            $contentStr = "欢迎关注TMFC，TM足球队微信公众帐号。"."\n";//zz
            $contentStr .= "1. 您可回复任意字符，获取本周的足球比赛消息。";
            $contentStr .= "2. 回复bm报名，qx取消报名，cx查询报名名单。";
            break;
        case "unsubscribe":
            $contentStr = "轻轻地你走了，不带走一片云彩。";
            break;
        }
        return $this->structureResponse($object, $contentStr);
    }
    private function structureResponse($object, $contentStr) {
        $textTpl = "<xml>
						<ToUserName><![CDATA[%s]]></ToUserName>
						<FromUserName><![CDATA[%s]]></FromUserName>
						<CreateTime>%s</CreateTime>
						<MsgType><![CDATA[text]]></MsgType>
						<Content><![CDATA[%s]]></Content>
						<FuncFlag>0</FuncFlag>
					</xml>";             
        $resultStr = sprintf($textTpl, $object->FromUserName, $object->ToUserName, time(), $contentStr);
        return $resultStr;
    }
				
    private function getCalItem() {
        $result = null;
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL,CALADDR);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,10);
        $json = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($json, true);
        if (is_null($data))
            //echo '我日，貌似Google又连不上了，请回复1翻墙。';
            return "0";
        //print_r($data);
        $time1 = new DateTime();
        foreach ($data['items'] as $item) {
            //$time2 = DateTime::createFromFormat(DateTime::W3C, $item['start']['dateTime']);  here depends on php version...
            $time2 = date_create($item['start']['dateTime']);
            if ($time2 < $time1)
                continue;
            //echo $time1->format('c').'\n';
            //echo $time2->format('c').'\n';
            $diff_days = ($time2->format('U') - $time1->format('U'))/(3600*24);
            if ($diff_days < 7) {
                $result = '时间: '.$item['start']['dateTime']."\n";
                $result .= '地点: '.$item['location']."\n";
                $result .= '对手: '.$item['summary']."\n";
                $result .= '备注: '.$item['description']."\n";
                self::$peopleFile = substr($item['start']['dateTime'], 0, 10);
            } else {
                continue;
            }
        }
        return $result;
    }
		
	private function checkSignature()
	{
        $signature = $_GET["signature"];
        $timestamp = $_GET["timestamp"];
        $nonce = $_GET["nonce"];	
        		
		$token = TOKEN;
		$tmpArr = array($token, $timestamp, $nonce);
		sort($tmpArr);
		$tmpStr = implode( $tmpArr );
		$tmpStr = sha1( $tmpStr );
		
		if( $tmpStr == $signature ){
			return true;
		}else{
			return false;
		}
	}
}

?>
