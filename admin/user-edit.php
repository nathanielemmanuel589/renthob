<?php
/**
 * Admin — Edit User
 * Rent Hob
 */
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('admin');

$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect(BASE_URL . '/admin/users.php');

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$user = $stmt->fetch();
if (!$user) redirect(BASE_URL . '/admin/users.php');

$errors  = [];
$success = false;

// ── Handle form submission ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? '')) {

    $first_name     = trim($_POST['first_name']    ?? '');
    $last_name      = trim($_POST['last_name']     ?? '');
    $email          = trim($_POST['email']         ?? '');
    $phone          = trim($_POST['phone']         ?? '');
    $role           = trim($_POST['role']          ?? '');
    $status         = trim($_POST['status']        ?? '');
    $email_verified = isset($_POST['email_verified']) ? 1 : 0;
    $new_password   = $_POST['new_password']       ?? '';
    $confirm_pass   = $_POST['confirm_password']   ?? '';

    // Validation
    if (empty($first_name)) $errors[] = 'First name is required.';
    if (empty($last_name))  $errors[] = 'Last name is required.';
    if (empty($email) || !isValidEmail($email)) $errors[] = 'Valid email is required.';
    if (!empty($phone) && !isValidPhone($phone)) $errors[] = 'Phone number must be 10–15 digits.';
    if (!in_array($role, ['admin','landlord','agent','tenant'])) $errors[] = 'Invalid role selected.';
    if (!in_array($status, ['active','pending','suspended','inactive'])) $errors[] = 'Invalid status selected.';

    // Protect: cannot demote/change the currently logged-in admin's own role
    if ($id === currentUserId() && $role !== 'admin') {
        $errors[] = 'You cannot change your own role.';
    }

    // Check email uniqueness (excluding current user)
    if (empty($errors)) {
        $stmtCheck = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1');
        $stmtCheck->execute([$email, $id]);
        if ($stmtCheck->fetch()) $errors[] = 'This email is already used by another account.';
    }

    // Password change — only if filled
    if (!empty($new_password)) {
        if (strlen($new_password) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        } elseif ($new_password !== $confirm_pass) {
            $errors[] = 'Passwords do not match.';
        }
    }

    // Handle avatar upload
    $avatarFilename = $user['avatar'];
    if (!empty($_FILES['avatar']['name'])) {
        $allowed   = ['jpg','jpeg','png','webp'];
        $ext       = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        $maxSize   = 2 * 1024 * 1024; // 2MB

        if (!in_array($ext, $allowed)) {
            $errors[] = 'Avatar must be JPG, PNG or WEBP.';
        } elseif ($_FILES['avatar']['size'] > $maxSize) {
            $errors[] = 'Avatar must be under 2MB.';
        } else {
            $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/renthob/uploads/avatars/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $newFilename = 'user_' . $id . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadDir . $newFilename)) {
                // Delete old avatar if it exists
                if ($avatarFilename && file_exists($uploadDir . $avatarFilename)) {
                    unlink($uploadDir . $avatarFilename);
                }
                $avatarFilename = $newFilename;
            } else {
                $errors[] = 'Failed to upload avatar. Check folder permissions.';
            }
        }
    }

    if (empty($errors)) {
        // Build update query
        $fields = [
            'first_name'     => $first_name,
            'last_name'      => $last_name,
            'email'          => $email,
            'phone'          => $phone,
            'role'           => $role,
            'status'         => $status,
            'email_verified' => $email_verified,
            'avatar'         => $avatarFilename,
        ];

        if (!empty($new_password)) {
            $fields['password'] = password_hash($new_password, PASSWORD_BCRYPT);
        }

        $setClauses = implode(', ', array_map(fn($k) => "$k = ?", array_keys($fields)));
        $values     = array_values($fields);
        $values[]   = $id;

        $pdo->prepare("UPDATE users SET $setClauses WHERE id = ?")->execute($values);

        logActivity($pdo, currentUserId(), "Updated user #$id ($email)", 'users');
        setFlash('success', 'User updated successfully.');
        redirect(BASE_URL . '/admin/user-view.php?id=' . $id);
    }

    // Keep form values on error
    $user = array_merge($user, [
        'first_name'     => $first_name,
        'last_name'      => $last_name,
        'email'          => $email,
        'phone'          => $phone,
        'role'           => $role,
        'status'         => $status,
        'email_verified' => $email_verified,
    ]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Edit User — Rent Hob Admin</title>
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
        .avatar-preview { width: 80px; height: 80px; object-fit: cover; }
    </style>
    <script defer src="<?= BASE_URL ?>/dashboard/assets/js/third-party.js"></script>
    <script defer src="<?= BASE_URL ?>/dashboard/assets/js/app.js"></script>
    <script defer src="<?= BASE_URL ?>/dashboard/assets/js/core.js"></script>
    <link href="<?= BASE_URL ?>/dashboard/assets/css/app.css" rel="stylesheet">
</head>

<body class="main-bg main-bg-blur main-bg-opac roundedui adminuiux-sidebar-standard adminuiux-sidebar-fill-white adminuiux-mobile-footer-fill-theme adminuiux-sidebar-boxed adminuiux-header-transparent adminuiux-header-standard theme-cyan bg-gradient-5 scrollup"
      data-theme="theme-cyan" data-sidebarfill="adminuiux-sidebar-fill-white"
      data-sidebarlayout="adminuiux-sidebar-boxed" data-headerlayout="adminuiux-header-standard"
      data-headerfill="adminuiux-header-transparent" data-bggradient="bg-gradient-5"
      data-bs-spy="scroll" data-bs-target="#list-example" data-bs-smooth-scroll="true" tabindex="0">

    <!-- Loader -->
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

    <!-- Header -->
    <header class="adminuiux-header inner-page">
        <nav class="navbar navbar-expand-lg fixed-top">
            <div class="container-fluid">
                <button class="btn btn-link btn-square sidebar-toggler ms-1" type="button" onclick="initSidebar()">
                    <i class="sidebar-svg" data-feather="menu"></i>
                </button>
                <a class="navbar-brand" href="dashboard.php">
                    <img data-bs-img="light" src="<?= BASE_URL ?>/assets/img/logo-2.svg" alt="Rent Hob" style="max-height:32px;">
                    <img data-bs-img="dark"  src="<?= BASE_URL ?>/assets/img/logo-2.svg" alt="Rent Hob" style="max-height:32px;">
                    <div class="lh-20"><span class="h4">Rent Hob</span><p class="company-tagline">Admin Panel</p></div>
                </a>
                <div class="ms-auto d-flex align-items-center gap-1">
                    <div class="dropdown">
                        <button class="btn btn-link no-caret dropdown-toggle d-flex align-items-center gap-2 px-2"
                                type="button" data-bs-toggle="dropdown">
                            <img src="<?= userAvatar($_SESSION['user_avatar'] ?? null) ?>" alt="Admin"
                                 class="rounded-circle" width="34" height="34" style="object-fit:cover;">
                            <span class="d-none d-md-inline small fw-semibold"><?= e($_SESSION['user_name']) ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php"><i data-feather="user" class="me-2" style="width:15px;height:15px;"></i>My Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/logout.php"><i data-feather="log-out" class="me-2" style="width:15px;height:15px;"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>
    </header>

    <div class="adminuiux-wrap">

        <!-- Sidebar -->
        <div class="adminuiux-sidebar">
            <div class="adminuiux-sidebar-inner">
                <ul class="nav flex-column menu-active-line mt-0">
                    <li class="nav-item"><a class="nav-link" href="dashboard.php"><i data-feather="grid"></i><span>Dashboard</span></a></li>
                    <li class="nav-item menu-title"><span>USER MANAGEMENT</span></li>
                    <li class="nav-item"><a class="nav-link active" href="users.php"><i data-feather="users"></i><span>All Users</span></a></li>
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
                    <li class="nav-item"><a class="nav-link" href="settings.php"><i data-feather="settings"></i><span>Settings</span></a></li>
                    <li class="nav-item"><a class="nav-link" href="activity-log.php"><i data-feather="activity"></i><span>Activity Log</span></a></li>
                    <li class="nav-item mt-2"><a class="nav-link text-danger" href="<?= BASE_URL ?>/logout.php"><i data-feather="log-out"></i><span>Logout</span></a></li>
                </ul>
                <div class="mt-auto"></div>
            </div>
        </div>

        <!-- Main Content -->
        <main class="adminuiux-content has-sidebar" onclick="contentClick()">
            <div class="container mt-2" id="main-content">

                <!-- Breadcrumb -->
                <div class="row mb-3">
                    <div class="col">
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb mb-1">
                                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="users.php">Users</a></li>
                                <li class="breadcrumb-item"><a href="user-view.php?id=<?= $id ?>"><?= e($user['first_name'] . ' ' . $user['last_name']) ?></a></li>
                                <li class="breadcrumb-item active">Edit</li>
                            </ol>
                        </nav>
                        <h5 class="mb-0 fw-semibold">Edit User</h5>
                    </div>
                    <div class="col-auto align-self-center">
                        <a href="user-view.php?id=<?= $id ?>" class="btn btn-outline-secondary btn-sm">
                            <i data-feather="arrow-left" style="width:14px;height:14px;" class="me-1"></i> Back
                        </a>
                    </div>
                </div>

                <!-- Errors -->
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger alert-dismissible fade show mb-3">
                        <?php foreach ($errors as $err): ?>
                            <div><?= e($err) ?></div>
                        <?php endforeach; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="user-edit.php?id=<?= $id ?>" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

                    <div class="row g-3">

                        <!-- Left: Avatar + account controls -->
                        <div class="col-xl-4 col-lg-5">

                            <!-- Avatar upload -->
                            <div class="card border-0 shadow-sm mb-3">
                                <div class="card-header bg-transparent border-0 pt-3 pb-0">
                                    <h6 class="fw-semibold mb-0">Profile Photo</h6>
                                </div>
                                <div class="card-body text-center">
                                    <img src="<?= userAvatar($user['avatar'] ?? null) ?>"
                                         alt="Avatar" id="avatarPreview"
                                         class="avatar-preview rounded-circle mb-3 shadow-sm">
                                    <div>
                                        <label class="btn btn-outline-primary btn-sm" for="avatarInput">
                                            <i data-feather="upload" style="width:14px;height:14px;" class="me-1"></i> Choose Photo
                                        </label>
                                        <input type="file" id="avatarInput" name="avatar"
                                               accept="image/jpeg,image/png,image/webp"
                                               class="d-none"
                                               onchange="previewAvatar(this)">
                                        <p class="text-muted small mt-2 mb-0">JPG, PNG or WEBP. Max 2MB.</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Role & Status -->
                            <div class="card border-0 shadow-sm mb-3">
                                <div class="card-header bg-transparent border-0 pt-3 pb-0">
                                    <h6 class="fw-semibold mb-0">Account Settings</h6>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold">Role</label>
                                        <select class="form-select" name="role"
                                                <?= $id === currentUserId() ? 'disabled' : '' ?>>
                                            <?php foreach (['tenant'=>'Tenant','landlord'=>'Landlord','agent'=>'Agent','admin'=>'Admin'] as $r => $label): ?>
                                                <option value="<?= $r ?>" <?= $user['role'] === $r ? 'selected' : '' ?>><?= $label ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if ($id === currentUserId()): ?>
                                            <input type="hidden" name="role" value="admin">
                                            <p class="text-muted small mt-1 mb-0">You cannot change your own role.</p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold">Status</label>
                                        <select class="form-select" name="status">
                                            <?php foreach (['active'=>'Active','pending'=>'Pending','suspended'=>'Suspended','inactive'=>'Inactive'] as $s => $label): ?>
                                                <option value="<?= $s ?>" <?= $user['status'] === $s ? 'selected' : '' ?>><?= $label ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="email_verified"
                                               id="emailVerified" <?= $user['email_verified'] ? 'checked' : '' ?>>
                                        <label class="form-check-label small" for="emailVerified">Email Verified</label>
                                    </div>
                                </div>
                            </div>

                            <!-- Change Password -->
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-transparent border-0 pt-3 pb-0">
                                    <h6 class="fw-semibold mb-0">Change Password</h6>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted small mb-3">Leave blank to keep current password.</p>
                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold">New Password</label>
                                        <input type="password" class="form-control" name="new_password"
                                               placeholder="Min. 8 characters" autocomplete="new-password">
                                    </div>
                                    <div>
                                        <label class="form-label small fw-semibold">Confirm Password</label>
                                        <input type="password" class="form-control" name="confirm_password"
                                               placeholder="Repeat new password" autocomplete="new-password">
                                    </div>
                                </div>
                            </div>

                        </div>

                        <!-- Right: Personal info -->
                        <div class="col-xl-8 col-lg-7">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-transparent border-0 pt-3 pb-0">
                                    <h6 class="fw-semibold mb-0">Personal Information</h6>
                                </div>
                                <div class="card-body">

                                    <div class="row g-3">

                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold">First Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="first_name"
                                                   value="<?= e($user['first_name']) ?>"
                                                   autocomplete="given-name" required>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold">Last Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="last_name"
                                                   value="<?= e($user['last_name']) ?>"
                                                   autocomplete="family-name" required>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold">Email Address <span class="text-danger">*</span></label>
                                            <input type="email" class="form-control" name="email"
                                                   value="<?= e($user['email']) ?>"
                                                   autocomplete="email" required>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold">Phone Number</label>
                                            <input type="tel" class="form-control" name="phone"
                                                   value="<?= e($user['phone'] ?? '') ?>"
                                                   autocomplete="tel">
                                        </div>

                                        <div class="col-12 pt-2">
                                            <hr class="mb-3">
                                            <div class="d-flex gap-2">
                                                <button type="submit" class="btn btn-primary">
                                                    <i data-feather="save" style="width:15px;height:15px;" class="me-1"></i> Save Changes
                                                </button>
                                                <a href="user-view.php?id=<?= $id ?>" class="btn btn-outline-secondary">Cancel</a>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>

                            <!-- Danger zone -->
                            <?php if ($id !== currentUserId() && $user['role'] !== 'admin'): ?>
                                <div class="card border-danger border-opacity-25 shadow-sm mt-3">
                                    <div class="card-header bg-transparent border-0 pt-3 pb-0">
                                        <h6 class="fw-semibold text-danger mb-0">Danger Zone</h6>
                                    </div>
                                    <div class="card-body">
                                        <p class="small text-muted mb-3">
                                            Deleting this user is permanent and cannot be undone.
                                            All their data will be removed.
                                        </p>
                                        <form method="POST" action="users.php"
                                              onsubmit="return confirm('Permanently delete this user?')">
                                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                            <input type="hidden" name="user_id" value="<?= $id ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <button type="submit" class="btn btn-danger btn-sm">
                                                <i data-feather="trash-2" style="width:14px;height:14px;" class="me-1"></i> Delete This User
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endif; ?>

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
    </div>

    <footer class="adminuiux-mobile-footer hide-on-scrolldown style-1">
        <div class="row gx-0 justify-content-around">
            <div class="col-auto"><a href="dashboard.php" class="nav-link text-center py-2 px-3"><i data-feather="grid" style="width:20px;height:20px;"></i><p class="mb-0" style="font-size:.65rem;">Home</p></a></div>
            <div class="col-auto"><a href="users.php" class="nav-link active text-center py-2 px-3"><i data-feather="users" style="width:20px;height:20px;"></i><p class="mb-0" style="font-size:.65rem;">Users</p></a></div>
            <div class="col-auto"><a href="properties.php" class="nav-link text-center py-2 px-3"><i data-feather="home" style="width:20px;height:20px;"></i><p class="mb-0" style="font-size:.65rem;">Properties</p></a></div>
            <div class="col-auto"><a href="settings.php" class="nav-link text-center py-2 px-3"><i data-feather="settings" style="width:20px;height:20px;"></i><p class="mb-0" style="font-size:.65rem;">Settings</p></a></div>
            <div class="col-auto"><a href="<?= BASE_URL ?>/logout.php" class="nav-link text-center py-2 px-3"><i data-feather="log-out" style="width:20px;height:20px;"></i><p class="mb-0" style="font-size:.65rem;">Logout</p></a></div>
        </div>
    </footer>

    <script>
        // Live avatar preview
        function previewAvatar(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = e => document.getElementById('avatarPreview').src = e.target.result;
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>

</body>
</html>
