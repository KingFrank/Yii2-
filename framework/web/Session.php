<?php

namespace yii\web;

use yii;
use yii\base\Component;
use yii\InvalidConfigException;
use yii\InvalidParamException;

class Session extends Component implements \IteratorAggregate, \ArrayAccess,\Countable
{
    public $flashParam = '__flash';

    public $handler;
}
