<?php

namespace app\models;

use yii\base\Model;
use yii\console\widgets\Table;
use yii\helpers\Console;

class Ticket extends Model
{
    public function __construct(array $config = [])
    {
        parent::__construct($config);
    }

    /**
     * 用户登陆
     * @param $username
     * @param $password
     * @param $cookieArray
     * @return bool
     */
    public static function checkUser($username,$password,&$cookieArray){
        Check:
        $checkUserLoginUrl = urlp('user','check_user');
        $body = '';
        $head = '';
        $cookieArray = static::fileCacheArray('info');
        writeLog(date('Y-m-d H:i:s').'--check_user-- 请求数据：'.var_export($cookieArray,true),'user');
        curlRequest($checkUserLoginUrl, false, ['_json_att' => ''], false, $cookieArray, $body, $head);
        if ($body == '') {
            goto Check;
        }
        $checkUserLoginResult = json_decode($body, true);
        writeLog(date('Y-m-d H:i:s').'--check_user-- 响应数据：'.var_export($checkUserLoginResult,true),'user');
        if ($checkUserLoginResult['data']['flag'] == false) {
            //清空缓存文件Cookie信息
            \Yii::$app->cache->delete('info');

            Ticket::sdtout('正在获取验证码，请稍后...');
            $initUrl = urlp('user','init');
            curlRequest($initUrl, true, [], false, $cookieArray, $body, $head);
            GetYan:
            $yanUrl = urlp('user','captcha_image');
            curlRequest($yanUrl, true, [], false, $cookieArray, $body, $head);
            if ($body == '') {
                Ticket::sdtout('验证码获取失败,准备重试！');
                goto GetYan;
            }
            file_put_contents(dirname(__DIR__). '/code.jpeg', $body);
            $answer = (new Captcha())->recognitionVerifyCode();
            Ticket::sdtout('验证码识别中，请稍候。。。');
            writeLog(date('Y-m-d H:i:s').'--captcha-- 获取验证码坐标'.$answer,'user');
            //验证码 验证
            CheckYan:
            $checkYan = urlp('user','captcha_check'); //post
            $checkData = [
                'answer' => $answer,
                'login_site' => 'E',
                'rand' => 'sjrand'
            ];
            writeLog(date('Y-m-d H:i:s').'--captcha_check-- 请求数据：'.var_export($checkData,true),'user');
            curlRequest($checkYan, false, $checkData, false, $cookieArray, $body, $head);
            if ($body == '') {
                goto CheckYan;
            }
            $json = json_decode($body, true);
            writeLog(date('Y-m-d H:i:s').'--captcha_check-- 响应数据：'.var_export($json,true),'user');
            if ($json['result_code'] != "4") {
                unset($cookieArray['_passport_session']);
                unset($cookieArray['_passport_ct']);
                Ticket::sdtout('验证码检测失败,准备重新获取验证码！');
                goto GetYan;
            } else{
                Ticket::sdtout('开始登录，请稍候。。。');
                LoginPost:
                $body = '';
                $loginData = [
                    'username' => $username,
                    'password' => $password,
                    'appid' => 'otn'
                ];
                $loginPost = urlp('user','login');
                writeLog(date('Y-m-d H:i:s').'--login-- 请求数据：'.var_export($loginData,true),'user');
                curlRequest($loginPost, false, $loginData, false, $cookieArray, $body, $head);
                if ($body == '') {
                    goto LoginPost;
                }
                $loginJson = json_decode($body, true);
                writeLog(date('Y-m-d H:i:s').'--login-- 响应数据：'.var_export($json,true),'user');
                if ($loginJson['result_code'] == 0) {
                    $cookieArray['uamtk'] = $loginJson['uamtk'];
                    Ticket::sdtout('登录成功！');
                    uamTk:
                    writeLog(date('Y-m-d H:i:s').'--uamtk-- 请求数据：'.var_export(['appid' => 'otn'],true),'user');
                    $uamtkUrl = urlp('user','uamtk');
                    curlRequest($uamtkUrl, false, ['appid' => 'otn'], false, $cookieArray, $body, $head);
                    if ($body == '') {
                        goto uamTk;
                    }
                    $uamtkJson = json_decode($body, true);
                    writeLog(date('Y-m-d H:i:s').'--uamtk-- 响应数据：'.var_export($uamtkJson,true),'user');
                    if ($uamtkJson['result_code'] == 0) {
                        $tk = $uamtkJson['newapptk'];
                        $cookieArray['tk'] = $tk;
                        static::fileCacheArray('info',$cookieArray);
                        uamAuthClient:
                        $uamtkClientUrl = urlp('user','uamauthclient');
                        writeLog(date('Y-m-d H:i:s').'--uamauthclient-- 请求数据：'.var_export(['tk' => $tk],true),'user');
                        curlRequest($uamtkClientUrl, false, ['tk' => $tk], false, $cookieArray, $body, $head);
                        if ($body == '') {
                            goto uamAuthClient;
                        }
                        $uamtkClientJson = json_decode($body, true);
                        writeLog(date('Y-m-d H:i:s').'--uamauthclient-- 响应数据：'.var_export($uamtkClientJson,true),'user');
                        if ($uamtkClientJson['result_code'] == 0) {
                            return true;
                        }
                    }
                    goto Check;
                } else {
                    Ticket::sdtout('用户名或密码错误，请检查！');
                    return false;
                }
            }
        }else{
            Ticket::sdtout('已登录！');
            return true;
        }
    }

    /**
     * 火车票查询
     * @param $ticketInfo
     * @param bool $is_show
     * @return array|void
     * @throws \Exception
     */
    public static function ticketQuery($ticketInfo,$is_show = true){
        tripsFind:
        $cookieArray = Ticket::fileCacheArray('info');
        $query_ticket = urlp('train','query_ticket');
        curlRequest($query_ticket, true, [
            'leftTicketDTO.train_date' => $ticketInfo['train_date'],
            'leftTicketDTO.from_station' => $ticketInfo['from_code'],
            'leftTicketDTO.to_station' => $ticketInfo['to_code'],
            'purpose_codes' => 'ADULT'
        ], false, $cookieArray, $body, $head);
        $tripsJson = json_decode($body, true);
        writeLog(date('Y-m-d H:i:s').'--query_ticket-- 响应数据：'.var_export($tripsJson,true),'train');
        if ($tripsJson['httpstatus'] == 200) {
            $headCol = [
                '序号',
                '车次',
                '出发站->到达站',
                '出发时间',
                '到达时间',
                '历时',
                '商务座/特等座',
                '一等座',
                '二等座',
                '高级软卧',
                '软卧',
                '动卧',
                '硬卧',
                '软座',
                '硬座',
                '无座',
                '其他',
                '是否可订票',
            ];
            $tripsResult = [];
            $tripsResultIndex = 0;
            foreach ($tripsJson['data']['result'] as $item) {
                $d = explode('|', $item);
                $tripsResultIndex++;
                $tripsResult[] = [
                    'index' => $tripsResultIndex,
                    'cc' => $d[3],  //车次
                    'cfdd'=> $tripsJson['data']['map'][$d[6]].'->'.$tripsJson['data']['map'][$d[7]],
                    'cf' => $d[8],  //出发时间
                    'dd' => $d[9],  //到达时间
                    'ls' => $d[10], //历时
                    'swz' => isEmptyorNull($d[32]), //商务座/特等座 ok
                    'ydz' => isEmptyorNull($d[31]), //一等座 ok
                    'edz' => isEmptyorNull($d[30]), //二等座 ok
                    'gjrw' => isEmptyorNull($d[21]), //高级软卧 21
                    'rw' => isEmptyorNull($d[23]), //软卧 ok
                    'dw' => isEmptyorNull($d[33]), //动卧 ok
                    'yw' => isEmptyorNull($d[28]), //硬卧 ok
                    'rz' => isEmptyorNull($d[24]), //软座 ok
                    'yz' => isEmptyorNull($d[29]), //硬座 ok
                    'wz' => isEmptyorNull($d[26]), //无座 ok
                    'qt' => isEmptyorNull($d[22]), //其他 ok
                    'ok' => $d[0] == '' ? 'NO' : 'YES', //是否可订票 ok
                    'secretStr' => urldecode($d[0])     //当前车次标记
                ];
            }
            if($is_show){
                Ticket::sdtout('已为你查询到以下车次');
                foreach ($tripsResult AS $key => $val){
                    unset($val['secretStr']);
                }
                $ticketTable =  Table::widget([
                    'headers' => $headCol,
                    'rows' => $tripsResult,
                ]);
                static::sdtout($ticketTable);
            }
            return $tripsResult;
        }else{
            static::sdtout('查询失败，准备重新获取。。。');
            goto tripsFind;
        }
    }

    /**
     * 获取席别信息
     * @return mixed
     * @throws \Exception
     */
    public static function getSeatType(){
        selectSiteIndex:
        $seat_type = \Yii::$app->params['SeatType'];
        $ticketInfo = \Yii::$app->params['TicketInfo'];
        if(\Yii::$app->params['TicketBase']['auto'] === TRUE){
            if(!isset($ticketInfo['seat_index'])){
                static::sdtout('请填写购买车次的席别！');
            }
            $seatIndex = $ticketInfo['seat_index'];
        }else{
            $seatTable = Table::widget([
                'headers' => ['序号', '座位类型', '座位字段', '座位索引'],
                'rows' => $seat_type,
            ]);
            static::sdtout($seatTable);
            static::sdtout('请依据车次选择座位席别！');
            $seatIndex = Console::input();
        }
        if (array_key_exists($seatIndex, $seat_type)) {
            $data['site_name'] = $seat_type[$seatIndex]['name'];
            $data['site_type'] = $seat_type[$seatIndex]['value'];
            $data['site_key'] = $seat_type[$seatIndex]['key'];
        } else {
            static::sdtout('席别错误，请重新选择！');
            goto selectSiteIndex;
        }
        return $data;
    }

    /**
     * 刷票并下单
     * @param array $ticketInfo         购票信息
     * @param array $passengerInfo      乘客信息
     * @param string $tripsCode         车次
     * @param array $seatType          席别
     * @throws \Exception
     */
    public static function brushTicket($ticketInfo = [],$passengerInfo = [],$tripsCode = '',$seatType = []){
        $i = 0;
        while (true){
            $i++;
            static::sdtout("正在为您第{$i}次查询，当前时间：".date('Y-m-d H:i:s'));
            //刷新频次，如未配置默认5秒
            $refresh = \Yii::$app->params['TicketBase']['refresh'];
            $refresh_num = 5;
            if(!empty($refresh) && intval($refresh) > 0){
                $refresh_num = $refresh;
            }
            sleep($refresh_num); //休眠2秒
            $tripsResult = static::ticketQuery($ticketInfo,false);
            foreach ($tripsResult AS $key => $val){
                if($i <= 1 && $val['cc'] == $tripsCode){
                    //显示查询车次的详细信息
                    static::trainDetail($val);
                }
                if($val['ok'] === 'YES' && $val['cc'] == $tripsCode && ($val[$seatType['site_key']] === '有' || (integer)$val[$seatType['site_key']]) > 0){
                    $res = static::submitOrderRequest($ticketInfo,$passengerInfo,$val['secretStr'],$seatType);
                    writeLog(date('Y-m-d H:i:s').'购票状态：'.$res,'order');
                    if($res){
                        exit;
                    }
                }
            }
        }
    }

    /**
     * 获取乘客列表
     * @param $seatType
     * @return array
     * @throws \Exception
     */
    public static function getPassenger($seatType){
        GetPassenger:
        $cookieArray = Ticket::fileCacheArray('info');
        $url = urlp('user','passengers');
        curlRequest($url, false, ['_json_att' => ''], false, $cookieArray, $body, $head);
        if ($body == '') {
            goto GetPassenger;
        }
        preg_match('/passengers=\[.*\];/', $body, $mth);
        $passengerObj = str_replace("passengers=", '', $mth[0]);
        $passengerObj = str_replace(";", '', $passengerObj);
        $passengerJsonStr = str_replace("'", '"', $passengerObj);
        $passengerJson = json_decode($passengerJsonStr, true);
        writeLog(date('Y-m-d H:i:s').'--passengers-- 响应数据：'.var_export($passengerJson,true),'passenger');
        ShowUserList:
        $passengerTicketStr = '';
        $oldPassengerStr = '';
        $passengerRes = [];
        $passengerResIndex = 0;
        foreach ($passengerJson as $item) {
            $passengerResIndex++;
            $headCol = [
                '序号',
                '姓名',
                '身份证号',
                '手机号',
            ];
            $passengerRes[] = [
                'index' => $passengerResIndex,
                'name' => $item['passenger_name'],
                'idCard' => $item['passenger_id_no'],
                'phone' => $item['mobile_no'],
            ];
        }
        $ticketInfo = \Yii::$app->params['TicketInfo'];
        if(\Yii::$app->params['TicketBase']['auto'] == TRUE){
            if(!isset($ticketInfo['passenger'])){
                static::sdtout('请填写购票的乘客，多人请以英文逗号分隔！');
            }
            $passenger = explode(',',$ticketInfo['passenger']);
            foreach ($passengerRes AS $key => $val){
                if(in_array($val['name'],$passenger)){
                    $userArray[] = $val['index'];
                }
            }
        }else{
            $ticketTable =  Table::widget([
                'headers' => $headCol,
                'rows' => $passengerRes,
            ]);
            static::sdtout($ticketTable);
            static::sdtout('请选择乘车人 如果多人请以英文逗号分隔！');
            $userList = Console::input();
            $userArray = explode(',', $userList);
        }
        foreach ($userArray as $value) {
            if (!isset($passengerRes[$value - 1])) {
                static::sdtout('乘客信息错误 请重新选择！');
                goto ShowUserList;
            }
            $passengerTicketStr .= $seatType['site_type'] . ",0,{$passengerJson[$value - 1]['passenger_type']},{$passengerJson[$value - 1]['passenger_name']},{$passengerJson[$value - 1]['passenger_id_type_code']},{$passengerJson[$value - 1]['passenger_id_no']},{$passengerJson[$value - 1]['mobile_no']},N_";
            $oldPassengerStr .= "{$passengerJson[$value - 1]['passenger_name']},{$passengerJson[$value - 1]['passenger_id_type_code']},{$passengerJson[$value - 1]['passenger_id_no']},1_";
        }
        $passengerTicketStr = substr($passengerTicketStr, 0, strlen($passengerTicketStr) - 1);
        $data = [
            'oldPassengerStr' => $oldPassengerStr,
            'passengerTicketStr' => $passengerTicketStr,
        ];
        writeLog(date('Y-m-d H:i:s').'--passengers-- 响应（处理）数据：'.var_export($data,true),'passenger');
        return $data;
    }

    /**
     * 提交订单
     * @param array $ticketInfo
     * @param array $passengerInfo
     * @param $secretStr
     * @return bool
     */
    public static function submitOrderRequest($ticketInfo = [],$passengerInfo = [],$secretStr,$seatType){
        submitOrderRequest:
        $cookieArray = Ticket::fileCacheArray('info');
        $submitOrderRequestUrl = urlp('order','submit_order_request');
        $cookieArray['_jc_save_fromDate'] = $ticketInfo['train_date'];
        $cookieArray['_jc_save_fromStation'] = str_replace("\\", "%", json_encode($ticketInfo['from_name'])) . '%2C' . $ticketInfo['from_code'];
        $cookieArray['_jc_save_showIns'] = 'true';
        $cookieArray['_jc_save_toDate'] = $ticketInfo['back_train_date'];
        $cookieArray['_jc_save_toStation'] = str_replace("\\", "%", json_encode($ticketInfo['to_name'])) . '%2C' . $ticketInfo['to_code'];
        $cookieArray['_jc_save_wfdc_flag'] = 'dc';          //dc 单程 wc 往返
        $submitOrderRequestData = [
            'secretStr' => $secretStr,                                     //查询票的 [0]字段
            'train_date' => $ticketInfo['train_date'],                    //去程日期
            'back_train_date' => $ticketInfo['back_train_date'],          //反程日期
            'tour_flag' => 'dc',                                           //dc 单程 wc 往返
            'purpose_codes' => 'ADULT',                                    //目前未知 固定
            'query_from_station_name' => $ticketInfo['from_name'],
            'query_to_station_name' => $ticketInfo['to_name'],
            'undefined' => '',                                             //目前未知 固定为空
        ];
        writeLog(date('Y-m-d H:i:s').'--submit_order_request-- 请求Cookie数据：'.var_export($cookieArray,true),'order');
        writeLog(date('Y-m-d H:i:s').'--submit_order_request-- 请求数据：'.var_export($submitOrderRequestData,true),'order');
        curlRequest($submitOrderRequestUrl, false, $submitOrderRequestData, false, $cookieArray, $body, $head);
        if ($body == '') {
            goto submitOrderRequest;
        }
        $submitOrderJson = json_decode($body, true);
        writeLog(date('Y-m-d H:i:s').'--submit_order_request-- 响应数据：'.var_export($submitOrderJson,true),'order');
        if ($submitOrderJson['httpstatus'] == 200 && $submitOrderJson['status'] == true) {
            static::sdtout('提交订单成功');
            globalRepeatSubmitToken:
            $initDc = urlp('order','init_dc');
            curlRequest($initDc, false, ['_json_att' => ''], true, $cookieArray, $body, $head);
            if ($body == '') {
                goto globalRepeatSubmitToken;
            }
            preg_match("/globalRepeatSubmitToken\h=\h\'.*\'\;/", $body, $ma);
            if ($ma[0] != '') {
                $st = str_replace("globalRepeatSubmitToken = '", '', $ma[0]);
                $repeatSubmitToken = str_replace("';", '', $st);
            } else {
                goto globalRepeatSubmitToken;
            }
            writeLog(date('Y-m-d H:i:s').'--init_dc-- 响应数据 处理（1）：'.var_export($repeatSubmitToken,true),'order');
            preg_match("/ticketInfoForPassengerForm=.*\}\;/", $body, $ma2);
            if ($ma2[0] != '') {
                $st1 = str_replace("ticketInfoForPassengerForm=", '', $ma2[0]);
                $st2 = str_replace(";", '', $st1);
                $ticketInfoForPassengerForm = str_replace("'", '"', $st2);
                $ticketInfoForPassengerForm = json_decode($ticketInfoForPassengerForm, true);
            }
            writeLog(date('Y-m-d H:i:s').'--init_dc-- 响应数据 处理（2）：'.var_export($ticketInfoForPassengerForm,true),'order');
            //订单验证
            checkOrderInfo:
            $checkOrderInfoUrl = urlp('order','check_order_info');;
            $checkOrderInfoData = [
                'cancel_flag' => '2',
                'bed_level_order_num' => '000000000000000000000000000000',
                'passengerTicketStr' => $passengerInfo['passengerTicketStr'],
                'oldPassengerStr' => $passengerInfo['oldPassengerStr'],
                'tour_flag' => 'dc',                                //dc 单程 wc 往返
                'randCode' => '',                                   //默认空
                'whatsSelect' => '1',                               //暂时默认为1
                '_json_att' => '',                                  //默认空
                'REPEAT_SUBMIT_TOKEN' => $repeatSubmitToken,        //
            ];
            writeLog(date('Y-m-d H:i:s').'--check_order_info-- 请求数据：'.var_export($checkOrderInfoData,true),'order');
            curlRequest($checkOrderInfoUrl, false, $checkOrderInfoData, false, $cookieArray, $body, $head);
            if ($body == '') {
                goto checkOrderInfo;
            }
            $checkOrderInfoDataJson = json_decode($body, true);
            writeLog(date('Y-m-d H:i:s').'--check_order_info-- 响应数据：'.var_export($checkOrderInfoDataJson,true),'order');
            if ($checkOrderInfoDataJson['httpstatus'] == 200 && $checkOrderInfoDataJson['data']['submitStatus'] == true) {
                static::sdtout('验证订单成功');
                queueCount:
                $time = new \DateTime(date('Y-m-d 00:00:00',strtotime($ticketInfo['train_date'])), new \DateTimeZone("GMT+0800"));
                $queueData = [
                    'train_date' => $time->format("D, d M Y H:i:s T"),
                    'train_no' => $ticketInfoForPassengerForm['orderRequestDTO']['train_no'],
                    'stationTrainCode' => $ticketInfoForPassengerForm['orderRequestDTO']['station_train_code'],
                    'seatType' => $seatType['site_type'],
                    'fromStationTelecode' => $ticketInfoForPassengerForm['orderRequestDTO']['from_station_telecode'],
                    'toStationTelecode' => $ticketInfoForPassengerForm['orderRequestDTO']['to_station_telecode'],
                    'leftTicket' => $ticketInfoForPassengerForm['queryLeftTicketRequestDTO']['ypInfoDetail'],
                    'purpose_codes' => 'V',
                    'train_location' => $ticketInfoForPassengerForm['train_location'],
                    'isCheckOrderInfo' => 'W',
                ];
                $queue_url = urlp('order','queue_count');
                writeLog(date('Y-m-d H:i:s').'--queue_count-- 请求数据：'.var_export($queueData,true),'order');
                curlRequest($queue_url, false, $queueData, false, $cookieArray, $body, $head);
                if ($body == '') {
                    goto queueCount;
                }
                $queueCount = json_decode($body, true);
                writeLog(date('Y-m-d H:i:s').'--queue_count-- 响应数据：'.var_export($queueCount,true),'order');
                if ($queueCount['status'] == true && $queueCount['httpstatus'] == 200) {
                    $ticket = explode(',',$queueCount['data']['ticket']);
                    $info = "本次列车，{$seatType['site_name']}余票";
                    if(intval($ticket[0]) >= 0){
                        $info .= "{$ticket[0]}张";
                    }else{
                        $info .= $ticket[0];
                    }
                    if(count($ticket) > 1){
                        $info .= ",无座余票";
                        if (intval($ticket[1]) >= 0) {
                            $info .= "{$ticket[1]}张";
                        } else {
                            $info .= $ticket[1];
                        }
                    }
                    $info .= "。";
                    static::sdtout($info);
                    if ($queueCount['data']['op_2'] == "true") {
                        $info .= '目前排队人数已经超过余票张数，请您选择其他席别或车次。';
                        static::sdtout($info);
                        return true;
                    } else {
                        if (intval($queueCount['data']['countT']) > 0) {
                            $info .= "目前排队人数{$queueCount['data']['countT']}人，";
                            $info .= "系统将为您随机分配席位。";
                            static::sdtout($info);
                        }
                    }
                    if (intval($ticket[0]) > 0 || intval($ticket[1]) > 0 || "充足" == $ticket[0] || "充足" == $ticket[1]) {
                        static::sdtout('票源充足！');
                    }
                } else {
                    static::sdtout($queueCount['messages'][0]);
                }
                confirm:
                $confirmData = [
                    'passengerTicketStr' => $passengerInfo['passengerTicketStr'],
                    'oldPassengerStr' => $passengerInfo['oldPassengerStr'],
                    'randCode' => '',
                    'purpose_codes' => '00',
                    'key_check_isChange' => $ticketInfoForPassengerForm['key_check_isChange'],
                    'leftTicketStr' => $ticketInfoForPassengerForm['leftTicketStr'],
                    'train_location' => $ticketInfoForPassengerForm['train_location'],
                    'choose_seats' => '',
                    'seatDetailType' => '000',
                    'whatsSelect' => '1',
                    'roomType' => '00',
                    'dwAll' => 'N',
                    '_json_att' => '',
                ];
                writeLog(date('Y-m-d H:i:s').'--queue-- 请求数据：'.var_export($confirmData,true),'order');
                $confirm = urlp('order','queue');
                curlRequest($confirm, false, $confirmData, false, $cookieArray, $body, $head);
                if ($body == '') {
                    goto confirm;
                }
                $confirmSingleForQueueRes = json_decode($body, true);
                writeLog(date('Y-m-d H:i:s').'--queue-- 响应数据：'.var_export($confirmSingleForQueueRes,true),'order');
                if ($confirmSingleForQueueRes['httpstatus'] == 200 && $confirmSingleForQueueRes['data']['submitStatus'] == true) {
                    static::sdtout('请稍候，正在排队获取订单号！');
                } else {
                    static::sdtout($confirmSingleForQueueRes['data']['errMsg']);
                    return false;
                }
                $current_queue_wait = 0;
                queryOrder:
                $queryOrderUrl = urlp('order','query_order');
                $time = GTIME * 1000;
                $queryOrderData = [
                    'random' => $time,
                    'tourFlag' => 'dc',
                    '_json_att' => '',
                    'REPEAT_SUBMIT_TOKEN' => $repeatSubmitToken
                ];
                writeLog(date('Y-m-d H:i:s').'--query_order-- 请求URL数据：'.var_export($queryOrderData,true),'order');
                writeLog(date('Y-m-d H:i:s').'--query_order-- 请求Cookie数据：'.var_export($cookieArray,true),'order');
                curlRequest($queryOrderUrl, true,$queryOrderData, false, $cookieArray, $body, $head);
                writeLog(date('Y-m-d H:i:s').'--query_order-- 响应数据：'.$body,'order');
                writeLog(date('Y-m-d H:i:s').'--query_order-- 响应Head数据：'.$head,'order');
                if ($body == '') {
                    Ticket::sdtout('获取订单号失败,准备重试！');
                    goto queryOrder;
                }
                $queryOrderRes = json_decode($body, true);
                writeLog(date('Y-m-d H:i:s').'--query_order-- 响应数据：'.var_export($queryOrderRes,true),'order');
                if ($queryOrderRes['status'] == true && $queryOrderRes['httpstatus'] == 200) {
                    $current_queue_wait++;
                    $result = $queryOrderRes['data'];
                    if(isset($result['orderId'])){
                        $subject = "购票成功~";
                        $body = "购票成功，请访问未完成订单！";
                        static::sdtout($subject);
                        static::sdtout($body);
                        $ticketNotify = \Yii::$app->params['TicketNotify'];
                        //发送邮件
                        if(isset($ticketNotify['Email']['is_open']) && $ticketNotify['Email']['is_open'] == 'YES'){
                            sendMail($subject,$body);
                        }elseif(isset($ticketNotify['Sms']['is_open']) && $ticketNotify['Sms']['is_open'] == 'YES'){
                            //暂无
                        }
                        return true;
                    }elseif (isset($result['waitTime']) && intval($result['waitTime']) >= 0){
                        sleep(1);
                        static::sdtout("排队等待中，预计还需要 {$result['waitTime']} 秒");
                    }elseif(isset($result['msg'])){
                        static::sdtout("排队失败，错误原因: {$result['msg']}");
                        return false;
                    }
                    goto queryOrder;
                }elseif ($queryOrderRes['messages'] || $queryOrderRes['validateMessages']){
                    static::sdtout("排队失败，错误原因: {$queryOrderRes['messages'][0]}");
                    return false;
                }else {
                    static::sdtout("第{$current_queue_wait}次排队，请耐心等待");
                    goto queryOrder;
                }
            } else {
                static::sdtout($checkOrderInfoDataJson['data']['errMsg']);
                return true;
            }
        }else{
            static::sdtout(strip_tags($submitOrderJson['messages'][0]));
            return true;
        }
    }

    /**
     * 车次详情
     * @param $data
     * @throws \Exception
     */
    public static function trainDetail($data){
        $headCol = [
            '车 次','出发站->到达站','出发时间','到达时间','商务座/特等座','一等座','二等座','高级软卧','软卧','动卧','硬卧','软座','硬座','无座','其他',];
        $trainResult[] = [
            'cc' => $data['cc'],  //车次
            'cfdd'=> $data['cfdd'],
            'cf' => $data['cf'],  //出发时间
            'dd' => $data['dd'],  //到达时间
            'swz' => $data['swz'], //商务座/特等座 ok
            'ydz' => $data['ydz'], //一等座 ok
            'edz' => $data['edz'], //二等座 ok
            'gjrw' => $data['gjrw'], //高级软卧 21
            'rw' => $data['rw'], //软卧 ok
            'dw' => $data['dw'], //动卧 ok
            'yw' => $data['yw'], //硬卧 ok
            'rz' => $data['rz'], //软座 ok
            'yz' => $data['yz'], //硬座 ok
            'wz' => $data['wz'], //无座 ok
            'qt' => $data['qt'], //其他 ok
        ];
        $ticketTable =  Table::widget([
            'headers' => $headCol,
            'rows' => $trainResult,
        ]);
        $date = \Yii::$app->params['TicketQuery']['train_date'];
        Ticket::sdtout("*************** 购票日期（{$date}） ***************");
        static::sdtout($ticketTable);
    }

    /**
     * Cookie信息存入文件缓存
     * @param $name
     * @param array $cookieArray
     * @return mixed
     */
    public static function fileCacheArray($name,$cookieArray = []){
        $cache = \Yii::$app->cache;
        $c_name = $cache->get($name);
        if($c_name){
            return $c_name;
        }
        if(!$c_name){
            $cache->set($name,$cookieArray);
        }
    }

    /**
     * 标准输入
     * @param $message
     */
    public static function sdtin($message){
        //$message = Console::isRunningOnWindows() ? iconv('UTF-8','GB2312//IGNORE',$message) : $message;
        Console::input($message);
    }

    /**
     * 标准输出
     * @param $message
     */
    public static function sdtout($message){
        //$message = Console::isRunningOnWindows() ? iconv('UTF-8','GB2312//IGNORE',$message) : $message;
        Console::output($message);
    }

}
