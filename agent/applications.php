<?php
/**
 * Agent - Applications
 * View applications for assigned properties
 * Agent can recommend/not recommend (final approval by landlord)
 * Rent Hob
 */
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/agent-functions.php';

requireRole('agent');

$agentId = currentUserId();

// Handle agent recommendation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $applicationId = (int)$_POST['application_id'];
    $action = $_POST['action'];
    
    // Verify this application belongs to agent's property
    $stmt = $pdo->prepare('
        SELECT app.id
        FROM applications app
        INNER JOIN properties p ON p.id = app.property_id
        INNER JOIN agent_properties ap ON ap.property_id = p.id
        WHERE app.id = ? AND ap.agent_id = ? AND ap.status = "active"
    ');
    $stmt->execute([$applicationId, $agentId]);
    
    if ($stmt->fetch()) {
        if ($action === 'recommend') {
            $stmt = $pdo->prepare('UPDATE applications SET agent_recommendation = "recommended", agent_notes = ? WHERE id = ?');
            $stmt->execute([$_POST['notes'] ?? 'Recommended by agent', $applicationId]);
            setFlash('Application recommended successfully', 'success');
        } elseif ($action === 'not_recommend') {
            $stmt = $pdo->prepare('UPDATE applications SET agent_recommendation = "not_recommended", agent_notes = ? WHERE id = ?');
            $stmt->execute([$_POST['notes'] ?? 'Not recommended by agent', $applicationId]);
            setFlash('Recommendation updated', 'success');
        } elseif ($action === 'remove_recommendation') {
            $stmt = $pdo->prepare('UPDATE applications SET agent_recommendation = NULL, agent_notes = NULL WHERE id = ?');
            $stmt->execute([$applicationId]);
            setFlash('Recommendation removed', 'success');
        }
    } else {
        setFlash('Invalid application', 'error');
    }
    
    header('Location: applications.php');
    exit;
}

// Get filter
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$recommendationFilter = isset($_GET['recommendation']) ? $_GET['recommendation'] : '';

// Build query - only applications for agent's assigned properties
$sql = "SELECT app.*, 
        u.first_name, u.last_name, u.email, u.phone, u.avatar,
        p.id as property_id, p.title as property_title, p.price as property_price,
        (SELECT image_path FROM property_images WHERE property_id = p.id ORDER BY id ASC LIMIT 1) as property_image
        FROM applications app
        INNER JOIN users u ON u.id = app.user_id
        INNER JOIN properties p ON p.id = app.property_id
        INNER JOIN agent_properties ap ON ap.property_id = p.id
        WHERE ap.agent_id = ? AND ap.status = 'active'";

$params = [$agentId];

if ($statusFilter) {
    $sql .= " AND app.status = ?";
    $params[] = $statusFilter;
}

if ($recommendationFilter) {
    if ($recommendationFilter === 'none') {
        $sql .= " AND app.agent_recommendation IS NULL";
    } else {
        $sql .= " AND app.agent_recommendation = ?";
        $params[] = $recommendationFilter;
    }
}

$sql .= " ORDER BY app.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$applications = $stmt->fetchAll();

// Count by status
$stmt = $pdo->prepare('
    SELECT app.status, COUNT(*) as count
    FROM applications app
    INNER JOIN agent_properties ap ON ap.property_id = app.property_id
    WHERE ap.agent_id = ? AND ap.status = "active"
    GROUP BY app.status
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
    <title>Applications — Rent Hob Agent</title>
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
                        <h4 class="mb-1 fw-semibold">Applications</h4>
                        <p class="text-muted mb-0">Review and recommend applications for your assigned properties</p>
                    </div>
                    <div class="col-auto align-self-center">
                        <span class="badge bg-info px-3 py-2"><?= array_sum($statusCounts) ?> Total Applications</span>
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
                                <div class="btn-group w-100" role="group">
                                    <a href="applications.php" class="btn btn-<?= !$statusFilter ? 'primary' : 'outline-primary' ?> btn-sm">
                                        All (<?= array_sum($statusCounts) ?>)
                                    </a>
                                    <a href="applications.php?status=pending" class="btn btn-<?= $statusFilter === 'pending' ? 'warning' : 'outline-warning' ?> btn-sm">
                                        Pending (<?= $statusCounts['pending'] ?? 0 ?>)
                                    </a>
                                    <a href="applications.php?status=under_review" class="btn btn-<?= $statusFilter === 'under_review' ? 'info' : 'outline-info' ?> btn-sm">
                                        Under Review (<?= $statusCounts['under_review'] ?? 0 ?>)
                                    </a>
                                    <a href="applications.php?status=approved" class="btn btn-<?= $statusFilter === 'approved' ? 'success' : 'outline-success' ?> btn-sm">
                                        Approved (<?= $statusCounts['approved'] ?? 0 ?>)
                                    </a>
                                    <a href="applications.php?status=rejected" class="btn btn-<?= $statusFilter === 'rejected' ? 'danger' : 'outline-danger' ?> btn-sm">
                                        Rejected (<?= $statusCounts['rejected'] ?? 0 ?>)
                                    </a>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="btn-group w-100" role="group">
                                    <a href="applications.php" class="btn btn-<?= !$recommendationFilter ? 'secondary' : 'outline-secondary' ?> btn-sm">
                                        All Recommendations
                                    </a>
                                    <a href="applications.php?recommendation=recommended" class="btn btn-<?= $recommendationFilter === 'recommended' ? 'success' : 'outline-success' ?> btn-sm">
                                        Recommended
                                    </a>
                                    <a href="applications.php?recommendation=not_recommended" class="btn btn-<?= $recommendationFilter === 'not_recommended' ? 'danger' : 'outline-danger' ?> btn-sm">
                                        Not Recommended
                                    </a>
                                    <a href="applications.php?recommendation=none" class="btn btn-<?= $recommendationFilter === 'none' ? 'secondary' : 'outline-secondary' ?> btn-sm">
                                        No Recommendation
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <?php if (empty($applications)): ?>
                            <div class="text-center py-5">
                                <i data-feather="file-text" style="width:64px;height:64px;" class="text-muted mb-3"></i>
                                <h5 class="text-muted">No Applications</h5>
                                <p class="text-muted">Applications for your assigned properties will appear here.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Applicant</th>
                                            <th>Property</th>
                                            <th>Applied On</th>
                                            <th>Status</th>
                                            <th>Your Recommendation</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($applications as $app): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <img src="<?= userAvatar($app['avatar']) ?>" class="rounded-circle" width="40" height="40" alt="" style="object-fit:cover;">
                                                        <div>
                                                            <p class="mb-0 fw-semibold small"><?= e($app['first_name'] . ' ' . $app['last_name']) ?></p>
                                                            <small class="text-muted"><?= e($app['phone']) ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <?php if ($app['property_image']): ?>
                                                            <img src="<?= BASE_URL ?>/<?= e($app['property_image']) ?>" class="rounded" width="50" height="40" alt="" style="object-fit:cover;">
                                                        <?php endif; ?>
                                                        <div>
                                                            <a href="property-view.php?id=<?= $app['property_id'] ?>" class="text-decoration-none small">
                                                                <?= e(truncate($app['property_title'], 30)) ?>
                                                            </a>
                                                            <br><small class="text-muted"><?= formatCurrency($app['property_price']) ?>/month</small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><small><?= formatDate($app['created_at']) ?></small></td>
                                                <td><?= applicationStatusBadge($app['status']) ?></td>
                                                <td>
                                                    <?php if (isset($app['agent_recommendation']) && $app['agent_recommendation'] === 'recommended'): ?>
                                                        <span class="badge bg-success">✓ Recommended</span>
                                                    <?php elseif (isset($app['agent_recommendation']) && $app['agent_recommendation'] === 'not_recommended'): ?>
                                                        <span class="badge bg-danger">✗ Not Recommended</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">No recommendation</span>
                                                    <?php endif; ?>
                                                    <?php if (isset($app['agent_notes']) && $app['agent_notes']): ?>
                                                        <br><small class="text-muted" title="<?= e($app['agent_notes']) ?>">
                                                            <?= e(truncate($app['agent_notes'], 30)) ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="dropdown">
                                                        <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                            <i data-feather="more-vertical" style="width:14px;height:14px;"></i>
                                                        </button>
                                                        <ul class="dropdown-menu">
                                                            <li><h6 class="dropdown-header">Agent Actions</h6></li>
                                                            <li>
                                                                <button class="dropdown-item" onclick="recommendApplication(<?= $app['id'] ?>, 'recommend')">
                                                                    <i data-feather="thumbs-up" class="me-2" style="width:14px;height:14px;"></i> Recommend
                                                                </button>
                                                            </li>
                                                            <li>
                                                                <button class="dropdown-item text-danger" onclick="recommendApplication(<?= $app['id'] ?>, 'not_recommend')">
                                                                    <i data-feather="thumbs-down" class="me-2" style="width:14px;height:14px;"></i> Do Not Recommend
                                                                </button>
                                                            </li>
                                                            <?php if (isset($app['agent_recommendation']) && $app['agent_recommendation']): ?>
                                                                <li><hr class="dropdown-divider"></li>
                                                                <li>
                                                                    <form method="POST" style="display:inline;">
                                                                        <input type="hidden" name="application_id" value="<?= $app['id'] ?>">
                                                                        <input type="hidden" name="action" value="remove_recommendation">
                                                                        <button type="submit" class="dropdown-item text-muted">
                                                                            <i data-feather="x" class="me-2" style="width:14px;height:14px;"></i> Remove Recommendation
                                                                        </button>
                                                                    </form>
                                                                </li>
                                                            <?php endif; ?>
                                                            <li><hr class="dropdown-divider"></li>
                                                            <li><h6 class="dropdown-header">Contact</h6></li>
                                                            <li>
                                                                <a class="dropdown-item" href="tel:<?= e($app['phone']) ?>">
                                                                    <i data-feather="phone" class="me-2" style="width:14px;height:14px;"></i> Call
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a class="dropdown-item" href="mailto:<?= e($app['email']) ?>">
                                                                    <i data-feather="mail" class="me-2" style="width:14px;height:14px;"></i> Email
                                                                </a>
                                                            </li>
                                                        </ul>
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

    <script>
    function recommendApplication(appId, action) {
        const notes = prompt(action === 'recommend' ? 'Add a note (optional):' : 'Why not recommended?');
        if (notes !== null) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="application_id" value="${appId}">
                <input type="hidden" name="action" value="${action}">
                <input type="hidden" name="notes" value="${notes}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }
    </script>
</body>
</html>
