<?php
// Suppress warnings for production
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

require_once 'includes/track_visit.php';
trackVisit();
try {
    // إنشاء اتصال مع قاعدة البيانات SQLite
    $db = new SQLite3('anime_episodes.db');

    // إنشاء جدول الخلفيات إذا لم يكن موجوداً
    $db->exec('
        CREATE TABLE IF NOT EXISTS backgrounds (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            image_url TEXT NOT NULL,
            anime_title TEXT NOT NULL,
            description TEXT,
            year TEXT,
            age_rating TEXT,
            season TEXT,
            match_rate TEXT,
            watch_url TEXT,
            active INTEGER DEFAULT 1
        )
    ');

    // التحقق من وجود خلفيات، وإضافة خلفية افتراضية إذا كان الجدول فارغاً
    $count = $db->querySingle('SELECT COUNT(*) FROM backgrounds');
    if ($count == 0) {
        $db->exec('
            INSERT INTO backgrounds (
                image_url, 
                anime_title, 
                description, 
                year, 
                age_rating, 
                season, 
                match_rate, 
                watch_url
            ) VALUES (
                "https://via.placeholder.com/1920x1080",
                "عنوان افتراضي",
                "وصف افتراضي للأنمي",
                "2024",
                "PG-13",
                "شتاء",
                "98%",
                "#"
            )
        ');
    }

    // جلب الخلفيات النشطة
    $backgrounds = $db->query('
        SELECT * FROM backgrounds 
        WHERE active = 1 
        ORDER BY RANDOM() 
        LIMIT 5
    ');

    // تحديد عدد العناصر في كل صفحة
    $per_page = 20;
    
    // الحصول على إجمالي عدد الحلقات
    $total_count = $db->querySingle('SELECT COUNT(*) FROM episodes');

    // حساب عدد الصفحات
    $total_pages = ceil($total_count / $per_page);

    // الحصول على رقم الصفحة الحالية من URL
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $page = max(1, min($page, $total_pages)); // تأكد من أن رقم الصفحة صحيح

    // حساب نقطة البداية بشكل صحيح للترتيب التنازلي
    $offset = ($page - 1) * $per_page;

    // استعلام لجلب أحدث الحلقات مع pagination
    $latest_episodes = $db->query("
        SELECT 
            episode_title,
            episode_url,
            anime_thumbnail as thumbnail,
            anime_title,
            anime_url,
            details,
            genres
        FROM episodes 
        ORDER BY id DESC  -- Changed from ASC to DESC to show newest first
        LIMIT $per_page OFFSET $offset
    ");

    if (!$latest_episodes) {
        throw new Exception("خطأ في استعلام أحدث الحلقات");
    }

    // استعلام لجلب الأنميات المميزة بشكل عشوائي
    $featured_anime = $db->query('
        SELECT DISTINCT 
            anime_title,
            anime_url,
            anime_thumbnail,
            genres
        FROM episodes 
        GROUP BY anime_title 
        ORDER BY RANDOM() -- ترتيب عشوائي
        LIMIT 10
    ');

    if (!$featured_anime) {
        throw new Exception("خطأ في استعلام الأنميات المميزة");
    }

    $slides = [];
    while ($bg = $backgrounds->fetchArray(SQLITE3_ASSOC)) {
        $slides[] = $bg;
    }
    // تعديل استعلام الأنمي المقترح ليستخدم قاعدة البيانات الجديدة
    $featured_anime = $db2 = new SQLite3('anime_database.db');
    $featured_results = $db2->query('
        SELECT 
            id as anime_id,
            title as anime_title,
            poster,
            year,
            episodes_count,
            GROUP_CONCAT(g.genre) as genres
        FROM anime a
        LEFT JOIN genres g ON g.anime_id = a.id
        GROUP BY a.id
        ORDER BY RANDOM()
        LIMIT 10
    ');
    if (!$featured_results) {
        throw new Exception("خطأ في استعلام الأنميات المقترحة");
    }

    // Language switcher logic
    $lang = isset($_GET['lang']) && $_GET['lang'] === 'ar' ? 'ar' : 'en';
    function t($en, $ar) {
        global $lang;
        return $lang === 'ar' ? $ar : $en;
    }
    // Get spotlight candidates from episodes database
    $spotlight_query = "
        SELECT 
            id as episode_id,
            episode_url,
            episode_title,
            anime_title,
            anime_thumbnail,
            thumbnail as episode_thumbnail,
            genres as episode_genres,
            details
        FROM episodes
        WHERE anime_title IS NOT NULL AND anime_title != ''
        ORDER BY id DESC
        LIMIT 5
    ";
    
    $spotlight_results = $db->query($spotlight_query);
    $spotlight_candidates = [];
    while ($row = $spotlight_results->fetchArray(SQLITE3_ASSOC)) {
        $spotlight_candidates[] = $row;
    }
    
    // Select spotlight anime (you can randomize this or use other criteria)
    $spotlight = !empty($spotlight_candidates) ? $spotlight_candidates[0] : null;
    
    // Enhance spotlight data with anime database information if available
    if ($spotlight) {
        try {
            $db2 = new SQLite3('anime_database.db');
            
            // Try to find matching anime in anime database
            $anime_query = "SELECT * FROM anime WHERE LOWER(TRIM(title)) LIKE LOWER(TRIM(?))";
            $stmt = $db2->prepare($anime_query);
            $stmt->bindValue(1, '%' . trim($spotlight['anime_title']) . '%');
            $anime_result = $stmt->execute();
            $anime_data = $anime_result->fetchArray(SQLITE3_ASSOC);
            
            if ($anime_data) {
                // Merge anime database data
                $spotlight['anime_id'] = $anime_data['id'];
                $spotlight['poster'] = $anime_data['poster'];
                $spotlight['status'] = $anime_data['status'];
                $spotlight['type'] = $anime_data['type'];
                $spotlight['year'] = $anime_data['year'];
                $spotlight['episodes_count'] = $anime_data['episodes_count'];
                $spotlight['episode_duration'] = $anime_data['episode_duration'];
                $spotlight['season'] = $anime_data['season'];
                $spotlight['story'] = $anime_data['story'];
                
                // Get genres from genres table
                $genres_query = "SELECT genre FROM genres WHERE anime_id = ?";
                $stmt2 = $db2->prepare($genres_query);
                $stmt2->bindValue(1, $anime_data['id']);
                $genres_result = $stmt2->execute();
                $anime_genres = [];
                while ($genre_row = $genres_result->fetchArray(SQLITE3_ASSOC)) {
                    $anime_genres[] = $genre_row['genre'];
                }
                $spotlight['anime_genres'] = implode(',', $anime_genres);
            }
            
            $db2->close();
        } catch (Exception $e) {
            // If anime database fails, continue with episode data only
            error_log("Anime database error: " . $e->getMessage());
        }
        
        // Parse details and get description
        $spotlight_details = [];
        $spotlight_description = '';
        
        // Try to get description from anime database first, then episode details
        if (!empty($spotlight['story'])) {
            $spotlight_description = $spotlight['story'];
        } elseif (!empty($spotlight['details'])) {
            $spotlight_details = json_decode($spotlight['details'], true);
            $spotlight_description = $spotlight_details['القصة'] ?? $spotlight_details['story'] ?? '';
        }
        
        // Parse genres from both sources
        $all_genres = [];
        if (!empty($spotlight['anime_genres'])) {
            $all_genres = array_merge($all_genres, explode(',', $spotlight['anime_genres']));
        }
        if (!empty($spotlight['episode_genres'])) {
            $episode_genres = json_decode($spotlight['episode_genres'], true);
            if (is_array($episode_genres)) {
                $all_genres = array_merge($all_genres, $episode_genres);
            }
        }
        $spotlight['combined_genres'] = array_unique(array_filter($all_genres));
    }
    // Get trending anime (latest 10)
    $trending = $db->query('SELECT id, episode_title, anime_title, thumbnail, episode_url FROM episodes ORDER BY id DESC LIMIT 10');
} catch (Exception $e) {
    die("خطأ في قاعدة البيانات: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" dir="<?php echo $lang === 'ar' ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('ANIME-WR | Best site to watch and download anime', 'ANIME-WR | أفضل موقع لمشاهدة وتحميل الأنمي'); ?></title>
    <meta name="description" content="شاهد أحدث حلقات الأنمي المترجمة اون لاين على ANIME-WR. أكبر موقع عربي لمشاهدة وتحميل الأنمي المترجم بجودة عالية HD. أنمي مترجم، أفلام أنمي، مسلسلات أنمي، أنمي اون لاين.">
    
    <!-- Primary Keywords -->
    <meta name="keywords" content="anime-wordl,animeworld,anime-world.site,أنمي, انمي مترجم, حلقات انمي, أنمي اون لاين, anime, anime online, مشاهدة انمي">
    
    <!-- Additional Keywords for Better SEO -->
    <meta name="keywords" content="anime-wordl,animeworld,anime-world.site,أفلام أنمي, مسلسلات أنمي, أنمي جديد, أنمي 2024, أنمي بجودة عالية, أنمي مدبلج, أنمي مترجم عربي, أنمي رومانسي, أنمي أكشن, أنمي مغامرات, أنمي خيال علمي, أنمي كوميدي, أنمي دراما">
    
    <!-- Popular Anime Keywords -->
    <meta name="keywords" content="anime-wordl,animeworld,anime-world.site,ناروتو, ون بيس, بوكيمون, دراغون بول, هجوم العمالقة, ديث نوت, بليتش, جوجوتسو كايسن">
    
    <!-- Seasonal Keywords -->
    <meta name="keywords" content="anime-wordl,animeworld,anime-world.site,أنمي ربيع 2024, أنمي شتاء 2024, أنمي صيف 2024, أنمي خريف 2024, مواسم الأنمي">
    
    <!-- Technical Keywords -->
    <meta name="keywords" content="anime-wordl,animeworld,anime-world.site,أنمي HD, أنمي بلوراي, تحميل أنمي, مشاهدة مباشرة, بدون إعلانات">

    <!-- الـ Meta Tags الموجودة مسبقاً -->
    <meta property="og:title" content="ANIME-WR | موقع مشاهدة الأنمي المترجم">
    <meta property="og:description" content="شاهد أحدث حلقات الأنمي المترجمة مباشرة على ANIME-WR">
    <meta property="og:image" content="<?php echo isset($background['image_url']) ? htmlspecialchars($background['image_url']) : '/images/default-og.jpg'; ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="ANIME-WR">
    
    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="ANIME-WR | موقع مشاهدة الأنمي المترجم">
    <meta name="twitter:description" content="شاهد أحدث حلقات الأنمي المترجمة مباشرة">
    
    <!-- Canonical URL -->
    <link rel="canonical" href="https://anime-world.site<?php echo $_SERVER['REQUEST_URI']; ?>">
    
    <!-- Structured Data -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebSite",
        "name": "ANIME-WR",
        "url": "https://anime-world.site",
        "description": "موقع مشاهدة الأنمي المترجم",
        "potentialAction": {
            "@type": "SearchAction",
            "target": "https://anime-world.site/search?q={search_term_string}",
            "query-input": "required name=search_term_string"
        }
    }
    </script>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="/favicon.png">
    <style>
        body { background: #232136; }
        .star-bg { background: url('https://static.vecteezy.com/system/resources/previews/022/832/888/original/galaxy-background-with-stars-cosmic-night-sky-illustration-free-vector.jpg') center/cover no-repeat; }
        .scrollbar-hide::-webkit-scrollbar { display: none; }
        .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .description-text.expanded {
            -webkit-line-clamp: unset;
        }
        .image-placeholder {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
    </style>
</head>
<body class="min-h-screen text-white">
    <!-- Navigation Bar -->
    <nav class="w-full bg-[#232136]/80 backdrop-blur-md shadow-lg sticky top-0 z-50">
        <div class="flex items-center justify-between px-4 py-2 h-16">
            <!-- Left Section: Menu & Logo -->
            <div class="flex items-center gap-4">
                <button id="menuToggle" class="text-white hover:text-pink-400 text-xl lg:hidden">
                    <i class="fas fa-bars"></i>
                </button>
                <a href="index.php" class="text-2xl font-bold select-none">
                    h<span class="text-pink-400">!</span>anime
                </a>
            </div>

            <!-- Center Section: Search Bar -->
            <div class="flex-1 max-w-2xl mx-6">
                <form action="search.php" method="GET" class="flex items-center">
                    <div class="relative flex-1">
                        <input type="text" name="q" 
                               placeholder="<?php echo t('Search anime...', 'ابحث عن أنمي...'); ?>" 
                               class="w-full px-4 py-2 pr-20 text-gray-800 bg-white rounded-full focus:outline-none focus:ring-2 focus:ring-pink-400 text-sm" />
                        <div class="absolute right-2 top-1/2 -translate-y-1/2 flex items-center gap-1">
                            <button type="button" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-3 py-1 rounded-full text-xs font-medium transition">
                                <i class="fas fa-filter mr-1"></i>Filter
                            </button>
                            <button type="submit" class="bg-pink-400 hover:bg-pink-500 text-white w-8 h-8 rounded-full flex items-center justify-center transition">
                                <i class="fas fa-search text-sm"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Right Section: Icons & Actions -->
            <div class="flex items-center gap-3">
                <!-- Join Now Button -->
                <div class="hidden md:flex items-center gap-2 text-xs">
                    <span class="text-gray-300">Join now</span>
                    <div class="flex gap-1">
                        <a href="#" class="w-8 h-8 bg-blue-600 hover:bg-blue-700 rounded-full flex items-center justify-center transition">
                            <i class="fab fa-discord text-white text-sm"></i>
                        </a>
                        <a href="#" class="w-8 h-8 bg-blue-500 hover:bg-blue-600 rounded-full flex items-center justify-center transition">
                            <i class="fab fa-telegram text-white text-sm"></i>
                        </a>
                        <a href="#" class="w-8 h-8 bg-red-600 hover:bg-red-700 rounded-full flex items-center justify-center transition">
                            <i class="fab fa-reddit text-white text-sm"></i>
                        </a>
                        <a href="#" class="w-8 h-8 bg-blue-400 hover:bg-blue-500 rounded-full flex items-center justify-center transition">
                            <i class="fab fa-twitter text-white text-sm"></i>
                        </a>
                    </div>
                </div>

                <!-- Divider -->
                <div class="hidden md:block w-px h-6 bg-gray-600"></div>

                <!-- Action Icons -->
                <div class="flex items-center gap-2">
                    <button class="text-gray-300 hover:text-pink-400 text-lg transition" title="Watch2gether">
                        <i class="fas fa-users"></i>
                    </button>
                    <button class="text-gray-300 hover:text-pink-400 text-lg transition" title="Random">
                        <i class="fas fa-random"></i>
                    </button>
                    <button class="text-gray-300 hover:text-pink-400 text-lg transition" title="A-Z List">
                        <i class="fas fa-list"></i>
                    </button>
                    <button class="text-gray-300 hover:text-pink-400 text-lg transition" title="News">
                        <i class="fas fa-newspaper"></i>
                    </button>
                    <button class="text-gray-300 hover:text-pink-400 text-lg transition" title="Community">
                        <i class="fas fa-comments"></i>
                    </button>
                </div>

                <!-- Language Switcher -->
                <div class="flex items-center gap-1 ml-2">
                    <a href="?lang=en" class="px-2 py-1 rounded text-xs font-medium <?php echo $lang==='en'?'bg-pink-400 text-white':'bg-gray-700 text-gray-200 hover:bg-gray-600'; ?> transition">EN</a>
                    <a href="?lang=ar" class="px-2 py-1 rounded text-xs font-medium <?php echo $lang==='ar'?'bg-pink-400 text-white':'bg-gray-700 text-gray-200 hover:bg-gray-600'; ?> transition">AR</a>
                </div>

                <!-- Login Button -->
                <a href="#" class="bg-pink-400 hover:bg-pink-500 text-white font-bold py-2 px-4 rounded-full text-sm shadow-lg transition">
                    Login
                </a>
            </div>
        </div>

        <!-- Mobile Menu Dropdown -->
        <div id="mobileMenu" class="hidden lg:hidden bg-[#1a1825] border-t border-gray-700">
            <div class="px-4 py-3 space-y-3">
                <a href="index.php" class="block text-white hover:text-pink-400 font-medium">Home</a>
                <a href="#" class="block text-white hover:text-pink-400 font-medium">Movies</a>
                <a href="#" class="block text-white hover:text-pink-400 font-medium">TV Series</a>
                <a href="#" class="block text-white hover:text-pink-400 font-medium">Most Popular</a>
                <a href="#" class="block text-white hover:text-pink-400 font-medium">Top Airing</a>
                <a href="#" class="block text-white hover:text-pink-400 font-medium">A-Z List</a>
                <a href="#" class="block text-white hover:text-pink-400 font-medium">News</a>
                <a href="#" class="block text-white hover:text-pink-400 font-medium">Community</a>
                <div class="flex items-center gap-2 pt-2">
                    <span class="text-gray-300 text-sm">Follow us:</span>
                    <div class="flex gap-2">
                        <a href="#" class="text-blue-400 hover:text-blue-300"><i class="fab fa-discord"></i></a>
                        <a href="#" class="text-blue-400 hover:text-blue-300"><i class="fab fa-telegram"></i></a>
                        <a href="#" class="text-red-400 hover:text-red-300"><i class="fab fa-reddit"></i></a>
                        <a href="#" class="text-blue-400 hover:text-blue-300"><i class="fab fa-twitter"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </nav>
    <!-- Hero/Spotlight Section -->
    <?php if ($spotlight): ?>
    <section class="star-bg rounded-3xl mx-4 mt-8 mb-12 shadow-xl overflow-hidden relative flex flex-col md:flex-row items-center justify-between p-8 min-h-[420px]">
        <div class="flex-1 z-10 max-w-2xl">
            <div class="mb-2 text-pink-400 font-semibold text-lg">#1 Spotlight</div>
            <h1 class="text-4xl md:text-5xl font-bold mb-4 leading-tight"><?php echo htmlspecialchars($spotlight['anime_title']); ?></h1>
            
            <!-- Dynamic metadata from database -->
            <div class="flex flex-wrap items-center gap-3 mb-3 text-gray-200 text-sm">
                <span><i class="fas fa-tv mr-1"></i> <?php echo htmlspecialchars($spotlight['type'] ?? 'TV'); ?></span>
                <?php if (!empty($spotlight['episode_duration'])): ?>
                <span><i class="fas fa-clock mr-1"></i> <?php echo htmlspecialchars($spotlight['episode_duration']); ?>m</span>
                <?php endif; ?>
                <span><i class="fas fa-calendar-alt mr-1"></i> <?php echo htmlspecialchars($spotlight['year'] ?? '2024'); ?></span>
                <?php if (!empty($spotlight['status'])): ?>
                <span class="bg-green-600 text-white px-2 py-1 rounded text-xs font-bold"><?php echo htmlspecialchars($spotlight['status']); ?></span>
                <?php endif; ?>
                <?php if (!empty($spotlight['episodes_count'])): ?>
                <span class="bg-blue-600 text-white px-2 py-1 rounded text-xs font-bold"><?php echo htmlspecialchars($spotlight['episodes_count']); ?> EP</span>
                <?php endif; ?>
                <span class="bg-pink-400 text-white px-2 py-1 rounded text-xs font-bold">HD</span>
            </div>
            
            <!-- Dynamic genres -->
            <?php if (!empty($spotlight['combined_genres'])): ?>
            <div class="flex flex-wrap items-center gap-2 mb-4">
                <?php foreach (array_slice($spotlight['combined_genres'], 0, 5) as $genre): ?>
                <span class="bg-gray-800/50 text-pink-300 px-3 py-1 rounded-full text-xs font-medium border border-pink-400/30">
                    <?php echo htmlspecialchars(trim($genre)); ?>
                </span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <p class="text-gray-200 mb-6 max-w-xl leading-relaxed">
                <?php 
                $description = $spotlight_description ?: 'Experience the latest anime with stunning visuals and compelling storylines. Join the adventure today!';
                echo htmlspecialchars(strlen($description) > 200 ? substr($description, 0, 200) . '...' : $description);
                ?>
            </p>
            
            <div class="flex gap-4 mb-4">
                <a href="watch-episode.php?url=<?php echo urlencode($spotlight['episode_url'] ?? ''); ?>" 
                   class="bg-pink-400 hover:bg-pink-500 text-white font-bold py-3 px-8 rounded-full text-lg shadow-lg transition flex items-center gap-2">
                    <i class="fas fa-play"></i> <?php echo t('Watch Now', 'شاهد الآن'); ?>
                </a>
                <a href="details.php?title=<?php echo urlencode($spotlight['anime_title']); ?>" 
                   class="bg-gray-700 hover:bg-gray-600 text-white font-bold py-3 px-8 rounded-full text-lg shadow-lg transition flex items-center gap-2">
                    <i class="fas fa-info-circle"></i> <?php echo t('Detail', 'تفاصيل'); ?>
                </a>
            </div>
        </div>
        
        <div class="flex-1 flex items-center justify-center relative z-10">
            <?php
            // Safely get image source with proper fallbacks
            $poster = isset($spotlight['poster']) ? $spotlight['poster'] : '';
            $anime_thumbnail = isset($spotlight['anime_thumbnail']) ? $spotlight['anime_thumbnail'] : '';
            $default_image = 'https://static.hianime.to/cover/our-last-crusade-or-the-rise-of-a-new-world-season-2.png';
            
            $image_src = !empty($poster) ? $poster : (!empty($anime_thumbnail) ? $anime_thumbnail : $default_image);
            ?>
            <img src="<?php echo htmlspecialchars($image_src); ?>" 
                 alt="<?php echo htmlspecialchars($spotlight['anime_title']); ?>" 
                 class="w-[340px] h-[420px] object-cover rounded-2xl shadow-2xl border-4 border-white/10"
                 onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzQwIiBoZWlnaHQ9IjQyMCIgdmlld0JveD0iMCAwIDM0MCA0MjAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGRlZnM+PGxpbmVhckdyYWRpZW50IGlkPSJncmFkIiB4MT0iMCUiIHkxPSIwJSIgeDI9IjEwMCUiIHkyPSIxMDAlIj48c3RvcCBvZmZzZXQ9IjAlIiBzdHlsZT0ic3RvcC1jb2xvcjojNjY3ZWVhO3N0b3Atb3BhY2l0eToxIiAvPjxzdG9wIG9mZnNldD0iMTAwJSIgc3R5bGU9InN0b3AtY29sb3I6Izc2NGJhMjtzdG9wLW9wYWNpdHk6MSIgLz48L2xpbmVhckdyYWRpZW50PjwvZGVmcz48cmVjdCB3aWR0aD0iMzQwIiBoZWlnaHQ9IjQyMCIgZmlsbD0idXJsKCNncmFkKSIvPjx0ZXh0IHg9IjE3MCIgeT0iMjEwIiBmb250LWZhbWlseT0iQXJpYWwsIHNhbnMtc2VyaWYiIGZvbnQtc2l6ZT0iMTgiIGZpbGw9IndoaXRlIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBkeT0iLjNlbSI+QW5pbWUgUG9zdGVyPC90ZXh0Pjwvc3ZnPg=='; this.onerror=null;" />
        </div>
        
        <!-- Carousel arrows for multiple spotlight items -->
        <?php if (count($spotlight_candidates) > 1): ?>
        <button onclick="changeSpotlight(-1)" class="absolute left-4 top-1/2 -translate-y-1/2 bg-black/40 hover:bg-black/70 text-white rounded-full w-10 h-10 flex items-center justify-center z-20">
            <i class="fas fa-chevron-left"></i>
        </button>
        <button onclick="changeSpotlight(1)" class="absolute right-4 top-1/2 -translate-y-1/2 bg-black/40 hover:bg-black/70 text-white rounded-full w-10 h-10 flex items-center justify-center z-20">
            <i class="fas fa-chevron-right"></i>
        </button>
        <?php endif; ?>
    </section>
    <?php else: ?>
    <!-- Fallback if no spotlight data -->
    <section class="star-bg rounded-3xl mx-4 mt-8 mb-12 shadow-xl overflow-hidden relative flex flex-col md:flex-row items-center justify-between p-8 min-h-[420px]">
        <div class="flex-1 z-10 max-w-2xl">
            <div class="mb-2 text-pink-400 font-semibold text-lg">#1 Spotlight</div>
            <h1 class="text-4xl md:text-5xl font-bold mb-4 leading-tight">Welcome to h!anime</h1>
            <p class="text-gray-200 mb-6 max-w-xl">Discover the latest and greatest anime series and movies. Your ultimate destination for anime entertainment.</p>
            <div class="flex gap-4 mb-4">
                <a href="search.php" class="bg-pink-400 hover:bg-pink-500 text-white font-bold py-3 px-8 rounded-full text-lg shadow-lg transition flex items-center gap-2">
                    <i class="fas fa-search"></i> <?php echo t('Browse Anime', 'تصفح الأنمي'); ?>
                </a>
            </div>
        </div>
    </section>
    <?php endif; ?>
    <!-- Trending Section -->
    <section class="mb-12">
        <div class="container mx-auto px-6">
            <h3 class="text-white text-2xl font-medium mb-6">Trending</h3>
            <div class="flex gap-6 overflow-x-auto scrollbar-hide pb-2">
                <?php 
                $trendingIndex = 1;
                while ($ep = $trending->fetchArray(SQLITE3_ASSOC)): ?>
                    <a href="watch-episode.php?url=<?php echo urlencode($ep['episode_url'] ?? ''); ?>" class="flex-none w-44 group cursor-pointer hover:scale-105 transition-transform duration-300">
                        <div class="relative rounded-lg overflow-hidden mb-2">
                            <img src="<?php echo htmlspecialchars($ep['thumbnail']); ?>" 
                                 class="w-full h-60 object-cover group-hover:brightness-110 transition-all duration-300" 
                                 alt="<?php echo htmlspecialchars($ep['anime_title']); ?>"
                                 onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTc2IiBoZWlnaHQ9IjI0MCIgdmlld0JveD0iMCAwIDE3NiAyNDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGRlZnM+PGxpbmVhckdyYWRpZW50IGlkPSJncmFkIiB4MT0iMCUiIHkxPSIwJSIgeDI9IjEwMCUiIHkyPSIxMDAlIj48c3RvcCBvZmZzZXQ9IjAlIiBzdHlsZT0ic3RvcC1jb2xvcjojZjA5M2ZiO3N0b3Atb3BhY2l0eToxIiAvPjxzdG9wIG9mZnNldD0iMTAwJSIgc3R5bGU9InN0b3AtY29sb3I6IzY2N2VlYTtzdG9wLW9wYWNpdHk6MSIgLz48L2xpbmVhckdyYWRpZW50PjwvZGVmcz48cmVjdCB3aWR0aD0iMTc2IiBoZWlnaHQ9IjI0MCIgZmlsbD0idXJsKCNncmFkKSIvPjx0ZXh0IHg9Ijg4IiB5PSIxMjAiIGZvbnQtZmFtaWx5PSJBcmlhbCwgc2Fucy1zZXJpZiIgZm9udC1zaXplPSIxNCIgZmlsbD0id2hpdGUiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj5BbmltZTwvdGV4dD48L3N2Zz4='; this.onerror=null;">
                            <span class="absolute top-2 left-2 bg-pink-400 text-white text-xs font-bold px-2 py-1 rounded-full">0<?php echo $trendingIndex++; ?></span>
                            <!-- Play button overlay on hover -->
                            <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity duration-300">
                                <i class="fas fa-play text-white text-3xl"></i>
                            </div>
                        </div>
                        <div class="text-center">
                            <span class="block text-white font-semibold truncate group-hover:text-pink-400 transition-colors duration-300"> <?php echo htmlspecialchars($ep['anime_title']); ?> </span>
                        </div>
                    </a>
                <?php endwhile; ?>
            </div>
        </div>
    </section>

    <!-- JavaScript for spotlight carousel -->
    <script>
        // Spotlight data from PHP
        const spotlightData = <?php echo json_encode($spotlight_candidates); ?>;
        let currentSpotlightIndex = 0;

        function changeSpotlight(direction) {
            if (spotlightData.length <= 1) return;
            
            currentSpotlightIndex += direction;
            if (currentSpotlightIndex >= spotlightData.length) {
                currentSpotlightIndex = 0;
            } else if (currentSpotlightIndex < 0) {
                currentSpotlightIndex = spotlightData.length - 1;
            }
            
            updateSpotlightDisplay();
        }

        function updateSpotlightDisplay() {
            const spotlight = spotlightData[currentSpotlightIndex];
            if (!spotlight) return;

            // Update title
            const titleElement = document.querySelector('.text-4xl.md\\:text-5xl');
            if (titleElement) titleElement.textContent = spotlight.anime_title || 'Unknown Anime';

            // Update image
            const imgElement = document.querySelector('.w-\\[340px\\].h-\\[420px\\]');
            if (imgElement) {
                const imgSrc = spotlight.poster || spotlight.anime_thumbnail || 'https://static.hianime.to/cover/our-last-crusade-or-the-rise-of-a-new-world-season-2.png';
                imgElement.src = imgSrc;
                imgElement.alt = spotlight.anime_title || 'Anime Poster';
            }

            // Update Watch Now button
            const watchButton = document.querySelector('a[href*="watch-episode.php"]');
            if (watchButton && spotlight.episode_url) {
                watchButton.href = 'watch-episode.php?url=' + encodeURIComponent(spotlight.episode_url);
            }

            // Update Detail button
            const detailButton = document.querySelector('a[href*="details.php"]');
            if (detailButton && spotlight.anime_title) {
                detailButton.href = 'details.php?title=' + encodeURIComponent(spotlight.anime_title);
            }
        }

        // Auto-rotate spotlight every 10 seconds
        if (spotlightData.length > 1) {
            setInterval(() => {
                changeSpotlight(1);
            }, 10000);
        }

        // Mobile menu toggle
        document.getElementById('menuToggle').addEventListener('click', function() {
            const mobileMenu = document.getElementById('mobileMenu');
            mobileMenu.classList.toggle('hidden');
        });
    </script>
</body>
</html>