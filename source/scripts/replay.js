const ws = new WebSocket(`ws://${window.location.hostname}:8081`);
const urlParams = new URLSearchParams(window.location.search);
const gameId = urlParams.get('gameId');

let gameData = null;
let currentMoveIndex = 0;
let previousState = null;

ws.onopen = function () {
    console.log('WebSocket connection established');
    if (gameId) {
        ws.send(JSON.stringify({
            type: 'request-replay',
            gameId: gameId,
        }));
    }
};

ws.onmessage = function (event) {
    const data = JSON.parse(event.data);
    console.log('Received message:', data);

    if (data.type === "error") {
        console.error("Invalid Game ID. Redirecting...");
        window.location.href = "index.html";
    } else {
        gameData = data;
        currentMoveIndex = 0;
        document.getElementById("time-label").textContent = gameData.starting_player_username;
        document.getElementById("time-label-opponent").textContent = (gameData.starting_player_username == gameData.player1_username) ? gameData.player2_username : gameData.player1_username;


        const initialState = generateBoardState(gameData.move_sequence, currentMoveIndex);
        updateGame(initialState);
    }
};

function generateBoardState(moveSequence, moveIndex) {
    const rows = gameData.rows;
    const cols = gameData.cols;
    const board = Array.from({ length: rows }, () => Array(cols).fill(null));

    const moves = moveSequence.split(',').map(move => parseInt(move.trim(), 10));

    for (let i = 0; i <= moveIndex; i++) {
        const column = moves[i];
        if (column >= 0 && column < cols) {
            for (let row = rows - 1; row >= 0; row--) {
                if (board[row][column] === null) {
                    board[row][column] = i % 2 === 0 ? 'red' : 'yellow';
                    break;
                }
            }
        }
    }
	console.log(moveSequence);
	console.log(board);

    return { rows, cols, board };
}

function updateGame(state, nextMove) {
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
            board.appendChild(cell);
        }
    }
    container.appendChild(board);

    // Animate the last move
    if (lastMove && nextMove) {
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

    // Display end game message if it's the last move
    if (currentMoveIndex === gameData.move_sequence.split(',').length - 1) {
        let text_result;
        switch (gameData.end_type) {
            case 'normal':
                if (gameData.result === 'draw') {
                    text_result = 'The game ended in a draw';
                } else {
                    text_result = gameData.winner_username + " has won";
                }
                break;

            case 'draw':
            case 'draw-accepted':
                text_result = 'The game ended in a draw';
                break;

            case 'timeout':
                if (gameData.player1_username === gameData.winner_username) {
                    text_result = gameData.player2_username + " timed out, " + gameData.player1_username + " has won";
                } else {
                    text_result = gameData.player1_username + " timed out, " + gameData.player2_username + " has won";
                }
                break;

            case 'concede':
                if (gameData.player1_username === gameData.winner_username) {
                    text_result = gameData.player2_username + " conceded, " + gameData.player1_username + " has won";
                } else {
                    text_result = gameData.player1_username + " conceded, " + gameData.player2_username + " has won";
                }
                break;

            default:
                console.error('Game-End error: Invalid end-type received');
                return;
        }

        document.getElementById('status').textContent = text_result;
    } else {
        document.getElementById('status').textContent = `Move ${currentMoveIndex + 1} of ${gameData.move_sequence.split(',').length}`;
    }
}

function previousMove() {
    if (currentMoveIndex > 0) {
        currentMoveIndex--;
        const newState = generateBoardState(gameData.move_sequence, currentMoveIndex);
        updateGame(newState, false);
    }
}

function nextMove() {
    const moves = gameData.move_sequence.split(',').length;
    if (currentMoveIndex < moves - 1) {
        currentMoveIndex++;
        const newState = generateBoardState(gameData.move_sequence, currentMoveIndex);
        updateGame(newState, true);
    }
}