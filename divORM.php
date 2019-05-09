<?php

/**
 * Div ORM for PHP
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
 * or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License
 *
 * You should have received a copy of the GNU General Public License
 * along with this program as the file LICENSE.txt; if not, please see
 * http://www.gnu.org/licenses/gpl.txt.
 *
 * @author  Rafa Rodriguez [@rafageist] <rafageist@hotmail.com>
 * @version 0.1
 * @link    https://github.com/divengine/div-orm.git
 */
class divORM {

	const FIELD_TYPE_SERIAL = 'serial';

	/** @var array $__pdo_instances Physical Db instances */
	static $__pdo_instances = ["default" => NULL];

	/** @var array $__map_instances ORM instances */
	static $__map_instances = ["default" => NULL];

	/** @var string $__current_pdo_instance */
	static $__current_pdo_instance = "default";

	static $__current_map_instance = "default";

	static $__tokens = [];

	public $__config = NULL;

	public $__current_sql = NULL;

	public $__instance_name = NULL;

	public $__current_pdo = NULL;

	/**
	 * divORM constructor.
	 *
	 * @param $map_instance
	 * @param $connect
	 */
	public function __construct($connect = NULL, $map_instance = "default") {
		$this->__instance_name = $map_instance;
		if (!is_null($connect)) {

			// assuming that $connect is the db config
			if (!isset($connect['config'])) {
				$connect = ["config" => $connect];
			}

			// ... then, this is the default instance
			if (!isset($connect['instance'])) {
				$connect['instance'] = "default";
			}

			self::connect($connect['config'], $connect['instance']);
		}

		self::$__map_instances[$map_instance] = $this;
	}

	/**
	 * Connect to db with PDO
	 *
	 * @param $config
	 * @param string $instance
	 */
	static function connect($config, $instance = NULL) {
		if (is_null($instance)) {
			$instance = self::$__current_pdo_instance;
		}

		self::$__current_pdo_instance = $instance;

		$pdo = new PDO("{$config["type"]}:host={$config["host"]};port={$config["port"]};dbname={$config["name"]};", $config['user'], $config['pass']);
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		self::$__pdo_instances[$instance] = [
			"pdo"    => $pdo,
			"config" => $config,
		];
	}

	static function useDb($instance) {
		self::$__current_pdo_instance = $instance;
	}

	static function useMap($instance) {
		self::$__current_map_instance = $instance;
	}

	static function createInstance($config, $map_instance = "default") {
		$obj = new self($config, $map_instance);
		return $obj;
	}

	/**
	 * Database instance
	 *
	 * @param string $instance
	 *
	 * @return \PDO
	 */
	static function db($instance = NULL) {
		if (is_null($instance)) {
			$instance = self::$__current_pdo_instance;
		}

		return self::$__pdo_instances[$instance]['pdo'];
	}

	/**
	 * Get map instance
	 *
	 * @param null $instance
	 *
	 * @return divORM
	 */
	static function map($instance = NULL) {
		if (is_null($instance)) {
			$instance = self::$__current_map_instance;
		}

		return self::$__map_instances[$instance];
	}

	static function uniqueToken($tokenName) {
		if (!isset(self::$__tokens[$tokenName])) {
			self::$__tokens[$tokenName] = '{' . uniqid("token", TRUE) . '}';
		}
		return self::$__tokens[$tokenName];
	}

	public function clearTokens() {
		foreach (self::$__tokens as $tokenName => $token) {
			$this->__current_sql = str_replace($token, '', $this->__current_sql);
		}
	}

	/**
	 * Prepare statement
	 *
	 * @param $query
	 *
	 * @return PDOStatement
	 */
	public function prepare($query = NULL, $instance = NULL) {
		if (is_null($query)) {
			$query = $this->__current_sql;
		}

		$this->__current_sql = str_replace(self::uniqueToken('{selectAll}'), ' * ', $this->__current_sql);

		$this->clearTokens();

		return self::db($instance)->prepare($query);
	}

	/**
	 * Generic INSERT
	 *
	 * @param $tableName
	 * @param $fields
	 * @param PDOStatement $st
	 *
	 * @throws Exception
	 * @return bool
	 */
	public function insert($tableName, $fields, PDOStatement &$st = NULL) {
		$sqlFields = implode(',', array_keys($fields));
		$sqlValues = '?' . str_repeat(",?", count($fields) - 1);
		$sql       = "INSERT INTO $tableName ($sqlFields) VALUES ($sqlValues);";
		$st        = $this->db()->prepare($sql);
		$result    = $st->execute(array_values($fields));

		if ($result === FALSE) {
			throw new Exception($st->errorInfo(), $st->errorCode());
		}

		return $result;
	}

	/**
	 * SELECT
	 *
	 * @param string $fields
	 *
	 * @return $this
	 */
	public function select($fields = "*") {
		if ($fields == "*") {
			$fields = self::uniqueToken('{selectAll}');
		}

		$this->__current_sql = "SELECT $fields ";
		return $this;
	}

	/**
	 * SELECT FROM
	 *
	 * @param $from
	 *
	 * @return $this
	 */
	public function from($from) {
		$this->__current_sql .= " FROM $from ";
		return $this;
	}

	/**
	 * SELECT WHERE
	 *
	 * @param $where
	 *
	 * @return $this
	 */
	public function where($where) {
		$this->__current_sql .= " WHERE $where ";
		return $this;
	}

	public function designTable($tableName) {
		$this->__current_sql = "CREATE TABLE $tableName (";
		return $this;
	}

	/**
	 * Field design
	 *
	 * @param $fieldName
	 * @param $type
	 *
	 * @return $this
	 */
	public function addField($fieldName, $type) {
		$this->__current_sql .= " $fieldName $type,";
		return $this;
	}

	/**
	 * Default for field
	 *
	 * @param $default
	 *
	 * @return $this
	 */
	public function defaultValue($default) {
		$this->__current_sql = trim($this->__current_sql);
		if (substr($this->__current_sql, -1) == ",") {
			$this->__current_sql = substr($this->__current_sql, 0, strlen($this->__current_sql) - 1);
		}
		$this->__current_sql .= " DEFAULT $default ";
		return $this;
	}

	/**
	 * Finalize CREATE query and execute
	 *
	 * @return $this
	 */
	public function create() {
		$this->__current_sql = trim($this->__current_sql);

		if (substr($this->__current_sql, -1, 1) == ",") {
			$this->__current_sql = substr($this->__current_sql, 0, strlen($this->__current_sql) - 1);
		}

		$this->__current_sql .= ");";

		self::prepare()->execute();

		return $this;
	}

	/**
	 * DROP TABLE
	 *
	 * @param $tableName
	 *
	 * @return $this
	 */
	public function dropTable($tableName) {
		$this->__current_sql = 'DROP TABLE ' . self::uniqueToken('{ifExists}') . " $tableName ";
		return $this;
	}

	/**
	 * IF EXISTS
	 *
	 * @return $this
	 */
	public function ifExists() {
		$this->__current_sql = str_replace(self::uniqueToken('{ifExists}'), ' IF EXISTS ', $this->__current_sql);
		return $this;
	}

	/**
	 * CASCADE
	 *
	 * @return $this
	 */
	public function cascade() {
		$this->__current_sql .= ' CASCADE ';
		return $this;
	}

	/**
	 * Fetch all
	 *
	 * @param array $params
	 * @param null $className
	 * @param array $constructorArguments
	 *
	 * @return array
	 */
	public function fetchObjects($params = [], $className = NULL, $constructorArguments = []) {
		$properties = NULL;
		if (!is_null($className)) {
			$properties = [];
			$reflection = (new ReflectionClass($className))->getProperties(ReflectionProperty::IS_PUBLIC);
			foreach ($reflection as $ref) {
				$properties[] = $ref->name;
			}
		}

		if (!is_null($properties)) {
			$this->__current_sql = str_replace(self::uniqueToken('{selectAll}'), implode(',', $properties), $this->__current_sql);
		}

		$st = $this->prepare();
		$st->execute($params);

		if (is_null($className) && empty($constructorArguments)) {
			return $st->fetchAll(PDO::FETCH_OBJ);
		}


		$results = NULL;

		if (!is_null($className) && empty($constructorArguments)) {
			$results = $st->fetchAll(PDO::FETCH_CLASS, $className);
		}

		if (!is_null($className) && !empty($constructorArguments)) {
			$results = $st->fetchAll(PDO::FETCH_OBJ, $className, $constructorArguments);
		}

		if (!is_null($results) && !is_null($properties)) {
			foreach ($results as $result) {
				$vars = get_object_vars($result);
				foreach ($vars as $var => $value) {
					if (array_search($var, $properties) === FALSE) {
						unset($result->$var);
					}
				}
			}
		}

		return $results;
	}


	/**
	 * Fetch one
	 *
	 * @param array $params
	 * @param null $className
	 * @param array $constructorArguments
	 *
	 * @return array
	 */
	public function fetchObject($params = [], $className = NULL, $constructorArguments = []) {
		$properties = NULL;
		if (!is_null($className)) {
			$properties = [];
			$reflection = (new ReflectionClass($className))->getProperties(ReflectionProperty::IS_PUBLIC);
			foreach ($reflection as $ref) {
				$properties[] = $ref->name;
			}
		}

		if (!is_null($properties)) {
			$this->__current_sql = str_replace(self::uniqueToken('{selectAll}'), implode(',', $properties), $this->__current_sql);
		}

		$st = $this->prepare();
		$st->execute($params);

		if (is_null($className) && empty($constructorArguments)) {
			return $st->fetchObject();
		}

		$result = NULL;

		if (!is_null($className) && empty($constructorArguments)) {
			$result = $st->fetchObject($className);
		}

		if (!is_null($className) && !empty($constructorArguments)) {
			$result = $st->fetchObject($className, $constructorArguments);
		}

		if (!is_null($result) && !is_null($properties)) {
			$vars = get_object_vars($result);
			foreach ($vars as $var => $value) {
				if (array_search($var, $properties) === FALSE) {
					unset($result->$var);
				}
			}
		}

		return $result;
	}
}