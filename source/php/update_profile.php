<?php
require_once 'database.php';

header('Content-Type: application/json');

$db = Database::getInstance()->getDb();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
	exit;
}

$authToken = $_COOKIE['auth_token'] ?? null;

if (!$authToken) {
	echo json_encode(['success' => false, 'error' => 'Unauthorized']);
	exit;
}

$stmt = $db->prepare("SELECT id, password_hash FROM users WHERE token = ?");
$stmt->execute([$authToken]);
$loggedInUser = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$loggedInUser) {
	echo json_encode(['success' => false, 'error' => 'User not found']);
	exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$currentPassword = $data['currentPassword'] ?? null;
$newUsername = $data['newUsername'] ?? null;
$newPassword = $data['newPassword'] ?? null;

if (!$currentPassword || (!$newUsername && !$newPassword)) {
	echo json_encode(['success' => false, 'error' => 'Current password and at least one new field are required']);
	exit;
}

if (!password_verify($currentPassword, $loggedInUser['password_hash'])) {
	echo json_encode(['success' => false, 'error' => 'Current password is incorrect']);
	exit;
}

$updates = [];
$params = [];

if ($newUsername) {
	$updates[] = 'username = ?';
	$params[] = $newUsername;
}

if ($newPassword) {
	$updates[] = 'password_hash = ?';
	$params[] = password_hash($newPassword, PASSWORD_DEFAULT);
}

$params[] = $loggedInUser['id'];

$query = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->execute($params);

echo json_encode(['success' => true]);
