<?php
/**
 * Agent - Tasks Management
 * Track daily tasks, follow-ups, and property-related work
 * Rent Hob
 */
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/agent-functions.php';

requireRole('agent');

$agentId = currentUserId();

// Handle task actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $propertyId = !empty($_POST['property_id']) ? (int)$_POST['property_id'] : null;
        $tenantId = !empty($_POST['tenant_id']) ? (int)$_POST['tenant_id'] : null;
        $priority = $_POST['priority'] ?? 'medium';
        $dueDate = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
        
        if ($title) {
            $stmt = $pdo->prepare('
                INSERT INTO agent_tasks (agent_id, property_id, tenant_id, title, description, priority, due_date, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, "pending")
            ');
            $stmt->execute([$agentId, $propertyId, $tenantId, $title, $description, $priority, $dueDate]);
            setFlash('Task created successfully', 'success');
        }
        header('Location: tasks.php');
        exit;
    }
    
    if ($action === 'update_status') {
        $taskId = (int)$_POST['task_id'];
        $status = $_POST['status'];
        
        // Verify task belongs to agent
        $stmt = $pdo->prepare('SELECT id FROM agent_tasks WHERE id = ? AND agent_id = ?');
        $stmt->execute([$taskId, $agentId]);
        
        if ($stmt->fetch()) {
            $completedAt = ($status === 'completed') ? date('Y-m-d H:i:s') : null;
            $stmt = $pdo->prepare('UPDATE agent_tasks SET status = ?, completed_at = ? WHERE id = ?');
            $stmt->execute([$status, $completedAt, $taskId]);
            setFlash('Task status updated', 'success');
        }
        header('Location: tasks.php');
        exit;
    }
    
    if ($action === 'delete') {
        $taskId = (int)$_POST['task_id'];
        
        // Verify task belongs to agent
        $stmt = $pdo->prepare('SELECT id FROM agent_tasks WHERE id = ? AND agent_id = ?');
        $stmt->execute([$taskId, $agentId]);
        
        if ($stmt->fetch()) {
            $stmt = $pdo->prepare('DELETE FROM agent_tasks WHERE id = ?');
            $stmt->execute([$taskId]);
            setFlash('Task deleted', 'success');
        }
        header('Location: tasks.php');
        exit;
    }
    
    if ($action === 'update') {
        $taskId = (int)$_POST['task_id'];
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $priority = $_POST['priority'] ?? 'medium';
        $dueDate = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
        
        // Verify task belongs to agent
        $stmt = $pdo->prepare('SELECT id FROM agent_tasks WHERE id = ? AND agent_id = ?');
        $stmt->execute([$taskId, $agentId]);
        
        if ($stmt->fetch() && $title) {
            $stmt = $pdo->prepare('
                UPDATE agent_tasks 
                SET title = ?, description = ?, priority = ?, due_date = ?
                WHERE id = ?
            ');
            $stmt->execute([$title, $description, $priority, $dueDate, $taskId]);
            setFlash('Task updated successfully', 'success');
        }
        header('Location: tasks.php');
        exit;
    }
}

// Get filters
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$priorityFilter = isset($_GET['priority']) ? $_GET['priority'] : '';
$dueDateFilter = isset($_GET['due']) ? $_GET['due'] : '';

// Build query
$sql = "SELECT t.*,
        p.title as property_title,
        u.first_name as tenant_first_name, u.last_name as tenant_last_name
        FROM agent_tasks t
        LEFT JOIN properties p ON p.id = t.property_id
        LEFT JOIN users u ON u.id = t.tenant_id
        WHERE t.agent_id = ?";

$params = [$agentId];

if ($statusFilter) {
    $sql .= " AND t.status = ?";
    $params[] = $statusFilter;
}

if ($priorityFilter) {
    $sql .= " AND t.priority = ?";
    $params[] = $priorityFilter;
}

if ($dueDateFilter === 'today') {
    $sql .= " AND DATE(t.due_date) = CURDATE()";
} elseif ($dueDateFilter === 'overdue') {
    $sql .= " AND DATE(t.due_date) < CURDATE() AND t.status != 'completed'";
} elseif ($dueDateFilter === 'week') {
    $sql .= " AND t.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
}

$sql .= " ORDER BY 
    CASE t.status 
        WHEN 'pending' THEN 1 
        WHEN 'in_progress' THEN 2 
        WHEN 'completed' THEN 3 
    END,
    CASE t.priority 
        WHEN 'urgent' THEN 1 
        WHEN 'high' THEN 2 
        WHEN 'medium' THEN 3 
        WHEN 'low' THEN 4 
    END,
    t.due_date ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tasks = $stmt->fetchAll();

// Count by status
$stmt = $pdo->prepare('
    SELECT status, COUNT(*) as count
    FROM agent_tasks
    WHERE agent_id = ?
    GROUP BY status
');
$stmt->execute([$agentId]);
$statusCounts = [];
while ($row = $stmt->fetch()) {
    $statusCounts[$row['status']] = $row['count'];
}

// Count overdue
$stmt = $pdo->prepare('
    SELECT COUNT(*) as count
    FROM agent_tasks
    WHERE agent_id = ? AND DATE(due_date) < CURDATE() AND status != "completed"
');
$stmt->execute([$agentId]);
$overdueCount = (int)$stmt->fetchColumn();

// Get agent's properties for task creation
$stmt = $pdo->prepare('
    SELECT p.id, p.title
    FROM properties p
    INNER JOIN agent_properties ap ON ap.property_id = p.id
    WHERE ap.agent_id = ? AND ap.status = "active"
    ORDER BY p.title ASC
');
$stmt->execute([$agentId]);
$agentProperties = $stmt->fetchAll();

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>My Tasks — Rent Hob Agent</title>
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
                        <h4 class="mb-1 fw-semibold">My Tasks</h4>
                        <p class="text-muted mb-0">Manage your daily tasks and to-dos</p>
                    </div>
                    <div class="col-auto align-self-center">
                        <?php if ($overdueCount > 0): ?>
                            <span class="badge bg-danger px-3 py-2 me-2">
                                <i data-feather="alert-circle" style="width:14px;height:14px;" class="me-1"></i>
                                <?= $overdueCount ?> Overdue
                            </span>
                        <?php endif; ?>
                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createTaskModal">
                            <i data-feather="plus" style="width:16px;height:16px;" class="me-1"></i> New Task
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
                            <div class="col-md-4">
                                <label class="form-label small text-muted mb-2">Filter by Status</label>
                                <div class="btn-group w-100" role="group">
                                    <a href="tasks.php" class="btn btn-<?= !$statusFilter ? 'primary' : 'outline-primary' ?> btn-sm">
                                        All (<?= array_sum($statusCounts) ?>)
                                    </a>
                                    <a href="tasks.php?status=pending" class="btn btn-<?= $statusFilter === 'pending' ? 'warning' : 'outline-warning' ?> btn-sm">
                                        Pending (<?= $statusCounts['pending'] ?? 0 ?>)
                                    </a>
                                    <a href="tasks.php?status=in_progress" class="btn btn-<?= $statusFilter === 'in_progress' ? 'info' : 'outline-info' ?> btn-sm">
                                        In Progress (<?= $statusCounts['in_progress'] ?? 0 ?>)
                                    </a>
                                    <a href="tasks.php?status=completed" class="btn btn-<?= $statusFilter === 'completed' ? 'success' : 'outline-success' ?> btn-sm">
                                        Completed (<?= $statusCounts['completed'] ?? 0 ?>)
                                    </a>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small text-muted mb-2">Filter by Priority</label>
                                <div class="btn-group w-100" role="group">
                                    <a href="tasks.php" class="btn btn-<?= !$priorityFilter ? 'secondary' : 'outline-secondary' ?> btn-sm">All</a>
                                    <a href="tasks.php?priority=urgent" class="btn btn-<?= $priorityFilter === 'urgent' ? 'danger' : 'outline-danger' ?> btn-sm">Urgent</a>
                                    <a href="tasks.php?priority=high" class="btn btn-<?= $priorityFilter === 'high' ? 'warning' : 'outline-warning' ?> btn-sm">High</a>
                                    <a href="tasks.php?priority=medium" class="btn btn-<?= $priorityFilter === 'medium' ? 'info' : 'outline-info' ?> btn-sm">Medium</a>
                                    <a href="tasks.php?priority=low" class="btn btn-<?= $priorityFilter === 'low' ? 'secondary' : 'outline-secondary' ?> btn-sm">Low</a>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small text-muted mb-2">Filter by Due Date</label>
                                <div class="btn-group w-100" role="group">
                                    <a href="tasks.php" class="btn btn-<?= !$dueDateFilter ? 'secondary' : 'outline-secondary' ?> btn-sm">All</a>
                                    <a href="tasks.php?due=today" class="btn btn-<?= $dueDateFilter === 'today' ? 'info' : 'outline-info' ?> btn-sm">Today</a>
                                    <a href="tasks.php?due=week" class="btn btn-<?= $dueDateFilter === 'week' ? 'warning' : 'outline-warning' ?> btn-sm">This Week</a>
                                    <a href="tasks.php?due=overdue" class="btn btn-<?= $dueDateFilter === 'overdue' ? 'danger' : 'outline-danger' ?> btn-sm">Overdue</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tasks List -->
                <?php if (empty($tasks)): ?>
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center py-5">
                            <i data-feather="check-square" style="width:64px;height:64px;" class="text-muted mb-3"></i>
                            <h5 class="text-muted">No Tasks</h5>
                            <p class="text-muted mb-3">
                                <?php if ($statusFilter || $priorityFilter || $dueDateFilter): ?>
                                    No tasks match your filters. <a href="tasks.php">Clear filters</a>
                                <?php else: ?>
                                    You're all caught up! Create a new task to get started.
                                <?php endif; ?>
                            </p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTaskModal">
                                <i data-feather="plus" style="width:16px;height:16px;" class="me-1"></i> Create First Task
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($tasks as $task): ?>
                            <?php 
                            $isOverdue = $task['due_date'] && strtotime($task['due_date']) < strtotime('today') && $task['status'] !== 'completed';
                            $isDueToday = $task['due_date'] && date('Y-m-d', strtotime($task['due_date'])) === date('Y-m-d');
                            ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="card border-0 shadow-sm h-100 <?= $isOverdue ? 'border-danger border-2' : '' ?>">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <?= taskPriorityBadge($task['priority']) ?>
                                            <?= statusBadge($task['status']) ?>
                                        </div>
                                        
                                        <h6 class="fw-semibold mb-2"><?= e($task['title']) ?></h6>
                                        
                                        <?php if ($task['description']): ?>
                                            <p class="small text-muted mb-2"><?= e(truncate($task['description'], 80)) ?></p>
                                        <?php endif; ?>
                                        
                                        <?php if ($task['property_title']): ?>
                                            <p class="small mb-1">
                                                <i data-feather="home" style="width:12px;height:12px;"></i>
                                                <strong>Property:</strong> <?= e($task['property_title']) ?>
                                            </p>
                                        <?php endif; ?>
                                        
                                        <?php if ($task['tenant_first_name']): ?>
                                            <p class="small mb-1">
                                                <i data-feather="user" style="width:12px;height:12px;"></i>
                                                <strong>Tenant:</strong> <?= e($task['tenant_first_name'] . ' ' . $task['tenant_last_name']) ?>
                                            </p>
                                        <?php endif; ?>
                                        
                                        <?php if ($task['due_date']): ?>
                                            <p class="small mb-2 <?= $isOverdue ? 'text-danger fw-semibold' : ($isDueToday ? 'text-warning fw-semibold' : '') ?>">
                                                <i data-feather="calendar" style="width:12px;height:12px;"></i>
                                                <strong>Due:</strong> <?= formatDate($task['due_date']) ?>
                                                <?php if ($isOverdue): ?>
                                                    <span class="badge bg-danger ms-1">Overdue</span>
                                                <?php elseif ($isDueToday): ?>
                                                    <span class="badge bg-warning text-dark ms-1">Today</span>
                                                <?php endif; ?>
                                            </p>
                                        <?php endif; ?>
                                        
                                        <div class="d-flex gap-2 mt-3">
                                            <?php if ($task['status'] !== 'completed'): ?>
                                                <div class="btn-group btn-group-sm flex-fill" role="group">
                                                    <?php if ($task['status'] === 'pending'): ?>
                                                        <form method="POST" style="display:inline;" class="flex-fill">
                                                            <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                                            <input type="hidden" name="action" value="update_status">
                                                            <input type="hidden" name="status" value="in_progress">
                                                            <button type="submit" class="btn btn-outline-primary btn-sm w-100">
                                                                <i data-feather="play" style="width:12px;height:12px;"></i> Start
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <form method="POST" style="display:inline;" class="flex-fill">
                                                        <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                                        <input type="hidden" name="action" value="update_status">
                                                        <input type="hidden" name="status" value="completed">
                                                        <button type="submit" class="btn btn-outline-success btn-sm w-100">
                                                            <i data-feather="check" style="width:12px;height:12px;"></i> Complete
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php endif; ?>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                    <i data-feather="more-horizontal" style="width:14px;height:14px;"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <a class="dropdown-item" href="#" onclick="editTask(<?= htmlspecialchars(json_encode($task), ENT_QUOTES) ?>); return false;">
                                                            <i data-feather="edit" class="me-2" style="width:14px;height:14px;"></i> Edit
                                                        </a>
                                                    </li>
                                                    <?php if ($task['status'] === 'completed'): ?>
                                                        <li>
                                                            <form method="POST" style="display:inline;">
                                                                <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                                                <input type="hidden" name="action" value="update_status">
                                                                <input type="hidden" name="status" value="pending">
                                                                <button type="submit" class="dropdown-item">
                                                                    <i data-feather="rotate-ccw" class="me-2" style="width:14px;height:14px;"></i> Reopen
                                                                </button>
                                                            </form>
                                                        </li>
                                                    <?php endif; ?>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this task?')">
                                                            <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                                            <input type="hidden" name="action" value="delete">
                                                            <button type="submit" class="dropdown-item text-danger">
                                                                <i data-feather="trash-2" class="me-2" style="width:14px;height:14px;"></i> Delete
                                                            </button>
                                                        </form>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="row mt-3">
                    <div class="col text-center text-muted small py-2">
                        &copy; <?= date('Y') ?> Rent Hob. All rights reserved.
                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- Create Task Modal -->
    <div class="modal fade" id="createTaskModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="create">
                    <div class="modal-header">
                        <h5 class="modal-title">Create New Task</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Priority</label>
                                <select name="priority" class="form-select">
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Due Date</label>
                                <input type="date" name="due_date" class="form-control">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Related Property (Optional)</label>
                            <select name="property_id" class="form-select">
                                <option value="">-- None --</option>
                                <?php foreach ($agentProperties as $prop): ?>
                                    <option value="<?= $prop['id'] ?>"><?= e($prop['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Task</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Task Modal -->
    <div class="modal fade" id="editTaskModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="editTaskForm">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="task_id" id="edit_task_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Task</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" id="edit_title" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Priority</label>
                                <select name="priority" id="edit_priority" class="form-select">
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Due Date</label>
                                <input type="date" name="due_date" id="edit_due_date" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Task</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    function editTask(task) {
        document.getElementById('edit_task_id').value = task.id;
        document.getElementById('edit_title').value = task.title;
        document.getElementById('edit_description').value = task.description || '';
        document.getElementById('edit_priority').value = task.priority;
        document.getElementById('edit_due_date').value = task.due_date || '';
        
        const modal = new bootstrap.Modal(document.getElementById('editTaskModal'));
        modal.show();
    }
    </script>
</body>
</html>
