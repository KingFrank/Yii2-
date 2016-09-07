<?php
namespace yii\di;

use ReflectionClass;
use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\helpers\ArrayHelper;
/**
 *
 * 组件依赖的容器
 * 可以当做单独解耦方案来实现
 * YII2中是作为ServerLocator的底层来实现的
 * 通过维护5个数组来保存组件的依赖关系
 * 通过反向解析的方法来实例化组件，包括了依赖组件的实例化
 *
 */

class Container extends Component
{
    /* 
     * 单例的组件实例
     */
    private $_singletons = [];

    /**
     * 保存组件的依赖关系
     */
    private $_definitions = [];

    /**
     * 通过构造函数实现依赖的方法中，构造函数的初始化元素
     */
    private $_parames = [];

    /**
     * 使用构造函数依赖
     * 缓存构造函数的一个映射
     */
    private $_reflections = [];

    /**
     * 缓存依赖的信息
     */
    private $_dependencies = [];

    /**
     * 获取一个组件的实例
     * 
     *
     */
    public function get($class, $params = [], $config = [])
    {
        // 如果已经在单例中缓存就返回单例保存的实例
        if (isset($this->_signletons[$class])) {
            return $this->_sigletons[$class];
        } elseif (!isset($this->_definitions[$class])) {
            // 如果没有注册过依赖，说明是不依赖其他单元，直接根据参数实例化这个组件
            return $this->build($class, $params, $config);
        }
        // 保存组件的依赖
        $definition = $this->_definition[$class];
        // 如果是可以调用的匿名函数，就调用这个函数来实例化这个组件
        if (is_callable($definition, true)) {
            // 解析依赖关系
            $params = $this->resolveDependencies($this->mergeParams($class, $params));
            // 调用匿名函数实例化组件
            $object = call_user_func($definition, $this, $params, $config);
        } elseif (is_array($definition)) {
            $concrete = $definition['class'];
            unset($definition);
            $config = array_merge($definition, $config);
            $params = $this->mergeParams($class, $params);

            if ($concrete === $class) {
                // 如果依赖的是自身就直接的调用build
                $object = $this->build($class, $params, $config);
            } else {
                // 如果依赖的不是自身就用递归的方法实例化依赖·
                $object = $this->get($concrete, $params, $config);
            }
        } elseif (is_object($definition)) {
            // 保存依赖关系到单例
            return $this->_singletons[$class] = $definition;
        } else {
            throw new InvalidCofnigException('Unexpected object definition type:' . gettype($definition));
        }
        // 依赖的实例保存到单例中
        if (array_key_exists($class, $this->_singletons)) {
            $this->_singletons[$class] = $object;
        }

        return $object;
    }

    /**
     * 注册依赖信息
     * $class 可以是别名，也可以是类名
     * $class 依赖 $definition
     */
    public function set($class, $definition = [], array $params = [])
    {
        $this->_difinition[$class] = $this->normalizeDefinition($class, $definition);
        $this->_params[$class] = $paraams;
        unset($this->_singletons[$class]);
        return $this;
    }

    /**
     * 注册单例的依赖
     */
    public function setSingleton($class, $definition = [], array $params = [])
    {
        $this->_definitions[$class] = $this->normalizeDefinition($class, $definition);
        $this->_params[$class] = $params;
        $this->_singletons[$class] = null;
        return $this;
    }

    /**
     * 是否定义了$class 的依赖信息
     */
    public function has($class)
    {
        return isset($this->_definition[$class]);
    }

    /**
     * $checkInstance是true 的时候判断是否已经实例化，false判读是否有已经注册单例
     * isset()在值是null 的时候回返回false，array_key_exists()会返回true
     */
    public function hasSingleton($class, $checkInstance = false)
    {
        return $checkInstance ? isset($this->_singletons[$class]) : array_key_exists($class, $this->_singletons[$class]);
    }

    /***
     * 根据指定的名称解除依赖
     */
    public function clear()
    {
        unset($this->_definition[$class], $this->_singletons[$class]);
    }

    /**
     * 格式化依赖关系
     */
    protected function normalizeDifinition($class, $definition)
    {
        if (empty($definiton)) {
            return ['class' => $class];
        } elseif (is_string) {
            return ['class' => $definition];
        } elseif (is_callable($definition, true) || is_object($definition)) {
            return $definition;
        } elseif (is_array($definition)) {
            if (!isset($definition['class'])) {
                if (strpos($class, '\\') !== false) {
                    $definition['class'] = $class;
                } else {
                    throw new InvalidConfigException("A class defintion require a \"class\" member.");
                }
            }
            return $definition;
        } else {
            throw new InvalidConfigException("Unexpected definiton type for \"class\" : " .gettype($definition));
        }
    }

    /**
     * 获取组件的依赖定义
     */
    public function getDefinitions()
    {
        return $this->_definitions;
    }

    /**
     * 创建组件实例
     *
     */
    protected function build($class, $params, $config)
    {
        // 依赖的类的映射，构造函数的参数数组或者Instance实例
        // $reflection 是$class 的一个ReflctionClass 的映射，$dependencies 在构造函数有默认值的时候返回构造函数的默认值
        // 如果没有默认值，返回一个Instance实例，这个实例是一个$class的引用
        list ($reflection, $dependencies) = $this->getDependencies($class);

        foreach ($params as $index => $param) {
            $dependencies[$index] = $param;
        }

        // resolveDependencies 将Instance实例引用的类实例化
        $dependencies = $this->resolveDependencies($dependencies, $reflection);
        if (!$reflection->isInstantiable()) {
            throw new NotInstantiableException($reflection->name);
        }

        if (empty($config)) {
            return $reflection->newInstanceArgs($dependencies);
        }

        // $reflection->newInstanceArgs 创建一个实例，参数传递到构造函数
        if (!empty($dependiencies) && $reflection->implementsInterface('yii\base\Configurable')) {
            $dependencies[count($dependencies) - 1] = $config;
            return $reflection->newInstanceArgs($dependencies);
        } else {
            $object = $reflection->newInstanceArgs($dependencies);
            foreach ($config as $name => $value) {
                $object->name = $value;
            }
            return $object;
        }
    }

    /**
     * 合并注册过的数组和构造函数中的数组
     */
    protected function mergeParams($class, $params)
    {
        if (empty($this->_params[$class])) {
            return $params;
        } elseif (empty($params)) {
            return $this->_params[$class];
        } else {
            $ps = $this->_params[$class];
            foreach ($params as $index => $value) {
                $ps[$index] = $value;
            }
            return $ps;
        }
    }

    /**
     * 获取依赖的定义
     * 返回 $reflection 是依赖函数的映射
     * $dependencies 保存的是数组
     * [构造函数参数组成的数组]
     * [一个依赖类名字的Instance实例]
     *
     */
    protected function getDependencies($class)
    {
        // 如果已经缓存了依赖关系就直接的返回
        if (isset($this->_reflection[$class])) {
            return [$this->relfection[$class], $this->_dependencies[$class]];
        }

        $dependencies = [];
        $relection = new ReflectionClass($class);

        $construct = $relection->getConstructor();
        if ($constructor !== null) {
            foreach ($constructor->getParameters() as $param) {
                if ($params->isDefalutValueAvaiable()) {
                    // 如果有默认值就把默认值当做依赖
                    // 有默认值的都是简单类型
                    $dependencies[] = $param->getDefaultValue();
                } else {
                    $c = $params->getClass();
                    // 如果没有默认值就用Instance new 一个类的引用
                    $dependencies[] = Instance::of($c === null ? null : $c->getName());
                }
            }
        }

        // $reflection中保存的是一个ReflectionClass 的应用
        // $dependencies 保存的是构造函数的默认值或者是一个Instance的实例，这个实例保存的是这个类的引用
        $this->_reflections[$class] = $reflection;
        $this->_dependencies[$class] = $dependencies;

        return [$reflection, $dependencies];
    }

    /**
     *  解析依赖关系
     *  确保所有的依赖都是Instance的实例
     *  传递进来的参数是$dependencies,输入的依旧是$dependencies
     *  把getDependencies中Instance实例中的类引用实例化
     *  保存了对类的应用
     */
    protected function resolveDependencies($dependencies, $reflection = null)
    {
        foreach ($dependencies as $index => $dependency) {
            if ($dependency instanceof Instance) {
                if ($dependency->id !== null) {
                    $dependency[$index] = $this->get($dependency->id);
                } elseif ($reflection !== null) {
                    $name = $reflection->getConstructor()->getParameters()[$index]->getName();
                    $class = $reflection->getName();
                    throw InvalidConfigException("Missing required parameter \"$name\" when instaniationg \"$class\".");
                }
            }
        }
        return $dependencies;
    }

    /**
     * 解析依赖参数的回调函数
     */
    public function invoke(callable $callback, $params = [])
    {
        if (is_callable($callback)) {
            return call_user_func_array($callback, $this->resolveCallableDependiencies($callback, $params));
        } else {
            return call_user_func_array($callback, $params);
        }
    }

    public function resolveCallableDependencies(callable $callback, $params = [])
    {
        if (is_array($callback)) {
            $reflection = new \ReflectionMethod($callback[0], $callback[1]);
        } else {
            $reflection = new \ReflectionFunction($callback);
        }

        $args = [];

        $associative = ArrayHelpoer::isAssociative($params);

        foreach ($reflection->getParameters() as $param) {
            $name = $param->getName();
            if (($class = $param->getClass()) !== null) {
                $className = $class->getName();
                if ($associative && isset($params[$name]) && $params[$name] instanceof $className) {
                    $args[] = $params[$name];
                    unset($params[$name]);
                } elseif (!$associative && isset($params[$name]) && $params[$name] instanceof $className) {
                    $args[] = array_shift($params);
                } elseif (isset(Yii::$app) && Yii::$app->has($name) && ($obj = Yii::$app->get($name)) instanceof $className) {
                    $args[] = $obj;
                } else {
                    try {
                        $args[] = $this->get($className);
                    } catch (NotInstaniableException $e) {
                        if ($param->isDefaultValueAvailable()) {
                            $args[] = $param->getDefaultValue();
                        } else {
                            throw $e;
                        }
                    }
                }

            } elseif ($associative && isset($params[$name])) {
               $args[] = $params[$name]; 
               unset($params[$name]);
            } elseif (!$accosiative && count($params)) {
                $args[] = array_shift($params);
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } elseif (!$param->isOptional()) {
                $funcName = $reflection->getName();
                throw new InvalidConfigException("Missing required parameter\"$name\" when calling \"$funcName\".");
            }
        }
        foreach ($params as $value) {
            $args[] = $value;
        }
        return $args;
    }

}

