const ws = new WebSocket(`ws://${window.location.hostname}:8081`);
const urlParams = new URLSearchParams(window.location.search);
const gameId = urlParams.get('gameId');
let myColor;
let currentPlayer;
let token;
let previousState = null;
let timeRemaining = 0;
let timeRemainingOpponent = 0;
let countdownInterval = null;
let draw_offered = false;
let draw_offered_opponent = false;
let chatInput;
let chatMessages;
let chatSend;

document.addEventListener("DOMContentLoaded", function () {

	chatMessages = document.getElementById('chat-messages');
	chatInput = document.getElementById('chat-input');
	chatSend = document.getElementById('chat-send');

	chatSend.onclick = sendChatMessage;

	chatInput.addEventListener('keydown', (event) => {
		if (event.key === 'Enter' && !event.shiftKey) {
			event.preventDefault();
			sendChatMessage();
		}
	});

});

// Format time in seconds in hh:mm:ss
function formatTime(seconds) {
	const hours = Math.floor(seconds / 3600);
	const minutes = Math.floor((seconds % 3600) / 60);
	const secs = seconds % 60;
	return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
}

// Function to update the timer display
function updateTimerDisplay() {
	document.getElementById('timer').textContent = formatTime(timeRemaining);
	document.getElementById('timer-opponent').textContent = formatTime(timeRemainingOpponent);
}

function startCountdowns() {
	if (countdownInterval) clearInterval(countdownInterval);

	countdownInterval = setInterval(() => {
		if (currentPlayer === "your-turn") {
			timeRemaining--;
		} else {
			timeRemainingOpponent--;
		}

		if (timeRemaining <= 0 || timeRemainingOpponent <= 0) {
			clearInterval(countdownInterval);
			stopCountdown();
			requestState();
		}
		updateTimerDisplay();
	}, 1000);
}

function stopCountdown() {
	if (countdownInterval) {
		clearInterval(countdownInterval);
		countdownInterval = null;
	}
}

function requestState() {
	if (gameId) {
		ws.send(JSON.stringify({
			type: 'request-state',
			gameId: gameId,
			token: token
		}));
	}
}

ws.onopen = function () {
	console.log('WebSocket connection established');
	fetch('/php/check-token.php', { credentials: 'include' })
		.then(response => response.json())
		.then(data => {
			if (data.authenticated) {
				token = data.token;
				ws.send(JSON.stringify({ type: 'token-login', token: data.token }));
				requestState();
			} else {
				console.log("No valid token found.");
			}
		})
		.catch(err => console.error("Token check failed", err));
};

ws.onmessage = function (event) {
	const data = JSON.parse(event.data);
	console.log('Received message:', data);

	if (data.type == "error") {
		console.error("Failed to view game: " + data.message);
		window.location.href = "index.html";
	}

	if (data.gameId !== gameId && data.type != "token-login-success") {
		console.error("Received data for the wrong game");
	}

	// Handle chat messages
	if (data.type === 'chat') {
		const entry = document.createElement('div');
		entry.textContent = data.fromUser + ': ' + data.message;
		chatMessages.appendChild(entry);
		chatMessages.scrollTop = chatMessages.scrollHeight;
	} else

		// Handle game state updates
		if (data.type === 'game-state' && data.gameId === gameId) {
			timeRemaining = data.state.timeRemaining;
			timeRemainingOpponent = data.state.timeRemainingOpponent;

			if (data.yourColor == "yellow") {
				document.getElementById("timer-box").classList.remove("red-time");
				document.getElementById("timer-box").classList.add("yellow-time");
				document.getElementById("timer-box-opponent").classList.remove("yellow-time");
				document.getElementById("timer-box-opponent").classList.add("red-time");
			} else {
				document.getElementById("timer-box").classList.remove("yellow-time");
				document.getElementById("timer-box").classList.add("red-time");
				document.getElementById("timer-box-opponent").classList.remove("red-time");
				document.getElementById("timer-box-opponent").classList.add("yellow-time");
			}
			document.getElementById("time-label").textContent = data.username;
			document.getElementById("time-label-opponent").textContent = data.opponent_username;

			updateGame(data.state);
			startCountdowns();
		} else if (data.type === 'game-end' && data.gameId === gameId) {
			stopCountdown();

			let text_result;
			switch (data.end_type) {
				case 'normal':
					if (data.result === 'draw') {
						text_result = 'The game ended in a draw!';
					} else {
						text_result = (data.result === 'win') ? "You have won" : "You have lost";
					}
					break;

				case 'draw':
				case 'draw-accepted':
					text_result = 'The game ended in a draw!';
					break;

				case 'timeout':
					text_result = (data.result === 'win') ? "Opponent timed out, you have won" : "You timed out, you have lost";
					break;

				case 'concede':
					text_result = (data.result === 'win') ? "Opponent conceded, you have won" : "You have conceded";
					break;

				default:
					console.error('Game-End error: Invalid end-type received');
					return;
			}

			document.getElementById('status').textContent = text_result;

			document.getElementById('replay-button').addEventListener('click', function () {
				window.location.href = "/replay.html?gameId=" + data.endId;
			});

			document.querySelectorAll('.cell').forEach(cell => cell.onclick = null);
			document.getElementById("concede-button").style.display = "none";
			document.getElementById("draw-offer-button").style.display = "none";
			document.getElementById("exit-button").style.display = "block";
			document.getElementById("replay-button").style.display = "block";
			document.getElementById("chat-container").style.display = "none";
		} else if (data.type === "draw-offer-status") {
			if (data.draw_offered) {
				draw_offered = true;
				document.getElementById("draw-offer-button").innerHTML = 'Retire draw offer &nbsp;<i class="fa-solid fa-handshake-slash"></i>';
			} else {
				draw_offered = false
				document.getElementById("draw-offer-button").innerHTML = 'Offer draw &nbsp;<i class="fa-solid fa-handshake"></i>';
			}
			if (data.draw_offered_opponent) {
				draw_offered_opponent = true;
				document.getElementById("draw-offer-button").innerHTML = 'Accept draw offer &nbsp;<i class="fa-solid fa-handshake"></i>';
			} else {
				draw_offered_opponent = false
			}
		}
};

function updateGame(state) {
	currentPlayer = state.currentPlayer;
	timeRemaining = state.timeRemaining;
	timeRemainingOpponent = state.timeRemainingOpponent;
	updateTimerDisplay();

	const container = document.getElementById('game-container');
	container.innerHTML = '';

	const board = document.createElement('div');
	board.className = 'board';
	board.style.gridTemplateColumns = `repeat(${state.cols}, 1fr)`;

	let lastMove = null;
	if (previousState) {
		for (let row = 0; row < state.rows; row++) {
			for (let col = 0; col < state.cols; col++) {
				if (state.board[row][col] !== previousState.board[row][col]) {
					lastMove = { row, col };
					break;
				}
			}
			if (lastMove) break;
		}
	}

	for (let row = 0; row < state.rows; row++) {
		for (let col = 0; col < state.cols; col++) {
			const cell = document.createElement('div');
			cell.className = 'cell';
			if (state.board[row][col]) {
				cell.classList.add(state.board[row][col]);
			}
			cell.dataset.row = row;
			cell.dataset.col = col;
			cell.onclick = () => makeMove(col);
			board.appendChild(cell);
		}
	}
	container.appendChild(board);

	// Animate the last move
	if (lastMove) {
		requestAnimationFrame(() => {
			const lastMoveElement = container.querySelector(
				`.cell[data-row='${lastMove.row}'][data-col='${lastMove.col}']`
			);
			if (lastMoveElement) {
				const rowHeight = 50;
				const fallDistance = (lastMove.row + 1) * rowHeight;
				lastMoveElement.style.transform = `translateY(-${fallDistance}px)`;
				lastMoveElement.style.transition = 'transform 0.5s ease-in-out';

				requestAnimationFrame(() => {
					lastMoveElement.style.transform = 'translateY(0)';
				});
			} else {
				console.error('Last move element not found:', lastMove);
			}
		});
	}
	previousState = JSON.parse(JSON.stringify(state));
	document.getElementById('status').textContent =
		(currentPlayer === "your-turn") ? 'Your turn!' : 'Waiting for opponent\'s move...';
}

function makeMove(col) {
	if (currentPlayer === "your-turn") {
		ws.send(JSON.stringify({
			type: 'move',
			gameId: gameId,
			token: token,
			col
		}));
	}
}

// Send a chat message
function sendChatMessage() {
	if (!token || !gameId) return;
	const message = chatInput.value.trim();
	if (message) {
		ws.send(JSON.stringify({
			type: 'chat',
			gameId: gameId,
			token: token,
			message: message
		}));
		chatInput.value = '';
	}
}


function concede() {
	if (!confirm("Are you sure you want to concede?")) return;
	ws.send(JSON.stringify({
		type: 'concede',
		gameId: gameId,
		token: token
	}));
}

function offerDraw() {
	if (!draw_offered) {
		if (!draw_offered_opponent) {
			if (!confirm("Are you sure you want to offer a draw?")) return;
		} else {
			if (!confirm("Are you sure you want to accept your opponent's draw offer?")) return;
		}
		ws.send(JSON.stringify({
			type: 'draw-offer',
			action: 'offer',
			gameId: gameId,
			token: token
		}));
	} else {
		ws.send(JSON.stringify({
			type: 'draw-offer',
			action: 'retire',
			gameId: gameId,
			token: token
		}));
	}
}