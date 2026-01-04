<?php
define('PAGE_TITLE', 'Returns Management');
require_once __DIR__ . '/functions.php';
require_login();

$message = get_flash('message');
$error = get_flash('error');

// Process new return
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_return') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Invalid CSRF token.');
        redirect('returns.php');
    }
    
    $order_id = (int)($_POST['order_id'] ?? 0);
    $return_items = json_decode($_POST['return_items'] ?? '[]', true);
    $refund_method = in_array($_POST['refund_method'] ?? '', ['cash', 'card', 'bank_transfer'], true) 
        ? $_POST['refund_method'] : 'cash';
    $reason = trim($_POST['reason'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    if ($order_id <= 0 || empty($return_items)) {
        set_flash('error', 'Invalid return data.');
        redirect('returns.php');
    }
    
    try {
        $pdo->beginTransaction();
        
        // Get order details
        $stmt = $pdo->prepare("SELECT customer_id, total_amount FROM orders WHERE id = ?");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch();
        
        if (!$order) {
            throw new Exception('Order not found.');
        }
        
        // Calculate total refund
        $total_refund = 0;
        $validated_items = [];
        
        foreach ($return_items as $item) {
            $product_id = (int)($item['product_id'] ?? 0);
            $quantity = (int)($item['quantity'] ?? 0);
            $condition = $item['condition'] ?? 'good';
            
            if ($product_id <= 0 || $quantity <= 0) continue;
            
            // Get original order item
            $stmt = $pdo->prepare("
                SELECT oi.unit_price, p.name 
                FROM order_items oi 
                JOIN products p ON p.id = oi.product_id
                WHERE oi.order_id = ? AND oi.product_id = ?
            ");
            $stmt->execute([$order_id, $product_id]);
            $orderItem = $stmt->fetch();
            
            if (!$orderItem) continue;
            
            $unit_price = (float)$orderItem['unit_price'];
            $line_total = $unit_price * $quantity;
            $total_refund += $line_total;
            
            $validated_items[] = [
                'product_id' => $product_id,
                'quantity' => $quantity,
                'unit_price' => $unit_price,
                'total_price' => $line_total,
                'condition' => $condition
            ];
        }
        
        if (empty($validated_items)) {
            throw new Exception('No valid items to return.');
        }
        
        // Generate return number
        $return_number = 'RET-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // Create return record
        $stmt = $pdo->prepare("
            INSERT INTO returns (order_id, return_number, customer_id, total_refund, refund_method, reason, status, processed_by, notes) 
            VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?)
        ");
        $stmt->execute([
            $order_id,
            $return_number,
            $order['customer_id'],
            $total_refund,
            $refund_method,
            $reason,
            current_user()['id'],
            $notes
        ]);
        
        $return_id = (int)$pdo->lastInsertId();
        
        // Insert return items
        $itemStmt = $pdo->prepare("
            INSERT INTO return_items (return_id, product_id, quantity, unit_price, total_price, condition_status) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($validated_items as $item) {
            $itemStmt->execute([
                $return_id,
                $item['product_id'],
                $item['quantity'],
                $item['unit_price'],
                $item['total_price'],
                $item['condition']
            ]);
        }
        
        // Mark order as having return
        $pdo->prepare("UPDATE orders SET has_return = TRUE WHERE id = ?")->execute([$order_id]);
        
        $pdo->commit();
        set_flash('message', "Return {$return_number} created successfully! Please approve to restore stock.");
        redirect('returns.php?id=' . $return_id);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        set_flash('error', $e->getMessage());
        redirect('returns.php');
    }
}

// Approve return (restore stock and mark as completed)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'approve_return') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Invalid CSRF token.');
        redirect('returns.php');
    }
    
    $return_id = (int)($_POST['return_id'] ?? 0);
    
    if ($return_id > 0) {
        try {
            $pdo->beginTransaction();
            
            // Get return items
            $stmt = $pdo->prepare("
                SELECT product_id, quantity, condition_status 
                FROM return_items 
                WHERE return_id = ?
            ");
            $stmt->execute([$return_id]);
            $items = $stmt->fetchAll();
            
            // Restore stock (only for good condition items)
            $stockStmt = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?");
            foreach ($items as $item) {
                if ($item['condition_status'] === 'good' || $item['condition_status'] === 'damaged') {
                    $stockStmt->execute([$item['quantity'], $item['product_id']]);
                }
            }
            
            // Update return status
            $pdo->prepare("UPDATE returns SET status = 'approved' WHERE id = ?")->execute([$return_id]);
            
            $pdo->commit();
            set_flash('message', 'Return approved and stock restored successfully!');
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            set_flash('error', 'Failed to approve return: ' . $e->getMessage());
        }
    }
    
    redirect('returns.php');
}

// Reject return
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reject_return') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Invalid CSRF token.');
        redirect('returns.php');
    }
    
    $return_id = (int)($_POST['return_id'] ?? 0);
    $reject_reason = trim($_POST['reject_reason'] ?? '');
    
    if ($return_id > 0) {
        try {
            $stmt = $pdo->prepare("UPDATE returns SET status = 'rejected', notes = CONCAT(IFNULL(notes, ''), '\nRejected: ', ?) WHERE id = ?");
            $stmt->execute([$reject_reason, $return_id]);
            set_flash('message', 'Return rejected successfully.');
        } catch (Exception $e) {
            set_flash('error', 'Failed to reject return.');
        }
    }
    
    redirect('returns.php');
}

// Filters
$status_filter = $_GET['status'] ?? '';
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$search = trim($_GET['search'] ?? '');

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// View single return
$viewId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$returnDetail = null;
$returnItems = [];

if ($viewId > 0) {
    $stmt = $pdo->prepare("
        SELECT r.*, o.invoice_number, c.name as customer_name, c.phone as customer_phone,
               u.username as processed_by_name
        FROM returns r
        LEFT JOIN orders o ON o.id = r.order_id
        LEFT JOIN customers c ON c.id = r.customer_id
        LEFT JOIN users u ON u.id = r.processed_by
        WHERE r.id = ?
    ");
    $stmt->execute([$viewId]);
    $returnDetail = $stmt->fetch();
    
    if ($returnDetail) {
        $itemsStmt = $pdo->prepare("
            SELECT ri.*, p.name as product_name, p.unit
            FROM return_items ri
            JOIN products p ON p.id = ri.product_id
            WHERE ri.return_id = ?
        ");
        $itemsStmt->execute([$viewId]);
        $returnItems = $itemsStmt->fetchAll();
    }
}

// Fetch returns list
$countSql = "SELECT COUNT(*) FROM returns r WHERE 1=1";
$params = [];
if ($status_filter) { $countSql .= " AND r.status = ?"; $params[] = $status_filter; }
if ($from) { $countSql .= " AND DATE(r.return_date) >= ?"; $params[] = $from; }
if ($to) { $countSql .= " AND DATE(r.return_date) <= ?"; $params[] = $to; }
if ($search) { $countSql .= " AND r.return_number LIKE ?"; $params[] = '%' . $search . '%'; }

$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalReturns = (int)$countStmt->fetchColumn();
$totalPages = (int)ceil($totalReturns / $perPage);

$sql = "SELECT r.*, o.invoice_number, c.name as customer_name, u.username as processed_by_name
        FROM returns r
        LEFT JOIN orders o ON o.id = r.order_id
        LEFT JOIN customers c ON c.id = r.customer_id
        LEFT JOIN users u ON u.id = r.processed_by
        WHERE 1=1";
$params = [];
if ($status_filter) { $sql .= " AND r.status = ?"; $params[] = $status_filter; }
if ($from) { $sql .= " AND DATE(r.return_date) >= ?"; $params[] = $from; }
if ($to) { $sql .= " AND DATE(r.return_date) <= ?"; $params[] = $to; }
if ($search) { $sql .= " AND r.return_number LIKE ?"; $params[] = '%' . $search . '%'; }
$sql .= " ORDER BY r.return_date DESC LIMIT ? OFFSET ?";

$stmt = $pdo->prepare($sql);
foreach ($params as $i => $param) {
    $stmt->bindValue($i + 1, $param);
}
$stmt->bindValue(count($params) + 1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
$stmt->execute();
$returns = $stmt->fetchAll();

// Stats
$stats = [
    'total_returns' => $totalReturns,
    'pending' => 0,
    'approved' => 0,
    'total_refund' => 0
];

try {
    $statsData = $pdo->query("
        SELECT 
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
            IFNULL(SUM(CASE WHEN status = 'approved' THEN total_refund ELSE 0 END), 0) as total_refunded
        FROM returns
    ")->fetch();
    
    $stats['pending'] = (int)$statsData['pending_count'];
    $stats['approved'] = (int)$statsData['approved_count'];
    $stats['total_refund'] = (float)$statsData['total_refunded'];
} catch (Exception $e) {}

require_once __DIR__ . '/includes/header.php';
?>

<style>
.status-pending { background: #fef3c7; color: #92400e; }
.status-approved { background: #d1fae5; color: #065f46; }
.status-rejected { background: #fee2e2; color: #991b1b; }
</style>

<div class="max-w-7xl mx-auto px-4 py-6">
  
  <?php if ($viewId > 0 && $returnDetail): ?>
    <!-- Return Detail View -->
    <div class="mb-6">
      <a href="returns.php" class="text-secondary hover:underline flex items-center gap-1 mb-4">
        ← Back to Returns
      </a>
      <h1 class="font-heading text-3xl font-bold text-primary flex items-center gap-2">
        🔄 Return Details
      </h1>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Return Info -->
      <div class="card bg-white shadow-lg">
        <div class="flex items-center justify-between mb-4">
          <h2 class="font-heading text-lg font-semibold">Return Information</h2>
          <span class="px-3 py-1 rounded-full text-xs font-bold uppercase status-<?= e($returnDetail['status']) ?>">
            <?= e($returnDetail['status']) ?>
          </span>
        </div>
        
        <div class="space-y-2 text-sm">
          <div><strong>Return #:</strong> <?= e($returnDetail['return_number']) ?></div>
          <div><strong>Order #:</strong> <?= e($returnDetail['invoice_number']) ?></div>
          <div><strong>Customer:</strong> <?= e($returnDetail['customer_name'] ?? 'Walk-in') ?></div>
          <div><strong>Date:</strong> <?= date('M j, Y g:i A', strtotime($returnDetail['return_date'])) ?></div>
          <div><strong>Processed By:</strong> <?= e($returnDetail['processed_by_name']) ?></div>
          <div><strong>Refund Method:</strong> <?= e(ucfirst(str_replace('_', ' ', $returnDetail['refund_method']))) ?></div>
          <div class="pt-2 border-t">
            <div class="text-xs text-gray-600">Total Refund</div>
            <div class="text-2xl font-bold text-danger"><?= format_currency($returnDetail['total_refund']) ?></div>
          </div>
        </div>

        <?php if ($returnDetail['reason']): ?>
          <div class="mt-4 p-3 bg-yellow-50 rounded">
            <div class="text-xs font-semibold text-gray-700 mb-1">Reason:</div>
            <div class="text-sm text-gray-800"><?= nl2br(e($returnDetail['reason'])) ?></div>
          </div>
        <?php endif; ?>

        <?php if ($returnDetail['notes']): ?>
          <div class="mt-2 p-3 bg-blue-50 rounded">
            <div class="text-xs font-semibold text-gray-700 mb-1">Notes:</div>
            <div class="text-sm text-gray-800"><?= nl2br(e($returnDetail['notes'])) ?></div>
          </div>
        <?php endif; ?>

        <?php if ($returnDetail['status'] === 'pending'): ?>
          <div class="mt-4 flex gap-2">
            <form method="post" class="flex-1">
              <?= csrf_input() ?>
              <input type="hidden" name="action" value="approve_return">
              <input type="hidden" name="return_id" value="<?= $viewId ?>">
              <button type="submit" class="btn-primary w-full" onclick="return confirm('Approve this return and restore stock?')">
                ✅ Approve
              </button>
            </form>
            <button onclick="openRejectModal(<?= $viewId ?>)" class="btn-danger flex-1">
              ❌ Reject
            </button>
          </div>
        <?php endif; ?>
      </div>

      <!-- Returned Items -->
      <div class="lg:col-span-2 card bg-white shadow-lg">
        <h2 class="font-heading text-lg font-semibold mb-4">Returned Items</h2>
        
        <table class="min-w-full text-sm">
          <thead>
            <tr class="bg-gray-50">
              <th class="th text-left">Product</th>
              <th class="th text-center">Qty</th>
              <th class="th text-right">Price</th>
              <th class="th text-right">Total</th>
              <th class="th text-center">Condition</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($returnItems as $item): ?>
              <tr class="hover:bg-gray-50">
                <td class="td">
                  <div class="font-semibold"><?= e($item['product_name']) ?></div>
                  <div class="text-xs text-gray-500"><?= e($item['unit']) ?></div>
                </td>
                <td class="td text-center font-semibold"><?= (int)$item['quantity'] ?></td>
                <td class="td text-right"><?= format_currency($item['unit_price']) ?></td>
                <td class="td text-right font-semibold text-danger"><?= format_currency($item['total_price']) ?></td>
                <td class="td text-center">
                  <span class="px-2 py-1 bg-gray-100 rounded text-xs capitalize">
                    <?= e($item['condition_status']) ?>
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  <?php else: ?>
    <!-- Returns List View -->
    <div class="mb-6">
      <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
        <div>
          <h1 class="font-heading text-3xl font-bold text-primary flex items-center gap-2">
            🔄 Returns Management
          </h1>
          <p class="text-sm text-gray-600 mt-1">Process product returns and refunds</p>
        </div>
        <a href="orders.php" class="btn-primary flex items-center gap-2">
          Create Return from Order →
        </a>
      </div>

      <!-- Stats -->
      <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-6">
        <div class="card bg-gradient-to-br from-primary/10 to-primary/5 border-l-4 border-primary">
          <div class="text-gray-600 text-sm">Total Returns</div>
          <div class="text-2xl font-bold text-primary"><?= $stats['total_returns'] ?></div>
        </div>
        <div class="card bg-gradient-to-br from-warning/10 to-warning/5 border-l-4 border-warning">
          <div class="text-gray-600 text-sm">Pending</div>
          <div class="text-2xl font-bold text-warning"><?= $stats['pending'] ?></div>
        </div>
        <div class="card bg-gradient-to-br from-success/10 to-success/5 border-l-4 border-success">
          <div class="text-gray-600 text-sm">Approved</div>
          <div class="text-2xl font-bold text-success"><?= $stats['approved'] ?></div>
        </div>
        <div class="card bg-gradient-to-br from-danger/10 to-danger/5 border-l-4 border-danger">
          <div class="text-gray-600 text-sm">Total Refunded</div>
          <div class="text-2xl font-bold text-danger"><?= format_currency($stats['total_refund']) ?></div>
        </div>
      </div>
    </div>

    <?php if ($message): ?>
      <div class="alert-success mb-4">✅ <?= e($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert-danger mb-4">❌ <?= e($error) ?></div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="card mb-6">
      <form method="get" class="grid grid-cols-1 md:grid-cols-5 gap-3">
        <div>
          <label class="label">Status</label>
          <select name="status" class="input">
            <option value="">All Status</option>
            <option value="pending" <?= $status_filter==='pending'?'selected':'' ?>>Pending</option>
            <option value="approved" <?= $status_filter==='approved'?'selected':'' ?>>Approved</option>
            <option value="rejected" <?= $status_filter==='rejected'?'selected':'' ?>>Rejected</option>
          </select>
        </div>
        <div>
          <label class="label">From</label>
          <input type="date" name="from" value="<?= e($from) ?>" class="input">
        </div>
        <div>
          <label class="label">To</label>
          <input type="date" name="to" value="<?= e($to) ?>" class="input">
        </div>
        <div>
          <label class="label">Search</label>
          <input type="text" name="search" value="<?= e($search) ?>" placeholder="Return number..." class="input">
        </div>
        <div class="flex items-end gap-2">
          <button type="submit" class="btn-primary flex-1">Filter</button>
          <?php if ($status_filter || $from || $to || $search): ?>
            <a href="returns.php" class="btn-secondary">Clear</a>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <!-- Returns Table -->
    <div class="card">
      <table class="min-w-full text-sm">
        <thead>
          <tr class="bg-gradient-to-r from-primary to-dark text-white">
            <th class="th text-left">Return #</th>
            <th class="th text-left">Order #</th>
            <th class="th text-left">Customer</th>
            <th class="th text-right">Refund Amount</th>
            <th class="th text-center">Method</th>
            <th class="th text-center">Status</th>
            <th class="th text-left">Date</th>
            <th class="th text-center">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($returns)): ?>
            <tr>
              <td colspan="8" class="td text-center py-12 text-gray-400">
                <div class="text-5xl mb-2">🔄</div>
                <div>No returns found</div>
              </td>
            </tr>
          <?php else: foreach ($returns as $r): ?>
            <tr class="hover:bg-gray-50">
              <td class="td font-mono text-xs font-bold text-primary"><?= e($r['return_number']) ?></td>
              <td class="td font-mono text-xs"><?= e($r['invoice_number']) ?></td>
              <td class="td"><?= e($r['customer_name'] ?? 'Walk-in') ?></td>
              <td class="td text-right font-bold text-danger"><?= format_currency($r['total_refund']) ?></td>
              <td class="td text-center capitalize text-xs"><?= e(str_replace('_', ' ', $r['refund_method'])) ?></td>
              <td class="td text-center">
                <span class="px-2 py-1 rounded-full text-xs font-bold uppercase status-<?= e($r['status']) ?>">
                  <?= e($r['status']) ?>
                </span>
              </td>
              <td class="td text-xs"><?= date('M j, Y', strtotime($r['return_date'])) ?></td>
              <td class="td text-center">
                <a href="returns.php?id=<?= (int)$r['id'] ?>" class="btn-secondary text-xs px-3 py-1">
                  View
                </a>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>

      <?php if ($totalPages > 1): ?>
        <div class="mt-4 flex justify-between items-center px-4 pb-4">
          <div class="text-sm text-gray-600">Page <?= $page ?> of <?= $totalPages ?></div>
          <div class="flex gap-2">
            <?php if ($page > 1): ?>
              <a href="?page=<?= $page-1 ?><?= $status_filter?'&status='.$status_filter:''?><?= $from?'&from='.$from:''?><?= $to?'&to='.$to:''?><?= $search?'&search='.urlencode($search):''?>" class="btn-secondary">← Prev</a>
            <?php endif; ?>
            <?php if ($page < $totalPages): ?>
              <a href="?page=<?= $page+1 ?><?= $status_filter?'&status='.$status_filter:''?><?= $from?'&from='.$from:''?><?= $to?'&to='.$to:''?><?= $search?'&search='.urlencode($search):''?>" class="btn-primary">Next →</a>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<!-- Reject Modal -->
<div id="rejectModal" class="modal fixed inset-0 bg-black/50 items-center justify-center p-4 z-50">
  <div class="bg-white rounded-lg p-6 max-w-md w-full">
    <h3 class="font-heading text-lg font-bold mb-4">Reject Return</h3>
    <form method="post">
      <?= csrf_input() ?>
      <input type="hidden" name="action" value="reject_return">
      <input type="hidden" name="return_id" id="rejectReturnId">
      <div class="mb-4">
        <label class="label">Rejection Reason</label>
        <textarea name="reject_reason" class="input" rows="4" required></textarea>
      </div>
      <div class="flex gap-2 justify-end">
        <button type="button" onclick="closeModal('rejectModal')" class="btn-secondary">Cancel</button>
        <button type="submit" class="btn-danger">Reject Return</button>
      </div>
    </form>
  </div>
</div>

<script>
function openRejectModal(returnId) {
  document.getElementById('rejectReturnId').value = returnId;
  openModal('rejectModal');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>