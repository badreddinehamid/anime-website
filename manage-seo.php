<?php
session_start();

// التحقق من تسجيل الدخول
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin.php');
    exit;
}

try {
    $db = new SQLite3('anime_database.db'); // للحصول على معلومات الأنمي
    $seo_db = new SQLite3('seo.db'); // قاعدة بيانات SEO الجديدة
    
    // معالجة النموذج عند الإرسال
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['update_anime_seo'])) {
            $anime_id = $_POST['anime_id'];
            $meta_title = $_POST['meta_title'];
            $meta_description = $_POST['meta_description'];
            $keywords = $_POST['keywords'];
            
            // تحديث أو إدراج بيانات SEO
            $stmt = $seo_db->prepare('
                INSERT OR REPLACE INTO anime_seo 
                (anime_id, meta_title, meta_description, keywords, updated_at) 
                VALUES 
                (:anime_id, :meta_title, :meta_description, :keywords, CURRENT_TIMESTAMP)
            ');
            
            $stmt->bindValue(':anime_id', $anime_id, SQLITE3_INTEGER);
            $stmt->bindValue(':meta_title', $meta_title, SQLITE3_TEXT);
            $stmt->bindValue(':meta_description', $meta_description, SQLITE3_TEXT);
            $stmt->bindValue(':keywords', $keywords, SQLITE3_TEXT);
            
            $stmt->execute();
            $success_message = "تم تحديث بيانات SEO بنجاح";
        }
    }

    // جلب قائمة الأنميات مع بيانات SEO الخاصة بها
    $anime_list = $db->query('
        SELECT a.id, a.title 
        FROM anime a 
        ORDER BY a.title ASC
    ');

    $anime_data = [];
    while ($row = $anime_list->fetchArray(SQLITE3_ASSOC)) {
        $seo_data = $seo_db->querySingle("
            SELECT meta_title, meta_description, keywords 
            FROM anime_seo 
            WHERE anime_id = {$row['id']}", 
            true
        );
        
        $row['meta_title'] = $seo_data['meta_title'] ?? '';
        $row['meta_description'] = $seo_data['meta_description'] ?? '';
        $row['keywords'] = $seo_data['keywords'] ?? '';
        $anime_data[] = $row;
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
    <title>إدارة SEO - ANIME-WR</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <!-- القائمة العلوية -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="admin.php" class="text-xl font-bold">لوحة التحكم</a>
                    <span class="mx-4 text-gray-500">|</span>
                    <span class="text-blue-600">إدارة SEO</span>
                </div>
            </div>
        </div>
    </nav>

    <!-- المحتوى الرئيسي -->
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <?php if (isset($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- نموذج تحديث SEO -->
        <div class="bg-white shadow rounded-lg p-6">
            <h2 class="text-xl font-semibold mb-6">تحديث بيانات SEO</h2>
            
            <form method="POST" action="">
                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-bold mb-2">
                        اختر الأنمي
                    </label>
                    <!-- في قسم select -->
                    <select name="anime_id" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <?php foreach ($anime_data as $anime): ?>
                            <option value="<?php echo $anime['id']; ?>" 
                                    data-meta-title="<?php echo htmlspecialchars($anime['meta_title']); ?>"
                                    data-meta-description="<?php echo htmlspecialchars($anime['meta_description']); ?>"
                                    data-keywords="<?php echo htmlspecialchars($anime['keywords']); ?>">
                                <?php echo htmlspecialchars($anime['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-bold mb-2">
                        عنوان Meta (Title)
                    </label>
                    <input type="text" name="meta_title" 
                           class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="عنوان الصفحة في نتائج البحث">
                </div>

                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-bold mb-2">
                        وصف Meta (Description)
                    </label>
                    <textarea name="meta_description" 
                              class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                              rows="3"
                              placeholder="وصف مختصر يظهر في نتائج البحث"></textarea>
                </div>

                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-bold mb-2">
                        الكلمات المفتاحية (Keywords)
                    </label>
                    <textarea name="keywords" 
                              class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                              rows="3"
                              placeholder="كلمات مفتاحية مفصولة بفواصل"></textarea>
                    <p class="mt-2 text-sm text-gray-500">
                        مثال: أنمي اكشن, أنمي رومانسي, مشاهدة مباشرة, تحميل
                    </p>
                </div>

                <button type="submit" 
                        name="update_anime_seo"
                        class="bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    حفظ التغييرات
                </button>
            </form>
        </div>
    </div>

    <script>
        // تحديث الحقول عند تغيير الأنمي المحدد
        document.querySelector('select[name="anime_id"]').addEventListener('change', function() {
            const selected = this.options[this.selectedIndex];
            document.querySelector('input[name="meta_title"]').value = selected.dataset.metaTitle || '';
            document.querySelector('textarea[name="meta_description"]').value = selected.dataset.metaDescription || '';
            document.querySelector('textarea[name="keywords"]').value = selected.dataset.keywords || '';
        });

        // تحديث الحقول عند تحميل الصفحة
        document.querySelector('select[name="anime_id"]').dispatchEvent(new Event('change'));
    </script>
</body>
</html>