<?php
/**
 * Companero Resort — Database Connection
 * Centralized PDO connection using prepared statements
 */

require_once __DIR__ . '/config.php';

class Database {
    private static $instance = null;
    private $connection;
    private $error;

    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
            $this->connection = new PDO($dsn, DB_USER, DB_PASSWORD, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            // Log error but don't expose to user
            error_log('Database Connection Error: ' . $e->getMessage());
            $this->error = 'Database connection failed. Please try again later.';
            die($this->error);
        }
    }

    /**
     * Get singleton database instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get PDO connection
     */
    public function getConnection() {
        return $this->connection;
    }

    /**
     * Execute prepared statement safely
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log('Query Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get single row
     */
    public function fetch($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt ? $stmt->fetch() : null;
    }

    /**
     * Get all rows
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt ? $stmt->fetchAll() : [];
    }

    /**
     * Insert and return last ID
     */
    public function insert($table, $data) {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
        
        $stmt = $this->query($sql, array_values($data));
        return $stmt ? $this->connection->lastInsertId() : false;
    }

    /**
     * Update records
     */
    public function update($table, $data, $where, $whereParams = []) {
        $set = implode(', ', array_map(function($key) { return "$key = ?"; }, array_keys($data)));
        $sql = "UPDATE $table SET $set WHERE $where";
        
        $params = array_merge(array_values($data), $whereParams);
        return $this->query($sql, $params);
    }

    /**
     * Delete records
     */
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM $table WHERE $where";
        return $this->query($sql, $params);
    }
}

// Get global database instance
$db = Database::getInstance();
