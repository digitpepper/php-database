<?php

declare(strict_types = 1);

namespace DP;

use PDO;
use PDOStatement;
use Exception;

class Database
{
	/**
	 * @var bool
	 */
	public static $is_constructed = false;

	/** @var PDO $pdo */
	public static $pdo = null;

	/**
	 * @var array<int, int|string>
	 */
	protected static $options = [];

	/**
	 * @var string
	 */
	protected static $host = null;

	/**
	 * @var string
	 */
	protected static $unix_socket = null;

	/**
	 * @var string
	 */
	protected static $name = null;

	/**
	 * @var string
	 */
	protected static $user = null;

	/**
	 * @var string
	 */
	protected static $password = null;

	public static function construct(): void
	{
		if (self::$is_constructed) {
			return;
		}
		$options = [
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
			PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4, SESSION sql_mode="ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION"',
		];
		if (\defined('\DB_OPTIONS')) {
			$options = array_replace($options, \DB_OPTIONS);
		}
		self::$options = $options;
		self::$host = \defined('\DB_HOST') ? \DB_HOST : null;
		self::$unix_socket = \defined('\DB_UNIX_SOCKET') ? \DB_UNIX_SOCKET : null;
		self::$name = \DB_NAME;
		self::$user = \DB_USER;
		self::$password = \DB_PASSWORD;
		self::connect();
		\register_shutdown_function([self::class, 'shutdown_function']);
		self::$is_constructed = true;
	}

	public static function begin(): void
	{
		self::construct();
		if (!self::$pdo->beginTransaction()) {
			throw new Exception('PDO cannot begin transaction.');
		}
	}

	public static function commit(): void
	{
		self::construct();
		if (!self::$pdo->commit()) {
			throw new Exception('PDO cannot commit transaction.');
		}
	}

	public static function roll_back(): void
	{
		self::construct();
		if (!self::$pdo->rollBack()) {
			throw new Exception('PDO cannot roll back.');
		}
	}

	/**
	 * @param string $sql
	 * @param array <string, string|int|null|bool> $data
	 * @param array <string, int> $data_types
	 * @return PDOStatement
	 * @throws Exception
	 */
	public static function prepare_bind_execute(string $sql, array $data = [], array $data_types = []): PDOStatement
	{
		self::construct();
		$sth = self::$pdo->prepare($sql);
		if (!$sth) {
			$error_info = self::$pdo->errorInfo();
			throw new Exception("SQLSTATE: {$error_info[0]}, error code: {$error_info[1]}, error string: {$error_info[2]}");
		}
		if ($data) {
			foreach ($data as $key => $value) {
				if (isset($data_types[$key])) {
					$data_type = $data_types[$key];
				} else {
					switch (\gettype($value)) {
						case 'string':
							$data_type = PDO::PARAM_STR;
							break;
						case 'integer':
							$data_type = PDO::PARAM_INT;
							break;
						case 'NULL':
							$data_type = PDO::PARAM_NULL;
							break;
						case 'boolean':
							$data_type = PDO::PARAM_BOOL;
							break;
						default:
							$data_type = PDO::PARAM_STR;
					}
				}
				if (!$sth->bindValue($key, $value, $data_type)) {
					throw new Exception('PDO statement cannot bind value.');
				}
			}
		}
		if (!$sth->execute()) {
			throw new Exception('SQL statement cannot be executed.');
		}
		return $sth;
	}

	public static function shutdown_function(): void
	{
		$pdo = self::$pdo;
		if ($pdo && $pdo->inTransaction()) {
			self::roll_back();
		}
	}

	protected static function connect(): void
	{
		$dsn = 'mysql:';
		$host = self::$host;
		$unix_socket = self::$unix_socket;
		if ($host) {
			$dsn .= "host=$host";
		} else if ($unix_socket) {
			$dsn .= "unix_socket=$unix_socket";
		}
		self::$pdo = new PDO("$dsn;dbname=" . self::$name, self::$user, self::$password, self::$options);
	}
}
