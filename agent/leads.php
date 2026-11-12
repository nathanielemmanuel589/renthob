<?php
/**
 * Agent - Tenant Leads
 * Manage potential tenants and track lead status
 * Rent Hob
 */
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/agent-functions.php';

requireRole('agent');

$agentId = currentUserId();

// Get filter
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query
$sql = "SELECT tl.*, p.title as property_title, p.price as property_price
        FROM tenant_leads tl
        LEFT JOIN properties p ON p.id = tl.interested_property_id
        WHERE tl.agent_id = ?";

$params = [$agentId];

if ($statusFilter) {
    $sql .= " AND tl.status = ?";
    $params[] = $statusFilter;
}

if ($searchQuery) {
    $sql .= " AND (tl.name LIKE ? OR tl.phone LIKE ? OR tl.email LIKE ?)";
    $searchTerm = "%{$searchQuery}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$sql .= " ORDER BY tl.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$leads = $stmt->fetchAll();

// Count by status
$stmt = $pdo->prepare('
    SELECT status, COUNT(*) as count
    FROM tenant_leads
    WHERE agent_id = ?
    GROUP BY status
');
$stmt->execute([$agentId]);
$statusCounts = [];
while ($row = $stmt->fetch()) {
    $statusCounts[$row['status']] = $row['count'];
}

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Tenant Leads — Rent Hob Agent</title>
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
                        <h4 class="mb-1 fw-semibold">Tenant Leads</h4>
                        <p class="text-muted mb-0">Manage potential tenants and track lead status</p>
                    </div>
                    <div class="col-auto align-self-center">
                        <button class="btn btn-success" onclick="alert('Add Lead feature coming soon!')">
                            <i data-feather="plus" style="width:16px;height:16px;" class="me-1"></i> Add New Lead
                        </button>
                    </div>
                </div>

                <?php if ($flash): ?>
                    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show mb-3">
                        <?= e($flash['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Filters -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <form method="GET" action="" class="d-flex gap-2">
                                    <input type="text" name="search" class="form-control" placeholder="Search leads..." value="<?= e($searchQuery) ?>">
                                    <button type="submit" class="btn btn-primary">
                                        <i data-feather="search" style="width:16px;height:16px;"></i>
                                    </button>
                                    <?php if ($searchQuery): ?>
                                        <a href="leads.php" class="btn btn-outline-secondary">Clear</a>
                                    <?php endif; ?>
                                </form>
                            </div>
                            <div class="col-md-6">
                                <div class="btn-group w-100" role="group">
                                    <a href="leads.php" class="btn btn-<?= !$statusFilter ? 'primary' : 'outline-primary' ?> btn-sm">
                                        All (<?= array_sum($statusCounts) ?>)
                                    </a>
                                    <a href="leads.php?status=new" class="btn btn-<?= $statusFilter === 'new' ? 'info' : 'outline-info' ?> btn-sm">
                                        New (<?= $statusCounts['new'] ?? 0 ?>)
                                    </a>
                                    <a href="leads.php?status=contacted" class="btn btn-<?= $statusFilter === 'contacted' ? 'success' : 'outline-success' ?> btn-sm">
                                        Contacted (<?= $statusCounts['contacted'] ?? 0 ?>)
                                    </a>
                                    <a href="leads.php?status=interested" class="btn btn-<?= $statusFilter === 'interested' ? 'warning' : 'outline-warning' ?> btn-sm">
                                        Interested (<?= $statusCounts['interested'] ?? 0 ?>)
                                    </a>
                                    <a href="leads.php?status=converted" class="btn btn-<?= $statusFilter === 'converted' ? 'success' : 'outline-success' ?> btn-sm">
                                        Converted (<?= $statusCounts['converted'] ?? 0 ?>)
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <?php if (empty($leads)): ?>
                            <div class="text-center py-5">
                                <i data-feather="user-plus" style="width:64px;height:64px;" class="text-muted mb-3"></i>
                                <h5 class="text-muted">No Leads Yet</h5>
                                <p class="text-muted">
                                    <?php if ($searchQuery): ?>
                                        No leads match your search. <a href="leads.php">Clear search</a>
                                    <?php else: ?>
                                        Start adding potential tenants to track and convert them.
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Name</th>
                                            <th>Contact</th>
                                            <th>Interested Property</th>
                                            <th>Budget</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($leads as $lead): ?>
                                            <tr>
                                                <td>
                                                    <p class="mb-0 fw-semibold small"><?= e($lead['name']) ?></p>
                                                    <?php if ($lead['source']): ?>
                                                        <small class="text-muted">Source: <?= e($lead['source']) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small><?= e($lead['phone']) ?></small><br>
                                                    <?php if ($lead['email']): ?>
                                                        <small class="text-muted"><?= e($lead['email']) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($lead['property_title']): ?>
                                                        <a href="property-view.php?id=<?= $lead['interested_property_id'] ?>" class="text-decoration-none small">
                                                            <?= e(truncate($lead['property_title'], 30)) ?>
                                                        </a>
                                                        <br><small class="text-muted"><?= formatCurrency($lead['property_price']) ?></small>
                                                    <?php else: ?>
                                                        <small class="text-muted">Not specified</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($lead['budget_min'] && $lead['budget_max']): ?>
                                                        <small><?= formatCurrency($lead['budget_min']) ?> - <?= formatCurrency($lead['budget_max']) ?></small>
                                                    <?php else: ?>
                                                        <small class="text-muted">N/A</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= leadStatusBadge($lead['status']) ?></td>
                                                <td><small><?= formatDate($lead['created_at']) ?></small></td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="tel:<?= e($lead['phone']) ?>" class="btn btn-outline-primary" title="Call">
                                                            <i data-feather="phone" style="width:14px;height:14px;"></i>
                                                        </a>
                                                        <?php if ($lead['email']): ?>
                                                            <a href="mailto:<?= e($lead['email']) ?>" class="btn btn-outline-primary" title="Email">
                                                                <i data-feather="mail" style="width:14px;height:14px;"></i>
                                                            </a>
                                                        <?php endif; ?>
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
