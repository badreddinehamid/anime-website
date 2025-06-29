<?php
require_once 'includes/track_visit.php';
trackVisit();
try {
    if (!isset($_GET['id'])) {
        die("لم يتم تحديد الحلقة");
    }

    $db = new SQLite3('anime_database.db');
    $episode_id = (int)$_GET['id'];
    
    // Get episode details with anime info and servers
    $stmt = $db->prepare("
        SELECT 
            e.*,
            a.title as anime_title,
            a.poster as anime_poster,
            a.episodes_count,
            GROUP_CONCAT(s.name || '::' || s.url) as servers
        FROM episodes e
        JOIN anime a ON a.id = e.anime_id
        LEFT JOIN servers s ON s.episode_id = e.id
        WHERE e.id = :id
        GROUP BY e.id
    ");
    $stmt->bindValue(':id', $episode_id, SQLITE3_INTEGER);
    $episode = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    
    if (!$episode) {
        die("الحلقة غير موجودة");
    }

    // Get previous and next episode IDs
    $prev_id = $db->querySingle("SELECT id FROM episodes WHERE anime_id = {$episode['anime_id']} AND id < $episode_id ORDER BY id DESC LIMIT 1");
    $next_id = $db->querySingle("SELECT id FROM episodes WHERE anime_id = {$episode['anime_id']} AND id > $episode_id ORDER BY id ASC LIMIT 1");

    // Get all episodes for this anime
    $stmt = $db->prepare("
        SELECT id, title 
        FROM episodes 
        WHERE anime_id = :anime_id 
        ORDER BY id ASC
    ");
    $stmt->bindValue(':anime_id', $episode['anime_id'], SQLITE3_INTEGER);
    $all_episodes = $stmt->execute();
    
    // Parse servers
    $servers = [];
    if ($episode['servers']) {
        foreach (explode(',', $episode['servers']) as $server) {
            list($name, $url) = explode('::', $server);
            $servers[] = ['name' => $name, 'url' => $url];
        }
    }
    
} catch (Exception $e) {
    die("خطأ في قاعدة البيانات: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($episode['title']); ?> - <?php echo htmlspecialchars($episode['anime_title']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
   

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-black min-h-screen">
    <!-- القائمة العلوية -->
    <nav class="fixed top-0 left-0 right-0 z-50 bg-[#232136] shadow-lg">
        <div class="container mx-auto px-6 py-4 flex justify-between items-center">
            <div class="flex items-center gap-12">
                <span class="text-3xl font-bold text-white select-none">
                    h<span class="text-pink-400">!</span>anime
                </span>
                <div class="hidden md:flex items-center gap-8 text-base">
                    <a href="index.php" class="text-white font-semibold">Home</a>
                    <a href="#" class="text-gray-400 hover:text-white">Movies</a>
                    <a href="#" class="text-gray-400 hover:text-white">TV Series</a>
                    <a href="#" class="text-gray-400 hover:text-white">Most Popular</a>
                    <a href="#" class="text-gray-400 hover:text-white">Top Airing</a>
                </div>
            </div>
            <div class="flex items-center gap-6">
                <!-- Search icon removed, search is on homepage -->
            </div>
        </div>
    </nav>
    <!-- Note: Community/trending posts, user system, and social sharing are not implemented. -->

    <main class="container mx-auto px-6 pt-24">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            <!-- القسم الرئيسي -->
            <div class="lg:col-span-9">
                <!-- معلومات الحلقة -->
                <div class="bg-gray-800/50 backdrop-blur-sm rounded-xl p-6 mb-6">
                    <div class="flex items-center gap-4 mb-4">
                        <img src="<?php echo htmlspecialchars($episode['anime_poster']); ?>" 
                             class="w-16 h-16 rounded-lg object-cover" 
                             alt="<?php echo htmlspecialchars($episode['anime_title']); ?>">
                        <div>
                            <h1 class="text-2xl font-bold text-white mb-2">
                                <?php echo htmlspecialchars($episode['title']); ?>
                            </h1>
                            <a href="details.php?id=<?php echo $episode['anime_id']; ?>" 
                               class="text-blue-400 hover:text-blue-300 transition-colors text-sm">
                                <?php echo htmlspecialchars($episode['anime_title']); ?>
                            </a>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <?php if ($prev_id): ?>
                            <a href="watch2.php?id=<?php echo $prev_id; ?>" 
                               class="bg-gray-700/50 hover:bg-gray-600 text-gray-300 px-4 py-2 rounded-lg text-sm transition-colors">
                                <i class="fas fa-step-backward ml-2"></i>
                                الحلقة السابقة
                            </a>
                        <?php endif; ?>
                        <?php if ($next_id): ?>
                            <a href="watch2.php?id=<?php echo $next_id; ?>" 
                               class="bg-gray-700/50 hover:bg-gray-600 text-gray-300 px-4 py-2 rounded-lg text-sm transition-colors">
                                الحلقة التالية
                                <i class="fas fa-step-forward mr-2"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- مشغل الفيديو -->
                <div class="bg-black rounded-xl overflow-hidden mb-6 shadow-2xl">
                    <div class="relative aspect-video">
                        <iframe id="player-iframe"
                                class="absolute inset-0 w-full h-full"
                                src=""
                                allowfullscreen>
                        </iframe>
                    </div>
                </div>

                <!-- قائمة السيرفرات -->
                <div class="bg-gray-800/50 backdrop-blur-sm rounded-xl p-6">
                    <h3 class="text-xl font-semibold text-white mb-4">سيرفرات المشاهدة</h3>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                        <?php foreach ($servers as $index => $server): ?>
                            <button onclick="changeServer('<?php echo htmlspecialchars($server['url']); ?>', this)"
                                    class="server-btn bg-gray-700/50 hover:bg-gray-600 text-gray-300 px-4 py-3 rounded-xl transition-all duration-300 text-sm">
                                <?php echo htmlspecialchars($server['name']); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- القائمة الجانبية -->
            <div class="lg:col-span-3">
                <div class="bg-gray-800/50 backdrop-blur-sm rounded-xl p-6 sticky top-24">
                    <h3 class="text-xl font-semibold text-white mb-4">قائمة الحلقات</h3>
                    <div class="h-[60vh] overflow-y-auto custom-scrollbar">
                        <div class="grid gap-2">
                            <?php 
                            $stmt = $db->prepare("SELECT id, title FROM episodes WHERE anime_id = :anime_id ORDER BY id DESC");
                            $stmt->bindValue(':anime_id', $episode['anime_id'], SQLITE3_INTEGER);
                            $episodes_list = $stmt->execute();
                            while ($ep = $episodes_list->fetchArray(SQLITE3_ASSOC)): 
                            ?>
                                <a href="watch2.php?id=<?php echo $ep['id']; ?>" 
                                   class="<?php echo ($ep['id'] == $episode_id) ? 'bg-blue-600/20 text-blue-400' : 'bg-gray-700/50 text-gray-300 hover:bg-gray-600/50'; ?> 
                                          px-4 py-3 rounded-lg transition-colors text-sm">
                                    <?php echo htmlspecialchars($ep['title']); ?>
                                </a>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <style>
        .custom-scrollbar {
            scrollbar-width: thin;
            scrollbar-color: rgba(59, 130, 246, 0.5) rgba(17, 24, 39, 0.7);
        }
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: rgba(17, 24, 39, 0.7);
            border-radius: 10px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(59, 130, 246, 0.5);
            border-radius: 10px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: rgba(59, 130, 246, 0.7);
        }
    </style>

    <script>
        function changeServer(url, button) {
            document.getElementById('player-iframe').src = url;
            document.querySelectorAll('.server-btn').forEach(btn => {
                btn.classList.remove('bg-blue-600/50', 'text-white');
                btn.classList.add('bg-gray-700/50');
            });
            button.classList.remove('bg-gray-700/50');
            button.classList.add('bg-blue-600/50', 'text-white');
        }

        window.onload = function() {
            <?php if (!empty($servers)): ?>
            const firstServerBtn = document.querySelector('.server-btn');
            changeServer('<?php echo htmlspecialchars($servers[0]['url']); ?>', firstServerBtn);
            <?php endif; ?>
        }
    </script>
<script type='text/javascript' src='//pl25648932.effectiveratecpm.com/d9/49/8c/d9498cda075310c8ee8091d8752584d4.js'></script>
    <?php $db->close(); ?>
</body>
</html>