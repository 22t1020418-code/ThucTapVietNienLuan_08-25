<?php
session_start();
include "db.php";

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id = $_SESSION['user_id'];

// Thông báo khôi phục
if (!empty($_SESSION['restored'])) {
  echo "<div style='background-color: #fff3cd; color: #856404; padding: 12px; margin: 16px 0; border: 1px solid #ffeeba; border-radius: 6px; font-weight: bold;'>" . $_SESSION['restored'] . "</div>";
  unset($_SESSION['restored']);
}

// Truy vấn giao dịch đã xóa
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';
$description = $_GET['description'] ?? '';
$account_id = $_GET['account_id'] ?? '';
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
if ($account_id) {
  $sql .= " AND account_id = \$$idx";
  $params[] = $account_id;
  $idx++;
}

$sql .= " ORDER BY date DESC";
$res = pg_query_params($conn, $sql, $params);
if (!$res) {
  echo "Lỗi truy vấn cơ sở dữ liệu.";
  exit();
}
$deleted_count = pg_num_rows($res);
$deleted_transactions = pg_fetch_all($res) ?: [];

$grouped = [];

foreach ($deleted_transactions as $tran) {
    $date = date('Y-m-d', strtotime($tran['date']));
    if (!isset($grouped[$date])) {
        $grouped[$date] = [];
    }
    $grouped[$date][] = $tran;
}

// Truy vấn danh sách tài khoản
$account_res = pg_query_params($conn, "SELECT id, name, balance FROM accounts WHERE user_id = $1", [$user_id]);
$accounts = pg_fetch_all($account_res) ?: [];

$totalAccountBalance = '0';
foreach ($accounts as $acc) {
    $balance_res = pg_query_params($conn, "SELECT balance FROM accounts WHERE id = $1 AND user_id = $2", [$acc['id'], $user_id]);
    if ($balance_res && $row = pg_fetch_assoc($balance_res)) {
        $totalAccountBalance += $row['balance'];
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>🗑️ Giao dịch đã xóa</title>
    <style>
    :root {
      --primary-color: #4CAF50;
      --secondary-color: #f1f1f1;
      --text-color: #333;
      --border-color: #ddd;
      --hover-color: #e0e0e0;
      --danger-color: #e74c3c;
      --income-color: #2ecc71;
      --expense-color: #e67e22;
      --font-family: 'Segoe UI', sans-serif;
    }
    
    body {
      margin: 0;
      font-family: var(--font-family);
      background-color: var(--secondary-color);
    }
    
    .header {
      background-color: var(--primary-color);
      color: white;
      padding: 16px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .header h2 {
      margin: 0;
    }
    
    .user {
      display: flex;
      align-items: center;
    }
    
    .profile-link {
      color: white;
      text-decoration: none;
      display: flex;
      align-items: center;
    }
    
    .avatar-img {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      margin-left: 8px;
    }
    
    .dashboard-wrapper {
      display: flex;
    }
    
    .sidebar {
      width: 240px;
      background-color: white;
      padding: 16px;
      border-right: 1px solid var(--border-color);
    }
    
    .sidebar h3 {
      margin-top: 0;
    }
    
    .account-card {
      display: block;
      padding: 8px;
      margin-bottom: 8px;
      background-color: var(--secondary-color);
      border-radius: 4px;
      text-decoration: none;
      color: var(--text-color);
    }
    
    .account-card:hover {
      background-color: var(--hover-color);
    }
    
    .add-account {
      display: block;
      margin-top: 8px;
      text-decoration: none;
      color: var(--primary-color);
    }
    
    .account-total {
      margin-top: 16px;
      font-weight: bold;
    }
    
    .sidebar a {
      display: block;
      margin-top: 12px;
      text-decoration: none;
      color: var(--text-color);
    }
    
    .sidebar a.active {
      font-weight: bold;
      color: var(--primary-color);
    }
    
    .content {
      flex-grow: 1;
      padding: 24px;
    }
    
    .content-header h2 {
      margin-top: 0;
    }
    
    .filter-panel {
      background-color: white;
      padding: 16px;
      border-radius: 8px;
      margin-bottom: 24px;
      box-shadow: 0 0 4px rgba(0,0,0,0.1);
    }
    
    .filter-row {
      display: flex;
      flex-wrap: wrap;
      justify-content: space-between;
      align-items: center;
    }
    
    .filters {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
    }
    
    .filter-summary-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 12px;
      flex-wrap: wrap;
    }
    
    .stats-inline span {
      margin-right: 16px;
    }
    
    .filter-buttons button,
    .filter-buttons .reset {
      background-color: var(--primary-color);
      color: white;
      border: none;
      padding: 8px 12px;
      border-radius: 4px;
      cursor: pointer;
      text-decoration: none;
    }
    
    .filter-buttons .reset {
      background-color: var(--danger-color);
    }
    
    .table-wrapper {
      overflow-x: auto;
      background-color: white;
      border-radius: 8px;
      padding: 16px;
      box-shadow: 0 0 4px rgba(0,0,0,0.1);
    }
    
    table {
      width: 100%;
      border-collapse: collapse;
    }
    
    th, td {
      padding: 12px;
      border-bottom: 1px solid var(--border-color);
      text-align: left;
    }
    
    tr:hover {
      background-color: var(--hover-color);
    }
    
    .deleted-transaction td {
      color: var(--danger-color);
    }
    
    .action-buttons {
      display: flex;
      gap: 8px;
    }
    
    .btn-edit,
    .btn-delete {
      padding: 6px 10px;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      color: white;
    }
    
    .btn-edit {
      background-color: #e3f2fd;
      color: #1565c0;
      padding: 6px 10px;
      border-radius: 4px;
      font-size: 14px;
      text-decoration: none;
      margin-right: 8px;
    }
    
    .btn-delete {
      background-color: #ffebee;
      color: #c62828;
      padding: 6px 10px;
      border-radius: 4px;
      font-size: 14px;
      border: none;
      cursor: pointer;
    }
    
    .toggle-btn {
      background-color: transparent;
      border: none;
      color: var(--primary-color);
      cursor: pointer;
      font-size: 14px;
      margin-top: 8px;
    }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
      <h2>Giao dịch đã xóa</h2>
      <div class="user">
        <a href="profile.php" class="profile-link">
          <span>Xin chào, <?= htmlspecialchars($user['fullname'] ?? '') ?></span>
          <img src="<?= $avatarPath ?>" alt="Avatar" class="avatar-img">
        </a>
      </div>
    </div>
    
    <div class="dashboard-wrapper">
      <!-- Sidebar -->
      <nav class="sidebar">
        <h3>Các khoản tiền</h3>
        <?php foreach ($accounts as $acc): ?>
          <a href="edit_account_balance.php?account_id=<?= $acc['id'] ?>" class="account-card">
            <div class="account-name"><?= htmlspecialchars($acc['name']) ?></div>
            <div class="account-balance">Số dư: <?= number_format($acc['balance'], 0, ',', '.') ?> VND</div>
          </a>
        <?php endforeach; ?>
        <a href="create_account.php" class="add-account">+ Thêm khoản tiền</a>
        <div class="account-total">
          <strong>Tổng số dư:</strong> <?= number_format($totalAccountBalance, 0, ',', '.') ?> VND
        </div>
        <hr>
        <a href="dashboard.php">📋 Quay lại Dashboard</a>
        <a href="trash.php" class="active">🗑️ Giao dịch đã xóa</a>
      </nav>
    
      <!-- Content -->
      <div class="content">
        <main class="main">
          <div class="content-header">
            <h2>🗑️ Giao dịch đã xóa</h2>
          </div>
    
          <?php if (empty($deletedTransactions)): ?>
            <p>Không có giao dịch đã xóa nào.</p>
          <?php else: ?>
            <div class="table-wrapper">
              <table>
                <thead>
                  <tr>
                    <th>Ngày</th>
                    <th>Loại</th>
                    <th>Mô tả</th>
                    <th>Số tiền</th>
                    <th>Khoản tiền</th>
                    <th>Thao tác</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($deletedTransactions as $row): ?>
                    <tr class="deleted-transaction">
                      <td><?= date('d/m/Y H:i', strtotime($row['date'])) ?></td>
                      <td><?= $typeLabels[$row['type']] ?? '-' ?></td>
                      <td><?= htmlspecialchars($row['description']) ?></td>
                      <td><?= number_format($row['amount'], 0, ',', '.') ?> VND</td>
                      <td><?= htmlspecialchars($row['account_name']) ?></td>
                      <td class="action-buttons">
                        <form method="post" action="restore.php" style="display:inline;">
                          <input type="hidden" name="transaction_id" value="<?= $row['id'] ?>">
                          <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                          <button type="submit" class="btn-edit">↩️ Khôi phục</button>
                        </form>
                        <form method="post" action="delete_forever.php" style="display:inline;" onsubmit="return confirm('Xóa vĩnh viễn giao dịch này?');">
                          <input type="hidden" name="transaction_id" value="<?= $row['id'] ?>">
                          <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                          <button type="submit" class="btn-delete">❌ Xóa vĩnh viễn</button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </main>
      </div>
    </div>
    <script>
    function toggleGroup(id) {
      const el = document.getElementById(id);
      el.style.display = (el.style.display === 'none') ? 'block' : 'none';
    }
    </script>
</body>
</html>
