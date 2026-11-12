<?php
/**
 * Agent - My Tenants
 * Shows tenants on agent's assigned properties
 * Rent Hob
 */
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/agent-functions.php';

requireRole('agent');

$agentId = currentUserId();

// Get tenants from active leases on agent's properties
$stmt = $pdo->prepare('
    SELECT DISTINCT
        u.id, u.first_name, u.last_name, u.email, u.phone, u.avatar,
        p.id as property_id, p.title as property_title, p.price as rent_amount,
        l.id as lease_id, l.start_date, l.end_date, l.status as lease_status,
        DATEDIFF(l.end_date, CURDATE()) as days_remaining
    FROM users u
    INNER JOIN leases l ON l.tenant_id = u.id
    INNER JOIN properties p ON p.id = l.property_id
    INNER JOIN agent_properties ap ON ap.property_id = p.id
    WHERE ap.agent_id = ? AND ap.status = "active"
    AND l.status IN ("active", "expiring")
    ORDER BY l.end_date ASC
');
$stmt->execute([$agentId]);
$tenants = $stmt->fetchAll();

// Count by status
$activeCount = 0;
$expiringCount = 0;
foreach ($tenants as $tenant) {
    if ($tenant['days_remaining'] <= 30) {
        $expiringCount++;
    }
    $activeCount++;
}

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>My Tenants — Rent Hob Agent</title>
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/img/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Open+Sans:ital,wght@0,300..800;1,300..800&display=swap" rel="stylesheet">
    <style>:root{--adminuiux-content-font:"Open Sans",sans-serif;--adminuiux-content-font-weight:400;--adminuiux-title-font:"Inter",sans-serif;--adminuiux-title-font-weight:600;}</style>
    <script defer src="<?= BASE_URL ?>/dashboard/assets/js/third-party.js"></script>
    <script defer src="<?= BASE_URL ?>/dashboard/assets/js/app.js"></script>
    <script defer src="<?= BASE_URL ?>/dashboard/assets/js/core.js"></script>
    <link href="<?= BASE_URL ?>/dashboard/assets/css/app.css" rel="stylesheet">
</head>
<body class="main-bg main-bg-blur main-bg-opac roundedui adminuiux-sidebar-standard adminuiux-sidebar-fill-white adminuiux-mobile-footer-fill-theme adminuiux-sidebar-boxed adminuiux-header-transparent adminuiux-header-standard theme-orange bg-gradient-4 scrollup" data-theme="theme-orange" data-sidebarfill="adminuiux-sidebar-fill-white" data-sidebarlayout="adminuiux-sidebar-boxed" data-headerlayout="adminuiux-header-standard" data-headerfill="adminuiux-header-transparent" data-bggradient="bg-gradient-4">
    <div class="pageloader"><div class="container h-100"><div class="row justify-content-center align-items-center text-center h-100"><div class="col-auto"><img src="<?= BASE_URL ?>/dashboard/assets/img/logo-512.png" alt="" class="height-100 mb-3"><p class="h3 mb-0">Rent Hob</p><div class="loader10 mb-3 mx-auto"></div></div></div></div></div>
    <header class="adminuiux-header inner-page"><nav class="navbar navbar-expand-lg fixed-top"><div class="container-fluid"><button class="btn btn-link btn-square sidebar-toggler ms-1" type="button" onclick="initSidebar()"><i class="sidebar-svg" data-feather="menu"></i></button><a class="navbar-brand" href="dashboard.php"><img data-bs-img="light" src="<?= BASE_URL ?>/assets/img/logo-2.svg" alt="Rent Hob" style="max-height:32px;"><img data-bs-img="dark" src="<?= BASE_URL ?>/assets/img/logo-2.svg" alt="Rent Hob" style="max-height:32px;"><div class="lh-20"><span class="h4">Rent Hob</span><p class="company-tagline">Agent Portal</p></div></a><div class="ms-auto d-flex align-items-center gap-1"><div class="dropdown"><button class="btn btn-link no-caret dropdown-toggle d-flex align-items-center gap-2 px-2" type="button" data-bs-toggle="dropdown"><img src="<?= userAvatar($_SESSION['user_avatar'] ?? null) ?>" alt="Agent" class="rounded-circle" width="34" height="34" style="object-fit:cover;"><span class="d-none d-md-inline small fw-semibold"><?= e($_SESSION['user_name']) ?></span></button><ul class="dropdown-menu dropdown-menu-end"><li><a class="dropdown-item" href="profile.php"><i data-feather="user" class="me-2" style="width:15px;height:15px;"></i> My Profile</a></li><li><a class="dropdown-item" href="settings.php"><i data-feather="settings" class="me-2" style="width:15px;height:15px;"></i> Settings</a></li><li><hr class="dropdown-divider"></li><li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/logout.php"><i data-feather="log-out" class="me-2" style="width:15px;height:15px;"></i> Logout</a></li></ul></div></div></div></nav></header>
    <div class="adminuiux-wrap">
        <?php include '../includes/agent-sidebar.php'; ?>
        <main class="adminuiux-content has-sidebar" onclick="contentClick()">
            <div class="container mt-2" id="main-content">
                
                <div class="row mb-3">
                    <div class="col">
                        <h4 class="mb-1 fw-semibold">My Tenants</h4>
                        <p class="text-muted mb-0">Tenants on your assigned properties</p>
                    </div>
                    <div class="col-auto align-self-center">
                        <span class="badge bg-success px-3 py-2"><?= $activeCount ?> Active Tenants</span>
                        <?php if ($expiringCount > 0): ?>
                            <span class="badge bg-warning text-dark px-3 py-2"><?= $expiringCount ?> Expiring Soon</span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($flash): ?>
                    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show mb-3">
                        <?= e($flash['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <?php if (empty($tenants)): ?>
                            <div class="text-center py-5">
                                <i data-feather="users" style="width:64px;height:64px;" class="text-muted mb-3"></i>
                                <h5 class="text-muted">No Tenants Yet</h5>
                                <p class="text-muted">Tenants will appear here once they have active leases on your assigned properties.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Tenant</th>
                                            <th>Property</th>
                                            <th>Lease Period</th>
                                            <th>Rent Amount</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tenants as $tenant): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <img src="<?= userAvatar($tenant['avatar']) ?>" class="rounded-circle" width="40" height="40" alt="" style="object-fit:cover;">
                                                        <div>
                                                            <p class="mb-0 fw-semibold small"><?= e($tenant['first_name'] . ' ' . $tenant['last_name']) ?></p>
                                                            <small class="text-muted"><?= e($tenant['phone']) ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <a href="property-view.php?id=<?= $tenant['property_id'] ?>" class="text-decoration-none">
                                                        <?= e($tenant['property_title']) ?>
                                                    </a>
                                                </td>
                                                <td>
                                                    <small><?= formatDate($tenant['start_date']) ?></small><br>
                                                    <small class="text-muted">to <?= formatDate($tenant['end_date']) ?></small>
                                                    <?php if ($tenant['days_remaining'] <= 30): ?>
                                                        <br><small class="text-warning">Expires in <?= $tenant['days_remaining'] ?> days</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="fw-semibold"><?= formatCurrency($tenant['rent_amount']) ?></td>
                                                <td>
                                                    <?php if ($tenant['days_remaining'] <= 30): ?>
                                                        <span class="badge bg-warning text-dark">Expiring Soon</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="tel:<?= e($tenant['phone']) ?>" class="btn btn-outline-primary" title="Call">
                                                            <i data-feather="phone" style="width:14px;height:14px;"></i>
                                                        </a>
                                                        <a href="mailto:<?= e($tenant['email']) ?>" class="btn btn-outline-primary" title="Email">
                                                            <i data-feather="mail" style="width:14px;height:14px;"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
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
    </div>
</body>
</html>
