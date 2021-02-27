<?php
class databaseconfig{

    private $mysql_hostname = "localhost:3306";
    private $mysql_user = "root";
    private $mysql_password = "root";
    private $mysql_database = "test";

    public function getConnection(){

        $bd = mysqli_connect($this->mysql_hostname, $this->mysql_user, $this->mysql_password , $this->mysql_database) or die("Datenbank nicht erreichbar");
        return $bd;
    }
}
?>