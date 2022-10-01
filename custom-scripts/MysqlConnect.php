<?php 


class MysqlConnect {
///////// CONFIGURATION PART START////////////////
    protected $conn       = null;
    protected $server     = "localhost";
    protected $dbname     = "db_mage66";
    protected $user       = "db_mage66";
    protected $password   = "test";
    protected $table_prefix= "mg_";

///////// CONFIGURATION PART END////////////////

    
    protected function initiateConnection(){
        try {
            $this->conn = new PDO("mysql:host=$this->server;dbname=$this->dbname", $this->user, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            echo "Connected successfully\n";
        }
        catch(PDOException $e)
        {
            echo "Connection failed: \n" . $e->getMessage();
        }
        
        
        
         
        
        
        return $this->conn;
    }
}
