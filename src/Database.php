<?php
declare(strict_types=1);

namespace CubeSpace;

class Database {
    private static ?Database $instance = null;
    private \mysqli $conn;

    private string $host;
    private string $user;
    private string $pass;
    private string $name;
    private int $port;

    public function __construct() {
        $configFile = __DIR__ . '/../config/database.php';
        if (file_exists($configFile)) {
            require_once $configFile;
            $this->host = defined('DB_HOST') ? DB_HOST : '127.0.0.1';
            $this->user = defined('DB_USER') ? DB_USER : 'root';
            $this->pass = defined('DB_PASS') ? DB_PASS : '';
            $this->name = defined('DB_NAME') ? DB_NAME : 'cubespaces';
        } else {
            $this->host = getenv('DB_HOST') ?: '127.0.0.1';
            $this->user = getenv('DB_USER') ?: 'root';
            $this->pass = getenv('DB_PASS') ?: '';
            $this->name = getenv('DB_NAME') ?: 'cubespaces';
        }
        $this->port = (int)(getenv('DB_PORT') ?: 3306);

        $this->connect();
    }

    private function connect(): void {
        $this->conn = mysqli_connect($this->host, $this->user, $this->pass, $this->name, $this->port);
        if (!$this->conn) {
            throw new \RuntimeException('Database connection failed: ' . mysqli_connect_error());
        }
        mysqli_set_charset($this->conn, 'utf8mb4');
    }

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): \mysqli {
        if (!$this->conn || !$this->conn->ping()) {
            $this->connect();
        }
        return $this->conn;
    }

    public function prepare(string $sql): \mysqli_stmt|false {
        return mysqli_prepare($this->getConnection(), $sql);
    }

    public function query(string $sql): \mysqli_result|bool {
        return mysqli_query($this->getConnection(), $sql);
    }

    public function insertId(): int {
        return (int)mysqli_insert_id($this->getConnection());
    }

    public function affectedRows(): int {
        return (int)mysqli_affected_rows($this->getConnection());
    }

    public function beginTransaction(): bool {
        return mysqli_begin_transaction($this->getConnection());
    }

    public function commit(): bool {
        return mysqli_commit($this->getConnection());
    }

    public function rollback(): bool {
        return mysqli_rollback($this->getConnection());
    }

    public function __destruct() {
        if (isset($this->conn)) {
            mysqli_close($this->conn);
        }
    }
}
