<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id = $_SESSION['user_id'];

// Xử lý khôi phục
$transaction_id = $_POST['transaction_id'] ?? null;
if ($transaction_id) {
  $check_sql = "SELECT * FROM transactions WHERE id = $1 AND user_id = $2 AND type = 3";
  $check_res = pg_query_params($conn, $check_sql, [$transaction_id, $user_id]);
  if (pg_num_rows($check_res) === 1) {
      
    // Bước 2: Lấy loại giao dịch hiện tại
    $get_type_sql = "SELECT type FROM transactions WHERE id = $1 AND user_id = $2";
    $get_type_res = pg_query_params($conn, $get_type_sql, [$transaction_id, $user_id]);
    
    if (pg_num_rows($get_type_res) > 0) {
        $current_type = pg_fetch_result($get_type_res, 0, 'type');
    
        // Cập nhật: lưu loại gốc và đánh dấu là đã xóa
        $update_sql = "UPDATE transactions SET type = 3, original_type = $1 WHERE id = $2 AND user_id = $3";
        pg_query_params($conn, $update_sql, [$current_type, $transaction_id, $user_id]);
    } else {
        echo "Không tìm thấy giao dịch.";
    }

    $restore_sql = "UPDATE transactions SET type = 1 WHERE id = $1 AND user_id = $2";
    pg_query_params($conn, $restore_sql, [$transaction_id, $user_id]);
    $_SESSION['restored'] = "Giao dịch đã được khôi phục!";
  }
  header("Location: trash.php");
  exit();
}

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

// Truy vấn danh sách tài khoản
$account_res = pg_query_params($conn, "SELECT id, name FROM accounts WHERE user_id = $1", [$user_id]);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>🗑️ Thùng rác</title>
    <style>
      * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
      }
    
      body {
        font-family: 'Segoe UI', sans-serif;
        background-color: #f4f6f8;
        color: #2c3e50;
      }
    
      .container {
        display: flex;
        min-height: 100vh;
      }
    
      /* Sidebar */
      .sidebar {
          width: 220px;
          background-color: #2c3e50;
          color: white;
          padding: 24px;
          font-size: 16px;
        }
      .sidebar h2 {
        font-size: 18px;
        margin-bottom: 16px;
      }
    
      .sidebar ul {
        list-style: none;
        padding: 0;
      }
    
      .sidebar ul li {
        margin-bottom: 12px;
      }
    
     .sidebar ul li a {
          color: white;
          text-decoration: none;
          display: block;
          padding: 10px 14px;
          border-radius: 6px;
          transition: background-color 0.3s ease;
        }
        .sidebar ul li.active a {
          background-color: #1abc9c;
          color: white;
        }
      .sidebar ul li.active a,
        .sidebar ul li a:hover {
          background-color: #1abc9c;
          color: white;
        }
    
      /* Main content */
      .main-content {
        flex: 1;
        padding: 30px;
      }
    
      .header h1 {
        font-size: 24px;
        margin-bottom: 8px;
      }
    
      .header p {
          font-size: 16px;
          color: #7f8c8d;
          margin-bottom: 24px;
        }
    
      .content {
        background-color: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 0 8px rgba(0,0,0,0.05);
      }
    
      /* Table */
      table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
      }
    
      th, td {
        padding: 12px;
        border-bottom: 1px solid #ddd;
        text-align: left;
      }
    
      th {
        background-color: #ecf0f1;
        font-weight: bold;
      }
    
      tr:hover {
        background-color: #f9f9f9;
      }
    
      .deleted-transaction td {
        text-decoration: line-through;
        opacity: 0.6;
      }
    
      /* Buttons */
      .btn-edit, .btn-delete {
        padding: 6px 12px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        margin-right: 6px;
      }
    
      .btn-edit {
        background-color: #3498db;
        color: white;
      }
    
      .btn-delete {
        background-color: #e74c3c;
        color: white;
      }
    
      .btn-edit:hover {
        background-color: #2980b9;
      }
    
      .btn-delete:hover {
        background-color: #c0392b;
      }
    
      /* Feedback popup */
      .popup-feedback {
        background-color: #fff3cd;
        color: #856404;
        padding: 12px;
        margin-bottom: 16px;
        border-left: 4px solid #ffeeba;
        border-radius: 4px;
        font-weight: bold;
        animation: fadeOut 4s ease forwards;
      }
        form {
          display: flex;
          flex-wrap: wrap;
          gap: 16px;
          margin-bottom: 20px;
          align-items: center;
        }
        
        form label {
          min-width: 80px;
          font-weight: bold;
        }
        
        form input, form select {
          padding: 8px;
          border: 1px solid #ccc;
          border-radius: 6px;
          min-width: 160px;
        }
        td:last-child {
          text-align: center;
        }
        .header .user {
          display: flex;
          align-items: center;
        }  
        .header .user a {
          display: flex;
          align-items: center;
          text-decoration: none;
          color: white;
        }
        .header .user span {
          font-weight: bold;
        }
        .header .user img {
          width: 40px;
          height: 40px;
          border-radius: 50%;
          margin-left: 10px;
          object-fit: cover;
          border: 2px solid white;
        } 
        .profile-link img {
          width: 40px;
          height: 40px;
          border-radius: 50%;
          object-fit: cover;
          margin-left: 10px;
          border: 2px solid white;
        }
        .avatar-img {
          width: 40px;
          height: 40px;
          border-radius: 50%;
          object-fit: cover;
          border: 2px solid white;
        }
        .sidebar-header {
          margin-bottom: 24px;
          text-align: center;
        }
        .sidebar-header h1 {
          font-size: 20px;
          margin-bottom: 6px;
          color: #ecf0f1;
        }
        .sidebar-header p {
          font-size: 14px;
          color: #bdc3c7;
        }
      @keyframes fadeOut {
        0% { opacity: 1; }
        80% { opacity: 1; }
        100% { opacity: 0; display: none; }
      }
    </style>
</head>
<body>
  <div class="container">
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="user">
        <a href="profile.php" class="profile-link">
          <span>Xin chào, <?= htmlspecialchars($user['fullname'] ?? '') ?></span>
          <img src="<?= $avatarPath ?>" alt="Avatar" class="avatar-img">
        </a>
      </div>
      <h2>📁 Menu</h2>
      <ul>
        <li><a href="dashboard.php">🏠 Dashboard</a></li>
        <li><a href="advanced_statistics.php">📊 Thống kê nâng cao</a></li>
        <li class="active"><a href="trash.php">🗑️ Thùng rác</a></li>
        <li><a href="feedback.php">📩 Gửi phản hồi</a></li>
      </ul>
    </aside>

    <!-- Main content -->
    <main class="main-content">
      <header class="header">
        <h1>🗑️ Lịch sử giao dịch đã xóa</h1>
        <p>Tổng số giao dịch đã xóa: <strong><?= $deleted_count ?></strong></p>
      </header>

      <!-- Nội dung chính -->
      <section class="content">
        <?php if (isset($_GET['message'])): ?>
          <div class="popup-feedback">
            <?= htmlspecialchars($_GET['message']) ?>
          </div>
        <?php endif; ?>
        
        <!-- Form lọc theo loại ví -->
        <form method="GET" action="trash.php">
          <label for="from_date">Từ ngày:</label>
          <input type="date" name="from_date" id="from_date" value="<?= htmlspecialchars($from_date) ?>">
        
          <label for="to_date">Đến ngày:</label>
          <input type="date" name="to_date" id="to_date" value="<?= htmlspecialchars($to_date) ?>">
        
          <label for="description">Mô tả:</label>
          <input type="text" name="description" id="description" value="<?= htmlspecialchars($description) ?>">
        
          <label for="account_id">Khoản tiền:</label>
          <select name="account_id" id="account_id">
            <option value="">-- Tất cả --</option>
            <?php while ($row = pg_fetch_assoc($account_res)): ?>
              <option value="<?= $row['id'] ?>" <?= $account_id == $row['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($row['name']) ?>
              </option>
            <?php endwhile; ?>
          </select>
        
          <button type="submit" class="btn-edit">Lọc</button>
        </form>

        
        <!-- Bảng giao dịch đã xóa -->
        <table>
          <thead>
              <tr>
                <th>Ngày tạo</th>
                <th>Ngày xóa</th>
                <th>Loại</th>
                <th>Số tiền</th>
                <th>Số dư còn lại</th>
                <th>Ghi chú</th>
                <th>Khoản tiền</th>
                <th>Thao tác</th>
              </tr>
            </thead>
            <tbody>
            <?php if (empty($deleted_transactions)): ?>
              <tr><td colspan="8">Không có giao dịch nào đã xóa.</td></tr>
            <?php else: ?>
              <?php foreach ($deleted_transactions as $txn): ?>
                <tr class="deleted-transaction">
                  <td><?= date('d/m/Y H:i:s', strtotime($txn['date'])) ?></td>
                  <td><?= isset($txn['deleted_at']) ? date('d/m/Y H:i:s', strtotime($txn['deleted_at'])) : 'Không rõ' ?></td>
                  <td><?= $txn['original_type'] == 1 ? 'Thu' : ($txn['original_type'] == 2 ? 'Chi' : 'Không rõ') ?></td>
                  <td><?= number_format($txn['amount'], 0, ',', '.') ?>₫</td>
                  <td><?= number_format($txn['remaining_balance'], 0, ',', '.') ?>₫</td>
                  <td><?= htmlspecialchars($txn['description']) ?></td>
                  <td><?= htmlspecialchars($txn['account_name']) ?></td>
                  <td>
                    <form method="POST" action="restore.php" style="display:inline;">
                        <input type="hidden" name="transaction_id" value="<?= $txn['id'] ?>">
                        <button type="submit" class="btn-edit">Khôi phục</button>
                      </form>
                      <form method="POST" action="delete_forever.php" style="display:inline;" onsubmit="return confirm('Bạn có chắc muốn xóa vĩnh viễn?');">
                        <input type="hidden" name="transaction_id" value="<?= $txn['id'] ?>">
                        <button type="submit" class="btn-delete">Xóa vĩnh viễn</button>
                      </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        </table>
      </section>
    </main>
  </div>
</body>
</html>
