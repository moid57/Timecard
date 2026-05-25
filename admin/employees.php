<?php
// Admin Employee Management
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

// AJAX Actions Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $action = $_POST['ajax_action'];
    $response = ['success' => false, 'message' => 'Invalid action'];

    if ($action === 'toggle_status') {
        $emp_id = (int)($_POST['id'] ?? 0);
        $new_status = $_POST['status'] === 'active' ? 'active' : 'inactive';

        try {
            $stmt = $pdo->prepare("UPDATE employees SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $emp_id]);
            
            // Get user id to log activity
            $user_stmt = $pdo->prepare("SELECT user_id, emp_id FROM employees WHERE id = ?");
            $user_stmt->execute([$emp_id]);
            $emp = $user_stmt->fetch();

            log_activity($_SESSION['user_id'], "Changed status of employee {$emp['emp_id']} to {$new_status}");
            $response = ['success' => true, 'message' => 'Status updated successfully'];
        } catch (PDOException $e) {
            $response['message'] = 'Database error: ' . $e->getMessage();
        }
    }

    if ($action === 'reset_password') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        
        try {
            // Find employee id
            $emp_stmt = $pdo->prepare("SELECT emp_id FROM employees WHERE id = ?");
            $emp_stmt->execute([$user_id]);
            $emp_id = $emp_stmt->fetchColumn();

            if ($emp_id) {
                // Set default password format: Tca@EmployeeID
                $default_pass = "Tca@" . $emp_id;
                $stmt = $pdo->prepare("UPDATE employees SET password = ? WHERE id = ?");
                $stmt->execute([$default_pass, $user_id]);

                log_activity($_SESSION['user_id'], "Reset password for employee {$emp_id}");
                $response = ['success' => true, 'message' => "Password reset to default (Tca@{$emp_id})"];
            } else {
                $response['message'] = 'Employee not found';
            }
        } catch (PDOException $e) {
            $response['message'] = 'Database error: ' . $e->getMessage();
        }
    }

    echo json_encode($response);
    exit;
}

$error = '';
$success = '';

// Normal POST CRUD handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $token = $_POST['csrf_token'] ?? '';

    if (!verify_csrf($token)) {
        $error = 'Invalid CSRF security token.';
    } else {
        if ($action === 'add') {
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $emp_id = strtoupper(trim($_POST['emp_id'] ?? ''));
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');

            if (empty($first_name) || empty($last_name) || empty($emp_id) || empty($email)) {
                $error = 'All fields except phone are required.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address.';
            } else {
                try {
                    // Check duplicates
                    $check = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE emp_id = ?");
                    $check->execute([$emp_id]);
                    if ($check->fetchColumn() > 0) {
                        $error = 'Employee ID already exists.';
                    } else {
                        $check_email = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE email = ?");
                        $check_email->execute([$email]);
                        if ($check_email->fetchColumn() > 0) {
                            $error = 'Email address is already registered.';
                        }
                    }

                    if (empty($error)) {
                        // Insert Employee profile directly
                        $default_pass = "Tca@" . $emp_id;
                        $stmt = $pdo->prepare("INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone) VALUES (?, ?, 'employee', ?, ?, ?, ?)");
                        $stmt->execute([$emp_id, $default_pass, $first_name, $last_name, $email, $phone]);

                        $success = "Employee Added successfully. Default Password: Tca@{$emp_id}";
                        log_activity($_SESSION['user_id'], "Created new employee {$emp_id}");
                    }
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }

        if ($action === 'edit') {
            $emp_db_id = (int)($_POST['id'] ?? 0);
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');

            if (empty($first_name) || empty($last_name) || empty($email)) {
                $error = 'First name, last name, and email are required.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address.';
            } else {
                try {
                    // Check duplicate email (excluding current user)
                    $check = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE email = ? AND id != ?");
                    $check->execute([$email, $emp_db_id]);
                    if ($check->fetchColumn() > 0) {
                        $error = 'Email address is already registered by another employee.';
                    } else {
                        $stmt = $pdo->prepare("UPDATE employees SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE id = ?");
                        $stmt->execute([$first_name, $last_name, $email, $phone, $emp_db_id]);
                        
                        // Get emp_id to log
                        $emp_stmt = $pdo->prepare("SELECT emp_id FROM employees WHERE id = ?");
                        $emp_stmt->execute([$emp_db_id]);
                        $emp_id = $emp_stmt->fetchColumn();

                        $success = "Employee profile updated successfully.";
                        log_activity($_SESSION['user_id'], "Updated details of employee {$emp_id}");
                    }
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }

        if ($action === 'delete') {
            $emp_db_id = (int)($_POST['id'] ?? 0);
            try {
                // Fetch emp_id first
                $stmt = $pdo->prepare("SELECT emp_id FROM employees WHERE id = ?");
                $stmt->execute([$emp_db_id]);
                $emp_id = $stmt->fetchColumn();

                if ($emp_id) {
                    $del = $pdo->prepare("DELETE FROM employees WHERE id = ?");
                    $del->execute([$emp_db_id]);

                    $success = "Employee {$emp_id} deleted successfully.";
                    log_activity($_SESSION['user_id'], "Deleted employee {$emp_id}");
                }
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Pagination & Query Setup
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';

$where = ["e.role = 'employee'"];
$params = [];

if ($search !== '') {
    $where[] = "(e.emp_id LIKE ? OR e.first_name LIKE ? OR e.last_name LIKE ? OR e.email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($status_filter !== '') {
    $where[] = "e.status = ?";
    $params[] = $status_filter;
}

$where_sql = implode(" AND ", $where);

try {
    // Get total rows for pagination
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM employees e WHERE $where_sql");
    $count_stmt->execute($params);
    $total_rows = $count_stmt->fetchColumn();
    $total_pages = ceil($total_rows / $limit);

    // Get employees page data
    $select_stmt = $pdo->prepare("
        SELECT e.*, e.emp_id as username, e.id as user_id 
        FROM employees e 
        WHERE $where_sql 
        ORDER BY e.emp_id ASC 
        LIMIT " . (int)$offset . ", " . (int)$limit
    );
    $select_stmt->execute($params);
    $employees = $select_stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error: " . $e->getMessage();
}

$page_title = 'Employee Management';
require_once __DIR__ . '/../includes/header.php';
?>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<!-- Main Content Area -->
<main class="main-content d-flex flex-column flex-grow-1">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h1 class="h3 mb-0 text-gray-800">Employee Management</h1>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
                <i class="bi bi-person-plus-fill me-1"></i> Add Employee
            </button>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 small"><?php echo e($error); ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success py-2 small"><?php echo e($success); ?></div>
        <?php endif; ?>

        <!-- Search and Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form action="" method="GET" class="row g-3">
                    <div class="col-md-5">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" name="search" placeholder="Search by name, ID or email..." value="<?php echo e($search); ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <select class="form-select" name="status">
                            <option value="">All Statuses</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active Only</option>
                            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive Only</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-secondary w-100 fw-medium">Apply Filters</button>
                        <a href="employees" class="btn btn-outline-secondary w-50">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Employees Table -->
        <div class="card mb-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.9rem;">
                        <thead class="table-light">
                            <tr>
                                <th>Emp ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($employees)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">No employees found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($employees as $emp): ?>
                                    <tr>
                                        <td><code><?php echo e($emp['emp_id']); ?></code></td>
                                        <td><span class="fw-semibold"><?php echo e($emp['first_name'] . ' ' . $emp['last_name']); ?></span></td>
                                        <td><?php echo e($emp['email']); ?></td>
                                        <td><?php echo e($emp['phone'] ?: 'N/A'); ?></td>
                                        <td>
                                            <!-- Status Toggle -->
                                            <div class="form-check form-switch mb-0">
                                                <input class="form-check-input status-toggle-switch" type="checkbox" role="switch" 
                                                       data-id="<?php echo $emp['id']; ?>" 
                                                       <?php echo $emp['status'] === 'active' ? 'checked' : ''; ?>>
                                                <span class="badge <?php echo $emp['status'] === 'active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'; ?> status-badge-label">
                                                    <?php echo ucfirst(e($emp['status'])); ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td class="text-end">
                                            <div class="btn-group">
                                                <button class="btn btn-outline-secondary btn-sm" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#editEmployeeModal" 
                                                        data-id="<?php echo $emp['id']; ?>"
                                                        data-fname="<?php echo e($emp['first_name']); ?>"
                                                        data-lname="<?php echo e($emp['last_name']); ?>"
                                                        data-email="<?php echo e($emp['email']); ?>"
                                                        data-phone="<?php echo e($emp['phone']); ?>"
                                                        title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-outline-warning btn-sm reset-pw-btn" 
                                                        data-user-id="<?php echo $emp['user_id']; ?>" 
                                                        title="Reset Password">
                                                    <i class="bi bi-key"></i>
                                                </button>
                                                <button class="btn btn-outline-danger btn-sm" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#deleteEmployeeModal" 
                                                        data-id="<?php echo $emp['id']; ?>"
                                                        data-empid="<?php echo e($emp['emp_id']); ?>"
                                                        title="Delete">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation" class="d-flex justify-content-center">
                <ul class="pagination pagination-sm shadow-sm">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">Previous</a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">Next</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</main>

<!-- ADD EMPLOYEE MODAL -->
<div class="modal fade" id="addEmployeeModal" tabindex="-1" aria-labelledby="addEmployeeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title" id="addEmployeeModalLabel">Add New Employee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">First Name</label>
                            <input type="text" class="form-control" name="first_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Last Name</label>
                            <input type="text" class="form-control" name="last_name" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Employee ID (Username)</label>
                            <input type="text" class="form-control" name="emp_id" placeholder="e.g. EMP161" required>
                            <small class="text-muted" style="font-size:0.75rem;">Default password will be set to: Tca@(EmployeeID)</small>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Email Address</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Phone Number</label>
                            <input type="text" class="form-control" name="phone">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Employee</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- EDIT EMPLOYEE MODAL -->
<div class="modal fade" id="editEmployeeModal" tabindex="-1" aria-labelledby="editEmployeeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="" method="POST" id="editForm">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editEmpId">
                <div class="modal-header">
                    <h5 class="modal-title" id="editEmployeeModalLabel">Edit Employee Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">First Name</label>
                            <input type="text" class="form-control" name="first_name" id="editFirstName" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Last Name</label>
                            <input type="text" class="form-control" name="last_name" id="editLastName" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Email Address</label>
                            <input type="email" class="form-control" name="email" id="editEmail" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Phone Number</label>
                            <input type="text" class="form-control" name="phone" id="editPhone">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- DELETE CONFIRMATION MODAL -->
<div class="modal fade" id="deleteEmployeeModal" tabindex="-1" aria-labelledby="deleteEmployeeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteEmpId">
                <div class="modal-header">
                    <h5 class="modal-title text-danger" id="deleteEmployeeModalLabel"><i class="bi bi-exclamation-triangle-fill me-1"></i> Delete Employee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <p class="mb-0">Are you sure you want to permanently delete employee <strong id="deleteEmpIdLabel"></strong>?</p>
                    <p class="text-danger small mt-2"><i class="bi bi-info-circle"></i> This will delete all associated timesheets, tasks, and history.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Confirm Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Populate Edit Modal
    const editModal = document.getElementById('editEmployeeModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            document.getElementById('editEmpId').value = button.getAttribute('data-id');
            document.getElementById('editFirstName').value = button.getAttribute('data-fname');
            document.getElementById('editLastName').value = button.getAttribute('data-lname');
            document.getElementById('editEmail').value = button.getAttribute('data-email');
            document.getElementById('editPhone').value = button.getAttribute('data-phone');
        });
    }

    // 2. Populate Delete Modal
    const deleteModal = document.getElementById('deleteEmployeeModal');
    if (deleteModal) {
        deleteModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            document.getElementById('deleteEmpId').value = button.getAttribute('data-id');
            document.getElementById('deleteEmpIdLabel').textContent = button.getAttribute('data-empid');
        });
    }

    // 3. Status Switch Ajax Toggle
    const switches = document.querySelectorAll('.status-toggle-switch');
    switches.forEach(sw => {
        sw.addEventListener('change', function () {
            const id = this.getAttribute('data-id');
            const status = this.checked ? 'active' : 'inactive';
            const badge = this.parentNode.querySelector('.status-badge-label');
            
            toggleLoader(true);

            const params = new URLSearchParams();
            params.append('ajax_action', 'toggle_status');
            params.append('id', id);
            params.append('status', status);

            fetch('employees', {
                method: 'POST',
                body: params,
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => res.json())
            .then(data => {
                toggleLoader(false);
                if (data.success) {
                    showToast(data.message, 'success');
                    // Update Badge visuals
                    if (status === 'active') {
                        badge.className = 'badge bg-success-subtle text-success status-badge-label';
                        badge.textContent = 'Active';
                    } else {
                        badge.className = 'badge bg-danger-subtle text-danger status-badge-label';
                        badge.textContent = 'Inactive';
                    }
                } else {
                    showToast(data.message, 'danger');
                    // Revert switch state
                    this.checked = !this.checked;
                }
            })
            .catch(err => {
                toggleLoader(false);
                showToast('Failed to update status', 'danger');
                this.checked = !this.checked;
            });
        });
    });

    // 4. Reset Password Ajax Toggle
    const resetButtons = document.querySelectorAll('.reset-pw-btn');
    resetButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            if (!confirm('Are you sure you want to reset password for this employee?')) return;
            const userId = this.getAttribute('data-user-id');
            
            toggleLoader(true);

            const params = new URLSearchParams();
            params.append('ajax_action', 'reset_password');
            params.append('user_id', userId);

            fetch('employees', {
                method: 'POST',
                body: params,
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => res.json())
            .then(data => {
                toggleLoader(false);
                if (data.success) {
                    showToast(data.message, 'success');
                } else {
                    showToast(data.message, 'danger');
                }
            })
            .catch(err => {
                toggleLoader(false);
                showToast('Failed to reset password', 'danger');
            });
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
