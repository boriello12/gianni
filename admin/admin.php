<?php
// Set page title
$pageTitle = 'Admin Panel';

// Include configuration and required functions
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/auth-functions.php';


// Require admin access
requireAdmin();

// Get database connection
$conn = getDbConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'Invalid request.');
        redirect('admin.php');
    }

    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add_station':
            // Add new station
            $street = sanitizeInput($_POST['address_street']);
            $city = sanitizeInput($_POST['address_city']);
            $municipality = sanitizeInput($_POST['address_municipality']);
            $civic = sanitizeInput($_POST['address_civic_num']);
            $zipcode = sanitizeInput($_POST['address_zipcode']);
            $columns = (int)$_POST['columns_num'];
            $lat = (float)$_POST['latitude'];
            $lng = (float)$_POST['longitude'];

            $sql = "INSERT INTO Stations (address_street, address_city, address_municipality,
                    address_civic_num, address_zipcode, columns_num, latitude, longitude)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssidd", $street, $city, $municipality, $civic, $zipcode, $columns, $lat, $lng);

            if ($stmt->execute()) {
                // Add charging points for the new station
                $stationId = $stmt->insert_id;
                for ($i = 0; $i < $columns * 2; $i++) { // 2 charging points per column
                    $sql = "INSERT INTO Charging_Points (charging_point_state, slots_num, station_id)
                            VALUES ('available', 2, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $stationId);
                    $stmt->execute();
                }
                setFlashMessage('success', 'Station added successfully.');
            } else {
                setFlashMessage('error', 'Error adding station.');
            }
            break;

        case 'delete_station':
            $stationId = (int)$_POST['station_id'];

            // Start transaction
            $conn->begin_transaction();

            try {
                // Delete charging points first
                $sql = "DELETE FROM Charging_Points WHERE station_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $stationId);
                $stmt->execute();

                // Then delete station
                $sql = "DELETE FROM Stations WHERE station_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $stationId);
                $stmt->execute();

                $conn->commit();
                setFlashMessage('success', 'Station deleted successfully.');
            } catch (Exception $e) {
                $conn->rollback();
                setFlashMessage('error', 'Error deleting station.');
            }
            break;

        case 'toggle_bookings':
            $enabled = $_POST['enabled'] === '1';
            // Update system settings
            $sql = "UPDATE Settings SET value = ? WHERE name = 'bookings_enabled'";
            $stmt = $conn->prepare($sql);
            $value = $enabled ? '1' : '0';
            $stmt->bind_param("s", $value);

            if ($stmt->execute()) {
                setFlashMessage('success', 'Booking system ' . ($enabled ? 'enabled' : 'disabled') . ' successfully.');
            } else {
                setFlashMessage('error', 'Error updating booking system status.');
            }
            break;

        case 'update_user':
            $userId = (int)$_POST['user_id'];
            $active = $_POST['active'] === '1';

            $sql = "UPDATE Users SET active = ? WHERE user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $active, $userId);

            if ($stmt->execute()) {
                setFlashMessage('success', 'User status updated successfully.');
            } else {
                setFlashMessage('error', 'Error updating user status.');
            }
            break;
    }

    // Redirect to prevent form resubmission
    redirect('admin.php');
}

// Get statistics
$stats = [
    'users' => $conn->query("SELECT COUNT(*) as count FROM Users")->fetch_assoc()['count'],
    'stations' => $conn->query("SELECT COUNT(*) as count FROM Stations")->fetch_assoc()['count'],
    'bookings' => $conn->query("SELECT COUNT(*) as count FROM Bookings")->fetch_assoc()['count'],
    'active_bookings' => $conn->query("SELECT COUNT(*) as count FROM Bookings WHERE booking_datetime >= NOW()")->fetch_assoc()['count']
];

// Get recent activity
$recentActivity = $conn->query("
    SELECT 'booking' as type, b.booking_datetime as datetime, u.name as user_name,
           s.address_street as location
    FROM Bookings b
    JOIN Users u ON b.user_id = u.user_id
    JOIN Charging_Points cp ON b.charging_point_id = cp.charging_point_id
    JOIN Stations s ON cp.station_id = s.station_id
    ORDER BY b.booking_datetime DESC
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// Generate CSRF token
$csrfToken = generateCsrfToken();

// Include header
require_once dirname(__DIR__) . '/includes/header.php';
?>




<div class="container">
    <div class="admin-container">
        <!-- Sidebar -->
        <div class="admin-sidebar">
            <?php require_once dirname(__DIR__) . '/includes/admin-navbar.php'; ?>
        </div>

        <!-- Main Content -->
        <div class="admin-content">
            <?php
            $page = $_GET['page'] ?? 'dashboard';
            switch ($page) {
                case 'dashboard':
                    require_once 'admin-dashboard.php';
                    break;
                case 'stations':
                    require_once 'admin-stations.php';
                    break;
                case 'users':
                    require_once 'admin-users.php';
                    break;
                case 'bookings':
                    require_once 'admin-bookings.php';
                    break;
                case 'reports':
                    require_once 'admin-reports.php';
                    break;
                case 'maintenance':
                    require_once 'admin-maintenance.php';
                    break;
                default:
                    require_once 'admin-dashboard.php';
                    break;
            }
            ?>
        </div>
    </div>
</div>

<style>
    /* Activity List */
    .activity-list {
        display: flex;
        flex-direction: column;
        gap: var(--space-4);
    }

    .activity-item {
        display: flex;
        align-items: flex-start;
        gap: var(--space-4);
        padding: var(--space-4);
        background-color: var(--white);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-sm);
        transition: all var(--transition-fast);
    }

    .activity-item:hover {
        transform: translateX(5px);
        box-shadow: var(--shadow-md);
    }

    .activity-icon {
        width: 40px;
        height: 40px;
        background-color: var(--primary-light);
        color: var(--white);
        border-radius: var(--radius-full);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
    }

    .activity-content {
        flex: 1;
    }

    .activity-title {
        font-weight: 600;
        color: var(--gray-900);
        margin-bottom: var(--space-1);
    }

    .activity-details {
        font-size: 0.875rem;
        color: var(--gray-600);
    }

    /* Form Styling */
    .form-section {
        margin-bottom: var(--space-6);
        padding-bottom: var(--space-6);
        border-bottom: 1px solid var(--gray-200);
    }

    .form-section:last-child {
        margin-bottom: 0;
        padding-bottom: 0;
        border-bottom: none;
    }

    .form-row {
        display: flex;
        gap: var(--space-4);
        margin-bottom: var(--space-4);
    }

    .form-group {
        margin-bottom: var(--space-4);
    }

    /* Table Styling */
    .table-responsive {
        margin: 0 calc(var(--space-4) * -1);
    }

    .table {
        min-width: 800px;
    }

    /* Card Styling */
    .card {
        margin-bottom: var(--space-6);
    }

    .card:last-child {
        margin-bottom: 0;
    }

    /* Stile per il menu di navigazione */
    .admin-nav ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .admin-nav li {
        margin-bottom: 10px;
    }

    .admin-nav a {
        display: block;
        padding: 10px;
        background-color: #f8f9fa;
        color: #333;
        text-decoration: none;
        border-radius: 5px;
        transition: background-color 0.3s;
    }

    .admin-nav a:hover {
        background-color: #e9ecef;
    }

    .admin-nav i {
        margin-right: 10px;
    }

    /* Stile per il contenuto principale */
    .admin-container {
        display: flex;
    }

    .admin-sidebar {
        width: 250px;
        padding: 20px;
        background-color: #f8f9fa;
        border-right: 1px solid #dee2e6;
    }

    .admin-content {
        flex: 1;
        padding: 20px;
    }
</style>

<?php
// Include footer
require_once dirname(__DIR__) . '/includes/footer.php';
?>
