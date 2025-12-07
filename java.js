async function login(nameOrEmail, password) {
  try {
    const response = await fetch('login.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ name: nameOrEmail, password: password })
    });

    const data = await response.json();

    if (data.success) {
      alert(data.message);
      // Optionally, store user info in localStorage/sessionStorage
      sessionStorage.setItem('user_name', data.name);
      // Redirect to dashboard or another page
      window.location.href = 'dashboard.html';
    } else {
      alert(data.message);
    }
  } catch (error) {
    console.error('Error:', error);
    alert('Something went wrong. Please try again later.');
  }
}

// Example usage
document.getElementById('loginForm').addEventListener('submit', function(e) {
  e.preventDefault();
  const nameOrEmail = document.getElementById('nameOrEmail').value;
  const password = document.getElementById('password').value;
  login(nameOrEmail, password);
});
