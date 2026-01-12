<?php
require_once __DIR__ . '/functions.php';
require_login();
enforce_role(['admin']);

$search = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');
$message = get_flash('message');
$error = get_flash('error');

// Add product via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    header('Content-Type: application/json');
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
        exit;
    }
    
    $required = ['name', 'category', 'unit', 'form', 'buy_price', 'sell_price', 'stock_quantity', 'min_stock'];
    $errors = [];
    foreach ($required as $field) {
        if (empty($_POST[$field] ?? '')) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
        }
    }
    
    if ($errors) {
        echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO products (name, category, composition, unit, form, buy_price, sell_price, stock_quantity, min_stock) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            trim($_POST['name']),
            trim($_POST['category']),
            trim($_POST['composition'] ?? ''),
            trim($_POST['unit']),
            trim($_POST['form']),
            max(0, (float)$_POST['buy_price']),
            max(0, (float)$_POST['sell_price']),
            max(0, (int)$_POST['stock_quantity']),
            max(0, (int)$_POST['min_stock'])
        ]);
        echo json_encode(['success' => true, 'message' => 'Product added successfully!']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to add product.']);
    }
    exit;
}

// Edit product via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    header('Content-Type: application/json');
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
        exit;
    }
    
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid product ID.']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE products SET name=?, category=?, composition=?, unit=?, form=?, buy_price=?, sell_price=?, stock_quantity=?, min_stock=? WHERE id=?");
        $stmt->execute([
            trim($_POST['name']),
            trim($_POST['category']),
            trim($_POST['composition'] ?? ''),
            trim($_POST['unit']),
            trim($_POST['form']),
            max(0, (float)$_POST['buy_price']),
            max(0, (float)$_POST['sell_price']),
            max(0, (int)$_POST['stock_quantity']),
            max(0, (int)$_POST['min_stock']),
            $id
        ]);
        echo json_encode(['success' => true, 'message' => 'Product updated successfully!']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to update product.']);
    }
    exit;
}

// Get single product for editing
if (isset($_GET['get_product'])) {
    header('Content-Type: application/json');
    $id = (int)($_GET['get_product'] ?? 0);
    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $product = $stmt->fetch();
        echo json_encode($product ?: []);
    }
    exit;
}

// Delete product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Invalid CSRF token.');
        redirect('products.php');
    }
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([$id]);
            set_flash('message', 'Product deleted successfully.');
        } catch (Exception $e) {
            set_flash('error', 'Could not delete product (it may be linked to orders).');
        }
    }
    redirect('products.php');
}

// Update stock
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'stock') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Invalid CSRF token.');
        redirect('products.php');
    }
    $id = (int)($_POST['id'] ?? 0);
    $qty = (int)($_POST['stock_quantity'] ?? 0);
    if ($id > 0 && $qty >= 0) {
        try {
            $stmt = $pdo->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
            $stmt->execute([$qty, $id]);
            set_flash('message', 'Stock updated successfully.');
        } catch (Exception $e) {
            set_flash('error', 'Could not update stock.');
        }
    }
    redirect('products.php');
}

// CSV import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Invalid CSRF token.');
        redirect('products.php');
    }
    
    if (!empty($_FILES['csv']['tmp_name'])) {
        $handle = fopen($_FILES['csv']['tmp_name'], 'r');
        if ($handle !== false) {
            $row = 0;
            $imported = 0;
            $skipped = 0;
            
            while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                $row++;
                if ($row === 1) continue;
                if (count($data) < 9) { $skipped++; continue; }
                
                [$name, $cat, $comp, $unit, $form, $buy, $sell, $stock, $min] = $data;
                
                if (empty(trim($name)) || empty(trim($cat))) { $skipped++; continue; }
                
                try {
                    $stmt = $pdo->prepare("INSERT INTO products (name, category, composition, unit, form, buy_price, sell_price, stock_quantity, min_stock) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        trim($name), trim($cat), trim($comp), trim($unit), trim($form),
                        max(0, (float)$buy), max(0, (float)$sell), max(0, (int)$stock), max(0, (int)$min)
                    ]);
                    $imported++;
                } catch (Exception $e) { $skipped++; }
            }
            fclose($handle);
            
            set_flash('message', "Imported $imported product(s)." . ($skipped > 0 ? " Skipped $skipped row(s)." : ""));
        } else {
            set_flash('error', 'Failed to read CSV file.');
        }
    } else {
        set_flash('error', 'Please choose a CSV file.');
    }
    redirect('products.php');
}

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Count total
$countSql = "SELECT COUNT(*) FROM products WHERE 1=1";
$params = [];
if ($search !== '') {
    $countSql .= " AND (name LIKE ? OR composition LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
if ($category !== '') {
    $countSql .= " AND category = ?";
    $params[] = $category;
}
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalProducts = (int)$countStmt->fetchColumn();
$totalPages = (int)ceil($totalProducts / $perPage);

// Load products
$sql = "SELECT * FROM products WHERE 1=1";
$params = [];
if ($search !== '') {
    $sql .= " AND (name LIKE ? OR composition LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
if ($category !== '') {
    $sql .= " AND category = ?";
    $params[] = $category;
}
$sql .= " ORDER BY name ASC LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);
foreach ($params as $i => $param) {
    $stmt->bindValue($i + 1, $param);
}
$stmt->bindValue(count($params) + 1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll();

// Calculate stats - FIX: Use sell_price instead of buy_price
$totalCount = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$lowStockCount = $pdo->query("SELECT COUNT(*) FROM products WHERE stock_quantity < min_stock")->fetchColumn();
$totalValue = $pdo->query("SELECT IFNULL(SUM(stock_quantity * sell_price), 0) FROM products")->fetchColumn();
$categories = $pdo->query("SELECT COUNT(DISTINCT category) FROM products")->fetchColumn();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?= e(SITE_NAME) ?> - Products Management</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="assets/css/fonts.css">
<link rel="stylesheet" href="fontawesome/css/all.min.css">
<link rel="stylesheet" href="assets/css/tailwind.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<script src="assets/js/jquery-3.6.0.min.js"></script>
<style>
.stock-low { background-color: #fee; }
.stock-good { background-color: #efe; }
.modal { display: none; }
.modal.active { display: flex !important; }
</style>
</head>
<body class="bg-lightbg min-h-screen">
  <?php include 'includes/header.php'; ?>

  <main class="max-w-7xl mx-auto px-4 pt-24 pb-10">
    <!-- Header -->
    <div class="mb-6">
      <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
        <div>
          <h1 class="font-heading text-3xl text-primary font-bold flex items-center gap-2">
            📦 Products Management
          </h1>
          <p class="text-sm text-gray-600 mt-1">Manage your inventory, stock levels, and product catalog</p>
        </div>
        <button onclick="openAddModal()" class="btn-primary flex items-center gap-2 shadow-lg hover:shadow-xl transition px-5 py-2.5">
          ➕ Add New Product
        </button>
      </div>

      <!-- Stats Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow-md p-5 border-l-4 border-primary hover:shadow-lg transition">
          <div class="text-gray-600 text-sm font-medium mb-1">Total Products</div>
          <div class="text-3xl font-bold text-primary"><?= number_format($totalCount) ?></div>
        </div>
        <div class="bg-white rounded-lg shadow-md p-5 border-l-4 border-danger hover:shadow-lg transition">
          <div class="text-gray-600 text-sm font-medium mb-1">Low Stock Items</div>
          <div class="text-3xl font-bold text-danger"><?= number_format($lowStockCount) ?></div>
        </div>
        <div class="bg-white rounded-lg shadow-md p-5 border-l-4 border-success hover:shadow-lg transition">
          <div class="text-gray-600 text-sm font-medium mb-1">Inventory Value</div>
          <div class="text-3xl font-bold text-success">Rs <?= number_format($totalValue, 2) ?></div>
        </div>
        <div class="bg-white rounded-lg shadow-md p-5 border-l-4 border-secondary hover:shadow-lg transition">
          <div class="text-gray-600 text-sm font-medium mb-1">Categories</div>
          <div class="text-3xl font-bold text-secondary"><?= number_format($categories) ?></div>
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

    <!-- Search & Import -->
    <div class="card mb-6 shadow-md">
      <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
        <h2 class="font-heading text-lg font-semibold text-gray-800 flex items-center gap-2">
          🔍 Search & Filter
        </h2>
        
        <form method="post" enctype="multipart/form-data" class="flex items-center gap-2" id="importForm">
          <?= csrf_input() ?>
          <input type="hidden" name="action" value="import">
          <label class="btn-secondary cursor-pointer flex items-center gap-2 px-4 py-2">
            📄 Choose CSV
            <input type="file" name="csv" accept=".csv" class="hidden" id="csvInput" required>
          </label>
          <button class="btn-primary flex items-center gap-2 px-4 py-2" type="submit" id="importBtn" disabled>
            📥 Import Products
          </button>
          <button type="button" onclick="showCSVFormat()" class="text-sm text-secondary hover:underline">
            View CSV Format
          </button>
        </form>
      </div>

      <form method="get" class="grid grid-cols-1 md:grid-cols-4 gap-3">
        <div class="md:col-span-2">
          <input 
            type="text" 
            name="search" 
            value="<?= e($search) ?>" 
            placeholder="🔍 Search by name or composition..." 
            class="input w-full"
          >
        </div>
        <div>
          <select name="category" class="input w-full">
            <option value="">All Categories</option>
            <option value="antibiotics" <?= $category==='antibiotics'?'selected':'' ?>>Antibiotics</option>
            <option value="neutration" <?= $category==='neutration'?'selected':'' ?>>Neutration</option>
          </select>
        </div>
        <div class="flex gap-2">
          <button class="btn-primary flex-1 px-4 py-2" type="submit">Apply Filter</button>
          <?php if ($search || $category): ?>
            <a href="products.php" class="btn-secondary px-4 py-2">Clear</a>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <!-- Products Table -->
    <div class="card overflow-hidden shadow-md">
      <div class="mb-4 flex items-center justify-between px-2">
        <h2 class="font-heading text-lg font-semibold text-gray-800">
          Products List 
          <span class="text-sm font-normal text-gray-500">
            (Showing <?= count($products) ?> of <?= number_format($totalProducts) ?>)
          </span>
        </h2>
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="bg-gradient-to-r from-primary to-dark text-white">
              <th class="th text-center w-12">#</th>
              <th class="th text-left">Product Details</th>
              <th class="th text-center w-32">Category</th>
              <th class="th text-left w-40">Form</th>
              <th class="th text-center w-20">Unit</th>
              <th class="th text-right w-28">Buy Price</th>
              <th class="th text-right w-28">Sell Price</th>
              <th class="th text-center w-24">Stock</th>
              <th class="th text-center w-20">Min</th>
              <th class="th text-center w-40">Actions</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <?php if (empty($products)): ?>
              <tr>
                <td colspan="10" class="td text-center text-gray-500 py-12">
                  <div class="text-5xl mb-3">📭</div>
                  <div class="text-lg font-medium">No products found</div>
                  <?php if ($search || $category): ?>
                    <a href="products.php" class="text-primary hover:underline text-sm mt-2 inline-block">Clear filters</a>
                  <?php endif; ?>
                </td>
              </tr>
            <?php else: 
              $counter = $offset + 1;
              foreach ($products as $p): 
                $isLowStock = (int)$p['stock_quantity'] < (int)$p['min_stock'];
            ?>
              <tr class="hover:bg-gray-50 transition <?= $isLowStock ? 'stock-low' : '' ?>">
                <td class="td text-center text-gray-600 font-semibold"><?= $counter++ ?></td>
                <td class="td">
                  <div class="font-semibold text-gray-800"><?= e($p['name']) ?></div>
                  <div class="text-xs text-gray-500 truncate max-w-xs" title="<?= e($p['composition']) ?>">
                    <?= e($p['composition']) ?>
                  </div>
                </td>
                <td class="td text-center">
                  <span class="inline-block px-2 py-1 bg-primary/10 text-primary rounded text-xs font-medium capitalize">
                    <?= e($p['category']) ?>
                  </span>
                </td>
                <td class="td text-gray-700"><?= e($p['form']) ?></td>
                <td class="td text-center text-gray-700"><?= e($p['unit']) ?></td>
                <td class="td text-right text-gray-700">Rs <?= number_format((float)$p['buy_price'], 2) ?></td>
                <td class="td text-right font-semibold text-success">Rs <?= number_format((float)$p['sell_price'], 2) ?></td>
                <td class="td text-center">
                  <form method="post" class="inline" onsubmit="return confirm('Update stock?');">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="stock">
                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                    <input 
                      type="number" 
                      name="stock_quantity" 
                      value="<?= (int)$p['stock_quantity'] ?>" 
                      min="0"
                      class="w-16 border rounded px-2 py-1 text-center font-bold <?= $isLowStock ? 'border-danger text-danger bg-red-50' : 'border-success text-success bg-green-50' ?>"
                      onchange="this.form.submit()"
                    >
                  </form>
                </td>
                <td class="td text-center text-gray-600 font-medium"><?= (int)$p['min_stock'] ?></td>
                <td class="td text-center">
                  <div class="flex items-center justify-center gap-1">
                    <button 
                      onclick="openEditModal(<?= (int)$p['id'] ?>)" 
                      class="btn-secondary text-xs px-3 py-1.5 hover:bg-secondary hover:text-white transition"
                      title="Edit Product"
                    >
                      ✏️ Edit
                    </button>
                    <form method="post" class="inline" onsubmit="return confirm('⚠️ Delete this product permanently?');">
                      <?= csrf_input() ?>
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                      <button class="btn-danger text-xs px-3 py-1.5" type="submit" title="Delete Product">
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
              <a href="?page=<?= $page - 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $category ? '&category=' . urlencode($category) : '' ?>" 
                 class="btn-secondary px-4 py-2">
                ← Prev
              </a>
            <?php endif; ?>
            
            <?php
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);
            for ($i = $startPage; $i <= $endPage; $i++):
            ?>
              <a href="?page=<?= $i ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $category ? '&category=' . urlencode($category) : '' ?>" 
                 class="<?= $i === $page ? 'bg-primary text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?> px-3 py-2 rounded transition font-medium">
                <?= $i ?>
              </a>
            <?php endfor; ?>
            
            <?php if ($page < $totalPages): ?>
              <a href="?page=<?= $page + 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $category ? '&category=' . urlencode($category) : '' ?>" 
                 class="btn-primary px-4 py-2">
                Next →
              </a>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <!-- Legend -->
    <div class="mt-4 card shadow-md">
      <h3 class="font-semibold text-sm mb-2 text-gray-800">📊 Stock Status Legend:</h3>
      <div class="flex items-center gap-4 text-xs">
        <div class="flex items-center gap-2">
          <div class="w-4 h-4 bg-green-100 rounded"></div>
          <span>Normal Stock</span>
        </div>
        <div class="flex items-center gap-2">
          <div class="w-4 h-4 bg-red-100 rounded"></div>
          <span>Low Stock (Below minimum)</span>
        </div>
      </div>
    </div>
  </main>

    <!-- Add/Edit Product Modal -->
  <div id="productModal" class="modal fixed inset-0 bg-black/50 items-center justify-center p-4 z-50">
    <div class="bg-white rounded-lg shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
      <!-- Header with gradient background -->
      <div class="sticky top-0 bg-gradient-to-r from-primary to-dark text-white border-b px-6 py-4 flex items-center justify-between">
        <h3 class="font-heading text-xl font-bold" id="modalTitle">Add New Product</h3>
        <button onclick="closeModal()" class="text-gray-200 hover:text-white text-3xl leading-none transition">
          &times;
        </button>
      </div>
      
      <form id="productForm" class="p-6">
        <?= csrf_input() ?>
        <input type="hidden" name="action" id="formAction" value="add">
        <input type="hidden" name="id" id="productId">
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- Product Name - Full Width -->
          <div class="md:col-span-2">
            <label for="productName" class="block text-sm font-bold text-gray-800 mb-2">Product Name <span class="text-red-600">*</span></label>
            <input 
              type="text" 
              name="name" 
              id="productName" 
              class="w-full border-2 border-gray-300 rounded-md px-4 py-2.5 text-gray-900 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-blue-200 transition" 
              required 
              placeholder="e.g., Amoxicillin 100g"
            >
          </div>
          
          <!-- Category -->
          <div>
            <label for="productCategory" class="block text-sm font-bold text-gray-800 mb-2">Category <span class="text-red-600">*</span></label>
            <select 
              name="category" 
              id="productCategory" 
              class="w-full border-2 border-gray-300 rounded-md px-4 py-2.5 text-gray-900 bg-white focus:border-primary focus:outline-none focus:ring-2 focus:ring-blue-200 transition cursor-pointer" 
              required
            >
              <option value="">-- Select Category --</option>
              <option value="antibiotics">Antibiotics</option>
              <option value="neutration">Neutration</option>
            </select>
          </div>
          
          <!-- Form Type -->
          <div>
            <label for="productForm" class="block text-sm font-bold text-gray-800 mb-2">Form <span class="text-red-600">*</span></label>
            <select 
              name="form" 
              id="productForm" 
              class="w-full border-2 border-gray-300 rounded-md px-4 py-2.5 text-gray-900 bg-white focus:border-primary focus:outline-none focus:ring-2 focus:ring-blue-200 transition cursor-pointer" 
              required
            >
              <option value="">-- Select Form --</option>
              <option value="Water Soluble Powder">Water Soluble Powder</option>
              <option value="Liquid Solution">Liquid Solution</option>
              <option value="Feed Premix Powder">Feed Premix Powder</option>
            </select>
          </div>
          
          <!-- Unit -->
          <div>
            <label for="productUnit" class="block text-sm font-bold text-gray-800 mb-2">Unit <span class="text-red-600">*</span></label>
            <input 
              type="text" 
              name="unit" 
              id="productUnit" 
              class="w-full border-2 border-gray-300 rounded-md px-4 py-2.5 text-gray-900 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-blue-200 transition" 
              required 
              placeholder="e.g., 100g, 100ml"
            >
          </div>
          
          <!-- Buy Price -->
          <div>
            <label for="productBuyPrice" class="block text-sm font-bold text-gray-800 mb-2">Buy Price (Rs) <span class="text-red-600">*</span></label>
            <input 
              type="number" 
              step="0.01" 
              name="buy_price" 
              id="productBuyPrice" 
              class="w-full border-2 border-gray-300 rounded-md px-4 py-2.5 text-gray-900 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-blue-200 transition" 
              required 
              min="0"
              placeholder="0.00"
            >
          </div>
          
          <!-- Sell Price -->
          <div>
            <label for="productSellPrice" class="block text-sm font-bold text-gray-800 mb-2">Sell Price (Rs) <span class="text-red-600">*</span></label>
            <input 
              type="number" 
              step="0.01" 
              name="sell_price" 
              id="productSellPrice" 
              class="w-full border-2 border-gray-300 rounded-md px-4 py-2.5 text-gray-900 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-blue-200 transition" 
              required 
              min="0"
              placeholder="0.00"
            >
          </div>
          
          <!-- Stock Quantity -->
          <div>
            <label for="productStock" class="block text-sm font-bold text-gray-800 mb-2">Stock Quantity <span class="text-red-600">*</span></label>
            <input 
              type="number" 
              name="stock_quantity" 
              id="productStock" 
              class="w-full border-2 border-gray-300 rounded-md px-4 py-2.5 text-gray-900 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-blue-200 transition" 
              required 
              min="0"
              placeholder="0"
            >
          </div>
          
          <!-- Min Stock -->
          <div>
            <label for="productMinStock" class="block text-sm font-bold text-gray-800 mb-2">Min Stock <span class="text-red-600">*</span></label>
            <input 
              type="number" 
              name="min_stock" 
              id="productMinStock" 
              class="w-full border-2 border-gray-300 rounded-md px-4 py-2.5 text-gray-900 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-blue-200 transition" 
              required 
              min="0"
              placeholder="0"
            >
          </div>
          
          <!-- Composition -->
          <div class="md:col-span-2">
            <label for="productComposition" class="block text-sm font-bold text-gray-800 mb-2">Composition</label>
            <textarea 
              name="composition" 
              id="productComposition" 
              class="w-full border-2 border-gray-300 rounded-md px-4 py-2.5 text-gray-900 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-blue-200 transition" 
              rows="3" 
              placeholder="e.g., Amoxicillin trihydrate 250mg/g"
            ></textarea>
          </div>
        </div>
        
        <!-- Footer Actions -->
        <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-gray-200">
          <button type="button" onclick="closeModal()" class="px-6 py-2.5 rounded-md font-medium text-gray-700 bg-gray-200 hover:bg-gray-300 transition">
            Cancel
          </button>
          <button type="submit" class="px-6 py-2.5 rounded-md font-medium text-white bg-primary hover:bg-blue-700 transition shadow-md hover:shadow-lg" id="submitBtn">
            <span id="submitText">Add Product</span>
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- CSV Format Modal -->
  <div id="csvModal" class="modal fixed inset-0 bg-black/50 items-center justify-center p-4 z-50">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl p-6">
      <div class="flex items-center justify-between mb-4">
        <h3 class="font-heading text-xl text-primary">📄 CSV Import Format</h3>
        <button onclick="document.getElementById('csvModal').classList.remove('active')" class="text-gray-500 hover:text-black text-2xl">
          &times;
        </button>
      </div>
      <div class="text-sm space-y-3">
        <p class="text-gray-700">Your CSV file must have the following columns in order:</p>
        <div class="bg-gray-50 p-3 rounded border font-mono text-xs overflow-x-auto">
          name,category,composition,unit,form,buy_price,sell_price,stock_quantity,min_stock
        </div>
        <p class="font-semibold">Example:</p>
        <div class="bg-gray-50 p-3 rounded border font-mono text-xs overflow-x-auto">
          Amoxicillin 100g,antibiotics,Amoxicillin trihydrate 250mg/g,100g,Water Soluble Powder,120.00,180.00,50,10
        </div>
      </div>
      <div class="mt-4 flex justify-end">
        <button onclick="document.getElementById('csvModal').classList.remove('active')" class="btn-primary">
          Got it!
        </button>
      </div>
    </div>
  </div>

  <script>
  const CSRF = '<?= e(csrf_token()) ?>';

  // CSV Import
  document.getElementById('csvInput').addEventListener('change', function() {
    document.getElementById('importBtn').disabled = !this.files.length;
  });

  function showCSVFormat() {
    document.getElementById('csvModal').classList.add('active');
  }

  // Product Modal Functions
  function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add New Product';
    document.getElementById('formAction').value = 'add';
    document.getElementById('submitText').textContent = 'Add Product';
    document.getElementById('productForm').reset();
    document.getElementById('productId').value = '';
    document.getElementById('productModal').classList.add('active');
  }

  function openEditModal(id) {
    document.getElementById('modalTitle').textContent = 'Edit Product';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('submitText').textContent = 'Update Product';
    
    // Fetch product data
    fetch(`?get_product=${id}`)
      .then(res => res.json())
      .then(product => {
        if (product.id) {
          document.getElementById('productId').value = product.id;
          document.getElementById('productName').value = product.name || '';
          document.getElementById('productCategory').value = product.category || '';
          document.getElementById('productForm').value = product.form || '';
          document.getElementById('productUnit').value = product.unit || '';
          document.getElementById('productBuyPrice').value = product.buy_price || 0;
          document.getElementById('productSellPrice').value = product.sell_price || 0;
          document.getElementById('productStock').value = product.stock_quantity || 0;
          document.getElementById('productMinStock').value = product.min_stock || 0;
          document.getElementById('productComposition').value = product.composition || '';
          document.getElementById('productModal').classList.add('active');
        }
      });
  }

  function closeModal() {
    document.getElementById('productModal').classList.remove('active');
    document.getElementById('productForm').reset();
  }

  // Form submission
  document.getElementById('productForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const submitBtn = document.getElementById('submitBtn');
    const submitText = document.getElementById('submitText');
    const originalText = submitText.textContent;
    
    submitBtn.disabled = true;
    submitText.textContent = 'Processing...';
    
    const formData = new FormData(this);
    
    fetch('products.php', {
      method: 'POST',
      body: formData
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        alert('✅ ' + data.message);
        window.location.reload();
      } else {
        alert('❌ ' + data.message);
      }
    })
    .catch(() => {
      alert('❌ An error occurred. Please try again.');
    })
    .finally(() => {
      submitBtn.disabled = false;
      submitText.textContent = originalText;
    });
  });

  // Auto-hide flash messages
  setTimeout(() => {
    document.querySelectorAll('.alert-success, .alert-danger').forEach(alert => {
      alert.style.transition = 'opacity 0.5s';
      alert.style.opacity = '0';
      setTimeout(() => alert.remove(), 500);
    });
  }, 5000);
  </script>
</body>
</html>