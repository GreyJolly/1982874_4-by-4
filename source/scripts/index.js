const ws = new WebSocket(`ws://${window.location.hostname}:8081`);
let currentUser = null;
let currentUserId = null;

document.addEventListener('DOMContentLoaded', function () {

	document.getElementById('logout-button').onclick = function () {
		fetch('/php/login.php', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ action: 'logout' }),
			credentials: 'include'
		})
			.then(response => response.json())
			.then(data => {
				if (data.success) {
					currentUser = null;
					const loggedInUserElement = document.getElementById('logged-in-user');
					const logoutButton = document.getElementById('logout-button');
					const loginButton = document.getElementById('login-button');
					const registerButton = document.getElementById('register-button');

					loggedInUserElement.style.display = 'none';
					logoutButton.style.display = 'none';
					loginButton.style.display = 'inline-block';
					registerButton.style.display = 'inline-block';
					
					ws.send(JSON.stringify({ type: 'query-challenges' }));
					alert('Logout successful!');
					document.getElementById('logout-button').style.display = 'none';
				} else {
					alert('Logout failed: ' + data.error);
				}
			})
			.catch(err => console.error("Logout failed", err));
	};


	document.getElementById('create-form').onsubmit = function (e) {
		e.preventDefault();
		const challenge = {
			rows: parseInt(document.getElementById('rows').value),
			cols: parseInt(document.getElementById('cols').value),
			win: parseInt(document.getElementById('win').value),
			time: parseInt(document.getElementById('initial-hours').value) * 3600 + parseInt(document.getElementById('initial-minutes').value) * 60 + parseInt(document.getElementById('initial-seconds').value),
			increment: parseInt(document.getElementById('increment-minutes').value) * 60 + parseInt(document.getElementById('increment-seconds').value),
			starting: document.querySelector('input[name="starting-player"]:checked').value
		};
		ws.send(JSON.stringify({ type: 'create-challenge', ...challenge }));
	};

});

ws.onopen = function () {
	console.log('WebSocket connection established');
	ws.send(JSON.stringify({ type: 'query-challenges' }));
	tokenLogin();
};

function tokenLogin() {
	fetch('/php/check-token.php', { credentials: 'include' })
		.then(response => response.json())
		.then(data => {
			if (data.authenticated) {
				console.log("Token authentication successful!");
				currentUser = data.username;
				currentUserId = data.userId;

				const loggedInUserElement = document.getElementById('logged-in-user');
				const logoutButton = document.getElementById('logout-button');
				const loginButton = document.getElementById('login-button');
				const registerButton = document.getElementById('register-button');
			
				if (currentUser) {
					
					loggedInUserElement.style.display = 'inline-block';
					logoutButton.style.display = 'inline-block';
					loginButton.style.display = 'none';
					registerButton.style.display = 'none';
				} else {
					loggedInUserElement.style.display = 'none';
					logoutButton.style.display = 'none';
					loginButton.style.display = 'inline-block';
					registerButton.style.display = 'inline-block';
				}

				ws.send(JSON.stringify({ type: 'token-login', token: data.token }));
			} else {
				console.log("No valid token found.");
			}
		})
		.catch(err => console.error("Token check failed", err));
};

ws.onerror = function (error) {
	console.error('WebSocket error:', error);
};

ws.onclose = function () {
	console.log('WebSocket connection closed');
};

ws.onmessage = function (event) {
	let data;
	try {
		data = JSON.parse(event.data);
	} catch (error) {
		console.error("Error parsing JSON:", error, "Received data:", event.data);
		return;
	}
	console.log('Received message:', data);

	if (data.type === 'token-login-success') {
		currentUser = data.username;
		document.getElementById('create-challenge').style.display = 'block';
		document.getElementById('logout-button').style.display = 'block';
		ws.send(JSON.stringify({ type: 'query-challenges' }));
	} else if (data.type === 'login-failure') {
		alert('Login failed: ' + data.message);
	} else if (data.type === 'register-success') {
		alert('Registration successful!');
	} else if (data.type === 'register-failure') {
		alert('Registration failed: ' + data.message);
	} else if (data.type === 'join-failure') {
		alert('Joining failed: ' + data.message);
	} else if (data.type === 'challenges') {
		updateChallenges(data.challenges);
	} else if (data.type === 'game-start') {
		window.location.href = `game.html?gameId=${data.gameId}`;
	} else if (data.type === 'challenge-created') {
		//
	} else if (data.type === 'challenge-retired') {
		//
	} else if (data.type === 'error') {
		alert('Error: ' + data.message);
	}
};

function updateChallenges(challenges) {
    const noChallengesMessage = document.getElementById('no-challenges-message');
    const challengesBody = document.getElementById('challenges-body');
    const challengesTable = document.getElementById('challenges-table');
    const createChallengeSection = document.getElementById('create-challenge');
    const createButton = document.getElementById('create-challenge-button');
    const retireButton = document.getElementById('retire-challenge-button');

    challengesBody.innerHTML = '';
    const isLoggedIn = currentUser !== null;
    const hasChallenges = challenges.length > 0;
    if (isLoggedIn) {
        if (hasChallenges) {
            noChallengesMessage.style.display = 'none';
            challengesTable.style.display = 'table'; 
            createChallengeSection.style.display = 'block'; 
        } else {
            noChallengesMessage.textContent = "No active challenges";
            noChallengesMessage.style.display = 'block';
            challengesTable.style.display = 'none';
            createChallengeSection.style.display = 'block';
        }
    } else {
        if (hasChallenges) {
            noChallengesMessage.textContent = "You need to log in in order to join the challenge"; 
            noChallengesMessage.style.display = 'block';
            challengesTable.style.display = 'table';
            createChallengeSection.style.display = 'none';
        } else {
            noChallengesMessage.textContent = "No active challenges";
            noChallengesMessage.style.display = 'block';
            challengesTable.style.display = 'none';
            createChallengeSection.style.display = 'none';
        }
    }

    challenges.forEach(challenge => {
        const row = document.createElement('tr');

        const usernameCell = document.createElement('td');
        const profileLink = document.createElement('a');
        profileLink.href = `profile.html?id=` + challenge.creator_id;
        profileLink.textContent = challenge.username;
        usernameCell.appendChild(profileLink);
        row.appendChild(usernameCell);

        const boardSizeCell = document.createElement('td');
        boardSizeCell.textContent = `${challenge.rows}x${challenge.cols}`;
        row.appendChild(boardSizeCell);

        const winConditionCell = document.createElement('td');
        winConditionCell.textContent = challenge.win;
        row.appendChild(winConditionCell);

        const timeControlCell = document.createElement('td');
        timeControlCell.textContent = `${formatTime(challenge.time)} | ${formatTime(challenge.increment)}`;
        row.appendChild(timeControlCell);

        const startingPlayerCell = document.createElement('td');
        startingPlayerCell.textContent = challenge.starting;
        row.appendChild(startingPlayerCell);

        const actionsCell = document.createElement('td');
        const joinButton = document.createElement('button');
        joinButton.innerHTML = 'Join Challenge';
        joinButton.className = 'action-button join-challenge-button';
        joinButton.onclick = () => joinChallenge(challenge.id);
        actionsCell.appendChild(joinButton);
        row.appendChild(actionsCell);

        challengesBody.appendChild(row);
    });

    const userHasChallenge = challenges.some(challenge => challenge.username === currentUser);

    if (isLoggedIn) {
        retireButton.style.display = userHasChallenge ? 'block' : 'none';
        createButton.style.display = userHasChallenge ? 'none' : 'block';
    } else {
        retireButton.style.display = 'none'; 
        createButton.style.display = 'none';
    }
}

function viewProfile() {
	if (currentUserId == null) return;
	window.location.href = "/profile.html?id=" + currentUserId;
}

function joinChallenge(id) {
	ws.send(JSON.stringify({ type: 'join', id }));
}

function retireChallenge() {
	event.preventDefault(); 
	ws.send(JSON.stringify({ type: 'retire' }));
}

function formatTime(seconds) {
	const hours = Math.floor(seconds / 3600);
	const minutes = Math.floor((seconds % 3600) / 60);
	const secs = seconds % 60;
	return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
}