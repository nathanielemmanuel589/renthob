<?php
/**
 * Admin - Agent Detail
 * View agent details and manage assignments
 * Rent Hob
 */
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('admin');

$agentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$agentId) {
    header('Location: agents.php');
    exit;
}

// Handle remove assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_assignment') {
    $assignmentId = (int)$_POST['assignment_id'];
    $stmt = $pdo->prepare('UPDATE agent_properties SET status = "inactive" WHERE id = ?');
    $stmt->execute([$assignmentId]);
    setFlash('Assignment removed successfully', 'success');
    header('Location: agent-detail.php?id=' . $agentId);
    exit;
}

// Get agent details
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? AND role = "agent"');
$stmt->execute([$agentId]);
$agent = $stmt->fetch();

if (!$agent) {
    setFlash('Agent not found', 'error');
    header('Location: agents.php');
    exit;
}

// Get assigned properties
$stmt = $pdo->prepare('
    SELECT ap.*, p.title, p.address, p.price, p.status as property_status,
           CONCAT(u.first_name, " ", u.last_name) as landlord_name,
           (SELECT image_path FROM property_images WHERE property_id = p.id ORDER BY id ASC LIMIT 1) as cover_image
    FROM agent_properties ap
    INNER JOIN properties p ON p.id = ap.property_id
    LEFT JOIN users u ON u.id = p.landlord_id
    WHERE ap.agent_id = ? AND ap.status = "active"
    ORDER BY ap.assigned_date DESC
');
$stmt->execute([$agentId]);
$assignments = $stmt->fetchAll();

// Get statistics
$stmt = $pdo->prepare('SELECT COUNT(*) FROM agent_properties WHERE agent_id = ? AND status = "active"');
$stmt->execute([$agentId]);
$totalProperties = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT SUM(commission_amount) FROM agent_commissions WHERE agent_id = ? AND status IN ("approved", "paid")');
$stmt->execute([$agentId]);
$totalEarned = (float)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT SUM(commission_amount) FROM agent_commissions WHERE agent_id = ? AND status = "pending"');
$stmt->execute([$agentId]);
$pendingCommission = (float)$stmt->fetchColumn();

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent Detail — Rent Hob Admin</title>
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/img/favicon.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <script defer src="<?= BASE_URL ?>/dashboard/assets/js/third-party.js"></script>
    <script defer src="<?= BASE_URL ?>/dashboard/assets/js/app.js"></script>
    <script defer src="<?= BASE_URL ?>/dashboard/assets/js/core.js"></script>
    <link href="<?= BASE_URL ?>/dashboard/assets/css/app.css" rel="stylesheet">
</head>
<body class="adminuiux-sidebar-standard theme-cyan" data-theme="theme-cyan">
    <div class="pageloader"><div class="container h-100"><div class="row justify-content-center align-items-center text-center h-100"><div class="col-auto"><img src="<?= BASE_URL ?>/dashboard/assets/img/logo-512.png" class="height-100 mb-3"><p class="h3">Rent Hob</p><div class="loader10 mx-auto"></div></div></div></div></div>
    
    <header class="adminuiux-header">
        <nav class="navbar navbar-expand-lg">
            <div class="container-fluid">
                <button class="btn btn-link sidebar-toggler" onclick="initSidebar()"><i data-feather="menu"></i></button>
                <a class="navbar-brand" href="dashboard.php">
                    <img src="<?= BASE_URL ?>/assets/img/logo-2.svg" style="max-height:32px;">
                    <div><span class="h4">Rent Hob</span><p>Admin Portal</p></div>
                </a>
                <div class="ms-auto">
                    <div class="dropdown">
                        <button class="btn btn-link dropdown-toggle" data-bs-toggle="dropdown">
                            <img src="<?= userAvatar($_SESSION['user_avatar'] ?? null) ?>" class="rounded-circle" width="34" height="34">
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li><a class="dropdown-item" href="settings.php">Settings</a></li>
                            <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/logout.php">Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>
    </header>

    <div class="adminuiux-wrap">
        <?php include '../includes/admin-sidebar.php'; ?>
        
        <main class="adminuiux-content has-sidebar">
            <div class="container-fluid mt-2">
                
                <div class="row mb-3">
                    <div class="col">
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="agents.php">Agents</a></li>
                                <li class="breadcrumb-item active"><?= e($agent['first_name'] . ' ' . $agent['last_name']) ?></li>
                            </ol>
                        </nav>
                        <h4 class="mb-0 fw-semibold">Agent Details</h4>
                    </div>
                </div>

                <?php if ($flash): ?>
                    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
                        <?= e($flash['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row g-4">
                    <!-- Agent Profile Card -->
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body text-center py-4">
                                <img src="<?= userAvatar($agent['avatar']) ?>" class="rounded-circle mb-3" width="100" height="100" style="object-fit:cover;">
                                <h5><?= e($agent['first_name'] . ' ' . $agent['last_name']) ?></h5>
                                <p class="text-muted mb-2"><span class="badge bg-orange">Agent</span></p>
                                <p class="small mb-1"><i data-feather="mail" style="width:14px;height:14px;"></i> <?= e($agent['email']) ?></p>
                                <p class="small mb-3"><i data-feather="phone" style="width:14px;height:14px;"></i> <?= e($agent['phone'] ?? 'N/A') ?></p>
                                <a href="user-edit.php?id=<?= $agent['id'] ?>" class="btn btn-sm btn-primary">Edit Profile</a>
                            </div>
                        </div>

                        <!-- Stats Card -->
                        <div class="card border-0 shadow-sm mt-3">
                            <div class="card-body">
                                <h6 class="mb-3">Statistics</h6>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted small">Assigned Properties</span>
                                    <span class="fw-semibold"><?= $totalProperties ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted small">Total Earned</span>
                                    <span class="fw-semibold text-success"><?= formatCurrency($totalEarned) ?></span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted small">Pending Commission</span>
                                    <span class="fw-semibold text-warning"><?= formatCurrency($pendingCommission) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Assigned Properties -->
                    <div class="col-md-8">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">Assigned Properties</h6>
                                <a href="agents.php" class="btn btn-sm btn-primary" onclick="event.preventDefault(); window.history.back();">
                                    <i data-feather="arrow-left" style="width:14px;height:14px;"></i> Back to List
                                </a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($assignments)): ?>
                                    <div class="text-center py-5">
                                        <i data-feather="home" style="width:48px;height:48px;" class="text-muted mb-3"></i>
                                        <p class="text-muted">No properties assigned yet</p>
                                        <a href="agents.php" class="btn btn-sm btn-primary">Assign Properties</a>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Property</th>
                                                    <th>Landlord</th>
                                                    <th>Commission Rate</th>
                                                    <th>Assigned Date</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($assignments as $assignment): ?>
                                                    <tr>
                                                        <td>
                                                            <div class="d-flex align-items-center gap-2">
                                                                <?php if ($assignment['cover_image']): ?>
                                                                    <img src="<?= BASE_URL ?>/<?= e($assignment['cover_image']) ?>" class="rounded" width="50" height="40" style="object-fit:cover;">
                                                                <?php endif; ?>
                                                                <div>
                                                                    <a href="property-view.php?id=<?= $assignment['property_id'] ?>" class="text-decoration-none small">
                                                                        <?= e($assignment['title']) ?>
                                                                    </a>
                                                                    <br><small class="text-muted"><?= e(truncate($assignment['address'], 30)) ?></small>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td><small><?= e($assignment['landlord_name']) ?></small></td>
                                                        <td><span class="badge bg-info"><?= number_format($assignment['commission_rate'], 1) ?>%</span></td>
                                                        <td><small><?= formatDate($assignment['assigned_date']) ?></small></td>
                                                        <td>
                                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this assignment?')">
                                                                <input type="hidden" name="action" value="remove_assignment">
                                                                <input type="hidden" name="assignment_id" value="<?= $assignment['id'] ?>">
                                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                                    <i data-feather="x-circle" style="width:14px;height:14px;"></i> Remove
                                                                </button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>
</body>
</html>
