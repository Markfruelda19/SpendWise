<?php
require_once 'includes/auth_guard.php';
require_once 'config/database.php';

$id = (int)($_GET['id'] ?? 0);

// Only delete if it belongs to the current user
$stmt = $pdo->prepare("DELETE FROM transactions WHERE id = ? AND user_id = ?");
$stmt->execute([$id, $current_user_id]);

header("Location: transactions.php?deleted=1");
exit();