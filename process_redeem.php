<?php
session_start();
require_once 'connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: homepage.php");
    exit;
}

$userId = $_SESSION['user_id'];
$voucherId = intval($_GET['id']);

// ✅ Check if voucher exists
$stmt = $conn->prepare("SELECT voucher_id FROM voucher WHERE voucher_id = ?");
$stmt->execute([$voucherId]);
$voucher = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$voucher) {
    header("Location: homepage.php");
    exit;
}

// ✅ Store only voucher_id (quantity defaults to 1)
$_SESSION['checkout_items'] = [$voucherId];

// ✅ Redirect to checkout
header("Location: checkout.php");
exit;
