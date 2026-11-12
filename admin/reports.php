<?php
/**
 * Admin — Reports & Analytics
 * Rent Hob
 */
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('admin');

// ── Date range filter ─────────────────────────────────────────────────────────
$dateRange = $_GET['range'] ?? '30days';
$customStart = $_GET['start'] ?? '';
$customEnd = $_GET['end'] ?? '';

$startDate = null;
$endDate = date('Y-m-d');

switch ($dateRange) {
    case '7days':
        $startDate = date('Y-m-d', strtotime('-7 days'));
        break;
    case '30days':
        $startDate = date('Y-m-d', strtotime('-30 days'));
        break;
    case '90days':
        $startDate = date('Y-m-d', strtotime('-90 days'));
        break;
    case 'year':
        $startDate = date('Y-m-d', strtotime('-1 year'));
        break;
    case 'custom':
        $startDate = $customStart ?: date('Y-m-d', strtotime('-30 days'));
        $endDate = $customEnd ?: date('Y-m-d');
        break;
    default:
        $startDate = date('Y-m-d', strtotime('-30 days'));
}

// ── User Statistics ───────────────────────────────────────────────────────────
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$newUsers = $pdo->prepare("SELECT COUNT(*) FROM users WHERE created_at BETWEEN ? AND ?")->execute([$startDate, $endDate]) ? $pdo->query("SELECT COUNT(*) FROM users WHERE created_at BETWEEN '$startDate' AND '$endDate'")->fetchColumn() : 0;
$usersByRole = $pdo->query("SELECT role, COUNT(*) as cnt FROM users GROUP BY role")->fetchAll(PDO::FETCH_KEY_PAIR);

// ── Property Statistics ───────────────────────────────────────────────────────
$totalProperties = $pdo->query("SELECT COUNT(*) FROM properties")->fetchColumn();
$activeProperties = $pdo->query("SELECT COUNT(*) FROM properties WHERE status='active'")->fetchColumn();
$pendingProperties = $pdo->query("SELECT COUNT(*) FROM properties WHERE status='pending'")->fetchColumn();
$propertiesByCategory = $pdo->query("SELECT c.name, COUNT(p.id) as cnt FROM properties p JOIN categories c ON p.category_id=c.id WHERE p.status='active' GROUP BY c.name ORDER BY cnt DESC LIMIT 5")->fetchAll();

// ── Booking Statistics ────────────────────────────────────────────────────────
$totalBookings = $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$bookingsByStatus = $pdo->query("SELECT status, COUNT(*) as cnt FROM bookings GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$recentBookings = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE created_at BETWEEN ? AND ?")->execute([$startDate, $endDate]) ? $pdo->query("SELECT COUNT(*) FROM bookings WHERE created_at BETWEEN '$startDate' AND '$endDate'")->fetchColumn() : 0;

// ── Payment Statistics ────────────────────────────────────────────────────────
$totalRevenue = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status='paid'")->fetchColumn();
$revenueThisPeriod = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status='paid' AND paid_date BETWEEN ? AND ?")->execute([$startDate, $endDate]) ? $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status='paid' AND paid_date BETWEEN '$startDate' AND '$endDate'")->fetchColumn() : 0;
$paymentsByMethod = $pdo->query("SELECT payment_method, COUNT(*) as cnt FROM payments WHERE status='paid' GROUP BY payment_method")->fetchAll(PDO::FETCH_KEY_PAIR);
$pendingPayments = $pdo->query("SELECT COUNT(*) FROM payments WHERE status='pending'")->fetchColumn();

// ── Maintenance Statistics ────────────────────────────────────────────────────
$totalMaintenance = $pdo->query("SELECT COUNT(*) FROM maintenance_requests")->fetchColumn();
$openMaintenance = $pdo->query("SELECT COUNT(*) FROM maintenance_requests WHERE status='open'")->fetchColumn();
$urgentMaintenance = $pdo->query("SELECT COUNT(*) FROM maintenance_requests WHERE priority='urgent' AND status IN ('open','in_progress')")->fetchColumn();

// ── Top Properties ────────────────────────────────────────────────────────────
$topProperties = $pdo->query("
    SELECT p.id, p.title, p.slug, COUNT(l.id) as booking_count, COALESCE(SUM(pay.amount), 0) as revenue
    FROM properties p
    LEFT JOIN leases l ON p.id = l.property_id AND l.status = 'active'
    LEFT JOIN payments pay ON l.id = pay.lease_id AND pay.status='paid'
    WHERE p.status = 'active'
    GROUP BY p.id, p.title, p.slug
    ORDER BY booking_count DESC, revenue DESC
    LIMIT 5
")->fetchAll();

// ── Recent Activity ───────────────────────────────────────────────────────────
$recentActivity = $pdo->query("
    SELECT a.*, u.first_name, u.last_name, u.avatar
    FROM activity_log a
    JOIN users u ON a.user_id = u.id
    ORDER BY a.created_at DESC
    LIMIT 10
")->fetchAll();

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Reports — Rent Hob Admin</title>
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/img/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Open+Sans:ital,wght@0,300..800;1,300..800&display=swap" rel="stylesheet">
    <style>
        :root {
            --adminuiux-content-font: "Open Sans", sans-serif;
            --adminuiux-content-font-weight: 400;
            --adminuiux-title-font: "Inter", sans-serif;
            --adminuiux-title-font-weight: 600;
        }
    </style>
    <script defer src="<?= BASE_URL ?>/dashboard/assets/js/third-party.js"></script>
    <script defer src="<?= BASE_URL ?>/dashboard/assets/js/app.js"></script>
    <script defer src="<?= BASE_URL ?>/dashboard/assets/js/core.js"></script>
    <link href="<?= BASE_URL ?>/dashboard/assets/css/app.css" rel="stylesheet">
</head>

<body class="main-bg main-bg-blur main-bg-opac roundedui adminuiux-sidebar-standard adminuiux-sidebar-fill-white adminuiux-mobile-footer-fill-theme adminuiux-sidebar-boxed adminuiux-header-transparent adminuiux-header-standard theme-cyan bg-gradient-5 scrollup"
      data-theme="theme-cyan"
      data-sidebarfill="adminuiux-sidebar-fill-white"
      data-sidebarlayout="adminuiux-sidebar-boxed"
      data-headerlayout="adminuiux-header-standard"
      data-headerfill="adminuiux-header-transparent"
      data-bggradient="bg-gradient-5">

    <!-- Page Loader -->
    <div class="pageloader">
        <div class="container h-100">
            <div class="row justify-content-center align-items-center text-center h-100">
                <div class="col-auto">
                    <img src="<?= BASE_URL ?>/dashboard/assets/img/logo-512.png" alt="" class="height-100 mb-3">
                    <p class="h3 mb-0">Rent Hob</p>
                    <div class="loader10 mb-3 mx-auto"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── HEADER ─────────────────────────────────────────────────────────────── -->
    <header class="adminuiux-header inner-page">
        <nav class="navbar navbar-expand-lg fixed-top">
            <div class="container-fluid">
                <button class="btn btn-link btn-square sidebar-toggler ms-1" type="button" onclick="initSidebar()">
                    <i class="sidebar-svg" data-feather="menu"></i>
                </button>
                <a class="navbar-brand" href="dashboard.php">
                    <img data-bs-img="light" src="<?= BASE_URL ?>/assets/img/logo-2.svg" alt="Rent Hob" style="max-height:32px;">
                    <img data-bs-img="dark"  src="<?= BASE_URL ?>/assets/img/logo-2.svg" alt="Rent Hob" style="max-height:32px;">
                    <div class="lh-20">
                        <span class="h4">Rent Hob</span>
                        <p class="company-tagline">Admin Panel</p>
                    </div>
                </a>
                <div class="ms-auto d-flex align-items-center gap-1">
                    <div class="dropdown">
                        <button class="btn btn-link no-caret dropdown-toggle d-flex align-items-center gap-2 px-2"
                                type="button" data-bs-toggle="dropdown">
                            <img src="<?= userAvatar($_SESSION['user_avatar'] ?? null) ?>"
                                 alt="Admin" class="rounded-circle" width="34" height="34" style="object-fit:cover;">
                            <span class="d-none d-md-inline small fw-semibold"><?= e($_SESSION['user_name']) ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php"><i data-feather="user" class="me-2" style="width:15px;height:15px;"></i> My Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/logout.php"><i data-feather="log-out" class="me-2" style="width:15px;height:15px;"></i> Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>
    </header>

    <div class="adminuiux-wrap">

        <!-- ── SIDEBAR ────────────────────────────────────────────────────────── -->
        <div class="adminuiux-sidebar">
            <div class="adminuiux-sidebar-inner">
                <ul class="nav flex-column menu-active-line mt-0">
                    <li class="nav-item"><a class="nav-link" href="dashboard.php"><i data-feather="grid"></i><span>Dashboard</span></a></li>
                    <li class="nav-item menu-title"><span>USER MANAGEMENT</span></li>
                    <li class="nav-item"><a class="nav-link" href="users.php"><i data-feather="users"></i><span>All Users</span></a></li>
                    <li class="nav-item"><a class="nav-link" href="users.php?role=tenant"><i data-feather="user"></i><span>Tenants</span></a></li>
                    <li class="nav-item"><a class="nav-link" href="users.php?role=landlord"><i data-feather="home"></i><span>Landlords</span></a></li>
                    <li class="nav-item"><a class="nav-link" href="users.php?role=agent"><i data-feather="briefcase"></i><span>Agents</span></a></li>
                    <li class="nav-item menu-title"><span>AGENT MANAGEMENT</span></li>
                    <li class="nav-item"><a class="nav-link" href="agents.php"><i data-feather="user-check"></i><span>Agent Directory</span></a></li>
                    <li class="nav-item"><a class="nav-link" href="commissions.php"><i data-feather="dollar-sign"></i><span>Commissions</span></a></li>
                    <li class="nav-item menu-title"><span>PROPERTIES</span></li>
                    <li class="nav-item"><a class="nav-link" href="properties.php"><i data-feather="map-pin"></i><span>All Properties</span></a></li>
                    <li class="nav-item"><a class="nav-link" href="properties.php?status=pending"><i data-feather="clock"></i><span>Pending Approval</span></a></li>
                    <li class="nav-item"><a class="nav-link" href="categories.php"><i data-feather="tag"></i><span>Categories</span></a></li>
                    <li class="nav-item"><a class="nav-link" href="amenities.php"><i data-feather="star"></i><span>Amenities</span></a></li>
                    <li class="nav-item menu-title"><span>LOCATIONS</span></li>
                    <li class="nav-item"><a class="nav-link" href="locations.php?tab=countries"><i data-feather="globe"></i><span>Countries</span></a></li>
                    <li class="nav-item"><a class="nav-link" href="locations.php?tab=states"><i data-feather="map"></i><span>States</span></a></li>
                    <li class="nav-item"><a class="nav-link" href="locations.php?tab=cities"><i data-feather="map-pin"></i><span>Cities</span></a></li>
                    <li class="nav-item"><a class="nav-link" href="locations.php?tab=areas"><i data-feather="navigation"></i><span>Areas</span></a></li>
                    <li class="nav-item menu-title"><span>TRANSACTIONS</span></li>
                    <li class="nav-item"><a class="nav-link" href="bookings.php"><i data-feather="calendar"></i><span>Bookings</span></a></li>
                    <li class="nav-item"><a class="nav-link" href="payments.php"><i data-feather="credit-card"></i><span>Payments</span></a></li>
                    <li class="nav-item"><a class="nav-link" href="maintenance.php"><i data-feather="tool"></i><span>Maintenance</span></a></li>
                    <li class="nav-item menu-title"><span>REPORTS & SETTINGS</span></li>
                    <li class="nav-item"><a class="nav-link active" href="reports.php"><i data-feather="bar-chart-2"></i><span>Reports</span></a></li>
                    <li class="nav-item"><a class="nav-link" href="settings.php"><i data-feather="settings"></i><span>Settings</span></a></li>
                    <li class="nav-item"><a class="nav-link" href="activity-log.php"><i data-feather="activity"></i><span>Activity Log</span></a></li>
                    <li class="nav-item mt-2"><a class="nav-link text-danger" href="<?= BASE_URL ?>/logout.php"><i data-feather="log-out"></i><span>Logout</span></a></li>
                </ul>
                <div class="mt-auto"></div>
            </div>
        </div>

        <!-- ── MAIN CONTENT ───────────────────────────────────────────────────── -->
        <main class="adminuiux-content has-sidebar" onclick="contentClick()">
            <div class="container mt-2" id="main-content">

                <!-- Breadcrumb -->
                <div class="row mb-3">
                    <div class="col">
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb mb-1">
                                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Reports</li>
                            </ol>
                        </nav>
                        <h5 class="mb-0 fw-semibold">Reports & Analytics</h5>
                    </div>
                    <div class="col-auto">
                        <form method="GET" class="d-flex gap-2 align-items-center">
                            <select name="range" class="form-select form-select-sm" onchange="toggleCustomDates(this.value)">
                                <option value="7days" <?= $dateRange === '7days' ? 'selected' : '' ?>>Last 7 Days</option>
                                <option value="30days" <?= $dateRange === '30days' ? 'selected' : '' ?>>Last 30 Days</option>
                                <option value="90days" <?= $dateRange === '90days' ? 'selected' : '' ?>>Last 90 Days</option>
                                <option value="year" <?= $dateRange === 'year' ? 'selected' : '' ?>>Last Year</option>
                                <option value="custom" <?= $dateRange === 'custom' ? 'selected' : '' ?>>Custom Range</option>
                            </select>
                            <div id="customDates" style="display:<?= $dateRange === 'custom' ? 'flex' : 'none' ?>;" class="gap-2">
                                <input type="date" name="start" class="form-control form-control-sm" value="<?= e($customStart) ?>">
                                <input type="date" name="end" class="form-control form-control-sm" value="<?= e($customEnd) ?>">
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                        </form>
                    </div>
                </div>

                <!-- Overview Stats -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <p class="text-muted small mb-1">Total Revenue</p>
                                        <h3 class="mb-0 fw-bold">₦<?= number_format($totalRevenue, 2) ?></h3>
                                        <small class="text-success">+₦<?= number_format($revenueThisPeriod, 2) ?> this period</small>
                                    </div>
                                    <div class="bg-success bg-opacity-10 p-2 rounded">
                                        <i data-feather="dollar-sign" class="text-success" style="width:20px;height:20px;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <p class="text-muted small mb-1">Total Users</p>
                                        <h3 class="mb-0 fw-bold"><?= number_format($totalUsers) ?></h3>
                                        <small class="text-info">+<?= $newUsers ?> new users</small>
                                    </div>
                                    <div class="bg-info bg-opacity-10 p-2 rounded">
                                        <i data-feather="users" class="text-info" style="width:20px;height:20px;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <p class="text-muted small mb-1">Properties</p>
                                        <h3 class="mb-0 fw-bold"><?= number_format($totalProperties) ?></h3>
                                        <small class="text-warning"><?= $pendingProperties ?> pending</small>
                                    </div>
                                    <div class="bg-primary bg-opacity-10 p-2 rounded">
                                        <i data-feather="home" class="text-primary" style="width:20px;height:20px;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <p class="text-muted small mb-1">Total Bookings</p>
                                        <h3 class="mb-0 fw-bold"><?= number_format($totalBookings) ?></h3>
                                        <small class="text-success">+<?= $recentBookings ?> this period</small>
                                    </div>
                                    <div class="bg-warning bg-opacity-10 p-2 rounded">
                                        <i data-feather="calendar" class="text-warning" style="width:20px;height:20px;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Two columns -->
                <div class="row g-3 mb-4">
                    <!-- User Distribution -->
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <h6 class="mb-3">Users by Role</h6>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($usersByRole as $role => $count): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center border-0 px-0">
                                            <span class="text-capitalize"><?= e($role) ?>s</span>
                                            <span class="badge bg-primary rounded-pill"><?= $count ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Booking Status -->
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <h6 class="mb-3">Bookings by Status</h6>
                                <div class="list-group list-group-flush">
                                    <?php 
                                    $statusLabels = ['pending'=>'Pending', 'confirmed'=>'Confirmed', 'completed'=>'Completed', 'cancelled'=>'Cancelled'];
                                    foreach ($statusLabels as $s => $label): 
                                        $count = $bookingsByStatus[$s] ?? 0;
                                    ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center border-0 px-0">
                                            <span><?= $label ?></span>
                                            <span class="badge bg-secondary rounded-pill"><?= $count ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Methods & Property Categories -->
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <h6 class="mb-3">Payment Methods</h6>
                                <div class="list-group list-group-flush">
                                    <?php 
                                    $methodLabels = ['gateway'=>'Payment Gateway', 'bank_transfer'=>'Bank Transfer', 'card'=>'Card', 'cash'=>'Cash'];
                                    foreach ($methodLabels as $m => $label): 
                                        $count = $paymentsByMethod[$m] ?? 0;
                                    ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center border-0 px-0">
                                            <span><?= $label ?></span>
                                            <span class="badge bg-info rounded-pill"><?= $count ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <h6 class="mb-3">Top Property Categories</h6>
                                <div class="list-group list-group-flush">
                                    <?php if (empty($propertiesByCategory)): ?>
                                        <p class="text-muted small">No data available</p>
                                    <?php else: ?>
                                        <?php foreach ($propertiesByCategory as $cat): ?>
                                            <div class="list-group-item d-flex justify-content-between align-items-center border-0 px-0">
                                                <span><?= e($cat['name']) ?></span>
                                                <span class="badge bg-success rounded-pill"><?= $cat['cnt'] ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top Properties -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <h6 class="mb-3">Top Performing Properties</h6>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Property</th>
                                        <th>Bookings</th>
                                        <th>Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($topProperties)): ?>
                                        <tr><td colspan="4" class="text-center text-muted py-3">No properties yet</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($topProperties as $i => $prop): ?>
                                            <tr>
                                                <td><?= $i + 1 ?></td>
                                                <td>
                                                    <a href="property-view.php?id=<?= $prop['id'] ?>" class="text-decoration-none fw-semibold">
                                                        <?= e($prop['title']) ?>
                                                    </a>
                                                </td>
                                                <td><span class="badge bg-primary"><?= $prop['booking_count'] ?></span></td>
                                                <td class="fw-semibold">₦<?= number_format($prop['revenue'], 2) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Quick Stats Row -->
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body text-center">
                                <i data-feather="alert-circle" class="text-warning mb-2" style="width:32px;height:32px;"></i>
                                <h4 class="mb-1 fw-bold"><?= $urgentMaintenance ?></h4>
                                <p class="text-muted small mb-0">Urgent Maintenance</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body text-center">
                                <i data-feather="clock" class="text-info mb-2" style="width:32px;height:32px;"></i>
                                <h4 class="mb-1 fw-bold"><?= $pendingPayments ?></h4>
                                <p class="text-muted small mb-0">Pending Payments</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body text-center">
                                <i data-feather="check-circle" class="text-success mb-2" style="width:32px;height:32px;"></i>
                                <h4 class="mb-1 fw-bold"><?= $activeProperties ?></h4>
                                <p class="text-muted small mb-0">Active Properties</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h6 class="mb-3">Recent Activity</h6>
                        <div class="list-group list-group-flush">
                            <?php if (empty($recentActivity)): ?>
                                <p class="text-muted small">No recent activity</p>
                            <?php else: ?>
                                <?php foreach ($recentActivity as $activity): ?>
                                    <div class="list-group-item border-0 px-0">
                                        <div class="d-flex gap-2">
                                            <img src="<?= userAvatar($activity['avatar']) ?>" 
                                                 class="rounded-circle" width="32" height="32" 
                                                 alt="" style="object-fit:cover;">
                                            <div class="flex-grow-1">
                                                <p class="mb-0 small">
                                                    <strong><?= e($activity['first_name'] . ' ' . $activity['last_name']) ?></strong>
                                                    <?= e($activity['action']) ?>
                                                </p>
                                                <small class="text-muted"><?= formatDate($activity['created_at']) ?></small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="text-center mt-3">
                            <a href="activity-log.php" class="btn btn-sm btn-outline-primary">View All Activity</a>
                        </div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col text-center text-muted small py-2">
                        &copy; <?= date('Y') ?> Rent Hob. All rights reserved.
                    </div>
                </div>

            </div>
        </main>

    </div><!-- end adminuiux-wrap -->

    <script>
    function toggleCustomDates(value) {
        const customDates = document.getElementById('customDates');
        if (value === 'custom') {
            customDates.style.display = 'flex';
        } else {
            customDates.style.display = 'none';
        }
    }
    </script>

</body>
</html>
