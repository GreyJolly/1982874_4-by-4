<?php
require_once 'database.php';

$db = DataBase::getInstance()->getDb();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	echo json_encode(["error" => "Method Not Allowed"]);
	exit;
}

$data = json_decode(file_get_contents("php://input"), true);

// Handle logout request
if (isset($data['action']) && $data['action'] === 'logout') {
	// Clear the token from the database if the user is logged in
	if (isset($_COOKIE['auth_token'])) {
		$token = $_COOKIE['auth_token'];
		$stmt = $db->prepare("UPDATE users SET token = NULL WHERE token = :token");
		$stmt->bindValue(':token', $token, PDO::PARAM_STR);
		$stmt->execute();
	}

	// Expire the auth_token cookie
	setcookie('auth_token', '', time() - 3600, '/', '', true, true); // Secure and HttpOnly

	echo json_encode(["success" => true, "message" => "Logout successful"]);
	exit;
}

// Handle login request
if (!isset($data['username'], $data['password'])) {
	http_response_code(400);
	echo json_encode(["error" => "Missing username or password"]);
	exit;
}

$stmt = $db->prepare("SELECT id, password_hash FROM users WHERE username = :username");
$stmt->bindValue(':username', $data['username'], PDO::PARAM_STR);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user && password_verify($data['password'], $user['password_hash'])) {
	$token = bin2hex(random_bytes(32));

	$stmt = $db->prepare("UPDATE users SET token = :token WHERE id = :id");
	$stmt->bindValue(':token', $token, PDO::PARAM_STR);
	$stmt->bindValue(':id', $user['id'], PDO::PARAM_INT);
	$stmt->execute();

	header("Content-Type: application/json");
	header("Set-Cookie: auth_token=$token; Max-Age=" . (86400 * 30) . "; Path=/; HttpOnly; SameSite=None; Secure");
	echo json_encode(["success" => true, "message" => "Login successful"]);
} else {
	http_response_code(401);
	echo json_encode(["error" => "Invalid username or password"]);
}
