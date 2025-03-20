const ws = new WebSocket('ws://localhost:8081');

document.addEventListener('DOMContentLoaded', function () {
	document.getElementById('login-form').onsubmit = async function (e) {
		e.preventDefault();
		const username = document.getElementById('login-username').value;
		const password = document.getElementById('login-password').value;

		// Upon login, fetch the cookie from login.php
		const response = await fetch('/php/login.php', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ username, password }),
			credentials: 'include'
		});

		const data = await response.json();
		if (data.success) {
			tokenLogin();
		} else {
			alert('Login failed: ' + data.error);
		}
	};

    document.getElementById('eyeIcon2').addEventListener('click', function () {
        var password = document.getElementById('login-password');
        var eyeIcon1 = document.getElementById('eyeIcon2');
        if (password.type === 'password') {
            password.type = 'text';
            eyeIcon1.classList.remove('fa-eye');
            eyeIcon1.classList.add('fa-eye-slash');
        } else {
            password.type = 'password';
            eyeIcon1.classList.remove('fa-eye-slash');
            eyeIcon1.classList.add('fa-eye');
        }
    });

    document.getElementById('login-form').addEventListener('submit', function (event) {
        event.preventDefault();
    });
});

function tokenLogin() {
	fetch('/php/check-token.php', { credentials: 'include' })
		.then(response => response.json())
		.then(data => {
			if (data.authenticated) {
				console.log("Token authentication successful!");
				ws.send(JSON.stringify({ type: 'token-login', token: data.token }));
				window.location.href = 'index.html';
			} else {
				console.log("No valid token found.");
			}
		})
		.catch(err => console.error("Token check failed", err));
};