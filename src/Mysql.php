<?php

/** 
 * 文件名(Mysql.php) 
 * 
 * 功能描述（略） 
 * 
 * @author steve <ceiba_@126.com> 
 * @version 1.0 
 * @package sample2 
 */
namespace ClevePHP\Extension\mysql;

class Mysql
{

    private static $instance;

    private function __construct($config = null)
    {
        if ($config) {
            $this->_init($config);
        }
    }

    private function __clone()
    {}

    private $request = null;

    private $driveName;

    private $drive;

    private $config;

    static public function getInstance(\ClevePHP\Extension\mysql\Config $config = null)
    {
        if (! self::$instance instanceof self) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    public function setConfig(\ClevePHP\Extension\mysql\Config $config)
    {
        $this->config = $config;
        $this->_init($config);
        return $this;
    }

    public function getConfig(): ?\ClevePHP\Extension\mysql\Config
    
    {
        return $this->config;
    }

    private function _init(\ClevePHP\Extension\mysql\Config $config = null)
    {
        if ($this->config->isCoroutine) {
            $this->_swooleMysql($config);
        } else {
            $this->_mysqli($config);
        }
    }

    private function _swooleMysql(\ClevePHP\Extension\mysql\Config $config = null)
    {
        $this->config = $config;
        $config = (array) $config;
        $client = new \Swoole\Coroutine\MySQL();
        $client->connect([
            'host' => $this->getConfig()->host,
            'port' => $this->getConfig()->port,
            'user' => $this->getConfig()->user,
            'password' => $this->getConfig()->password,
            'database' => $this->getConfig()->dbname,
            "fetch_mode" => $this->getConfig()->fetchMode,
            'charset' => $this->getConfig()->charset ?? "utf8mb4"
        ]);
        $this->drive = $client;
    }

    private function _mysqli(\ClevePHP\Extension\mysql\Config $config = null)
    {
        $this->config = $config;
        $pro = [
            'host' => $this->getConfig()->host,
            'username' => $this->getConfig()->user,
            'password' => $this->getConfig()->password,
            'db' => $this->getConfig()->dbname,
            'port' => $this->getConfig()->port
        ];
        $params = array_values($pro);
        $charset = $this->getConfig()->charset;
        if (empty($pro['host']) && empty($pro['socket'])) {
            throw new \Exception('MySQL host or socket is not set');
        }
        
        $mysqlic = new \ReflectionClass('mysqli');
        $mysqli = $mysqlic->newInstanceArgs($params);
        
        if ($mysqli->connect_error || ! $mysqli) {
            throw new \Exception('Connect Error ' . $mysqli->connect_errno . ': ' . $mysqli->connect_error, $mysqli->connect_errno);
        }
        
        if (! empty($charset)) {
            $mysqli->set_charset($charset);
        }
        $this->drive = $mysqli;
        return $mysqli;
    }

    public function switchConfig(\ClevePHP\Extension\mysql\Config $config)
    {
        $this->_init($config);
        return $this;
    }

    //关闭
    public function close(){
        mysqli_close($this->drive);  
    }
    
    public function getDrive()
    {
        if ($this->config->autoReconnect === true && ($this->drive->errno === 2006 || $this->drive->errno === 2013)) {
            $this->setConfig($this->config);
            return $this->getDrive();
        }
        return $this->drive;
    }
}