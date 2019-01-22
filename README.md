12306-ticket
-------------------
本抢票工具基于Yii2 console程序开发，支持全自动、半自动抢票等
    使用方法：根目录 CLI模式下运行 yii ticket/index
      
      config/ticket_cfg.php          购票主配置文件
      
            'CaptchaConf' => [
                'isAutoCaptcha' => true, //是否自动输入验证码
                'Baiduai' => [ //百度AI图像识别
                    'appId' => '*',
                    'apiKey' => '*',
                    'secretKey' => '*'
                ]
            ],
            'TicketBase' => [
                'username' => '*',  // 12306帐号
                'password' => '*',  // 12306密码
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
                'to_station' => '',   //例：上海虹桥
                'train_date' => '',   //例：2019-02-14
                'back_train_date' => '' //暂时无用
            ],
            'TicketNotify' => [
                'Email' => [
                    'is_open' => 'YES',
                    'from' => '',
                    'to' => ''
                ],
                'Sms' => [
                    'is_open' => 'NO',
                ]
            ]
            
      config/console.php             邮件发送服务器配置
      
            'mailer' => [
                'class' => 'yii\swiftmailer\Mailer',
                'useFileTransport' => false,
                'transport' => [
                    'class' => 'Swift_SmtpTransport',
                    'host' => '',
                    'username' => '*',//发送者邮箱地址
                    'password' => '*', //SMTP密码 此处为邮件服务授权密码
                    'port' => '25',
                    'encryption' => 'tls',
                ],
                'messageConfig'=>[
                    'charset'=>'UTF-8',
                    'from'=>[''=>'system']
                ],
            ],
