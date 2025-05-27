<?php
// Set page title
$pageTitle = 'Admin Panel';

// Include configuration and required functions
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/auth-functions.php';

// Require admin access
requireAdmin();

// Get database connection
$conn = getDbConnection();

// Get current page from query string
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

// Validate page parameter
$allowedPages = ['dashboard', 'stations', 'users', 'bookings', 'reports', 'maintenance'];
if (!in_array($page, $allowedPages)) {
    $page = 'dashboard';
}

// Include header
require_once dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="container">
    <div class="admin-container">
        <!-- Sidebar -->
        <div class="admin-sidebar">
            <?php require_once dirname(dirname(__DIR__)) . '/includes/admin-navbar.php'; ?>
        </div>

        <!-- Main Content -->
        <div class="admin-content">
            <?php
            // Include the appropriate admin page based on the query parameter
            switch ($page) {
                case 'dashboard':
                    require_once dirname(__DIR__) . '/admin/admin-dashboard.php';
                    break;
                case 'stations':
                    require_once dirname(__DIR__) . '/admin/admin-stations.php';
                    break;
                case 'users':
                    require_once dirname(__DIR__) . '/admin/admin-users.php';
                    break;
                case 'bookings':
                    require_once dirname(__DIR__) . '/admin/admin-bookings.php';
                    break;
                case 'reports':
                    require_once dirname(__DIR__) . '/admin/admin-reports.php';
                    break;
                case 'maintenance':
                    require_once dirname(__DIR__) . '/admin/admin-maintenance.php';
                    break;
                default:
                    require_once dirname(__DIR__) . '/admin/admin-dashboard.php';
                    break;
            }
            ?>
        </div>
    </div>
</div>

<style>
    .admin-container {
        display: flex;
        gap: var(--space-6);
    }

    .admin-sidebar {
        flex: 0 0 250px;
        background-color: var(--white);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow);
        padding: var(--space-4);
        height: fit-content;
    }

    .admin-content {
        flex: 1;
    }

    @media (max-width: 768px) {
        .admin-container {
            flex-direction: column;
        }

        .admin-sidebar {
            flex: 0 0 auto;
            width: 100%;
        }
    }
</style>

<?php
// Include footer
require_once dirname(dirname(__DIR__)) . '/includes/footer.php';
?>