<?php
/*
*微信平台公众号开发
*time: 2015-12-25
*author: 王少波
*email： shaobo123_ok@163.com
*QQ: 861155139 
*/

    /*
    *@params $_appid 应用id
    *@params $_appsecret 应用密钥
    *@params $_token 消息验证令牌
    */
    class Wechat{

        private $_appid;
        private $_appsecret;
        private $_token;
        private $_msg = '<xml>
                        <ToUserName><![CDATA[%s]]></ToUserName>
                        <FromUserName><![CDATA[%s]]></FromUserName>
                        <CreateTime>%s</CreateTime>';
        /*
        *初始化数据
        */
        public function __construct($appid, $appsecret, $token){
            $this->_appid = $appid;
            $this->_appsecret = $appsecret;
            $this->_token = $token;
        }

        /*
        *请求微信服务端
        *@params $curl string 接口地址
        *@params $https string 是否HTTPS请求
        *@params $method  string 请求方式
        *@params $data string post提交数据
        */
        private function _request( $curl, $https = true, $method = 'GET', $data = NULL){

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $curl);
            curl_setopt($ch, CURLOPT_HEADER, FALSE);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

            if( $https ){
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            }

            if($data){
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            }

            $content = curl_exec($ch);
            curl_close($ch);
            return $content;

        }

        /*
        *获取access_token
        */
        public function _getAccessToken(){

            $filename = "token.txt";//设置token保存文件
            /*if(file_exists($filename)){

                $content = file_get_contents($filename);
                $content = json_decode($content);
                if($content->expires_in > time()-filemtime($filename))//判断access_token是否过期
                    return $content->access_token;
            }*/

            $curl = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".$this->_appid."&secret=".$this->_appsecret;

            $content = $this->_request($curl);

            if(function_exists('file_put_contents')){
                file_put_contents($filename, $content);
            }else{
                $handle = fopen($filename, 'w+');
                fwrite($handle, $content);
                fclose($handle);
            }
            
			
            $content =  json_decode($content);
            
            return $content->access_token;
        }

        /*
         * 获取二维码ticket
         */
        public function _getTicket($sceneid, $type = 'temp', $expire_time = 604800){
            if( $type == 'temp' ){
                $data = '{"expire_seconds": %s, "action_name": "QR_SCENE", "action_info": {"scene": {"scene_id": %s}}}';
                $data = sprintf($data, $expire_time,$sceneid);
            }else{
                $data = '{"action_name": "QR_LIMIT_SCENE", "action_info": {"scene": {"scene_id": %s}}}';
                $data = sprintf($data,$sceneid);
            }
            $curl = 'https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token='.$this->_getAccessToken();

            $content = $this->_request($curl,'true','POST',$data);
            $content = json_decode($content);
            
            return $content->ticket;
        }

        /*
        *用ticket换取二维码
        */
        public function _getQRCode($sceneid, $type = 'temp', $expire_time = 604800){
            $curl = 'https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket='.$this->_getTicket($sceneid, $type = 'temp', $expire_time = 604800);
            return $this->_request($curl);
        }

        /*
        *获取随机字符
        */
        public function valid()
        {
            $echoStr = $_GET["echostr"];

            //valid signature , option
            if($this->checkSignature()){
                echo $echoStr;
                exit;
            }
        }

        /*
        *响应消息
        */
        public function responseMsg()
        {
            //获取http post无法获取的数据（如：xml）
            $postStr = $GLOBALS["HTTP_RAW_POST_DATA"];

            //检测数据
            if (!empty($postStr)){
                    
                $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
                //file_put_contents('./postObj.txt', json_encode($postObj));
                switch ($postObj->MsgType) {
                    case 'text':        $this->_doText($postObj);break;
                    case 'image':       $this->_doImage($postObj);break;
                    case 'voice':       $this->_doVoice($postObj);break;
                    case 'video':       $this->_doVideo($postObj);break;
                    case 'shortvideo':  $this->_doSvideo($postObj);break;
                    case 'location':    $this->_doLocation($postObj);break;
                    case 'link':        $this->_doLink($postObj);break;
                    case 'event':       $this->_doEvent($postObj);break;
                    default:            $this->_doText($postObj);break;
                }
                return ;
            }else {
                echo "";
                exit;
            }
        }

        /*
        *文本消息响应
        */
        private function _doText($postObj){

            $fromUsername = $postObj->FromUserName;
            $toUsername = $postObj->ToUserName;

            $keyword = trim($postObj->Content);
            $time = time();
            $textTpl = $this->_msg."<MsgType><![CDATA[%s]]></MsgType>
                        <Content><![CDATA[%s]]></Content>
                        <FuncFlag>0</FuncFlag>
                        </xml>"; 
            
            $contentStr = $this->_rebootTalk($keyword);
            $msgType = "text";
                        
            $resultStr = sprintf($textTpl, $fromUsername, $toUsername, $time, $msgType, $contentStr);
            echo $resultStr;    
        }

        /*
        *智齿机器人微信聊天
        */
        private function _rebootTalk($keyword){

            $curl = "http://www.sobot.com/chat/user/robotsend.action?callback=jQuery110203937682623574671_1451097424049";
            $data = "requestText=".$keyword."&sysNum=2dd2945198524f75b4b7dc9ca8a0fcb2&uid=39bc179e5fec4a0cb8bfa3a79947adb1&cid=90f273f6b85b43739176e9cacd92db4c
&source=1";
            $result = preg_replace('/.+\(/','',$this->_request($curl,'false','POST',$data));
            $result = preg_replace('/\)$/','',$result);
            $content = json_decode($result);
            $content = htmlspecialchars(trim(strip_tags($content->answer,'\n\r')));
           
            return $content;
        }

        /*
        *图片消息响应
        */
        private function _doImage($postObj){

        }

        /*
        *事件消息响应
        */
        private function _doEvent($postObj){
            if(isset($postObj->EventKey))
                switch($postObj->EventKey){
                    case 'produce': $this->_sendProduce($postObj);break;
                    case 'news'   : $this->_sendNews($postObj);break;
                }
        }

        /*
        *语音消息响应
        */
        private function _doVoice($postObj){
            
        }

        /*
        *视频消息响应
        */
        private function _doVideo($postObj){
            
        }
        
        /*
        *验证签名
        */
        private function checkSignature()
        {
            $signature = $_GET["signature"];
            $timestamp = $_GET["timestamp"];
            $nonce = $_GET["nonce"];
            $token = $this->_token;
            $tmpArr = array($token, $timestamp, $nonce);
            sort($tmpArr, SORT_STRING);
            $tmpStr = implode( $tmpArr );
            $tmpStr = sha1( $tmpStr );
            
            if( $tmpStr == $signature ){
                return true;
            }else{
                return false;
            }
        }

        /*
        *创建菜单
        */
        public function _createMenu(){
            $curl = 'https://api.weixin.qq.com/cgi-bin/menu/create?access_token='.$this->_getAccessToken();
            $data = ' {
                         "button":[
                         {  
                              "type":"click",
                              "name":"爱小罗",
                              "key":"produce"
                          },
                          {
                               "name":"菜单",
                               "sub_button":[
                               {    
                                   "type":"click",
                                   "name":"新闻",
                                   "key":"news"
                                },
                                {
                                   "type":"view",
                                   "name":"视频",
                                   "url":"http://v.qq.com/"
                                },
                                {
                                   "type":"view",
                                   "name":"娱乐",
                                   "url":"http://www.epub360.com/v2/manage/book/rx3k33/#page/page_b794e1c41a732986ed2670f91f7853f8"
                                }]
                           }]
                     }';
            $content = $this->_request($curl,'true','POST',$data);
            $content = json_decode($content);
            if($content->errcode == 0){
                echo "创建成功";
            }else{
                echo "创建成功";
            }
        }

        /*
        *删除菜单
        */
        public function _deleteMenu(){
            $curl = 'https://api.weixin.qq.com/cgi-bin/menu/delete?access_token='.$this->_getAccessToken();
            $content = $this->_request($curl,'true','POST',$data);
            $content = json_decode($content);
            if($content->errcode == 0){
                echo "删除成功";
            }else{
                echo "删除失败";
            }
        }

        /*
        *发送图文信息（新闻）
        */
        private function _sendNews($postObj){
            $news_tpl = '<xml>
                        <ToUserName><![CDATA[%s]]></ToUserName>
                        <FromUserName><![CDATA[%s]]></FromUserName>
                        <CreateTime>%s</CreateTime>
                        <MsgType><![CDATA[news]]></MsgType>
                        <ArticleCount>%s</ArticleCount>
                        <Articles>
                        %s
                        </Articles>
                    </xml> ';
            $item_tpl = '<item>
                        <Title><![CDATA[%s]]></Title> 
                        <Description><![CDATA[%s]]></Description>
                        <PicUrl><![CDATA[%s]]></PicUrl>
                        <Url><![CDATA[%s]]></Url>
                        </item>';

            $news = array(
                    array(
                            'title'=>'中央农村工作会议25日闭幕 习近平作重要指示',
                            'desc'=>'新华社北京12月25日电中央农村工作会议24日至25日在北京召开。会议全面贯彻落实党的十八大和十八届三中、四中、五中全会以及中央经济工作会议精神，总结“十二五”时期“三农”工作，分析当前农业农村形势，部署2016年和“十三五”时期农业农村工作',
                            'picurl'=>'http://news.cnr.cn/native/gd/20151225/W020151225729848704447.jpg',
                            'url'=>'http://news.cnr.cn/native/gd/20151225/t20151225_520929592.shtml'
                        ),
                    array(
                            'title'=>'中央农村工作会议25日闭幕 习近平作重要指示',
                            'desc'=>'新华社北京12月25日电中央农村工作会议24日至25日在北京召开。会议全面贯彻落实党的十八大和十八届三中、四中、五中全会以及中央经济工作会议精神，总结“十二五”时期“三农”工作，分析当前农业农村形势，部署2016年和“十三五”时期农业农村工作',
                            'picurl'=>'http://news.cnr.cn/native/gd/20151225/W020151225729848704447.jpg',
                            'url'=>'http://news.cnr.cn/native/gd/20151225/t20151225_520929592.shtml'
                        )
                );
            $item_list = '';
            foreach ($news as $val) {
                $item_list .= sprintf($item_tpl,$val['title'],$val['desc'],$val['picurl'],$val['url']);
            }

            $fromUsername = $postObj->FromUserName;
            $toUsername = $postObj->ToUserName;
            $time = time();
            echo sprintf($news_tpl,$fromUsername,$toUsername,$time,count($news),$item_list);
        }

        /*
        *创建素材
        */
        public function _createMedia( $type, $file ){
            $curl = 'https://api.weixin.qq.com/cgi-bin/media/upload?access_token='.$this->_getAccessToken().'&type='.$type;
            $data['type'] = $type;
            $data['media'] = '@'.$file;
            
            var_dump($this->_request($curl,'true','POST',$data));

        }
    }

    /*$wObject = new Wechat('wx430266b80ca7d25a','d4624c36b6795d1d99dcf0547af5443d','');
    header("Content-type:image/jpeg");
    echo $wObject->_getQRCode(1);
    $obj = json_decode(file_get_contents('./postObj.txt'));
    var_dump($obj->EventKey);
    $wObject->_doEvent($obj);*/
    //echo "junwei";

?>