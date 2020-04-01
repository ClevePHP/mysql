<?php

/**
 * 文件名(Mysqlpool.php)
 *
 * 功能描述（略）
 * mysql连接池实现
 * @author steve <ceiba_@126.com>
 * @version 1.0
 * @package sample2
 */
namespace ClevePHP\Extension\mysql;

use think\db\exception\DbException;
use Swoole\Coroutine as co;
use Core\Util\Logger;

class Mysqlpools
{

    private static $instance;

    function __construct($config = null)
    {
        $this->switchConfig($config); 
        return $this;
    }

    private function __clone()
    {}

    private $connectObjects;

    private $config;

    static public function getInstance()
    {
        if (! self::$instance instanceof self) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function connect(\ClevePHP\Extension\mysql\Config $config)
    {
        $this->config = $config;
        if (empty($config)) {
            throw new \Exception("mysql config is null");
        }
        $this->config = $config;
        $this->config->maxConnect = 1;
        $this->connectObjects = new co\Channel($this->config->maxConnect + 1);
        for ($i = 0; $i <= $config->maxConnect; $i ++) {
            $db = $this->dbObject($config);
            if ($db) {
                $this->connectObjects->push($db);
            }
        }
        return $this;
    }

    private function dbObject($config)
    {
        $db = \ClevePHP\Extension\mysql\Mysql::getInstance()->setConfig($config)->getDrive();
        if (($db->errno != 2006 && $db->errno != 2013)) {
            return $db;
        }
    }

    public function switchConfig(\ClevePHP\Extension\mysql\Config $config)
    {
        $this->connect($config);
        return $this;
    }

    public function closes()
    {
        if ($this->connectObjects->isEmpty()) {
            return false;
        }
        for ($i = 0; $i < $this->connectObjects->length(); $i ++) {
            $db = $this->connectObjects->pop();
            $db->close();
        }
    }

    public function getConnect()
    {
        if (! $this->connectObjects) {
            return null;
        }
        $avilConnectionNum = $this->connectObjects->length();
        if ($avilConnectionNum == 0 || $avilConnectionNum < $this->config->miniConnect) {
            $obj = $this->resetCennect();
            $this->connectObjects->push($obj);
            return $obj;
        }
        $mysql = null;
        $mysql = $this->connectObjects->pop();
        if (! $mysql) {
            $obj = $this->resetCennect();
            $this->connectObjects->push($obj);
            return $obj;
        }
        if ($mysql && ($mysql->errno != 2006 && $mysql->errno != 2013)) {
            $this->connectObjects->push($mysql);
        }
        if (($mysql->errno === 2006 || $mysql->errno === 2013) && ($this->config->autoReconnect === true)) {
            $obj = $this->resetCennect();
            $this->connectObjects->push($obj);
            return $obj;
        }
        return $mysql;
    }

    // 重新连接
    private function resetCennect()
    {
        try {
            if ($this->config) {
                $connect = $this->dbObject($this->config);
                if ($connect) {
                    return $connect;
                }
                throw new DbException("pool error:mysql conenct failure");
            }
            throw new DbException("pool error:config is emtpy");
        } catch (\Throwable $e) {
            $this->clears();
        }
    }

    // 清理无效的连接
    public function clears()
    {
        if ($this->connectObjects) {
            for ($i = 0; $i < $this->connectObjects->length(); $i ++) {
                $db = $this->connectObjects->pop();
                if (! $db->ping() || ! mysqli_ping($db) || $db->errno || mysqli_errno($db) || mysqli_connect_errno($db)) {
                   echo "清除无效连接".PHP_EOL;
                    Logger::echo("mysql to close");
                    mysqli_close($db);
                    $db = $this->resetCennect();
                } 
                $this->connectObjects->push($db);
            }
        }
    }
}
