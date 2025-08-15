<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit();
}

$user_id = $_SESSION['user_id'];
$transaction_id = $_POST['transaction_id'] ?? null;

if ($transaction_id && ctype_digit($transaction_id)) {
  $check_sql = "SELECT * FROM transactions WHERE id = $1 AND user_id = $2 AND type = 3";
  $check_res = pg_query_params($conn, $check_sql, [$transaction_id, $user_id]);

  if (pg_num_rows($check_res) === 1) {
    $hide_sql = "UPDATE transactions SET is_hidden = TRUE WHERE id = $1";
    pg_query_params($conn, $hide_sql, [$transaction_id]);
    $_SESSION['restored'] = "Giao dịch đã được ẩn khỏi lịch sử.";
  }
}

header("Location: dashboard.php");
exit();
