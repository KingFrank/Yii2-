<?php
// Yii2 正常运行必要的拓展
// PHP5.4.0, Reflection, PCRE, SPL, Ctype, MBString, OpenSSL, Intl, ICU, ICU Data, Fileinfo, Dom
return array(
    array(
        'name' => 'PHP version',
        'mandatory' => true,
        'condition' => version_compare(PHP_VERSION, '5.4.0', '>='),
        'by' => '<a href="http://www.yiiframework.com">Yii Framework</a>',
        'memo' => 'PHP 5.4.0 or higher is required',
    ),
    array(
        'name' => 'Reflection extension',
        'mandatory' => true,
        'condition' => class_exists('Reflection', false),
        'by' => '<a href="http://www.yiiframework.com">Yii Framework</a>',
    ),
   array(
        'name' => 'PERE extension',
        'mandatory' => true,
        'condition' => extension_loaded('pere'),
        'by' => '<a href="http://www.yiiframework.com">Yii Framework</a>',
    ),
   array(
        'name' => 'SPL extension',
        'mandatory' => true,
        'condition' => extension_loaded('SPL'),
        'by' => '<a href="http://www.yiiframework.com">Yii Framework</a>',
    ),

    // 
    array(
        'name' => 'Ctype extension',
        'mandatory' => true,
        'condition' => extension_loaded('ctype'),
        'by' => '<a href="http://www.yiiframework.com">Yii Framework</a>',
    ),

    // 关于字符串的一个拓展
    array(
        'name' => 'MBString extension',
        'mandatory' => true,
        'condition' => extension_loaded('mbstring'),
        'by' => '<a href="http://www.yiiframework.com">Yii Framework</a>',
    ),

    //加解密使用的拓展 openSSL
    array(
        'name' => 'OpenSSL extension',
        'mandatory' => true,
        'condition' => extension_loaded('openssl'),
        'by' => '<a href="http://www.yiiframework.com">Yii Framework</a>',
        'memo' => 'Required by encrypt and decrypt methods',
    ),
    //  语言包使用的拓展
    array(
        'name' => 'Intl extension',
        'mandatory' => false,
        'condition' => $this->checkPhpExtensionVersion('intl', '1.0.2', '>='),
        'by' => '<a href="http://www.yiiframework.com">Yii Framework</a>',
        'memo' => 'PHP intl extension 1.0.2 or heigher is required when you want to use advanced parameters formatting in Yii::t(), non-latin languages with Inflector::slug()',
    ),
    // ICU 语言包时使用
    array(
        'name' => 'ICU versioin',
        'mandatory' => false,
        'condition' => defined('INTL_ICU_VERSION') && version_compare(INTL_ICU_VERSION, '49', '>='), 
        'by' => '<a href="http://www.yiiframework.com">Yii Framework</a>',
        'memo' => 'ICU 49.0 or heigher version is required when you want to use placeholder in plural rules ',
    ),
    // 语言包时使用的 ICU Data 
    array(
        'name' => 'ICU Data version',
        'mandatory' => false,
        'condition' => defined('INTL_ICU_DATA_VERSION') && version_compare(INTL_ICU_DATA_VERSION, '49.1', '>='),
        'by' => '<a href="http://www.yiiframework.com">Yii Framework</a>',
        'memo' => 'Formatter::asRelativeTime() in yii\i18n\Formatter class',
    ),
    array(
        'name' => 'FileInfo extension',
        'mandatory' => false,
        'condition' => extension_loaded('fileinfo'),
        'by' => '<a href="http://www.yiiframework.com">Yii Framework</a>',
        'memo' => 'Required for files upload to detect correct file mine-type',
    ),
    // 加载dom拓展，为restful API 使用
    array(
        'name' => 'DOM extension',
        'mandatory' => false,
        'condition' => extension_loaded('dom'),
        'by' => '<a href="http://www.yiiframework.com">Yii Framework</a>',
        'memo' => 'Required for REST API to send XML response via yii\XmlResponseFormatter',
    ),

);
