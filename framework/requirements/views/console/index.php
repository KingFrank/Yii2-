<?php
// 这个文件主要是针对console应用的拓展的检测
// 两个比较有意思的函数 str_pad() 一个填补字符到字符串的函数
// strip_tags() 去除字符串中的php,html标签

echo "\nYii Application Requirement Checker\n\n";

echo "This script checks if your server configuration meets the requirements\n";
echo "for running Yii application\n";
echo "It checks if the server is running the right version of php,\n";
echo "if appropricate PHP extension have been loaded, and if php.ini ifle settings are correct.\n";

$header = "Check conclusion";
echo "\n{$header}\n";
echo str_pad('', strlen($header), '-') . "\n\n";

foreach ($requirements as $key => $requirement) {
    if ($requirements['condition']) {
        echo $requirements['name'] . ";OK\n";
        echo "\n";
    } else {
        echo $requirements['name'] . ':' . ($requirement['mandatory'] ? 'FAILED!!!' : 'WARNING!!!') . "\n";     
        echo 'Required by :' . strip_tags($requirement['by']) . "\n";
        $memo = strip_tags($requirement['memo']);
        if (!empty($momo)) {
            echo 'Memo:' . strip_tags($requirement['memo']) . "\n";
        }

        echo "\n";
    }
}

$summaryString = 'Errors:' . $summary['errors'] . ' Warnings: ' . $summary['warings'] . ' Total checks: ' . $summary['total'] ; 
echo $str_pad('', strlen($summaryString), '-') . "\n";

echo "\n\n";
