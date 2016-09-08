<?php

namespace yii\di;

use Yii;
use Closure;
use yii\base\Component;
use yii\base\InvalidConfigException;

/**
 * 用于解耦的服务定位
 *
 */

class ServiceLocator extends Component
{
    /**
     * 存放组件的实例
     */
    private $_components = [];

    /**
     * 存放组件的定义
     */
    private $_definitions = [];

    /**
     * 魔术方法
     * 像访问一个属性一样访问一个组件
     */
    public function __get($name)
    {
        if ($this->has($name)) {
            return $this->get($name);
        } else {
            return parent::__get($name);
        }
    }

    /**
     * 判断属性值是否是null 
     *
     */
    public function __isset($name)
    {
        if ($this->has($name, true)) {
            return true;
        } else {
            return parent::__isset($name);
        }
    }

    /**
     *
     * serviceLocator 是否有实例化的组件，活着是组件的定义
     *
     */
    public function has($id, $checkInstance = false)
    {
        return $checkInstance ? isset($this->_components[$id]) : isset($this->_definitions[$id]);
    }

    /**
     * 根据ID来获取组件的实例
     */
    public function get($id, $thorwException = true)
    {
        if (isset($this->_components[$id])) {
            return $this->_components[$id];
        }

        if (isset($this->_definitions[$id])) {
            $definition = $this->_definitions[$id];
            // 是对象，并且不是closure对象
            if (is_object($definition) && !$definition instanceof Closure) {
                return $this->_components[$id] = $definition;
            } else {
                return $this->_components[$id] = Yii::createObject($definition);
            }
        } elseif ($$throwException) {
            throw new InvalidConfigException("Unknown component ID : $id");
        } else {
            return null;
        }
    }

    /**
     * 定义组件
     * 可以是实例也可以是定义
     */
    public function set($id, $definition)
    {
        if ($definition === null) {
            unset($this->_components[$id], $this->_definitions[$id]);
            return;
        }

        unset($this->_components[$id]);

        if (is_object($definition) || is_callable($definition, true)) {
            $this->_definitions[$id] = $definition;
        } elseif (is_array($definition)) {
            if (isset($definition['class'])) {
                $this->_definitions[$id] = $definition;
            } else {
                throw new InvalidConfigException("The configuration for the \"$id\" component must contain a \"class\" element.");
            }
        } else {
                throw new InvalidConfigException("Unexpected configuration type for the \"$id\" component must contain a \"class\" element.");
        }
    }

    /**
     * 清除一个组件的实例和定义
     */
    public function clear($id)
    {
        unset($this->_definitions[$id], $this->_components[$id]);
    }

    /**
     * 获取所有组件的实例或定义
     */
    public function getComponents($returnDefinitions = true)
    {
        return $returnDefinitions ? $this->_definitions : $this->components;
    }

    /**
     *
     * 批量的注册组件的定义
     *
     */
    public function setComponents($components)
    {
        foreach ($components as $id => $component) {
            $this->set($id, $component);
        }
    }
}
