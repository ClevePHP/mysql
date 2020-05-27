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

class Pdo
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
        return $this->_pdo($config);
    }

    private function _pdo(\ClevePHP\Extension\mysql\Config $config = null)
    {
        $this->config = $config;
        $charset = $this->getConfig()->charset;
        if (empty($this->getConfig()->host)) {
            throw new \Exception('pdo host is not set');
        }
        $dbms = 'mysql';
        $user = $this->getConfig()->user;
        $pwd = $this->getConfig()->password;
        $dbName = $this->getConfig()->dbname;
        $host = $this->getConfig()->host;
        $port = $this->getConfig()->port;
        // $charset = 'utf8';
        $dsn = "$dbms:host=$host;dbname=$dbName;port=$port";
        if ($charset) {
            $dsn .= ";charset=$charset";
        }
        $pdo = new \PDO($dsn, $user, $pwd);
        if ($config->isPrepares){
            $pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
            $pdo->setAttribute(\PDO::ATTR_STRINGIFY_FETCHES, false);
        }
        if ($config->debugMode) {
            $errMode = \PDO::ERRMODE_WARNING;
            if ($config->debugMode == 2) {
                $errMode = \PDO::ERRMODE_EXCEPTION;
            } elseif ($config->debugMode==3){
                $errMode = \PDO::ERRMODE_SILENT;
            }
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, $errMode);
        }
        $this->drive = $pdo;
        return $pdo;
    }

    public function switchConfig(\ClevePHP\Extension\mysql\Config $config)
    {
        $this->_init($config);
        return $this;
    }

    // 关闭
    public function close()
    {
        $this->drive = null;
    }

    public function getDrive()
    {
        return $this->drive;
    }
}