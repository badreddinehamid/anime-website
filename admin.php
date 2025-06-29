<?php
session_start();

// بيانات الدخول (يفضل نقلها إلى ملف تكوين منفصل)
$admin_username = "jellouli471";
$admin_password = "0698623659"; // يفضل استخدام كلمة مرور قوية وتشفيرها

// التحقق من تسجيل الدخول
if (!isset($_SESSION['admin_logged_in'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($_POST['username'] === $admin_username && $_POST['password'] === $admin_password) {
            $_SESSION['admin_logged_in'] = true;
        } else {
            $error_message = "اسم المستخدم أو كلمة المرور غير صحيحة";
        }
    }
}

// تسجيل الخروج
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// الاتصال بقاعدة البيانات إذا كان المستخدم مسجل الدخول
if (isset($_SESSION['admin_logged_in'])) {
    try {
        $db = new SQLite3('anime_episodes.db');
        
        // إضافة معالجة البحث
        $search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
        
        // إحصائيات عامة
        $total_episodes = $db->querySingle('SELECT COUNT(*) FROM episodes');
        $total_anime = $db->querySingle('SELECT COUNT(DISTINCT anime_title) FROM episodes');
        
        // إعدادات الصفحات
        $items_per_page = 10;
        $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $current_page = max(1, $current_page);
        $offset = ($current_page - 1) * $items_per_page;
        
        // تعديل الاستعلام ليشمل البحث
        if (!empty($search_query)) {
            $stmt = $db->prepare("
                SELECT COUNT(*) 
                FROM episodes 
                WHERE anime_title LIKE :search 
                   OR episode_title LIKE :search
            ");
            $search_param = '%' . $search_query . '%';
            $stmt->bindValue(':search', $search_param, SQLITE3_TEXT);
            $total_episodes = $stmt->execute()->fetchArray()[0];
            
            $stmt = $db->prepare("
                SELECT 
                    episode_title,
                    anime_title,
                    episode_url
                FROM episodes 
                WHERE anime_title LIKE :search 
                   OR episode_title LIKE :search
                ORDER BY id ASC 
                LIMIT :limit OFFSET :offset
            ");
            $stmt->bindValue(':search', $search_param, SQLITE3_TEXT);
            $stmt->bindValue(':limit', $items_per_page, SQLITE3_INTEGER);
            $stmt->bindValue(':offset', $offset, SQLITE3_INTEGER);
            $latest_episodes = $stmt->execute();
        } else {
            // الاستعلام العادي بدون بحث
            $latest_episodes = $db->query("
                SELECT 
                    episode_title,
                    anime_title,
                    episode_url
                FROM episodes 
                ORDER BY id ASC 
                LIMIT $items_per_page OFFSET $offset
            ");
        }
        
        $total_pages = ceil($total_episodes / $items_per_page);
        
    } catch (Exception $e) {
        $db_error = "خطأ في الاتصال بقاعدة البيانات: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم - ANIME-WR</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <?php if (!isset($_SESSION['admin_logged_in'])): ?>
        <!-- نموذج تسجيل الدخول -->
        <div class="min-h-screen flex items-center justify-center">
            <div class="bg-white p-8 rounded-lg shadow-md w-96">
                <h1 class="text-2xl font-bold mb-6 text-center">تسجيل الدخول</h1>
                
                <?php if (isset($error_message)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="username">
                            اسم المستخدم
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                               id="username"
                               name="username"
                               type="text"
                               required>
                    </div>
                    <div class="mb-6">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="password">
                            كلمة المرور
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 mb-3 leading-tight focus:outline-none focus:shadow-outline"
                               id="password"
                               name="password"
                               type="password"
                               required>
                    </div>
                    <button class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline w-full"
                            type="submit">
                        دخول
                    </button>
                </form>
            </div>
        </div>
    <?php else: ?>
        <!-- لوحة التحكم -->
        <div class="min-h-screen bg-gray-100">
            <!-- القائمة العلوية -->
            <nav class="bg-white shadow-lg">
                <div class="max-w-7xl mx-auto px-4">
                    <div class="flex justify-between h-16">
                        <div class="flex items-center">
                            <span class="text-xl font-bold">لوحة التحكم</span>
                            <a href="manage-backgrounds.php" 
                               class="mr-6 text-blue-500 hover:text-blue-700">
                                إدارة الخلفيات
                            </a>
                            <a href="manage-anime.php" 
                               class="mr-6 text-blue-500 hover:text-blue-700">
                                إدارة الأنمي
                            </a>
                            <!-- Add this link in the navigation section -->
                            <a href="manage-seo.php" 
                               class="mr-6 text-blue-500 hover:text-blue-700">
                                إدارة SEO
                            </a>
                            <a href="dashboard_seo.php" class="mr-6 text-blue-500 hover:text-blue-700">
        لوحة تحكم SEO
    </a>
    <a href="analytics.php" class="mr-6 text-blue-500 hover:text-blue-700">
    إحصائيات الزوار
</a>
                        </div>
                        <div class="flex items-center">
                            <a href="?logout=1" 
                               class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">
                                تسجيل الخروج
                            </a>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- المحتوى الرئيسي -->
            <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
                <?php if (isset($db_error)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        <?php echo htmlspecialchars($db_error); ?>
                    </div>
                <?php else: ?>
                    <!-- الإحصائيات -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
                        <div class="bg-white overflow-hidden shadow rounded-lg">
                            <div class="px-4 py-5 sm:p-6">
                                <dt class="text-sm font-medium text-gray-500 truncate">
                                    إجمالي الحلقات
                                </dt>
                                <dd class="mt-1 text-3xl font-semibold text-gray-900">
                                    <?php echo $total_episodes; ?>
                                </dd>
                            </div>
                        </div>
                        <div class="bg-white overflow-hidden shadow rounded-lg">
                            <div class="px-4 py-5 sm:p-6">
                                <dt class="text-sm font-medium text-gray-500 truncate">
                                    عدد الأنميات
                                </dt>
                                <dd class="mt-1 text-3xl font-semibold text-gray-900">
                                    <?php echo $total_anime; ?>
                                </dd>
                            </div>
                        </div>
                    </div>

                    <!-- آخر الحلقات المضافة -->
                    <div class="bg-white shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">الحلقات المضافة</h3>
                            <div class="mb-6">
                                <form action="" method="GET" class="flex gap-4">
                                    <div class="flex-1">
                                        <input type="text" 
                                               name="search" 
                                               value="<?php echo htmlspecialchars($search_query); ?>"
                                               placeholder="ابحث عن أنمي أو حلقة..."
                                               class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    </div>
                                    <button type="submit" 
                                            class="px-6 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        بحث
                                    </button>
                                    <?php if (!empty($search_query)): ?>
                                        <a href="admin.php" 
                                           class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500">
                                            إلغاء البحث
                                        </a>
                                    <?php endif; ?>
                                </form>
                            </div>
                            <div class="overflow-x-auto">
                                <!-- جدول الحلقات -->
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                عنوان الحلقة
                                            </th>
                                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                اسم الأنمي
                                            </th>
                                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                الإجراءات
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php while ($episode = $latest_episodes->fetchArray(SQLITE3_ASSOC)): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <?php echo htmlspecialchars($episode['episode_title']); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <?php echo htmlspecialchars($episode['anime_title']); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <div class="flex gap-3">
                                                        <a href="watch-episode.php?url=<?php echo urlencode($episode['episode_url']); ?>" 
                                                           class="text-indigo-600 hover:text-indigo-900"
                                                           target="_blank">
                                                            عرض
                                                        </a>
                                                        <a href="edit-episode.php?url=<?php echo urlencode($episode['episode_url']); ?>" 
                                                           class="text-green-600 hover:text-green-900">
                                                            تعديل
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>

                                <!-- أزرار التنقل بين الصفحات -->
                                <?php if ($total_pages > 1): ?>
                                <div class="mt-4 flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <!-- زر الصفحة السابقة -->
                                        <?php if ($current_page > 1): ?>
                                            <a href="?page=<?php echo $current_page - 1; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>" 
                                               class="px-3 py-1 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200">
                                                السابق
                                            </a>
                                        <?php endif; ?>

                                        <!-- أرقام الصفحات -->
                                        <div class="flex gap-1">
                                            <?php
                                            $start_page = max(1, $current_page - 2);
                                            $end_page = min($total_pages, $current_page + 2);

                                            if ($start_page > 1) {
                                                echo '<a href="?page=1" class="px-3 py-1 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200">1</a>';
                                                if ($start_page > 2) {
                                                    echo '<span class="px-2">...</span>';
                                                }
                                            }

                                            for ($i = $start_page; $i <= $end_page; $i++) {
                                                $active_class = $i === $current_page ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200';
                                                $search_param = !empty($search_query) ? '&search=' . urlencode($search_query) : '';
                                                echo "<a href=\"?page=$i$search_param\" class=\"px-3 py-1 rounded-md $active_class\">$i</a>";
                                            }

                                            if ($end_page < $total_pages) {
                                                if ($end_page < $total_pages - 1) {
                                                    echo '<span class="px-2">...</span>';
                                                }
                                                echo "<a href=\"?page=$total_pages\" class=\"px-3 py-1 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200\">$total_pages</a>";
                                            }
                                            ?>
                                        </div>

                                        <!-- زر الصفحة التالية -->
                                        <?php if ($current_page < $total_pages): ?>
                                            <a href="?page=<?php echo $current_page + 1; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>" 
                                               class="px-3 py-1 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200">
                                                التالي
                                            </a>
                                        <?php endif; ?>
                                    </div>

                                    <!-- عرض معلومات الصفحة الحالية -->
                                    <div class="text-sm text-gray-500">
                                        الصفحة <?php echo $current_page; ?> من <?php echo $total_pages; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if (isset($db)) $db->close(); ?>
</body>
</html>