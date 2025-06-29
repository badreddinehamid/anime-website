<?php
session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin.php');
    exit;
}

try {
    $seo_db = new SQLite3('seo.db');
    
    // إحصائيات SEO
    $total_anime_seo = $seo_db->querySingle('SELECT COUNT(*) FROM anime_seo');
    
    // جلب تحديثات SEO
    $search = isset($_GET['search']) ? $seo_db->escapeString(trim($_GET['search'])) : '';
    
    $seo_query = "
        SELECT 
            id,
            anime_id,
            meta_title,
            meta_description,
            keywords,
            updated_at
        FROM anime_seo
        WHERE 1=1
    ";
    
    if (!empty($search)) {
        $seo_query .= " AND (meta_title LIKE '%{$search}%' OR meta_description LIKE '%{$search}%')";
    }
    
    $seo_query .= " ORDER BY updated_at DESC";
    $seo_results = $seo_db->query($seo_query);

    // تجميع النتائج
    $updates = [];
    if ($seo_results) {
        while ($row = $seo_results->fetchArray(SQLITE3_ASSOC)) {
            $updates[] = $row;
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
    <title>لوحة تحكم SEO - ANIME-WR</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- إضافة هذا الكود في قسم head -->
    <script>
    function openEditModal(id, title, description, keywords) {
        document.getElementById('edit_anime_id').value = id;
        document.getElementById('edit_meta_title').value = title;
        document.getElementById('edit_meta_description').value = description;
        document.getElementById('edit_keywords').value = keywords;
        document.getElementById('editModal').classList.remove('hidden');
    }

    function closeEditModal() {
        document.getElementById('editModal').classList.add('hidden');
    }

    function confirmDelete(id) {
        if (confirm('هل أنت متأكد من حذف هذا العنصر؟')) {
            window.location.href = 'delete-seo.php?id=' + id;
        }
    }
    </script>
</head>

<!-- إضافة النافذة المنبثقة قبل نهاية body -->
<div id="editModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">تعديل SEO</h3>
            <form action="update-seo.php" method="POST">
                <input type="hidden" id="edit_anime_id" name="anime_id">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">عنوان SEO</label>
                    <input type="text" id="edit_meta_title" name="meta_title" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">الوصف</label>
                    <textarea id="edit_meta_description" name="meta_description" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"></textarea>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">الكلمات المفتاحية</label>
                    <input type="text" id="edit_keywords" name="keywords" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div class="flex justify-end gap-4">
                    <button type="button" onclick="closeEditModal()" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">إلغاء</button>
                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">حفظ</button>
                </div>
            </form>
        </div>
    </div>
</div>

<body class="bg-gray-100">
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="admin.php" class="text-xl font-bold">لوحة التحكم</a>
                    <span class="mx-4 text-gray-500">|</span>
                    <span class="text-blue-600">لوحة تحكم SEO</span>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="manage-seo.php" class="text-blue-500 hover:text-blue-700">
                        <i class="fas fa-cog ml-2"></i>
                        إدارة SEO
                    </a>
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

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold mb-2">إجمالي صفحات SEO</h3>
                <p class="text-3xl font-bold text-blue-600"><?php echo $total_anime_seo; ?></p>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">قائمة SEO</h3>
            
            <div class="mb-4">
                <form method="GET" class="flex gap-4">
                    <input type="text" 
                           name="search" 
                           value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>"
                           placeholder="بحث في العناوين..."
                           class="flex-1 px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <button type="submit" 
                            class="bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600">
                        بحث
                    </button>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">معرف الأنمي</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">عنوان SEO</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">الوصف</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">الكلمات المفتاحية</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">آخر تحديث</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($updates as $update): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php echo htmlspecialchars($update['anime_id']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php echo htmlspecialchars($update['meta_title'] ?? 'غير محدد'); ?>
                            </td>
                            <td class="px-6 py-4">
                                <?php echo htmlspecialchars($update['meta_description'] ?? 'غير محدد'); ?>
                            </td>
                            <td class="px-6 py-4">
                                <?php echo htmlspecialchars($update['keywords'] ?? 'غير محدد'); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php echo $update['updated_at'] ?? 'غير محدد'; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <button onclick="openEditModal('<?php echo $update['anime_id']; ?>', '<?php echo htmlspecialchars($update['meta_title'] ?? ''); ?>', '<?php echo htmlspecialchars($update['meta_description'] ?? ''); ?>', '<?php echo htmlspecialchars($update['keywords'] ?? ''); ?>')" 
                                        class="text-blue-600 hover:text-blue-900 ml-2">
                                    تعديل
                                </button>
                                <button onclick="confirmDelete(<?php echo $update['anime_id']; ?>)" 
                                        class="text-red-600 hover:text-red-900">
                                    حذف
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>