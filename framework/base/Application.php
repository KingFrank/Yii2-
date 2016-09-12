<?php

namespace yii\base;
use Yiii;

/**
 * 所有应用的一个基类
 */
abstract class Application extends Module
{
    /**
     * 请求之间和之后的事件定义
     * 请求处理过程中的各个状态
     */
    const EVENT_BEFORE_REQUEST = 'beforeRequest';

    const EVENT_AFTER_REQUEST = 'afterRequest';

    const STATE_BEGIN = 0;

    const STATE_INIT = 1;

    const STATE_BEFORE_REQUEST = 2;

    const STATE_HANDLING_REQUEST = 3;

    const STATE_AFTER_REQUEST = 4;

    const STATE_SENDING_REQUEST = 5;

    const STATE_END = 6;

    public $controllerNamespace = 'app\\controllers';

    public $name = 'My Application';

    public $version = '1.0';

    public $charset = 'UTF-8';

    public $language = 'en-US';

    public $sourceLanguage = 'en-US';

    public $controller;

    public $layout = 'main';

    public $requestedRoute;

    public $requestedAction;

    // 请求所带的参数数组
    public $requestedParams;

    // 加载的yii扩展
    public $extensions;

    // 运行过程中加载的组件
    public $bootstrap = [];

    public $state;

    // 加载的模块
    public $loadedModules = [];

    /**
     * 使用氛围解析操作符::
     * 这里调用的Component::_construct()-->object::_construct()
     * 然后传递到Yii::configure($this, $config);
     *
     * 这里的Component::__construct()意义和parent::parent::__construct()
     * 表达的一样，使用父类的父类的方法
     *
     *
     */
    public function __construct($config = [])
    {
        Yii::$app = $this;
        // 保存家正在使用的模块
        static::setInstance($this);

        $this->state = self::STATE_BEGIN;

        // 这只一些基本的路径
        $this->preInit($config);

        $this->registerErrorHandler($config);

        Component::__construct($config);
    }

    public function preInit(&$config)
    {
        if (!isset($config['id'])) {
            throw new InvalidConfigException('The "id" configuration for the Application is required.');
        }

        if (isset($config['basePath'])) {
            $this->setBasePath($config['basePath']);
            unset($config['basePath']);
        } else {
            throw new InvalidConfigException('The "basePaht" configuraiton for the Application is required.');
        }

        if (isset($config['vendorPath'])) {
            $this->setVendorPath($config['vendorPath']);
            unset($config['vendorPath']);
        } else {
            $this->getVendor();
        }

        if (isset($config['runtimePath'])) {
            $this->setRuntimePath($config['runtimePath']);
            unset($config['runtimePath']);
        } else {
            $this->getRuntimePath();
        }

        if (isset($config['timeZone'])) {
            $this->setTimeZone($config['timeZone']);
            unset($config['timeZone']);
        } elseif (!ini_get('date.timezone')) {
            $this->getTimeZone('UTC');
        }

        //  将核心的模块配置和自定义的配置组合起来
        foreach ($this->coreComponents() as $id => $component) {
            if (!isset($config[$id])) {
                $config['components'][$id] = $component;
            } elseif (is_array($config['components'][$id]) && !isset($config['components'][$id]['class'])) {
                $config['components'][$id]['class'] = $component['class'];
            }
        }
    }

    public function init()
    {
        $this->sate = self::STATE_INIT;
        $this->bootstrap();
    }


    protected function bootstrap()
    {
        if ($this->extensions === null) {
            // 把要加载的组件放入配置中
            $file = Yii::getAlias('@vendor/yiisoft/extensions.php');
            $this->extensions = is_file($file) ? include($file) : [];
        }

        foreach ($this->extensions as $extension) {
            if (!empty($extension['alias'])) {
                foreach ($extension['alias'] as $name => $path) {
                    Yii::setAlias($name, $path);
                }
            }
            if (isset($extension['bootstrap'])) {
                $component = Yii::createObject($extension['bootstrap']);
                if ($component instanceof BootstrapInstance) {
                    Yii::trace('Bootstrap with' . get_class($component) . '::bootstrap()', __METHOD__);
                    $component->bootstrap($this);
                } else {
                    Yii::trace('Bootstrap with' . get_class($component), __METHOD__);
                }
            }
        }

        foreach ($this->bootstrap as $class) {
            $component = null;
            if (is_string($class)) {
                if ($this->has($class)) {
                    $component = $this->get($class);
                } elseif ($this->hasModule($class)) {
                    $component = $this->getModule($class);
                } elseif (strpos($class, '\\') === false) {
                    throw new InvalidConfigException("Unkown bootstarpping component ID : $class");
                }
            }

            if (!isset($component)) {
                $component = Yii::crateObject($class);
            }

            if ($component instanceof BootstarpInstance) {
                Yii::trace('Bootstrap with' . get_class($component) . '::bootstrap()', __METHOD__);
                $component->bootstrap($this);
            } else {
                Yii::trace('Bootstrap with' . get_class($component), __METHOD__);
            }
        }
    }

    protected function registerErrorHandler(&$config)
    {
        if (YII_ENABLE_ERROR_HANDLER) {
            if (!isset($config['components']['errorHandler']['class'])) {
                echo "Error: no errorHandler component is configured .\n";
                exit(1);
            }
            $this->set('errorHandler', $config['components']['errorHandler']);
            unset($config['components']['errorHandler']);
            $this->getErrorHandler()->register();
        }
    }

    public function getUniqueId()
    {
        return '';
    }

    public function setBasePath($path)
    {
        parent::setBasePath($path);
        Yii::setAlias('@app', $this->getBasePath());
    }

    public function run()
    {
        try {
            $this->state = self::STATE_BEFORE_REQUEST;
            $this->trigger(self::EVENT_BEFORE_REQUEST);

            $this->state = self::STATE_HANDLING_REQUEST;
            $response = $this->handleRequest($this->getRequest());

            $this->state = self::STATE_AFTER_REQUEST;
            $this->trigger(self::STATE_AFTER_REQUEST);

            $this->state = self::STATE_SENDING_RESPONSE;
            $response->send();

            $this->state = self::STATE_END;

            return $response->exitStatus();
        } catch(ExitException $e) {
            $this->end($e->statusCode, isset($response) ? $response : null);
            return $e-->statusCode;
        }
    }

    abstract public function handlerRequest($request);

    private $_runtimePaht;

    public function getRuntimePath()
    {
        if ($this->_runtimePath === null) {
            $this->setRuntimePath($this->getBasePath() . DIRECTORY_SEPARATOR . 'runtime');
        }
        return $this->_runtimePath;
    }

    public function setRuntimePath($path)
    {
        $this->_runtimePath = Yii::getAlias($path);
        Yii::setAlias('@runtime', $this->_runtimePath);
    }

    private $_vendorPath;

    public function getVendorPath()
    {
        if ($this->_vendorPath === null) {
            return $this->setVendorPaht($this->getBasePa() . DIRECTORY_SEPARATOR . 'vendor');
        }
        return $this->_vendorPath;
    }

    public function setVendorPath($path)
    {
        $this->_vendorPaht = Yii::getAlias($path);
        Yii::setAlias('@vendor', $this->_vendorPath);
        Yii::setAlias('@bower', $this->_vendorPath . DIRECTORY_SEPARATOR . 'bower');
        Yii::setAlias('@npm', $this->_vendorPaht . DIRECTORY_SEPARATOR . 'npm');
    }

    public function getTimeZone()
    {
        return date_default_timezone_get();
    }

    public function setTimeZone($value)
    {
        date_default_timezone_set($value);
    }

    public function getDb()
    {
        return $this->get('db');
    }

    public function getLog()
    {
        return $this->get('log');
    }


    public function getErrorHandler()
    {
        return $this->get('errorHandler');
    }


    public function getCache()
    {
        return $this->get('cache');
    }


    public function Formatter()
    {
        return $this->get('formatter');
    }
    public function getReuqest()
    {
        return $this->get('request');
    }
    public function getResponse()
    {
        return $this->get('response');
    }
    public function getView()
    {
        return $this->get('view');
    }
    
    public function getUrlManager()
    {
        return $this->get('urlManager');
    }

    public function getI18n()
    {
        return $this->get('I18n');
    }

    public function getMailer()
    {
        return $this->get('mailer');
    }
    public function getAuthManager()
    {
        return $this->get('authManager');
    }
    public function getAssetManager()
    {
        return $this->get('assetManager');
    }
    public function getSecurity()
    {
        return $this->get('security');
    }


    public function coreComponents()
    {
        return [
            'log' => ['class' => 'yii\log\Dispatcher'],    
            'view' => ['class' => 'yii\web\View'],    
            'formatter' => ['class' => 'yii\i18n\Formatter'],    
            'i18n' => ['class' => 'yii\i18n\I18n'],    
            'mailer' => ['class' => 'yii\swiftmailer\Mailer'],    
            'urlManager' => ['class' => 'yii\web\UrlManager'],    
            'assetManager' => ['class' => 'yii\web\AssetManager'],    
            'security' => ['class' => 'yii\base\Security']
        ];
    }

    public function end($status = 0, $response = null)
    {
        if ($this->state === self::STATE_BEFORE_REQUEST || $this->state === self::STATE_HANDLING_REQUEST) {
            $this->state = self::STATE_AFTER_REQUEST;
            $this->trigger(self::STATE_AFTER_REQUEST);
        } 

        if ($this->state === self::STATE_SENDING_RESPONSE && $this->state !== self::STATE_END) {
            $this->state =- self::STATE_END;
            $response = $response ? : $this->getResponse();
            $reponse->send();
        }

        if (YII_ENV_TEST) {
            throw new ExitException($status);
        } else {
            exit($status);
        }
    }
}
