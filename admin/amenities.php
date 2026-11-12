<?php
/**
 * Admin - Property Amenities
 * Rent Hob
 */
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    $amenityId = (int)($_POST['amenity_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $icon = trim($_POST['icon'] ?? '');
    $status = $_POST['status'] ?? 'active';

    if (!in_array($status, ['active', 'inactive'], true)) {
        $status = 'active';
    }

    if ($action === 'create' || $action === 'update') {
        if ($name === '') {
            setFlash('error', 'Amenity name is required.');
        } elseif ($action === 'create') {
            $pdo->prepare('INSERT INTO amenities (name, icon, status) VALUES (?, ?, ?)')
                ->execute([$name, $icon ?: null, $status]);
            logActivity($pdo, currentUserId(), "Created amenity $name", 'amenities');
            setFlash('success', 'Amenity created.');
        } elseif ($amenityId > 0) {
            $pdo->prepare('UPDATE amenities SET name = ?, icon = ?, status = ? WHERE id = ?')
                ->execute([$name, $icon ?: null, $status, $amenityId]);
            logActivity($pdo, currentUserId(), "Updated amenity #$amenityId", 'amenities');
            setFlash('success', 'Amenity updated.');
        }
    } elseif ($amenityId > 0) {
        switch ($action) {
            case 'activate':
                $pdo->prepare('UPDATE amenities SET status = ? WHERE id = ?')->execute(['active', $amenityId]);
                setFlash('success', 'Amenity activated.');
                break;
            case 'deactivate':
                $pdo->prepare('UPDATE amenities SET status = ? WHERE id = ?')->execute(['inactive', $amenityId]);
                setFlash('success', 'Amenity deactivated.');
                break;
            case 'delete':
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM property_amenities WHERE amenity_id = ?');
                $stmt->execute([$amenityId]);
                if ((int)$stmt->fetchColumn() > 0) {
                    setFlash('error', 'This amenity is assigned to properties. Deactivate it instead.');
                } else {
                    $pdo->prepare('DELETE FROM amenities WHERE id = ?')->execute([$amenityId]);
                    logActivity($pdo, currentUserId(), "Deleted amenity #$amenityId", 'amenities');
                    setFlash('success', 'Amenity deleted.');
                }
                break;
        }
    }

    redirect(BASE_URL . '/admin/amenities.php');
}

$editId = (int)($_GET['edit'] ?? 0);
$editAmenity = null;
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM amenities WHERE id = ? LIMIT 1');
    $stmt->execute([$editId]);
    $editAmenity = $stmt->fetch();
}

$stmt = $pdo->query(
    'SELECT am.*, COUNT(pa.property_id) AS property_count
     FROM amenities am
     LEFT JOIN property_amenities pa ON pa.amenity_id = am.id
     GROUP BY am.id
     ORDER BY am.name'
);
$amenities = $stmt->fetchAll();
$activeCount = 0;
$inactiveCount = 0;
foreach ($amenities as $amenity) {
    if ($amenity['status'] === 'active') {
        $activeCount++;
    } else {
        $inactiveCount++;
    }
}

$pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM properties WHERE status = 'pending'")->fetchColumn();
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Amenities - Rent Hob Admin</title>
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/img/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Open+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root { --adminuiux-content-font: "Open Sans", sans-serif; --adminuiux-title-font: "Inter", sans-serif; }
    </style>
    <script defer src="<?= BASE_URL ?>/dashboard/assets/js/third-party.js"></script>
    <script defer src="<?= BASE_URL ?>/dashboard/assets/js/app.js"></script>
    <script defer src="<?= BASE_URL ?>/dashboard/assets/js/core.js"></script>
    <link href="<?= BASE_URL ?>/dashboard/assets/css/app.css" rel="stylesheet">
</head>
<body class="main-bg main-bg-blur main-bg-opac roundedui adminuiux-sidebar-standard adminuiux-sidebar-fill-white adminuiux-mobile-footer-fill-theme adminuiux-sidebar-boxed adminuiux-header-transparent adminuiux-header-standard theme-cyan bg-gradient-5 scrollup"
      data-theme="theme-cyan" data-sidebarfill="adminuiux-sidebar-fill-white" data-sidebarlayout="adminuiux-sidebar-boxed"
      data-headerlayout="adminuiux-header-standard" data-headerfill="adminuiux-header-transparent" data-bggradient="bg-gradient-5">
    <header class="adminuiux-header inner-page">
        <nav class="navbar navbar-expand-lg fixed-top">
            <div class="container-fluid">
                <button class="btn btn-link btn-square sidebar-toggler ms-1" type="button" onclick="initSidebar()"><i class="sidebar-svg" data-feather="menu"></i></button>
                <a class="navbar-brand" href="dashboard.php">
                    <img data-bs-img="light" src="<?= BASE_URL ?>/assets/img/logo-2.svg" alt="Rent Hob" style="max-height:32px;">
                    <img data-bs-img="dark" src="<?= BASE_URL ?>/assets/img/logo-2.svg" alt="Rent Hob" style="max-height:32px;">
                    <div class="lh-20"><span class="h4">Rent Hob</span><p class="company-tagline">Admin Panel</p></div>
                </a>
                <div class="ms-auto">
                    <a class="btn btn-link text-danger" href="<?= BASE_URL ?>/logout.php"><i data-feather="log-out" style="width:16px;height:16px;"></i></a>
                </div>
            </div>
        </nav>
    </header>

    <div class="adminuiux-wrap">
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
                    <li class="nav-item"><a class="nav-link" href="properties.php?status=pending"><i data-feather="clock"></i><span>Pending Approval</span><span class="badge bg-secondary ms-auto"><?= $pendingCount ?></span></a></li>
                    <li class="nav-item"><a class="nav-link" href="categories.php"><i data-feather="tag"></i><span>Categories</span></a></li>
                    <li class="nav-item"><a class="nav-link active" href="amenities.php"><i data-feather="star"></i><span>Amenities</span></a></li>
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
                    <li class="nav-item"><a class="nav-link" href="settings.php"><i data-feather="settings"></i><span>Settings</span></a></li>
                    <li class="nav-item"><a class="nav-link" href="activity-log.php"><i data-feather="activity"></i><span>Activity Log</span></a></li>
                    <li class="nav-item mt-2"><a class="nav-link text-danger" href="<?= BASE_URL ?>/logout.php"><i data-feather="log-out"></i><span>Logout</span></a></li>
                </ul>
            </div>
        </div>

        <main class="adminuiux-content has-sidebar" onclick="contentClick()">
            <div class="container mt-2" id="main-content">
                <div class="row mb-3">
                    <div class="col">
                        <nav aria-label="breadcrumb"><ol class="breadcrumb mb-1"><li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li><li class="breadcrumb-item active">Amenities</li></ol></nav>
                        <h5 class="mb-0 fw-semibold">Property Amenities</h5>
                    </div>
                </div>

                <?php if ($flash): ?>
                    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show mb-3">
                        <?= e($flash['message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row g-3">
                    <div class="col-lg-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <h6 class="fw-semibold mb-3"><?= $editAmenity ? 'Edit Amenity' : 'Add Amenity' ?></h6>
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                    <input type="hidden" name="action" value="<?= $editAmenity ? 'update' : 'create' ?>">
                                    <?php if ($editAmenity): ?><input type="hidden" name="amenity_id" value="<?= (int)$editAmenity['id'] ?>"><?php endif; ?>
                                    <div class="mb-3">
                                        <label class="form-label">Name</label>
                                        <input type="text" class="form-control" name="name" value="<?= e($editAmenity['name'] ?? '') ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Icon class</label>
                                        <input type="text" class="form-control" name="icon" placeholder="fa-wifi" value="<?= e($editAmenity['icon'] ?? '') ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Status</label>
                                        <select class="form-select" name="status">
                                            <option value="active" <?= ($editAmenity['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                                            <option value="inactive" <?= ($editAmenity['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                        </select>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-primary flex-fill" type="submit"><?= $editAmenity ? 'Save Changes' : 'Add Amenity' ?></button>
                                        <?php if ($editAmenity): ?><a href="amenities.php" class="btn btn-outline-secondary">Cancel</a><?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-8">
                        <div class="row g-3 mb-3">
                            <div class="col-sm-4"><div class="card border-0 shadow-sm"><div class="card-body"><p class="text-muted mb-1">Total</p><h4 class="mb-0"><?= count($amenities) ?></h4></div></div></div>
                            <div class="col-sm-4"><div class="card border-0 shadow-sm"><div class="card-body"><p class="text-muted mb-1">Active</p><h4 class="mb-0"><?= $activeCount ?></h4></div></div></div>
                            <div class="col-sm-4"><div class="card border-0 shadow-sm"><div class="card-body"><p class="text-muted mb-1">Inactive</p><h4 class="mb-0"><?= $inactiveCount ?></h4></div></div></div>
                        </div>
                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light"><tr><th class="ps-3">Amenity</th><th>Properties</th><th>Status</th><th class="text-end pe-3">Actions</th></tr></thead>
                                        <tbody>
                                            <?php if (!$amenities): ?>
                                                <tr><td colspan="4" class="text-center text-muted py-5">No amenities found.</td></tr>
                                            <?php endif; ?>
                                            <?php foreach ($amenities as $amenity): ?>
                                                <tr>
                                                    <td class="ps-3"><p class="mb-0 fw-semibold"><?= e($amenity['name']) ?></p><p class="mb-0 small text-muted"><?= e($amenity['icon'] ?: 'No icon') ?></p></td>
                                                    <td><span class="badge bg-light text-dark border"><?= (int)$amenity['property_count'] ?></span></td>
                                                    <td><?= statusBadge($amenity['status']) ?></td>
                                                    <td class="text-end pe-3">
                                                        <div class="d-flex justify-content-end gap-1">
                                                            <a href="amenities.php?edit=<?= (int)$amenity['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                                                            <form method="POST">
                                                                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                                                <input type="hidden" name="amenity_id" value="<?= (int)$amenity['id'] ?>">
                                                                <input type="hidden" name="action" value="<?= $amenity['status'] === 'active' ? 'deactivate' : 'activate' ?>">
                                                                <button class="btn btn-sm btn-outline-primary" type="submit"><?= $amenity['status'] === 'active' ? 'Deactivate' : 'Activate' ?></button>
                                                            </form>
                                                            <form method="POST" onsubmit="return confirm('Delete this amenity?')">
                                                                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                                                <input type="hidden" name="amenity_id" value="<?= (int)$amenity['id'] ?>">
                                                                <input type="hidden" name="action" value="delete">
                                                                <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
