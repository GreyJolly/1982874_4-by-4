const ws = new WebSocket('ws://localhost:8081');

document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('register-form').onsubmit = function (e) {
        e.preventDefault();
        const username = document.getElementById('register-username').value;
        const password = document.getElementById('register-password').value;
        console.log('Sending register request:', { username, password });

        ws.send(JSON.stringify({
            type: 'register',
            username: username,
            password: password
        }));
    };

    document.getElementById('eyeIcon1').addEventListener('click', function () {
        var password = document.getElementById('register-password');
        var eyeIcon1 = document.getElementById('eyeIcon1');
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

    document.getElementById('register-form').addEventListener('submit', function (event) {
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

ws.onopen = function () {
    console.log('WebSocket connection established');
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

    if (data.type === 'register-success') {
        alert('Registration successful!');
        loginUser();
    } else if (data.type === 'register-failure') {
        alert('Registration failed: ' + data.message);
    }
};

async function loginUser() {
    const username = document.getElementById('register-username').value;
    const password = document.getElementById('register-password').value;

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
}