<?php
/**
 * Agent - Bookings & Viewings
 * Manage property viewing schedules
 * Rent Hob
 */
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/agent-functions.php';

requireRole('agent');

$agentId = currentUserId();

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $bookingId = (int)$_POST['booking_id'];
    $action = $_POST['action'];
    
    // Verify this booking belongs to agent's property
    $stmt = $pdo->prepare('
        SELECT b.id
        FROM bookings b
        INNER JOIN agent_properties ap ON ap.property_id = b.property_id
        WHERE b.id = ? AND ap.agent_id = ? AND ap.status = "active"
    ');
    $stmt->execute([$bookingId, $agentId]);
    
    if ($stmt->fetch()) {
        if ($action === 'confirm') {
            $stmt = $pdo->prepare('UPDATE bookings SET status = "confirmed" WHERE id = ?');
            $stmt->execute([$bookingId]);
            setFlash('Booking confirmed successfully', 'success');
        } elseif ($action === 'complete') {
            $stmt = $pdo->prepare('UPDATE bookings SET status = "completed", visit_completed_at = NOW() WHERE id = ?');
            $stmt->execute([$bookingId]);
            setFlash('Booking marked as completed', 'success');
        } elseif ($action === 'cancel') {
            $reason = $_POST['cancel_reason'] ?? 'Cancelled by agent';
            $stmt = $pdo->prepare('UPDATE bookings SET status = "cancelled", cancellation_reason = ? WHERE id = ?');
            $stmt->execute([$reason, $bookingId]);
            setFlash('Booking cancelled', 'success');
        } elseif ($action === 'reschedule') {
            $newDate = $_POST['new_date'] ?? null;
            $newTime = $_POST['new_time'] ?? null;
            if ($newDate && $newTime) {
                $stmt = $pdo->prepare('UPDATE bookings SET visit_date = ?, visit_time = ?, status = "rescheduled" WHERE id = ?');
                $stmt->execute([$newDate, $newTime, $bookingId]);
                setFlash('Booking rescheduled successfully', 'success');
            }
        } elseif ($action === 'add_notes') {
            $notes = $_POST['notes'] ?? '';
            $stmt = $pdo->prepare('UPDATE bookings SET agent_notes = ? WHERE id = ?');
            $stmt->execute([$notes, $bookingId]);
            setFlash('Notes added successfully', 'success');
        }
    } else {
        setFlash('Invalid booking', 'error');
    }
    
    header('Location: bookings.php');
    exit;
}

// Get filter
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$dateFilter = isset($_GET['date']) ? $_GET['date'] : '';

// Build query - only bookings for agent's assigned properties
$sql = "SELECT b.*, 
        u.first_name, u.last_name, u.email, u.phone, u.avatar,
        p.id as property_id, p.title as property_title, p.address as property_address,
        (SELECT image_path FROM property_images WHERE property_id = p.id ORDER BY id ASC LIMIT 1) as property_image
        FROM bookings b
        INNER JOIN users u ON u.id = b.user_id
        INNER JOIN properties p ON p.id = b.property_id
        INNER JOIN agent_properties ap ON ap.property_id = p.id
        WHERE ap.agent_id = ? AND ap.status = 'active'";

$params = [$agentId];

if ($statusFilter) {
    $sql .= " AND b.status = ?";
    $params[] = $statusFilter;
}

if ($dateFilter === 'today') {
    $sql .= " AND DATE(b.visit_date) = CURDATE()";
} elseif ($dateFilter === 'tomorrow') {
    $sql .= " AND DATE(b.visit_date) = DATE_ADD(CURDATE(), INTERVAL 1 DAY)";
} elseif ($dateFilter === 'week') {
    $sql .= " AND b.visit_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
} elseif ($dateFilter === 'past') {
    $sql .= " AND DATE(b.visit_date) < CURDATE()";
}

$sql .= " ORDER BY b.visit_date ASC, b.visit_time ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

// Count by status
$stmt = $pdo->prepare('
    SELECT b.status, COUNT(*) as count
    FROM bookings b
    INNER JOIN agent_properties ap ON ap.property_id = b.property_id
    WHERE ap.agent_id = ? AND ap.status = "active"
    GROUP BY b.status
');
$stmt->execute([$agentId]);
$statusCounts = [];
while ($row = $stmt->fetch()) {
    $statusCounts[$row['status']] = $row['count'];
}

// Count upcoming (next 7 days)
$stmt = $pdo->prepare('
    SELECT COUNT(*) as count
    FROM bookings b
    INNER JOIN agent_properties ap ON ap.property_id = b.property_id
    WHERE ap.agent_id = ? AND ap.status = "active"
    AND b.visit_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    AND b.status NOT IN ("cancelled", "completed")
');
$stmt->execute([$agentId]);
$upcomingCount = (int)$stmt->fetchColumn();

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Bookings & Viewings — Rent Hob Agent</title>
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
    <header class="adminuiux-header inner-page"><nav class="navbar navbar-expand-lg fixed-top"><div class="container-fluid"><button class="btn btn-link btn-square sidebar-toggler ms-1" type="button" onclick="initSidebar()"><i class="sidebar-svg" data-feather="menu"></i></button><a class="navbar-brand" href="dashboard.php"><img data-bs-img="light" src="<?= BASE_URL ?>/assets/img/logo-2.svg" alt="Rent Hob" style="max-height:32px;"><img data-bs-img="dark" src="<?= BASE_URL ?>/assets/img/logo-2.svg" alt="Rent Hob" style="max-height:32px;"><div class="lh-20"><span class="h4">Rent Hob</span><p class="company-tagline">Agent Portal</p></div></a><div class="ms-auto d-flex align-items-center gap-1"><div class="dropdown"><button class="btn btn-link no-caret dropdown-toggle d-flex align-items-center gap-2 px-2" type="button" data-bs-toggle="dropdown"><img src="<?= userAvatar($_SESSION['user_avatar'] ?? null) ?>" alt="Agent" class="rounded-circle" width="34" height="34" style="object-fit:cover;"><span class="d-none d-md-inline small fw-semibold"><?= e($_SESSION['user_name']) ?></span></button><ul class="dropdown-menu dropdown-menu-end"><li><a class="dropdown-item" href="profile.php"><i data-feather="user" class="me-2" style="width:15px;height:15px;"></i> My Profile</a></li><li><a class="dropdown-item" href="settings.php"><i data-feather="settings" class="me-2" style="width:15px;height:15px;"></i> Settings</a></li><li><hr class="dropdown-divider"></li><li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/logout.php"><i data-feather="log-out" class="me-2" style="width:15px;height:15px;"></i> Logout</a></li></ul></div></div></div></nav></header>
    <div class="adminuiux-wrap">
        <?php include '../includes/agent-sidebar.php'; ?>
        <main class="adminuiux-content has-sidebar" onclick="contentClick()">
            <div class="container mt-2" id="main-content">
                
                <div class="row mb-3">
                    <div class="col">
                        <h4 class="mb-1 fw-semibold">Bookings & Viewings</h4>
                        <p class="text-muted mb-0">Manage property viewing schedules</p>
                    </div>
                    <div class="col-auto align-self-center">
                        <span class="badge bg-warning text-dark px-3 py-2">
                            <i data-feather="calendar" style="width:14px;height:14px;" class="me-1"></i>
                            <?= $upcomingCount ?> Upcoming This Week
                        </span>
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
                                <label class="form-label small text-muted mb-2">Filter by Status</label>
                                <div class="btn-group w-100" role="group">
                                    <a href="bookings.php" class="btn btn-<?= !$statusFilter ? 'primary' : 'outline-primary' ?> btn-sm">
                                        All (<?= array_sum($statusCounts) ?>)
                                    </a>
                                    <a href="bookings.php?status=pending" class="btn btn-<?= $statusFilter === 'pending' ? 'warning' : 'outline-warning' ?> btn-sm">
                                        Pending (<?= $statusCounts['pending'] ?? 0 ?>)
                                    </a>
                                    <a href="bookings.php?status=confirmed" class="btn btn-<?= $statusFilter === 'confirmed' ? 'success' : 'outline-success' ?> btn-sm">
                                        Confirmed (<?= $statusCounts['confirmed'] ?? 0 ?>)
                                    </a>
                                    <a href="bookings.php?status=completed" class="btn btn-<?= $statusFilter === 'completed' ? 'primary' : 'outline-primary' ?> btn-sm">
                                        Completed (<?= $statusCounts['completed'] ?? 0 ?>)
                                    </a>
                                    <a href="bookings.php?status=cancelled" class="btn btn-<?= $statusFilter === 'cancelled' ? 'danger' : 'outline-danger' ?> btn-sm">
                                        Cancelled (<?= $statusCounts['cancelled'] ?? 0 ?>)
                                    </a>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small text-muted mb-2">Filter by Date</label>
                                <div class="btn-group w-100" role="group">
                                    <a href="bookings.php" class="btn btn-<?= !$dateFilter ? 'secondary' : 'outline-secondary' ?> btn-sm">
                                        All Dates
                                    </a>
                                    <a href="bookings.php?date=today" class="btn btn-<?= $dateFilter === 'today' ? 'info' : 'outline-info' ?> btn-sm">
                                        Today
                                    </a>
                                    <a href="bookings.php?date=tomorrow" class="btn btn-<?= $dateFilter === 'tomorrow' ? 'info' : 'outline-info' ?> btn-sm">
                                        Tomorrow
                                    </a>
                                    <a href="bookings.php?date=week" class="btn btn-<?= $dateFilter === 'week' ? 'warning' : 'outline-warning' ?> btn-sm">
                                        This Week
                                    </a>
                                    <a href="bookings.php?date=past" class="btn btn-<?= $dateFilter === 'past' ? 'secondary' : 'outline-secondary' ?> btn-sm">
                                        Past
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <?php if (empty($bookings)): ?>
                            <div class="text-center py-5">
                                <i data-feather="calendar" style="width:64px;height:64px;" class="text-muted mb-3"></i>
                                <h5 class="text-muted">No Bookings</h5>
                                <p class="text-muted">Property viewing bookings will appear here.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Visitor</th>
                                            <th>Property</th>
                                            <th>Visit Date & Time</th>
                                            <th>Status</th>
                                            <th>Notes</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($bookings as $booking): ?>
                                            <?php 
                                            $visitDate = strtotime($booking['visit_date']);
                                            $isToday = date('Y-m-d', $visitDate) === date('Y-m-d');
                                            $isTomorrow = date('Y-m-d', $visitDate) === date('Y-m-d', strtotime('+1 day'));
                                            $isPast = $visitDate < strtotime('today');
                                            ?>
                                            <tr class="<?= $isToday ? 'table-warning' : '' ?>">
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <img src="<?= userAvatar($booking['avatar']) ?>" class="rounded-circle" width="40" height="40" alt="" style="object-fit:cover;">
                                                        <div>
                                                            <p class="mb-0 fw-semibold small"><?= e($booking['first_name'] . ' ' . $booking['last_name']) ?></p>
                                                            <small class="text-muted"><?= e($booking['phone']) ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <?php if ($booking['property_image']): ?>
                                                            <img src="<?= BASE_URL ?>/<?= e($booking['property_image']) ?>" class="rounded" width="50" height="40" alt="" style="object-fit:cover;">
                                                        <?php endif; ?>
                                                        <div>
                                                            <a href="property-view.php?id=<?= $booking['property_id'] ?>" class="text-decoration-none small">
                                                                <?= e(truncate($booking['property_title'], 30)) ?>
                                                            </a>
                                                            <br><small class="text-muted"><?= e(truncate($booking['property_address'], 30)) ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div>
                                                        <p class="mb-0 small fw-semibold">
                                                            <?= formatDate($booking['visit_date'], 'D, M d, Y') ?>
                                                            <?php if ($isToday): ?>
                                                                <span class="badge bg-warning text-dark ms-1">Today</span>
                                                            <?php elseif ($isTomorrow): ?>
                                                                <span class="badge bg-info ms-1">Tomorrow</span>
                                                            <?php elseif ($isPast): ?>
                                                                <span class="badge bg-secondary ms-1">Past</span>
                                                            <?php endif; ?>
                                                        </p>
                                                        <small class="text-muted">
                                                            <i data-feather="clock" style="width:12px;height:12px;"></i>
                                                            <?= date('g:i A', strtotime($booking['visit_time'])) ?>
                                                        </small>
                                                    </div>
                                                </td>
                                                <td><?= bookingStatusBadge($booking['status']) ?></td>
                                                <td>
                                                    <?php if (isset($booking['agent_notes']) && $booking['agent_notes']): ?>
                                                        <small class="text-muted" title="<?= e($booking['agent_notes']) ?>">
                                                            <?= e(truncate($booking['agent_notes'], 30)) ?>
                                                        </small>
                                                    <?php else: ?>
                                                        <small class="text-muted">No notes</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="dropdown">
                                                        <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                            <i data-feather="more-vertical" style="width:14px;height:14px;"></i>
                                                        </button>
                                                        <ul class="dropdown-menu">
                                                            <li><h6 class="dropdown-header">Booking Actions</h6></li>
                                                            <?php if ($booking['status'] === 'pending'): ?>
                                                                <li>
                                                                    <form method="POST" style="display:inline;">
                                                                        <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                                                        <input type="hidden" name="action" value="confirm">
                                                                        <button type="submit" class="dropdown-item">
                                                                            <i data-feather="check-circle" class="me-2" style="width:14px;height:14px;"></i> Confirm Booking
                                                                        </button>
                                                                    </form>
                                                                </li>
                                                            <?php endif; ?>
                                                            <?php if (in_array($booking['status'], ['pending', 'confirmed'])): ?>
                                                                <li>
                                                                    <button class="dropdown-item" onclick="rescheduleBooking(<?= $booking['id'] ?>)">
                                                                        <i data-feather="calendar" class="me-2" style="width:14px;height:14px;"></i> Reschedule
                                                                    </button>
                                                                </li>
                                                            <?php endif; ?>
                                                            <?php if ($booking['status'] === 'confirmed' && !$isPast): ?>
                                                                <li>
                                                                    <form method="POST" style="display:inline;">
                                                                        <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                                                        <input type="hidden" name="action" value="complete">
                                                                        <button type="submit" class="dropdown-item">
                                                                            <i data-feather="check" class="me-2" style="width:14px;height:14px;"></i> Mark Completed
                                                                        </button>
                                                                    </form>
                                                                </li>
                                                            <?php endif; ?>
                                                            <li>
                                                                <button class="dropdown-item" onclick="addNotes(<?= $booking['id'] ?>, '<?= e($booking['agent_notes'] ?? '') ?>')">
                                                                    <i data-feather="edit" class="me-2" style="width:14px;height:14px;"></i> Add/Edit Notes
                                                                </button>
                                                            </li>
                                                            <?php if (!in_array($booking['status'], ['cancelled', 'completed'])): ?>
                                                                <li><hr class="dropdown-divider"></li>
                                                                <li>
                                                                    <button class="dropdown-item text-danger" onclick="cancelBooking(<?= $booking['id'] ?>)">
                                                                        <i data-feather="x-circle" class="me-2" style="width:14px;height:14px;"></i> Cancel Booking
                                                                    </button>
                                                                </li>
                                                            <?php endif; ?>
                                                            <li><hr class="dropdown-divider"></li>
                                                            <li><h6 class="dropdown-header">Contact Visitor</h6></li>
                                                            <li>
                                                                <a class="dropdown-item" href="tel:<?= e($booking['phone']) ?>">
                                                                    <i data-feather="phone" class="me-2" style="width:14px;height:14px;"></i> Call
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a class="dropdown-item" href="mailto:<?= e($booking['email']) ?>">
                                                                    <i data-feather="mail" class="me-2" style="width:14px;height:14px;"></i> Email
                                                                </a>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
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

    <script>
    function rescheduleBooking(bookingId) {
        const newDate = prompt('Enter new date (YYYY-MM-DD):');
        if (newDate) {
            const newTime = prompt('Enter new time (HH:MM):');
            if (newTime) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="booking_id" value="${bookingId}">
                    <input type="hidden" name="action" value="reschedule">
                    <input type="hidden" name="new_date" value="${newDate}">
                    <input type="hidden" name="new_time" value="${newTime}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    }

    function cancelBooking(bookingId) {
        const reason = prompt('Reason for cancellation:');
        if (reason !== null) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="booking_id" value="${bookingId}">
                <input type="hidden" name="action" value="cancel">
                <input type="hidden" name="cancel_reason" value="${reason}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }

    function addNotes(bookingId, currentNotes) {
        const notes = prompt('Add/Edit notes:', currentNotes);
        if (notes !== null) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="booking_id" value="${bookingId}">
                <input type="hidden" name="action" value="add_notes">
                <input type="hidden" name="notes" value="${notes}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }
    </script>
</body>
</html>
