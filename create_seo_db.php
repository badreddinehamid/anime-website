<?php
try {
    $db = new SQLite3('seo.db');
    
    // إنشاء جدول SEO للأنمي
    $db->exec('
        CREATE TABLE IF NOT EXISTS anime_seo (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            anime_id INTEGER NOT NULL,
            meta_title TEXT,
            meta_description TEXT,
            keywords TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(anime_id)
        )
    ');

    // إنشاء جدول SEO للحلقات
    $db->exec('
        CREATE TABLE IF NOT EXISTS episode_seo (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            episode_url TEXT NOT NULL,
            meta_title TEXT,
            meta_description TEXT,
            keywords TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(episode_url)
        )
    ');

    echo "تم إنشاء قاعدة البيانات وجداول SEO بنجاح";
    
} catch (Exception $e) {
    die("خطأ في إنشاء قاعدة البيانات: " . $e->getMessage());
}
?>