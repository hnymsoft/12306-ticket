<?php
//Ticket config
return [
    'CaptchaConf' => [
        'isAutoCaptcha' => true, //是否自动输入验证码
        'Baiduai' => [ //百度AI图像识别
            'appId' => '***',
            'apiKey' => '***',
            'secretKey' => '***'
        ]
    ],
    'TicketBase' => [
        'username' => '***',  // 12306帐号
        'password' => '***',  // 12306密码
        'auto' => true, //是否开启自动购票 true 自动  false 手动
        'isTicket' => [6,23], //6-23购票时间段
        'refresh' => 3,//购票刷新间隔单位（秒）
    ],
    'TicketInfo' => [
        'seat_index' => '9', //席别索引号（1 硬座 2 软座 3 硬卧 4 软卧 5 高级软卧 6 商务座 7 动卧 8 一等座 9 二等座） 例：8
        'trips_num' => '', //购买车次 例：G1
        'passenger' => '',  //购票人（多人以逗号分隔开） 例：孙**,刘**
    ],
    'TicketQuery' => [
        'from_station' => '', //例：北京南
        'to_station' => '', //例：上海虹桥
        'train_date' => '', //例：2019-02-14
        'back_train_date' => ''
    ],
    'TicketNotify' => [
        'Email' => [
            'is_open' => 'YES',
            'from' => '***@163.com',
            'to' => '***@qq.com'
        ],
        'Sms' => [
            'is_open' => 'NO',
        ]
    ],
    'TicketUrl' => [
        'domain' => 'https://kyfw.12306.cn',
        'user' => [
            'check_user' => '/otn/login/checkUser',
            'init' => '/otn/login/init',
            'captcha_image' => '/passport/captcha/captcha-image?login_site=E&module=login&rand=sjrand&'.mt_rand(0, 999),
            'captcha_check' => '/passport/captcha/captcha-check',
            'login' => '/passport/web/login',
            'uamtk' => '/passport/web/auth/uamtk',
            'uamauthclient' => '/otn/uamauthclient',
            'passengers' => '/otn/passengers/init'
        ],
        'train' => [
            'query_ticket' => '/otn/leftTicket/queryZ'
        ],
        'order' => [
            'submit_order_request' => '/otn/leftTicket/submitOrderRequest',
            'init_dc' => '/otn/confirmPassenger/initDc',
            'check_order_info' => '/otn/confirmPassenger/checkOrderInfo',
            'queue_count' => '/otn/confirmPassenger/getQueueCount', //暂时没有反应
            'queue' => '/otn/confirmPassenger/confirmSingleForQueue',
            'query_order' => '/otn/confirmPassenger/queryOrderWaitTime'
        ]
    ],
    'SeatType' => [
        1 => ['index' => 1,'name' => '硬座','value' => '1','key' => 'yz'],
        2 => ['index' => 2,'name' => '软座','value' => '2','key' => 'rz'],
        3 => ['index' => 3,'name' => '硬卧','value' => '3','key' => 'yw'],
        4 => ['index' => 4,'name' => '软卧','value' => '4','key' => 'rw'],
        5 => ['index' => 5,'name' => '高级软卧','value' => '6','key' => 'gjrw'],
        6 => ['index' => 6,'name' => '商务座','value' => '9','key' => 'swz'],
        7 => ['index' => 7,'name' => '动卧','value' => 'F','key' => 'dw'],
        8 => ['index' => 8,'name' => '一等座','value' => 'M','key' => 'ydz'],
        9 => ['index' => 9,'name' => '二等座','value' => 'O','key' => 'edz']
    ]
];
