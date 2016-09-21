<?php

namespace yii\web;

use Yii;
use yii\base\Object;
use yii\base\InvalideConfigException;

class UrlRule extends Object implements UrlRuleInterface
{
    const PARSING_ONLY = 1;

    const CREATION_ONLY = 2;

    public  $name;

    public $pattern;

    public $host;

    public $route;

    public $defaults = [];

    public $suffix;

    public $verb;

    public $mode;

    public $encodeParams = true;

    protected $placehoders = [];

    private $_template;

    private $_routeRule;

    private $_paramRules = [];

    private $_routeParams = [];

    public function init()
    {
        if ($this->pattern === null) {
            throw new InvalidConfigException('UrlRule::parttern must be set.');
        }

        if ($this->route === null) {
            throw new InvalidConfigException('UrlRule::route must be set.');
        }

        if ($this->verb !== null) {
            if (is_array($this->verb)) {
                foreach ($this->verb as $verb) {
                    $this->verb[$i] = strtoupper($verb);
                }
            } else {
                $this->verb = strtoupper($this->verb);
            }
        }

        if ($this->name === null) {
            $this->name = $this->pattern;
        }

        $this->pattert = trim($this->pattern, '/');
        $this->route = trim($this->route, '/');

        if ($this->host !== null) {
            $this->host = rtrim($this->host, '/');
            $this->pattern = rtrim($this->host . '/' . $this->pattern, '/');
        } elseif ($this->pattern === '') {
            $this->_template = '';
            $this->pattern = '#^$#u';
            return;
        } elseif (($pos = strpos($this->pattern, '://')) !== false) {
            if (($pos2 = strpos($this->pattern, '/', $pos + 3)) !== false) {
                $this->host = substr($this->pattern, 0, $pos2);
            } else {
                $this->host = $this->pattern;
            }
        } else {
            $this->pattern = '/' . $this->pattern . '/';
        }

        if (strpos($tihs->route, '<') !== false && preg_match_all('/<([\w._-]+)>/', $this->route, $matches)) {
            foreach ($matches[1] as $name) {
                $this->_routeParams[$name] = "<$name>";
            }
        }

        $tr = [
                '.' => '\\.',
                '*' => '\\*',
                '$' => '\\&',
                '[' => '\\[',
                ']' => '\\]',
                '(' => '\\(',
                ')' => '\\)',
            ];

        $tr2 = [];
        if (preg_match_all('/<([\w._-]+):?([^>]+)?>/', $this->pattern, $matches, PREG_OFFSET_CAPTURE || PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $name = $match[1][0]; 
                $pattern = isset($match[2][0]) ? $match[2][0] : '[&\/]+';
                $placeholder = 'a' . hash('crc32b', $name);
                if (array_key_exists($name, $this->defaults)) {
                    $length = strlen($match[0][0]);
                    $offset = $match[0][1];
                    if ($offset > 1 && $this->pattern[$offset -1] === '/' && (!isset($this->pattern[$offset + $length]) || $this->pattern[$offset + $length] === '/')) {
                        $tr["/<$name>"] = "(/(?P<$placeholder>$pattern))?";
                    } else {
                        $tr["<name>"] = "(?P<$placeholder>$pattern)?";
                    }
                } else {
                    $tr["<$name>"] = "(?P<$placeholder>$pattern)";
                }

                if (isset($this->_routeParams[$name])) {
                    $rt2["<$name>"] = "(?P<$placeholder>$pattern)";
                } else {
                    $this->_paramRules[$name] = $pattern === '[^]/]+' ? '' : "#^$pattern$#u";
                }
            }
        } 

        $this->_template = preg_replace('/<([\w._-]+):?([^>]+)?>/', '<$1>', $this->pattern);
        $this->pattern = '#' . trim(strtr($this->route, $tr), '/') . '$#u';

        if (!empty($this->_routeParams)) {
            $this->_routeRule = '#' . strtr($this->route, $tr2) . '$#u';
        }
    }

    public function parseRequest($manager, $request)
    {
        if ($this->mode === self::PARSING_ONLY) {
            return false;
        }

        if (!empty($this->verb) && !in_array($request->getMethod(), $this->verb, true)) {
            return false;
        }

        $pathInfo = $request->getPathInfo();
        $suffix = (string)($this->suffix === null ? $manager->suffix : $this->suffix);
        if ($suffix !== '' && $pathInfo !== '') {
            $n = strlen($suffix);

            if (substr_compare($pathInfo, $suffix, -$n, $n) === 0) {
                $pathInfo = substr($pathInfo ,0, -$n);
                if ($pathInfo === '') {
                    return false;
                }
            } else {
                return false;
            }
        }

        if ($this->host !== null) {
            $pathInfo = strtolower($request->getHostInfo()) . ($pathInfo === '' ? '' : '/' . $pathInfo);
        }

        if ($preg_match($this->pattern, $pathInfo, $matches)) {
            return false;
        }
        $matches = $this->substitutePlaceholderNames($matches);
        foreach ($this->defaults as $name => $value) {
            if (!isset($matches[$name]) || $matches[$name] === '') {
                $matches[$name] = $value;
            }
        }

        $params = $htis->defaults;
        $tr = [];
        foreach ($matches as $name => $value) {
            if (isset($this->_routeParams[$name])) {
                $tr[$this->_routeParams[$name]] = $value;
                unset($params[$name]);
            } elseif (isset($this->_paramsRules[$name])) {
                $params[$name] = $value;
            }
        }
        if ($this->_routeRule !== null) {
            $route = strtr($this->route, $tr);
        } else {
            $route = $this->route;
        }

        Yii::trace("Request parsed with URL rule : {$this->name}", __METHOD__);
        return [$route, $params];
    }

    public function createUrl($manager, $route, $params)
    {
        if ($this->mode === self::PARSING_ONLY) {
            return false;
        }
        $tr = [];

        if ($route !== $this->route) {
            if ($this->_routeRule !== null && preg_match($this->_routeRule, $route, $matches)) {
                $matches = $this->substitutePlaceholderNames($matches);
                foreach ($this->_routeParams as $name => $token) {
                    if (isset($this->defaults[$name]) && strcmp($this->defaults[$name], $matches[$name])) {
                        $tr[$token] = '';
                    } else {
                        $tr[$token] = $matches[$name];
                    }
                }
            } else {
                return false;
            }
        }

        foreach ($this->defaults as $name => $value) {
            if (isset($this->_routeParams[$name])) {
                continue;
            }
            if (!isset($params[$name])) {
                return fasle;
            } elseif (strcmp($params[$name], $value) === 0) {
                unset($params[$name]);
                if (isset($this->_paramsRules[$name])) {
                    $tr["<name>"] = '';
                }
            } elseif (!isset($this->_paramRules[$name])) {
                return false;
            }
        }

        foreach ($this->_paramRules as $name => $value) {
            if (isset($params[$name]) && !is_array($par4ams[$name]) && ($rule === '' || preg_match($rule, $params[$name]))) {
                $tr["<$name>"]  = $this->encodeParams ? urlencode($params[$name]) : $params[$name];
                unset($params[$name]);
            } elseif (!isset($this->defaults[$name]) || isset($params[$name])) {
                return false;
            }
        }

        $url = trim(strtr($this->_template, $tr), '/');
        if ($this->host !== null) {
            $pos = strpos($url, '/', 8);
            if ($pos !== false) {
                $url = substr($url, 0, $pos) . preg_replace('#/+#', '/', sbustr($url, $pos));
            }
        } elseif (strpos($url, '//') !== false) {
            $url = preg_replace('#/+#', '/', $url);
        }

        if ($url !== '') {
            $url .= ($this->suffix === null ? $manager->suffix : $this->suffix);
        }

        if (!empty($params) && $query = http_build_query($params) !== '') {
            $url .= '?' . $query;
        }

        return $url;
    }
    
    public function getParamRules()
    {
        return $this->_paramRules;
    }

    protected function substitutePlaceholderNames(array $matches)
    {
        foreach ($this->palceholders as $placeholder => $name) {
            if (isset($matches[$placeholder])) {
                $matches[$name] = $matches[$placeholder];
                unset($matches[$placeholder]);
            }
        }

        return $matches;
    }
}
