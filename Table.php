<?php
/* ------------------------------------------------------------------
	Objet de base pour définir une table.
--------------------------------------------------------------------- */


class Table {
	const TABLE = '';  // nom de la table associée à l'objet
	const KEY = '';  // nom de la table associée à l'objet

	// -- propriétés privées
	protected static $cursor = [];  // liste des curseurs par table
	protected static $entries = [];  // liste des éléments recherchés, triés par table
	protected static $new = [];  // liste des booléens de nouveaux éléments, triés par table


	// -- fonctions de gestion des entrées
	public static function entries() 	{ return self::$entries[self::TABLE]; }
	public static function entry($i) 	{ return self::$entries[self::TABLE][$i]; }
	public static function first() 		{ return self::count() ? self::entry(0) : null; }
	public static function last() 		{ return self::count() ? self::entry(self::count()-1) : null; }
	public static function count() 		{ return count(self::$entries[self::TABLE]); }
	
	// indique si les entrées sont de nouvelles entrées ou non
	protected static function isNew() 	{
		if (!self::count()) {
			self::$new[self::TABLE] = true;
			return true;
		}
		if (!isset(self::$new[self::TABLE])) {
			self::$new[self::TABLE] = true;
			return true;
		}
		return self::$new[self::TABLE];
	}


	// -- fonctions de parcours par curseur
	protected static function cursor() {
		if (isset(self::$cursor[self::TABLE])) return self::$cursor[self::TABLE];
		self::$cursor[self::TABLE] = 0;
		return 0;
	}

	// incrémente le curseur et retourne son ancienne valeur
	protected static function incrementCursor() {  
		if (isset(self::$cursor[self::TABLE])) return self::$cursor[self::TABLE]++;
		self::$cursor[self::TABLE] = 1;
		return 0;
	}

	// retourne la valeur courante et incrémente le curseur
	public static function next() {
		$count = self::count();
		if (!$count) return null;  // pas de résultat
		$cursor = self::cursor();

		if ($cursor < $count) return self::entry(self::incrementCursor());

		if (self::incrementCursor() == $c) return null;
		
		self::$cursor[self::TABLE] = 1;  // on met automatiquement à jour pour ne pas nécessiter resetCursor
		return self::first();
	}

	// reset le curseur
	public static function resetCursor() {
		self::$cursor[self::TABLE] = 0;
	}




	// -- fonctions de sélection de parties des entrées
	// retourne un tableau associatif de type ["key_1" => property_1, "key_2" => property_2, ...]
	public static function properties($prop, $key='') {
		if (!$prop) return [];
		if (!$key) $key = self::KEY;
		if (!$key) return [];
		
		$propList = [];
		while ($elt = self::next()) $propList[$elt->$key] = $elt->$prop;
		return $propList;
	}


	// retourne un tableau d'ids. Il est possible de spécifier la clé des ids.
	public static function ids($key='') {
		if (!$key) $key = self::KEY;
		if (!$key) return [];
		
		$ids = [];
		while ($elt = self::next()) $ids[] = $elt->$key;
		return $ids;
	}





	// -- fonctions de suppression des entrées
	// supprime le dernier élément de l'objet
	public static function pop() {
		if (!self::count()) return;
		$entry = self::last();
		unset(self::$entries[self::TABLE][self::count()-1]);
		
		if (self::cursor() >= self::count()) {
			self::$cursor[self::TABLE]--;
		}

		if (!self::count()) {
			self::$new[self::TABLE] = true;
		}

		return $entry;
	}

	// supprime le premier élément de l'objet
	public static function shift() {
		if (!self::count()) return;
		$entry = self::first();
		unset(self::$entries[self::TABLE][0]);
		
		if (self::cursor() >= self::count()) {
			self::$cursor[self::TABLE]--;
		}

		if (!self::count()) {
			self::$new[self::TABLE] = true;
		}

		return $entry;
	}


	// vide la classe bddObject
	public static function clean() {
		self::$entries[self::TABLE] = [];
		self::$cursor[self::TABLE] = 0;
		self::$new[self::TABLE] = true;
	}


	// supprime un élément de la classe bddObject
	public static function cleanEntry($i) {
		unset(self::$entries[self::TABLE][$i]);
		
		if (self::cursor() > self::count()-1) {
			self::$cursor[self::TABLE] = self::count()-1;
		}

		if (!self::count()) {
			self::$new[self::TABLE] = true;
		}
	}




	// -- fonctions d'ajout des entrées
	// clean la table et ajoute un nouvel utilisateur
	public static function new($data) {
		if (!self::isNew()) self::clean();
		return self::push($data);
	}

	// ajoute un élément à l'objet
	public static function push($data) {

		// si l'argument est un tableau associatif, on le transforme en objet
		if (gettype($data) == "array") {
			$newData = new stdClass;

			foreach ($data as $key => $val) {
				if (!$key || (int) $key) continue;
				$newData->$key = $val;
			}

			$data = $newData;
		}
		else if (gettype($data) != "object") return null;


		// on ajoute l'objet aux entrées
		if (isset(self::$entries[self::TABLE])) {
			self::$entries[self::TABLE][] = $data;
		}
		else {
			self::$entries[self::TABLE] = [$data];
		}

		// on retourne l'objet que l'on vient d'ajouter
		return $data;
	}


	// va chercher de nouvelles entrées et remplit l'instance avec
	public static function find() {
		if (func_num_args() == 0) return null;
		self::clean();
		return call_user_func_array(array(self, 'findAndAdd'), func_get_args());
	}


	// va chercher de nouvelles entrées et les ajoute à l'instance bddObject
	public static function findAndAdd() {
		if (func_num_args() == 0) return null;

		// on informe que les entrées ne sont pas neuves
		self::$new[self::TABLE] = false;

		// -- on va chercher les informations dans la base de données
		$query = call_user_func_array(array(self, 'getSelectQuery'), func_get_args());
		$data = DB::query($query, PDO::FETCH_CLASS, 'stdClass');

		// -- puis on ajoute les résultats à l'objet
		if (!$data) return null;
		while ($newEntry = $data->fetch()) {
			self::push($newEntry);
		}

		$data->closeCursor();

		// on retourne la valeur ainsi récupérée
		if (func_num_args() > 1) return self::entries();
		$arg0 = gettype(func_get_args()[0]);
		if (gettype($arg0) === 'integer') return self::first();
		if (gettype($arg0) === 'string' && !self::hasSqlOperator($arg0)) return self::first();
		return self::entries();
	}



	// sauvegarde l'objet dans la base de données
	public static function save() {
		if (!self::count()) return;

		// on sauvegarde dans la base de données
		$query = self::getSaveQuery('update');
		if ($query) return DB::exec($query);
		return 0;
	}




	// sauvegarde l'objet dans la base de données en tant que nouvelle entrée
	public static function saveAsNew() {
		if (!self::count()) return;

		// on sauvegarde dans la base de données
		$query = self::getSaveQuery();
		if ($query) return DB::exec($query);
		return 0;
	}



	public static function delete($arg) {
		$query = self::getSelectQuery($arg);
		if ($query == '') return "DELETE FROM ". self::TABLE;

		// on remplace "SELECT *" PAR "DELETE"
		if (strpos($query, "SELECT *") === 0) {
			$query = "DELETE" . substr($query, strlen("SELECT *"));
			return DB::exec($query);
		}
		return 0;
	}











	// -- fonctions d'obtention des requêtes
	// retourne une requête SQL de sélection correspondant aux arguments passés à la fonction
	protected static function getSelectQuery() {
		if (func_num_args() == 0) return '';
		$query = "SELECT * FROM ". self::TABLE;

		$arguments = func_get_args();
		if (sizeof($arguments) == 1 && $arguments[0] == '*') return $query;

		// -- on effectue le "WHERE"
		$arg = $arguments[0];
		$query .= " WHERE (";

		if (gettype($arg) != "array") $arg = [$arg];

		$j = 0;
		foreach ($arg as $condition) {
			if ($j++) $query .= " OR ";

			$query .= '(';
			if ($condition == '*') $query .= '1';
			else if (!self::KEY || BddObject::hasSqlOperator($condition)) {
				$query .= $condition;
			}
			else {  // s'il n'y a aucun opérateur de condition, par défaut on compare à l'id primaire de la table
				$query .= self::KEY ." = ". $condition;
			}
			$query .= ')';
		}

		$query .= ')';

		// -- on effectue les autres commandes ('ORDER BY', etc...)
		$i = 1;
		while ($i < count($arguments)) $query .= " ". $arguments[$i++];

		return $query;
	}



	// retourne une requête SQL de déletion correspondant aux arguments passés à la fonction
	protected static function getDeleteQuery() {
		$query = call_user_func_array(array(self, 'getSelectQuery'), func_get_args());
		if ($query == '') return "DELETE FROM ". self::TABLE;

		// on remplace "SELECT *" PAR "DELETE"
		if (strpos($query, "SELECT *") === 0) {
			return substr_replace($query, "DELETE", 0, strlen("SELECT *"));
		}
		else return '';  // erreur de lecture
	}




	protected static function getSaveQuery($mode='') {
		// on obtient les noms des fields
		$fieldNames = [];
		$recordset = DB::query("SHOW COLUMNS FROM ". self::TABLE);
		$fields = $recordset->fetchAll(PDO::FETCH_ASSOC);
		foreach ($fields as $field) $fieldNames[] = $field['Field'];
		
		if (!count($fieldNames)) return '';  // table vide

		// on créé la requête
		$query = "INSERT INTO ". self::TABLE;
		$query .= " VALUES ";

		$i = 0;
		foreach (self::entries() as $entry) {
			$values = [];

			foreach ($fields as $field) {
				$name = $field['Field'];
				$type = $field['Type'];

				if ($name == self::KEY) $val = null;  // on enlève la KEY afin d'enregistrer une nouvelle entrée
				else if (isset($entry->$name)) $val = $entry->$name;
				else $val = $field['Default'];

				// on enroule la valeur entre des guillemets selon le type du champ
				if ($val == null) {
					// null
					$val = 'NULL';
				}
				else if (strpos($type, 'date') !== false && $val != 'CURRENT_TIMESTAMP') {
					// date
					$val = "'$val'";
				}
				else if (strpos($type, 'char') !== false) {
					// string
					$val = '"'.str_replace('"', "'", $val).'"';
				}
				else if (strpos($type, 'text') !== false) {
					// texte
					$val = '"'.str_replace('"', "'", $val).'"';
				}

				$values[] = $val;
			}

			if ($i++) $query .= ', ';
			$query .= '('. implode(', ', $values) .')';
		}


		// on prend en compte le cas de l'update
		if ($mode == 'update') {
			$query .= " ON DUPLICATE KEY UPDATE ";
			$i = 0;
			foreach ($fieldNames as $field) {
				if ($i++) $query .= ', ';
				$query .= "$field=VALUES($field)";
			}
		}

		return $query;
	}





	// -- fonctions-outils
	// retourne si une chaîne de caractère possède un opérateur comme '=', '<', '>', 'like', 'between', 'is'
	protected static function hasSqlOperator($str) {
		if (gettype($str) != "string") return false;

		return !(
			strpos($str, '=') === false
			&& strpos($str, '<') === false
			&& strpos($str, '>') === false
			&& stripos($str, 'in') === false
			&& stripos($str, 'like') === false
			&& stripos($str, 'regexp') === false
			&& stripos($str, 'between') === false
			&& stripos($str, 'is') === false
		);
	}

}
