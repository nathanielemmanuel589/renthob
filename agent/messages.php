<?php
/**
 * Agent — Messages
 * Rent Hob
 */
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('agent');

$agentId = currentUserId();

// Get conversations (grouped by other user/property)
$stmt = $pdo->prepare("
    SELECT 
        CASE 
            WHEN m.sender_id = ? THEN m.receiver_id 
            ELSE m.sender_id 
        END as other_user_id,
        m.property_id,
        p.title as property_title,
        u.first_name,
        u.last_name,
        u.avatar,
        u.role,
        MAX(m.created_at) as last_message_time,
        (SELECT message FROM messages 
         WHERE (sender_id = ? AND receiver_id = other_user_id) 
            OR (sender_id = other_user_id AND receiver_id = ?) 
         ORDER BY created_at DESC LIMIT 1) as last_message,
        (SELECT COUNT(*) FROM messages 
         WHERE receiver_id = ? 
           AND sender_id = other_user_id 
           AND is_read = 0) as unread_count
    FROM messages m
    LEFT JOIN properties p ON m.property_id = p.id
    LEFT JOIN users u ON u.id = CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END
    WHERE m.sender_id = ? OR m.receiver_id = ?
    GROUP BY other_user_id, m.property_id, p.title, u.first_name, u.last_name, u.avatar, u.role
    ORDER BY MAX(m.created_at) DESC
");
$stmt->execute([$agentId, $agentId, $agentId, $agentId, $agentId, $agentId, $agentId]);
$conversations = $stmt->fetchAll();

// Get selected conversation messages
$selectedUserId = $_GET['user_id'] ?? null;
$selectedPropertyId = $_GET['property_id'] ?? null;
$messages = [];
$selectedConversation = null;

if ($selectedUserId) {
    // Get conversation details
    $stmt = $pdo->prepare("
        SELECT 
            u.id as user_id,
            u.first_name,
            u.last_name,
            u.email,
            u.phone,
            u.avatar,
            u.role,
            p.id as property_id,
            p.title as property_title
        FROM users u
        LEFT JOIN properties p ON p.id = ?
        WHERE u.id = ?
    ");
    $stmt->execute([$selectedPropertyId, $selectedUserId]);
    $selectedConversation = $stmt->fetch();
    
    // Get messages
    if ($selectedPropertyId) {
        $stmt = $pdo->prepare("
            SELECT * FROM messages 
            WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
              AND property_id = ?
            ORDER BY created_at ASC
        ");
        $stmt->execute([$agentId, $selectedUserId, $selectedUserId, $agentId, $selectedPropertyId]);
    } else {
        $stmt = $pdo->prepare("
            SELECT * FROM messages 
            WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
              AND property_id IS NULL
            ORDER BY created_at ASC
        ");
        $stmt->execute([$agentId, $selectedUserId, $selectedUserId, $agentId]);
    }
    $messages = $stmt->fetchAll();
    
    // Mark messages as read
    if ($selectedPropertyId) {
        $stmt = $pdo->prepare("
            UPDATE messages SET is_read = 1, read_at = NOW()
            WHERE receiver_id = ? AND sender_id = ? AND property_id = ? AND is_read = 0
        ");
        $stmt->execute([$agentId, $selectedUserId, $selectedPropertyId]);
    } else {
        $stmt = $pdo->prepare("
            UPDATE messages SET is_read = 1, read_at = NOW()
            WHERE receiver_id = ? AND sender_id = ? AND property_id IS NULL AND is_read = 0
        ");
        $stmt->execute([$agentId, $selectedUserId]);
    }
}

// Handle new message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $message = trim($_POST['message'] ?? '');
    $userId = $_POST['user_id'] ?? null;
    $propertyId = $_POST['property_id'] ?? null;
    
    if (!empty($message) && $userId) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO messages (sender_id, receiver_id, property_id, message, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$agentId, $userId, $propertyId ?: null, $message]);
            
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Message sent successfully'];
            $redirectUrl = BASE_URL . '/agent/messages.php?user_id=' . $userId;
            if ($propertyId) {
                $redirectUrl .= '&property_id=' . $propertyId;
            }
            header('Location: ' . $redirectUrl);
            exit();
        } catch (Exception $e) {
            error_log("Message send error: " . $e->getMessage());
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Failed to send message'];
        }
    }
}

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Messages — Rent Hob Agent</title>
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
        .conversation-list {
            max-height: calc(100vh - 200px);
            overflow-y: auto;
        }
        .conversation-item {
            cursor: pointer;
            transition: background 0.2s;
            border-left: 3px solid transparent;
        }
        .conversation-item:hover {
            background: #f8f9fa;
        }
        .conversation-item.active {
            background: #fff3cd;
            border-left-color: #ff9800;
        }
        .messages-container {
            height: calc(100vh - 350px);
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            padding: 20px;
        }
        .message-bubble {
            max-width: 70%;
            padding: 12px 16px;
            border-radius: 16px;
            margin-bottom: 12px;
            word-wrap: break-word;
        }
        .message-sent {
            background: #ff9800;
            color: white;
            align-self: flex-end;
            margin-left: auto;
            border-bottom-right-radius: 4px;
        }
        .message-received {
            background: #f1f3f5;
            color: #212529;
            align-self: flex-start;
            border-bottom-left-radius: 4px;
        }
        .message-time {
            font-size: 0.75rem;
            opacity: 0.8;
            margin-top: 4px;
        }
        .role-badge {
            font-size: 0.65rem;
            padding: 2px 6px;
        }
    </style>
</head>

<body class="main-bg main-bg-blur main-bg-opac roundedui adminuiux-sidebar-standard adminuiux-sidebar-fill-white adminuiux-mobile-footer-fill-theme adminuiux-sidebar-boxed adminuiux-header-transparent adminuiux-header-standard theme-orange bg-gradient-2 scrollup"
      data-theme="theme-orange"
      data-sidebarfill="adminuiux-sidebar-fill-white"
      data-sidebarlayout="adminuiux-sidebar-boxed"
      data-headerlayout="adminuiux-header-standard"
      data-headerfill="adminuiux-header-transparent"
      data-bggradient="bg-gradient-2">

    <?php include '../includes/agent-header.php'; ?>

    <div class="adminuiux-wrap">
        <?php include '../includes/agent-sidebar.php'; ?>

        <main class="adminuiux-content has-sidebar" onclick="contentClick()">
            <div class="container mt-2" id="main-content">

                <div class="row mb-3">
                    <div class="col">
                        <h4 class="mb-1 fw-semibold">Messages</h4>
                        <p class="text-muted mb-0">Communicate with landlords, tenants, and clients</p>
                    </div>
                </div>

                <?php if ($flash): ?>
                    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
                        <?= e($flash['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-lg-4 mb-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white border-0">
                                <h6 class="mb-0">Conversations</h6>
                            </div>
                            <div class="card-body p-0 conversation-list">
                                <?php if (empty($conversations)): ?>
                                    <div class="text-center py-5">
                                        <i data-feather="message-square" style="width:48px;height:48px;opacity:.3;" class="mb-2"></i>
                                        <p class="text-muted small mb-0">No conversations yet</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($conversations as $conv): 
                                        $isActive = ($selectedUserId == $conv['other_user_id'] && 
                                                    (($selectedPropertyId && $selectedPropertyId == $conv['property_id']) || 
                                                     (!$selectedPropertyId && !$conv['property_id'])));
                                        $roleColors = ['landlord' => 'success', 'tenant' => 'primary', 'agent' => 'warning', 'admin' => 'danger'];
                                        $roleColor = $roleColors[$conv['role']] ?? 'secondary';
                                    ?>
                                        <div class="conversation-item p-3 border-bottom <?= $isActive ? 'active' : '' ?>" 
                                             onclick="window.location.href='<?= BASE_URL ?>/agent/messages.php?user_id=<?= $conv['other_user_id'] ?><?= $conv['property_id'] ? '&property_id=' . $conv['property_id'] : '' ?>'">
                                            <div class="d-flex align-items-start">
                                                <img src="<?= userAvatar($conv['avatar']) ?>" 
                                                     class="rounded-circle flex-shrink-0" 
                                                     width="40" height="40" 
                                                     alt="" style="object-fit:cover;">
                                                <div class="flex-grow-1 ms-3 overflow-hidden">
                                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                                        <div>
                                                            <h6 class="mb-0 small fw-semibold"><?= e($conv['first_name'] . ' ' . $conv['last_name']) ?></h6>
                                                            <span class="badge bg-<?= $roleColor ?> role-badge"><?= ucfirst($conv['role']) ?></span>
                                                        </div>
                                                        <?php if ($conv['unread_count'] > 0): ?>
                                                            <span class="badge bg-warning rounded-pill"><?= $conv['unread_count'] ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php if ($conv['property_title']): ?>
                                                        <p class="small text-muted mb-1">
                                                            <i data-feather="home" style="width:12px;height:12px;"></i>
                                                            <?= e($conv['property_title']) ?>
                                                        </p>
                                                    <?php endif; ?>
                                                    <p class="small text-muted mb-0 text-truncate"><?= e($conv['last_message']) ?></p>
                                                    <small class="text-muted"><?= timeAgo($conv['last_message_time']) ?></small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-8">
                        <?php if ($selectedConversation): ?>
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-white border-0">
                                    <div class="d-flex align-items-center">
                                        <img src="<?= userAvatar($selectedConversation['avatar']) ?>" 
                                             class="rounded-circle" 
                                             width="40" height="40" 
                                             alt="" style="object-fit:cover;">
                                        <div class="ms-3 flex-grow-1">
                                            <h6 class="mb-0">
                                                <?= e($selectedConversation['first_name'] . ' ' . $selectedConversation['last_name']) ?>
                                                <span class="badge bg-<?= $roleColors[$selectedConversation['role']] ?? 'secondary' ?> ms-2" style="font-size:0.65rem;">
                                                    <?= ucfirst($selectedConversation['role']) ?>
                                                </span>
                                            </h6>
                                            <?php if ($selectedConversation['property_title']): ?>
                                                <small class="text-muted">
                                                    <i data-feather="home" style="width:12px;height:12px;"></i>
                                                    <?= e($selectedConversation['property_title']) ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="btn-group btn-group-sm">
                                            <?php if ($selectedConversation['phone']): ?>
                                                <a href="tel:<?= e($selectedConversation['phone']) ?>" 
                                                   class="btn btn-outline-warning">
                                                    <i data-feather="phone" style="width:14px;height:14px;"></i>
                                                </a>
                                            <?php endif; ?>
                                            <a href="mailto:<?= e($selectedConversation['email']) ?>" 
                                               class="btn btn-outline-primary">
                                                <i data-feather="mail" style="width:14px;height:14px;"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card-body messages-container bg-light" id="messagesContainer">
                                    <?php if (empty($messages)): ?>
                                        <div class="text-center py-5 text-muted">
                                            <i data-feather="message-circle" style="width:48px;height:48px;opacity:.3;" class="mb-2"></i>
                                            <p class="mb-0">No messages yet. Start the conversation!</p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($messages as $msg): 
                                            $isSent = ($msg['sender_id'] == $agentId);
                                        ?>
                                            <div class="message-bubble <?= $isSent ? 'message-sent' : 'message-received' ?>">
                                                <?= nl2br(e($msg['message'])) ?>
                                                <div class="message-time">
                                                    <?= date('M d, h:i A', strtotime($msg['created_at'])) ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="card-footer bg-white border-0">
                                    <form method="POST" action="">
                                        <input type="hidden" name="user_id" value="<?= $selectedConversation['user_id'] ?>">
                                        <input type="hidden" name="property_id" value="<?= $selectedConversation['property_id'] ?? '' ?>">
                                        <div class="input-group">
                                            <input type="text" name="message" class="form-control border-0" 
                                                   placeholder="Type your message..." required autofocus>
                                            <button type="submit" name="send_message" class="btn btn-warning">
                                                <i data-feather="send" style="width:16px;height:16px;"></i>
                                                Send
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="card border-0 shadow-sm">
                                <div class="card-body text-center py-5">
                                    <i data-feather="message-square" style="width:64px;height:64px;opacity:.3;" class="mb-3"></i>
                                    <h6>Select a conversation</h6>
                                    <p class="text-muted mb-0">Choose a conversation from the list to start messaging</p>
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

    <script>
        const messagesContainer = document.getElementById('messagesContainer');
        if (messagesContainer && messagesContainer.children.length > 1) {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
    </script>
</body>
</html>
