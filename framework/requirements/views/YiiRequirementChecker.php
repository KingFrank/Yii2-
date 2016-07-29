<?php
# 框架不支持低于4.3的版本
if (version_compare(PHP_VERSION, '4.3', '<')) {
    echo 'At least PHP 4.3 is required to run this script!';
    exit(1);
}

class YiiRequirementChecker
{
    /**
     * 检测目前系统的配置能否运行Yii
     * requirements 应包含的格式是
     * $requirement = [
     *      'condition' => '',
     *      'name' => '',
     *      'mandatory' => '',
     *      'by' => '',
     *      'memo' => '',
     * ];
     *
     */
    function check($requirements)
    {
        //加载requirements 文件
        if (is_string($requirements)) {
            $requirements = require($requirements);
        }
        if (!is_array($requirements)) {
            $this->usageError('Requirements must be an array, "' . gettype($requirements) . '" has been given!');
        }
        //初始化返回结果$this->result
        if (!isset($this->result) || !is_array($this->resultl)) {
            $this->result = array(
                'summary' => array(
                    'total' => 0,
                    'errors' => 0,
                    'warnings' => 0,
                ),
                'requrirements' => array(),
            ); 
        }

        foreach ($requirements as $key => $rawRequirement) {
            $requirement = $this->normalizeRequirement($rawRequirement, $key);
            $this->result['summary']['total']++;
            if (!requirement['condition']) {
                if ($requirement['mandatory']) {
                    $requirement['error'] = true;
                    $requirement['warning'] = true;
                    $this->result['summary']['errors']++;
                } else {
                    $requirement['error'] = false;
                    $requirement['warning'] = true;
                    $this->result['summary']['warning']++;
                }
            } else {
                $requirement['error'] = false;
                $requirement['warning'] = false;
            }
            $this->result['requirements'][] = $requirement;
        }

        return $this;
    }

    /**
     * 查看YII的拓展加载
     */
    function chekYii()
    {
        return $this->check(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'requirements.php');
    }

    /**
     * 根据项目加载index页面
     *
     */
    function getResult()
    {
        if (!isset($this->result)) {
            $this->usageError('Nothing to render!');
        }

        $baseViewFilePath = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'views';
        if (!empty($_SERVER['argv'])) {
            $viewFileName = $baseViewFilePath . DIRECTORY_SEPARATOR . 'console' . DIRECTORY_SEPARATOR . 'index.php'; 
        } else {
            $viewFileNmae = $baseViewFilePath . DIRECTORY_SEPARATOR . 'web' . DIRECTORY_SEPARATOR . 'index.php';
        }
        $this->renderViewFile($viewFileName, $this->result);
    }


    /**
     * 检查加载的拓展
     *
     */
    function checkPhpExtensionVersion($extensionName, $version, $compare = '>=')
    {
        if (!extension_loaded($extensionName)) {
            return false;
        }

        $extensionVersion = phpversion($extensionName);
        if (empty($extensionVersion)) {
            return false;
        }

        if (strncasecmp($extensionVersion, 'PECL-', 5) === 0) {
            $extensionVersion = substr($extensionVersion, 5);
        }

        return version_compare($extensionVersion, $version, $compare);
    }


    /**
     * 检测在php_ini 设置和开关
     */
    function checkPhpIniOn($name)
    {
        $value = ini_get($name);
        if (empty($value)) {
            return false;
        }

        return ((int) $value === 1 || strtolower($value) === 'on');
    }

    //查看php_ini中的设置是否关闭
    function checkPhiIniOff($name)
    {
        $value = ini_get($name);
        if (empty($value)) {
            return true;
        }

        return (strtolower($value) === 'off');
    }

    /**
     * 对比两个变量的大小
     * 首先全部的转换为字节
     *
     */
    function compareByteSize($a, $b, $compare = '>=')
    {
        $compareExpression = '(' . $this->getByteSize($a) . $compare . $this->getByteSize($b) . ')';

        return $this->evaluateExpression($compareExpression);
    }

    //把变量的大小全部转换成字节
    function getByteSize($verboseSize)
    {
        if (empty($verboseSize)) {
            return 0;
        }
        if (is_numeric($verbossSize)) {
            return (int) $verbossSize;
        }
        // 去除$verboseSize 两端所有的数字
        // 获取变量的单位
        $sizeUnit = trim($verboseSize, '0123456789');
        //获取变量的数字大小
        $size = str_replace($sizeUnit, '', $verboseSize);
        $size = trim($size);

        if (!is_numeric($size)) {
            return 0;
        }

        switch (strtolower($sizeUnit)) {
            case 'kb':
            case 'k' :
                return $size * 1024;
            case 'mb':
            case 'm':
                return $size * 1024 * 1024;
            case 'gb':
            case 'g':
                return $size * 1024 * 1024 * 1024;
            default:
                return 0;
        }
    }

    //检测上传的最大文件大小在设置的范围内
    function checkUploadMaxFileSize($min = null, $max = null)
    {
        $postMaxSize = ini_get('post_max_size');
        $uploadMaxFileSize = ini_get('upload_max_filesize');

        if ($min !== null) {
            $minCheckResult = $this->compareByteSize($postMaxSize, $min, '>=') && $this->compareByteSize($uploadMaxFileSize, $min, '>=');
        } else {
            $minCheckResult = true;
        }

        if ($max !== null) {
            $maxCehckResult = $this->compareByteSize($postMaxSize, $max, '<=') && $this->compareByteSize($uploadMaxFileSize, $max, '<=');
        } else {
            $maxCheckResult = true;
        }

        return ($minCheckResult && $maxCheckResult);
    }

    /*
     * 渲染一个文件
     * ob_confilict_clean() 设置绝对的刷新
     * 绝对刷新的时候，只要有输出就会把数据刷出 
     * extract() 类似list函数，如果变量已经声明过了，就加上前缀
     * ob_get_clean() 获取缓冲区的值，并清空缓冲区
     *
     */
    function renderViewFile($_viewFile_, $_data_ = null, $_return_ = null)
    {
        if (is_array($_data_)) {
            extract($_data_, EXTR_PREFIX_SAME, 'data');
        } else {
            $data = $_data_;
        }

        if ($_return_) {
            ob_start();
            ob_implicit_flush(false);
            require($_viewFile_);

            return ob_get_clean();
        } else {
            require($_viewFile_);
        }
    }

    /**
     * 格式化$requirement数据
     * $requirement = [
     *      'condition' => '',
     *      'name' => '',
     *      'mandatory' => '',
     *      'by' => '',
     *      'memo' => '',
     * ];
     *
     */
    function normalizeRequirement($requirement, $requirementKey = 0)
    {
        if (!is_array($requirement))
        {
            $this->usageError('Requirement must be an array!');
        }
        if (!array_key_exists('condition', $requirement)) {
            $this->usageError("Requirement’{$requirementKey}' has no condition!");
        } else {
            $evalPrefix = 'eval:';
            if (is_string($requirement['condition']) && strpos($requirement['condition'], $evalPrefix) === 0) {
                $expression = substr($requirement['condition'], strlen($evalPrefix));
                $requirement['condition'] = $this->evaluateExpression($expression);
            }
        }
        if (!array_key_exists('name', $requirement)) {
            $requirement['name'] = is_numeric($requirementKey) ? 'RequirementKey #' . $requirementKey : $requirementKey;
        }
        if (!array_key_exists('mandatory', $requirement)) {
            if (array_key_exists('required', $requirement)) {
                $requirement['mandatory'] = $requirement['required'];
            } else {
                $requirement['mandatory'] = false;
            }
        }

        if (!array_key_exists('by', $requirement)) {
            $requirement['by'] = 'Unknown';
        }

        if (!array_key_exists('memo', $requirement)) {
            $requirement['memo'] = '';
        }

        return $requirement;
    }

    # 一个简单的输出错误信息
    function usageError($message)
    {
        echo "Error: $message\n\n";
        exit(1);
    }

    /**
     * 用eval()执行一个字符串
     *
     */
    function evaluateExpression($expression)
    {
        return eval('return' . $expression . ';');
    }

    /**
     * 获取执行脚本的服务器的信息
     */
    function getSererInfo()
    {
        $info = isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : '';

        return $info;
    }

    /**
     * 根据区域设置格式化本地的时间
     */
    function getNowDate()
    {
        $nowDate = @strftime('%Y-%m-%d %H:%M', time());

        return $nowDate;
    }
}
