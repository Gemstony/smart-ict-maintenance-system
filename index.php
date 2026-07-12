<?php
// index.php - Landing page with login modal + forgot password

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/session.php';

$error = isset($_GET['error']) ? $_GET['error'] : '';
$step = isset($_GET['step']) ? $_GET['step'] : 'login'; // login, forgot1, forgot2, forgot3, forgot4
$email = isset($_GET['email']) ? $_GET['email'] : '';
$msg = isset($_GET['msg']) ? $_GET['msg'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ICT Asset Management System - IFM</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* ====== GLOBAL ====== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
        }
        
        /* ====== NAVBAR ====== */
        .custom-navbar {
            background: linear-gradient(135deg, #1a73e8 0%, #0d47a1 100%);
            padding: 18px 0;
            box-shadow: 0 4px 30px rgba(0,0,0,0.25);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .custom-navbar .navbar-brand {
            color: white;
            font-weight: 700;
            font-size: 1.6rem;
            letter-spacing: 0.5px;
        }
        .custom-navbar .navbar-brand i {
            margin-right: 12px;
            font-size: 1.8rem;
        }
        .custom-navbar .navbar-brand small {
            font-size: 0.7rem;
            font-weight: 400;
            opacity: 0.8;
            display: block;
            margin-top: -2px;
        }
        .nav-link {
            color: rgba(255,255,255,0.85) !important;
            font-weight: 500;
            transition: all 0.3s;
            padding: 8px 18px !important;
            border-radius: 8px;
        }
        .nav-link:hover {
            color: white !important;
            background: rgba(255,255,255,0.15);
        }
        .btn-login-nav {
            background: #ffc107;
            color: #1a1a2e;
            font-weight: 700;
            padding: 10px 30px;
            border-radius: 50px;
            border: none;
            transition: all 0.3s;
            font-size: 0.95rem;
            box-shadow: 0 4px 15px rgba(255,193,7,0.3);
        }
        .btn-login-nav:hover {
            background: #ffca2c;
            color: #1a1a2e;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255,193,7,0.4);
        }
        
        /* ====== HERO ====== */
        .hero {
            background: linear-gradient(135deg, #1a73e8 0%, #0d47a1 100%);
            color: white;
            padding: 120px 0 80px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 500px;
            height: 500px;
            border-radius: 50%;
            background: rgba(255,255,255,0.05);
        }
        .hero::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -10%;
            width: 400px;
            height: 400px;
            border-radius: 50%;
            background: rgba(255,255,255,0.03);
        }
        .hero h1 {
            font-size: 3.8rem;
            font-weight: 800;
            position: relative;
            z-index: 1;
            line-height: 1.2;
        }
        .hero .highlight {
            color: #ffc107;
            position: relative;
        }
        .hero .highlight::after {
            content: '';
            position: absolute;
            bottom: 5px;
            left: 0;
            right: 0;
            height: 4px;
            background: #ffc107;
            border-radius: 2px;
        }
        .hero .subtitle {
            font-size: 1.4rem;
            opacity: 0.9;
            margin-top: 15px;
            position: relative;
            z-index: 1;
            font-weight: 300;
        }
        .hero .lead {
            font-size: 1.2rem;
            opacity: 0.8;
            position: relative;
            z-index: 1;
            max-width: 600px;
            margin: 20px auto 0;
        }
        .hero .badge-version {
            display: inline-block;
            background: rgba(255,255,255,0.15);
            padding: 6px 20px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            color: #ffc107;
            border: 1px solid rgba(255,193,7,0.3);
            position: relative;
            z-index: 1;
            margin-top: 10px;
        }
        .hero .floating-icons {
            position: relative;
            z-index: 1;
            margin-top: 30px;
        }
        .hero .floating-icons i {
            font-size: 2.5rem;
            margin: 0 15px;
            opacity: 0.6;
            transition: all 0.3s;
        }
        .hero .floating-icons i:hover {
            opacity: 1;
            transform: translateY(-5px);
        }
        
        /* ====== FEATURES ====== */
        .section-title {
            font-weight: 800;
            font-size: 2.5rem;
            color: #1a1a2e;
            position: relative;
            display: inline-block;
        }
        .section-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 4px;
            background: linear-gradient(135deg, #1a73e8, #ffc107);
            border-radius: 2px;
        }
        .section-subtitle {
            color: #6c757d;
            font-size: 1.1rem;
            margin-top: 20px;
        }
        
        .feature-card {
            transition: all 0.4s;
            border-radius: 20px;
            border: none;
            box-shadow: 0 5px 25px rgba(0,0,0,0.06);
            height: 100%;
            padding: 35px 25px;
            background: white;
            position: relative;
            overflow: hidden;
        }
        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #1a73e8, #ffc107);
            opacity: 0;
            transition: opacity 0.4s;
        }
        .feature-card:hover::before {
            opacity: 1;
        }
        .feature-card:hover {
            transform: translateY(-12px);
            box-shadow: 0 20px 50px rgba(0,0,0,0.12);
        }
        .feature-card .icon-circle {
            width: 75px;
            height: 75px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 18px;
            font-size: 32px;
            transition: all 0.4s;
        }
        .feature-card:hover .icon-circle {
            transform: scale(1.1) rotate(5deg);
        }
        .feature-card h5 {
            font-weight: 700;
            margin-bottom: 10px;
        }
        .feature-card p {
            color: #6c757d;
            font-size: 0.95rem;
            margin-bottom: 0;
        }
        
        .icon-primary { background: #e3f2fd; color: #1a73e8; }
        .icon-success { background: #e8f5e9; color: #2e7d32; }
        .icon-info { background: #e0f7fa; color: #00838f; }
        .icon-warning { background: #fff3e0; color: #e65100; }
        .icon-danger { background: #fce4ec; color: #c62828; }
        .icon-purple { background: #f3e5f5; color: #6a1b9a; }
        
        /* ====== HOW IT WORKS ====== */
        .step-card {
            text-align: center;
            padding: 30px 20px;
            border-radius: 20px;
            background: white;
            box-shadow: 0 5px 25px rgba(0,0,0,0.06);
            transition: all 0.4s;
            height: 100%;
        }
        .step-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.1);
        }
        .step-number {
            width: 65px;
            height: 65px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1a73e8, #0d47a1);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            font-weight: 800;
            margin: 0 auto 18px;
            box-shadow: 0 8px 25px rgba(26,115,232,0.3);
        }
        .step-card h5 {
            font-weight: 700;
        }
        .step-card p {
            color: #6c757d;
            font-size: 0.9rem;
        }
        .step-arrow {
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: #1a73e8;
            opacity: 0.3;
        }
        
        /* ====== STATS ====== */
        .stats-section {
            background: linear-gradient(135deg, #0d47a1, #1a73e8);
            padding: 60px 0;
            color: white;
        }
        .stat-item h3 {
            font-size: 3rem;
            font-weight: 800;
        }
        .stat-item p {
            opacity: 0.8;
            font-weight: 300;
        }
        
        /* ====== FOOTER ====== */
        .custom-footer {
            background: #0d47a1;
            color: white;
            padding: 40px 0 30px;
        }
        .custom-footer a {
            color: #ffc107;
            text-decoration: none;
            transition: all 0.3s;
        }
        .custom-footer a:hover {
            color: #ffca2c;
            text-decoration: underline;
        }
        .custom-footer .social-icons a {
            display: inline-block;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255,255,255,0.1);
            color: white;
            text-align: center;
            line-height: 40px;
            transition: all 0.3s;
            margin: 0 5px;
        }
        .custom-footer .social-icons a:hover {
            background: #ffc107;
            color: #0d47a1;
            transform: translateY(-3px);
        }
        
        /* ====== LOGIN MODAL ====== */
        .modal-header {
            background: linear-gradient(135deg, #1a73e8 0%, #0d47a1 100%);
            color: white;
            border-radius: 16px 16px 0 0;
            padding: 20px 25px;
        }
        .modal-header .btn-close {
            filter: brightness(0) invert(1);
            opacity: 0.8;
        }
        .modal-header .btn-close:hover {
            opacity: 1;
        }
        .modal-content {
            border-radius: 16px;
            border: none;
            box-shadow: 0 30px 80px rgba(0,0,0,0.3);
        }
        .modal-body {
            padding: 30px;
        }
        
        .password-toggle {
            cursor: pointer;
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 10;
            color: #6c757d;
            transition: all 0.3s;
        }
        .password-toggle:hover {
            color: #1a73e8;
        }
        .input-group {
            position: relative;
        }
        .input-group .form-control {
            padding-right: 45px;
            border-radius: 10px;
            border: 2px solid #e9ecef;
            transition: all 0.3s;
            font-size: 0.95rem;
        }
        .input-group .form-control:focus {
            border-color: #1a73e8;
            box-shadow: 0 0 0 3px rgba(26,115,232,0.15);
        }
        .input-group .input-group-text {
            border-radius: 10px 0 0 10px;
            border: 2px solid #e9ecef;
            border-right: none;
            background: #f8f9fa;
        }
        
        .forgot-link {
            color: #1a73e8;
            text-decoration: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }
        .forgot-link:hover {
            text-decoration: underline;
            color: #0d47a1;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #1a73e8, #0d47a1);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(26,115,232,0.4);
        }
        .btn-success {
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(40,167,69,0.4);
        }
        
        /* ====== STEP INDICATOR ====== */
        .step-indicator {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-bottom: 25px;
        }
        .step-dot {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 13px;
            color: #6c757d;
            transition: all 0.3s;
        }
        .step-dot.active {
            background: #1a73e8;
            color: white;
            box-shadow: 0 4px 15px rgba(26,115,232,0.3);
        }
        .step-dot.completed {
            background: #28a745;
            color: white;
        }
        .step-line {
            width: 35px;
            height: 3px;
            background: #e9ecef;
            border-radius: 2px;
            transition: all 0.3s;
        }
        .step-line.completed {
            background: #28a745;
        }
        
        /* ====== ALERT STYLES ====== */
        .alert {
            border-radius: 12px;
            border: none;
            padding: 15px 20px;
        }
        .alert-danger {
            background: #fce4ec;
            color: #c62828;
            border-left: 4px solid #dc3545;
        }
        .alert-warning {
            background: #fff3e0;
            color: #e65100;
            border-left: 4px solid #ffc107;
        }
        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border-left: 4px solid #28a745;
        }
        .alert-info {
            background: #e3f2fd;
            color: #0d47a1;
            border-left: 4px solid #1a73e8;
        }
        .alert i {
            margin-right: 10px;
        }
        
        /* ====== RESPONSIVE ====== */
        @media (max-width: 992px) {
            .hero h1 { font-size: 2.8rem; }
            .hero .subtitle { font-size: 1.2rem; }
            .step-arrow { display: none; }
        }
        
        @media (max-width: 768px) {
            .hero h1 { font-size: 2.2rem; }
            .hero .subtitle { font-size: 1rem; }
            .hero { padding: 80px 0 60px; }
            .section-title { font-size: 2rem; }
            .stat-item h3 { font-size: 2.2rem; }
            .custom-navbar .navbar-brand { font-size: 1.3rem; }
            .custom-navbar .navbar-brand small { font-size: 0.6rem; }
            .modal-body { padding: 20px; }
            .step-indicator { gap: 4px; }
            .step-dot { width: 28px; height: 28px; font-size: 11px; }
            .step-line { width: 20px; }
            .btn-login-nav { padding: 8px 20px; font-size: 0.85rem; }
        }
        
        @media (max-width: 576px) {
            .hero h1 { font-size: 1.8rem; }
            .hero .lead { font-size: 1rem; }
            .hero .floating-icons i { font-size: 1.8rem; margin: 0 10px; }
            .section-title { font-size: 1.6rem; }
            .feature-card { padding: 25px 18px; }
            .feature-card .icon-circle { width: 60px; height: 60px; font-size: 26px; }
        }
    </style>
</head>
<body>

<!-- ====== NAVBAR ====== -->
<nav class="navbar navbar-expand-lg custom-navbar">
    <div class="container">
        <a class="navbar-brand" href="#">
            <i class="fas fa-microchip"></i> <?php echo APP_NAME; ?>
            <small>Institute of Finance Management</small>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMenu">
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item">
                    <a href="#features" class="nav-link"><i class="fas fa-cubes"></i> Features</a>
                </li>
                <li class="nav-item">
                    <a href="#how-it-works" class="nav-link"><i class="fas fa-play-circle"></i> How It Works</a>
                </li>
                <li class="nav-item">
                    <a href="#stats" class="nav-link"><i class="fas fa-chart-bar"></i> Stats</a>
                </li>
                <li class="nav-item">
                    <button class="btn btn-login-nav" data-bs-toggle="modal" data-bs-target="#loginModal">
                        <i class="fas fa-lock"></i> Login
                    </button>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- ====== HERO ====== -->
<section class="hero">
    <div class="container">
        
        <h1>SMART ICT Asset <br><span class="highlight">Maintenance &amp; Fault Detection</span></h1>
        <p class="subtitle">Institute of Finance Management (IFM)</p>
        <p class="lead">
            <i class="fas fa-check-circle text-warning"></i> Streamline asset tracking, fault reporting, and maintenance management
        </p>
        <div class="floating-icons">
            <i class="fas fa-qrcode text-warning"></i>
            <i class="fas fa-print text-light"></i>
            <i class="fas fa-server text-info"></i>
            <i class="fas fa-network-wired text-success"></i>
            <i class="fas fa-laptop text-light"></i>
        </div>
    </div>
</section>

<!-- ====== FEATURES ====== -->
<section id="features" class="py-5">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="section-title">System Features</h2>
            <p class="section-subtitle">Comprehensive ICT asset management solution for IFM</p>
        </div>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="feature-card text-center">
                    <div class="icon-circle icon-primary"><i class="fas fa-qrcode"></i></div>
                    <h5>QR Code Tracking</h5>
                    <p>Scan QR codes for instant asset identification and real-time status check</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card text-center">
                    <div class="icon-circle icon-success"><i class="fas fa-exclamation-triangle"></i></div>
                    <h5>Fault Reporting</h5>
                    <p>Report ICT faults electronically and track resolution progress in real-time</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card text-center">
                    <div class="icon-circle icon-info"><i class="fas fa-bell"></i></div>
                    <h5>Real-time Notifications</h5>
                    <p>Instant alerts for technicians when faults are reported or status changes</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card text-center">
                    <div class="icon-circle icon-warning"><i class="fas fa-tasks"></i></div>
                    <h5>Task Assignment</h5>
                    <p>Assign maintenance tasks to technicians and track progress efficiently</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card text-center">
                    <div class="icon-circle icon-danger"><i class="fas fa-chart-line"></i></div>
                    <h5>Analytics Dashboard</h5>
                    <p>Get insights on asset performance, maintenance trends, and technician productivity</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card text-center">
                    <div class="icon-circle icon-purple"><i class="fas fa-shield-alt"></i></div>
                    <h5>Role-Based Access</h5>
                    <p>Secure access for System Admin, ICT Technicians, and Staff members</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ====== STATS ====== -->
<section id="stats" class="stats-section">
    <div class="container">
        <div class="row text-center">
            <div class="col-md-3 col-6">
                <div class="stat-item">
                    <h3 id="statAssets">0</h3>
                    <p><i class="fas fa-laptop"></i> Assets Tracked</p>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-item">
                    <h3 id="statRequests">0</h3>
                    <p><i class="fas fa-clipboard-list"></i> Requests Processed</p>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-item">
                    <h3 id="statResolved">0</h3>
                    <p><i class="fas fa-check-circle"></i> Issues Resolved</p>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-item">
                    <h3 id="statUsers">0</h3>
                    <p><i class="fas fa-users"></i> Active Users</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ====== HOW IT WORKS ====== -->
<section id="how-it-works" class="py-5">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="section-title">How It Works</h2>
            <p class="section-subtitle">Simple 4-step workflow for efficient asset maintenance</p>
        </div>
        <div class="row align-items-center">
            <div class="col-md-3">
                <div class="step-card">
                    <div class="step-number">1</div>
                    <h5><i class="fas fa-exclamation-triangle text-warning"></i> Report Fault</h5>
                    <p>Staff reports ICT fault via the system with detailed description</p>
                </div>
            </div>
            <div class="col-md-1 step-arrow">
                <i class="fas fa-chevron-right"></i>
            </div>
            <div class="col-md-3">
                <div class="step-card">
                    <div class="step-number">2</div>
                    <h5><i class="fas fa-user-check text-primary"></i> Tech Assigned</h5>
                    <p>Admin assigns technician to the request based on expertise</p>
                </div>
            </div>
            <div class="col-md-1 step-arrow">
                <i class="fas fa-chevron-right"></i>
            </div>
            <div class="col-md-3">
                <div class="step-card">
                    <div class="step-number">3</div>
                    <h5><i class="fas fa-spinner text-info"></i> Task Tracking</h5>
                    <p>Technician updates task progress and adds resolution notes</p>
                </div>
            </div>
            <div class="col-md-1 step-arrow">
                <i class="fas fa-chevron-right"></i>
            </div>
            <div class="col-md-3">
                <div class="step-card">
                    <div class="step-number">4</div>
                    <h5><i class="fas fa-check-double text-success"></i> Resolution</h5>
                    <p>Fault resolved and analytics updated automatically</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ====== FOOTER ====== -->
<footer class="custom-footer">
    <div class="container">
        <div class="row">
           
                <p class="small opacity-50"> Powered by TZONETECH</p>
            </div>
        </div>
    </div>
</footer>

<!-- ====== LOGIN / FORGOT PASSWORD MODAL ====== -->
<div class="modal fade" id="loginModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <?php if ($step == 'login'): ?>
                        <i class="fas fa-lock"></i> System Login
                    <?php else: ?>
                        <i class="fas fa-key"></i> Forgot Password
                    <?php endif; ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" onclick="resetForgotPassword()"></button>
            </div>
            <div class="modal-body">
                <!-- ====== ERROR MESSAGES ====== -->
                <?php if ($error === 'invalid'): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> Invalid email or password. Please try again.
                    </div>
                <?php elseif ($error === 'unauthorized'): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> You do not have permission to access that page.
                    </div>
                <?php elseif ($error === 'inactive'): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-ban"></i> Your account has been <strong>deactivated</strong>.<br>
                        <small>Please contact the System Administrator for assistance.</small>
                    </div>
                <?php elseif ($error === 'pending'): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-clock"></i> Your account is <strong>pending approval</strong>.<br>
                        <small>Please wait for the administrator to approve your account.</small>
                    </div>
                <?php elseif ($error === 'not_approved'): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-user-clock"></i> Your account has not been <strong>approved</strong> yet.<br>
                        <small>Please wait for the administrator to approve your registration.</small>
                    </div>
                <?php elseif ($error === 'email_not_found'): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-envelope-open"></i> Email not found in our system.
                    </div>
                <?php elseif ($error === 'phone_mismatch'): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-phone-slash"></i> Phone number does not match our records.
                    </div>
                <?php elseif ($error === 'lastname_mismatch'): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-user-slash"></i> Last name does not match our records.
                    </div>
                <?php elseif ($error === 'password_mismatch'): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-times-circle"></i> Passwords do not match.
                    </div>
                <?php elseif ($error === 'password_weak'): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> Password must be at least 8 characters.
                    </div>
                <?php elseif ($msg === 'password_updated'): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> Password updated successfully! Please login.
                    </div>
                <?php endif; ?>

                <?php if ($step == 'login'): ?>
                    <!-- ====== LOGIN FORM ====== -->
                    <form action="public/login_process.php" method="POST">
                        <div class="mb-3">
                            <label for="email" class="form-label fw-bold">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" required placeholder="Enter your email" autofocus>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label fw-bold">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-key"></i></span>
                                <input type="password" class="form-control" id="login_password" name="password" required placeholder="Enter your password">
                                <i class="fas fa-eye password-toggle" id="toggleLoginPassword" onclick="togglePassword('login_password', this)"></i>
                            </div>
                        </div>
                        <div class="mb-3 d-flex justify-content-between align-items-center">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="rememberMe" name="remember">
                                <label class="form-check-label" for="rememberMe">Remember Me</label>
                            </div>
                            <a href="?step=forgot1" class="forgot-link" onclick="showForgotStep('forgot1')">
                                <i class="fas fa-question-circle"></i> Forgot Password?
                            </a>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-2">
                            <i class="fas fa-sign-in-alt"></i> Login
                        </button>
                    </form>
                    <hr>
                    <div class="text-center">
                        <p class="text-muted small">
                            <i class="fas fa-shield-alt text-primary"></i> 
                            Secure system for authorized users only
                        </p>
                    </div>

                <?php elseif ($step == 'forgot1'): ?>
                    <!-- ====== FORGOT PASSWORD - STEP 1: EMAIL ====== -->
                    <div class="step-indicator">
                        <div class="step-dot active">1</div>
                        <div class="step-line"></div>
                        <div class="step-dot">2</div>
                        <div class="step-line"></div>
                        <div class="step-dot">3</div>
                        <div class="step-line"></div>
                        <div class="step-dot">4</div>
                    </div>
                    <p class="text-muted text-center mb-3">
                        <i class="fas fa-info-circle"></i> Enter your registered email address to verify your identity.
                    </p>
                    <form action="public/forgot_password_process.php" method="POST">
                        <input type="hidden" name="step" value="1">
                        <div class="mb-3">
                            <label for="forgot_email" class="form-label fw-bold">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="forgot_email" name="email" required placeholder="Enter your registered email">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-2">
                            <i class="fas fa-arrow-right"></i> Next Step
                        </button>
                    </form>
                    <hr>
                    <p class="text-center mb-0">
                        <a href="?step=login" class="forgot-link" onclick="showForgotStep('login')">
                            <i class="fas fa-arrow-left"></i> Back to Login
                        </a>
                    </p>

                <?php elseif ($step == 'forgot2'): ?>
                    <!-- ====== FORGOT PASSWORD - STEP 2: PHONE ====== -->
                    <div class="step-indicator">
                        <div class="step-dot completed">✓</div>
                        <div class="step-line completed"></div>
                        <div class="step-dot active">2</div>
                        <div class="step-line"></div>
                        <div class="step-dot">3</div>
                        <div class="step-line"></div>
                        <div class="step-dot">4</div>
                    </div>
                    <p class="text-muted text-center mb-3">
                        <i class="fas fa-phone"></i> Verify your identity by entering your registered phone number.
                    </p>
                    <form action="public/forgot_password_process.php" method="POST">
                        <input type="hidden" name="step" value="2">
                        <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                        <div class="mb-3">
                            <label for="forgot_phone" class="form-label fw-bold">Phone Number</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                <input type="tel" class="form-control" id="forgot_phone" name="phone" required placeholder="Enter your registered phone number">
                            </div>
                            <small class="text-muted">Format: 255XXXXXXXXX (12 digits)</small>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-2">
                            <i class="fas fa-arrow-right"></i> Next Step
                        </button>
                    </form>
                    <hr>
                    <p class="text-center mb-0">
                        <a href="?step=forgot1" class="forgot-link" onclick="showForgotStep('forgot1')">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                    </p>

                <?php elseif ($step == 'forgot3'): ?>
                    <!-- ====== FORGOT PASSWORD - STEP 3: LAST NAME ====== -->
                    <div class="step-indicator">
                        <div class="step-dot completed">✓</div>
                        <div class="step-line completed"></div>
                        <div class="step-dot completed">✓</div>
                        <div class="step-line completed"></div>
                        <div class="step-dot active">3</div>
                        <div class="step-line"></div>
                        <div class="step-dot">4</div>
                    </div>
                    <p class="text-muted text-center mb-3">
                        <i class="fas fa-user-check"></i> Enter your last name for final verification.
                    </p>
                    <form action="public/forgot_password_process.php" method="POST">
                        <input type="hidden" name="step" value="3">
                        <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                        <div class="mb-3">
                            <label for="forgot_lastname" class="form-label fw-bold">Last Name</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control" id="forgot_lastname" name="last_name" required placeholder="Enter your last name">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-2">
                            <i class="fas fa-arrow-right"></i> Next Step
                        </button>
                    </form>
                    <hr>
                    <p class="text-center mb-0">
                        <a href="?step=forgot2&email=<?php echo urlencode($email); ?>" class="forgot-link" onclick="showForgotStep('forgot2')">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                    </p>

                <?php elseif ($step == 'forgot4'): ?>
                    <!-- ====== FORGOT PASSWORD - STEP 4: NEW PASSWORD ====== -->
                    <div class="step-indicator">
                        <div class="step-dot completed">✓</div>
                        <div class="step-line completed"></div>
                        <div class="step-dot completed">✓</div>
                        <div class="step-line completed"></div>
                        <div class="step-dot completed">✓</div>
                        <div class="step-line completed"></div>
                        <div class="step-dot active">4</div>
                    </div>
                    <p class="text-muted text-center mb-3">
                        <i class="fas fa-key"></i> Create a new password for your account.
                    </p>
                    <form action="public/forgot_password_process.php" method="POST">
                        <input type="hidden" name="step" value="4">
                        <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                        <div class="mb-3">
                            <label for="new_password" class="form-label fw-bold">New Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-key"></i></span>
                                <input type="password" class="form-control" id="new_password" name="new_password" required placeholder="Enter new password (min 8 chars)">
                                <i class="fas fa-eye password-toggle" id="toggleNewPassword" onclick="togglePassword('new_password', this)"></i>
                            </div>
                            <small class="text-muted">Password must be at least 8 characters</small>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label fw-bold">Confirm Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-check"></i></span>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required placeholder="Confirm new password">
                                <i class="fas fa-eye password-toggle" id="toggleConfirmPassword" onclick="togglePassword('confirm_password', this)"></i>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-success w-100 py-2">
                            <i class="fas fa-check-circle"></i> Update Password
                        </button>
                    </form>
                    <hr>
                    <p class="text-center mb-0">
                        <a href="?step=login" class="forgot-link" onclick="showForgotStep('login')">
                            <i class="fas fa-arrow-left"></i> Back to Login
                        </a>
                    </p>

                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // ====== TOGGLE PASSWORD VISIBILITY ======
    function togglePassword(inputId, iconElement) {
        const input = document.getElementById(inputId);
        if (input.type === 'password') {
            input.type = 'text';
            iconElement.classList.remove('fa-eye');
            iconElement.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            iconElement.classList.remove('fa-eye-slash');
            iconElement.classList.add('fa-eye');
        }
    }

    // ====== SHOW FORGOT PASSWORD STEPS ======
    function showForgotStep(step) {
        window.location.href = '?step=' + step;
    }

    function resetForgotPassword() {
        setTimeout(function() {
            window.location.href = '?step=login';
        }, 300);
    }

    // ====== AUTO-SHOW MODAL IF ERROR EXISTS ======
    <?php if ($error || $step != 'login'): ?>
        document.addEventListener('DOMContentLoaded', function() {
            var modal = new bootstrap.Modal(document.getElementById('loginModal'));
            modal.show();
        });
    <?php endif; ?>

    // ====== HANDLE FORGOT PASSWORD STEP FROM URL ======
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        const step = urlParams.get('step');
        if (step && step !== 'login') {
            var modal = new bootstrap.Modal(document.getElementById('loginModal'));
            modal.show();
        }
        
        // ====== ANIMATED COUNTER FOR STATS ======
        animateCounter('statAssets', <?php 
            try {
                $db = getDB();
                $stmt = $db->query("SELECT COUNT(*) as count FROM assets");
                echo $stmt->fetch()['count'] ?? 0;
            } catch (Exception $e) {
                echo 0;
            }
        ?>);
        animateCounter('statRequests', <?php 
            try {
                $stmt = $db->query("SELECT COUNT(*) as count FROM maintenance_requests");
                echo $stmt->fetch()['count'] ?? 0;
            } catch (Exception $e) {
                echo 0;
            }
        ?>);
        animateCounter('statResolved', <?php 
            try {
                $stmt = $db->query("SELECT COUNT(*) as count FROM maintenance_requests WHERE status IN ('Resolved', 'Closed')");
                echo $stmt->fetch()['count'] ?? 0;
            } catch (Exception $e) {
                echo 0;
            }
        ?>);
        animateCounter('statUsers', <?php 
            try {
                $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE status = 'active' AND is_approved = 1");
                echo $stmt->fetch()['count'] ?? 0;
            } catch (Exception $e) {
                echo 0;
            }
        ?>);
    });

    // ====== ANIMATED COUNTER ======
    function animateCounter(elementId, target) {
        const element = document.getElementById(elementId);
        if (!element) return;
        
        let current = 0;
        const increment = Math.ceil(target / 60);
        const duration = 1500;
        const stepTime = Math.floor(duration / 60);
        
        const timer = setInterval(function() {
            current += increment;
            if (current >= target) {
                current = target;
                clearInterval(timer);
            }
            element.textContent = current.toLocaleString();
        }, stepTime);
    }

    console.log('✅ IFM ICT Asset Management System Loaded');
    console.log('📱 Responsive design ready for all devices');
    console.log('🔐 Account status checks: Active | Inactive | Pending');
</script>
</body>
</html>