<?php
define('PAGE_TITLE', 'Orders Management');
require_once __DIR__ . '/functions.php';
require_login();

$message = get_flash('message');
$error = get_flash('error');

// Delete order handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Invalid CSRF token.');
        redirect('orders.php');
    }
    
    $id = (int)($_POST['id'] ?? 0);
    
    if ($id > 0) {
        try {
            $pdo->beginTransaction();
            
            // Check if order has returns
            $returnCheck = $pdo->prepare("SELECT COUNT(*) FROM returns WHERE order_id = ?");
            $returnCheck->execute([$id]);
            $hasReturns = (int)$returnCheck->fetchColumn() > 0;
            
            if ($hasReturns) {
                throw new Exception('Cannot delete order with returns. Please delete returns first.');
            }
            
            // Get order items to restore stock
            $stmt = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
            $stmt->execute([$id]);
            $items = $stmt->fetchAll();
            
            // Restore stock for each product
            $stockStmt = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?");
            foreach ($items as $item) {
                $stockStmt->execute([$item['quantity'], $item['product_id']]);
            }
            
            // Delete order items
            $stmt = $pdo->prepare("DELETE FROM order_items WHERE order_id = ?");
            $stmt->execute([$id]);
            
            // Delete order
            $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
            $stmt->execute([$id]);
            
            $pdo->commit();
            set_flash('message', 'Order deleted successfully and stock restored.');
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            set_flash('error', 'Could not delete order: ' . $e->getMessage());
        }
    }
    
    redirect('orders.php');
}

// Filters
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$payment = $_GET['payment'] ?? '';
$status = $_GET['status'] ?? '';
$has_return = $_GET['has_return'] ?? '';
$q = trim($_GET['q'] ?? '');

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

// Export handlers
if (isset($_GET['export']) && in_array($_GET['export'], ['csv','pdf'], true)) {
    $sql = "SELECT o.id, o.invoice_number, c.name AS customer_name, o.total_amount, o.payment_method, o.order_date, o.status, o.has_return,
            (SELECT SUM(total_refund) FROM returns r WHERE r.order_id = o.id AND r.status = 'approved') as total_refunded
            FROM orders o LEFT JOIN customers c ON c.id = o.customer_id WHERE 1=1";
    $params = [];
    if ($from !== '') { $sql .= " AND DATE(o.order_date) >= ?"; $params[] = $from; }
    if ($to !== '') { $sql .= " AND DATE(o.order_date) <= ?"; $params[] = $to; }
    if ($payment !== '') { $sql .= " AND o.payment_method = ?"; $params[] = $payment; }
    if ($status !== '') { $sql .= " AND o.status = ?"; $params[] = $status; }
    if ($has_return !== '') { $sql .= " AND o.has_return = ?"; $params[] = $has_return === 'yes' ? 1 : 0; }
    if ($q !== '') { $sql .= " AND (o.invoice_number LIKE ? OR c.name LIKE ?)"; $params[] = '%'.$q.'%'; $params[] = '%'.$q.'%'; }
    $sql .= " ORDER BY o.order_date DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    if ($_GET['export'] === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="orders_' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Invoice', 'Customer', 'Gross Amount', 'Refunded', 'Net Amount', 'Payment', 'Status', 'Has Return', 'Date']);
        foreach ($rows as $r) {
            $refunded = (float)($r['total_refunded'] ?? 0);
            $net = (float)$r['total_amount'] - $refunded;
            fputcsv($out, [
                $r['invoice_number'], 
                $r['customer_name'] ?? 'Walk-in', 
                $r['total_amount'],
                $refunded,
                $net,
                $r['payment_method'], 
                $r['status'],
                $r['has_return'] ? 'Yes' : 'No',
                $r['order_date']
            ]);
        }
        fclose($out); 
        exit;
    }
}

// Count total
$countSql = "SELECT COUNT(*) FROM orders o LEFT JOIN customers c ON c.id = o.customer_id WHERE 1=1";
$params = [];
if ($from !== '') { $countSql .= " AND DATE(o.order_date) >= ?"; $params[] = $from; }
if ($to !== '') { $countSql .= " AND DATE(o.order_date) <= ?"; $params[] = $to; }
if ($payment !== '') { $countSql .= " AND o.payment_method = ?"; $params[] = $payment; }
if ($status !== '') { $countSql .= " AND o.status = ?"; $params[] = $status; }
if ($has_return !== '') { $countSql .= " AND o.has_return = ?"; $params[] = $has_return === 'yes' ? 1 : 0; }
if ($q !== '') { $countSql .= " AND (o.invoice_number LIKE ? OR c.name LIKE ?)"; $params[] = '%'.$q.'%'; $params[] = '%'.$q.'%'; }

$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalOrders = (int)$countStmt->fetchColumn();
$totalPages = (int)ceil($totalOrders / $perPage);

// Fetch orders with return info
$sql = "SELECT o.id, o.invoice_number, o.has_return, c.name AS customer_name, c.phone AS customer_phone, 
        o.total_amount, o.payment_method, o.order_date, o.status,
        (SELECT COUNT(*) FROM returns r WHERE r.order_id = o.id) as return_count,
        (SELECT SUM(total_refund) FROM returns r WHERE r.order_id = o.id AND r.status = 'approved') as total_refunded
        FROM orders o LEFT JOIN customers c ON c.id = o.customer_id WHERE 1=1";
$params = [];
if ($from !== '') { $sql .= " AND DATE(o.order_date) >= ?"; $params[] = $from; }
if ($to !== '') { $sql .= " AND DATE(o.order_date) <= ?"; $params[] = $to; }
if ($payment !== '') { $sql .= " AND o.payment_method = ?"; $params[] = $payment; }
if ($status !== '') { $sql .= " AND o.status = ?"; $params[] = $status; }
if ($has_return !== '') { $sql .= " AND o.has_return = ?"; $params[] = $has_return === 'yes' ? 1 : 0; }
if ($q !== '') { $sql .= " AND (o.invoice_number LIKE ? OR c.name LIKE ?)"; $params[] = '%'.$q.'%'; $params[] = '%'.$q.'%'; }
$sql .= " ORDER BY o.order_date DESC LIMIT ? OFFSET ?";

$stmt = $pdo->prepare($sql);
foreach ($params as $i => $param) {
    $stmt->bindValue($i + 1, $param);
}
$stmt->bindValue(count($params) + 1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
$stmt->execute();
$orders = $stmt->fetchAll();

// Calculate stats - with NET revenue (gross - refunded)
$stats = [
    'total_revenue_gross' => 0,
    'total_revenue_net' => 0,
    'total_orders' => $totalOrders,
    'cash_orders' => 0,
    'card_orders' => 0,
    'returned_orders' => 0,
    'total_refunded' => 0,
];

try {
    $statsQuery = "SELECT 
        IFNULL(SUM(total_amount), 0) as total_revenue,
        SUM(CASE WHEN payment_method = 'cash' THEN 1 ELSE 0 END) as cash_count,
        SUM(CASE WHEN payment_method = 'card' THEN 1 ELSE 0 END) as card_count,
        SUM(CASE WHEN has_return = 1 THEN 1 ELSE 0 END) as return_count
        FROM orders WHERE 1=1";
    
    $statsParams = [];
    if ($from !== '') { $statsQuery .= " AND DATE(order_date) >= ?"; $statsParams[] = $from; }
    if ($to !== '') { $statsQuery .= " AND DATE(order_date) <= ?"; $statsParams[] = $to; }
    
    $statsStmt = $pdo->prepare($statsQuery);
    $statsStmt->execute($statsParams);
    $statsData = $statsStmt->fetch();
    
    $stats['total_revenue_gross'] = (float)$statsData['total_revenue'];
    $stats['cash_orders'] = (int)$statsData['cash_count'];
    $stats['card_orders'] = (int)$statsData['card_count'];
    $stats['returned_orders'] = (int)$statsData['return_count'];
    
    // Get total refunded
    $refundQuery = "SELECT IFNULL(SUM(total_refund), 0) FROM returns WHERE status = 'approved'";
    $refundParams = [];
    if ($from !== '') { $refundQuery .= " AND DATE(return_date) >= ?"; $refundParams[] = $from; }
    if ($to !== '') { $refundQuery .= " AND DATE(return_date) <= ?"; $refundParams[] = $to; }
    
    $refundStmt = $pdo->prepare($refundQuery);
    $refundStmt->execute($refundParams);
    $stats['total_refunded'] = (float)$refundStmt->fetchColumn();
    
    // Calculate NET revenue
    $stats['total_revenue_net'] = $stats['total_revenue_gross'] - $stats['total_refunded'];
    
} catch (Exception $e) {
    // Keep default values
}

// Include header
require_once __DIR__ . '/includes/header.php';
?>

<!-- Page Specific Styles -->
<style>
.status-badge {
    padding: 4px 12px;
    border-radius: 9999px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}
.status-completed { background: #d1fae5; color: #065f46; }
.status-pending { background: #fef3c7; color: #92400e; }
.status-cancelled { background: #fee2e2; color: #991b1b; }
.has-return-row {
    background: linear-gradient(90deg, #fef3c7 0%, #ffffff 100%);
    border-left: 4px solid #F59E0B;
}
.return-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: #FEF3C7;
    color: #92400E;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 700;
}
</style>

<!-- Orders Content -->
<div class="max-w-7xl mx-auto px-4 py-6">
  <!-- Header -->
  <div class="mb-6">
    <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
      <div>
        <h1 class="font-heading text-3xl font-bold text-primary flex items-center gap-2">
          📋 Orders Management
        </h1>
        <p class="text-sm text-gray-600 mt-1">View, filter, and manage all orders</p>
      </div>
      <div class="flex items-center gap-2">
        <a href="returns.php" class="bg-warning/10 text-warning hover:bg-warning hover:text-white px-4 py-2 rounded-lg transition font-semibold flex items-center gap-2">
          🔄 Manage Returns
        </a>
        <a class="btn-secondary flex items-center gap-2" href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>">
          📄 Export CSV
        </a>
        <a href="pos.php" class="btn-primary flex items-center gap-2">
          ➕ New Sale
        </a>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
      <div class="card bg-gradient-to-br from-primary/10 to-primary/5 border-l-4 border-primary hover:shadow-lg transition">
        <div class="text-gray-600 text-sm font-medium">Total Orders</div>
        <div class="text-2xl font-bold text-primary mt-1"><?= number_format($stats['total_orders']) ?></div>
        <div class="text-xs text-gray-500 mt-1">📊 Filtered results</div>
      </div>
      <div class="card bg-gradient-to-br from-success/10 to-success/5 border-l-4 border-success hover:shadow-lg transition">
        <div class="text-gray-600 text-sm font-medium">NET Revenue</div>
        <div class="text-2xl font-bold text-success mt-1"><?= format_currency($stats['total_revenue_net']) ?></div>
        <div class="text-xs text-gray-500 mt-1">
          <div>Gross: <?= format_currency($stats['total_revenue_gross']) ?></div>
          <?php if ($stats['total_refunded'] > 0): ?>
            <div class="text-danger">Refunded: -<?= format_currency($stats['total_refunded']) ?></div>
          <?php endif; ?>
        </div>
      </div>
      <div class="card bg-gradient-to-br from-secondary/10 to-secondary/5 border-l-4 border-secondary hover:shadow-lg transition">
        <div class="text-gray-600 text-sm font-medium">Cash Payments</div>
        <div class="text-2xl font-bold text-secondary mt-1"><?= number_format($stats['cash_orders']) ?></div>
        <div class="text-xs text-gray-500 mt-1">💵 Cash orders</div>
      </div>
      <div class="card bg-gradient-to-br from-warning/10 to-warning/5 border-l-4 border-warning hover:shadow-lg transition">
        <div class="text-gray-600 text-sm font-medium">Returned Orders</div>
        <div class="text-2xl font-bold text-warning mt-1"><?= number_format($stats['returned_orders']) ?></div>
        <div class="text-xs text-gray-500 mt-1">🔄 Has returns</div>
      </div>
      <div class="card bg-gradient-to-br from-danger/10 to-danger/5 border-l-4 border-danger hover:shadow-lg transition">
        <div class="text-gray-600 text-sm font-medium">Total Refunded</div>
        <div class="text-2xl font-bold text-danger mt-1"><?= format_currency($stats['total_refunded']) ?></div>
        <div class="text-xs text-gray-500 mt-1">💸 Approved returns</div>
      </div>
    </div>
  </div>

  <!-- Flash Messages -->
  <?php if ($message): ?>
    <div class="alert-success mb-4 flex items-center gap-2 animate-fade-in">
      <span class="text-xl">✅</span>
      <span><?= e($message) ?></span>
    </div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert-danger mb-4 flex items-center gap-2 animate-fade-in">
      <span class="text-xl">❌</span>
      <span><?= e($error) ?></span>
    </div>
  <?php endif; ?>

  <!-- Filters -->
  <div class="card mb-6 shadow-md">
    <h2 class="font-heading text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
      🔍 Filter Orders
    </h2>
    <form method="get" class="grid grid-cols-1 md:grid-cols-6 gap-3">
      <div>
        <label class="label font-medium">From Date</label>
        <input type="date" name="from" value="<?= e($from) ?>" class="input focus:ring-2 focus:ring-primary">
      </div>
      <div>
        <label class="label font-medium">To Date</label>
        <input type="date" name="to" value="<?= e($to) ?>" class="input focus:ring-2 focus:ring-primary">
      </div>
      <div>
        <label class="label font-medium">Payment</label>
        <select name="payment" class="input focus:ring-2 focus:ring-primary">
          <option value="">All Payments</option>
          <option value="cash" <?= $payment==='cash'?'selected':'' ?>>💵 Cash</option>
          <option value="card" <?= $payment==='card'?'selected':'' ?>>💳 Card</option>
          <option value="bank_transfer" <?= $payment==='bank_transfer'?'selected':'' ?>>🏦 Bank Transfer</option>
        </select>
      </div>
      <div>
        <label class="label font-medium">Status</label>
        <select name="status" class="input focus:ring-2 focus:ring-primary">
          <option value="">All Status</option>
          <option value="completed" <?= $status==='completed'?'selected':'' ?>>Completed</option>
          <option value="pending" <?= $status==='pending'?'selected':'' ?>>Pending</option>
          <option value="cancelled" <?= $status==='cancelled'?'selected':'' ?>>Cancelled</option>
        </select>
      </div>
      <div>
        <label class="label font-medium">Returns</label>
        <select name="has_return" class="input focus:ring-2 focus:ring-primary">
          <option value="">All Orders</option>
          <option value="yes" <?= $has_return==='yes'?'selected':'' ?>>🔄 With Returns</option>
          <option value="no" <?= $has_return==='no'?'selected':'' ?>>✅ No Returns</option>
        </select>
      </div>
      <div>
        <label class="label font-medium">Search</label>
        <input type="text" name="q" class="input focus:ring-2 focus:ring-primary" value="<?= e($q) ?>" placeholder="Invoice or customer...">
      </div>
      <div class="md:col-span-6 flex gap-2">
        <button class="btn-primary px-6 py-2" type="submit">Apply Filters</button>
        <?php if ($from || $to || $payment || $status || $has_return || $q): ?>
          <a class="btn-secondary px-6 py-2" href="orders.php">Clear Filters</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- Orders Table -->
  <div class="card overflow-hidden shadow-md">
    <div class="mb-4 flex items-center justify-between">
      <h2 class="font-heading text-lg font-semibold text-gray-800">
        Orders List 
        <span class="text-sm font-normal text-gray-500">
          (Showing <?= count($orders) ?> of <?= number_format($totalOrders) ?>)
        </span>
      </h2>
    </div>

    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead>
          <tr class="bg-gradient-to-r from-primary to-dark text-white">
            <th class="th text-left">#</th>
            <th class="th text-left">Invoice</th>
            <th class="th text-left">Customer</th>
            <th class="th text-right">Amount</th>
            <th class="th text-center">Payment</th>
            <th class="th text-center">Status</th>
            <th class="th text-left">Date</th>
            <th class="th text-center">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <?php if (empty($orders)): ?>
            <tr>
              <td colspan="8" class="td text-center text-gray-400 py-12">
                <div class="text-5xl mb-2">📭</div>
                <div class="text-lg">No orders found</div>
                <?php if ($from || $to || $payment || $status || $has_return || $q): ?>
                  <a href="orders.php" class="text-primary hover:underline text-sm mt-2 inline-block">Clear filters</a>
                <?php endif; ?>
              </td>
            </tr>
          <?php else: 
            $counter = $offset + 1;
            foreach ($orders as $o): 
              $statusClass = $o['status'] === 'completed' ? 'status-completed' : 
                            ($o['status'] === 'pending' ? 'status-pending' : 'status-cancelled');
              $hasReturn = (bool)$o['has_return'];
              $returnCount = (int)($o['return_count'] ?? 0);
              $totalRefunded = (float)($o['total_refunded'] ?? 0);
              $netAmount = (float)$o['total_amount'] - $totalRefunded;
          ?>
            <tr class="hover:bg-gray-50 transition <?= $hasReturn ? 'has-return-row' : '' ?>">
              <td class="td text-gray-600 font-semibold"><?= $counter++ ?></td>
              <td class="td">
                <div class="flex items-center gap-2">
                  <span class="font-mono text-xs font-bold text-primary"><?= e($o['invoice_number']) ?></span>
                  <?php if ($hasReturn): ?>
                    <span class="return-badge" title="<?= $returnCount ?> return(s)">
                      🔄 <?= $returnCount ?>
                    </span>
                  <?php endif; ?>
                </div>
              </td>
              <td class="td">
                <div class="font-semibold text-gray-800"><?= e($o['customer_name'] ?? 'Walk-in') ?></div>
                <?php if (!empty($o['customer_phone'])): ?>
                  <div class="text-xs text-gray-500"><?= e($o['customer_phone']) ?></div>
                <?php endif; ?>
              </td>
              <td class="td text-right">
                <?php if ($totalRefunded > 0): ?>
                  <div class="text-xs text-gray-500 line-through"><?= format_currency($o['total_amount']) ?></div>
                  <div class="font-bold text-success"><?= format_currency($netAmount) ?></div>
                  <div class="text-xs text-danger font-semibold">-<?= format_currency($totalRefunded) ?> refunded</div>
                <?php else: ?>
                  <div class="font-bold text-success"><?= format_currency($o['total_amount']) ?></div>
                <?php endif; ?>
              </td>
              <td class="td text-center">
                <span class="inline-block px-3 py-1 bg-primary/10 text-primary rounded-full text-xs font-semibold capitalize">
                  <?= e(str_replace('_', ' ', $o['payment_method'])) ?>
                </span>
              </td>
              <td class="td text-center">
                <span class="status-badge <?= $statusClass ?>">
                  <?= e(ucfirst($o['status'])) ?>
                </span>
              </td>
              <td class="td">
                <div class="text-xs text-gray-700"><?= date('M j, Y', strtotime($o['order_date'])) ?></div>
                <div class="text-xs text-gray-500"><?= date('g:i A', strtotime($o['order_date'])) ?></div>
              </td>
              <td class="td text-center">
                <div class="flex items-center justify-center gap-1 flex-wrap">
                  <?php if (!$hasReturn): ?>
                    <a 
                      href="create_return.php?order_id=<?= (int)$o['id'] ?>" 
                      class="text-xs bg-warning/10 text-warning hover:bg-warning hover:text-white px-2 py-1 rounded transition whitespace-nowrap"
                      title="Create Return"
                    >
                      🔄 Return
                    </a>
                  <?php else: ?>
                    <a 
                      href="returns.php?search=<?= urlencode($o['invoice_number']) ?>" 
                      class="text-xs bg-orange-100 text-orange-700 hover:bg-orange-200 px-2 py-1 rounded transition whitespace-nowrap"
                      title="View Returns for this order"
                    >
                      🔄 View Returns (<?= $returnCount ?>)
                    </a>
                  <?php endif; ?>
                  
                  <a 
                    href="invoice.php?id=<?= (int)$o['id'] ?>" 
                    class="btn-secondary text-xs px-3 py-1.5 hover:bg-secondary hover:text-white transition whitespace-nowrap"
                    title="View Invoice"
                  >
                    👁️ View
                  </a>
                  
                  <form method="post" class="inline" onsubmit="return confirm('⚠️ Delete this order?\n<?= $hasReturn ? "This order has {$returnCount} return(s)! Cannot delete." : "Stock will be restored." ?>');">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
                    <button 
                      class="btn-danger text-xs px-3 py-1.5 whitespace-nowrap <?= $hasReturn ? 'opacity-50 cursor-not-allowed' : '' ?>" 
                      type="submit" 
                      title="<?= $hasReturn ? 'Cannot delete order with returns' : 'Delete Order' ?>"
                      <?= $hasReturn ? 'disabled' : '' ?>
                    >
                      🗑️
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
      <div class="mt-4 px-4 pb-4 flex items-center justify-between border-t pt-4">
        <div class="text-sm text-gray-600">
          Page <?= $page ?> of <?= $totalPages ?> (<?= number_format($totalOrders) ?> total orders)
        </div>
        <div class="flex items-center gap-2">
          <?php if ($page > 1): ?>
            <a 
              href="?page=<?= $page - 1 ?><?= $from ? '&from=' . urlencode($from) : '' ?><?= $to ? '&to=' . urlencode($to) : '' ?><?= $payment ? '&payment=' . urlencode($payment) : '' ?><?= $status ? '&status=' . urlencode($status) : '' ?><?= $has_return ? '&has_return=' . urlencode($has_return) : '' ?><?= $q ? '&q=' . urlencode($q) : '' ?>" 
              class="btn-secondary px-4 py-2"
            >
              ← Prev
            </a>
          <?php endif; ?>
          
          <?php
          $startPage = max(1, $page - 2);
          $endPage = min($totalPages, $page + 2);
          for ($i = $startPage; $i <= $endPage; $i++):
          ?>
            <a 
              href="?page=<?= $i ?><?= $from ? '&from=' . urlencode($from) : '' ?><?= $to ? '&to=' . urlencode($to) : '' ?><?= $payment ? '&payment=' . urlencode($payment) : '' ?><?= $status ? '&status=' . urlencode($status) : '' ?><?= $has_return ? '&has_return=' . urlencode($has_return) : '' ?><?= $q ? '&q=' . urlencode($q) : '' ?>" 
              class="<?= $i === $page ? 'bg-primary text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?> px-3 py-2 rounded transition font-medium"
            >
              <?= $i ?>
            </a>
          <?php endfor; ?>
          
          <?php if ($page < $totalPages): ?>
            <a 
              href="?page=<?= $page + 1 ?><?= $from ? '&from=' . urlencode($from) : '' ?><?= $to ? '&to=' . urlencode($to) : '' ?><?= $payment ? '&payment=' . urlencode($payment) : '' ?><?= $status ? '&status=' . urlencode($status) : '' ?><?= $has_return ? '&has_return=' . urlencode($has_return) : '' ?><?= $q ? '&q=' . urlencode($q) : '' ?>" 
              class="btn-primary px-4 py-2"
            >
              Next →
            </a>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <!-- Info Notes -->
  <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="card bg-blue-50 border border-blue-200">
      <div class="flex items-start gap-2 text-sm text-gray-700">
        <span class="text-lg">ℹ️</span>
        <div>
          <strong>Note:</strong> Deleting an order will automatically restore the product stock quantities. Orders with returns cannot be deleted.
        </div>
      </div>
    </div>
    
    <div class="card bg-yellow-50 border border-yellow-200">
      <div class="flex items-start gap-2 text-sm text-gray-700">
        <span class="text-lg">🔄</span>
        <div>
          <strong>Returns:</strong> Orders with returns are highlighted with a yellow background and show the return count badge. NET amount is displayed after deducting refunds.
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>