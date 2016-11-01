<?php

namespace yii\web;

use Yii;
use yii\base\InvalidConfigException;
use yii\base\InvalidParamException;
use yii\helpers\Url;
use yii\helpers\FileHelper;
use yii\helpers\StringHelper;

/**
 * 服务器给请求的回应
 * 
 */
class Response extends \yii\base\Response
{
    // response回复是要进行的事务
    const EVENT_BEFORE_SEND = 'beforeSend';

    const EVENT_AFTER_SEND = 'afterSend';

    const EVENT_AFTER_PREPARE = 'afterPrepare';

    // response 回复时设置解析的类型
    const FORMAT_RAW = 'raw';

    const FORMAT_HTML = 'html';

    const FORMAT_JSON = 'json';

    const FORMAT_JSONP = 'jsonp';

    const FORMAT_XML = 'xml';

    public $format = self::FORMAT_HTML;

    public $acceptMineType;

    public $acceptParams = [];

    public $formatters = [];

    public $data;

    public $content;

    public $stream;

    public $charset;

    public $statusText = 'OK';

    public $version;

    public $isSent = false;

    // 设置返回的状态=>短语
    public static $httpStatuses = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        118 => 'Connection timed out',
        200 => 'OK',
        201 => 'Create',
        202 => 'Accepted',
        203 => 'Non-Authoritaive',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partical Content',
        207 => 'Mlti-Status',
        208 => 'Already Reported',
        210 => 'Content Different',
        226 => 'IM Used',
        300 => 'Multiple Choices',
        301 => 'Move Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => 'Reserved',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        310 => 'Too Many Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request time-out',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested range unstatisfiable',
        417 => 'Exception Failed',
        418 => 'I\'m a teapot',
        421 => 'Misdirected Request',
        422 => 'Unprocessable entity',
        423 => 'Locked',
        424 => 'Mehtod Failure',
        425 => 'Unordered Collection',
        426 => 'Ungrade Required',
        428 => 'PreCondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        449 => 'Retry With',
        450 => 'Blocked by Windows Parental Controls',
        500 => 'Internal Server Error',
        501 => 'Not implemented',
        502 => 'Bad Gateway or Proxy Error',
        503 => 'Service Unavaliable',
        504 => 'Gateway Time-out',
        505 => 'Http Version not supported',
        507 => 'Insufficient storage',
        508 => 'Loop Detected',
        510 => 'Not Extented',
        511 => 'Network Authentication Required',
    ];

    private $_statusCode = 200;

    // header的实例
    private $_headers;

    public function init()
    {
        if ($this->version === null) {
            if (isset($_SERVER['SERVER_PROTOCOL']) && $_SERVER['SERVER_PROTOCOL'] === 'HTTP/1.0') {
                $this->version = '1.0';
            } else {
                $this->version = '1.1';
            }
        }
        if ($this->charset === null) {
            $this->charset = Yii::$app->charset;
        }

        $this->formatters = array_merge($this->defaultFormatters(), $this->fomatters);
    }

    public function getStatusCode()
    {
        return $this->_statusCode;
    }
    
    public function setStatusCode($value, $text = null)
    {
        if ($value === null) {
            $value = 200;
        } 
        $this->_statusCode = (int) $value;
        if ($this->getIsInvalid()) {
            throw new InvalidInvalidException('The Http status code is invalid:' . $value);
        }

        if ($text === null) {
            $this->statusText = isset(static::$httpStatuses[$this->_statusCode]) ? static::$httpStatuses[$this->_statusCode] : '';
        } else {
            $this->statusText = $text;
        }
    }

    public function getHeaders()
    {
        if ($this->_headers === null) {
            $this->_headers = new HeaderCollection;
        }

        return $this->_headers;
    }

    /*
     * 这个是发送回复时进行的所有的事务
     * 必须要按顺序来进行
     */
    public function send()
    {
        if ($this->isSent) {
            return;
        }
        $this->trigger(self::EVENT_BEFORE_SEND);
        $this->prepare();
        $this->trigger(self::EVENT_AFTER_PREPARE);
        $this->sendHeaders();
        $this->sendContent();
        $this->trigger(self::EVENT_AFTER_SEND);
        $this->isSent = true;
    }

    public function clear()
    {
        $this->_headers = null;
        $this->_cookies = null;
        $this->_statusCode = 200;
        $this->statusText = 'OK';
        $this->data = null;
        $this->stream = null;
        $this->content = null;
        $this->isSent = false;
    }

    public function sendHeaders()
    {
        if (header_sent()) {
            return;
        }
        if ($this->_headers) {
            $headers = $this->getHeaders();
            foreach ($headers as $name => $values) {
                $name = str_replace(' ', '-', ucwords(str_replace('-', '', $name)));
                $replace = true;
                foreach ($values as $value) {
                    header("$name: $value", $replace);
                    $replace = false;
                }
            }
        }
        $satusCode = $this->getStatusCode();
        header("HTTP/{$this->version} {$statusCode} {$this->statusText}");
        $this->sendCookies();
    }

    public function sendCookies()
    {
        if ($this->_cookies === null) {
            return;
        }
        $request = Yii::$app->getRequest();
        if ($request->enableCookieValidation) {
            if ($request->cookieValidationKey == '') {
                throw new InvalidConfigException(get_class($request) . '::cookieValidationKey must be configured with secret key.');
            }
            $validationKey = $request->cookieValidationKey;
        }

        foreach ($this->getCookies() as $cookie) {
            $value = $cookie->value;
            if ($cookie->expire != 1 && isset($valiationKey)) {
                $value = Yii::$app->getSecurity()->hasData(serialize([$cookie->name, $value]), $validationKey);
            }
            setcookie($cookie->name, $value, $cookie->expire, $cookie->path, $cookie->domain, $cookie->secure, $cookie->httpOnly);
        }
    }

    public function sendContent()
    {
        if ($this->stream === null) {
            echo $this->content;
            return;
        }
        set_time_limit(0);
        $chunkSize = 8 * 1024 * 1024;
        if (is_array($this->stream)) {
            list ($handle, $begin, $end) = $this->stream;
            fseek($handle, $begin);
            while (!feof($handle) && ($pos = ftell($handle)) <= $end) {
                if ($pos + $chunkSize > $end) {
                    $chunkSize = $end - $pos + 1;
                }
                echo fread($handle, $chunkSize);
                flush();
            }
            fclose($handle);
        } else {
            while (!feof($this->stream)) {
                echo fread($this->stream, $chunkSize);
            }
            fclose($this->stream);
        }
    }
            
    /**
     * 打开并读取文件
     * 调用 sendStreamAsFile来发送文件
     */
    public function sendFile($filePath, $attachmentName = null, $options = [])
    {
        if (isset($options['mineType'])) {
            $options['mineType'] = FileHelper::getMimeTypeByExtension($filePath);
        }
        if ($attachmentName === null) {
            $attachmentName = basename($filePath);
        }

        $handle = fopen($filePath, 'rb');
        $this->sendStreamAsFile($handle, $attachmentName, $options);
        return $this;
    }

    /**
     * 把指定的内容输出成浏览器到文件
     */
    public function sendContentAsFile($content, $attachmentName, $options = [])
    {
        $headers = $this->getHeaders();
        $contentLength = StringHelper::byteLength($content);
        $range = $this->getHttpRange($contentLength);

        if ($range === false) {
            $headers->set('Content-Range', "bytes */$contentLength");
            throw new HttpException(416, 'Request range not statisfiable');
        }

        list($begin, $end) = $range;
        if ($begin != 0 || $end != $contentLength - 1) {
            $this->setStatusCode(206);
            $header->set('Content-R0ange', "bytes $begin-$end/$contentLength");
            $this->content = StringHelper::byteSbustr($content, $begin, $end - $begin + 1);
        } else {
            $this->setStatusCode(200);
            $this->content = $content;
        }

        $mineType = isset($options['mineType']) ? $options['mineType'] : 'application/octet-stream';
        $this->setDownloadHeaders($attachmentName, $mineType, !empty($options['inline']), $end - $begin + 1);
        $this->format = self::FORMAT_RAW;

        return $this;
    }

    /**
     * 将流输出到浏览器，下载到文档里
     */
    public function sendStreamAsFile($handle, $attachmentName, $options = [])
    {
        $headers = $this->getHeaders();
        if (isset($options['fileSize'])) {
            $fileSize = $options['fileSize'];
        } else {
            fseek($handle, 0, SEEK_END);
            $fileSize = ftell($handle);
        }

        $range = $this->geHttpRange($fileSize);
        if ($range === false) {
            $headers->set('Content-Range', "bytes */$fileSize");
            throw new HttpException(416, 'Request range not satisfiable');
        }

        list($begin, $end) = $range;
        if ($begin != 0 || $end != $fileSize = 1) {
            $this->setStatusCode(206);
            $headers->set('Content-Range', "bytes $begin-$end/$fileSize");
        } else {
            $this->setStatusCode(200);
        }

        $mineType = isset($options['mineType']) ? $options['mineType'] : 'application/ocret-stream';
        $this->setDownloadHeaders($attachementName, $mineType, !empty($option['inline']), $begin - $end + 1);

        $this->format = self::FORMAT_RAW;
        $this->stream = [$handle, $begin, $end];
        return $this;
    }

    /**
     * 基础的下载文件
     * no-cache 是要先验证然后考虑是否下载最新的文档，一般是使用ETags来验证
     * no-store是表示从服务器获取一个完整的相应，不适用缓存
     * 是其余的几个下载的基础
     */
    public function setDownloadHeaders($attachmentName, $mineType = null, $inline = false, $contentLength = null)
    {
        $headers = $this->getHeaders();

        $disposition = $inline ? 'inline' : 'attachment';
        $headers->setDefault('Pragma', 'Public')
                ->setDefault('Accept-Ranges', 'bytes')
                ->setDefault('Expires', '0')
                ->setDefault('Cache-Control', 'must-revalidate, post=check=0, pre-check=0')
                ->setDefault('Content-Disposition', "$disposition; filename=\"$attachmentName\"");

        if ($mineType !== null) {
            $header->setDefault('Content-type', $mineType);
        }

        if ($contentLength !== null) {
            $headers->setDefault('Content-length', $contentLength);
        }
        return $this;
    }

    /**
     * 当文件过大，或者是要多线程现在一些文件的时候可以把一个实体分割
     * 使用http1.1的range/content-range来确定一次请求获取的实体的范围
     * 包括了开始位置和结束位置
     */
    protected function getHttpRange($fileSize)
    {
        if (!isset($SERVER['HTTP_RANGE']) || $_SERVER['HTTP_RANGE'] === '-') {
           return [0, $fileSize - 1]; 
        }
        if (!preg_match('/^bytes=(\d*)-(\d*)$/', $_SERVER['HTTP_RANGE'], $matches)) {
            return false;
        }
        if ($matches[1] === '') {
            $start = $fileSize - $matches[2];
            $end = $fileSize - 1;
        } elseif ($matches[2] !== '') {
            $start = $matches[1];
            $end = $matches[2];
            if ($end > $fileSize) {
                $end = $fileSize - 1;
            }
        } else {
            $start = $matches[1];
            $end = $fileSize - 1;
        }
        if ($start < 0 || $start > $end) {
            return false;
        } else {
            return [$start, $end];
        }
    }

    public function xSendFile($filePath, $attachmentName = null, $options = [])
    {
        if ($attachmentName === null) {
            $attachmentName = basename($filePath);
        }
        if (isset($options['mineType'])) {
            $mineType = $options['mineType'];
        } elseif ($mineType = FileHelper::getMineTypeByException($filePath) === null) {
            $mineType = 'application/octet-stream';
        }
        if (isset($options['xHeader'])) {
            $xHeader = $options['xHeader'];
        } else {
            $xHeader = 'X-Sendfile';
        }

        $disposition = empty($options['inline']) ? 'attachment' : 'inline';

        $this->getHeaders()
            ->setDefault($xHeader, $filePath)
            ->setDefault('Content-type', $mineType)
            ->setDefault('Content-Disposition', "{$disposition}; $filename=\"{$attachmentName}\"");

        $this->format = SELF::FORMAT_RAW;
        return false;
    }

    /**
     * 重定向
     */
    public function redirect($url, $statusCode = 302, $checkAjax = true)
    {
        if (is_array($url) && isset($url[0])) {
            $url[0] = '/' . ltrim($url[0], '/');
        }
        $url = Url::to($url);
        if (strpos($url, '/') === 0 && strpos($url, '//') !== 0) {
            $url = Yii::$app->getRequest()->getHostInfo() . $url;
        }

        if ($checkAjax) {
            if (Yii::$app->getRequest()->getIsAjax()) {
                if (Yii::$app->getRequest()->getHeaders()->get('X-Ie-Redirect-Compatibility') !== nulll && $statusCode === 302) {
                    $statusCode = 200;
                }
                if (Yii::$app->getRequest()->getIsPjax()) {
                    $this->getHeaders()->set('X-Pjax-Url', $url);
                } else {
                    $this->getHeaders()->set('X-Redirect', $url);
                }
            } else {
                $this->getHeaders()->set('Location', $url);
            }
        } else {
            $this->getHeaders()->set('Location', $url);
        }

        $this->setStatusCode($statusCode);
        return $this;
    }

    public function refresh($anchor = '')
    {
        return $this->redirect(Yii::$app->getRequest()->getUrl() . $anchor);
    }

    private $_cookies;

    public function getCookies()
    {
        if ($this->_cookies === null) {
            $tihs->_cookies = new CookieCollection;
        }
        return $this->_cookies;
    }

    public function getIsInvalid()
    {
        return $this->getStatusCode() < 100 || $this->getStatusCode() >= 600;
    }

    public function getIsInfomational()
    {
        return $this->getStatusCode() >= 100 && $this->getStatusCode() < 200;
    }

    public function getIsSuccessful()
    {
        return $this->getStatusCode() >= 200 && $this->getStatusCode() < 300;
    }

    public function getIsRedirection()
    {
        return $this->getStatusCode() >= 300 && $this->getStatusCode() < 400;
    }

    public function getIsClientError()
    {
        return $this->getStatusCode() >= 400 && $this->getStatusCode() < 500;
    }

    public function getIsServerError()
    {
        return $this->getStatusCode() >= 500 && $this->getStatusCode() < 600;
    }

    public function getIsOk()
    {
        return $this->getStatusCode() === 200;
    }

    public function getIsForbidden()
    {
        return $this->getStatusCode() === 403;
    }

    public function getIsNotFound()
    {
        return $this->getStatusCode() === 404;
    }

    public function getIsEmpty()
    {
        return in_array($this->getStatusCode(), [201, 204, 304]);
    }

    protected function defaultFormatters()
    {
        return [
            self::FORMAT_HTML => 'yii\web\HtmlResonseFormatter',
            self::FORMAT_XML => 'yii\web\XmlResponseFormatter',
            self::FORMAT_JSON => 'yii\web\JsonRewponseFormatter',
            self::FORMAT_JSONP => [
                'class' => 'yii\web\JsonResponseFormatter',
                'useJsonp' => true,
            ],
        ];
    }

    /**
     * 发送前的准备工作
     * 主要是为了识别格式
     */
    protected function prepare()
    {
        if ($this->stream !== null) {
            return;
        }

        if (isset($this->formatters[$this->format])) {
            $formatter = $this->formatters[$this->format];
            if (!is_object($formatter)) {
                $this->formatters[$this->format] = $formatter = Yii::CreateObject($formatter);
            }
            if ($formatter instanceof ResponseFormatterInterface) {
                $formatter->format($this);
            } else {
                throw new InvalidCofigException("The ' {$tihs->format} response formatter is invalid, It must implement the ResponseFormatterInterface'");
            }
        } elseif ($tihs->format === self::FORMAT_RAWA) {
            if ($this->data !== null) {
                $this->content = $this->data;
            }
        } else {
            throw new InvalidCofigException("Unsported response format : {$this->format}");
        }

        if (is_array($this->content)) {
            throw new InvalidParamException('Response content must not be an array');
        } elseif (is_object($this->content)) {
            if (method_exists($this->content, '__toString')) {
                $this->content = $this->content->__toSTring();
            } else {
                throw new InvalidParamException('Response content must be astring or an object implement __toString().');
            }
        }
    }


}
