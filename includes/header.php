<?php
// Ensure session and authentication
if (!defined('PAGE_TITLE')) {
    define('PAGE_TITLE', 'Alfah POS');
}

// Get current page for active menu
$current_page = basename($_SERVER['PHP_SELF'], '.php');

// Get user info
$user = current_user();
$username = $user['username'] ?? 'Guest';
$role = $user['role'] ?? 'user';

// Get pending returns count (for notification badge)
$pendingReturnsCount = 0;
try {
    $pendingReturnsCount = (int)$pdo->query("SELECT COUNT(*) FROM returns WHERE status = 'pending'")->fetchColumn();
} catch (Exception $e) {
    // Ignore error
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?= e(PAGE_TITLE) ?> - <?= e(SITE_NAME) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="<?= e(COMPANY_NAME) ?> - <?= e(COMPANY_TAGLINE) ?>">
<link rel="icon" type="image/png" href="assets/images/logo.png">

<!-- Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<!-- Tailwind CSS -->
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
  theme: {
    extend: {
      colors: {
        primary: '#1E40AF',
        secondary: '#0EA5E9',
        success: '#10B981',
        warning: '#F59E0B',
        danger: '#EF4444',
        lightbg: '#F0F9FF',
        dark: '#1E3A8A',
      },
      fontFamily: {
        sans: ['Inter', 'ui-sans-serif', 'system-ui'],
        heading: ['Poppins', 'ui-sans-serif', 'system-ui']
      }
    }
  }
}
</script>

<!-- Custom Styles -->
<link rel="stylesheet" href="assets/css/style.css">

<!-- jQuery (if needed) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<style>
/* Global Animations */
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-10px); }
  to { opacity: 1; transform: translateY(0); }
}

.animate-fade-in {
  animation: fadeIn 0.3s ease-in-out;
}

/* Loading Spinner */
.spinner {
  border: 3px solid #f3f4f6;
  border-top: 3px solid #1E40AF;
  border-radius: 50%;
  width: 40px;
  height: 40px;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

/* Modal Styles */
.modal {
  display: none;
  transition: all 0.3s ease;
}

.modal.active {
  display: flex !important;
}

.modal-content {
  animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
  from {
    opacity: 0;
    transform: translateY(-50px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Stock Status Colors */
.stock-low {
  background-color: #fee;
  border-left: 3px solid #EF4444;
}

.stock-good {
  background-color: #efe;
  border-left: 3px solid #10B981;
}

/* Print Styles */
@media print {
  .no-print {
    display: none !important;
  }
  
  nav, .modal, .alert-success, .alert-danger {
    display: none !important;
  }
}

/* Responsive Table */
@media (max-width: 768px) {
  .table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }
}

/* Utility Classes */
.line-clamp-1 {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.line-clamp-2 {
  overflow: hidden;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

/* Notification Badge */
.notification-badge {
  position: absolute;
  top: -6px;
  right: -6px;
  background: #EF4444;
  color: white;
  font-size: 10px;
  font-weight: 700;
  padding: 2px 6px;
  border-radius: 10px;
  min-width: 18px;
  text-align: center;
  animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes pulse {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: .7;
  }
}

/* Mobile Menu Toggle */
.mobile-menu {
  display: none;
}

@media (max-width: 768px) {
  .mobile-menu.active {
    display: flex;
    flex-direction: column;
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: linear-gradient(to right, #1E40AF, #1E3A8A);
    padding: 1rem;
    box-shadow: 0 10px 25px rgba(0,0,0,0.2);
  }
}
</style>

</head>
<body class="bg-lightbg min-h-screen">

<!-- Navigation Bar -->
<nav class="bg-gradient-to-r from-primary via-dark to-primary text-white fixed top-0 left-0 right-0 z-50 shadow-lg no-print">
  <div class="max-w-7xl mx-auto px-4 py-3">
    <div class="flex items-center justify-between">
      <!-- Logo & Company Info -->
      <div class="flex items-center gap-3">
        <img src="assets/images/logo.png" class="h-10 w-10 rounded" alt="<?= e(COMPANY_NAME) ?> Logo">
        <div class="hidden sm:block">
<div class="font-heading font-semibold text-lg leading-tight"><?= e(COMPANY_NAME) ?></div>
          <div class="text-xs opacity-90 leading-tight"><?= e(COMPANY_TAGLINE) ?></div>
        </div>
      </div>

      <!-- Desktop Navigation -->
      <div class="hidden lg:flex items-center gap-2 text-sm flex-wrap">
        <a href="dashboard.php" 
           class="<?= $current_page === 'dashboard' ? 'bg-white/20 font-semibold' : 'hover:bg-white/10' ?> px-3 py-1.5 rounded transition flex items-center gap-1">
          <span>📊</span>
          <span>Dashboard</span>
        </a>
        <a href="pos.php" 
           class="<?= $current_page === 'pos' ? 'bg-white/20 font-semibold' : 'hover:bg-white/10' ?> px-3 py-1.5 rounded transition flex items-center gap-1">
          <span>🛒</span>
          <span>POS</span>
        </a>
        <a href="products.php" 
           class="<?= $current_page === 'products' || $current_page === 'product_add' || $current_page === 'product_edit' ? 'bg-white/20 font-semibold' : 'hover:bg-white/10' ?> px-3 py-1.5 rounded transition flex items-center gap-1">
          <span>📦</span>
          <span>Products</span>
        </a>
        <a href="customers.php" 
           class="<?= $current_page === 'customers' || $current_page === 'customer_add' ? 'bg-white/20 font-semibold' : 'hover:bg-white/10' ?> px-3 py-1.5 rounded transition flex items-center gap-1">
          <span>👥</span>
          <span>Customers</span>
        </a>
        <a href="orders.php" 
           class="<?= $current_page === 'orders' || $current_page === 'invoice' ? 'bg-white/20 font-semibold' : 'hover:bg-white/10' ?> px-3 py-1.5 rounded transition flex items-center gap-1">
          <span>📋</span>
          <span>Orders</span>
        </a>
        <a href="returns.php" 
           class="<?= $current_page === 'returns' || $current_page === 'create_return' ? 'bg-white/20 font-semibold' : 'hover:bg-white/10' ?> px-3 py-1.5 rounded transition flex items-center gap-1 relative">
          <span>🔄</span>
          <span>Returns</span>
          <?php if ($pendingReturnsCount > 0): ?>
            <span class="notification-badge"><?= $pendingReturnsCount ?></span>
          <?php endif; ?>
        </a>
        
        <span class="opacity-60 hidden xl:inline">|</span>
        
        <!-- User Info -->
        <div class="flex items-center gap-2 bg-white/10 px-3 py-1.5 rounded">
          <span class="text-xs opacity-90">👤</span>
          <span class="font-medium"><?= e($username) ?></span>
          <span class="text-xs opacity-75 hidden xl:inline">(<?= e(ucfirst($role)) ?>)</span>
        </div>
        
        <!-- Logout -->
        <a href="logout.php" 
           class="bg-danger hover:bg-red-700 px-4 py-1.5 rounded transition font-medium shadow-md hover:shadow-lg flex items-center gap-1"
           onclick="return confirm('Are you sure you want to logout?')">
          <span>🚪</span>
          <span class="hidden xl:inline">Logout</span>
        </a>
      </div>

      <!-- Mobile Menu Button -->
      <button id="mobileMenuBtn" class="lg:hidden bg-white/10 hover:bg-white/20 px-3 py-2 rounded transition">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
      </button>
    </div>

    <!-- Mobile Navigation Menu -->
    <div id="mobileMenu" class="mobile-menu">
      <a href="dashboard.php" class="<?= $current_page === 'dashboard' ? 'bg-white/20 font-semibold' : 'hover:bg-white/10' ?> px-3 py-2 rounded transition flex items-center gap-2">
        <span>📊</span>
        <span>Dashboard</span>
      </a>
      <a href="pos.php" class="<?= $current_page === 'pos' ? 'bg-white/20 font-semibold' : 'hover:bg-white/10' ?> px-3 py-2 rounded transition flex items-center gap-2 mt-1">
        <span>🛒</span>
        <span>POS</span>
      </a>
      <a href="products.php" class="<?= $current_page === 'products' ? 'bg-white/20 font-semibold' : 'hover:bg-white/10' ?> px-3 py-2 rounded transition flex items-center gap-2 mt-1">
        <span>📦</span>
        <span>Products</span>
      </a>
      <a href="customers.php" class="<?= $current_page === 'customers' ? 'bg-white/20 font-semibold' : 'hover:bg-white/10' ?> px-3 py-2 rounded transition flex items-center gap-2 mt-1">
        <span>👥</span>
        <span>Customers</span>
      </a>
      <a href="orders.php" class="<?= $current_page === 'orders' ? 'bg-white/20 font-semibold' : 'hover:bg-white/10' ?> px-3 py-2 rounded transition flex items-center gap-2 mt-1">
        <span>📋</span>
        <span>Orders</span>
      </a>
      <a href="returns.php" class="<?= $current_page === 'returns' ? 'bg-white/20 font-semibold' : 'hover:bg-white/10' ?> px-3 py-2 rounded transition flex items-center gap-2 mt-1 relative">
        <span>🔄</span>
        <span>Returns</span>
        <?php if ($pendingReturnsCount > 0): ?>
          <span class="bg-danger text-white text-xs px-2 py-0.5 rounded-full ml-2"><?= $pendingReturnsCount ?> pending</span>
        <?php endif; ?>
      </a>
      
      <div class="border-t border-white/20 my-2"></div>
      
      <div class="px-3 py-2 bg-white/10 rounded flex items-center gap-2">
        <span>👤</span>
        <span class="font-medium"><?= e($username) ?></span>
        <span class="text-xs opacity-75">(<?= e(ucfirst($role)) ?>)</span>
      </div>
      
      <a href="logout.php" 
         class="bg-danger hover:bg-red-700 px-3 py-2 rounded transition font-medium shadow-md mt-2 flex items-center gap-2 justify-center"
         onclick="return confirm('Are you sure you want to logout?')">
        <span>🚪</span>
        <span>Logout</span>
      </a>
    </div>
  </div>
</nav>

<!-- Main Content Wrapper -->
<main class="pt-20 min-h-screen">

<script>
// Mobile menu toggle
document.addEventListener('DOMContentLoaded', function() {
  const mobileMenuBtn = document.getElementById('mobileMenuBtn');
  const mobileMenu = document.getElementById('mobileMenu');
  
  if (mobileMenuBtn && mobileMenu) {
    mobileMenuBtn.addEventListener('click', function() {
      mobileMenu.classList.toggle('active');
    });
    
    // Close mobile menu when clicking outside
    document.addEventListener('click', function(event) {
      if (!mobileMenuBtn.contains(event.target) && !mobileMenu.contains(event.target)) {
        mobileMenu.classList.remove('active');
      }
    });
  }
});
</script> 