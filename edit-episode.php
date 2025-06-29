<?php
session_start();

// التحقق من تسجيل الدخول
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin.php');
    exit;
}

try {
    // التحقق من وجود معرف الحلقة
    if (!isset($_GET['url'])) {
        throw new Exception("لم يتم تحديد الحلقة");
    }

    $db = new SQLite3('anime_episodes.db');
    
    // جلب بيانات الحلقة
    $stmt = $db->prepare('
        SELECT *
        FROM episodes 
        WHERE episode_url = :url
        LIMIT 1
    ');
    $stmt->bindValue(':url', $_GET['url'], SQLITE3_TEXT);
    $result = $stmt->execute();
    $episode = $result->fetchArray(SQLITE3_ASSOC);
    
    if (!$episode) {
        throw new Exception("الحلقة غير موجودة");
    }

    // معالجة تحديث البيانات
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $stmt = $db->prepare('
            UPDATE episodes 
            SET 
                episode_title = :episode_title,
                anime_title = :anime_title,
                episode_url = :episode_url,
                anime_url = :anime_url,
                anime_thumbnail = :anime_thumbnail,
                details = :details,
                genres = :genres
            WHERE episode_url = :original_url
        ');

        $stmt->bindValue(':episode_title', $_POST['episode_title'], SQLITE3_TEXT);
        $stmt->bindValue(':anime_title', $_POST['anime_title'], SQLITE3_TEXT);
        $stmt->bindValue(':episode_url', $_POST['episode_url'], SQLITE3_TEXT);
        $stmt->bindValue(':anime_url', $_POST['anime_url'], SQLITE3_TEXT);
        $stmt->bindValue(':anime_thumbnail', $_POST['anime_thumbnail'], SQLITE3_TEXT);
        
        // تحديث التفاصيل
        $details = [
            'النوع' => $_POST['type'],
            'بداية العرض' => $_POST['start_date'],
            'حالة الأنمي' => $_POST['status'],
            'عدد الحلقات' => $_POST['episodes_count'],
            'مدة الحلقة' => $_POST['episode_duration'],
            'الموسم' => $_POST['season'],
            'servers' => json_decode($_POST['servers'], true)
        ];
        
        $stmt->bindValue(':details', json_encode($details), SQLITE3_TEXT);

        // معالجة التصنيفات
        $genres = [];
        $genre_names = $_POST['genre_names'] ?? [];
        
        foreach ($genre_names as $genre) {
            if (!empty(trim($genre))) {
                $genres[] = trim($genre);
            }
        }
        
        $stmt->bindValue(':genres', json_encode($genres), SQLITE3_TEXT);
        $stmt->bindValue(':original_url', $_GET['url'], SQLITE3_TEXT);

        if ($stmt->execute()) {
            $success_message = "تم تحديث البيانات بنجاح";
            // تحديث بيانات الحلقة المعروضة
            $episode = array_merge($episode, $_POST);
            $episode['details'] = json_encode($details);
        } else {
            throw new Exception("حدث خطأ أثناء تحديث البيانات");
        }
    }

} catch (Exception $e) {
    $error_message = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعديل الحلقة - لوحة التحكم</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen">
        <!-- القائمة العلوية -->
        <nav class="bg-white shadow-lg">
            <div class="max-w-7xl mx-auto px-4">
                <div class="flex justify-between h-16">
                    <div class="flex items-center">
                        <a href="admin.php" class="text-xl font-bold">لوحة التحكم</a>
                    </div>
                </div>
            </div>
        </nav>

        <!-- المحتوى الرئيسي -->
        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <?php if (isset($error_message)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($success_message)): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <div class="bg-white shadow rounded-lg p-6">
                <h2 class="text-2xl font-bold mb-6">تعديل الحلقة</h2>
                
                <form method="POST" action="" class="space-y-6">
                    <!-- معلومات الحلقة الأساسية -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                عنوان الحلقة
                            </label>
                            <input type="text" 
                                   name="episode_title" 
                                   value="<?php echo htmlspecialchars($episode['episode_title']); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                اسم الأنمي
                            </label>
                            <input type="text" 
                                   name="anime_title" 
                                   value="<?php echo htmlspecialchars($episode['anime_title']); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                رابط الحلقة
                            </label>
                            <input type="text" 
                                   name="episode_url" 
                                   value="<?php echo htmlspecialchars($episode['episode_url']); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                رابط الأنمي
                            </label>
                            <input type="text" 
                                   name="anime_url" 
                                   value="<?php echo htmlspecialchars($episode['anime_url']); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                صورة الأنمي
                            </label>
                            <input type="text" 
                                   name="anime_thumbnail" 
                                   value="<?php echo htmlspecialchars($episode['anime_thumbnail']); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>

                    <!-- التفاصيل الإضافية -->
                    <?php 
                    $details = json_decode($episode['details'], true);
                    ?>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                النوع
                            </label>
                            <input type="text" 
                                   name="type" 
                                   value="<?php echo htmlspecialchars($details['النوع'] ?? ''); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                بداية العرض
                            </label>
                            <input type="text" 
                                   name="start_date" 
                                   value="<?php echo htmlspecialchars($details['بداية العرض'] ?? ''); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                حالة الأنمي
                            </label>
                            <input type="text" 
                                   name="status" 
                                   value="<?php echo htmlspecialchars($details['حالة الأنمي'] ?? ''); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                عدد الحلقات
                            </label>
                            <input type="text" 
                                   name="episodes_count" 
                                   value="<?php echo htmlspecialchars($details['عدد الحلقات'] ?? ''); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                مدة الحلقة
                            </label>
                            <input type="text" 
                                   name="episode_duration" 
                                   value="<?php echo htmlspecialchars($details['مدة الحلقة'] ?? ''); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                الموسم
                            </label>
                            <input type="text" 
                                   name="season" 
                                   value="<?php echo htmlspecialchars($details['الموسم'] ?? ''); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>

                    <!-- السيرفرات والتصنيفات -->
                    <div class="mb-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-medium text-gray-700">سيرفرات المشاهدة</h3>
                            <button type="button" 
                                    onclick="addServer()" 
                                    class="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600">
                                <i class="fas fa-plus ml-2"></i>إضافة سيرفر جديد
                            </button>
                        </div>

                        <div id="servers-container" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <?php
                            $servers = isset($details['servers']) ? $details['servers'] : [];
                            foreach ($servers as $index => $server):
                            ?>
                            <div class="server-card bg-white p-4 rounded-lg shadow-md border border-gray-200">
                                <div class="mb-3">
                                    <label class="block text-sm font-medium text-gray-600 mb-1">اسم السيرفر</label>
                                    <input type="text"
                                           name="server_names[]"
                                           value="<?php echo htmlspecialchars($server['name']); ?>"
                                           placeholder="مثال: Server 1"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="block text-sm font-medium text-gray-600 mb-1">رابط السيرفر</label>
                                    <input type="text"
                                           name="server_urls[]"
                                           value="<?php echo htmlspecialchars($server['url']); ?>"
                                           placeholder="https://"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                                </div>
                                
                                <div class="flex justify-end">
                                    <button type="button" 
                                            onclick="removeServer(this)" 
                                            class="px-3 py-1 bg-red-500 text-white rounded-md hover:bg-red-600 text-sm">
                                        <i class="fas fa-trash ml-1"></i>حذف
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="mb-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-medium text-gray-700">التصنيفات</h3>
                            <button type="button" 
                                    onclick="addGenre()" 
                                    class="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600">
                                <i class="fas fa-plus ml-2"></i>إضافة تصنيف جديد
                            </button>
                        </div>

                        <div id="genres-container" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <?php
                            $genres = json_decode($episode['genres'], true) ?? [];
                            foreach ($genres as $index => $genre):
                            ?>
                            <div class="genre-card bg-white p-4 rounded-lg shadow-md border border-gray-200">
                                <div class="mb-3">
                                    <label class="block text-sm font-medium text-gray-600 mb-1">اسم التصنيف</label>
                                    <input type="text"
                                           name="genre_names[]"
                                           value="<?php echo htmlspecialchars($genre); ?>"
                                           placeholder="مثال: أكشن"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                                </div>
                                
                                <div class="flex justify-end">
                                    <button type="button" 
                                            onclick="removeGenre(this)" 
                                            class="px-3 py-1 bg-red-500 text-white rounded-md hover:bg-red-600 text-sm">
                                        <i class="fas fa-trash ml-1"></i>حذف
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- أزرار التحكم -->
                    <div class="flex justify-end gap-4">
                        <a href="admin.php" 
                           class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600">
                            إلغاء
                        </a>
                        <button type="submit" 
                                class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600">
                            حفظ التغييرات
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    function addServer() {
        const container = document.getElementById('servers-container');
        const serverCard = document.createElement('div');
        serverCard.className = 'server-card bg-white p-4 rounded-lg shadow-md border border-gray-200';
        
        serverCard.innerHTML = `
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-600 mb-1">اسم السيرفر</label>
                <input type="text"
                       name="server_names[]"
                       placeholder="مثال: Server 1"
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
            </div>
            
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-600 mb-1">رابط السيرفر</label>
                <input type="text"
                       name="server_urls[]"
                       placeholder="https://"
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
            </div>
            
            <div class="flex justify-end">
                <button type="button" 
                        onclick="removeServer(this)" 
                        class="px-3 py-1 bg-red-500 text-white rounded-md hover:bg-red-600 text-sm">
                    <i class="fas fa-trash ml-1"></i>حذف
                </button>
            </div>
        `;
        
        container.appendChild(serverCard);
    }

    function removeServer(button) {
        button.closest('.server-card').remove();
    }

    // تعديل معالجة النموذج
    document.querySelector('form').addEventListener('submit', function(e) {
        const serverNames = document.getElementsByName('server_names[]');
        const serverUrls = document.getElementsByName('server_urls[]');
        const servers = [];
        
        for (let i = 0; i < serverNames.length; i++) {
            if (serverNames[i].value && serverUrls[i].value) {
                servers.push({
                    name: serverNames[i].value,
                    url: serverUrls[i].value
                });
            }
        }
        
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'servers';
        input.value = JSON.stringify(servers);
        this.appendChild(input);

        // معالجة التصنيفات
        const genreNames = document.getElementsByName('genre_names[]');
        const genres = [];
        
        for (let i = 0; i < genreNames.length; i++) {
            if (genreNames[i].value.trim()) {
                genres.push(genreNames[i].value.trim());
            }
        }
        
        const genresInput = document.createElement('input');
        genresInput.type = 'hidden';
        genresInput.name = 'genres';
        genresInput.value = JSON.stringify(genres);
        this.appendChild(genresInput);
    });

    function addGenre() {
        const container = document.getElementById('genres-container');
        const genreCard = document.createElement('div');
        genreCard.className = 'genre-card bg-white p-4 rounded-lg shadow-md border border-gray-200';
        
        genreCard.innerHTML = `
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-600 mb-1">اسم التصنيف</label>
                <input type="text"
                       name="genre_names[]"
                       placeholder="مثال: أكشن"
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
            </div>
            
            <div class="flex justify-end">
                <button type="button" 
                        onclick="removeGenre(this)" 
                        class="px-3 py-1 bg-red-500 text-white rounded-md hover:bg-red-600 text-sm">
                    <i class="fas fa-trash ml-1"></i>حذف
                </button>
            </div>
        `;
        
        container.appendChild(genreCard);
    }

    function removeGenre(button) {
        button.closest('.genre-card').remove();
    }
    </script>
</body>
</html> 