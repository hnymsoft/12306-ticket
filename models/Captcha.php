<?php
namespace app\models;
use \Intervention\Image\ImageManager;
use \thiagoalessio\TesseractOCR\TesseractOCR;
use yii\base\Model;
use yii\helpers\Console;

class Captcha extends Model {
    protected $captchaPath;
    protected $captchaWordPath;
    protected $captchaSubPath;
    protected $isAutoCaptcha;
    protected $isAi;
    protected $managerObj;
    protected $aipImageObj;
    protected $aipOcrObj;
    protected $baiduAiObj;
    protected $captcha_conf;

    //验证码坐标
    const CAPTCHA_POSITION = [
        [41,46],
        [112,47],
        [193,48],
        [264,49],
        [42,115],
        [113,116],
        [194,117],
        [265,118],
    ];

    public function __construct(){
        $this->captchaPath = dirname(__DIR__). '/code.jpeg';
        $this->captchaWordPath = dirname(__DIR__).'/statics/image/word/word.jpg';
        $this->captchaSubPath = dirname(__DIR__).'/statics/image/thumb/';
        $this->managerObj = new ImageManager(array('driver' => 'imagick'));
        $this->captcha_conf = \Yii::$app->params['CaptchaConf'];
        $this->isAutoCaptcha = $this->captcha_conf['isAutoCaptcha'];
        $this->isAi = $this->checkBaiduaiConf();
        if ($this->isAi) {
            //引入百度AI类
            require_once dirname(__DIR__).'/sdk/aip/AipImageClassify.php';
            require_once dirname(__DIR__).'/sdk/aip/AipOcr.php';
            $this->aipImageObj = new \AipImageClassify($this->captcha_conf['Baiduai']['appId'],$this->captcha_conf['Baiduai']['apiKey'],$this->captcha_conf['Baiduai']['secretKey']);
            $this->aipOcrObj = new \AipOcr($this->captcha_conf['Baiduai']['appId'],$this->captcha_conf['Baiduai']['apiKey'],$this->captcha_conf['Baiduai']['secretKey']);
        }
        $this->checkAutoEnv();
        parent::__construct();
    }

    /**
     * 检测是否配置百度AIkey
     * @return bool
     */
    private function checkBaiduaiConf() {
        if (empty($this->captcha_conf['Baiduai']['appId']) ||
            empty($this->captcha_conf['Baiduai']['apiKey']) ||
            empty($this->captcha_conf['Baiduai']['secretKey'])) {
            return false;
        }
        return true;
    }

    /**
     * 检测是否安装、开启扩展
     * @return string
     */
    private function checkAutoEnv() {//
        // check 图像处理，依赖：GD Library、Imagick PHP extension
        if ($this->isAutoCaptcha) {
            if (!extension_loaded('gd')) {
                return '请检查PHP环境是否引入 GD 库';
            }
            if (!extension_loaded('imagick')) {
                return '请检查PHP环境是否引入 Imagick 拓展';
            }
        }

        // check tesseract-ocr
        if ($this->isAutoCaptcha && !$this->isAi) {
            $cmd = stripos(PHP_OS, 'win') === 0
                ? 'where.exe "tesseract" > NUL 2>&1'
                : 'type "tesseract" > /dev/null 2>&1';
            system($cmd, $exitCode);
            if ($exitCode != 0) {
                return '请检查环境是否安装 Tesseract OCR';
            }
        }
    }

    /**
     * 截图图片文字
     * @return \Intervention\Image\Image
     */
    private function captureWord() {
        return $this->managerObj
            ->make($this->captchaPath)
            ->crop(130, 25, 120, 2)
            ->save($this->captchaWordPath);
    }

    /**
     * 截取图片
     * @return array
     */
    private  function captureThumb() {
        $subImgs = [];
        $key = 0;
        $width = 67;
        $height = 67;
        $topX = 5;
        $topY = 41;
        $space = 5;
        for ($y = 0; $y < 2; $y++) {
            for ($x = 0; $x < 4; $x++) {
                $subImg = $this->managerObj
                    ->make($this->captchaPath)
                    ->crop($width, $height, $topX + ($space + $width) * $x, $topY + ($space + $height) * $y)
                    ->save($this->captchaSubPath . ($key + 1) . '.jpg');
                $key++;
                $subImgs[$key] = $subImg;
            }
        }
        return $subImgs;
    }

    /**
     * 验证码识别
     * @return array|string
     */
    public function recognitionVerifyCode() {
        if ($this->isAutoCaptcha) {
            $captchaKeyArr = $this->auto();
        } else {
            $captchaKeyArr = $this->manual();
        }
        $captcha = $this->transferPosition($captchaKeyArr);
        return $captcha;
    }

    /**
     * 验证码手动识别
     * @return array
     */
    protected function manual() {
        Ticket::sdtout('
        *****************
        | 1 | 2 | 3 | 4 |
        *****************
        | 5 | 6 | 7 | 8 |
        *****************
        请输入验证码（例如选择第一和第二张，输入1,2）'
        );
        exec('@start ' . $this->captchaPath);
        $input = Console::input();
        $input = str_replace('，', ',', $input);
        $input = str_replace(' ', '', $input);
        return explode(',', $input);
    }

    /**
     * 验证码自动识别
     * @return array
     */
    protected function auto() {
        $this->captureWord();
        $this->captureThumb();

        // 识别汉字
        $keywordByWord = $this->recognizeWord($this->captchaWordPath);
        if (empty($keywordByWord)) {
            return [];
        }

        // 遍历识别每张子图
        $captchaKeyArr = [];
        for ($imgKey = 1; $imgKey <= 8; $imgKey++) {
            $keywordByImg = $this->recognizeThumb($this->captchaSubPath . $imgKey . '.jpg');
            $arr[] = $keywordByImg;
            if ($this->isImgMatchKeyword($keywordByImg, $keywordByWord)) {
                if (!in_array($imgKey, $captchaKeyArr)) {
                    $captchaKeyArr[] = $imgKey;
                    // 单关键字验证码，匹配的图片数量最多为3个
                    if (count($captchaKeyArr) === 3) {
                        break;
                    }
                }
            }
        }
        return $captchaKeyArr;
    }

    /**
     * OCR图片文字识别
     * @param $imgPath
     * @return string
     */
    protected function recognizeWord($imgPath) {
        if ($this->isAi) {
            // 百度AI (50000次/天免费)
            $word = file_get_contents($imgPath);
            $keywordArr = $this->aipOcrObj->basicGeneral($word);
            if (!empty($keywordArr['words_result'][0]['words'])) {
                $keyword = $keywordArr['words_result'][0]['words'];
            }
        } else {
            // 本地OCR
            $keyword = (new TesseractOCR($imgPath))
                ->lang('chi_sim','chi_tra')
                ->run();
        }
        return $keyword ?? '';
    }

    /**
     * 缩略图识别
     * @param $imgPath
     * @return string
     */
    protected function recognizeThumb($imgPath) {
        $keywordByImg = [];
        if ($this->isAi) {
            //百度AI (500次/天免费)
            $image = file_get_contents($imgPath);
            $keywordArr = $this->aipImageObj->advancedGeneral($image);
            if (!empty($keywordArr['result'])) {
                $keywordByImg = implode('|', array_column($keywordArr['result'], 'keyword'));
            }
        }
        return $keywordByImg ? $keywordByImg : '';
    }

    /**
     * 图片文字拆分
     * @param $keywordByImg
     * @param $keywordByWord
     * @return bool
     */
    protected function isImgMatchKeyword($keywordByImg, $keywordByWord) {
        // 拆分每个字符
        $keywordByWordArr = preg_split("//u", $keywordByWord, -1, PREG_SPLIT_NO_EMPTY);
        // 遍历每个汉字
        foreach ($keywordByWordArr as $keywordByWordItem) {
            if (mb_strpos($keywordByImg, $keywordByWordItem) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * 设置图像坐标
     * @param $numArr
     * @return array|string
     */
    protected function transferPosition($numArr) {
        if (empty($numArr) || count($numArr) === 0) {
            return '';
        }
        $numArr = array_unique($numArr);
        $positions = [];
        foreach ($numArr as $num) {
            $num = intval($num);
            $key = ($num >= 1 && $num <= 8) ? $num - 1 : 0;
            list($x, $y) = $this::CAPTCHA_POSITION[$key];
            if (!empty($x) && !empty($y)) {
                $pos = $x . ',' . $y;
                $positions[] = $pos;
            }
        }
        $positions = implode(',', $positions);
        return $positions;
    }
}