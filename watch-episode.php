<?php
// Suppress warnings for production
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

require_once 'includes/track_visit.php';
trackVisit();

try {
    // Check for episode URL parameter
    if (!isset($_GET['url'])) {
        header('Location: index.php');
        exit;
    }

    $episode_url = $_GET['url'];
    
    // Language switcher logic
    $lang = isset($_GET['lang']) && $_GET['lang'] === 'ar' ? 'ar' : 'en';
    function t($en, $ar) {
        global $lang;
        return $lang === 'ar' ? $ar : $en;
    }
    
    // Connect to database
    $db = new SQLite3('anime_episodes.db');
    
    // Query to get episode details
    $stmt = $db->prepare('
        SELECT 
            episode_title,
            episode_url,
            anime_thumbnail,
            anime_title,
            anime_url,
            details,
            genres
        FROM episodes 
        WHERE episode_url = :url
        LIMIT 1
    ');
    
    $stmt->bindValue(':url', $episode_url, SQLITE3_TEXT);
    $result = $stmt->execute();
    $episode = $result->fetchArray(SQLITE3_ASSOC);
    
    if (!$episode) {
        header('Location: index.php');
        exit;
    }

    // Convert JSON data to arrays
    $details = is_string($episode['details']) ? json_decode($episode['details'], true) : $episode['details'];
    $genres = is_string($episode['genres']) ? json_decode($episode['genres'], true) : $episode['genres'];
    
    // Ensure variables are arrays
    $details = is_array($details) ? $details : [];
    $genres = is_array($genres) ? $genres : [];
    
    // Extract servers from details
    $servers = isset($details['servers']) ? $details['servers'] : [];
    
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
    <title><?php echo htmlspecialchars($episode['episode_title']); ?> - h!anime</title>
    <meta name="description" content="<?php echo t('Watch', 'شاهد'); ?> <?php echo htmlspecialchars($episode['episode_title']); ?> - <?php echo htmlspecialchars($episode['anime_title']); ?> <?php echo t('online in HD quality', 'اون لاين بجودة عالية'); ?>">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        body { background: #232136; }
        .star-bg { background: url('https://static.vecteezy.com/system/resources/previews/022/832/888/original/galaxy-background-with-stars-cosmic-night-sky-illustration-free-vector.jpg') center/cover no-repeat; }
        .scrollbar-hide::-webkit-scrollbar { display: none; }
        .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
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

    <!-- Main Content -->
    <main class="container mx-auto px-6 pt-20">
        <!-- Video Player -->
        <div class="mb-8">
            <div class="relative w-full max-w-5xl mx-auto bg-gray-900/50 rounded-2xl overflow-hidden shadow-2xl">
                <div class="relative w-full" style="padding-top: 56.25%;">
                    <iframe id="video-player"
                            class="absolute top-0 left-0 w-full h-full"
                            src=""
                            frameborder="0"
                            allowfullscreen>
                    </iframe>
                </div>
            </div>
        </div>

        <!-- Watch Servers -->
        <?php if (!empty($servers)): ?>
        <div class="mb-8">
            <h2 class="text-2xl font-bold mb-6 text-white flex items-center gap-3">
                <i class="fas fa-server text-pink-400"></i>
                <?php echo t('Watch Servers', 'سيرفرات المشاهدة'); ?>
            </h2>
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                <?php foreach ($servers as $index => $server): ?>
                    <button onclick="changeServer('<?php echo htmlspecialchars($server['url']); ?>')"
                            class="bg-gray-800/50 hover:bg-pink-400 text-center py-3 px-4 rounded-xl transition-all duration-300 font-medium border border-gray-700 hover:border-pink-400 hover:scale-105 <?php echo $index === 0 ? 'ring-2 ring-pink-400 bg-pink-400/20' : ''; ?>"
                            id="server-btn-<?php echo $index; ?>">
                        <i class="fas fa-play-circle mr-2"></i>
                        <?php echo htmlspecialchars($server['name']); ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Episode Information -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Anime Poster -->
            <div class="lg:col-span-1">
                <div class="bg-gray-900/30 rounded-2xl p-6 backdrop-blur-sm">
                    <img src="<?php echo htmlspecialchars($episode['anime_thumbnail']); ?>" 
                         class="w-full rounded-xl shadow-2xl mb-6"
                         alt="<?php echo htmlspecialchars($episode['anime_title']); ?>"
                         onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAwIiBoZWlnaHQ9IjYwMCIgdmlld0JveD0iMCAwIDQwMCA2MDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGRlZnM+PGxpbmVhckdyYWRpZW50IGlkPSJncmFkIiB4MT0iMCUiIHkxPSIwJSIgeDI9IjEwMCUiIHkyPSIxMDAlIj48c3RvcCBvZmZzZXQ9IjAlIiBzdHlsZT0ic3RvcC1jb2xvcjojNjY3ZWVhO3N0b3Atb3BhY2l0eToxIiAvPjxzdG9wIG9mZnNldD0iMTAwJSIgc3R5bGU9InN0b3AtY29sb3I6Izc2NGJhMjtzdG9wLW9wYWNpdHk6MSIgLz48L2xpbmVhckdyYWRpZW50PjwvZGVmcz48cmVjdCB3aWR0aD0iNDAwIiBoZWlnaHQ9IjYwMCIgZmlsbD0idXJsKCNncmFkKSIvPjx0ZXh0IHg9IjIwMCIgeT0iMzAwIiBmb250LWZhbWlseT0iQXJpYWwsIHNhbnMtc2VyaWYiIGZvbnQtc2l6ZT0iMTgiIGZpbGw9IndoaXRlIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBkeT0iLjNlbSI+QW5pbWUgUG9zdGVyPC90ZXh0Pjwvc3ZnPg=='; this.onerror=null;" />
                    
                    <!-- Action Buttons -->
                    <div class="space-y-3">
                        <a href="details.php?title=<?php echo urlencode($episode['anime_title']); ?>" 
                           class="w-full bg-pink-400 hover:bg-pink-500 text-white font-bold py-3 px-6 rounded-full text-lg shadow-lg transition flex items-center justify-center gap-2">
                            <i class="fas fa-info-circle"></i> <?php echo t('Anime Details', 'تفاصيل الأنمي'); ?>
                        </a>
                        <button class="w-full bg-gray-700 hover:bg-gray-600 text-white font-bold py-3 px-6 rounded-full text-lg shadow-lg transition flex items-center justify-center gap-2">
                            <i class="fas fa-plus"></i> <?php echo t('Add to List', 'أضف للقائمة'); ?>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Episode Details -->
            <div class="lg:col-span-2">
                <div class="bg-gray-900/30 rounded-2xl p-8 backdrop-blur-sm">
                    <h1 class="text-4xl font-bold mb-4 text-white">
                        <?php echo htmlspecialchars($episode['episode_title']); ?>
                    </h1>
                    
                    <a href="details.php?title=<?php echo urlencode($episode['anime_title']); ?>" 
                       class="text-2xl text-pink-400 hover:text-pink-300 mb-6 block font-semibold transition-colors">
                        <?php echo htmlspecialchars($episode['anime_title']); ?>
                    </a>

                    <?php if (!empty($genres)): ?>
                    <div class="mb-6">
                        <h3 class="text-xl font-semibold mb-3 text-pink-400"><?php echo t('Genres', 'التصنيفات'); ?></h3>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach (array_slice($genres, 0, 8) as $genre): ?>
                            <span class="bg-gray-800/50 text-pink-300 px-4 py-2 rounded-full text-sm font-medium border border-pink-400/30 hover:bg-pink-400/20 transition-colors cursor-pointer">
                                <?php echo htmlspecialchars(trim($genre)); ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($details)): ?>
                    <div class="bg-gray-800/30 rounded-xl p-6 mb-6 border border-gray-700/50">
                        <h3 class="text-xl font-semibold mb-4 text-pink-400 flex items-center gap-2">
                            <i class="fas fa-info-circle"></i>
                            <?php echo t('Additional Information', 'معلومات إضافية'); ?>
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php 
                            // Define keys we want to display
                            $allowed_keys = [
                                'النوع' => t('Type', 'النوع'),
                                'بداية العرض' => t('Release Date', 'بداية العرض'),
                                'حالة الأنمي' => t('Status', 'حالة الأنمي'),
                                'عدد الحلقات' => t('Episodes', 'عدد الحلقات'),
                                'مدة الحلقة' => t('Duration', 'مدة الحلقة'),
                                'الموسم' => t('Season', 'الموسم')
                            ];
                            
                            foreach ($details as $key => $value): 
                                if (array_key_exists($key, $allowed_keys) && !empty($value)): 
                            ?>
                                <div class="bg-gray-900/50 rounded-lg p-3 border border-gray-600/30">
                                    <span class="text-gray-400 text-sm font-medium block"><?php echo htmlspecialchars($allowed_keys[$key]); ?></span>
                                    <span class="text-white font-semibold"><?php echo htmlspecialchars($value); ?></span>
                                </div>
                            <?php 
                                endif; 
                            endforeach; 
                            ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Set default server when page loads
        window.onload = function() {
            const servers = <?php echo json_encode($servers); ?>;
            if (servers && servers.length > 0) {
                changeServer(servers[0].url);
            }
        };

        // Function to change server
        function changeServer(url) {
            const iframe = document.getElementById('video-player');
            iframe.src = url;

            // Update button states
            const buttons = document.querySelectorAll('[id^="server-btn-"]');
            buttons.forEach(button => {
                if (button.getAttribute('onclick').includes(url)) {
                    button.classList.add('ring-2', 'ring-pink-400', 'bg-pink-400/20');
                    button.classList.remove('bg-gray-800/50');
                } else {
                    button.classList.remove('ring-2', 'ring-pink-400', 'bg-pink-400/20');
                    button.classList.add('bg-gray-800/50');
                }
            });
        }

        // Mobile menu toggle
        document.getElementById('menuToggle').addEventListener('click', function() {
            const mobileMenu = document.getElementById('mobileMenu');
            mobileMenu.classList.toggle('hidden');
        });
    </script>
    <script type='text/javascript' src='//pl25648932.effectiveratecpm.com/d9/49/8c/d9498cda075310c8ee8091d8752584d4.js'></script>

    <?php $db->close(); ?>
</body>
</html> 