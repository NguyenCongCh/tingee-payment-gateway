<?php
    require("../../../wp-config.php");
    class AccessData{
        protected $conn;
        private $host = DB_HOST;
        private $database = DB_NAME;
        private $username = DB_USER;
        private $password = DB_PASSWORD;
        public function __construct()
        {
        }
        public function connect(){
            try {
                if(!$this->conn){
                    $this-> conn = mysqli_connect($this->host
                    ,$this->username, $this->password,$this->database,);
                }
            } catch(Exception $err){
                die($err->getMessage());
            }
        }
        public function dis_connect(){
            if($this->conn){
                mysqli_close($this->conn);
            }
        }
        public function query($sql){
            $this->connect();
            $result = $this->conn->query($sql);
            return $result;
        }
        public function update($sql){
            $this->connect();
            return mysqli_query($this->conn, $sql);
        }
    }

    $db = new AccessData();