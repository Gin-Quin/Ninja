<?php


class App {
	private static $URI;
	private static $rules = [];
	private static $root = '';
	private static $defaultAction = 'home';
	private static $notFound = '404';
	private static $parameters = [];


	/**
	* Initialise la structure.
	*/
	public static function init() {
		// on trouve l'url et on le divise en sous-items
		$uri = explode('/', $_SERVER['REQUEST_URI']);
		App::clearEmptyValues($uri);
		App::$URI = $uri;
	}


	/**
	* Obtient et modifie la racine.
	*/
	public static function setRoot($root) {
		if (!$root) $root = '';
		else if (substr($root, -1) != '/') $root = $root . '/';
		App::$root = $root;
	}

	public static function getRoot() {
		return App::$root;
	}


	/**
	* Ajoute une règle de routage.
	* @param $location - location recherchée
	* @param $action - fichier déclenché si jamais la location est correcte
	*/
	public static function addRule($location, $action='') {
		if ($location[0] != '/') $location = '/' . $location;
		App::$rules[$location] = $action;
	}


	/**
	* Ajoute une ou pusieurs règles de routage.
	* @param $ruleArray - un tableau associatif de la forme :
		['location1' => 'action1', ...]
	*/
	public static function addRules($ruleArray) {
		foreach ($ruleArray as $location => $action) {
			if ($location[0] != '/') $location = '/' . $location;
			App::$rules[$location] = [$action];
		}
	}


	/**
	* Lance l'application.
	*/
	public static function run() {
		$noResult = true;

		foreach (App::$rules as $location => $action) {
			if (!App::matchLocation($location)) continue;
			$noResult = false;

			$action = explode('>', $action);
			$file = trim($action[0]);
			$function = trim($action[1]);

			if (strpos($file, -4) != '.php' && strpos($file, -5) != '.html') {
				$file .= '.php';
			}

			include App::$root.$file;

			if ($function) {
				call_user_func_array($function, App::$parameters);
			}
		}

		if ($noResult) {
			if (!count(App::$URI)) $file = App::$defaultAction;
			else $file = App::$notFound;

			var_dump(App::$URI, count(App::$URI), $file);

			if (strpos($file, -4) != '.php' && strpos($file, -5) != '.html') {
				$file .= '.php';
			}

			include App::$root.$file;
		}
	}


	/**
	* Vérifie si l'uri match la location donnée.
	*/
	private static function matchLocation($location) {
		$uri = App::$URI;
		$items = explode('/', $location);
		App::clearEmptyValues($items);

		if (count($items) != count($uri)) return false;

		// pour chaque item de la règle
		foreach ($items as $key => $val) {

			if ($val[0] == ':') {
				App::$parameters[substr($val, 1)] = $uri[$key];
			}

			else if ($val != $uri[$key]) {
				App::$parameters = [];
				return false;
			}

		}

		return true;
	}


	/**
	* Retourne la valeur d'un paramètre.
	*/
	public static function param($p) {
		if (!isset(App::$params[$p])) return null;

		$p = App::$params[$p];
		
		if (is_numeric($p)) return (0 + $p);
		return $p;
	}


	public static function allParameters() {
		return App::$params;
	}



	/**
	* Supprime d'un tableau tous les élements vide.
	*/
	private static function clearEmptyValues(&$array) {
		foreach ($array as $key => $value) {
			if (empty($value)) unset($array[$key]);
		}
		$array = array_values($array); // Réorganise les clés
	}


}



App::init();

