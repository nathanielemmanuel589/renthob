<?php
/**
 * Admin - Agent Management
 * Manage agents and property assignments
 * Rent Hob
 */
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('admin');

// Handle agent assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'assign_property') {
        $agentId = (int)$_POST['agent_id'];
        $propertyId = (int)$_POST['property_id'];
        $commissionRate = (float)$_POST['commission_rate'];
        
        // Check if already assigned
        $stmt = $pdo->prepare('SELECT id FROM agent_properties WHERE agent_id = ? AND property_id = ?');
        $stmt->execute([$agentId, $propertyId]);
        
        if ($stmt->fetch()) {
            setFlash('Property already assigned to this agent', 'error');
        } else {
            $stmt = $pdo->prepare('
                INSERT INTO agent_properties (agent_id, property_id, commission_rate, assigned_date, status)
                VALUES (?, ?, ?, NOW(), "active")
            ');
            $stmt->execute([$agentId, $propertyId, $commissionRate]);
            setFlash('Property assigned successfully', 'success');
        }
        header('Location: agents.php');
        exit;
    }
    
    if ($_POST['action'] === 'remove_assignment') {
        $assignmentId = (int)$_POST['assignment_id'];
        $stmt = $pdo->prepare('UPDATE agent_properties SET status = "inactive" WHERE id = ?');
        $stmt->execute([$assignmentId]);
        setFlash('Property assignment removed', 'success');
        header('Location: agents.php');
        exit;
    }
}

// Get all agents with statistics
$stmt = $pdo->query('
    SELECT u.*, 
           (SELECT COUNT(*) FROM agent_properties WHERE agent_id = u.id AND status = "active") as assigned_properties,
           (SELECT COUNT(*) FROM agent_commissions WHERE agent_id = u.id) as total_commissions,
           (SELECT SUM(commission_amount) FROM agent_commissions WHERE agent_id = u.id AND status IN ("approved", "paid")) as total_earned
    FROM users u
    WHERE u.role = "agent"
    ORDER BY u.first_name ASC
');
$agents = $stmt->fetchAll();

// Get all available properties for assignment
$stmt = $pdo->query('
    SELECT p.id, p.title, p.address,
           CONCAT(u.first_name, " ", u.last_name) as landlord_name
    FROM properties p
    LEFT JOIN users u ON u.id = p.landlord_id
    WHERE p.status = "active"
    ORDER BY p.title ASC
');
$properties = $stmt->fetchAll();

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent Management — Rent Hob Admin</title>
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
                        <h4 class="mb-1 fw-semibold">Agent Management</h4>
                        <p class="text-muted mb-0">Manage agents and property assignments</p>
                    </div>
                    <div class="col-auto">
                        <a href="user-edit.php?role=agent" class="btn btn-primary">
                            <i data-feather="plus" style="width:16px;height:16px;"></i> Add New Agent
                        </a>
                    </div>
                </div>

                <?php if ($flash): ?>
                    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
                        <?= e($flash['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <?php if (empty($agents)): ?>
                            <div class="text-center py-5">
                                <i data-feather="users" style="width:64px;height:64px;" class="text-muted mb-3"></i>
                                <h5 class="text-muted">No Agents Found</h5>
                                <p class="text-muted">Add agents to start managing property assignments</p>
                                <a href="user-edit.php?role=agent" class="btn btn-primary">Add First Agent</a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Agent</th>
                                            <th>Contact</th>
                                            <th>Assigned Properties</th>
                                            <th>Total Commissions</th>
                                            <th>Total Earned</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($agents as $agent): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <img src="<?= userAvatar($agent['avatar']) ?>" class="rounded-circle" width="40" height="40" style="object-fit:cover;">
                                                        <div>
                                                            <p class="mb-0 fw-semibold small"><?= e($agent['first_name'] . ' ' . $agent['last_name']) ?></p>
                                                            <small class="text-muted">ID: <?= $agent['id'] ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <small><?= e($agent['email']) ?></small><br>
                                                    <small class="text-muted"><?= e($agent['phone'] ?? 'N/A') ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info"><?= $agent['assigned_properties'] ?> Properties</span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary"><?= $agent['total_commissions'] ?> Records</span>
                                                </td>
                                                <td>
                                                    <span class="fw-semibold text-success"><?= formatCurrency($agent['total_earned'] ?? 0) ?></span>
                                                </td>
                                                <td><?= statusBadge($agent['status'] ?? 'active') ?></td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button class="btn btn-outline-primary" onclick="assignProperty(<?= $agent['id'] ?>, '<?= e($agent['first_name'] . ' ' . $agent['last_name']) ?>')">
                                                            <i data-feather="plus-circle" style="width:14px;height:14px;"></i> Assign
                                                        </button>
                                                        <a href="agent-detail.php?id=<?= $agent['id'] ?>" class="btn btn-outline-secondary">
                                                            <i data-feather="eye" style="width:14px;height:14px;"></i> View
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

            </div>
        </main>
    </div>

    <!-- Assign Property Modal -->
    <div class="modal fade" id="assignPropertyModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="assign_property">
                    <input type="hidden" name="agent_id" id="assign_agent_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Assign Property to <span id="agent_name"></span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Select Property <span class="text-danger">*</span></label>
                            <select name="property_id" class="form-select" required>
                                <option value="">-- Choose Property --</option>
                                <?php foreach ($properties as $property): ?>
                                    <option value="<?= $property['id'] ?>">
                                        <?= e($property['title']) ?> (<?= e($property['landlord_name']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Commission Rate (%) <span class="text-danger">*</span></label>
                            <input type="number" name="commission_rate" class="form-control" value="10.00" step="0.01" min="0" max="100" required>
                            <small class="text-muted">Percentage of transaction amount agent will earn</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Assign Property</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    function assignProperty(agentId, agentName) {
        document.getElementById('assign_agent_id').value = agentId;
        document.getElementById('agent_name').textContent = agentName;
        const modal = new bootstrap.Modal(document.getElementById('assignPropertyModal'));
        modal.show();
    }
    </script>
</body>
</html>
