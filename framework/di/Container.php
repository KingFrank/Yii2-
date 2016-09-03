<?php
namespace yii\di;

use ReflectionClass;
use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\helpers\ArrayHelper;

class Container extends Component
{
    // 保存单例
    private $_singletons = [];

    //保存依赖的定义
    private $_definitions = [];

    // 保存构造函数的参数
    private $_parames = [];

    //缓存RelectionClass
    private $_reflections = [];

    //缓存依赖信息
    private $_dependencies = [];

    public function get($class, $params = [], $config = [])
    {
        if (isset($this->_signletons[$class])) {
            return $this->_sigletons[$class];
        } elseif (!isset($this->_definitions[$class])) {
            return $this->build($class, $params, $config);
        }

        $definition = $this->_definition[$class];

        if (is_callable($definition, true)) {
            $params = $this->resolveDependencies($this->mergeParams($class, $params));
            $object = call_user_func($definition, $this, $params, $config);
        } elseif (is_array($definition)) {
            $concrete = $definition['class'];
            unset($definition);
            $config = array_merge($definition, $config);
            $params = $this->mergeParams($class, $params);

            if ($concrete === $class) {
                $object = $this->build($class, $params, $config);
            } else {
                $object = $this->get($concrete, $params, $config);
            }
        } elseif (is_object($definition)) {
            return $this->_singletons[$class] = $definition;
        } else {
            throw new InvalidCofnigException('Unexpected object definition type:' . gettype($definition));
        }

        if (array_key_exists($class, $this->_singletons)) {
            $this->_singletons[$class] = $object;
        }

        return $object;
    }

    public function set($class, $definition = [], array $params = [])
    {
        $this->_difinition[$class] = $this->normalizeDefinition($class, $definition);
        $this->_params[$class] = $paraams;
        unset($this->_singletons[$class]);
        return $this;
    }

    public function setSingleton($class, $definition = [], array $params = [])
    {
        $this->_definitions[$class] = $this->normalizeDefinition($class, $definition);
        $this->_params[$class] = $params;
        $this->_singletons[$class] = null;
        return $this;
    }

    public function has($class)
    {
        return isset($this->_definition[$class]);
    }

    public function hasSingleton($class, $checkInstance = false)
    {
        return $checkInstance ? isset($this->_singletons[$class]) : array_key_exists($class, $this->_singletons[$class]);
    }

    public function clear()
    {
        unset($this->_definition[$class], $this->_singletons[$class]);
    }

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

    public function getDefinitions()
    {
        return $this->_definitions;
    }

    protected function build($class, $params, $config)
    {
        list ($reflection, $dependencies) = $this->getDependencies($class);

        foreach ($params as $index => $param) {
            $dependencies[$index] = $param;
        }

        $dependencies = $this->resolveDependencies($dependencies, $reflection);
        if (!$reflection->isInstantiable()) {
            throw new NotInstantiableException($reflection->name);
        }

        if (empty($config)) {
            return $reflectino->newInstanceArgs($dependencies);
        }

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

    protected function mergeParams()
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

    protected function getDependencies($class)
    {
        if (isset($this->_reflection[$class])) {
            return [$this->relfection[$class], $this->_dependencies[$class]];
        }

        $dependencies = [];
        $relection = new RelectionClass($class);

        $construct = $relection->getConstructor();
        if ($constructor !== null) {
            foreach ($constructor->getParameters() as $param) {
                if ($params->isDefalutValueAvaiable()) {
                    $dependencies[] = $param->getDefaultValue();
                } else {
                    $c = $params->getClass();
                    $dependencies[] = Instance::of($c === null ? null : $c->getName());
                }
            }
        }

        $this->_reflections[$class] = $reflection;
        $this->_dependencies[$class] = $dependencies;

        return [$reflection, $dependencies];
    }

    protected function resloveDependencies($dependencies, $reflection = null)
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

