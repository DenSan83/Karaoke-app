<?php
require_once 'app/Services/Database.php';
header('Content-Type: text/plain');

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    echo "Database connection: OK\n";
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'visit_logs'");
    if ($stmt->rowCount() > 0) {
        echo "Table 'visit_logs': EXISTS\n";
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM visit_logs");
        echo "Total visits: " . $stmt->fetchColumn() . "\n";
        
        $stmt = $pdo->query("SELECT * FROM visit_logs ORDER BY timestamp DESC LIMIT 5");
        echo "\nLast 5 visits:\n";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "ID: {$row['id']} | Time: {$row['timestamp']} | Page: {$row['page']}\n";
        }
    } else {
        echo "Table 'visit_logs': NOT FOUND\n";
        echo "Attempting to create table...\n";
        
        $sql = "CREATE TABLE IF NOT EXISTS `visit_logs` (
            id INT AUTO_INCREMENT PRIMARY KEY,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            page VARCHAR(255) NOT NULL,
            data LONGTEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        
        $pdo->exec($sql);
        echo "Table creation attempt finished. Please refresh this page.\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
