<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/xml; charset=utf-8');

try {
    $db = new SQLite3('anime_database.db');
    $seo_db = new SQLite3('seo.db'); // إضافة اتصال لقاعدة بيانات SEO
    $today = date('c');
    $output = '';
    
    // بداية XML
    $output .= '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
    $output .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;
    
    // الصفحة الرئيسية
    $output .= '<url>' . PHP_EOL;
    $output .= '    <loc>https://anime-world.site/</loc>' . PHP_EOL;
    $output .= '    <lastmod>' . $today . '</lastmod>' . PHP_EOL;
    $output .= '    <changefreq>daily</changefreq>' . PHP_EOL;
    $output .= '    <priority>1.0</priority>' . PHP_EOL;
    $output .= '</url>' . PHP_EOL;

    // صفحات الأنمي مع التفاصيل
    $anime_query = "SELECT a.id, a.title, a.story, a.year, a.type, a.status,
                           GROUP_CONCAT(g.genre) as genres
                    FROM anime a
                    LEFT JOIN genres g ON a.id = g.anime_id
                    GROUP BY a.id
                    ORDER BY a.id DESC";
    
    $result = $db->query($anime_query);
    
    if ($result === false) {
        throw new Exception($db->lastErrorMsg());
    }
    
    while ($anime = $result->fetchArray(SQLITE3_ASSOC)) {
        // جلب بيانات SEO للأنمي الحالي
        $seo_data = $seo_db->querySingle("
            SELECT meta_title, meta_description, keywords 
            FROM anime_seo 
            WHERE anime_id = " . $anime['id'],
            true
        );

        $title_slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($anime['title']));
        $title_slug = trim($title_slug, '-');
        
        $output .= '<url>' . PHP_EOL;
        $output .= '    <loc>https://anime-world.site/details.php?id=' . htmlspecialchars($anime['id']) . 
                  '&amp;title=' . urlencode($title_slug) . 
                  '&amp;type=' . urlencode($anime['type']) . 
                  '&amp;year=' . urlencode($anime['year']);

        // إضافة meta description و keywords كمعلمات URL
        if (!empty($seo_data['meta_description'])) {
            $output .= '&amp;desc=' . urlencode(substr($seo_data['meta_description'], 0, 150));
        } elseif (!empty($anime['story'])) {
            $output .= '&amp;desc=' . urlencode(substr($anime['story'], 0, 150));
        }

        if (!empty($seo_data['keywords'])) {
            $output .= '&amp;tags=' . urlencode($seo_data['keywords']);
        } elseif (!empty($anime['genres'])) {
            $output .= '&amp;tags=' . urlencode($anime['genres']);
        }

        $output .= '</loc>' . PHP_EOL;
        $output .= '    <lastmod>' . $today . '</lastmod>' . PHP_EOL;
        $output .= '    <changefreq>weekly</changefreq>' . PHP_EOL;
        $output .= '    <priority>0.8</priority>' . PHP_EOL;
        $output .= '</url>' . PHP_EOL;
    }
    // صفحات الحلقات مع معلومات الأنمي
    $episodes_query = "SELECT e.id, e.title as episode_title, e.anime_id,
                             a.title as anime_title, a.story, a.type, a.year
                      FROM episodes e
                      LEFT JOIN anime a ON e.anime_id = a.id
                      ORDER BY e.id DESC";
    
    $episodes_result = $db->query($episodes_query);  // Changed variable name to avoid conflict
    
    if ($episodes_result === false) {
        throw new Exception($db->lastErrorMsg());
    }
    
    while ($episode = $episodes_result->fetchArray(SQLITE3_ASSOC)) {
        $anime_title_slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($episode['anime_title']));
        $anime_title_slug = trim($anime_title_slug, '-');
        
        $output .= '<url>' . PHP_EOL;
        $output .= '    <loc>https://anime-world.site/watch2.php?id=' . htmlspecialchars($episode['id']) . 
                  '&amp;anime=' . urlencode($anime_title_slug) . 
                  '&amp;ep=' . htmlspecialchars($episode['episode_title']) . 
                  '&amp;type=' . urlencode($episode['type']) . 
                  '&amp;year=' . urlencode($episode['year']);

        if (!empty($episode['story'])) {
            $output .= '&amp;desc=' . urlencode(substr($episode['story'], 0, 150));
        }
        
        $output .= '</loc>' . PHP_EOL;
        $output .= '    <lastmod>' . $today . '</lastmod>' . PHP_EOL;
        $output .= '    <changefreq>monthly</changefreq>' . PHP_EOL;
        $output .= '    <priority>0.6</priority>' . PHP_EOL;
        $output .= '</url>' . PHP_EOL;
    }
    // الصفحات الثابتة
    $output .= '<url>' . PHP_EOL;
    $output .= '    <loc>https://anime-world.site/search.php</loc>' . PHP_EOL;
    $output .= '    <lastmod>' . $today . '</lastmod>' . PHP_EOL;
    $output .= '    <changefreq>weekly</changefreq>' . PHP_EOL;
    $output .= '    <priority>0.7</priority>' . PHP_EOL;
    $output .= '</url>' . PHP_EOL;

    $output .= '</urlset>';
    
    ob_clean();
    echo trim($output);
    $db->close();
    $seo_db->close(); // إغلاق اتصال قاعدة بيانات SEO
    $db->close();
    $seo_db->close(); // إغلاق اتصال قاعدة بيانات SEO

} catch (Exception $e) {
    error_log("Sitemap Error: " . $e->getMessage());
    
    ob_clean();
    $output = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
    $output .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;
    $output .= '<url>' . PHP_EOL;
    $output .= '    <loc>https://anime-world.site/</loc>' . PHP_EOL;
    $output .= '    <lastmod>' . date('c') . '</lastmod>' . PHP_EOL;
    $output .= '    <changefreq>daily</changefreq>' . PHP_EOL;
    $output .= '    <priority>1.0</priority>' . PHP_EOL;
    $output .= '</url>' . PHP_EOL;
    $output .= '</urlset>';
    
    echo trim($output);
}

ob_end_flush();
?>