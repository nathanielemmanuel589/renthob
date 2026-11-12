<?php
/**
 * Admin - Commission Management
 * Approve and manage agent commissions
 * Rent Hob
 */
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('admin');

// Handle commission status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $commissionId = (int)$_POST['commission_id'];
    $action = $_POST['action'];
    
    if ($action === 'approve') {
        $stmt = $pdo->prepare('UPDATE agent_commissions SET status = "approved" WHERE id = ?');
        $stmt->execute([$commissionId]);
        setFlash('Commission approved successfully', 'success');
    } elseif ($action === 'pay') {
        $paymentMethod = $_POST['payment_method'] ?? 'bank_transfer';
        $stmt = $pdo->prepare('UPDATE agent_commissions SET status = "paid", payment_date = CURDATE(), payment_method = ? WHERE id = ?');
        $stmt->execute([$paymentMethod, $commissionId]);
        setFlash('Commission marked as paid', 'success');
    } elseif ($action === 'reject') {
        $stmt = $pdo->prepare('UPDATE agent_commissions SET status = "cancelled" WHERE id = ?');
        $stmt->execute([$commissionId]);
        setFlash('Commission rejected', 'success');
    }
    
    header('Location: commissions.php');
    exit;
}

// Get filter
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';

// Build query
$sql = "SELECT c.*,
        CONCAT(a.first_name, ' ', a.last_name) as agent_name,
        p.title as property_title,
        CONCAT(t.first_name, ' ', t.last_name) as tenant_name,
        CONCAT(l.first_name, ' ', l.last_name) as landlord_name
        FROM agent_commissions c
        LEFT JOIN users a ON a.id = c.agent_id
        LEFT JOIN properties p ON p.id = c.property_id
        LEFT JOIN users t ON t.id = c.tenant_id
        LEFT JOIN users l ON l.id = c.landlord_id
        WHERE 1=1";

$params = [];

if ($statusFilter) {
    $sql .= " AND c.status = ?";
    $params[] = $statusFilter;
}

$sql .= " ORDER BY c.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$commissions = $stmt->fetchAll();

// Get counts
$stmt = $pdo->query('
    SELECT status, COUNT(*) as count, SUM(commission_amount) as total
    FROM agent_commissions
    GROUP BY status
');
$stats = [];
while ($row = $stmt->fetch()) {
    $stats[$row['status']] = [
        'count' => $row['count'],
        'total' => $row['total']
    ];
}

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commission Management — Rent Hob Admin</title>
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/img/favicon.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <script defer src="<?= BASE_URL ?>/dashboard/assets/js/third-party.js"></script>
    <script defer src="<?= BASE_URL ?>/dashboard/assets/js/app.js"></script>
    <script defer src="<?= BASE_URL ?>/dashboard/assets/js/core.js"></script>
    <link href="<?= BASE_URL ?>/dashboard/assets/css/app.css" rel="stylesheet">
</head>
<body class="adminuiux-sidebar-standard theme-cyan" data-theme="theme-cyan">
    <div class="pageloader"><div class="container h-100"><div class="row justify-content-center align-items-center text-center h-100"><div class="col-auto"><img src="<?= BASE_URL ?>/dashboard/assets/img/logo-512.png" class="height-100 mb-3"><p class="h3">Rent Hob</p><div class="loader10 mx-auto"></div></div></div></div></div>
    
    <header class="adminuiux-header">
        <nav class="navbar navbar-expand-lg">
            <div class="container-fluid">
                <button class="btn btn-link sidebar-toggler" onclick="initSidebar()"><i data-feather="menu"></i></button>
                <a class="navbar-brand" href="dashboard.php">
                    <img src="<?= BASE_URL ?>/assets/img/logo-2.svg" style="max-height:32px;">
                    <div><span class="h4">Rent Hob</span><p>Admin Portal</p></div>
                </a>
                <div class="ms-auto">
                    <div class="dropdown">
                        <button class="btn btn-link dropdown-toggle" data-bs-toggle="dropdown">
                            <img src="<?= userAvatar($_SESSION['user_avatar'] ?? null) ?>" class="rounded-circle" width="34" height="34">
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li><a class="dropdown-item" href="settings.php">Settings</a></li>
                            <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/logout.php">Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>
    </header>

    <div class="adminuiux-wrap">
        <?php include '../includes/admin-sidebar.php'; ?>
        
        <main class="adminuiux-content has-sidebar">
            <div class="container-fluid mt-2">
                
                <div class="row mb-3">
                    <div class="col">
                        <h4 class="mb-1 fw-semibold">Commission Management</h4>
                        <p class="text-muted mb-0">Approve and manage agent commission payments</p>
                    </div>
                </div>

                <?php if ($flash): ?>
                    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
                        <?= e($flash['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Summary Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm bg-warning bg-opacity-10">
                            <div class="card-body">
                                <h6 class="text-muted small">Pending</h6>
                                <h4 class="mb-0"><?= formatCurrency($stats['pending']['total'] ?? 0) ?></h4>
                                <small class="text-muted"><?= $stats['pending']['count'] ?? 0 ?> transactions</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm bg-info bg-opacity-10">
                            <div class="card-body">
                                <h6 class="text-muted small">Approved</h6>
                                <h4 class="mb-0"><?= formatCurrency($stats['approved']['total'] ?? 0) ?></h4>
                                <small class="text-muted"><?= $stats['approved']['count'] ?? 0 ?> transactions</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm bg-success bg-opacity-10">
                            <div class="card-body">
                                <h6 class="text-muted small">Paid</h6>
                                <h4 class="mb-0"><?= formatCurrency($stats['paid']['total'] ?? 0) ?></h4>
                                <small class="text-muted"><?= $stats['paid']['count'] ?? 0 ?> transactions</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm bg-danger bg-opacity-10">
                            <div class="card-body">
                                <h6 class="text-muted small">Cancelled</h6>
                                <h4 class="mb-0"><?= formatCurrency($stats['cancelled']['total'] ?? 0) ?></h4>
                                <small class="text-muted"><?= $stats['cancelled']['count'] ?? 0 ?> transactions</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <div class="btn-group" role="group">
                            <a href="commissions.php" class="btn btn-<?= !$statusFilter ? 'primary' : 'outline-primary' ?> btn-sm">
                                All
                            </a>
                            <a href="commissions.php?status=pending" class="btn btn-<?= $statusFilter === 'pending' ? 'warning' : 'outline-warning' ?> btn-sm">
                                Pending (<?= $stats['pending']['count'] ?? 0 ?>)
                            </a>
                            <a href="commissions.php?status=approved" class="btn btn-<?= $statusFilter === 'approved' ? 'info' : 'outline-info' ?> btn-sm">
                                Approved (<?= $stats['approved']['count'] ?? 0 ?>)
                            </a>
                            <a href="commissions.php?status=paid" class="btn btn-<?= $statusFilter === 'paid' ? 'success' : 'outline-success' ?> btn-sm">
                                Paid (<?= $stats['paid']['count'] ?? 0 ?>)
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Commissions Table -->
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <?php if (empty($commissions)): ?>
                            <div class="text-center py-5">
                                <i data-feather="dollar-sign" style="width:64px;height:64px;" class="text-muted mb-3"></i>
                                <h5 class="text-muted">No Commissions Found</h5>
                                <p class="text-muted">Commission records will appear here</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Date</th>
                                            <th>Agent</th>
                                            <th>Property</th>
                                            <th>Tenant</th>
                                            <th>Transaction</th>
                                            <th>Commission</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($commissions as $commission): ?>
                                            <tr>
                                                <td><small><?= formatDate($commission['created_at'], 'M d, Y') ?></small></td>
                                                <td><small><?= e($commission['agent_name']) ?></small></td>
                                                <td><small><?= e(truncate($commission['property_title'] ?? 'N/A', 25)) ?></small></td>
                                                <td><small><?= e($commission['tenant_name'] ?? 'N/A') ?></small></td>
                                                <td>
                                                    <small><?= formatCurrency($commission['transaction_amount']) ?></small>
                                                    <br><small class="text-muted"><?= e($commission['transaction_type'] ?? '') ?></small>
                                                </td>
                                                <td>
                                                    <span class="fw-bold text-success"><?= formatCurrency($commission['commission_amount']) ?></span>
                                                    <br><small class="text-muted"><?= number_format($commission['commission_rate'], 1) ?>%</small>
                                                </td>
                                                <td><?= commissionStatusBadge($commission['status']) ?></td>
                                                <td>
                                                    <?php if ($commission['status'] === 'pending'): ?>
                                                        <div class="btn-group btn-group-sm">
                                                            <form method="POST" style="display:inline;">
                                                                <input type="hidden" name="commission_id" value="<?= $commission['id'] ?>">
                                                                <input type="hidden" name="action" value="approve">
                                                                <button type="submit" class="btn btn-outline-success" title="Approve">
                                                                    <i data-feather="check" style="width:14px;height:14px;"></i>
                                                                </button>
                                                            </form>
                                                            <form method="POST" style="display:inline;">
                                                                <input type="hidden" name="commission_id" value="<?= $commission['id'] ?>">
                                                                <input type="hidden" name="action" value="reject">
                                                                <button type="submit" class="btn btn-outline-danger" title="Reject">
                                                                    <i data-feather="x" style="width:14px;height:14px;"></i>
                                                                </button>
                                                            </form>
                                                        </div>
                                                    <?php elseif ($commission['status'] === 'approved'): ?>
                                                        <button class="btn btn-sm btn-outline-primary" onclick="markAsPaid(<?= $commission['id'] ?>)">
                                                            <i data-feather="check-circle" style="width:14px;height:14px;"></i> Mark Paid
                                                        </button>
                                                    <?php elseif ($commission['status'] === 'paid'): ?>
                                                        <small class="text-muted">
                                                            Paid on <?= formatDate($commission['payment_date'], 'M d, Y') ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- Mark as Paid Modal -->
    <div class="modal fade" id="markPaidModal" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="pay">
                    <input type="hidden" name="commission_id" id="pay_commission_id">
                    <div class="modal-header">
                        <h6 class="modal-title">Mark as Paid</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label">Payment Method</label>
                        <select name="payment_method" class="form-select">
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="cash">Cash</option>
                            <option value="check">Check</option>
                            <option value="mobile_money">Mobile Money</option>
                        </select>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-sm btn-success">Confirm Payment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    function markAsPaid(commissionId) {
        document.getElementById('pay_commission_id').value = commissionId;
        const modal = new bootstrap.Modal(document.getElementById('markPaidModal'));
        modal.show();
    }
    </script>
</body>
</html>
