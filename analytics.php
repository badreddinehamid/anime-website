<?php
session_start();

// Check login
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin.php');
    exit;
}

try {
    $db = new SQLite3('analytics.db');
    
    // Get date range from query parameters or default to last 7 days
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-7 days'));
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
    
    // Total visits (all page views)
    $stmt = $db->prepare("
        SELECT COUNT(*) as count FROM visitors 
        WHERE date(visit_date) BETWEEN :start_date AND :end_date
    ");
    $stmt->bindValue(':start_date', $start_date, SQLITE3_TEXT);
    $stmt->bindValue(':end_date', $end_date, SQLITE3_TEXT);
    $result = $stmt->execute();
    $total_visits = $result->fetchArray(SQLITE3_ASSOC)['count'];
    
    // Unique visitors (count distinct unique_visitor_id)
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT unique_visitor_id) as count FROM visitors 
        WHERE date(visit_date) BETWEEN :start_date AND :end_date
    ");
    $stmt->bindValue(':start_date', $start_date, SQLITE3_TEXT);
    $stmt->bindValue(':end_date', $end_date, SQLITE3_TEXT);
    $result = $stmt->execute();
    $unique_visitors = $result->fetchArray(SQLITE3_ASSOC)['count'];
    
    // Most visited pages
    $stmt = $db->prepare("
        SELECT 
            page_url, 
            COUNT(*) as visits,
            COUNT(DISTINCT unique_visitor_id) as unique_visits
        FROM visitors 
        WHERE date(visit_date) BETWEEN :start_date AND :end_date
        GROUP BY page_url 
        ORDER BY visits DESC 
        LIMIT 10
    ");
    $stmt->bindValue(':start_date', $start_date, SQLITE3_TEXT);
    $stmt->bindValue(':end_date', $end_date, SQLITE3_TEXT);
    $pages = $stmt->execute();
    
    // Daily visits
    $stmt = $db->prepare("
        SELECT 
            date(visit_date) as date,
            COUNT(*) as total_visits,
            COUNT(DISTINCT unique_visitor_id) as unique_visits
        FROM visitors 
        WHERE date(visit_date) BETWEEN :start_date AND :end_date
        GROUP BY date(visit_date)
        ORDER BY date(visit_date) DESC
    ");
    $stmt->bindValue(':start_date', $start_date, SQLITE3_TEXT);
    $stmt->bindValue(':end_date', $end_date, SQLITE3_TEXT);
    $daily_visits = $stmt->execute();
    
    // Top referrers
    $stmt = $db->prepare("
        SELECT referer, COUNT(*) as count
        FROM visitors 
        WHERE date(visit_date) BETWEEN :start_date AND :end_date
        AND referer != ''
        GROUP BY referer 
        ORDER BY count DESC 
        LIMIT 10
    ");
    $stmt->bindValue(':start_date', $start_date, SQLITE3_TEXT);
    $stmt->bindValue(':end_date', $end_date, SQLITE3_TEXT);
    $referrers = $stmt->execute();

} catch (Exception $e) {
    $error_message = "Database Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إحصائيات الزوار - ANIME-WR</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
</head>
<body class="bg-gray-100">
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="admin.php" class="text-xl font-bold">لوحة التحكم</a>
                    <span class="mx-4 text-gray-500">|</span>
                    <span class="text-blue-600">إحصائيات الزوار</span>
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

        <!-- Date Range Filter -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <form method="GET" class="flex gap-4 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">من تاريخ</label>
                    <input type="date" name="start_date" value="<?php echo $start_date; ?>" 
                           class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">إلى تاريخ</label>
                    <input type="date" name="end_date" value="<?php echo $end_date; ?>" 
                           class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                </div>
                <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600">
                    تطبيق
                </button>
            </form>
        </div>

        <!-- Statistics Overview -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">إجمالي الزيارات</h3>
                <p class="text-3xl font-bold text-blue-600"><?php echo number_format($total_visits); ?></p>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">الزوار الفريدين</h3>
                <p class="text-3xl font-bold text-green-600"><?php echo number_format($unique_visitors); ?></p>
            </div>
        </div>

        <!-- Visits Chart -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">الزيارات اليومية</h3>
            <div style="height: 400px;"> <!-- Add fixed height container -->
                <canvas id="visitsChart"></canvas>
            </div>
        </div>

        <!-- Most Visited Pages -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">الصفحات الأكثر زيارة</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                الصفحة
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                عدد الزيارات
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php while ($page = $pages->fetchArray(SQLITE3_ASSOC)): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($page['page_url']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo number_format($page['visits']); ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Top Referrers -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">مصادر الزيارات</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                المصدر
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                عدد الزيارات
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php while ($referrer = $referrers->fetchArray(SQLITE3_ASSOC)): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($referrer['referer']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo number_format($referrer['count']); ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Prepare chart data
        const chartData = {
            labels: <?php 
                $dates = [];
                $totalVisits = [];
                $uniqueVisits = [];
                $daily_visits->reset();
                while ($row = $daily_visits->fetchArray(SQLITE3_ASSOC)) {
                    $dates[] = $row['date'];
                    $totalVisits[] = $row['total_visits'];
                    $uniqueVisits[] = $row['unique_visits'];
                }
                echo json_encode(array_reverse($dates));
            ?>,
            datasets: [{
                type: 'bar',
                label: 'إجمالي الزيارات',
                data: <?php echo json_encode(array_reverse($totalVisits)); ?>,
                backgroundColor: 'rgba(59, 130, 246, 0.8)',
                borderRadius: 0,
                maxBarThickness: 2,
                datalabels: {
                    display: true,
                    align: 'top',
                    anchor: 'end',
                    offset: -2,
                    formatter: function() {
                        return '✕';
                    },
                    color: 'rgba(59, 130, 246, 1)',
                    font: {
                        size: 14,
                        weight: 'bold'
                    }
                }
            }, {
                type: 'bar',
                label: 'الزيارات الفريدة',
                data: <?php echo json_encode(array_reverse($uniqueVisits)); ?>,
                backgroundColor: 'rgba(34, 197, 94, 0.8)',
                borderRadius: 0,
                maxBarThickness: 2,
                datalabels: {
                    display: true,
                    align: 'top',
                    anchor: 'end',
                    offset: -2,
                    formatter: function() {
                        return '✕';
                    },
                    color: 'rgba(34, 197, 94, 1)',
                    font: {
                        size: 14,
                        weight: 'bold'
                    }
                }
            }]
        };

        // Create chart
        const ctx = document.getElementById('visitsChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: chartData,
            plugins: [ChartDataLabels],
            options: {
                responsive: true,
                maintainAspectRatio: true,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            display: true,
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                        align: 'end',
                        labels: {
                            usePointStyle: true,
                            padding: 20
                        }
                    },
                    datalabels: {
                        clamp: true
                    }
                }
            }
        });
    </script>
</body>
</html>