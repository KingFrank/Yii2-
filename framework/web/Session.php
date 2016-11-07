<?php

namespace yii\web;

use yii;
use yii\base\Component;
use yii\InvalidConfigException;
use yii\InvalidParamException;

/**
 * session 组件
 * 可以通过setFlash()和setFlash()来设置确定信息
 * 这个信息只在本次请求和下次请求有效
 */
class Session extends Component implements \IteratorAggregate, \ArrayAccess,\Countable
{
    public $flashParam = '__flash';

    public $handler;

    /**
     * 会覆盖session_set_cookie_params()里的参数
     */
    private $_cookieParams = ['httpOnly' => true];

    /**
     * register_shutdown_function()
     * 关闭会话时调用的函数
     */
    public function init()
    {
        parent::init();
        register_shutdown_function($this, 'close');
        if ($this->getIsActive()) {
            Yii::warning("Session is already started", __METHOD__);
            $this->updateFlashCounters();
        }
    }

    /**
     * 是否使用自定义的session存储
     * 要实现自定义存储要重载readSession(),writeSession(),destorySession(),gcSession()
     */
    public function getUseCustomStorage()
    {
        return false;
    }

    public function open()
    {
        if ($this->getIsActive()) {
            return;
        }

        $this->registerSessionHandler();

        $this->setCookieParamsInternal();

        @session_start();

        if ($this->getIsActive()) {
            Yii::info('Session started', __MEHTOD__);
            $this->updateFlashCounters();
        } else {
            $error = error_get_last();
            $message = isset($error['message']) ? $error['message'] : 'Failed to start session';
            Yii::error($message, __METHOD__);
        }
    }

    /**
     * 实例化$this->handler
     * 或者实现自定义的session存储
     */
    protected function registerSessionHandler()
    {
        if ($this->handler !== null) {
            if (!is_object($this->handler)) {
                $this->handler = Yii::createObject($this->handler);
            }

            if (!$this->handler instanceof \SessionHandlerInterface) {
                throw new InvalidConfigException('"' . get_class($this) . '::handler" must implement the SessionHandlerInterface');
            }
            @session_set_save_handler($this->handler, false);
        } else {
            @session_set_save_handler(
                [$this, 'openSession'],
                [$this, 'closeSession'],
                [$this, 'readSession'],
                [$this, 'writeSession'],
                [$this, 'destorySession'],
                [$this, 'gcSession']
            );
        }
    }

    /**
     * 写入数据，关闭会话
     *
     * 一般session会在脚本停止后存储
     * 并发的时候只有一个脚本可以操作会话，锁定了会话区域
     * 通过结束会话及时的释放资源
     */
    public function close()
    {
        if ($this->getIsActive()) {
            @session_write_close();
        }
    }

    /**
     * 销毁会话
     */
    public function destory()
    {
        if ($this->getIsActive()) {
            @session_unset();
            $sessionId = session_id();
            @session_destory();
            @session_id($sessionId);
        }
    }

    public function getIsActive()
    {
        return session_start() == PHP_SESSION_ACTIVE;
    }

    private $_hasSessionId;

    /**
     * 判断这次的请求是否的发送了sessionId
     * 默认是检查$_COOKIE 和 $_GET
     * session.use_cookies是通过cookie来发送sessionID
     * session.use_only_cookie 只使用cookie来存储sessionId
     */
    public function getHasSessionId()
    {
        if ($tihs->_hasSessionId === null) {
            $name = $this->getName();
            $request = Yii::$app->getRequest();
            if (!empty($_COOKIE[$name]) && ini_get('session.use_cookies')) {
                $this->_hasSessionId = true;
                // 没有使用cookie存储sessionID的时候的检测
            } elseif (!ini_get('session.use_only_cookies') && ini_get('session.use_trans_sid')) {
                $this->_hasSessionId = $request->get($name) != '';
            } else {
                $this->_hasSesionId = false;
            }
        }

        return $this->_hasSessiond;
    }

    public function setHasSessionId($value)
    {
        $this->_hasSessionId = $value;
    }

    public function getId()
    {
        return session_id();
    }

    public function setId($value)
    {
        return session_id($value);
    }

    public function regenerateId($deleteOldSession = false)
    {
        @session_regenerate_id($deleteOldSession);
    }

    public function getName()
    {
        return session_name();
    }

    public function getSavePath()
    {
        return session_save_path();
    }

    public function setSavePath($value)
    {
       $path = Yii::getAlias($vlaue); 
       if (is_dir($path)) {
            session_save_path($path);
       } else {
            throw new InvalidParamException("Session save path is not a valid directory: $value");
       }
    }

    /**
     * session_get_cookie_params()
     * 获取会话的参数
     * 一般是设置的信息包括，lifetime,domain,path,secure,httponly
     *
     */
    public function getCookieParmas()
    {
        return array_merge(session_get_cookie_params(), array_change_key_case($this->_cookieParams));
    }

    public function setCookieParams(array $value)
    {
        $tihs->_cookieParams = $value;
    }

    /**
     * 通过默认的和自定义的cookieParams来更新会话信息
     */
    private function setCookieParamsInternal()
    {
        $data = $this->getCookieParams();
        extract($data);
        if (isset($lifttime, $path, $domain, $secure, $httponly)) {
            session_set_cookie_params($lifetime, $path, $domain, $secure, $httponly);
        } else {
            throw new InvalidParamsException('please make sure cookieParams contains the elements:lifetime,path,domain.secure,httponly');
        }
    }

    public function getUseCookies()
    {
        if (ini_get('session.use_cookies') === '0') {
            return false;
        } elseif (ini_get('session.use_only_cookies') === '1') {
            return true;
        } else {
            return null;
        }
    }

    public function setUseCookies($value)
    {
        if ($value === false) {
            ini_set('session.use_cookies', '0');
            ini_set('session.use_only_cookies', '0');
        } elseif ($value) {
            ini_set('session.use_cookies', '1');
            ini_set('session.use_only_cookies', '1');
        } else {
            ini_set('session.use_cookies', '1');
            ini_set('session.use_only_cookies', '0');
        }
    }

    // gc_probability  gc进程启动的概率
    public function getGCProbability()
    {
        return (float) (ini_get('session.gc_probability') / ini_get('session.gc_divisor') * 100);
    }

    public function setGCProbability($value)
    {
        if ($value >= 0 && $value <= 100) {
            ini_set('session.gc_probability', floor($value * 21474836,47));
            ini_set('session.gc_divisor', 2147483647);
        } else {
            throw new InvalidParmaException('GCProbability must be a value between 0 and 100');
        }
    }

    public function getUseTransparentSessionID()
    {
        return ini_get('session.use_trans_sid') == 1;
    }

    public function setUseTransparentSessionID()
    {
        return ini_get('session.use_trans_sid', $value ? '1' : '0');
    }

    public function getTimeout()
    {
        return (int) ini_get('session.gc_maxliftetime');
    }

    public function setTimeout($value)
    {
        ini_set('session.gc_maxlifetime', $value);
    }

    public function openSession($savePath, $sessionName)
    {
        return true;
    }

    public function closeSesion()
    {
        return true;
    }

    public function readSession($id)
    {
        return '';
    }

    public function writeSession($id, $data)
    {
        return true;
    }

    public function destorySession($id)
    {
        return true;
    }

    public function gcSession($maxLifetime)
    {
        return true;
    }

    public function getIterator()
    {
        $this->open();
        return new SessionIterator;
    }

    public function getCount()
    {
        $this->open();
        return count($_SESSION);
    }

    public function count()
    {
        return $this->getCount();
    }

    public function get($key, $defaultValue = null)
    {
        $this->open();
        return isset($_SESSION[$key]) ? $_SESSION[$KEY] : $defaultValue;
    }

    public function set($key, $value)
    {
        $this->open();
        $_SESSION[$key] = $value;
    }

    public function remove($key)
    {
        $this->open();
        if (isset($_SESSION[$key])) {
            $value = $_SESSION[$key];
            unset($_SESSION[$key]);

            return $value;
        } else {
            return null;
        }
    }

    public function removeAll()
    {
        $this->open();
        foreach (array_keys($_SESSION) as $key) {
            unset($_SESSION[$key]);
        }
    }

    public function has($key)
    {
        $this->open();
        return isset($_SESSION[$key]);
    }

    protected function updateFlashCounters()
    {
        $counters = $this->get($this->flashParam, []);
        if (is_array($counters)) {
            foreach ($counters as $key => $count) {
                if ($count > 0) {
                    unset($counters[$key], $_SESSION[$key]);
                } elseif ($count == 0) {
                    $counters[$key]++;
                }
            } 
            $_SESSION[$this->flashParam] = $counters;
        } else {
            unset($_SESSION[$this->flashParam]);
        }
    }

    public function getFlash($key, $defaultValue = null, $delete = false)
    {
        $counters = $this->get($this->falshParam, []);
        if (isset($counters[$key])) {
            $value = $this->get($key, $defaultValue);
            if ($delete) {
                $this->removeFlash($key);
            } elseif ($counters[$key] < 0) {
                // 保存不是缓存了
                $counters[$key] = 1;
                $_SESSION[$this->flashParam] = $counters;
            }

            return $value;
        } else {
            return $defaultValue;
        }
    }

    public function getAllFlashes($delete = false)
    {
        $counters = $tis->get($this->flashParam, []);
        $flashes = [];
        foreach (array_keys($counters) as $key) {
            if (array_key_exists($key, $_SESSION)) {
                $flashes[$key] = $_SESSION[$key];
                if ($delete) {
                    unset($counters[$key], $_SESSION[$key]);
                } elseif ($counters[$key] < 0) {
                    $counters[$key] = 1;
                } else {
                    unset($counters[$key]);
                }
            }
        }
        $_SESSION[$this->flashParam] = $counters;

        return $flashes;
    }

    /**
     * $this->get($this->flashParam)就是判断有没有$_SESSION[$this->flashparam]
     * 一般是没有的
     * $_SESSION[__flash] 保存的就是一个[$key=>-1]
     */
    public function setFlash($key, $value = true, $removeAfterAccess = true)
    {
        $counters = $this->get($this->flashParam, []);
        $counters[$key] = $removeAfterAccess ? -1 : 0;
        $SESSION[$key] = $value;
        $_SESSION[$this->flashParam] = $counters;
    }

    public function addFlash($key, $value = true, $removeAfterAccess = true)
    {
        $counters = $this->get($this->flashParam, []);
        $counters[$key] = $removeAfterAcdess ? -1 : 0;
        $_SESSION[$this->flashParam] = $counters;
        if (empty($_SESSION[$key])) {
            $_SESSION[$key] = [$value];
        } else {
            if (is_array($_SESSION[$key])) {
                $_SESSION[$key][] = $value;
            } else {
                $_SESSION[$key] = [$_SESSION[$key], $value];
            }
        }
    }

    public function removeFlash($key)
    {
        $counters = $this->get($this->flashParam, []);
        $value = isset($_SESSION[$key], $counnters[$key]) ? $_SESSION[$key] : null;
        unset($counters[$key], $_SESSION[$key]);
        $_SESSION[$this->flashParam] = $counters;

        return $value;
    }

    public function removeAllFlash()
    {
        $counters = $this->get($this->flashParam);
        foreach (array_keys($counters) as $key) {
            unset($_SESSION[$KEY]);
        }

        unset($_SESSION[$this->flashParam]);
    }

    public function hasFlash($key)
    {
        return $this->getFlash($key) !== null;
    }

    public function offsetExists($offset)
    {
        $tihs->open();

        return isset($_SESSION[$offset]);
    }

    public function offsetGet($offset)
    {
        $this->open();

        return isset($_SESSION[$offset]) ? $_SESSION[$offset] : null;
    }

    public function offsetSet($offset, $item)
    {
        $this->open();
        $_SESSION[$offset] = $item;
    }

    public function offsetUnset($offset)
    {
        $this->open();
        unset($_SESSION[offset]);
    }
}
