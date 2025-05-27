<nav class="admin-nav">
    <ul class="admin-menu">
        <li><a href="/admin/admin-dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
        <li><a href="/admin/admin-stations.php"><i class="fas fa-charging-station"></i> Stations</a></li>
        <li><a href="/admin/admin-users.php"><i class="fas fa-users"></i> Users</a></li>
        <li><a href="/admin/admin-bookings.php"><i class="fas fa-calendar-check"></i> Bookings</a></li>
        <li><a href="/admin/admin-reports.php"><i class="fas fa-chart-line"></i> Reports</a></li>
        <li><a href="/admin/admin-maintenance.php"><i class="fas fa-tools"></i> Maintenance</a></li>
        <li><a href="/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
</nav>

<style>
    .admin-nav {
        padding: 1rem;
        background-color: #f8f9fa;
        height: 100%;
        border-right: 1px solid #dee2e6;
    }

    .admin-menu {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .admin-menu li {
        margin-bottom: 1rem;
    }

    .admin-menu a {
        display: flex;
        align-items: center;
        color: #343a40;
        text-decoration: none;
        font-weight: 500;
        transition: color 0.3s ease;
    }

    .admin-menu a i {
        margin-right: 0.5rem;
        font-size: 1.1rem;
    }

    .admin-menu a:hover {
        color: #007bff;
    }
</style>
