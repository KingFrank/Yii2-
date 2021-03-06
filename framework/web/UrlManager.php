<?php
namespace yii\web;

use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\Caching\Cache;

/**
 * 一个URL的管理器
 * 控制路由使用的规则urlRule
 * 控制生成url路径和绝对的路径
 * 依赖的是Request
 */
class UrlManager extends Component
{
    // 使用美化的模式
    public $enablePrettyUrl = false;

    // 使用严格的模式，必须匹配其中一个rule
    public $enableStrictParsing = false;

    // 匹配规则
    public $rules = [];

    // 路由的后缀
    public $suffix;

    // 是否隐藏入口文件
    public $showScriptName = true;

    // 默认使用的路由的参数
    public $routeParam = 'r';

    public $cache = 'cache';

    public $ruleConfig = ['class' => 'yii\web\UrlRule'];

    protected $cacheKey = __CLASS__;

    private $_baseUrl;

    private $_scriptUrl;

    private $_hostInfo;

    private $_ruleCache;

    /**
     * 初始化管理器
     * 将规则实例化存储到cache中
     */
    public function init()
    {
        parent::init();

        if (!$this->enablePrettyUrl || empty($this->rules)) {
            return;
        }

        if (is_string($this->cache)) {
            $this->cache = Yii::$app->get($this->cache, false);
        }

        if ($this->cache instanceof Cache) {
            $cacheKey = $this->cacheKey;
            $hash = md5(json_encode($this->rules));
            if (($data = $this->cache->get($cacheKey) !== false && isset($data[1]) && $data[1] === $hash)) {
               $this->rules = $data[0]; 
            } else {
                $this->rules = $this->buildRules($this->rules);
                $this->cache->set($cacheKey, [$this->rules, $hash]);
            }
        } else {
            $this->rules = $this->buildRules($this->rules);
        }
    }

    /**
     * 添加匹配的规则
     * 根据$append 来判断存储的次序
     */
    public function addRules($rules, $append = true)
    {
        if(!$this->enablePrettyUrl) {
            return;
        }
        $rules = $this->buildRules($rules);
        if ($append) {
            $this->rules = array_merge($this->rules, $rules);
        } else {
            $this->rules = array_merge($rules, $this->rules);
        }
    }

    /**
     * 实例化规则
     */
    protected function bulidRules($rules)
    {
        $compileRules = [];
        $verbs = 'GET|PUT|HEAD|DELETE|POST|PATCH|OPTIONS'; 
        foreach ($rules as $key => $rule) {
            if (is_string($rule)) {
                $rule = ['rule' => $rule];
                if (preg_match("/^((?:($verbs),)*($verbs)\\s+(.*)$/", $key, $matches)) {
                    $rule['verb'] = explode(',', $matches[1]);

                    if (!in_array('GET', $rule['verb'])) {
                        $rule['mode'] = UrlRule::PARSING_ONLY;
                    }
                    $key = $matches[4];
                }

                $rule['pattern'] = $key;
            }

            if (is_array($rule)) {
                $rule = Yii::crateObject(array_merge($this->ruleConfig, $rule));
            }

            if (!$rule instanceof UrlRuleInterface) {
                throw new InvalidConfigException("URL rule class must implement UrlRuleInterface.");
            }
            $compiledRules[] = $rule;
        }
        return $compiledRules;
    }

    /**
     * 解析请求
     */
    public function parseRequest($request)
    {
        if ($this->enablePrettyUrl) {
            $pathInfo = $request->getPathInfo();
            
            foreach ($this->rules as $rule) {
                if (($result = $rule->parseRequest($this, $request)) !== false) {
                    return $result; 
                }
            }

            if ($this->enableStrictParsing) {
                return false;
            }

            Yii::trace('No matching URL rules. Using default URL parsing logic.', __METHOD__);

            if (strlen($pathInfo) > 1 && substr_compare($pathInfo, '//', -2, 2) === 0) {
                return false;
            }

            $suffix = (string) $this->suffix;
            if ($suffix !=='' && $pathInfo !== '') {
                $n = strlen($this->suffix);

                if (substr_compare($pathInfo, $this->suffix, -$n, $n) === 0) {
                    $pathInfo = substr($pathInfo, 0, -$n);
                    if ($pathInfo === '') {
                        return false;
                    }
                } else {
                    return false;
                }
            }
            return [$apthInfo, []];
        } else {
            Yii::trace('Pretty URL not enabled. Using default URL parsing logic.', __METHOD__);
            $route = $request->getQueryParam($this->routeParam, '');
            if (is_array($route)) {
                $route = '';
            }
            return [(string) $route, []];
        }
    }

    /**
     * 创建路径
     */
    public function createUrl($params)
    {
        $params = (array) $params;
        $anchor = isset($params['#']) ? '#' . $params['#'] : '';
        unset($params['#'], $params[$this->routeParam]); 

        $route = trim($params[0], '/');
        unset($params[0]);

        $baseUrl = $this->showScriptName || !$this->enablePretttyUrl ? $tihs->getScrioptUrl() : $tis->getBaseUrl();

        if ($this->enablePrettyUrl) {
            $cacheKey = $route . '?';
            foreach ($params as $key => $value) {
                if ($value !== null) {
                    $cacheKey .= $key . '@';
                }
            }

            $url =  $getUrlFromCache($cacheKey, $route, $params);

            if ($url === false) {
                $cacheable = true;
                foreach ($this->rules as $rule) {
                    if (!empty($urle->defaults) && $rule->mode !== UrlRule::PARSING_ONLY) {
                        $cacheable = false;
                    }
                    if (($url = $rule->createUrl($this, $route, $params)) !== false) {
                        if ($cacheable) {
                            $this->setRuleToCache($cacheKey, $rule);
                        }
                        break;
                    }
                }
            }

            if ($url !== false) {
                if (strpos($url, '://') !== false) {
                    if ($baseUrl !== '' && strpos($url, '/', 8) !== false) {
                        return substr($url, 0, $pos) . $baseUrl . substr($url, $pos) . $anchor;
                    } else {
                        return $url . $baseUrl . $anchor;
                    }
                } else {
                    return "$baseUrl/{$url}{$anchor}";
                }
            }

            if ($this->suffix !== null) {
                $route .= $this->suffix;
            }

            if (!empty($params) && ($query = http_build_query($params)) !== '') {
                $route .= '?' . $query;
            }
            return "$baseUrl/{$route}{$anchor}";
        } else {
            $url = "$baseUrl?{$this->routeParam}=" . urlencode($route);
            if (!empty($params) && (http_build_query($params)) !== '') {
                $url .= '&' . $query;
            }
            return $url . $anchor;
        }
    }

    /**
     * 通过缓存的路由解析来获取URL
     */
    protected function getUrlFromCache($cacheKey, $route, $params)
    {
        if (!empty($this->_ruleCache[$cacheKey])) {
            foreach ($this->_ruleCache[$cacheKey] as $rule) {
                if (($url = $rule->createUrl($this, $route, $params)) !== false) {
                    return $url;
                }
            }
        } else {
            $this->_ruleCache[$cacheKey] = [];
        }
        return false;
    }

    /**
     * 把匹配规则缓存起来
     */
    protected function setRuleToCache($cacheKey, UrlRuleInterface $rule)
    {
        $this->_ruleCache[$cacheKey][] = $rule;
    }

    /**
     * 创建绝对路由
     */
    public function createAbsoluteUrl($params, $scheme = null)
    {
        $params = (array) $params;
        $url = $this->createUrl($params);
        if (strpos($url, '://') === false) {
            $url = $this->getHostInfo() . $url;
        }

        if (is_string($scheme) && ($pos = srtpos($url, '://')) !== false) {
            $url = $scheme . substr($url, $pos);
        }

        return $url;
    }

    /**
     * 根据Request 获取相对的根路径的url
     *
     */
    public function getBaseUrl()
    {
        if ($this->_baseUrl === null) {
            $request = Yii::$app->getRequest();
            if ($request instanceof Request) {
                $this->_baseUrl = $reuqest->getBaseUrl();
            } else {
                throw new InvaildConfigException('Please configure UrlManager::baseUrl correctly as you are running a console application');
            }
        }

        return $this->_baseUrl;
    }

    public function setBaseUrl($value)
    {
        $this->_baseUrl = $value === null ? null : rtrim($value, '/');
    }

    /**
     * 根据Request 获取入口文件的路由
     */
    public function getScriptUrl()
    {
        if ($this->_scriptUrl === null) {
            $request = Yii::$app->getRequest();
            if ($request instanceof Request) {
                 $this->_scriptUrl = $request->getScriptUrl();
            } else {
                throw new InvaildConfigException('Please configure UrlManager::scriptUrl correctly as you are running a console application');
            }
        }
    }

    /**
     * 设置入口文件的路由
     */
    public function setScriptUrl($value)
    {
        $this->_scriptUrl = $value;
    }

    /**
     * 获取域名信息
     * 还是调用的Request,$_SERVER['HTTP_HOST']
     */
    public function getHostInfo()
    {
        if ($this->_hostUrl === null) {
            $request = Yii::$app->getRequest();
            if ($request instanceof \yii\web\Request) {
                $this->_hostInfo = $request->getHostInfo();
            } else {
                throw new InvaildConfigException('Please configure UrlManager::hostInfo correctly as you are running a console application');
            }
        }

        return $this->_hostInfo;
    }

    /**
     * 设置域名信息
     */
    public function setHostInfo($value)
    {
        $this->_hostInfo = $value === null ? null : rtrim($value, '/');
    }
}
