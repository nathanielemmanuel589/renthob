<?php
/**
 * Agent - Profile
 * View and edit agent profile information
 * Rent Hob
 */
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/agent-functions.php';

requireRole('agent');

$agentId = currentUserId();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_profile') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        
        if ($firstName && $lastName && $email) {
            $stmt = $pdo->prepare('
                UPDATE users 
                SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ?
                WHERE id = ?
            ');
            $stmt->execute([$firstName, $lastName, $email, $phone, $address, $agentId]);
            
            // Update session name
            $_SESSION['user_name'] = $firstName . ' ' . $lastName;
            
            setFlash('success', 'Profile updated successfully');
        }
        redirect(BASE_URL . '/agent/profile.php');
    }
    
    if ($_POST['action'] === 'update_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Verify current password
        $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ?');
        $stmt->execute([$agentId]);
        $user = $stmt->fetch();
        
        if (password_verify($currentPassword, $user['password'])) {
            if ($newPassword === $confirmPassword && strlen($newPassword) >= 8) {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
                $stmt->execute([$hashedPassword, $agentId]);
                setFlash('success', 'Password updated successfully');
            } else {
                setFlash('error', 'New passwords do not match or are too short (min 8 characters)');
            }
        } else {
            setFlash('error', 'Current password is incorrect');
        }
        redirect(BASE_URL . '/agent/profile.php');
    }
}

// Get user profile
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$agentId]);
$profile = $stmt->fetch();

// Get agent-specific profile if exists
$stmt = $pdo->prepare('SELECT * FROM agent_profile WHERE agent_id = ?');
$stmt->execute([$agentId]);
$agentProfile = $stmt->fetch();

// Get statistics
$stats = getAgentStats($pdo, $agentId);

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>My Profile — Rent Hob Agent</title>
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
    <header class="adminuiux-header inner-page"><nav class="navbar navbar-expand-lg fixed-top"><div class="container-fluid"><button class="btn btn-link btn-square sidebar-toggler ms-1" type="button" onclick="initSidebar()"><i class="sidebar-svg" data-feather="menu"></i></button><a class="navbar-brand" href="dashboard.php"><img data-bs-img="light" src="<?= BASE_URL ?>/assets/img/logo-2.svg" alt="Rent Hob" style="max-height:32px;"><img data-bs-img="dark" src="<?= BASE_URL ?>/assets/img/logo-2.svg" alt="Rent Hob" style="max-height:32px;"><div class="lh-20"><span class="h4">Rent Hob</span><p class="company-tagline">Agent Portal</p></div></a><div class="ms-auto d-flex align-items-center gap-1"><div class="dropdown"><button class="btn btn-link no-caret dropdown-toggle d-flex align-items-center gap-2 px-2" type="button" data-bs-toggle="dropdown"><img src="<?= userAvatar($profile['avatar'] ?? null) ?>" alt="Agent" class="rounded-circle" width="34" height="34" style="object-fit:cover;"><span class="d-none d-md-inline small fw-semibold"><?= e($profile['first_name'] . ' ' . $profile['last_name']) ?></span></button><ul class="dropdown-menu dropdown-menu-end"><li><a class="dropdown-item" href="profile.php"><i data-feather="user" class="me-2" style="width:15px;height:15px;"></i> My Profile</a></li><li><a class="dropdown-item" href="settings.php"><i data-feather="settings" class="me-2" style="width:15px;height:15px;"></i> Settings</a></li><li><hr class="dropdown-divider"></li><li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/logout.php"><i data-feather="log-out" class="me-2" style="width:15px;height:15px;"></i> Logout</a></li></ul></div></div></div></nav></header>
    <div class="adminuiux-wrap">
        <?php include '../includes/agent-sidebar.php'; ?>
        <main class="adminuiux-content has-sidebar" onclick="contentClick()">
            <div class="container mt-2" id="main-content">
                
                <div class="row mb-3">
                    <div class="col">
                        <h4 class="mb-1 fw-semibold">My Profile</h4>
                        <p class="text-muted mb-0">Manage your profile information and account details</p>
                    </div>
                </div>

                <?php if ($flash): ?>
                    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show mb-3">
                        <?= e($flash['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row g-4">
                    <!-- Profile Card -->
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body text-center py-4">
                                <img src="<?= userAvatar($profile['avatar']) ?>" class="rounded-circle mb-3" width="120" height="120" alt="" style="object-fit:cover;">
                                <h5 class="mb-1"><?= e($profile['first_name'] . ' ' . $profile['last_name']) ?></h5>
                                <p class="text-muted mb-3"><span class="badge bg-orange">Agent</span></p>
                                <p class="small text-muted mb-2">
                                    <i data-feather="mail" style="width:14px;height:14px;"></i>
                                    <?= e($profile['email']) ?>
                                </p>
                                <p class="small text-muted mb-2">
                                    <i data-feather="phone" style="width:14px;height:14px;"></i>
                                    <?= e($profile['phone'] ?? 'Not set') ?>
                                </p>
                                <p class="small text-muted mb-0">
                                    <i data-feather="calendar" style="width:14px;height:14px;"></i>
                                    Joined <?= formatDate($profile['created_at'], 'M d, Y') ?>
                                </p>
                            </div>
                        </div>

                        <!-- Quick Stats -->
                        <div class="card border-0 shadow-sm mt-3">
                            <div class="card-body">
                                <h6 class="mb-3">Quick Stats</h6>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted small">Properties</span>
                                    <span class="fw-semibold"><?= $stats['assigned_properties'] ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted small">Active Tenants</span>
                                    <span class="fw-semibold"><?= $stats['active_tenants'] ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted small">Pending Tasks</span>
                                    <span class="fw-semibold"><?= $stats['pending_tasks'] ?></span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted small">Total Earned</span>
                                    <span class="fw-semibold text-success"><?= formatCurrency($stats['total_commission']) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Profile Form -->
                    <div class="col-md-8">
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-white border-0">
                                <h5 class="mb-0">Personal Information</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_profile">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">First Name <span class="text-danger">*</span></label>
                                            <input type="text" name="first_name" class="form-control" value="<?= e($profile['first_name']) ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Last Name <span class="text-danger">*</span></label>
                                            <input type="text" name="last_name" class="form-control" value="<?= e($profile['last_name']) ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Email <span class="text-danger">*</span></label>
                                            <input type="email" name="email" class="form-control" value="<?= e($profile['email']) ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Phone</label>
                                            <input type="tel" name="phone" class="form-control" value="<?= e($profile['phone'] ?? '') ?>">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Address</label>
                                            <textarea name="address" class="form-control" rows="2"><?= e($profile['address'] ?? '') ?></textarea>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <button type="submit" class="btn btn-primary">
                                            <i data-feather="save" style="width:16px;height:16px;" class="me-1"></i> Update Profile
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Change Password -->
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white border-0">
                                <h5 class="mb-0">Change Password</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_password">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label">Current Password <span class="text-danger">*</span></label>
                                            <input type="password" name="current_password" class="form-control" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">New Password <span class="text-danger">*</span></label>
                                            <input type="password" name="new_password" class="form-control" minlength="8" required>
                                            <small class="text-muted">Minimum 8 characters</small>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                                            <input type="password" name="confirm_password" class="form-control" minlength="8" required>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <button type="submit" class="btn btn-warning">
                                            <i data-feather="lock" style="width:16px;height:16px;" class="me-1"></i> Change Password
                                        </button>
                                    </div>
                                </form>
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
