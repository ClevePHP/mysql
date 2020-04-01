<?php
namespace ClevePHP\Extension\mysql;

class PoolManage
{

    private static $instance;

    private $configs = [];

    function __construct($configs)
    {
        $this->configs = $configs;
        $this->_init();
    }

    private function __clone()
    {}

    private $pools = [];

    private $configVersion = [];

    static public function getInstance(array $configs)
    {
        if (! self::$instance instanceof self) {
            self::$instance = new self($configs);
        }
        return self::$instance;
    }

    private function hartbeate()
    {
        \Swoole\Timer::tick(1000, function () {
            $this->clears();
        });
    }

    // 切换配置
    public function setConfig($configs, $auto = true)
    {
        foreach ($configs as $tag => $config) {
            $conf=(Array)$config;
            if ($auto) {
                $version = $this->configVersion[$tag];
                $configVerson=md5(json_encode($conf));
                $this->configVersion[$tag] = $configVerson;
                // 有变化再更新
                if ($version && ($configVerson != $version)) {
                    $this->configs[$tag] = $config;
                    $this->pools[$tag] = new Mysqlpool($config);
                }
            } else {
                $this->configs[$tag] = $config;
                $this->pools[$tag] = new Mysqlpool($config);
            }
        }
    }

    private function clears()
    {
        if ($this->configs) {
            $configs=(Array)$this->configs;
            foreach ($this->configs as $k => $v) {
                $v->clears();
                $varsion=md5(json_encode($config));
                if (($this->configVersion[$k] != $varsion)) {
                    $this->configVersion[$k] = $varsion;
                    $this->pools[$k] = new Mysqlpool($v);
                }
            }
        }
    }

    public function getPool($tag)
    {
        if ($this->pools) {
            $pool = $this->pools[$tag] ?? $this->_init();
            $connect = $pool->getConnect();
            if ($connect){
                return $connect;
            }else{
                $this->_init();
                $this->getPool($tag);
            }
        }
    }

    private function _init()
    {
        if ($this->configs) {
            foreach ($this->configs as $key => $config) {
                $this->pools[$key] = new Mysqlpool($config);
                $this->configVersion[$key] = md5(json_encode($config));
            }
        }
        if ($this->pools) {
            foreach ($this->pools as $k => $pool) {
                \Swoole\Timer::tick(1000, function () use ($pool) {
                    $pool->clears();
                });
            }
        }
    }
}
