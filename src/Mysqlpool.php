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

class Mysqlpool
{

    private static $instance;

    private function __construct(\ClevePHP\Extension\mysql\Config $config, $tag = null)
    {
        $this->connect($config, $tag);
    }

    private function __clone()
    {}

    private $tag = "default";

    private $connectObjects = [];

    private $config;

    static public function getInstance(\ClevePHP\Extension\mysql\Config $config, $tag = null)
    {
        if (! self::$instance instanceof self) {
            self::$instance = new self($config, $tag);
        }
        return self::$instance;
    }

    private function connect(\ClevePHP\Extension\mysql\Config $config, $tag = null)
    {
        if ($tag) {
            $this->tag = $tag;
        }
        $this->config = $config;
        if (empty($config)) {
            throw new \Exception("mysql config is null");
        }
        $this->config = $config;
        if (empty($this->tag)) {
            $this->tag = "default";
        }
        for ($i = 0; $i < $config->maxConnect; $i ++) {
            $this->connectObjects[$this->tag][] = \ClevePHP\Extension\mysql\Mysql::getInstance()->setConfig($config)->getDrive();
        }
        return $this;
    }

    public function switchConfig(\ClevePHP\Extension\mysql\Config $config, $tag = null)
    {
        $this->connect($config, $tag);
        return $this;
    }

    public function setTag($tag)
    {
        $this->tag = $tag;
        return $this;
    }
    public function closes($tag)
    {
        if ($tag) {
            $this->tag = $tag;
        }
        if (empty($this->connectObjects[$this->tag])) {
            return false;
        }

        foreach ($this->connectObjects[$this->tag] as $key => $value) {
            mysqli_close($value);
            unset($this->connectObjects[$this->tag][$key]);
        }
    }

    public function getConnect($tag = null)
    {
        if ($tag) {
            $this->tag = $tag;
        }
        if (empty($this->connectObjects[$this->tag])) {
            return false;
        }
        $avilConnectionNum = count($this->connectObjects[$this->tag]);
        if ($avilConnectionNum == 0 || $avilConnectionNum <= $this->config->miniConnect) {
            $this->connect($this->config, $this->tag);
            return $this->getConnect();
        }
        $mysql = null;
        $mysql = array_pop($this->connectObjects[$this->tag]);
        if (! ($mysql->errno === 2006 )) {
            array_push($this->connectObjects[$this->tag], $mysql);
        }
        if (($mysql->errno === 2006 || $mysql->errno === 2013) && $this->config->autoReconnect === true) {
            $this->connect($this->config, $this->tag);
            return $this->getConnect();
        }
        return $mysql;
    }
}
