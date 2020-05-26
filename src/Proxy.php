// <?php
// /**
//  * 文件名(Relolver.php)
//  *
//  * 功能描述（略）
//  * --代理-转自于ClevePHP
//  * @author steve <ceiba_@126.com>
//  * @version 1.0
//  * @package sample2
//  */
// namespace ClevePHP\Extension\mysql;

// class Proxy extends Relolver
// {

//     private $isWrite = false;

//     private $mysqls = [];

//     function __construct($mysql = null, $prefix = null)
//     {
//         if (is_array($mysql) && isset($mysql["write"]) && isset($mysql['read'])) {
//             $this->mysqls = $mysql;
//         } else {
//             return parent::__construct($mysql, $prefix);
//         }
//     }
//     //插入
//     public function insert($tableName, $insertData)
//     {
//         $this->isWrite = true;
//         $this->setMysqli($this->getDb());
//         parent::insert($tableName, $insertData);
//     }
//     //更新
//     public function update($tableName, $tableData){
//         $this->isWrite=true;
//         $this->setMysqli($this->getDb());
//         return parent::update($tableName, $tableData);
//     }
//     //强制使用写库
//     public function master(){
//         $this->isWrite=true;
//         $this->setMysqli($this->getDb());
//         return $this;
//     }
//     //
//     private function getDb()
//     {
//         $db = null;
//         if ($this->isWrite) {
//             $result = $this->mysqls["write"];
//             if ($result && count($result) > 1) {
//                 return array_rand($result, 2);
//             } else {
//                 return $result ?? null;
//             }
//         } else {
//             $result = $this->mysqls["read"];
//             if ($result && count($result) > 1) {
//                 return array_rand($result, 2);
//             } else {
//                 return $result ?? null;
//             }
//         }
//     }
// }