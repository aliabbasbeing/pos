<?php
define('PAGE_TITLE', 'Customers Management');
require_once __DIR__ . '/functions.php';
require_login();

$message = get_flash('message');
$error = get_flash('error');

// Add customer via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    header('Content-Type: application/json');
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
        exit;
    }
    
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    if (empty($name) || empty($phone)) {
        echo json_encode(['success' => false, 'message' => 'Name and phone are required.']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO customers (name, phone, address) VALUES (?, ?, ?)");
        $stmt->execute([$name, $phone, $address]);
        echo json_encode(['success' => true, 'message' => 'Customer added successfully!']);
    } catch (PDOException $e) {
        if ((int)$e->getCode() === 23000) {
            echo json_encode(['success' => false, 'message' => 'Phone number already exists.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add customer.']);
        }
    }
    exit;
}

// Edit customer via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    header('Content-Type: application/json');
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
        exit;
    }
    
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    if ($id <= 0 || empty($name) || empty($phone)) {
        echo json_encode(['success' => false, 'message' => 'Invalid data provided.']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE customers SET name=?, phone=?, address=? WHERE id=?");
        $stmt->execute([$name, $phone, $address, $id]);
        echo json_encode(['success' => true, 'message' => 'Customer updated successfully!']);
    } catch (PDOException $e) {
        if ((int)$e->getCode() === 23000) {
            echo json_encode(['success' => false, 'message' => 'Phone number already exists.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update customer.']);
        }
    }
    exit;
}

// Get single customer for editing
if (isset($_GET['get_customer'])) {
    header('Content-Type: application/json');
    $id = (int)($_GET['get_customer'] ?? 0);
    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
        $stmt->execute([$id]);
        $customer = $stmt->fetch();
        echo json_encode($customer ?: []);
    }
    exit;
}

// Delete customer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Invalid CSRF token.');
        redirect('customers.php');
    }
    
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        try {
            // Check if customer has orders
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE customer_id = ?");
            $stmt->execute([$id]);
            $orderCount = (int)$stmt->fetchColumn();
            
            if ($orderCount > 0) {
                set_flash('error', "Cannot delete customer with {$orderCount} order(s). Set orders to null first.");
            } else {
                $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
                $stmt->execute([$id]);
                set_flash('message', 'Customer deleted successfully.');
            }
        } catch (Exception $e) {
            set_flash('error', 'Could not delete customer.');
        }
    }
    redirect('customers.php');
}

// Pagination & Filters
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Count total
$countSql = "SELECT COUNT(*) FROM customers WHERE 1=1";
$params = [];
if ($search !== '') {
    $countSql .= " AND (name LIKE ? OR phone LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalCustomers = (int)$countStmt->fetchColumn();
$totalPages = (int)ceil($totalCustomers / $perPage);

// Fetch customers with order stats
$sql = "SELECT c.*, 
        COUNT(o.id) as order_count, 
        IFNULL(SUM(o.total_amount), 0) as total_spent,
        MAX(o.order_date) as last_order_date
        FROM customers c 
        LEFT JOIN orders o ON o.customer_id = c.id
        WHERE 1=1";
$params = [];
if ($search !== '') {
    $sql .= " AND (c.name LIKE ? OR c.phone LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
$sql .= " GROUP BY c.id ORDER BY c.created_at DESC LIMIT ? OFFSET ?";

$stmt = $pdo->prepare($sql);
foreach ($params as $i => $param) {
    $stmt->bindValue($i + 1, $param);
}
$stmt->bindValue(count($params) + 1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
$stmt->execute();
$customers = $stmt->fetchAll();

// Customer detail view
$viewId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$customerDetail = null;
$history = [];

if ($viewId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$viewId]);
    $customerDetail = $stmt->fetch();

    if ($customerDetail) {
        $orders = $pdo->prepare("
            SELECT o.id, o.invoice_number, o.total_amount, o.order_date, o.payment_method, o.status
            FROM orders o
            WHERE o.customer_id = ?
            ORDER BY o.order_date DESC
        ");
        $orders->execute([$viewId]);
        $history = $orders->fetchAll();
    }
}

// Stats
$stats = [
    'total_customers' => $totalCustomers,
    'new_this_month' => 0,
    'top_spender' => 0,
];

try {
    $stats['new_this_month'] = (int)$pdo->query("
        SELECT COUNT(*) FROM customers 
        WHERE MONTH(created_at) = MONTH(CURDATE()) 
        AND YEAR(created_at) = YEAR(CURDATE())
    ")->fetchColumn();
    
    $topSpenderData = $pdo->query("
        SELECT IFNULL(SUM(total_amount), 0) as max_spent
        FROM orders 
        GROUP BY customer_id 
        ORDER BY max_spent DESC 
        LIMIT 1
    ")->fetch();
    
    $stats['top_spender'] = (float)($topSpenderData['max_spent'] ?? 0);
} catch (Exception $e) {
    // Keep defaults
}

// Include header
require_once __DIR__ . '/includes/header.php';
?>

<!-- Customers Content -->
<div class="max-w-7xl mx-auto px-4 py-6">
  
  <?php if ($viewId > 0 && $customerDetail): ?>
    <!-- Customer Detail View -->
    <div class="mb-6">
      <a href="customers.php" class="text-secondary hover:underline flex items-center gap-1 mb-4">
        ← Back to Customers
      </a>
      <h1 class="font-heading text-3xl font-bold text-primary flex items-center gap-2">
        👤 Customer Profile
      </h1>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Customer Info Card -->
      <div class="card bg-white shadow-lg">
        <div class="flex items-center justify-between mb-4">
          <h2 class="font-heading text-lg font-semibold text-gray-800">Customer Information</h2>
          <button onclick="openEditModal(<?= (int)$customerDetail['id'] ?>)" class="text-primary hover:text-dark">
            ✏️
          </button>
        </div>
        
        <div class="space-y-3 text-sm">
          <div class="flex items-start gap-2">
            <span class="font-semibold text-gray-600 w-20">Name:</span>
            <span class="text-gray-800 font-medium"><?= e($customerDetail['name']) ?></span>
          </div>
          <div class="flex items-start gap-2">
            <span class="font-semibold text-gray-600 w-20">Phone:</span>
            <span class="text-gray-800"><?= e($customerDetail['phone']) ?></span>
          </div>
          <div class="flex items-start gap-2">
            <span class="font-semibold text-gray-600 w-20">Address:</span>
            <span class="text-gray-800"><?= nl2br(e($customerDetail['address'])) ?></span>
          </div>
          <div class="flex items-start gap-2">
            <span class="font-semibold text-gray-600 w-20">Joined:</span>
            <span class="text-gray-800"><?= date('M j, Y', strtotime($customerDetail['created_at'])) ?></span>
          </div>
        </div>

        <div class="mt-4 pt-4 border-t">
          <div class="text-sm text-gray-600 mb-1">Total Spent</div>
          <div class="text-2xl font-bold text-success">
            <?php
            $totalSpent = 0;
            foreach ($history as $h) { $totalSpent += (float)$h['total_amount']; }
            echo format_currency($totalSpent);
            ?>
          </div>
          <div class="text-xs text-gray-500 mt-1"><?= count($history) ?> order(s)</div>
        </div>
      </div>

      <!-- Purchase History -->
      <div class="lg:col-span-2 card bg-white shadow-lg">
        <h2 class="font-heading text-lg font-semibold text-gray-800 mb-4">
          📋 Purchase History (<?= count($history) ?>)
        </h2>
        
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="bg-gray-50 border-b">
                <th class="th text-left">Invoice</th>
                <th class="th text-right">Amount</th>
                <th class="th text-center">Payment</th>
                <th class="th text-center">Status</th>
                <th class="th text-left">Date</th>
                <th class="th text-center">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y">
            <?php if (empty($history)): ?>
              <tr>
                <td colspan="6" class="td text-center text-gray-400 py-8">
                  <div class="text-4xl mb-2">📭</div>
                  <div>No orders yet</div>
                </td>
              </tr>
            <?php else: foreach ($history as $h): ?>
              <tr class="hover:bg-gray-50">
                <td class="td">
                  <span class="font-mono text-xs font-semibold text-primary"><?= e($h['invoice_number']) ?></span>
                </td>
                <td class="td text-right font-semibold text-success"><?= format_currency($h['total_amount']) ?></td>
                <td class="td text-center">
                  <span class="inline-block px-2 py-1 bg-primary/10 text-primary rounded text-xs capitalize">
                    <?= e(str_replace('_', ' ', $h['payment_method'])) ?>
                  </span>
                </td>
                <td class="td text-center">
                  <span class="inline-block px-2 py-1 bg-success/10 text-success rounded text-xs font-semibold capitalize">
                    <?= e($h['status']) ?>
                  </span>
                </td>
                <td class="td">
                  <div class="text-xs"><?= date('M j, Y g:i A', strtotime($h['order_date'])) ?></div>
                </td>
                <td class="td text-center">
                  <a class="btn-secondary text-xs px-3 py-1" href="invoice.php?id=<?= (int)$h['id'] ?>">
                    View
                  </a>
                </td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  <?php else: ?>
    <!-- Customers List View -->
    <div class="mb-6">
      <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
        <div>
          <h1 class="font-heading text-3xl font-bold text-primary flex items-center gap-2">
            👥 Customers Management
          </h1>
          <p class="text-sm text-gray-600 mt-1">Manage customer information and track purchase history</p>
        </div>
        <button onclick="openAddModal()" class="btn-primary flex items-center gap-2 shadow-lg">
          ➕ Add New Customer
        </button>
      </div>

      <!-- Stats Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="card bg-gradient-to-br from-primary/10 to-primary/5 border-l-4 border-primary">
          <div class="text-gray-600 text-sm font-medium">Total Customers</div>
          <div class="text-3xl font-bold text-primary mt-1"><?= number_format($stats['total_customers']) ?></div>
          <div class="text-xs text-gray-500 mt-1">👥 Registered</div>
        </div>
        <div class="card bg-gradient-to-br from-success/10 to-success/5 border-l-4 border-success">
          <div class="text-gray-600 text-sm font-medium">New This Month</div>
          <div class="text-3xl font-bold text-success mt-1"><?= number_format($stats['new_this_month']) ?></div>
          <div class="text-xs text-gray-500 mt-1">📅 <?= date('F Y') ?></div>
        </div>
        <div class="card bg-gradient-to-br from-secondary/10 to-secondary/5 border-l-4 border-secondary">
          <div class="text-gray-600 text-sm font-medium">Top Customer Spent</div>
          <div class="text-3xl font-bold text-secondary mt-1"><?= format_currency($stats['top_spender']) ?></div>
          <div class="text-xs text-gray-500 mt-1">🏆 Highest value</div>
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

    <!-- Search & Filter -->
    <div class="card mb-6 shadow-md">
      <form method="get" class="flex gap-3">
        <input 
          type="text" 
          name="search" 
          value="<?= e($search) ?>" 
          placeholder="🔍 Search by name or phone..." 
          class="input flex-1 focus:ring-2 focus:ring-primary"
        >
        <button type="submit" class="btn-primary px-6">Search</button>
        <?php if ($search): ?>
          <a href="customers.php" class="btn-secondary">Clear</a>
        <?php endif; ?>
      </form>
    </div>

    <!-- Customers Table -->
    <div class="card overflow-hidden shadow-md">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="font-heading text-lg font-semibold text-gray-800">
          Customers List 
          <span class="text-sm font-normal text-gray-500">
            (Showing <?= count($customers) ?> of <?= number_format($totalCustomers) ?>)
          </span>
        </h2>
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="bg-gradient-to-r from-primary to-dark text-white">
              <th class="th text-left">#</th>
              <th class="th text-left">Name</th>
              <th class="th text-left">Phone</th>
              <th class="th text-left">Address</th>
              <th class="th text-center">Orders</th>
              <th class="th text-right">Total Spent</th>
              <th class="th text-left">Last Order</th>
              <th class="th text-center">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
          <?php if (empty($customers)): ?>
            <tr>
              <td colspan="8" class="td text-center text-gray-400 py-12">
                <div class="text-5xl mb-2">👥</div>
                <div class="text-lg">No customers found</div>
                <?php if ($search): ?>
                  <a href="customers.php" class="text-primary hover:underline text-sm mt-2 inline-block">Clear search</a>
                <?php endif; ?>
              </td>
            </tr>
          <?php else: 
            $counter = $offset + 1;
            foreach ($customers as $c): 
          ?>
            <tr class="hover:bg-gray-50 transition">
              <td class="td text-gray-600 font-semibold"><?= $counter++ ?></td>
              <td class="td">
                <div class="font-semibold text-gray-800"><?= e($c['name']) ?></div>
              </td>
              <td class="td text-gray-700"><?= e($c['phone']) ?></td>
              <td class="td text-gray-600 text-xs"><?= e(substr($c['address'], 0, 30)) ?><?= strlen($c['address']) > 30 ? '...' : '' ?></td>
              <td class="td text-center">
                <span class="inline-block px-2 py-1 bg-primary/10 text-primary rounded-full text-xs font-bold">
                  <?= (int)$c['order_count'] ?>
                </span>
              </td>
              <td class="td text-right font-semibold text-success">
                <?= format_currency($c['total_spent']) ?>
              </td>
              <td class="td text-xs text-gray-600">
                <?= $c['last_order_date'] ? date('M j, Y', strtotime($c['last_order_date'])) : 'Never' ?>
              </td>
              <td class="td text-center">
                <div class="flex items-center justify-center gap-1">
                  <a 
                    href="customers.php?id=<?= (int)$c['id'] ?>" 
                    class="btn-secondary text-xs px-2 py-1"
                    title="View Details"
                  >
                    👁️ View
                  </a>
                  <button 
                    onclick="openEditModal(<?= (int)$c['id'] ?>)" 
                    class="bg-primary/10 text-primary hover:bg-primary hover:text-white px-2 py-1 rounded text-xs transition"
                    title="Edit"
                  >
                    ✏️ Edit
                  </button>
                  <form method="post" class="inline" onsubmit="return confirm('⚠️ Delete this customer?');">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                    <button class="btn-danger text-xs px-2 py-1" type="submit" title="Delete">
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
            Page <?= $page ?> of <?= $totalPages ?>
          </div>
          <div class="flex items-center gap-2">
            <?php if ($page > 1): ?>
              <a href="?page=<?= $page - 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>" class="btn-secondary px-4 py-2">
                ← Prev
              </a>
            <?php endif; ?>
            
            <?php
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);
            for ($i = $startPage; $i <= $endPage; $i++):
            ?>
              <a href="?page=<?= $i ?><?= $search ? '&search=' . urlencode($search) : '' ?>" 
                 class="<?= $i === $page ? 'bg-primary text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?> px-3 py-2 rounded transition font-medium">
                <?= $i ?>
              </a>
            <?php endfor; ?>
            
            <?php if ($page < $totalPages): ?>
              <a href="?page=<?= $page + 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>" class="btn-primary px-4 py-2">
                Next →
              </a>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<!-- Add/Edit Customer Modal -->
<div id="customerModal" class="modal fixed inset-0 bg-black/50 items-center justify-center p-4 z-50">
  <div class="modal-content bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
    <div class="flex items-center justify-between mb-4">
      <h3 class="font-heading text-xl text-primary font-bold" id="modalTitle">Add New Customer</h3>
      <button onclick="closeModal('customerModal')" class="text-gray-400 hover:text-gray-600 text-3xl leading-none">
        &times;
      </button>
    </div>
    
    <form id="customerForm">
      <?= csrf_input() ?>
      <input type="hidden" name="action" id="formAction" value="add">
      <input type="hidden" name="id" id="customerId">
      
      <div class="space-y-4">
        <div>
          <label class="label font-medium">Name <span class="text-danger">*</span></label>
          <input type="text" name="name" id="customerName" class="input focus:ring-2 focus:ring-primary" required>
        </div>
        <div>
          <label class="label font-medium">Phone <span class="text-danger">*</span></label>
          <input type="text" name="phone" id="customerPhone" class="input focus:ring-2 focus:ring-primary" required>
        </div>
        <div>
          <label class="label font-medium">Address</label>
          <textarea name="address" id="customerAddress" class="input focus:ring-2 focus:ring-primary" rows="3"></textarea>
        </div>
      </div>
      
      <div class="mt-6 flex justify-end gap-3">
        <button type="button" onclick="closeModal('customerModal')" class="btn-secondary px-6 py-2">
          Cancel
        </button>
        <button type="submit" class="btn-primary px-6 py-2" id="submitBtn">
          <span id="submitText">Add Customer</span>
        </button>
      </div>
    </form>
  </div>
</div>

<script>
const CSRF = '<?= e(csrf_token()) ?>';

function openAddModal() {
  document.getElementById('modalTitle').textContent = 'Add New Customer';
  document.getElementById('formAction').value = 'add';
  document.getElementById('submitText').textContent = 'Add Customer';
  document.getElementById('customerForm').reset();
  document.getElementById('customerId').value = '';
  openModal('customerModal');
}

function openEditModal(id) {
  document.getElementById('modalTitle').textContent = 'Edit Customer';
  document.getElementById('formAction').value = 'edit';
  document.getElementById('submitText').textContent = 'Update Customer';
  
  fetch(`?get_customer=${id}`)
    .then(res => res.json())
    .then(customer => {
      if (customer.id) {
        document.getElementById('customerId').value = customer.id;
        document.getElementById('customerName').value = customer.name || '';
        document.getElementById('customerPhone').value = customer.phone || '';
        document.getElementById('customerAddress').value = customer.address || '';
        openModal('customerModal');
      }
    });
}

document.getElementById('customerForm').addEventListener('submit', function(e) {
  e.preventDefault();
  
  const submitBtn = document.getElementById('submitBtn');
  const submitText = document.getElementById('submitText');
  const originalText = submitText.textContent;
  
  submitBtn.disabled = true;
  submitText.textContent = 'Processing...';
  
  const formData = new FormData(this);
  
  fetch('customers.php', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      showToast(data.message, 'success');
      setTimeout(() => window.location.reload(), 1000);
    } else {
      showToast(data.message, 'error');
    }
  })
  .catch(() => showToast('An error occurred', 'error'))
  .finally(() => {
    submitBtn.disabled = false;
    submitText.textContent = originalText;
  });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>