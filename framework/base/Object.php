<?php
/**
 * 这个是规范类的属性读写的基类
 * 框架中基本所有的类都继承了这个基类
 * 参考了一个大牛写的比较完善的文档 http://www.digpage.com/property.html
 *
 * php 提供的重载是动态的创建类的属性和方法
 * 重载是通过魔术方法来是是实现的
 * 当调用当前环境不存在或不可见的类属性或方法的时候，重载方法会被调用
 */
namespace yii\base;
use Yii;

class Object implements Configurable
{
    /**
     * 返回调用的类的名称，
     * 使用的是后期静态绑定
     */
    public static function className()
    {
        return get_called_class();
    }

    /**
     * 就是一个构造方法，实例化的时候把$config 作为最后一个参数传递
     * Yii::configure($this, $config) 方法是把$config 中的键和值对应到$this 中的属性和值
     */
    public function __construct($config = [])
    {
        if (!empty($config)) {
            Yii::configure($this, $config);
        }
        $this->init();
    }

    public function init()
    {
    }

    /**
     * 覆盖PHP中的魔法函数
     * 调用没有声明或者私有函数的时候调用
     */
    public function __get($name)
    {
        $getter = 'get' . $name;
        if (method_exists($this, $getter)) {
            return $this->getter;
        } elseif (method_exists($this, 'set' . $name)) {
            throw new InvalidCallException('Getting write-only property' . get_class($this) . '::' . $name);
        } else {
            throw new InvalidCallException('Getting read-only property' . get_class($this) . '::' . $name);
        }
    }

    /**
     * 覆盖PHP中的__set 魔方函数
     * 在给没有存在或者私有的变量赋值的时候被调用
     */
    public function __set($name, $value)
    {
        $setter = 'set' . $name;
        if (method_exists($this, $setter)) {
            return $this->setter($value);
        } elseif (method_exists($this, 'get' . $name)) {
            throw new InvalideCallException('Getting read-only property' . get_class($this) . '::' . $name);
        } else {
            throw new InvalidCallException('Getting write-only property' . get_class($this) . '::' . $name);
        }
    }

    /**
     * 覆盖PHP中的__isset() 魔法函数
     * 在检测没有声明或者私有的变量的时候被调用 isset(),empty();
     */
    public function __isset($name)
    {
        $getter = 'get' . $name;
        if (method_exists($this, $getter)) {
            return $this->getter() !== null;
        } else {
            return false;
        }
    }

    /**
     * 覆盖PHP中的__unset() 魔法函数
     * 对不可访问属性调用unset()的时候被调用
     */
    public function __unset($name)
    {
        $setter = 'set' . $name;
        if (method_exists($this, $setter)) {
            $this->setter(null);
        } elseif (method_exists($this, 'get' . $name)) {
            throw new InvalidCallException('Unsetting read-only property' . get_class($this) . '::' . $name);
        }
    }

    /**
     * 覆盖PHP中的魔法函数 __call()
     * 调用不可访问的方法时被调用
     */
    public function __call($name, $params)
    {
        throw new UnknownMethodException('Calling unknown method:' . get_class($this) . "::$name()");
    }

    /**
     * 类中是否有$name属性
     * 这个属性可以读写
     */
    public function hasProperty($name, $checkVars = true)
    {
        return $this->canGetProperty($name, $checkVars) || $this->canSetProperty($name, $false);
    }

    public function canGetProperty($name, $checkVars)
    {
        return method_exists($this, 'get' . $name) || $checkVars && property_exists($this, $name);
    }

    public function canSetProperty($name, $chackVars)
    {
        return method_exists($this, 'set' . $name) || $checkVars && property_exists($this, $name);
    }

    public function hasMethod($name)
    {
        return method_exists($this, $name);
    }
}
