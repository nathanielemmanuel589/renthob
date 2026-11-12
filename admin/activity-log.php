<?php
/**
 * Admin — Activity Log
 * Rent Hob
 */
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('admin');

// ── Filters ───────────────────────────────────────────────────────────────────
$search = trim($_GET['search'] ?? '');
$module = $_GET['module'] ?? '';
$userId = (int)($_GET['user_id'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$where = ['1=1'];
$params = [];

if ($search) {
    $where[] = '(a.action LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)';
    $term = '%' . $search . '%';
    $params = array_merge($params, [$term, $term, $term]);
}
if ($module) {
    $where[] = 'a.module = ?';
    $params[] = $module;
}
if ($userId > 0) {
    $where[] = 'a.user_id = ?';
    $params[] = $userId;
}

$whereSQL = implode(' AND ', $where);

// Total count
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM activity_log a JOIN users u ON a.user_id=u.id WHERE $whereSQL");
$stmtCount->execute($params);
$total = (int)$stmtCount->fetchColumn();
$pager = paginate($total, $perPage, $page);

// Fetch activity logs
$stmt = $pdo->prepare(
    "SELECT a.*, u.first_name, u.last_name, u.email, u.avatar, u.role
     FROM activity_log a
     JOIN users u ON a.user_id = u.id
     WHERE $whereSQL
     ORDER BY a.created_at DESC
     LIMIT ? OFFSET ?"
);
$stmt->execute(array_merge($params, [$perPage, $pager['offset']]));
$activities = $stmt->fetchAll();

// Get available modules for filter
$modules = $pdo->query("SELECT DISTINCT module FROM activity_log WHERE module IS NOT NULL ORDER BY module")->fetchAll(PDO::FETCH_COLUMN);

// Get admins for filter
$admins = $pdo->query("SELECT id, first_name, last_name FROM users WHERE role='admin' ORDER BY first_name")->fetchAll();

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Activity Log — Rent Hob Admin</title>
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
                    <li class="nav-item"><a class="nav-link" href="reports.php"><i data-feather="bar-chart-2"></i><span>Reports</span></a></li>
                    <li class="nav-item"><a class="nav-link" href="settings.php"><i data-feather="settings"></i><span>Settings</span></a></li>
                    <li class="nav-item"><a class="nav-link active" href="activity-log.php"><i data-feather="activity"></i><span>Activity Log</span></a></li>
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
                                <li class="breadcrumb-item active">Activity Log</li>
                            </ol>
                        </nav>
                        <h5 class="mb-0 fw-semibold">Activity Log</h5>
                        <p class="text-muted small mb-0">Track all admin actions and system events</p>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <form method="GET" class="row g-2">
                            <div class="col-md-5">
                                <div class="input-group">
                                    <span class="input-group-text"><i data-feather="search" style="width:14px;height:14px;"></i></span>
                                    <input type="text" class="form-control" name="search"
                                           placeholder="Search actions, users..."
                                           value="<?= e($search) ?>">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" name="module">
                                    <option value="">All Modules</option>
                                    <?php foreach ($modules as $mod): ?>
                                        <option value="<?= e($mod) ?>" <?= $module === $mod ? 'selected' : '' ?>><?= ucfirst(e($mod)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" name="user_id">
                                    <option value="">All Admins</option>
                                    <?php foreach ($admins as $admin): ?>
                                        <option value="<?= $admin['id'] ?>" <?= $userId === $admin['id'] ? 'selected' : '' ?>>
                                            <?= e($admin['first_name'] . ' ' . $admin['last_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex gap-2">
                                <button type="submit" class="btn btn-primary flex-fill">Filter</button>
                                <a href="activity-log.php" class="btn btn-outline-secondary">Reset</a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Activity Timeline -->
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <?php if (empty($activities)): ?>
                            <div class="text-center text-muted py-5">
                                <i data-feather="activity" style="width:40px;height:40px;opacity:.3;" class="d-block mx-auto mb-2"></i>
                                No activity found.
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($activities as $activity): ?>
                                    <div class="list-group-item border-0 px-0 py-3">
                                        <div class="d-flex gap-3">
                                            <img src="<?= userAvatar($activity['avatar']) ?>" 
                                                 class="rounded-circle flex-shrink-0" width="40" height="40" 
                                                 alt="" style="object-fit:cover;">
                                            <div class="flex-grow-1">
                                                <div class="d-flex justify-content-between align-items-start mb-1">
                                                    <div>
                                                        <p class="mb-0 fw-semibold small">
                                                            <?= e($activity['first_name'] . ' ' . $activity['last_name']) ?>
                                                            <span class="badge bg-<?= $activity['role'] === 'admin' ? 'danger' : 'secondary' ?> ms-1"><?= ucfirst($activity['role']) ?></span>
                                                        </p>
                                                        <p class="mb-0 text-muted small"><?= e($activity['email']) ?></p>
                                                    </div>
                                                    <small class="text-muted"><?= formatDate($activity['created_at']) ?></small>
                                                </div>
                                                <p class="mb-1"><?= e($activity['action']) ?></p>
                                                <div class="d-flex gap-3 text-muted small">
                                                    <?php if ($activity['module']): ?>
                                                        <span>
                                                            <i data-feather="folder" style="width:12px;height:12px;"></i>
                                                            <?= ucfirst(e($activity['module'])) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                    <?php if ($activity['ip_address']): ?>
                                                        <span>
                                                            <i data-feather="globe" style="width:12px;height:12px;"></i>
                                                            <?= e($activity['ip_address']) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Pagination -->
                        <?php if ($pager['total_pages'] > 1): ?>
                            <div class="d-flex justify-content-between align-items-center px-0 py-3 border-top mt-3">
                                <p class="mb-0 small text-muted">
                                    Showing <?= $pager['offset'] + 1 ?>–<?= min($pager['offset'] + $perPage, $total) ?> of <?= $total ?> activities
                                </p>
                                <nav>
                                    <ul class="pagination pagination-sm mb-0">
                                        <li class="page-item <?= !$pager['has_prev'] ? 'disabled' : '' ?>">
                                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                                <i data-feather="chevron-left" style="width:14px;height:14px;"></i>
                                            </a>
                                        </li>
                                        <?php for ($p = 1; $p <= $pager['total_pages']; $p++): ?>
                                            <?php if ($p <= 2 || $p >= $pager['total_pages'] - 1 || abs($p - $page) <= 1): ?>
                                                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"><?= $p ?></a>
                                                </li>
                                            <?php elseif (abs($p - $page) === 2): ?>
                                                <li class="page-item disabled"><span class="page-link">…</span></li>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                        <li class="page-item <?= !$pager['has_next'] ? 'disabled' : '' ?>">
                                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                                <i data-feather="chevron-right" style="width:14px;height:14px;"></i>
                                            </a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                        <?php else: ?>
                            <div class="px-0 py-2 border-top mt-3">
                                <p class="mb-0 small text-muted"><?= $total ?> activit<?= $total !== 1 ? 'ies' : 'y' ?> found</p>
                            </div>
                        <?php endif; ?>

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

</body>
</html>
