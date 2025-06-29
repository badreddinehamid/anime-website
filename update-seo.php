<?php
session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $seo_db = new SQLite3('seo.db');
        
        $anime_id = $seo_db->escapeString($_POST['anime_id']);
        $meta_title = $seo_db->escapeString($_POST['meta_title']);
        $meta_description = $seo_db->escapeString($_POST['meta_description']);
        $keywords = $seo_db->escapeString($_POST['keywords']);
        
        $seo_db->exec("
            UPDATE anime_seo 
            SET meta_title = '$meta_title',
                meta_description = '$meta_description',
                keywords = '$keywords',
                updated_at = datetime('now')
            WHERE anime_id = '$anime_id'
        ");
        
        header('Location: dashboard_seo.php?message=updated');
        exit;
    } catch (Exception $e) {
        header('Location: dashboard_seo.php?error=update_failed');
        exit;
    }
}