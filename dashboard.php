<?php
define('PAGE_TITLE', 'Dashboard');
require_once __DIR__ . '/functions.php';
require_login();

// Get date ranges
$today = date('Y-m-d');
$last30Days = date('Y-m-d', strtotime('-30 days'));
$last7Days = date('Y-m-d', strtotime('-7 days'));

$stats = [
    'month_revenue' => 0.0,
    'month_profit' => 0.0,
    'month_orders' => 0,
    'total_products' => 0,
    'total_customers' => 0,
    'total_orders' => 0,
    'low_stock_count' => 0,
    'total_inventory_value' => 0.0,
    'pending_orders' => 0,
    'month_refunded' => 0.0,
    'month_net_revenue' => 0.0,
    'total_returns' => 0,
    'pending_returns' => 0,
];

try {
    // Basic stats
    $stats['total_products'] = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    $stats['total_customers'] = (int)$pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();
    $stats['total_orders'] = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    $stats['low_stock_count'] = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE stock_quantity < min_stock")->fetchColumn();
    $stats['pending_orders'] = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
    
    // Inventory value (stock_quantity * sell_price)
    $stats['total_inventory_value'] = (float)$pdo->query("SELECT IFNULL(SUM(stock_quantity * sell_price), 0) FROM products")->fetchColumn();

    // Last 30 days revenue (GROSS)
    $stmt = $pdo->query("SELECT IFNULL(SUM(total_amount), 0) FROM orders WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
    $stats['month_revenue'] = (float)$stmt->fetchColumn();

    // Last 30 days REFUNDED amount (approved returns only)
    $stmt = $pdo->query("
        SELECT IFNULL(SUM(total_refund), 0) 
        FROM returns 
        WHERE status = 'approved' 
        AND return_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ");
    $stats['month_refunded'] = (float)$stmt->fetchColumn();

    // Calculate NET revenue (Gross - Refunded)
    $stats['month_net_revenue'] = $stats['month_revenue'] - $stats['month_refunded'];

    // Last 30 days profit (using net revenue)
    $stmt = $pdo->query("
        SELECT IFNULL(SUM((oi.unit_price - p.buy_price) * oi.quantity), 0)
        FROM order_items oi
        JOIN orders o ON o.id = oi.order_id
        JOIN products p ON p.id = oi.product_id
        WHERE o.order_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ");
    $grossProfit = (float)$stmt->fetchColumn();
    
    // Subtract refunded profit (estimated based on margin)
    $profitMargin = $stats['month_revenue'] > 0 ? ($grossProfit / $stats['month_revenue']) : 0;
    $refundedProfit = $stats['month_refunded'] * $profitMargin;
    $stats['month_profit'] = $grossProfit - $refundedProfit;

    // Last 30 days order count
    $stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
    $stats['month_orders'] = (int)$stmt->fetchColumn();

    // Returns stats
    $stats['total_returns'] = (int)$pdo->query("
        SELECT COUNT(*) FROM returns 
        WHERE return_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ")->fetchColumn();
    
    $stats['pending_returns'] = (int)$pdo->query("
        SELECT COUNT(*) FROM returns WHERE status = 'pending'
    ")->fetchColumn();

    // Average order value (using net revenue)
    $stats['avg_order_value'] = $stats['month_orders'] > 0 ? ($stats['month_net_revenue'] / $stats['month_orders']) : 0;

    // Low stock items
    $lowStock = $pdo->query("
        SELECT id, name, stock_quantity, min_stock, sell_price 
        FROM products 
        WHERE stock_quantity < min_stock 
        ORDER BY (min_stock - stock_quantity) DESC 
        LIMIT 10
    ")->fetchAll();

    // Recent orders (last 10) with return info
    $lastOrders = $pdo->query("
        SELECT o.id, o.invoice_number, o.total_amount, o.payment_method, o.order_date, o.status, o.has_return,
               c.name AS customer_name,
               (SELECT COUNT(*) FROM returns r WHERE r.order_id = o.id) as return_count,
               (SELECT SUM(total_refund) FROM returns r WHERE r.order_id = o.id AND r.status = 'approved') as refunded_amount
        FROM orders o
        LEFT JOIN customers c ON c.id = o.customer_id
        ORDER BY o.order_date DESC 
        LIMIT 10
    ")->fetchAll();

    // Top selling products (last 30 days)
    $topProducts = $pdo->query("
        SELECT p.name, p.id, SUM(oi.quantity) as total_sold, SUM(oi.total_price) as revenue,
               COUNT(DISTINCT o.id) as order_count
        FROM order_items oi
        JOIN products p ON p.id = oi.product_id
        JOIN orders o ON o.id = oi.order_id
        WHERE o.order_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY p.id, p.name
        ORDER BY total_sold DESC
        LIMIT 5
    ")->fetchAll();

    // Weekly sales (last 7 days) for chart - with refunds
    $weeklyDataStmt = $pdo->query("
        SELECT DATE(order_date) as d, SUM(total_amount) as total, COUNT(*) as order_count
        FROM orders
        WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(order_date)
        ORDER BY d ASC
    ");
    $weeklyRows = $weeklyDataStmt->fetchAll();
    
    // Get weekly refunds
    $weeklyRefundsStmt = $pdo->query("
        SELECT DATE(return_date) as d, SUM(total_refund) as refunded
        FROM returns
        WHERE status = 'approved'
        AND return_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(return_date)
    ");
    $weeklyRefunds = $weeklyRefundsStmt->fetchAll();
    
    $labels = [];
    $values = [];
    $netValues = [];
    $refundValues = [];
    $orderCounts = [];
    $dateMap = [];
    $countMap = [];
    $refundMap = [];
    
    foreach ($weeklyRows as $r) {
        $dateMap[$r['d']] = (float)$r['total'];
        $countMap[$r['d']] = (int)$r['order_count'];
    }
    
    foreach ($weeklyRefunds as $r) {
        $refundMap[$r['d']] = (float)$r['refunded'];
    }
    
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i day"));
        $labels[] = date('D, M j', strtotime($d));
        $grossRevenue = $dateMap[$d] ?? 0.0;
        $refunded = $refundMap[$d] ?? 0.0;
        
        $values[] = $grossRevenue;
        $refundValues[] = $refunded;
        $netValues[] = $grossRevenue - $refunded;
        $orderCounts[] = $countMap[$d] ?? 0;
    }

    // Monthly comparison (current month vs previous month) - NET revenue
    $currentMonthGross = (float)$pdo->query("
        SELECT IFNULL(SUM(total_amount), 0) 
        FROM orders 
        WHERE MONTH(order_date) = MONTH(CURDATE()) AND YEAR(order_date) = YEAR(CURDATE())
    ")->fetchColumn();
    
    $currentMonthRefunds = (float)$pdo->query("
        SELECT IFNULL(SUM(total_refund), 0) 
        FROM returns 
        WHERE status = 'approved'
        AND MONTH(return_date) = MONTH(CURDATE()) AND YEAR(return_date) = YEAR(CURDATE())
    ")->fetchColumn();
    
    $currentMonth = $currentMonthGross - $currentMonthRefunds;
    
    $lastMonthGross = (float)$pdo->query("
        SELECT IFNULL(SUM(total_amount), 0) 
        FROM orders 
        WHERE MONTH(order_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) 
        AND YEAR(order_date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
    ")->fetchColumn();
    
    $lastMonthRefunds = (float)$pdo->query("
        SELECT IFNULL(SUM(total_refund), 0) 
        FROM returns 
        WHERE status = 'approved'
        AND MONTH(return_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
        AND YEAR(return_date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
    ")->fetchColumn();
    
    $lastMonth = $lastMonthGross - $lastMonthRefunds;
    
    $monthGrowth = $lastMonth > 0 ? (($currentMonth - $lastMonth) / $lastMonth * 100) : 0;

    // Category-wise sales (last 30 days)
    $categorySales = $pdo->query("
        SELECT p.category, SUM(oi.total_price) as total_revenue, SUM(oi.quantity) as total_qty
        FROM order_items oi
        JOIN products p ON p.id = oi.product_id
        JOIN orders o ON o.id = oi.order_id
        WHERE o.order_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY p.category
        ORDER BY total_revenue DESC
    ")->fetchAll();

    // Pending returns for alert
    $pendingReturns = $pdo->query("
        SELECT r.id, r.return_number, r.total_refund, r.return_date, o.invoice_number
        FROM returns r
        JOIN orders o ON o.id = r.order_id
        WHERE r.status = 'pending'
        ORDER BY r.return_date DESC
        LIMIT 5
    ")->fetchAll();

} catch (Exception $e) {
    $lowStock = [];
    $lastOrders = [];
    $topProducts = [];
    $categorySales = [];
    $pendingReturns = [];
    $labels = [];
    $values = [];
    $netValues = [];
    $refundValues = [];
    $orderCounts = [];
    $monthGrowth = 0;
}

// Include header
require_once __DIR__ . '/includes/header.php';
?>

<!-- Page-specific styles -->
<style>
.stat-card {
    transition: all 0.3s ease;
}
.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}
.trend-up { color: #10B981; }
.trend-down { color: #EF4444; }
.pulse {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: .7; }
}
.has-return-indicator {
    display: inline-block;
    width: 8px;
    height: 8px;
    background: #F59E0B;
    border-radius: 50%;
    margin-left: 4px;
}
</style>

<!-- Dashboard Content -->
<div class="max-w-7xl mx-auto px-4 py-6">
  <!-- Header -->
  <div class="mb-6">
    <h1 class="font-heading text-3xl font-bold text-primary flex items-center gap-2">
      📊 Dashboard Overview
    </h1>
    <p class="text-sm text-gray-600 mt-1">Welcome back, <strong><?= e(current_user()['username']) ?></strong>! Here's your business performance.</p>
    <div class="text-xs text-gray-500 mt-1 flex items-center gap-3 flex-wrap">
      <span>📅 <?= date('l, F j, Y') ?></span>
      <span>⏰ <?= date('h:i A') ?></span>
      <span class="bg-primary/10 text-primary px-2 py-1 rounded font-semibold">Last 30 Days Overview</span>
      <?php if ($stats['month_refunded'] > 0): ?>
        <span class="bg-danger/10 text-danger px-2 py-1 rounded font-semibold">
          🔄 Rs <?= number_format($stats['month_refunded'], 2) ?> Refunded
        </span>
      <?php endif; ?>
    </div>
  </div>

  <!-- Pending Returns Alert -->
  <?php if (!empty($pendingReturns)): ?>
  <div class="mb-6 bg-yellow-50 border-l-4 border-warning rounded-lg p-4 shadow-md">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <span class="text-3xl">⚠️</span>
        <div>
          <h3 class="font-semibold text-warning">Pending Returns Require Action</h3>
          <p class="text-sm text-gray-700 mt-1">
            You have <strong><?= count($pendingReturns) ?></strong> pending return(s) waiting for approval
          </p>
        </div>
      </div>
      <a href="returns.php?status=pending" class="btn-warning px-4 py-2 hover:scale-105 transition-transform">
        Review Returns →
      </a>
    </div>
  </div>
  <?php endif; ?>

  <!-- Main Stats Grid (Last 30 Days) -->
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <!-- 30-Day NET Revenue -->
    <div class="stat-card card bg-gradient-to-br from-success/10 to-success/5 border-l-4 border-success">
      <div class="flex items-center justify-between">
        <div class="flex-1">
          <div class="text-gray-600 text-sm font-medium flex items-center gap-2">
            <span>30-Day NET Revenue</span>
            <span class="text-xs bg-success/20 text-success px-2 py-0.5 rounded-full">Live</span>
          </div>
          <div class="text-3xl font-bold text-success mt-1">
            <?= format_currency($stats['month_net_revenue']) ?>
          </div>
          <div class="text-xs text-gray-600 mt-1 space-y-0.5">
            <div class="flex items-center justify-between">
              <span>Gross:</span>
              <span class="font-semibold"><?= format_currency($stats['month_revenue']) ?></span>
            </div>
            <?php if ($stats['month_refunded'] > 0): ?>
              <div class="flex items-center justify-between text-danger">
                <span>Refunded:</span>
                <span class="font-semibold">-<?= format_currency($stats['month_refunded']) ?></span>
              </div>
            <?php endif; ?>
          </div>
        </div>
        <div class="text-4xl opacity-20">💵</div>
      </div>
    </div>

    <!-- 30-Day Profit -->
    <div class="stat-card card bg-gradient-to-br from-secondary/10 to-secondary/5 border-l-4 border-secondary">
      <div class="flex items-center justify-between">
        <div>
          <div class="text-gray-600 text-sm font-medium">30-Day NET Profit</div>
          <div class="text-3xl font-bold text-secondary mt-1">
            <?= format_currency($stats['month_profit']) ?>
          </div>
          <div class="text-xs text-gray-500 mt-1">
            📈 Net margin (<?= $stats['month_net_revenue'] > 0 ? number_format(($stats['month_profit'] / $stats['month_net_revenue']) * 100, 1) : 0 ?>%)
          </div>
        </div>
        <div class="text-4xl opacity-20">💎</div>
      </div>
    </div>

    <!-- 30-Day Orders & Returns -->
    <div class="stat-card card bg-gradient-to-br from-primary/10 to-primary/5 border-l-4 border-primary">
      <div class="flex items-center justify-between">
        <div>
          <div class="text-gray-600 text-sm font-medium">30-Day Orders</div>
          <div class="text-3xl font-bold text-primary mt-1"><?= number_format($stats['month_orders']) ?></div>
          <div class="text-xs text-gray-500 mt-1 space-y-0.5">
            <div>Avg: <?= format_currency($stats['avg_order_value']) ?>/order</div>
            <?php if ($stats['total_returns'] > 0): ?>
              <div class="text-warning font-semibold">🔄 <?= $stats['total_returns'] ?> returns</div>
            <?php endif; ?>
          </div>
        </div>
        <div class="text-4xl opacity-20">📦</div>
      </div>
    </div>

    <!-- Low Stock Alert -->
    <div class="stat-card card bg-gradient-to-br from-danger/10 to-danger/5 border-l-4 border-danger">
      <div class="flex items-center justify-between">
        <div>
          <div class="text-gray-600 text-sm font-medium">Low Stock Items</div>
          <div class="text-3xl font-bold text-danger mt-1 flex items-center gap-2">
            <?= number_format($stats['low_stock_count']) ?>
            <?php if ($stats['low_stock_count'] > 0): ?>
              <span class="text-base pulse">⚠️</span>
            <?php endif; ?>
          </div>
          <div class="text-xs text-gray-500 mt-1">⚠️ Needs restocking</div>
        </div>
        <div class="text-4xl opacity-20">⚠️</div>
      </div>
    </div>
  </div>

  <!-- Secondary Stats -->
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="card bg-white shadow-md hover:shadow-lg transition">
      <div class="text-gray-600 text-sm font-medium">Total Products</div>
      <div class="text-2xl font-bold text-gray-800 mt-1"><?= number_format($stats['total_products']) ?></div>
      <div class="text-xs text-gray-500 mt-1">📦 In inventory</div>
    </div>

    <div class="card bg-white shadow-md hover:shadow-lg transition">
      <div class="text-gray-600 text-sm font-medium">Total Customers</div>
      <div class="text-2xl font-bold text-gray-800 mt-1"><?= number_format($stats['total_customers']) ?></div>
      <div class="text-xs text-gray-500 mt-1">👥 Registered</div>
    </div>

    <div class="card bg-white shadow-md hover:shadow-lg transition">
      <div class="text-gray-600 text-sm font-medium">Inventory Value</div>
      <div class="text-2xl font-bold text-gray-800 mt-1"><?= format_currency($stats['total_inventory_value']) ?></div>
      <div class="text-xs text-gray-500 mt-1">💼 Total stock worth</div>
    </div>

    <div class="card bg-white shadow-md hover:shadow-lg transition">
      <div class="text-gray-600 text-sm font-medium">Monthly Growth</div>
      <div class="text-2xl font-bold <?= $monthGrowth >= 0 ? 'trend-up' : 'trend-down' ?> mt-1">
        <?= $monthGrowth >= 0 ? '↗' : '↘' ?> <?= number_format(abs($monthGrowth), 1) ?>%
      </div>
      <div class="text-xs text-gray-500 mt-1">📊 vs last month (NET)</div>
    </div>
  </div>

  <!-- Charts & Insights Row -->
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <!-- Weekly Sales Chart -->
    <div class="lg:col-span-2 card bg-white shadow-md">
      <div class="flex items-center justify-between mb-4">
        <h2 class="font-heading text-lg font-semibold text-gray-800">📈 Weekly Sales Trend</h2>
        <div class="text-xs text-gray-500">Last 7 days (NET)</div>
      </div>
      <canvas id="weeklyChart" height="100"></canvas>
      <div class="mt-3 grid grid-cols-3 gap-2 text-center text-xs border-t pt-3">
        <div>
          <div class="text-gray-500">Gross Revenue</div>
          <div class="font-bold text-primary"><?= format_currency(array_sum($values)) ?></div>
        </div>
        <div>
          <div class="text-gray-500">Refunded</div>
          <div class="font-bold text-danger">-<?= format_currency(array_sum($refundValues)) ?></div>
        </div>
        <div>
          <div class="text-gray-500">NET Revenue</div>
          <div class="font-bold text-success"><?= format_currency(array_sum($netValues)) ?></div>
        </div>
      </div>
    </div>

    <!-- Low Stock Alert -->
    <div class="card bg-white shadow-md">
      <div class="flex items-center justify-between mb-4">
        <h2 class="font-heading text-lg font-semibold text-gray-800">⚠️ Low Stock Alert</h2>
        <a href="products.php" class="text-secondary text-sm hover:underline font-medium">Manage →</a>
      </div>
      <div class="space-y-2 max-h-80 overflow-y-auto pr-2">
        <?php if (empty($lowStock)): ?>
          <div class="text-center py-8 text-gray-400">
            <div class="text-4xl mb-2">✅</div>
            <div class="text-sm">All products stocked well!</div>
          </div>
        <?php else: foreach ($lowStock as $ls): 
          $shortage = (int)$ls['min_stock'] - (int)$ls['stock_quantity'];
          $urgency = $shortage > 10 ? 'danger' : 'warning';
          $percentage = ((int)$ls['stock_quantity'] / (int)$ls['min_stock']) * 100;
        ?>
          <div class="flex items-center justify-between p-3 bg-<?= $urgency ?>/10 rounded-lg border border-<?= $urgency ?>/20 hover:shadow-md transition">
            <div class="flex-1">
              <div class="font-semibold text-sm text-gray-800"><?= e($ls['name']) ?></div>
              <div class="flex items-center gap-2 mt-1">
                <span class="text-xs bg-danger text-white px-2 py-0.5 rounded">
                  Stock: <?= e($ls['stock_quantity']) ?>
                </span>
                <span class="text-xs text-gray-600">
                  Min: <?= e($ls['min_stock']) ?>
                </span>
                <span class="text-xs text-<?= $urgency ?> font-semibold">
                  ↓ <?= $shortage ?>
                </span>
              </div>
              <div class="w-full bg-gray-200 rounded-full h-1.5 mt-2">
                <div class="bg-<?= $urgency ?> h-1.5 rounded-full" style="width: <?= min(100, $percentage) ?>%"></div>
              </div>
            </div>
            <a href="products.php" class="text-primary hover:text-dark ml-2" title="Update Stock">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
              </svg>
            </a>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>

  <!-- Category Performance -->
  <?php if (!empty($categorySales)): ?>
  <div class="card bg-white shadow-md mb-6">
    <h2 class="font-heading text-lg font-semibold text-gray-800 mb-4">📊 Category Performance (Last 30 Days)</h2>
    <div class="grid grid-cols-1 md:grid-cols-<?= count($categorySales) ?> gap-4">
      <?php foreach ($categorySales as $cat): ?>
        <div class="bg-gradient-to-br from-primary/5 to-secondary/5 rounded-lg p-4 border border-primary/20">
          <div class="text-sm text-gray-600 capitalize"><?= e($cat['category']) ?></div>
          <div class="text-2xl font-bold text-primary mt-1"><?= format_currency($cat['total_revenue']) ?></div>
          <div class="text-xs text-gray-500 mt-1">Qty Sold: <?= number_format($cat['total_qty']) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Recent Orders & Top Products -->
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Recent Orders -->
    <div class="lg:col-span-2 card bg-white shadow-md">
      <div class="flex items-center justify-between mb-4">
        <h2 class="font-heading text-lg font-semibold text-gray-800">📋 Recent Orders</h2>
        <a href="orders.php" class="text-secondary text-sm hover:underline font-medium">View All →</a>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="bg-gray-50 border-b">
              <th class="th text-left">Invoice</th>
              <th class="th text-left">Customer</th>
              <th class="th text-right">Amount</th>
              <th class="th text-center">Payment</th>
              <th class="th text-left">Date</th>
              <th class="th text-center">Status</th>
              <th class="th text-center">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y">
          <?php if (empty($lastOrders)): ?>
            <tr>
              <td colspan="7" class="td text-center text-gray-400 py-8">
                <div class="text-4xl mb-2">📭</div>
                <div>No recent orders</div>
              </td>
            </tr>
          <?php else: foreach ($lastOrders as $o): 
            $statusClass = $o['status'] === 'completed' ? 'success' : 'warning';
            $orderDate = date('M j, g:i A', strtotime($o['order_date']));
            $hasReturn = (bool)$o['has_return'];
            $refundedAmount = (float)($o['refunded_amount'] ?? 0);
            $netAmount = (float)$o['total_amount'] - $refundedAmount;
          ?>
            <tr class="hover:bg-gray-50 transition <?= $hasReturn ? 'bg-yellow-50' : '' ?>">
              <td class="td">
                <div class="flex items-center gap-1">
                  <span class="font-mono text-xs font-semibold text-primary"><?= e($o['invoice_number']) ?></span>
                  <?php if ($hasReturn): ?>
                    <span class="has-return-indicator" title="Has return"></span>
                  <?php endif; ?>
                </div>
              </td>
              <td class="td">
                <div class="font-medium text-gray-800"><?= e($o['customer_name'] ?? 'Walk-in') ?></div>
              </td>
              <td class="td text-right">
                <?php if ($refundedAmount > 0): ?>
                  <div class="text-xs text-gray-500 line-through"><?= format_currency($o['total_amount']) ?></div>
                  <div class="font-semibold text-success"><?= format_currency($netAmount) ?></div>
                  <div class="text-xs text-danger">-<?= format_currency($refundedAmount) ?></div>
                <?php else: ?>
                  <div class="font-semibold text-success"><?= format_currency($o['total_amount']) ?></div>
                <?php endif; ?>
              </td>
              <td class="td text-center">
                <span class="inline-block px-2 py-1 bg-primary/10 text-primary rounded text-xs font-medium capitalize">
                  <?= e(str_replace('_', ' ', $o['payment_method'])) ?>
                </span>
              </td>
              <td class="td">
                <div class="text-xs text-gray-700"><?= $orderDate ?></div>
              </td>
              <td class="td text-center">
                <span class="inline-block px-2 py-1 bg-<?= $statusClass ?>/10 text-<?= $statusClass ?> rounded text-xs font-semibold capitalize">
                  <?= e($o['status']) ?>
                </span>
              </td>
              <td class="td text-center">
                <a class="btn-secondary text-xs px-3 py-1 hover:bg-secondary hover:text-white transition" href="invoice.php?id=<?= (int)$o['id'] ?>">
                  View
                </a>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Top Selling Products -->
    <div class="card bg-white shadow-md">
      <div class="flex items-center justify-between mb-4">
        <h2 class="font-heading text-lg font-semibold text-gray-800">🏆 Top Products</h2>
        <div class="text-xs text-gray-500">Last 30 days</div>
      </div>
      <div class="space-y-3">
        <?php if (empty($topProducts)): ?>
          <div class="text-center py-8 text-gray-400">
            <div class="text-4xl mb-2">📊</div>
            <div class="text-sm">No sales data yet</div>
          </div>
        <?php else: 
          $rank = 1;
          foreach ($topProducts as $tp): 
            $medal = $rank === 1 ? '🥇' : ($rank === 2 ? '🥈' : ($rank === 3 ? '🥉' : '🏅'));
        ?>
          <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
            <div class="text-2xl"><?= $medal ?></div>
            <div class="flex-1 min-w-0">
              <div class="font-semibold text-sm text-gray-800 truncate"><?= e($tp['name']) ?></div>
              <div class="flex items-center gap-2 mt-1 text-xs text-gray-600">
                <span>Sold: <strong class="text-primary"><?= number_format($tp['total_sold']) ?></strong></span>
                <span>•</span>
                <span>Orders: <strong class="text-secondary"><?= number_format($tp['order_count']) ?></strong></span>
              </div>
              <div class="text-xs text-success font-semibold mt-1">
                Revenue: <?= format_currency($tp['revenue']) ?>
              </div>
            </div>
          </div>
        <?php 
          $rank++;
          endforeach; 
        endif; ?>
      </div>
    </div>
  </div>

  <!-- Quick Actions -->
  <div class="mt-6 card bg-gradient-to-r from-primary to-dark text-white shadow-lg">
    <div class="flex items-center justify-between flex-wrap gap-4">
      <div>
        <h3 class="font-heading text-lg font-semibold">⚡ Quick Actions</h3>
        <p class="text-sm opacity-90 mt-1">Jump to common tasks</p>
      </div>
      <div class="flex gap-3 flex-wrap">
        <a href="pos.php" class="bg-white text-primary px-5 py-2.5 rounded-lg font-semibold hover:bg-gray-100 transition shadow-md">
          🛒 New Sale
        </a>
        <?php if ($stats['pending_returns'] > 0): ?>
          <a href="returns.php?status=pending" class="bg-warning text-white px-5 py-2.5 rounded-lg font-semibold hover:bg-yellow-600 transition relative">
            🔄 Review Returns
            <span class="absolute -top-2 -right-2 bg-danger text-white text-xs px-2 py-0.5 rounded-full">
              <?= $stats['pending_returns'] ?>
            </span>
          </a>
        <?php endif; ?>
        <a href="products.php" class="bg-white/10 backdrop-blur text-white px-5 py-2.5 rounded-lg font-semibold hover:bg-white/20 transition">
          📦 Manage Products
        </a>
        <a href="orders.php" class="bg-white/10 backdrop-blur text-white px-5 py-2.5 rounded-lg font-semibold hover:bg-white/20 transition">
          📋 View Orders
        </a>
        <a href="customers.php" class="bg-white/10 backdrop-blur text-white px-5 py-2.5 rounded-lg font-semibold hover:bg-white/20 transition">
          👥 Customers
        </a>
      </div>
    </div>
  </div>
</div>

<!-- Chart.js (Local) -->
<script src="assets/js/chart.min.js"></script>
<script>
const ctx = document.getElementById('weeklyChart').getContext('2d');
const chart = new Chart(ctx, {
  type: 'line',
  data: {
    labels: <?= json_encode($labels) ?>,
    datasets: [
      {
        label: 'Gross Revenue (Rs)',
        data: <?= json_encode($values) ?>,
        backgroundColor: 'rgba(14, 165, 233, 0.05)',
        borderColor: '#0EA5E9',
        borderWidth: 2,
        borderDash: [5, 5],
        fill: false,
        tension: 0.4,
        pointRadius: 4,
        pointBackgroundColor: '#0EA5E9',
        pointBorderColor: '#fff',
        pointBorderWidth: 2,
      },
      {
        label: 'Refunded (Rs)',
        data: <?= json_encode($refundValues) ?>,
        backgroundColor: 'rgba(239, 68, 68, 0.1)',
        borderColor: '#EF4444',
        borderWidth: 2,
        fill: true,
        tension: 0.4,
        pointRadius: 4,
        pointBackgroundColor: '#EF4444',
        pointBorderColor: '#fff',
        pointBorderWidth: 2,
      },
      {
        label: 'NET Revenue (Rs)',
        data: <?= json_encode($netValues) ?>,
        backgroundColor: 'rgba(16, 185, 129, 0.1)',
        borderColor: '#10B981',
        borderWidth: 3,
        fill: true,
        tension: 0.4,
        pointRadius: 5,
        pointBackgroundColor: '#10B981',
        pointBorderColor: '#fff',
        pointBorderWidth: 2,
        pointHoverRadius: 7
      }
    ]
  },
  options: {
    responsive: true,
    maintainAspectRatio: true,
    plugins: {
      legend: {
        display: true,
        position: 'top',
      },
      tooltip: {
        mode: 'index',
        intersect: false,
        callbacks: {
          label: function(context) {
            return context.dataset.label + ': Rs ' + context.parsed.y.toLocaleString('en-PK', {minimumFractionDigits: 2, maximumFractionDigits: 2});
          }
        }
      }
    },
    scales: {
      y: { 
        beginAtZero: true,
        ticks: {
          callback: function(value) {
            return 'Rs ' + value.toLocaleString();
          }
        }
      },
      x: {
        grid: {
          display: false
        }
      }
    }
  }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>