<?php
session_start();
require 'config.php';

if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("UPDATE users SET session_token = NULL WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    session_destroy();
    session_regenerate_id(true);
}

header("Location: index.php");
exit();