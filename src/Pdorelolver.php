<?php

/**
 * 文件名(Relolver.php)
 *
 * 功能描述（略）
 * --Pdo转换
 * @author steve <ceiba_@126.com>
 * @version 1.0
 * @package sample2
 */
namespace ClevePHP\Extension\mysql;

class Pdorelolver
{

    protected static $_instance;

    public $prefix = '';

    protected $_mysqli = array();

    protected $_query;

    protected $_lastQuery;

    protected $_queryOptions = array();

    protected $_join = array();

    protected $_where = array();

    protected $_joinAnd = array();

    protected $_having = array();

    protected $_orderBy = array();

    protected $_groupBy = array();

    protected $_tableLocks = array();

    protected $_tableLockMethod = "READ";

    protected $_bindParams = array(
        ''
    );

    public $count = 0;

    public $totalCount = 0;

    protected $_stmtError;

    protected $_stmtErrno;

    protected $isSubQuery = false;

    protected $_lastInsertId = null;

    protected $_updateColumns = null;

    public $returnType = 'array';

    protected $_nestJoin = false;

    private $_tableName = '';

    protected $_forUpdate = false;

    protected $_lockInShareMode = false;

    protected $_mapKey = null;

    protected $traceStartQ;

    protected $traceEnabled;

    protected $traceStripPrefix;

    public $trace = array();

    public $pageLimit = 20;

    public $totalPages = 0;

    protected $connectionsSettings = array();

    public $defConnectionName = 'default';

    public $autoReconnect = true;

    protected $autoReconnectCount = 0;

    protected $_transaction_in_progress = false;

    // smproxy 注解
    protected $annotation = "";

    protected $annotationType = "";

    function __construct($mysql = null, $prefix = null)
    {
        if ($mysql) {
            $this->_mysqli = $mysql;
            $this->prefix = $prefix;
        }
    }

    public static function getInstance()
    {
        if (! self::$_instance instanceof self) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function setSubQuery(bool $bool)
    {
        $this->isSubQuery = $bool;
    }

    protected function reset()
    {
        if ($this->traceEnabled) {
            $this->trace[] = array(
                $this->_lastQuery,
                (microtime(true) - $this->traceStartQ),
                $this->_traceGetCaller()
            );
        }
        
        $this->_where = array();
        $this->_having = array();
        $this->_join = array();
        $this->_joinAnd = array();
        $this->_orderBy = array();
        $this->_groupBy = array();
        $this->_bindParams = array(
            ''
        ); // Create the empty 0 index
        $this->_query = null;
        $this->_queryOptions = array();
        $this->returnType = 'array';
        $this->_nestJoin = false;
        $this->_forUpdate = false;
        $this->_lockInShareMode = false;
        $this->_tableName = '';
        $this->_lastInsertId = null;
        $this->_updateColumns = null;
        $this->_mapKey = null;
        if (! $this->_transaction_in_progress) {
            $this->defConnectionName = 'default';
        }
        $this->autoReconnectCount = 0;
        return $this;
    }

    public function jsonBuilder()
    {
        $this->returnType = 'json';
        return $this;
    }

    public function arrayBuilder()
    {
        $this->returnType = 'array';
        return $this;
    }

    public function objectBuilder()
    {
        $this->returnType = 'object';
        return $this;
    }

    public function setPrefix($prefix = '')
    {
        $this->prefix = $prefix;
        return $this;
    }

    public function setMysqli(&$mysqli)
    {
        $this->_mysqli = $mysqli;
        return $this;
    }

    private function queryUnprepared($query)
    {
        $stmt = $this->_mysqli->query($query);
        if ($stmt !== false) {
            return $stmt;
        }
    }

    public function rawQuery($query, $bindParams = null)
    {
        $params = array(
            ''
        ); // Create the empty 0 index
        $this->_query = $query;
        $stmt = $this->_prepareQuery();
        
        if (is_array($bindParams) === true) {
            foreach ($bindParams as $prop => $val) {
                $params[0] .= $this->_determineType($val);
                array_push($params, $bindParams[$prop]);
            }
            call_user_func_array(array(
                $stmt,
                'bind_param'
            ), $this->refValues($params));
        }
        $stmt->execute($this->prepareVal());
        $this->count = $stmt->rowCount();
        $this->_stmtError = $stmt->errorInfo();
        $this->_stmtErrno = $stmt->errorCode();
        $this->_lastQuery = $this->replacePlaceHolders($this->_query, $params);
        $res = $this->_dynamicBindResults($stmt);
        $this->reset();
        return $res;
    }

    public function rawQueryOne($query, $bindParams = null)
    {
        $res = $this->rawQuery($query, $bindParams);
        if (is_array($res) && isset($res[0])) {
            return $res[0];
        }
        return null;
    }

    public function rawQueryValue($query, $bindParams = null)
    {
        $res = $this->rawQuery($query, $bindParams);
        if (! $res) {
            return null;
        }
        $limit = preg_match('/limit\s+1;?$/i', $query);
        $key = key($res[0]);
        if (isset($res[0][$key]) && $limit == true) {
            return $res[0][$key];
        }
        $newRes = Array();
        for ($i = 0; $i < $this->count; $i ++) {
            $newRes[] = $res[$i][$key];
        }
        return $newRes;
    }

    public function query($query, $numRows = null)
    {
        $this->_query = $query;
        $stmt = $this->_buildQuery($numRows);
        $stmt->execute($this->prepareVal());
        $this->_stmtError = $stmt->errorInfo();
        $this->_stmtErrno = $stmt->errorCode();
        $res = $this->_dynamicBindResults($stmt);
        $this->reset();
        return $res;
    }

    public function setQueryOption($options)
    {
        $allowedOptions = Array(
            'ALL',
            'DISTINCT',
            'DISTINCTROW',
            'HIGH_PRIORITY',
            'STRAIGHT_JOIN',
            'SQL_SMALL_RESULT',
            'SQL_BIG_RESULT',
            'SQL_BUFFER_RESULT',
            'SQL_CACHE',
            'SQL_NO_CACHE',
            'SQL_CALC_FOUND_ROWS',
            'LOW_PRIORITY',
            'IGNORE',
            'QUICK',
            'MYSQLI_NESTJOIN',
            'FOR UPDATE',
            'LOCK IN SHARE MODE'
        );
        
        if (! is_array($options)) {
            $options = Array(
                $options
            );
        }
        foreach ($options as $option) {
            $option = strtoupper($option);
            if (! in_array($option, $allowedOptions)) {
                throw new \Exception('Wrong query option: ' . $option);
            }
            
            if ($option == 'MYSQLI_NESTJOIN') {
                $this->_nestJoin = true;
            } elseif ($option == 'FOR UPDATE') {
                $this->_forUpdate = true;
            } elseif ($option == 'LOCK IN SHARE MODE') {
                $this->_lockInShareMode = true;
            } else {
                $this->_queryOptions[] = $option;
            }
        }
        return $this;
    }

    public function withTotalCount()
    {
        $this->setQueryOption('SQL_CALC_FOUND_ROWS');
        return $this;
    }

    // smproxy 模式下强制使用主库
    public function master()
    {
        $this->annotation = '/** smproxy:db_type=write */';
        if (strtolower($this->annotationType) == "ks") {
            $this->annotation = '/*master*/';
        }
        return $this;
    }
    public function setAnnotationType($type){
        $this->annotationType=$type;
        return $this;
    }
    
    // smproxy 模式下强制使用读库
    public function read()
    {
        $this->annotationType = strtolower($type);
        $this->annotation = '/** smproxy:db_type=read */';
        return $this;
    }

    public function get($tableName, $numRows = null, $columns = '*')
    {
        if (empty($columns)) {
            $columns = '*';
        }
        $column = is_array($columns) ? implode(', ', $columns) : $columns;
        if (strpos($tableName, '.') === false) {
            $this->_tableName = $this->prefix . $tableName;
        } else {
            $this->_tableName = $tableName;
        }
        if ($this->annotation) {
            if ($this->annotationType == "smp") {
                $this->_query = $this->annotation . '  SELECT ' . implode(' ', $this->_queryOptions) . ' ' . $column . " FROM " . $this->_tableName;
            } elseif ($this->annotationType == "ks") {
                $this->_query = '  SELECT  ' . $this->annotation . "  " . implode(' ', $this->_queryOptions) . ' ' . $column . " FROM " . $this->_tableName;
            }
        } else {
            $this->_query = 'SELECT ' . implode(' ', $this->_queryOptions) . ' ' . $column . " FROM " . $this->_tableName;
        }
        
        $stmt = $this->_buildQuery($numRows);
        
        if ($this->isSubQuery) {
            return $this;
        }
        $stmt->execute($this->prepareVal());
        $this->_stmtError = $stmt->errorInfo();
        $this->_stmtErrno = $stmt->errorCode();
        $res = $this->_dynamicBindResults($stmt);
        $this->reset();
        
        return $res;
    }

    public function getOne($tableName, $columns = '*')
    {
        $res = $this->get($tableName, 1, $columns);
        if (is_array($res) && isset($res[0])) {
            return $res[0];
        } elseif ($res) {
            return $res;
        }
        
        return null;
    }

    public function getValue($tableName, $column, $limit = 1)
    {
        $res = $this->ArrayBuilder()->get($tableName, $limit, "{$column} AS retval");
        
        if (! $res) {
            return null;
        }
        
        if ($limit == 1) {
            if (isset($res[0]["retval"])) {
                return $res[0]["retval"];
            }
            return null;
        }
        
        $newRes = Array();
        for ($i = 0; $i < $this->count; $i ++) {
            $newRes[] = $res[$i]['retval'];
        }
        return $newRes;
    }

    public function insert($tableName, $insertData)
    {
        return $this->_buildInsert($tableName, $insertData, 'INSERT');
    }

    public function insertMulti($tableName, array $multiInsertData, array $dataKeys = null)
    {
        // only auto-commit our inserts, if no transaction is currently running
        $autoCommit = (isset($this->_transaction_in_progress) ? ! $this->_transaction_in_progress : true);
        $ids = array();
        
        if ($autoCommit) {
            $this->startTransaction();
        }
        
        foreach ($multiInsertData as $insertData) {
            if ($dataKeys !== null) {
                // apply column-names if given, else assume they're already given in the data
                $insertData = array_combine($dataKeys, $insertData);
            }
            
            $id = $this->insert($tableName, $insertData);
            if (! $id) {
                if ($autoCommit) {
                    $this->rollback();
                }
                return false;
            }
            $ids[] = $id;
        }
        
        if ($autoCommit) {
            $this->commit();
        }
        
        return $ids;
    }

    public function replace($tableName, $insertData)
    {
        return $this->_buildInsert($tableName, $insertData, 'REPLACE');
    }

    public function has($tableName)
    {
        $this->getOne($tableName, '1');
        return $this->count >= 1;
    }

    //
    private function prepareVal()
    {
        return array_slice($this->_bindParams, 1);
    }

    //
    public function update($tableName, $tableData, $numRows = null)
    {
        if ($this->isSubQuery) {
            return;
        }
        
        $this->_query = "UPDATE " . $this->prefix . $tableName;
        
        $stmt = $this->_buildQuery($numRows, $tableData);
        $status = $stmt->execute($this->prepareVal());
        $this->reset();
        $this->_stmtError = $stmt->errorInfo();
        $this->_stmtErrno = $stmt->errorCode();
        $this->count = $stmt->rowCount();
        
        return $status;
    }

    public function delete($tableName, $numRows = null)
    {
        if ($this->isSubQuery) {
            return;
        }
        
        $table = $this->prefix . $tableName;
        
        if (count($this->_join)) {
            $this->_query = "DELETE " . preg_replace('/.* (.*)/', '$1', $table) . " FROM " . $table;
        } else {
            $this->_query = "DELETE FROM " . $table;
        }
        
        $stmt = $this->_buildQuery($numRows);
        $stmt->execute($this->prepareVal());
        $this->_stmtError = $stmt->errorInfo();
        $this->_stmtErrno = $stmt->errorCode();
        $this->count = $stmt->rowCount();
        $this->reset();
        
        return ($this->count > - 1); // -1 indicates that the query returned an error
    }

    public function where($whereProp, $whereValue = 'DBNULL', $operator = '=', $cond = 'AND')
    {
        if (count($this->_where) == 0) {
            $cond = '';
        }
        
        $this->_where[] = array(
            $cond,
            $whereProp,
            $operator,
            $whereValue
        );
        return $this;
    }

    public function onDuplicate($updateColumns, $lastInsertId = null)
    {
        $this->_lastInsertId = $lastInsertId;
        $this->_updateColumns = $updateColumns;
        return $this;
    }

    public function orWhere($whereProp, $whereValue = 'DBNULL', $operator = '=')
    {
        return $this->where($whereProp, $whereValue, $operator, 'OR');
    }

    public function having($havingProp, $havingValue = 'DBNULL', $operator = '=', $cond = 'AND')
    {
        // forkaround for an old operation api
        if (is_array($havingValue) && ($key = key($havingValue)) != "0") {
            $operator = $key;
            $havingValue = $havingValue[$key];
        }
        
        if (count($this->_having) == 0) {
            $cond = '';
        }
        
        $this->_having[] = array(
            $cond,
            $havingProp,
            $operator,
            $havingValue
        );
        return $this;
    }

    public function orHaving($havingProp, $havingValue = null, $operator = null)
    {
        return $this->having($havingProp, $havingValue, $operator, 'OR');
    }

    public function join($joinTable, $joinCondition, $joinType = '')
    {
        $allowedTypes = array(
            'LEFT',
            'RIGHT',
            'OUTER',
            'INNER',
            'LEFT OUTER',
            'RIGHT OUTER',
            'NATURAL'
        );
        $joinType = strtoupper(trim($joinType));
        
        if ($joinType && ! in_array($joinType, $allowedTypes)) {
            throw new \Exception('Wrong JOIN type: ' . $joinType);
        }
        
        if (! is_object($joinTable)) {
            $joinTable = $this->prefix . $joinTable;
        }
        
        $this->_join[] = Array(
            $joinType,
            $joinTable,
            $joinCondition
        );
        
        return $this;
    }

    public function loadData($importTable, $importFile, $importSettings = null)
    {
        // We have to check if the file exists
        if (! file_exists($importFile)) {
            // Throw an exception
            throw new Exception("importCSV -> importFile " . $importFile . " does not exists!");
        }
        
        // Define the default values
        // We will merge it later
        $settings = Array(
            "fieldChar" => ';',
            "lineChar" => PHP_EOL,
            "linesToIgnore" => 1
        );
        
        // Check the import settings
        if (gettype($importSettings) == "array") {
            // Merge the default array with the custom one
            $settings = array_merge($settings, $importSettings);
        }
        
        // Add the prefix to the import table
        $table = $this->prefix . $importTable;
        
        // Add 1 more slash to every slash so maria will interpret it as a path
        $importFile = str_replace("\\", "\\\\", $importFile);
        
        // Switch between LOAD DATA and LOAD DATA LOCAL
        $loadDataLocal = isset($settings["loadDataLocal"]) ? 'LOCAL' : '';
        
        // Build SQL Syntax
        $sqlSyntax = sprintf('LOAD DATA %s INFILE \'%s\' INTO TABLE %s', $loadDataLocal, $importFile, $table);
        
        // FIELDS
        $sqlSyntax .= sprintf(' FIELDS TERMINATED BY \'%s\'', $settings["fieldChar"]);
        if (isset($settings["fieldEnclosure"])) {
            $sqlSyntax .= sprintf(' ENCLOSED BY \'%s\'', $settings["fieldEnclosure"]);
        }
        
        // LINES
        $sqlSyntax .= sprintf(' LINES TERMINATED BY \'%s\'', $settings["lineChar"]);
        if (isset($settings["lineStarting"])) {
            $sqlSyntax .= sprintf(' STARTING BY \'%s\'', $settings["lineStarting"]);
        }
        
        // IGNORE LINES
        $sqlSyntax .= sprintf(' IGNORE %d LINES', $settings["linesToIgnore"]);
        
        // Execute the query unprepared because LOAD DATA only works with unprepared statements.
        $result = $this->queryUnprepared($sqlSyntax);
        
        // Are there rows modified?
        // Let the user know if the import failed / succeeded
        return (bool) $result;
    }

    public function loadXml($importTable, $importFile, $importSettings = null)
    {
        // We have to check if the file exists
        if (! file_exists($importFile)) {
            // Does not exists
            throw new Exception("loadXml: Import file does not exists");
            return;
        }
        
        // Create default values
        $settings = Array(
            "linesToIgnore" => 0
        );
        
        // Check the import settings
        if (gettype($importSettings) == "array") {
            $settings = array_merge($settings, $importSettings);
        }
        
        // Add the prefix to the import table
        $table = $this->prefix . $importTable;
        
        // Add 1 more slash to every slash so maria will interpret it as a path
        $importFile = str_replace("\\", "\\\\", $importFile);
        
        // Build SQL Syntax
        $sqlSyntax = sprintf('LOAD XML INFILE \'%s\' INTO TABLE %s', $importFile, $table);
        
        // FIELDS
        if (isset($settings["rowTag"])) {
            $sqlSyntax .= sprintf(' ROWS IDENTIFIED BY \'%s\'', $settings["rowTag"]);
        }
        
        // IGNORE LINES
        $sqlSyntax .= sprintf(' IGNORE %d LINES', $settings["linesToIgnore"]);
        
        // Exceute the query unprepared because LOAD XML only works with unprepared statements.
        $result = $this->queryUnprepared($sqlSyntax);
        
        // Are there rows modified?
        // Let the user know if the import failed / succeeded
        return (bool) $result;
    }

    public function orderBy($orderByField, $orderbyDirection = "DESC", $customFieldsOrRegExp = null)
    {
        $allowedDirection = Array(
            "ASC",
            "DESC"
        );
        $orderbyDirection = strtoupper(trim($orderbyDirection));
        $orderByField = preg_replace("/[^ -a-z0-9\.\(\),_`\*\'\"]+/i", '', $orderByField);
        
        // Add table prefix to orderByField if needed.
        // FIXME: We are adding prefix only if table is enclosed into `` to distinguish aliases
        // from table names
        $orderByField = preg_replace('/(\`)([`a-zA-Z0-9_]*\.)/', '\1' . $this->prefix . '\2', $orderByField);
        
        if (empty($orderbyDirection) || ! in_array($orderbyDirection, $allowedDirection)) {
            throw new \Exception('Wrong order direction: ' . $orderbyDirection);
        }
        
        if (is_array($customFieldsOrRegExp)) {
            foreach ($customFieldsOrRegExp as $key => $value) {
                $customFieldsOrRegExp[$key] = preg_replace("/[^\x80-\xff-a-z0-9\.\(\),_` ]+/i", '', $value);
            }
            $orderByField = 'FIELD (' . $orderByField . ', "' . implode('","', $customFieldsOrRegExp) . '")';
        } elseif (is_string($customFieldsOrRegExp)) {
            $orderByField = $orderByField . " REGEXP '" . $customFieldsOrRegExp . "'";
        } elseif ($customFieldsOrRegExp !== null) {
            throw new \Exception('Wrong custom field or Regular Expression: ' . $customFieldsOrRegExp);
        }
        
        $this->_orderBy[$orderByField] = $orderbyDirection;
        return $this;
    }
    public function close(){
        $this->_mysqli=null;
        $this->dbobject=null;
    }
    public function groupBy($groupByField)
    {
        $groupByField = preg_replace("/[^-a-z0-9\.\(\),_\* <>=!]+/i", '', $groupByField);
        
        $this->_groupBy[] = $groupByField;
        return $this;
    }

    public function setLockMethod($method)
    {
        // Switch the uppercase string
        switch (strtoupper($method)) {
            // Is it READ or WRITE?
            case "READ" || "WRITE":
                // Succeed
                $this->_tableLockMethod = $method;
                break;
            default:
                // Else throw an exception
                throw new Exception("Bad lock type: Can be either READ or WRITE");
                break;
        }
        return $this;
    }

    public function lock($table)
    {
        // Main Query
        $this->_query = "LOCK TABLES";
        
        // Is the table an array?
        if (gettype($table) == "array") {
            // Loop trough it and attach it to the query
            foreach ($table as $key => $value) {
                if (gettype($value) == "string") {
                    if ($key > 0) {
                        $this->_query .= ",";
                    }
                    $this->_query .= " " . $this->prefix . $value . " " . $this->_tableLockMethod;
                }
            }
        } else {
            // Build the table prefix
            $table = $this->prefix . $table;
            
            // Build the query
            $this->_query = "LOCK TABLES " . $table . " " . $this->_tableLockMethod;
        }
        
        // Execute the query unprepared because LOCK only works with unprepared statements.
        $result = $this->queryUnprepared($this->_query);
        $errno = $this->mysqli()->errno;
        
        // Reset the query
        $this->reset();
        
        // Are there rows modified?
        if ($result) {
            // Return true
            // We can't return ourself because if one table gets locked, all other ones get unlocked!
            return true;
        } // Something went wrong
        else {
            throw new Exception("Locking of table " . $table . " failed", $errno);
        }
        
        // Return the success value
        return false;
    }

    public function unlock()
    {
        // Build the query
        $this->_query = "UNLOCK TABLES";
        
        // Execute the query unprepared because UNLOCK and LOCK only works with unprepared statements.
        $result = $this->queryUnprepared($this->_query);
        $errno = $this->mysqli()->errno;
        
        // Reset the query
        $this->reset();
        
        // Are there rows modified?
        if ($result) {
            // return self
            return $this;
        } // Something went wrong
        else {
            throw new \Exception("Unlocking of tables failed", $errno);
        }
        
        // Return self
        return $this;
    }

    public function getInsertId()
    {
        return $this->_mysqli()->insert_id;
    }

    public function escape($str)
    {
        return $this->mysqli()->real_escape_string($str);
    }

    public function ping()
    {
        return $this->mysqli()->ping();
    }

    protected function _determineType($item)
    {
        switch (gettype($item)) {
            case 'NULL':
            case 'string':
                return 's';
                break;
            
            case 'boolean':
            case 'integer':
                return 'i';
                break;
            
            case 'blob':
                return 'b';
                break;
            
            case 'double':
                return 'd';
                break;
        }
        return '';
    }

    protected function _bindParam($value)
    {
        $this->_bindParams[0] .= $this->_determineType($value);
        array_push($this->_bindParams, $value);
    }

    protected function _bindParams($values)
    {
        foreach ($values as $value) {
            $this->_bindParam($value);
        }
    }

    protected function _buildPair($operator, $value)
    {
        if (! is_object($value)) {
            $this->_bindParam($value);
            return ' ' . $operator . ' ? ';
        }
        
        $subQuery = $value->getSubQuery();
        $this->_bindParams($subQuery['params']);
        
        return " " . $operator . " (" . $subQuery['query'] . ") " . $subQuery['alias'];
    }

    private function _buildInsert($tableName, $insertData, $operation)
    {
        if ($this->isSubQuery) {
            return;
        }
        $this->_query = $operation . " " . implode(' ', $this->_queryOptions) . " INTO " . $this->prefix . $tableName;
        $stmt = $this->_buildQuery(null, $insertData);
        $status = $stmt->execute($this->prepareVal());
        $this->_stmtError = $stmt->errorInfo();
        $this->_stmtErrno = $stmt->errorCode();
        $haveOnDuplicate = ! empty($this->_updateColumns);
        $this->reset();
        $this->count = $stmt->rowCount();
        if ($this->_mysqli->lastInsertId() > 0) {
            return $this->_mysqli->lastInsertId();
        }
        return true;
    }

    protected function _buildQuery($numRows = null, $tableData = null)
    {
        // $this->_buildJoinOld();
        $this->_buildJoin();
        $this->_buildInsertQuery($tableData);
        $this->_buildCondition('WHERE', $this->_where);
        $this->_buildGroupBy();
        $this->_buildCondition('HAVING', $this->_having);
        $this->_buildOrderBy();
        $this->_buildLimit($numRows);
        $this->_buildOnDuplicate($tableData);
        
        if ($this->_forUpdate) {
            $this->_query .= ' FOR UPDATE';
        }
        if ($this->_lockInShareMode) {
            $this->_query .= ' LOCK IN SHARE MODE';
        }
        
        $this->_lastQuery = $this->replacePlaceHolders($this->_query, $this->_bindParams);
        
        if ($this->isSubQuery) {
            return;
        }
        
        // Prepare query
        $stmt = $this->_prepareQuery();
        
        // Bind parameters to statement if any
        if (count($this->_bindParams) > 1 && $stmt) {
            @call_user_func_array(array(
                $stmt,
                'bind_param'
            ), $this->refValues($this->_bindParams));
        }
        
        return $stmt;
    }

    protected function _dynamicBindResults($stmt)
    {
        $stmt->setFetchMode(\PDO::FETCH_ASSOC);
        $result = $stmt->fetchAll();
        return $result;
    }

    private function mysqli()
    {
        return $this->_mysqli;
    }

    protected function _buildJoinOld()
    {
        if (empty($this->_join)) {
            return;
        }
        
        foreach ($this->_join as $data) {
            list ($joinType, $joinTable, $joinCondition) = $data;
            
            if (is_object($joinTable)) {
                $joinStr = $this->_buildPair("", $joinTable);
            } else {
                $joinStr = $joinTable;
            }
            
            $this->_query .= " " . $joinType . " JOIN " . $joinStr . (false !== stripos($joinCondition, 'using') ? " " : " on ") . $joinCondition;
        }
    }

    public function _buildDataPairs($tableData, $tableColumns, $isInsert)
    {
        foreach ($tableColumns as $column) {
            $value = $tableData[$column];
            
            if (! $isInsert) {
                if (strpos($column, '.') === false) {
                    $this->_query .= "`" . $column . "` = ";
                } else {
                    $this->_query .= str_replace('.', '.`', $column) . "` = ";
                }
            }
            
            // Subquery value
            if ($value instanceof MysqliDb) {
                $this->_query .= $this->_buildPair("", $value) . ", ";
                continue;
            }
            
            // Simple value
            if (! is_array($value)) {
                $this->_bindParam($value);
                $this->_query .= '?, ';
                continue;
            }
            
            // Function value
            $key = key($value);
            $val = $value[$key];
            switch ($key) {
                case '[I]':
                    $this->_query .= $column . $val . ", ";
                    break;
                case '[F]':
                    $this->_query .= $val[0] . ", ";
                    if (! empty($val[1])) {
                        $this->_bindParams($val[1]);
                    }
                    break;
                case '[N]':
                    if ($val == null) {
                        $this->_query .= "!" . $column . ", ";
                    } else {
                        $this->_query .= "!" . $val . ", ";
                    }
                    break;
                default:
                    throw new \Exception("Wrong operation");
            }
        }
        $this->_query = rtrim($this->_query, ', ');
    }

    protected function _buildOnDuplicate($tableData)
    {
        if (is_array($this->_updateColumns) && ! empty($this->_updateColumns)) {
            $this->_query .= " ON DUPLICATE KEY UPDATE ";
            if ($this->_lastInsertId) {
                $this->_query .= $this->_lastInsertId . "=LAST_INSERT_ID (" . $this->_lastInsertId . "), ";
            }
            
            foreach ($this->_updateColumns as $key => $val) {
                // skip all params without a value
                if (is_numeric($key)) {
                    $this->_updateColumns[$val] = '';
                    unset($this->_updateColumns[$key]);
                } else {
                    $tableData[$key] = $val;
                }
            }
            $this->_buildDataPairs($tableData, array_keys($this->_updateColumns), false);
        }
    }

    protected function _buildInsertQuery($tableData)
    {
        if (! is_array($tableData)) {
            return;
        }
        
        $isInsert = preg_match('/^[INSERT|REPLACE]/', $this->_query);
        $dataColumns = array_keys($tableData);
        if ($isInsert) {
            if (isset($dataColumns[0]))
                $this->_query .= ' (`' . implode($dataColumns, '`, `') . '`) ';
            $this->_query .= ' VALUES (';
        } else {
            $this->_query .= " SET ";
        }
        
        $this->_buildDataPairs($tableData, $dataColumns, $isInsert);
        
        if ($isInsert) {
            $this->_query .= ')';
        }
    }

    protected function _buildCondition($operator, &$conditions)
    {
        if (empty($conditions)) {
            return;
        }
        
        // Prepare the where portion of the query
        $this->_query .= ' ' . $operator;
        
        foreach ($conditions as $cond) {
            list ($concat, $varName, $operator, $val) = $cond;
            $this->_query .= " " . $concat . " " . $varName;
            
            switch (strtolower($operator)) {
                case 'not in':
                case 'in':
                    $comparison = ' ' . $operator . ' (';
                    if (is_object($val)) {
                        $comparison .= $this->_buildPair("", $val);
                    } else {
                        foreach ($val as $v) {
                            $comparison .= ' ?,';
                            $this->_bindParam($v);
                        }
                    }
                    $this->_query .= rtrim($comparison, ',') . ' ) ';
                    break;
                case 'not between':
                case 'between':
                    $this->_query .= " $operator ? AND ? ";
                    $this->_bindParams($val);
                    break;
                case 'not exists':
                case 'exists':
                    $this->_query .= $operator . $this->_buildPair("", $val);
                    break;
                default:
                    if (is_array($val)) {
                        $this->_bindParams($val);
                    } elseif ($val === null) {
                        $this->_query .= ' ' . $operator . " NULL";
                    } elseif ($val != 'DBNULL' || $val == '0') {
                        $this->_query .= $this->_buildPair($operator, $val);
                    }
            }
        }
    }

    protected function _buildGroupBy()
    {
        if (empty($this->_groupBy)) {
            return;
        }
        
        $this->_query .= " GROUP BY ";
        
        foreach ($this->_groupBy as $key => $value) {
            $this->_query .= $value . ", ";
        }
        
        $this->_query = rtrim($this->_query, ', ') . " ";
    }

    protected function _buildOrderBy()
    {
        if (empty($this->_orderBy)) {
            return;
        }
        
        $this->_query .= " ORDER BY ";
        foreach ($this->_orderBy as $prop => $value) {
            if (strtolower(str_replace(" ", "", $prop)) == 'rand()') {
                $this->_query .= "rand(), ";
            } else {
                $this->_query .= $prop . " " . $value . ", ";
            }
        }
        
        $this->_query = rtrim($this->_query, ', ') . " ";
    }

    protected function _buildLimit($numRows)
    {
        if (! isset($numRows)) {
            return;
        }
        
        if (is_array($numRows)) {
            $this->_query .= ' LIMIT ' . (int) $numRows[0] . ', ' . (int) $numRows[1];
        } else {
            $this->_query .= ' LIMIT ' . (int) $numRows;
        }
    }

    protected function _prepareQuery()
    {
        if (! $this->_mysqli) {
            throw new \PDOException("pdo mysql is null");
        }
        // echo "sql：" . $this->_query . PHP_EOL;
        $stmt = $this->_mysqli->prepare($this->_query);
        if ($stmt !== false) {
            if ($this->traceEnabled)
                $this->traceStartQ = microtime(true);
            return $stmt;
        }
        $error = $this->_mysqli->error;
        $query = $this->_query;
        $errno = $this->_mysqli->errno;
        $this->reset();
        throw new \Exception(sprintf('%s query: %s', $error, $query), $errno);
    }

    protected function refValues(array &$arr)
    {
        // Reference in the function arguments are required for HHVM to work
        // https://github.com/facebook/hhvm/issues/5155
        // Referenced data array is required by mysqli since PHP 5.3+
        if (strnatcmp(phpversion(), '5.3') >= 0) {
            $refs = array();
            foreach ($arr as $key => $value) {
                $refs[$key] = & $arr[$key];
            }
            return $refs;
        }
        return $arr;
    }

    protected function replacePlaceHolders($str, $vals)
    {
        $i = 1;
        $newStr = "";
        
        if (empty($vals)) {
            return $str;
        }
        
        while ($pos = strpos($str, "?")) {
            $val = $vals[$i ++];
            if (is_object($val)) {
                $val = '[object]';
            }
            if ($val === null) {
                $val = 'NULL';
            }
            $newStr .= substr($str, 0, $pos) . "'" . $val . "'";
            $str = substr($str, $pos + 1);
        }
        $newStr .= $str;
        return $newStr;
    }

    public function getLastQuery()
    {
        return $this->_lastQuery;
    }

    public function getLastError()
    {
        return $this->mysqli()->errorInfo();
    }

    public function getLastErrno()
    {
        return $this->mysqli()->errorCode();
    }

    public function getSubQuery()
    {
        if (! $this->isSubQuery) {
            return null;
        }
        
        array_shift($this->_bindParams);
        $val = Array(
            'query' => $this->_query,
            'params' => $this->_bindParams,
            'alias' => isset($this->connectionsSettings[$this->defConnectionName]) ? $this->connectionsSettings[$this->defConnectionName]['host'] : null
        );
        $this->reset();
        return $val;
    }

    public function interval($diff, $func = "NOW()")
    {
        $types = Array(
            "s" => "second",
            "m" => "minute",
            "h" => "hour",
            "d" => "day",
            "M" => "month",
            "Y" => "year"
        );
        $incr = '+';
        $items = '';
        $type = 'd';
        $matches = [];
        if ($diff && preg_match('/([+-]?) ?([0-9]+) ?([a-zA-Z]?)/', $diff, $matches)) {
            if (! empty($matches[1])) {
                $incr = $matches[1];
            }
            
            if (! empty($matches[2])) {
                $items = $matches[2];
            }
            
            if (! empty($matches[3])) {
                $type = $matches[3];
            }
            
            if (! in_array($type, array_keys($types))) {
                throw new \Exception("invalid interval type in '{$diff}'");
            }
            
            $func .= " " . $incr . " interval " . $items . " " . $types[$type] . " ";
        }
        return $func;
    }

    public function now($diff = null, $func = "NOW()")
    {
        return array(
            "[F]" => Array(
                $this->interval($diff, $func)
            )
        );
    }

    public function inc($num = 1)
    {
        if (! is_numeric($num)) {
            throw new Exception('Argument supplied to inc must be a number');
        }
        return array(
            "[I]" => "+" . $num
        );
    }

    public function dec($num = 1)
    {
        if (! is_numeric($num)) {
            throw new Exception('Argument supplied to dec must be a number');
        }
        return array(
            "[I]" => "-" . $num
        );
    }

    public function not($col = null)
    {
        return array(
            "[N]" => (string) $col
        );
    }

    public function func($expr, $bindParams = null)
    {
        return array(
            "[F]" => array(
                $expr,
                $bindParams
            )
        );
    }

    public static function subQuery(\ClevePHP\Extension\mysql\Config $config = null)
    {
        $object = new self();
        if ($config) {
            $object->setPrefix($config->prefix);
            $mysql = \ClevePHP\Extension\mysql\Mysql::getInstance(null)->setConfig($config)->getDrive();
            $object->_mysqli = $mysql;
        }
        $object->setPrefix(\ClevePHP\Extension\mysql\Config::getInstance()->prefix);
        $object->setSubQuery(TRUE);
        return $object;
    }

    public function copy()
    {
        $copy = unserialize(serialize($this));
        $copy->_mysqli = array();
        return $copy;
    }

    private $dbobject = null;

    public function startTransaction()
    {
        $this->dbobject = $this->mysqli();
        
        $this->dbobject->beginTransaction();
        // $this->mysqli ()->autocommit ( false );
        $this->_transaction_in_progress = true;
        register_shutdown_function(array(
            $this,
            "_transaction_status_check"
        ));
    }

    public function commit()
    {
        $result = $this->dbobject->commit();
        $this->_transaction_in_progress = false;
        // $this->mysqli()->autocommit(true);
        return $result;
    }

    public function rollback()
    {
        $result = $this->dbobject->rollback();
        $this->_transaction_in_progress = false;
        // $this->mysqli()->autocommit(true);
        return $result;
    }

    public function _transaction_status_check()
    {
        if (! $this->_transaction_in_progress) {
            return;
        }
        $this->rollback();
    }

    public function setTrace($enabled, $stripPrefix = null)
    {
        $this->traceEnabled = $enabled;
        $this->traceStripPrefix = $stripPrefix;
        return $this;
    }

    private function _traceGetCaller()
    {
        $dd = debug_backtrace();
        $caller = next($dd);
        while (isset($caller) && $caller["file"] == __FILE__) {
            $caller = next($dd);
        }
        
        return __CLASS__ . "->" . $caller["function"] . "() >>  file \"" . str_replace($this->traceStripPrefix, '', $caller["file"]) . "\" line #" . $caller["line"] . " ";
    }

    public function tableExists($tables)
    {
        $tables = ! is_array($tables) ? Array(
            $tables
        ) : $tables;
        $count = count($tables);
        if ($count == 0) {
            return false;
        }
        
        foreach ($tables as $i => $value)
            $tables[$i] = $this->prefix . $value;
        $db = isset($this->connectionsSettings[$this->defConnectionName]) ? $this->connectionsSettings[$this->defConnectionName]['db'] : null;
        $this->where('table_schema', $db);
        $this->where('table_name', $tables, 'in');
        $this->get('information_schema.tables', $count);
        return $this->count == $count;
    }

    public function map($idField)
    {
        $this->_mapKey = $idField;
        return $this;
    }

    public function paginate($table, $page, $fields = null)
    {
        $offset = $this->pageLimit * ($page - 1);
        $res = $this->withTotalCount()->get($table, Array(
            $offset,
            $this->pageLimit
        ), $fields);
        $this->totalPages = ceil($this->totalCount / $this->pageLimit);
        return $res;
    }

    public function joinWhere($whereJoin, $whereProp, $whereValue = 'DBNULL', $operator = '=', $cond = 'AND')
    {
        $this->_joinAnd[$this->prefix . $whereJoin][] = Array(
            $cond,
            $whereProp,
            $operator,
            $whereValue
        );
        return $this;
    }

    public function joinOrWhere($whereJoin, $whereProp, $whereValue = 'DBNULL', $operator = '=', $cond = 'AND')
    {
        return $this->joinWhere($whereJoin, $whereProp, $whereValue, $operator, 'OR');
    }

    protected function _buildJoin()
    {
        if (empty($this->_join))
            return;
        
        foreach ($this->_join as $data) {
            list ($joinType, $joinTable, $joinCondition) = $data;
            
            if (is_object($joinTable))
                $joinStr = $this->_buildPair("", $joinTable);
            else
                $joinStr = $joinTable;
            
            $this->_query .= " " . $joinType . " JOIN " . $joinStr . (false !== stripos($joinCondition, 'using') ? " " : " on ") . $joinCondition;
            
            // Add join and query
            if (! empty($this->_joinAnd) && isset($this->_joinAnd[$joinStr])) {
                foreach ($this->_joinAnd[$joinStr] as $join_and_cond) {
                    list ($concat, $varName, $operator, $val) = $join_and_cond;
                    $this->_query .= " " . $concat . " " . $varName;
                    $this->conditionToSql($operator, $val);
                }
            }
        }
    }

    private function conditionToSql($operator, $val)
    {
        switch (strtolower($operator)) {
            case 'not in':
            case 'in':
                $comparison = ' ' . $operator . ' (';
                if (is_object($val)) {
                    $comparison .= $this->_buildPair("", $val);
                } else {
                    foreach ($val as $v) {
                        $comparison .= ' ?,';
                        $this->_bindParam($v);
                    }
                }
                $this->_query .= rtrim($comparison, ',') . ' ) ';
                break;
            case 'not between':
            case 'between':
                $this->_query .= " $operator ? AND ? ";
                $this->_bindParams($val);
                break;
            case 'not exists':
            case 'exists':
                $this->_query .= $operator . $this->_buildPair("", $val);
                break;
            default:
                if (is_array($val))
                    $this->_bindParams($val);
                else if ($val === null)
                    $this->_query .= $operator . " NULL";
                else if ($val != 'DBNULL' || $val == '0')
                    $this->_query .= $this->_buildPair($operator, $val);
        }
    }
}