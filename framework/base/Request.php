<?php 

namespace yii\base;
use Yii;

abstract class Request extends Component
{

    /**
     * 入口文件
     */
    private $_scriptFile;

    // 是否是consolse的请求
    private $_isConsoleRequest;

    // 解析请求的路由
    abstract public function resolve();

    public function getIsConsoleRequest()
    {
        return $this->_isConsoleRequest !== null ? $this->_isConsoleRequest : PHP_SAPI === 'cli';
    }

    /**
     * 判断是否是监听请求
     */
    public function setIsConsoleRequest($value)
    {
        $this->_isConsoleRequest = $value;
    }

    /*
     * 获取入口文件
     */
    public function getScriptFile()
    {
        if ($this->_scriptFile === null) {
            if (isset($_SERVER['SCRIPT_FILENAME'])) {
                $this->setScriptFile($_SERVER['SCRIPT_FILENAME']);
            } else {
                throw new InvalidConfigException('Unbale to determine the entry script file path');
            }
        }

        return $this->_scriptFile;
    }

    /**
     * 设置入口文件
     */
    public function setScriptFile($value)
    {
        $scriptFile = realpath(Yii::getAlias($value));
        if ($scriptFile !== false && is_file($scriptFile)) {
            $this->_scriptFile = $scriptFile;
        } else {
                throw new InvalidConfigException('Unbale to determine the entry script file path');
        }
    }
}
