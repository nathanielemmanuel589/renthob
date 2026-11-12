<?php
/**
 * Agent Properties - My Properties List
 * Rent Hob
 */
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/agent-functions.php';

requireRole('agent');

$agentId = currentUserId();

// Get filter
$statusFilter = isset($_GET['status']) ? $_GET['status'] : null;
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query
$sql = "SELECT p.*, ap.assigned_date, ap.commission_rate,
               c.name as category_name,
               a.name as area_name,
               ci.name as city_name,
               s.name as state_name,
               u.first_name as landlord_first, u.last_name as landlord_last,
               (SELECT image_path FROM property_images WHERE property_id = p.id ORDER BY id ASC LIMIT 1) as cover_image,
               (SELECT COUNT(*) FROM applications WHERE property_id = p.id) as application_count,
               (SELECT COUNT(*) FROM bookings WHERE property_id = p.id AND visit_date >= CURDATE() AND status != 'cancelled') as upcoming_viewings,
               (SELECT COUNT(*) FROM leases WHERE property_id = p.id AND status = 'active') as active_leases
        FROM properties p
        INNER JOIN agent_properties ap ON ap.property_id = p.id
        LEFT JOIN users u ON u.id = p.landlord_id
        LEFT JOIN categories c ON c.id = p.category_id
        LEFT JOIN areas a ON a.id = p.area_id
        LEFT JOIN cities ci ON ci.id = a.city_id
        LEFT JOIN states s ON s.id = ci.state_id
        WHERE ap.agent_id = ? AND ap.status = 'active'";

$params = [$agentId];

if ($statusFilter) {
    $sql .= " AND p.status = ?";
    $params[] = $statusFilter;
}

if ($searchQuery) {
    $sql .= " AND (p.title LIKE ? OR a.name LIKE ? OR ci.name LIKE ?)";
    $searchTerm = "%{$searchQuery}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$sql .= " ORDER BY ap.assigned_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$properties = $stmt->fetchAll();

// Count properties by status
$stmt = $pdo->prepare('
    SELECT p.status, COUNT(*) as count
    FROM properties p
    INNER JOIN agent_properties ap ON ap.property_id = p.id
    WHERE ap.agent_id = ? AND ap.status = "active"
    GROUP BY p.status
');
$stmt->execute([$agentId]);
$statusCounts = [];
while ($row = $stmt->fetch()) {
    $statusCounts[$row['status']] = $row['count'];
}

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>My Properties — Rent Hob Agent</title>
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
        .property-card { transition: transform .2s; }
        .property-card:hover { transform: translateY(-5px); }
        .property-image {
            height: 200px;
            object-fit: cover;
            width: 100%;
        }
    </style>
    <script defer src="<?= BASE_URL ?>/dashboard/assets/js/third-party.js"></script>
    <script defer src="<?= BASE_URL ?>/dashboard/assets/js/app.js"></script>
    <script defer src="<?= BASE_URL ?>/dashboard/assets/js/core.js"></script>
    <link href="<?= BASE_URL ?>/dashboard/assets/css/app.css" rel="stylesheet">
</head>

<body class="main-bg main-bg-blur main-bg-opac roundedui adminuiux-sidebar-standard adminuiux-sidebar-fill-white adminuiux-mobile-footer-fill-theme adminuiux-sidebar-boxed adminuiux-header-transparent adminuiux-header-standard theme-orange bg-gradient-4 scrollup"
      data-theme="theme-orange"
      data-sidebarfill="adminuiux-sidebar-fill-white"
      data-sidebarlayout="adminuiux-sidebar-boxed"
      data-headerlayout="adminuiux-header-standard"
      data-headerfill="adminuiux-header-transparent"
      data-bggradient="bg-gradient-4">

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
                        <p class="company-tagline">Agent Portal</p>
                    </div>
                </a>
                <div class="ms-auto d-flex align-items-center gap-1">
                    <div class="dropdown">
                        <button class="btn btn-link no-caret dropdown-toggle d-flex align-items-center gap-2 px-2"
                                type="button" data-bs-toggle="dropdown">
                            <img src="<?= userAvatar($_SESSION['user_avatar'] ?? null) ?>"
                                 alt="Agent" class="rounded-circle" width="34" height="34" style="object-fit:cover;">
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
        <?php include '../includes/agent-sidebar.php'; ?>

        <main class="adminuiux-content has-sidebar" onclick="contentClick()">
            <div class="container mt-2" id="main-content">

                <!-- Page Header -->
                <div class="row mb-3">
                    <div class="col">
                        <h4 class="mb-1 fw-semibold">My Properties</h4>
                        <p class="text-muted mb-0">Properties assigned to you by landlords or admin</p>
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
                            <div class="col-md-6">
                                <form method="GET" action="" class="d-flex gap-2">
                                    <input type="text" name="search" class="form-control" placeholder="Search properties..." value="<?= e($searchQuery) ?>">
                                    <button type="submit" class="btn btn-primary">
                                        <i data-feather="search" style="width:16px;height:16px;"></i>
                                    </button>
                                    <?php if ($searchQuery): ?>
                                        <a href="properties.php" class="btn btn-outline-secondary">Clear</a>
                                    <?php endif; ?>
                                </form>
                            </div>
                            <div class="col-md-6">
                                <div class="btn-group w-100" role="group">
                                    <a href="properties.php" class="btn btn-<?= !$statusFilter ? 'primary' : 'outline-primary' ?>">
                                        All (<?= array_sum($statusCounts) ?>)
                                    </a>
                                    <a href="properties.php?status=active" class="btn btn-<?= $statusFilter === 'active' ? 'success' : 'outline-success' ?>">
                                        Active (<?= $statusCounts['active'] ?? 0 ?>)
                                    </a>
                                    <a href="properties.php?status=pending" class="btn btn-<?= $statusFilter === 'pending' ? 'warning' : 'outline-warning' ?>">
                                        Pending (<?= $statusCounts['pending'] ?? 0 ?>)
                                    </a>
                                    <a href="properties.php?status=suspended" class="btn btn-<?= $statusFilter === 'suspended' ? 'secondary' : 'outline-secondary' ?>">
                                        Suspended (<?= $statusCounts['suspended'] ?? 0 ?>)
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Properties Grid -->
                <?php if (empty($properties)): ?>
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center py-5">
                            <i data-feather="home" style="width:64px;height:64px;" class="text-muted mb-3"></i>
                            <h5 class="text-muted">No Properties Found</h5>
                            <p class="text-muted">
                                <?php if ($searchQuery): ?>
                                    No properties match your search. <a href="properties.php">Clear search</a>
                                <?php else: ?>
                                    You don't have any properties assigned yet. Contact your admin or landlords to get property assignments.
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="row g-4 mb-4">
                        <?php foreach ($properties as $property): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="card property-card border-0 shadow-sm h-100">
                                    <img src="<?= getPropertyCoverImage($property) ?>" 
                                         class="card-img-top property-image" 
                                         alt="<?= e($property['title']) ?>">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="card-title mb-0">
                                                <a href="property-view.php?id=<?= $property['id'] ?>" class="text-decoration-none text-dark">
                                                    <?= e(truncate($property['title'], 40)) ?>
                                                </a>
                                            </h6>
                                            <?= statusBadge($property['status']) ?>
                                        </div>
                                        
                                        <p class="text-muted small mb-2">
                                            <i data-feather="map-pin" style="width:14px;height:14px;"></i>
                                            <?= e(formatPropertyLocation($property)) ?>
                                        </p>
                                        
                                        <div class="mb-3">
                                            <span class="badge bg-info"><?= ucfirst(e($property['type'])) ?></span>
                                            <span class="badge bg-secondary"><?= e($property['category_name']) ?></span>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <div>
                                                <h5 class="mb-0 text-primary"><?= formatCurrency($property['price']) ?></h5>
                                                <small class="text-muted">Per month</small>
                                            </div>
                                            <div class="text-end">
                                                <small class="text-muted d-block">Commission</small>
                                                <strong><?= $property['commission_rate'] ?>%</strong>
                                            </div>
                                        </div>
                                        
                                        <div class="row g-2 text-center mb-3">
                                            <div class="col-3">
                                                <small class="text-muted d-block">Beds</small>
                                                <strong><?= $property['bedrooms'] ?></strong>
                                            </div>
                                            <div class="col-3">
                                                <small class="text-muted d-block">Baths</small>
                                                <strong><?= $property['bathrooms'] ?></strong>
                                            </div>
                                            <div class="col-3">
                                                <small class="text-muted d-block">Parking</small>
                                                <strong><?= $property['garage'] ?></strong>
                                            </div>
                                            <div class="col-3">
                                                <small class="text-muted d-block">Apps</small>
                                                <strong><?= $property['application_count'] ?? 0 ?></strong>
                                            </div>
                                        </div>
                                        
                                        <div class="border-top pt-3 mt-3">
                                            <div class="row text-center g-2">
                                                <div class="col-6">
                                                    <small class="text-muted d-block">Applications</small>
                                                    <strong class="text-primary"><?= $property['application_count'] ?></strong>
                                                </div>
                                                <div class="col-6">
                                                    <small class="text-muted d-block">Viewings</small>
                                                    <strong class="text-success"><?= $property['upcoming_viewings'] ?></strong>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="border-top pt-3">
                                            <small class="text-muted d-block mb-1">Landlord</small>
                                            <p class="mb-0 fw-semibold small"><?= e($property['landlord_first'] . ' ' . $property['landlord_last']) ?></p>
                                            <small class="text-muted">Assigned: <?= formatDate($property['assigned_date']) ?></small>
                                        </div>
                                    </div>
                                    <div class="card-footer bg-transparent border-top-0">
                                        <div class="d-grid gap-2">
                                            <a href="property-view.php?id=<?= $property['id'] ?>" class="btn btn-sm btn-primary">
                                                <i data-feather="eye" style="width:14px;height:14px;"></i>
                                                View Details
                                            </a>
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

</body>
</html>
