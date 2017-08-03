<?php
/* ------------------------------------------------------------------
	Objet de base pour manipuler la base de données.
--------------------------------------------------------------------- */


class DB {
	public static $pdo = null;


	// connect to the database
	public static function connect($host, $db_name, $user, $pwd, $charset='utf8') {
		try {
			DB::$pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=$charset", $user, $pwd);
		}

		catch (Exception $e) {
			die('Error connecting to database : '. $e->getMessage());
		}
	}


	// query
	public static function query($query) {
		if (!DB::$pdo) die('Database not connected. Use DB::connect before DB::query.');
		return DB::$pdo->query($query);
	}


	// exec
	public static function exec($query) {
		if (!DB::$pdo) die('Database not connected. Use DB::connect before DB::exec.');
		return DB::$pdo->exec($query);
	}
	
}