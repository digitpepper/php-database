<?php

declare(strict_types = 1);

namespace DP;

use PDO;
use PDOStatement;
use Exception;

class Database
{
	/**
	 * @var array <string, array{dsn: string, username: string, password: string, options?: array<int, mixed>}>
	 */
	protected static $configs = [];

	/**
	 * @var array <string, Database>
	 */
	protected static $instances = [];

	/**
	 * @var PDO
	 */
	public $pdo = null;

	/**
	 * @var array <int, mixed>
	 */
	public static $default_options = [
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
		PDO::MYSQL_ATTR_INIT_COMMAND => 'SET SESSION sql_mode="ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION"',
	];

	/**
	 * Database constructor.
	 * @param array{dsn: string, username: string, password: string, options?: array<int, mixed>} $config
	 */
	protected function __construct(array $config)
	{
		$options = self::$default_options;
		if (isset($config['options'])) {
			$options = array_replace($options, $config['options']);
		}
		$this->pdo = new PDO($config['dsn'], $config['username'], $config['password'], $options);
		\register_shutdown_function([$this, 'shutdown_function']);
	}

	/**
	 * @param array{dsn: string, username: string, password: string, options?: array<int, mixed>} $config
	 * @param string $name
	 */
	public static function set_config(array $config, string $name = 'main'): void
	{
		self::$configs[$name] = $config;
	}

	public static function get_instance(string $name = 'main'): self
	{
		if (!isset(self::$instances[$name])) {
			self::$instances[$name] = new self(self::$configs[$name]);
		}
		return self::$instances[$name];
	}

	public function begin(): void
	{
		if (!$this->pdo->beginTransaction()) {
			throw new Exception('PDO cannot begin transaction.');
		}
	}

	public function commit(): void
	{
		if (!$this->pdo->commit()) {
			throw new Exception('PDO cannot commit transaction.');
		}
	}

	public function roll_back(): void
	{
		if (!$this->pdo->rollBack()) {
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
	public function prepare_bind_execute(string $sql, array $data = [], array $data_types = []): PDOStatement
	{
		$sth = $this->pdo->prepare($sql);
		if (!$sth) {
			$error_info = $this->pdo->errorInfo();
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

	public function shutdown_function(): void
	{
		if ($this->pdo->inTransaction()) {
			$this->roll_back();
		}
	}
}
