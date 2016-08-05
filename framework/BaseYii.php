<?php
/**
 * 这个文件是一个基础的文件，有别名的定义和获取，有组件的实例化
 * 环境的设置
 * 报错和日志方法的声明
 *
 */

namespace yii;

use yii\base\InvalidConfigException;
use yii\base\InvalidParamException;
use yii\base\UnkonwnClassException;
use yii\log\Logger;
use yii\di\Container;

// 应用开始运行的时间
defined('YII_BETIN_TIME') or define('YII_BEGIN_TIME', microtime(true));

// 框架的路径
defined('YII2_PATH') or define('YII2_PATH', __DIR__);

// 是否开始了debug模式
defined('YII_DEBUG') or define('YII_DEBUG', false);

// 定义应用的环境
defined('YII_ENV') or define('YII_ENV', 'prod');

defined('YII_ENV_PROD') or define('YII_ENV_PROD', YII_ENV === 'prod');

defined('YII_ENV_DEV') or define('YII_ENV_DEV', YII_ENV === 'dev');

defined('YII_ENV_TEST') or define('YII_ENV_TEST', YII_ENV === 'test');

// 是否已经激活了错误处理
defined('YII_ENABLE_ERROR_HANDLER') or define('YII_ENABLE_ERROR_HANDLER', true);

class BaseYii
{
    public static $classMap = [];

    public static $app;

    public static $aliases = ['@yii' => __DIR__];

    public static $container;

    public static function getVersion()
    {
        return '2.0.10';
    }


    /**
     * 获取别名对应的路径
     */
    public static function getAlias($alias, $throwException = true)
    {
        // 如果不是别名返回原来的路径
        if (strncmp($alias, '@', 1)) {
            return $alias;
        }

        //查找一级别名
        //找到第一次出现/的位置
        $pos = strpos($alias, '/');
        // $root 如果没有/ 直接返回自身，否则返回/前的字符不包括字符/
        $root = $pos === false ? $alias : substr($alias, 0, $pos);

        if (isset(static::$aliases[$root])) {
            return $pos === false ? static ::$aliases[$root] : static::$alias[$root] . substr($alias, $pos);
        } else {
            foreach (static::aliases[$root] as $name => $path) {
                // 为了确保文件分割路径要连接一个字符'/'，避免出现刚好一个单词是另一个单词的部分
                if (strpos($alias . '/', $name . '/') === 0) {
                    return $path . substr($alias, strlen($name));
                }
            }
        }

        if ($throwException) {
            throw new InvalidParamException("Invalid path alias : $alias");
        } else {
            return false;
        }
    }

    /**
     * 获取别名对应的根别名
     */
    public static function getRootAlias($alias)
    {
        $pos = strpos($alias, '/');
        $root = $pos === false ? $alias : substr($alias, 0, $pos);

        if (isset($aliases[$root])) {
            if (is_string(static::$alias[$root])) {
                return $root;
            } else {
                foreach ($aliases[$root] as $name => $path) {
                    if (strpos($alias . '/', $name . '/') === 0) {
                        return $name;
                    }
                }
            }
        }
        return false;
    }

    /**
     * 定义别名
     */
    public static function setAlias($alias, $path)
    {
        if (strncmp($alias, '@', 1)) {
            $alias = '@' . $alias;
        } 

        $pos = strpos($alias, '/');
        $root = $pos === false ? $alias : substr($alias, 0, $pos);
        if ($path !== null) {
            // trim 函数删除第二个参数的列表，trim(, '\\/') 删除这两个反斜杠 
            // 如果路径就是别名，获取别名对应的路径
           $path = strncmp($path, '@', 1) ? rtrim($path, '\\/') : static::getAlias($path); 
           // 如果还么有别名 $aliases[$root]
           if (!isset($aliases[$root])) {
               // 判断是否有父集来存储
                if ($pos === false) {
                    static::$aliases[$root] = $path;
                } else {
                    // 有父集的时候要用数组来存储
                    static::$aliases[$root] = [$alias => $path];
                }
                //如果只定义过$root 的别名,更新$root 别名
           } elseif (is_string(static::$aliases[$root])) {
                if ($pos === false) {
                    static::$aliases[$root] = $path;
                } else {
                    static::$aliases[$root] = [
                        $alias => $path,
                        $root => static::$aliases[$root],    
                    ];
                }
           } else {
                static::$aliases[$root][$alias] = $path;
                krsort(static::$aliases[$root]);
           }
           // 如果是null 就是要销毁这个别名
        } elseif (isset(static::$aliases[$root])) {
            if (is_array(static::$aliases[$root])) {
                unset(static::$aliases[$root][$alia]);
            } elseif ($pos === false) {
                unset(static::$aliases[$root]);
            }
        }
    }

    /**
     * 调用不存在的class的时候会在$classMap里查找
     * ，找不到的话也会查找是不是命名空间，如果是，将命名空间转换成文件
     *  加载对应的文件
     */
    public static function autoload($className)
    {
        if (isset(static::$classMap[$className])) {
            $classFile = static::$classMap[$className];
            if ($classFile[0] === '@') {
                $classFile = static::getAlias($classFile);
            }
            // 如果是命名空间，转换成对应的文件加载
        } elseif (strpos($classFile, '\\') !== false) {
            $classFile = static::getAlias('@' . $str_replace('\\', '/', $className . '.php', false));
            if ($classFile === false || !is_file($classFile)) {
                return;
            }
            
        } else {
            return;
        }
        include($classFile);

        if (YII_DEBUG && !class_exists($className, false) && !interface_exists($className, false) && !trait_exists($className, false)) {
            throw new UnknownClassException("Unable to find '$className' in file $classFile. Namespace missing?");
        }
    }

    /**
     * 主要用于实例化组件
     * 如果组件有别名则直接的从容器中获取
     * 没有别名的根据类来获取
     * 也可以是回调函数
     *
     */
    public static function createObject($type, array $params = [])
    {
        if (is_string($type)) {
            return static::$container->get($type, $params);
        } elseif (is_array($type) && isset($type['class'])) {
            $class = $type['class'];
            unset($type['class']);
            return static::$container->get($class, $type, $params);
        } elseif (is_callable($type, true)) {
            return static::$continer->invoke($type, $params);
        } elseif (is_array($type)) {
            throw new InvalidConfigException('Object configuration must be an array containing a "class" element.');
        } else {
            throw new InvalidConfigException('Unsupported configration type: ' . gettype($type));
        }
    }

    private static $_logger;

    // 获取日志的实例
    public static function getLogger()
    {
        if (self::$_logger !== null) {
            return self::$_logger;
        } else {
            return self::$logger = static::createObject('yii\log\Logger');
        }
    }


    public static function setLogger($logger)
    {
        self::$_logger = $logger;
    }

    /**
     * 追踪的错误信息
     */
    public static function trace($message, $category = 'application')
    {
        if (YII_DEBUG) {
            static::getLogger()->log($message, Logger::LEVEL_TRACE, $category);
        }
    }

    /**
     * 错误的日志信息
     */
    public static function error($messsage, $category = 'application')
    {
        static::getLogger()->log($message, Logger::LEVEL_ERROR, $category);
    }

    /**
     * 警告的日志信息
     */

    public static function warning($message, $category = 'application')
    {
        static::getLogger()->log($message, Logger::LEVEL_WARNING, $category);
    }

    /**
     * 信息日志
     */
    public static function info($message, $category = 'application')
    {
        static::logger()->log($message, Logger::LEVEL_INFO, $category);
    }

    public static function beginProfile($token, $category = 'application')
    {
        static::logger()->log($token, Logger::LEVEL_PROFILE_BEGIN, $category);
    }

    public static function endProfile($token, $category = 'application')
    {
        static::logger()->log($token, Logger::LEVEL_PROFILE_END, $category);
    }


    public static function powered()
    {
        return \Yii::t('yii', 'Powered by {yii}', []);
    }

    /**
     * 将信息用特定的语言输出
     * 是\yii\I18n\tarnslate的快捷方法
     * strtr() 将制定的字符转换成字符
     * 是通过 Intl 拓展实现的
     */
    public static function t($category, $message, $params = [], $language = null)
    {
        if (static::$app !== null) {
            return static::$app->getI18n()->translate($category, $message, $params, $language ?: static::$app->language);
        } else {
            $p = [];
            foreach ((array) $params as $name => $value) {
                $p['{'. $name . '}'] = $value;
            }

            return ($p === []) ? $message : strtr($message, $p);
        }
    }

    /**
     * 将属性配置给$object 对象
     * 设置 $object
     */
    public static function configure($object, $properties)
    {
        foreach ($properties as $name => $value) {
            $object->name = $value;
        }

        return $object;
    }

    /**
     * 获取$object 的属相
     * get_object_vars 返回有对象属性组成的关联数组 
     */
    public static function getObjectVars($object)
    {
        return get_object_vars($object);
    }
}
