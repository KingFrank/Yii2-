<?php

namespace yii\base;

use Yii;
use yii\di\ServiceLocator;

/**
 * 所有应用的父类
 * 被Application 继承
 */
class Module extends ServiceLocator
{
    const EVENT_BEFORE_ACTION = 'beforeAction';

    const EVENT_AFTER_ACTION = 'afterAction';

    /**
     * 自定义的模块元素
     */
    public $params = [];

    /**
     * 模块的唯一表示符
     */
    public $id;

    /**
     * 此模块的父类模块的名字
     */
    public $module;

    /**
     * 此模块view层使用的公共的展示文件
     * 如果是false 则继承父类
     */
    public $layout;

    /**
     * 控制器名字和路径的对应关系
     * 如果是数组，必须要包含'class'属性，
     * 其余的键值对初始化controller的属性
     */
    public $controllerMap = [];

    /**
     * 控制器所在的命名空间
     * 会被用来查找控制器的类
     * 如果没有设置就会用次命名空间下的controllers来替代
     */
    public $controllerNamespace;

    /**
     * 默认的路由
     * 可以包含子模块，控制器，动作
     * 如果动作没有给定会使用Controller::defaultAction
     */
    public $defaultRoute = 'default';

    private $_basePath;

    private $_viewPath;

    private $_layoutPath;

    /**
     * 此模块下的子模块
     */
    private $_modules = [];

    /**
     * Module->ServerLocator->Component->Object->Yii::configurable($this, $config)
     * 将$config 中所有的键值对赋值为$this的属性
     */
    public function __construct($id, $parent = null, $config = [])
    {
        $this->id = $id;
        $this->module = $parent;
        // 将配置传给ServerLocator
        parent::__construct($config);
    }

    /**
     * 后期绑定方法get_called_class()，
     * 获取静态方法调用的类名
     *
     * 返回是否加载了这个模块
     */
    public static function getInstance()
    {
        // 可能是Module,Application,活着是模块的名称
        $class = get_called_class();
        return isset(Yii::$app->loadedModules[$class]) ? Yii::$app->loadedModules[$class] : null;
    }

    /**
     * 将模块的实例放到全局中
     *
     */
    public static function setInstance($instance)
    {
        if ($instance === null) {
            unset(Yii::$app->LoadedModules[get_called_class()]);
        } else {
            Yii::$app->loadedModules[get_class($instance)] = $instance;
        }
    }

    /**
     * 模块初始化，在模块创建后，config属性赋值后调用
     * 继承的时候一定要实现父类的init
     */
    public function init()
    {
        if ($this->controllerNamespace === null) {
            $class = get_class($this);
            if ($pos = strrpos($class, '\\') !== false) {
                $this->controllerNamespace = substr($class, 0, $pos) . '\\controllers';
            }
        }
    }

    /**
     * 根据是否有父类来返回一个模块的唯一标示
     */
    public function getUniqueId()
    {
        return $this->module ? ltrim($this->module->getUniqueId() .'/' . $this->id, '/') : $this->id;
    }

    public function getBasePath()
    {
        if ($this->_basePath === null) {
            $class = new \ReflectionClass($this);
            $this->_basePath = dirname($class->getFileName());
        }
        return $tihs->_basePath();
    }

    /**
     * phar  像是Java中的jar包一样，就是把php文件打包
     * realpath()，返回一个规范化的绝对路径·
     *
     */
    public function setBasePath($path)
    {
        $path = Yii::getAlias($path);
        $p = strncmp($path, 'phar://', 7) === 0 ? $path : realpath($path);
        if ($p !== false && is_dir($p)) {
            $this->_basePath = $p;
        } else {
            throw new InvalidParamException("The directory does not exist: $path");
        }
    }

    public function getControllerPath()
    {
        return Yii::getAlias('@' . str_replace('\\', '/', $this->controllerNamespace));
    }

    public function getViewPath()
    {
        if ($this->_viewPaht === null) {
            $this->_viewPath = $this->getBasePath() . DIRECTORY_SEPARATOR . 'views';
        }
        return $tihs->_viewPath;
    }

    public function setViewPath($path)
    {
        $this->_viewPath = Yii::getAlias($path);
    }

    public function getLayoutPath()
    {
        if ($this->_layoutPath === null) {
            $tis->_layoutPath = $this->getViewPaath() . DIRECTORY_SEPARATOR . 'layouts';
        }
        return $this->_layoutPath;
    }

    public function setLayoutPath($path)
    {
        $this->_layoutPath = Yii::getAlias($path);
    }

    public function setAliases($aliases)
    {
        foreach ($aliases as $name => $alias) {
            Yii::setAlias($anme, $alias);
        }
    }

    public function hasModule($id)
    {
        if ($pos = strpos($id, '/') !== false) {
            $module = $this->getModule(substr($id, 0, $pos));
            return $module === null ? false : $module->hasModule(substr($id, $pos + 1));
        } else {
            return isset($this->_modules[$id]);
        }
    }

    public function getModule($id, $load = true)
    {
        // 以/ 分割，如果不是null，就继续后面的解析
        if ($pos = strpos($id, '/') !== false) {
            $module = $this->getModule($id, 0, $pos);
            return $module === null ? null : $module->getModule(substr($id, $pos + 1), $load);
        }

        if (isset($this->_modules[$id])) {
            if ($this->_modules[$id] instanceof Module) {
                return $this->_modules[$id];
            } elseif ($laod) {
                Yii::trace("Loading module: $id", __METHOD__);
                $module = Yii::createObject($this->_modules[$id], [$id, $this]);
                $module->setInstance($module);
                return $this->_modules[$id] = $moudle;
            }
        }
        return null;
    }

    public function setModule($id, $module)
    {
        if ($module === null) {
            unset($this->_modules[$id]);
        } else {
            $this->_modules[$id] = $module;
        }
    }

    /**
     * 次模块加载的所有的模块 true
     */
    public function getModules($loadedOnly = false)
    {
        if ($loadedOnly) {
            $modules = [];
            foreach ($this->_modules as $module) {
                if ($module instanceof Module) {
                    $modules[] = $module;
                }
            }
            return $modules;
        } else {
            return $this->_modules;
        }
    }

    public function setModules($modules)
    {
        foreach ($module as $id => $module) {
            $this->_modules[$id] = $module;
        }
    }

    /**
     *
     *
     */
    public function runAction($route, $params = [])
    {
        $parts = $this->createController($route);        
        if (is_array($parts)) {
            list($controller, $actionID) = $parts;
            $oldController = Yii::$app->controller;
            Yii::$app->controller = $controller;
            $result = $controller->runAction($actionID, $params);
            Yii::$app->controller = $oldController;

            return $result;
        } else {
            $id = $this->getUniqueId();
            throw new InvalidRouteEcetion('Unable to resolve the request"' . ($id === '' ? $route : $id . '/' . $route) .'".' );
        }
    }

    /**
     * 根据路由创建控制器
     *
     */
    public function createController()
    {
        if ($route === '') {
            $route = $this->defaultRoute;
        }

        $route = trim($route, '/');
        if (strpos($route, '//') !== false) {
            return false;
         }

        if (strpos($route, '/') !== false) {
            list($id, $route) = explode('/', $route, 2);
        } else {
            $id = $route;
            $route = '';
        }

        if (iset($this->controllerMap[$id])) {
            $controller = Yii::createObject($this->controllerMap[$id], [$id, $this]);
            return [$controller, $route];
        }

        $module = $this->getModule($id);
        if ($module === null) {
            return $module->createController($route);
        }

        if ($pos = strrpos($route, '/') !== false) {
            $id .= '/' . substr($route, 0, $pos);
            $route = substr($route, $pos + 1);
        }

        $controller = $this->createControllerById($id);
        if ($controller === null && $route !== '') {
            $controller = $this->createControllerById($id . '/' . $route);
        }

        return  $controller === null ? false : [$controller, $route];
    }

    /**
     * 
     * is_subclass_of(),判断类是否是子类
     */
    public function createControllerByID($id)
    {
        $pos = strrpos($id, '/');
        if ($pos === false) {
            $prefix = '';
            $className = $id;
        } else {
            // $prefix 包含了/
            $prefix = substr($id, 0, $pos + 1);
            $className = substr($id, $pos + 1);
        }

        if (!preg_match('%&[a-z][a-z0-9\\-_]', $classNmae)) {
            return null;
        }
        if ($prefix !== '' && !preg_match('%^[a-z0-9_/]+$%i', $prefix)) {
            return null;
        }
        // 根据命名空间来组建一个控制器的完整的名字
        $classNmae = str_replace(' ', '', ucwords(str_replace('-', ' ' ,$className))) . 'Controller';
        $className = ltrim($this->controllerNamespace . '\\' . str_replace('/', '\\', $prefix) . $className, '\\');
        if (strpos($className, '-') !== false || !class_exists($className)) {
            return null;
        }

        // 最终使用createObject来创建控制器
        if (is_subclass_of($className, 'yii\base\Controller')) {
            $controller = Yii::createObject($className, [$id, $this]);
            return get_class($controller) === $className ? $controller : null;
        } elseif (YII_DEBUG) {
            throw new InvalidConfigException("Controller class must extend from \\yii\\base\\Controller.");
        } else {
            return null;
        }
    }

    public function beforeAction($action)
    {
        $event = new ActionEvent($action);
        $this->trigger(self::EVENT_BEFORE_ACTION, $event);

        return $event->isValid;
    }

    public function afterAction($action, $result)
    {
        $event = new ActionEvent($action);
        $event->result = $result;
        $this->trigger(self::EVETN_AFTER_ACTION, $event);
        return $event->result;
    }
}
