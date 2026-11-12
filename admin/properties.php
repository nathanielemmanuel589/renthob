<?php
/**
 * Admin - Property Management
 * Rent Hob
 */
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('admin');

$validStatuses = ['pending', 'active', 'rejected', 'suspended'];
$validTypes = ['rent', 'sale', 'both'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    $propertyId = (int)($_POST['property_id'] ?? 0);

    if ($propertyId > 0) {
        switch ($action) {
            case 'activate':
                $pdo->prepare('UPDATE properties SET status = ? WHERE id = ?')->execute(['active', $propertyId]);
                logActivity($pdo, currentUserId(), "Activated property #$propertyId", 'properties');
                setFlash('success', 'Property approved and activated.');
                break;
            case 'reject':
                $pdo->prepare('UPDATE properties SET status = ? WHERE id = ?')->execute(['rejected', $propertyId]);
                logActivity($pdo, currentUserId(), "Rejected property #$propertyId", 'properties');
                setFlash('success', 'Property rejected.');
                break;
            case 'suspend':
                $pdo->prepare('UPDATE properties SET status = ? WHERE id = ?')->execute(['suspended', $propertyId]);
                logActivity($pdo, currentUserId(), "Suspended property #$propertyId", 'properties');
                setFlash('success', 'Property suspended.');
                break;
            case 'feature':
                $pdo->prepare('UPDATE properties SET featured = 1 WHERE id = ?')->execute([$propertyId]);
                logActivity($pdo, currentUserId(), "Featured property #$propertyId", 'properties');
                setFlash('success', 'Property marked as featured.');
                break;
            case 'unfeature':
                $pdo->prepare('UPDATE properties SET featured = 0 WHERE id = ?')->execute([$propertyId]);
                logActivity($pdo, currentUserId(), "Removed featured flag from property #$propertyId", 'properties');
                setFlash('success', 'Property removed from featured.');
                break;
            case 'delete':
                $pdo->prepare('DELETE FROM properties WHERE id = ?')->execute([$propertyId]);
                logActivity($pdo, currentUserId(), "Deleted property #$propertyId", 'properties');
                setFlash('success', 'Property deleted.');
                break;
            default:
                setFlash('error', 'Invalid property action.');
                break;
        }
    } else {
        setFlash('error', 'Invalid property selected.');
    }

    redirect(BASE_URL . '/admin/properties.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
}

$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$type = $_GET['type'] ?? '';
$categoryId = (int)($_GET['category_id'] ?? 0);
$landlordId = (int)($_GET['landlord'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

$where = ['1=1'];
$params = [];

if ($search !== '') {
    $where[] = '(p.title LIKE ? OR p.address LIKE ? OR p.slug LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)';
    $term = '%' . $search . '%';
    $params = array_merge($params, [$term, $term, $term, $term, $term]);
}

if ($status && in_array($status, $validStatuses, true)) {
    $where[] = 'p.status = ?';
    $params[] = $status;
}

if ($type && in_array($type, $validTypes, true)) {
    $where[] = 'p.type = ?';
    $params[] = $type;
}

if ($categoryId > 0) {
    $where[] = 'p.category_id = ?';
    $params[] = $categoryId;
}

if ($landlordId > 0) {
    $where[] = 'p.landlord_id = ?';
    $params[] = $landlordId;
}

$whereSQL = implode(' AND ', $where);

$stmtCount = $pdo->prepare(
    "SELECT COUNT(*)
     FROM properties p
     JOIN users u ON u.id = p.landlord_id
     WHERE $whereSQL"
);
$stmtCount->execute($params);
$total = (int)$stmtCount->fetchColumn();
$pager = paginate($total, $perPage, $page);

$stmt = $pdo->prepare(
    "SELECT
        p.id, p.title, p.slug, p.type, p.price, p.bedrooms, p.bathrooms, p.toilets,
        p.garage, p.area_size, p.address, p.status, p.featured, p.created_at,
        c.name AS category_name,
        a.name AS area_name,
        ci.name AS city_name,
        s.name AS state_name,
        co.name AS country_name,
        u.first_name AS landlord_first_name,
        u.last_name AS landlord_last_name,
        ag.first_name AS agent_first_name,
        ag.last_name AS agent_last_name,
        img.image_path AS cover_image
     FROM properties p
     JOIN users u ON u.id = p.landlord_id
     LEFT JOIN users ag ON ag.id = p.agent_id
     JOIN categories c ON c.id = p.category_id
     JOIN areas a ON a.id = p.area_id
     JOIN cities ci ON ci.id = a.city_id
     JOIN states s ON s.id = ci.state_id
     JOIN countries co ON co.id = s.country_id
     LEFT JOIN (
        SELECT pi.property_id, pi.image_path
        FROM property_images pi
        INNER JOIN (
            SELECT property_id, MIN(id) AS first_id
            FROM property_images
            GROUP BY property_id
        ) pick ON pick.property_id = pi.property_id AND pi.id = pick.first_id
     ) img ON img.property_id = p.id
     WHERE $whereSQL
     ORDER BY p.created_at DESC
     LIMIT ? OFFSET ?"
);
$stmt->execute(array_merge($params, [$perPage, $pager['offset']]));
$properties = $stmt->fetchAll();

$categories = $pdo->query(
    "SELECT id, name FROM categories WHERE status = 'active' ORDER BY name"
)->fetchAll();

$statusCounts = $pdo->query(
    "SELECT status, COUNT(*) AS total FROM properties GROUP BY status"
)->fetchAll(PDO::FETCH_KEY_PAIR);

$totalProperties = array_sum($statusCounts);
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Properties - Rent Hob Admin</title>
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
        .property-thumb {
            width: 68px;
            height: 52px;
            object-fit: cover;
            border-radius: 6px;
            background: #f3f4f6;
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
                <button class="btn btn-link btn-square sidebar-toggler ms-1" type="button" onclick="initSidebar()">
                    <i class="sidebar-svg" data-feather="menu"></i>
                </button>
                <a class="navbar-brand" href="dashboard.php">
                    <img data-bs-img="light" src="<?= BASE_URL ?>/assets/img/logo-2.svg" alt="Rent Hob" style="max-height:32px;">
                    <img data-bs-img="dark" src="<?= BASE_URL ?>/assets/img/logo-2.svg" alt="Rent Hob" style="max-height:32px;">
                    <div class="lh-20">
                        <span class="h4">Rent Hob</span>
                        <p class="company-tagline">Admin Panel</p>
                    </div>
                </a>
                <div class="ms-auto d-flex align-items-center gap-1">
                    <div class="dropdown">
                        <button class="btn btn-link no-caret dropdown-toggle d-flex align-items-center gap-2 px-2" type="button" data-bs-toggle="dropdown">
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
                    <li class="nav-item"><a class="nav-link active" href="properties.php"><i data-feather="map-pin"></i><span>All Properties</span></a></li>
                    <li class="nav-item"><a class="nav-link" href="properties.php?status=pending"><i data-feather="clock"></i><span>Pending Approval</span><span class="badge bg-secondary ms-auto"><?= $statusCounts['pending'] ?? 0 ?></span></a></li>
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
                    <li class="nav-item"><a class="nav-link" href="settings.php"><i data-feather="settings"></i><span>Settings</span></a></li>
                    <li class="nav-item"><a class="nav-link" href="activity-log.php"><i data-feather="activity"></i><span>Activity Log</span></a></li>
                    <li class="nav-item mt-2"><a class="nav-link text-danger" href="<?= BASE_URL ?>/logout.php"><i data-feather="log-out"></i><span>Logout</span></a></li>
                </ul>
                <div class="mt-auto"></div>
            </div>
        </div>

        <main class="adminuiux-content has-sidebar" onclick="contentClick()">
            <div class="container mt-2" id="main-content">
                <div class="row mb-3">
                    <div class="col">
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb mb-1">
                                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Properties</li>
                            </ol>
                        </nav>
                        <h5 class="mb-0 fw-semibold">Property Management</h5>
                    </div>
                </div>

                <?php if ($flash): ?>
                    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show mb-3">
                        <?= e($flash['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body p-0">
                        <ul class="nav nav-tabs border-0 px-3 pt-3">
                            <li class="nav-item">
                                <a class="nav-link <?= !$status ? 'active' : '' ?>" href="properties.php<?= $search ? '?search=' . urlencode($search) : '' ?>">
                                    All <span class="badge bg-secondary ms-1"><?= $totalProperties ?></span>
                                </a>
                            </li>
                            <?php foreach ($validStatuses as $s): ?>
                                <li class="nav-item">
                                    <a class="nav-link <?= $status === $s ? 'active' : '' ?>" href="properties.php?status=<?= $s ?><?= $search ? '&search=' . urlencode($search) : '' ?>">
                                        <?= ucfirst($s) ?> <span class="badge bg-secondary ms-1"><?= $statusCounts[$s] ?? 0 ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                        <div class="p-3 border-top">
                            <form method="GET" action="properties.php" class="row g-2">
                                <?php if ($landlordId > 0): ?>
                                    <input type="hidden" name="landlord" value="<?= $landlordId ?>">
                                <?php endif; ?>
                                <div class="col-lg-4 col-md-6">
                                    <div class="input-group">
                                        <span class="input-group-text"><i data-feather="search" style="width:14px;height:14px;"></i></span>
                                        <input type="text" class="form-control" name="search" placeholder="Search title, address, landlord..." value="<?= e($search) ?>">
                                    </div>
                                </div>
                                <div class="col-lg-2 col-md-3">
                                    <select class="form-select" name="status">
                                        <option value="">All Statuses</option>
                                        <?php foreach ($validStatuses as $s): ?>
                                            <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-lg-2 col-md-3">
                                    <select class="form-select" name="type">
                                        <option value="">All Types</option>
                                        <?php foreach ($validTypes as $t): ?>
                                            <option value="<?= $t ?>" <?= $type === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-lg-2 col-md-6">
                                    <select class="form-select" name="category_id">
                                        <option value="">All Categories</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?= (int)$category['id'] ?>" <?= $categoryId === (int)$category['id'] ? 'selected' : '' ?>>
                                                <?= e($category['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-lg-2 col-md-6 d-flex gap-2">
                                    <button type="submit" class="btn btn-primary flex-fill">Filter</button>
                                    <a href="properties.php" class="btn btn-outline-secondary">Reset</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">#</th>
                                        <th>Property</th>
                                        <th>Owner</th>
                                        <th>Category</th>
                                        <th>Price</th>
                                        <th>Location</th>
                                        <th>Status</th>
                                        <th>Featured</th>
                                        <th class="text-end pe-3">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($properties)): ?>
                                        <tr>
                                            <td colspan="9" class="text-center text-muted py-5">
                                                <i data-feather="home" style="width:40px;height:40px;opacity:.3;" class="d-block mx-auto mb-2"></i>
                                                No properties found.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($properties as $i => $property): ?>
                                            <?php
                                            $cover = $property['cover_image']
                                                ? BASE_URL . '/' . ltrim($property['cover_image'], '/')
                                                : BASE_URL . '/assets/img/property-img-1.jpg';
                                            $landlordName = trim($property['landlord_first_name'] . ' ' . $property['landlord_last_name']);
                                            $agentName = trim(($property['agent_first_name'] ?? '') . ' ' . ($property['agent_last_name'] ?? ''));
                                            ?>
                                            <tr>
                                                <td class="ps-3 text-muted small"><?= $pager['offset'] + $i + 1 ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <img src="<?= e($cover) ?>" class="property-thumb" alt="">
                                                        <div>
                                                            <p class="mb-0 small fw-semibold"><?= e($property['title']) ?></p>
                                                            <p class="mb-0 text-muted" style="font-size:.75rem;">
                                                                <?= ucfirst(e($property['type'])) ?> -
                                                                <?= (int)$property['bedrooms'] ?> bed,
                                                                <?= (int)$property['bathrooms'] ?> bath
                                                            </p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <p class="mb-0 small fw-semibold"><?= e($landlordName) ?></p>
                                                    <p class="mb-0 text-muted" style="font-size:.75rem;"><?= $agentName ? 'Agent: ' . e($agentName) : 'No agent assigned' ?></p>
                                                </td>
                                                <td class="small"><?= e($property['category_name']) ?></td>
                                                <td class="small fw-semibold"><?= formatCurrency((float)$property['price']) ?></td>
                                                <td class="small text-muted">
                                                    <?= e($property['area_name']) ?>, <?= e($property['city_name']) ?>
                                                </td>
                                                <td><?= statusBadge($property['status']) ?></td>
                                                <td>
                                                    <?php if ((int)$property['featured'] === 1): ?>
                                                        <span class="badge bg-primary">Yes</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">No</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end pe-3">
                                                    <div class="dropdown">
                                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                            Actions
                                                        </button>
                                                        <ul class="dropdown-menu dropdown-menu-end">
                                                            <li><a class="dropdown-item" href="property-view.php?id=<?= (int)$property['id'] ?>"><i data-feather="eye" class="me-2" style="width:14px;height:14px;"></i>View</a></li>
                                                            <li><hr class="dropdown-divider"></li>
                                                            <?php if ($property['status'] !== 'active'): ?>
                                                                <li>
                                                                    <form method="POST" class="d-inline">
                                                                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                                                        <input type="hidden" name="property_id" value="<?= (int)$property['id'] ?>">
                                                                        <input type="hidden" name="action" value="activate">
                                                                        <button class="dropdown-item text-success" type="submit"><i data-feather="check-circle" class="me-2" style="width:14px;height:14px;"></i>Approve</button>
                                                                    </form>
                                                                </li>
                                                            <?php endif; ?>
                                                            <?php if ($property['status'] !== 'rejected'): ?>
                                                                <li>
                                                                    <form method="POST" class="d-inline">
                                                                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                                                        <input type="hidden" name="property_id" value="<?= (int)$property['id'] ?>">
                                                                        <input type="hidden" name="action" value="reject">
                                                                        <button class="dropdown-item text-warning" type="submit"><i data-feather="x-circle" class="me-2" style="width:14px;height:14px;"></i>Reject</button>
                                                                    </form>
                                                                </li>
                                                            <?php endif; ?>
                                                            <?php if ($property['status'] !== 'suspended'): ?>
                                                                <li>
                                                                    <form method="POST" class="d-inline">
                                                                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                                                        <input type="hidden" name="property_id" value="<?= (int)$property['id'] ?>">
                                                                        <input type="hidden" name="action" value="suspend">
                                                                        <button class="dropdown-item text-warning" type="submit"><i data-feather="slash" class="me-2" style="width:14px;height:14px;"></i>Suspend</button>
                                                                    </form>
                                                                </li>
                                                            <?php endif; ?>
                                                            <li>
                                                                <form method="POST" class="d-inline">
                                                                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                                                    <input type="hidden" name="property_id" value="<?= (int)$property['id'] ?>">
                                                                    <input type="hidden" name="action" value="<?= (int)$property['featured'] === 1 ? 'unfeature' : 'feature' ?>">
                                                                    <button class="dropdown-item" type="submit">
                                                                        <i data-feather="star" class="me-2" style="width:14px;height:14px;"></i><?= (int)$property['featured'] === 1 ? 'Unfeature' : 'Feature' ?>
                                                                    </button>
                                                                </form>
                                                            </li>
                                                            <li><hr class="dropdown-divider"></li>
                                                            <li>
                                                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this property? This cannot be undone.')">
                                                                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                                                    <input type="hidden" name="property_id" value="<?= (int)$property['id'] ?>">
                                                                    <input type="hidden" name="action" value="delete">
                                                                    <button class="dropdown-item text-danger" type="submit"><i data-feather="trash-2" class="me-2" style="width:14px;height:14px;"></i>Delete</button>
                                                                </form>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($pager['total_pages'] > 1): ?>
                            <div class="d-flex justify-content-between align-items-center px-3 py-3 border-top">
                                <p class="mb-0 small text-muted">
                                    Showing <?= $pager['offset'] + 1 ?>-<?= min($pager['offset'] + $perPage, $total) ?> of <?= $total ?> properties
                                </p>
                                <nav>
                                    <ul class="pagination pagination-sm mb-0">
                                        <li class="page-item <?= !$pager['has_prev'] ? 'disabled' : '' ?>">
                                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                                <i data-feather="chevron-left" style="width:14px;height:14px;"></i>
                                            </a>
                                        </li>
                                        <?php for ($p = 1; $p <= $pager['total_pages']; $p++): ?>
                                            <?php if ($p <= 2 || $p >= $pager['total_pages'] - 1 || abs($p - $page) <= 1): ?>
                                                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"><?= $p ?></a>
                                                </li>
                                            <?php elseif (abs($p - $page) === 2): ?>
                                                <li class="page-item disabled"><span class="page-link">...</span></li>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                        <li class="page-item <?= !$pager['has_next'] ? 'disabled' : '' ?>">
                                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                                <i data-feather="chevron-right" style="width:14px;height:14px;"></i>
                                            </a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                        <?php else: ?>
                            <div class="px-3 py-2 border-top">
                                <p class="mb-0 small text-muted"><?= $total ?> propert<?= $total === 1 ? 'y' : 'ies' ?> found</p>
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
            <div class="col-auto"><a href="properties.php" class="nav-link active text-center py-2 px-3"><i data-feather="home" style="width:20px;height:20px;"></i><p class="mb-0" style="font-size:.65rem;">Properties</p></a></div>
            <div class="col-auto"><a href="settings.php" class="nav-link text-center py-2 px-3"><i data-feather="settings" style="width:20px;height:20px;"></i><p class="mb-0" style="font-size:.65rem;">Settings</p></a></div>
            <div class="col-auto"><a href="<?= BASE_URL ?>/logout.php" class="nav-link text-center py-2 px-3"><i data-feather="log-out" style="width:20px;height:20px;"></i><p class="mb-0" style="font-size:.65rem;">Logout</p></a></div>
        </div>
    </footer>
</body>
</html>
