<?php
/**
 * Admin Dashboard — Home
 * Rent Hob
 */
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('admin');

$adminName = $_SESSION['user_name'];

// ── Stats ─────────────────────────────────────────────────────────────────────
$stmt = $pdo->query('SELECT role, COUNT(*) as total FROM users GROUP BY role');
$roleCount = [];
foreach ($stmt->fetchAll() as $row) { $roleCount[$row['role']] = (int)$row['total']; }
$stats['tenants']            = $roleCount['tenant']   ?? 0;
$stats['landlords']          = $roleCount['landlord'] ?? 0;
$stats['agents']             = $roleCount['agent']    ?? 0;
$stats['total_users']        = array_sum($roleCount);

$stmt = $pdo->query('SELECT status, COUNT(*) as total FROM properties GROUP BY status');
$propCount = [];
foreach ($stmt->fetchAll() as $row) { $propCount[$row['status']] = (int)$row['total']; }
$stats['total_properties']   = array_sum($propCount);
$stats['active_properties']  = $propCount['active']  ?? 0;
$stats['pending_properties'] = $propCount['pending'] ?? 0;

$stmt = $pdo->query('SELECT COUNT(*) FROM bookings');
$stats['total_bookings'] = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='paid'");
$stats['total_revenue'] = (float)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM maintenance_requests WHERE status='open'");
$stats['open_maintenance'] = (int)$stmt->fetchColumn();

// ── Recent data ───────────────────────────────────────────────────────────────
$recentUsers = $pdo->query(
    'SELECT id,first_name,last_name,email,role,status,avatar,created_at
     FROM users ORDER BY created_at DESC LIMIT 6'
)->fetchAll();

$recentProperties = $pdo->query(
    'SELECT p.id,p.title,p.status,p.price,p.type,p.created_at,u.first_name,u.last_name
     FROM properties p JOIN users u ON u.id=p.landlord_id
     ORDER BY p.created_at DESC LIMIT 6'
)->fetchAll();

$recentActivity = $pdo->query(
    'SELECT a.action,a.created_at,u.first_name,u.last_name
     FROM activity_log a JOIN users u ON u.id=a.user_id
     ORDER BY a.created_at DESC LIMIT 8'
)->fetchAll();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0');
$stmt->execute([currentUserId()]);
$unreadNotifs = (int)$stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Admin Dashboard — Rent Hob</title>
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
        .stat-card { transition: transform .2s; }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-icon {
            width: 52px; height: 52px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
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
      data-bggradient="bg-gradient-5"
      data-bs-spy="scroll"
      data-bs-target="#list-example"
      data-bs-smooth-scroll="true"
      tabindex="0">

    <!-- ── PAGE LOADER ──────────────────────────────────────────────────────── -->
    <div class="pageloader">
        <div class="container h-100">
            <div class="row justify-content-center align-items-center text-center h-100">
                <div class="col-12 mb-auto pt-4"></div>
                <div class="col-auto">
                    <img src="<?= BASE_URL ?>/dashboard/assets/img/logo-512.png" alt="" class="height-100 mb-3">
                    <p class="h3 mb-0">Rent Hob</p>
                    <p class="small text-secondary mb-3">Admin Dashboard</p>
                    <div class="loader10 mb-3 mx-auto"></div>
                </div>
                <div class="col-12 mt-auto pb-4">
                    <p class="text-secondary">Loading...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ── HEADER (outside adminuiux-wrap) ──────────────────────────────────── -->
    <header class="adminuiux-header inner-page">
        <nav class="navbar navbar-expand-lg fixed-top">
            <div class="container-fluid">

                <!-- Sidebar toggle -->
                <button class="btn btn-link btn-square sidebar-toggler ms-1" type="button" onclick="initSidebar()">
                    <i class="sidebar-svg" data-feather="menu"></i>
                </button>

                <!-- Logo -->
                <a class="navbar-brand" href="dashboard.php">
                    <img data-bs-img="light" src="<?= BASE_URL ?>/assets/img/logo-2.svg" alt="Rent Hob" style="max-height:32px;">
                    <img data-bs-img="dark"  src="<?= BASE_URL ?>/assets/img/logo-2.svg" alt="Rent Hob" style="max-height:32px;">
                    <div class="lh-20">
                        <span class="h4">Rent Hob</span>
                        <p class="company-tagline">Admin Panel</p>
                    </div>
                </a>

                <!-- Right icons -->
                <div class="ms-auto d-flex align-items-center gap-1">

                    <!-- Notifications -->
                    <a href="notifications.php" class="btn btn-link btn-square btn-icon btn-link-header position-relative">
                        <i data-feather="bell"></i>
                        <?php if ($unreadNotifs > 0): ?>
                            <span class="position-absolute top-0 end-0 badge rounded-pill bg-danger p-1">
                                <small><?= $unreadNotifs > 9 ? '9+' : $unreadNotifs ?></small>
                            </span>
                        <?php endif; ?>
                    </a>

                    <!-- Admin dropdown -->
                    <div class="dropdown">
                        <button class="btn btn-link no-caret dropdown-toggle d-flex align-items-center gap-2 px-2"
                                type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <img src="<?= userAvatar($_SESSION['user_avatar'] ?? null) ?>"
                                 alt="Admin" class="rounded-circle"
                                 width="34" height="34" style="object-fit:cover;">
                            <span class="d-none d-md-inline small fw-semibold"><?= e($adminName) ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php"><i data-feather="user" class="me-2" style="width:15px;height:15px;"></i> My Profile</a></li>
                            <li><a class="dropdown-item" href="settings.php"><i data-feather="settings" class="me-2" style="width:15px;height:15px;"></i> Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/logout.php"><i data-feather="log-out" class="me-2" style="width:15px;height:15px;"></i> Logout</a></li>
                        </ul>
                    </div>

                </div>
            </div>
        </nav>
    </header>
    <!-- ── END HEADER ───────────────────────────────────────────────────────── -->

    <!-- ── adminuiux-wrap: sidebar + main content as siblings ───────────────── -->
    <div class="adminuiux-wrap">

        <!-- ── SIDEBAR ──────────────────────────────────────────────────────── -->
        <div class="adminuiux-sidebar">
            <div class="adminuiux-sidebar-inner">

                <ul class="nav flex-column menu-active-line mt-0">

                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i data-feather="grid"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>

                    <li class="nav-item menu-title"><span>USER MANAGEMENT</span></li>

                    <li class="nav-item">
                        <a class="nav-link" href="users.php">
                            <i data-feather="users"></i>
                            <span>All Users</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php?role=tenant">
                            <i data-feather="user"></i>
                            <span>Tenants</span>
                            <span class="badge bg-secondary ms-auto"><?= $stats['tenants'] ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php?role=landlord">
                            <i data-feather="home"></i>
                            <span>Landlords</span>
                            <span class="badge bg-secondary ms-auto"><?= $stats['landlords'] ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php?role=agent">
                            <i data-feather="briefcase"></i>
                            <span>Agents</span>
                            <span class="badge bg-secondary ms-auto"><?= $stats['agents'] ?></span>
                        </a>
                    </li>

                    <li class="nav-item menu-title"><span>AGENT MANAGEMENT</span></li>
                    
                    <li class="nav-item">
                        <a class="nav-link" href="agents.php">
                            <i data-feather="user-check"></i>
                            <span>Agent Directory</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="commissions.php">
                            <i data-feather="dollar-sign"></i>
                            <span>Commissions</span>
                        </a>
                    </li>

                    <li class="nav-item menu-title"><span>PROPERTIES</span></li>

                    <li class="nav-item">
                        <a class="nav-link" href="properties.php">
                            <i data-feather="map-pin"></i>
                            <span>All Properties</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="properties.php?status=pending">
                            <i data-feather="clock"></i>
                            <span>Pending Approval</span>
                            <?php if ($stats['pending_properties'] > 0): ?>
                                <span class="badge bg-warning text-dark ms-auto"><?= $stats['pending_properties'] ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="categories.php">
                            <i data-feather="tag"></i>
                            <span>Categories</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="amenities.php">
                            <i data-feather="star"></i>
                            <span>Amenities</span>
                        </a>
                    </li>

                    <li class="nav-item menu-title"><span>LOCATIONS</span></li>

                    <li class="nav-item">
                        <a class="nav-link" href="locations.php?tab=countries">
                            <i data-feather="globe"></i>
                            <span>Countries</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="locations.php?tab=states">
                            <i data-feather="map"></i>
                            <span>States</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="locations.php?tab=cities">
                            <i data-feather="map-pin"></i>
                            <span>Cities</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="locations.php?tab=areas">
                            <i data-feather="navigation"></i>
                            <span>Areas</span>
                        </a>
                    </li>

                    <li class="nav-item menu-title"><span>TRANSACTIONS</span></li>

                    <li class="nav-item">
                        <a class="nav-link" href="bookings.php">
                            <i data-feather="calendar"></i>
                            <span>Bookings</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="payments.php">
                            <i data-feather="credit-card"></i>
                            <span>Payments</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="maintenance.php">
                            <i data-feather="tool"></i>
                            <span>Maintenance</span>
                            <?php if ($stats['open_maintenance'] > 0): ?>
                                <span class="badge bg-danger ms-auto"><?= $stats['open_maintenance'] ?></span>
                            <?php endif; ?>
                        </a>
                    </li>

                    <li class="nav-item menu-title"><span>REPORTS & SETTINGS</span></li>

                    <li class="nav-item">
                        <a class="nav-link" href="reports.php">
                            <i data-feather="bar-chart-2"></i>
                            <span>Reports</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="settings.php">
                            <i data-feather="settings"></i>
                            <span>Settings</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="activity-log.php">
                            <i data-feather="activity"></i>
                            <span>Activity Log</span>
                        </a>
                    </li>

                    <li class="nav-item mt-2">
                        <a class="nav-link text-danger" href="<?= BASE_URL ?>/logout.php">
                            <i data-feather="log-out"></i>
                            <span>Logout</span>
                        </a>
                    </li>

                </ul>

                <div class="mt-auto"></div>

            </div>
        </div>
        <!-- ── END SIDEBAR ──────────────────────────────────────────────────── -->

        <!-- ── MAIN CONTENT ─────────────────────────────────────────────────── -->
        <main class="adminuiux-content has-sidebar" onclick="contentClick()">
            <div class="container mt-2" id="main-content">

                <!-- Page Title -->
                <div class="row mb-3">
                    <div class="col">
                        <h5 class="mb-1">Welcome back, <?= e(explode(' ', $adminName)[0]) ?> 👋</h5>
                        <p class="text-secondary small mb-0">Here's what's happening on Rent Hob today.</p>
                    </div>
                    <div class="col-auto align-self-center">
                        <span class="badge bg-primary px-3 py-2"><?= date('l, d M Y') ?></span>
                    </div>
                </div>

                <!-- ── STAT CARDS ── -->
                <div class="row g-3 mb-4">

                    <div class="col-xl-3 col-md-6">
                        <div class="card stat-card border-0 shadow-sm h-100">
                            <div class="card-body d-flex align-items-center gap-3">
                                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                                    <i data-feather="users"></i>
                                </div>
                                <div>
                                    <p class="text-secondary small mb-1">Total Users</p>
                                    <h4 class="mb-0 fw-bold"><?= number_format($stats['total_users']) ?></h4>
                                    <small class="text-muted">
                                        <?= $stats['tenants'] ?> tenants &middot;
                                        <?= $stats['landlords'] ?> landlords &middot;
                                        <?= $stats['agents'] ?> agents
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6">
                        <div class="card stat-card border-0 shadow-sm h-100">
                            <div class="card-body d-flex align-items-center gap-3">
                                <div class="stat-icon bg-success bg-opacity-10 text-success">
                                    <i data-feather="home"></i>
                                </div>
                                <div>
                                    <p class="text-secondary small mb-1">Total Properties</p>
                                    <h4 class="mb-0 fw-bold"><?= number_format($stats['total_properties']) ?></h4>
                                    <small class="text-muted">
                                        <?= $stats['active_properties'] ?> active &middot;
                                        <?= $stats['pending_properties'] ?> pending
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6">
                        <div class="card stat-card border-0 shadow-sm h-100">
                            <div class="card-body d-flex align-items-center gap-3">
                                <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                                    <i data-feather="calendar"></i>
                                </div>
                                <div>
                                    <p class="text-secondary small mb-1">Total Bookings</p>
                                    <h4 class="mb-0 fw-bold"><?= number_format($stats['total_bookings']) ?></h4>
                                    <small class="text-muted">All time</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6">
                        <div class="card stat-card border-0 shadow-sm h-100">
                            <div class="card-body d-flex align-items-center gap-3">
                                <div class="stat-icon bg-info bg-opacity-10 text-info">
                                    <i data-feather="credit-card"></i>
                                </div>
                                <div>
                                    <p class="text-secondary small mb-1">Total Revenue</p>
                                    <h4 class="mb-0 fw-bold"><?= formatCurrency($stats['total_revenue']) ?></h4>
                                    <small class="text-muted">Paid payments</small>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
                <!-- ── END STAT CARDS ── -->

                <!-- ── RECENT USERS + ACTIVITY ── -->
                <div class="row g-3 mb-4">

                    <div class="col-xl-8">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center pt-3">
                                <h6 class="mb-0 fw-semibold">Recent Users</h6>
                                <a href="users.php" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="ps-3">User</th>
                                                <th>Role</th>
                                                <th>Status</th>
                                                <th>Joined</th>
                                                <th class="text-end pe-3">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($recentUsers)): ?>
                                                <tr><td colspan="5" class="text-center text-muted py-4">No users yet.</td></tr>
                                            <?php else: ?>
                                                <?php foreach ($recentUsers as $user): ?>
                                                    <tr>
                                                        <td class="ps-3">
                                                            <div class="d-flex align-items-center gap-2">
                                                                <img src="<?= userAvatar($user['avatar'] ?? null) ?>"
                                                                     class="rounded-circle" width="34" height="34"
                                                                     alt="" style="object-fit:cover;">
                                                                <div>
                                                                    <p class="mb-0 small fw-semibold"><?= e($user['first_name'] . ' ' . $user['last_name']) ?></p>
                                                                    <p class="mb-0 text-muted" style="font-size:.75rem;"><?= e($user['email']) ?></p>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td><span class="badge bg-secondary"><?= ucfirst(e($user['role'])) ?></span></td>
                                                        <td><?= statusBadge($user['status']) ?></td>
                                                        <td class="small text-muted"><?= formatDate($user['created_at']) ?></td>
                                                        <td class="text-end pe-3">
                                                            <a href="user-view.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-outline-secondary">View</a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center pt-3">
                                <h6 class="mb-0 fw-semibold">Recent Activity</h6>
                                <a href="activity-log.php" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body p-0">
                                <ul class="list-group list-group-flush">
                                    <?php if (empty($recentActivity)): ?>
                                        <li class="list-group-item text-center text-muted py-4">No activity yet.</li>
                                    <?php else: ?>
                                        <?php foreach ($recentActivity as $log): ?>
                                            <li class="list-group-item px-3 py-2">
                                                <div class="d-flex align-items-start gap-2">
                                                    <div class="stat-icon bg-light text-secondary mt-1"
                                                         style="width:32px;height:32px;border-radius:8px;">
                                                        <i data-feather="activity" style="width:14px;height:14px;"></i>
                                                    </div>
                                                    <div>
                                                        <p class="mb-0 small fw-semibold"><?= e($log['first_name'] . ' ' . $log['last_name']) ?></p>
                                                        <p class="mb-0 text-muted" style="font-size:.75rem;"><?= e($log['action']) ?></p>
                                                        <p class="mb-0 text-muted" style="font-size:.7rem;"><?= timeAgo($log['created_at']) ?></p>
                                                    </div>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>

                </div>
                <!-- ── END RECENT USERS + ACTIVITY ── -->

                <!-- ── RECENT PROPERTIES ── -->
                <div class="row g-3 mb-4">
                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center pt-3">
                                <h6 class="mb-0 fw-semibold">Recent Properties</h6>
                                <a href="properties.php" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="ps-3">Title</th>
                                                <th>Landlord</th>
                                                <th>Type</th>
                                                <th>Price</th>
                                                <th>Status</th>
                                                <th>Date</th>
                                                <th class="text-end pe-3">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($recentProperties)): ?>
                                                <tr><td colspan="7" class="text-center text-muted py-4">No properties yet.</td></tr>
                                            <?php else: ?>
                                                <?php foreach ($recentProperties as $prop): ?>
                                                    <tr>
                                                        <td class="ps-3 small fw-semibold"><?= e(truncate($prop['title'], 35)) ?></td>
                                                        <td class="small text-muted"><?= e($prop['first_name'] . ' ' . $prop['last_name']) ?></td>
                                                        <td><span class="badge bg-info text-dark"><?= ucfirst(e($prop['type'])) ?></span></td>
                                                        <td class="small fw-semibold"><?= formatCurrency((float)$prop['price']) ?></td>
                                                        <td><?= statusBadge($prop['status']) ?></td>
                                                        <td class="small text-muted"><?= formatDate($prop['created_at']) ?></td>
                                                        <td class="text-end pe-3">
                                                            <a href="property-view.php?id=<?= $prop['id'] ?>" class="btn btn-sm btn-outline-secondary">View</a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- ── END RECENT PROPERTIES ── -->

                <div class="row">
                    <div class="col text-center text-muted small py-3">
                        &copy; <?= date('Y') ?> Rent Hob. All rights reserved.
                    </div>
                </div>

            </div>
        </main>
        <!-- ── END MAIN CONTENT ─────────────────────────────────────────────── -->

    </div>
    <!-- ── END adminuiux-wrap ───────────────────────────────────────────────── -->

    <!-- ── MOBILE BOTTOM NAV (outside wrap) ─────────────────────────────────── -->
    <footer class="adminuiux-mobile-footer hide-on-scrolldown style-1">
        <div class="row gx-0 justify-content-around">
            <div class="col-auto">
                <a href="dashboard.php" class="nav-link active text-center py-2 px-3">
                    <i data-feather="grid" style="width:20px;height:20px;"></i>
                    <p class="mb-0" style="font-size:.65rem;">Home</p>
                </a>
            </div>
            <div class="col-auto">
                <a href="users.php" class="nav-link text-center py-2 px-3">
                    <i data-feather="users" style="width:20px;height:20px;"></i>
                    <p class="mb-0" style="font-size:.65rem;">Users</p>
                </a>
            </div>
            <div class="col-auto">
                <a href="properties.php" class="nav-link text-center py-2 px-3">
                    <i data-feather="home" style="width:20px;height:20px;"></i>
                    <p class="mb-0" style="font-size:.65rem;">Properties</p>
                </a>
            </div>
            <div class="col-auto">
                <a href="settings.php" class="nav-link text-center py-2 px-3">
                    <i data-feather="settings" style="width:20px;height:20px;"></i>
                    <p class="mb-0" style="font-size:.65rem;">Settings</p>
                </a>
            </div>
            <div class="col-auto">
                <a href="<?= BASE_URL ?>/logout.php" class="nav-link text-center py-2 px-3">
                    <i data-feather="log-out" style="width:20px;height:20px;"></i>
                    <p class="mb-0" style="font-size:.65rem;">Logout</p>
                </a>
            </div>
        </div>
    </footer>
    <!-- ── END MOBILE BOTTOM NAV ────────────────────────────────────────────── -->

</body>
</html>
