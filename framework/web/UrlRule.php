<?php

namespace yii\web;

use Yii;
use yii\base\Object;
use yii\base\InvalideConfigException;

/** 
 * UrlManger使用的规则
 * 主要是为了解析和生成路由
 * 如果想使用自己定义的规则可以在UrlManger::rules里添加
 * 'rules' => [
 *   ['class'=>'MyRule', 'pattern' => '', 'route' => '']
 * ]
 * 
 *
 * 默认会使用的规则
 * [
 *      // 根据名字指定路由
 *      'posts' => 'post/index',
 *
 *      // <id:\d+> 参数名，参数值对应的规则，指向的路由
 *      'post/<id:\d+>' => 'post/view',
 *
 *      // controller,action,id对应的规则
 *      '<controller:(post|comment)>/<id:\d+>/<actioin:(crate|update|deleete)>' => '<controller>/<action>',
 *
 *      // 包含了http使用方法的规则
 *      'DELETE <controlelr:\w+>/<id:\d+>' => '<controller>/delete',
 *
 *      // 指定了请求的来源的参数
 *      'http://<user:\w+>.kingFrank.com/<lang:\w+>/profile' => 'user/profile'
 * ]
 *
 * 会把类似这个数组的键值赋值给$this->pattern
 * 值会赋值给$this->route
 */
class UrlRule extends Object implements UrlRuleInterface
{
    // 只解析，不生成路由
    const PARSING_ONLY = 1;

    // 只创建不解析路由
    const CREATION_ONLY = 2;
    
    // 规则的名称
    public  $name;

    // 用于解析和生成URL的模式，通常是正则表达式
    public $pattern;

    // 用于解析和创建URL时，处理主机域名的部分
    public $host;

    // 指向controller/action 的路由
    public $route;

    // 一些设置的默认的参数，在路径解析的时候被注入到$_GET中
    public $defaults = [];

    // 指定的URL后缀名，类似.html
    public $suffix;

    // 使用RESTFull时指定的动作HTTP动作
    // 没有设定则使用与全部的方法
    public $verb;

    // 当前规则的模式0,PARSING_ONLY,CREATE_ONLY
    public $mode;

    // URL中的参数是否要进行编码
    public $encodeParams = true;

    // 匹配元素名字的占位符
    protected $placeholders = [];

    //  生成的模板的路径
    private $_template;

    // 生成URL路由的规则
    private $_routeRule;

    // 路由中的参数的规则
    private $_paramRules = [];

    // 路由中的参数
    private $_routeParams = [];

    /**
     * 规则的初始化
     */
    public function init()
    {
        // 如果没有参数规则和指定的路由，这个规则是错误的
        if ($this->pattern === null) {
            throw new InvalidConfigException('UrlRule::parttern must be set.');
        }

        if ($this->route === null) {
            throw new InvalidConfigException('UrlRule::route must be set.');
        }

        // 判断使用restfull方法使用的http方法
        if ($this->verb !== null) {
            if (is_array($this->verb)) {
                foreach ($this->verb as $verb) {
                    $this->verb[$i] = strtoupper($verb);
                }
            } else {
                $this->verb = strtoupper($this->verb);
            }
        }

        // 如果这个规则没有名字，使用规则自身作为名字
        if ($this->name === null) {
            $this->name = $this->pattern;
        }

        // 过滤两端的字符/
        $this->pattert = trim($this->pattern, '/');
        $this->route = trim($this->route, '/');

        // 域名不为空的时候把域名连接到规则之前
        if ($this->host !== null) {
            $this->host = rtrim($this->host, '/');
            $this->pattern = rtrim($this->host . '/' . $this->pattern, '/');
        } elseif ($this->pattern === '') {
            //域名没有定义， 规则是空值的时候直接返回
            $this->_template = '';
            $this->pattern = '#^$#u';
            return;
        } elseif (($pos = strpos($this->pattern, '://')) !== false) {
            // 域名没有定义，规则中包换有://字符的时候，把域名抽出来
            if (($pos2 = strpos($this->pattern, '/', $pos + 3)) !== false) {
                $this->host = substr($this->pattern, 0, $pos2);
            } else {
                $this->host = $this->pattern;
            }
        } else {
            // pattern 不是空的字符串，域名不是空的时候，两端加上/
            $this->pattern = '/' . $this->pattern . '/';
        }
        // 得到的patern 前后都会有字符/

        /* 将$this->route 中的匹配的元素放到$this->_routeParams中
         *类似 ['controler'=>'<controler>', 'action' => '<action>']
         */
        if (strpos($this->route, '<') !== false && preg_match_all('/<([\w._-]+)>/', $this->route, $matches)) {
            foreach ($matches[1] as $name) {
                // 将route中的参数拿出来放到$this->_routeParams中
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
        /**
         * preg_match_all 中的第三个参数PREG_SET_ORDER
         * $match[0] 保存是匹配所有规则成功后，第一次的结果捕获
         * $match[0][0] 是所有匹配的值
         * $match[0][1] 是匹配的子组的值
         * 以此的类推
         *
         * PREG_OFFSET_CAPTURE
         * 返回值会变成三维的数组
         * 在匹配值的同时，会返回匹配字符在字符串中的偏移量位置
         * PREG_OFFSET_CATPURE | PREG_SET_ORDER 得到的结果是$matches[0] 是第一个匹配全部的规范和子组，偏移量的集合
         * PREG_OFFSET_CAPTURE $matches[0] 是保存所有的匹配规范的字符和偏移量的集合
         *
         */
        if (preg_match_all('/<([\w._-]+):?([^>]+)?>/', $this->pattern, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                // $name 匹配参数controller,action之类的
                $name = $match[1][0]; 
                // $pattern 是参数对应的值
                $pattern = isset($match[2][0]) ? $match[2][0] : '[&\/]+';
                $placeholder = 'a' . hash('crc32b', $name);
                $this->placeholders[$placehodler] = $name;
                if (array_key_exists($name, $this->defaults)) {
                    // <controller:\w> 对应的长度和偏移量
                    $length = strlen($match[0][0]);
                    $offset = $match[0][1];
                    if ($offset > 1 && $this->pattern[$offset -1] === '/' && (!isset($this->pattern[$offset + $length]) || $this->pattern[$offset + $length] === '/')) {
                        // (?P<$name>$pattern) 是讲匹配$pattern规范的
                        // 这个没有匹配只是保存了匹配的规范
                        $tr["/<$name>"] = "(/(?P<$placeholder>$pattern))?";
                    } else {
                        $tr["<name>"] = "(?P<$placeholder>$pattern)?";
                    }
                } else {
                    $tr["<$name>"] = "(?P<$placeholder>$pattern)";
                }

                if (isset($this->_routeParams[$name])) {
                    $tr2["<$name>"] = "(?P<$placeholder>$pattern)";
                } else {
                    $this->_paramRules[$name] = $pattern === '[^]/]+' ? '' : "#^$pattern$#u";
                }
            }
        } 

        // preg_replace 将<controller:post> 替换成<controller>
        $this->_template = preg_replace('/<([\w._-]+):?([^>]+)?>/', '<$1>', $this->pattern);
        // 将$this->route 中的按照$tr中的键值替换一下，组成$this->pattern
        // 会变成$this->pattern = '#(?P<$name>$pattern)/(?P<$name>$pattern)'格式
        $this->pattern = '#' . trim(strtr($this->route, $tr), '/') . '$#u';

        if (!empty($this->_routeParams)) {
            // strtr转换字符串，将$tr2中的键值互换
            $this->_routeRule = '#' . strtr($this->route, $tr2) . '$#u';
        }
    }

    /**
     * 将浏览器中的路由，转换成最原始的路由规范
     */
    public function parseRequest($manager, $request)
    {
        if ($this->mode === self::PARSING_ONLY) {
            return false;
        }

        if (!empty($this->verb) && !in_array($request->getMethod(), $this->verb, true)) {
            return false;
        }

        // $pathInfo是默认入口文件后的部分
        $pathInfo = $request->getPathInfo();
        $suffix = (string)($this->suffix === null ? $manager->suffix : $this->suffix);
        if ($suffix !== '' && $pathInfo !== '') {
            $n = strlen($suffix);
            // 删除掉末尾的后缀
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

        if (!$preg_match($this->pattern, $pathInfo, $matches)) {
            return false;
        }
        $matches = $this->substitutePlaceholderNames($matches);
        foreach ($this->defaults as $name => $value) {
            if (!isset($matches[$name]) || $matches[$name] === '') {
                $matches[$name] = $value;
            }
        }

        // 将路由信息加上默认路由，去掉后缀，去掉主机信息，匹配路由规则
        $params = $htis->defaults;
        $tr = [];
        foreach ($matches as $name => $value) {
            if (isset($this->_routeParams[$name])) {
                // $tr['<action>'] = 'post'
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

    /**
     * 根据最原始的路由和参数生成在浏览器里能看的路由
     *
     */
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
                    // 将路由的参数保存到$tr中
                    // 存在默认路由和传递过来的路由相同的情况下可以省略
                    if (isset($this->defaults[$name]) && strcmp($this->defaults[$name], $matches[$name] === 0)) {
                        $tr[$token] = '';
                    } else {
                        // $tr['<action>'] = $matches[$name],就是路由中对应的(?P<$name>$pattern) $name 对应的值 
                        $tr[$token] = $matches[$name];
                    }
                }
            } else {
                return false;
            }
        }

        // 遍历所有的默认参数，传递过来的$params必须要包含的所有的默认参数
        // 提供的值和$defaults里保存的一样的情况下可以省略
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

        foreach ($this->_paramRules as $name => $rule) {
            // 传递过来的参数，没有规则，活着是符合规则，编码以后放入到$tr中
            if (isset($params[$name]) && !is_array($params[$name]) && ($rule === '' || preg_match($rule, $params[$name]))) {
                $tr["<$name>"]  = $this->encodeParams ? urlencode($params[$name]) : $params[$name];
                unset($params[$name]);
            } elseif (!isset($this->defaults[$name]) || isset($params[$name])) {
                return false;
            }
        }

        // 初步的更具$this->_template生成URL $this->_template =  '/post/<action>/<id>'
        $url = trim(strtr($this->_template, $tr), '/');
        if ($this->host !== null) {
            $pos = strpos($url, '/', 8);
            if ($pos !== false) {
                // 去掉路由中的+
                $url = substr($url, 0, $pos) . preg_replace('#/+#', '/', substr($url, $pos));
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

        // 根源其实还是有$this->_template转换来的
        return $url;
    }
    
    public function getParamRules()
    {
        return $this->_paramRules;
    }

    /**
     * 替换占位符
     * 如果在$this->placeholders 中定义了参数
     * 将参数的键值和键替换
     *
     */
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
