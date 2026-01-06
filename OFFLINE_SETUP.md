# Offline Asset Setup

This project has been configured to work completely offline without any external CDN dependencies.

## Local Assets Included

### 1. Tailwind CSS
- **Location**: `assets/css/tailwind.min.css`
- **Source**: Built locally using Tailwind CSS standalone CLI
- **Configuration**: `tailwind.config.js` with custom colors and fonts

### 2. Google Fonts (Inter & Poppins)
- **Location**: `assets/fonts/inter/` and `assets/fonts/poppins/`
- **CSS**: `assets/css/fonts.css`
- **Weights included**: 300, 400, 500, 600, 700 (Inter and Poppins)
- **Formats**: WOFF and WOFF2

### 3. FontAwesome
- **Location**: `fontawesome/`
- **CSS**: `fontawesome/css/all.min.css`
- **Includes**: All FontAwesome icons and webfonts

### 4. JavaScript Libraries
- **jQuery**: `assets/js/jquery-3.6.0.min.js` (v3.6.0)
- **Chart.js**: `assets/js/chart.min.js` (for dashboard charts)
- **html2pdf.js**: `assets/js/html2pdf.bundle.min.js` (for invoice generation)

## Rebuilding Tailwind CSS

If you need to rebuild the Tailwind CSS after making changes:

1. Ensure you have the Tailwind CSS standalone CLI:
   ```bash
   curl -sLO https://github.com/tailwindlabs/tailwindcss/releases/latest/download/tailwindcss-linux-x64
   chmod +x tailwindcss-linux-x64
   ```

2. Build the CSS:
   ```bash
   ./tailwindcss-linux-x64 -i assets/css/tailwind-input.css -o assets/css/tailwind.min.css --minify
   ```

## Files Modified

The following files have been updated to use local assets instead of CDNs:
- `includes/header.php` - Main header template
- `login.php` - Login page
- `invoice.php` - Invoice generation page
- `products.php` - Products management page
- `product_edit.php` - Product edit page
- `product_add.php` - Product add page
- `customer_add.php` - Customer add page
- `dashboard.php` - Dashboard with charts

## Benefits

- ✅ No internet connection required
- ✅ Faster page loads (no external requests)
- ✅ Better privacy (no tracking from CDNs)
- ✅ Works in restricted environments
- ✅ More reliable (no CDN downtime)

## Note

The `node_modules/` directory and `tailwindcss-linux-x64` binary are excluded from version control via `.gitignore`. They are only needed for development/building purposes.
