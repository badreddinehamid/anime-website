<?php
session_start();

// التحقق من تسجيل الدخول
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin.php');
    exit;
}

try {
    $db = new SQLite3('anime_database.db');
    
    // معالجة الإضافة/التحديث
    if (isset($_POST['save_anime'])) {
        $anime_id = isset($_POST['anime_id']) ? (int)$_POST['anime_id'] : null;
        $title = $_POST['title'];
        $story = $_POST['story'];
        $year = (int)$_POST['year'];
        $status = $_POST['status'];
        $type = $_POST['type'];
        $episodes_count = (int)$_POST['episodes_count'];
        $poster = $_POST['poster'];
        $genres = explode(',', $_POST['genres']);
        // Removed watch_link

        $db->exec("BEGIN TRANSACTION");
        try {
            if ($anime_id) {
                $stmt = $db->prepare("UPDATE anime SET title = :title, story = :story, year = :year, status = :status, type = :type, episodes_count = :episodes_count, poster = :poster WHERE id = :id");
                $stmt->bindValue(':title', $title, SQLITE3_TEXT);
                $stmt->bindValue(':story', $story, SQLITE3_TEXT);
                $stmt->bindValue(':year', $year, SQLITE3_INTEGER);
                $stmt->bindValue(':status', $status, SQLITE3_TEXT);
                $stmt->bindValue(':type', $type, SQLITE3_TEXT);
                $stmt->bindValue(':episodes_count', $episodes_count, SQLITE3_INTEGER);
                $stmt->bindValue(':poster', $poster, SQLITE3_TEXT);
                $stmt->bindValue(':id', $anime_id, SQLITE3_INTEGER);
                $stmt->execute();

                $stmt = $db->prepare("DELETE FROM genres WHERE anime_id = :anime_id");
                $stmt->bindValue(':anime_id', $anime_id, SQLITE3_INTEGER);
                $stmt->execute();
            } else {
                $stmt = $db->prepare("INSERT INTO anime (title, story, year, status, type, episodes_count, poster) VALUES (:title, :story, :year, :status, :type, :episodes_count, :poster)");
                $stmt->bindValue(':title', $title, SQLITE3_TEXT);
                $stmt->bindValue(':story', $story, SQLITE3_TEXT);
                $stmt->bindValue(':year', $year, SQLITE3_INTEGER);
                $stmt->bindValue(':status', $status, SQLITE3_TEXT);
                $stmt->bindValue(':type', $type, SQLITE3_TEXT);
                $stmt->bindValue(':episodes_count', $episodes_count, SQLITE3_INTEGER);
                $stmt->bindValue(':poster', $poster, SQLITE3_TEXT);
                $stmt->execute();
                $anime_id = $db->lastInsertRowID();
            }

            foreach ($genres as $genre) {
                $genre = trim($genre);
                if (!empty($genre)) {
                    $stmt = $db->prepare("INSERT INTO genres (anime_id, genre) VALUES (:anime_id, :genre)");
                    $stmt->bindValue(':anime_id', $anime_id, SQLITE3_INTEGER);
                    $stmt->bindValue(':genre', $genre, SQLITE3_TEXT);
                    $stmt->execute();
                }
            }

            // إضافة الحلقات
            if (isset($_POST['episodes']) && is_array($_POST['episodes'])) {
                foreach ($_POST['episodes'] as $episode) {
                    if (!empty($episode['number']) && !empty($episode['servers'])) {
                        $stmt = $db->prepare("INSERT INTO episodes (anime_id, number) VALUES (:anime_id, :number)");
                        $stmt->bindValue(':anime_id', $anime_id, SQLITE3_INTEGER);
                        $stmt->bindValue(':number', $episode['number'], SQLITE3_INTEGER);
                        $stmt->execute();
                        $episode_id = $db->lastInsertRowID();

                        foreach ($episode['servers'] as $server) {
                            if (!empty($server['name']) && !empty($server['url'])) {
                                $stmt = $db->prepare("INSERT INTO servers (episode_id, name, url) VALUES (:episode_id, :name, :url)");
                                $stmt->bindValue(':episode_id', $episode_id, SQLITE3_INTEGER);
                                $stmt->bindValue(':name', $server['name'], SQLITE3_TEXT);
                                $stmt->bindValue(':url', $server['url'], SQLITE3_TEXT);
                                $stmt->execute();
                            }
                        }
                    }
                }
            }

            $db->exec("COMMIT");
            header('Location: manage-anime.php?success=1');
            exit;
        } catch (Exception $e) {
            $db->exec("ROLLBACK");
            $error_message = "فشل حفظ الأنمي: " . $e->getMessage();
        }
    }
} catch (Exception $e) {
    $error_message = "خطأ في قاعدة البيانات: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إضافة أنمي جديد - ANIME-WR</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-900 text-white">
    <!-- القائمة العلوية -->
    <nav class="bg-gray-800 border-b border-gray-700">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center gap-8">
                    <a href="admin.php" class="text-2xl font-bold text-blue-500">ANIME-WR</a>
                    <div class="flex items-center gap-4">
                        <a href="admin.php" class="text-gray-300 hover:text-white">لوحة التحكم</a>
                        <a href="manage-anime.php" class="text-white border-b-2 border-blue-500 px-1">إدارة الأنمي</a>
                        <a href="manage-backgrounds.php" class="text-gray-300 hover:text-white">إدارة الخلفيات</a>
                    </div>
                </div>
                <div class="flex items-center">
                    <a href="?logout=1" class="text-red-500 hover:text-red-400">
                        <i class="fas fa-sign-out-alt ml-2"></i>
                        تسجيل الخروج
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <?php if (isset($error_message)): ?>
            <div class="bg-red-900/50 border border-red-500 text-red-300 px-4 py-3 rounded mb-4">
                <i class="fas fa-exclamation-circle ml-2"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <div class="bg-gray-800 rounded-lg shadow-lg">
            <!-- Tabs Navigation -->
            <div class="border-b border-gray-700">
                <nav class="flex">
                    <button type="button" onclick="switchTab('info')" 
                            class="tab-btn active px-6 py-4 border-b-2 border-blue-500 text-blue-500 font-medium">
                        <i class="fas fa-info-circle ml-2"></i>
                        معلومات الأنمي
                    </button>
                    <button type="button" onclick="switchTab('episodes')" 
                            class="tab-btn px-6 py-4 border-b-2 border-transparent text-gray-400 hover:text-gray-300 font-medium">
                        <i class="fas fa-play-circle ml-2"></i>
                        الحلقات والسيرفرات
                    </button>
                </nav>
            </div>

            <form action="" method="POST" class="p-6">
                <!-- معلومات الأنمي Tab -->
                <div id="info-tab" class="tab-content">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <!-- Column 1: Basic Info -->
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">عنوان الأنمي</label>
                                <input type="text" name="title" required
                                       class="w-full bg-gray-700 border-gray-600 rounded-lg text-white px-4 py-2">
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-300 mb-1">السنة</label>
                                    <input type="number" name="year" required
                                           class="w-full bg-gray-700 border-gray-600 rounded-lg text-white px-4 py-2">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-300 mb-1">عدد الحلقات</label>
                                    <input type="number" name="episodes_count" required
                                           class="w-full bg-gray-700 border-gray-600 rounded-lg text-white px-4 py-2">
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-300 mb-1">الحالة</label>
                                    <select name="status" required
                                            class="w-full bg-gray-700 border-gray-600 rounded-lg text-white px-4 py-2">
                                        <option value="مستمر">مستمر</option>
                                        <option value="مكتمل">مكتمل</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-300 mb-1">النوع</label>
                                    <select name="type" required
                                            class="w-full bg-gray-700 border-gray-600 rounded-lg text-white px-4 py-2">
                                        <option value="TV">TV</option>
                                        <option value="Movie">Movie</option>
                                        <option value="OVA">OVA</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Column 2: Media -->
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">رابط الصورة</label>
                                <input type="url" name="poster" required
                                       class="w-full bg-gray-700 border-gray-600 rounded-lg text-white px-4 py-2">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">التصنيفات</label>
                                <input type="text" name="genres" required
                                       placeholder="أكشن, مغامرة, كوميدي"
                                       class="w-full bg-gray-700 border-gray-600 rounded-lg text-white px-4 py-2">
                            </div>
                        </div>

                        <!-- Column 3: Story -->
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">القصة</label>
                            <textarea name="story" required rows="11"
                                      class="w-full bg-gray-700 border-gray-600 rounded-lg text-white px-4 py-2"></textarea>
                        </div>
                    </div>
                </div>

                <!-- الحلقات Tab -->
                <div id="episodes-tab" class="tab-content hidden">
                    <div class="space-y-6">
                        <div id="episodes-container">
                            <!-- سيتم إضافة الحلقات هنا -->
                        </div>
                        
                        <button type="button" onclick="addEpisode()" 
                                class="w-full py-3 border-2 border-dashed border-gray-600 rounded-lg text-gray-400 hover:border-gray-500 hover:text-gray-300 transition-colors">
                            <i class="fas fa-plus-circle ml-2"></i>
                            إضافة حلقة جديدة
                        </button>
                    </div>
                </div>

                <div class="flex justify-between mt-6 pt-6 border-t border-gray-700">
                    <a href="manage-anime.php" 
                       class="px-4 py-2 bg-gray-700 text-gray-300 rounded-lg hover:bg-gray-600 transition-colors">
                        <i class="fas fa-arrow-right ml-2"></i>
                        رجوع
                    </a>
                    <button type="submit" name="save_anime" 
                            class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-save ml-2"></i>
                        حفظ الأنمي
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(tab => tab.classList.add('hidden'));
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('border-blue-500', 'text-blue-500');
                btn.classList.add('border-transparent', 'text-gray-400');
            });
            
            document.getElementById(tabName + '-tab').classList.remove('hidden');
            event.currentTarget.classList.remove('border-transparent', 'text-gray-400');
            event.currentTarget.classList.add('border-blue-500', 'text-blue-500');
        }

        function addEpisode() {
            const container = document.getElementById('episodes-container');
            const episodeCount = container.children.length + 1;
            
            const episodeHtml = `
                <div class="episode-item bg-gray-700/50 rounded-lg p-4 mb-4">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium">الحلقة ${episodeCount}</h3>
                        <button type="button" onclick="this.closest('.episode-item').remove()" 
                                class="text-red-400 hover:text-red-300">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                    <input type="hidden" name="episodes[${episodeCount}][number]" value="${episodeCount}">
                    
                    <div class="space-y-4">
                        <div id="servers-${episodeCount}" class="space-y-3">
                            <!-- سيتم إضافة السيرفرات هنا -->
                        </div>
                        
                        <button type="button" onclick="addServer(${episodeCount})" 
                                class="w-full py-2 border border-dashed border-gray-500 rounded text-gray-400 hover:text-gray-300">
                            <i class="fas fa-plus-circle ml-2"></i>
                            إضافة سيرفر
                        </button>
                    </div>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', episodeHtml);
            addServer(episodeCount); // إضافة سيرفر افتراضي
        }

        function addServer(episodeNumber) {
            const container = document.getElementById(`servers-${episodeNumber}`);
            const serverCount = container.children.length + 1;
            
            const serverHtml = `
                <div class="server-item grid grid-cols-5 gap-4">
                    <div class="col-span-1">
                        <input type="text" name="episodes[${episodeNumber}][servers][${serverCount}][name]"
                               placeholder="اسم السيرفر"
                               class="w-full bg-gray-700 border-gray-600 rounded text-white px-3 py-2">
                    </div>
                    <div class="col-span-3">
                        <input type="url" name="episodes[${episodeNumber}][servers][${serverCount}][url]"
                               placeholder="رابط السيرفر"
                               class="w-full bg-gray-700 border-gray-600 rounded text-white px-3 py-2">
                    </div>
                    <div class="col-span-1">
                        <button type="button" onclick="this.closest('.server-item').remove()"
                                class="w-full px-4 py-2 bg-red-900/30 border border-red-500 text-red-400 rounded hover:bg-red-900/50">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', serverHtml);
        }
    </script>
</body>
</html>