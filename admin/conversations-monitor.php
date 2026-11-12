<?php
/**
 * Admin — Conversation Monitor
 * View all conversations between users in the system
 */
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('admin');

$adminId = currentUserId();

// Get filters
$roleFilter = $_GET['role'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Get all conversations in the system (between any users)
$whereConditions = ['1=1'];
$params = [];

if ($roleFilter !== 'all') {
    $whereConditions[] = '(sender.role = ? OR receiver.role = ?)';
    $params[] = $roleFilter;
    $params[] = $roleFilter;
}

if ($searchQuery) {
    $whereConditions[] = "(sender.first_name LIKE ? OR sender.last_name LIKE ? OR 
                           receiver.first_name LIKE ? OR receiver.last_name LIKE ? OR
                           sender.email LIKE ? OR receiver.email LIKE ?)";
    $searchTerm = "%$searchQuery%";
    for ($i = 0; $i < 6; $i++) {
        $params[] = $searchTerm;
    }
}

$whereClause = implode(' AND ', $whereConditions);

// Get unique conversations with latest message
$stmt = $pdo->prepare("
    SELECT 
        m.sender_id,
        m.receiver_id,
        m.property_id,
        sender.first_name as sender_first_name,
        sender.last_name as sender_last_name,
        sender.email as sender_email,
        sender.role as sender_role,
        sender.avatar as sender_avatar,
        receiver.first_name as receiver_first_name,
        receiver.last_name as receiver_last_name,
        receiver.email as receiver_email,
        receiver.role as receiver_role,
        receiver.avatar as receiver_avatar,
        p.title as property_title,
        MAX(m.created_at) as last_message_time,
        (SELECT message FROM messages 
         WHERE (sender_id = m.sender_id AND receiver_id = m.receiver_id) 
            OR (sender_id = m.receiver_id AND receiver_id = m.sender_id)
         ORDER BY created_at DESC LIMIT 1) as last_message,
        (SELECT COUNT(*) FROM messages 
         WHERE (sender_id = m.sender_id AND receiver_id = m.receiver_id) 
            OR (sender_id = m.receiver_id AND receiver_id = m.sender_id)) as message_count
    FROM messages m
    JOIN users sender ON m.sender_id = sender.id
    JOIN users receiver ON m.receiver_id = receiver.id
    LEFT JOIN properties p ON m.property_id = p.id
    WHERE $whereClause
    GROUP BY m.sender_id, m.receiver_id, m.property_id, 
             sender.first_name, sender.last_name, sender.email, sender.role, sender.avatar,
             receiver.first_name, receiver.last_name, receiver.email, receiver.role, receiver.avatar,
             p.title
    ORDER BY MAX(m.created_at) DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$conversations = $stmt->fetchAll();

// Get total count
$countStmt = $pdo->prepare("
    SELECT COUNT(DISTINCT CONCAT(
        LEAST(sender_id, receiver_id), 
        '-', 
        GREATEST(sender_id, receiver_id),
        IFNULL(CONCAT('-', property_id), '')
    )) as total
    FROM messages m
    JOIN users sender ON m.sender_id = sender.id
    JOIN users receiver ON m.receiver_id = receiver.id
    WHERE $whereClause
");
$countStmt->execute($params);
$totalConversations = $countStmt->fetchColumn();
$totalPages = ceil($totalConversations / $perPage);

// Get selected conversation messages
$selectedSenderId = $_GET['sender_id'] ?? null;
$selectedReceiverId = $_GET['receiver_id'] ?? null;
$selectedPropertyId = $_GET['property_id'] ?? null;
$messages = [];
$conversationDetails = null;

if ($selectedSenderId && $selectedReceiverId) {
    // Get conversation details
    $stmt = $pdo->prepare("
        SELECT 
            s.id as sender_id, s.first_name as sender_first_name, s.last_name as sender_last_name,
            s.email as sender_email, s.role as sender_role, s.avatar as sender_avatar,
            r.id as receiver_id, r.first_name as receiver_first_name, r.last_name as receiver_last_name,
            r.email as receiver_email, r.role as receiver_role, r.avatar as receiver_avatar,
            p.id as property_id, p.title as property_title
        FROM users s
        CROSS JOIN users r
        LEFT JOIN properties p ON p.id = ?
        WHERE s.id = ? AND r.id = ?
    ");
    $stmt->execute([$selectedPropertyId, $selectedSenderId, $selectedReceiverId]);
    $conversationDetails = $stmt->fetch();
    
    // Get all messages in conversation
    if ($selectedPropertyId) {
        $stmt = $pdo->prepare("
            SELECT * FROM messages 
            WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
              AND property_id = ?
            ORDER BY created_at ASC
        ");
        $stmt->execute([$selectedSenderId, $selectedReceiverId, $selectedReceiverId, $selectedSenderId, $selectedPropertyId]);
    } else {
        $stmt = $pdo->prepare("
            SELECT * FROM messages 
            WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
              AND property_id IS NULL
            ORDER BY created_at ASC
        ");
        $stmt->execute([$selectedSenderId, $selectedReceiverId, $selectedReceiverId, $selectedSenderId]);
    }
    $messages = $stmt->fetchAll();
}

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Conversation Monitor — Rent Hob Admin</title>
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/img/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Open+Sans:ital,wght@0,300..800;1,300..800&display=swap" rel="stylesheet">
    
    <style>:root{--adminuiux-content-font:"Open Sans",sans-serif;--adminuiux-content-font-weight:400;--adminuiux-title-font:"Inter",sans-serif;--adminuiux-title-font-weight:600;}</style>
    <script defer src="<?= BASE_URL ?>/dashboard/assets/js/third-party.js"></script>
    <script defer src="<?= BASE_URL ?>/dashboard/assets/js/app.js"></script>
    <script defer src="<?= BASE_URL ?>/dashboard/assets/js/core.js"></script>
    <link href="<?= BASE_URL ?>/dashboard/assets/css/app.css" rel="stylesheet">
    
    <style>
        .conversation-item {
            cursor: pointer;
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }
        .conversation-item:hover {
            background: #f8f9fa;
        }
        .conversation-item.active {
            background: #d4edda;
            border-left-color: #28a745;
        }
        .messages-container {
            height: calc(100vh - 300px);
            overflow-y: auto;
            padding: 20px;
        }
        .message-bubble {
            max-width: 80%;
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 16px;
        }
        .message-from {
            background: #e3f2fd;
            margin-right: auto;
        }
        .message-to {
            background: #f3e5f5;
            margin-left: auto;
        }
        .role-badge {
            font-size: 0.7rem;
            padding: 2px 6px;
        }
        .conversation-arrow {
            font-size: 1.2rem;
            color: #6c757d;
        }
    </style>
</head>

<body class="main-bg main-bg-blur main-bg-opac roundedui adminuiux-sidebar-standard adminuiux-sidebar-fill-white adminuiux-mobile-footer-fill-theme adminuiux-sidebar-boxed adminuiux-header-transparent adminuiux-header-standard theme-green bg-gradient-3 scrollup"
      data-theme="theme-green"
      data-sidebarfill="adminuiux-sidebar-fill-white"
      data-sidebarlayout="adminuiux-sidebar-boxed"
      data-headerlayout="adminuiux-header-standard"
      data-headerfill="adminuiux-header-transparent"
      data-bggradient="bg-gradient-3">

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

    <!-- HEADER -->
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

        <?php include '../includes/admin-sidebar.php'; ?>

        <main class="adminuiux-content has-sidebar" onclick="contentClick()">
            <div class="container mt-2" id="main-content">

                <div class="row mb-3">
                    <div class="col">
                        <h4 class="mb-1 fw-semibold">
                            <i data-feather="monitor" style="width:24px;height:24px;"></i>
                            Conversation Monitor
                        </h4>
                        <p class="text-muted mb-0">View all conversations between users in the system</p>
                    </div>
                    <div class="col-auto">
                        <a href="messages.php" class="btn btn-primary">
                            <i data-feather="message-square" style="width:16px;height:16px;"></i>
                            My Messages
                        </a>
                    </div>
                </div>

                <?php if ($flash): ?>
                    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
                        <?= e($flash['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Filters -->
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label small">Filter by Role</label>
                                <select name="role" class="form-select" onchange="this.form.submit()">
                                    <option value="all" <?= $roleFilter === 'all' ? 'selected' : '' ?>>All Roles</option>
                                    <option value="landlord" <?= $roleFilter === 'landlord' ? 'selected' : '' ?>>Landlords</option>
                                    <option value="tenant" <?= $roleFilter === 'tenant' ? 'selected' : '' ?>>Tenants</option>
                                    <option value="agent" <?= $roleFilter === 'agent' ? 'selected' : '' ?>>Agents</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Search Conversations</label>
                                <input type="text" name="search" class="form-control" 
                                       placeholder="Search by user name or email..." 
                                       value="<?= e($searchQuery) ?>">
                            </div>
                            <div class="col-md-2 align-self-end">
                                <button type="submit" class="btn btn-success w-100">
                                    <i data-feather="search" style="width:16px;height:16px;"></i>
                                    Search
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="row">
                    <!-- Conversations List -->
                    <div class="col-lg-5 mb-4">
                        <div class="card border-0 shadow-sm" style="max-height: calc(100vh - 200px); overflow-y: auto;">
                            <div class="card-header bg-white">
                                <h6 class="mb-0">All Conversations (<?= number_format($totalConversations) ?>)</h6>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($conversations)): ?>
                                    <div class="text-center py-5">
                                        <i data-feather="message-circle" style="width:48px;height:48px;opacity:.3;" class="mb-2"></i>
                                        <p class="text-muted mb-0">No conversations found</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($conversations as $conv): 
                                        $isActive = ($selectedSenderId == $conv['sender_id'] && 
                                                    $selectedReceiverId == $conv['receiver_id'] &&
                                                    (($selectedPropertyId && $selectedPropertyId == $conv['property_id']) || 
                                                     (!$selectedPropertyId && !$conv['property_id'])));
                                        
                                        $roleColors = ['landlord' => 'success', 'tenant' => 'primary', 'agent' => 'warning'];
                                        $senderColor = $roleColors[$conv['sender_role']] ?? 'secondary';
                                        $receiverColor = $roleColors[$conv['receiver_role']] ?? 'secondary';
                                    ?>
                                        <div class="conversation-item p-3 border-bottom <?= $isActive ? 'active' : '' ?>" 
                                             onclick="window.location.href='<?= BASE_URL ?>/admin/conversations-monitor.php?sender_id=<?= $conv['sender_id'] ?>&receiver_id=<?= $conv['receiver_id'] ?><?= $conv['property_id'] ? '&property_id=' . $conv['property_id'] : '' ?>&role=<?= $roleFilter ?>&search=<?= urlencode($searchQuery) ?>'">
                                            <div class="d-flex align-items-center gap-2 mb-2">
                                                <!-- Sender -->
                                                <div class="d-flex align-items-center flex-shrink-0">
                                                    <img src="<?= userAvatar($conv['sender_avatar']) ?>" 
                                                         class="rounded-circle" 
                                                         width="30" height="30" 
                                                         alt="" style="object-fit:cover;">
                                                    <div class="ms-2">
                                                        <div class="fw-semibold small"><?= e($conv['sender_first_name']) ?></div>
                                                        <span class="badge bg-<?= $senderColor ?> role-badge"><?= ucfirst($conv['sender_role']) ?></span>
                                                    </div>
                                                </div>
                                                
                                                <span class="conversation-arrow">↔</span>
                                                
                                                <!-- Receiver -->
                                                <div class="d-flex align-items-center flex-shrink-0">
                                                    <img src="<?= userAvatar($conv['receiver_avatar']) ?>" 
                                                         class="rounded-circle" 
                                                         width="30" height="30" 
                                                         alt="" style="object-fit:cover;">
                                                    <div class="ms-2">
                                                        <div class="fw-semibold small"><?= e($conv['receiver_first_name']) ?></div>
                                                        <span class="badge bg-<?= $receiverColor ?> role-badge"><?= ucfirst($conv['receiver_role']) ?></span>
                                                    </div>
                                                </div>
                                                
                                                <div class="ms-auto">
                                                    <span class="badge bg-secondary"><?= $conv['message_count'] ?></span>
                                                </div>
                                            </div>
                                            
                                            <?php if ($conv['property_title']): ?>
                                                <div class="small text-muted mb-1">
                                                    <i data-feather="home" style="width:12px;height:12px;"></i>
                                                    <?= e($conv['property_title']) ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <p class="small text-muted mb-1 text-truncate"><?= e($conv['last_message']) ?></p>
                                            <small class="text-muted"><?= timeAgo($conv['last_message_time']) ?></small>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($totalPages > 1): ?>
                                <div class="card-footer bg-white">
                                    <nav>
                                        <ul class="pagination pagination-sm justify-content-center mb-0">
                                            <?php if ($page > 1): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=<?= $page - 1 ?>&role=<?= $roleFilter ?>&search=<?= urlencode($searchQuery) ?>">Previous</a>
                                                </li>
                                            <?php endif; ?>
                                            
                                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                                    <a class="page-link" href="?page=<?= $i ?>&role=<?= $roleFilter ?>&search=<?= urlencode($searchQuery) ?>"><?= $i ?></a>
                                                </li>
                                            <?php endfor; ?>
                                            
                                            <?php if ($page < $totalPages): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=<?= $page + 1 ?>&role=<?= $roleFilter ?>&search=<?= urlencode($searchQuery) ?>">Next</a>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </nav>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Message Thread -->
                    <div class="col-lg-7">
                        <?php if ($conversationDetails): ?>
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-white">
                                    <div class="row align-items-center">
                                        <div class="col">
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="d-flex align-items-center">
                                                    <img src="<?= userAvatar($conversationDetails['sender_avatar']) ?>" 
                                                         class="rounded-circle" 
                                                         width="35" height="35" 
                                                         alt="" style="object-fit:cover;">
                                                    <div class="ms-2">
                                                        <h6 class="mb-0"><?= e($conversationDetails['sender_first_name'] . ' ' . $conversationDetails['sender_last_name']) ?></h6>
                                                        <span class="badge bg-<?= $roleColors[$conversationDetails['sender_role']] ?? 'secondary' ?> role-badge">
                                                            <?= ucfirst($conversationDetails['sender_role']) ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                
                                                <span class="text-muted">↔</span>
                                                
                                                <div class="d-flex align-items-center">
                                                    <img src="<?= userAvatar($conversationDetails['receiver_avatar']) ?>" 
                                                         class="rounded-circle" 
                                                         width="35" height="35" 
                                                         alt="" style="object-fit:cover;">
                                                    <div class="ms-2">
                                                        <h6 class="mb-0"><?= e($conversationDetails['receiver_first_name'] . ' ' . $conversationDetails['receiver_last_name']) ?></h6>
                                                        <span class="badge bg-<?= $roleColors[$conversationDetails['receiver_role']] ?? 'secondary' ?> role-badge">
                                                            <?= ucfirst($conversationDetails['receiver_role']) ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <?php if ($conversationDetails['property_title']): ?>
                                                <small class="text-muted d-block mt-1">
                                                    <i data-feather="home" style="width:12px;height:12px;"></i>
                                                    <?= e($conversationDetails['property_title']) ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card-body messages-container bg-light">
                                    <?php if (empty($messages)): ?>
                                        <div class="text-center py-5">
                                            <i data-feather="message-circle" style="width:48px;height:48px;opacity:.3;" class="mb-2"></i>
                                            <p class="text-muted mb-0">No messages in this conversation</p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($messages as $msg): 
                                            $isFromSender = ($msg['sender_id'] == $conversationDetails['sender_id']);
                                            $senderName = $isFromSender ? 
                                                $conversationDetails['sender_first_name'] : 
                                                $conversationDetails['receiver_first_name'];
                                        ?>
                                            <div class="message-bubble <?= $isFromSender ? 'message-from' : 'message-to' ?>">
                                                <div class="small fw-semibold mb-1"><?= e($senderName) ?></div>
                                                <?= nl2br(e($msg['message'])) ?>
                                                <div class="small text-muted mt-1">
                                                    <?= date('M d, Y h:i A', strtotime($msg['created_at'])) ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="card-footer bg-light border-0">
                                    <small class="text-muted">
                                        <i data-feather="info" style="width:14px;height:14px;"></i>
                                        Total messages: <?= count($messages) ?> | 
                                        Last activity: <?= !empty($messages) ? timeAgo(end($messages)['created_at']) : 'N/A' ?>
                                    </small>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="card border-0 shadow-sm">
                                <div class="card-body text-center py-5">
                                    <i data-feather="eye" style="width:64px;height:64px;opacity:.3;" class="mb-3"></i>
                                    <h6>Select a conversation to view</h6>
                                    <p class="text-muted mb-0">Click on any conversation from the list to monitor the messages</p>
                                </div>
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

    <footer class="adminuiux-mobile-footer hide-on-scrolldown style-1">
        <div class="row gx-0 justify-content-around">
            <div class="col-auto"><a href="dashboard.php" class="nav-link text-center py-2 px-3"><i data-feather="grid" style="width:20px;height:20px;"></i><p class="mb-0" style="font-size:.65rem;">Home</p></a></div>
            <div class="col-auto"><a href="users.php" class="nav-link text-center py-2 px-3"><i data-feather="users" style="width:20px;height:20px;"></i><p class="mb-0" style="font-size:.65rem;">Users</p></a></div>
            <div class="col-auto"><a href="properties.php" class="nav-link text-center py-2 px-3"><i data-feather="home" style="width:20px;height:20px;"></i><p class="mb-0" style="font-size:.65rem;">Properties</p></a></div>
            <div class="col-auto"><a href="settings.php" class="nav-link text-center py-2 px-3"><i data-feather="settings" style="width:20px;height:20px;"></i><p class="mb-0" style="font-size:.65rem;">Settings</p></a></div>
            <div class="col-auto"><a href="<?= BASE_URL ?>/logout.php" class="nav-link text-center py-2 px-3"><i data-feather="log-out" style="width:20px;height:20px;"></i><p class="mb-0" style="font-size:.65rem;">Logout</p></a></div>
        </div>
    </footer>

    <script>
        const messagesContainer = document.querySelector('.messages-container');
        if (messagesContainer && messagesContainer.children.length > 1) {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
    </script>
</body>
</html>
