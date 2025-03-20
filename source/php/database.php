<?php

class Database
{
	private static $instance = null;
	private $db;

	private function __construct()
	{
		$env = parse_ini_file(__DIR__ . '/../.env');
		
		$this->db = new PDO('pgsql:host=db;dbname=' . $env["POSTGRES_DB"], $env["POSTGRES_USER"], $env["POSTGRES_PASSWORD"]);
		$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$this->initializeTables();
	}

	public static function getInstance()
	{
		if (self::$instance === null) {
			self::$instance = new Database();
		}
		return self::$instance;
	}

	public function getDb()
	{
		return $this->db;
	}

	private function initializeTables()
	{

		// Create users table
		$this->db->exec("CREATE TABLE IF NOT EXISTS users (
            id SERIAL PRIMARY KEY,
            username TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            token TEXT
        )");

		// Create games table
		$this->db->exec("CREATE TABLE IF NOT EXISTS games (
            id SERIAL PRIMARY KEY,
            player1_id INTEGER NOT NULL,
            player2_id INTEGER NOT NULL,
            starting_player_id INTEGER NOT NULL,
            winner_id INTEGER,
            rows INTEGER NOT NULL,
            cols INTEGER NOT NULL,
            win INTEGER NOT NULL,
            end_type TEXT NOT NULL,
            end_time TIMESTAMP,
            time INTEGER,
            increment INTEGER,
            move_sequence TEXT,
            FOREIGN KEY(player1_id) REFERENCES users(id),
            FOREIGN KEY(player2_id) REFERENCES users(id),
            FOREIGN KEY(starting_player_id) REFERENCES users(id),
            FOREIGN KEY(winner_id) REFERENCES users(id)
        )");

		// Create challenges table
		$this->db->exec("CREATE TABLE IF NOT EXISTS challenges (
            id TEXT PRIMARY KEY,
            user_id INTEGER NOT NULL,
            rows INTEGER NOT NULL,
            cols INTEGER NOT NULL,
            win INTEGER NOT NULL,
            time INTEGER NOT NULL,
            increment INTEGER NOT NULL,
            FOREIGN KEY(user_id) REFERENCES users(id)
        )");
	}
}
