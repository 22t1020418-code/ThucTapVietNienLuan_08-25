<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id = $_SESSION['user_id'];

// ✅ Di chuyển xử lý khôi phục lên trước
$transaction_id = $_POST['transaction_id'] ?? null;
if ($transaction_id) {
  $check_sql = "SELECT * FROM transactions WHERE id = $1 AND user_id = $2 AND type = 3";
  $check_res = pg_query_params($conn, $check_sql, [$transaction_id, $user_id]);
  if (pg_num_rows($check_res) === 1) {
    $restore_sql = "UPDATE transactions SET type = 1 WHERE id = $1";
    pg_query_params($conn, $restore_sql, [$transaction_id]);
    $_SESSION['restored'] = "Giao dịch đã được khôi phục!";
  }
  // ✅ Gọi header trước khi in ra bất kỳ nội dung nào
  header("Location: trash.php");
  exit();
}

// ✅ Sau khi xử lý xong, mới in ra thông báo
if (!empty($_SESSION['restored'])) {
  echo "<div style='background-color: #fff3cd; color: #856404; padding: 12px; margin: 16px 0; border: 1px solid #ffeeba; border-radius: 6px; font-weight: bold;'>" . $_SESSION['restored'] . "</div>";
  unset($_SESSION['restored']);
}

$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';
$description = $_GET['description'] ?? '';
$wallet_type = $_GET['wallet_type'] ?? '';
$sql = "SELECT * FROM transactions WHERE user_id = $1 AND type = 3";
$params = [$user_id];
$idx = 2;

if ($from_date) {
  $sql .= " AND DATE(date) >= \$$idx";
  $params[] = $from_date;
  $idx++;
}
if ($to_date) {
  $sql .= " AND DATE(date) <= \$$idx";
  $params[] = $to_date;
  $idx++;
}
if ($description) {
  $sql .= " AND description ILIKE \$$idx";
  $params[] = "%$description%";
  $idx++;
}
if ($wallet_type) {
  $sql .= " AND wallet_type = \$$idx";
  $params[] = $wallet_type;
  $idx++;
}

$sql .= " ORDER BY date DESC";
$res = pg_query_params($conn, $sql, $params);
if (!$res) {
  echo "Lỗi truy vấn cơ sở dữ liệu.";
  exit();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>🗑️ Thùng rác</title>
  <style>
    :root {
    --sidebar-width: 260px;
    --color-primary: #1e88e5;
    --color-danger: #e53935;
    --color-bg: #f9fafb;
    --color-card: #ffffff;
    --color-text: #2e3d49;
    --color-muted: #64748b;
    --border-radius: 8px;
    --spacing: 16px;
    --transition-speed: 0.3s;
  }
  
  body {
    font-family: 'Segoe UI', Tahoma, sans-serif;
    background: var(--color-bg);
    color: var(--color-text);
    margin: 0;
    padding: 0;
  }
  
  .container {
    display: grid;
    grid-template-columns: var(--sidebar-width) 1fr;
    min-height: 100vh;
  }
  
  .sidebar {
    background: var(--color-card);
    padding: var(--spacing);
    border-right: 1px solid #e2e8f0;
  }
  
  .sidebar h3 {
    font-size: 1rem;
    color: var(--color-muted);
    margin-bottom: 12px;
  }
  
  .sidebar ul {
    list-style: none;
    padding: 0;
  }
  
  .sidebar li {
    margin-bottom: 12px;
  }
  
  .sidebar a {
    text-decoration: none;
    color: var(--color-text);
    font-weight: 500;
    display: block;
    padding: 8px;
    border-radius: 4px;
    transition: background var(--transition-speed);
  }
  
  .sidebar a:hover,
  .sidebar a.active {
    background: #e3f2fd;
    color: var(--color-primary);
    font-weight: bold;
  }
  
  .main-content {
    padding: var(--spacing);
    background: var(--color-card);
  }
  
  .main-content h2 {
    margin-bottom: var(--spacing);
    font-size: 1.5rem;
    color: var(--color-primary);
  }
  
  .transaction-table {
    width: 100%;
    border-collapse: collapse;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    border-radius: var(--border-radius);
    overflow: hidden;
  }
  
  .transaction-table th,
  .transaction-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
  }
  
  .transaction-table th {
    background: #f1f5f9;
    font-weight: 600;
  }
  
  .transaction-table tr:nth-child(even) {
    background: #f8fafc;
  }
  
  .transaction-table tr:hover {
    background: #eef2f7;
  }
  
  button {
    background: var(--color-primary);
    color: white;
    border: none;
    padding: 8px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 500;
    transition: background var(--transition-speed);
  }
  
  button:hover {
    background: #1565c0;
  }
  
  form {
    display: inline;
  }
  
  .deleted-transaction {
    background-color: #ffecec;
    color: #d00;
    font-weight: bold;
  }

  </style>
</head>
<body>
  <div class="container">
    <!-- Sidebar -->
    <div class="sidebar">
      <h3>📁 Menu</h3>
      <ul>
        <li><a href="dashboard.php">🏠 Dashboard</a></li>
        <li><a href="statistics.php">📊 Thống kê nâng cao</a></li>
        <li><a href="trash.php" class="active">🗑️ Thùng rác</a></li>
        <li><a href="feedback.php">📩 Gửi phản hồi</a></li>
      </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
      <h2>🗑️ Lịch sử giao dịch đã xóa</h2>
      <p>🗑️ Tổng số giao dịch đã xóa: <strong><?= pg_num_rows($res) ?></strong></p>
      <form method="get" class="filter-panel">
      <div class="filters">
        <div class="form-group">
          <label for="from_date">Từ ngày</label>
          <input type="date" name="from_date" value="<?= htmlspecialchars($_GET['from_date'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label for="to_date">Đến ngày</label>
          <input type="date" name="to_date" value="<?= htmlspecialchars($_GET['to_date'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label for="description">Mô tả</label>
          <input type="text" name="description" value="<?= htmlspecialchars($_GET['description'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label for="wallet_type">Khoản tiền</label>
          <select name="wallet_type">
            <option value="">Tất cả</option>
            <?php
            $wallets = pg_query_params($conn, "SELECT DISTINCT wallet_type FROM transactions WHERE user_id = $1 AND type = 3", [$user_id]);
            while ($w = pg_fetch_assoc($wallets)) {
              $selected = ($_GET['wallet_type'] ?? '') === $w['wallet_type'] ? 'selected' : '';
              echo "<option value=\"{$w['wallet_type']}\" $selected>{$w['wallet_type']}</option>";
            }
            ?>
          </select>
        </div>
      </div>
      <div class="filter-buttons">
        <button type="submit">🔍 Lọc</button>
        <a href="trash.php" class="reset">🧹 Làm mới</a>
      </div>
    </form>

      <!-- Bảng hiển thị giao dịch đã xóa -->
      <table class="transaction-table">
        <thead>
          <tr>
            <th>Giờ</th>
            <th>Loại</th>
            <th>Mô tả</th>
            <th>Số tiền</th>
            <th>Số dư còn lại</th>
            <th>Khoản tiền</th>
            <th>Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($row = pg_fetch_assoc($res)): ?>
          <tr>
            <td><?= date('H:i:s', strtotime($row['date'])) ?></td>
            <td>Đã xóa</td>
            <td><?= htmlspecialchars($row['description']) ?></td>
            <td><?= number_format($row['amount'], 0, ',', '.') ?> VND</td>
            <td><?= number_format($row['remaining_balance'], 0, ',', '.') ?> VND</td>
            <td><?= $row['wallet_type'] ?></td>
            <td>
              <form method="POST" action="restore.php" onsubmit="return confirm('Khôi phục giao dịch này?');">
                <input type="hidden" name="transaction_id" value="<?= $row['id'] ?>">
                <button type="submit">↩️ Khôi phục</button>
              </form>
            </td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
    <script>
      setTimeout(() => {
        const alertBox = document.querySelector('div[style*="background-color"]');
        if (alertBox) alertBox.style.display = 'none';
      }, 4000);
    </script>
</body>
</html>
