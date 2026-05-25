// Custom Application Scripts

document.addEventListener('DOMContentLoaded', function () {
    // 1. Sidebar Toggle Logic
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');

    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function (e) {
            e.preventDefault();
            if (window.innerWidth > 768) {
                sidebar.classList.toggle('collapsed');
            } else {
                sidebar.classList.toggle('show-mobile');
            }
        });
    }

    // Close mobile sidebar when clicking outside
    document.addEventListener('click', function (e) {
        if (window.innerWidth <= 768 && sidebar && sidebarToggle) {
            if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target) && sidebar.classList.contains('show-mobile')) {
                sidebar.classList.remove('show-mobile');
            }
        }
    });

    // 2. Theme Toggle Logic
    const themeToggle = document.getElementById('themeToggle');
    const themeIcon = document.getElementById('themeIcon');

    if (themeToggle && themeIcon) {
        themeToggle.addEventListener('click', function () {
            const currentTheme = document.documentElement.getAttribute('data-bs-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            document.documentElement.setAttribute('data-bs-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeIcon(newTheme);
        });

        // Sync icon on page load
        const activeTheme = document.documentElement.getAttribute('data-bs-theme') || 'light';
        updateThemeIcon(activeTheme);
    }

    function updateThemeIcon(theme) {
        if (theme === 'dark') {
            themeIcon.className = 'bi bi-moon-stars-fill fs-5';
        } else {
            themeIcon.className = 'bi bi-sun-fill fs-5';
        }
    }

    // 3. Clear Notifications Handler
    const clearNotiBtn = document.getElementById('clearNotificationsBtn');
    if (clearNotiBtn) {
        clearNotiBtn.addEventListener('click', function () {
            // Find base path dynamically (e.g. /timecard/ or /)
            const appRoot = getAppRootPath();
            fetch(appRoot + 'includes/clear_notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Notifications marked as read', 'success');
                    // Reload page after a delay or dynamically empty items
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showToast(data.message || 'Error clearing notifications', 'danger');
                }
            })
            .catch(err => {
                console.error(err);
                showToast('Failed to clear notifications', 'danger');
            });
        });
    }

    // Helper to extract app root path from standard links
    function getAppRootPath() {
        const sidebarLink = document.querySelector('#sidebar .nav-link');
        if (sidebarLink) {
            const href = sidebarLink.getAttribute('href');
            // e.g. /timecard/admin/dashboard
            const parts = href.split('/');
            // Remove 'admin/dashboard' or 'employee/dashboard'
            parts.pop();
            parts.pop();
            return parts.join('/') + '/';
        }
        return '/';
    }
});

// Toast notification helper
function showToast(message, type = 'success') {
    const toastEl = document.getElementById('liveToast');
    const toastMsg = document.getElementById('toastMessage');
    
    if (toastEl && toastMsg) {
        toastMsg.innerText = message;
        
        // Remove existing type classes
        toastEl.className = 'toast align-items-center border-0';
        
        // Add color class
        if (type === 'success') {
            toastEl.classList.add('text-bg-success');
        } else if (type === 'danger') {
            toastEl.classList.add('text-bg-danger');
        } else if (type === 'warning') {
            toastEl.classList.add('text-bg-warning');
        } else {
            toastEl.classList.add('text-bg-info');
        }
        
        const toast = new bootstrap.Toast(toastEl);
        toast.show();
    }
}

// Show/Hide loader overlay helper
function toggleLoader(show = true) {
    let overlay = document.getElementById('ajaxLoaderOverlay');
    if (show) {
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'ajaxLoaderOverlay';
            overlay.className = 'spinner-overlay';
            overlay.innerHTML = '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>';
            document.body.appendChild(overlay);
        }
    } else {
        if (overlay) {
            overlay.remove();
        }
    }
}
