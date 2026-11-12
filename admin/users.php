<?php
/**
 * Admin — User Management
 * Rent Hob
 */
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('admin');

// ── Handle quick actions (suspend / activate / delete) ────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? '')) {
    $action  = $_POST['action']  ?? '';
    $user_id = (int)($_POST['user_id'] ?? 0);

    if ($user_id && $user_id !== currentUserId()) { // never act on yourself
        switch ($action) {
            case 'activate':
                $pdo->prepare('UPDATE users SET status=? WHERE id=?')->execute(['active', $user_id]);
                logActivity($pdo, currentUserId(), "Activated user #$user_id", 'users');
                setFlash('success', 'User activated successfully.');
                break;
            case 'suspend':
                $pdo->prepare('UPDATE users SET status=? WHERE id=?')->execute(['suspended', $user_id]);
                logActivity($pdo, currentUserId(), "Suspended user #$user_id", 'users');
                setFlash('success', 'User suspended.');
                break;
            case 'delete':
                $pdo->prepare('DELETE FROM users WHERE id=? AND role != ?')->execute([$user_id, 'admin']);
                logActivity($pdo, currentUserId(), "Deleted user #$user_id", 'users');
                setFlash('success', 'User deleted.');
                break;
        }
    } else {
        setFlash('error', 'Invalid action.');
    }
    redirect(BASE_URL . '/admin/users.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
}

// ── Filters ───────────────────────────────────────────────────────────────────
$search  = trim($_GET['search'] ?? '');
$role    = $_GET['role']   ?? '';
$status  = $_GET['status'] ?? '';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

$where  = ['1=1'];
$params = [];

if ($search) {
    $where[]  = '(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ?)';
    $term     = '%' . $search . '%';
    $params   = array_merge($params, [$term, $term, $term, $term]);
}
if ($role && in_array($role, ['admin','landlord','agent','tenant'])) {
    $where[]  = 'role = ?';
    $params[] = $role;
}
if ($status && in_array($status, ['active','pending','suspended','inactive'])) {
    $where[]  = 'status = ?';
    $params[] = $status;
}

$whereSQL = implode(' AND ', $where);

// Total count
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM users WHERE $whereSQL");
$stmtCount->execute($params);
$total = (int)$stmtCount->fetchColumn();
$pager = paginate($total, $perPage, $page);

// Fetch page
$stmt = $pdo->prepare(
    "SELECT id, first_name, last_name, email, phone, role, status, avatar, email_verified, created_at
     FROM users WHERE $whereSQL
     ORDER BY created_at DESC
     LIMIT ? OFFSET ?"
);
$stmt->execute(array_merge($params, [$perPage, $pager['offset']]));
$users = $stmt->fetchAll();

// Role counts for tabs
$roleCounts = $pdo->query(
    "SELECT role, COUNT(*) as total FROM users GROUP BY role"
)->fetchAll(PDO::FETCH_KEY_PAIR);

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Users — Rent Hob Admin</title>
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
      data-bggradient="bg-gradient-5"
      data-bs-spy="scroll" data-bs-target="#list-example"
      data-bs-smooth-scroll="true" tabindex="0">

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
                    <li class="nav-item"><a class="nav-link active" href="users.php"><i data-feather="users"></i><span>All Users</span></a></li>
                    <li class="nav-item"><a class="nav-link" href="users.php?role=tenant"><i data-feather="user"></i><span>Tenants</span><span class="badge bg-secondary ms-auto"><?= $roleCounts['tenant'] ?? 0 ?></span></a></li>
                    <li class="nav-item"><a class="nav-link" href="users.php?role=landlord"><i data-feather="home"></i><span>Landlords</span><span class="badge bg-secondary ms-auto"><?= $roleCounts['landlord'] ?? 0 ?></span></a></li>
                    <li class="nav-item"><a class="nav-link" href="users.php?role=agent"><i data-feather="briefcase"></i><span>Agents</span><span class="badge bg-secondary ms-auto"><?= $roleCounts['agent'] ?? 0 ?></span></a></li>
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

        <!-- ── MAIN CONTENT ───────────────────────────────────────────────────── -->
        <main class="adminuiux-content has-sidebar" onclick="contentClick()">
            <div class="container mt-2" id="main-content">

                <!-- Breadcrumb -->
                <div class="row mb-3">
                    <div class="col">
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb mb-1">
                                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Users</li>
                            </ol>
                        </nav>
                        <h5 class="mb-0 fw-semibold">User Management</h5>
                    </div>
                    <div class="col-auto align-self-center">
                        <a href="invite-agent.php" class="btn btn-primary btn-sm">
                            <i data-feather="user-plus" style="width:15px;height:15px;" class="me-1"></i> Invite Agent
                        </a>
                    </div>
                </div>

                <!-- Flash -->
                <?php if ($flash): ?>
                    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show mb-3">
                        <?= e($flash['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Role filter tabs -->
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body p-0">
                        <ul class="nav nav-tabs border-0 px-3 pt-3">
                            <li class="nav-item">
                                <a class="nav-link <?= !$role ? 'active' : '' ?>" href="users.php<?= $search ? '?search='.urlencode($search) : '' ?>">
                                    All <span class="badge bg-secondary ms-1"><?= array_sum($roleCounts) ?></span>
                                </a>
                            </li>
                            <?php foreach (['tenant'=>'Tenants','landlord'=>'Landlords','agent'=>'Agents','admin'=>'Admins'] as $r => $label): ?>
                                <li class="nav-item">
                                    <a class="nav-link <?= $role === $r ? 'active' : '' ?>"
                                       href="users.php?role=<?= $r ?><?= $search ? '&search='.urlencode($search) : '' ?>">
                                        <?= $label ?>
                                        <span class="badge bg-secondary ms-1"><?= $roleCounts[$r] ?? 0 ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                        <!-- Search + status filter -->
                        <div class="p-3 border-top">
                            <form method="GET" action="users.php" class="row g-2">
                                <?php if ($role): ?>
                                    <input type="hidden" name="role" value="<?= e($role) ?>">
                                <?php endif; ?>
                                <div class="col-md-6">
                                    <div class="input-group">
                                        <span class="input-group-text"><i data-feather="search" style="width:14px;height:14px;"></i></span>
                                        <input type="text" class="form-control" name="search"
                                               placeholder="Search name, email or phone..."
                                               value="<?= e($search) ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <select class="form-select" name="status">
                                        <option value="">All Statuses</option>
                                        <?php foreach (['active','pending','suspended','inactive'] as $s): ?>
                                            <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3 d-flex gap-2">
                                    <button type="submit" class="btn btn-primary flex-fill">Filter</button>
                                    <a href="users.php" class="btn btn-outline-secondary">Reset</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Users table -->
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">#</th>
                                        <th>User</th>
                                        <th>Phone</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Verified</th>
                                        <th>Joined</th>
                                        <th class="text-end pe-3">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($users)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-5">
                                                <i data-feather="users" style="width:40px;height:40px;opacity:.3;" class="d-block mx-auto mb-2"></i>
                                                No users found.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($users as $i => $user): ?>
                                            <tr>
                                                <td class="ps-3 text-muted small"><?= $pager['offset'] + $i + 1 ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <img src="<?= userAvatar($user['avatar'] ?? null) ?>"
                                                             class="rounded-circle" width="36" height="36"
                                                             alt="" style="object-fit:cover;">
                                                        <div>
                                                            <p class="mb-0 small fw-semibold"><?= e($user['first_name'] . ' ' . $user['last_name']) ?></p>
                                                            <p class="mb-0 text-muted" style="font-size:.75rem;"><?= e($user['email']) ?></p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="small text-muted"><?= $user['phone'] ? e($user['phone']) : '—' ?></td>
                                                <td>
                                                    <?php
                                                    $roleColors = ['admin'=>'danger','landlord'=>'primary','agent'=>'info','tenant'=>'success'];
                                                    $rc = $roleColors[$user['role']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?= $rc ?>"><?= ucfirst(e($user['role'])) ?></span>
                                                </td>
                                                <td><?= statusBadge($user['status']) ?></td>
                                                <td>
                                                    <?php if ($user['email_verified']): ?>
                                                        <span class="text-success"><i data-feather="check-circle" style="width:16px;height:16px;"></i></span>
                                                    <?php else: ?>
                                                        <span class="text-muted"><i data-feather="circle" style="width:16px;height:16px;"></i></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="small text-muted"><?= formatDate($user['created_at']) ?></td>
                                                <td class="text-end pe-3">
                                                    <div class="dropdown">
                                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                            Actions
                                                        </button>
                                                        <ul class="dropdown-menu dropdown-menu-end">
                                                            <li><a class="dropdown-item" href="user-view.php?id=<?= $user['id'] ?>"><i data-feather="eye" class="me-2" style="width:14px;height:14px;"></i>View</a></li>
                                                            <li><a class="dropdown-item" href="user-edit.php?id=<?= $user['id'] ?>"><i data-feather="edit-2" class="me-2" style="width:14px;height:14px;"></i>Edit</a></li>
                                                            <?php if ($user['id'] !== currentUserId()): ?>
                                                                <li><hr class="dropdown-divider"></li>
                                                                <?php if ($user['status'] === 'suspended'): ?>
                                                                    <li>
                                                                        <form method="POST" class="d-inline">
                                                                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                                            <input type="hidden" name="action" value="activate">
                                                                            <button class="dropdown-item text-success" type="submit">
                                                                                <i data-feather="check-circle" class="me-2" style="width:14px;height:14px;"></i>Activate
                                                                            </button>
                                                                        </form>
                                                                    </li>
                                                                <?php else: ?>
                                                                    <li>
                                                                        <form method="POST" class="d-inline">
                                                                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                                            <input type="hidden" name="action" value="suspend">
                                                                            <button class="dropdown-item text-warning" type="submit">
                                                                                <i data-feather="slash" class="me-2" style="width:14px;height:14px;"></i>Suspend
                                                                            </button>
                                                                        </form>
                                                                    </li>
                                                                <?php endif; ?>
                                                                <?php if ($user['role'] !== 'admin'): ?>
                                                                    <li>
                                                                        <form method="POST" class="d-inline"
                                                                              onsubmit="return confirm('Delete this user? This cannot be undone.')">
                                                                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                                            <input type="hidden" name="action" value="delete">
                                                                            <button class="dropdown-item text-danger" type="submit">
                                                                                <i data-feather="trash-2" class="me-2" style="width:14px;height:14px;"></i>Delete
                                                                            </button>
                                                                        </form>
                                                                    </li>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
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
                                    Showing <?= $pager['offset'] + 1 ?>–<?= min($pager['offset'] + $perPage, $total) ?> of <?= $total ?> users
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
                            <div class="px-3 py-2 border-top">
                                <p class="mb-0 small text-muted"><?= $total ?> user<?= $total !== 1 ? 's' : '' ?> found</p>
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

    <!-- Mobile footer -->
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
