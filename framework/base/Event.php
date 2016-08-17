<?php

namespace yii\base;
use Yii;

class Event extends Object
{
    /**
      * 事件的调名称
      * 由 Component::trigger() 和 trigger() 设定
      * handlers 会用这个属性来检测时间是否处理
     */
    public $name;

    /**
     * 事件的调起者
     * 如果没有设定值会把调用trigger()的类名称赋值给$sender
     * 这个属性可能是Null 当这个事件是在静态情况下引发的类级事件
     */
    public $sender;

    /**
     * 事件是否已经被处理
     * 如果是true 以后的处理器就不能继续的处理
     */
    public $handled = false;

    /**
     * 由Compontent::on() 绑定到处理器上
     * 这个变量存储的是目前执行的事件的数据
     */
    public $data;

    /**
     * 保存全局事件的容器
     */
    public static $_events = [];

    /**
     * 绑定事件处理器到类级事件
     * 当一个类级事件被触发的时候,事件处理器和所有的父集处理器会被调用
     * $class 是一个全名称的类名
     * $append 事件插入事件列表的顺序，false 插入到最前面
     */
    public static function on($class, $name, $handler, $data = null, $append = true)
    {
        $class = ltrim($class, '\\');    
        if ($append || empty(self::$_events[$name][$class])) {
            self::$_events[$name][$class][] = [$handler, $data];
        } else {
            array_unshift(self::$_events[$name][$class], [$handler, $data]);
        }
    }

    /**
     * $class 要是全称的
     * 将处理器和事件解除绑定
     * 如果 $handler 是null 的话所有绑定到该名称事件的所有处理器会被解除
     */
    public static function off($class, $name, $handler = null)
    {
        $class = ltrim($class, '\\'); 
        if (empty(self::$_evnets[$name][$class])) {
            return false;
        }
        if ($handler === null) {
            unset($_events[$name][$class]);
            return true;
        } else {
            $removed = false;
            foreach (self::$_events[$name][$class] as $i => $event) {
                if ($event[0] === $handler) {
                    unset(selft::$_event[$name][$class][$i]);
                    $removed = true;
                }
            }
            if ($removed) {
                self::$_events[$name][$class] = array_values(self::$_events[$name][$class]);
            }
            return $removed;
        }
    }

    /**
     * 指定的类级事件是否绑定了处理器
     * 也会检测所有的父集
     */
    public static function hasHandlers($class, $name)
    {
        if (empty(self::$_event[$name][$class])) {
            return false;
        }
        if (is_object($class)) {
            $class = get_class($class);
        } else {
            $class = ltrim($class, '\\');
        }

        $classes = array_merge(
            [$class],
            class_parents($class, true),
            class_implements($class, true)
        );

        foreach ($classes as $class) {
            if (!empty(self::$_events[$name][$class])) {
                return ture;
            }
        }
        return false;
    }

    /**
     * 事件的触发
     */
    public static function trigger($class, $name, $event = null)
    {
        if (empty(self::$_events[$name][$class])) {
            return;
        }

        if ($event === null) {
            $event = new static;
        }

        $event->handled = false;
        $event->name = $name;

        if (is_object($class)) {
            if ($event->sender === null) {
                $event->sender = $class;
            }
            $class = get_class($class);
        } else {
            $class = ltrim($class, '\\');
        }

        $classes = array_merge(
            [$class],
            class_parents($class, true),
            class_implements($class, true)
        );

        foreach ($classes as $class) {
            if (!empty(self::$_event[$name][$class])) {
                foreach (self::$_event[$name][$class] as $handler) {
                    $event->data = $handler[1];
                    call_user_func($handler[0], $event);
                    if ($event->handled) {
                        return;
                    }
                }
            }
        }
    }

}
