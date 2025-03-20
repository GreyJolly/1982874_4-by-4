<?php
require_once 'database.php';

header("Content-Type: application/json");

if (!isset($_COOKIE['auth_token'])) {
    echo json_encode(["authenticated" => false]);
    exit;
}

$db = DataBase::getInstance()->getDb();
$stmt = $db->prepare("SELECT id, username FROM users WHERE token = :token");
$stmt->bindValue(':token', $_COOKIE['auth_token'], PDO::PARAM_STR);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    echo json_encode([
        "authenticated" => true,
        "userId" => $user['id'],
        "username" => $user['username'],
        "token" => $_COOKIE['auth_token']
    ]);
} else {
    echo json_encode(["authenticated" => false]);
}
