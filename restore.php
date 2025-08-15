<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit();
}

$user_id = $_SESSION['user_id'];
$transaction_id = $_POST['transaction_id'] ?? null;

if (!$transaction_id || !is_numeric($transaction_id)) {
  $_SESSION['restored'] = "❌ Giao dịch không hợp lệ.";
  header("Location: trash.php");
  exit();
}

// Bắt đầu giao dịch
pg_query($conn, 'BEGIN');

try {
  // Kiểm tra giao dịch có tồn tại và thuộc về user
  $check_sql = "SELECT * FROM transactions WHERE id = $1 AND user_id = $2 AND type = 3";
  $check_res = pg_query_params($conn, $check_sql, [ $transaction_id, $user_id ]);

  if (pg_num_rows($check_res) !== 1) {
    throw new Exception("Giao dịch không tồn tại hoặc không thuộc về bạn.");
  }

  // Khôi phục: cập nhật type về 1 (hoặc 2 nếu bạn có phân loại)
  $restore_sql = "UPDATE transactions SET type = 1 WHERE id = $1";
  $restore_res = pg_query_params($conn, $restore_sql, [ $transaction_id ]);

  if (!$restore_res) {
    throw new Exception("Không thể khôi phục giao dịch.");
  }

  pg_query($conn, 'COMMIT');
  $_SESSION['restored'] = "✅ Giao dịch đã được khôi phục!";
} catch (Exception $e) {
  pg_query($conn, 'ROLLBACK');
  $_SESSION['restored'] = "❌ Lỗi khôi phục: " . $e->getMessage();
}

header("Location: trash.php");
exit();
?>
