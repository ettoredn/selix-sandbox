<?php

class Database
{
    const SESSION_TABLE = "session";
    const TRACEDATA_TABLE = "tracedata";
    const SESSION_CACHE_TABLE = "session_cache";
    private static $db;

    private function __construct() {}

    public static function GetConnection()
    {
        if (!isset(self::$db))
        {
            $user = "root";
            $pass = "ettore";
            $dbName = "php_benchmark";

            // Connect to DB
            try {
                self::$db = new PDO("mysql:host=localhost;dbname=". $dbName, $user, $pass);
                self::$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch (PDOException $e) {
                print "Error: ". $e->getMessage() ."<br />";
                die();
            }
        }

        return self::$db;
    }
}

?>
