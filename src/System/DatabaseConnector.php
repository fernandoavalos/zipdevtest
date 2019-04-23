<?php
namespace Src\System;

class DatabaseConnector {

    private $dbConnection = null;

    /**
     * This method constructs and sets needed variables
     */
    public function __construct()
    {
        //$host = getenv('DB_HOST');
        //$port = getenv('DB_PORT');
        //$db   = getenv('DB_DATABASE');
        //$user = getenv('DB_USERNAME');
        //$pass = getenv('DB_PASSWORD');

        $host = 'localhost';
        $db   = 'id9351791_zcbtest';
        $user = 'id9351791_fernandoavalos';
        $pass = '1234567890';

        try {
            //$this->dbConnection = new \PDO(
            //    "mysql:host=$host;port=$port;charset=utf8mb4;dbname=$db",
            //    $user,
            //    $pass
            //);
            $this->dbConnection = new \mysqli($host, $user, $pass, $db);
            //$this->dbConnection = mysqli_connect($host, $user, $pass, $db);
            if($this->dbConnection === false){
                die("ERROR: Could not connect. " . $mysqli->connect_error);
            }
            //echo "Connect Successfully. Host info: " . mysqli_get_host_info($link);
        } catch (\Exception $e) { 
            exit($e->getMessage());
        }
    }

    /**
     * This method returns the database connection
     * @return dbConnection
     */
    public function getConnection()
    {
        return $this->dbConnection;
    }
}