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
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        /* ====== NAVBAR ====== */
        .custom-navbar {
            background: linear-gradient(135deg, #1a73e8 0%, #0d47a1 100%);
            padding: 15px 0;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }
        .custom-navbar .navbar-brand {
            color: white;
            font-weight: bold;
            font-size: 1.5rem;
        }
        .custom-navbar .navbar-brand i {
            margin-right: 10px;
        }
        .btn-login-nav {
            background-color: #ffc107;
            color: #000;
            font-weight: bold;
            padding: 8px 25px;
            border-radius: 50px;
            border: none;
            transition: all 0.3s;
        }
        .btn-login-nav:hover {
            background-color: #e0a800;
            color: #000;
            transform: scale(1.05);
        }
        
        /* ====== HERO ====== */
        .hero {
            background: linear-gradient(135deg, #1a73e8 0%, #0d47a1 100%);
            color: white;
            padding: 100px 0;
            text-align: center;
            margin-top: 0;
        }
        .hero h1 {
            font-size: 3.5rem;
            font-weight: 700;
        }
        .hero .highlight {
            color: #ffc107;
        }
        .hero .subtitle {
            font-size: 1.3rem;
            opacity: 0.9;
            margin-top: 15px;
        }
        
        /* ====== FEATURES ====== */
        .feature-card {
            transition: transform 0.3s, box-shadow 0.3s;
            border-radius: 15px;
            border: none;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            height: 100%;
        }
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
        }
        .feature-card .icon-circle {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 30px;
        }
        .icon-primary { background: #e3f2fd; color: #1a73e8; }
        .icon-success { background: #e8f5e9; color: #2e7d32; }
        .icon-info { background: #e0f7fa; color: #00838f; }
        .icon-warning { background: #fff3e0; color: #e65100; }
        .icon-danger { background: #fce4ec; color: #c62828; }
        .icon-purple { background: #f3e5f5; color: #6a1b9a; }
        
        /* ====== STEPS ====== */
        .step-circle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #1a73e8;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: bold;
            margin: 0 auto 15px;
        }
        
        /* ====== FOOTER ====== */
        .custom-footer {
            background: #0d47a1;
            color: white;
            padding: 30px 0;
            margin-top: 50px;
        }
        .custom-footer a {
            color: #ffc107;
            text-decoration: none;
        }
        
        /* ====== LOGIN MODAL ====== */
        .modal-header {
            background: linear-gradient(135deg, #1a73e8 0%, #0d47a1 100%);
            color: white;
        }
        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }
        
        .password-toggle {
            cursor: pointer;
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 10;
            color: #6c757d;
        }
        .password-toggle:hover {
            color: #1a73e8;
        }
        .input-group {
            position: relative;
        }
        .input-group .form-control {
            padding-right: 45px;
        }
        
        /* ====== RESPONSIVE ====== */
        @media (max-width: 768px) {
            .hero h1 { font-size: 2.2rem; }
            .hero .subtitle { font-size: 1rem; }
        }
        
        .forgot-link {
            color: #1a73e8;
            text-decoration: none;
            cursor: pointer;
        }
        .forgot-link:hover {
            text-decoration: underline;
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        .step-dot {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 12px;
            color: #6c757d;
        }
        .step-dot.active {
            background: #1a73e8;
            color: white;
        }
        .step-dot.completed {
            background: #28a745;
            color: white;
        }
        .step-line {
            width: 30px;
            height: 2px;
            background: #e9ecef;
            align-self: center;
        }
        .step-line.completed {
            background: #28a745;
        }
    </style>
</head>
<body>

<!-- ====== NAVBAR ====== -->
<nav class="navbar navbar-expand-lg custom-navbar">
    <div class="container">
        <a class="navbar-brand" href="#">
            <i class="fas fa-microchip"></i> <?php echo APP_NAME; ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMenu">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a href="#features" class="nav-link text-white">Features</a>
                </li>
                <li class="nav-item">
                    <a href="#how-it-works" class="nav-link text-white">How It Works</a>
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
        <p class="lead mt-3">Streamline asset tracking, fault reporting, and maintenance management</p>
        <button class="btn btn-lg btn-warning mt-4 fw-bold" data-bs-toggle="modal" data-bs-target="#loginModal">
            <i class="fas fa-sign-in-alt"></i> Get Started
        </button>
    </div>
</section>

<!-- ====== FEATURES ====== -->
<section id="features" class="container my-5">
    <div class="text-center mb-5">
        <h2 class="fw-bold">System Features</h2>
        <p class="text-muted">Comprehensive ICT asset management solution</p>
    </div>
    <div class="row g-4">
        <div class="col-md-4">
            <div class="card feature-card p-4 text-center">
                <div class="icon-circle icon-primary"><i class="fas fa-qrcode"></i></div>
                <h5>QR Code Tracking</h5>
                <p class="text-muted">Scan QR codes for instant asset identification and status check</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card feature-card p-4 text-center">
                <div class="icon-circle icon-success"><i class="fas fa-exclamation-triangle"></i></div>
                <h5>Fault Reporting</h5>
                <p class="text-muted">Report ICT faults electronically and track resolution progress</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card feature-card p-4 text-center">
                <div class="icon-circle icon-info"><i class="fas fa-bell"></i></div>
                <h5>Real-time Notifications</h5>
                <p class="text-muted">Instant alerts for technicians when faults are reported</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card feature-card p-4 text-center">
                <div class="icon-circle icon-warning"><i class="fas fa-tasks"></i></div>
                <h5>Task Assignment</h5>
                <p class="text-muted">Assign maintenance tasks to technicians and track progress</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card feature-card p-4 text-center">
                <div class="icon-circle icon-danger"><i class="fas fa-chart-line"></i></div>
                <h5>Analytics Dashboard</h5>
                <p class="text-muted">Get insights on asset performance and maintenance trends</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card feature-card p-4 text-center">
                <div class="icon-circle icon-purple"><i class="fas fa-shield-alt"></i></div>
                <h5>Role-Based Access</h5>
                <p class="text-muted">Secure access for System Admin, Technicians, and Staff</p>
            </div>
        </div>
    </div>
</section>

<!-- ====== HOW IT WORKS ====== -->
<section id="how-it-works" class="container my-5">
    <div class="text-center mb-5">
        <h2 class="fw-bold">How It Works</h2>
        <p class="text-muted">Simple workflow for asset maintenance</p>
    </div>
    <div class="row text-center">
        <div class="col-md-3">
            <div class="step-circle">1</div>
            <h5>Report Fault</h5>
            <p class="text-muted">Staff reports ICT fault via the system</p>
        </div>
        <div class="col-md-3">
            <div class="step-circle">2</div>
            <h5>Tech Assigned</h5>
            <p class="text-muted">Admin assigns technician to the request</p>
        </div>
        <div class="col-md-3">
            <div class="step-circle">3</div>
            <h5>Task Tracking</h5>
            <p class="text-muted">Technician updates task progress</p>
        </div>
        <div class="col-md-3">
            <div class="step-circle">4</div>
            <h5>Resolution</h5>
            <p class="text-muted">Fault resolved and analytics updated</p>
        </div>
    </div>
</section>

<!-- ====== FOOTER ====== -->
<footer class="custom-footer">
    <div class="container text-center">
        <p>&copy; <?php echo date('Y'); ?> <strong>Institute of Finance Management (IFM)</strong>. All rights reserved.</p>
        <p class="small">Smart ICT Asset Maintenance &amp; Fault Detection System</p>
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
               <?php if ($error === 'invalid'): ?>
    <div class="alert alert-danger">Invalid email or password. Please try again.</div>
<?php elseif ($error === 'unauthorized'): ?>
    <div class="alert alert-warning">You do not have permission to access that page.</div>
<?php elseif ($error === 'inactive'): ?>
    <div class="alert alert-danger">
        <i class="fas fa-ban"></i> Your account has been <strong>deactivated</strong>. 
        Please contact the System Administrator for assistance.
    </div>
<?php elseif ($error === 'email_not_found'): ?>
    <div class="alert alert-danger">Email not found in our system.</div>
<?php elseif ($error === 'phone_mismatch'): ?>
    <div class="alert alert-danger">Phone number does not match our records.</div>
<?php elseif ($error === 'lastname_mismatch'): ?>
    <div class="alert alert-danger">Last name does not match our records.</div>
<?php elseif ($error === 'password_mismatch'): ?>
    <div class="alert alert-danger">Passwords do not match.</div>
<?php elseif ($error === 'password_weak'): ?>
    <div class="alert alert-danger">Password must be at least 8 characters.</div>
<?php elseif ($msg === 'password_updated'): ?>
    <div class="alert alert-success">Password updated successfully! Please login.</div>
<?php endif; ?>

                <?php if ($step == 'login'): ?>
                    <!-- ====== LOGIN FORM ====== -->
                    <form action="public/login_process.php" method="POST">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" required placeholder="Enter your email">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-key"></i></span>
                                <input type="password" class="form-control" id="login_password" name="password" required placeholder="Enter your password">
                                <i class="fas fa-eye password-toggle" id="toggleLoginPassword" onclick="togglePassword('login_password', this)"></i>
                            </div>
                        </div>
                        <div class="mb-3 d-flex justify-content-between">
                            <div></div>
                            <a href="?step=forgot1" class="forgot-link" onclick="showForgotStep('forgot1')">
                                <i class="fas fa-question-circle"></i> Forgot Password?
                            </a>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-2">
                            <i class="fas fa-sign-in-alt"></i> Login
                        </button>
                    </form>
                    <hr>
                    <p class="text-center text-muted small mb-0">
                        <strong>Demo Credentials:</strong><br>
                        Admin: admin@ict.ifm.ac.tz / Admin@123<br>
                        Technician: tech1@ict.ifm.ac.tz / Admin@123<br>
                        Staff: staff1@ifm.ac.tz / Admin@123
                    </p>

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
                    <p class="text-muted text-center mb-3">Enter your registered email address to verify your identity.</p>
                    <form action="public/forgot_password_process.php" method="POST">
                        <input type="hidden" name="step" value="1">
                        <div class="mb-3">
                            <label for="forgot_email" class="form-label">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="forgot_email" name="email" required placeholder="Enter your registered email">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-2">
                            <i class="fas fa-arrow-right"></i> Next
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
                    <p class="text-muted text-center mb-3">Verify your identity by entering your registered phone number.</p>
                    <form action="public/forgot_password_process.php" method="POST">
                        <input type="hidden" name="step" value="2">
                        <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                        <div class="mb-3">
                            <label for="forgot_phone" class="form-label">Phone Number</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                <input type="tel" class="form-control" id="forgot_phone" name="phone" required placeholder="Enter your registered phone number">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-2">
                            <i class="fas fa-arrow-right"></i> Next
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
                    <p class="text-muted text-center mb-3">Enter your last name for final verification.</p>
                    <form action="public/forgot_password_process.php" method="POST">
                        <input type="hidden" name="step" value="3">
                        <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                        <div class="mb-3">
                            <label for="forgot_lastname" class="form-label">Last Name</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control" id="forgot_lastname" name="last_name" required placeholder="Enter your last name">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-2">
                            <i class="fas fa-arrow-right"></i> Next
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
                    <p class="text-muted text-center mb-3">Create a new password for your account.</p>
                    <form action="public/forgot_password_process.php" method="POST">
                        <input type="hidden" name="step" value="4">
                        <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-key"></i></span>
                                <input type="password" class="form-control" id="new_password" name="new_password" required placeholder="Enter new password (min 8 chars)">
                                <i class="fas fa-eye password-toggle" id="toggleNewPassword" onclick="togglePassword('new_password', this)"></i>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm Password</label>
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
        // Reload page with step parameter
        window.location.href = '?step=' + step;
    }

    function resetForgotPassword() {
        // Reset to login step when modal is closed
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
    });
</script>
</body>
</html>