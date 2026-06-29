<?php
// public/index.php - Welcome page for NIT USSD System
// No authentication required – purely informational
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NIT USSD Information System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for icons (optional but nice) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .hero {
            background: linear-gradient(135deg, #1a73e8 0%, #0d47a1 100%);
            color: white;
            padding: 80px 0;
            text-align: center;
        }
        .feature-card {
            transition: transform 0.3s;
            margin-bottom: 20px;
        }
        .feature-card:hover {
            transform: translateY(-5px);
        }
        .btn-login {
            background-color: #ffc107;
            color: #000;
            font-weight: bold;
            padding: 12px 30px;
            border-radius: 50px;
        }
        .btn-login:hover {
            background-color: #e0a800;
            color: #000;
        }
        footer {
            background-color: #f8f9fa;
            padding: 20px 0;
            margin-top: 50px;
            text-align: center;
        }
    </style>
</head>
<body>

<!-- Hero Section -->
<div class="hero">
    <div class="container">
        <h1 class="display-4 fw-bold">USSD - BASED INFORMATION SYSTEM</h1>
        <h2 class="display-6">for National Institute of Transport (NIT)</h2>
        <p class="lead mt-3">Access academic and administrative services from any mobile phone – no internet required!</p>
        <a href="public/admin/login.php" class="btn btn-login btn-lg mt-4">
            <i class="fas fa-lock"></i> Admin Login
        </a>
    </div>
</div>

<!-- Features Section -->
<div class="container my-5">
    <div class="row text-center mb-4">
        <div class="col-12">
            <h3>What can you do with the USSD service?</h3>
            <p class="text-muted">Dial the shortcode from your mobile phone and access:</p>
        </div>
    </div>
    <div class="row">
        <div class="col-md-4">
            <div class="card feature-card h-100 shadow-sm">
                <div class="card-body text-center">
                    <i class="fas fa-file-alt fa-3x text-primary mb-3"></i>
                    <h5 class="card-title">Exam Results</h5>
                    <p class="card-text">Check your semester results instantly using your registration number and PIN.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card feature-card h-100 shadow-sm">
                <div class="card-body text-center">
                    <i class="fas fa-money-bill-wave fa-3x text-success mb-3"></i>
                    <h5 class="card-title">Fee Balance</h5>
                    <p class="card-text">View your fee balances, paid amounts, and outstanding dues per semester.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card feature-card h-100 shadow-sm">
                <div class="card-body text-center">
                    <i class="fas fa-book-open fa-3x text-info mb-3"></i>
                    <h5 class="card-title">Course Registration</h5>
                    <p class="card-text">See which courses you are registered for in the current and past semesters.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4 mt-3">
            <div class="card feature-card h-100 shadow-sm">
                <div class="card-body text-center">
                    <i class="fas fa-bullhorn fa-3x text-warning mb-3"></i>
                    <h5 class="card-title">Announcements</h5>
                    <p class="card-text">Read important institutional announcements and updates.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4 mt-3">
            <div class="card feature-card h-100 shadow-sm">
                <div class="card-body text-center">
                    <i class="fas fa-shield-alt fa-3x text-danger mb-3"></i>
                    <h5 class="card-title">Secure Access</h5>
                    <p class="card-text">Your data is protected with PIN authentication and encrypted sessions.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4 mt-3">
            <div class="card feature-card h-100 shadow-sm">
                <div class="card-body text-center">
                    <i class="fas fa-mobile-alt fa-3x text-secondary mb-3"></i>
                    <h5 class="card-title">Works on Any Phone</h5>
                    <p class="card-text">Compatible with all GSM phones – no smartphone or internet required.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- How It Works Section -->
<div class="container my-5">
    <div class="row">
        <div class="col-12 text-center">
            <h3>How to Use</h3>
        </div>
    </div>
    <div class="row text-center mt-3">
        <div class="col-md-3">
            <i class="fas fa-dial fa-2x text-primary"></i>
            <p><strong>1. Dial</strong><br>Dial the USSD shortcode *123# </p>
        </div>
        <div class="col-md-3">
            <i class="fas fa-list fa-2x text-primary"></i>
            <p><strong>2. Choose</strong><br>Select a service from the menu</p>
        </div>
        <div class="col-md-3">
            <i class="fas fa-id-card fa-2x text-primary"></i>
            <p><strong>3. Enter</strong><br>Registration number & PIN</p>
        </div>
        <div class="col-md-3">
            <i class="fas fa-check-circle fa-2x text-primary"></i>
            <p><strong>4. Receive</strong><br>Information displayed on screen</p>
        </div>
    </div>
</div>

<!-- Footer -->
<footer>
    <div class="container">
        <p>&copy; <?php echo date('Y'); ?> National Institute of Transport (NIT). All rights reserved.</p>
    </div>
</footer>

<!-- Bootstrap JS Bundle (optional, for interactivity) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>