<?php
require(__DIR__ . '/BaseYii.php');

class Yii extends \yii\BaseYii
{
}

// 把Base::autoload 函数注入到自动加载队列，作为__autoload的实现，在遇到没有声明的函数的时候调起
spl_autoload_register(['Yii', 'autoload'], true, true);

//calsses文件是一个数组,主要是为了autoload自动加载使用
//通过类名找到对应的路径
Yii::$classMap = require(__DIR__ . '/classes.php');
//存放项目中使用的组件的容器
Yii::$container = new yii\di\Container();
