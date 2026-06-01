<?php
/**
 * Helper function to check if a database table exists
 * 
 * @param PDO $pdo Database connection
 * @param string $tableName Name of the table to check
 * @return bool True if table exists, false otherwise
 */
function tableExists($pdo, $tableName) {
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE :table_name");
        $stmt->bindParam(':table_name', $tableName, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Error checking table existence: " . $e->getMessage());
        return false;
    }
}

/**
 * Helper function to safely execute a query and return a PDOStatement or null
 * 
 * @param PDO $pdo Database connection
 * @param string $query SQL query to execute
 * @param array $params Parameters to bind
 * @return PDOStatement|null Returns statement on success, null on failure
 */
function safeQuery($pdo, $query, $params = []) {
    try {
        $stmt = $pdo->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt;
    } catch (PDOException $e) {
        error_log("Query execution failed: " . $e->getMessage() . " | Query: " . $query);
        return null;
    }
}

/**
 * Helper function to create an empty result set that behaves like a PDOStatement
 * 
 * @return object Empty result set object with rowCount() method
 */
function createEmptyResultSet() {
    return new class {
        public function fetch($fetchStyle = PDO::FETCH_ASSOC) {
            return false;
        }
        
        public function fetchAll($fetchStyle = PDO::FETCH_ASSOC) {
            return [];
        }
        
        public function rowCount() {
            return 0;
        }
        
        public function fetchColumn($columnNumber = 0) {
            return false;
        }
    };
}
?>
