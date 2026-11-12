and keep dashboard/ only for shared dashboard assets or remove it once you extract the real pages.<?php
/**
 * Admin - View Property
 * Rent Hob
 */
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('admin');

$propertyId = (int)($_GET['id'] ?? 0);
if ($propertyId <= 0) {
    setFlash('error', 'Property not found.');
    redirect(BASE_URL . '/admin/properties.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';

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
        default:
            setFlash('error', 'Invalid property action.');
            break;
    }

    redirect(BASE_URL . '/admin/property-view.php?id=' . $propertyId);
}

$stmt = $pdo->prepare(
    "SELECT
        p.*,
        c.name AS category_name,
        a.name AS area_name,
        ci.name AS city_name,
        s.name AS state_name,
        co.name AS country_name,
        u.id AS landlord_user_id,
        u.first_name AS landlord_first_name,
        u.last_name AS landlord_last_name,
        u.email AS landlord_email,
        u.phone AS landlord_phone,
        ag.first_name AS agent_first_name,
        ag.last_name AS agent_last_name,
        ag.email AS agent_email
     FROM properties p
     JOIN categories c ON c.id = p.category_id
     JOIN areas a ON a.id = p.area_id
     JOIN cities ci ON ci.id = a.city_id
     JOIN states s ON s.id = ci.state_id
     JOIN countries co ON co.id = s.country_id
     JOIN users u ON u.id = p.landlord_id
     LEFT JOIN users ag ON ag.id = p.agent_id
     WHERE p.id = ?
     LIMIT 1"
);
$stmt->execute([$propertyId]);
$property = $stmt->fetch();

if (!$property) {
    setFlash('error', 'Property not found.');
    redirect(BASE_URL . '/admin/properties.php');
}

$stmt = $pdo->prepare('SELECT image_path FROM property_images WHERE property_id = ? ORDER BY id ASC');
$stmt->execute([$propertyId]);
$images = $stmt->fetchAll();

$stmt = $pdo->prepare(
    'SELECT am.name, am.icon
     FROM property_amenities pa
     JOIN amenities am ON am.id = pa.amenity_id
     WHERE pa.property_id = ?
     ORDER BY am.name'
);
$stmt->execute([$propertyId]);
$amenities = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM applications WHERE property_id = ?');
$stmt->execute([$propertyId]);
$applicationsCount = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM bookings WHERE property_id = ?');
$stmt->execute([$propertyId]);
$bookingsCount = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM inquiries WHERE property_id = ?');
$stmt->execute([$propertyId]);
$inquiriesCount = (int)$stmt->fetchColumn();

$flash = getFlash();
$landlordName = trim($property['landlord_first_name'] . ' ' . $property['landlord_last_name']);
$agentName = trim(($property['agent_first_name'] ?? '') . ' ' . ($property['agent_last_name'] ?? ''));
$cover = $images[0]['image_path'] ?? null;
$coverUrl = $cover ? BASE_URL . '/' . $cover : BASE_URL . '/assets/img/property-img-1.jpg';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= e($property['title']) ?> - Rent Hob Admin</title>
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/img/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Open+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --adminuiux-content-font: "Open Sans", sans-serif;
            --adminuiux-title-font: "Inter", sans-serif;
        }
        .property-cover {
            width: 100%;
            height: 320px;
            object-fit: cover;
            border-radius: 8px;
            background: #f3f4f6;
        }
        .property-gallery-thumb {
            width: 86px;
            height: 66px;
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
      data-bggradient="bg-gradient-5">

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
                                <li class="breadcrumb-item"><a href="properties.php">Properties</a></li>
                                <li class="breadcrumb-item active">View</li>
                            </ol>
                        </nav>
                        <h5 class="mb-0 fw-semibold"><?= e($property['title']) ?></h5>
                    </div>
                    <div class="col-auto align-self-center">
                        <a href="properties.php" class="btn btn-outline-secondary btn-sm">
                            <i data-feather="arrow-left" style="width:15px;height:15px;" class="me-1"></i> Back
                        </a>
                    </div>
                </div>

                <?php if ($flash): ?>
                    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show mb-3">
                        <?= e($flash['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row g-3">
                    <div class="col-lg-8">
                        <div class="card border-0 shadow-sm mb-3">
                            <div class="card-body">
                                <img src="<?= e($coverUrl) ?>" alt="" class="property-cover mb-3">
                                <?php if ($images): ?>
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php foreach ($images as $image): ?>
                                            <img src="<?= e(BASE_URL . '/' . $image['image_path']) ?>" alt="" class="property-gallery-thumb">
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                                    <div>
                                        <p class="text-muted mb-1"><?= e($property['category_name']) ?> - <?= ucfirst(e($property['type'])) ?></p>
                                        <h4 class="mb-0"><?= formatCurrency((float)$property['price']) ?></h4>
                                    </div>
                                    <div class="text-end">
                                        <?= statusBadge($property['status']) ?>
                                        <?php if ((int)$property['featured'] === 1): ?>
                                            <span class="badge bg-primary ms-1">Featured</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <p class="text-muted mb-4"><?= nl2br(e($property['description'] ?: 'No description provided.')) ?></p>
                                <div class="row g-3">
                                    <div class="col-6 col-md-3"><div class="p-3 bg-light rounded"><p class="mb-1 small text-muted">Bedrooms</p><h6 class="mb-0"><?= (int)$property['bedrooms'] ?></h6></div></div>
                                    <div class="col-6 col-md-3"><div class="p-3 bg-light rounded"><p class="mb-1 small text-muted">Bathrooms</p><h6 class="mb-0"><?= (int)$property['bathrooms'] ?></h6></div></div>
                                    <div class="col-6 col-md-3"><div class="p-3 bg-light rounded"><p class="mb-1 small text-muted">Toilets</p><h6 class="mb-0"><?= (int)$property['toilets'] ?></h6></div></div>
                                    <div class="col-6 col-md-3"><div class="p-3 bg-light rounded"><p class="mb-1 small text-muted">Garage</p><h6 class="mb-0"><?= (int)$property['garage'] ?></h6></div></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card border-0 shadow-sm mb-3">
                            <div class="card-body">
                                <h6 class="fw-semibold mb-3">Admin Actions</h6>
                                <div class="d-grid gap-2">
                                    <?php if ($property['status'] !== 'active'): ?>
                                        <form method="POST"><input type="hidden" name="csrf_token" value="<?= csrfToken() ?>"><input type="hidden" name="action" value="activate"><button class="btn btn-success w-100" type="submit">Approve Property</button></form>
                                    <?php endif; ?>
                                    <?php if ($property['status'] !== 'rejected'): ?>
                                        <form method="POST"><input type="hidden" name="csrf_token" value="<?= csrfToken() ?>"><input type="hidden" name="action" value="reject"><button class="btn btn-outline-warning w-100" type="submit">Reject Property</button></form>
                                    <?php endif; ?>
                                    <?php if ($property['status'] !== 'suspended'): ?>
                                        <form method="POST"><input type="hidden" name="csrf_token" value="<?= csrfToken() ?>"><input type="hidden" name="action" value="suspend"><button class="btn btn-outline-danger w-100" type="submit">Suspend Property</button></form>
                                    <?php endif; ?>
                                    <form method="POST">
                                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                        <input type="hidden" name="action" value="<?= (int)$property['featured'] === 1 ? 'unfeature' : 'feature' ?>">
                                        <button class="btn btn-outline-primary w-100" type="submit"><?= (int)$property['featured'] === 1 ? 'Remove Featured' : 'Mark Featured' ?></button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm mb-3">
                            <div class="card-body">
                                <h6 class="fw-semibold mb-3">Owner</h6>
                                <p class="mb-1 fw-semibold"><?= e($landlordName) ?></p>
                                <p class="mb-1 small text-muted"><?= e($property['landlord_email']) ?></p>
                                <p class="mb-3 small text-muted"><?= $property['landlord_phone'] ? e($property['landlord_phone']) : 'No phone number' ?></p>
                                <a href="user-view.php?id=<?= (int)$property['landlord_user_id'] ?>" class="btn btn-sm btn-outline-primary">View Owner</a>
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm mb-3">
                            <div class="card-body">
                                <h6 class="fw-semibold mb-3">Location</h6>
                                <p class="mb-1"><?= e($property['address'] ?: 'No address provided') ?></p>
                                <p class="mb-0 small text-muted"><?= e($property['area_name']) ?>, <?= e($property['city_name']) ?>, <?= e($property['state_name']) ?>, <?= e($property['country_name']) ?></p>
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm mb-3">
                            <div class="card-body">
                                <h6 class="fw-semibold mb-3">Activity</h6>
                                <div class="d-flex justify-content-between py-1"><span class="text-muted">Applications</span><strong><?= $applicationsCount ?></strong></div>
                                <div class="d-flex justify-content-between py-1"><span class="text-muted">Bookings</span><strong><?= $bookingsCount ?></strong></div>
                                <div class="d-flex justify-content-between py-1"><span class="text-muted">Inquiries</span><strong><?= $inquiriesCount ?></strong></div>
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <h6 class="fw-semibold mb-3">Amenities</h6>
                                <?php if ($amenities): ?>
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php foreach ($amenities as $amenity): ?>
                                            <span class="badge bg-light text-dark border"><?= e($amenity['name']) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="mb-0 text-muted small">No amenities added.</p>
                                <?php endif; ?>
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
