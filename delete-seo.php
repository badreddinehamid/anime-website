<?php
session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin.php');
    exit;
}

if (isset($_GET['id'])) {
    try {
        $seo_db = new SQLite3('seo.db');
        $id = $seo_db->escapeString($_GET['id']);
        
        $seo_db->exec("DELETE FROM anime_seo WHERE anime_id = '$id'");
        
        header('Location: dashboard_seo.php?message=deleted');
        exit;
    } catch (Exception $e) {
        header('Location: dashboard_seo.php?error=delete_failed');
        exit;
    }
}