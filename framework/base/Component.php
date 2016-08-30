<?php
/**
 * 组件的基类
 * 一般所有的组件都会继承这个基类
 * 包含了属性，行为和事件
 */
namespace yii\base;
use Yii;

class Component extends Object
{
    // 这个组件绑定的事件
    private $_events;

    // 这个事件绑定的行为
    private $_behaviors;

    // 重新的定义查找不存在的属性，包括了检测在行为中出现的属性
    public function __get($name)
    {
        $getter = 'get' . $name;
        if (method_exists($this, $getter)) {
            return $this->$getter();
        } else {
            $this->ensureBehaviors();
            foreach ($this->_behaviors as $behavior) {
                if ($behavior->canGetProperty($name)) {
                    return $behavoir->$name;
                }
            }
        }

        if (method_exists($this, 'set' . $name)) {
            throw new InvalidCallException('Getting write-only property:' . get_class($this) . '::' . $name);
        } else {
            throw new InvalidCallException('Getting unkonwn property:' . get_class($this) . '::' . $name);
        }
    }

    /**
     * 给不可访问属性赋值的时候调用，包括检测该组件的属性
     */
    public function __set($name, $value)
    {
        $setter = 'set' . $name;
        if (method_exists($this, $setter)) {
            $this->$setter($value);
            return;
        } elseif (strcmp($name, 'on' , 3) === 0) {
             $this->on(trim(substr($name, 3)), $value);
             return;
        } elseif (strcmp($name, 'as', 3) === 0) {
            $name = trim(substr($name, 3));
            $this->attachBehavior($name, $value instanceof Behavoir ? $value : Yii::createObject($value));
            return;
        } else {
            $this->ensureBehavior();
            foreach ($this->_behaviors as $behavior) {
                if ($behavior->canSetProperty($name)) {
                    $behavior->$name = $value;
                    return;
                }
            }
        }

        if (method_exists($this, 'get' . $name)) {
            throw new InvalidCallException('Setting read-only property:' . get_class($this) . '::' . $name);
        } else {
            throw new InvalidCallException('Setting unknown property:' . get_class($this) . '::' . $name);
        }
    }

    /**
     * 在对不可访问的属性使用isset()时候调用，包括检测行为中的属性
     */
    public function __isset($name)
    {
        $getter = 'get' . $name;
        if (method_exists($this, $getter)) {
            return $this->$getter() !== null;
        } else {
            $this->ensureBehaviors();
            foreach ($this->behaviors as $behavior) {
                if ($behavior->canGetProperty($name)) {
                    return $behavior->$name !== null;
                }
            }
        }
        return false;
    }

    /**
     * 在对不可访问的属性使用unset()时候调用，包括检测行为中的属性
     */
    public function __unset($name)
    {
        $setter = 'set' . $name;
        if (method_exists($this, $setter)) {
            return $this->$setter(null);
            return;
        } else {
            $this->ensureBehaviors();
            foreach ($this->_behaviors as $behavior) {
                if ($behavior->canSetProperty($name)) {
                    $behavior->$name = null;
                    return;
                }
            }
        }
        throw new InvalidCallException('Unsetting an unknown or read-only property:' . get_class($this) . '::' . $name);
    }

    /**
     * 在对不可访问的方法时候调用，包括检测行为中方法
     */
    public function __call($name, $params)
    {
        $this->ensureBehaviors();
        foreach ($this->_behaviors as $object) {
            if ($object->hasMethod($name)) {
                return call_user_func_array([$object, $name], $params);
            }
        }    
        throw new UnkonwMethodException('Calling unkonwn method:' . get_class($this) . "::$name()");
    }

    /**
     * 对象复制的时候调用
     */
    public function __clone()
    {
        $this->_events = [];
        $this->_behaviors = null;
    }

    /**
     * 该组件是否有$name的属性
     * $checkVars 代表的是有public $name 一直有读写的权限的
     */
    public function hasProperty($name, $checkVars = true, $checkBehaviors = true)
    {
        return $this->canGetProoperty($name, $checkVars, $checkBehaviors) || $this->canSetProperty($name, false, $checkBehaviors);
    }

    /**
     * 用递归的方法来查看是否可以获取一个属性的值
     */
    public function canGetProperty($name, $checkVars = true, $checkBehaviors = true)
    {
        if (method_exists($this, 'get' . $name) || $checkVars && property_exists($this, $name)) {
            return true;
        } elseif ($checkBehaviors) {
            $this->ensureBehaviors();
            foreach ($this->_behaviors as $behavior) {
                if ($behavior->canGetProperty($name, $checkVars)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * 对组件的一个属性复制
     */
    public function canSetProperty($name, $checkVars = true, $checkBehaviors = true)
    {
        if (method_exists($this, 'set' . $name) || $checkVars && property_exists($this, $name)) {
            return true;
        } elseif ($checkBehaviors) {
            $this->ensureBehaviors();
            foreach ($this->_behaviors as $behavior) {
                if ($behavior->canSetProperty($name, $checkVars)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 确定该组件是否有$name 方法
     * $checkBehaviors 来判断是否检索行为中的方法
     * 也是通过递归来是实现的
     */
    public function hasMethod($name, $checkBehaviors = true)
    {
        if (method_exists($this, $name)) {
            return true;
        } elseif ($checkBehaviors) {
            $this->ensureBehaviors();
            foreach ($this->_behaviors as $behavior) {
                if ($behavior->hasMethod($name)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * 组件的行为，一般会被子类覆盖
     * 返回一个格式化的数组
     * 数组值一个行为类或者是返回行为名称索引的配置
     * 配置可以是一个字符串（指定的行为类）
     * 也可以是一个数组
     * 'behaviorName' => [
     *      'class' => 'BehaviorClass',
     *      'property1' => 'value1',
     *      'porperty2' => 'value2',
     * ]
     */
    public function behaviors()
    {
        return [];
    }

    /**
     * 判断该组件是否是否有事件及事件处理绑定
     */
    public function hasEventHandlers($name)
    {
        $this->ensureBehaviors();
        return !empty($htis->_event[$name]) || Event::hasHandlers($this, $name);
    }

    /**
     * 将名称是$name,处理是$handler的事件绑定到该组件上
     * $data 是否传递给事件处理器数据
     * $append 判断事件绑定在事件组中的顺序
     */
    public function on($name, $handler, $data = null, $append = true)
    {
        $this->ensureBehaviors();
        if ($append || empty($this->_events[$name])) {
            $this->_events[$name][] = [$handler, $data];
        } else {
            array_unshift($this->_events[$name], [$handler, $data]);
        }
    }

    /**
     * 将$name事件从该组件中删除
     */
    public function off($name, $handler = null)
    {
        $this->ensureBehaviors();
        if (empty($this->_events[$name])) {
            return false;
        }
        if ($handler === null) {
            unset($this->_event[$name]);
            return true;
        } else {
            $removed = false;
            foreach ($this->_events[$name] as $i => $event) {
                if ($event[0] === $handler) {
                    unset($this->_event[$name][$i]);
                    $removed = true;
                }
            }

            if ($removed) {
                $this->_events[$name] = array_values($name);
            }
            return $removed;
        }
    }

    /**
     * 触发事件
     * $_events[$name]保存的格式中
     * $handler 保存的是传递给事件处理器的数据和处理方法
     * $handler[0]处理方法,$handler[1]处理数据，$handler[2]
     */
    public function trigger($name, Event $event = null)
    {
        $this->ensureBehaviors();
        if (!empty($this->_events[$name])) {
            if ($event === null) {
                $event = new Event;
            }
            if ($event->sender === null) {
                $event->sender = $this;
            }
            $event->handled = false;
            $event->name = $name;
            foreach ($this->_events[$name] as $handler) {
                $event->data = $handler[1];
                call_user_func($handler[0], $event);
                if ($event->handled) {
                    return;
                }
            }
        } 
        Event::trigger($this, $name, $event);
    }

    /**
     * 获取行为
     */
    public function getBehavior($name)
    {
        $this->ensureBehaviors();
        return isset($this->_behaiors[$name]) ? $this->_behaviors[$name] : $null;
    }

    /**
     * 返回行为组
     */
    public function getBehaviors()
    {
        $this->ensureBehaviors();
        return $this->_behaviors;
    }

    /**
     * 绑定行为到该组件
     */
    public function attachBehavior($name, $behavior)
    {
        $this->ensureBehaviors();
        return $this->attachBehaviorInternal($name, $behavior);
    }

    /**
     * 绑定行为组到该组件
     */
    public function attachBehaviors($behaviors) {
        $this->ensureBehaviors();
        foreach ($behaviors as $name => $behavior) {
            $this->attachBehaviorInternal($name, $behavior);
        }
    }

    /**
     * 解除该组件的$name行为
     */
    public function detachBehavior($name)
    {
        $this->ensureBehaviors();
        if (isset($this->_behaviors[$name])) {
            $behavior = $this->_behaviors[$name];
            unset($this->_behaviors[$name]);
            $behavior->detach();
            return $behavior;
        } else {
            return null;
        }
    }

    /**
     * 删除该组件的所有行为
     */
    public function detachBehaviors()
    {
        $this->ensureBehaviors();
        foreach ($this->_behaviors as $name => $behavior) {
            $this->detachBehavior($name);
        }
    }

    /**
     * 保证定义的行为全部的绑定到了该组件
     */
    public function ensureBehaviors()
    {
        if ($this->_behaviors === null) {
            $this->_behaviors = [];
            foreach ($this->behaviors() as $name => $behavior) {
                $this->attachBehaviorInternal($name, $behavior);
            }

        }
    }

    /** 
     * 绑定一个行为到该组件
     * 如果不是behavior实例，说明是类名，配置数组，用createObject创建出来
     */
    public function attachBehaviorInternal($name, $behavior)
    {
        if (!$behavior instanceof Behavior) {
            $behavior = Yii::createObject($behavior);
        }

        if (is_int($name)) {
            $behavior->attach($this);
            $this->_behaviors[] = $behavior;
        } else {
            // 如果行为已经存在就先解除以后再绑定
            if (isset($this->_behaviors[$name])) {
                $this->detach($this);
            }
            $behavior->attach($this);
            $this->__behaviors[$anme] = $behavior;
        }
        return $behavior;
    }
}
