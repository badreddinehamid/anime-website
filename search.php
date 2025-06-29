<?php
// Suppress warnings for production
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

require_once 'includes/track_visit.php';
trackVisit();

try {
    // Language switcher logic
    $lang = isset($_GET['lang']) && $_GET['lang'] === 'ar' ? 'ar' : 'en';
    function t($en, $ar) {
        global $lang;
        return $lang === 'ar' ? $ar : $en;
    }
    
    // Try anime_episodes.db first (main database)
    $db = new SQLite3('anime_episodes.db');
    $search_query = isset($_GET['q']) ? trim($_GET['q']) : '';
    
    $results = [];
    $total_results = 0;
    
    if (!empty($search_query)) {
        // Search in episodes table
        $stmt = $db->prepare("
            SELECT DISTINCT
                anime_title,
                anime_thumbnail,
                genres,
                details,
                COUNT(*) as episode_count
            FROM episodes 
            WHERE anime_title LIKE :search 
               OR episode_title LIKE :search
               OR genres LIKE :search
            GROUP BY anime_title
            ORDER BY anime_title ASC
            LIMIT 40
        ");
        
        $search_param = '%' . $search_query . '%';
        $stmt->bindValue(':search', $search_param, SQLITE3_TEXT);
        $result = $stmt->execute();
        
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $results[] = $row;
            $total_results++;
        }
    } else {
        // Get random anime when no search query
        $result = $db->query("
            SELECT DISTINCT
                anime_title,
                anime_thumbnail,
                genres,
                details,
                COUNT(*) as episode_count
            FROM episodes 
            GROUP BY anime_title
            ORDER BY RANDOM()
            LIMIT 20
        ");
        
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $results[] = $row;
            $total_results++;
        }
    }
    
} catch (Exception $e) {
    $results = [];
    $total_results = 0;
}
?>

<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" dir="<?php echo $lang === 'ar' ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo !empty($search_query) ? htmlspecialchars($search_query) . ' - ' : ''; ?><?php echo t('Search', 'بحث'); ?> - h!anime</title>
    <meta name="description" content="<?php echo t('Search for your favorite anime series and movies', 'ابحث عن مسلسلات وأفلام الأنمي المفضلة لديك'); ?>">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        body { background: #232136; }
        .star-bg { background: url('https://static.vecteezy.com/system/resources/previews/022/832/888/original/galaxy-background-with-stars-cosmic-night-sky-illustration-free-vector.jpg') center/cover no-repeat; }
        .scrollbar-hide::-webkit-scrollbar { display: none; }
        .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
        .search-gradient {
            background: linear-gradient(135deg, rgba(244, 114, 182, 0.1) 0%, rgba(139, 92, 246, 0.1) 100%);
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
                               value="<?php echo htmlspecialchars($search_query); ?>"
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

    <!-- Search Hero Section -->
    <?php if (empty($search_query)): ?>
    <section class="relative min-h-[400px] star-bg">
        <div class="absolute inset-0 bg-gradient-to-b from-[#232136]/80 to-[#232136]/95"></div>
        <div class="relative z-10 container mx-auto px-6 py-20 text-center">
            <h1 class="text-5xl md:text-6xl font-bold mb-6 text-white">
                <?php echo t('Discover Anime', 'اكتشف الأنمي'); ?>
            </h1>
            <p class="text-xl text-gray-300 mb-8 max-w-2xl mx-auto">
                <?php echo t('Search through thousands of anime series and movies. Find your next favorite adventure!', 'ابحث في آلاف مسلسلات وأفلام الأنمي. اعثر على مغامرتك المفضلة التالية!'); ?>
            </p>
            
            <!-- Large Search Bar -->
            <div class="max-w-3xl mx-auto mb-8">
                <form action="search.php" method="GET" class="flex items-center bg-white/95 backdrop-blur-sm rounded-full shadow-2xl overflow-hidden">
                    <input type="text" 
                           name="q" 
                           placeholder="<?php echo t('Search for anime, characters, genres...', 'ابحث عن أنمي، شخصيات، أنواع...'); ?>"
                           class="flex-1 px-8 py-4 text-lg text-gray-800 focus:outline-none bg-transparent" />
                    <button type="submit" 
                            class="bg-pink-400 hover:bg-pink-500 text-white px-8 py-4 font-bold text-lg transition-colors">
                        <i class="fas fa-search mr-2"></i><?php echo t('Search', 'بحث'); ?>
                    </button>
                </form>
            </div>
            
            <!-- Popular Search Terms -->
            <div class="text-gray-400 text-sm">
                <span class="font-medium"><?php echo t('Popular searches:', 'البحث الشائع:'); ?></span>
                <span class="ml-2">One Piece, Demon Slayer, Attack on Titan, Naruto, Dragon Ball, Death Note</span>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Main Content -->
    <main class="container mx-auto px-6 py-12">
        <!-- Search Results Header -->
        <?php if (!empty($search_query)): ?>
        <div class="mb-8">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h1 class="text-3xl font-bold text-white mb-2">
                        <?php echo t('Search Results for', 'نتائج البحث عن'); ?> "<?php echo htmlspecialchars($search_query); ?>"
                    </h1>
                    <p class="text-gray-400">
                        <?php echo t('Found', 'تم العثور على'); ?> <span class="text-pink-400 font-semibold"><?php echo $total_results; ?></span> <?php echo t('results', 'نتيجة'); ?>
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <button class="bg-gray-800/50 hover:bg-gray-700 text-white px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-sort mr-2"></i><?php echo t('Sort', 'ترتيب'); ?>
                    </button>
                    <button class="bg-gray-800/50 hover:bg-gray-700 text-white px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-filter mr-2"></i><?php echo t('Filter', 'تصفية'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="mb-8">
            <h2 class="text-3xl font-bold text-white mb-2">
                <?php echo t('Recommended Anime', 'أنمي مُوصى به'); ?>
            </h2>
            <p class="text-gray-400">
                <?php echo t('Discover amazing anime series and movies', 'اكتشف مسلسلات وأفلام أنمي رائعة'); ?>
            </p>
        </div>
        <?php endif; ?>

        <!-- Results Grid -->
        <?php if (!empty($results)): ?>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-6">
            <?php foreach ($results as $anime): 
                // Parse genres
                $genres = json_decode($anime['genres'], true);
                $genres_list = is_array($genres) ? array_slice($genres, 0, 3) : [];
                
                // Parse details for additional info
                $details = json_decode($anime['details'], true);
                $details = is_array($details) ? $details : [];
            ?>
            <div class="group cursor-pointer">
                <div class="relative aspect-[3/4] rounded-xl overflow-hidden mb-4 bg-gray-900/50">
                    <img src="<?php echo htmlspecialchars($anime['anime_thumbnail']); ?>" 
                         class="w-full h-full object-cover transform group-hover:scale-110 transition-transform duration-500" 
                         alt="<?php echo htmlspecialchars($anime['anime_title']); ?>"
                         onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjQwMCIgdmlld0JveD0iMCAwIDMwMCA0MDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGRlZnM+PGxpbmVhckdyYWRpZW50IGlkPSJncmFkIiB4MT0iMCUiIHkxPSIwJSIgeDI9IjEwMCUiIHkyPSIxMDAlIj48c3RvcCBvZmZzZXQ9IjAlIiBzdHlsZT0ic3RvcC1jb2xvcjojNjY3ZWVhO3N0b3Atb3BhY2l0eToxIiAvPjxzdG9wIG9mZnNldD0iMTAwJSIgc3R5bGU9InN0b3AtY29sb3I6Izc2NGJhMjtzdG9wLW9wYWNpdHk6MSIgLz48L2xpbmVhckdyYWRpZW50PjwvZGVmcz48cmVjdCB3aWR0aD0iMzAwIiBoZWlnaHQ9IjQwMCIgZmlsbD0idXJsKCNncmFkKSIvPjx0ZXh0IHg9IjE1MCIgeT0iMjAwIiBmb250LWZhbWlseT0iQXJpYWwsIHNhbnMtc2VyaWYiIGZvbnQtc2l6ZT0iMTYiIGZpbGw9IndoaXRlIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBkeT0iLjNlbSI+QW5pbWUgUG9zdGVyPC90ZXh0Pjwvc3ZnPg=='; this.onerror=null;" />
                    
                    <!-- Overlay with Actions -->
                    <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                        <div class="absolute bottom-4 left-4 right-4 space-y-2">
                            <a href="details.php?title=<?php echo urlencode($anime['anime_title']); ?>" 
                               class="w-full bg-pink-400 hover:bg-pink-500 text-white py-2 rounded-lg flex items-center justify-center gap-2 font-bold transition-colors">
                                <i class="fas fa-info-circle"></i>
                                <?php echo t('Details', 'تفاصيل'); ?>
                            </a>
                            <?php if ($anime['episode_count'] > 0): ?>
                            <button class="w-full bg-white/90 hover:bg-white text-black py-2 rounded-lg flex items-center justify-center gap-2 font-bold transition-colors">
                                <i class="fas fa-play"></i>
                                <?php echo t('Watch', 'شاهد'); ?>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Episode Count Badge -->
                    <?php if ($anime['episode_count'] > 0): ?>
                    <div class="absolute top-2 right-2 bg-pink-400 text-white text-xs font-bold px-2 py-1 rounded-full">
                        <?php echo $anime['episode_count']; ?> EP
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Anime Info -->
                <div class="space-y-2">
                    <h3 class="text-white font-semibold line-clamp-2 group-hover:text-pink-400 transition-colors duration-300">
                        <?php echo htmlspecialchars($anime['anime_title']); ?>
                    </h3>
                    
                    <!-- Genres -->
                    <?php if (!empty($genres_list)): ?>
                    <div class="flex flex-wrap gap-1">
                        <?php foreach ($genres_list as $genre): ?>
                        <span class="text-xs bg-gray-800/50 text-gray-300 px-2 py-1 rounded-full">
                            <?php echo htmlspecialchars(trim($genre)); ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <!-- No Results -->
        <div class="text-center py-20">
            <div class="max-w-md mx-auto">
                <div class="text-6xl text-gray-600 mb-6">
                    <i class="fas fa-search"></i>
                </div>
                <h3 class="text-2xl font-bold text-white mb-4">
                    <?php echo t('No Results Found', 'لم يتم العثور على نتائج'); ?>
                </h3>
                <p class="text-gray-400 mb-8">
                    <?php echo t('Try searching with different keywords or browse our recommended anime.', 'جرب البحث بكلمات مفتاحية مختلفة أو تصفح الأنمي المُوصى به.'); ?>
                </p>
                <a href="search.php" class="bg-pink-400 hover:bg-pink-500 text-white font-bold py-3 px-8 rounded-full transition-colors">
                    <?php echo t('Browse Anime', 'تصفح الأنمي'); ?>
                </a>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <footer class="bg-[#232136] py-8 mt-16">
        <div class="container mx-auto px-6 text-center">
            <p class="text-gray-400">
                © 2024 h<span class="text-pink-400">!</span>anime - <?php echo t('Your ultimate anime destination', 'وجهتك المثلى للأنمي'); ?>
            </p>
        </div>
    </footer>

    <?php if (isset($db)) $db->close(); ?>

    <script>
        // Mobile menu toggle
        document.getElementById('menuToggle').addEventListener('click', function() {
            const mobileMenu = document.getElementById('mobileMenu');
            mobileMenu.classList.toggle('hidden');
        });

        // Auto-focus search input if no query
        <?php if (empty($search_query)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.querySelector('input[name="q"]');
            if (searchInput && window.innerWidth > 768) {
                searchInput.focus();
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>