<?php
/*
    Author: James.Shi
    Date: 2014-11-24 12:11
*/

/*
	Function Blog
	
	alter help module. 11/24/2014
	alter bind. 11/24/2014
	add query bind info. 11/24/2014
	
*/

define("TOKEN", "weixin");

$wechatObj = new wechatCallbackapiTest();

if (isset($_GET['echostr'])) {
    $wechatObj->valid();
}else{
    $wechatObj->responseMsg();
}

class wechatCallbackapiTest
{
    public function valid()
    {
        $echoStr = $_GET["echostr"];
        if($this->checkSignature()){
            echo $echoStr;
            exit;
        }
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

    public function responseMsg()
    {
        $postStr = $GLOBALS["HTTP_RAW_POST_DATA"];

        if (!empty($postStr)){
            $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
			$RX_TYPE = trim($postObj->MsgType);
			switch ($RX_TYPE) {
			case "event":
				# code...
				$result = $this->receiveEvent($postObj);
				break;
			case "text":
				$result = $this->receiveText($postObj);
				break;
			default:
				# code...
				$result = $this->receiveOthers($postObj);
				break;
			}
			echo $result;  // result 打错了，调了好久
        }else{
            echo "what's this!?";
            exit;
        }
    }
	
    // super function
    // function: get student's score
    // $ID: student's id
    private function getMark($ID)
    {
        // return "Test";
        // create a connection
        $mysql = new SaeMysql();
        // $sql = "select MidMark from `bds_2014_autumn_mark` where `StudentID`='3129901013'"; // this is can be work, i can suppose the mistake is no connection has been made
        $id = '3120000158';
        $sql = "select MidMark from `bds_2014_autumn_mark` where `studentid`={$ID}"; // test result: right, id,will succeed, ID, no. But the sql is right
        $data = $mysql->getLine($sql); 
        /*problem is here: data is not a string, it's an array, and there less a '$'' befor sql. the key is the column in the table, and value is is what you want*/
        if (!empty($data)) {
            foreach ($data as $key => $value) {
                # code...
                $result = $value;
            }
        } else {
            $result = "查无此人";
        }
        $mysql->closeDb();
        return $result;
    }

	// function: response to pic or vedio msg
	// $object: post object
	private function receiveOthers($object)
	{
		$content = "无法识别发送的信息";
		$result = $this->transmitText($object, $content);
		return $result;
	}

    // function: receive a event
    // $object: post object
    private function receiveEvent($object)
    {
        $content = "";
        switch ($object->Event) {
            case "subscribe" :
                $content = "Welcome to follow Assistant James. If your teacher is not He Qinming, please do unfollow. Thanks. Any help please send '?', or '帮助'.";
                break;
            case "unsubscribe":
                $content = ""; // the first time, I missed a semicolon, then it dont work, all functions.
                break;
        }

        $result = $this->transmitWelcome($object, $content);
        return $result;
    }

    // function: the type of msg is text
    private function receiveText($object)
    {
        // 不能使用switch
        $cont = explode(" ", trim($object->Content));
        $keyword = $cont[0];
        if ($keyword == "X") {
            $result = $this->testFunction($object);
        } elseif($keyword == "0") {
            $result = $this->bindAccount($object);
        } elseif($keyword == "1") {
            $result = $this->bindQuery($object);
        } else {
            $result = $this->transmitHelp($object);
        }
        
        return $result;
    }

    // function: send welcome info
    // $content: what we will send
    private function transmitWelcome($object, $content) 
    {
        $result = $this->transmitText($object, $content);
        return $result;
    }

    // function: bind account
	// $object: post object
    private function bindAccount($object) 
    {
        // bind
        // content: [0 3130000313 名字 组号 电话 邮箱]
        $user = explode(" ", trim($object->Content));
        $bindwxid = $object->FromUserName;
        if (count($user) < 6) 
        {  
            $content = "1. 组号是纯数字，不带字母。\n2. 放心吧，哥不会泄露你们的个人信息。"
                . "\n[3. 不要绑定其他同学的学号等，因为不提供解绑.]"
                . "\n\n绑定账号格式如下，用空格分开:\n0 学号 姓名 组号 电话 邮箱";
            $result = $this->transmitText($object, $content);
            return $result;
        }

        // 信息完整
        $sql = "select id from 2014_winter_ads_info where sid = '$user[1]' and name = '$user[2]' and gid = '$user[3]' ";
        
        $mysql = new SaeMysql();
        $data = $mysql->getLine($sql);
        $content = "";
        if (empty($data)) 
        {
            $content = "查无此人，请核实学号、姓名、组号.\n";
        } else {
            // 查看是否已经绑定
			
			$sql = "select id from 2014_winter_ads_info where wxid = '$bindwxid'";
			$data = $mysql->getline($sql);
			if (empty($data))
			{
				// 此微信号没有绑定
				$sql = "update 2014_winter_ads_info set wxid = '$bindwxid', gid = '$user[3]', "
					. " phone = '$user[4]', email = '$user[5]' where sid = '$user[1]' and name = '$user[2]' ";
                $mysql->runsql($sql);
				$rows = $mysql->affectedrows();
				if ($mysql->errno() != 0)
				{
					die("error:" . $mysql->errmsg());
					$content = "出现错误，绑定失败，请邮箱联系助教.\n";
				} else {
					$content = "绑定成功.\n";
				}
			} else 
			{
				$content = "此微信号已绑定，回复1,查看绑定信息，若不是自己信息，请邮箱联系助教.\n";
			}
        }
           
        $mysql->closeDb();
        // return info
		
        $result = $this->transmitText($object, $content);
        return $result;
    }
	
	// function: query bind info
	// return：sid, name, gid
	private function bindQuery($object)
	{
		$wxaccount = trim($object->FromUserName);
		$sql = "select sid, name, gid, phone, email from 2014_winter_ads_info where wxid = '$wxaccount'";
		$mysql = new SaeMysql();
		$data = $mysql->getLine($sql);
		// 检查是否出现错误
        if ($mysql->errno() != 0)
        {
            die("Error:" . $mysql->errmsg());
            $content = "出现错误，请邮箱联系助教.\n";
            $result = $this->transmitText($object, $content);
            return $result;
        }

		if (empty($data))
        {
            $content = "请先绑定账号，再查询成绩。回复‘h’查看帮助。";
        } else {
			// 获取结果 
			$content = $content . "学号：" . $data['sid'] . "\n";
			$content = $content . "姓名：" . $data['name'] . "\n";
			$content = $content . "组号：" . $data['gid'] . "\n";
			$content = $content . "电话：" . $data['phone'] . "\n";
			$content = $content . "邮箱：" . $data['email'] . "\n";
		}
		
		
		$result = $this->transmitText($object, $content);
        return $result;
	}
	
    // function: get project's comment
	// $object: post object
    private function getProject($object)
    {
        $wxaccount = trim($object->FromUserName);
        $sql = "select gid 
        from bds_2014_autumn_project 
        join bds_2014_autumn_mark 
        on bds_2014_autumn_project.studentid = bds_2014_autumn_mark.studentid 
        where bds_2014_autumn_mark.wxid = '$wxaccount' ";
        
        $mysql = new SaeMysql();
        $data = $mysql->getLine($sql);
        // 检查是否出现错误
        if ($mysql->errno() != 0)
        {
            die("Error:" . $mysql->errmsg());
            $content = "出现错误，请邮箱联系助教.\n";
            $result = $this->transmitText($object, $content);
            return $result;
        }

		// 没有查到信息
        if (empty($data))
        {
            $content = "请先绑定账号，再查询成绩。回复‘h’查看帮助。";
            //$content = $sql;  // debug
        }
		
        // 获取结果
        foreach ($data as $key => $value) {
            # code...
             $gidvar =  $value;
        }
		$httppath = "http://1.jamesappbds.sinaapp.com/P3/" . $gidvar . ".htm";
        $content = "点击链接查看结果\n". $httppath;
        
        // test
        // $content = $wxaccount;
        $result = $this->transmitText($object, $content);
        return $result;
    }

    // function: send help info
	// $object: post object
    private function transmitHelp($object) 
    {
        $content = "帮助：\n\n输入代码，执行相应操作。若没有绑定账号，需要先绑定账号。" 
            . "\n\n==================\n\n0 -- 绑定账号";
        $result = $this->transmitText($object, $content);
        return $result;
    }

    // function：send text info: $content
	// $object: post object
    private function transmitText($object, $content)
    {
        // text template
        $textTeml = "
        <xml>
        <ToUserName><![CDATA[%s]]></ToUserName>
        <FromUserName><![CDATA[%s]]></FromUserName>
        <CreateTime>%s</CreateTime>
        <MsgType><![CDATA[text]]></MsgType>
        <Content><![CDATA[%s]]></Content>
        </xml>";

        // produce result with template
        $result = sprintf($textTeml, $object->FromUserName, $object->ToUserName, time(), $content);
        return $result;
    }

    // function: test other function
	// $object: post object
    private function testFunction($object)
    {
		$content = "test function";
        $result = $this->transmitText($object, $content);
        return $result;
    }
}
