<?php
/*
     Author: James.Shi
    Date: 2014-11-24 12:11
*/

/*
	Function Blog
	
	alter help module. 11/24/2014
	
	
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
            // $RX_TYPE = trim($postObj->MsgType);

            // switch ($RX_TYPE) {
            //     case "event":
            //         # code...
            //         $result = $this->receiveEvent($postObj);
            //         break;
            //     case "text":
            //         $result = $this->receiveText($postObj);
            //         break;
            //     default:
            //         # code...
            //         break;
            // }
            // echo $retult;
            

            $fromUsername = $postObj->FromUserName;
            $toUsername = $postObj->ToUserName;
            $keyword = trim($postObj->Content);
            $content = $keyword; //
            $time = time();
            $textTpl = "<xml>
                        <ToUserName><![CDATA[%s]]></ToUserName>
                        <FromUserName><![CDATA[%s]]></FromUserName>
                        <CreateTime>%s</CreateTime>
                        <MsgType><![CDATA[%s]]></MsgType>
                        <Content><![CDATA[%s]]></Content>
                        <FuncFlag>0</FuncFlag>
                        </xml>";
            
            $flag = strstr($content, "Middle");
            if ($flag) {
                $msgType = "text";
                $ID = substr($content, 6);
                $result = $this->getMark($ID);
                $resultStr = sprintf($textTpl, $fromUsername, $toUsername, $time, $msgType, $result);
                echo $resultStr;
            } elseif($keyword == "?" || $keyword == "？" || $keyword == "帮助")
            {
                // 保存这个比较好，稳定以后再修改。
                $msgType = "text";
                //$contentStr = date("Y-m-d H:i:s",time());
                $contentStr = "Help:\nSend student id, you can get a middle score with a valid student id. Like this:\nMiddle 3120000123\n" . 
                "\n帮助：\n输入代码，执行相应操作。若没有绑定账号，需要先绑定账号。
                \n0--绑定账号\n1--期中成绩\n2--实验2成绩\n3--实验3成绩\n4--Bonus成绩\n\n绑定账号格式为\n0 1230000123 张航";
                $resultStr = sprintf($textTpl, $fromUsername, $toUsername, $time, $msgType, $contentStr);
                echo $resultStr;
                
            } else {
                // for compatibility, just put it here temprary

                /*---------------------*/
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
                    break;
                }
                echo $result;  // result 打错了，调了好久
                /*---------------------*/

            }
        }else{
            echo "";
            exit;
        }
    }
    
    // old function
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

    // function: 
    private function receiveText($object)
    {
        // 不能使用switch
        // 不能使用switch
        $cont = explode(" ", trim($object->Content));
        $keyword = $cont[0];
        if ($keyword == "X") {
            $result = $this->testFunction($object);
        } elseif($keyword == "0") {
            $result = $this->bindAccount($object);
        } elseif($keyword == "1") {
            $result = $this->getScore($object);
        } elseif($keyword == "2") {
            $result = $this->getProject2($object);
        } elseif($keyword == "3") {
        	$result = $this->getProject3($object);
        } elseif($keyword == "4") {
			$result = $this->getBonus($object);
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
    private function bindAccount($object) 
    {
        // bind
        // content: [0 3130000313 石中]
        $user = explode(" ", trim($object->Content));
        $bindwxid = $object->FromUserName;
        if (count($user) < 3) 
        {  
            $content = "格式为: 0 学号 姓名\n";
            $result = $this->transmitText($object, $content);
            return $result;
        }

        // 信息完整
        $sql = "select wxid from bds_2014_autumn_mark where studentid = '$user[1]' and name = '$user[2]' ";
        $mysql = new SaeMysql();
        $data = $mysql->getLine($sql);
        $content = "";
        if (empty($data)) 
        {
            $content = "查无此人，请核实学号和姓名.\n";
        } else {
            // 存在此人,执行绑定
            $sql = "update bds_2014_autumn_mark set wxid = '$bindwxid' where studentid = '$user[1]' and name = '$user[2]' ";
            $mysql->runSql($sql);
            $rows = $mysql->affectedRows();
            if ($mysql->errno() != 0)
            {
                die("Error:" . $mysql->errmsg());
                $content = "出现错误，绑定失败，请邮箱联系助教.\n";
            } else {
                $content = "绑定成功.\n";
            }
        }
           
        $mysql->closeDb();

        // return info
        $result = $this->transmitText($object, $content);
        return $result;
    }


    // new function
    // function: get middle and finall score
    private function getScore($object)
    {
        
        $wxaccount = trim($object->FromUserName);
        $sql = "select midmark from bds_2014_autumn_mark where wxid='{$wxaccount}'";
        
        $mysql = new SaeMysql();
        $data = $mysql->getLine($sql);
        foreach ($data as $key => $value) {
            # code...
            $content = $value;
        }

        if (empty($data))
        {
            $content = "请先绑定账号，再查询成绩。回复‘h’查看帮助。";
        }

        // test
        // $content = $wxaccount;

        $result = $this->transmitText($object, $content);
        return $result;
    }


    // function: get project2's comment
    private function getProject2($object)
    {
        $wxaccount = trim($object->FromUserName);
        $sql = "select p2addr 
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

        // 获取结果
        foreach ($data as $key => $value) {
            # code...
            $content = "点击链接查看结果\n" . $value;
        }

        // 没有查到信息
        if (empty($data))
        {
            $content = "请先绑定账号，再查询成绩。回复‘h’查看帮助。";
            //$content = $sql;  // debug
        }

        // test
        // $content = $wxaccount;

        $result = $this->transmitText($object, $content);
        return $result;
    }
    
    
    // function: get project3's comment
    private function getProject3($object)
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

        // 获取结果
        foreach ($data as $key => $value) {
            # code...
             $gidvar =  $value;
        }
		$httppath = "http://1.jamesappbds.sinaapp.com/P3/" . $gidvar . ".htm";
        $content = "点击链接查看结果\n". $httppath;
        
        // 没有查到信息
        if (empty($data))
        {
            $content = "请先绑定账号，再查询成绩。回复‘h’查看帮助。";
            //$content = $sql;  // debug
        }

        // test
        // $content = $wxaccount;

        $result = $this->transmitText($object, $content);
        return $result;
    }
    
    // function: get Bonus
	private function getBonus($object)
	{
        // $result = $this->transmitText($object, "更新数据库，暂不提供");
        // return $result;
        
		$wxaccount = trim($object->FromUserName);
        $sql = "select id,bds_2014_autumn_bonus.name,b1,b2,sum 
        from bds_2014_autumn_bonus 
        join bds_2014_autumn_mark 
        on bds_2014_autumn_bonus.id = bds_2014_autumn_mark.studentid 
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

		$content = "Bonus之前已在微信公布，现在Bonus已处理完毕，不再提交和补交。"
			. "若果b1或b2后面显示为空，则代表该题目没有成绩。\n\n";
        // 获取结果
        foreach ($data as $key => $value) {
            # code...
			$str = $key . " : " . $value . "\n";
			$content = $content . $str;
        }
        
        // 没有查到信息
        if (empty($data))
        {
            $content = "请先绑定账号，再查询成绩。回复‘h’查看帮助。";
            $content = $sql;  // debug
        }

        // test
        // $content = $wxaccount;

        $result = $this->transmitText($object, $content);
        return $result;
	}

    // function: send help info
    private function transmitHelp($object) 
    {
        $content = "帮助：\n输入代码，执行相应操作。若没有绑定账号，需要先绑定账号。" 
            . "\n0--绑定账号\n1--期中成绩\n2--实验2成绩\n3--实验3成绩\n4--Bonus成绩\n\n绑定账号格式为\n0 1230000123 张航";
        $result = $this->transmitText($object, $content);
        return $result;
    }

    // function：send text info: $content
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
    private function testFunction($object)
    {
        $wxaccount = trim($object->FromUserName);
        $sql = "select p2addr 
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

        // 获取结果
        foreach ($data as $key => $value) {
            # code...
            $content = "点击链接查看结果\n" . $value;
        }

        // 没有查到信息
        if (empty($data))
        {
            $content = "请先绑定账号，再查询成绩。回复‘h’查看帮助。";
            //$content = $sql;  // debug
        }

        // test
        // $content = $wxaccount;

        $result = $this->transmitText($object, $content);
        return $result;
    }
}
