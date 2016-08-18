<?php

namespace yii\base;

/**
 * 行为是动态的注入到组件当中，能够直接的被组件调用
 * 和trait类似
 */
class Behavior extends Object
{
    /**
     * 绑定行为的组件
     */
    public $owner;

    /**
     * 声明组件绑定事件的事件处理器
     * 子类覆盖的时候回绑定回调方法到这个组件的事件
     * 回调方法在行为绑定到这个组件的时候绑定
     * 当行为解除绑定以后事件的处理器也解除绑定
     * 回调函数可以有四种方式
     * 'handlerClick' 和 [$this, 'handlerClick'] 相同
     * 实例的方法[$object, 'handlerClick']
     * 类的静态方法['Page', 'handlerClick']
     * 匿名函数 function
     * [
     *      Model::EVENT_BEFORE_VALIDATE => 'myBeforeValidate',
     *      Model::EVENT_AFTER_VALIDATE => 'myAfterValidate',
     * ]
     */
    public function events()
    {
        return [];
    }

    /**
     * 绑定行为到组件
     * 默认情况给组件属性赋值，
     * 绑定在events 中声明的事件处理器
     * 如果子类覆盖了次方法确保调用了父类的实现
     */
    public function attach($owner)
    {
        $this->owner = $owner;
        foreach ($this->events() as $event => $handler) {
            $owner->on($event, is_string($handler) ? [$this, $handler] : $handler);
        }
    }

    /**
     * 解除组件的行为
     * 将组件的属相置空
     * 如果子类覆盖了此方法，确保所有的父类调用都实现了
     */
    public function detach()
    {
        if ($this->owner) {
            foreach ($this->events() as $event => $handler) {
                $this->owner->off($event, is_string($handler) ? [$this, $handler] : $handler);
            }
        }
        $this->owner = null;
    }
}
