<?php
/**
 * Admin — View Single User
 * Rent Hob
 */
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('admin');

$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect(BASE_URL . '/admin/users.php');

// Fetch user
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$user = $stmt->fetch();
if (!$user) redirect(BASE_URL . '/admin/users.php');

// Fetch user's properties (if landlord/agent)
$properties = [];
if (in_array($user['role'], ['landlord', 'agent'])) {
    $stmt = $pdo->prepare(
        'SELECT p.id, p.title, p.type, p.price, p.status, p.created_at,
                c.name as category
         FROM properties p
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE p.landlord_id = ? OR p.agent_id = ?
         ORDER BY p.created_at DESC LIMIT 10'
    );
    $stmt->execute([$id, $id]);
    $properties = $stmt->fetchAll();
}

// Fetch user's bookings (if tenant)
$bookings = [];
if ($user['role'] === 'tenant') {
    $stmt = $pdo->prepare(
        'SELECT b.id, b.status, b.visit_date, b.visit_time, b.created_at,
                p.title as property_title, p.price
         FROM bookings b
         JOIN properties p ON p.id = b.property_id
         WHERE b.user_id = ?
         ORDER BY b.created_at DESC LIMIT 10'
    );
    $stmt->execute([$id]);
    $bookings = $stmt->fetchAll();
}

// Fetch activity log
$stmt = $pdo->prepare(
    'SELECT action, module, ip_address, created_at
     FROM activity_log WHERE user_id = ?
     ORDER BY created_at DESC LIMIT 10'
);
$stmt->execute([$id]);
$activity = $stmt->fetchAll();

// Stat counts
$stmtBookings = $pdo->prepare('SELECT COUNT(*) FROM bookings WHERE user_id = ?');
$stmtBookings->execute([$id]);
$totalBookings = (int)$stmtBookings->fetchColumn();

$stmtProps = $pdo->prepare('SELECT COUNT(*) FROM properties WHERE landlord_id = ? OR agent_id = ?');
$stmtProps->execute([$id, $id]);
$totalProperties = (int)$stmtProps->fetchColumn();

$stmtMaint = $pdo->prepare('SELECT COUNT(*) FROM maintenance_requests WHERE tenant_id = ?');
$stmtMaint->execute([$id]);
$totalMaintenance = (int)$stmtMaint->fetchColumn();

$stmtPay = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE tenant_id = ? AND status = 'paid'");
$stmtPay->execute([$id]);
$totalPaid = (float)$stmtPay->fetchColumn();

$roleColors = ['admin'=>'danger','landlord'=>'primary','agent'=>'info','tenant'=>'success'];
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= e($user['first_name'] . ' ' . $user['last_name']) ?> — Rent Hob Admin</title>
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
        .avatar-lg { width: 90px; height: 90px; object-fit: cover; }
        .stat-icon { width: 46px; height: 46px; border-radius: 12px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    </style>
    <script defer src="<?= BASE_URL ?>/dashboard/assets/js/third-party.js"></script>
    <script defer src="<?= BASE_URL ?>/dashboard/assets/js/app.js"></script>
    <script defer src="<?= BASE_URL ?>/dashboard/assets/js/core.js"></script>
    <link href="<?= BASE_URL ?>/dashboard/assets/css/app.css" rel="stylesheet">
</head>

<body class="main-bg main-bg-blur main-bg-opac roundedui adminuiux-sidebar-standard adminuiux-sidebar-fill-white adminuiux-mobile-footer-fill-theme adminuiux-sidebar-boxed adminuiux-header-transparent adminuiux-header-standard theme-cyan bg-gradient-5 scrollup"
      data-theme="theme-cyan" data-sidebarfill="adminuiux-sidebar-fill-white"
      data-sidebarlayout="adminuiux-sidebar-boxed" data-headerlayout="adminuiux-header-standard"
      data-headerfill="adminuiux-header-transparent" data-bggradient="bg-gradient-5"
      data-bs-spy="scroll" data-bs-target="#list-example" data-bs-smooth-scroll="true" tabindex="0">

    <!-- Loader -->
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

    <!-- Header -->
    <header class="adminuiux-header inner-page">
        <nav class="navbar navbar-expand-lg fixed-top">
            <div class="container-fluid">
                <button class="btn btn-link btn-square sidebar-toggler ms-1" type="button" onclick="initSidebar()">
                    <i class="sidebar-svg" data-feather="menu"></i>
                </button>
                <a class="navbar-brand" href="dashboard.php">
                    <img data-bs-img="light" src="<?= BASE_URL ?>/assets/img/logo-2.svg" alt="Rent Hob" style="max-height:32px;">
                    <img data-bs-img="dark"  src="<?= BASE_URL ?>/assets/img/logo-2.svg" alt="Rent Hob" style="max-height:32px;">
                    <div class="lh-20"><span class="h4">Rent Hob</span><p class="company-tagline">Admin Panel</p></div>
                </a>
                <div class="ms-auto d-flex align-items-center gap-1">
                    <div class="dropdown">
                        <button class="btn btn-link no-caret dropdown-toggle d-flex align-items-center gap-2 px-2"
                                type="button" data-bs-toggle="dropdown">
                            <img src="<?= userAvatar($_SESSION['user_avatar'] ?? null) ?>" alt="Admin"
                                 class="rounded-circle" width="34" height="34" style="object-fit:cover;">
                            <span class="d-none d-md-inline small fw-semibold"><?= e($_SESSION['user_name']) ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php"><i data-feather="user" class="me-2" style="width:15px;height:15px;"></i>My Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/logout.php"><i data-feather="log-out" class="me-2" style="width:15px;height:15px;"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>
    </header>

    <div class="adminuiux-wrap">

        <!-- Sidebar -->
        <div class="adminuiux-sidebar">
            <div class="adminuiux-sidebar-inner">
                <ul class="nav flex-column menu-active-line mt-0">
                    <li class="nav-item"><a class="nav-link" href="dashboard.php"><i data-feather="grid"></i><span>Dashboard</span></a></li>
                    <li class="nav-item menu-title"><span>USER MANAGEMENT</span></li>
                    <li class="nav-item"><a class="nav-link active" href="users.php"><i data-feather="users"></i><span>All Users</span></a></li>
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
                    <li class="nav-item"><a class="nav-link" href="activity-log.php"><i data-feather="activity"></i><span>Activity Log</span></a></li>
                    <li class="nav-item mt-2"><a class="nav-link text-danger" href="<?= BASE_URL ?>/logout.php"><i data-feather="log-out"></i><span>Logout</span></a></li>
                </ul>
                <div class="mt-auto"></div>
            </div>
        </div>

        <!-- Main Content -->
        <main class="adminuiux-content has-sidebar" onclick="contentClick()">
            <div class="container mt-2" id="main-content">

                <!-- Breadcrumb -->
                <div class="row mb-3">
                    <div class="col">
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb mb-1">
                                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="users.php">Users</a></li>
                                <li class="breadcrumb-item active"><?= e($user['first_name'] . ' ' . $user['last_name']) ?></li>
                            </ol>
                        </nav>
                        <h5 class="mb-0 fw-semibold">User Profile</h5>
                    </div>
                    <div class="col-auto align-self-center d-flex gap-2">
                        <a href="user-edit.php?id=<?= $user['id'] ?>" class="btn btn-primary btn-sm">
                            <i data-feather="edit-2" style="width:14px;height:14px;" class="me-1"></i> Edit User
                        </a>
                        <a href="users.php" class="btn btn-outline-secondary btn-sm">
                            <i data-feather="arrow-left" style="width:14px;height:14px;" class="me-1"></i> Back
                        </a>
                    </div>
                </div>

                <?php if ($flash): ?>
                    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show mb-3">
                        <?= e($flash['message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row g-3">

                    <!-- Left: Profile card -->
                    <div class="col-xl-4 col-lg-5">

                        <!-- Profile -->
                        <div class="card border-0 shadow-sm mb-3">
                            <div class="card-body text-center py-4">
                                <img src="<?= userAvatar($user['avatar'] ?? null) ?>"
                                     alt="<?= e($user['first_name']) ?>"
                                     class="avatar-lg rounded-circle mb-3 shadow-sm">
                                <h6 class="fw-semibold mb-1"><?= e($user['first_name'] . ' ' . $user['last_name']) ?></h6>
                                <p class="text-muted small mb-2"><?= e($user['email']) ?></p>
                                <div class="d-flex justify-content-center gap-2 mb-3">
                                    <span class="badge bg-<?= $roleColors[$user['role']] ?? 'secondary' ?>"><?= ucfirst(e($user['role'])) ?></span>
                                    <?= statusBadge($user['status']) ?>
                                    <?php if ($user['email_verified']): ?>
                                        <span class="badge bg-success">
                                            <i data-feather="check" style="width:11px;height:11px;"></i> Verified
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-muted small mb-0">
                                    <i data-feather="calendar" style="width:13px;height:13px;" class="me-1"></i>
                                    Joined <?= formatDate($user['created_at'], 'M d, Y') ?>
                                </p>
                            </div>
                        </div>

                        <!-- Contact info -->
                        <div class="card border-0 shadow-sm mb-3">
                            <div class="card-header bg-transparent border-0 pt-3 pb-0">
                                <h6 class="fw-semibold mb-0">Contact Info</h6>
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled mb-0">
                                    <li class="d-flex align-items-center gap-2 mb-2">
                                        <i data-feather="mail" style="width:15px;height:15px;" class="text-muted flex-shrink-0"></i>
                                        <span class="small"><?= e($user['email']) ?></span>
                                    </li>
                                    <li class="d-flex align-items-center gap-2 mb-2">
                                        <i data-feather="phone" style="width:15px;height:15px;" class="text-muted flex-shrink-0"></i>
                                        <span class="small"><?= $user['phone'] ? e($user['phone']) : '<span class="text-muted">Not provided</span>' ?></span>
                                    </li>
                                    <li class="d-flex align-items-center gap-2">
                                        <i data-feather="shield" style="width:15px;height:15px;" class="text-muted flex-shrink-0"></i>
                                        <span class="small">Email <?= $user['email_verified'] ? '<span class="text-success">verified</span>' : '<span class="text-warning">not verified</span>' ?></span>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- Quick actions -->
                        <?php if ($user['id'] !== currentUserId()): ?>
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-transparent border-0 pt-3 pb-0">
                                    <h6 class="fw-semibold mb-0">Quick Actions</h6>
                                </div>
                                <div class="card-body d-flex flex-column gap-2">
                                    <a href="user-edit.php?id=<?= $user['id'] ?>" class="btn btn-outline-primary btn-sm">
                                        <i data-feather="edit-2" style="width:14px;height:14px;" class="me-1"></i> Edit Profile
                                    </a>
                                    <?php if ($user['status'] === 'suspended'): ?>
                                        <form method="POST" action="users.php">
                                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            <input type="hidden" name="action" value="activate">
                                            <button type="submit" class="btn btn-outline-success btn-sm w-100">
                                                <i data-feather="check-circle" style="width:14px;height:14px;" class="me-1"></i> Activate Account
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" action="users.php">
                                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            <input type="hidden" name="action" value="suspend">
                                            <button type="submit" class="btn btn-outline-warning btn-sm w-100">
                                                <i data-feather="slash" style="width:14px;height:14px;" class="me-1"></i> Suspend Account
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($user['role'] !== 'admin'): ?>
                                        <form method="POST" action="users.php"
                                              onsubmit="return confirm('Delete this user permanently?')">
                                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                                                <i data-feather="trash-2" style="width:14px;height:14px;" class="me-1"></i> Delete User
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                    </div>

                    <!-- Right: Stats + tables -->
                    <div class="col-xl-8 col-lg-7">

                        <!-- Stat mini cards -->
                        <div class="row g-3 mb-3">
                            <div class="col-6 col-md-3">
                                <div class="card border-0 shadow-sm text-center py-3">
                                    <h4 class="fw-bold mb-1"><?= $totalBookings ?></h4>
                                    <p class="text-muted small mb-0">Bookings</p>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="card border-0 shadow-sm text-center py-3">
                                    <h4 class="fw-bold mb-1"><?= $totalProperties ?></h4>
                                    <p class="text-muted small mb-0">Properties</p>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="card border-0 shadow-sm text-center py-3">
                                    <h4 class="fw-bold mb-1"><?= $totalMaintenance ?></h4>
                                    <p class="text-muted small mb-0">Maintenance</p>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="card border-0 shadow-sm text-center py-3">
                                    <h4 class="fw-bold mb-1"><?= formatCurrency($totalPaid) ?></h4>
                                    <p class="text-muted small mb-0">Total Paid</p>
                                </div>
                            </div>
                        </div>

                        <!-- Properties table (landlord/agent) -->
                        <?php if (!empty($properties)): ?>
                            <div class="card border-0 shadow-sm mb-3">
                                <div class="card-header bg-transparent border-0 pt-3 d-flex justify-content-between align-items-center">
                                    <h6 class="fw-semibold mb-0">Properties</h6>
                                    <a href="properties.php?landlord=<?= $user['id'] ?>" class="btn btn-sm btn-outline-primary">View All</a>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th class="ps-3">Title</th>
                                                    <th>Type</th>
                                                    <th>Price</th>
                                                    <th>Status</th>
                                                    <th class="pe-3">Date</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($properties as $prop): ?>
                                                    <tr>
                                                        <td class="ps-3 small fw-semibold">
                                                            <a href="property-view.php?id=<?= $prop['id'] ?>" class="text-decoration-none">
                                                                <?= e(truncate($prop['title'], 30)) ?>
                                                            </a>
                                                        </td>
                                                        <td><span class="badge bg-info text-dark"><?= ucfirst(e($prop['type'])) ?></span></td>
                                                        <td class="small"><?= formatCurrency((float)$prop['price']) ?></td>
                                                        <td><?= statusBadge($prop['status']) ?></td>
                                                        <td class="small text-muted pe-3"><?= formatDate($prop['created_at']) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Bookings table (tenant) -->
                        <?php if (!empty($bookings)): ?>
                            <div class="card border-0 shadow-sm mb-3">
                                <div class="card-header bg-transparent border-0 pt-3">
                                    <h6 class="fw-semibold mb-0">Bookings</h6>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th class="ps-3">Property</th>
                                                    <th>Visit Date</th>
                                                    <th>Visit Time</th>
                                                    <th>Amount</th>
                                                    <th class="pe-3">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($bookings as $b): ?>
                                                    <tr>
                                                        <td class="ps-3 small fw-semibold"><?= e(truncate($b['property_title'], 28)) ?></td>
                                                        <td class="small text-muted"><?= formatDate($b['visit_date'] ?? $b['created_at']) ?></td>
                                                        <td class="small text-muted"><?= e($b['visit_time'] ?? '—') ?></td>
                                                        <td class="small"><?= isset($b['price']) ? formatCurrency((float)$b['price']) : '—' ?></td>
                                                        <td class="pe-3"><?= statusBadge($b['status']) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Activity log -->
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-transparent border-0 pt-3">
                                <h6 class="fw-semibold mb-0">Recent Activity</h6>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($activity)): ?>
                                    <p class="text-muted text-center py-4 mb-0">No activity recorded.</p>
                                <?php else: ?>
                                    <ul class="list-group list-group-flush">
                                        <?php foreach ($activity as $log): ?>
                                            <li class="list-group-item px-3 py-2">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <p class="mb-0 small"><?= e($log['action']) ?></p>
                                                        <?php if ($log['module']): ?>
                                                            <span class="badge bg-light text-secondary" style="font-size:.7rem;"><?= e($log['module']) ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <span class="text-muted" style="font-size:.7rem; white-space:nowrap;">
                                                        <?= timeAgo($log['created_at']) ?>
                                                    </span>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
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
    </div>

    <footer class="adminuiux-mobile-footer hide-on-scrolldown style-1">
        <div class="row gx-0 justify-content-around">
            <div class="col-auto"><a href="dashboard.php" class="nav-link text-center py-2 px-3"><i data-feather="grid" style="width:20px;height:20px;"></i><p class="mb-0" style="font-size:.65rem;">Home</p></a></div>
            <div class="col-auto"><a href="users.php" class="nav-link active text-center py-2 px-3"><i data-feather="users" style="width:20px;height:20px;"></i><p class="mb-0" style="font-size:.65rem;">Users</p></a></div>
            <div class="col-auto"><a href="properties.php" class="nav-link text-center py-2 px-3"><i data-feather="home" style="width:20px;height:20px;"></i><p class="mb-0" style="font-size:.65rem;">Properties</p></a></div>
            <div class="col-auto"><a href="settings.php" class="nav-link text-center py-2 px-3"><i data-feather="settings" style="width:20px;height:20px;"></i><p class="mb-0" style="font-size:.65rem;">Settings</p></a></div>
            <div class="col-auto"><a href="<?= BASE_URL ?>/logout.php" class="nav-link text-center py-2 px-3"><i data-feather="log-out" style="width:20px;height:20px;"></i><p class="mb-0" style="font-size:.65rem;">Logout</p></a></div>
        </div>
    </footer>

</body>
</html>
