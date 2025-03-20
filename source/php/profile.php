<?php
require_once 'database.php';

header('Content-Type: application/json');

$db = Database::getInstance()->getDb();

// Get the profile ID from the URL
$profileId = $_GET['id'] ?? null;

if (!$profileId) {
    echo json_encode(['error' => 'Profile ID is required']);
    exit;
}

// Fetch the profile data for the requested user
$stmt = $db->prepare("SELECT id, username FROM users WHERE id = ?");
$stmt->execute([$profileId]);
$profileUser = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$profileUser) {
    echo json_encode(['error' => 'User not found']);
    exit;
}

// Check if the logged-in user is viewing their own profile
$isOwnProfile = false;
$authToken = $_COOKIE['auth_token'] ?? null;

if ($authToken) {
    $stmt = $db->prepare("SELECT id FROM users WHERE token = ?");
    $stmt->execute([$authToken]);
    $loggedInUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($loggedInUser && $loggedInUser['id'] == $profileId) {
        $isOwnProfile = true;
    }
}

// Fetch past games for the profile user
// Updated past games query with joins
$stmt = $db->prepare("
    SELECT
        g.id,
        g.rows,
        g.cols,
        g.time,
        g.increment,
        p1.username AS player1_username,
        p2.username AS player2_username,
		p1.id AS player1_id,
		p2.id AS player2_id,
        winner.username AS winner_username,
        g.winner_id
    FROM games g
    JOIN users p1 ON g.player1_id = p1.id
    JOIN users p2 ON g.player2_id = p2.id
    LEFT JOIN users winner ON g.winner_id = winner.id
    WHERE g.player1_id = ? OR g.player2_id = ?
");
$stmt->execute([$profileId, $profileId]);
$pastGames = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate win rate
$totalGames = count($pastGames);
$wins = 0;
foreach ($pastGames as $game) {
    if ($game['winner_id'] == $profileId) {
        $wins++;
    }
}
$winRate = $totalGames > 0 ? round(($wins / $totalGames) * 100, 2) : 0;

// Prepare the response
$response = [
    'id' => $profileUser['id'],
    'username' => $profileUser['username'],
    'win_rate' => $winRate,
    'total_games' => $totalGames,
    'past_games' => $pastGames,
    'is_own_profile' => $isOwnProfile
];

echo json_encode($response);