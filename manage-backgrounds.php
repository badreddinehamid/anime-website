<?php
session_start();

// التحقق من تسجيل الدخول
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin.php');
    exit;
}

try {
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

    // التحقق من وجود العمود watch_url وإضافته إذا لم يكن موجوداً
    $columns = $db->query("PRAGMA table_info(backgrounds)");
    $has_watch_url = false;
    
    while ($column = $columns->fetchArray(SQLITE3_ASSOC)) {
        if ($column['name'] === 'watch_url') {
            $has_watch_url = true;
            break;
        }
    }

    if (!$has_watch_url) {
        $db->exec('ALTER TABLE backgrounds ADD COLUMN watch_url TEXT');
    }

    // معالجة إضافة خلفية جديدة
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $stmt = $db->prepare('
                INSERT INTO backgrounds (
                    image_url, anime_title, description, 
                    year, age_rating, season, match_rate, watch_url
                ) VALUES (
                    :image_url, :anime_title, :description,
                    :year, :age_rating, :season, :match_rate, :watch_url
                )
            ');
            
            $stmt->bindValue(':image_url', $_POST['image_url'], SQLITE3_TEXT);
            $stmt->bindValue(':anime_title', $_POST['anime_title'], SQLITE3_TEXT);
            $stmt->bindValue(':description', $_POST['description'], SQLITE3_TEXT);
            $stmt->bindValue(':year', $_POST['year'], SQLITE3_TEXT);
            $stmt->bindValue(':age_rating', $_POST['age_rating'], SQLITE3_TEXT);
            $stmt->bindValue(':season', $_POST['season'], SQLITE3_TEXT);
            $stmt->bindValue(':match_rate', $_POST['match_rate'], SQLITE3_TEXT);
            $stmt->bindValue(':watch_url', $_POST['watch_url'], SQLITE3_TEXT);
            
            if ($stmt->execute()) {
                $success_message = "تمت إضافة الخلفية بنجاح";
            }
        } elseif ($_POST['action'] === 'delete' && isset($_POST['id'])) {
            $stmt = $db->prepare('DELETE FROM backgrounds WHERE id = :id');
            $stmt->bindValue(':id', $_POST['id'], SQLITE3_INTEGER);
            $stmt->execute();
        }
    }

    // جلب جميع الخلفيات
    $backgrounds = $db->query('SELECT * FROM backgrounds ORDER BY id DESC');

} catch (Exception $e) {
    $error_message = "خطأ في قاعدة البيانات: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الخلفيات - لوحة التحكم</title>
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
                    <span class="text-gray-600">إدارة الخلفيات</span>
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

        <!-- نموذج إضافة خلفية جديدة -->
        <div class="bg-white shadow rounded-lg p-6 mb-6">
            <h2 class="text-xl font-bold mb-4">إضافة خلفية جديدة</h2>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="add">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            رابط الصورة (1920x1080)
                        </label>
                        <input type="url" 
                               name="image_url" 
                               required
                               placeholder="https://"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            اسم الأنمي
                        </label>
                        <input type="text" 
                               name="anime_title" 
                               required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            الوصف
                        </label>
                        <textarea name="description" 
                                  rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md"></textarea>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            السنة
                        </label>
                        <input type="text" 
                               name="year" 
                               placeholder="2024"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            التصنيف العمري
                        </label>
                        <input type="text" 
                               name="age_rating" 
                               placeholder="+13"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            الموسم
                        </label>
                        <input type="text" 
                               name="season" 
                               placeholder="موسم 1"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            نسبة التطابق
                        </label>
                        <input type="text" 
                               name="match_rate" 
                               placeholder="98%"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            رابط المشاهدة
                        </label>
                        <input type="url" 
                               name="watch_url" 
                               required
                               placeholder="https://"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" 
                            class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600">
                        إضافة الخلفية
                    </button>
                </div>
            </form>
        </div>

        <!-- عرض الخلفيات الحالية -->
        <div class="bg-white shadow rounded-lg p-6">
            <h2 class="text-xl font-bold mb-4">الخلفيات الحالية</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php while ($bg = $backgrounds->fetchArray(SQLITE3_ASSOC)): ?>
                <div class="bg-gray-50 rounded-lg p-4">
                    <img src="<?php echo htmlspecialchars($bg['image_url']); ?>" 
                         class="w-full h-48 object-cover rounded-lg mb-4" 
                         alt="<?php echo htmlspecialchars($bg['anime_title']); ?>">
                    
                    <h3 class="font-bold mb-2"><?php echo htmlspecialchars($bg['anime_title']); ?></h3>
                    <p class="text-sm text-gray-600 mb-4"><?php echo htmlspecialchars($bg['description']); ?></p>
                    
                    <div class="flex flex-wrap gap-2 mb-4">
                        <span class="px-2 py-1 bg-gray-200 rounded-md text-sm">
                            <?php echo htmlspecialchars($bg['year']); ?>
                        </span>
                        <span class="px-2 py-1 bg-gray-200 rounded-md text-sm">
                            <?php echo htmlspecialchars($bg['age_rating']); ?>
                        </span>
                        <span class="px-2 py-1 bg-gray-200 rounded-md text-sm">
                            <?php echo htmlspecialchars($bg['season']); ?>
                        </span>
                        <span class="px-2 py-1 bg-gray-200 rounded-md text-sm">
                            تطابق: <?php echo htmlspecialchars($bg['match_rate']); ?>
                        </span>
                        <a href="<?php echo htmlspecialchars($bg['watch_url']); ?>" 
                           target="_blank"
                           class="px-2 py-1 bg-blue-500 text-white rounded-md text-sm hover:bg-blue-600">
                            رابط المشاهدة
                        </a>
                    </div>
                    
                    <div class="flex justify-end gap-2">
                        <a href="edit-background.php?id=<?php echo $bg['id']; ?>" 
                           class="px-3 py-1 bg-green-500 text-white rounded-md hover:bg-green-600 text-sm">
                            <i class="fas fa-edit ml-1"></i>تعديل
                        </a>
                        <form method="POST" class="inline">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $bg['id']; ?>">
                            <button type="submit" 
                                    class="px-3 py-1 bg-red-500 text-white rounded-md hover:bg-red-600 text-sm"
                                    onclick="return confirm('هل أنت متأكد من حذف هذه الخلفية؟')">
                                <i class="fas fa-trash ml-1"></i>حذف
                            </button>
                        </form>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>

    <?php if (isset($db)) $db->close(); ?>
</body>
</html> 