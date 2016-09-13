<?php
namespace yii\web;

use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\Caching\Cache;

class UrlManager extends Component
{
    public $enablePrettyUrl = false;

    public $enableStrictParsing = false;

    public $rules = [];

    public $suffix;

    public $showScriptName = true;

    public $routeParam = 'r';

    public $cache = 'cache';

    public $ruleConfig = ['class' => 'yii\web\UrlRule'];

    protected $cacheKey = __CLASS__;

    private $_baseUrl;

    private $_scriptUrl;

    private $_hostInfo;

    private $_ruleCache;

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

    protected function setRuleToCache($cacheKey, UrlRuleInterface $rule)
    {
        $this->_ruleCache[$cacheKey][] = $rule;
    }

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

    public function setScriptUrl($value)
    {
        $this->_scriptUrl = $value;
    }

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

    public function setHostInfo($value)
    {
        $this->_hostInfo = $value === null ? null : rtrim($value, '/');
    }
}
