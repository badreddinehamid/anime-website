<?php
session_start();

// التحقق من تسجيل الدخول
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin.php');
    exit;
}

try {
    $db = new SQLite3('anime_episodes.db');
    
    // التحقق من وجود معرف الخلفية
    if (!isset($_GET['id'])) {
        throw new Exception("لم يتم تحديد الخلفية");
    }

    // جلب بيانات الخلفية
    $stmt = $db->prepare('SELECT * FROM backgrounds WHERE id = :id');
    $stmt->bindValue(':id', $_GET['id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    $background = $result->fetchArray(SQLITE3_ASSOC);

    if (!$background) {
        throw new Exception("الخلفية غير موجودة");
    }

    // معالجة تحديث البيانات
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $stmt = $db->prepare('
            UPDATE backgrounds 
            SET 
                image_url = :image_url,
                anime_title = :anime_title,
                description = :description,
                year = :year,
                age_rating = :age_rating,
                season = :season,
                match_rate = :match_rate,
                watch_url = :watch_url
            WHERE id = :id
        ');

        $stmt->bindValue(':id', $_GET['id'], SQLITE3_INTEGER);
        $stmt->bindValue(':image_url', $_POST['image_url'], SQLITE3_TEXT);
        $stmt->bindValue(':anime_title', $_POST['anime_title'], SQLITE3_TEXT);
        $stmt->bindValue(':description', $_POST['description'], SQLITE3_TEXT);
        $stmt->bindValue(':year', $_POST['year'], SQLITE3_TEXT);
        $stmt->bindValue(':age_rating', $_POST['age_rating'], SQLITE3_TEXT);
        $stmt->bindValue(':season', $_POST['season'], SQLITE3_TEXT);
        $stmt->bindValue(':match_rate', $_POST['match_rate'], SQLITE3_TEXT);
        $stmt->bindValue(':watch_url', $_POST['watch_url'], SQLITE3_TEXT);

        if ($stmt->execute()) {
            $success_message = "تم تحديث الخلفية بنجاح";
            // تحديث البيانات المعروضة
            $background = array_merge($background, $_POST);
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
    <title>تعديل الخلفية - لوحة التحكم</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="admin.php" class="text-xl font-bold">لوحة التحكم</a>
                    <span class="mx-4">|</span>
                    <a href="manage-backgrounds.php" class="text-gray-600">إدارة الخلفيات</a>
                    <span class="mx-4">|</span>
                    <span class="text-gray-600">تعديل الخلفية</span>
                </div>
            </div>
        </div>
    </nav>

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
            <h2 class="text-xl font-bold mb-6">تعديل الخلفية</h2>
            
            <form method="POST" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            معاينة الصورة
                        </label>
                        <img id="preview" 
                             src="<?php echo htmlspecialchars($background['image_url']); ?>" 
                             class="w-full h-64 object-cover rounded-lg mb-2">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            رابط الصورة (1920x1080)
                        </label>
                        <input type="url" 
                               name="image_url" 
                               value="<?php echo htmlspecialchars($background['image_url']); ?>"
                               required
                               onchange="updatePreview(this.value)"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            اسم الأنمي
                        </label>
                        <input type="text" 
                               name="anime_title" 
                               value="<?php echo htmlspecialchars($background['anime_title']); ?>"
                               required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            الوصف
                        </label>
                        <textarea name="description" 
                                  rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md"><?php echo htmlspecialchars($background['description']); ?></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            السنة
                        </label>
                        <input type="text" 
                               name="year" 
                               value="<?php echo htmlspecialchars($background['year']); ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            التصنيف العمري
                        </label>
                        <input type="text" 
                               name="age_rating" 
                               value="<?php echo htmlspecialchars($background['age_rating']); ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            الموسم
                        </label>
                        <input type="text" 
                               name="season" 
                               value="<?php echo htmlspecialchars($background['season']); ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            نسبة التطابق
                        </label>
                        <input type="text" 
                               name="match_rate" 
                               value="<?php echo htmlspecialchars($background['match_rate']); ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            رابط المشاهدة
                        </label>
                        <input type="url" 
                               name="watch_url" 
                               value="<?php echo htmlspecialchars($background['watch_url']); ?>"
                               required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>
                </div>

                <div class="flex justify-end gap-4">
                    <a href="manage-backgrounds.php" 
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

    <script>
    function updatePreview(url) {
        document.getElementById('preview').src = url;
    }
    </script>

    <?php if (isset($db)) $db->close(); ?>
</body>
</html> 