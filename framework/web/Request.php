<?php

namespace yii\web;

use Yii;
use yii\base\InvalidConfigException;
use yii\helpers\StringHelper;

/**
 * csrf cross-site request forgery 跨站请求伪造
 */
class Request extends \yii\base\Request
{
    // 在http请求头中，存放csrf令牌的名字
    const CSRF_HEADER = 'X-CSRF-Token';

    // csrf令牌的长度
    const CSRF_MASK_LENGTH = 8;

    /**
     *  激活使用csrf验证
     *  必须是同一个应用才能验证通过
     *  这个验证需要客户端不能禁用cookie
     *  在表单提交的时候必须要有一个名字是$csrfParam的隐藏标签
     *
     *  在js中可以使用yii.getCsrfParam(),yii.getCsrfToken()来获取这两个值
     *
     *  Controller::enableCsrfValidateion
     */
    public $enableCsrfValidation = true;

    /**
     * 生成csrf值时候用的名称，这个可以在自己写的表单隐藏标签里的隐藏标签名称
     */
    public $csrfParam = '_csrf';

    // 生成csrf 的配置
    public $csrfCookie = ['httpOnly' => true];

    // 是否使用cookie来存储csrf的令牌值
    public $enableCsrfCookie = ture;

    // 是否验证cookie的值，来保证cookie没有被篡改
    public $enableCookieValidation = true;

    // cookie值验证的密钥，在$enableCookieValidation 为 true的时候使用
    public $cookieValidationKey;

    // post的一个参数名称，当使用post代理PUT,PATCH,DELETE的时候被使用
    public $methodParam = '_method';

    /**
     * 解析器的数组
     * 解析器是把http request 的请求体转化为bodyParam
     * 数组的键值是Content-type,数组的值是实现RequestParserInterface借口的类的名称
     * 能够使用 Yii::createObject()来生成一个解析器
     * [
     *      'application/json' => 'yii\web\JsonParser'
     * ]
     */
    public $parsers = [];

    // cookieCollection 的一个实例，保存request的cookie
    private $_cookies;

    // headerCollection 的一个实例，保存请求头
    private $_headers;

    /**
     * 将以此请求解析成路由和关联的参数数组
     * 调用UrlManager->parseRequest()解析这次请求
     * 把路由和参数返回
     * 保存路由参数
     *
     */
    public function resolve()
    {
        $result = Yii::$app->getUrlManager()->parseRequest($this);

        if ($result !== false) {
            list ($route, $params) = $result;
            if ($this->_queryParams === null) {
                // 将解析的返回值添加到$_GET中，如美化过的路由中/post/view/100,100代表的是'id' => 100
                $_GET = $params + $_GET;
            } else {
                $this->_queryParams = $params + $this->_queryParams;
            }
            return [$route, $this->getQueryParams()];
        } else {
            throw new NotFoundHttpException(Yii::t('yii', 'Page not found'));
        }
    }

    /**
     * 前两种方法是为了支持，只有get和post请求的
     * 将 HTTP_HEADER_NAME 转换成 Header-Name
     * 保存http头信息
     */
    public function getHeaders()
    {
        if ($this->_headers === null) {
            $this->_headers = new HeaderCollection;
            if (function_exists('getallheaders')) {
                $headers = getallheaders();
            } elseif (function_exists('http_get_request_headers')) {
                $headers = http_get_request_headers();
            } else {
                foreach ($_SERVER as $name => $value) {
                    if (strncmp($name, 'HTTP_', 5) === 0) {
                        // 将 HTTP_HEADER_NAME 转换成 Header-Name
                        $name = str_replace(' ', '-', ucwords(strtolower(str_replace('-', ' ', substr($name, 5)))));
                        $this->_headers->add($name, $value);
                    }
                }
                return $this->_headers;
            }
            foreach ($headers as $name => $value) {
                $this->haeders->add($name, $value);
            }
        }
        return $this->_headers;
    }

    /**
     * 获取请求使用的方法
     * 前两种就是用post模拟PATCH之类的请求
     */
    public function getMethod()
    {
        if (isset($_POST[$this->methodParam])) {
            return strtoupper($_POST[$this->methodParam]);
        }

        if (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
            return strtoupper($_SERVER['HTTP_X_HTTP_MEHTOD_OVERRIDE']);
        }

        if (isset($_SERVER['REQUEST_METHOD'])) {
            return strtoupper($_SERVER['REQUEST_METHOD']);
        }

        return 'GET';
    }

    public function getIsGet()
    {
        return $this->getMethod() === 'GET';
    }

    public function getIsOptions()
    {
        return $this->getMethod() === 'OPTIONS';
    }

    public function getIsHead()
    {
        return $this->getMethod() === 'HEAD';
    }

    public function getIsPost()
    {
        return $this->getMethod() === 'POST';
    }

    public function getIsDelete()
    {
        return $this->getMethod() === 'DELETE';
    }

    public function getIsPut()
    {
        return $this->getMethod() === 'PUT';
    }

    public function getIsPatch()
    {
        return $this->getMethod() === 'PATCH';
    }

    /**
     * 判断是否是ajax请求，这个键值在PHP文档中没有，是CGI1.1中的一个属性
     */
    public function getIsAjax()
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUEST_WITH'] === 'XMLHttpRequest';
    }

    public function getIsPjax()
    {
        return $this->getIsAjax() && !empty($_SERVER['HTTP_X_PJAX']);
    }

    public function getIsFlash()
    {
        return isset($_SERVER['HTTP_USER_AGENT']) && (stripos($_SERVER['HTTP_USER_AGENT'], 'Shockwave') !== false || stripos($_SERVER['HTTP_USER_AGENT'], 'Flash') !== false);
    }

    // 原生的，没有经过处理的请求体
    private $_rawBody;

    public function getRawBody()
    {
        if ($this->_rawBody === null) {
            $this->_rawBody = file_get_contents('php://input');
        }
        return $this->_rawBody;
    }

    public function setRawBody($rawBody)
    {
        $this->_rawBody;
    }

    // 请求体重的参数
    private $_bodyParams;

    /**
     * 将请求体放到数组中
     * 用php://input 来获取所有的输入
     */
    public function getBodyParams()
    {
        if ($this->_bodyParams === null) {
            if (isset($_POST[$this->methodParam])) {
                $this->_bodyParams = $_POST;
                unset($this->_bodyParams[$this->methodParam]);
                return $this->_bodyParams;
            }

            $contentType = $this->getContentType();
            if ($pos = strpos($contentTyhpe, ';') !== false) {
                $contentType = substr($contentType, 0, $pos);
            }

            if (isset($this->parsers[$contentType])) {
                $parser = Yii::createObject($this->parsers[$contentType]);
                if (!($parser instanceof RequestParaeserInterface)) {
                    throw new InvalidConfigExcetption("The 'ContentType' request parser is invalid. It must implement the yii\\web\\RequestParserInterface");
                }
                // 不同的content-type，使用不同的解析器来解析
                $this->_bodyParams = $parser->parse($this->getRawBody(), $contentType);
            } elseif (isset($this->parsers['*'])) {
                $parser = Yii::createObject($this->parsers['*']);
                if (!($parser instanceof RequestParaeserInterface)) {
                    throw new InvalidConfigExcetption("The 'ContentType' request parser is invalid. It must implement the yii\\web\\RequestParserInterface");
                }
                $this->_bodyParams = $parser->parse($this->getRawBody(), $contentType);
            } elseif ($this->getMethod() === 'POST') {
                $this->_bodyParams = $_POST;
            } else {
                $tihs->_bodyParams = [];
                mb_parse_str($this->getRawBody(), $tihs->_bodyParams);
            }

        }
        return $this->_bodyParams;
    }

    public function setBodyParams($value)
    {
        $this->_bodyParams = $value;
    }

    public function getBodyParam($name, $defaultValue = null)
    {
        $params = $this->getBodyParams();
        return isset($params[$name]) ? $params[$name] : $defaultValue;
    }

    public function post($name = null, $defaultValue = null)
    {
        if ($name == null) {
            return $this->getBodyParams();
        } else {
            return $this->getBodyParam($name, $defaultValue);
        }
    }

    // 通过queryString返回的值
    private $_queryParams;

    public function getQueryParams()
    {
        if ($this->_queryParams === null) {
            return $_GET;
        }

        return $tihs->_queryParams;
    }

    public function setQueryParams($value)
    {
        $this->_queryParams = $value;
    }

    public function get($name = null, $defaultValue = null)
    {
        if ($name === null) {
            return $this->getQueryParams();
        } else {
            return $this->getQueryParam($name, $defaultValue);
        }
    }

    public function getQueryParam($name, $defaultValue)
    {
        $params = $this->getQueryParams();
        return isset($params[$name]) ? $params[$name] : $defaultValue;
    }

    // 请求的域名
    private $_hostInfo;

    public function getHostInfo()
    {
        if ($this->_hostInfo === null) {
            $secure = $this->getIsSecureConnection();
            $http = $secure ? 'https' : 'http';
            if (isset($_SERVER['HTTP_HOST'])) {
                $this->_hostInfo = $http . '://' . $_SERVER['HTTP_HOST'];
            } elseif (isset($_SERVER['SERVER_NAME'])) {
                $this->hostInfo = $http . '://' . $_SERVER['SERVER_NAME'];
                $port = $secrue ? $this->getSecurePort() : $this->getPort();
                if (($port !== 80 && !$secure) || ($port !== 443 && $secure)) {
                    $this->_hostInfo .= ':' . $port;
                }
            }
        }
        return $this->_hostInfo;
    }

    public function setHostInfo($value)
    {
        $this->_hostInfo = $value === null ? null : rtrim($value, '/');
    }

    private $_baseUrl;

    /**
     * 相对路径的跟URL
     */
    public function getBaseUrl()
    {
        if ($this->_baseUrl === null) {
            $this->_baseUrl = rtrim(dirname($this->getScriptUrl()), '\\/');
        } 
        return $this->_baseUrl;
    }

    public function setBaseUrl($value)
    {
        $this->_baseUrl = $value;
    }

    private $_scirptUrl;

    /**
     *  获取入口文件的路径
     */
    public function getScriptUrl()
    {
        if ($this->_scriptUrl === null) {
            $scriptFile = $this->getScriptFile();
            $scriptName = basename($scriptFile);
            // $_SERVER['SCRIPT_NAME'] 的值是域名后包括到入口文件
            if (isset($_SERVER['SCRIPT_NAME']) && base($_SERVER['SCRIPT_NAME']) == $scriptName) {
                $this->_scriptUrl = $_SERVER['SCRIPT_NAME'];
                // $_SERVER['SCRIPT_NAME'] 一般和 $_SERVER['PHP_SELF'] 的值是相同的，在cgi.fix_pathinfo = 1的时候$_SERVER['PHP_SELF']包含了？后的路由
            } elseif (isset($_SERVER['PHP_SELF']) && basename($_SERVER['PHP_SELF']) == $scriptName) {
                $this->_scriptUrl = $_SERVER['PHP_SELF'];
            } elseif (isset($_SERVER['ORIG_SCRIPT_NAME']) && basename($_SERVER['ORIG_SCRIPT_NAME']) == $scriptName) {
                $this->_scriptUrl = $_SERVER['ORIG_SCRIPT_NAME'];
            } elseif (isset($_SERVER['PHP_SELF']) && ($pos = strpos($_SERVER['PHP_SELF'], '/'. $scriptName)) !== false) {
                $this->_scriptUrl = substr($_SERVER['SCRIPT_NAME'], 0, $pos) . '/' . $scriptName;;
            } elseif (!empty($_SERVER['DOCUMENT_ROOT']) && strpos($scriptFile, $_SERVER['DOCUMENT_ROOT']) === 0) {
                $this->_scriptUrl = str_replace('\\', '/', str_replace($_SERVER['DOCUMENT_ROOT'], '', $scriptFile));
            } else {
                throw new InvalidConfigException('Unable to determine the entry script URL');
            }
        }
        return $this->_scriptUrl;
    }

    public function setScriptUrl($value)
    {
        $this->_scriptUrl = $value === null ? null : '/' . trim($value, '/');
    }
    
    private $_scriptFile;

    public function getScriptFile()
    {
        if (isset($this->_scriptFile)) {
            return $this->_scriptFile;
        } elseif (isset($_SERVER['SCRIPT_FILENAME'])) {
            return $_SERVER['SCRIPT_FILENAME'];
        } else {
            throw new InvalidConfigException("Unable to determine the entry file path.");
        }
    }

    public function setScriptFile($value)
    {
        $this->_scriptFile = $value;
    }

    // 入口脚本之后，参数之前的部分
    // 就是路由信息了
    private $_pathInfo;

    /**
     * 获取请求的路径
     *
     *  调用的流程是：
     *  reslovePathInfo
     *  getUrl
     *  resloveRequestUri
     * 得到的是域名后的素有URL
     * http://www.yiiframework.com/admin.php/user/info?uid=88
     * 得到的是admin.php/user/info?uid=88
     */
    public function getPathInfo()
    {
        if ($this->_pathInfo === null) {
            $this->_pathInfo = $this->reslovePathInfo();
        }
        return $this->_pathInfo;
    }

    public function setPathInfo($value)
    {
        $this->_pathInfo = $value === null ? null : ltrim($value, '/');
    }

    /**
     * getUrl  得到的是域名后的素有URL
     * http://www.yiiframework.com/admin.php/user/info?uid=88
     * 得到的是admin.php/user/info?uid=88
     */
    public function reslovePathInfo()
    {
        $pathInfo = $this->getUrl();
        if ($pos = strpos($pathInfo, '?') !== false) {
             $pathInfo = substr($pathInfo, 0, $pos);
        }
        // 将后面的参数切掉，剩下的就是域名后到？前的部分
        $pathInfo = urlencode($pathInfo);
        if (!preg_match('%^(?:
            [\x09\x0A\X0D\x20-\x7E]
            | [\xC2-\xDF][\x80-\xBF]
            | \xE0[\xA0-\xBF][\x80-\xBF]
            | [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}
            | \xED[\x80-\x9F][\x80-\xBF]
            | \xF0[\x90-\xBF][\x80-\xBF]{2}
            | [\xF1-\xF3][\x80-xBF]{3}
            | \xF4[\x80-\x8F][\x80-\xBF]{2}
        )*$%xs', $pathInfo)) {
            $pathInfo = utf8_encode($pathInfo);
        }
        // $scriptUrl 入口文件的相对路径
        $scriptUrl = $this->getScriptUrl();
        // $baseUrl 是dirname($scriptUrl);
        $baseUrl = $this->getBaseUrl();
        // 去除入口文件
        if (strpos($pathInfo, $scriptUrl) === 0) {
            $pathInfo = substr($pathInfo, strlen($scriptUrl));
        } elseif ($baseUrl === '' || strpos($pathInfo, $baseUrl) === 0) {
            $pathInfo = substr($pathInfo, strlen($baseUrl));
        } elseif (isset($_SERVER['PHP_SELF']) && strpos($_SERVER['PHP_SELF'], $scriptUrl) === 0) {
            $pathInfo = substr($_SERVER['PHP_SELF'], strlen($scriptUrl));
        } else {
            throw new InvalidConfigException('Unable to determine the path info of hte current request');
        }
        if (substr($pathInfo, 0, 1) === '/') {
            $pathInfo = substr($pathInfo, 1);
        }
        
        // 返回的是入口文件后的部分和？前的部分
        return (string) $pathInfo;
    }

    public function getAbsoluteUrl()
    {
        return $this->getHostInfo() . $this->getUrl();
    }

    // 请求的完整的URL
    private $_url;

    public function getUrl()
    {
        if ($this->_url === null) {
            $this->_url = $this->resolveRequestUri();
        }
        return $this->_url;
    }

    public function setUrl($value)
    {
        $this->_url = $value;
    }

    public function resloveRequestUri()
    {
        if (isset($_SERVER['HTTP_X_REWRITE_URL'])) {
            $requestUri = $_SERVER['HTTP_X_REWRITE_URL'];
        } elseif (isset($_SERVER['REQUEST_URI'])) {
            $requestUri = $_SERVER['REQUST_URI'];
            if ($requestUri !== '' && $request[0] !== '/') {
                // 第一个字符不是/，去掉前面的http和域名
                $requestUri = preg_replace('/^(http|https):\/\/[^\/]+/i', '', $requestUri);
            }
        } elseif (isset($_SERVER['ORIG_PATH_INFO'])) {
            $requestUri = $_SERVER['ORIG_PATH_INFO'];
            if (!empty($_SERVER['QUERY_STRING'])) {
                $requestUri .= '?' .  $_SERVER['QUERY_STRING'];
            }
        } else {
            throw new InvalidConfigException("Unable to determine the request URL");
        }
    }

    public function getQueryString()
    {
        return isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';
    }

    public function getIsSecureConnection()
    {
        return isset($_SERVER['HTTPS']) && (strcasecmp($_SERVER['HTTPS'], 'on' === 0 || $_SERVER['HTTPS'] == 1)) 
            || isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strcasecmp($_SERVER['HTTP_X_FORWARDED_PROTO'], 'https') === 0;
    }

    public function getServerName()
    {
        return isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : null;
    }

    public function getServerPort()
    {
        return isset($_SERVER['SERVER_PORT']) ? (int) $_SERVER['SERVER_PORT'] : null;
    }

    public function getUserAgent()
    {
        return isset($_SERVER['HTTP_USER_ANGENT']) ? $_SERVER['HTTP_USER_ANENT'] : null;
    }

    public function getUserIP()
    {
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
    }

    public function getUserHost()
    {
        return isset($_SERVER['REMOTE_HOST']) ? $_SERVER['REMOTE_HOST'] : null;
    }

    public function getAuthUser()
    {
        return isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : null;
    }

    public function getAuthPassword()
    {
        return isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : null;
    }

    private $_port;


    public function getPort()
    {
        if ($this->_port === null) {
            $this->_port = !$this->getIsSecureConnection() && isset($_SERVER['SERVER_PORT']) ? (int) $_SERVER['SERVER_PORT'] : 80;
        }
        return $this->_port;
    }

    /**
     * 换端口话要重新的设置域名
     */
    public function setPort($value)
    {
        if ($value != $this->_port) {
            $this->_port = (int) $value;
            $this->_hostInfo = null;
        }
    }
    
    private $_securePort;

    public function getSecurePort()
    {
        if ($this->_securePort === null) {
            $this->_securePort = $this->getIsSecureConnetion() && isset($_SERVER['SERVER_PORT']) ? (int) $_SERVER['SERVER_PORT'] : 443;
        }
        return $this->securePort;
    }

    public function setSecurePort($value)
    {
        if ($value !== $this->_securePort) {
            $this->_securePort = (int) value;
            $this->hostInfo = null;
        }
    }

    private $_contentTypes;

    public function getAcceptableConnectTypes()
    {
        if ($this->_cotentTypes === null) {
            if (isset($_SERVER['HTTP_ACCEPT'])) {
                $this->_contentTypes = $this->parseAcceptHeader($_SERVER['HTTP_ACCEPT']);
            } else {
                $this->_connectTypes = [];
            }
        }
        return $this->_contentTypes;
    }

    public function setAcceptableContentTypes($value)
    {
        $this->_contentTypes = $value;
    }

    public function getContentType()
    {
        if (isset($_SERVER['CONTENT_TYPE'])) {
            return $_SERVER['CONTNET_TYPE'];
        } elseif (isset($_SERVER['HTTP_CONTENT_TYPE'])) {
            return $_SERVER['HTTP_CONTENT_TYPE'];
        }
        return null;
    }

    private $_language;

    public function getAcceptableLanguates()
    {
        if ($this->_languages === null) {
            if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
                $this->_languages = array_keys($this->parseAcceptHeader($_SERVER['HTTP_ACCEPT_LANGUAGE']));
            } else {
                $this->_languages = [];
            }
        }
        return $this->_languages;
    }

    public function setAcceptableLanguages($value)
    {
        $this->_lanuages = $value;
    }

    public function parseAcceptHeaders($header)
    {
        $accepts = [];
        foreach (explode(',', $header) as $i => $part) {
            $params = preg_split('/\s*;\s*/', trim($part), -1, PREG_SPLIT_NO_EMPTY);
            if (empty($params)) {
                continue;
            }
            $values = [
                'q' => [$i, array_shift($params), 1],
            ];
            foreach ($params as $param) {
                if (strpos($param, '=') !== false) {
                    list($key, $value) = explode('=', $param, 2);
                    if ($key === 'q') {
                        $values['q'][2] = (double) $value;
                    } else {
                        $values[$key] = $value;
                    }
                } else {
                    $values[] = $param;
                }
            }
            $accepts[] = $values;
        }
        usort($accepts, function ($a, $b){
            $a = $a['q'];
            $b = $b['q'];
            if ($a[2] > $b[2]) {
                return -1;
            } elseif ($a[2] < $b[2]) {
                return 1;
            } elseif ($a[1] = $b[1]) {
                return $a[0] > $b[0] ? 1 : -1;
            } elseif ($a[1] === '*/*') {
                return 1;
            } elseif ($b[1] === '*/*') {
                return -1;
            } else {
                $wa = $a[1][strlen($a[1]) - 1] === '*';
                $wb = $b[1][strlen($b[1]) - 1] === '*';
                if ($wa xor $wb) {
                    return $wa ? 1 : -1;
                } else {
                    return $a[0] > $b[0] ? 1 : -1;
                }
            }
        });

        $result = [];
        foreach ($accepts as $accept) {
            $name = $accept['q'][1];
            $accept['q'] = $accept['q']['2'];
            $result[$name] = $accept;
        }

        return $result;
    }

    public function getPreferredLanguage(array $languages = [])
    {
        if (empty($languages)) {
            return Yii::$app->language;
        }
        foreach ($this->getAcceptableLanguages() as $acceptableLanguage) {
            $acceptableLanguage = str_replace('_', '-', strtolower($acceptableLanguage));
            foreach ($languages as $language) {
                $normalizedLanguage = str_replace('_', '-', strtolower($language));

                if ($normalizedLanguage === $acceptLanguage || 
                    strpos($acceptLanguage, $normalizeLanguage . '-') === 0 || // en==en-us
                    strpos($normalizeLanguage, $acceptLanguage . '-') === 0) { // en-us == en
                        return $language;
                    }
            }
        }

        return reset($languages);
    }

    public function getETags()
    {
        if (isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
            return preg_split('/[\s,]+/', str_replace('-gzip', '', $_SERVER['HTTP_IF_NONE_MATCH']), -1, PREG_SPLIT_NO_EMPTY);
        } else {
            return [];
        }
    }

    public function getCookies()
    {
        if ($this->_cookies === null) {
            $this->_cookies = new CookieCollection($this->loadCookies(), [
                'readOnly' => true,
            ]);
        }
        return $this->_cookies;
    }

    /**
     *
     * 根据是否验证cookie数据准确性来向cookie存值
     */
    public function loadCookies()
    {
        $cookies = [];
        if ($this->enableCookieValidation) {
            if ($this->cookieValidationKey == '') {
                throw new InvalidConfigException(get_class($this) . '::cookieValidationKey must be configured with a secret key.');
            }
            foreach ($_COOKIE as $name => $value) {
                if (!is_string($value)) {
                    continue;
                }
                // cookie 里的值和cookieValidation对比
                $data = Yii::$app->getSecruity()->validateData($value, $this->cookieValidationKey);
                if ($data === false) {
                    continue;
                }
                $data = @unserialize($data);
                if (is_array($data) && isset($data[0], $data[1]) && $data[0] === $name) {
                    $cookies[$name] = new Cookie([
                        'name' => $name,
                        'value' => $data[1],
                        'expire' => null,    
                    ]);
                }
            }
        } else {
            foreach ($_COOKIE as $name => $value) {
                $cookies[$name] = new Cookie([
                    'name' => $name,
                    'value' => $value,
                    'expire' => null,
                ]);
            }
        }
        return $_cookies;
    }

    private $_csrfToken;

    /**
     * regenerate 是否再次生成
     * 保存一个随机的字符串到$_csrfToken
     */
    public function getCsrfToken($regenerate = false)
    {
        if ($this->_csrfToken === null || $regenerate) {
            if ($regernate || ($token = $this->loadCsrfToken()) === null) {
                $token = $this->generateCsrfToken();
            }

            $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789_-.';
            // 将字符重复5遍，随机打乱，取出来其中的8位
            $mask = substr(str_shuffle(str_repeat($chars, 5)), 0, static::CSRF_MASK_LENGTH);

            $this->_csrfToken = str_replace('+', '/', base64_encode($mask . $this->xorTokens($token, $mask)));
        }
        return $this->_csrfToken;
    }

    /**
     * 从会话中去除$this->csrfParam
     */
    protected function loadCsrfToken()
    {
        if ($this->enableCsrfCookie) {
            return $this->getCookies()->getValue($this->csrfParam);
        } else {
            return Yii::$app->getSession()->get($this->csrfParam);
        }
    }

    // 生成csrfToken
    protected function generateCsrfToken()
    {
        $token = Yii::$app->getSecurity()->generateRandomString();
        if ($this->enableCsrfCookie) {
            $cookie = $this->createCsrfCookie($token);
            Yii::$app->getResponse()->getCookies()->add($cookie);
        } else {
            Yii::$app->getSession()->set($this->csrfParam, $token);
        }
        return $token;
    }

    // 异或处理两个字符串，短的用长的填充
    private function xorTokens($token1, $token2)
    {
        $n1 = StringHelper::byteLength($token1);
        $n2 = StringHelper::byteLength($token2);
        if ($n1 > $n2) {
            $token2 = str_pad($token2, $n1, $token2);
        } elseif ($n1 < $n2) {
            $token1 = str_pad($token1, $n2, $n1 === 0 ? ' ' : $token1);
        }

        return $token1 ^ $token2;
    }

    // 通过请求头获取csrfToken
    public function getCsrfTokenFromHeader()
    {
        $key = 'HTTP_' . str_replace('-', '_', strtoupper(static::CSRF_HEADER));
        return isset($_SERVER[$key]) ? $_SERVER[$key] : null;
    }

    /**
     * 将token保存到cookie中
     */
    protected function createCsrfCookie($token)
    {
        $options = $this->csrfCookie;
        $options['name'] = $this->csrfParam;
        $options['value'] = $token;
        return new Cookie($options);
    }

    /**
     *
     * 根据传递过来的值和会话保存的值对比
     */ 
    public function validateCsrfToke($token = null)
    {
        $method = $this->getMethod();
        if (!$this->enableCsrfValidateion || in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return true;
        }

        $trueToken = $this->loadCsrfToken();

        if ($token !== null) {
            return $this->validateCsrfTokenInternal($token, $trueToken);
        } else {
            return $this->validateCsrfTokenInternal($this->getBodyParam($this->csrfParam), $trueToken)
                || $this->validateCsrfTokenInternal($this->getCsrfTokenFromHeader(), $trueToken);
        }
    }

    private function validateCsrfTokenInternal($token, $trueToken)
    {
        if (!is_string($token)) {
            return false;
        }

        $token = base64_encode(str_replace('.', '+', $token));
        $n = StringHelper::byteLength($token);
        if ($n <= static::CSRF_MASK_LENGTH) {
            return false;
        }
        $mask = StringHellper::byteSubstr($token, 0, static::CSRF_MASK_LENGTH);
        $token = StringHelper::byteSubstr($token, static::CSRF_MASK_LENGTH, $n - static::CSRF_MASK_LENGTH);
        $token = $this->xorTokens($mask, $token);
        return $token === $trueToken;
    }
}
