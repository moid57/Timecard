<?php
// Login Page & Authentication Handler
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Redirect if already logged in
if (is_logged_in()) {
    if (is_admin()) {
        header("Location: " . APP_ROOT . "admin/dashboard");
    } else {
        header("Location: " . APP_ROOT . "employee/dashboard");
    }
    exit;
}

$error = '';
$timeout_msg = isset($_GET['timeout']) && $_GET['timeout'] == 1 ? 'Your session expired due to inactivity. Please log in again.' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    if (!verify_csrf($token)) {
        $error = 'Invalid request security token (CSRF).';
    } elseif (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM employees WHERE emp_id = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user) {
                $login_success = false;

                // 1. Check standard bcrypt password_hash
                if (password_verify($password, $user['password'])) {
                    $login_success = true;
                } 
                // 2. Check plaintext fallback (For default SQL seeded employees / Admin plaintext default)
                elseif (strpos($user['password'], '$2y$') !== 0 && $password === $user['password']) {
                    // Automatically upgrade to secure bcrypt password hash
                    $new_hash = password_hash($password, PASSWORD_DEFAULT);
                    $update_stmt = $pdo->prepare("UPDATE employees SET password = ? WHERE id = ?");
                    $update_stmt->execute([$new_hash, $user['id']]);
                    $login_success = true;
                }

                if ($login_success) {
                    // Check if employee is inactive
                    if ($user['role'] === 'employee' && $user['status'] === 'inactive') {
                        $error = 'Your account has been deactivated. Please contact the administrator.';
                    }
                    if (empty($error)) {
                        // Establish Secure Session variables
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['emp_id'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['last_activity'] = time();
                        $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];

                        // Log activity
                        log_activity($user['id'], "User logged in successfully");

                        // Redirect based on role
                        if ($user['role'] === 'admin') {
                            header("Location: " . APP_ROOT . "admin/dashboard");
                        } else {
                            header("Location: " . APP_ROOT . "employee/dashboard");
                        }
                        exit;
                    }
                } else {
                    $error = 'Invalid username or password.';
                    log_activity(null, "Failed login attempt for username: " . sanitize($username));
                }
            } else {
                $error = 'Invalid username or password.';
                log_activity(null, "Failed login attempt for username: " . sanitize($username));
            }
        } catch (PDOException $e) {
            $error = 'Database error occurred. Please try again later.';
            error_log("Login DB Error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Rhine System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bs-body-tertiary);
            min-vh: 100vh;
        }
        .login-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            max-width: 400px;
            width: 100%;
        }
        [data-bs-theme="dark"] .login-card {
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
        }
    </style>
    <script>
        (function () {
            const theme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-bs-theme', theme);
        })();
    </script>
</head>
<body class="d-flex align-items-center justify-content-center p-3">

    <div class="card login-card p-4 bg-body">
        <div class="text-center mb-4">
            <h2 class="fw-bold text-primary mb-1">Rhine App</h2>
            <p class="text-muted small">Sign in to your corporate workspace</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 small" role="alert">
                <?php echo e($error); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($timeout_msg)): ?>
            <div class="alert alert-warning py-2 small" role="alert">
                <?php echo e($timeout_msg); ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST">
            <!-- CSRF Token -->
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">

            <div class="mb-3">
                <label for="username" class="form-label small fw-semibold">Employee ID / Username</label>
                <input type="text" class="form-control" id="username" name="username" placeholder="e.g. EMP101" required autofocus>
            </div>

            <div class="mb-4">
                <label for="password" class="form-label small fw-semibold">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">Sign In</button>
        </form>

        <div class="text-center mt-4">
            <small class="text-muted">&copy; 2026 Rhine Management System</small>
        </div>
    </div>

</body>
</html>
