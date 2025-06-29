<?php
function trackVisit() {
    try {
        // Get current page URL
        $page_url = $_SERVER['REQUEST_URI'];
        
        // List of pages to track
        $allowed_pages = [
            '/',
            '/index.php',
            '/search.php',
            '/details.php',
            '/watch-episode.php',
            '/watch2.php'
        ];
        
        // Check if current page should be tracked
        $should_track = false;
        foreach ($allowed_pages as $allowed_page) {
            if ($page_url === $allowed_page || $page_url === $allowed_page . '/') {
                $should_track = true;
                break;
            }
        }
        
        // If page is not in allowed list, don't track
        if (!$should_track) {
            return;
        }

        $db = new SQLite3(__DIR__ . '/../analytics.db');
        
        $ip = $_SERVER['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
        
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $session_id = session_id();
        
        // Create a unique visitor ID based on IP and User Agent
        $unique_visitor_id = md5($ip . $user_agent);
        
        $stmt = $db->prepare('
            INSERT INTO visitors (
                ip_address,
                user_agent,
                page_url,
                referer,
                session_id,
                visit_date,
                unique_visitor_id
            ) VALUES (
                :ip,
                :user_agent,
                :page_url,
                :referer,
                :session_id,
                DATE("now"),
                :unique_visitor_id
            )
        ');

        $stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
        $stmt->bindValue(':user_agent', $user_agent, SQLITE3_TEXT);
        $stmt->bindValue(':page_url', $page_url, SQLITE3_TEXT);
        $stmt->bindValue(':referer', $referer, SQLITE3_TEXT);
        $stmt->bindValue(':session_id', $session_id, SQLITE3_TEXT);
        $stmt->bindValue(':unique_visitor_id', $unique_visitor_id, SQLITE3_TEXT);
        
        $stmt->execute();
        
    } catch (Exception $e) {
        error_log("Analytics Error: " . $e->getMessage());
    }
}
?>