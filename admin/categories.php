<?php
/**
 * Admin - Property Categories
 * Rent Hob
 */
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('admin');

function uniqueCategorySlug(PDO $pdo, string $name, int $ignoreId = 0): string {
    $base = slugify($name) ?: 'category';
    $slug = $base;
    $i = 2;

    while (true) {
        $stmt = $pdo->prepare('SELECT id FROM categories WHERE slug = ? AND id != ? LIMIT 1');
        $stmt->execute([$slug, $ignoreId]);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $base . '-' . $i++;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $icon = trim($_POST['icon'] ?? '');
    $status = $_POST['status'] ?? 'active';

    if (!in_array($status, ['active', 'inactive'], true)) {
        $status = 'active';
    }

    if ($action === 'create' || $action === 'update') {
        if ($name === '') {
            setFlash('error', 'Category name is required.');
        } elseif ($action === 'create') {
            $slug = uniqueCategorySlug($pdo, $name);
            $pdo->prepare('INSERT INTO categories (name, slug, icon, status) VALUES (?, ?, ?, ?)')
                ->execute([$name, $slug, $icon ?: null, $status]);
            logActivity($pdo, currentUserId(), "Created category $name", 'categories');
            setFlash('success', 'Category created.');
        } elseif ($categoryId > 0) {
            $slug = uniqueCategorySlug($pdo, $name, $categoryId);
            $pdo->prepare('UPDATE categories SET name = ?, slug = ?, icon = ?, status = ? WHERE id = ?')
                ->execute([$name, $slug, $icon ?: null, $status, $categoryId]);
            logActivity($pdo, currentUserId(), "Updated category #$categoryId", 'categories');
            setFlash('success', 'Category updated.');
        }
    } elseif ($categoryId > 0) {
        switch ($action) {
            case 'activate':
                $pdo->prepare('UPDATE categories SET status = ? WHERE id = ?')->execute(['active', $categoryId]);
                setFlash('success', 'Category activated.');
                break;
            case 'deactivate':
                $pdo->prepare('UPDATE categories SET status = ? WHERE id = ?')->execute(['inactive', $categoryId]);
                setFlash('success', 'Category deactivated.');
                break;
            case 'delete':
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM properties WHERE category_id = ?');
                $stmt->execute([$categoryId]);
                if ((int)$stmt->fetchColumn() > 0) {
                    setFlash('error', 'This category is used by properties. Deactivate it instead.');
                } else {
                    $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$categoryId]);
                    logActivity($pdo, currentUserId(), "Deleted category #$categoryId", 'categories');
                    setFlash('success', 'Category deleted.');
                }
                break;
        }
    }

    redirect(BASE_URL . '/admin/categories.php');
}

$editId = (int)($_GET['edit'] ?? 0);
$editCategory = null;
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM categories WHERE id = ? LIMIT 1');
    $stmt->execute([$editId]);
    $editCategory = $stmt->fetch();
}

$stmt = $pdo->query(
    'SELECT c.*, COUNT(p.id) AS property_count
     FROM categories c
     LEFT JOIN properties p ON p.category_id = c.id
     GROUP BY c.id
     ORDER BY c.name'
);
$categories = $stmt->fetchAll();
$activeCount = 0;
$inactiveCount = 0;
foreach ($categories as $category) {
    if ($category['status'] === 'active') {
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
    <title>Categories - Rent Hob Admin</title>
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
                    <li class="nav-item"><a class="nav-link active" href="categories.php"><i data-feather="tag"></i><span>Categories</span></a></li>
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
            </div>
        </div>

        <main class="adminuiux-content has-sidebar" onclick="contentClick()">
            <div class="container mt-2" id="main-content">
                <div class="row mb-3">
                    <div class="col">
                        <nav aria-label="breadcrumb"><ol class="breadcrumb mb-1"><li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li><li class="breadcrumb-item active">Categories</li></ol></nav>
                        <h5 class="mb-0 fw-semibold">Property Categories</h5>
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
                                <h6 class="fw-semibold mb-3"><?= $editCategory ? 'Edit Category' : 'Add Category' ?></h6>
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                    <input type="hidden" name="action" value="<?= $editCategory ? 'update' : 'create' ?>">
                                    <?php if ($editCategory): ?><input type="hidden" name="category_id" value="<?= (int)$editCategory['id'] ?>"><?php endif; ?>
                                    <div class="mb-3">
                                        <label class="form-label">Name</label>
                                        <input type="text" class="form-control" name="name" value="<?= e($editCategory['name'] ?? '') ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Icon class</label>
                                        <input type="text" class="form-control" name="icon" placeholder="fa-building" value="<?= e($editCategory['icon'] ?? '') ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Status</label>
                                        <select class="form-select" name="status">
                                            <option value="active" <?= ($editCategory['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                                            <option value="inactive" <?= ($editCategory['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                        </select>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-primary flex-fill" type="submit"><?= $editCategory ? 'Save Changes' : 'Add Category' ?></button>
                                        <?php if ($editCategory): ?><a href="categories.php" class="btn btn-outline-secondary">Cancel</a><?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-8">
                        <div class="row g-3 mb-3">
                            <div class="col-sm-4"><div class="card border-0 shadow-sm"><div class="card-body"><p class="text-muted mb-1">Total</p><h4 class="mb-0"><?= count($categories) ?></h4></div></div></div>
                            <div class="col-sm-4"><div class="card border-0 shadow-sm"><div class="card-body"><p class="text-muted mb-1">Active</p><h4 class="mb-0"><?= $activeCount ?></h4></div></div></div>
                            <div class="col-sm-4"><div class="card border-0 shadow-sm"><div class="card-body"><p class="text-muted mb-1">Inactive</p><h4 class="mb-0"><?= $inactiveCount ?></h4></div></div></div>
                        </div>
                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light"><tr><th class="ps-3">Category</th><th>Slug</th><th>Properties</th><th>Status</th><th class="text-end pe-3">Actions</th></tr></thead>
                                        <tbody>
                                            <?php if (!$categories): ?>
                                                <tr><td colspan="5" class="text-center text-muted py-5">No categories found.</td></tr>
                                            <?php endif; ?>
                                            <?php foreach ($categories as $category): ?>
                                                <tr>
                                                    <td class="ps-3"><p class="mb-0 fw-semibold"><?= e($category['name']) ?></p><p class="mb-0 small text-muted"><?= e($category['icon'] ?: 'No icon') ?></p></td>
                                                    <td class="small text-muted"><?= e($category['slug']) ?></td>
                                                    <td><a href="properties.php?category_id=<?= (int)$category['id'] ?>" class="badge bg-light text-dark border"><?= (int)$category['property_count'] ?></a></td>
                                                    <td><?= statusBadge($category['status']) ?></td>
                                                    <td class="text-end pe-3">
                                                        <div class="d-flex justify-content-end gap-1">
                                                            <a href="categories.php?edit=<?= (int)$category['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                                                            <form method="POST">
                                                                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                                                <input type="hidden" name="category_id" value="<?= (int)$category['id'] ?>">
                                                                <input type="hidden" name="action" value="<?= $category['status'] === 'active' ? 'deactivate' : 'activate' ?>">
                                                                <button class="btn btn-sm btn-outline-primary" type="submit"><?= $category['status'] === 'active' ? 'Deactivate' : 'Activate' ?></button>
                                                            </form>
                                                            <form method="POST" onsubmit="return confirm('Delete this category?')">
                                                                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                                                <input type="hidden" name="category_id" value="<?= (int)$category['id'] ?>">
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
