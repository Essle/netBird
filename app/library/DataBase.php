<?php

namespace App;

/**
 * netBird/DataBase
 *
 * Модуль для работы с базой данных
 * 
 * @package netBird
 * @author Essle Jaxcate <me@essle.ru>
 * @copyright Copyright (c) 2013 netBird, Inc
 */

class DataBase {
	
	/**
	 * Префикс таблиц базы данных
	 *
	 * @var string
	 */
	public $prefix;	

	/**
	 * Экземпляр PDO
	 *
	 * @var PDO
	 */
	private $model;

	/**
	 * Массив методов, поддерживающих расширение
	 *
	 * @var array
	 */
	private const EXPANSIONS = [
		'insert',
		'select',
		'selectOnce',
		'delete',
		'counted',
		'update'
	];

	/**
	 * Подключение к базе данных
	 * 
	 * @param array $data - массив параметров для подключения
	 * @return void
	 */
	public function connect(array $data) : void {

		try {

			$this->prefix = $data['prefix'] ?? '';

			$this->model = new \PDO('mysql:host=' . $data['host'], $data['user'] ?? 'root', $data['password'] ?? '');

			if(!$this->isDatabaseExists($data['database'])) {
				$this->createDatabase($data['database']);
			}

			$this->model->exec('USE `' . $data['database'] . '`');
			$this->model->exec('SET NAMES \'utf8\'');
			$this->model->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
			$this->model->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_WARNING);
			
		} catch(Exception $e) {
			throw new EngineException($e->getMessage());
		}

	}

	/**
	 * Проверка на существование базы данных
	 * 
	 * @param string $database - название базы данных
	 * @return bool
	 */
	public function isDatabaseExists(string $database) : bool {

		$sql = $this->model->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?');
		$sql->execute([ 
			$database 
		]);
		return (bool)$sql->fetchColumn();

	}

	/**
	 * Создание базы данных
	 * 
	 * @param string $database - название базы данных
	 * @return bool
	 */
	public function createDatabase(string $database) : void {

		$this->model->exec('CREATE DATABASE `' . $database . '`');

	}

	/**
	 * Проверка на существование таблицы
	 * 
	 * @param string $table - название таблицы
	 * @return bool
	 */
	public function isTableExists(string $table) : bool {

		$sql = $this->model->prepare('SHOW TABLES LIKE ?');
		$sql->execute([ 
			$this->prefix . $table 
		]);
		return ($sql->rowCount() > 0);

	}

	/**
	 * Создание таблицы
	 * 
	 * @param string $table - название таблицы
	 * @param array $items - массив локальных данных столбцов
	 * @return void
	 */
	public function createTable(string $table, array $items) : void {

		$uniq = false;
		$sql = 'CREATE TABLE `' . $this->prefix . $table . '` (';

		foreach ($items as $key => $data) {
			if(isset($data['uniq']) && $data['uniq']) {
				$uniq = $key;
			}
			$sql .= '`' . $key . '` ' . self::getFieldType($data) . ',';
		}

		if($uniq) {
			$sql .= 'PRIMARY KEY (`' . $uniq . '`)';
		}

		$sql .= ') ENGINE=MyISAM DEFAULT CHARSET=utf8;';

		$this->model->exec($sql);

	}

	/**
	 * Добавление столбца в таблицу
	 * 
	 * @param string $table - название таблицы
	 * @param string $field - название столбца
	 * @param array $data - локальные данные столбца
	 * @return void
	 */
	public function addTableField(string $table, string $name, array $data) : void {

		$sql = $this->model->prepare('ALTER TABLE `' . $this->prefix . $table . '` ADD `' . $name . '` ' . self::getFieldType($data));
		$sql->execute();

	}

	/**
	 * Удаление столбца таблицы
	 * 
	 * @param string $table - название таблицы
	 * @param string $field - название столбца
	 * @return void
	 */
	public function removeTableField(string $table, string $field) : void {

		$sql = $this->model->prepare('ALTER TABLE `' . $this->prefix . $table . '` DROP COLUMN `' . $field . '`');
		$sql->execute();

	}

	/**
	 * Получение всех столбцов таблицы
	 * 
	 * @param string $table - название таблицы
	 * @return array
	 */
	public function getTableFields(string $table) : array {

		$sql = $this->model->prepare('SHOW COLUMNS FROM ' . $this->prefix . $table);
		$sql->execute();

		$data = $sql->fetchAll();
		$list = [];
		foreach($data as $value) {
			$list[] = $value['Field'];
		}

		return $list;

	}

	/**
	 * Проверка на существование в таблице столбца
	 * 
	 * @param string $table - название таблицы
	 * @param string $field - название столбца
	 * @return bool
	 */
	public function isTableFieldExists(string $table, string $field) : bool {

		$sql = $this->model->prepare('SELECT column_name FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = ? AND column_name = ?');
		$exists = $sql->execute([ 
			$this->prefix . $table,
			$field
		]);

		return ($sql->rowCount() != 0);

	}

	/**
	 * Проверка расширяемости метода
	 * 
	 * @param string $method - название метода
	 * @return bool
	 */
	public static function isExpansion(string $method) : bool {

		return in_array($method, self::EXPANSIONS);

	}

	/**
	 * Получение экземпляра PDO
	 * 
	 * @return PDO
	 */
	public function pdo() : PDO {

		return $this->model;

	}

	/**
	 * Выполнение sql-запроса
	 * 
	 * @param string $sql - запрос
	 * @param array $values - массив встраиваемых значений
	 * @return PDOStatement
	 */
	public function sql(string $sql, array $values = []) : \PDOStatement {

		$sql = $this->model->prepare($sql);
		$sql->execute($values);

		return $sql;

	}

	/**
	 * Добавление записей в таблицу
	 * 
	 * @param string $table - название таблицы
	 * @param array $data - массив данных (столбец => значение)
	 * @return int
	 */
	public function insert(string $table, array $values) : int {

		if(empty($values[0])) {
			$values = [ $values ];
		}

		foreach ($values as $data) {
		
			$fields = [];
			$values = [];
			$mask = [];

			foreach($data as $key => $value) {
				$values[] = $value;
				$fields[] = '`' . $key . '`';
				$mask[] = '?';
			}

			$this->sql('INSERT INTO `' . $this->prefix . $table .'` (' . implode($fields, ', ') . ') VALUES (' . implode($mask, ', ') . ')', $values);

		}

		return $this->model->lastInsertId();

	}

	/**
	 * Выборка записей из таблицы
	 * 
	 * @param string $table - название таблицы
	 * @param string $fields - перечень полей
	 * @param string $sql - sql-постфикс
	 * @param array $values - массив встраиваемых значений
	 * @return array
	 */
	public function select(string $table, string $fields = '*', string $sql = '', array $values = [], int &$count = NULL) : array {

		$sql = $this->sql('SELECT ' . $fields . ' FROM `' . $this->prefix . $table . '` ' . $sql, $values);
		if(is_int($count)) {
			$count = $sql->rowCount();
		}

		return $sql->fetchAll();

	}

	/**
	 * Выборка одной записи из таблицы
	 * 
	 * @param string $table - название таблицы
	 * @param string $fields - перечень полей
	 * @param string $sql - sql-постфикс
	 * @param array $values - массив встраиваемых значений
	 * @return array
	 */
	public function selectOnce(string $table, string $fields = '*', string $sql = '', array $values = []) : ?array {
		
		$sql = $this->sql('SELECT ' . $fields . ' FROM `' . $this->prefix . $table . '` ' . $sql . ' LIMIT 1', $values);
		
		return $sql->fetchAll()[0] ?? null;

	}

	/**
	 * Удаление записей из таблицы
	 * 
	 * @param string $table - название таблицы
	 * @param string $sql - sql-постфикс
	 * @param array $values - массив встраиваемых значений
	 * @return void
	 */
	public function delete(string $table, string $sql = '', array $values = []) : void {

		$this->sql('DELETE FROM `' . $this->prefix . $table . '` ' . $sql, $values);

	}

	/**
	 * Подсчет количества записей в таблице
	 * 
	 * @param string $table - название таблицы
	 * @param string $sql - sql-постфикс
	 * @param array $values - массив встраиваемых значений
	 * @return int
	 */
	public function counted(string $table, string $sql = '', array $values = []) : int {

		$sql = $this->sql('SELECT COUNT(*) FROM `' . $this->prefix . $table . '` ' . $sql, $values);
		
		return $sql->fetchColumn();

	}

	/**
	 * Обновление значений в заданных столбцах таблицы
	 * 
	 * @param string $table - название таблицы
	 * @param array $data - массив данных (столбец => значение)
	 * @param string $sql - sql-постфикс
	 * @param array $values - массив встраиваемых значений
	 * @return void
	 */
	public function update(string $table, array $data, string $sql = '', array $values = []) : void {

		$updates = [];
		$params = [];
		foreach($data as $key => $value) {
			$updates[] = $value;
			$params[] = '`' . $key . '` = ?';
		}

		$sql = $this->sql('UPDATE `' . $this->prefix . $table . '` SET ' . implode($params, ', ') . $sql, array_merge($updates, $values));

	}

	/**
	 * Sql-постфикс сортировки
	 * 
	 * @param string $field - название столбца
	 * @param bool $asc - режим сортировки
	 * @return string
	 */
	public static function order(string $field, bool $asc = false) : string {

		$type = ($asc ? 'ASC' : 'DESC');

		return ' ORDER BY `' . str_replace([ ' ', ',' ], [ '', '` ' . $type . ', `' ], $field) . '` ' . $type;

	}

	/**
	 * Sql-постфикс группировки
	 * 
	 * @param string $field - название столбца
	 * @return string
	 */
	public static function group(string $field) : string {

		return ' GROUP BY `' . $field . '`';

	}

	/**
	 * Sql-постфикс лимитирования
	 * 
	 * @param mixed $limit - лимит записей
	 * @param int $offset - индекс начала выборки
	 * @return string
	 */
	public static function limit($limit, int $offset = null) : string {

		if(is_array($limit)) {
			$offset = $limit[1];
			$limit = $limit[0];
		}

		return ' LIMIT ' . (!is_null($offset) ? $limit . ',' . $offset : $limit);

	}

	/**
	 * Получение реального типа столбца
	 * 
	 * @param array $data - локальные данные столбца
	 * @return string
	 */
	private static function getFieldType(array $data) : string {

		if(strtoupper($data['type']) == 'AI' || strtoupper($data['type']) == 'AUTO_INCREMENT') {
			return "int(11) NOT NULL AUTO_INCREMENT";
		} else {
			return $data['type'] ." NOT NULL" . (isset($data['default']) ? " DEFAULT '" . $data['default'] . "'" : "");
		}

	}

}