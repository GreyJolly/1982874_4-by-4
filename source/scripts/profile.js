const urlParams = new URLSearchParams(window.location.search);
const profileId = urlParams.get('id');


document.addEventListener("DOMContentLoaded", function () {
	fetch(`/php/profile.php?id=${profileId}`)
		.then(response => response.json())
		.then(data => {

			document.getElementById('username').textContent = data.username;
			document.getElementById('winrate').textContent = data.win_rate + '%';
			document.getElementById('games-played').textContent = data.total_games;

			const pastGamesBody = document.getElementById('past-games-body');

			data.past_games.forEach(game => {
				const row = document.createElement('tr');

				// Player 1
				const player1Cell = document.createElement('td');
				const profile1Link = document.createElement('a');
				profile1Link.href = `profile.html?id=` + game.player1_id;
				profile1Link.textContent = game.player1_username;
				player1Cell.appendChild(profile1Link);
				row.appendChild(player1Cell);


				// Player 2
				const player2Cell = document.createElement('td');
				const profile2Link = document.createElement('a');
				profile2Link.href = `profile.html?id=` + game.player2_id;
				profile2Link.textContent = game.player2_username;
				player2Cell.appendChild(profile2Link);
				row.appendChild(player2Cell);

				// Dimension (rows x cols)
				const dimensionCell = document.createElement('td');
				dimensionCell.textContent = `${game.rows}x${game.cols}`;
				row.appendChild(dimensionCell);

				// Timing (convert seconds to minutes + increment)
				const timeMinutes = Math.floor(game.time / 60);
				const timingText = `${timeMinutes} min + ${game.increment} sec`;
				const timingCell = document.createElement('td');
				timingCell.textContent = timingText;
				row.appendChild(timingCell);

				// Result (Winner or Draw)
				const resultCell = document.createElement('td');
				resultCell.textContent = game.winner_username ? game.winner_username : 'Draw';
				row.appendChild(resultCell);

				// Action Buttons
				const actionCell = document.createElement('td');

				// View Replay Button
				const viewReplayButton = document.createElement('button');
				viewReplayButton.innerHTML = 'View Replay &nbsp;<i class="fa-solid fa-tv"></i>';
				viewReplayButton.className = 'action-button';
				viewReplayButton.onclick = () => {
					window.location.href = `/replay.html?gameId=${game.id}`;
				};
				actionCell.appendChild(viewReplayButton);
				row.appendChild(actionCell);
				pastGamesBody.append(row);
			});

			if (data.is_own_profile) {
				document.getElementById('settings-button').style.display = 'block';
			} else {
				document.getElementById('settings-button').style.display = 'none';
			}
		})
		.catch(err => { console.error("Failed to view profile", err); window.location.href = "index.html" });


	document.getElementById("back-button").addEventListener("click", function () {
		window.location.href = "index.html";
	});

	const settingsButton = document.getElementById('settings-button');

	settingsButton.addEventListener('click', function (event) {
		event.stopPropagation();
		editProfileForm.style.display = editProfileForm.style.display === 'none' ? 'block' : 'none';
	});

	const editProfileForm = document.getElementById('edit-profile-form');
	const updateProfileForm = document.getElementById('update-profile-form');

	updateProfileForm.addEventListener('submit', function (event) {
		event.preventDefault();

		const currentPassword = document.getElementById('current-password').value;
		const newUsername = document.getElementById('new-username').value;
		const newPassword = document.getElementById('new-password').value;
		const confirmPassword = document.getElementById('confirm-password').value;

		if (newPassword !== confirmPassword) {
			alert('Passwords do not match');
			return;
		}

		fetch('/php/update_profile.php', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json'
			},
			credentials: 'include',
			body: JSON.stringify({
				currentPassword: currentPassword,
				newUsername: newUsername,
				newPassword: newPassword
			})
		})
			.then(response => response.json())
			.then(data => {
				if (data.success) {
					alert('Profile updated successfully');
					window.location.reload();
				} else {
					alert('Failed to update profile: ' + data.error);
				}
			})
			.catch(error => {
				console.error('Error:', error);
			});
	});
});

