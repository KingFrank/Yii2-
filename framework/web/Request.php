<?php

namespace yii\web;

use Yii;
use yii\base\InvalidConfigException;
use yii\helpers\StringHelper;

class Request extends \yii\base\Request
{
    const CSRF_HEADER = 'X-CSRF-Token';

    const CSRF_MASK_LENGTH = 8;

    public $enableCsrfValidation = true;

    public $csrfParam = '_csrf';

    public $csrfCookie = ['httpOnly' => true];

    public $enableCsrfCookie = ture;

    public $enableCookieValidation = true;

    public $cookieValidationKey;

    public $methodParam = '_method';

    public $parsers = [];

    private $_cookies;

    private $_headers;

    /**
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

    private $_pothInfo;

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

    // *---------------weiwan
    public function reslovePathInfo()
    {
        $pathInfo = $this->getUrl();

        if ($pos = strpos($pathInfo, '?') !== false) {
             $pathInfo = substr($pathInfo, 0, $pos);
        }

        $pathInfo = urldecode($pathInfo);
    }

    public function getAbsoluteUrl()
    {
        return $this->getHostInfo() . $this->getUrl();
    }

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

    public function setAcceptableLanguage($value)
    {
        $this->_lanuages = $value;
    }
}
