<?php

namespace yii\web;

interface UrlRuleInterface
{
    public function parseRequest($manager, $request);

    public function createUrl($manager, $route, $params);
}
