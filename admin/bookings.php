<?php
/**
 * Admin — Bookings Management
 * Rent Hob
 */
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('admin');

// ── Handle status change ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    $bookingId = (int)($_POST['booking_id'] ?? 0);

    if ($bookingId > 0) {
        $validStatuses = ['confirmed', 'cancelled', 'completed'];
        if ($action === 'change_status') {
            $newStatus = $_POST['status'] ?? '';
            if (in_array($newStatus, $validStatuses)) {
                $pdo->prepare('UPDATE bookings SET status=? WHERE id=?')->execute([$newStatus, $bookingId]);
                logActivity($pdo, currentUserId(), "Changed booking #$bookingId status to $newStatus", 'bookings');
                setFlash('success', 'Booking status updated.');
            }
        } elseif ($action === 'delete') {
            $pdo->prepare('DELETE FROM bookings WHERE id=?')->execute([$bookingId]);
            logActivity($pdo, currentUserId(), "Deleted booking #$bookingId", 'bookings');
            setFlash('success', 'Booking deleted.');
        }
    }
    redirect(BASE_URL . '/admin/bookings.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
}

// ── Filters ───────────────────────────────────────────────────────────────────
$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

$where = ['1=1'];
$params = [];

if ($search) {
    $where[] = '(p.title LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)';
    $term = '%' . $search . '%';
    $params = array_merge($params, [$term, $term, $term, $term]);
}
if ($status && in_array($status, ['pending', 'confirmed', 'cancelled', 'completed'])) {
    $where[] = 'b.status = ?';
    $params[] = $status;
}

$whereSQL = implode(' AND ', $where);

// Total count
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM bookings b JOIN users u ON b.user_id=u.id JOIN properties p ON b.property_id=p.id WHERE $whereSQL");
$stmtCount->execute($params);
$total = (int)$stmtCount->fetchColumn();
$pager = paginate($total, $perPage, $page);

// Fetch bookings
$stmt = $pdo->prepare(
    "SELECT b.*, 
            u.first_name, u.last_name, u.email, u.avatar,
            p.title as property_title, p.slug as property_slug
     FROM bookings b
     JOIN users u ON b.user_id = u.id
     JOIN properties p ON b.property_id = p.id
     WHERE $whereSQL
     ORDER BY b.created_at DESC
     LIMIT ? OFFSET ?"
);
$stmt->execute(array_merge($params, [$perPage, $pager['offset']]));
$bookings = $stmt->fetchAll();

// Status counts
$statusCounts = $pdo->query("SELECT status, COUNT(*) as cnt FROM bookings GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Bookings — Rent Hob Admin</title>
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
                    <li class="nav-item"><a class="nav-link active" href="bookings.php"><i data-feather="calendar"></i><span>Bookings</span></a></li>
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

        <!-- ── MAIN CONTENT ───────────────────────────────────────────────────── -->
        <main class="adminuiux-content has-sidebar" onclick="contentClick()">
            <div class="container mt-2" id="main-content">

                <!-- Breadcrumb -->
                <div class="row mb-3">
                    <div class="col">
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb mb-1">
                                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Bookings</li>
                            </ol>
                        </nav>
                        <h5 class="mb-0 fw-semibold">Booking Management</h5>
                    </div>
                </div>

                <!-- Flash -->
                <?php if ($flash): ?>
                    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show mb-3">
                        <?= e($flash['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Status tabs -->
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body p-0">
                        <ul class="nav nav-tabs border-0 px-3 pt-3">
                            <li class="nav-item">
                                <a class="nav-link <?= !$status ? 'active' : '' ?>" href="bookings.php">
                                    All <span class="badge bg-secondary ms-1"><?= $total ?></span>
                                </a>
                            </li>
                            <?php foreach (['pending'=>'Pending', 'confirmed'=>'Confirmed', 'completed'=>'Completed', 'cancelled'=>'Cancelled'] as $s => $label): ?>
                                <li class="nav-item">
                                    <a class="nav-link <?= $status === $s ? 'active' : '' ?>" href="bookings.php?status=<?= $s ?>">
                                        <?= $label ?>
                                        <span class="badge bg-secondary ms-1"><?= $statusCounts[$s] ?? 0 ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                        <!-- Search -->
                        <div class="p-3 border-top">
                            <form method="GET" class="row g-2">
                                <?php if ($status): ?>
                                    <input type="hidden" name="status" value="<?= e($status) ?>">
                                <?php endif; ?>
                                <div class="col-md-8">
                                    <div class="input-group">
                                        <span class="input-group-text"><i data-feather="search" style="width:14px;height:14px;"></i></span>
                                        <input type="text" class="form-control" name="search"
                                               placeholder="Search tenant, property..."
                                               value="<?= e($search) ?>">
                                    </div>
                                </div>
                                <div class="col-md-4 d-flex gap-2">
                                    <button type="submit" class="btn btn-primary flex-fill">Filter</button>
                                    <a href="bookings.php" class="btn btn-outline-secondary">Reset</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Bookings table -->
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">#</th>
                                        <th>Tenant</th>
                                        <th>Property</th>
                                        <th>Duration</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Booked On</th>
                                        <th class="text-end pe-3">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($bookings)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-5">
                                                <i data-feather="calendar" style="width:40px;height:40px;opacity:.3;" class="d-block mx-auto mb-2"></i>
                                                No bookings found.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($bookings as $i => $b): ?>
                                            <tr>
                                                <td class="ps-3 text-muted small"><?= $pager['offset'] + $i + 1 ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <img src="<?= userAvatar($b['avatar']) ?>" 
                                                             class="rounded-circle" width="32" height="32" 
                                                             alt="" style="object-fit:cover;">
                                                        <div>
                                                            <p class="mb-0 small fw-semibold"><?= e($b['first_name'] . ' ' . $b['last_name']) ?></p>
                                                            <p class="mb-0 text-muted" style="font-size:.7rem;"><?= e($b['email']) ?></p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="small">
                                                    <a href="property-view.php?id=<?= $b['property_id'] ?>" class="text-decoration-none">
                                                        <?= e($b['property_title']) ?>
                                                    </a>
                                                </td>
                                                <td class="small">
                                                    <?= formatDate($b['start_date']) ?>
                                                    <?php if ($b['end_date']): ?>
                                                        <br><small class="text-muted">to <?= formatDate($b['end_date']) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="fw-semibold small">
                                                    <?= $b['total_amount'] ? '₦' . number_format($b['total_amount'], 2) : '—' ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $statusColors = ['pending'=>'warning', 'confirmed'=>'info', 'completed'=>'success', 'cancelled'=>'danger'];
                                                    $color = $statusColors[$b['status']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?= $color ?>"><?= ucfirst($b['status']) ?></span>
                                                </td>
                                                <td class="small text-muted"><?= formatDate($b['created_at']) ?></td>
                                                <td class="text-end pe-3">
                                                    <div class="dropdown">
                                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                            Actions
                                                        </button>
                                                        <ul class="dropdown-menu dropdown-menu-end">
                                                            <?php if ($b['status'] === 'pending'): ?>
                                                                <li>
                                                                    <form method="POST" class="d-inline">
                                                                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                                                        <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                                                        <input type="hidden" name="action" value="change_status">
                                                                        <input type="hidden" name="status" value="confirmed">
                                                                        <button type="submit" class="dropdown-item text-success">
                                                                            <i data-feather="check" class="me-2" style="width:14px;height:14px;"></i>Confirm
                                                                        </button>
                                                                    </form>
                                                                </li>
                                                                <li>
                                                                    <form method="POST" class="d-inline">
                                                                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                                                        <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                                                        <input type="hidden" name="action" value="change_status">
                                                                        <input type="hidden" name="status" value="cancelled">
                                                                        <button type="submit" class="dropdown-item text-warning">
                                                                            <i data-feather="x" class="me-2" style="width:14px;height:14px;"></i>Cancel
                                                                        </button>
                                                                    </form>
                                                                </li>
                                                            <?php elseif ($b['status'] === 'confirmed'): ?>
                                                                <li>
                                                                    <form method="POST" class="d-inline">
                                                                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                                                        <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                                                        <input type="hidden" name="action" value="change_status">
                                                                        <input type="hidden" name="status" value="completed">
                                                                        <button type="submit" class="dropdown-item text-success">
                                                                            <i data-feather="check-circle" class="me-2" style="width:14px;height:14px;"></i>Mark Complete
                                                                        </button>
                                                                    </form>
                                                                </li>
                                                            <?php endif; ?>
                                                            <li><hr class="dropdown-divider"></li>
                                                            <li>
                                                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this booking?')">
                                                                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                                                    <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                                                    <input type="hidden" name="action" value="delete">
                                                                    <button type="submit" class="dropdown-item text-danger">
                                                                        <i data-feather="trash-2" class="me-2" style="width:14px;height:14px;"></i>Delete
                                                                    </button>
                                                                </form>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($pager['total_pages'] > 1): ?>
                            <div class="d-flex justify-content-between align-items-center px-3 py-3 border-top">
                                <p class="mb-0 small text-muted">
                                    Showing <?= $pager['offset'] + 1 ?>–<?= min($pager['offset'] + $perPage, $total) ?> of <?= $total ?> bookings
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
