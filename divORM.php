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

	public $__pdo = NULL;

	public $__config = NULL;

	public $__current_sql = NULL;

	/**
	 * divORM constructor.
	 *
	 * @param $config
	 */
	public function __construct($config) {
		$this->__config = $config;
	}

	/**
	 * Connect to db
	 *
	 * @param $config
	 */
	public function connect($config) {
		$this->__pdo = new PDO("{$config["type"]}:host={$config["host"]};port={$config["port"]};dbname={$config["name"]};", $config['user'], $config['pass']);
		$this->__pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	}

	/**
	 * Database instance
	 *
	 * @return PDO
	 */
	public function db() {
		if (is_null($this->__pdo)) {
			$this->connect($this->__config);
		}

		return $this->__pdo;
	}

	/**
	 * Prepare statement
	 *
	 * @param $query
	 *
	 * @return PDOStatement
	 */
	public function prepare($query = null) {
		if (is_null($query))
			$query = $this->__current_sql;
		return $this->db()->prepare($query);
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
	public function select($fields = "*"){
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

}