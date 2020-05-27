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

class Pdopool
{

    private static $instance;

    function __construct()
    {
        $this->connectObjects = new co\Channel();
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
        if (! $this->connectObjects->isEmpty()) {
            return $this;
        }
        $this->config = $config;
        $this->connectObjects = new co\Channel($this->config->maxConnect * 3);
        $db = null;
        if ($this->config->maxConnect > 2) {
            for ($i = 0; $i <= $config->maxConnect; $i ++) {
                $db = $this->dbObject($config);
                if ($db) {
                    $this->connectObjects->push($db);
                }
            }
        } else {
            $db = $this->dbObject($config);
        }
        
        if ($db) {
            $this->connectObjects->push($db);
        }
        return $this;
    }

    private function dbObject($config)
    {
        $db = \ClevePHP\Extension\mysql\Pdo::getInstance()->setConfig($config)->getDrive();
        return $db;
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
        if ($this->connectObjects->isEmpty()) {
            return null;
        }
        $mysql = $this->connectObjects->pop();
        if ($mysql) {
            $this->connectObjects->push($mysql);
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
                throw new DbException("pool error:pdo conenct failure");
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
                try {
                    $db->getAttribute(\PDO::ATTR_SERVER_INFO);
                } catch (\PDOException $e) {
                    if (strpos($db->getMessage(), 'gone away') !== false) {
                        $db = null;
                    }
                }
                if ($db) {
                    $this->connectObjects->push($db);
                }
            }
        }
    }
}

