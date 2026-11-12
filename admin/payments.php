<?php
/**
 * Admin — Payments Management
 * Rent Hob
 */
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('admin');

// ── Handle status change ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    $paymentId = (int)($_POST['payment_id'] ?? 0);

    if ($paymentId > 0) {
        if ($action === 'mark_paid') {
            $pdo->prepare('UPDATE payments SET status=?, paid_date=NOW() WHERE id=?')->execute(['paid', $paymentId]);
            logActivity($pdo, currentUserId(), "Marked payment #$paymentId as paid", 'payments');
            setFlash('success', 'Payment marked as paid.');
        } elseif ($action === 'mark_failed') {
            $pdo->prepare('UPDATE payments SET status=? WHERE id=?')->execute(['failed', $paymentId]);
            logActivity($pdo, currentUserId(), "Marked payment #$paymentId as failed", 'payments');
            setFlash('success', 'Payment marked as failed.');
        } elseif ($action === 'refund') {
            $pdo->prepare('UPDATE payments SET status=? WHERE id=?')->execute(['refunded', $paymentId]);
            logActivity($pdo, currentUserId(), "Refunded payment #$paymentId", 'payments');
            setFlash('success', 'Payment refunded.');
        } elseif ($action === 'delete') {
            $pdo->prepare('DELETE FROM payments WHERE id=?')->execute([$paymentId]);
            logActivity($pdo, currentUserId(), "Deleted payment #$paymentId", 'payments');
            setFlash('success', 'Payment deleted.');
        }
    }
    redirect(BASE_URL . '/admin/payments.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
}

// ── Filters ───────────────────────────────────────────────────────────────────
$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$method = $_GET['method'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

$where = ['1=1'];
$params = [];

if ($search) {
    $where[] = '(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR p.reference LIKE ?)';
    $term = '%' . $search . '%';
    $params = array_merge($params, [$term, $term, $term, $term]);
}
if ($status && in_array($status, ['pending', 'paid', 'failed', 'refunded'])) {
    $where[] = 'p.status = ?';
    $params[] = $status;
}
if ($method && in_array($method, ['bank_transfer', 'card', 'cash', 'gateway'])) {
    $where[] = 'p.method = ?';
    $params[] = $method;
}

$whereSQL = implode(' AND ', $where);

// Total count
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM payments p JOIN users u ON p.tenant_id=u.id WHERE $whereSQL");
$stmtCount->execute($params);
$total = (int)$stmtCount->fetchColumn();
$pager = paginate($total, $perPage, $page);

// Fetch payments
$stmt = $pdo->prepare(
    "SELECT p.*, 
            u.first_name, u.last_name, u.email, u.avatar,
            l.property_id
     FROM payments p
     JOIN users u ON p.tenant_id = u.id
     JOIN leases l ON p.lease_id = l.id
     WHERE $whereSQL
     ORDER BY p.created_at DESC
     LIMIT ? OFFSET ?"
);
$stmt->execute(array_merge($params, [$perPage, $pager['offset']]));
$payments = $stmt->fetchAll();

// Status & method counts
$statusCounts = $pdo->query("SELECT status, COUNT(*) as cnt FROM payments GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$methodCounts = $pdo->query("SELECT payment_method, COUNT(*) as cnt FROM payments GROUP BY payment_method")->fetchAll(PDO::FETCH_KEY_PAIR);

// Total revenue
$totalRevenue = $pdo->query("SELECT SUM(amount) FROM payments WHERE status='paid'")->fetchColumn();

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Payments — Rent Hob Admin</title>
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
                    <li class="nav-item"><a class="nav-link active" href="payments.php"><i data-feather="credit-card"></i><span>Payments</span></a></li>
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
                                <li class="breadcrumb-item active">Payments</li>
                            </ol>
                        </nav>
                        <h5 class="mb-0 fw-semibold">Payment Management</h5>
                    </div>
                </div>

                <!-- Flash -->
                <?php if ($flash): ?>
                    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show mb-3">
                        <?= e($flash['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Stats cards -->
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-muted small mb-1">Total Revenue</p>
                                        <h4 class="mb-0 fw-bold">₦<?= number_format($totalRevenue ?? 0, 2) ?></h4>
                                    </div>
                                    <div class="bg-success bg-opacity-10 p-3 rounded">
                                        <i data-feather="dollar-sign" class="text-success" style="width:24px;height:24px;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-muted small mb-1">Paid</p>
                                        <h4 class="mb-0 fw-bold"><?= $statusCounts['paid'] ?? 0 ?></h4>
                                    </div>
                                    <div class="bg-success bg-opacity-10 p-3 rounded">
                                        <i data-feather="check-circle" class="text-success" style="width:24px;height:24px;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-muted small mb-1">Pending</p>
                                        <h4 class="mb-0 fw-bold"><?= $statusCounts['pending'] ?? 0 ?></h4>
                                    </div>
                                    <div class="bg-warning bg-opacity-10 p-3 rounded">
                                        <i data-feather="clock" class="text-warning" style="width:24px;height:24px;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-muted small mb-1">Failed</p>
                                        <h4 class="mb-0 fw-bold"><?= $statusCounts['failed'] ?? 0 ?></h4>
                                    </div>
                                    <div class="bg-danger bg-opacity-10 p-3 rounded">
                                        <i data-feather="x-circle" class="text-danger" style="width:24px;height:24px;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
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
                                           placeholder="Search tenant, reference..."
                                           value="<?= e($search) ?>">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" name="status">
                                    <option value="">All Statuses</option>
                                    <?php foreach (['pending', 'paid', 'failed', 'refunded'] as $s): ?>
                                        <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" name="method">
                                    <option value="">All Methods</option>
                                    <?php foreach (['gateway'=>'Gateway', 'bank_transfer'=>'Bank Transfer', 'card'=>'Card', 'cash'=>'Cash'] as $m => $label): ?>
                                        <option value="<?= $m ?>" <?= $method === $m ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex gap-2">
                                <button type="submit" class="btn btn-primary flex-fill">Filter</button>
                                <a href="payments.php" class="btn btn-outline-secondary">Reset</a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Payments table -->
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">#</th>
                                        <th>Tenant</th>
                                        <th>Reference</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Status</th>
                                        <th>Paid At</th>
                                        <th class="text-end pe-3">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($payments)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-5">
                                                <i data-feather="credit-card" style="width:40px;height:40px;opacity:.3;" class="d-block mx-auto mb-2"></i>
                                                No payments found.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($payments as $i => $p): ?>
                                            <tr>
                                                <td class="ps-3 text-muted small"><?= $pager['offset'] + $i + 1 ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <img src="<?= userAvatar($p['avatar']) ?>" 
                                                             class="rounded-circle" width="32" height="32" 
                                                             alt="" style="object-fit:cover;">
                                                        <div>
                                                            <p class="mb-0 small fw-semibold"><?= e($p['first_name'] . ' ' . $p['last_name']) ?></p>
                                                            <p class="mb-0 text-muted" style="font-size:.7rem;"><?= e($p['email']) ?></p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="small font-monospace"><?= $p['reference'] ? e($p['reference']) : '—' ?></td>
                                                <td class="fw-semibold">₦<?= number_format($p['amount'], 2) ?></td>
                                                <td>
                                                    <?php
                                                    $methodLabels = ['gateway'=>'Gateway', 'bank_transfer'=>'Bank Transfer', 'card'=>'Card', 'cash'=>'Cash'];
                                                    ?>
                                                    <span class="badge bg-secondary"><?= $methodLabels[$p['method']] ?? ucfirst($p['method']) ?></span>
                                                </td>
                                                <td>
                                                    <?php
                                                    $statusColors = ['pending'=>'warning', 'paid'=>'success', 'failed'=>'danger', 'refunded'=>'info'];
                                                    $color = $statusColors[$p['status']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?= $color ?>"><?= ucfirst($p['status']) ?></span>
                                                </td>
                                                <td class="small text-muted"><?= $p['paid_date'] ? formatDate($p['paid_date']) : '—' ?></td>
                                                <td class="text-end pe-3">
                                                    <div class="dropdown">
                                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                            Actions
                                                        </button>
                                                        <ul class="dropdown-menu dropdown-menu-end">
                                                            <?php if ($p['status'] === 'pending'): ?>
                                                                <li>
                                                                    <form method="POST" class="d-inline">
                                                                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                                                        <input type="hidden" name="payment_id" value="<?= $p['id'] ?>">
                                                                        <input type="hidden" name="action" value="mark_paid">
                                                                        <button type="submit" class="dropdown-item text-success">
                                                                            <i data-feather="check-circle" class="me-2" style="width:14px;height:14px;"></i>Mark as Paid
                                                                        </button>
                                                                    </form>
                                                                </li>
                                                                <li>
                                                                    <form method="POST" class="d-inline">
                                                                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                                                        <input type="hidden" name="payment_id" value="<?= $p['id'] ?>">
                                                                        <input type="hidden" name="action" value="mark_failed">
                                                                        <button type="submit" class="dropdown-item text-danger">
                                                                            <i data-feather="x-circle" class="me-2" style="width:14px;height:14px;"></i>Mark as Failed
                                                                        </button>
                                                                    </form>
                                                                </li>
                                                            <?php endif; ?>
                                                            <?php if ($p['status'] === 'paid'): ?>
                                                                <li>
                                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Refund this payment?')">
                                                                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                                                        <input type="hidden" name="payment_id" value="<?= $p['id'] ?>">
                                                                        <input type="hidden" name="action" value="refund">
                                                                        <button type="submit" class="dropdown-item text-warning">
                                                                            <i data-feather="rotate-ccw" class="me-2" style="width:14px;height:14px;"></i>Refund
                                                                        </button>
                                                                    </form>
                                                                </li>
                                                            <?php endif; ?>
                                                            <li><hr class="dropdown-divider"></li>
                                                            <li>
                                                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this payment?')">
                                                                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                                                    <input type="hidden" name="payment_id" value="<?= $p['id'] ?>">
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
                                    Showing <?= $pager['offset'] + 1 ?>–<?= min($pager['offset'] + $perPage, $total) ?> of <?= $total ?> payments
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
