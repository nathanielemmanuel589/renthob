<?php
/**
 * Agent Dashboard - Home
 * Rent Hob
 */
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/agent-functions.php';

requireRole('agent');

$agentId = currentUserId();
$agentName = $_SESSION['user_name'];
$agentStatus = $_SESSION['user_status'] ?? 'active';

// Check if agent is pending approval
$isPending = ($agentStatus === 'pending');

// If pending, show a restricted "Under Review" dashboard
if ($isPending) {
    include 'dashboard-pending.php';
    exit;
}

// Get statistics
$stats = getAgentStats($pdo, $agentId);

// Get recent applications
$recentApplications = $pdo->prepare('
    SELECT a.*, p.title as property_title, p.slug as property_slug,
           u.first_name, u.last_name, u.email, u.avatar
    FROM applications a
    INNER JOIN agent_properties ap ON ap.property_id = a.property_id
    INNER JOIN properties p ON p.id = a.property_id
    INNER JOIN users u ON u.id = a.user_id
    WHERE ap.agent_id = ? AND ap.status = "active"
    ORDER BY a.created_at DESC
    LIMIT 5
');
$recentApplications->execute([$agentId]);
$recentApplications = $recentApplications->fetchAll();

// Get upcoming viewings
$upcomingViewings = $pdo->prepare('
    SELECT b.*, p.title as property_title, p.slug as property_slug,
           u.first_name, u.last_name, u.avatar
    FROM bookings b
    INNER JOIN agent_properties ap ON ap.property_id = b.property_id
    INNER JOIN properties p ON p.id = b.property_id
    INNER JOIN users u ON u.id = b.user_id
    WHERE ap.agent_id = ? AND ap.status = "active"
    AND b.visit_date >= CURDATE()
    AND b.status != "cancelled"
    ORDER BY b.visit_date ASC, b.visit_time ASC
    LIMIT 5
');
$upcomingViewings->execute([$agentId]);
$upcomingViewings = $upcomingViewings->fetchAll();

// Get recent leads
$recentLeads = $pdo->prepare('
    SELECT tl.*, p.title as property_title
    FROM tenant_leads tl
    LEFT JOIN properties p ON p.id = tl.interested_property_id
    WHERE tl.agent_id = ?
    ORDER BY tl.created_at DESC
    LIMIT 5
');
$recentLeads->execute([$agentId]);
$recentLeads = $recentLeads->fetchAll();

// Get pending tasks (today and overdue)
$pendingTasks = $pdo->prepare('
    SELECT t.*, p.title as property_title
    FROM agent_tasks t
    LEFT JOIN properties p ON p.id = t.property_id
    WHERE t.agent_id = ? AND t.status IN ("pending", "in_progress")
    AND (t.due_date <= CURDATE() OR t.due_date IS NULL)
    ORDER BY 
        CASE t.priority
            WHEN "urgent" THEN 1
            WHEN "high" THEN 2
            WHEN "medium" THEN 3
            WHEN "low" THEN 4
        END,
        t.due_date ASC
    LIMIT 5
');
$pendingTasks->execute([$agentId]);
$pendingTasks = $pendingTasks->fetchAll();

// Get top performing properties
$topProperties = $pdo->prepare('
    SELECT p.id, p.title, p.slug,
           COUNT(DISTINCT a.id) as application_count,
           COUNT(DISTINCT l.id) as lease_count,
           COALESCE(SUM(ac.commission_amount), 0) as total_commission
    FROM properties p
    INNER JOIN agent_properties ap ON ap.property_id = p.id
    LEFT JOIN applications a ON a.property_id = p.id
    LEFT JOIN leases l ON l.property_id = p.id AND l.status = "active"
    LEFT JOIN agent_commissions ac ON ac.property_id = p.id AND ac.agent_id = ?
    WHERE ap.agent_id = ? AND ap.status = "active"
    GROUP BY p.id
    ORDER BY total_commission DESC, application_count DESC
    LIMIT 5
');
$topProperties->execute([$agentId, $agentId]);
$topProperties = $topProperties->fetchAll();

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Dashboard — Rent Hob Agent</title>
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

<body class="main-bg main-bg-blur main-bg-opac roundedui adminuiux-sidebar-standard adminuiux-sidebar-fill-white adminuiux-mobile-footer-fill-theme adminuiux-sidebar-boxed adminuiux-header-transparent adminuiux-header-standard theme-orange bg-gradient-4 scrollup"
      data-theme="theme-orange"
      data-sidebarfill="adminuiux-sidebar-fill-white"
      data-sidebarlayout="adminuiux-sidebar-boxed"
      data-headerlayout="adminuiux-header-standard"
      data-headerfill="adminuiux-header-transparent"
      data-bggradient="bg-gradient-4">

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

    <!-- Header -->
    <header class="adminuiux-header inner-page">
        <nav class="navbar navbar-expand-lg fixed-top">
            <div class="container-fluid">
                <button class="btn btn-link btn-square sidebar-toggler ms-1" type="button" onclick="initSidebar()">
                    <i class="sidebar-svg" data-feather="menu"></i>
                </button>
                <a class="navbar-brand" href="dashboard.php">
                    <img data-bs-img="light" src="<?= BASE_URL ?>/assets/img/logo-2.svg" alt="Rent Hob" style="max-height:32px;">
                    <img data-bs-img="dark" src="<?= BASE_URL ?>/assets/img/logo-2.svg" alt="Rent Hob" style="max-height:32px;">
                    <div class="lh-20">
                        <span class="h4">Rent Hob</span>
                        <p class="company-tagline">Agent Portal</p>
                    </div>
                </a>
                <div class="ms-auto d-flex align-items-center gap-1">
                    <div class="dropdown">
                        <button class="btn btn-link no-caret dropdown-toggle d-flex align-items-center gap-2 px-2"
                                type="button" data-bs-toggle="dropdown">
                            <img src="<?= userAvatar($_SESSION['user_avatar'] ?? null) ?>"
                                 alt="Agent" class="rounded-circle" width="34" height="34" style="object-fit:cover;">
                            <span class="d-none d-md-inline small fw-semibold"><?= e($agentName) ?></span>
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

    <div class="adminuiux-wrap">

        <!-- Sidebar -->
        <?php include '../includes/agent-sidebar.php'; ?>

        <!-- Main Content -->
        <main class="adminuiux-content has-sidebar" onclick="contentClick()">
            <div class="container mt-2" id="main-content">

                <!-- Page Header -->
                <div class="row mb-3">
                    <div class="col">
                        <h4 class="mb-1 fw-semibold">Welcome back, <?= e(explode(' ', $agentName)[0]) ?>! 👋</h4>
                        <p class="text-muted mb-0">Here's what's happening with your properties today.</p>
                    </div>
                    <div class="col-auto align-self-center">
                        <span class="badge bg-primary px-3 py-2"><?= date('l, d M Y') ?></span>
                    </div>
                </div>

                <!-- Flash Messages -->
                <?php if ($flash): ?>
                    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show mb-3">
                        <?= e($flash['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Stats Cards Row 1 -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <p class="text-muted small mb-1">Assigned Properties</p>
                                        <h3 class="mb-0 fw-bold"><?= number_format($stats['assigned_properties']) ?></h3>
                                        <small class="text-muted"><?= $stats['active_listings'] ?> active listings</small>
                                    </div>
                                    <div class="bg-primary bg-opacity-10 p-2 rounded">
                                        <i data-feather="home" class="text-primary" style="width:24px;height:24px;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <p class="text-muted small mb-1">Pending Applications</p>
                                        <h3 class="mb-0 fw-bold"><?= number_format($stats['pending_applications']) ?></h3>
                                        <small class="text-muted">Awaiting review</small>
                                    </div>
                                    <div class="bg-warning bg-opacity-10 p-2 rounded">
                                        <i data-feather="file-text" class="text-warning" style="width:24px;height:24px;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <p class="text-muted small mb-1">Active Tenants</p>
                                        <h3 class="mb-0 fw-bold"><?= number_format($stats['active_tenants']) ?></h3>
                                        <small class="text-muted"><?= $stats['total_leads'] ?> leads in pipeline</small>
                                    </div>
                                    <div class="bg-success bg-opacity-10 p-2 rounded">
                                        <i data-feather="users" class="text-success" style="width:24px;height:24px;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <p class="text-muted small mb-1">This Month's Earnings</p>
                                        <h3 class="mb-0 fw-bold"><?= formatCurrency($stats['month_earnings']) ?></h3>
                                        <small class="text-success">Commission earned</small>
                                    </div>
                                    <div class="bg-info bg-opacity-10 p-2 rounded">
                                        <i data-feather="dollar-sign" class="text-info" style="width:24px;height:24px;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards Row 2 -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <p class="text-muted small mb-1">Tenant Leads</p>
                                        <h3 class="mb-0 fw-bold"><?= number_format($stats['total_leads']) ?></h3>
                                        <small class="text-muted"><?= $stats['new_leads'] ?> new this week</small>
                                    </div>
                                    <div class="bg-secondary bg-opacity-10 p-2 rounded">
                                        <i data-feather="user-plus" class="text-secondary" style="width:24px;height:24px;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <p class="text-muted small mb-1">Upcoming Viewings</p>
                                        <h3 class="mb-0 fw-bold"><?= number_format($stats['upcoming_viewings']) ?></h3>
                                        <small class="text-muted">Next 7 days</small>
                                    </div>
                                    <div class="bg-info bg-opacity-10 p-2 rounded">
                                        <i data-feather="calendar" class="text-info" style="width:24px;height:24px;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <p class="text-muted small mb-1">Pending Tasks</p>
                                        <h3 class="mb-0 fw-bold"><?= number_format($stats['pending_tasks']) ?></h3>
                                        <small class="text-muted">Requires attention</small>
                                    </div>
                                    <div class="bg-danger bg-opacity-10 p-2 rounded">
                                        <i data-feather="check-square" class="text-danger" style="width:24px;height:24px;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <p class="text-muted small mb-1">Total Commission</p>
                                        <h3 class="mb-0 fw-bold"><?= formatCurrency($stats['total_commission']) ?></h3>
                                        <small class="text-muted"><?= formatCurrency($stats['pending_commission']) ?> pending</small>
                                    </div>
                                    <div class="bg-success bg-opacity-10 p-2 rounded">
                                        <i data-feather="trending-up" class="text-success" style="width:24px;height:24px;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions Alert -->
                <?php if ($stats['pending_applications'] > 0 || $stats['upcoming_viewings'] > 0 || $stats['pending_tasks'] > 0): ?>
                    <div class="alert alert-info border-0 shadow-sm mb-4" role="alert">
                        <div class="d-flex align-items-center">
                            <i data-feather="bell" class="me-2" style="width:20px;height:20px;"></i>
                            <div class="flex-grow-1">
                                <strong>Action Required:</strong>
                                <?php if ($stats['pending_applications'] > 0): ?>
                                    You have <a href="applications.php" class="alert-link"><?= $stats['pending_applications'] ?> pending application<?= $stats['pending_applications'] > 1 ? 's' : '' ?></a> to review.
                                <?php endif; ?>
                                <?php if ($stats['upcoming_viewings'] > 0): ?>
                                    <?= $stats['pending_applications'] > 0 ? ' Also, ' : '' ?>
                                    <a href="bookings.php" class="alert-link"><?= $stats['upcoming_viewings'] ?> viewing<?= $stats['upcoming_viewings'] > 1 ? 's' : '' ?></a> scheduled in the next 7 days.
                                <?php endif; ?>
                                <?php if ($stats['pending_tasks'] > 0): ?>
                                    <?= ($stats['pending_applications'] > 0 || $stats['upcoming_viewings'] > 0) ? ' Also, ' : '' ?>
                                    <a href="tasks.php" class="alert-link"><?= $stats['pending_tasks'] ?> task<?= $stats['pending_tasks'] > 1 ? 's' : '' ?></a> need<?= $stats['pending_tasks'] > 1 ? '' : 's' ?> your attention.
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Content Sections -->
                <div class="row g-3 mb-4">
                    
                    <!-- Recent Applications -->
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="mb-0">Recent Applications</h6>
                                    <a href="applications.php" class="btn btn-sm btn-outline-primary">View All</a>
                                </div>

                                <?php if (empty($recentApplications)): ?>
                                    <p class="text-muted text-center py-3">No applications yet</p>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($recentApplications as $app): ?>
                                            <div class="list-group-item border-0 px-0">
                                                <div class="d-flex gap-2">
                                                    <img src="<?= userAvatar($app['avatar']) ?>" class="rounded-circle" width="40" height="40" alt="" style="object-fit:cover;">
                                                    <div class="flex-grow-1">
                                                        <p class="mb-0 fw-semibold small"><?= e($app['first_name'] . ' ' . $app['last_name']) ?></p>
                                                        <p class="mb-0 text-muted small"><?= e($app['property_title']) ?></p>
                                                        <small class="text-muted"><?= formatDate($app['created_at']) ?></small>
                                                    </div>
                                                    <div>
                                                        <?= statusBadge($app['status']) ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Upcoming Viewings -->
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="mb-0">Upcoming Viewings</h6>
                                    <a href="bookings.php" class="btn btn-sm btn-outline-primary">View All</a>
                                </div>

                                <?php if (empty($upcomingViewings)): ?>
                                    <p class="text-muted text-center py-3">No upcoming viewings</p>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($upcomingViewings as $viewing): ?>
                                            <div class="list-group-item border-0 px-0">
                                                <div class="d-flex gap-2">
                                                    <img src="<?= userAvatar($viewing['avatar']) ?>" class="rounded-circle" width="40" height="40" alt="" style="object-fit:cover;">
                                                    <div class="flex-grow-1">
                                                        <p class="mb-0 fw-semibold small"><?= e($viewing['first_name'] . ' ' . $viewing['last_name']) ?></p>
                                                        <p class="mb-0 text-muted small"><?= e($viewing['property_title']) ?></p>
                                                        <small class="text-muted"><?= formatDate($viewing['visit_date']) ?> at <?= date('g:i A', strtotime($viewing['visit_time'])) ?></small>
                                                    </div>
                                                    <div>
                                                        <?= statusBadge($viewing['status']) ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="row g-3 mb-4">
                    
                    <!-- Recent Leads -->
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="mb-0">Recent Leads</h6>
                                    <a href="leads.php" class="btn btn-sm btn-outline-success">View All</a>
                                </div>

                                <?php if (empty($recentLeads)): ?>
                                    <p class="text-muted text-center py-3">No leads yet. <a href="leads.php">Add your first lead</a></p>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($recentLeads as $lead): ?>
                                            <div class="list-group-item border-0 px-0">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <p class="mb-0 fw-semibold small"><?= e($lead['name']) ?></p>
                                                        <p class="mb-0 text-muted small"><?= e($lead['phone']) ?></p>
                                                        <?php if ($lead['property_title']): ?>
                                                            <small class="text-muted">Interested in: <?= e($lead['property_title']) ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <?= leadStatusBadge($lead['status']) ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Pending Tasks -->
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="mb-0">Today's Tasks</h6>
                                    <a href="tasks.php" class="btn btn-sm btn-outline-primary">View All</a>
                                </div>

                                <?php if (empty($pendingTasks)): ?>
                                    <p class="text-muted text-center py-3">No pending tasks</p>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($pendingTasks as $task): ?>
                                            <div class="list-group-item border-0 px-0">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div class="flex-grow-1">
                                                        <p class="mb-0 fw-semibold small"><?= e($task['title']) ?></p>
                                                        <?php if ($task['property_title']): ?>
                                                            <p class="mb-0 text-muted small"><?= e($task['property_title']) ?></p>
                                                        <?php endif; ?>
                                                        <small class="text-muted">
                                                            <?php if ($task['due_date']): ?>
                                                                Due: <?= formatDate($task['due_date']) ?>
                                                            <?php endif; ?>
                                                        </small>
                                                    </div>
                                                    <div>
                                                        <?= taskPriorityBadge($task['priority']) ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Top Performing Properties -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <h6 class="mb-3">Top Performing Properties</h6>
                        <?php if (empty($topProperties)): ?>
                            <p class="text-muted text-center py-3">No properties assigned yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Property</th>
                                            <th>Applications</th>
                                            <th>Active Leases</th>
                                            <th>Commission Earned</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($topProperties as $i => $prop): ?>
                                            <tr>
                                                <td><?= $i + 1 ?></td>
                                                <td>
                                                    <a href="property-view.php?id=<?= $prop['id'] ?>" class="text-decoration-none fw-semibold">
                                                        <?= e($prop['title']) ?>
                                                    </a>
                                                </td>
                                                <td><span class="badge bg-info"><?= $prop['application_count'] ?></span></td>
                                                <td><span class="badge bg-success"><?= $prop['lease_count'] ?></span></td>
                                                <td class="fw-semibold"><?= formatCurrency($prop['total_commission']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Footer -->
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
