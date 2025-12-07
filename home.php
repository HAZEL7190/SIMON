<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    // Not logged in, redirect to login page
    header("Location: login.html");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scholarship Eligibility Checker</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <!-- Hero Banner Section -->
    <div class="hero-banner">
        <div class="hero-content">
            <h1>Welcome to the Scholarship Eligibility Checker</h1>
            <p>
                Find out which scholarships you are eligible for. We make the process
                of discovering your options quick and easy.
            </p>
        </div>
        <div class="hero-image-container">
            <img src="img/WhatsApp Image 2025-10-27 at 18.20.46_4b028448.jpg" 
                 alt="A person studying, symbolizing scholarships." 
                 class="hero-image">
            </img>
        </div>
    </div>

    <!-- Action Cards and Buttons Section -->
    <div class="action-section">
        <div class="action-card">
            <div class="card-content">
                <h2>View Scholarships</h2>
               
            </div>
            <p>Explore our database of successful scholars and get inspired by their achievements.</p>
            <a href="vsch.html" class="cta-button">View Scholarship</a>
        </div>

        <div class="action-card">
            <div class="card-content">
                <h2>Check Scholarship Eligibility</h2>
                 <p>Quickly determine which scholarships you may qualify for based on your details.</p>
            </div>
            <a href="eligibility.html" class="cta-button secondary-button">Check Eligibility</a>
        </div>

        <div class="action-card">
            <div class="card-content">
                <h2>application</h2>
                <p>Learn more about the purpose and mission behind the Scholarship Eligibility Checker project.</p>
            </div>
            <a href="app.php" class="cta-button about-button">Apply Scholarship</a>
        </div>

    </div>

    <div class="welcome-logout">
        <h2>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></h2>
        <a href="logout.php" class="logout-button">Logout</a>
    </div>

    <style>
        .welcome-logout {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin: 40px 0;
            gap: 12px;
        }
        .welcome-logout h2 {
            margin: 0;
            font-size: 1.4rem;
            color: #222;
            text-transform: capitalize;
        }
        .logout-button {
            display: inline-block;
            padding: 10px 18px;
            background: #155ab6;
            color: #fff;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
        }
        .logout-button:hover { opacity: 0.95; }
    </style>
</body>
</html>
