<?php
/**
 * Agent — Property View/Details
 * Rent Hob
 */
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/agent-functions.php';

requireRole('agent');

$agentId = currentUserId();
$propertyId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($propertyId <= 0) {
    setFlash('error', 'Invalid property ID.');
    redirect(BASE_URL . '/agent/properties.php');
}

// Check if agent has access to this property
requireAgentProperty($pdo, $agentId, $propertyId);

// Fetch property with full details
$stmt = $pdo->prepare('
    SELECT p.*, 
           ap.assigned_date, ap.commission_rate, ap.notes as assignment_notes,
           c.name as category_name,
           a.name as area_name,
           ci.name as city_name,
           s.name as state_name,
           co.name as country_name,
           u.first_name as landlord_first, u.last_name as landlord_last,
           u.email as landlord_email, u.phone as landlord_phone, u.avatar as landlord_avatar,
           COUNT(DISTINCT b.id) as total_bookings,
           COUNT(DISTINCT app.id) as total_applications,
           COUNT(DISTINCT l.id) as active_leases
    FROM properties p
    INNER JOIN agent_properties ap ON ap.property_id = p.id AND ap.agent_id = ? AND ap.status = "active"
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN areas a ON p.area_id = a.id
    LEFT JOIN cities ci ON a.city_id = ci.id
    LEFT JOIN states s ON ci.state_id = s.id
    LEFT JOIN countries co ON s.country_id = co.id
    LEFT JOIN users u ON u.id = p.landlord_id
    LEFT JOIN bookings b ON p.id = b.property_id
    LEFT JOIN applications app ON p.id = app.property_id
    LEFT JOIN leases l ON l.property_id = p.id AND l.status = "active"
    WHERE p.id = ?
    GROUP BY p.id
');
$stmt->execute([$agentId, $propertyId]);
$property = $stmt->fetch();

if (!$property) {
    setFlash('error', 'Property not found or you do not have access to it.');
    redirect(BASE_URL . '/agent/properties.php');
}

// Fetch property images
$stmt = $pdo->prepare('SELECT * FROM property_images WHERE property_id = ? ORDER BY id ASC');
$stmt->execute([$propertyId]);
$images = $stmt->fetchAll();

// Fetch amenities
$stmt = $pdo->prepare('
    SELECT am.name, am.icon
    FROM property_amenities pa
    JOIN amenities am ON pa.amenity_id = am.id
    WHERE pa.property_id = ?
    ORDER BY am.name
');
$stmt->execute([$propertyId]);
$amenities = $stmt->fetchAll();

// Fetch recent applications
$stmt = $pdo->prepare('
    SELECT app.*, u.first_name, u.last_name, u.email, u.phone, u.avatar
    FROM applications app
    JOIN users u ON app.user_id = u.id
    WHERE app.property_id = ?
    ORDER BY app.created_at DESC
    LIMIT 10
');
$stmt->execute([$propertyId]);
$applications = $stmt->fetchAll();

// Fetch upcoming viewings/bookings
$stmt = $pdo->prepare('
    SELECT b.*, u.first_name, u.last_name, u.email, u.phone, u.avatar
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    WHERE b.property_id = ? AND b.visit_date >= CURDATE()
    ORDER BY b.visit_date ASC, b.visit_time ASC
    LIMIT 10
');
$stmt->execute([$propertyId]);
$upcomingViewings = $stmt->fetchAll();

// Fetch current tenant (active lease)
$stmt = $pdo->prepare('
    SELECT l.*, u.first_name, u.last_name, u.email, u.phone, u.avatar
    FROM leases l
    JOIN users u ON u.id = l.tenant_id
    WHERE l.property_id = ? AND l.status = "active" AND l.end_date >= CURDATE()
    ORDER BY l.start_date DESC
    LIMIT 1
');
$stmt->execute([$propertyId]);
$currentTenant = $stmt->fetch();

// Fetch leads interested in this property
$stmt = $pdo->prepare('
    SELECT * FROM tenant_leads
    WHERE agent_id = ? AND interested_property_id = ?
    ORDER BY created_at DESC
    LIMIT 5
');
$stmt->execute([$agentId, $propertyId]);
$interestedLeads = $stmt->fetchAll();

// Calculate stats
$pendingApplications = 0;
$approvedApplications = 0;
foreach ($applications as $app) {
    if ($app['status'] === 'pending') $pendingApplications++;
    if ($app['status'] === 'approved') $approvedApplications++;
}

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= e($property['title']) ?> — Rent Hob Agent</title>
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
        .property-image-main {
            height: 400px;
            object-fit: cover;
            width: 100%;
            border-radius: 8px;
        }
        .property-image-thumb {
            height: 80px;
            object-fit: cover;
            width: 100%;
            border-radius: 4px;
            cursor: pointer;
            transition: transform .2s;
        }
        .property-image-thumb:hover {
            transform: scale(1.05);
        }
        .amenity-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 12px;
            background: #f8f9fa;
            border-radius: 6px;
            margin: 4px;
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
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                            <div>
                                <h4 class="mb-1"><?= e($property['title']) ?></h4>
                                <p class="text-muted mb-2">
                                    <i data-feather="map-pin" style="width:14px;height:14px;"></i>
                                    <?= e(formatPropertyLocation($property)) ?>
                                </p>
                                <div>
                                    <?= statusBadge($property['status']) ?>
                                    <span class="badge bg-info"><?= ucfirst(e($property['type'])) ?></span>
                                    <span class="badge bg-secondary"><?= e($property['category_name']) ?></span>
                                    <?php if ($property['featured']): ?>
                                        <span class="badge bg-warning text-dark">Featured</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="d-flex gap-2">
                                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#scheduleViewingModal">
                                    <i data-feather="calendar" style="width:16px;height:16px;"></i> Schedule Viewing
                                </button>
                                <a href="properties.php" class="btn btn-outline-secondary">
                                    <i data-feather="arrow-left" style="width:16px;height:16px;"></i> Back
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($flash): ?>
                    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
                        <?= e($flash['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Quick Stats -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <p class="text-muted small mb-1">Rent Price</p>
                                        <h4 class="mb-0 fw-bold text-primary"><?= formatCurrency($property['price']) ?></h4>
                                        <small class="text-muted">Per month</small>
                                    </div>
                                    <div class="bg-primary bg-opacity-10 p-2 rounded">
                                        <i data-feather="dollar-sign" class="text-primary" style="width:24px;height:24px;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <p class="text-muted small mb-1">Applications</p>
                                        <h4 class="mb-0 fw-bold"><?= $property['total_applications'] ?></h4>
                                        <small class="text-muted"><?= $pendingApplications ?> pending</small>
                                    </div>
                                    <div class="bg-warning bg-opacity-10 p-2 rounded">
                                        <i data-feather="file-text" class="text-warning" style="width:24px;height:24px;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <p class="text-muted small mb-1">Total Bookings</p>
                                        <h4 class="mb-0 fw-bold"><?= $property['total_bookings'] ?></h4>
                                        <small class="text-muted"><?= count($upcomingViewings) ?> upcoming</small>
                                    </div>
                                    <div class="bg-info bg-opacity-10 p-2 rounded">
                                        <i data-feather="calendar" class="text-info" style="width:24px;height:24px;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <p class="text-muted small mb-1">Commission Rate</p>
                                        <h4 class="mb-0 fw-bold text-success"><?= number_format($property['commission_rate'], 1) ?>%</h4>
                                        <small class="text-muted">On rent</small>
                                    </div>
                                    <div class="bg-success bg-opacity-10 p-2 rounded">
                                        <i data-feather="trending-up" class="text-success" style="width:24px;height:24px;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="row g-4">
                    
                    <!-- Left Column -->
                    <div class="col-lg-8">
                        
                        <!-- Property Images -->
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-body">
                                <?php if (!empty($images)): ?>
                                    <img src="<?= BASE_URL ?>/<?= e($images[0]['image_path']) ?>" 
                                         class="property-image-main mb-3" 
                                         alt="<?= e($property['title']) ?>"
                                         id="mainImage">
                                    
                                    <?php if (count($images) > 1): ?>
                                        <div class="row g-2">
                                            <?php foreach ($images as $index => $image): ?>
                                                <div class="col-2">
                                                    <img src="<?= BASE_URL ?>/<?= e($image['image_path']) ?>" 
                                                         class="property-image-thumb" 
                                                         alt="Thumbnail"
                                                         onclick="document.getElementById('mainImage').src='<?= BASE_URL ?>/<?= e($image['image_path']) ?>'">
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <img src="<?= BASE_URL ?>/assets/img/property-placeholder.jpg" 
                                         class="property-image-main" 
                                         alt="No image">
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Property Details -->
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-white">
                                <h6 class="mb-0">Property Details</h6>
                            </div>
                            <div class="card-body">
                                <div class="row g-3 mb-4">
                                    <div class="col-md-3 col-6">
                                        <div class="text-center p-3 bg-light rounded">
                                            <i data-feather="home" class="text-primary mb-2" style="width:24px;height:24px;"></i>
                                            <p class="mb-0 small text-muted">Bedrooms</p>
                                            <h5 class="mb-0"><?= $property['bedrooms'] ?></h5>
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-6">
                                        <div class="text-center p-3 bg-light rounded">
                                            <i data-feather="droplet" class="text-info mb-2" style="width:24px;height:24px;"></i>
                                            <p class="mb-0 small text-muted">Bathrooms</p>
                                            <h5 class="mb-0"><?= $property['bathrooms'] ?></h5>
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-6">
                                        <div class="text-center p-3 bg-light rounded">
                                            <i data-feather="maximize" class="text-success mb-2" style="width:24px;height:24px;"></i>
                                            <p class="mb-0 small text-muted">Size</p>
                                            <h5 class="mb-0"><?= number_format($property['size']) ?> sqft</h5>
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-6">
                                        <div class="text-center p-3 bg-light rounded">
                                            <i data-feather="square" class="text-warning mb-2" style="width:24px;height:24px;"></i>
                                            <p class="mb-0 small text-muted">Parking</p>
                                            <h5 class="mb-0"><?= $property['garage'] ?></h5>
                                        </div>
                                    </div>
                                </div>

                                <h6 class="mb-3">Description</h6>
                                <p class="text-muted"><?= nl2br(e($property['description'])) ?></p>

                                <?php if (!empty($amenities)): ?>
                                    <h6 class="mb-3 mt-4">Amenities</h6>
                                    <div>
                                        <?php foreach ($amenities as $amenity): ?>
                                            <span class="amenity-badge">
                                                <i data-feather="check-circle" style="width:16px;height:16px;color:#28a745;"></i>
                                                <?= e($amenity['name']) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Applications Tab -->
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-white">
                                <h6 class="mb-0">Recent Applications</h6>
                            </div>
                            <div class="card-body">
                                <?php if (empty($applications)): ?>
                                    <p class="text-muted text-center py-3">No applications yet</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Applicant</th>
                                                    <th>Applied</th>
                                                    <th>Status</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($applications as $app): ?>
                                                    <tr>
                                                        <td>
                                                            <div class="d-flex align-items-center gap-2">
                                                                <img src="<?= userAvatar($app['avatar']) ?>" class="rounded-circle" width="32" height="32" alt="">
                                                                <div>
                                                                    <p class="mb-0 small fw-semibold"><?= e($app['first_name'] . ' ' . $app['last_name']) ?></p>
                                                                    <small class="text-muted"><?= e($app['email']) ?></small>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td><small><?= formatDate($app['created_at']) ?></small></td>
                                                        <td><?= statusBadge($app['status']) ?></td>
                                                        <td>
                                                            <a href="application-view.php?id=<?= $app['id'] ?>" class="btn btn-sm btn-outline-primary">View</a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Upcoming Viewings -->
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-white">
                                <h6 class="mb-0">Upcoming Viewings</h6>
                            </div>
                            <div class="card-body">
                                <?php if (empty($upcomingViewings)): ?>
                                    <p class="text-muted text-center py-3">No upcoming viewings</p>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($upcomingViewings as $viewing): ?>
                                            <div class="list-group-item px-0">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div class="d-flex gap-2">
                                                        <img src="<?= userAvatar($viewing['avatar']) ?>" class="rounded-circle" width="40" height="40" alt="">
                                                        <div>
                                                            <p class="mb-0 fw-semibold small"><?= e($viewing['first_name'] . ' ' . $viewing['last_name']) ?></p>
                                                            <small class="text-muted"><?= e($viewing['phone']) ?></small>
                                                            <p class="mb-0 mt-1">
                                                                <i data-feather="calendar" style="width:14px;height:14px;"></i>
                                                                <?= formatDate($viewing['visit_date']) ?> at <?= date('g:i A', strtotime($viewing['visit_time'])) ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <?= statusBadge($viewing['status']) ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>

                    <!-- Right Column -->
                    <div class="col-lg-4">
                        
                        <!-- Landlord Info -->
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-white">
                                <h6 class="mb-0">Landlord Information</h6>
                            </div>
                            <div class="card-body text-center">
                                <img src="<?= userAvatar($property['landlord_avatar']) ?>" 
                                     class="rounded-circle mb-3" 
                                     width="80" height="80" 
                                     alt="Landlord"
                                     style="object-fit:cover;">
                                <h6 class="mb-1"><?= e($property['landlord_first'] . ' ' . $property['landlord_last']) ?></h6>
                                <p class="text-muted small mb-3">Property Owner</p>
                                <div class="d-grid gap-2">
                                    <a href="tel:<?= e($property['landlord_phone']) ?>" class="btn btn-sm btn-outline-primary">
                                        <i data-feather="phone" style="width:14px;height:14px;"></i> Call
                                    </a>
                                    <a href="mailto:<?= e($property['landlord_email']) ?>" class="btn btn-sm btn-outline-primary">
                                        <i data-feather="mail" style="width:14px;height:14px;"></i> Email
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Current Tenant -->
                        <?php if ($currentTenant): ?>
                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-header bg-white">
                                    <h6 class="mb-0">Current Tenant</h6>
                                </div>
                                <div class="card-body text-center">
                                    <img src="<?= userAvatar($currentTenant['avatar']) ?>" 
                                         class="rounded-circle mb-3" 
                                         width="60" height="60" 
                                         alt="Tenant"
                                         style="object-fit:cover;">
                                    <h6 class="mb-1"><?= e($currentTenant['first_name'] . ' ' . $currentTenant['last_name']) ?></h6>
                                    <p class="text-muted small mb-2"><?= e($currentTenant['phone']) ?></p>
                                    <p class="small mb-0">
                                        <strong>Lease:</strong> <?= formatDate($currentTenant['start_date']) ?> - <?= formatDate($currentTenant['end_date']) ?>
                                    </p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Interested Leads -->
                        <?php if (!empty($interestedLeads)): ?>
                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-header bg-white">
                                    <h6 class="mb-0">Interested Leads</h6>
                                </div>
                                <div class="card-body">
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($interestedLeads as $lead): ?>
                                            <div class="list-group-item px-0">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <p class="mb-0 fw-semibold small"><?= e($lead['name']) ?></p>
                                                        <small class="text-muted"><?= e($lead['phone']) ?></small>
                                                    </div>
                                                    <div>
                                                        <?= leadStatusBadge($lead['status']) ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Assignment Info -->
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-white">
                                <h6 class="mb-0">Assignment Details</h6>
                            </div>
                            <div class="card-body">
                                <p class="mb-2">
                                    <strong>Assigned:</strong> <?= formatDate($property['assigned_date']) ?>
                                </p>
                                <p class="mb-2">
                                    <strong>Commission:</strong> <span class="text-success fw-bold"><?= $property['commission_rate'] ?>%</span>
                                </p>
                                <?php if ($property['assignment_notes']): ?>
                                    <p class="mb-0">
                                        <strong>Notes:</strong><br>
                                        <small class="text-muted"><?= nl2br(e($property['assignment_notes'])) ?></small>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-white">
                                <h6 class="mb-0">Quick Actions</h6>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#scheduleViewingModal">
                                        <i data-feather="calendar" style="width:14px;height:14px;"></i> Schedule Viewing
                                    </button>
                                    <a href="applications.php?property=<?= $property['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i data-feather="file-text" style="width:14px;height:14px;"></i> View All Applications
                                    </a>
                                    <a href="leads.php?action=add&property=<?= $property['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i data-feather="user-plus" style="width:14px;height:14px;"></i> Add Lead
                                    </a>
                                </div>
                            </div>
                        </div>

                    </div>

                </div>

                <!-- Footer -->
                <div class="row mt-3">
                    <div class="col text-center text-muted small py-2">
                        &copy; <?= date('Y') ?> Rent Hob. All rights reserved.
                    </div>
                </div>

            </div>
        </main>

    </div>

    <!-- Schedule Viewing Modal -->
    <div class="modal fade" id="scheduleViewingModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Schedule Property Viewing</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3">Property: <strong><?= e($property['title']) ?></strong></p>
                    <form action="schedule-viewing-handler.php" method="POST">
                        <input type="hidden" name="property_id" value="<?= $property['id'] ?>">
                        <div class="mb-3">
                            <label class="form-label">Select Lead or Enter Details</label>
                            <select class="form-select" name="lead_id">
                                <option value="">-- New Viewing --</option>
                                <?php foreach ($interestedLeads as $lead): ?>
                                    <option value="<?= $lead['id'] ?>"><?= e($lead['name']) ?> - <?= e($lead['phone']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Viewing Date</label>
                            <input type="date" class="form-control" name="viewing_date" required min="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Viewing Time</label>
                            <input type="time" class="form-control" name="viewing_time" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes (Optional)</label>
                            <textarea class="form-control" name="notes" rows="3"></textarea>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-success">Schedule Viewing</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
