<?php

namespace ClevePHP\Extension\mysql;

class Config {
	private static $instance;
	function __construct() {
	}
	static public function getInstance() {
		if (! self::$instance instanceof self) {
			self::$instance = new self ();
		}
		return self::$instance;
	}
	public $host = "localhost";
	public $port = "3306";
	public $user = "";
	public $password = "";
	public $dbname;
	public $timeout = 5;
	public $charset = "";
	public $connection_num = 4;
	public $prefix;
	public $tag = "default";
	public $autoReconnect = true;
	public $miniConnect = 1;
	public $maxConnect = 1;
	public $isCoroutine = false;
	public $fetchMode = true;
	public $version=0;
	public $pdoModel=1;
	public $dbProxy="ks";
	public function loadConfig($config = []) {
		if ($config) {
			$this->host = isset ( $config ["host"] ) ? $config ["host"] : $this->host;
			$this->user = isset ( $config ["user"] ) ? $config ["user"] : $this->user;
			$this->port = isset ( $config ["port"] ) ? $config ["port"] : $this->port;
			$this->password = isset ( $config ["password"] ) ? $config ["password"] : null;
			$this->dbname = isset ( $config ["dbname"] ) ? $config ["dbname"] : $this->dbname;
			$this->timeout = isset ( $config ["timeout"] ) ? $config ["timeout"] : $this->timeout;
			$this->charset = isset ( $config ["charset"] ) ? $config ["charset"] : $this->charset;
			$this->tag = isset ( $config ["tag"] ) ? $config ["tag"] : $this->tag;
			$this->connection_num = isset ( $config ["connection_num"] ) ? $config ["connection_num"] : $this->connection_num;
			$this->prefix = isset ( $config ["prefix"] ) ? $config ["prefix"] : $this->prefix;
			$this->autoReconnect = isset ( $config ["auto_reconnect"] ) ? $config ["auto_reconnect"] : $this->autoReconnect;
			$this->miniConnect = isset ( $config ["mini_connect"] ) ? $config ["mini_connect"] : $this->miniConnect;
			$this->maxConnect = isset ( $config ["connection_num"] ) ? $config ["connection_num"] : $this->maxConnect;
			$this->isCoroutine = isset ( $config ["is_coroutine"] ) ? $config ["is_coroutine"] : $this->isCoroutine;
			$this->fetchMode = isset ( $config ["fetch_mode"] ) ? $config ["fetch_mode"] : $this->fetchMode;
			$this->version = isset ( $config ["version"] ) ? $config ["version"] : $this->version;
			$this->pdoModel = isset ( $config ["pdo_model"] ) ? $config ["pdo_model"] : $this->pdoModel;
			$this->dbProxy = isset ( $config ["db_proxy"] ) ? $config ["db_proxy"] : $this->dbProxy;
			
		}
		return $this;
	}
}
