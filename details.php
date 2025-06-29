<?php
// Suppress warnings for production
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

require_once 'includes/track_visit.php';
trackVisit();

try {
    // Check if we have either anime_title (search-based) or id
    $anime_title = isset($_GET['title']) ? $_GET['title'] : null;
    $anime_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
    
    if (!$anime_title && !$anime_id) {
        header('Location: index.php');
        exit;
    }
    
    $db = new SQLite3('anime_episodes.db');
    $anime_info = null;
    $episodes = [];
    
    // Language switcher logic
    $lang = isset($_GET['lang']) && $_GET['lang'] === 'ar' ? 'ar' : 'en';
    function t($en, $ar) {
        global $lang;
        return $lang === 'ar' ? $ar : $en;
    }
    
    if ($anime_title) {
        // Search-based approach using anime title
        $stmt = $db->prepare("SELECT * FROM episodes WHERE anime_title LIKE ? ORDER BY id ASC");
        $stmt->bindValue(1, '%' . $anime_title . '%');
        $episodes_result = $stmt->execute();
        
        while ($ep = $episodes_result->fetchArray(SQLITE3_ASSOC)) {
            $episodes[] = $ep;
        }
        
        if (!empty($episodes)) {
            // Use first episode data to get anime info
            $first_ep = $episodes[0];
            $anime_info = [
                'title' => $first_ep['anime_title'],
                'thumbnail' => $first_ep['anime_thumbnail'],
                'genres' => $first_ep['genres'],
                'details' => $first_ep['details'],
                'total_episodes' => count($episodes)
            ];
            
            // Try to get additional info from anime database
            try {
                $db2 = new SQLite3('anime_database.db');
                $stmt2 = $db2->prepare("SELECT * FROM anime WHERE LOWER(TRIM(title)) LIKE LOWER(TRIM(?))");
                $stmt2->bindValue(1, '%' . trim($first_ep['anime_title']) . '%');
                $anime_result = $stmt2->execute();
                $anime_data = $anime_result->fetchArray(SQLITE3_ASSOC);
                
                if ($anime_data) {
                    $anime_info = array_merge($anime_info, $anime_data);
                    
                    // Get genres from genres table
                    $genres_query = "SELECT genre FROM genres WHERE anime_id = ?";
                    $stmt3 = $db2->prepare($genres_query);
                    $stmt3->bindValue(1, $anime_data['id']);
                    $genres_result = $stmt3->execute();
                    $db_genres = [];
                    while ($genre_row = $genres_result->fetchArray(SQLITE3_ASSOC)) {
                        $db_genres[] = $genre_row['genre'];
                    }
                    if (!empty($db_genres)) {
                        $anime_info['db_genres'] = $db_genres;
                    }
                }
                $db2->close();
            } catch (Exception $e) {
                // Continue without anime database data
            }
        }
    }
    
    if (!$anime_info) {
        header('Location: index.php');
        exit;
    }
    
    // Parse details JSON
    $details_data = [];
    if (!empty($anime_info['details'])) {
        $details_data = json_decode($anime_info['details'], true) ?: [];
    }
    
    // Parse genres
    $genres_list = [];
    if (!empty($anime_info['db_genres'])) {
        $genres_list = $anime_info['db_genres'];
    } elseif (!empty($anime_info['genres'])) {
        $parsed_genres = json_decode($anime_info['genres'], true);
        if (is_array($parsed_genres)) {
            $genres_list = $parsed_genres;
        }
    }
    
} catch (Exception $e) {
    header('Location: index.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" dir="<?php echo $lang === 'ar' ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($anime_info['title']); ?> - h!anime</title>
    <meta name="description" content="<?php echo t('Watch', 'شاهد'); ?> <?php echo htmlspecialchars($anime_info['title']); ?> <?php echo t('episodes online in HD quality', 'حلقات اون لاين بجودة عالية'); ?>">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        body { background: #232136; }
        .star-bg { background: url('https://static.vecteezy.com/system/resources/previews/022/832/888/original/galaxy-background-with-stars-cosmic-night-sky-illustration-free-vector.jpg') center/cover no-repeat; }
        .scrollbar-hide::-webkit-scrollbar { display: none; }
        .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
        .gradient-overlay {
            background: linear-gradient(90deg, rgba(35, 33, 54, 0.95) 0%, rgba(35, 33, 54, 0.8) 50%, rgba(35, 33, 54, 0.95) 100%);
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
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['lang' => 'en'])); ?>" class="px-2 py-1 rounded text-xs font-medium <?php echo $lang==='en'?'bg-pink-400 text-white':'bg-gray-700 text-gray-200 hover:bg-gray-600'; ?> transition">EN</a>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['lang' => 'ar'])); ?>" class="px-2 py-1 rounded text-xs font-medium <?php echo $lang==='ar'?'bg-pink-400 text-white':'bg-gray-700 text-gray-200 hover:bg-gray-600'; ?> transition">AR</a>
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
                <a href="index.php" class="block text-white hover:text-pink-400 font-medium"><?php echo t('Home', 'الرئيسية'); ?></a>
                <a href="#" class="block text-white hover:text-pink-400 font-medium"><?php echo t('Movies', 'أفلام'); ?></a>
                <a href="#" class="block text-white hover:text-pink-400 font-medium"><?php echo t('TV Series', 'مسلسلات'); ?></a>
                <a href="#" class="block text-white hover:text-pink-400 font-medium"><?php echo t('Most Popular', 'الأكثر شعبية'); ?></a>
                <a href="#" class="block text-white hover:text-pink-400 font-medium"><?php echo t('Top Airing', 'الأعلى مشاهدة'); ?></a>
                <a href="#" class="block text-white hover:text-pink-400 font-medium"><?php echo t('A-Z List', 'قائمة أ-ي'); ?></a>
                <a href="#" class="block text-white hover:text-pink-400 font-medium"><?php echo t('News', 'أخبار'); ?></a>
                <a href="#" class="block text-white hover:text-pink-400 font-medium"><?php echo t('Community', 'مجتمع'); ?></a>
                <div class="flex items-center gap-2 pt-2">
                    <span class="text-gray-300 text-sm"><?php echo t('Follow us:', 'تابعنا:'); ?></span>
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

    <!-- Hero Section with Anime Info -->
    <section class="relative min-h-[600px] star-bg">
        <div class="absolute inset-0 gradient-overlay"></div>
        <div class="relative z-10 container mx-auto px-6 py-12">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
                <!-- Anime Poster -->
                <div class="lg:col-span-1">
                    <div class="bg-gray-900/30 rounded-2xl p-6 backdrop-blur-sm">
                        <img src="<?php echo htmlspecialchars($anime_info['poster'] ?? $anime_info['thumbnail'] ?? 'https://via.placeholder.com/400x600'); ?>" 
                             alt="<?php echo htmlspecialchars($anime_info['title']); ?>" 
                             class="w-full rounded-xl shadow-2xl mb-6"
                             onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAwIiBoZWlnaHQ9IjYwMCIgdmlld0JveD0iMCAwIDQwMCA2MDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGRlZnM+PGxpbmVhckdyYWRpZW50IGlkPSJncmFkIiB4MT0iMCUiIHkxPSIwJSIgeDI9IjEwMCUiIHkyPSIxMDAlIj48c3RvcCBvZmZzZXQ9IjAlIiBzdHlsZT0ic3RvcC1jb2xvcjojNjY3ZWVhO3N0b3Atb3BhY2l0eToxIiAvPjxzdG9wIG9mZnNldD0iMTAwJSIgc3R5bGU9InN0b3AtY29sb3I6Izc2NGJhMjtzdG9wLW9wYWNpdHk6MSIgLz48L2xpbmVhckdyYWRpZW50PjwvZGVmcz48cmVjdCB3aWR0aD0iNDAwIiBoZWlnaHQ9IjYwMCIgZmlsbD0idXJsKCNncmFkKSIvPjx0ZXh0IHg9IjIwMCIgeT0iMzAwIiBmb250LWZhbWlseT0iQXJpYWwsIHNhbnMtc2VyaWYiIGZvbnQtc2l6ZT0iMTgiIGZpbGw9IndoaXRlIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBkeT0iLjNlbSI+QW5pbWUgUG9zdGVyPC90ZXh0Pjwvc3ZnPg=='; this.onerror=null;" />
                        
                        <!-- Action Buttons -->
                        <div class="space-y-3">
                            <?php if (!empty($episodes)): ?>
                            <a href="watch-episode.php?url=<?php echo urlencode($episodes[0]['episode_url']); ?>" 
                               class="w-full bg-pink-400 hover:bg-pink-500 text-white font-bold py-3 px-6 rounded-full text-lg shadow-lg transition flex items-center justify-center gap-2">
                                <i class="fas fa-play"></i> <?php echo t('Watch Now', 'شاهد الآن'); ?>
                            </a>
                            <?php endif; ?>
                            <button class="w-full bg-gray-700 hover:bg-gray-600 text-white font-bold py-3 px-6 rounded-full text-lg shadow-lg transition flex items-center justify-center gap-2">
                                <i class="fas fa-plus"></i> <?php echo t('Add to List', 'أضف للقائمة'); ?>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Anime Details -->
                <div class="lg:col-span-2">
                    <div class="bg-gray-900/30 rounded-2xl p-8 backdrop-blur-sm">
                        <h1 class="text-4xl lg:text-5xl font-bold mb-6 text-white"><?php echo htmlspecialchars($anime_info['title']); ?></h1>
                        
                        <!-- Meta Information -->
                        <div class="flex flex-wrap items-center gap-4 mb-6">
                            <?php if (!empty($anime_info['year'])): ?>
                            <span class="bg-gray-800/50 text-gray-300 px-3 py-1 rounded-full text-sm">
                                <i class="fas fa-calendar-alt mr-1"></i> <?php echo htmlspecialchars($anime_info['year']); ?>
                            </span>
                            <?php endif; ?>
                            
                            <?php if (!empty($anime_info['type'])): ?>
                            <span class="bg-gray-800/50 text-gray-300 px-3 py-1 rounded-full text-sm">
                                <i class="fas fa-tv mr-1"></i> <?php echo htmlspecialchars($anime_info['type']); ?>
                            </span>
                            <?php endif; ?>
                            
                            <span class="bg-blue-600 text-white px-3 py-1 rounded-full text-sm font-bold">
                                <i class="fas fa-film mr-1"></i> <?php echo count($episodes); ?> <?php echo t('Episodes', 'حلقات'); ?>
                            </span>
                            
                            <?php if (!empty($anime_info['status'])): ?>
                            <span class="bg-green-600 text-white px-3 py-1 rounded-full text-sm font-bold">
                                <?php echo htmlspecialchars($anime_info['status']); ?>
                            </span>
                            <?php endif; ?>
                            
                            <span class="bg-pink-400 text-white px-3 py-1 rounded-full text-sm font-bold">HD</span>
                        </div>

                        <!-- Genres -->
                        <?php if (!empty($genres_list)): ?>
                        <div class="mb-6">
                            <h3 class="text-xl font-semibold mb-3 text-pink-400"><?php echo t('Genres', 'التصنيفات'); ?></h3>
                            <div class="flex flex-wrap gap-2">
                                <?php foreach (array_slice($genres_list, 0, 8) as $genre): ?>
                                <span class="bg-gray-800/50 text-pink-300 px-4 py-2 rounded-full text-sm font-medium border border-pink-400/30 hover:bg-pink-400/20 transition-colors cursor-pointer">
                                    <?php echo htmlspecialchars(trim($genre)); ?>
                                </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Synopsis -->
                        <div class="mb-6">
                            <h3 class="text-xl font-semibold mb-3 text-pink-400"><?php echo t('Synopsis', 'القصة'); ?></h3>
                            <p class="text-gray-300 leading-relaxed text-lg">
                                <?php 
                                $synopsis = '';
                                if (!empty($anime_info['story'])) {
                                    $synopsis = $anime_info['story'];
                                } elseif (!empty($details_data['القصة'])) {
                                    $synopsis = $details_data['القصة'];
                                } elseif (!empty($details_data['story'])) {
                                    $synopsis = $details_data['story'];
                                }
                                
                                if (empty($synopsis)) {
                                    $synopsis = t(
                                        'An exciting anime series with compelling characters and an engaging storyline. Follow the adventures and discover the world of ' . $anime_info['title'] . '.',
                                        'سلسلة أنمي مثيرة بشخصيات جذابة وقصة مشوقة. تابع المغامرات واكتشف عالم ' . $anime_info['title'] . '.'
                                    );
                                }
                                
                                echo nl2br(htmlspecialchars($synopsis));
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Episodes Section -->
    <section class="py-12 bg-[#1a1825]">
        <div class="container mx-auto px-6">
            <h2 class="text-3xl font-bold mb-8 text-white flex items-center gap-3">
                <i class="fas fa-play-circle text-pink-400"></i>
                <?php echo t('Episodes', 'الحلقات'); ?>
                <span class="text-pink-400">(<?php echo count($episodes); ?>)</span>
            </h2>
            
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-6">
                <?php foreach ($episodes as $index => $episode): ?>
                <a href="watch-episode.php?url=<?php echo urlencode($episode['episode_url']); ?>" 
                   class="group bg-gray-900/50 rounded-xl overflow-hidden hover:scale-105 transition-transform duration-300 hover:shadow-2xl">
                    <div class="relative aspect-video">
                        <img src="<?php echo htmlspecialchars($episode['thumbnail'] ?? $anime_info['thumbnail'] ?? 'https://via.placeholder.com/300x169'); ?>" 
                             alt="<?php echo htmlspecialchars($episode['episode_title']); ?>" 
                             class="w-full h-full object-cover group-hover:brightness-110 transition-all duration-300"
                             onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjE2OSIgdmlld0JveD0iMCAwIDMwMCAxNjkiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGRlZnM+PGxpbmVhckdyYWRpZW50IGlkPSJncmFkIiB4MT0iMCUiIHkxPSIwJSIgeDI9IjEwMCUiIHkyPSIxMDAlIj48c3RvcCBvZmZzZXQ9IjAlIiBzdHlsZT0ic3RvcC1jb2xvcjojZjA5M2ZiO3N0b3Atb3BhY2l0eToxIiAvPjxzdG9wIG9mZnNldD0iMTAwJSIgc3R5bGU9InN0b3AtY29sb3I6IzY2N2VlYTtzdG9wLW9wYWNpdHk6MSIgLz48L2xpbmVhckdyYWRpZW50PjwvZGVmcz48cmVjdCB3aWR0aD0iMzAwIiBoZWlnaHQ9IjE2OSIgZmlsbD0idXJsKCNncmFkKSIvPjx0ZXh0IHg9IjE1MCIgeT0iODQuNSIgZm9udC1mYW1pbHk9IkFyaWFsLCBzYW5zLXNlcmlmIiBmb250LXNpemU9IjE0IiBmaWxsPSJ3aGl0ZSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPkVwaXNvZGU8L3RleHQ+PC9zdmc+'; this.onerror=null;" />
                        
                        <!-- Episode Number Badge -->
                        <div class="absolute top-2 left-2 bg-pink-400 text-white text-xs font-bold px-2 py-1 rounded-full">
                            <?php echo $index + 1; ?>
                        </div>
                        
                        <!-- Play Overlay -->
                        <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity duration-300">
                            <i class="fas fa-play text-white text-4xl"></i>
                        </div>
                    </div>
                    
                    <div class="p-4">
                        <h3 class="text-white font-semibold truncate group-hover:text-pink-400 transition-colors duration-300 text-sm">
                            <?php echo htmlspecialchars($episode['episode_title']); ?>
                        </h3>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-[#232136] py-8">
        <div class="container mx-auto px-6 text-center">
            <p class="text-gray-400">
                © 2024 h<span class="text-pink-400">!</span>anime - <?php echo t('Your ultimate anime destination', 'وجهتك المثلى للأنمي'); ?>
            </p>
        </div>
    </footer>

    <?php 
    $db->close();
    ?>

    <script>
        // Mobile menu toggle
        document.getElementById('menuToggle').addEventListener('click', function() {
            const mobileMenu = document.getElementById('mobileMenu');
            mobileMenu.classList.toggle('hidden');
        });
    </script>
</body>
</html>