<?php
// Set page title
$pageTitle = 'Reports';

// Include configuration and required functions
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/admin-navbar.php';

// Require admin access
requireAdmin();

// Get database connection
$conn = getDbConnection();

// Get date range from query parameters
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Get statistics
$stats = [
    'total_bookings' => $conn->query("
        SELECT COUNT(*) as count 
        FROM Bookings 
        WHERE booking_datetime BETWEEN '$startDate' AND '$endDate'
    ")->fetch_assoc()['count'],
    
    'total_charges' => $conn->query("
        SELECT COUNT(*) as count 
        FROM Chargings 
        WHERE start_datetime BETWEEN '$startDate' AND '$endDate'
    ")->fetch_assoc()['count'],
    
    'total_energy' => $conn->query("
        SELECT COALESCE(SUM(energy_consumed), 0) as total 
        FROM Chargings 
        WHERE start_datetime BETWEEN '$startDate' AND '$endDate'
    ")->fetch_assoc()['total'],
    
    'total_revenue' => $conn->query("
        SELECT COALESCE(SUM(cost), 0) as total 
        FROM Chargings 
        WHERE start_datetime BETWEEN '$startDate' AND '$endDate'
    ")->fetch_assoc()['total']
];

// Get station usage
$stationUsage = $conn->query("
    SELECT s.address_street, s.address_city,
           COUNT(DISTINCT b.booking_id) as total_bookings,
           COUNT(DISTINCT c.charging_id) as total_charges,
           COALESCE(SUM(c.energy_consumed), 0) as total_energy,
           COALESCE(SUM(c.cost), 0) as total_revenue
    FROM Stations s
    LEFT JOIN Charging_Points cp ON s.station_id = cp.station_id
    LEFT JOIN Bookings b ON cp.charging_point_id = b.charging_point_id 
        AND b.booking_datetime BETWEEN '$startDate' AND '$endDate'
    LEFT JOIN Chargings c ON cp.charging_point_id = c.charging_point_id 
        AND c.start_datetime BETWEEN '$startDate' AND '$endDate'
    GROUP BY s.station_id
    ORDER BY total_bookings DESC
")->fetch_all(MYSQLI_ASSOC);

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
                <h1>Reports</h1>
            </div>

            <!-- Date Range Filter -->
            <div class="card mb-6">
                <div class="card-body">
                    <form method="GET" class="form-inline">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Start Date</label>
                                    <input type="date" name="start_date" class="form-control" 
                                           value="<?= $startDate ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>End Date</label>
                                    <input type="date" name="end_date" class="form-control" 
                                           value="<?= $endDate ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary mt-4">
                                    <i class="fas fa-filter"></i> Filter
                                </button>
                                <a href="reports.php" class="btn btn-secondary mt-4">
                                    <i class="fas fa-redo"></i> Reset
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Statistics -->
            <div class="stats-grid mb-6">
                <div class="stat-card">
                    <div class="stat-card-title">Total Bookings</div>
                    <div class="stat-card-value"><?= $stats['total_bookings'] ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-title">Total Charges</div>
                    <div class="stat-card-value"><?= $stats['total_charges'] ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-title">Total Energy</div>
                    <div class="stat-card-value"><?= number_format($stats['total_energy'], 2) ?> kWh</div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-title">Total Revenue</div>
                    <div class="stat-card-value">€<?= number_format($stats['total_revenue'], 2) ?></div>
                </div>
            </div>

            <!-- Station Usage -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Station Usage</h2>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Station</th>
                                    <th>Bookings</th>
                                    <th>Charges</th>
                                    <th>Energy (kWh)</th>
                                    <th>Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stationUsage as $station): ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars($station['address_street']) ?>,
                                        <?= htmlspecialchars($station['address_city']) ?>
                                    </td>
                                    <td><?= $station['total_bookings'] ?></td>
                                    <td><?= $station['total_charges'] ?></td>
                                    <td><?= number_format($station['total_energy'], 2) ?></td>
                                    <td>€<?= number_format($station['total_revenue'], 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
require_once dirname(dirname(__DIR__)) . '/includes/footer.php';
?>