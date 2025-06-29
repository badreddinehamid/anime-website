<?php
session_start();

// التحقق من تسجيل الدخول
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin.php');
    exit;
}

try {
    $db = new SQLite3('anime_database.db');
    
    // معالجة الحذف
    // في جزء معالجة الحذف
    if (isset($_POST['delete_anime'])) {
        $anime_id = (int)$_POST['anime_id'];
        $db->exec("BEGIN TRANSACTION");
        try {
            // حذف التصنيفات المرتبطة
            $stmt = $db->prepare("DELETE FROM genres WHERE anime_id = :id");
            $stmt->bindValue(':id', $anime_id, SQLITE3_INTEGER);
            $stmt->execute();
    
            // حذف الحلقات المرتبطة
            $stmt = $db->prepare("DELETE FROM episodes WHERE anime_id = :id");
            $stmt->bindValue(':id', $anime_id, SQLITE3_INTEGER);
            $stmt->execute();
    
            // حذف السيرفرات المرتبطة بالحلقات
            $stmt = $db->prepare("DELETE FROM servers WHERE episode_id IN (SELECT id FROM episodes WHERE anime_id = :id)");
            $stmt->bindValue(':id', $anime_id, SQLITE3_INTEGER);
            $stmt->execute();
    
            // حذف الأنمي
            $stmt = $db->prepare("DELETE FROM anime WHERE id = :id");
            $stmt->bindValue(':id', $anime_id, SQLITE3_INTEGER);
            $stmt->execute();
    
            $db->exec("COMMIT");
            $success_message = "تم حذف الأنمي بنجاح";
        } catch (Exception $e) {
            $db->exec("ROLLBACK");
            $error_message = "فشل حذف الأنمي: " . $e->getMessage();
        }
    }
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
    
        $db->exec("BEGIN TRANSACTION");
        try {
            if ($anime_id) {
                // تحديث الأنمي الموجود
                $stmt = $db->prepare("UPDATE anime SET title = :title, story = :story, year = :year, 
                                    status = :status, type = :type, episodes_count = :episodes_count, 
                                    poster = :poster WHERE id = :id");
                $stmt->bindValue(':title', $title, SQLITE3_TEXT);
                $stmt->bindValue(':story', $story, SQLITE3_TEXT);
                $stmt->bindValue(':year', $year, SQLITE3_INTEGER);
                $stmt->bindValue(':status', $status, SQLITE3_TEXT);
                $stmt->bindValue(':type', $type, SQLITE3_TEXT);
                $stmt->bindValue(':episodes_count', $episodes_count, SQLITE3_INTEGER);
                $stmt->bindValue(':poster', $poster, SQLITE3_TEXT);
                $stmt->bindValue(':id', $anime_id, SQLITE3_INTEGER);
                $stmt->execute();
                
                // حذف التصنيفات القديمة
                $stmt = $db->prepare("DELETE FROM genres WHERE anime_id = :anime_id");
                $stmt->bindValue(':anime_id', $anime_id, SQLITE3_INTEGER);
                $stmt->execute();
            } else {
                // إضافة أنمي جديد
                $stmt = $db->prepare("INSERT INTO anime (title, story, year, status, type, episodes_count, poster) 
                                    VALUES (:title, :story, :year, :status, :type, :episodes_count, :poster)");
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
    
            // إضافة التصنيفات
            foreach ($genres as $genre) {
                $genre = trim($genre);
                if (!empty($genre)) {
                    $stmt = $db->prepare("INSERT INTO genres (anime_id, genre) VALUES (:anime_id, :genre)");
                    $stmt->bindValue(':anime_id', $anime_id, SQLITE3_INTEGER);
                    $stmt->bindValue(':genre', $genre, SQLITE3_TEXT);
                    $stmt->execute();
                }
            }
    
            $db->exec("COMMIT");
            $success_message = "تم حفظ الأنمي بنجاح";
        } catch (Exception $e) {
            $db->exec("ROLLBACK");
            $error_message = "فشل حفظ الأنمي: " . $e->getMessage();
        }
    }

    // جلب قائمة الأنمي
    $search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
    $items_per_page = 10;
    $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $current_page = max(1, $current_page);
    $offset = ($current_page - 1) * $items_per_page;

    // جلب إجمالي عدد الأنمي للصفحات
    if (!empty($search_query)) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM anime WHERE title LIKE :search");
        $stmt->bindValue(':search', "%$search_query%", SQLITE3_TEXT);
        $total_items = $stmt->execute()->fetchArray()[0];
    } else {
        $total_items = $db->querySingle("SELECT COUNT(*) FROM anime");
    }
    
    $total_pages = ceil($total_items / $items_per_page);

    // جلب قائمة الأنمي مع الترقيم
    if (!empty($search_query)) {
        $stmt = $db->prepare("
            SELECT 
                a.*,
                GROUP_CONCAT(g.genre) as genres
            FROM anime a
            LEFT JOIN genres g ON g.anime_id = a.id
            WHERE a.title LIKE :search
            GROUP BY a.id
            ORDER BY a.id DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':search', "%$search_query%", SQLITE3_TEXT);
        $stmt->bindValue(':limit', $items_per_page, SQLITE3_INTEGER);
        $stmt->bindValue(':offset', $offset, SQLITE3_INTEGER);
        $anime_list = $stmt->execute();
    } else {
        $stmt = $db->prepare("
            SELECT 
                a.*,
                GROUP_CONCAT(g.genre) as genres
            FROM anime a
            LEFT JOIN genres g ON g.anime_id = a.id
            GROUP BY a.id
            ORDER BY a.id DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':limit', $items_per_page, SQLITE3_INTEGER);
        $stmt->bindValue(':offset', $offset, SQLITE3_INTEGER);
        $anime_list = $stmt->execute();
    }
    // Remove this query as it's overwriting the paginated results
    // $anime_list = $db->query("SELECT a.*, GROUP_CONCAT(g.genre) as genres FROM anime a LEFT JOIN genres g ON g.anime_id = a.id GROUP BY a.id ORDER BY a.id DESC");
} catch (Exception $e) {
    $error_message = "خطأ في قاعدة البيانات: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الأنمي - ANIME-WR</title>
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
        <!-- رسائل النجاح والخطأ -->
        <?php if (isset($success_message)): ?>
            <div class="bg-green-900/50 border border-green-500 text-green-300 px-4 py-3 rounded mb-4 flex items-center">
                <i class="fas fa-check-circle ml-2"></i>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="bg-red-900/50 border border-red-500 text-red-300 px-4 py-3 rounded mb-4 flex items-center">
                <i class="fas fa-exclamation-circle ml-2"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- قائمة الأنمي -->
            <div class="lg:col-span-3"> <!-- Changed from lg:col-span-2 to lg:col-span-3 -->
                <div class="bg-gray-800 rounded-lg shadow-lg p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-bold flex items-center gap-2">
                            <i class="fas fa-list text-blue-500"></i>
                            قائمة الأنمي
                            <span class="text-sm text-gray-400">(<?php echo $total_items; ?> أنمي)</span>
                        </h2>
                        <div class="flex items-center gap-4"> <!-- Added container for buttons -->
                            <a href="add-anime.php" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                <i class="fas fa-plus-circle ml-2"></i>
                                إضافة أنمي جديد
                            </a>
                            <form action="" method="GET" class="flex gap-2">
                                <div class="relative">
                                    <input type="text" name="search" 
                                           value="<?php echo htmlspecialchars($search_query); ?>"
                                           placeholder="ابحث عن أنمي..."
                                           class="w-64 bg-gray-700 border-gray-600 rounded-lg text-white pl-10 pr-4 py-2 focus:ring-blue-500 focus:border-blue-500">
                                    <i class="fas fa-search absolute right-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-700">
                            <thead>
                                <tr class="bg-gray-700/50">
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-300 uppercase">العنوان</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-300 uppercase">السنة</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-300 uppercase">النوع</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-300 uppercase">الحالة</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-300 uppercase">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-700">
                                <?php while ($anime = $anime_list->fetchArray(SQLITE3_ASSOC)): ?>
                                <tr class="hover:bg-gray-700/50 transition-colors">
                                    <td class="px-6 py-4 text-sm">
                                        <div class="flex items-center gap-3">
                                            <img src="<?php echo htmlspecialchars($anime['poster']); ?>" 
                                                 class="w-10 h-14 object-cover rounded"
                                                 alt="<?php echo htmlspecialchars($anime['title']); ?>">
                                            <span><?php echo htmlspecialchars($anime['title']); ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm"><?php echo htmlspecialchars($anime['year']); ?></td>
                                    <td class="px-6 py-4 text-sm"><?php echo htmlspecialchars($anime['type']); ?></td>
                                    <td class="px-6 py-4 text-sm">
                                        <span class="px-2 py-1 rounded-full text-xs <?php echo $anime['status'] === 'مستمر' ? 'bg-green-900/50 text-green-400 border border-green-500' : 'bg-blue-900/50 text-blue-400 border border-blue-500'; ?>">
                                            <?php echo htmlspecialchars($anime['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        <div class="flex gap-3">
                                            <a href="manage-episodes.php?anime_id=<?php echo $anime['id']; ?>"
                                               class="text-yellow-400 hover:text-yellow-300 transition-colors"
                                               title="إدارة الحلقات">
                                                <i class="fas fa-play-circle"></i>
                                            </a>
                                            <button onclick='editAnime(<?php echo htmlspecialchars(json_encode($anime)); ?>)'
                                                    class="text-blue-400 hover:text-blue-300 transition-colors">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form action="" method="POST" class="inline" 
                                                  onsubmit="return confirm('هل أنت متأكد من حذف هذا الأنمي؟');">
                                                <input type="hidden" name="anime_id" value="<?php echo $anime['id']; ?>">
                                                <button type="submit" name="delete_anime" 
                                                        class="text-red-400 hover:text-red-300 transition-colors">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                        
                        <!-- أزرار التنقل بين الصفحات -->
                        <?php if ($total_pages > 1): ?>
                        <div class="mt-6 flex justify-center gap-2">
                            <?php if ($current_page > 1): ?>
                                <a href="?page=<?php echo $current_page - 1; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>" 
                                   class="px-3 py-1 bg-gray-700 text-gray-300 rounded hover:bg-gray-600 transition-colors">
                                    <i class="fas fa-chevron-right ml-1"></i>
                                    السابق
                                </a>
                            <?php endif; ?>

                            <?php
                            $start_page = max(1, $current_page - 2);
                            $end_page = min($total_pages, $current_page + 2);

                            if ($start_page > 1) {
                                echo '<a href="?page=1' . (!empty($search_query) ? '&search=' . urlencode($search_query) : '') . '" 
                                         class="px-3 py-1 bg-gray-700 text-gray-300 rounded hover:bg-gray-600 transition-colors">1</a>';
                                if ($start_page > 2) {
                                    echo '<span class="px-2 text-gray-500">...</span>';
                                }
                            }

                            for ($i = $start_page; $i <= $end_page; $i++) {
                                $active_class = $i === $current_page ? 'bg-blue-600 text-white' : 'bg-gray-700 text-gray-300 hover:bg-gray-600';
                                echo '<a href="?page=' . $i . (!empty($search_query) ? '&search=' . urlencode($search_query) : '') . '" 
                                         class="px-3 py-1 ' . $active_class . ' rounded transition-colors">' . $i . '</a>';
                            }

                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) {
                                    echo '<span class="px-2 text-gray-500">...</span>';
                                }
                                echo '<a href="?page=' . $total_pages . (!empty($search_query) ? '&search=' . urlencode($search_query) : '') . '" 
                                         class="px-3 py-1 bg-gray-700 text-gray-300 rounded hover:bg-gray-600 transition-colors">' . $total_pages . '</a>';
                            }
                            ?>

                            <?php if ($current_page < $total_pages): ?>
                                <a href="?page=<?php echo $current_page + 1; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>" 
                                   class="px-3 py-1 bg-gray-700 text-gray-300 rounded hover:bg-gray-600 transition-colors">
                                    التالي
                                    <i class="fas fa-chevron-left mr-1"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal للتعديل -->
        <div id="editModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4 overflow-y-auto">
            <div class="bg-gray-800 rounded-lg shadow-xl p-6 w-4/5 mx-auto max-h-[80vh] overflow-y-auto">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-bold">تعديل الأنمي</h3>
                    <button onclick="closeModal()" class="text-gray-400 hover:text-gray-300">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form action="" method="POST" class="space-y-4">
                    <input type="hidden" name="anime_id" id="anime_id">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">العنوان</label>
                        <input type="text" name="title" id="title" required
                               class="w-full bg-gray-700 border-gray-600 rounded-lg text-white px-4 py-2">
                    </div>
    
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">السنة</label>
                        <input type="number" name="year" id="year" required min="1900"
                               class="w-full bg-gray-700 border-gray-600 rounded-lg text-white px-4 py-2">
                    </div>
    
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">الحالة</label>
                        <select name="status" id="status" required
                                class="w-full bg-gray-700 border-gray-600 rounded-lg text-white px-4 py-2">
                            <option value="مستمر">مستمر</option>
                            <option value="مكتمل">مكتمل</option>
                        </select>
                    </div>
    
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">النوع</label>
                        <select name="type" id="type" required
                                class="w-full bg-gray-700 border-gray-600 rounded-lg text-white px-4 py-2">
                            <option value="TV">TV</option>
                            <option value="Movie">Movie</option>
                            <option value="OVA">OVA</option>
                        </select>
                    </div>
    
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">عدد الحلقات</label>
                        <input type="number" name="episodes_count" id="episodes_count" required min="1"
                               class="w-full bg-gray-700 border-gray-600 rounded-lg text-white px-4 py-2">
                    </div>
    
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">رابط الصورة</label>
                        <input type="url" name="poster" id="poster" required
                               class="w-full bg-gray-700 border-gray-600 rounded-lg text-white px-4 py-2">
                    </div>
    
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">التصنيفات (مفصولة بفواصل)</label>
                        <input type="text" name="genres" id="genres"
                               class="w-full bg-gray-700 border-gray-600 rounded-lg text-white px-4 py-2">
                    </div>
    
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">القصة</label>
                        <textarea name="story" id="story" rows="4" required
                                  class="w-full bg-gray-700 border-gray-600 rounded-lg text-white px-4 py-2"></textarea>
                    </div>
    
                    <div class="flex justify-end gap-4 pt-4">
                        <button type="button" onclick="closeModal()"
                                class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                            إلغاء
                        </button>
                        <button type="submit" name="save_anime"
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            حفظ التغييرات
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function editAnime(anime) {
            document.getElementById('anime_id').value = anime.id;
            document.getElementById('title').value = anime.title;
            document.getElementById('year').value = anime.year;
            document.getElementById('status').value = anime.status;
            document.getElementById('type').value = anime.type;
            document.getElementById('episodes_count').value = anime.episodes_count;
            document.getElementById('poster').value = anime.poster;
            document.getElementById('genres').value = anime.genres || '';
            document.getElementById('story').value = anime.story;
    
            document.getElementById('editModal').style.display = 'flex';
        }
    
        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
        }
    </script>
</body>
</html>