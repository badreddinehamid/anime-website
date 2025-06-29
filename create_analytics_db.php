<?php
try {
    $db = new SQLite3('analytics.db');
    
    // Create visitors table with better structure
    $db->exec('
        CREATE TABLE IF NOT EXISTS visitors (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ip_address TEXT,
            user_agent TEXT,
            page_url TEXT,
            referer TEXT,
            visit_time DATETIME DEFAULT CURRENT_TIMESTAMP,
            session_id TEXT,
            visit_date DATE DEFAULT CURRENT_DATE,
            unique_visitor_id TEXT
        )
    ');

    // Create index for better performance
    $db->exec('CREATE INDEX IF NOT EXISTS idx_visit_date ON visitors(visit_date)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_unique_visitor ON visitors(unique_visitor_id)');

    echo "Analytics database and tables created successfully";
    
} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
}
?>