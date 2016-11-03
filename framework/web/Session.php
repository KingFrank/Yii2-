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

    private $_cookieParams = ['httpOnly' => true];

    public function init()
    {
        parent::init();
        register_shutdown_function($this, 'close');
        if ($this->getIsActive()) {
            Yii::warning("Session is already started", __METHOD__);
            $this->updateFlashCounters();
        }
    }

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

    public function close()
    {
        if ($this->getIsActive()) {
            @session_write_close();
        }
    }

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

}
