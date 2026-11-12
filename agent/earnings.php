<?php
/**
 * Agent - Commission & Earnings
 * Track commission payments and earnings
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
$periodFilter = isset($_GET['period']) ? $_GET['period'] : '';

// Build commission query
$sql = "SELECT c.*,
        p.title as property_title, p.address as property_address,
        u.first_name as tenant_first_name, u.last_name as tenant_last_name,
        ll.first_name as landlord_first_name, ll.last_name as landlord_last_name
        FROM agent_commissions c
        LEFT JOIN properties p ON p.id = c.property_id
        LEFT JOIN users u ON u.id = c.tenant_id
        LEFT JOIN users ll ON ll.id = c.landlord_id
        WHERE c.agent_id = ?";

$params = [$agentId];

if ($statusFilter) {
    $sql .= " AND c.status = ?";
    $params[] = $statusFilter;
}

if ($periodFilter === 'this_month') {
    $sql .= " AND MONTH(c.created_at) = MONTH(CURDATE()) AND YEAR(c.created_at) = YEAR(CURDATE())";
} elseif ($periodFilter === 'last_month') {
    $sql .= " AND MONTH(c.created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) 
              AND YEAR(c.created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
} elseif ($periodFilter === 'this_year') {
    $sql .= " AND YEAR(c.created_at) = YEAR(CURDATE())";
}

$sql .= " ORDER BY c.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$commissions = $stmt->fetchAll();

// Calculate summary statistics
$totalEarned = 0;
$pendingAmount = 0;
$approvedAmount = 0;
$paidAmount = 0;
$thisMonthEarnings = 0;

$stmt = $pdo->prepare('
    SELECT 
        SUM(CASE WHEN status = "pending" THEN commission_amount ELSE 0 END) as pending,
        SUM(CASE WHEN status = "approved" THEN commission_amount ELSE 0 END) as approved,
        SUM(CASE WHEN status = "paid" THEN commission_amount ELSE 0 END) as paid,
        SUM(CASE WHEN status IN ("approved", "paid") THEN commission_amount ELSE 0 END) as total_earned,
        SUM(CASE WHEN status IN ("approved", "paid") 
            AND MONTH(created_at) = MONTH(CURDATE()) 
            AND YEAR(created_at) = YEAR(CURDATE()) 
            THEN commission_amount ELSE 0 END) as this_month
    FROM agent_commissions
    WHERE agent_id = ?
');
$stmt->execute([$agentId]);
$summary = $stmt->fetch();

$pendingAmount = (float)($summary['pending'] ?? 0);
$approvedAmount = (float)($summary['approved'] ?? 0);
$paidAmount = (float)($summary['paid'] ?? 0);
$totalEarned = (float)($summary['total_earned'] ?? 0);
$thisMonthEarnings = (float)($summary['this_month'] ?? 0);

// Count by status
$stmt = $pdo->prepare('
    SELECT status, COUNT(*) as count
    FROM agent_commissions
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
    <title>Commission & Earnings — Rent Hob Agent</title>
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
                        <h4 class="mb-1 fw-semibold">Commission & Earnings</h4>
                        <p class="text-muted mb-0">Track your commission payments and earnings</p>
                    </div>
                </div>

                <?php if ($flash): ?>
                    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show mb-3">
                        <?= e($flash['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Summary Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            <div class="card-body text-white">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <h6 class="text-white mb-0 opacity-75">Total Earned</h6>
                                    <i data-feather="trending-up" style="width:20px;height:20px;" class="opacity-75"></i>
                                </div>
                                <h3 class="mb-0 fw-bold"><?= formatCurrency($totalEarned) ?></h3>
                                <small class="opacity-75">Approved + Paid</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <div class="card-body text-white">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <h6 class="text-white mb-0 opacity-75">This Month</h6>
                                    <i data-feather="calendar" style="width:20px;height:20px;" class="opacity-75"></i>
                                </div>
                                <h3 class="mb-0 fw-bold"><?= formatCurrency($thisMonthEarnings) ?></h3>
                                <small class="opacity-75"><?= date('F Y') ?></small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                            <div class="card-body text-white">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <h6 class="text-white mb-0 opacity-75">Approved</h6>
                                    <i data-feather="check-circle" style="width:20px;height:20px;" class="opacity-75"></i>
                                </div>
                                <h3 class="mb-0 fw-bold"><?= formatCurrency($approvedAmount) ?></h3>
                                <small class="opacity-75">Awaiting payment</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                            <div class="card-body text-white">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <h6 class="text-white mb-0 opacity-75">Paid</h6>
                                    <i data-feather="dollar-sign" style="width:20px;height:20px;" class="opacity-75"></i>
                                </div>
                                <h3 class="mb-0 fw-bold"><?= formatCurrency($paidAmount) ?></h3>
                                <small class="opacity-75">Received</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small text-muted mb-2">Filter by Status</label>
                                <div class="btn-group w-100" role="group">
                                    <a href="earnings.php" class="btn btn-<?= !$statusFilter ? 'primary' : 'outline-primary' ?> btn-sm">
                                        All (<?= array_sum($statusCounts) ?>)
                                    </a>
                                    <a href="earnings.php?status=pending" class="btn btn-<?= $statusFilter === 'pending' ? 'warning' : 'outline-warning' ?> btn-sm">
                                        Pending (<?= $statusCounts['pending'] ?? 0 ?>)
                                    </a>
                                    <a href="earnings.php?status=approved" class="btn btn-<?= $statusFilter === 'approved' ? 'info' : 'outline-info' ?> btn-sm">
                                        Approved (<?= $statusCounts['approved'] ?? 0 ?>)
                                    </a>
                                    <a href="earnings.php?status=paid" class="btn btn-<?= $statusFilter === 'paid' ? 'success' : 'outline-success' ?> btn-sm">
                                        Paid (<?= $statusCounts['paid'] ?? 0 ?>)
                                    </a>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small text-muted mb-2">Filter by Period</label>
                                <div class="btn-group w-100" role="group">
                                    <a href="earnings.php" class="btn btn-<?= !$periodFilter ? 'secondary' : 'outline-secondary' ?> btn-sm">
                                        All Time
                                    </a>
                                    <a href="earnings.php?period=this_month" class="btn btn-<?= $periodFilter === 'this_month' ? 'info' : 'outline-info' ?> btn-sm">
                                        This Month
                                    </a>
                                    <a href="earnings.php?period=last_month" class="btn btn-<?= $periodFilter === 'last_month' ? 'info' : 'outline-info' ?> btn-sm">
                                        Last Month
                                    </a>
                                    <a href="earnings.php?period=this_year" class="btn btn-<?= $periodFilter === 'this_year' ? 'secondary' : 'outline-secondary' ?> btn-sm">
                                        This Year
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Commission History -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">Commission History</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($commissions)): ?>
                            <div class="text-center py-5">
                                <i data-feather="dollar-sign" style="width:64px;height:64px;" class="text-muted mb-3"></i>
                                <h5 class="text-muted">No Commission Records</h5>
                                <p class="text-muted">
                                    <?php if ($statusFilter || $periodFilter): ?>
                                        No commission records match your filters. <a href="earnings.php">Clear filters</a>
                                    <?php else: ?>
                                        Commission records will appear here once properties are leased.
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Date</th>
                                            <th>Property</th>
                                            <th>Tenant</th>
                                            <th>Landlord</th>
                                            <th>Transaction</th>
                                            <th>Commission</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($commissions as $commission): ?>
                                            <tr>
                                                <td>
                                                    <small><?= formatDate($commission['created_at'], 'M d, Y') ?></small>
                                                    <br><small class="text-muted"><?= date('g:i A', strtotime($commission['created_at'])) ?></small>
                                                </td>
                                                <td>
                                                    <?php if ($commission['property_title']): ?>
                                                        <a href="property-view.php?id=<?= $commission['property_id'] ?>" class="text-decoration-none small">
                                                            <?= e(truncate($commission['property_title'], 30)) ?>
                                                        </a>
                                                        <br><small class="text-muted"><?= e(truncate($commission['property_address'] ?? '', 30)) ?></small>
                                                    <?php else: ?>
                                                        <small class="text-muted">N/A</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($commission['tenant_first_name']): ?>
                                                        <small><?= e($commission['tenant_first_name'] . ' ' . $commission['tenant_last_name']) ?></small>
                                                    <?php else: ?>
                                                        <small class="text-muted">N/A</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($commission['landlord_first_name']): ?>
                                                        <small><?= e($commission['landlord_first_name'] . ' ' . $commission['landlord_last_name']) ?></small>
                                                    <?php else: ?>
                                                        <small class="text-muted">N/A</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small class="fw-semibold"><?= formatCurrency($commission['transaction_amount'] ?? 0) ?></small>
                                                    <br><small class="text-muted"><?= e($commission['transaction_type'] ?? 'N/A') ?></small>
                                                </td>
                                                <td>
                                                    <span class="fw-bold text-success"><?= formatCurrency($commission['commission_amount']) ?></span>
                                                    <?php if ($commission['commission_rate']): ?>
                                                        <br><small class="text-muted"><?= number_format($commission['commission_rate'], 1) ?>%</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= commissionStatusBadge($commission['status']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Summary Footer -->
                            <div class="border-top pt-3 mt-3">
                                <div class="row">
                                    <div class="col-md-8">
                                        <p class="text-muted small mb-0">
                                            <i data-feather="info" style="width:14px;height:14px;"></i>
                                            Commission status is controlled by Admin/Landlord. Pending commissions will be reviewed and approved before payment.
                                        </p>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <p class="mb-1"><strong>Total Displayed:</strong> <?= formatCurrency(array_sum(array_column($commissions, 'commission_amount'))) ?></p>
                                        <p class="mb-0 text-muted small"><?= count($commissions) ?> transaction(s)</p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Info Card -->
                <div class="card border-0 shadow-sm mt-4 bg-light">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-1 text-center">
                                <i data-feather="alert-circle" style="width:40px;height:40px;" class="text-info"></i>
                            </div>
                            <div class="col-md-11">
                                <h6 class="mb-2">Commission Payment Process</h6>
                                <p class="small text-muted mb-0">
                                    <strong>1. Pending:</strong> Commission recorded, awaiting landlord review. →
                                    <strong>2. Approved:</strong> Landlord approved, payment being processed. →
                                    <strong>3. Paid:</strong> Commission paid to your account.
                                </p>
                                <p class="small text-muted mb-0 mt-2">
                                    For payment inquiries, contact your landlord or the admin team.
                                </p>
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
</body>
</html>
