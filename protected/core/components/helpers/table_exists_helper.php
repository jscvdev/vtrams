<?php

require_once __DIR__ . '/schema_cache_helper.inc.php';

/**
 * Helper function to check if a database table exists (request-cached).
 *
 * @param PDO $pdo Database connection
 * @param string $tableName Name of the table to check
 * @return bool True if table exists, false otherwise
 */
function tableExists($pdo, $tableName)
{
    return schema_table_exists($pdo, $tableName);
}

/**
 * Helper function to safely execute a query and return a PDOStatement or null
 *
 * @param PDO $pdo Database connection
 * @param string $query SQL query to execute
 * @param array $params Parameters to bind
 * @return PDOStatement|null Returns statement on success, null on failure
 */
function safeQuery($pdo, $query, $params = [])
{
    try {
        $stmt = $pdo->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        return $stmt;
    } catch (PDOException $e) {
        error_log('Query execution failed: ' . $e->getMessage() . ' | Query: ' . $query);

        return null;
    }
}

/**
 * Helper function to create an empty result set that behaves like a PDOStatement
 *
 * @return object Empty result set object with rowCount() method
 */
function createEmptyResultSet()
{
    return new class {
        public function fetch($fetchStyle = PDO::FETCH_ASSOC)
        {
            return false;
        }

        public function fetchAll($fetchStyle = PDO::FETCH_ASSOC)
        {
            return [];
        }

        public function rowCount()
        {
            return 0;
        }

        public function fetchColumn($columnNumber = 0)
        {
            return false;
        }
    };
}
