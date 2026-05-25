<?php
// Employee Dashboard
$page_title = 'Employee Dashboard';
require_once __DIR__ . '/../includes/header.php';

// Secure page access
if (is_admin()) {
    header("Location: " . APP_ROOT . "admin/dashboard");
    exit;
}

$user_id = $_SESSION['user_id'];
$error = '';

$display_name = $_SESSION['username'];
if (isset($_SESSION['full_name'])) {
    $display_name = $_SESSION['full_name'];
} else {
    try {
        $name_stmt = $pdo->prepare("SELECT first_name, last_name FROM employees WHERE id = ?");
        $name_stmt->execute([$user_id]);
        $emp_profile = $name_stmt->fetch();
        if ($emp_profile) {
            $display_name = $emp_profile['first_name'] . ' ' . $emp_profile['last_name'];
            $_SESSION['full_name'] = $display_name;
        }
    } catch (PDOException $e) {
        // fallback to username
    }
}

try {
    // 1. Fetch Today's Tasks Count
    $today_tasks_stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status = 'pending' AND deadline = CURDATE()");
    $today_tasks_stmt->execute([$user_id]);
    $today_tasks_count = $today_tasks_stmt->fetchColumn();

    // 2. Fetch Pending Tasks Count
    $pending_tasks_stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status = 'pending'");
    $pending_tasks_stmt->execute([$user_id]);
    $pending_tasks_count = $pending_tasks_stmt->fetchColumn();

    // 3. Fetch Monthly Hours Summary
    $monthly_hours_stmt = $pdo->prepare("
        SELECT SUM(duration) 
        FROM timesheets 
        WHERE user_id = ? AND date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
    ");
    $monthly_hours_stmt->execute([$user_id]);
    $monthly_hours = $monthly_hours_stmt->fetchColumn() ?? 0;

    // 4. Fetch Recent Timesheet Entries (Last 5)
    $recent_timesheets_stmt = $pdo->prepare("
        SELECT t.*, tk.title as task_title 
        FROM timesheets t 
        LEFT JOIN tasks tk ON t.task_id = tk.id 
        WHERE t.user_id = ? 
        ORDER BY t.date DESC, t.id DESC 
        LIMIT 5
    ");
    $recent_timesheets_stmt->execute([$user_id]);
    $recent_timesheets = $recent_timesheets_stmt->fetchAll();

    // 5. Fetch Top 3 Pending Tasks
    $tasks_preview_stmt = $pdo->prepare("
        SELECT * FROM tasks 
        WHERE assigned_to = ? AND status = 'pending' 
        ORDER BY deadline ASC, priority DESC 
        LIMIT 3
    ");
    $tasks_preview_stmt->execute([$user_id]);
    $tasks_preview = $tasks_preview_stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Employee dashboard error: " . $e->getMessage());
    $error = "Error loading dashboard metrics.";
}
?>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<!-- Main Content Area -->
<main class="main-content d-flex flex-column flex-grow-1">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
            <div>
                <h1 class="h3 mb-1 text-gray-800">Welcome Back, <?php echo e($display_name); ?></h1>
                <p class="text-muted small mb-0">Here is your schedule and timesheet overview.</p>
            </div>
            <span class="text-muted small"><i class="bi bi-clock me-1"></i> Today is <?php echo date('D, M d, Y'); ?></span>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 small"><?php echo e($error); ?></div>
        <?php endif; ?>

        <!-- KPI Cards Row -->
        <div class="row g-3 mb-4">
            <!-- Today's Tasks -->
            <div class="col-md-4">
                <div class="card h-100 border-start border-danger border-4 hover-lift">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted text-uppercase small fw-bold">Due Today</span>
                            <h3 class="mb-0 fw-bold mt-1"><?php echo $today_tasks_count; ?> <span class="fs-6 fw-normal text-muted">tasks</span></h3>
                        </div>
                        <div class="bg-danger bg-opacity-10 text-danger p-3 rounded-circle">
                            <i class="bi bi-calendar-event-fill fs-3"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pending To-Dos -->
            <div class="col-md-4">
                <div class="card h-100 border-start border-warning border-4 hover-lift">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted text-uppercase small fw-bold">Pending To-Dos</span>
                            <h3 class="mb-0 fw-bold mt-1"><?php echo $pending_tasks_count; ?> <span class="fs-6 fw-normal text-muted">tasks</span></h3>
                        </div>
                        <div class="bg-warning bg-opacity-10 text-warning p-3 rounded-circle">
                            <i class="bi bi-clipboard2-check-fill fs-3"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Monthly Worked Hours -->
            <div class="col-md-4">
                <div class="card h-100 border-start border-success border-4 hover-lift">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted text-uppercase small fw-bold">Hours Logged This Month</span>
                            <h3 class="mb-0 fw-bold mt-1"><?php echo number_format($monthly_hours, 1); ?> <span class="fs-6 fw-normal text-muted">hrs</span></h3>
                        </div>
                        <div class="bg-success bg-opacity-10 text-success p-3 rounded-circle">
                            <i class="bi bi-hourglass-split fs-3"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Left Column: Pending Tasks preview -->
            <div class="col-xl-6">
                <div class="card h-100">
                    <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 fw-bold text-primary">Your Tasks Preview</h6>
                        <a href="my-tasks" class="btn btn-sm btn-outline-primary fw-semibold">View All Tasks</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($tasks_preview)): ?>
                            <div class="text-center py-5 text-muted">
                                <i class="bi bi-emoji-smile fs-2 mb-2"></i>
                                <p class="mb-0">Awesome! No pending tasks on your list.</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($tasks_preview as $task): ?>
                                    <div class="list-group-item px-0 py-3 d-flex justify-content-between align-items-start gap-2 flex-wrap flex-sm-nowrap">
                                        <div>
                                            <h6 class="fw-bold mb-1"><?php echo e($task['title']); ?></h6>
                                            <p class="text-muted small mb-1 text-truncate-2"><?php echo e($task['description'] ?: 'No details provided'); ?></p>
                                            <span class="badge bg-secondary-subtle text-secondary small">Deadline: <?php echo e($task['deadline']); ?></span>
                                            <span class="badge <?php echo $task['priority'] === 'high' ? 'bg-danger-subtle text-danger' : ($task['priority'] === 'medium' ? 'bg-warning-subtle text-warning' : 'bg-info-subtle text-info'); ?> small">
                                                <?php echo ucfirst(e($task['priority'])); ?>
                                            </span>
                                        </div>
                                        <a href="my-tasks" class="btn btn-sm btn-primary">Complete</a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column: Recent Timesheet entries -->
            <div class="col-xl-6">
                <div class="card h-100">
                    <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 fw-bold text-primary">Recent Timesheet Entries</h6>
                        <a href="add-timesheet" class="btn btn-sm btn-success fw-semibold"><i class="bi bi-plus-circle me-1"></i> Log Hours</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_timesheets)): ?>
                            <p class="text-muted text-center py-5">No timesheet records logged recently.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Date</th>
                                            <th>Hours</th>
                                            <th>Description</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_timesheets as $ts): ?>
                                            <tr>
                                                <td><span class="fw-semibold"><?php echo e($ts['date']); ?></span></td>
                                                <td><span class="badge bg-primary-subtle text-primary"><?php echo number_format($ts['duration'], 1); ?> hrs</span></td>
                                                <td>
                                                    <span class="text-truncate d-inline-block" style="max-width: 150px;" title="<?php echo e($ts['description']); ?>">
                                                        <?php echo e($ts['description']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $ts['status'] === 'approved' ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning'; ?>">
                                                        <?php echo ucfirst(e($ts['status'])); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
