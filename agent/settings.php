<?php
/**
 * Agent - Settings
 * Manage account settings and preferences
 * Rent Hob
 */
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/agent-functions.php';

requireRole('agent');

$agentId = currentUserId();

// Get current user
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$agentId]);
$user = $stmt->fetch();

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Settings — Rent Hob Agent</title>
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
    <header class="adminuiux-header inner-page"><nav class="navbar navbar-expand-lg fixed-top"><div class="container-fluid"><button class="btn btn-link btn-square sidebar-toggler ms-1" type="button" onclick="initSidebar()"><i class="sidebar-svg" data-feather="menu"></i></button><a class="navbar-brand" href="dashboard.php"><img data-bs-img="light" src="<?= BASE_URL ?>/assets/img/logo-2.svg" alt="Rent Hob" style="max-height:32px;"><img data-bs-img="dark" src="<?= BASE_URL ?>/assets/img/logo-2.svg" alt="Rent Hob" style="max-height:32px;"><div class="lh-20"><span class="h4">Rent Hob</span><p class="company-tagline">Agent Portal</p></div></a><div class="ms-auto d-flex align-items-center gap-1"><div class="dropdown"><button class="btn btn-link no-caret dropdown-toggle d-flex align-items-center gap-2 px-2" type="button" data-bs-toggle="dropdown"><img src="<?= userAvatar($user['avatar'] ?? null) ?>" alt="Agent" class="rounded-circle" width="34" height="34" style="object-fit:cover;"><span class="d-none d-md-inline small fw-semibold"><?= e($user['first_name'] . ' ' . $user['last_name']) ?></span></button><ul class="dropdown-menu dropdown-menu-end"><li><a class="dropdown-item" href="profile.php"><i data-feather="user" class="me-2" style="width:15px;height:15px;"></i> My Profile</a></li><li><a class="dropdown-item" href="settings.php"><i data-feather="settings" class="me-2" style="width:15px;height:15px;"></i> Settings</a></li><li><hr class="dropdown-divider"></li><li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/logout.php"><i data-feather="log-out" class="me-2" style="width:15px;height:15px;"></i> Logout</a></li></ul></div></div></div></nav></header>
    <div class="adminuiux-wrap">
        <?php include '../includes/agent-sidebar.php'; ?>
        <main class="adminuiux-content has-sidebar" onclick="contentClick()">
            <div class="container mt-2" id="main-content">
                
                <div class="row mb-3">
                    <div class="col">
                        <h4 class="mb-1 fw-semibold">Settings</h4>
                        <p class="text-muted mb-0">Manage your account settings and preferences</p>
                    </div>
                </div>

                <?php if ($flash): ?>
                    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show mb-3">
                        <?= e($flash['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row g-4">
                    <!-- Account Information -->
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="p-3 bg-primary bg-opacity-10 rounded-3 me-3">
                                        <i data-feather="user" style="width:24px;height:24px;" class="text-primary"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0">Account Information</h6>
                                        <small class="text-muted">View and edit your profile</small>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <p class="mb-1"><strong>Name:</strong> <?= e($user['first_name'] . ' ' . $user['last_name']) ?></p>
                                    <p class="mb-1"><strong>Email:</strong> <?= e($user['email']) ?></p>
                                    <p class="mb-1"><strong>Phone:</strong> <?= e($user['phone'] ?? 'Not set') ?></p>
                                    <p class="mb-0"><strong>Role:</strong> <span class="badge bg-orange">Agent</span></p>
                                </div>
                                <a href="profile.php" class="btn btn-primary btn-sm">
                                    <i data-feather="edit" style="width:14px;height:14px;" class="me-1"></i> Edit Profile
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Security -->
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="p-3 bg-warning bg-opacity-10 rounded-3 me-3">
                                        <i data-feather="shield" style="width:24px;height:24px;" class="text-warning"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0">Security</h6>
                                        <small class="text-muted">Manage your password and security</small>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <p class="mb-2"><strong>Password:</strong> ••••••••</p>
                                    <p class="mb-0 small text-muted">Last changed: <?= formatDate($user['updated_at'] ?? $user['created_at'], 'M d, Y') ?></p>
                                </div>
                                <a href="profile.php#password" class="btn btn-warning btn-sm">
                                    <i data-feather="lock" style="width:14px;height:14px;" class="me-1"></i> Change Password
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Notifications (Placeholder) -->
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="p-3 bg-info bg-opacity-10 rounded-3 me-3">
                                        <i data-feather="bell" style="width:24px;height:24px;" class="text-info"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0">Notifications</h6>
                                        <small class="text-muted">Email and push notification preferences</small>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" id="emailNotif" checked disabled>
                                        <label class="form-check-label" for="emailNotif">Email Notifications</label>
                                    </div>
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" id="taskReminders" checked disabled>
                                        <label class="form-check-label" for="taskReminders">Task Reminders</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="commissionAlerts" checked disabled>
                                        <label class="form-check-label" for="commissionAlerts">Commission Alerts</label>
                                    </div>
                                </div>
                                <button class="btn btn-info btn-sm" disabled>
                                    <i data-feather="save" style="width:14px;height:14px;" class="me-1"></i> Coming Soon
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Preferences (Placeholder) -->
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="p-3 bg-success bg-opacity-10 rounded-3 me-3">
                                        <i data-feather="settings" style="width:24px;height:24px;" class="text-success"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0">Preferences</h6>
                                        <small class="text-muted">Display and interface preferences</small>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <p class="mb-2"><strong>Theme:</strong> Orange (Agent)</p>
                                    <p class="mb-2"><strong>Language:</strong> English</p>
                                    <p class="mb-0"><strong>Timezone:</strong> UTC</p>
                                </div>
                                <button class="btn btn-success btn-sm" disabled>
                                    <i data-feather="edit-3" style="width:14px;height:14px;" class="me-1"></i> Coming Soon
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Account Activity -->
                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="p-3 bg-secondary bg-opacity-10 rounded-3 me-3">
                                        <i data-feather="activity" style="width:24px;height:24px;" class="text-secondary"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0">Account Activity</h6>
                                        <small class="text-muted">Recent account information</small>
                                    </div>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <div class="border rounded p-3 text-center">
                                            <p class="text-muted small mb-1">Account Created</p>
                                            <p class="fw-semibold mb-0"><?= formatDate($user['created_at'], 'M d, Y') ?></p>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="border rounded p-3 text-center">
                                            <p class="text-muted small mb-1">Last Updated</p>
                                            <p class="fw-semibold mb-0"><?= formatDate($user['updated_at'] ?? $user['created_at'], 'M d, Y') ?></p>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="border rounded p-3 text-center">
                                            <p class="text-muted small mb-1">Account Status</p>
                                            <p class="mb-0"><?= statusBadge($user['status'] ?? 'active') ?></p>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="border rounded p-3 text-center">
                                            <p class="text-muted small mb-1">Verification</p>
                                            <p class="mb-0"><?= $user['is_verified'] ?? 0 ? '<span class="badge bg-success">Verified</span>' : '<span class="badge bg-warning text-dark">Pending</span>' ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Danger Zone -->
                    <div class="col-12">
                        <div class="card border-danger border-2 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="p-3 bg-danger bg-opacity-10 rounded-3 me-3">
                                        <i data-feather="alert-triangle" style="width:24px;height:24px;" class="text-danger"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0 text-danger">Danger Zone</h6>
                                        <small class="text-muted">Irreversible actions - proceed with caution</small>
                                    </div>
                                </div>
                                <div class="alert alert-danger mb-3">
                                    <p class="mb-0 small">
                                        <strong>Warning:</strong> Account deletion and deactivation features are managed by the system administrator.
                                        Contact support if you need to deactivate or delete your account.
                                    </p>
                                </div>
                                <button class="btn btn-outline-danger btn-sm" disabled>
                                    <i data-feather="user-x" style="width:14px;height:14px;" class="me-1"></i> Deactivate Account
                                </button>
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
