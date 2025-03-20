<?php

require __DIR__ . '/../vendor/autoload.php';
require_once 'database.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

ini_set('session.save_handler', 'redis');
ini_set('session.save_path', 'tcp://redis:6379');
session_start();

class GameServer implements MessageComponentInterface
{
	protected $clients;
	protected $challenges;
	protected $games;
	protected $db;
	private $loggedInUsers = [];
	private $activeChallenges = [];

	public function __construct()
	{
		$this->clients = new \SplObjectStorage;
		$this->challenges = [];
		$this->games = [];
		$this->db = DataBase::getInstance()->getDb();
	}

	public function onOpen(ConnectionInterface $conn)
	{
		$this->clients->attach($conn);
		$conn->userId = null;
		$conn->username = null;
	}

	public function onMessage(ConnectionInterface $from, $msg)
	{
		error_log("Received message: $msg\n"); // Log incoming messages
		$data = json_decode($msg, true);

		if (json_last_error() !== JSON_ERROR_NONE) {
			error_log("Invalid JSON received\n");
			return;
		}

		switch ($data['type']) {
			case 'register':
				error_log("Register request received\n");
				$this->registerUser($from, $data['username'], $data['password']);
				break;

			case 'login':
				error_log("Login request received\n");
				$this->loginUser($from, $data['username'], $data['password']);
				break;

			case 'token-login':
				error_log("Token login request received\n");
				$this->tokenLogin($from, $data['token']);
				break;

			case 'query-challenges':
				$this->sendChallenges($from);
				break;

			case 'create-challenge':
				if ($from->userId === null) {
					$from->send(json_encode(['type' => 'error', 'message' => 'You must be logged in to create a challenge']));
					return;
				}
				if (isset($this->activeChallenges[$from->userId])) {
					$from->send(json_encode(['type' => 'error', 'message' => 'You already have an active challenge']));
					return;
				}
				$id = uniqid();
				$this->challenges[$id] = [
					'id' => $id,
					'rows' => $data['rows'] > 20 ? 20 : $data['rows'],
					'cols' => $data['cols'] > 20 ? 20 : $data['cols'],
					'win' => min($data['win'], $data['rows'], $data['cols']),
					'time' => $data['time'],
					'increment' => $data['increment'],
					'starting' => $data['starting'],
					'creator' => $from->userId
				];
				$this->activeChallenges[$from->userId] = $id; // Track the active challenge
				$this->broadcastChallenges();
				$from->send(json_encode(['type' => 'challenge-created', 'message' => 'Challenge created successfully']));
				break;

			case 'join':
				if ($from->userId === null) {
					$from->send(json_encode(['type' => 'join-failure', 'message' => 'You must be logged in to join a challenge']));
					return;
				}
				if (isset($this->activeChallenges[$from->userId])) {
					$from->send(json_encode(['type' => 'join-failure', 'message' => 'You cannot join a challenge while you have an active challenge']));
					return;
				}
				if (isset($this->challenges[$data['id']])) {
					$challenge = $this->challenges[$data['id']];
					error_log("Joining challenge");
					unset($this->challenges[$data['id']]);
					unset($this->activeChallenges[$challenge['creator']]); // Remove the challenge from active challenges

					if ($challenge['starting'] == 'random') {
						$starting = rand(0, 1) == 0 ? $challenge['creator'] : $from->userId;
					} else {
						$starting = $challenge['starting'] == 'creator' ? $challenge['creator'] : $from->userId;
					}

					$gameId = uniqid();
					$this->games[$gameId] = [
						'players' => [$challenge['creator'], $from->userId],
						'state' => [
							'id' => $gameId,
							'rows' => $challenge['rows'],
							'cols' => $challenge['cols'],
							'win' => $challenge['win'],
							'board' => array_fill(0, $challenge['rows'], array_fill(0, $challenge['cols'], null)),
							'move_sequence' => [],
							'currentPlayer' => $starting,
							'player1Time' => $challenge['time'],
							'player2Time' => $challenge['time'],
							'lastMoveTime' => time(),
							'draw_offers' => [false, false]
						],
						'starting' => $starting,
						'time' => $challenge['time'],
						'increment' => $challenge['increment']
					];
					$this->sendGameStart($this->getConnectionByUserId($challenge['creator']), $gameId);
					$this->sendGameStart($from, $gameId);

					$this->broadcastChallenges();
				}
				break;

			case 'retire':
				if ($from->userId === null) {
					$from->send(json_encode(['type' => 'error', 'message' => 'You must be logged in to retire a challenge']));
					return;
				}
				if (isset($this->activeChallenges[$from->userId])) {
					$challengeId = $this->activeChallenges[$from->userId];
					unset($this->challenges[$challengeId]);
					unset($this->activeChallenges[$from->userId]);
					$this->broadcastChallenges();
					$from->send(json_encode(['type' => 'challenge-retired', 'message' => 'Challenge retired successfully']));
				} else {
					$from->send(json_encode(['type' => 'error', 'message' => 'You do not have an active challenge to retire']));
				}
				break;

			case 'request-state':
				if (isset($this->games[$data['gameId']])) {
					$game = $this->games[$data['gameId']];

					$currentTime = time();
					$elapsedTime = $currentTime - $game['state']['lastMoveTime'];
					if ($game['state']['currentPlayer'] == $game['players'][0]) {
						$game['state']['player1Time'] -= $elapsedTime;
						if ($game['state']['player1Time'] <= 0) {
							$this->endGame($game['state'], $game['players'][1], 'timeout');
							return;
						}
					} else {
						$game['state']['player2Time'] -= $elapsedTime;
						if ($game['state']['player2Time'] <= 0) {
							$this->endGame($game['state'], $game['players'][0], 'timeout');
							return;
						}
					}

					$this->sendGameState($from, $game['state']);
				} else {
					$from->send(json_encode(['type' => 'error', 'message' => 'Invalid Game ID']));
				}

				break;

			case 'move':
				$user =	$this->checkToken($from, $data['token']);

				if (!isset($this->games[$data['gameId']])) return;
				$game = &$this->games[$data['gameId']];
				if ($user['id'] != $game['state']['currentPlayer']) {
					error_log("User is not authorized to move.");
					return;
				}

				$currentTime = time();
				$elapsedTime = $currentTime - $game['state']['lastMoveTime'];
				if ($game['state']['currentPlayer'] == $game['players'][0]) {
					$game['state']['player1Time'] -= $elapsedTime;
					if ($game['state']['player1Time'] <= 0) {
						$this->endGame($game['state'], $game['players'][1], 'timeout');
						return;
					}
					$game['state']['player1Time'] += $game['increment'];
				} else {
					$game['state']['player2Time'] -= $elapsedTime;
					if ($game['state']['player2Time'] <= 0) {
						$this->endGame($game['state'], $game['players'][0], 'timeout');
						return;
					}

					$game['state']['player2Time'] += $game['increment'];
				}

				// Update the last move time
				$game['state']['lastMoveTime'] = $currentTime;

				$col = $data['col'];
				$game['state']['move_sequence'][] = $col;

				for ($row = $game['state']['rows'] - 1; $row >= 0; $row--) {
					if (!$game['state']['board'][$row][$col]) {
						$currentcolor = $game['state']['currentPlayer'] == $game['starting'] ? 'red' : 'yellow';
						$game['state']['board'][$row][$col] = $currentcolor;

						if ($this->checkWin($game['state']['board'], $row, $col, $game['state']['win'], $currentcolor)) {
							$this->endGame($game['state'], $game['state']['currentPlayer'], 'normal');
							return;
						}

						if ($this->checkDraw($game['state']['board'])) {
							$this->endGame($game['state'], null, 'draw');
							return;
						}

						$game['state']['currentPlayer'] = $game['state']['currentPlayer'] === $game['players'][0] ? $game['players'][1] : $game['players'][0];
						$this->broadcastGameState($game['state']); // Broadcast the updated state
						break;
					}
				}

				break;
			case 'chat':
				$user = $this->checkToken($from, $data['token']);
				if (!$user) {
					return;
				}
				$gameId = $data['gameId'] ?? null;
				$message = $data['message'] ?? '';

				if (isset($this->games[$gameId])) {
					// Broadcast the chat message to both players
					$game = $this->games[$gameId];
					foreach ($game['players'] as $playerId) {
						$connection = $this->getConnectionByUserId($playerId);
						if ($connection) {
							$connection->send(json_encode([
								'type' => 'chat',
								'gameId' => $gameId,
								'fromUser' => $from->username,
								'message' => $message
							]));
						}
					}
				}
				break;
			case 'concede':
				$user =	$this->checkToken($from, $data['token']);

				if (!isset($this->games[$data['gameId']])) return;
				$game = &$this->games[$data['gameId']];
				if ($user['id'] != $game['players'][0] && $user['id'] != $game['players'][1]) {
					error_log("User is not authorized to concede.");
					return;
				}
				$this->endGame($game['state'], $from->userId == $game['players'][0] ? $game['players'][1] : $game['players'][0], 'concede');

				break;

			case 'draw-offer':
				$user =	$this->checkToken($from, $data['token']);
				if (!isset($this->games[$data['gameId']])) return;
				$game = &$this->games[$data['gameId']];
				$player_number = null;
				if ($user['id'] == $game['players'][0]) {
					$player_number = 0;
				} else if ($user['id'] == $game['players'][1]) {
					$player_number = 1;
				}
				if ($player_number === null) {
					error_log("User is not authorized to handle draw offers.");
					return;
				}
				if ($data['action'] == "offer") {
					$game['state']['draw_offers'][$player_number] = true;
				} else if ($data['action'] == "retire") {
					$game['state']['draw_offers'][$player_number] = false;
				}
				if ($game['state']['draw_offers'][0] && $game['state']['draw_offers'][1]) {
					$this->endGame($game['state'], null, "draw-accepted");
					return;
				}

				$connection = $this->getConnectionByUserId(($game['players'][0]));
				$connection->send(json_encode([
					'type' => 'draw-offer-status',
					'gameId' => $game['state']['id'],
					'draw_offered' => $game['state']['draw_offers'][0],
					'draw_offered_opponent' => $game['state']['draw_offers'][1]
				]));

				$connection = $this->getConnectionByUserId(($game['players'][1]));
				$connection->send(json_encode([
					'type' => 'draw-offer-status',
					'gameId' => $game['state']['id'],
					'draw_offered' => $game['state']['draw_offers'][1],
					'draw_offered_opponent' => $game['state']['draw_offers'][0]
				]));


				break;

			case 'request-replay':
				$gameData = $this->getGameData($data['gameId']);
				if ($gameData) {
					$from->send(json_encode($gameData));
				} else {
					$from->send(json_encode(['type' => 'error', 'message' => 'Invalid Game ID']));
				}
				break;

			default:
				error_log("Unknown message type: {$data['type']}\n");
				break;
		}
	}

	private function registerUser($conn, $username, $password)
	{
		try {
			$stmt = $this->db->prepare("INSERT INTO users (username, password_hash) VALUES (:username, :hash)");
			$stmt->bindValue(':username', $username, PDO::PARAM_STR);
			$stmt->bindValue(':hash', password_hash($password, PASSWORD_DEFAULT), PDO::PARAM_STR);
			$result = $stmt->execute();

			if ($result) {
				error_log("User registered successfully: $username\n");
				$conn->send(json_encode([
					'type' => 'register-success',
					'message' => 'Registration successful'
				]));
			}
		} catch (PDOException $e) {
			if ($e->getCode() == '23505') { // UNIQUE violation error code
				error_log("Failed to register user: $username (username already exists)\n");
				$conn->send(json_encode([
					'type' => 'register-failure',
					'message' => 'Username already exists'
				]));
			} else {
				error_log("Database error: " . $e->getMessage());
				$conn->send(json_encode([
					'type' => 'register-failure',
					'message' => 'Registration failed'
				]));
			}
		}
	}

	private function loginUser($conn, $username, $password)
	{
		$stmt = $this->db->prepare("SELECT id, password_hash FROM users WHERE username = :username");
		$stmt->bindValue(':username', $username, PDO::PARAM_STR);
		$stmt->execute();
		$user = $stmt->fetch(PDO::FETCH_ASSOC);

		if ($user && password_verify($password, $user['password_hash'])) {
			$token = bin2hex(random_bytes(32));
			$stmt = $this->db->prepare("UPDATE users SET token = :token WHERE id = :id");
			$stmt->bindValue(':token', $token, PDO::PARAM_STR);
			$stmt->bindValue(':id', $user['id'], PDO::PARAM_INT);
			$stmt->execute();

			header("Set-Cookie: auth_token=$token; Max-Age=" . (86400 * 30) . "; Path=/; HttpOnly; SameSite=Lax");

			$conn->userId = $user['id'];
			$conn->username = $username;

			$conn->send(json_encode([
				'type' => 'login-success',
				'userId' => $user['id'],
				'username' => $username
			]));
		} else {
			error_log("Failed to log in: $username\n");
			$conn->send(json_encode([
				'type' => 'login-failure',
				'message' => 'Invalid username or password'
			]));
		}
	}

	private function checkToken($conn, $token)
	{
		if (!$token) {
			error_log("No token received.");
			return;
		}
		$stmt = $this->db->prepare("SELECT id, username FROM users WHERE token = :token");
		$stmt->bindValue(':token', $token, PDO::PARAM_STR);
		$stmt->execute();
		$user = $stmt->fetch(PDO::FETCH_ASSOC);

		if ($user) {
			$conn->userId = $user['id'];
			$conn->username = $user['username'];
		}

		return $user;
	}

	private function tokenLogin($conn, $token)
	{
		if ($user = $this->checkToken($conn, $token)) {
			$conn->send(json_encode([
				'type' => 'token-login-success',
				'userId' => $user['id'],
				'username' => $user['username']
			]));
			error_log("User logged in via token: " . $user['username']);
		} else {
			error_log("Invalid token login attempt.");
			$conn->send(json_encode([
				'type' => 'login-failure',
				'message' => 'Invalid token'
			]));
		}
	}



	private function endGame($state, $winner_id, $end_type)
	{
		$this->broadcastGameState($state);

		$game = $this->games[$state['id']];

		$stmt = $this->db->prepare("INSERT INTO games (player1_id, player2_id, starting_player_id, winner_id, rows, cols, win, end_time, time, increment, end_type, move_sequence) VALUES (:player1_id, :player2_id, :starting_player_id, :winner_id, :rows, :cols, :win, CURRENT_TIMESTAMP, :time, :increment, :end_type, :move_sequence)");
		$stmt->bindValue(':player1_id', $game['players'][0], PDO::PARAM_INT); 	// Player 1 is always the challenge creator
		$stmt->bindValue(':player2_id', $game['players'][1], PDO::PARAM_INT);	// Player 2 is always the challenge joiner
		$stmt->bindValue(':starting_player_id', $game['starting'], PDO::PARAM_INT);
		$stmt->bindValue(':winner_id', $winner_id, PDO::PARAM_INT);
		$stmt->bindValue(':rows', $state['rows'], PDO::PARAM_INT);
		$stmt->bindValue(':cols', $state['cols'], PDO::PARAM_INT);
		$stmt->bindValue(':win', $state['win'], PDO::PARAM_INT);
		$stmt->bindValue(':time', $game['time'], PDO::PARAM_INT);
		$stmt->bindValue(':increment', $game['increment'], PDO::PARAM_INT);
		$stmt->bindValue(':end_type', $end_type, PDO::PARAM_STR);
		$stmt->bindValue(':move_sequence', implode(',', $game['state']['move_sequence']), PDO::PARAM_STR); // Move sequence is formatted as a comma separated string

		$stmt->execute();

		foreach ($game['players'] as $player) {
			$connection = $this->getConnectionByUserId($player);
			if ($winner_id === null) {
				$result = "draw";
			} else {
				$result = ($winner_id == $player) ? "win" : "loss";
			}
			if ($connection) {
				$connection->send(json_encode([
					'type' => 'game-end',
					'gameId' => $state['id'],
					'result' => $result,
					'end_type' => $end_type,
					'endId' => $this->db->lastInsertId()
				]));
			}
		}
		unset($this->games[$state['id']]);
	}

	function getUsernameByUserId($userId)
	{
		$stmt = $this->db->prepare("SELECT username FROM users WHERE id = :id");
		$stmt->bindValue(':id', $userId, PDO::PARAM_INT);

		$stmt->execute();

		if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			return $row['username'];
		}

		return null;
	}


	private function getConnectionByUserId($userId)
	{
		foreach ($this->clients as $client) {
			if (isset($client->userId) && $client->userId === $userId) {
				return $client;
			}
		}
		return null;
	}

	private function sendGameStart(ConnectionInterface $conn, $gameId)
	{
		if (!$conn) {
			error_log("Invalid connection passed to sendGameStart");
			return;
		}

		$conn->send(json_encode([
			'type' => 'game-start',
			'gameId' => $gameId
		]));
	}

	private function sendGameState(ConnectionInterface $player, $state)
	{
		if (!is_array($state)) {
			error_log("Invalid state passed to sendGameState");
			return;
		}
		$player->send(json_encode([
			'type' => 'game-state',
			'gameId' => $state['id'],
			'username' => ($this->games[$state['id']]['players'][0] == $player->userId) ? $this->getUsernameByUserId($this->games[$state['id']]['players'][0]) : $this->getUsernameByUserId($this->games[$state['id']]['players'][1]),
			'opponent_username' => ($this->games[$state['id']]['players'][0] == $player->userId) ? $this->getUsernameByUserId($this->games[$state['id']]['players'][1]) : $this->getUsernameByUserId($this->games[$state['id']]['players'][0]),
			'state' => [
				'rows' => $state['rows'],
				'cols' => $state['cols'],
				'win' => $state['win'],
				'board' => $state['board'],
				'currentPlayer' => ($state['currentPlayer'] == $player->userId) ? "your-turn" : "opponent-turn",
				'timeRemaining' => ($this->games[$state['id']]['players'][0] == $player->userId) ? $state['player1Time'] : $state['player2Time'],
				'timeRemainingOpponent' => ($this->games[$state['id']]['players'][0] != $player->userId) ? $state['player1Time'] : $state['player2Time']
			],
			'yourColor' => ($this->games[$state['id']]['starting'] == $player->userId) ? "red" : "yellow"
		]));
	}

	private function broadcastGameState($state)
	{
		if (!isset($this->games[$state['id']])) {
			error_log("Invalid game ID in broadcastGameState: " . $state['id']);
			return;
		}

		error_log("Broadcasting game state for game ID: " . $state['id']);

		foreach ($this->games[$state['id']]['players'] as $player) {
			$connection = $this->getConnectionByUserId(($player));
			if (!$connection) {
				error_log("Invalid player passed to sendGameState");
				return;
			}
			$color = ($player === $this->games[$state['id']]['players'][0]) ? 'red' : 'yellow';
			$this->sendGameState($connection, $state, $color);
		}
	}

	private function sendChallenges($client)
	{
		$challenges = array_values(array_map(function ($challenge) {
			$stmt = $this->db->prepare("SELECT username FROM users WHERE id = :id");
			$stmt->bindValue(':id', $challenge['creator'], PDO::PARAM_INT);
			$stmt->execute();
			$user = $stmt->fetch(PDO::FETCH_ASSOC);

			return [
				'id' => $challenge['id'],
				'creator_id' => $challenge['creator'],
				'rows' => $challenge['rows'],
				'cols' => $challenge['cols'],
				'win' => $challenge['win'],
				'username' => $user['username'],
				'time' => $challenge['time'],
				'increment' => $challenge['increment'],
				'starting' => $challenge['starting']
			];
		}, $this->challenges));

		$client->send(json_encode([
			'type' => 'challenges',
			'challenges' => $challenges
		]));
	}

	private function getGameData($gameId)
	{
		$stmt = $this->db->prepare("
        SELECT 
            id, 
            player1_id, 
            player2_id, 
            starting_player_id, 
            winner_id,
			rows,
			cols,
			win,
            end_type, 
            end_time, 
            time, 
            increment, 
            move_sequence 
        FROM games 
        WHERE id = :gameId
    ");

		$stmt->bindValue(':gameId', $gameId, PDO::PARAM_INT);
		$stmt->execute();

		$gameData = $stmt->fetch(PDO::FETCH_ASSOC);

		if ($gameData) {
			// Optionally, you can fetch usernames for player1, player2, starting_player, and winner
			$userStmt = $this->db->prepare("SELECT username FROM users WHERE id = :userId");

			$fetchUsername = function ($userId) use ($userStmt) {
				$userStmt->bindValue(':userId', $userId, PDO::PARAM_INT);
				$userStmt->execute();
				$user = $userStmt->fetch(PDO::FETCH_ASSOC);
				return $user ? $user['username'] : null;
			};

			$gameData['player1_username'] = $fetchUsername($gameData['player1_id']);
			$gameData['player2_username'] = $fetchUsername($gameData['player2_id']);
			$gameData['starting_player_username'] = $fetchUsername($gameData['starting_player_id']);
			$gameData['winner_username'] = $gameData['winner_id'] ? $fetchUsername($gameData['winner_id']) : null;
		}

		return $gameData;
	}

	private function broadcastChallenges()
	{
		foreach ($this->clients as $client) {
			$this->sendChallenges($client);
		}
	}

	public function onClose(ConnectionInterface $conn)
	{
		$this->clients->detach($conn);
		if (isset($this->loggedInUsers[$conn->resourceId])) {
			unset($this->loggedInUsers[$conn->resourceId]);
		}
	}
	private function checkWin($board, $row, $col, $win, $currentcolor)
	{
		$directions = [
			[[-1, 0], [1, 0]],  // Vertical
			[[0, -1], [0, 1]],  // Horizontal
			[[-1, -1], [1, 1]], // Diagonal (top-left to bottom-right)
			[[-1, 1], [1, -1]]  // Diagonal (bottom-left to top-right)
		];

		foreach ($directions as $dir) {
			$count = 1;
			foreach ($dir as $d) {
				$r = $row + $d[0];
				$c = $col + $d[1];
				while ($r >= 0 && $r < count($board) && $c >= 0 && $c < count($board[0]) && $board[$r][$c] === $currentcolor) {
					$count++;
					$r += $d[0];
					$c += $d[1];
				}
			}
			if ($count >= $win) {
				return true;
			}
		}
		return false;
	}

	private function checkDraw($board)
	{
		foreach ($board as $row) {
			if (in_array(null, $row, true)) {
				return false;
			}
		}
		return true;
	}

	public function onError(ConnectionInterface $conn, \Exception $e)
	{
		$conn->close();
	}
}

$server = IoServer::factory(
	new HttpServer(
		new WsServer(
			new GameServer()
		)
	),
	8081
);

$server->run();
