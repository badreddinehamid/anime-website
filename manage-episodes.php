<?php
session_start();

// التحقق من تسجيل الدخول
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin.php');
    exit;
}

// التحقق من وجود معرف الأنمي
if (!isset($_GET['anime_id'])) {
    header('Location: manage-anime.php');
    exit;
}

try {
    $db = new SQLite3('anime_database.db');
    $anime_id = (int)$_GET['anime_id'];

    // جلب معلومات الأنمي
    $stmt = $db->prepare("SELECT * FROM anime WHERE id = :id");
    $stmt->bindValue(':id', $anime_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $anime = $result->fetchArray(SQLITE3_ASSOC);

    if (!$anime) {
        header('Location: manage-anime.php');
        exit;
    }

    // معالجة حذف الحلقة
    if (isset($_POST['delete_episode'])) {
        $episode_id = (int)$_POST['episode_id'];
        $db->exec("BEGIN TRANSACTION");
        try {
            // حذف السيرفرات المرتبطة
            $stmt = $db->prepare("DELETE FROM servers WHERE episode_id = :episode_id");
            $stmt->bindValue(':episode_id', $episode_id, SQLITE3_INTEGER);
            $stmt->execute();

            // حذف الحلقة
            $stmt = $db->prepare("DELETE FROM episodes WHERE id = :id AND anime_id = :anime_id");
            $stmt->bindValue(':id', $episode_id, SQLITE3_INTEGER);
            $stmt->bindValue(':anime_id', $anime_id, SQLITE3_INTEGER);
            $stmt->execute();

            $db->exec("COMMIT");
            $success_message = "تم حذف الحلقة بنجاح";
        } catch (Exception $e) {
            $db->exec("ROLLBACK");
            $error_message = "فشل حذف الحلقة: " . $e->getMessage();
        }
    }

    // معالجة إضافة/تحديث الحلقة
    if (isset($_POST['save_episode'])) {
        $episode_id = isset($_POST['episode_id']) ? (int)$_POST['episode_id'] : null;
        $number = (int)$_POST['number'];
        $servers = $_POST['servers'] ?? [];

        $db->exec("BEGIN TRANSACTION");
        try {
            if ($episode_id) {
                // تحديث الحلقة
                $stmt = $db->prepare("UPDATE episodes SET title = :title WHERE id = :id AND anime_id = :anime_id");
                $stmt->bindValue(':title', "الحلقة " . $number, SQLITE3_TEXT);
                $stmt->bindValue(':id', $episode_id, SQLITE3_INTEGER);
                $stmt->bindValue(':anime_id', $anime_id, SQLITE3_INTEGER);
                $stmt->execute();
            } else {
                // إضافة حلقة جديدة
                $stmt = $db->prepare("INSERT INTO episodes (anime_id, title) VALUES (:anime_id, :title)");
                $stmt->bindValue(':anime_id', $anime_id, SQLITE3_INTEGER);
                $stmt->bindValue(':title', "الحلقة " . $number, SQLITE3_TEXT);
                $stmt->execute();
                $episode_id = $db->lastInsertRowID();
            }

            // إضافة السيرفرات
            foreach ($servers as $server) {
                if (!empty($server['name']) && !empty($server['url'])) {
                    $stmt = $db->prepare("INSERT INTO servers (episode_id, name, url) VALUES (:episode_id, :name, :url)");
                    $stmt->bindValue(':episode_id', $episode_id, SQLITE3_INTEGER);
                    $stmt->bindValue(':name', $server['name'], SQLITE3_TEXT);
                    $stmt->bindValue(':url', $server['url'], SQLITE3_TEXT);
                    $stmt->execute();
                }
            }

            $db->exec("COMMIT");
            $success_message = "تم حفظ الحلقة بنجاح";
        } catch (Exception $e) {
            $db->exec("ROLLBACK");
            $error_message = "فشل حفظ الحلقة: " . $e->getMessage();
        }
    }

    // جلب قائمة الحلقات
    $db->exec("PRAGMA group_concat_max_len = 50000;"); // زيادة الحد الأقصى
    $episodes = $db->prepare("
        SELECT 
            e.*,
            GROUP_CONCAT(s.name || '::' || s.url) as servers
        FROM episodes e
        LEFT JOIN servers s ON s.episode_id = e.id
        WHERE e.anime_id = :anime_id
        GROUP BY e.id
        ORDER BY e.id ASC
    ");
    $episodes->bindValue(':anime_id', $anime_id, SQLITE3_INTEGER);
    $episodes_list = $episodes->execute();

} catch (Exception $e) {
    $error_message = "خطأ في قاعدة البيانات: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة حلقات <?php echo htmlspecialchars($anime['title']); ?> - ANIME-WR</title>
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
            <div class="bg-green-900/50 border border-green-500 text-green-300 px-4 py-3 rounded mb-4">
                <i class="fas fa-check-circle ml-2"></i>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="bg-red-900/50 border border-red-500 text-red-300 px-4 py-3 rounded mb-4">
                <i class="fas fa-exclamation-circle ml-2"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <div class="mb-6">
            <a href="manage-anime.php" class="text-gray-400 hover:text-white transition-colors">
                <i class="fas fa-arrow-right ml-2"></i>
                العودة لقائمة الأنمي
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- نموذج إضافة/تعديل الحلقة -->
            <div class="lg:col-span-1">
                <div class="bg-gray-800 rounded-lg shadow-lg p-6">
                    <h2 class="text-xl font-bold mb-6 flex items-center gap-2">
                        <i class="fas fa-plus-circle text-blue-500"></i>
                        <span id="formTitle">إضافة حلقة جديدة</span>
                    </h2>

                    <form action="" method="POST" class="space-y-4">
                        <input type="hidden" name="episode_id" id="episode_id">
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">رقم الحلقة</label>
                            <input type="number" name="number" required min="1"
                                   class="w-full bg-gray-700 border-gray-600 rounded-lg text-white px-4 py-2">
                        </div>

                        <div id="servers-container" class="space-y-4">
                            <!-- سيتم إضافة السيرفرات هنا -->
                        </div>

                        <button type="button" onclick="addServer()" 
                                class="w-full py-2 border-2 border-dashed border-gray-600 rounded-lg text-gray-400 hover:border-gray-500 hover:text-gray-300">
                            <i class="fas fa-plus-circle ml-2"></i>
                            إضافة سيرفر
                        </button>

                        <div class="flex justify-end gap-4 pt-4">
                            <button type="reset" onclick="resetForm()" 
                                    class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                                <i class="fas fa-undo ml-1"></i>
                                إعادة تعيين
                            </button>
                            <button type="submit" name="save_episode" 
                                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                <i class="fas fa-save ml-1"></i>
                                حفظ
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- قائمة الحلقات -->
            <div class="lg:col-span-2">
                <div class="bg-gray-800 rounded-lg shadow-lg p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-bold flex items-center gap-2">
                            <i class="fas fa-play-circle text-blue-500"></i>
                            حلقات <?php echo htmlspecialchars($anime['title']); ?>
                        </h2>
                    </div>

                    <div class="space-y-4">
                        <?php while ($episode = $episodes_list->fetchArray(SQLITE3_ASSOC)): ?>
                            <div class="bg-gray-700/50 rounded-lg p-4">
                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="text-lg font-medium"><?php echo htmlspecialchars($episode['title']); ?></h3>
                                    <div class="flex gap-2">
                                        <button onclick='editEpisode(<?php 
                                            // Extract number from title (e.g., "الحلقة 1" -> "1")
                                            $episode['number'] = (int)preg_replace('/[^0-9]/', '', $episode['title']);
                                            echo json_encode($episode); 
                                        ?>)'
                                                class="text-blue-400 hover:text-blue-300 transition-colors">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form action="" method="POST" class="inline"
                                              onsubmit="return confirm('هل أنت متأكد من حذف هذه الحلقة؟');">
                                            <input type="hidden" name="episode_id" value="<?php echo $episode['id']; ?>">
                                            <button type="submit" name="delete_episode"
                                                    class="text-red-400 hover:text-red-300 transition-colors">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>

                                <?php if (!empty($episode['servers'])): ?>
                                    <div class="space-y-2">
                                        <?php
                                        $servers = explode(',', $episode['servers']);
                                        $groupedServers = [];
                                        
                                        // Group servers by type
                                        foreach ($servers as $server) {
                                            if (strpos($server, '::') !== false) {
                                                list($name, $url) = explode('::', $server);
                                                $type = explode(' | ', $name)[0]; // Get server type (4shared, dood wf, mega, etc)
                                                if (!isset($groupedServers[$type])) {
                                                    $groupedServers[$type] = [];
                                                }
                                                $groupedServers[$type][] = ['name' => $name, 'url' => $url];
                                            }
                                        }

                                        // Display servers grouped by type
                                        foreach ($groupedServers as $type => $typeServers):
                                        ?>
                                            <div class="mb-3">
                                                <h4 class="text-sm font-medium text-gray-400 mb-2"><?php echo htmlspecialchars($type); ?></h4>
                                                <div class="grid grid-cols-2 gap-2">
                                                    <?php foreach ($typeServers as $server): ?>
                                                        <div class="flex items-center justify-between bg-gray-700 rounded p-2">
                                                            <span class="text-sm truncate"><?php echo htmlspecialchars($server['name']); ?></span>
                                                            <a href="<?php echo htmlspecialchars($server['url']); ?>" target="_blank"
                                                               class="text-blue-400 hover:text-blue-300 text-sm shrink-0 mr-2">
                                                                <i class="fas fa-external-link-alt ml-1"></i>
                                                                فتح الرابط
                                                            </a>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-gray-400 text-sm">لا توجد سيرفرات لهذه الحلقة</p>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function addServer() {
            const container = document.getElementById('servers-container');
            const serverCount = container.children.length + 1;
            
            const serverHtml = `
                <div class="server-item grid grid-cols-5 gap-4">
                    <div class="col-span-2">
                        <input type="text" name="servers[${serverCount}][name]"
                               placeholder="اسم السيرفر"
                               class="w-full bg-gray-700 border-gray-600 rounded text-white px-3 py-2">
                    </div>
                    <div class="col-span-2">
                        <input type="url" name="servers[${serverCount}][url]"
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

        function editEpisode(episode) {
            document.getElementById('episode_id').value = episode.id;
            document.querySelector('input[name="number"]').value = episode.number;
            document.getElementById('formTitle').textContent = 'تعديل الحلقة ' + episode.number;
            
            // Clear existing servers
            const serversContainer = document.getElementById('servers-container');
            serversContainer.innerHTML = '';
            
            // Add existing servers
            if (episode.servers) {
                const servers = episode.servers.split(',');
                servers.forEach((server, index) => {
                    const [name, url] = server.split('::');
                    const serverCount = index + 1;
                    
                    const serverHtml = `
                        <div class="server-item grid grid-cols-5 gap-4">
                            <div class="col-span-2">
                                <input type="text" name="servers[${serverCount}][name]"
                                       value="${name}"
                                       placeholder="اسم السيرفر"
                                       class="w-full bg-gray-700 border-gray-600 rounded text-white px-3 py-2">
                            </div>
                            <div class="col-span-2">
                                <input type="url" name="servers[${serverCount}][url]"
                                       value="${url}"
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
                    
                    serversContainer.insertAdjacentHTML('beforeend', serverHtml);
                });
            }

            // Scroll to form
            document.querySelector('form').scrollIntoView({ behavior: 'smooth' });
        }

        function resetForm() {
            document.getElementById('episode_id').value = '';
            document.getElementById('formTitle').textContent = 'إضافة حلقة جديدة';
            document.getElementById('servers-container').innerHTML = '';
            document.querySelector('form').reset();
        }

        // Add initial server on page load
        addServer();
    </script>
</body>
</html>