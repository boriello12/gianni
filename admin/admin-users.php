<?php
// Set page title
$pageTitle = 'Manage Users';

// Include configuration and required functions
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/admin-navbar.php';require_once dirname(__DIR__) . '/includes/admin-navbar.php';

// Require admin access
requireAdmin();

// Get database connection
$conn = getDbConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'Invalid request.');
        redirect('admin/users.php');
    }

    $action = $_POST['action'] ?? '';
    $userId = (int)($_POST['user_id'] ?? 0);

    switch ($action) {
        case 'toggle_status':
            $active = (int)($_POST['active'] ?? 0);
            $sql = "UPDATE Users SET active = ? WHERE user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $active, $userId);
            
            if ($stmt->execute()) {
                setFlashMessage('success', 'User status updated successfully.');
            } else {
                setFlashMessage('error', 'Error updating user status.');
            }
            break;

        case 'delete_user':
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Delete user's bookings
                $sql = "DELETE FROM Bookings WHERE user_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $userId);
                $stmt->execute();

                // Delete user's charging history
                $sql = "DELETE FROM Chargings WHERE user_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $userId);
                $stmt->execute();

                // Delete user
                $sql = "DELETE FROM Users WHERE user_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $userId);
                $stmt->execute();

                $conn->commit();
                setFlashMessage('success', 'User deleted successfully.');
            } catch (Exception $e) {
                $conn->rollback();
                setFlashMessage('error', 'Error deleting user.');
            }
            break;
    }

    redirect('admin/users.php');
}

// Get users list with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

$totalUsers = $conn->query("SELECT COUNT(*) as count FROM Users")->fetch_assoc()['count'];
$totalPages = ceil($totalUsers / $perPage);

$users = $conn->query("
    SELECT u.*, 
           COUNT(DISTINCT b.booking_id) as total_bookings,
           COUNT(DISTINCT c.charging_id) as total_charges
    FROM Users u
    LEFT JOIN Bookings b ON u.user_id = b.user_id
    LEFT JOIN Chargings c ON u.user_id = c.user_id
    GROUP BY u.user_id
    ORDER BY u.user_id DESC
    LIMIT $offset, $perPage
")->fetch_all(MYSQLI_ASSOC);

// Generate CSRF token
$csrfToken = generateCsrfToken();

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
            <div class="page-header">
                <h1>Manage Users</h1>
            </div>

            <!-- Users List -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Bookings</th>
                                    <th>Charges</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?= $user['user_id'] ?></td>
                                    <td><?= htmlspecialchars($user['name']) ?></td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td><?= $user['total_bookings'] ?></td>
                                    <td><?= $user['total_charges'] ?></td>
                                    <td>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                            <input type="hidden" name="active" value="<?= $user['active'] ? '0' : '1' ?>">
                                            <button type="submit" class="btn btn-sm <?= $user['active'] ? 'btn-success' : 'btn-warning' ?>">
                                                <?= $user['active'] ? 'Active' : 'Inactive' ?>
                                            </button>
                                        </form>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-primary view-user" 
                                                data-user='<?= json_encode($user) ?>'
                                                data-bs-toggle="modal" 
                                                data-bs-target="#viewUserModal">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <div class="pagination-container">
                        <nav>
                            <ul class="pagination">
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- View User Modal -->
<div class="modal fade" id="viewUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">User Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="user-details">
                    <p><strong>Name:</strong> <span id="view_name"></span></p>
                    <p><strong>Email:</strong> <span id="view_email"></span></p>
                    <p><strong>Total Bookings:</strong> <span id="view_bookings"></span></p>
                    <p><strong>Total Charges:</strong> <span id="view_charges"></span></p>
                    <p><strong>Status:</strong> <span id="view_status"></span></p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle view user button clicks
    document.querySelectorAll('.view-user').forEach(button => {
        button.addEventListener('click', function() {
            const user = JSON.parse(this.dataset.user);
            
            // Fill the modal with user data
            document.getElementById('view_name').textContent = user.name;
            document.getElementById('view_email').textContent = user.email;
            document.getElementById('view_bookings').textContent = user.total_bookings;
            document.getElementById('view_charges').textContent = user.total_charges;
            document.getElementById('view_status').textContent = user.active ? 'Active' : 'Inactive';
        });
    });
});
</script>

<?php
// Include footer
require_once dirname(dirname(__DIR__)) . '/includes/footer.php';
?>