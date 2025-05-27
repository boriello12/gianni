<?php
// Set page title
$pageTitle = 'Manage Bookings';

// Include configuration and required functions
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/admin-navbar.php';

// Require admin access
requireAdmin();

// Get database connection
$conn = getDbConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'Invalid request.');
        redirect('admin/bookings.php');
    }

    $action = $_POST['action'] ?? '';
    $bookingId = (int)($_POST['booking_id'] ?? 0);

    switch ($action) {
        case 'cancel_booking':
            $sql = "UPDATE Bookings SET status = 'cancelled' WHERE booking_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $bookingId);
            
            if ($stmt->execute()) {
                setFlashMessage('success', 'Booking cancelled successfully.');
            } else {
                setFlashMessage('error', 'Error cancelling booking.');
            }
            break;
    }

    redirect('admin/bookings.php');
}

// Get bookings list with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

$totalBookings = $conn->query("SELECT COUNT(*) as count FROM Bookings")->fetch_assoc()['count'];
$totalPages = ceil($totalBookings / $perPage);

$bookings = $conn->query("
    SELECT b.*, 
           u.name as user_name,
           s.address_street,
           s.address_city,
           cp.charging_point_state
    FROM Bookings b
    JOIN Users u ON b.user_id = u.user_id
    JOIN Charging_Points cp ON b.charging_point_id = cp.charging_point_id
    JOIN Stations s ON cp.station_id = s.station_id
    ORDER BY b.booking_datetime DESC
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
                <h1>Manage Bookings</h1>
            </div>

            <!-- Bookings List -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>User</th>
                                    <th>Station</th>
                                    <th>Date & Time</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bookings as $booking): ?>
                                <tr>
                                    <td><?= $booking['booking_id'] ?></td>
                                    <td><?= htmlspecialchars($booking['user_name']) ?></td>
                                    <td>
                                        <?= htmlspecialchars($booking['address_street']) ?>,
                                        <?= htmlspecialchars($booking['address_city']) ?>
                                    </td>
                                    <td><?= date('M j, Y g:i A', strtotime($booking['booking_datetime'])) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $booking['status'] === 'active' ? 'success' : 
                                                              ($booking['status'] === 'cancelled' ? 'danger' : 'warning') ?>">
                                            <?= ucfirst($booking['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-primary view-booking" 
                                                data-booking='<?= json_encode($booking) ?>'
                                                data-bs-toggle="modal" 
                                                data-bs-target="#viewBookingModal">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($booking['status'] !== 'cancelled'): ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to cancel this booking?');">
                                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                            <input type="hidden" name="action" value="cancel_booking">
                                            <input type="hidden" name="booking_id" value="<?= $booking['booking_id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
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

<!-- View Booking Modal -->
<div class="modal fade" id="viewBookingModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Booking Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="booking-details">
                    <p><strong>Booking ID:</strong> <span id="view_booking_id"></span></p>
                    <p><strong>User:</strong> <span id="view_user_name"></span></p>
                    <p><strong>Station:</strong> <span id="view_station"></span></p>
                    <p><strong>Date & Time:</strong> <span id="view_datetime"></span></p>
                    <p><strong>Status:</strong> <span id="view_status"></span></p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle view booking button clicks
    document.querySelectorAll('.view-booking').forEach(button => {
        button.addEventListener('click', function() {
            const booking = JSON.parse(this.dataset.booking);
            
            // Fill the modal with booking data
            document.getElementById('view_booking_id').textContent = booking.booking_id;
            document.getElementById('view_user_name').textContent = booking.user_name;
            document.getElementById('view_station').textContent = `${booking.address_street}, ${booking.address_city}`;
            document.getElementById('view_datetime').textContent = new Date(booking.booking_datetime).toLocaleString();
            document.getElementById('view_status').textContent = booking.status.charAt(0).toUpperCase() + booking.status.slice(1);
        });
    });
});
</script>

<?php
// Include footer
require_once dirname(dirname(__DIR__)) . '/includes/footer.php';
?>