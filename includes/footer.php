</main>

<!-- Footer -->
<footer class="bg-white border-t border-gray-200 mt-10 no-print">
  <div class="max-w-7xl mx-auto px-4 py-6">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <!-- Company Info -->
      <div>
        <h3 class="font-heading font-semibold text-primary mb-2"><?= e(COMPANY_NAME) ?></h3>
        <p class="text-sm text-gray-600"><?= e(COMPANY_TAGLINE) ?></p>
      </div>

      <!-- Contact Info -->
      <div>
        <h3 class="font-heading font-semibold text-primary mb-2">Contact Us</h3>
        <div class="text-sm text-gray-600 space-y-1">
          <?php 
          $contacts = json_decode(COMPANY_CONTACTS, true);
          foreach ($contacts as $city => $info): 
          ?>
            <div>
              <strong><?= e($city) ?>:</strong> <?= e($info['phone']) ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Quick Links -->
      <div>
        <h3 class="font-heading font-semibold text-primary mb-2">Quick Links</h3>
        <div class="text-sm text-gray-600 space-y-1">
          <a href="dashboard.php" class="block hover:text-primary transition">Dashboard</a>
          <a href="pos.php" class="block hover:text-primary transition">Point of Sale</a>
          <a href="products.php" class="block hover:text-primary transition">Manage Products</a>
          <a href="orders.php" class="block hover:text-primary transition">View Orders</a>
        </div>
      </div>
    </div>

    <div class="border-t border-gray-200 mt-6 pt-4 text-center text-sm text-gray-500">
      <p>&copy; <?= date('Y') ?> <?= e(COMPANY_NAME) ?>. All rights reserved.</p>
      <p class="text-xs mt-1">Powered by Alfah POS System v1.0</p>
    </div>
  </div>
</footer>

<!-- Global JavaScript -->
<script>
// CSRF Token
window.CSRF_TOKEN = '<?= e(csrf_token()) ?>';

// Auto-hide flash messages after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
  setTimeout(() => {
    const alerts = document.querySelectorAll('.alert-success, .alert-danger, .alert-warning');
    alerts.forEach(alert => {
      alert.style.transition = 'opacity 0.5s ease';
      alert.style.opacity = '0';
      setTimeout(() => alert.remove(), 500);
    });
  }, 5000);
});

// Confirm before leaving page with unsaved changes
let formChanged = false;
document.querySelectorAll('form').forEach(form => {
  form.addEventListener('change', () => formChanged = true);
  form.addEventListener('submit', () => formChanged = false);
});

window.addEventListener('beforeunload', (e) => {
  if (formChanged) {
    e.preventDefault();
    e.returnValue = '';
  }
});

// Global Modal Functions
function openModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
  }
}

function closeModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.remove('active');
    document.body.style.overflow = 'auto';
  }
}

// Close modal on background click
document.addEventListener('click', function(e) {
  if (e.target.classList.contains('modal')) {
    closeModal(e.target.id);
  }
});

// Escape key closes modal
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal.active').forEach(modal => {
      closeModal(modal.id);
    });
  }
});

// Number formatting helper
function formatCurrency(amount) {
  return 'Rs ' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

// Format number with commas
function formatNumber(num) {
  return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

// Show loading spinner
function showLoading(message = 'Loading...') {
  const loader = document.createElement('div');
  loader.id = 'globalLoader';
  loader.className = 'fixed inset-0 bg-black/50 flex items-center justify-center z-[9999]';
  loader.innerHTML = `
    <div class="bg-white rounded-lg p-6 flex flex-col items-center gap-3">
      <div class="spinner"></div>
      <div class="text-gray-700 font-medium">${message}</div>
    </div>
  `;
  document.body.appendChild(loader);
}

function hideLoading() {
  const loader = document.getElementById('globalLoader');
  if (loader) loader.remove();
}

// Toast notification
function showToast(message, type = 'success') {
  const toast = document.createElement('div');
  const bgColor = type === 'success' ? 'bg-success' : type === 'error' ? 'bg-danger' : 'bg-warning';
  const icon = type === 'success' ? '✅' : type === 'error' ? '❌' : '⚠️';
  
  toast.className = `fixed top-24 right-4 ${bgColor} text-white px-6 py-3 rounded-lg shadow-lg z-50 animate-fade-in`;
  toast.innerHTML = `
    <div class="flex items-center gap-2">
      <span class="text-xl">${icon}</span>
      <span>${message}</span>
    </div>
  `;
  
  document.body.appendChild(toast);
  
  setTimeout(() => {
    toast.style.transition = 'opacity 0.3s ease';
    toast.style.opacity = '0';
    setTimeout(() => toast.remove(), 300);
  }, 3000);
}

// Confirm dialog with better styling
function confirmAction(message, callback) {
  if (confirm(message)) {
    callback();
  }
}

// Print function
function printPage() {
  window.print();
}

// Copy to clipboard
function copyToClipboard(text) {
  navigator.clipboard.writeText(text).then(() => {
    showToast('Copied to clipboard!', 'success');
  }).catch(() => {
    showToast('Failed to copy', 'error');
  });
}

console.log('%c🚀 Alfah POS System', 'color: #1E40AF; font-size: 20px; font-weight: bold;');
console.log('%cVersion 1.0 | Developed for Alfah Tech International', 'color: #0EA5E9; font-size: 12px;');
</script>

</body>
</html>