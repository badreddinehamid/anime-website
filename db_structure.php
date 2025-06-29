<?php
try {
    $db = new SQLite3('anime_database.db');
    
    // Get list of tables
    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'");
    
    while ($table = $tables->fetchArray(SQLITE3_ASSOC)) {
        $tableName = $table['name'];
        echo "<h3>Table: {$tableName}</h3>";
        
        // Get columns info for each table
        $columns = $db->query("PRAGMA table_info('{$tableName}')");
        
        echo "<table border='1' style='margin-bottom: 20px;'>";
        echo "<tr><th>Column Name</th><th>Type</th><th>Not Null</th><th>Default Value</th><th>Primary Key</th></tr>";
        
        while ($column = $columns->fetchArray(SQLITE3_ASSOC)) {
            echo "<tr>";
            echo "<td>{$column['name']}</td>";
            echo "<td>{$column['type']}</td>";
            echo "<td>{$column['notnull']}</td>";
            echo "<td>{$column['dflt_value']}</td>";
            echo "<td>{$column['pk']}</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    }
    
    $db->close();
    
} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
}
?>