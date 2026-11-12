<?php
/**
 * Admin — Platform Settings
 * Rent Hob
 */
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('admin');

// ── Fetch current settings ────────────────────────────────────────────────────
$stmt = $pdo->query('SELECT setting_key, setting_val FROM settings');
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_val'];
}

// Helper function to get setting value (renamed to avoid conflict)
function getSettingValue($key, $default = '') {
    global $settings;
    return $settings[$key] ?? $default;
}

// ── Handle settings update ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? '')) {
    $settingsToUpdate = [
        'site_name',
        'site_email',
        'site_phone',
        'site_address',
        'currency',
        'currency_symbol',
        'platform_fee_percent',
        'maintenance_mode'
    ];

    foreach ($settingsToUpdate as $key) {
        $value = $_POST[$key] ?? '';
        $stmt = $pdo->prepare('INSERT INTO settings (setting_key, setting_val) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_val = ?');
        $stmt->execute([$key, $value, $value]);
    }

    logActivity($pdo, currentUserId(), "Updated platform settings", 'settings');
    setFlash('success', 'Settings updated successfully.');
    redirect(BASE_URL . '/admin/settings.php');
}

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Settings — Rent Hob Admin</title>
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

<body class="main-bg main-bg-blur main-bg-opac roundedui adminuiux-sidebar-standard adminuiux-sidebar-fill-white adminuiux-mobile-footer-fill-theme adminuiux-sidebar-boxed adminuiux-header-transparent adminuiux-header-standard theme-cyan bg-gradient-5 scrollup"
      data-theme="theme-cyan"
      data-sidebarfill="adminuiux-sidebar-fill-white"
      data-sidebarlayout="adminuiux-sidebar-boxed"
      data-headerlayout="adminuiux-header-standard"
      data-headerfill="adminuiux-header-transparent"
      data-bggradient="bg-gradient-5">

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

    <!-- ── HEADER ─────────────────────────────────────────────────────────────── -->
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
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/logout.php"><i data-feather="log-out" class="me-2" style="width:15px;height:15px;"></i> Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>
    </header>

    <div class="adminuiux-wrap">

        <!-- ── SIDEBAR ────────────────────────────────────────────────────────── -->
        <div class="adminuiux-sidebar">
            <div class="adminuiux-sidebar-inner">
                <ul class="nav flex-column menu-active-line mt-0">
                    <li class="nav-item"><a class="nav-link" href="dashboard.php"><i data-feather="grid"></i><span>Dashboard</span></a></li>
                    <li class="nav-item menu-title"><span>USER MANAGEMENT</span></li>
                    <li class="nav-item"><a class="nav-link" href="users.php"><i data-feather="users"></i><span>All Users</span></a></li>
                    <li class="nav-item"><a class="nav-link" href="users.php?role=tenant"><i data-feather="user"></i><span>Tenants</span></a></li>
                    <li class="nav-item"><a class="nav-link" href="users.php?role=landlord"><i data-feather="home"></i><span>Landlords</span></a></li>
                    <li class="nav-item"><a class="nav-link" href="users.php?role=agent"><i data-feather="briefcase"></i><span>Agents</span></a></li>
                    <li class="nav-item menu-title"><span>AGENT MANAGEMENT</span></li>
                    <li class="nav-item"><a class="nav-link" href="agents.php"><i data-feather="user-check"></i><span>Agent Directory</span></a></li>
                    <li class="nav-item"><a class="nav-link" href="commissions.php"><i data-feather="dollar-sign"></i><span>Commissions</span></a></li>
                    <li class="nav-item menu-title"><span>PROPERTIES</span></li>
                    <li class="nav-item"><a class="nav-link" href="properties.php"><i data-feather="map-pin"></i><span>All Properties</span></a></li>
                    <li class="nav-item"><a class="nav-link" href="properties.php?status=pending"><i data-feather="clock"></i><span>Pending Approval</span></a></li>
                    <li class="nav-item"><a class="nav-link" href="categories.php"><i data-feather="tag"></i><span>Categories</span></a></li>
                    <li class="nav-item"><a class="nav-link" href="amenities.php"><i data-feather="star"></i><span>Amenities</span></a></li>
                    <li class="nav-item menu-title"><span>LOCATIONS</span></li>
                    <li class="nav-item"><a class="nav-link" href="locations.php?tab=countries"><i data-feather="globe"></i><span>Countries</span></a></li>
                    <li class="nav-item"><a class="nav-link" href="locations.php?tab=states"><i data-feather="map"></i><span>States</span></a></li>
                    <li class="nav-item"><a class="nav-link" href="locations.php?tab=cities"><i data-feather="map-pin"></i><span>Cities</span></a></li>
                    <li class="nav-item"><a class="nav-link" href="locations.php?tab=areas"><i data-feather="navigation"></i><span>Areas</span></a></li>
                    <li class="nav-item menu-title"><span>TRANSACTIONS</span></li>
                    <li class="nav-item"><a class="nav-link" href="bookings.php"><i data-feather="calendar"></i><span>Bookings</span></a></li>
                    <li class="nav-item"><a class="nav-link" href="payments.php"><i data-feather="credit-card"></i><span>Payments</span></a></li>
                    <li class="nav-item"><a class="nav-link" href="maintenance.php"><i data-feather="tool"></i><span>Maintenance</span></a></li>
                    <li class="nav-item menu-title"><span>REPORTS & SETTINGS</span></li>
                    <li class="nav-item"><a class="nav-link" href="reports.php"><i data-feather="bar-chart-2"></i><span>Reports</span></a></li>
                    <li class="nav-item"><a class="nav-link active" href="settings.php"><i data-feather="settings"></i><span>Settings</span></a></li>
                    <li class="nav-item"><a class="nav-link" href="activity-log.php"><i data-feather="activity"></i><span>Activity Log</span></a></li>
                    <li class="nav-item mt-2"><a class="nav-link text-danger" href="<?= BASE_URL ?>/logout.php"><i data-feather="log-out"></i><span>Logout</span></a></li>
                </ul>
                <div class="mt-auto"></div>
            </div>
        </div>

        <!-- ── MAIN CONTENT ───────────────────────────────────────────────────── -->
        <main class="adminuiux-content has-sidebar" onclick="contentClick()">
            <div class="container mt-2" id="main-content">

                <!-- Breadcrumb -->
                <div class="row mb-3">
                    <div class="col">
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb mb-1">
                                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Settings</li>
                            </ol>
                        </nav>
                        <h5 class="mb-0 fw-semibold">Platform Settings</h5>
                    </div>
                </div>

                <!-- Flash -->
                <?php if ($flash): ?>
                    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show mb-3">
                        <?= e($flash['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Settings Form -->
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

                    <!-- General Settings -->
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <h6 class="mb-3 border-bottom pb-2">General Settings</h6>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Site Name</label>
                                    <input type="text" name="site_name" class="form-control" 
                                           value="<?= e(getSettingValue('site_name', 'Rent Hob')) ?>" required>
                                    <small class="text-muted">The name of your platform</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Site Email</label>
                                    <input type="email" name="site_email" class="form-control" 
                                           value="<?= e(getSettingValue('site_email', 'info@renthob.com')) ?>" required>
                                    <small class="text-muted">Contact email for the platform</small>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Site Phone</label>
                                    <input type="text" name="site_phone" class="form-control" 
                                           value="<?= e(getSettingValue('site_phone', '08000000000')) ?>">
                                    <small class="text-muted">Contact phone number</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Site Address</label>
                                    <input type="text" name="site_address" class="form-control" 
                                           value="<?= e(getSettingValue('site_address', 'Nigeria')) ?>">
                                    <small class="text-muted">Physical address or location</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Currency Settings -->
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <h6 class="mb-3 border-bottom pb-2">Currency Settings</h6>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Currency Code</label>
                                    <select name="currency" class="form-select">
                                        <option value="NGN" <?= getSettingValue('currency', 'NGN') === 'NGN' ? 'selected' : '' ?>>NGN - Nigerian Naira</option>
                                        <option value="USD" <?= getSettingValue('currency') === 'USD' ? 'selected' : '' ?>>USD - US Dollar</option>
                                        <option value="GBP" <?= getSettingValue('currency') === 'GBP' ? 'selected' : '' ?>>GBP - British Pound</option>
                                        <option value="EUR" <?= getSettingValue('currency') === 'EUR' ? 'selected' : '' ?>>EUR - Euro</option>
                                        <option value="GHS" <?= getSettingValue('currency') === 'GHS' ? 'selected' : '' ?>>GHS - Ghanaian Cedi</option>
                                        <option value="ZAR" <?= getSettingValue('currency') === 'ZAR' ? 'selected' : '' ?>>ZAR - South African Rand</option>
                                    </select>
                                    <small class="text-muted">Platform currency</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Currency Symbol</label>
                                    <input type="text" name="currency_symbol" class="form-control" 
                                           value="<?= e(getSettingValue('currency_symbol', '₦')) ?>" maxlength="5">
                                    <small class="text-muted">Symbol to display (e.g., ₦, $, £)</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Settings -->
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <h6 class="mb-3 border-bottom pb-2">Payment Settings</h6>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Platform Fee (%)</label>
                                    <input type="number" name="platform_fee_percent" class="form-control" 
                                           value="<?= e(getSettingValue('platform_fee_percent', '5')) ?>" 
                                           min="0" max="100" step="0.1">
                                    <small class="text-muted">Percentage fee charged per transaction</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- System Settings -->
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <h6 class="mb-3 border-bottom pb-2">System Settings</h6>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label d-block">Maintenance Mode</label>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="maintenance_mode" 
                                               id="maintenanceMode" value="1" 
                                               <?= getSettingValue('maintenance_mode', '0') === '1' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="maintenanceMode">
                                            Enable Maintenance Mode
                                        </label>
                                    </div>
                                    <small class="text-muted">When enabled, only admins can access the platform</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Email Settings (Placeholder) -->
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <h6 class="mb-3 border-bottom pb-2">Email Settings</h6>
                            <p class="text-muted small mb-0">
                                <i data-feather="info" style="width:14px;height:14px;" class="me-1"></i>
                                Email configuration (SMTP settings) should be configured in your environment or configuration files for security.
                            </p>
                        </div>
                    </div>

                    <!-- System Info (Read-only) -->
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <h6 class="mb-3 border-bottom pb-2">System Information</h6>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted small">PHP Version</label>
                                    <p class="mb-0 fw-semibold"><?= phpversion() ?></p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted small">Database</label>
                                    <p class="mb-0 fw-semibold">MySQL (<?= DB_NAME ?>)</p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted small">Server Software</label>
                                    <p class="mb-0 fw-semibold"><?= $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' ?></p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted small">Base URL</label>
                                    <p class="mb-0 fw-semibold"><?= BASE_URL ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Save Button -->
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <p class="text-muted small mb-0">
                                    <i data-feather="alert-circle" style="width:14px;height:14px;" class="me-1"></i>
                                    Changes will take effect immediately
                                </p>
                                <button type="submit" class="btn btn-primary">
                                    <i data-feather="save" style="width:16px;height:16px;" class="me-1"></i>
                                    Save Settings
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

                <div class="row mt-3">
                    <div class="col text-center text-muted small py-2">
                        &copy; <?= date('Y') ?> Rent Hob. All rights reserved.
                    </div>
                </div>

            </div>
        </main>

    </div><!-- end adminuiux-wrap -->

</body>
</html>
