<?php
// Employee Profile & Password Change
$page_title = 'My Profile';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Secure page access
if (is_admin()) {
    header("Location: " . APP_ROOT . "admin/dashboard");
    exit;
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    if (!verify_csrf($token)) {
        $error = 'Invalid security token (CSRF).';
    } else {
        // 1. UPDATE CONTACT DETAILS
        if ($action === 'update_profile') {
            $phone = trim($_POST['phone'] ?? '');
            
            try {
                $stmt = $pdo->prepare("UPDATE employees SET phone = ? WHERE id = ?");
                $stmt->execute([$phone, $user_id]);
                
                log_activity($user_id, "Updated contact details in profile");
                $success = 'Profile details updated successfully.';
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }

        // 2. CHANGE PASSWORD
        if ($action === 'change_password') {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                $error = 'All password fields are required.';
            } elseif ($new_password !== $confirm_password) {
                $error = 'New password and confirmation do not match.';
            } elseif (strlen($new_password) < 6) {
                $error = 'New password must be at least 6 characters long.';
            } else {
                try {
                    // Fetch user info
                    $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $user = $stmt->fetch();

                    // Check validation
                    $password_valid = false;
                    if ($user) {
                        if (password_verify($current_password, $user['password'])) {
                            $password_valid = true;
                        } 
                        // Plaintext fallback check (if they never logged in or haven't migrated yet)
                        elseif (strpos($user['password'], '$2y$') !== 0 && $current_password === $user['password']) {
                            $password_valid = true;
                        }
                    }

                    if ($password_valid) {
                        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                        $update_stmt = $pdo->prepare("UPDATE employees SET password = ? WHERE id = ?");
                        $update_stmt->execute([$new_hash, $user_id]);

                        log_activity($user_id, "Changed account password");
                        $success = 'Password changed successfully.';
                    } else {
                        $error = 'Current password is incorrect.';
                    }
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }
    }
}

// Fetch Profile info
try {
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
    $stmt->execute([$user_id]);
    $profile = $stmt->fetch();
    
    if (!$profile) {
        die("Employee profile record missing.");
    }
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

require_once __DIR__ . '/../includes/header.php';
?>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<!-- Main Content Area -->
<main class="main-content d-flex flex-column flex-grow-1">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <!-- Header -->
        <div class="mb-4">
            <h1 class="h3 mb-1 text-gray-800">My Profile</h1>
            <p class="text-muted small">Manage your personal contact details and password credentials.</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 small"><?php echo e($error); ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success py-2 small"><?php echo e($success); ?></div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Details View & Phone Form (Left Column) -->
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header bg-transparent py-3">
                        <h6 class="m-0 fw-bold text-primary"><i class="bi bi-person-badge-fill me-1"></i> Personal Profile Info</h6>
                    </div>
                    <div class="card-body">
                        <!-- Readonly Info -->
                        <div class="row g-3 mb-4">
                            <div class="col-sm-6">
                                <label class="text-muted small d-block mb-1">Employee ID</label>
                                <span class="fw-bold font-monospace bg-light px-2 py-1 rounded text-dark border"><?php echo e($profile['emp_id']); ?></span>
                            </div>
                            <div class="col-sm-6">
                                <label class="text-muted small d-block mb-1">Register Date</label>
                                <span class="fw-semibold text-dark"><?php echo date('M d, Y', strtotime($profile['created_at'])); ?></span>
                            </div>
                            <div class="col-sm-6">
                                <label class="text-muted small d-block mb-1">Full Name</label>
                                <span class="fw-semibold text-dark"><?php echo e($profile['first_name'] . ' ' . $profile['last_name']); ?></span>
                            </div>
                            <div class="col-sm-6">
                                <label class="text-muted small d-block mb-1">Work Email</label>
                                <span class="fw-semibold text-dark"><?php echo e($profile['email']); ?></span>
                            </div>
                        </div>

                        <hr>

                        <!-- Editable Phone -->
                        <form action="" method="POST" class="mt-4">
                            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                            <input type="hidden" name="action" value="update_profile">

                            <div class="mb-4">
                                <label class="form-label small fw-semibold">Contact Phone Number</label>
                                <input type="text" class="form-control" name="phone" value="<?php echo e($profile['phone']); ?>" placeholder="e.g. 555-0101">
                            </div>

                            <button type="submit" class="btn btn-primary fw-semibold">Save Contact Details</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Password Change Form (Right Column) -->
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header bg-transparent py-3">
                        <h6 class="m-0 fw-bold text-primary"><i class="bi bi-key-fill me-1"></i> Change Password</h6>
                    </div>
                    <div class="card-body">
                        <form action="" method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                            <input type="hidden" name="action" value="change_password">

                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Current Password</label>
                                <input type="password" class="form-control" name="current_password" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-semibold">New Password</label>
                                <input type="password" class="form-control" name="new_password" required>
                            </div>

                            <div class="mb-4">
                                <label class="form-label small fw-semibold">Confirm New Password</label>
                                <input type="password" class="form-control" name="confirm_password" required>
                            </div>

                            <button type="submit" class="btn btn-primary fw-semibold">Update Password</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
