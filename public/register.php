<?php
// public/register.php - Self-registration page for new users

// ====== DEFINE HASH FUNCTION IF NOT EXISTS ======
if (!function_exists('hashPassword')) {
    function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/notification_helper.php';

// Check if registration is allowed
$token = isset($_GET['token']) ? trim($_GET['token']) : '';

if (empty($token)) {
    header('Location: index.php');
    exit();
}

$db = getDB();

// Validate token
$stmt = $db->prepare("
    SELECT * FROM registration_links 
    WHERE token = ? 
    AND is_active = 1 
    AND (expires_at IS NULL OR expires_at > NOW())
    AND (max_uses = 0 OR used_count < max_uses)
");
$stmt->execute([$token]);
$link = $stmt->fetch();

if (!$link) {
    $stmt = $db->prepare("SELECT * FROM registration_links WHERE token = ?");
    $stmt->execute([$token]);
    $link = $stmt->fetch();
    
    if ($link) {
        if ($link['expires_at'] <= date('Y-m-d H:i:s')) {
            $error = 'This registration link has expired. Please request a new link from the administrator.';
        } elseif ($link['used_count'] >= $link['max_uses'] && $link['max_uses'] > 0) {
            $error = 'This registration link has been used the maximum number of times.';
        } else {
            $error = 'This registration link is no longer active.';
        }
    } else {
        $error = 'Invalid registration link. Please contact the administrator.';
    }
    
    $error = true;
} else {
    $error = false;
}

// Handle form submission
$submitted = false;
$success = false;
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate inputs
    $errors = [];
    
    if (empty($first_name) || strlen($first_name) < 2) {
        $errors[] = 'First name is required and must be at least 2 characters.';
    }
    
    if (empty($last_name) || strlen($last_name) < 2) {
        $errors[] = 'Last name is required and must be at least 2 characters.';
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    
    // Phone validation: must be exactly 9 digits after +255
    $phone_clean = preg_replace('/[^0-9]/', '', $phone);
    if (empty($phone) || strlen($phone_clean) !== 12 || substr($phone_clean, 0, 3) !== '255') {
        $errors[] = 'Please enter a valid phone number with +255 prefix (e.g., +255712345678).';
    }
    
    if (empty($password) || strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters long.';
    }
    
    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }
    
    // Check if email already exists
    if (empty($errors)) {
        $stmt = $db->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'This email is already registered. Please use a different email or contact the administrator.';
        }
    }
    
    // Check if phone already exists
    if (empty($errors)) {
        $stmt = $db->prepare("SELECT user_id FROM users WHERE phone = ?");
        $stmt->execute([$phone]);
        if ($stmt->fetch()) {
            $errors[] = 'This phone number is already registered. Please use a different number.';
        }
    }
    
    if (empty($errors)) {
        // Register user - status inactive until approved
        $hashed_password = hashPassword($password);
        
        $stmt = $db->prepare("
            INSERT INTO users (
                first_name, last_name, email, phone, password, 
                role, department, status, is_approved
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'inactive', 0)
        ");
        
        $stmt->execute([
            $first_name,
            $last_name,
            $email,
            $phone, // Store with +255 prefix
            $hashed_password,
            $link['role'],        // Staff by default
            $link['department'],  // Selected by user
        ]);
        
        $user_id = $db->lastInsertId();
        
        if ($user_id) {
            // Update link usage
            $stmt = $db->prepare("
                UPDATE registration_links 
                SET used_count = used_count + 1 
                WHERE link_id = ?
            ");
            $stmt->execute([$link['link_id']]);
            
            // Send notification to admin
            $stmt = $db->prepare("
                INSERT INTO notifications (user_id, title, message, type)
                SELECT user_id, '👤 New Registration', 
                CONCAT('A new user has registered and is awaiting approval. Name: ', ?, ', Email: ', ?),
                'info'
                FROM users WHERE role = 'System Administrator'
            ");
            $stmt->execute([$first_name . ' ' . $last_name, $email]);
            
            $success = true;
            $message = 'Registration successful! Your account is pending approval. You will receive a notification once approved.';
            
            // Clear token from URL
            $token = '';
        } else {
            $errors[] = 'Failed to register. Please try again.';
        }
    }
    
    if (!empty($errors)) {
        $message = '<ul>';
        foreach ($errors as $err) {
            $message .= '<li>' . htmlspecialchars($err) . '</li>';
        }
        $message .= '</ul>';
    }
}

// NO HEADER - Clean page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - ICT Asset Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #0d47a1 0%, #1a73e8 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .register-wrapper {
            max-width: 600px;
            width: 100%;
        }
        
        .register-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
            border: none;
        }
        
        .register-card .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .register-card .logo i {
            font-size: 3.5rem;
            color: #1a73e8;
            background: #e8f0fe;
            padding: 20px;
            border-radius: 50%;
            display: inline-block;
        }
        
        .register-card .logo h3 {
            margin-top: 15px;
            font-weight: 700;
            color: #212529;
        }
        
        .register-card .logo p {
            color: #6c757d;
            font-size: 0.9rem;
            margin: 0;
        }
        
        .register-card .form-label {
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .register-card .required::after {
            content: " *";
            color: #dc3545;
        }
        
        .register-card .alert {
            border-radius: 12px;
        }
        
        .register-card .password-hint {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .register-card .btn-register {
            background: linear-gradient(135deg, #1a73e8, #0d47a1);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            width: 100%;
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        .register-card .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(26, 115, 232, 0.4);
        }
        
        .register-card .btn-register:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }
        
        .register-card .info-box {
            background: #e8f0fe;
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 20px;
            border-left: 4px solid #1a73e8;
        }
        
        .register-card .info-box h6 {
            color: #1a73e8;
            font-weight: 600;
            margin: 0 0 5px 0;
        }
        
        .register-card .info-box small {
            color: #495057;
        }
        
        .register-card .info-box .detail {
            display: inline-block;
            background: white;
            padding: 2px 12px;
            border-radius: 12px;
            margin: 2px 4px 2px 0;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .register-card .success-icon {
            font-size: 4rem;
            color: #28a745;
            display: block;
            margin: 0 auto 20px;
        }
        
        .register-card .login-link {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }
        
        .register-card .login-link a {
            color: #1a73e8;
            text-decoration: none;
            font-weight: 600;
        }
        
        .register-card .login-link a:hover {
            text-decoration: underline;
        }
        
        .password-strength {
            height: 4px;
            border-radius: 2px;
            margin-top: 5px;
            transition: all 0.3s;
        }
        .password-strength.weak { background: #dc3545; width: 25%; }
        .password-strength.fair { background: #ffc107; width: 50%; }
        .password-strength.good { background: #17a2b8; width: 75%; }
        .password-strength.strong { background: #28a745; width: 100%; }
        
        /* ====== RESPONSIVE - TWO COLUMN ON DESKTOP ====== */
        .form-row-two {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        /* Phone input with prefix */
        .phone-input-group {
            display: flex;
            align-items: stretch;
        }
        
        .phone-input-group .phone-prefix {
            background: #f8f9fa;
            border: 1px solid #ced4da;
            border-right: none;
            border-radius: 6px 0 0 6px;
            padding: 0 12px;
            display: flex;
            align-items: center;
            font-weight: 600;
            color: #495057;
            font-size: 0.95rem;
            white-space: nowrap;
            min-width: 50px;
        }
        
        .phone-input-group .phone-prefix i {
            margin-right: 5px;
            color: #1a73e8;
        }
        
        .phone-input-group .form-control {
            border-radius: 0 6px 6px 0;
            border-left: none;
        }
        
        .phone-input-group .form-control:focus {
            border-color: #1a73e8;
            box-shadow: 0 0 0 0.2rem rgba(26, 115, 232, 0.25);
        }
        
        .phone-hint {
            font-size: 0.75rem;
            color: #6c757d;
            margin-top: 4px;
        }
        
        .phone-hint i {
            color: #1a73e8;
        }
        
        /* ====== RESPONSIVE - MOBILE ====== */
        @media (max-width: 768px) {
            .register-card {
                padding: 25px;
            }
            
            .form-row-two {
                grid-template-columns: 1fr;
                gap: 0;
            }
            
            .register-card .logo i {
                font-size: 2.8rem;
                padding: 15px;
            }
            
            .register-card .logo h3 {
                font-size: 1.3rem;
            }
            
            .phone-input-group .phone-prefix {
                font-size: 0.85rem;
                padding: 0 10px;
                min-width: 45px;
            }
            
            .register-card .info-box .detail {
                display: block;
                margin: 2px 0;
            }
        }
        
        @media (max-width: 576px) {
            .register-card {
                padding: 20px 15px;
                border-radius: 16px;
            }
            
            .register-card .logo i {
                font-size: 2.2rem;
                padding: 12px;
            }
            
            .register-card .logo h3 {
                font-size: 1.1rem;
            }
            
            .register-card .logo p {
                font-size: 0.8rem;
            }
            
            .register-card .form-label {
                font-size: 0.8rem;
            }
            
            .register-card .btn-register {
                padding: 10px 20px;
                font-size: 0.9rem;
            }
            
            .phone-input-group .phone-prefix {
                font-size: 0.75rem;
                padding: 0 8px;
                min-width: 40px;
            }
            
            .phone-input-group .phone-prefix i {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="register-wrapper">
        <div class="register-card">
            
            <!-- Logo -->
            <div class="logo">
                <i class="fas fa-user-plus"></i>
                <h3>Create Account</h3>
                <p>Register to start reporting ICT faults</p>
            </div>
            
            <?php if (isset($error) && $error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <div class="text-center mt-3">
                    <a href="index.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left"></i> Back to Login
                    </a>
                </div>
            <?php elseif (isset($success) && $success): ?>
                <!-- Success Message -->
                <div class="text-center">
                    <i class="fas fa-check-circle success-icon"></i>
                    <h5 class="text-success">Registration Submitted!</h5>
                    <p class="text-muted"><?php echo $message; ?></p>
                    <div class="alert alert-info mt-3">
                        <i class="fas fa-info-circle"></i>
                        You will receive a notification once your account is approved.
                    </div>
                    <a href="index.php" class="btn btn-primary mt-3">
                        <i class="fas fa-arrow-left"></i> Go to Login
                    </a>
                </div>
            <?php else: ?>
                <!-- Registration Form - User selects Department only -->
                <div class="info-box">
                    <h6><i class="fas fa-info-circle"></i> Registration Details</h6>
                    <small>
                        <strong>Role:</strong> 
                        <span class="detail"><?php echo htmlspecialchars($link['role']); ?></span><br>
                        <strong>Department:</strong> 
                        <span class="detail"><?php echo htmlspecialchars($link['department']); ?></span>
                    </small>
                    <div class="mt-2 text-warning small">
                        <i class="fas fa-clock"></i> Block and Room will be assigned by the administrator.
                    </div>
                </div>
                
                <?php if (!empty($message)): ?>
                    <div class="alert alert-danger">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" id="registerForm">
                    <!-- First Name & Last Name - Two columns on desktop, single on mobile -->
                    <div class="form-row-two">
                        <div class="mb-3">
                            <label for="first_name" class="form-label required">First Name</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" 
                                   value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" 
                                   required autofocus>
                        </div>
                        <div class="mb-3">
                            <label for="last_name" class="form-label required">Last Name</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" 
                                   value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" required>
                        </div>
                    </div>
                    
                    <!-- Email - Full width -->
                    <div class="mb-3">
                        <label for="email" class="form-label required">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                        <small class="text-muted">This will be your login username.</small>
                    </div>
                    
                    <!-- Phone - With +255 prefix -->
                    <div class="mb-3">
                        <label for="phone" class="form-label required">Phone Number</label>
                        <div class="phone-input-group">
                            <span class="phone-prefix">
                                <i class="fas fa-phone"></i> +255
                            </span>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   placeholder="712345678" 
                                   value="<?php echo htmlspecialchars(preg_replace('/^255/', '', $_POST['phone'] ?? '')); ?>"
                                   required maxlength="9"
                                   oninput="formatPhone(this)">
                        </div>
                        <div class="phone-hint">
                            <i class="fas fa-info-circle"></i> 
                            Enter 9 digits after +255 (e.g., 712345678)
                            <span id="phoneCount" class="badge bg-secondary ms-2">0/9</span>
                        </div>
                    </div>
                    
                    <!-- Password & Confirm - Two columns on desktop, single on mobile -->
                    <div class="form-row-two">
                        <div class="mb-3">
                            <label for="password" class="form-label required">Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password" name="password" required minlength="6">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword()">
                                    <i class="fas fa-eye" id="passwordIcon"></i>
                                </button>
                            </div>
                            <div class="password-strength" id="passwordStrength"></div>
                            <div class="password-hint mt-1">
                                <i class="fas fa-info-circle"></i> Password must be at least 6 characters long.
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label required">Confirm Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="toggleConfirmPassword()">
                                    <i class="fas fa-eye" id="confirmPasswordIcon"></i>
                                </button>
                            </div>
                            <div id="passwordMatch" class="mt-1"></div>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-clock"></i>
                        <strong>Pending Approval:</strong> Your account will be inactive until approved by the administrator.
                    </div>
                    
                    <button type="submit" name="register" class="btn-register" id="registerBtn">
                        <i class="fas fa-user-plus"></i> Register
                    </button>
                </form>
                
                <div class="login-link">
                    <small class="text-muted">
                        Already have an account? <a href="index.php">Login here</a>
                    </small>
                </div>
            <?php endif; ?>
            
        </div>
    </div>

    <script>
        // ====== TOGGLE PASSWORD VISIBILITY ======
        function togglePassword() {
            const password = document.getElementById('password');
            const icon = document.getElementById('passwordIcon');
            if (password.type === 'password') {
                password.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                password.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }
        
        function toggleConfirmPassword() {
            const password = document.getElementById('confirm_password');
            const icon = document.getElementById('confirmPasswordIcon');
            if (password.type === 'password') {
                password.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                password.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }
        
        // ====== FORMAT PHONE NUMBER ======
        function formatPhone(input) {
            // Remove all non-numeric characters
            let value = input.value.replace(/\D/g, '');
            
            // Limit to 9 digits
            if (value.length > 9) {
                value = value.slice(0, 9);
            }
            
            // Update input value
            input.value = value;
            
            // Update character count
            const countEl = document.getElementById('phoneCount');
            if (countEl) {
                countEl.textContent = value.length + '/9';
                if (value.length === 9) {
                    countEl.className = 'badge bg-success ms-2';
                } else if (value.length >= 7) {
                    countEl.className = 'badge bg-warning text-dark ms-2';
                } else {
                    countEl.className = 'badge bg-secondary ms-2';
                }
            }
        }
        
        // ====== PASSWORD STRENGTH ======
        document.getElementById('password').addEventListener('input', function() {
            const strength = document.getElementById('passwordStrength');
            const value = this.value;
            let score = 0;
            
            if (value.length >= 6) score++;
            if (value.length >= 10) score++;
            if (/[a-z]/.test(value) && /[A-Z]/.test(value)) score++;
            if (/\d/.test(value)) score++;
            if (/[^a-zA-Z0-9]/.test(value)) score++;
            
            const levels = ['weak', 'fair', 'good', 'strong'];
            const colors = ['danger', 'warning', 'info', 'success'];
            
            let index = 0;
            if (score >= 4) index = 3;
            else if (score >= 3) index = 2;
            else if (score >= 2) index = 1;
            
            strength.className = 'password-strength ' + levels[index];
            strength.style.width = ((index + 1) * 25) + '%';
            strength.style.backgroundColor = 'var(--bs-' + colors[index] + ')';
            strength.style.display = value.length > 0 ? 'block' : 'none';
        });
        
        // ====== PASSWORD MATCH ======
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirm = this.value;
            const match = document.getElementById('passwordMatch');
            
            if (confirm.length === 0) {
                match.innerHTML = '';
                return;
            }
            
            if (password === confirm) {
                match.innerHTML = '<i class="fas fa-check-circle text-success"></i> <span class="text-success">Passwords match!</span>';
                match.className = 'mt-1';
            } else {
                match.innerHTML = '<i class="fas fa-times-circle text-danger"></i> <span class="text-danger">Passwords do not match!</span>';
                match.className = 'mt-1';
            }
        });
        
        // ====== FORM VALIDATION ======
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;
            const phone = document.getElementById('phone').value;
            const phoneCount = document.getElementById('phoneCount');
            
            // Check password match
            if (password !== confirm) {
                e.preventDefault();
                alert('Passwords do not match! Please correct them.');
                document.getElementById('confirm_password').focus();
                return;
            }
            
            // Check phone number length
            if (phone.length !== 9) {
                e.preventDefault();
                alert('Please enter exactly 9 digits for the phone number (after +255).');
                document.getElementById('phone').focus();
                return;
            }
            
            // Add +255 prefix before submitting
            const phoneInput = document.getElementById('phone');
            const fullPhone = '255' + phoneInput.value;
            
            // Create hidden input for full phone number
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'phone';
            hiddenInput.value = fullPhone;
            this.appendChild(hiddenInput);
            
            // Disable the original phone input
            phoneInput.disabled = true;
        });
        
        // ====== INITIALIZE PHONE COUNT ON LOAD ======
        document.addEventListener('DOMContentLoaded', function() {
            const phoneInput = document.getElementById('phone');
            if (phoneInput && phoneInput.value) {
                formatPhone(phoneInput);
            }
            
            // Add visual indicator for phone field
            const phoneGroup = document.querySelector('.phone-input-group');
            if (phoneGroup) {
                phoneGroup.addEventListener('click', function() {
                    document.getElementById('phone').focus();
                });
            }
        });
        
        console.log('✅ Registration Page Loaded');
        console.log('📱 Phone format: +255 followed by 9 digits');
        console.log('💻 Desktop: Two columns | 📱 Mobile: Single column');
    </script>
</body>
</html>