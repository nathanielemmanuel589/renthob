<?php
/**
 * Admin — Agent Applications Review
 * Rent Hob
 */
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('admin');

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    $agentId = (int)($_POST['agent_id'] ?? 0);
    
    if ($agentId > 0) {
        // Verify it's an agent with pending status
        $check = $pdo->prepare('SELECT id, email, first_name, last_name FROM users WHERE id = ? AND role = ? AND status = ?');
        $check->execute([$agentId, 'agent', 'pending']);
        $agent = $check->fetch();
        
        if ($agent) {
            if ($action === 'approve') {
                $stmt = $pdo->prepare('UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?');
                $stmt->execute(['active', $agentId]);
                
                logActivity($pdo, currentUserId(), "Approved agent application #$agentId", 'users');
                
                // TODO: Send approval email to agent
                
                setFlash('success', "Agent {$agent['first_name']} {$agent['last_name']} has been approved.");
            } 
            elseif ($action === 'reject') {
                $reason = trim($_POST['rejection_reason'] ?? '');
                
                // Either delete or set to rejected status
                // For now, let's change status to 'inactive' with a note
                $stmt = $pdo->prepare('UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?');
                $stmt->execute(['inactive', $agentId]);
                
                logActivity($pdo, currentUserId(), "Rejected agent application #$agentId - Reason: $reason", 'users');
                
                // TODO: Send rejection email to agent with reason
                
                setFlash('success', "Agent application has been rejected.");
            }
        } else {
            setFlash('error', 'Agent application not found or already processed.');
        }
    }
    
    redirect(BASE_URL . '/admin/agent-applications.php');
}

// Fetch pending agent applications
$stmt = $pdo->prepare('
    SELECT id, first_name, last_name, email, phone, notes, created_at
    FROM users
    WHERE role = ? AND status = ?
    ORDER BY created_at DESC
');
$stmt->execute(['agent', 'pending']);
$pendingAgents = $stmt->fetchAll();

// Fetch recently approved agents
$stmt = $pdo->prepare('
    SELECT id, first_name, last_name, email, phone, updated_at
    FROM users
    WHERE role = ? AND status = ?
    ORDER BY updated_at DESC
    LIMIT 10
');
$stmt->execute(['agent', 'active']);
$recentlyApproved = $stmt->fetchAll();

// Fetch rejected agents
$stmt = $pdo->prepare('
    SELECT id, first_name, last_name, email, phone, updated_at
    FROM users
    WHERE role = ? AND status = ?
    ORDER BY updated_at DESC
    LIMIT 10
');
$stmt->execute(['agent', 'inactive']);
$rejectedAgents = $stmt->fetchAll();

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Agent Applications — Rent Hob Admin</title>
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

<body class="main-bg main-bg-blur main-bg-opac roundedui adminuiux-sidebar-standard adminuiux-sidebar-fill-white adminuiux-mobile-footer-fill-theme adminuiux-sidebar-boxed adminuiux-header-transparent adminuiux-header-standard theme-cyan bg-gradient-5 scrollup">

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

    <header class="adminuiux-header inner-page">
        <nav class="navbar navbar-expand-lg fixed-top">
            <div class="container-fluid">
                <button class="btn btn-link btn-square sidebar-toggler ms-1" onclick="initSidebar()">
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
                        <button class="btn btn-link no-caret dropdown-toggle d-flex align-items-center gap-2 px-2" data-bs-toggle="dropdown">
                            <img src="<?= userAvatar($_SESSION['user_avatar'] ?? null) ?>" alt="Admin" class="rounded-circle" width="34" height="34" style="object-fit:cover;">
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
        <?php include '../includes/admin-sidebar.php'; ?>

        <main class="adminuiux-content has-sidebar" onclick="contentClick()">
            <div class="container mt-2">

                <div class="row mb-3">
                    <div class="col">
                        <h4 class="mb-1 fw-semibold">Agent Applications</h4>
                        <p class="text-muted mb-0">Review and approve agent applications</p>
                    </div>
                </div>

                <?php if ($flash): ?>
                    <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show">
                        <?= e($flash['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Tabs -->
                <ul class="nav nav-tabs mb-4">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="tab" href="#pending">
                            Pending <span class="badge bg-warning text-dark ms-1"><?= count($pendingAgents) ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#approved">
                            Recently Approved
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#rejected">
                            Rejected
                        </a>
                    </li>
                </ul>

                <div class="tab-content">
                    <!-- Pending Applications -->
                    <div class="tab-pane fade show active" id="pending">
                        <?php if (empty($pendingAgents)): ?>
                            <div class="card border-0 shadow-sm">
                                <div class="card-body text-center py-5">
                                    <i data-feather="inbox" style="width:64px;height:64px;" class="text-muted mb-3"></i>
                                    <h5 class="text-muted">No Pending Applications</h5>
                                    <p class="text-muted">All agent applications have been reviewed</p>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($pendingAgents as $agent): 
                                    $agentData = json_decode($agent['notes'] ?? '{}', true);
                                ?>
                                    <div class="col-lg-6 mb-4">
                                        <div class="card border-0 shadow-sm h-100">
                                            <div class="card-body">
                                                <div class="d-flex align-items-start justify-content-between mb-3">
                                                    <div class="d-flex align-items-center">
                                                        <div class="bg-warning bg-opacity-10 text-warning rounded-circle d-flex align-items-center justify-content-center me-3" style="width:50px;height:50px;">
                                                            <i data-feather="user" style="width:24px;height:24px;"></i>
                                                        </div>
                                                        <div>
                                                            <h5 class="mb-0"><?= e($agent['first_name'] . ' ' . $agent['last_name']) ?></h5>
                                                            <small class="text-muted">Applied: <?= date('M d, Y', strtotime($agent['created_at'])) ?></small>
                                                        </div>
                                                    </div>
                                                    <span class="badge bg-warning text-dark">Pending</span>
                                                </div>

                                                <div class="mb-3">
                                                    <small class="text-muted d-block">Contact Information</small>
                                                    <p class="mb-1"><i data-feather="mail" style="width:14px;height:14px;" class="me-1"></i> <?= e($agent['email']) ?></p>
                                                    <p class="mb-0"><i data-feather="phone" style="width:14px;height:14px;" class="me-1"></i> <?= e($agent['phone']) ?></p>
                                                </div>

                                                <?php if (!empty($agentData)): ?>
                                                    <div class="mb-3">
                                                        <div class="row g-2">
                                                            <?php if (isset($agentData['city']) && isset($agentData['state'])): ?>
                                                                <div class="col-6">
                                                                    <small class="text-muted d-block">Location</small>
                                                                    <strong><?= e($agentData['city']) ?>, <?= e($agentData['state']) ?></strong>
                                                                </div>
                                                            <?php endif; ?>
                                                            <?php if (isset($agentData['experience_years'])): ?>
                                                                <div class="col-6">
                                                                    <small class="text-muted d-block">Experience</small>
                                                                    <strong><?= e($agentData['experience_years']) ?> years</strong>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>

                                                    <?php if (isset($agentData['bio'])): ?>
                                                        <div class="mb-3">
                                                            <small class="text-muted d-block mb-1">Bio</small>
                                                            <p class="small mb-0"><?= e(substr($agentData['bio'], 0, 150)) ?><?= strlen($agentData['bio']) > 150 ? '...' : '' ?></p>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endif; ?>

                                                <div class="d-flex gap-2">
                                                    <button type="button" class="btn btn-success btn-sm flex-grow-1" 
                                                            onclick="approveAgent(<?= $agent['id'] ?>, '<?= e($agent['first_name'] . ' ' . $agent['last_name']) ?>')">
                                                        <i data-feather="check-circle" style="width:14px;height:14px;"></i> Approve
                                                    </button>
                                                    <button type="button" class="btn btn-danger btn-sm flex-grow-1" 
                                                            onclick="rejectAgent(<?= $agent['id'] ?>, '<?= e($agent['first_name'] . ' ' . $agent['last_name']) ?>')">
                                                        <i data-feather="x-circle" style="width:14px;height:14px;"></i> Reject
                                                    </button>
                                                    <a href="user-view.php?id=<?= $agent['id'] ?>" class="btn btn-outline-secondary btn-sm">
                                                        <i data-feather="eye" style="width:14px;height:14px;"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Recently Approved -->
                    <div class="tab-pane fade" id="approved">
                        <?php if (empty($recentlyApproved)): ?>
                            <div class="card border-0 shadow-sm">
                                <div class="card-body text-center py-5">
                                    <i data-feather="check-circle" style="width:64px;height:64px;" class="text-muted mb-3"></i>
                                    <h5 class="text-muted">No Approved Agents Yet</h5>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="card border-0 shadow-sm">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Agent</th>
                                                <th>Email</th>
                                                <th>Phone</th>
                                                <th>Approved Date</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recentlyApproved as $agent): ?>
                                                <tr>
                                                    <td><?= e($agent['first_name'] . ' ' . $agent['last_name']) ?></td>
                                                    <td><?= e($agent['email']) ?></td>
                                                    <td><?= e($agent['phone']) ?></td>
                                                    <td><?= date('M d, Y h:i A', strtotime($agent['updated_at'])) ?></td>
                                                    <td>
                                                        <a href="agent-detail.php?id=<?= $agent['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                            <i data-feather="eye" style="width:14px;height:14px;"></i> View
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Rejected -->
                    <div class="tab-pane fade" id="rejected">
                        <?php if (empty($rejectedAgents)): ?>
                            <div class="card border-0 shadow-sm">
                                <div class="card-body text-center py-5">
                                    <i data-feather="x-circle" style="width:64px;height:64px;" class="text-muted mb-3"></i>
                                    <h5 class="text-muted">No Rejected Applications</h5>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="card border-0 shadow-sm">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Agent</th>
                                                <th>Email</th>
                                                <th>Phone</th>
                                                <th>Rejected Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($rejectedAgents as $agent): ?>
                                                <tr>
                                                    <td><?= e($agent['first_name'] . ' ' . $agent['last_name']) ?></td>
                                                    <td><?= e($agent['email']) ?></td>
                                                    <td><?= e($agent['phone']) ?></td>
                                                    <td><?= date('M d, Y h:i A', strtotime($agent['updated_at'])) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- Approve Confirmation Modal -->
    <div class="modal fade" id="approveModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="agent_id" id="approve_agent_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Approve Agent Application</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to approve <strong id="approve_agent_name"></strong> as an agent?</p>
                        <div class="alert alert-success">
                            <i data-feather="check-circle" style="width:16px;height:16px;"></i>
                            The agent will gain full access to their dashboard and can start managing properties.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Approve Agent</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reject Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="agent_id" id="reject_agent_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Reject Agent Application</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to reject <strong id="reject_agent_name"></strong>?</p>
                        <div class="mb-3">
                            <label class="form-label">Reason for Rejection (optional)</label>
                            <textarea name="rejection_reason" class="form-control" rows="3" placeholder="This reason can be sent to the applicant"></textarea>
                        </div>
                        <div class="alert alert-warning">
                            <i data-feather="alert-triangle" style="width:16px;height:16px;"></i>
                            The applicant will not be able to access the agent dashboard.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Reject Application</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    function approveAgent(id, name) {
        document.getElementById('approve_agent_id').value = id;
        document.getElementById('approve_agent_name').textContent = name;
        const modal = new bootstrap.Modal(document.getElementById('approveModal'));
        modal.show();
    }

    function rejectAgent(id, name) {
        document.getElementById('reject_agent_id').value = id;
        document.getElementById('reject_agent_name').textContent = name;
        const modal = new bootstrap.Modal(document.getElementById('rejectModal'));
        modal.show();
    }
    </script>

</body>
</html>
