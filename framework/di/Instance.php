<?php

namespace yii\di;

use Yii;
use yii\base\InvalidConfigException;

/**
 * 这个类主要是为依赖注入使用的，可以通过类名，别名活或着接口名字来索引一个组件
 * 可以通过$id 被解析成一个实例
 * 实现部分主要是在$container中
 *
 */
class Instance
{
    // 组件的唯一标示,可以是类名，接口名，别名
    public $id;

    protected function __construct($id)
    {
        $this->id = $id;
    }

    /**
     * 创建一个新的Instance实例
     */
    public static function of($id)
    {
        return new static($id);
    }

    /**
     * 通过解析依赖关系生成一个确定的组件，并保证组件的类型
     * 应用类型是字符串或者一个Instance实例
     * 字符串会被当做组件的ID，类，Instance或者悲鸣依赖容器的类型
     * 如果没有$container就是先实例化一个
     *
     */
    public static function ensure($reference, $type = null, $container = null)
    {
        // 如果引用是数组,数组中没有配置实例的原型，就拿类型当做原型
        if (is_array($reference)) {
            $class = isset($reference['class']) ? $reference['class'] : $type;
            if (!$container instanceof Container) {
                $container = Yii::$container;
            }
            unset($reference['class']);
            // 获取一个组件的实例
            return $container->get($class, [], $reference);
        } elseif (empty($reference)) {
            throw new InvalidConfigException('The required component is not specified.');
        }

        // 如果是字符串的话，就是组件的ID
        if (is_string($reference)) {
            $reference = new static($reference);
        } elseif ($type === null || $reference instanceof $type) {
            // 如果已经实例化过的对象就直接的返回
            return $reference;
        }

        //，用get方法来获取实例
        if ($reference instanceof self) {
            $component = $reference->get($container);
            if ($type === null || $component instanceof $type) {
                return $component;
            } else {
                throw new InvalidConfigException('"' . $reference->id . '"refers to a ' . get_class($component) . " component $type is expected");
            }
        }
        $valueType = is_object($reference) ? get_class($reference) : gettype($reference);
        throw new InvalidConfigException("Invalid data type: $valueType. $type is expected");

    }

    /**
     * 如果容器中已经有了这个组件的实例，就根据ID返回这个实例
     * 如果有这个实例的缓存，就获取缓存
     * 没有就重新的实例化一个
     */
    public function get($container = null)
    {
        if ($container) {
            return $container->get($this->id);
        }

        if (Yii::$app && $app->has($this->id)) {
            return Yii::$app->get($this->id);
        } else {
            return Yii::$app->get($this->id);
        }
    }
}
