<?php
namespace H5\Controller;
//2、引入核心控制器
use Think\Controller;
//3、定义News控制器
class PayController extends CommonController {

    //在类初始化方法中，引入相关类库
    public function _initialize() {
        vendor('Alipay.Corefunction');
        vendor('Alipay.Md5function');
        vendor('Alipay.Notify');
        vendor('Alipay.Submit');
    }

    public function tryPay(){
        $type = I('get.type','','intval');
        if($type == 1){
            //h5支付宝支付
            $method = 'trpay.trade.create.wap';
            $payType = 1;
            $string  = '支付宝账号支付';
        }elseif($type == 2){
            //支付宝 扫码支付
            $method = 'trpay.trade.create.scan';
            $payType = 1;
            $string  = '支付宝扫码支付';
        }elseif($type == 3){
            //微信 扫码支付
            $method = 'trpay.trade.create.scan';
            $payType = 2;
            $string  = '微信扫码支付';
        }elseif($type == 4){
            //银联 扫码支付
            $method = 'trpay.trade.create.scan';
            $payType = 3;
            $string  = '银联扫码支付';
        }elseif($type == 5){
            //QQ 扫码支付
            $method = 'trpay.trade.create.scan';
            $payType = 4;
            $string  = 'QQ扫码支付';
        }else{
            $this -> ajaxReturnData(0,'请输入参数');
        }
        $url = "http://pay.trsoft.xin/order/trpayGetWay";
        //公共入参
        $appkey = '772576d5bfe240f79bd3065f6d35b74c';
        $timestamp = time();
        $version = '1.0';
        $notifyUrl = 'http://h5.lingjiuyi.cn/Pay/tryPayNotifyUrl';
        $synNotifyUrl = 'http://h5.lingjiuyi.cn/Pay/tryPaySynNotifyUrl';

        $order = D('order') -> where(['id' => I('get.id','','intval')]) -> find();

        //订单参数
        $data = [
            'outTradeNo'   => $order['order_sn'], //订单号
            'tradeName'    => 'a', //商品名称
            'amount'       => 1, //订单金额 单位分
            'appkey'       => $appkey,
            'payType'      => $payType, //订单类型 1 支付宝、2 微信 、 3银联
            'notifyUrl'    => $notifyUrl, //异步通知地址
            'synNotifyUrl' => $synNotifyUrl, //同步通知地址
            'payuserid'    => $order['user_id'], //支付用户id
            'channel'      => 'h5', //渠道
            'backparams'   => 'type='.$type.'&string='.$string, //回传参数
            'ipAddress'    => $_SERVER['REMOTE_ADDR'],//客户端ip地址，当为微信扫码、qq扫码时必传
            'method'       => $method,
            'version'      => $version,
            'timestamp'    => $timestamp,
        ];
        ksort($data);
        $stringA = '';
        foreach($data as $k => $v){
            $stringA .= $k.'='.$v.'&';
        }
        $stringA = rtrim($stringA,'&');
        $stringSignTemp = $stringA.'&appSceret=362a38b2c3874c39ad27c73bc9827df1';
        $sign = strtoupper(MD5($stringSignTemp));
        $data['sign'] = $sign;
        $res = curl_request($url,true,$data);
        if($type == 1){
            echo $res;
        }elseif($type == 2  || $type == 3 || $type == 4 || $type == 5){
            $result = json_decode($res,true);
            if($result['code'] == '0000'){
                $path = 'Public/Uploads/pay_temp/';//定义保存图片地址
                if(!file_exists($path))
                {
                    mkdir($path, 0777, true);//如果没有则创建目录
                }
                $filename = $path.$result['data']['outTradeNo'].'.png';
                $url = $result['data']['qrcode'];
                qrcode($url, $filename);
                $img = 'http://'.$_SERVER['SERVER_NAME'].'/'.$filename;
                $this -> ajaxReturnData(10000,'success',$img);
            }else{
                $this -> ajaxReturnError($result['code'],$result['tipMsg']);
            }

        }

    }

    public function tryPayNotifyUrl(){
        $content = '';
        foreach($_POST as $k => $v){
            $content .= $k.'='.$v.'&';
        }
        $content = rtrim($content,'&');
        D('Temp') -> add(['content' => $content]);
        $order = D('Order') -> where(['order_sn' => $_POST['outTradeNo']]) -> find();
        if(!empty($order)){
            $_POST['amount'] != $order['order_amount'] * 100 ? $this -> ajaxReturnError(0,'金额不符合') : true;
            $_POST['payUserId'] != $order['user_id'] ? $this -> ajaxReturnError(0,'用户不符合') :true;
            $order['pay_status'] != 0 ? $this -> ajaxReturnError(0,'订单支付状态错误') : true;
            $order['order_status'] != 0 ? $this -> ajaxReturnError(0,'订单状态错误') : true;
            $data = [
                'pay_status' => 1,
                'order_status' => 1,
            ];
            $res = D('Order') -> where(['order_sn' => $_POST['outTradeNo']]) -> save($data);
            if(!$res){
                D('Temp') -> add(['content' => '订单'.$_POST['outTradeNo'].'修改订单状态错误！']);
            }
        }
    }

    public function tryPaySynNotifyUrl(){
        //展示页
        $order = D('Order') -> where(['order_sn' => $_GET['outTradeNo']]) -> find();
        $data = [
            'trOrderNo' => $_GET['trOrderNo'], //TrPay平台订单号
            'tradeNo' => $_GET['tradeNo'], //第三方交易号
        ];
        $this -> redirect('Order/detail/id/'.$order['id']);
    }

    public function index(){
        $id    = $_GET['id'];
        $type  = D('Order') ->where("id = $id") -> getField('pay_type');
        switch ($type){
            case 0:
                //银联扫码
                $this->redirect('Pay/tryPay',['type' => 4,'id' => $id]);
                break;
            case 1:
                //微信扫码
                //$this->redirect('Pay/weixinpay',['id' => $id]);
                $this->redirect('Pay/tryPay',['type' => 3,'id' => $id]);
                break;
            case 2:
                //支付宝手机端
                //$this->redirect('Pay/alipay',['id' => $id]);
                $this->redirect('Pay/tryPay',['type' => 1,'id' => $id]);
                break;
            case 3:
                //余额
                break;
            case 4:
                //支付宝扫码
                $this->redirect('Pay/tryPay',['type' => 2,'id' => $id]);
                break;
        }
        /*
        if(isset($_POST['APP_PAY'])){
            //APP调用接口
            $url = full_url('Pay/apppay');
            $this->ajaxReturnSuccess(compact('url'));
        }elseif(is_weixin_browser()){
            //微信中的支付
            $this->redirect('Pay/weixinpay');
        }else{
            //普通浏览器
            $this->redirect('Pay/doalipay',['id' => $id]);
        }
        */

    }

    //APP支付
    public function apppay(){

    }

    //微信支付
    public function weixinpay($id){
        $order = D('Order') -> where("id = $id") -> find();
        if($order['order_status'] != 0){
            $this->success('订单已支付成功',U('user/order'));
        }
        vendor('weixinpay.lib.WxPay#Api');
        vendor('weixinpay.example.WxPay#NativePay');
        vendor('weixinpay.example.WxPay#JsApiPay');
        vendor('weixinpay.example.log');


        //如果是web端 则扫码支付
        //b，进行相应的参数组装
        $notify = new \NativePay();
        $input = new \WxPayUnifiedOrder();

        $input->SetBody("零玖一" . $order['order_sn']);//商品描述
        $input->SetAttach("test");//附加数据
        $input->SetOut_trade_no(\WxPayConfig::MCHID.date("YmdHis"));//商户订单号（网站自己的订单编号）
        $input->SetTotal_fee($order['order_amount'] * 100 ); //订单金额（单位：分）
        $input->SetTime_start(date("YmdHis"));//交易起始时间
        $input->SetTime_expire(date("YmdHis", time() + 1800));//交易结束时间
        $input->SetGoods_tag("test");//订单优惠标记
        $input->SetNotify_url("http://paysdk.weixin.qq.com/example/notify.php");//通知地址（异步通知），必须线上可以访问

        //如果是微信浏览器 则jsapi支付 暂时没有权限
        if(is_weixin_browser()){

            $input->SetTrade_type("JSAPI");//交易类型（MWEB为h5支付）JSAPI | NATIVE | APP | WAP
            $input->SetProduct_id("123456789");//商品ID
            //$openId = session('weixin_openid');
            $openId = "oEIZDuHaBLb89Q7WaAw7cYcYk9KQ";//先使用自己的测试
            $input->IsOpenidSet($openId);

            Vendor('weixinapi.system.WxPayPubHelper.WxPayPubHelper');
            //使用jsapi接口
            $jsApi = new \JsApi_pub();
            if(empty($openId)){

                //=========步骤1：网页授权获取用户openid============
                //通过code获得openid
                //if (!isset($_GET['code']))
                //{
                    //触发微信返回code码
                //    $url = $jsApi->createOauthUrlForCode(\WxPayConf_pub::JS_API_CALL_URL);
                //    Header("Location: $url");
                //}else
                //{
                    //获取code码，以获取openid
                //    $code = $_GET['code'];
                //    $jsApi->setCode($code);
                //    $openId = $jsApi->getOpenId();
                //}

                //或者
                //后期修改为登录获取用户的真实openid
                $config = C('WEIXIN_JSAPI_PAY');
                //$config['CALLBACK'] = "http://dg.v-town.cc/weixin/login_response";
                $config['CALLBACK'] = "http://192.168.0.63/H5/Pay/login_response";

                Vendor('weixinapi.public.class#authorize');
                $tool = new \weixintoolauthorize($config);
                $result = $tool->target();
                $this -> ajaxReturnData(0,'00',$result);
            }

            //c，获取支付地址
            $result = \WxPayApi::unifiedOrder($input);
            $prepay_id = $result['prepay_id'];
            $jsApi->setPrepayId($prepay_id);
            $jsApiParameters = $jsApi->getParameters();
            $this -> ajaxReturnData(10002,'微信浏览器支付',$jsApiParameters);

        }elseif(isMobile()){

//            $config = C('WEIXIN_JSAPI_PAY');
            $config =
                array(
                    'APPID'      => 'wx02f1ef39672b54ca', // 微信支付APPID
                    'MCHID'      => '1327248801', // 微信支付MCHID 商户收款账号
                    'KEY'        => '084fccd48ed8d3e2115faa56f6db579b', // 微信支付KEY
                    'APPSECRET'  => '6a8ecf87d7b4fb648b88e6f5fea8692c',  //公众帐号secert

                    'REDIRECT_URL'=>'http://sj.v-town.cc/pages/recharge.html',//回调路径(支付成功跳转的路径)
                    'NOTIFY_URL' => 'http://sjsc.v-town.cc/wechatH5/H5Notify/', // 接收支付状态的连接
                );


            $subject = "零玖一" . $order['order_sn']; //商品描述
            $total_amount = $order['order_amount'] * 100; //金额
            $order_id = $order['order_sn']; ////订单号
            $nonce_str=MD5($order_id);    //随机字符串
            $spbill_create_ip = get_ip(); //终端ip
            $key = $config['KEY'];
            //以上参数接收不必纠结，按照正常接收就行，相信大家都看得懂

            //$spbill_create_ip = '118.144.37.98'; //终端ip测试
            $trade_type = 'MWEB';//交易类型 具体看API 里面有详细介绍

            $notify_url = 'http://sj.v-town.cc/pages/recharge.html'; //回调地址
            $scene_info ='{"h5_info":{"type":"Wap","wap_url":"http://www.123.com","wap_name":"test"}}'; //场景信息
            //对参数按照key=value的格式，并按照参数名ASCII字典序排序生成字符串
            $signA = "appid=".$config['APPID']."&body=$subject&mch_id=".$config['MCHID']."&nonce_str=$nonce_str&notify_url=$notify_url&out_trade_no=$order_id
　　　　　　&scene_info=$scene_info&spbill_create_ip=$spbill_create_ip&total_fee=$total_amount&trade_type=$trade_type";

            $strSignTmp = $signA."&key=$key"; //拼接字符串

            $sign = strtoupper(MD5($strSignTmp)); // MD5 后转换成大写

//            $post_data = "<xml>
//                       <appid>".$config['APPID']."</appid>
//                       <body>$subject</body>
//                       <mch_id>".$config['MCHID']."</mch_id>
//                       <nonce_str>$nonce_str</nonce_str>
//                       <notify_url>$notify_url</notify_url>
//                       <out_trade_no>$order_id</out_trade_no>
//                       <scene_info>$scene_info</scene_info>
//                       <spbill_create_ip>$spbill_create_ip</spbill_create_ip>
//                       <total_fee>$total_amount</total_fee>
//                       <trade_type>$trade_type</trade_type>
//                       <sign>$sign</sign>
//                       </xml>";//拼接成XML 格式
            $post_data = "<xml>
                            <appid>wx02f1ef39672b54ca</appid>
                            <body>零玖一2018011715161748203750</body>
                            <mch_id>1327248801</mch_id>
                            <nonce_str>4a4fd08391865a1e012f912ae8cdeb6c</nonce_str>
                            <notify_url>http://sj.v-town.cc/pages/recharge.html</notify_url>
                            <out_trade_no>2018011715161748203750</out_trade_no>
                            <scene_info>{\"h5_info\":{\"type\":\"Wap\",\"wap_url\":\"http://www.123.com\",\"wap_name\":\"test\"}}</scene_info>
                            <spbill_create_ip>124.202.218.226</spbill_create_ip>
                            <total_fee>89900</total_fee>
                            <trade_type>MWEB</trade_type>
                            <sign>90570D24D5D2AD27BD28DE769ECFAE3C</sign>
                        </xml>";//拼接成XML 格式

            $url = "https://api.mch.weixin.qq.com/pay/unifiedorder";//微信传参地址
            $dataxml = http_post($url,$post_data); //后台POST微信传参地址  同时取得微信返回的参数，http_post方法请看下文
            $objectxml = (array)simplexml_load_string($dataxml, 'SimpleXMLElement', LIBXML_NOCDATA); //将微信返回的XML 转换成数组
            if($objectxml['return_code'] == 'SUCCESS')  {
                if($objectxml['result_code'] == 'SUCCESS'){//如果这两个都为此状态则返回mweb_url，详情看‘统一下单’接口文档
                    $this -> ajaxReturnData(10040,'微信h5支付',$objectxml['mweb_url']);
//                    return $objectxml['mweb_url']; //mweb_url是微信返回的支付连接要把这个连接分配到前台
                }
                if($objectxml['result_code'] == 'FAIL'){
                    $err_code_des = $objectxml['err_code_des'];
                    return $err_code_des;
                }
            }


        }else{

            //如果是web端 则扫码支付
            //交易类型（native为扫码支付）
            $input->SetTrade_type("NATIVE");
            //商品ID
            $input->SetProduct_id("123456789");

            //c，获取支付地址
            $result = $notify->GetPayUrl($input);
            $url2 = $result["code_url"];
            //d，输出二维码图片
            $url = "http://paysdk.weixin.qq.com/example/qrcode.php?data=" . urlencode($url2);
            $this -> ajaxReturnData(10001,'支付二维码图片',$url);

        }


    }

    public function login_response(){
        $config = C('WEIXIN_JSAPI_PAY');
        //$config['CALLBACK'] = "http://dg.v-town.cc/weixin/login_response";
        $config['CALLBACK'] = "http://192.168.0.63/H5/Pay/login_response";

        Vendor('weixinapi.public.class#authorize');
        $tool = new \weixintoolauthorize($config);
        $result = $tool->getAuthorize();
        //session('weixin_openid',$result['openid']);
        session('weixin_openid',"oEIZDuHaBLb89Q7WaAw7cYcYk9KQ");
    }


    //浏览器支付
    //alipay支付宝支付
    //该方法其实就是将接口文件包下alipayapi.php的内容复制过来
    //然后进行相关处理
    function alipay($id){
        $order = D('Order') -> where("id = $id") -> find();
        $_POST['trade_no']     = $order['order_sn'];
        $_POST['ordsubject']   = $order['order_sn'];
        $_POST['ordtotal_fee'] = $order['order_amount'];
        $_POST['ordbody']      = order_type($order['order_type']);
        $_POST['ordshow_url']  = 'User/Order/type/'.$order['order_type'];

        //这里我们通过TP的C函数把配置项参数读出，赋给$alipay_config；
        $alipay_config=C('ALIPAY_CONFIG');

        /**************************请求参数**************************/
        $payment_type = "1";                        //支付类型 //必填，不能修改
        $notify_url = C('ALIPAY_CONFIG.notify_url');       //服务器异步通知页面路径
        $return_url = C('ALIPAY_CONFIG.return_url');       //页面跳转同步通知页面路径
        $seller_email = C('ALIPAY_CONFIG.seller_email');   //卖家支付宝帐户必填
        $out_trade_no = $_POST['trade_no'];         //商户订单号 通过支付页面的表单进行传递，注意要唯一！
        $subject = $_POST['ordsubject'];            //订单名称 //必填 通过支付页面的表单进行传递
        $total_fee = $_POST['ordtotal_fee'];        //付款金额  //必填 通过支付页面的表单进行传递
        $body = $_POST['ordbody'];                  //订单描述 通过支付页面的表单进行传递
        $show_url = $_POST['ordshow_url'];          //商品展示地址 通过支付页面的表单进行传递
        $anti_phishing_key = "";                    //防钓鱼时间戳 //若要使用请调用类文件submit中的query_timestamp函数
        $exter_invoke_ip = get_client_ip();         //客户端的IP地址
        /************************************************************/

        //构造要请求的参数数组，无需改动
        $parameter = array(
            "service" => "create_direct_pay_by_user",
            "partner" => trim($alipay_config['partner']),
            "payment_type"    => $payment_type,
            "notify_url"    => $notify_url,
            "return_url"    => $return_url,
            "seller_email"    => $seller_email,
            "out_trade_no"    => $out_trade_no,
            "subject"    => $subject,
            "total_fee"    => $total_fee,
            "body"            => $body,
            "show_url"    => $show_url,
            "anti_phishing_key"    => $anti_phishing_key,
            "exter_invoke_ip"    => $exter_invoke_ip,
            "_input_charset"    => trim(strtolower($alipay_config['input_charset']))
        );
        //建立请求
        $alipaySubmit = new \AlipaySubmit($alipay_config);
        $html_text = $alipaySubmit->buildRequestForm($parameter,"post", "确认");
        echo $html_text;
    }


    //服务器异步通知页面方法
    //其实这里就是将notify_url.php文件中的代码复制过来进行处理
    function notifyurl(){

        //这里还是通过C函数来读取配置项，赋值给$alipay_config
        $alipay_config=C('ALIPAY_CONFIG');
        //计算得出通知验证结果
        $alipayNotify = new \AlipayNotify($alipay_config);
        //$verify_result = $alipayNotify->verifyNotify();
        //if($verify_result) {
            //验证成功
            //获取支付宝的通知返回参数，可参考技术文档中服务器异步通知参数列表
            $out_trade_no   = $_POST['out_trade_no'];      //商户订单号
            $trade_no       = $_POST['trade_no'];          //支付宝交易号
            $trade_status   = $_POST['trade_status'];      //交易状态
            $total_fee      = $_POST['total_fee'];         //交易金额
            $notify_id      = $_POST['notify_id'];         //通知校验ID。
            $notify_time    = $_POST['notify_time'];       //通知的发送时间。格式为yyyy-MM-dd HH:mm:ss。
            $buyer_email    = $_POST['buyer_email'];       //买家支付宝帐号；
            $parameter = array(
                "out_trade_no"     => $out_trade_no, //商户订单编号；
                "trade_no"     => $trade_no,     //支付宝交易号；
                "total_fee"     => $total_fee,    //交易金额；
                "trade_status"     => $trade_status, //交易状态
                "notify_id"     => $notify_id,    //通知校验ID。
                "notify_time"   => $notify_time,  //通知的发送时间。
                "buyer_email"   => $buyer_email,  //买家支付宝帐号；
            );

            if($_POST['trade_status'] == 'TRADE_FINISHED' || $_POST['trade_status'] == 'TRADE_SUCCESS') {
                //普通及时到账：不允许退款，支付成功返回交易状态为TRADE_FINISHED
                //高级及时到账：允许退款，支付成功返回交易状态为TRADE_SUCCESS
                //if(!checkorderstatus($out_trade_no)){
                //    orderhandle($parameter);
                    //进行订单处理，并传送从支付宝返回的参数；
                //}
                $data['order_status'] = 1;//订单状态：1,待发货
                $data['pay_status']   = 1;//支付状态：1已付款
                D('Order') ->where("order_sn = $out_trade_no") -> save($data);
                echo "success";        //请不要修改或删除
            }else {
                echo "trade_status=".$_GET['trade_status'];
                $this->redirect(C('ALIPAY_CONFIG.errorpage'));//跳转到配置项中配置的支付失败页面；
            }

        //}else {
            //验证失败
            //echo "fail";
        //}
    }


    // 页面跳转处理方法；
    // 这里其实就是将return_url.php这个文件中的代码复制过来，进行处理；
    function returnurl(){
        //头部的处理跟上面两个方法一样，这里不罗嗦了！
        $alipay_config=C('ALIPAY_CONFIG');
        $alipayNotify = new \AlipayNotify($alipay_config);//计算得出通知验证结果
        //$verify_result = $alipayNotify->verifyReturn();
        //if($verify_result) {
            //验证成功
            //获取支付宝的通知返回参数，可参考技术文档中页面跳转同步通知参数列表
            $out_trade_no   = $_GET['out_trade_no'];      //商户订单号

            if($_GET['trade_status'] == 'TRADE_FINISHED' || $_GET['trade_status'] == 'TRADE_SUCCESS') {
                //if(!checkorderstatus($out_trade_no)){
                //    orderhandle($parameter);  //进行订单处理，并传送从支付宝返回的参数；
                //}
                $data['order_status'] = 1;//订单状态：1,待发货
                $data['pay_status']   = 1;//支付状态：1已付款
                D('Order') ->where("order_sn = $out_trade_no") -> save($data);
                $this->redirect(C('ALIPAY_CONFIG.successpage'));//跳转到配置项中配置的支付成功页面；
            }else {
                echo "trade_status=".$_GET['trade_status'];
                $this->redirect(C('ALIPAY_CONFIG.errorpage'));//跳转到配置项中配置的支付失败页面；
            }
        //}else {
            //验证失败
            //如要调试，请看alipay_notify.php页面的verifyReturn函数
            //echo "支付失败！";
        //}
    }

}