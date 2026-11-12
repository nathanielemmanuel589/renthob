<?php
/**
 * Admin — Locations Management (Countries, States, Cities, Areas)
 * Rent Hob
 */
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('admin');

$tab = $_GET['tab'] ?? 'countries';
$validTabs = ['countries', 'states', 'cities', 'areas'];
if (!in_array($tab, $validTabs)) {
    $tab = 'countries';
}

// ── Handle POST actions ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    
    // COUNTRIES
    if ($action === 'add_country') {
        $name = trim($_POST['name'] ?? '');
        $code = strtoupper(trim($_POST['code'] ?? ''));
        if ($name && $code) {
            $pdo->prepare('INSERT INTO countries (name, code, status) VALUES (?, ?, ?)')
                ->execute([$name, $code, 'active']);
            logActivity($pdo, currentUserId(), "Added country: $name ($code)", 'locations');
            setFlash('success', 'Country added successfully.');
        }
    }
    elseif ($action === 'edit_country') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $status = $_POST['status'] ?? 'active';
        if ($id && $name && $code) {
            $pdo->prepare('UPDATE countries SET name=?, code=?, status=? WHERE id=?')
                ->execute([$name, $code, $status, $id]);
            logActivity($pdo, currentUserId(), "Updated country #$id", 'locations');
            setFlash('success', 'Country updated.');
        }
    }
    elseif ($action === 'delete_country') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $pdo->prepare('DELETE FROM countries WHERE id=?')->execute([$id]);
            logActivity($pdo, currentUserId(), "Deleted country #$id", 'locations');
            setFlash('success', 'Country deleted.');
        }
    }
    
    // STATES
    elseif ($action === 'add_state') {
        $countryId = (int)($_POST['country_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($countryId && $name) {
            $pdo->prepare('INSERT INTO states (country_id, name, status) VALUES (?, ?, ?)')
                ->execute([$countryId, $name, 'active']);
            logActivity($pdo, currentUserId(), "Added state: $name", 'locations');
            setFlash('success', 'State added successfully.');
        }
    }
    elseif ($action === 'edit_state') {
        $id = (int)($_POST['id'] ?? 0);
        $countryId = (int)($_POST['country_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $status = $_POST['status'] ?? 'active';
        if ($id && $countryId && $name) {
            $pdo->prepare('UPDATE states SET country_id=?, name=?, status=? WHERE id=?')
                ->execute([$countryId, $name, $status, $id]);
            logActivity($pdo, currentUserId(), "Updated state #$id", 'locations');
            setFlash('success', 'State updated.');
        }
    }
    elseif ($action === 'delete_state') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $pdo->prepare('DELETE FROM states WHERE id=?')->execute([$id]);
            logActivity($pdo, currentUserId(), "Deleted state #$id", 'locations');
            setFlash('success', 'State deleted.');
        }
    }
    
    // CITIES
    elseif ($action === 'add_city') {
        $stateId = (int)($_POST['state_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($stateId && $name) {
            $pdo->prepare('INSERT INTO cities (state_id, name, status) VALUES (?, ?, ?)')
                ->execute([$stateId, $name, 'active']);
            logActivity($pdo, currentUserId(), "Added city: $name", 'locations');
            setFlash('success', 'City added successfully.');
        }
    }
    elseif ($action === 'edit_city') {
        $id = (int)($_POST['id'] ?? 0);
        $stateId = (int)($_POST['state_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $status = $_POST['status'] ?? 'active';
        if ($id && $stateId && $name) {
            $pdo->prepare('UPDATE cities SET state_id=?, name=?, status=? WHERE id=?')
                ->execute([$stateId, $name, $status, $id]);
            logActivity($pdo, currentUserId(), "Updated city #$id", 'locations');
            setFlash('success', 'City updated.');
        }
    }
    elseif ($action === 'delete_city') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $pdo->prepare('DELETE FROM cities WHERE id=?')->execute([$id]);
            logActivity($pdo, currentUserId(), "Deleted city #$id", 'locations');
            setFlash('success', 'City deleted.');
        }
    }
    
    // AREAS
    elseif ($action === 'add_area') {
        $cityId = (int)($_POST['city_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($cityId && $name) {
            $pdo->prepare('INSERT INTO areas (city_id, name, status) VALUES (?, ?, ?)')
                ->execute([$cityId, $name, 'active']);
            logActivity($pdo, currentUserId(), "Added area: $name", 'locations');
            setFlash('success', 'Area added successfully.');
        }
    }
    elseif ($action === 'edit_area') {
        $id = (int)($_POST['id'] ?? 0);
        $cityId = (int)($_POST['city_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $status = $_POST['status'] ?? 'active';
        if ($id && $cityId && $name) {
            $pdo->prepare('UPDATE areas SET city_id=?, name=?, status=? WHERE id=?')
                ->execute([$cityId, $name, $status, $id]);
            logActivity($pdo, currentUserId(), "Updated area #$id", 'locations');
            setFlash('success', 'Area updated.');
        }
    }
    elseif ($action === 'delete_area') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $pdo->prepare('DELETE FROM areas WHERE id=?')->execute([$id]);
            logActivity($pdo, currentUserId(), "Deleted area #$id", 'locations');
            setFlash('success', 'Area deleted.');
        }
    }
    
    redirect(BASE_URL . '/admin/locations.php?tab=' . $tab);
}

// ── Fetch data based on active tab ────────────────────────────────────────────
$countries = $pdo->query('SELECT * FROM countries ORDER BY name')->fetchAll();
$states = $pdo->query('SELECT s.*, c.name as country_name FROM states s JOIN countries c ON s.country_id=c.id ORDER BY c.name, s.name')->fetchAll();
$cities = $pdo->query('SELECT ci.*, s.name as state_name, co.name as country_name FROM cities ci JOIN states s ON ci.state_id=s.id JOIN countries co ON s.country_id=co.id ORDER BY co.name, s.name, ci.name')->fetchAll();
$areas = $pdo->query('SELECT a.*, ci.name as city_name, s.name as state_name FROM areas a JOIN cities ci ON a.city_id=ci.id JOIN states s ON ci.state_id=s.id ORDER BY s.name, ci.name, a.name')->fetchAll();

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Locations — Rent Hob Admin</title>
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
      data-bggradient="bg-gradient-5"
      data-bs-spy="scroll" data-bs-target="#list-example"
      data-bs-smooth-scroll="true" tabindex="0">

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
                    <li class="nav-item"><a class="nav-link <?= $tab === 'countries' ? 'active' : '' ?>" href="locations.php?tab=countries"><i data-feather="globe"></i><span>Countries</span></a></li>
                    <li class="nav-item"><a class="nav-link <?= $tab === 'states' ? 'active' : '' ?>" href="locations.php?tab=states"><i data-feather="map"></i><span>States</span></a></li>
                    <li class="nav-item"><a class="nav-link <?= $tab === 'cities' ? 'active' : '' ?>" href="locations.php?tab=cities"><i data-feather="map-pin"></i><span>Cities</span></a></li>
                    <li class="nav-item"><a class="nav-link <?= $tab === 'areas' ? 'active' : '' ?>" href="locations.php?tab=areas"><i data-feather="navigation"></i><span>Areas</span></a></li>
                    <li class="nav-item menu-title"><span>TRANSACTIONS</span></li>
                    <li class="nav-item"><a class="nav-link" href="bookings.php"><i data-feather="calendar"></i><span>Bookings</span></a></li>
                    <li class="nav-item"><a class="nav-link" href="payments.php"><i data-feather="credit-card"></i><span>Payments</span></a></li>
                    <li class="nav-item"><a class="nav-link" href="maintenance.php"><i data-feather="tool"></i><span>Maintenance</span></a></li>
                    <li class="nav-item menu-title"><span>REPORTS & SETTINGS</span></li>
                    <li class="nav-item"><a class="nav-link" href="reports.php"><i data-feather="bar-chart-2"></i><span>Reports</span></a></li>
                    <li class="nav-item"><a class="nav-link" href="settings.php"><i data-feather="settings"></i><span>Settings</span></a></li>
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
                                <li class="breadcrumb-item active">Locations</li>
                            </ol>
                        </nav>
                        <h5 class="mb-0 fw-semibold">Location Management</h5>
                    </div>
                </div>

                <!-- Flash -->
                <?php if ($flash): ?>
                    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show mb-3">
                        <?= e($flash['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Tabs -->
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body p-0">
                        <ul class="nav nav-tabs border-0 px-3 pt-3">
                            <li class="nav-item">
                                <a class="nav-link <?= $tab === 'countries' ? 'active' : '' ?>" href="?tab=countries">
                                    <i data-feather="globe" style="width:16px;height:16px;"></i> Countries
                                    <span class="badge bg-secondary ms-1"><?= count($countries) ?></span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?= $tab === 'states' ? 'active' : '' ?>" href="?tab=states">
                                    <i data-feather="map" style="width:16px;height:16px;"></i> States
                                    <span class="badge bg-secondary ms-1"><?= count($states) ?></span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?= $tab === 'cities' ? 'active' : '' ?>" href="?tab=cities">
                                    <i data-feather="map-pin" style="width:16px;height:16px;"></i> Cities
                                    <span class="badge bg-secondary ms-1"><?= count($cities) ?></span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?= $tab === 'areas' ? 'active' : '' ?>" href="?tab=areas">
                                    <i data-feather="navigation" style="width:16px;height:16px;"></i> Areas
                                    <span class="badge bg-secondary ms-1"><?= count($areas) ?></span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- ══════════════════════════════════════════════════════════════ -->
                <!-- COUNTRIES TAB -->
                <!-- ══════════════════════════════════════════════════════════════ -->
                <?php if ($tab === 'countries'): ?>
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0">Manage Countries</h6>
                                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCountryModal">
                                    <i data-feather="plus" style="width:14px;height:14px;"></i> Add Country
                                </button>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Country</th>
                                            <th>Code</th>
                                            <th>Status</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($countries)): ?>
                                            <tr><td colspan="5" class="text-center text-muted py-4">No countries yet.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($countries as $i => $c): ?>
                                                <tr>
                                                    <td><?= $i + 1 ?></td>
                                                    <td class="fw-semibold"><?= e($c['name']) ?></td>
                                                    <td><span class="badge bg-secondary"><?= e($c['code']) ?></span></td>
                                                    <td><?= statusBadge($c['status']) ?></td>
                                                    <td class="text-end">
                                                        <button class="btn btn-sm btn-outline-primary" onclick="editCountry(<?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>)">Edit</button>
                                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this country?')">
                                                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                                            <input type="hidden" name="action" value="delete_country">
                                                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- ══════════════════════════════════════════════════════════════ -->
                <!-- STATES TAB -->
                <!-- ══════════════════════════════════════════════════════════════ -->
                <?php if ($tab === 'states'): ?>
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0">Manage States</h6>
                                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addStateModal">
                                    <i data-feather="plus" style="width:14px;height:14px;"></i> Add State
                                </button>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>State</th>
                                            <th>Country</th>
                                            <th>Status</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($states)): ?>
                                            <tr><td colspan="5" class="text-center text-muted py-4">No states yet.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($states as $i => $s): ?>
                                                <tr>
                                                    <td><?= $i + 1 ?></td>
                                                    <td class="fw-semibold"><?= e($s['name']) ?></td>
                                                    <td><?= e($s['country_name']) ?></td>
                                                    <td><?= statusBadge($s['status']) ?></td>
                                                    <td class="text-end">
                                                        <button class="btn btn-sm btn-outline-primary" onclick="editState(<?= htmlspecialchars(json_encode($s), ENT_QUOTES) ?>)">Edit</button>
                                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this state?')">
                                                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                                            <input type="hidden" name="action" value="delete_state">
                                                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- ══════════════════════════════════════════════════════════════ -->
                <!-- CITIES TAB -->
                <!-- ══════════════════════════════════════════════════════════════ -->
                <?php if ($tab === 'cities'): ?>
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0">Manage Cities</h6>
                                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCityModal">
                                    <i data-feather="plus" style="width:14px;height:14px;"></i> Add City
                                </button>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>City</th>
                                            <th>State</th>
                                            <th>Country</th>
                                            <th>Status</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($cities)): ?>
                                            <tr><td colspan="6" class="text-center text-muted py-4">No cities yet.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($cities as $i => $c): ?>
                                                <tr>
                                                    <td><?= $i + 1 ?></td>
                                                    <td class="fw-semibold"><?= e($c['name']) ?></td>
                                                    <td><?= e($c['state_name']) ?></td>
                                                    <td><?= e($c['country_name']) ?></td>
                                                    <td><?= statusBadge($c['status']) ?></td>
                                                    <td class="text-end">
                                                        <button class="btn btn-sm btn-outline-primary" onclick="editCity(<?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>)">Edit</button>
                                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this city?')">
                                                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                                            <input type="hidden" name="action" value="delete_city">
                                                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- ══════════════════════════════════════════════════════════════ -->
                <!-- AREAS TAB -->
                <!-- ══════════════════════════════════════════════════════════════ -->
                <?php if ($tab === 'areas'): ?>
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0">Manage Areas</h6>
                                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addAreaModal">
                                    <i data-feather="plus" style="width:14px;height:14px;"></i> Add Area
                                </button>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Area</th>
                                            <th>City</th>
                                            <th>State</th>
                                            <th>Status</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($areas)): ?>
                                            <tr><td colspan="6" class="text-center text-muted py-4">No areas yet.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($areas as $i => $a): ?>
                                                <tr>
                                                    <td><?= $i + 1 ?></td>
                                                    <td class="fw-semibold"><?= e($a['name']) ?></td>
                                                    <td><?= e($a['city_name']) ?></td>
                                                    <td><?= e($a['state_name']) ?></td>
                                                    <td><?= statusBadge($a['status']) ?></td>
                                                    <td class="text-end">
                                                        <button class="btn btn-sm btn-outline-primary" onclick="editArea(<?= htmlspecialchars(json_encode($a), ENT_QUOTES) ?>)">Edit</button>
                                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this area?')">
                                                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                                            <input type="hidden" name="action" value="delete_area">
                                                            <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="row mt-3">
                    <div class="col text-center text-muted small py-2">
                        &copy; <?= date('Y') ?> Rent Hob. All rights reserved.
                    </div>
                </div>

            </div>
        </main>

    </div><!-- end adminuiux-wrap -->

    <!-- ══════════════════════════════════════════════════════════════════════════ -->
    <!-- MODALS -->
    <!-- ══════════════════════════════════════════════════════════════════════════ -->

    <!-- Add Country Modal -->
    <div class="modal fade" id="addCountryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Add Country</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                        <input type="hidden" name="action" value="add_country">
                        <div class="mb-3">
                            <label class="form-label">Country Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Country Code</label>
                            <input type="text" name="code" class="form-control" placeholder="NG" maxlength="10" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Country</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Country Modal -->
    <div class="modal fade" id="editCountryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Country</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                        <input type="hidden" name="action" value="edit_country">
                        <input type="hidden" name="id" id="editCountryId">
                        <div class="mb-3">
                            <label class="form-label">Country Name</label>
                            <input type="text" name="name" id="editCountryName" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Country Code</label>
                            <input type="text" name="code" id="editCountryCode" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" id="editCountryStatus" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Country</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add State Modal -->
    <div class="modal fade" id="addStateModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Add State</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                        <input type="hidden" name="action" value="add_state">
                        <div class="mb-3">
                            <label class="form-label">Country</label>
                            <select name="country_id" class="form-select" required>
                                <option value="">Select Country</option>
                                <?php foreach ($countries as $co): ?>
                                    <option value="<?= $co['id'] ?>"><?= e($co['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">State Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add State</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit State Modal -->
    <div class="modal fade" id="editStateModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit State</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                        <input type="hidden" name="action" value="edit_state">
                        <input type="hidden" name="id" id="editStateId">
                        <div class="mb-3">
                            <label class="form-label">Country</label>
                            <select name="country_id" id="editStateCountryId" class="form-select" required>
                                <?php foreach ($countries as $co): ?>
                                    <option value="<?= $co['id'] ?>"><?= e($co['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">State Name</label>
                            <input type="text" name="name" id="editStateName" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" id="editStateStatus" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update State</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add City Modal -->
    <div class="modal fade" id="addCityModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Add City</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                        <input type="hidden" name="action" value="add_city">
                        <div class="mb-3">
                            <label class="form-label">State</label>
                            <select name="state_id" class="form-select" required>
                                <option value="">Select State</option>
                                <?php foreach ($states as $st): ?>
                                    <option value="<?= $st['id'] ?>"><?= e($st['name']) ?> (<?= e($st['country_name']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">City Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add City</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit City Modal -->
    <div class="modal fade" id="editCityModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit City</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                        <input type="hidden" name="action" value="edit_city">
                        <input type="hidden" name="id" id="editCityId">
                        <div class="mb-3">
                            <label class="form-label">State</label>
                            <select name="state_id" id="editCityStateId" class="form-select" required>
                                <?php foreach ($states as $st): ?>
                                    <option value="<?= $st['id'] ?>"><?= e($st['name']) ?> (<?= e($st['country_name']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">City Name</label>
                            <input type="text" name="name" id="editCityName" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" id="editCityStatus" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update City</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Area Modal -->
    <div class="modal fade" id="addAreaModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Add Area</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                        <input type="hidden" name="action" value="add_area">
                        <div class="mb-3">
                            <label class="form-label">City</label>
                            <select name="city_id" class="form-select" required>
                                <option value="">Select City</option>
                                <?php foreach ($cities as $ci): ?>
                                    <option value="<?= $ci['id'] ?>"><?= e($ci['name']) ?> (<?= e($ci['state_name']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Area Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Area</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Area Modal -->
    <div class="modal fade" id="editAreaModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Area</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                        <input type="hidden" name="action" value="edit_area">
                        <input type="hidden" name="id" id="editAreaId">
                        <div class="mb-3">
                            <label class="form-label">City</label>
                            <select name="city_id" id="editAreaCityId" class="form-select" required>
                                <?php foreach ($cities as $ci): ?>
                                    <option value="<?= $ci['id'] ?>"><?= e($ci['name']) ?> (<?= e($ci['state_name']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Area Name</label>
                            <input type="text" name="name" id="editAreaName" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" id="editAreaStatus" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Area</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    // Edit Country
    function editCountry(data) {
        document.getElementById('editCountryId').value = data.id;
        document.getElementById('editCountryName').value = data.name;
        document.getElementById('editCountryCode').value = data.code;
        document.getElementById('editCountryStatus').value = data.status;
        new bootstrap.Modal(document.getElementById('editCountryModal')).show();
    }

    // Edit State
    function editState(data) {
        document.getElementById('editStateId').value = data.id;
        document.getElementById('editStateCountryId').value = data.country_id;
        document.getElementById('editStateName').value = data.name;
        document.getElementById('editStateStatus').value = data.status;
        new bootstrap.Modal(document.getElementById('editStateModal')).show();
    }

    // Edit City
    function editCity(data) {
        document.getElementById('editCityId').value = data.id;
        document.getElementById('editCityStateId').value = data.state_id;
        document.getElementById('editCityName').value = data.name;
        document.getElementById('editCityStatus').value = data.status;
        new bootstrap.Modal(document.getElementById('editCityModal')).show();
    }

    // Edit Area
    function editArea(data) {
        document.getElementById('editAreaId').value = data.id;
        document.getElementById('editAreaCityId').value = data.city_id;
        document.getElementById('editAreaName').value = data.name;
        document.getElementById('editAreaStatus').value = data.status;
        new bootstrap.Modal(document.getElementById('editAreaModal')).show();
    }
    </script>

</body>
</html>
