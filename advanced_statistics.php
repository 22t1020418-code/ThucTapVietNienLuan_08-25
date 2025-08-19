<?php
session_start();
include "db.php"; // Đảm bảo db.php dùng pg_connect()
date_default_timezone_set('Asia/Ho_Chi_Minh');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$mode = $_GET['mode'] ?? 'month';
$chartType = ($mode === 'year') ? 'pie' : ($_GET['chart'] ?? 'line');

$labels = $thu_data = $chi_data = [];
$labels2 = $thu_data2 = $chi_data2 = [];

$filter_account = isset($_GET['account_id']) ? intval($_GET['account_id']) : 0;
$filter_type = $_GET['type'] ?? 'all';
$filter_description = trim($_GET['description'] ?? '');
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';

if ($mode === 'year' && $chartType === 'line') {
    $currentYear = date('Y');
    $sql = "
        SELECT EXTRACT(MONTH FROM date) AS label,
               SUM(CASE WHEN type = 1 THEN amount ELSE 0 END) AS thu,
               SUM(CASE WHEN type = 2 THEN amount ELSE 0 END) AS chi
        FROM transactions
        WHERE user_id = $1 AND EXTRACT(YEAR FROM date) = $2
    ";
    $params = [$user_id, $currentYear];
    $idx = 3; // bắt đầu từ $3 vì đã dùng $1 và $2

    // Thêm điều kiện lọc nếu có
    if ($filter_account > 0) {
        $sql .= " AND account_id = \${$idx}";
        $params[] = $filter_account;
        $idx++;
    }
    if ($filter_type !== 'all') {
        $sql .= " AND type = \${$idx}";
        $params[] = intval($filter_type);
        $idx++;
    }
    if ($filter_description !== '') {
        if ($filter_description === 'Tạo khoản tiền mới') {
            $sql .= " AND description ILIKE 'Tạo tài khoản mới:%'";
        } else {
            $sql .= " AND description ILIKE \${$idx}";
            $params[] = "%{$filter_description}%";
            $idx++;
        }
    }
    if ($from_date) {
        $sql .= " AND DATE(date) >= \${$idx}";
        $params[] = $from_date;
        $idx++;
    }
    if ($to_date) {
        $sql .= " AND DATE(date) <= \${$idx}";
        $params[] = $to_date;
        $idx++;
    }

    // Kết thúc truy vấn
    $sql .= " GROUP BY label ORDER BY label ASC";

    // Khởi tạo mảng 12 tháng
    for ($i = 1; $i <= 12; $i++) {
        $fullDates[$i] = ['thu' => 0, 'chi' => 0];
    }
 } elseif ($mode === 'week') {
    if ($chartType === 'line') {
        $sql = "
            SELECT DATE(date) AS label,
                   SUM(CASE WHEN type = 1 THEN amount ELSE 0 END) AS thu,
                   SUM(CASE WHEN type = 2 THEN amount ELSE 0 END) AS chi
            FROM transactions
            WHERE user_id = $1 AND date >= CURRENT_DATE - INTERVAL '8 days'
        ";
        $params = [$user_id];
        $idx = 2;
    } else {
        $sql = "
            SELECT EXTRACT(YEAR FROM date) AS y, EXTRACT(WEEK FROM date) AS w,
                   SUM(CASE WHEN type = 1 THEN amount ELSE 0 END) AS thu,
                   SUM(CASE WHEN type = 2 THEN amount ELSE 0 END) AS chi
            FROM transactions
            WHERE user_id = $1
        ";
        $params = [$user_id];
        $idx = 2;
    }

    // Thêm điều kiện lọc
    if ($filter_account > 0) {
        $sql .= " AND account_id = \${$idx}";
        $params[] = $filter_account;
        $idx++;
    }
    if ($filter_type !== 'all') {
        $sql .= " AND type = \${$idx}";
        $params[] = intval($filter_type);
        $idx++;
    }
    if ($filter_description !== '') {
        if ($filter_description === 'Tạo khoản tiền mới') {
            $sql .= " AND description ILIKE 'Tạo tài khoản mới:%'";
        } else {
            $sql .= " AND description ILIKE \${$idx}";
            $params[] = "%{$filter_description}%";
            $idx++;
        }
    }
    if ($from_date) {
        $sql .= " AND DATE(date) >= \${$idx}";
        $params[] = $from_date;
        $idx++;
    }
    if ($to_date) {
        $sql .= " AND DATE(date) <= \${$idx}";
        $params[] = $to_date;
        $idx++;
    }

    // Kết thúc truy vấn
    $sql .= ($chartType === 'line')
        ? " GROUP BY label ORDER BY label ASC"
        : " GROUP BY y, w ORDER BY y DESC, w DESC LIMIT 2";

} elseif ($mode === 'month') {
    if ($chartType === 'line') {
        $sql = "
            SELECT DATE(date) AS label,
                   SUM(CASE WHEN type = 1 THEN amount ELSE 0 END) AS thu,
                   SUM(CASE WHEN type = 2 THEN amount ELSE 0 END) AS chi
            FROM transactions
            WHERE user_id = $1 AND date >= CURRENT_DATE - INTERVAL '29 days'
        ";
        $params = [$user_id];
        $idx = 2;
    } else {
        $sql = "
            SELECT EXTRACT(YEAR FROM date) AS y, EXTRACT(MONTH FROM date) AS m,
                   SUM(CASE WHEN type = 1 THEN amount ELSE 0 END) AS thu,
                   SUM(CASE WHEN type = 2 THEN amount ELSE 0 END) AS chi
            FROM transactions
            WHERE user_id = $1
        ";
        $params = [$user_id];
        $idx = 2;
    }

    // Thêm điều kiện lọc
    if ($filter_account > 0) {
        $sql .= " AND account_id = \${$idx}";
        $params[] = $filter_account;
        $idx++;
    }
    if ($filter_type !== 'all') {
        $sql .= " AND type = \${$idx}";
        $params[] = intval($filter_type);
        $idx++;
    }
    if ($filter_description !== '') {
        if ($filter_description === 'Tạo khoản tiền mới') {
            $sql .= " AND description ILIKE 'Tạo tài khoản mới:%'";
        } else {
            $sql .= " AND description ILIKE \${$idx}";
            $params[] = "%{$filter_description}%";
            $idx++;
        }
    }
    if ($from_date) {
        $sql .= " AND DATE(date) >= \${$idx}";
        $params[] = $from_date;
        $idx++;
    }
    if ($to_date) {
        $sql .= " AND DATE(date) <= \${$idx}";
        $params[] = $to_date;
        $idx++;
    }

    // Kết thúc truy vấn
    $sql .= ($chartType === 'line')
        ? " GROUP BY label ORDER BY label ASC"
        : " GROUP BY y, m ORDER BY y DESC, m DESC LIMIT 2";
}


$params = [$user_id];
$result = pg_query_params($conn, $sql, $params);

$fullDates = [];

if ($chartType === 'line') {
    if ($mode === 'week') {
        for ($i = 7; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $fullDates[$date] = ['thu' => 0, 'chi' => 0];
        }
    } elseif ($mode === 'month') {
        for ($i = 29; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $fullDates[$date] = ['thu' => 0, 'chi' => 0];
        }
    }
}

$idx = count($params) + 1;

if ($filter_account > 0) {
    $sql .= " AND account_id = \${$idx}";
    $params[] = $filter_account;
    $idx++;
}
if ($filter_type !== 'all') {
    $sql .= " AND type = \${$idx}";
    $params[] = intval($filter_type);
    $idx++;
}
if ($filter_description !== '') {
    if ($filter_description === 'Tạo khoản tiền mới') {
        $sql .= " AND description ILIKE 'Tạo tài khoản mới:%'";
    } else {
        $sql .= " AND description ILIKE \${$idx}";
        $params[] = "%{$filter_description}%";
        $idx++;
    }
}
if ($from_date) {
    $sql .= " AND DATE(date) >= \${$idx}";
    $params[] = $from_date;
    $idx++;
}
if ($to_date) {
    $sql .= " AND DATE(date) <= \${$idx}";
    $params[] = $to_date;
    $idx++;
}

$index = 0;
while ($row = pg_fetch_assoc($result)) {
    if ($chartType === 'line' && $mode === 'year') {
        $month = (int)$row['label'];
        if (isset($fullDates[$month])) {
            $fullDates[$month]['thu'] = (float)$row['thu'];
            $fullDates[$month]['chi'] = (float)$row['chi'];
        }
    }
    if ($chartType === 'line' && ($mode === 'week' || $mode === 'month')) {
        $date = $row['label']; // định dạng 'Y-m-d'
        if (isset($fullDates[$date])) {
            $fullDates[$date]['thu'] = (float)$row['thu'];
            $fullDates[$date]['chi'] = (float)$row['chi'];
        }
    } else {
        // giữ nguyên xử lý cũ cho pie và year
        if (($mode === 'week' || $mode === 'month') && $chartType === 'pie') {
            $label = ($mode === 'week') ? "Tuần {$row['w']}/{$row['y']}" : "Tháng {$row['m']}/{$row['y']}";
            if ($index === 0) {
                $labels[] = $label;
                $thu_data[] = $row['thu'];
                $chi_data[] = $row['chi'];
            } else {
                $labels2[] = $label;
                $thu_data2[] = $row['thu'];
                $chi_data2[] = $row['chi'];
            }
        } elseif ($mode === 'year') {
            if ($index === 0) {
                $labels[] = $row['label'];
                $thu_data[] = $row['thu'];
                $chi_data[] = $row['chi'];
            } else {
                $labels2[] = $row['label'];
                $thu_data2[] = $row['thu'];
                $chi_data2[] = $row['chi'];
            }
        }
    }
    $index++;
}
if ($chartType === 'line' && ($mode === 'week' || $mode === 'month')) {
    foreach ($fullDates as $date => $data) {
        $labels[] = date('d/m', strtotime($date));
        $thu_data[] = $data['thu'];
        $chi_data[] = $data['chi'];
    }
}
if ($chartType === 'line' && $mode === 'year') {
    foreach ($fullDates as $month => $data) {
        $labels[] = "Tháng $month";
        $thu_data[] = $data['thu'];
        $chi_data[] = $data['chi'];
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Thống kê nâng cao</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @media screen and (max-width: 768px) {
          body {
            padding: 15px;
          }
        
          .container {
            padding: 15px;
            border-radius: 0;
            box-shadow: none;
          }
        
          .pie-row {
            flex-direction: column;
            gap: 30px;
            align-items: center;
          }
        
          canvas.pie-chart {
            max-width: 90vw;
          }
        
          .filter form {
            display: flex;
            flex-direction: column;
            gap: 10px;
            align-items: stretch;
          }
        
          select, button {
            font-size: 14px;
            width: 100%;
            margin: 5px 0;
          }
        
          h2 {
            font-size: 20px;
          }
        
          a {
            font-size: 14px;
          }
        }
        
        @media screen and (max-width: 500px) {
          h2 {
            font-size: 18px;
          }
        
          .container {
            padding: 10px;
          }
        
          select, button {
            padding: 8px;
          }
        
          p {
            font-size: 14px;
          }
        
          canvas.pie-chart {
            max-width: 100%;
          }
        }

        body {
            font-family: Arial;
            background: #f0f2f5;
            margin: 0;
            padding: 30px;
        }
        .container {
            max-width: 1000px;
            margin: auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
        }
        .filter {
            text-align: center;
            margin-bottom: 20px;
        }
        select, button {
            padding: 6px 10px;
            font-size: 16px;
            margin: 0 10px;
        }
        canvas {
            margin-top: 30px;
        }
        .pie-row {
            display: flex;
            justify-content: center;
            gap: 40px;
        }
        canvas.pie-chart {
            max-width: 400px;
            max-height: 400px;
            width: 100%;
            height: auto;
        }
        a {
            display: block;
            text-align: center;
            margin-top: 30px;
            text-decoration: none;
            color: #007bff;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>📈 Thống kê nâng cao</h2>
    <div class="filter">
        <form method="GET">
            <label>Thống kê:</label>
            <select name="mode">
                <option value="week" <?= $mode === 'week' ? 'selected' : '' ?>>Theo tuần</option>
                <option value="month" <?= $mode === 'month' ? 'selected' : '' ?>>Theo tháng</option>
                <option value="year" <?= $mode === 'year' ? 'selected' : '' ?>>Theo năm</option>
            </select>
        
            <label>Biểu đồ:</label>
            <select name="chart">
                <option value="pie" <?= $chartType === 'pie' ? 'selected' : '' ?>>Biểu đồ tròn</option>
                <option value="line" <?= $chartType === 'line' ? 'selected' : '' ?>>Biểu đồ đường</option>
            </select>
        
            <label>Từ ngày:</label>
            <input type="date" name="from_date" value="<?= $_GET['from_date'] ?? '' ?>">
        
            <label>Đến ngày:</label>
            <input type="date" name="to_date" value="<?= $_GET['to_date'] ?? '' ?>">
        
            <label>Loại giao dịch:</label>
            <select name="type">
                <option value="all" <?= ($_GET['type'] ?? '') === 'all' ? 'selected' : '' ?>>Tất cả</option>
                <option value="1" <?= ($_GET['type'] ?? '') === '1' ? 'selected' : '' ?>>Thu</option>
                <option value="2" <?= ($_GET['type'] ?? '') === '2' ? 'selected' : '' ?>>Chi</option>
            </select>
        
            <label>Mô tả:</label>
            <input type="text" name="description" value="<?= $_GET['description'] ?? '' ?>" placeholder="Nhập mô tả...">
        
            <label>Khoản tiền:</label>
            <select name="account_id">
                <option value="0">Tất cả</option>
                <?php
                $acc_result = pg_query_params($conn, "SELECT id, name FROM accounts WHERE user_id = $1", [$user_id]);
                while ($acc = pg_fetch_assoc($acc_result)) {
                    $selected = ($_GET['account_id'] ?? '') == $acc['id'] ? 'selected' : '';
                    echo "<option value=\"{$acc['id']}\" $selected>{$acc['name']}</option>";
                }
                ?>
            </select>
        
            <button type="submit">📊 Xem thống kê</button>
        </form>
    </div>

    <?php if (isset($_GET['mode']) && $mode === 'year'): ?>
    <div class="pie-row">
        <div>
            <canvas id="pieChart2" class="pie-chart"></canvas>
            <p style="text-align:center">
                Năm <?= $labels2[0] ?? '' ?><br>
                Tổng thu: <strong><?= number_format($thu_data2[0] ?? 0, 0, ',', '.') ?> VND</strong><br>
                Tổng chi: <strong><?= number_format($chi_data2[0] ?? 0, 0, ',', '.') ?> VND</strong>
            </p>
        </div>
        <div>
            <canvas id="pieChart1" class="pie-chart"></canvas>
            <p style="text-align:center">
                Năm <?= $labels[0] ?? '' ?><br>
                Tổng thu: <strong><?= number_format($thu_data[0] ?? 0, 0, ',', '.') ?> VND</strong><br>
                Tổng chi: <strong><?= number_format($chi_data[0] ?? 0, 0, ',', '.') ?> VND</strong>
            </p>
        </div>
    </div>
    <?php endif; ?>
                
    <?php if (isset($_GET['mode']) && ($mode === 'week' || $mode === 'month') && $chartType === 'pie'): ?>
    <div class="pie-row">
        <div>
            <canvas id="pieChart2" class="pie-chart"></canvas>
            <p style="text-align:center">
                Tổng thu: <strong><?= number_format($thu_data2[0] ?? 0, 0, ',', '.') ?> VND</strong><br>
                Tổng chi: <strong><?= number_format($chi_data2[0] ?? 0, 0, ',', '.') ?> VND</strong>
            </p>
        </div>
        <div>
            <canvas id="pieChart1" class="pie-chart"></canvas>
            <p style="text-align:center">
                Tổng thu: <strong><?= number_format($thu_data[0] ?? 0, 0, ',', '.') ?> VND</strong><br>
                Tổng chi: <strong><?= number_format($chi_data[0] ?? 0, 0, ',', '.') ?> VND</strong>
            </p>
        </div>
    </div>
    <?php elseif ($chartType === 'line' && ($mode === 'week' || $mode === 'month')): ?>
        <canvas id="myChart" class="line-chart"></canvas>
        <p style="text-align:center; margin-top: 20px;">
            Tổng thu: <strong><?= number_format(array_sum($thu_data), 0, ',', '.') ?> VND</strong><br>
            Tổng chi: <strong><?= number_format(array_sum($chi_data), 0, ',', '.') ?> VND</strong>
        </p>
    <?php elseif ($chartType === 'line' && $mode === 'year'): ?>
        <canvas id="myChart" class="line-chart" style="max-width: 100%; height: 400px;"></canvas>
        <p style="text-align:center; margin-top: 20px;">
            Tổng thu: <strong><?= number_format(array_sum($thu_data), 0, ',', '.') ?> VND</strong><br>
            Tổng chi: <strong><?= number_format(array_sum($chi_data), 0, ',', '.') ?> VND</strong>
        </p>
    <?php endif; ?>
    <a href="dashboard.php">← Quay lại Dashboard</a>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const mode = <?= json_encode($mode) ?>;
const chartType = <?= json_encode($chartType) ?>;
const labels = <?= json_encode($labels) ?>;
const thu = <?= json_encode($thu_data) ?>;
const chi = <?= json_encode($chi_data) ?>;

// Biểu đồ đường cho tuần, tháng, năm
if (chartType === 'line') {
    const ctx = document.getElementById('myChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Thu',
                    data: thu,
                    borderColor: '#28a745',
                    backgroundColor: '#28a74533',
                    tension: 0.4,
                    fill: true
                },
                {
                    label: 'Chi',
                    data: chi,
                    borderColor: '#dc3545',
                    backgroundColor: '#dc354533',
                    tension: 0.4,
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                title: {
                    display: true,
                    text: mode === 'year' ? 'Biểu đồ 12 tháng' : (mode === 'month' ? 'Biểu đồ tháng này' : 'Biểu đồ tuần này')
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: value => value.toLocaleString('vi-VN') + ' VND'
                    }
                }
            }
        }
    });
}

// Biểu đồ tròn cho năm
if (mode === 'year' && chartType === 'pie') {
    const ctx1 = document.getElementById('pieChart1').getContext('2d');
    new Chart(ctx1, {
        type: 'pie',
        data: {
            labels: ['Tổng thu', 'Tổng chi'],
            datasets: [{
                data: [<?= $thu_data[0] ?? 0 ?>, <?= $chi_data[0] ?? 0 ?>],
                backgroundColor: ['#28a745', '#dc3545']
            }]
        },
        options: {
            plugins: {
                title: {
                    display: true,
                    text: "Năm <?= $labels[0] ?? '' ?>"
                }
            }
        }
    });

    const ctx2 = document.getElementById('pieChart2').getContext('2d');
    new Chart(ctx2, {
        type: 'pie',
        data: {
            labels: ['Tổng thu', 'Tổng chi'],
            datasets: [{
                data: [<?= $thu_data2[0] ?? 0 ?>, <?= $chi_data2[0] ?? 0 ?>],
                backgroundColor: ['#28a745', '#dc3545']
            }]
        },
        options: {
            plugins: {
                title: {
                    display: true,
                    text: "Năm <?= $labels2[0] ?? '' ?>"
                }
            }
        }
    });
}

// Biểu đồ tròn cho tuần/tháng
if ((mode === 'week' || mode === 'month') && chartType === 'pie') {
    const ctx1 = document.getElementById('pieChart1').getContext('2d');
    new Chart(ctx1, {
        type: 'pie',
        data: {
            labels: ['Tổng thu', 'Tổng chi'],
            datasets: [{
                data: [<?= $thu_data[0] ?? 0 ?>, <?= $chi_data[0] ?? 0 ?>],
                backgroundColor: ['#28a745', '#dc3545']
            }]
        },
        options: {
            plugins: {
                title: {
                    display: true,
                    text: <?= json_encode($labels[0] ?? '') ?>
                }
            }
        }
    });

    const ctx2 = document.getElementById('pieChart2').getContext('2d');
    new Chart(ctx2, {
        type: 'pie',
        data: {
            labels: ['Tổng thu', 'Tổng chi'],
            datasets: [{
                data: [<?= $thu_data2[0] ?? 0 ?>, <?= $chi_data2[0] ?? 0 ?>],
                backgroundColor: ['#28a745', '#dc3545']
            }]
        },
        options: {
            plugins: {
                title: {
                    display: true,
                    text: <?= json_encode($labels2[0] ?? '') ?>
                }
            }
        }
    });
}

// Biểu đồ tròn mặc định nếu không khớp điều kiện nào
if (chartType === 'pie' && typeof pieChart1 === 'undefined' && typeof pieChart2 === 'undefined') {
    const ctx = document.getElementById('myChart').getContext('2d');
    new Chart(ctx, {
        type: 'pie',
        data: {
            labels: ['Tổng thu', 'Tổng chi'],
            datasets: [{
                data: [<?= array_sum($thu_data) ?>, <?= array_sum($chi_data) ?>],
                backgroundColor: ['#28a745', '#dc3545']
            }]
        }
    });
}
</script>
</body>
</html>
