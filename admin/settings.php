<?php
// Admin Settings Page
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$settings_file = __DIR__ . '/../config/settings.json';

// Initialize settings file with defaults if not exists
if (!file_exists($settings_file)) {
    $defaults = [
        'max_daily_hours' => 12,
        'enable_approvals' => true
    ];
    file_put_contents($settings_file, json_encode($defaults, JSON_PRETTY_PRINT));
}

$settings = json_decode(file_get_contents($settings_file), true);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    if (!verify_csrf($token)) {
        $error = 'Invalid CSRF security token.';
    } else {
        // Change Admin Password
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
                    // Fetch Admin User info
                    $stmt = $pdo->prepare("SELECT * FROM employees WHERE emp_id = 'Admin'");
                    $stmt->execute();
                    $admin = $stmt->fetch();

                    if ($admin && password_verify($current_password, $admin['password'])) {
                        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                        $update = $pdo->prepare("UPDATE employees SET password = ? WHERE emp_id = 'Admin'");
                        $update->execute([$new_hash]);
                        
                        log_activity($_SESSION['user_id'], "Admin changed account password");
                        $success = 'Admin password updated successfully.';
                    } else {
                        $error = 'Current password is incorrect.';
                    }
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }

        // Save General System Settings
        if ($action === 'general_settings') {
            $max_hours = (float)($_POST['max_daily_hours'] ?? 12);
            $enable_approvals = isset($_POST['enable_approvals']) ? true : false;

            if ($max_hours <= 0 || $max_hours > 24) {
                $error = 'Daily hours threshold must be between 1 and 24 hours.';
            } else {
                $settings['max_daily_hours'] = $max_hours;
                $settings['enable_approvals'] = $enable_approvals;

                if (file_put_contents($settings_file, json_encode($settings, JSON_PRETTY_PRINT))) {
                    log_activity($_SESSION['user_id'], "Admin updated general system settings");
                    $success = 'System settings updated successfully.';
                } else {
                    $error = 'Failed to write settings file. Check file permissions.';
                }
            }
        }
    }
}

$page_title = 'System Settings';
require_once __DIR__ . '/../includes/header.php';
?>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<!-- Main Content Area -->
<main class="main-content d-flex flex-column flex-grow-1">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <!-- Header -->
        <div class="mb-4">
            <h1 class="h3 mb-0 text-gray-800">System Settings</h1>
            <p class="text-muted small">Configure application preferences and credentials.</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 small"><?php echo e($error); ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success py-2 small"><?php echo e($success); ?></div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- General Settings -->
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header bg-transparent py-3">
                        <h6 class="m-0 fw-bold text-primary"><i class="bi bi-sliders me-1"></i> General System Settings</h6>
                    </div>
                    <div class="card-body">
                        <form action="" method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                            <input type="hidden" name="action" value="general_settings">

                            <div class="mb-4">
                                <label class="form-label small fw-semibold">Max Daily Hours Limit Warning</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" name="max_daily_hours" step="0.5" min="1" max="24" value="<?php echo e($settings['max_daily_hours']); ?>" required>
                                    <span class="input-group-text">Hours</span>
                                </div>
                                <small class="text-muted d-block mt-1">Warns employees when entering timesheet records that cause the daily total to exceed this value.</small>
                            </div>

                            <div class="mb-4">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="enableApprovals" name="enable_approvals" <?php echo $settings['enable_approvals'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label small fw-semibold" for="enableApprovals">Require Timesheet Approval</label>
                                </div>
                                <small class="text-muted d-block mt-1">If enabled, timesheets logged by employees start in 'Pending' state and require admin clearance.</small>
                            </div>

                            <button type="submit" class="btn btn-primary fw-semibold">Save Settings</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Security Settings / Password Change -->
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header bg-transparent py-3">
                        <h6 class="m-0 fw-bold text-primary"><i class="bi bi-shield-lock-fill me-1"></i> Change Admin Password</h6>
                    </div>
                    <div class="card-body">
                        <form action="" method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                            <input type="hidden" name="action" value="change_password">

                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Current Admin Password</label>
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
