<?php

namespace yii\base;
use Yii;
/**
 * 
 * 实现这个接口的类必须要类似如下的声明
 * public function __construct($param1, $param2, ..., $cofig = [])
 * 主要是被\yii\di\Container 使用
 */
interface Configurable
{
}
