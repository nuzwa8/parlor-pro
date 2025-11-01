<?php
/**
 * Plugin Name:       Retail Shop Accounting & Management
 * Description:       Aap ke retail/grocery store ke liye ek mukammal accounting, inventory, aur management solution.
 * Version:           1.0.0
 * Author:            Your Name
 * Author URI:        https://your-website.com
 * Text Domain:       rsam-plugin
 * Domain Path:       /languages
 * License:           GPLv2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

// Direct access ko rokein
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin version aur text domain set karein
define( 'RSAM_VERSION', '1.0.0' );
define( 'RSAM_TEXT_DOMAIN', 'rsam-plugin' );

/**
 * Part 1 — Plugin Activation (Database Tables aur Roles)
 */

/**
 * Plugin activate hone par chalne wala function.
 */
function rsam_activate_plugin() {
	// Database tables banayein
	rsam_create_database_tables();
	
	// User roles aur capabilities banayein
	rsam_create_roles();
	
	// Version number (database) mein save karein
	add_option( 'rsam_version', RSAM_VERSION );
}
register_activation_hook( __FILE__, 'rsam_activate_plugin' );

/**
 * Tamam custom table names ko manage karne ke liye helper function.
 *
 * @return array Table naamon ki list.
 */
function rsam_get_table_names() {
	global $wpdb;
	return [
		'products'          => $wpdb->prefix . 'rsam_products',
		'product_batches'   => $wpdb->prefix . 'rsam_product_batches',
		'purchases'         => $wpdb->prefix . 'rsam_purchases',
		'purchase_items'    => $wpdb->prefix . 'rsam_purchase_items',
		'sales'             => $wpdb->prefix . 'rsam_sales',
		'sale_items'        => $wpdb->prefix . 'rsam_sale_items',
		'expenses'          => $wpdb->prefix . 'rsam_expenses',
		'employees'         => $wpdb->prefix . 'rsam_employees',
		'suppliers'         => $wpdb->prefix . 'rsam_suppliers',
		'customers'         => $wpdb->prefix . 'rsam_customers',
		'customer_payments' => $wpdb->prefix . 'rsam_customer_payments', // (Payment records) ke liye (New)
	];
}

/**
 * Plugin ke liye zaroori (Database) tables banata hai.
 */
function rsam_create_database_tables() {
	global $wpdb;
	$tables      = rsam_get_table_names();
	$charset_collate = $wpdb->get_charset_collate();
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	// Products Table
	$sql_products = "CREATE TABLE {$tables['products']} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		name VARCHAR(255) NOT NULL,
		category VARCHAR(100) DEFAULT '' NOT NULL,
		unit_type VARCHAR(50) NOT NULL COMMENT 'e.g., kg, piece, liter',
		selling_price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
		stock_quantity DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
		low_stock_threshold DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
		supplier_id BIGINT(20) UNSIGNED DEFAULT 0,
		has_expiry BOOLEAN NOT NULL DEFAULT 0,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY  (id),
		KEY name (name),
		KEY category (category)
	) $charset_collate;";
	dbDelta( $sql_products );

	// Product Batches Table (Batch-wise costing ke liye)
	$sql_product_batches = "CREATE TABLE {$tables['product_batches']} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		product_id BIGINT(20) UNSIGNED NOT NULL,
		purchase_id BIGINT(20) UNSIGNED DEFAULT 0,
		batch_code VARCHAR(100) DEFAULT '' NOT NULL,
		quantity_received DECIMAL(10, 2) NOT NULL,
		quantity_in_stock DECIMAL(10, 2) NOT NULL,
		purchase_price DECIMAL(10, 2) NOT NULL COMMENT 'Base purchase price',
		cost_price DECIMAL(10, 2) NOT NULL COMMENT 'Purchase price + distributed costs',
		expiry_date DATE DEFAULT NULL,
		received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY  (id),
		KEY product_id (product_id),
		KEY purchase_id (purchase_id)
	) $charset_collate;";
	dbDelta( $sql_product_batches );

	// Purchases Table
	$sql_purchases = "CREATE TABLE {$tables['purchases']} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		supplier_id BIGINT(20) UNSIGNED DEFAULT 0,
		invoice_number VARCHAR(100) DEFAULT '' NOT NULL,
		purchase_date DATE NOT NULL,
		subtotal DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
		additional_costs DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT 'Transportation, tax, etc.',
		total_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
		notes TEXT,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY  (id),
		KEY supplier_id (supplier_id),
		KEY purchase_date (purchase_date)
	) $charset_collate;";
	dbDelta( $sql_purchases );

	// Purchase Items Table
	$sql_purchase_items = "CREATE TABLE {$tables['purchase_items']} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		purchase_id BIGINT(20) UNSIGNED NOT NULL,
		product_id BIGINT(20) UNSIGNED NOT NULL,
		batch_id BIGINT(20) UNSIGNED NOT NULL,
		quantity DECIMAL(10, 2) NOT NULL,
		purchase_price DECIMAL(10, 2) NOT NULL,
		item_subtotal DECIMAL(10, 2) NOT NULL,
		PRIMARY KEY  (id),
		KEY purchase_id (purchase_id),
		KEY product_id (product_id),
		KEY batch_id (batch_id)
	) $charset_collate;";
	dbDelta( $sql_purchase_items );

	// Sales Table
	$sql_sales = "CREATE TABLE {$tables['sales']} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id BIGINT(20) UNSIGNED DEFAULT 0,
		sale_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		subtotal DECIMAL(10, 2) NOT NULL,
		discount_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
		total_amount DECIMAL(10, 2) NOT NULL,
		total_cost DECIMAL(10, 2) NOT NULL COMMENT 'Total cost of goods sold',
		total_profit DECIMAL(10, 2) NOT NULL,
		payment_status VARCHAR(20) NOT NULL DEFAULT 'paid' COMMENT 'paid, unpaid, partial',
		notes TEXT,
		PRIMARY KEY  (id),
		KEY customer_id (customer_id),
		KEY sale_date (sale_date)
	) $charset_collate;";
	dbDelta( $sql_sales );

	// Sale Items Table
	$sql_sale_items = "CREATE TABLE {$tables['sale_items']} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		sale_id BIGINT(20) UNSIGNED NOT NULL,
		product_id BIGINT(20) UNSIGNED NOT NULL,
		batch_id BIGINT(20) UNSIGNED NOT NULL COMMENT 'Kis batch se farokht hua (FIFO)',
		quantity DECIMAL(10, 2) NOT NULL,
		selling_price DECIMAL(10, 2) NOT NULL,
		cost_price DECIMAL(10, 2) NOT NULL COMMENT 'Cost price from the batch',
		item_profit DECIMAL(10, 2) NOT NULL,
		PRIMARY KEY  (id),
		KEY sale_id (sale_id),
		KEY product_id (product_id)
	) $charset_collate;";
	dbDelta( $sql_sale_items );

	// Expenses Table
	$sql_expenses = "CREATE TABLE {$tables['expenses']} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		expense_date DATE NOT NULL,
		category VARCHAR(100) NOT NULL COMMENT 'e.g., rent, utility, salary, maintenance',
		amount DECIMAL(10, 2) NOT NULL,
		description TEXT,
		employee_id BIGINT(20) UNSIGNED DEFAULT 0 COMMENT 'Agar salary hai',
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY  (id),
		KEY expense_date (expense_date),
		KEY category (category)
	) $charset_collate;";
	dbDelta( $sql_expenses );

	// Employees Table
	$sql_employees = "CREATE TABLE {$tables['employees']} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		name VARCHAR(255) NOT NULL,
		phone VARCHAR(50) DEFAULT '' NOT NULL,
		designation VARCHAR(100) DEFAULT '' NOT NULL,
		monthly_salary DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
		joining_date DATE,
		is_active BOOLEAN NOT NULL DEFAULT 1,
		PRIMARY KEY  (id)
	) $charset_collate;";
	dbDelta( $sql_employees );

	// Suppliers Table
	$sql_suppliers = "CREATE TABLE {$tables['suppliers']} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		name VARCHAR(255) NOT NULL,
		phone VARCHAR(50) DEFAULT '' NOT NULL,
		address TEXT,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY  (id)
	) $charset_collate;";
	dbDelta( $sql_suppliers );

	// Customers Table (Khata/Credit ke liye)
	$sql_customers = "CREATE TABLE {$tables['customers']} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		name VARCHAR(255) NOT NULL,
		phone VARCHAR(50) DEFAULT '' NOT NULL,
		address TEXT,
		credit_balance DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY  (id)
	) $charset_collate;";
	dbDelta( $sql_customers );
}

/**
 * Custom roles aur (capabilities) banata hai.
 */
function rsam_create_roles() {
	// 'Shop Staff' role
	add_role(
		'shop_staff',
		__( 'Shop Staff', RSAM_TEXT_DOMAIN ),
		[
			'read'                   => true,
			'rsam_view_dashboard'    => true,
			'rsam_manage_products'   => true,
			'rsam_manage_purchases'  => true,
			'rsam_manage_sales'      => true,
			'rsam_manage_customers'  => true,
			'rsam_manage_suppliers'  => true,
		]
	);

	// 'Administrator' role ko (permissions) dein
	$admin_role = get_role( 'administrator' );
	if ( $admin_role ) {
		$admin_role->add_cap( 'rsam_view_dashboard', true );
		$admin_role->add_cap( 'rsam_manage_products', true );
		$admin_role->add_cap( 'rsam_manage_purchases', true );
		$admin_role->add_cap( 'rsam_manage_sales', true );
		$admin_role->add_cap( 'rsam_manage_expenses', true );
		$admin_role->add_cap( 'rsam_manage_employees', true );
		$admin_role->add_cap( 'rsam_manage_customers', true );
		$admin_role->add_cap( 'rsam_manage_suppliers', true );
		$admin_role->add_cap( 'rsam_view_reports', true );
		$admin_role->add_cap( 'rsam_manage_settings', true );
	}
}

/**
 * Plugin (translations) load karein.
 */
function rsam_load_textdomain() {
	load_plugin_textdomain( RSAM_TEXT_DOMAIN, false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'rsam_load_textdomain' );

/**
 * Deactivation par roles hata dein (safai ke liye)
 */
function rsam_deactivate_plugin() {
	remove_role( 'shop_staff' );
	
	// Admin se bhi (capabilities) hata dein
	$admin_role = get_role( 'administrator' );
	if ( $admin_role ) {
		$admin_role->remove_cap( 'rsam_view_dashboard' );
		$admin_role->remove_cap( 'rsam_manage_products' );
		$admin_role->remove_cap( 'rsam_manage_purchases' );
		$admin_role->remove_cap( 'rsam_manage_sales' );
		$admin_role->remove_cap( 'rsam_manage_expenses' );
		$admin_role->remove_cap( 'rsam_manage_employees' );
		$admin_role->remove_cap( 'rsam_manage_customers' );
		$admin_role->remove_cap( 'rsam_manage_suppliers' );
		$admin_role->remove_cap( 'rsam_view_reports' );
		$admin_role->remove_cap( 'rsam_manage_settings' );
	}
}
register_deactivation_hook( __FILE__, 'rsam_deactivate_plugin' );

/** Part 1 — Yahan khatam hua */

/**
 * Part 2 — Admin Menus, Enqueue Scripts, aur (AJAX) Data
 */

/**
 * (WordPress) Admin mein (Menu) pages banata hai.
 */
function rsam_admin_menus() {
	// Top-level menu
	add_menu_page(
		__( 'Shop Management', RSAM_TEXT_DOMAIN ), // Page title
		__( 'Shop Management', RSAM_TEXT_DOMAIN ), // Menu title
		'rsam_view_dashboard',                     // Capability
		'rsam-dashboard',                          // Menu slug
		'rsam_render_admin_page',                  // Function
		'dashicons-store',                         // Icon
		25                                         // Position
	);

	// Dashboard (Sub-menu)
	add_submenu_page(
		'rsam-dashboard',
		__( 'Dashboard', RSAM_TEXT_DOMAIN ),
		__( 'Dashboard', RSAM_TEXT_DOMAIN ),
		'rsam_view_dashboard',
		'rsam-dashboard',
		'rsam_render_admin_page'
	);

	// Products (Inventory) (Sub-menu)
	add_submenu_page(
		'rsam-dashboard',
		__( 'Products (Inventory)', RSAM_TEXT_DOMAIN ),
		__( 'Products', RSAM_TEXT_DOMAIN ),
		'rsam_manage_products',
		'rsam-products',
		'rsam_render_admin_page'
	);

	// Purchases (Sub-menu)
	add_submenu_page(
		'rsam-dashboard',
		__( 'Purchases', RSAM_TEXT_DOMAIN ),
		__( 'Purchases', RSAM_TEXT_DOMAIN ),
		'rsam_manage_purchases',
		'rsam-purchases',
		'rsam_render_admin_page'
	);

	// Sales (Sub-menu)
	add_submenu_page(
		'rsam-dashboard',
		__( 'Sales', RSAM_TEXT_DOMAIN ),
		__( 'Sales', RSAM_TEXT_DOMAIN ),
		'rsam_manage_sales',
		'rsam-sales',
		'rsam_render_admin_page'
	);

	// Expenses (Sub-menu)
	add_submenu_page(
		'rsam-dashboard',
		__( 'Expenses', RSAM_TEXT_DOMAIN ),
		__( 'Expenses', RSAM_TEXT_DOMAIN ),
		'rsam_manage_expenses',
		'rsam-expenses',
		'rsam_render_admin_page'
	);

	// Employees (Sub-menu)
	add_submenu_page(
		'rsam-dashboard',
		__( 'Employees', RSAM_TEXT_DOMAIN ),
		__( 'Employees', RSAM_TEXT_DOMAIN ),
		'rsam_manage_employees',
		'rsam-employees',
		'rsam_render_admin_page'
	);
    
	// Suppliers (Sub-menu)
	add_submenu_page(
		'rsam-dashboard',
		__( 'Suppliers', RSAM_TEXT_DOMAIN ),
		__( 'Suppliers', RSAM_TEXT_DOMAIN ),
		'rsam_manage_suppliers',
		'rsam-suppliers',
		'rsam_render_admin_page'
	);

	// Customers (Sub-menu)
	add_submenu_page(
		'rsam-dashboard',
		__( 'Customers (Khata)', RSAM_TEXT_DOMAIN ),
		__( 'Customers', RSAM_TEXT_DOMAIN ),
		'rsam_manage_customers',
		'rsam-customers',
		'rsam_render_admin_page'
	);

	// Reports (Sub-menu)
	add_submenu_page(
		'rsam-dashboard',
		__( 'Reports', RSAM_TEXT_DOMAIN ),
		__( 'Reports', RSAM_TEXT_DOMAIN ),
		'rsam_view_reports',
		'rsam-reports',
		'rsam_render_admin_page'
	);

	// Settings (Sub-menu)
	add_submenu_page(
		'rsam-dashboard',
		__( 'Settings', RSAM_TEXT_DOMAIN ),
		__( 'Settings', RSAM_TEXT_DOMAIN ),
		'rsam_manage_settings',
		'rsam-settings',
		'rsam_render_admin_page'
	);
}
add_action( 'admin_menu', 'rsam_admin_menus' );

/**
 * (JavaScript) aur (CSS) files ko (enqueue) karta hai.
 */
function rsam_admin_enqueue_scripts( $hook_suffix ) {
	// Sirf hamare plugin pages par (assets) load karein
	if ( strpos( $hook_suffix, 'rsam-' ) === false ) {
		return;
	}

	$plugin_url = plugin_dir_url( __FILE__ );
	$version    = RSAM_VERSION;

	// (CSS) file
	wp_enqueue_style(
		'rsam-admin-style',
		$plugin_url . 'assets/rsam-admin.css',
		[],
		$version
	);

	// (JavaScript) file
	wp_enqueue_script(
		'rsam-admin-script',
		$plugin_url . 'assets/rsam-admin.js',
		[ 'jquery' ], // (jQuery) par (depend) karta hai
		$version,
		true // (Footer) mein load karein
	);

	// (JavaScript) ko data bhejein (wp_localize_script)
	rsam_localize_script_data();
}
add_action( 'admin_enqueue_scripts', 'rsam_admin_enqueue_scripts' );

/**
 * (JavaScript) ke liye zaroori (PHP) data (localize) karta hai.
 * Is mein (AJAX URL), (nonce), (capabilities), aur (strings) shamil hain.
 */
function rsam_localize_script_data() {
	$current_user = wp_get_current_user();
	
	// User ki (capabilities) ka (map)
	$user_caps = [
		'canManageProducts'  => current_user_can( 'rsam_manage_products' ),
		'canManagePurchases' => current_user_can( 'rsam_manage_purchases' ),
		'canManageSales'     => current_user_can( 'rsam_manage_sales' ),
		'canManageExpenses'  => current_user_can( 'rsam_manage_expenses' ),
		'canManageEmployees' => current_user_can( 'rsam_manage_employees' ),
		'canManageSuppliers' => current_user_can( 'rsam_manage_suppliers' ),
		'canManageCustomers' => current_user_can( 'rsam_manage_customers' ),
		'canViewReports'     => current_user_can( 'rsam_view_reports' ),
		'canManageSettings'  => current_user_can( 'rsam_manage_settings' ),
	];

	// (JavaScript) mein 'rsamData' (object) ban jayega
	wp_localize_script(
		'rsam-admin-script',
		'rsamData',
		[
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'rsam-ajax-nonce' ),
			'caps'     => $user_caps,
			'strings'  => [
				'loading'              => __( 'Loading...', RSAM_TEXT_DOMAIN ),
				'errorOccurred'        => __( 'An error occurred. Please try again.', RSAM_TEXT_DOMAIN ),
				'confirmDelete'        => __( 'Are you sure you want to delete this?', RSAM_TEXT_DOMAIN ),
				'invalidInput'         => __( 'Please check your inputs.', RSAM_TEXT_DOMAIN ),
				'processing'           => __( 'Processing...', RSAM_TEXT_DOMAIN ),
				'itemSaved'            => __( 'Item saved successfully.', RSAM_TEXT_DOMAIN ),
				'itemDeleted'          => __( 'Item deleted successfully.', RSAM_TEXT_DOMAIN ),
                'noItemsFound'         => __( 'No items found.', RSAM_TEXT_DOMAIN ),
                'addNew'               => __( 'Add New', RSAM_TEXT_DOMAIN ),
                'edit'                 => __( 'Edit', RSAM_TEXT_DOMAIN ),
                'delete'               => __( 'Delete', RSAM_TEXT_DOMAIN ),
                'save'                 => __( 'Save', RSAM_TEXT_DOMAIN ),
                'cancel'               => __( 'Cancel', RSAM_TEXT_DOMAIN ),
                'close'                => __( 'Close', RSAM_TEXT_DOMAIN ),
			],
		]
	);
}

/**
 * Admin pages ko (render) karne ke liye bunyadi (callback) function.
 * Yeh (JavaScript) ke (mount) hone ke liye (root div) faraham karta hai.
 */
function rsam_render_admin_page() {
	// Maujooda (screen) ka (slug) hasil karein
	$screen_slug = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : 'rsam-dashboard';
	
	// (Slug) se 'rsam-' prefix hatayein
	$screen_name = str_replace( 'rsam-', '', $screen_slug );

	// Bunyadi (HTML container)
	// (JavaScript) is (data-screen) (attribute) ko istemal karke sahi (UI) (mount) karega.
	?>
	<div class="wrap">
		<div id="rsam-<?php echo esc_attr( $screen_name ); ?>-root" class="rsam-root" data-screen="<?php echo esc_attr( $screen_name ); ?>">
			<div class="rsam-loading-placeholder">
                <h2><?php esc_html_e( 'Shop Management', RSAM_TEXT_DOMAIN ); ?></h2>
                <p><?php esc_html_e( 'Loading...', RSAM_TEXT_DOMAIN ); ?></p>
            </div>
		</div>
	</div>

	<?php
	// Tamam (HTML templates) ko (footer) mein (include) karein
	rsam_include_all_templates();
}

/**
 * (JavaScript) ke istemal ke liye tamam (HTML <template>) blocks ko (footer) mein (print) karta hai.
 */
function rsam_include_all_templates() {
    // Dashboard
	rsam_template_dashboard();
    
    // Products
	rsam_template_products();

    // Purchases
    rsam_template_purchases();

    // Sales
    rsam_template_sales();

    // Expenses
    rsam_template_expenses();

    // Employees
    rsam_template_employees();

    // Suppliers
    rsam_template_suppliers();

    // Customers
    rsam_template_customers();

    // Reports
    rsam_template_reports();

    // Settings
    rsam_template_settings();

    // Modals/Drawers (Common UI)
    rsam_template_common_ui();
}

// (Templates) aur (AJAX handlers) ko include karna (Abhi hum in functions ko define karenge)
// Yeh files nahi hain, yeh sirf code ko (organize) karne ke liye (logical) functions hain.

/** Part 2 — Yahan khatam hua */
/**
 * Part 3 — Templates (Dashboard, Common UI) aur (AJAX) Handlers
 */

/**
 * Dashboard Screen ke liye (HTML <template>)
 */
function rsam_template_dashboard() {
	?>
	<template id="rsam-tmpl-dashboard">
		<div class="rsam-screen-header">
			<h1><?php _e( 'Dashboard', RSAM_TEXT_DOMAIN ); ?></h1>
		</div>
		<div class="rsam-dashboard-widgets">
			
			<div class="rsam-widget rsam-widget-loading" data-widget="stats">
				<div class="rsam-widget-header">
					<h3><?php _e( 'Shop Overview', RSAM_TEXT_DOMAIN ); ?></h3>
				</div>
				<div class="rsam-widget-body">
					<p><?php _e( 'Loading stats...', RSAM_TEXT_DOMAIN ); ?></p>
				</div>
			</div>

            <div class="rsam-widget" data-widget="quick-links">
				<div class="rsam-widget-header">
					<h3><?php _e( 'Quick Actions', RSAM_TEXT_DOMAIN ); ?></h3>
				</div>
				<div class="rsam-widget-body rsam-quick-links">
                    <?php if ( current_user_can( 'rsam_manage_sales' ) ) : ?>
					    <a href="<?php echo esc_url( admin_url( 'admin.php?page=rsam-sales' ) ); ?>" class="button button-primary button-hero">
                            <span class="dashicons dashicons-cart"></span>
                            <?php _e( 'New Sale', RSAM_TEXT_DOMAIN ); ?>
                        </a>
                    <?php endif; ?>
                    <?php if ( current_user_can( 'rsam_manage_purchases' ) ) : ?>
					    <a href="<?php echo esc_url( admin_url( 'admin.php?page=rsam-purchases' ) ); ?>" class="button button-secondary">
                             <span class="dashicons dashicons-archive"></span>
                            <?php _e( 'New Purchase', RSAM_TEXT_DOMAIN ); ?>
                        </a>
                    <?php endif; ?>
                    <?php if ( current_user_can( 'rsam_manage_products' ) ) : ?>
					    <a href="<?php echo esc_url( admin_url( 'admin.php?page=rsam-products' ) ); ?>" class="button button-secondary">
                             <span class="dashicons dashicons-package"></span>
                            <?php _e( 'Add Product', RSAM_TEXT_DOMAIN ); ?>
                        </a>
                    <?php endif; ?>
                    <?php if ( current_user_can( 'rsam_manage_expenses' ) ) : ?>
					    <a href="<?php echo esc_url( admin_url( 'admin.php?page=rsam-expenses' ) ); ?>" class="button button-secondary">
                             <span class="dashicons dashicons-money-alt"></span>
                            <?php _e( 'Add Expense', RSAM_TEXT_DOMAIN ); ?>
                        </a>
                    <?php endif; ?>
				</div>
			</div>

			<?php if ( current_user_can( 'rsam_manage_products' ) ) : ?>
			<div class="rsam-widget rsam-widget-loading" data-widget="top-products">
				<div class="rsam-widget-header">
					<h3><?php _e( 'Top Selling Products (This Month)', RSAM_TEXT_DOMAIN ); ?></h3>
				</div>
				<div class="rsam-widget-body">
					<p><?php _e( 'Loading...', RSAM_TEXT_DOMAIN ); ?></p>
				</div>
			</div>

			<div class="rsam-widget rsam-widget-loading" data-widget="low-stock">
				<div class="rsam-widget-header">
					<h3><?php _e( 'Low Stock Alerts', RSAM_TEXT_DOMAIN ); ?></h3>
				</div>
				<div class="rsam-widget-body">
					<p><?php _e( 'Loading...', RSAM_TEXT_DOMAIN ); ?></p>
				</div>
			</div>
            <?php endif; ?>

		</div>
	</template>
	<?php
}

/**
 * (Common UI) ke liye (Templates) - Maslan (Modal/Drawer) forms.
 */
function rsam_template_common_ui() {
	?>
    <template id="rsam-tmpl-modal-form">
		<div class="rsam-modal-backdrop"></div>
		<div class="rsam-modal-wrapper">
			<div class="rsam-modal-content">
				<div class="rsam-modal-header">
					<h3 class="rsam-modal-title"></h3>
					<button type="button" class="rsam-modal-close dashicons dashicons-no-alt"></button>
				</div>
				<div class="rsam-modal-body">
					</div>
				<div class="rsam-modal-footer">
					<button type="button" class="button rsam-modal-cancel"><?php _e( 'Cancel', RSAM_TEXT_DOMAIN ); ?></button>
					<button type="button" class="button button-primary rsam-modal-save">
						<span class="rsam-btn-text"><?php _e( 'Save', RSAM_TEXT_DOMAIN ); ?></span>
						<span class="rsam-loader-spinner"></span>
					</button>
				</div>
			</div>
		</div>
	</template>

    <template id="rsam-tmpl-modal-confirm">
		<div class="rsam-modal-backdrop"></div>
		<div class="rsam-modal-wrapper rsam-modal-confirm">
			<div class="rsam-modal-content">
				<div class="rsam-modal-header">
					<h3 class="rsam-modal-title"><?php _e( 'Are you sure?', RSAM_TEXT_DOMAIN ); ?></h3>
					<button type="button" class="rsam-modal-close dashicons dashicons-no-alt"></button>
				</div>
				<div class="rsam-modal-body">
                    <p class="rsam-confirm-text"><?php _e( 'Are you sure you want to delete this item? This action cannot be undone.', RSAM_TEXT_DOMAIN ); ?></p>
				</div>
				<div class="rsam-modal-footer">
					<button type="button" class="button rsam-modal-cancel"><?php _e( 'Cancel', RSAM_TEXT_DOMAIN ); ?></button>
					<button type="button" class="button button-danger rsam-modal-confirm-delete">
                        <span class="rsam-btn-text"><?php _e( 'Delete', RSAM_TEXT_DOMAIN ); ?></span>
						<span class="rsam-loader-spinner"></span>
                    </button>
				</div>
			</div>
		</div>
	</template>
	<?php
}


/**
 * (AJAX) Handler: Dashboard (Stats) hasil karne ke liye.
 */
function rsam_ajax_get_dashboard_stats() {
	// (Security) checks
	check_ajax_referer( 'rsam-ajax-nonce', 'nonce' );
	if ( ! current_user_can( 'rsam_view_dashboard' ) ) {
		wp_send_json_error( [ 'message' => __( 'You do not have permission to view this data.', RSAM_TEXT_DOMAIN ) ], 403 );
	}

	global $wpdb;
	$tables = rsam_get_table_names();

    // (Timezone) ke masail se bachne ke liye (WordPress) ka (timestamp) istemal karein
    $today_start = wp_date( 'Y-m-d 00:00:00' );
    $month_start = wp_date( 'Y-m-01 00:00:00' );

	// 1. Aaj ki (Sale)
	$today_sales = $wpdb->get_var( $wpdb->prepare(
		"SELECT SUM(total_amount) FROM {$tables['sales']} WHERE sale_date >= %s",
		$today_start
	) );

	// 2. Is maheenay ki (Sale)
	$monthly_sales = $wpdb->get_var( $wpdb->prepare(
		"SELECT SUM(total_amount) FROM {$tables['sales']} WHERE sale_date >= %s",
		$month_start
	) );

	// 3. Is maheenay ka (Profit)
	$monthly_profit = $wpdb->get_var( $wpdb->prepare(
		"SELECT SUM(total_profit) FROM {$tables['sales']} WHERE sale_date >= %s",
		$month_start
	) );
    
	// 4. Is maheenay ke (Expenses)
    $monthly_expenses = $wpdb->get_var( $wpdb->prepare(
        "SELECT SUM(amount) FROM {$tables['expenses']} WHERE expense_date >= %s",
        wp_date( 'Y-m-01' )
    ) );

    // 5. Total (Stock Value) (Cost Price par)
    // Yeh (query) thori (complex) hai: humein har (batch) ki (cost_price * quantity_in_stock) chahiye
    $stock_value = $wpdb->get_var(
        "SELECT SUM(cost_price * quantity_in_stock) 
         FROM {$tables['product_batches']} 
         WHERE quantity_in_stock > 0"
    );

    // 6. (Low Stock) Items
    $low_stock_count = $wpdb->get_var(
        "SELECT COUNT(id) 
         FROM {$tables['products']} 
         WHERE stock_quantity <= low_stock_threshold AND low_stock_threshold > 0"
    );

    // 7. (Top Selling Products) (Is maheenay)
    $top_products = $wpdb->get_results( $wpdb->prepare(
        "SELECT p.name, SUM(si.quantity) as total_quantity
         FROM {$tables['sale_items']} si
         JOIN {$tables['sales']} s ON s.id = si.sale_id
         JOIN {$tables['products']} p ON p.id = si.product_id
         WHERE s.sale_date >= %s
         GROUP BY si.product_id
         ORDER BY total_quantity DESC
         LIMIT 5",
         $month_start
    ) );

    // 8. (Low Stock Products) (List)
    $low_stock_products = $wpdb->get_results(
        "SELECT name, stock_quantity, low_stock_threshold 
         FROM {$tables['products']} 
         WHERE stock_quantity <= low_stock_threshold AND low_stock_threshold > 0
         ORDER BY (stock_quantity - low_stock_threshold) ASC
         LIMIT 5"
    );


	$stats = [
		'today_sales'      => rsam_format_price( $today_sales ),
		'monthly_sales'    => rsam_format_price( $monthly_sales ),
		'monthly_profit'   => rsam_format_price( $monthly_profit ),
        'monthly_expenses' => rsam_format_price( $monthly_expenses ),
        'stock_value'      => rsam_format_price( $stock_value ),
		'low_stock_count'  => (int) $low_stock_count,
        'top_products'     => $top_products,
        'low_stock_products' => $low_stock_products,
	];

	wp_send_json_success( $stats );
}
// (Admin) aur (Staff) dono (dashboard) dekh sakte hain
add_action( 'wp_ajax_rsam_get_dashboard_stats', 'rsam_ajax_get_dashboard_stats' );


/**
 * Raqam (Price) ko format karne ke liye ek helper function.
 * (Hum isey (Settings) page se (configurable) bana sakte hain)
 *
 * @param float|string $price Raqam.
 * @return string Formatted raqam.
 */
function rsam_format_price( $price ) {
    // Abhi ke liye (hardcoded) (PKR), baad mein (Settings) se le sakte hain
    $currency_symbol = __( 'Rs.', RSAM_TEXT_DOMAIN );
    $price = (float) $price;
    return $currency_symbol . ' ' . number_format( $price, 2 );
}

/** Part 3 — Yahan khatam hua */
/**
 * Part 4 — Products (Inventory) (Templates + AJAX)
 */

/**
 * Products (Inventory) Screen ke liye (HTML <template>)
 */
function rsam_template_products() {
	?>
	<template id="rsam-tmpl-products">
		<div class="rsam-screen-header">
			<h1><?php _e( 'Products (Inventory)', RSAM_TEXT_DOMAIN ); ?></h1>
			<button type="button" class="button button-primary" id="rsam-add-new-product">
				<span class="dashicons dashicons-plus-alt"></span>
				<?php _e( 'Add New Product', RSAM_TEXT_DOMAIN ); ?>
			</button>
		</div>

		<div class="rsam-list-controls">
			<input type="search" id="rsam-product-search" class="rsam-search-field" placeholder="<?php esc_attr_e( 'Search by product name...', RSAM_TEXT_DOMAIN ); ?>">
			</div>

		<div class="rsam-list-table-wrapper">
			<table class="rsam-list-table" id="rsam-products-table">
				<thead>
					<tr>
						<th><?php _e( 'Product Name', RSAM_TEXT_DOMAIN ); ?></th>
						<th><?php _e( 'Category', RSAM_TEXT_DOMAIN ); ?></th>
						<th><?php _e( 'Unit', RSAM_TEXT_DOMAIN ); ?></th>
						<th><?php _e( 'Stock Qty', RSAM_TEXT_DOMAIN ); ?></th>
						<th><?php _e( 'Stock Value (Cost)', RSAM_TEXT_DOMAIN ); ?></th>
						<th><?php _e( 'Selling Price', RSAM_TEXT_DOMAIN ); ?></th>
						<th><?php _e( 'Actions', RSAM_TEXT_DOMAIN ); ?></th>
					</tr>
				</thead>
				<tbody id="rsam-products-table-body">
					<tr>
						<td colspan="7" class="rsam-list-loading">
							<span class="rsam-loader-spinner"></span> <?php _e( 'Loading products...', RSAM_TEXT_DOMAIN ); ?>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<div class="rsam-pagination" id="rsam-products-pagination">
			</div>

		<div id="rsam-product-form-container" style="display: none;">
			<form id="rsam-product-form" class="rsam-form">
				<input type="hidden" name="product_id" value="0">
				
				<div class="rsam-form-field">
					<label for="rsam-product-name"><?php _e( 'Product Name', RSAM_TEXT_DOMAIN ); ?> <span class="rsam-required">*</span></label>
					<input type="text" id="rsam-product-name" name="name" required>
				</div>

				<div class="rsam-form-grid">
					<div class="rsam-form-field">
						<label for="rsam-product-category"><?php _e( 'Category', RSAM_TEXT_DOMAIN ); ?></label>
						<input type="text" id="rsam-product-category" name="category" placeholder="<?php esc_attr_e( 'e.g., Pulses, Soaps', RSAM_TEXT_DOMAIN ); ?>">
					</div>
					<div class="rsam-form-field">
						<label for="rsam-product-unit"><?php _e( 'Unit Type', RSAM_TEXT_DOMAIN ); ?> <span class="rsam-required">*</span></label>
						<input type="text" id="rsam-product-unit" name="unit_type" required placeholder="<?php esc_attr_e( 'e.g., kg, piece, liter', RSAM_TEXT_DOMAIN ); ?>">
					</div>
				</div>

				<div class="rsam-form-grid">
					<div class="rsam-form-field">
						<label for="rsam-product-selling-price"><?php _e( 'Selling Price', RSAM_TEXT_DOMAIN ); ?> <span class="rsam-required">*</span></label>
						<input type="number" id="rsam-product-selling-price" name="selling_price" step="0.01" min="0" required>
					</div>
					<div class="rsam-form-field">
						<label for="rsam-product-low-stock"><?php _e( 'Low Stock Threshold', RSAM_TEXT_DOMAIN ); ?></label>
						<input type="number" id="rsam-product-low-stock" name="low_stock_threshold" step="1" min="0" placeholder="<?php esc_attr_e( 'e.g., 5', RSAM_TEXT_DOMAIN ); ?>">
					</div>
				</div>

                <div class="rsam-form-field">
                    <label>
                        <input type="checkbox" name="has_expiry" id="rsam-product-has-expiry" value="1">
                        <?php _e( 'This product has an expiry date (e.g., bread, milk)', RSAM_TEXT_DOMAIN ); ?>
                    </label>
                </div>

                <p class="rsam-form-note"><?php _e( 'Note: Stock Quantity and Purchase Price are managed from the "Purchases" screen.', RSAM_TEXT_DOMAIN ); ?></p>
			</form>
		</div>
	</template>
	<?php
}

/**
 * (AJAX) Handler: Products ki list (fetch) karne ke liye.
 */
function rsam_ajax_get_products() {
	// (Security) checks
	check_ajax_referer( 'rsam-ajax-nonce', 'nonce' );
	if ( ! current_user_can( 'rsam_manage_products' ) ) {
		wp_send_json_error( [ 'message' => __( 'You do not have permission.', RSAM_TEXT_DOMAIN ) ], 403 );
	}

	global $wpdb;
	$tables = rsam_get_table_names();

	// (Pagination) parameters
	$page = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
	$limit = 20; // (Per page items)
	$offset = ( $page - 1 ) * $limit;

	// (Search) parameter
	$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';

	// (Query) conditions
	$where_clause = '';
	$params = [];
	if ( ! empty( $search ) ) {
		$where_clause = ' WHERE p.name LIKE %s';
		$params[] = '%' . $wpdb->esc_like( $search ) . '%';
	}

	// (Total items) (count) karein (pagination) ke liye
	$total_items = $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(p.id) FROM {$tables['products']} p $where_clause",
		$params
	) );

	// (Products) (fetch) karein, (stock value) ke sath
	// (Stock Value) = SUM(batch.cost_price * batch.quantity_in_stock)
	$query = $wpdb->prepare(
		"SELECT 
			p.id, 
			p.name, 
			p.category, 
			p.unit_type, 
			p.selling_price, 
			p.stock_quantity,
			p.low_stock_threshold,
            p.has_expiry,
			COALESCE(SUM(b.cost_price * b.quantity_in_stock), 0) as stock_value
		FROM 
			{$tables['products']} p
		LEFT JOIN 
			{$tables['product_batches']} b ON p.id = b.product_id AND b.quantity_in_stock > 0
		$where_clause
		GROUP BY 
			p.id
		ORDER BY 
			p.name ASC
		LIMIT %d
		OFFSET %d",
		array_merge( $params, [ $limit, $offset ] )
	);

	$products = $wpdb->get_results( $query );
    
    // (Prices) ko format karein
    foreach ( $products as $product ) {
        $product->stock_quantity = (float) $product->stock_quantity;
        $product->selling_price_formatted = rsam_format_price( $product->selling_price );
        $product->stock_value_formatted = rsam_format_price( $product->stock_value );
    }

	wp_send_json_success( [
		'products'   => $products,
		'pagination' => [
			'total_items' => (int) $total_items,
			'total_pages' => ceil( $total_items / $limit ),
			'current_page' => $page,
		],
	] );
}
add_action( 'wp_ajax_rsam_get_products', 'rsam_ajax_get_products' );


/**
 * (AJAX) Handler: Naya (Product) (save) ya (update) karne ke liye.
 */
function rsam_ajax_save_product() {
	// (Security) checks
	check_ajax_referer( 'rsam-ajax-nonce', 'nonce' );
	if ( ! current_user_can( 'rsam_manage_products' ) ) {
		wp_send_json_error( [ 'message' => __( 'You do not have permission.', RSAM_TEXT_DOMAIN ) ], 403 );
	}

	// (POST data) (parse) karein
	parse_str( $_POST['form_data'], $data );

	// (Validate) aur (sanitize) karein
	$product_id = isset( $data['product_id'] ) ? absint( $data['product_id'] ) : 0;
	$name = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
	$category = isset( $data['category'] ) ? sanitize_text_field( $data['category'] ) : '';
	$unit_type = isset( $data['unit_type'] ) ? sanitize_key( $data['unit_type'] ) : ''; // (e.g., kg, piece)
	$selling_price = isset( $data['selling_price'] ) ? floatval( $data['selling_price'] ) : 0;
	$low_stock_threshold = isset( $data['low_stock_threshold'] ) ? floatval( $data['low_stock_threshold'] ) : 0;
    $has_expiry = isset( $data['has_expiry'] ) ? 1 : 0;

	// (Validation)
	if ( empty( $name ) || empty( $unit_type ) || $selling_price < 0 ) {
		wp_send_json_error( [ 'message' => __( 'Please fill all required fields correctly.', RSAM_TEXT_DOMAIN ) ], 400 );
	}

	global $wpdb;
	$tables = rsam_get_table_names();

	$db_data = [
		'name'                => $name,
		'category'            => $category,
		'unit_type'           => $unit_type,
		'selling_price'       => $selling_price,
		'low_stock_threshold' => $low_stock_threshold,
        'has_expiry'          => $has_expiry,
	];
    
    $db_formats = ['%s', '%s', '%s', '%f', '%f', '%d'];

	// (Update) ya (Insert)
	if ( $product_id > 0 ) {
		// Update
		$result = $wpdb->update(
			$tables['products'],
			$db_data,
			[ 'id' => $product_id ], // (WHERE)
			$db_formats,
			[ '%d' ] // (WHERE format)
		);
        $message = __( 'Product updated successfully.', RSAM_TEXT_DOMAIN );
	} else {
		// Insert
        // Naye (product) ki (stock_quantity) (default) 0 hogi
        $db_data['stock_quantity'] = 0;
        $db_formats[] = '%f';

		$result = $wpdb->insert(
			$tables['products'],
			$db_data,
			$db_formats
		);
        $product_id = $wpdb->insert_id;
        $message = __( 'Product added successfully.', RSAM_TEXT_DOMAIN );
	}

	if ( $result === false ) {
		wp_send_json_error( [ 'message' => __( 'Database error. Could not save product.', RSAM_TEXT_DOMAIN ) ], 500 );
	}

	wp_send_json_success( [ 'message' => $message, 'product_id' => $product_id ] );
}
add_action( 'wp_ajax_rsam_save_product', 'rsam_ajax_save_product' );


/**
 * (AJAX) Handler: (Product) (delete) karne ke liye.
 */
function rsam_ajax_delete_product() {
	// (Security) checks
	check_ajax_referer( 'rsam-ajax-nonce', 'nonce' );
	if ( ! current_user_can( 'rsam_manage_products' ) ) {
		wp_send_json_error( [ 'message' => __( 'You do not have permission.', RSAM_TEXT_DOMAIN ) ], 403 );
	}

	$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;

	if ( $product_id <= 0 ) {
		wp_send_json_error( [ 'message' => __( 'Invalid Product ID.', RSAM_TEXT_DOMAIN ) ], 400 );
	}

	global $wpdb;
	$tables = rsam_get_table_names();
    
    // Check karein ke (product) (sale) ya (purchase) mein istemal to nahi hua
    // (Hum (batches) check kar sakte hain, agar (batches) hain to (delete) nahi karna chahiye)
    // (Behtar (solution) (soft delete) hai, lekin abhi ke liye hum (stock) check karte hain)
    
    $stock = $wpdb->get_var( $wpdb->prepare(
        "SELECT stock_quantity FROM {$tables['products']} WHERE id = %d", $product_id
    ) );
    
    if ( (float) $stock > 0 ) {
        wp_send_json_error( [ 'message' => __( 'Cannot delete. This product still has stock. Please adjust stock to 0 first.', RSAM_TEXT_DOMAIN ) ], 400 );
    }

    // (Batches) (delete) karein
    $wpdb->delete( $tables['product_batches'], [ 'product_id' => $product_id ], [ '%d' ] );

	// (Product) (delete) karein
	$result = $wpdb->delete( $tables['products'], [ 'id' => $product_id ], [ '%d' ] );

	if ( $result ) {
		wp_send_json_success( [ 'message' => __( 'Product deleted successfully.', RSAM_TEXT_DOMAIN ) ] );
	} else {
		wp_send_json_error( [ 'message' => __( 'Could not delete product.', RSAM_TEXT_DOMAIN ) ], 500 );
	}
}
add_action( 'wp_ajax_rsam_delete_product', 'rsam_ajax_delete_product' );


/**
 * (AJAX) Handler: (Sales/Purchases) forms ke liye (products) (search) karna.
 * Yeh (lightweight) (handler) hai jo sirf (name) aur (id) (return) karta hai.
 */
function rsam_ajax_search_products() {
    // (Security) checks (Yeh (Sales) aur (Purchases) dono ke liye hai)
	check_ajax_referer( 'rsam-ajax-nonce', 'nonce' );
	if ( ! current_user_can( 'rsam_manage_sales' ) && ! current_user_can( 'rsam_manage_purchases' ) ) {
		wp_send_json_error( [ 'message' => __( 'You do not have permission.', RSAM_TEXT_DOMAIN ) ], 403 );
	}

    global $wpdb;
	$tables = rsam_get_table_names();
    
    $search = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';

    if ( empty( $search ) ) {
        wp_send_json_success( [] );
    }

    $query = $wpdb->prepare(
        "SELECT id, name, unit_type, selling_price, has_expiry 
         FROM {$tables['products']} 
         WHERE name LIKE %s 
         LIMIT 15",
        '%' . $wpdb->esc_like( $search ) . '%'
    );
    
    $products = $wpdb->get_results( $query );

    // (JavaScript) (select/autocomplete) (library) ke liye (format) karein
    $results = [];
    foreach ( $products as $product ) {
        $results[] = [
            'id'    => $product->id,
            'label' => sprintf(
                '%s (%s) - %s',
                $product->name,
                $product->unit_type,
                rsam_format_price($product->selling_price)
            ),
            'value' => $product->name,
            'data'  => $product, // Pura (product object) (JS) ko bhej dein
        ];
    }
    
    wp_send_json_success( $results );
}
add_action( 'wp_ajax_rsam_search_products', 'rsam_ajax_search_products' );

/** Part 4 — Yahan khatam hua */
/**
 * Part 5 — Purchases (Templates)
 */

/**
 * Purchases Screen ke liye (HTML <template>)
 * Yeh (template) do (modes) ko handle karega: 'list' (fehrist) aur 'form' (naya/edit).
 */
function rsam_template_purchases() {
	?>
	<template id="rsam-tmpl-purchases">
		<div id="rsam-purchase-list-view">
			<div class="rsam-screen-header">
				<h1><?php _e( 'Purchases', RSAM_TEXT_DOMAIN ); ?></h1>
				<button type="button" class="button button-primary" id="rsam-add-new-purchase">
					<span class="dashicons dashicons-plus-alt"></span>
					<?php _e( 'Record New Purchase', RSAM_TEXT_DOMAIN ); ?>
				</button>
			</div>

			<div class="rsam-list-controls">
				<input type="search" id="rsam-purchase-search" class="rsam-search-field" placeholder="<?php esc_attr_e( 'Search by Invoice # or Supplier...', RSAM_TEXT_DOMAIN ); ?>">
			</div>

			<div class="rsam-list-table-wrapper">
				<table class="rsam-list-table" id="rsam-purchases-table">
					<thead>
						<tr>
							<th><?php _e( 'Invoice #', RSAM_TEXT_DOMAIN ); ?></th>
							<th><?php _e( 'Supplier', RSAM_TEXT_DOMAIN ); ?></th>
							<th><?php _e( 'Purchase Date', RSAM_TEXT_DOMAIN ); ?></th>
							<th><?php _e( 'Total Amount', RSAM_TEXT_DOMAIN ); ?></th>
							<th><?php _e( 'Actions', RSAM_TEXT_DOMAIN ); ?></th>
						</tr>
					</thead>
					<tbody id="rsam-purchases-table-body">
						<tr>
							<td colspan="5" class="rsam-list-loading">
								<span class="rsam-loader-spinner"></span> <?php _e( 'Loading purchases...', RSAM_TEXT_DOMAIN ); ?>
							</td>
						</tr>
					</tbody>
				</table>
			</div>

			<div class="rsam-pagination" id="rsam-purchases-pagination">
				</div>
		</div>

		<div id="rsam-purchase-form-view" style="display: none;">
			<div class="rsam-screen-header">
				<h1 id="rsam-purchase-form-title"><?php _e( 'Record New Purchase', RSAM_TEXT_DOMAIN ); ?></h1>
				<button type="button" class="button" id="rsam-back-to-purchase-list">
					<span class="dashicons dashicons-arrow-left-alt"></span>
					<?php _e( 'Back to List', RSAM_TEXT_DOMAIN ); ?>
				</button>
			</div>

			<form id="rsam-purchase-form" class="rsam-form">
				<input type="hidden" name="purchase_id" value="0">
				
				<div class="rsam-card">
					<h3><?php _e( 'Invoice Details', RSAM_TEXT_DOMAIN ); ?></h3>
					<div class="rsam-form-grid-3col">
						<div class="rsam-form-field">
							<label for="rsam-purchase-supplier"><?php _e( 'Supplier', RSAM_TEXT_DOMAIN ); ?></label>
							<select id="rsam-purchase-supplier" name="supplier_id" class="rsam-supplier-search-select">
								<option value="0"><?php _e( 'Select or Add Supplier', RSAM_TEXT_DOMAIN ); ?></option>
                                </select>
                            <button type="button" class="button button-small rsam-quick-add" data-type="supplier" title="<?php esc_attr_e( 'Add New Supplier', RSAM_TEXT_DOMAIN ); ?>"><span class="dashicons dashicons-plus-alt"></span></button>
						</div>
						<div class="rsam-form-field">
							<label for="rsam-purchase-invoice"><?php _e( 'Invoice Number', RSAM_TEXT_DOMAIN ); ?></label>
							<input type="text" id="rsam-purchase-invoice" name="invoice_number">
						</div>
						<div class="rsam-form-field">
							<label for="rsam-purchase-date"><?php _e( 'Purchase Date', RSAM_TEXT_DOMAIN ); ?> <span class="rsam-required">*</span></label>
							<input type="date" id="rsam-purchase-date" name="purchase_date" required>
						</div>
					</div>
				</div>

				<div class="rsam-card">
					<h3><?php _e( 'Product Items', RSAM_TEXT_DOMAIN ); ?></h3>
					
					<div class="rsam-form-field">
						<label for="rsam-purchase-product-search"><?php _e( 'Search Product to Add', RSAM_TEXT_DOMAIN ); ?></label>
						<input type="text" id="rsam-purchase-product-search" placeholder="<?php esc_attr_e( 'Type product name...', RSAM_TEXT_DOMAIN ); ?>">
					</div>
					
					<div class="rsam-list-table-wrapper">
						<table class="rsam-list-table" id="rsam-purchase-items-table">
							<thead>
								<tr>
									<th><?php _e( 'Product', RSAM_TEXT_DOMAIN ); ?></th>
									<th><?php _e( 'Qty', RSAM_TEXT_DOMAIN ); ?> <span class="rsam-required">*</span></th>
									<th><?php _e( 'Purchase Price (per unit)', RSAM_TEXT_DOMAIN ); ?> <span class="rsam-required">*</span></th>
									<th><?php _e( 'Expiry Date', RSAM_TEXT_DOMAIN ); ?></th>
									<th><?php _e( 'Subtotal', RSAM_TEXT_DOMAIN ); ?></th>
									<th><?php _e( 'Action', RSAM_TEXT_DOMAIN ); ?></th>
								</tr>
							</thead>
							<tbody id="rsam-purchase-items-body">
								<tr class="rsam-no-items-row">
                                    <td colspan="6"><?php _e( 'No products added yet.', RSAM_TEXT_DOMAIN ); ?></td>
                                </tr>
							</tbody>
						</table>
					</div>
				</div>
                
                <div class="rsam-card">
                    <h3><?php _e( 'Costs & Summary', RSAM_TEXT_DOMAIN ); ?></h3>
                    <div class="rsam-form-grid-3col">
                        <div class="rsam-form-field">
                            <label for="rsam-purchase-subtotal"><?php _e( 'Items Subtotal', RSAM_TEXT_DOMAIN ); ?></label>
                            <input type="text" id="rsam-purchase-subtotal" name="subtotal" readonly>
                        </div>
                        <div class="rsam-form-field">
                            <label for="rsam-purchase-additional-costs"><?php _e( 'Additional Costs', RSAM_TEXT_DOMAIN ); ?></label>
                            <input type="number" id="rsam-purchase-additional-costs" name="additional_costs" step="0.01" min="0" value="0" placeholder="<?php esc_attr_e( 'e.g., Transport, Tax', RSAM_TEXT_DOMAIN ); ?>">
                        </div>
                        <div class="rsam-form-field">
                            <label for="rsam-purchase-total-amount"><?php _e( 'Total Amount', RSAM_TEXT_DOMAIN ); ?></label>
                            <input type="text" id="rsam-purchase-total-amount" name="total_amount" readonly class="rsam-total-field">
                        </div>
                    </div>
                    <p class="rsam-form-note"><?php _e( 'Note: Additional Costs (transport, tax) will be automatically distributed proportionally into each product\'s cost price to calculate the accurate Cost of Goods.', RSAM_TEXT_DOMAIN ); ?></p>
                </div>

                <div class="rsam-card">
                    <div class="rsam-form-field">
                        <label for="rsam-purchase-notes"><?php _e( 'Notes', RSAM_TEXT_DOMAIN ); ?></label>
                        <textarea id="rsam-purchase-notes" name="notes" rows="3"></textarea>
                    </div>
                    
                    <div class="rsam-form-actions">
                        <button type="button" class="button" id="rsam-cancel-purchase-form"><?php _e( 'Cancel', RSAM_TEXT_DOMAIN ); ?></button>
                        <button type="submit" class="button button-primary" id="rsam-save-purchase-form">
                            <span class="rsam-btn-text"><?php _e( 'Save Purchase', RSAM_TEXT_DOMAIN ); ?></span>
						    <span class="rsam-loader-spinner"></span>
                        </button>
                    </div>
                </div>

			</form>
		</div>

	</template>
	<?php
}

/** Part 5 — Yahan khatam hua */
/**
 * Part 6 — Purchases (AJAX Handlers)
 */

/**
 * (AJAX) Handler: (Purchases) ki list (fetch) karne ke liye.
 */
function rsam_ajax_get_purchases() {
	// (Security) checks
	check_ajax_referer( 'rsam-ajax-nonce', 'nonce' );
	if ( ! current_user_can( 'rsam_manage_purchases' ) ) {
		wp_send_json_error( [ 'message' => __( 'You do not have permission.', RSAM_TEXT_DOMAIN ) ], 403 );
	}

	global $wpdb;
	$tables = rsam_get_table_names();

	// (Pagination) parameters
	$page = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
	$limit = 20;
	$offset = ( $page - 1 ) * $limit;

	// (Search) parameter
	$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';

	// (Query) conditions
	$where_clause = '';
	$params = [];
	if ( ! empty( $search ) ) {
		$search_like = '%' . $wpdb->esc_like( $search ) . '%';
		$where_clause = ' WHERE (p.invoice_number LIKE %s OR s.name LIKE %s)';
		$params[] = $search_like;
		$params[] = $search_like;
	}

	// (Total items) (count) karein
	$total_query = "SELECT COUNT(p.id) 
                    FROM {$tables['purchases']} p 
                    LEFT JOIN {$tables['suppliers']} s ON p.supplier_id = s.id
                    $where_clause";
	$total_items = $wpdb->get_var( $wpdb->prepare( $total_query, $params ) );

	// (Purchases) (fetch) karein
	$query = $wpdb->prepare(
		"SELECT 
			p.id, 
			p.invoice_number, 
			p.purchase_date, 
			p.total_amount,
			COALESCE(s.name, %s) as supplier_name
		FROM 
			{$tables['purchases']} p
		LEFT JOIN 
			{$tables['suppliers']} s ON p.supplier_id = s.id
		$where_clause
		ORDER BY 
			p.purchase_date DESC, p.id DESC
		LIMIT %d
		OFFSET %d",
		array_merge( [ __( 'N/A', RSAM_TEXT_DOMAIN ) ], $params, [ $limit, $offset ] )
	);

	$purchases = $wpdb->get_results( $query );
    
    // (Data) ko (format) karein
    foreach ( $purchases as $purchase ) {
        $purchase->total_amount_formatted = rsam_format_price( $purchase->total_amount );
        $purchase->purchase_date_formatted = wp_date( get_option( 'date_format' ), strtotime( $purchase->purchase_date ) );
    }

	wp_send_json_success( [
		'purchases'  => $purchases,
		'pagination' => [
			'total_items' => (int) $total_items,
			'total_pages' => ceil( $total_items / $limit ),
			'current_page' => $page,
		],
	] );
}
add_action( 'wp_ajax_rsam_get_purchases', 'rsam_ajax_get_purchases' );


/**
 * (AJAX) Handler: Ek (Purchase) ki mukammal (details) (fetch) karna (Edit ke liye).
 */
function rsam_ajax_get_purchase_details() {
    check_ajax_referer( 'rsam-ajax-nonce', 'nonce' );
	if ( ! current_user_can( 'rsam_manage_purchases' ) ) {
		wp_send_json_error( [ 'message' => __( 'You do not have permission.', RSAM_TEXT_DOMAIN ) ], 403 );
	}
    
    $purchase_id = isset( $_POST['purchase_id'] ) ? absint( $_POST['purchase_id'] ) : 0;
    if ( $purchase_id <= 0 ) {
		wp_send_json_error( [ 'message' => __( 'Invalid Purchase ID.', RSAM_TEXT_DOMAIN ) ], 400 );
	}

    global $wpdb;
	$tables = rsam_get_table_names();

    // (Purchase) ki (main details)
    $purchase = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$tables['purchases']} WHERE id = %d", $purchase_id
    ) );
    
    if ( ! $purchase ) {
        wp_send_json_error( [ 'message' => __( 'Purchase not found.', RSAM_TEXT_DOMAIN ) ], 404 );
    }
    
    // (Purchase Items) (details)
    // Hum (batch) se (data) le rahe hain kyunke (purchase_items) mein (cost_price) nahi, (base purchase_price) hai.
    $items = $wpdb->get_results( $wpdb->prepare(
        "SELECT 
            pi.product_id,
            p.name as product_name,
            p.unit_type,
            p.has_expiry,
            b.quantity_received as quantity,
            b.purchase_price,
            b.expiry_date
        FROM {$tables['purchase_items']} pi
        JOIN {$tables['product_batches']} b ON pi.batch_id = b.id
        JOIN {$tables['products']} p ON pi.product_id = p.id
        WHERE pi.purchase_id = %d",
        $purchase_id
    ) );
    
    // (Supplier) (details) (agar hai)
    $supplier = null;
    if ( $purchase->supplier_id > 0 ) {
        $supplier = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, name FROM {$tables['suppliers']} WHERE id = %d", $purchase->supplier_id
        ) );
    }

    wp_send_json_success( [
        'purchase' => $purchase,
        'items'    => $items,
        'supplier' => $supplier,
    ] );
}
add_action( 'wp_ajax_rsam_get_purchase_details', 'rsam_ajax_get_purchase_details' );


/**
 * (AJAX) Handler: Nayi (Purchase) (save) ya (update) karna.
 * Yeh ek (complex) (handler) hai jo (stock) aur (costing) ko (handle) karega.
 *
 * NOTE: Is (plugin) ke (design) ke mutabiq, (Purchase) ko (EDIT) karna bohat (complex) hai
 * kyunke is se (stock) aur (batch costing) (disturb) ho sakti hai jo pehle (sell) ho chuki hai.
 * Abhi ke liye, hum sirf NAYI (Purchase) (ADD) karne par (focus) karenge.
 * (Editing) ke liye (stock) ko (reverse) karne ka (logic) darkar hoga.
 */
function rsam_ajax_save_purchase() {
    check_ajax_referer( 'rsam-ajax-nonce', 'nonce' );
	if ( ! current_user_can( 'rsam_manage_purchases' ) ) {
		wp_send_json_error( [ 'message' => __( 'You do not have permission.', RSAM_TEXT_DOMAIN ) ], 403 );
	}

    // (POST data) (parse) karein
	$data = wp_unslash( $_POST );
    
    // (Main) (Purchase) (data) (sanitize) karein
    $purchase_id    = isset( $data['purchase_id'] ) ? absint( $data['purchase_id'] ) : 0;
    $supplier_id    = isset( $data['supplier_id'] ) ? absint( $data['supplier_id'] ) : 0;
    $invoice_number = isset( $data['invoice_number'] ) ? sanitize_text_field( $data['invoice_number'] ) : '';
    $purchase_date  = isset( $data['purchase_date'] ) ? sanitize_text_field( $data['purchase_date'] ) : '';
    $subtotal       = isset( $data['subtotal'] ) ? floatval( $data['subtotal'] ) : 0;
    $additional_costs = isset( $data['additional_costs'] ) ? floatval( $data['additional_costs'] ) : 0;
    $total_amount   = isset( $data['total_amount'] ) ? floatval( $data['total_amount'] ) : 0;
    $notes          = isset( $data['notes'] ) ? sanitize_textarea_field( $data['notes'] ) : '';
    
    // (Items) (JSON string) se (array) mein (convert) karein
    $items = isset( $data['items'] ) ? json_decode( $data['items'], true ) : [];

    // (Validation)
    if ( empty( $purchase_date ) || empty( $items ) || $subtotal < 0 || $additional_costs < 0 ) {
        wp_send_json_error( [ 'message' => __( 'Please fill all required fields correctly (Date and Items).', RSAM_TEXT_DOMAIN ) ], 400 );
    }

    // (Purchase editing) ko (block) karein (jaisa ke (Note) mein bataya gaya)
    if ( $purchase_id > 0 ) {
        // (Future Update): (Editing) (handle) karne ke liye (logic) yahan ayega.
        // Is mein (batches) ko (find) karna, (stock) (reverse) karna, aur phir (re-apply) karna shamil hoga.
        // Yeh (destructive) ho sakta hai agar (stock) (sell) ho chuka hai.
        wp_send_json_error( [ 'message' => __( 'Editing existing purchases is not supported in this version.', RSAM_TEXT_DOMAIN ) ], 400 );
    }

    global $wpdb;
	$tables = rsam_get_table_names();
    
    // (Database Transaction) shuru karein
    $wpdb->query( 'START TRANSACTION' );

    try {
        // 1. (Purchase) (record) (Insert) karein
        $purchase_result = $wpdb->insert(
            $tables['purchases'],
            [
                'supplier_id'      => $supplier_id,
                'invoice_number'   => $invoice_number,
                'purchase_date'    => $purchase_date,
                'subtotal'         => $subtotal,
                'additional_costs' => $additional_costs,
                'total_amount'     => $total_amount,
                'notes'            => $notes,
            ],
            [ '%d', '%s', '%s', '%f', '%f', '%f', '%s' ]
        );

        if ( ! $purchase_result ) {
            throw new Exception( __( 'Failed to save main purchase record.', RSAM_TEXT_DOMAIN ) );
        }
        
        $new_purchase_id = $wpdb->insert_id;

        // (Additional Costs) ko (distribute) karne ke liye (factor)
        // (Factor) = (Total Additional Costs) / (Items Subtotal)
        // (Cost Price) = (Purchase Price) + (Purchase Price * Factor)
        $cost_distribution_factor = ( $subtotal > 0 ) ? ( $additional_costs / $subtotal ) : 0;

        // 2. (Items) ko (loop) karein, (Batches) banayein, aur (Stock) (update) karein
        foreach ( $items as $item ) {
            $product_id     = absint( $item['product_id'] );
            $quantity       = floatval( $item['quantity'] );
            $purchase_price = floatval( $item['purchase_price'] );
            $expiry_date    = ! empty( $item['expiry_date'] ) ? sanitize_text_field( $item['expiry_date'] ) : null;
            
            if ( $quantity <= 0 || $purchase_price < 0 ) {
                continue; // (Invalid item) ko (skip) karein
            }

            // (Cost Price) (Calculate) karein (Proportional distribution)
            $distributed_cost = $purchase_price * $cost_distribution_factor;
            $cost_price = $purchase_price + $distributed_cost;
            
            // 2a. Naya (Product Batch) banayein
            $batch_result = $wpdb->insert(
                $tables['product_batches'],
                [
                    'product_id'        => $product_id,
                    'purchase_id'       => $new_purchase_id,
                    'quantity_received' => $quantity,
                    'quantity_in_stock' => $quantity, // Shuru mein (stock) (full) hoga
                    'purchase_price'    => $purchase_price,
                    'cost_price'        => $cost_price,
                    'expiry_date'       => $expiry_date,
                    'received_at'       => $purchase_date . ' ' . wp_date('H:i:s'),
                ],
                [ '%d', '%d', '%f', '%f', '%f', '%f', '%s', '%s' ]
            );
            
            if ( ! $batch_result ) {
                throw new Exception( __( 'Failed to create product batch.', RSAM_TEXT_DOMAIN ) );
            }

            $new_batch_id = $wpdb->insert_id;
            
            // 2b. (Purchase Item) (record) (link) karein
            $item_result = $wpdb->insert(
                $tables['purchase_items'],
                [
                    'purchase_id'   => $new_purchase_id,
                    'product_id'    => $product_id,
                    'batch_id'      => $new_batch_id,
                    'quantity'      => $quantity,
                    'purchase_price' => $purchase_price,
                    'item_subtotal' => $quantity * $purchase_price,
                ],
                [ '%d', '%d', '%d', '%f', '%f', '%f' ]
            );

            if ( ! $item_result ) {
                throw new Exception( __( 'Failed to save purchase item link.', RSAM_TEXT_DOMAIN ) );
            }

            // 2c. (Main Product) table mein (Stock Quantity) (update) karein
            // (Use (SQL) (increment) to avoid (race conditions))
            $stock_update_result = $wpdb->query( $wpdb->prepare(
                "UPDATE {$tables['products']} SET stock_quantity = stock_quantity + %f WHERE id = %d",
                $quantity,
                $product_id
            ) );

            if ( $stock_update_result === false ) {
                 throw new Exception( __( 'Failed to update main product stock.', RSAM_TEXT_DOMAIN ) );
            }
        }
        
        // (Transaction) (Commit) karein
        $wpdb->query( 'COMMIT' );
        wp_send_json_success( [ 'message' => __( 'Purchase recorded successfully. Stock updated.', RSAM_TEXT_DOMAIN ) ] );

    } catch ( Exception $e ) {
        // (Transaction) (Rollback) karein agar koi (error) aaye
        $wpdb->query( 'ROLLBACK' );
        wp_send_json_error( [ 'message' => $e->getMessage() ], 500 );
    }
}
add_action( 'wp_ajax_rsam_save_purchase', 'rsam_ajax_save_purchase' );


/**
 * (AJAX) Handler: (Purchase) (delete) karna.
 * WARNING: Yeh (stock) ko (reverse) karega. Yeh (dangerous) ho sakta hai.
 * Hum check karenge ke (batch) se (stock) (sell) to nahi hua.
 */
function rsam_ajax_delete_purchase() {
    check_ajax_referer( 'rsam-ajax-nonce', 'nonce' );
	if ( ! current_user_can( 'rsam_manage_purchases' ) ) {
		wp_send_json_error( [ 'message' => __( 'You do not have permission.', RSAM_TEXT_DOMAIN ) ], 403 );
	}
    
    $purchase_id = isset( $_POST['purchase_id'] ) ? absint( $_POST['purchase_id'] ) : 0;
    if ( $purchase_id <= 0 ) {
		wp_send_json_error( [ 'message' => __( 'Invalid Purchase ID.', RSAM_TEXT_DOMAIN ) ], 400 );
	}
    
    global $wpdb;
	$tables = rsam_get_table_names();

    // (Transaction) shuru karein
    $wpdb->query( 'START TRANSACTION' );
    
    try {
        // 1. Is (purchase) se (link) tamam (batches) (find) karein
        $batches = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, product_id, quantity_received, quantity_in_stock 
             FROM {$tables['product_batches']} 
             WHERE purchase_id = %d",
            $purchase_id
        ) );

        if ( ! $batches ) {
            // Aisa (purchase) jis ke (batches) nahi hain (ya pehle (delete) ho chuke)
             throw new Exception( __( 'No associated product batches found for this purchase.', RSAM_TEXT_DOMAIN ) );
        }
        
        foreach ( $batches as $batch ) {
            // 2. Check karein ke (stock) (sell) to nahi hua
            // Agar (received) (quantity) aur (stock) (quantity) barabar nahi, iska matlab (sell) hua hai
            if ( (float) $batch->quantity_received !== (float) $batch->quantity_in_stock ) {
                throw new Exception( __( 'Cannot delete. Stock from this purchase (Batch #%d) has already been sold.', RSAM_TEXT_DOMAIN ), $batch->id );
            }

            // 3. (Main Product) se (stock) (reverse) karein
            $stock_update_result = $wpdb->query( $wpdb->prepare(
                "UPDATE {$tables['products']} SET stock_quantity = stock_quantity - %f WHERE id = %d",
                $batch->quantity_in_stock,
                $batch->product_id
            ) );
            
            if ( $stock_update_result === false ) {
                throw new Exception( __( 'Failed to reverse product stock.', RSAM_TEXT_DOMAIN ) );
            }

            // 4. (Batch) (delete) karein
            $wpdb->delete( $tables['product_batches'], [ 'id' => $batch->id ], [ '%d' ] );
        }
        
        // 5. (Purchase Items) (delete) karein
        $wpdb->delete( $tables['purchase_items'], [ 'purchase_id' => $purchase_id ], [ '%d' ] );
        
        // 6. (Main Purchase) (record) (delete) karein
        $wpdb->delete( $tables['purchases'], [ 'id' => $purchase_id ], [ '%d' ] );

        // (Transaction) (Commit) karein
        $wpdb->query( 'COMMIT' );
        wp_send_json_success( [ 'message' => __( 'Purchase deleted and stock reversed successfully.', RSAM_TEXT_DOMAIN ) ] );

    } catch ( Exception $e ) {
        // (Transaction) (Rollback) karein
        $wpdb->query( 'ROLLBACK' );
        wp_send_json_error( [ 'message' => $e->getMessage() ], 500 );
    }
}
add_action( 'wp_ajax_rsam_delete_purchase', 'rsam_ajax_delete_purchase' );


/** Part 6 — Yahan khatam hua */
/**
 * Part 7 — Sales (Templates)
 */

/**
 * Sales Screen ke liye (HTML <template>)
 * Yeh (template) bhi 'list' (fehrist) aur 'form' (POS view) (modes) ko handle karega.
 */
function rsam_template_sales() {
	?>
	<template id="rsam-tmpl-sales">
		
		<div id="rsam-sales-list-view">
			<div class="rsam-screen-header">
				<h1><?php _e( 'Sales Ledger', RSAM_TEXT_DOMAIN ); ?></h1>
				<button type="button" class="button button-primary" id="rsam-add-new-sale">
					<span class="dashicons dashicons-cart"></span>
					<?php _e( 'New Sale (POS)', RSAM_TEXT_DOMAIN ); ?>
				</button>
			</div>

			<div class="rsam-list-controls">
				<input type="search" id="rsam-sales-search" class="rsam-search-field" placeholder="<?php esc_attr_e( 'Search by Sale ID or Customer...', RSAM_TEXT_DOMAIN ); ?>">
			</div>

			<div class="rsam-list-table-wrapper">
				<table class="rsam-list-table" id="rsam-sales-table">
					<thead>
						<tr>
							<th><?php _e( 'Sale ID', RSAM_TEXT_DOMAIN ); ?></th>
							<th><?php _e( 'Customer', RSAM_TEXT_DOMAIN ); ?></th>
							<th><?php _e( 'Sale Date', RSAM_TEXT_DOMAIN ); ?></th>
							<th><?php _e( 'Total Amount', RSAM_TEXT_DOMAIN ); ?></th>
                            <th><?php _e( 'Total Profit', RSAM_TEXT_DOMAIN ); ?></th>
                            <th><?php _e( 'Payment Status', RSAM_TEXT_DOMAIN ); ?></th>
							<th><?php _e( 'Actions', RSAM_TEXT_DOMAIN ); ?></th>
						</tr>
					</thead>
					<tbody id="rsam-sales-table-body">
						<tr>
							<td colspan="7" class="rsam-list-loading">
								<span class="rsam-loader-spinner"></span> <?php _e( 'Loading sales...', RSAM_TEXT_DOMAIN ); ?>
							</td>
						</tr>
					</tbody>
				</table>
			</div>

			<div class="rsam-pagination" id="rsam-sales-pagination">
				</div>
		</div>

		<div id="rsam-sales-form-view" style="display: none;">
			
            <div class="rsam-screen-header">
				<h1 id="rsam-sale-form-title"><?php _e( 'New Sale (Point of Sale)', RSAM_TEXT_DOMAIN ); ?></h1>
				<button type="button" class="button" id="rsam-back-to-sales-list">
					<span class="dashicons dashicons-arrow-left-alt"></span>
					<?php _e( 'Back to Sales Ledger', RSAM_TEXT_DOMAIN ); ?>
				</button>
			</div>

            <div class="rsam-pos-layout">

                <div class="rsam-pos-cart">
                    <form id="rsam-sale-form" class="rsam-form">
                        <input type="hidden" name="sale_id" value="0">
                        
                        <div class="rsam-card">
                            <div class="rsam-form-field">
                                <label for="rsam-sale-customer"><?php _e( 'Customer', RSAM_TEXT_DOMAIN ); ?></label>
                                <select id="rsam-sale-customer" name="customer_id" class="rsam-customer-search-select">
                                    <option value="0"><?php _e( 'Walk-in Customer', RSAM_TEXT_DOMAIN ); ?></option>
                                    </select>
                                <button type="button" class="button button-small rsam-quick-add" data-type="customer" title="<?php esc_attr_e( 'Add New Customer', RSAM_TEXT_DOMAIN ); ?>"><span class="dashicons dashicons-plus-alt"></span></button>
                            </div>
                        </div>

                        <div class="rsam-card">
                            <div class="rsam-form-field">
                                <label for="rsam-sale-product-search"><?php _e( 'Scan or Search Product', RSAM_TEXT_DOMAIN ); ?></label>
                                <input type="text" id="rsam-sale-product-search" placeholder="<?php esc_attr_e( 'Type product name or scan barcode...', RSAM_TEXT_DOMAIN ); ?>">
                            </div>
                        </div>

                        <div class="rsam-card rsam-cart-items-wrapper">
                            <div class="rsam-list-table-wrapper">
                                <table class="rsam-list-table" id="rsam-sale-items-table">
                                    <thead>
                                        <tr>
                                            <th><?php _e( 'Product', RSAM_TEXT_DOMAIN ); ?></th>
                                            <th><?php _e( 'Qty', RSAM_TEXT_DOMAIN ); ?></th>
                                            <th><?php _e( 'Price', RSAM_TEXT_DOMAIN ); ?></th>
                                            <th><?php _e( 'Total', RSAM_TEXT_DOMAIN ); ?></th>
                                            <th><?php _e( 'Action', RSAM_TEXT_DOMAIN ); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody id="rsam-sale-items-body">
                                        <tr class="rsam-no-items-row">
                                            <td colspan="5"><?php _e( 'Cart is empty.', RSAM_TEXT_DOMAIN ); ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </form>
                </div>

                <div class="rsam-pos-summary">
                    <div class="rsam-card">
                        <h3><?php _e( 'Transaction Summary', RSAM_TEXT_DOMAIN ); ?></h3>
                        
                        <div class="rsam-summary-row">
                            <span><?php _e( 'Subtotal', RSAM_TEXT_DOMAIN ); ?></span>
                            <span id="rsam-sale-subtotal">Rs. 0.00</span>
                        </div>

                        <div class="rsam-summary-row rsam-form-field-inline">
                            <label for="rsam-sale-discount"><?php _e( 'Discount', RSAM_TEXT_DOMAIN ); ?></label>
                            <input type="number" id="rsam-sale-discount" name="discount_amount" step="0.01" min="0" value="0">
                        </div>
                        
                        <div class="rsam-summary-row rsam-summary-total">
                            <span><?php _e( 'Total Amount', RSAM_TEXT_DOMAIN ); ?></span>
                            <span id="rsam-sale-total-amount">Rs. 0.00</span>
                        </div>
                        
                        <hr>
                        
                        <div class="rsam-form-field">
                            <label for="rsam-sale-payment-status"><?php _e( 'Payment Status', RSAM_TEXT_DOMAIN ); ?> <span class="rsam-required">*</span></label>
                            <select id="rsam-sale-payment-status" name="payment_status" required>
                                <option value="paid"><?php _e( 'Paid', RSAM_TEXT_DOMAIN ); ?></option>
                                <option value="unpaid"><?php _e( 'Unpaid (Add to Khata)', RSAM_TEXT_DOMAIN ); ?></option>
                                </select>
                        </div>
                        
                        <div class="rsam-form-field rsam-sale-notes">
                            <label for="rsam-sale-notes"><?php _e( 'Sale Notes', RSAM_TEXT_DOMAIN ); ?></label>
                            <textarea id="rsam-sale-notes" name="notes" rows="3"></textarea>
                        </div>

                        <div class="rsam-form-actions rsam-pos-actions">
                            <button type="button" class="button button-danger" id="rsam-cancel-sale-form"><?php _e( 'Cancel', RSAM_TEXT_DOMAIN ); ?></button>
                            <button type="button" class="button button-primary button-hero" id="rsam-save-sale-form">
                                <span class="rsam-btn-text"><?php _e( 'Complete Sale', RSAM_TEXT_DOMAIN ); ?></span>
                                <span class="rsam-loader-spinner"></span>
                            </button>
                        </div>

                        <div id="rsam-sale-stock-alert" class="rsam-alert rsam-alert-danger" style="display: none;">
                            </div>

                    </div>
                </div>

            </div>
		</div>

	</template>
	<?php
}

/** Part 7 — Yahan khatam hua */
/**
 * Part 8 — Sales (AJAX Handlers)
 */

/**
 * (AJAX) Handler: (Sales) ki list (fetch) karne ke liye.
 */
function rsam_ajax_get_sales() {
	// (Security) checks
	check_ajax_referer( 'rsam-ajax-nonce', 'nonce' );
	if ( ! current_user_can( 'rsam_manage_sales' ) ) {
		wp_send_json_error( [ 'message' => __( 'You do not have permission.', RSAM_TEXT_DOMAIN ) ], 403 );
	}

	global $wpdb;
	$tables = rsam_get_table_names();

	// (Pagination) parameters
	$page = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
	$limit = 20;
	$offset = ( $page - 1 ) * $limit;

	// (Search) parameter
	$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';

	// (Query) conditions
	$where_clause = '';
	$params = [];
	if ( ! empty( $search ) ) {
        // (Sale ID) ya (Customer Name) se (search)
		$search_like = '%' . $wpdb->esc_like( $search ) . '%';
        $search_id = absint( $search );
		$where_clause = ' WHERE (s.id = %d OR c.name LIKE %s)';
		$params[] = $search_id;
        $params[] = $search_like;
	}

	// (Total items) (count) karein
	$total_query = "SELECT COUNT(s.id) 
                    FROM {$tables['sales']} s
                    LEFT JOIN {$tables['customers']} c ON s.customer_id = c.id
                    $where_clause";
	$total_items = $wpdb->get_var( $wpdb->prepare( $total_query, $params ) );

	// (Sales) (fetch) karein
	$query = $wpdb->prepare(
		"SELECT 
			s.id, 
			s.sale_date, 
			s.total_amount,
            s.total_profit,
            s.payment_status,
			COALESCE(c.name, %s) as customer_name
		FROM 
			{$tables['sales']} s
		LEFT JOIN 
			{$tables['customers']} c ON s.customer_id = c.id
		$where_clause
		ORDER BY 
			s.sale_date DESC, s.id DESC
		LIMIT %d
		OFFSET %d",
		array_merge( [ __( 'Walk-in Customer', RSAM_TEXT_DOMAIN ) ], $params, [ $limit, $offset ] )
	);

	$sales = $wpdb->get_results( $query );
    
    // (Data) ko (format) karein
    foreach ( $sales as $sale ) {
        $sale->total_amount_formatted = rsam_format_price( $sale->total_amount );
        $sale->total_profit_formatted = rsam_format_price( $sale->total_profit );
        $sale->sale_date_formatted = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $sale->sale_date ) );
        $sale->payment_status_label = ucfirst( $sale->payment_status );
    }

	wp_send_json_success( [
		'sales'      => $sales,
		'pagination' => [
			'total_items' => (int) $total_items,
			'total_pages' => ceil( $total_items / $limit ),
			'current_page' => $page,
		],
	] );
}
add_action( 'wp_ajax_rsam_get_sales', 'rsam_ajax_get_sales' );


/**
 * (AJAX) Handler: Ek (Sale) ki mukammal (details) (fetch) karna (Receipt ke liye).
 */
function rsam_ajax_get_sale_details() {
    check_ajax_referer( 'rsam-ajax-nonce', 'nonce' );
	if ( ! current_user_can( 'rsam_manage_sales' ) ) {
		wp_send_json_error( [ 'message' => __( 'You do not have permission.', RSAM_TEXT_DOMAIN ) ], 403 );
	}
    
    $sale_id = isset( $_POST['sale_id'] ) ? absint( $_POST['sale_id'] ) : 0;
    if ( $sale_id <= 0 ) {
		wp_send_json_error( [ 'message' => __( 'Invalid Sale ID.', RSAM_TEXT_DOMAIN ) ], 400 );
	}

    global $wpdb;
	$tables = rsam_get_table_names();

    // (Sale) ki (main details)
    $sale = $wpdb->get_row( $wpdb->prepare(
        "SELECT s.*, COALESCE(c.name, %s) as customer_name 
         FROM {$tables['sales']} s
         LEFT JOIN {$tables['customers']} c ON s.customer_id = c.id
         WHERE s.id = %d",
         __( 'Walk-in Customer', RSAM_TEXT_DOMAIN ), $sale_id
    ) );
    
    if ( ! $sale ) {
        wp_send_json_error( [ 'message' => __( 'Sale not found.', RSAM_TEXT_DOMAIN ) ], 404 );
    }
    
    // (Sale Items) (details)
    $items = $wpdb->get_results( $wpdb->prepare(
        "SELECT 
            si.*,
            p.name as product_name,
            p.unit_type
        FROM {$tables['sale_items']} si
        JOIN {$tables['products']} p ON si.product_id = p.id
        WHERE si.sale_id = %d",
        $sale_id
    ) );
    
    // (Data) (format) karein
    $sale->total_amount_formatted = rsam_format_price( $sale->total_amount );
    $sale->subtotal_formatted = rsam_format_price( $sale->subtotal );
    $sale->discount_amount_formatted = rsam_format_price( $sale->discount_amount );
    $sale->total_cost_formatted = rsam_format_price( $sale->total_cost );
    $sale->total_profit_formatted = rsam_format_price( $sale->total_profit );
    $sale->sale_date_formatted = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $sale->sale_date ) );
    
    foreach ( $items as $item ) {
        $item->selling_price_formatted = rsam_format_price( $item->selling_price );
        $item->item_total = $item->quantity * $item->selling_price;
        $item->item_total_formatted = rsam_format_price( $item->item_total );
    }

    wp_send_json_success( [
        'sale'  => $sale,
        'items' => $items,
    ] );
}
add_action( 'wp_ajax_rsam_get_sale_details', 'rsam_ajax_get_sale_details' );


/**
 * (AJAX) Handler: Nayi (Sale) (save) karna.
 * Yeh (handler) (FIFO) (logic) istemal karke (stock) (deduct) karega aur (profit) (calculate) karega.
 */
function rsam_ajax_save_sale() {
    check_ajax_referer( 'rsam-ajax-nonce', 'nonce' );
	if ( ! current_user_can( 'rsam_manage_sales' ) ) {
		wp_send_json_error( [ 'message' => __( 'You do not have permission.', RSAM_TEXT_DOMAIN ) ], 403 );
	}

    global $wpdb;
	$tables = rsam_get_table_names();

    // (POST data) (parse) karein
	$data = wp_unslash( $_POST );
    
    // (Main) (Sale) (data) (sanitize) karein
    $sale_id         = isset( $data['sale_id'] ) ? absint( $data['sale_id'] ) : 0;
    $customer_id     = isset( $data['customer_id'] ) ? absint( $data['customer_id'] ) : 0;
    $discount_amount = isset( $data['discount_amount'] ) ? floatval( $data['discount_amount'] ) : 0;
    $payment_status  = isset( $data['payment_status'] ) ? sanitize_key( $data['payment_status'] ) : 'paid';
    $notes           = isset( $data['notes'] ) ? sanitize_textarea_field( $data['notes'] ) : '';
    
    // (Items) (JSON string) se (array) mein (convert) karein
    $items = isset( $data['items'] ) ? json_decode( $data['items'], true ) : [];
    
    // (Validation)
    if ( $sale_id > 0 ) {
         wp_send_json_error( [ 'message' => __( 'Editing existing sales is not supported. Please delete and create a new one.', RSAM_TEXT_DOMAIN ) ], 400 );
    }
    if ( empty( $items ) ) {
        wp_send_json_error( [ 'message' => __( 'Cart is empty. Please add products to sell.', RSAM_TEXT_DOMAIN ) ], 400 );
    }
    if ( $payment_status === 'unpaid' && $customer_id === 0 ) {
        wp_send_json_error( [ 'message' => __( 'Please select a customer to save an unpaid (Khata) sale.', RSAM_TEXT_DOMAIN ) ], 400 );
    }

    // (Sale) (Totals) (calculate) karne ke liye (variables)
    $grand_subtotal = 0;
    $grand_total_cost = 0;

    // (Database Transaction) shuru karein
    $wpdb->query( 'START TRANSACTION' );

    try {
        // (Sale) (record) (temporarily) banayein (taake humein (Sale ID) mil jaye)
        // Hum isey (baad) mein (totals) ke sath (update) karenge
        $sale_time = current_time( 'mysql' );
        $wpdb->insert(
            $tables['sales'],
            [
                'customer_id'   => $customer_id,
                'sale_date'     => $sale_time,
                'subtotal'      => 0, // (Temp)
                'total_amount'  => 0, // (Temp)
                'total_cost'    => 0, // (Temp)
                'total_profit'  => 0, // (Temp)
                'payment_status'=> $payment_status,
                'notes'         => $notes,
            ],
            [ '%d', '%s', '%f', '%f', '%f', '%f', '%s', '%s' ]
        );
        $new_sale_id = $wpdb->insert_id;
        if ( ! $new_sale_id ) {
            throw new Exception( __( 'Failed to create initial sale record.', RSAM_TEXT_DOMAIN ) );
        }

        // Ab (items) ko (process) karein (FIFO Logic)
        foreach ( $items as $item ) {
            $product_id     = absint( $item['product_id'] );
            $quantity_to_sell = floatval( $item['quantity'] );
            $selling_price  = floatval( $item['selling_price'] );
            
            if ( $quantity_to_sell <= 0 ) continue;
            
            $grand_subtotal += $quantity_to_sell * $selling_price;
            $item_total_cost = 0;
            
            // 1. (Stock) (check) karein
            $available_stock = $wpdb->get_var( $wpdb->prepare(
                "SELECT stock_quantity FROM {$tables['products']} WHERE id = %d FOR UPDATE", // (Lock row)
                $product_id
            ) );
            
            if ( $available_stock < $quantity_to_sell ) {
                $product_name = $wpdb->get_var( $wpdb->prepare( "SELECT name FROM {$tables['products']} WHERE id = %d", $product_id ) );
                throw new Exception( sprintf( __( 'Insufficient stock for %s. Available: %s, Required: %s', RSAM_TEXT_DOMAIN ), $product_name, $available_stock, $quantity_to_sell ) );
            }

            // 2. (FIFO) ke liye (batches) (fetch) karein
            $batches = $wpdb->get_results( $wpdb->prepare(
                "SELECT id, cost_price, quantity_in_stock 
                 FROM {$tables['product_batches']} 
                 WHERE product_id = %d AND quantity_in_stock > 0 
                 ORDER BY received_at ASC, id ASC FOR UPDATE", // (Lock rows)
                $product_id
            ) );
            
            $temp_qty_needed = $quantity_to_sell;

            foreach ( $batches as $batch ) {
                if ( $temp_qty_needed <= 0 ) break;

                $sell_from_this_batch = min( $temp_qty_needed, (float) $batch->quantity_in_stock );
                $batch_cost_price = (float) $batch->cost_price;
                
                $item_total_cost += $sell_from_this_batch * $batch_cost_price;
                
                // 3a. (Sale Item) (record) (insert) karein
                $wpdb->insert(
                    $tables['sale_items'],
                    [
                        'sale_id'       => $new_sale_id,
                        'product_id'    => $product_id,
                        'batch_id'      => $batch->id,
                        'quantity'      => $sell_from_this_batch,
                        'selling_price' => $selling_price,
                        'cost_price'    => $batch_cost_price,
                        'item_profit'   => ( $selling_price - $batch_cost_price ) * $sell_from_this_batch,
                    ],
                    [ '%d', '%d', '%d', '%f', '%f', '%f', '%f' ]
                );
                
                // 3b. (Batch) (stock) (update) karein
                $wpdb->query( $wpdb->prepare(
                    "UPDATE {$tables['product_batches']} SET quantity_in_stock = quantity_in_stock - %f WHERE id = %d",
                    $sell_from_this_batch,
                    $batch->id
                ) );
                
                $temp_qty_needed -= $sell_from_this_batch;
            }

            // 3c. (Main Product) (stock) (update) karein
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$tables['products']} SET stock_quantity = stock_quantity - %f WHERE id = %d",
                $quantity_to_sell,
                $product_id
            ) );

            $grand_total_cost += $item_total_cost;
        }

        // 4. (Main Sale) (record) ko (Final Totals) ke sath (update) karein
        $total_amount = $grand_subtotal - $discount_amount;
        $total_profit = ( $grand_subtotal - $grand_total_cost ) - $discount_amount; // (Profit calculation)

        $wpdb->update(
            $tables['sales'],
            [
                'subtotal'        => $grand_subtotal,
                'discount_amount' => $discount_amount,
                'total_amount'    => $total_amount,
                'total_cost'      => $grand_total_cost,
                'total_profit'    => $total_profit,
            ],
            [ 'id' => $new_sale_id ],
            [ '%f', '%f', '%f', '%f', '%f' ],
            [ '%d' ]
        );
        
        // 5. (Customer Khata) (update) karein (agar (unpaid) hai)
        if ( $payment_status === 'unpaid' && $customer_id > 0 ) {
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$tables['customers']} SET credit_balance = credit_balance + %f WHERE id = %d",
                $total_amount,
                $customer_id
            ) );
        }
        
        // (Transaction) (Commit) karein
        $wpdb->query( 'COMMIT' );
        wp_send_json_success( [ 
            'message' => __( 'Sale recorded successfully.', RSAM_TEXT_DOMAIN ),
            'sale_id' => $new_sale_id,
        ] );

    } catch ( Exception $e ) {
        // (Transaction) (Rollback) karein agar koi (error) aaye
        $wpdb->query( 'ROLLBACK' );
        wp_send_json_error( [ 'message' => $e->getMessage() ], 500 );
    }
}
add_action( 'wp_ajax_rsam_save_sale', 'rsam_ajax_save_sale' );


/**
 * (AJAX) Handler: (Sale) (delete) karna (Stock reversal).
 * Yeh (Stock) ko wapis (Batch) mein (add) karega.
 */
function rsam_ajax_delete_sale() {
    check_ajax_referer( 'rsam-ajax-nonce', 'nonce' );
	if ( ! current_user_can( 'rsam_manage_sales' ) ) {
		wp_send_json_error( [ 'message' => __( 'You do not have permission.', RSAM_TEXT_DOMAIN ) ], 403 );
	}
    
    $sale_id = isset( $_POST['sale_id'] ) ? absint( $_POST['sale_id'] ) : 0;
    if ( $sale_id <= 0 ) {
		wp_send_json_error( [ 'message' => __( 'Invalid Sale ID.', RSAM_TEXT_DOMAIN ) ], 400 );
	}
    
    global $wpdb;
	$tables = rsam_get_table_names();

    // (Transaction) shuru karein
    $wpdb->query( 'START TRANSACTION' );

    try {
        // 1. (Sale) (details) (fetch) karein
        $sale = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tables['sales']} WHERE id = %d FOR UPDATE", $sale_id ) );
        if ( ! $sale ) {
            throw new Exception( __( 'Sale not found.', RSAM_TEXT_DOMAIN ) );
        }
        
        // 2. (Sale Items) (fetch) karein
        $sale_items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$tables['sale_items']} WHERE sale_id = %d", $sale_id ) );
        if ( ! $sale_items ) {
             throw new Exception( __( 'Sale items not found.', RSAM_TEXT_DOMAIN ) );
        }

        // 3. (Stock) (reverse) karein (loop) (items)
        foreach ( $sale_items as $item ) {
            // 3a. (Batch) mein (stock) wapis (add) karein
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$tables['product_batches']} SET quantity_in_stock = quantity_in_stock + %f WHERE id = %d",
                $item->quantity,
                $item->batch_id
            ) );
            
            // 3b. (Main Product) mein (stock) wapis (add) karein
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$tables['products']} SET stock_quantity = stock_quantity + %f WHERE id = %d",
                $item->quantity,
                $item->product_id
            ) );
        }
        
        // 4. (Customer Khata) (reverse) karein (agar (unpaid) tha)
        if ( $sale->payment_status === 'unpaid' && $sale->customer_id > 0 ) {
             $wpdb->query( $wpdb->prepare(
                "UPDATE {$tables['customers']} SET credit_balance = credit_balance - %f WHERE id = %d",
                $sale->total_amount,
                $sale->customer_id
            ) );
        }
        
        // 5. (Sale Items) (delete) karein
        $wpdb->delete( $tables['sale_items'], [ 'sale_id' => $sale_id ], [ '%d' ] );

        // 6. (Main Sale) (record) (delete) karein
        $wpdb->delete( $tables['sales'], [ 'id' => $sale_id ], [ '%d' ] );
        
        // (Transaction) (Commit) karein
        $wpdb->query( 'COMMIT' );
        wp_send_json_success( [ 'message' => __( 'Sale deleted. Stock and customer khata reversed successfully.', RSAM_TEXT_DOMAIN ) ] );

    } catch ( Exception $e ) {
        // (Transaction) (Rollback) karein
        $wpdb->query( 'ROLLBACK' );
        wp_send_json_error( [ 'message' => $e->getMessage() ], 500 );
    }
}
add_action( 'wp_ajax_rsam_delete_sale', 'rsam_ajax_delete_sale' );

/** Part 8 — Yahan khatam hua */
/**
 * Part 9 — Expenses (Templates + AJAX)
 */

/**
 * Expenses Screen ke liye (HTML <template>)
 */
function rsam_template_expenses() {
	?>
	<template id="rsam-tmpl-expenses">
		<div class="rsam-screen-header">
			<h1><?php _e( 'Expenses', RSAM_TEXT_DOMAIN ); ?></h1>
			<button type="button" class="button button-primary" id="rsam-add-new-expense">
				<span class="dashicons dashicons-plus-alt"></span>
				<?php _e( 'Add New Expense', RSAM_TEXT_DOMAIN ); ?>
			</button>
		</div>

		<div class="rsam-list-controls">
			<input type="search" id="rsam-expense-search" class="rsam-search-field" placeholder="<?php esc_attr_e( 'Search by description...', RSAM_TEXT_DOMAIN ); ?>">
			
            <select id="rsam-expense-category-filter" name="category_filter" class="rsam-filter-field">
                <option value=""><?php _e( 'All Categories', RSAM_TEXT_DOMAIN ); ?></option>
                <option value="rent"><?php _e( 'Shop Rent', RSAM_TEXT_DOMAIN ); ?></option>
                <option value="salary"><?php _e( 'Employee Salary', RSAM_TEXT_DOMAIN ); ?></option>
                <option value="utility"><?php _e( 'Utilities (Electricity, Water)', RSAM_TEXT_DOMAIN ); ?></option>
                <option value="maintenance"><?php _e( 'Maintenance', RSAM_TEXT_DOMAIN ); ?></option>
                <option value="miscellaneous"><?php _e( 'Miscellaneous', RSAM_TEXT_DOMAIN ); ?></option>
            </select>

            <input type="date" id="rsam-expense-date-filter" name="date_filter" class="rsam-filter-field">
		</div>

		<div class="rsam-list-table-wrapper">
			<table class="rsam-list-table" id="rsam-expenses-table">
				<thead>
					<tr>
						<th><?php _e( 'Date', RSAM_TEXT_DOMAIN ); ?></th>
						<th><?php _e( 'Category', RSAM_TEXT_DOMAIN ); ?></th>
						<th><?php _e( 'Amount', RSAM_TEXT_DOMAIN ); ?></th>
						<th><?php _e( 'Description', RSAM_TEXT_DOMAIN ); ?></th>
						<th><?php _e( 'Actions', RSAM_TEXT_DOMAIN ); ?></th>
					</tr>
				</thead>
				<tbody id="rsam-expenses-table-body">
					<tr>
						<td colspan="5" class="rsam-list-loading">
							<span class="rsam-loader-spinner"></span> <?php _e( 'Loading expenses...', RSAM_TEXT_DOMAIN ); ?>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<div class="rsam-pagination" id="rsam-expenses-pagination">
			</div>

		<div id="rsam-expense-form-container" style="display: none;">
			<form id="rsam-expense-form" class="rsam-form">
				<input type="hidden" name="expense_id" value="0">
				
                <div class="rsam-form-grid">
                    <div class="rsam-form-field">
                        <label for="rsam-expense-date"><?php _e( 'Expense Date', RSAM_TEXT_DOMAIN ); ?> <span class="rsam-required">*</span></label>
                        <input type="date" id="rsam-expense-date" name="expense_date" required>
                    </div>
                    <div class="rsam-form-field">
                        <label for="rsam-expense-amount"><?php _e( 'Amount', RSAM_TEXT_DOMAIN ); ?> <span class="rsam-required">*</span></label>
                        <input type="number" id="rsam-expense-amount" name="amount" step="0.01" min="0" required>
                    </div>
                </div>

				<div class="rsam-form-field">
					<label for="rsam-expense-category"><?php _e( 'Category', RSAM_TEXT_DOMAIN ); ?> <span class="rsam-required">*</span></label>
					<select id="rsam-expense-category" name="category" required>
                        <option value=""><?php _e( 'Select Category', RSAM_TEXT_DOMAIN ); ?></option>
                        <option value="rent"><?php _e( 'Shop Rent', RSAM_TEXT_DOMAIN ); ?></option>
                        <option value="salary"><?php _e( 'Employee Salary', RSAM_TEXT_DOMAIN ); ?></option>
                        <option value="utility"><?php _e( 'Utilities (Electricity, Water)', RSAM_TEXT_DOMAIN ); ?></option>
                        <option value="maintenance"><?php _e( 'Maintenance', RSAM_TEXT_DOMAIN ); ?></option>
                        <option value="miscellaneous"><?php _e( 'Miscellaneous', RSAM_TEXT_DOMAIN ); ?></option>
                    </select>
				</div>
                
                <div class="rsam-form-field" id="rsam-expense-employee-field" style="display: none;">
                    <label for="rsam-expense-employee-id"><?php _e( 'Employee', RSAM_TEXT_DOMAIN ); ?> <span class="rsam-required">*</span></label>
                    <select id="rsam-expense-employee-id" name="employee_id">
                        <option value="0"><?php _e( 'Select Employee', RSAM_TEXT_DOMAIN ); ?></option>
                        </select>
                </div>

                <div class="rsam-form-field">
					<label for="rsam-expense-description"><?php _e( 'Description / Notes', RSAM_TEXT_DOMAIN ); ?></label>
					<textarea id="rsam-expense-description" name="description" rows="3"></textarea>
				</div>

			</form>
		</div>
	</template>
	<?php
}

/**
 * (AJAX) Handler: (Expenses) ki list (fetch) karne ke liye.
 */
function rsam_ajax_get_expenses() {
	// (Security) checks
	check_ajax_referer( 'rsam-ajax-nonce', 'nonce' );
	if ( ! current_user_can( 'rsam_manage_expenses' ) ) {
		wp_send_json_error( [ 'message' => __( 'You do not have permission.', RSAM_TEXT_DOMAIN ) ], 403 );
	}

	global $wpdb;
	$tables = rsam_get_table_names();

	// (Pagination) parameters
	$page = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
	$limit = 20;
	$offset = ( $page - 1 ) * $limit;

	// (Filters) & (Search)
	$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
    $category = isset( $_POST['category'] ) ? sanitize_key( $_POST['category'] ) : '';
    $date = isset( $_POST['date'] ) ? sanitize_text_field( $_POST['date'] ) : '';

	$where_clause = [];
	$params = [];
	
    $where_clause[] = '1=1'; // (Base condition)

	if ( ! empty( $search ) ) {
		$where_clause[] = 'e.description LIKE %s';
		$params[] = '%' . $wpdb->esc_like( $search ) . '%';
	}
    if ( ! empty( $category ) ) {
        $where_clause[] = 'e.category = %s';
        $params[] = $category;
    }
    if ( ! empty( $date ) ) {
        $where_clause[] = 'e.expense_date = %s';
        $params[] = $date;
    }
    
    $where_sql = ' WHERE ' . implode( ' AND ', $where_clause );

	// (Total items) (count) karein
	$total_items = $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(e.id) FROM {$tables['expenses']} e $where_sql",
		$params
	) );

	// (Expenses) (fetch) karein
	$query = $wpdb->prepare(
		"SELECT 
			e.*,
            emp.name as employee_name
		FROM 
			{$tables['expenses']} e
        LEFT JOIN
            {$tables['employees']} emp ON e.employee_id = emp.id
		$where_sql
		ORDER BY 
			e.expense_date DESC, e.id DESC
		LIMIT %d
		OFFSET %d",
		array_merge( $params, [ $limit, $offset ] )
	);

	$expenses = $wpdb->get_results( $query );
    
    // (Categories) ke (labels)
    $categories = [
        'rent' => __( 'Shop Rent', RSAM_TEXT_DOMAIN ),
        'salary' => __( 'Employee Salary', RSAM_TEXT_DOMAIN ),
        'utility' => __( 'Utilities', RSAM_TEXT_DOMAIN ),
        'maintenance' => __( 'Maintenance', RSAM_TEXT_DOMAIN ),
        'miscellaneous' => __( 'Miscellaneous', RSAM_TEXT_DOMAIN ),
    ];
    
    // (Data) ko (format) karein
    foreach ( $expenses as $expense ) {
        $expense->amount_formatted = rsam_format_price( $expense->amount );
        $expense->expense_date_formatted = wp_date( get_option( 'date_format' ), strtotime( $expense->expense_date ) );
        $expense->category_label = isset( $categories[$expense->category] ) ? $categories[$expense->category] : ucfirst( $expense->category );
        
        // Agar (salary) hai to (employee) ka naam dikhayein
        if ( $expense->category === 'salary' && ! empty( $expense->employee_name ) ) {
            $expense->description = $expense->employee_name . ( ! empty( $expense->description ) ? ' - ' . $expense->description : '' );
        }
    }

	wp_send_json_success( [
		'expenses'   => $expenses,
		'pagination' => [
			'total_items' => (int) $total_items,
			'total_pages' => ceil( $total_items / $limit ),
			'current_page' => $page,
		],
	] );
}
add_action( 'wp_ajax_rsam_get_expenses', 'rsam_ajax_get_expenses' );


/**
 * (AJAX) Handler: Naya (Expense) (save) ya (update) karne ke liye.
 */
function rsam_ajax_save_expense() {
	// (Security) checks
	check_ajax_referer( 'rsam-ajax-nonce', 'nonce' );
	if ( ! current_user_can( 'rsam_manage_expenses' ) ) {
		wp_send_json_error( [ 'message' => __( 'You do not have permission.', RSAM_TEXT_DOMAIN ) ], 403 );
	}

	// (POST data) (parse) karein
	parse_str( $_POST['form_data'], $data );

	// (Validate) aur (sanitize) karein
	$expense_id = isset( $data['expense_id'] ) ? absint( $data['expense_id'] ) : 0;
	$expense_date = isset( $data['expense_date'] ) ? sanitize_text_field( $data['expense_date'] ) : '';
    $amount = isset( $data['amount'] ) ? floatval( $data['amount'] ) : 0;
    $category = isset( $data['category'] ) ? sanitize_key( $data['category'] ) : '';
    $employee_id = isset( $data['employee_id'] ) ? absint( $data['employee_id'] ) : 0;
	$description = isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : '';

	// (Validation)
	if ( empty( $expense_date ) || empty( $category ) || $amount <= 0 ) {
		wp_send_json_error( [ 'message' => __( 'Please fill all required fields (Date, Amount, Category).', RSAM_TEXT_DOMAIN ) ], 400 );
	}
    
    // Agar (category) (salary) hai, to (employee) (ID) zaroori hai
    if ( $category === 'salary' && $employee_id === 0 ) {
         wp_send_json_error( [ 'message' => __( 'Please select an employee for salary expense.', RSAM_TEXT_DOMAIN ) ], 400 );
    }

	global $wpdb;
	$tables = rsam_get_table_names();

	$db_data = [
		'expense_date' => $expense_date,
		'amount'       => $amount,
        'category'     => $category,
        'employee_id'  => ( $category === 'salary' ) ? $employee_id : 0, // Sirf (salary) ke liye (save) karein
		'description'  => $description,
	];
    
    $db_formats = ['%s', '%f', '%s', '%d', '%s'];

	// (Update) ya (Insert)
	if ( $expense_id > 0 ) {
		// Update
		$result = $wpdb->update(
			$tables['expenses'],
			$db_data,
			[ 'id' => $expense_id ], // (WHERE)
			$db_formats,
			[ '%d' ] // (WHERE format)
		);
        $message = __( 'Expense updated successfully.', RSAM_TEXT_DOMAIN );
	} else {
		// Insert
        $db_data['created_at'] = current_time( 'mysql' );
        $db_formats[] = '%s';
        
		$result = $wpdb->insert(
			$tables['expenses'],
			$db_data,
			$db_formats
		);
        $expense_id = $wpdb->insert_id;
        $message = __( 'Expense added successfully.', RSAM_TEXT_DOMAIN );
	}

	if ( $result === false ) {
		wp_send_json_error( [ 'message' => __( 'Database error. Could not save expense.', RSAM_TEXT_DOMAIN ) ], 500 );
	}

	wp_send_json_success( [ 'message' => $message, 'expense_id' => $expense_id ] );
}
add_action( 'wp_ajax_rsam_save_expense', 'rsam_ajax_save_expense' );


/**
 * (AJAX) Handler: (Expense) (delete) karne ke liye.
 */
function rsam_ajax_delete_expense() {
	// (Security) checks
	check_ajax_referer( 'rsam-ajax-nonce', 'nonce' );
	if ( ! current_user_can( 'rsam_manage_expenses' ) ) {
		wp_send_json_error( [ 'message' => __( 'You do not have permission.', RSAM_TEXT_DOMAIN ) ], 403 );
	}

	$expense_id = isset( $_POST['expense_id'] ) ? absint( $_POST['expense_id'] ) : 0;

	if ( $expense_id <= 0 ) {
		wp_send_json_error( [ 'message' => __( 'Invalid Expense ID.', RSAM_TEXT_DOMAIN ) ], 400 );
	}

	global $wpdb;
	$tables = rsam_get_table_names();

	$result = $wpdb->delete( $tables['expenses'], [ 'id' => $expense_id ], [ '%d' ] );

	if ( $result ) {
		wp_send_json_success( [ 'message' => __( 'Expense deleted successfully.', RSAM_TEXT_DOMAIN ) ] );
	} else {
		wp_send_json_error( [ 'message' => __( 'Could not delete expense.', RSAM_TEXT_DOMAIN ) ], 500 );
	}
}
add_action( 'wp_ajax_rsam_delete_expense', 'rsam_ajax_delete_expense' );

/** Part 9 — Yahan khatam hua */
/**
 * Part 10 — Employees (Templates + AJAX)
 */

/**
 * Employees Screen ke liye (HTML <template>)
 */
function rsam_template_employees() {
	?>
	<template id="rsam-tmpl-employees">
		<div class="rsam-screen-header">
			<h1><?php _e( 'Employees', RSAM_TEXT_DOMAIN ); ?></h1>
			<button type="button" class="button button-primary" id="rsam-add-new-employee">
				<span class="dashicons dashicons-plus-alt"></span>
				<?php _e( 'Add New Employee', RSAM_TEXT_DOMAIN ); ?>
			</button>
		</div>

		<div class="rsam-list-controls">
			<input type="search" id="rsam-employee-search" class="rsam-search-field" placeholder="<?php esc_attr_e( 'Search by name or phone...', RSAM_TEXT_DOMAIN ); ?>">
		</div>

		<div class="rsam-list-table-wrapper">
			<table class="rsam-list-table" id="rsam-employees-table">
				<thead>
					<tr>
						<th><?php _e( 'Name', RSAM_TEXT_DOMAIN ); ?></th>
						<th><?php _e( 'Designation', RSAM_TEXT_DOMAIN ); ?></th>
						<th><?php _e( 'Phone', RSAM_TEXT_DOMAIN ); ?></th>
						<th><?php _e( 'Monthly Salary', RSAM_TEXT_DOMAIN ); ?></th>
                        <th><?php _e( 'Status', RSAM_TEXT_DOMAIN ); ?></th>
						<th><?php _e( 'Actions', RSAM_TEXT_DOMAIN ); ?></th>
					</tr>
				</thead>
				<tbody id="rsam-employees-table-body">
					<tr>
						<td colspan="6" class="rsam-list-loading">
							<span class="rsam-loader-spinner"></span> <?php _e( 'Loading employees...', RSAM_TEXT_DOMAIN ); ?>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<div class="rsam-pagination" id="rsam-employees-pagination">
			</div>

		<div id="rsam-employee-form-container" style="display: none;">
			<form id="rsam-employee-form" class="rsam-form">
				<input type="hidden" name="employee_id" value="0">
				
                <div class="rsam-form-field">
					<label for="rsam-employee-name"><?php _e( 'Full Name', RSAM_TEXT_DOMAIN ); ?> <span class="rsam-required">*</span></label>
					<input type="text" id="rsam-employee-name" name="name" required>
				</div>
                
                <div class="rsam-form-grid">
                    <div class="rsam-form-field">
                        <label for="rsam-employee-designation"><?php _e( 'Designation', RSAM_TEXT_DOMAIN ); ?></label>
                        <input type="text" id="rsam-employee-designation" name="designation" placeholder="<?php esc_attr_e( 'e.g., Salesman, Manager', RSAM_TEXT_DOMAIN ); ?>">
                    </div>
                    <div class="rsam-form-field">
                        <label for="rsam-employee-phone"><?php _e( 'Phone Number', RSAM_TEXT_DOMAIN ); ?></label>
                        <input type="tel" id="rsam-employee-phone" name="phone">
                    </div>
                </div>

                <div class="rsam-form-grid">
                    <div class="rsam-form-field">
                        <label for="rsam-employee-salary"><?php _e( 'Monthly Salary', RSAM_TEXT_DOMAIN ); ?> <span class="rsam-required">*</span></label>
                        <input type="number" id="rsam-employee-salary" name="monthly_salary" step="0.01" min="0" required>
                    </div>
                    <div class="rsam-form-field">
                        <label for="rsam-employee-joining-date"><?php _e( 'Joining Date', RSAM_TEXT_DOMAIN ); ?></label>
                        <input type="date" id="rsam-employee-joining-date" name="joining_date">
                    </div>
                </div>
                
                <div class="rsam-form-field">
                    <label>
                        <input type="checkbox" name="is_active" id="rsam-employee-is-active" value="1" checked>
                        <?php _e( 'Employee is Active', RSAM_TEXT_DOMAIN ); ?>
                    </label>
                </div>
			</form>
		</div>
	</template>
	<?php
}

/**
 * (AJAX) Handler: (Employees) ki list (fetch) karne ke liye.
 */
function rsam_ajax_get_employees() {
	// (Security) checks
	check_ajax_referer( 'rsam-ajax-nonce', 'nonce' );
	if ( ! current_user_can( 'rsam_manage_employees' ) ) {
		wp_send_json_error( [ 'message' => __( 'You do not have permission.', RSAM_TEXT_DOMAIN ) ], 403 );
	}

	global $wpdb;
	$tables = rsam_get_table_names();

	// (Pagination) parameters
	$page = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
	$limit = 20;
	$offset = ( $page - 1 ) * $limit;

	// (Search) parameter
	$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';

	// (Query) conditions
	$where_clause = '';
	$params = [];
	if ( ! empty( $search ) ) {
        $search_like = '%' . $wpdb->esc_like( $search ) . '%';
		$where_clause = ' WHERE (name LIKE %s OR phone LIKE %s)';
		$params[] = $search_like;
        $params[] = $search_like;
	}

	// (Total items) (count) karein
	$total_items = $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(id) FROM {$tables['employees']} $where_clause",
		$params
	) );

	// (Employees) (fetch) karein
	$query = $wpdb->prepare(
		"SELECT * FROM {$tables['employees']}
		$where_clause
		ORDER BY 
			name ASC
		LIMIT %d
		OFFSET %d",
		array_merge( $params, [ $limit, $offset ] )
	);

	$employees = $wpdb->get_results( $query );
    
    // (Data) ko (format) karein
    foreach ( $employees as $employee ) {
        $employee->monthly_salary_formatted = rsam_format_price( $employee->monthly_salary );
        $employee->status_label = $employee->is_active ? __( 'Active', RSAM_TEXT_DOMAIN ) : __( 'Inactive', RSAM_TEXT_DOMAIN );
    }

	wp_send_json_success( [
		'employees'  => $employees,
		'pagination' => [
			'total_items' => (int) $total_items,
			'total_pages' => ceil( $total_items / $limit ),
			'current_page' => $page,
		],
	] );
}
// (Employees) (list) (Expenses) (form) mein bhi zaroorat hoti hai
add_action( 'wp_ajax_rsam_get_employees', 'rsam_ajax_get_employees' );


/**
 * (AJAX) Handler: Naya (Employee) (save) ya (update) karne ke liye.
 */
function rsam_ajax_save_employee() {
	// (Security) checks
	check_ajax_referer( 'rsam-ajax-nonce', 'nonce' );
	if ( ! current_user_can( 'rsam_manage_employees' ) ) {
		wp_send_json_error( [ 'message' => __( 'You do not have permission.', RSAM_TEXT_DOMAIN ) ], 403 );
	}

	// (POST data) (parse) karein
	parse_str( $_POST['form_data'], $data );

	// (Validate) aur (sanitize) karein
	$employee_id = isset( $data['employee_id'] ) ? absint( $data['employee_id'] ) : 0;
	$name = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
    $designation = isset( $data['designation'] ) ? sanitize_text_field( $data['designation'] ) : '';
    $phone = isset( $data['phone'] ) ? sanitize_text_field( $data['phone'] ) : '';
    $monthly_salary = isset( $data['monthly_salary'] ) ? floatval( $data['monthly_salary'] ) : 0;
    $joining_date = isset( $data['joining_date'] ) && ! empty( $data['joining_date'] ) ? sanitize_text_field( $data['joining_date'] ) : null;
    $is_active = isset( $data['is_active'] ) ? 1 : 0;

	// (Validation)
	if ( empty( $name ) || $monthly_salary < 0 ) {
		wp_send_json_error( [ 'message' => __( 'Please fill all required fields (Name, Salary).', RSAM_TEXT_DOMAIN ) ], 400 );
	}

	global $wpdb;
	$tables = rsam_get_table_names();

	$db_data = [
		'name'           => $name,
		'designation'    => $designation,
        'phone'          => $phone,
        'monthly_salary' => $monthly_salary,
        'joining_date'   => $joining_date,
        'is_active'      => $is_active,
	];
    
    $db_formats = ['%s', '%s', '%s', '%f', '%s', '%d'];

	// (Update) ya (Insert)
	if ( $employee_id > 0 ) {
		// Update
		$result = $wpdb->update(
			$tables['employees'],
			$db_data,
			[ 'id' => $employee_id ], // (WHERE)
			$db_formats,
			[ '%d' ] // (WHERE format)
		);
        $message = __( 'Employee updated successfully.', RSAM_TEXT_DOMAIN );
	} else {
		// Insert
		$result = $wpdb->insert(
			$tables['employees'],
			$db_data,
			$db_formats
		);
        $employee_id = $wpdb->insert_id;
        $message = __( 'Employee added successfully.', RSAM_TEXT_DOMAIN );
	}

	if ( $result === false ) {
		wp_send_json_error( [ 'message' => __( 'Database error. Could not save employee.', RSAM_TEXT_DOMAIN ) ], 500 );
	}

	wp_send_json_success( [ 'message' => $message, 'employee_id' => $employee_id ] );
}
add_action( 'wp_ajax_rsam_save_employee', 'rsam_ajax_save_employee' );


/**
 * (AJAX) Handler: (Employee) (delete) karne ke liye.
 */
function rsam_ajax_delete_employee() {
	// (Security) checks
	check_ajax_referer( 'rsam-ajax-nonce', 'nonce' );
	if ( ! current_user_can( 'rsam_manage_employees' ) ) {
		wp_send_json_error( [ 'message' => __( 'You do not have permission.', RSAM_TEXT_DOMAIN ) ], 403 );
	}

	$employee_id = isset( $_POST['employee_id'] ) ? absint( $_POST['employee_id'] ) : 0;

	if ( $employee_id <= 0 ) {
		wp_send_json_error( [ 'message' => __( 'Invalid Employee ID.', RSAM_TEXT_DOMAIN ) ], 400 );
	}

	global $wpdb;
	$tables = rsam_get_table_names();
    
    // Check karein ke (employee) (salary) (expenses) mein (linked) to nahi
    $expense_count = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(id) FROM {$tables['expenses']} WHERE employee_id = %d AND category = 'salary'",
        $employee_id
    ) );
    
    if ( $expense_count > 0 ) {
        wp_send_json_error( [ 'message' => __( 'Cannot delete. This employee has salary expense records. You can mark them as Inactive instead.', RSAM_TEXT_DOMAIN ) ], 400 );
    }

	$result = $wpdb->delete( $tables['employees'], [ 'id' => $employee_id ], [ '%d' ] );

	if ( $result ) {
		wp_send_json_success( [ 'message' => __( 'Employee deleted successfully.', RSAM_TEXT_DOMAIN ) ] );
	} else {
		wp_send_json_error( [ 'message' => __( 'Could not delete employee.', RSAM_TEXT_DOMAIN ) ], 500 );
	}
}
add_action( 'wp_ajax_rsam_delete_employee', 'rsam_ajax_delete_employee' );

/** Part 10 — Yahan khatam hua */
/**
 * Part 11 — Suppliers (Templates + AJAX)
 */

/**
 * Suppliers Screen ke liye (HTML <template>)
 */
function rsam_template_suppliers() {
	?>
	<template id="rsam-tmpl-suppliers">
		<div class="rsam-screen-header">
			<h1><?php _e( 'Suppliers', RSAM_TEXT_DOMAIN ); ?></h1>
			<button type="button" class="button button-primary" id="rsam-add-new-supplier">
				<span class="dashicons dashicons-plus-alt"></span>
				<?php _e( 'Add New Supplier', RSAM_TEXT_DOMAIN ); ?>
			</button>
		</div>

		<div class="rsam-list-controls">
			<input type="search" id="rsam-supplier-search" class="rsam-search-field" placeholder="<?php esc_attr_e( 'Search by name or phone...', RSAM_TEXT_DOMAIN ); ?>">
		</div>

		<div class="rsam-list-table-wrapper">
			<table class="rsam-list-table" id="rsam-suppliers-table">
				<thead>
					<tr>
						<th><?php _e( 'Name', RSAM_TEXT_DOMAIN ); ?></th>
						<th><?php _e( 'Phone', RSAM_TEXT_DOMAIN ); ?></th>
						<th><?php _e( 'Address', RSAM_TEXT_DOMAIN ); ?></th>
						<th><?php _e( 'Actions', RSAM_TEXT_DOMAIN ); ?></th>
					</tr>
				</thead>
				<tbody id="rsam-suppliers-table-body">
					<tr>
						<td colspan="4" class="rsam-list-loading">
							<span class="rsam-loader-spinner"></span> <?php _e( 'Loading suppliers...', RSAM_TEXT_DOMAIN ); ?>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<div class="rsam-pagination" id="rsam-suppliers-pagination">
			</div>

		<div id="rsam-supplier-form-container" style="display: none;">
			<form id="rsam-supplier-form" class="rsam-form">
				<input type="hidden" name="supplier_id" value="0">
				
                <div class.rsam-form-grid">
                    <div class="rsam-form-field">
                        <label for="rsam-supplier-name"><?php _e( 'Supplier Name', RSAM_TEXT_DOMAIN ); ?> <span class="rsam-required">*</span></label>
                        <input type="text" id="rsam-supplier-name" name="name" required>
                    </div>
                    <div class="rsam-form-field">
                        <label for="rsam-supplier-phone"><?php _e( 'Phone Number', RSAM_TEXT_DOMAIN ); ?></label>
                        <input type="tel" id="rsam-supplier-phone" name="phone">
                    </div>
                </div>

                <div class="rsam-form-field">
					<label for="rsam-supplier-address"><?php _e( 'Address', RSAM_TEXT_DOMAIN ); ?></label>
					<textarea id="rsam-supplier-address" name="address" rows="3"></textarea>
				</div>
			</form>
		</div>
	</template>
	<?php
}

/**
 * (AJAX) Handler: (Suppliers) ki list (fetch) karne ke liye.
 */
function rsam_ajax_get_suppliers() {
	// (Security) checks
	check_ajax_referer( 'rsam-ajax-nonce', 'nonce' );
    // (Purchases) (manage) karne walay (Suppliers) dekh sakte hain
	if ( ! current_user_can( 'rsam_manage_suppliers' ) && ! current_user_can( 'rsam_manage_purchases' ) ) { 
		wp_send_json_error( [ 'message' => __( 'You do not have permission.', RSAM_TEXT_DOMAIN ) ], 403 );
	}

	global $wpdb;
	$tables = rsam_get_table_names();

	// (Pagination) parameters
	$page = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
    // Agar (dropdown) ke liye (fetch) kar rahe hain to (limit) (-1) hogi (ya (search) hoga)
    $limit = isset( $_POST['limit'] ) ? absint( $_POST['limit'] ) : 20;
	$offset = ( $page - 1 ) * $limit;

	// (Search) parameter
	$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';

	// (Query) conditions
	$where_clause = '';
	$params = [];
	if ( ! empty( $search ) ) {
        $search_like = '%' . $wpdb->esc_like( $search ) . '%';
		$where_clause = ' WHERE (name LIKE %s OR phone LIKE %s)';
		$params[] = $search_like;
        $params[] = $search_like;
	}
    
    $limit_sql = '';
    if ( $limit > 0 ) {
        $limit_sql = 'LIMIT %d OFFSET %d';
        $params[] = $limit;
        $params[] = $offset;
    }

	// (Total items) (count) karein (sirf (pagination) ke liye)
    $total_items = 0;
    if ( $limit > 0 ) {
        $total_items = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(id) FROM {$tables['suppliers']} $where_clause",
            $params // (Limit/Offset) (parameters) ke bagher
        ) );
    }

	// (Suppliers) (fetch) karein
	$query = $wpdb->prepare(
		"SELECT * FROM {$tables['suppliers']}
		$where_clause
		ORDER BY name ASC
		$limit_sql",
		$params
	);

	$suppliers = $wpdb->get_results( $query );

	wp_send_json_success( [
		'suppliers'  => $suppliers,
		'pagination' => ( $limit > 0 ) ? [
			'total_items' => (int) $total_items,
			'total_pages' => ceil( $total_items / $limit ),
			'current_page' => $page,
		] : null,
	] );
}
add_action( 'wp_ajax_rsam_get_suppliers', 'rsam_ajax_get_suppliers' );


/**
 * (AJAX) Handler: Naya (Supplier) (save) ya (update) karne ke liye.
 */
function rsam_ajax_save_supplier() {
	// (Security) checks
	check_ajax_referer( 'rsam-ajax-nonce', 'nonce' );
	if ( ! current_user_can( 'rsam_manage_suppliers' ) && ! current_user_can( 'rsam_manage_purchases' ) ) {
		wp_send_json_error( [ 'message' => __( 'You do not have permission.', RSAM_TEXT_DOMAIN ) ], 403 );
	}

	// (POST data) (parse) karein
	parse_str( $_POST['form_data'], $data );

	// (Validate) aur (sanitize) karein
	$supplier_id = isset( $data['supplier_id'] ) ? absint( $data['supplier_id'] ) : 0;
	$name = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
    $phone = isset( $data['phone'] ) ? sanitize_text_field( $data['phone'] ) : '';
    $address = isset( $data['address'] ) ? sanitize_textarea_field( $data['address'] ) : '';

	// (Validation)
	if ( empty( $name ) ) {
		wp_send_json_error( [ 'message' => __( 'Please fill the required field (Name).', RSAM_TEXT_DOMAIN ) ], 400 );
	}

	global $wpdb;
	$tables = rsam_get_table_names();

	$db_data = [
		'name'    => $name,
        'phone'   => $phone,
        'address' => $address,
	];
    
    $db_formats = ['%s', '%s', '%s'];

	// (Update) ya (Insert)
	if ( $supplier_id > 0 ) {
		// Update
		$result = $wpdb->update(
			$tables['suppliers'],
			$db_data,
			[ 'id' => $supplier_id ], // (WHERE)
			$db_formats,
			[ '%d' ] // (WHERE format)
		);
        $message = __( 'Supplier updated successfully.', RSAM_TEXT_DOMAIN );
	} else {
		// Insert
        $db_data['created_at'] = current_time( 'mysql' );
        $db_formats[] = '%s';
        
		$result = $wpdb->insert(
			$tables['suppliers'],
			$db_data,
			$db_formats
		);
        $supplier_id = $wpdb->insert_id;
        $message = __( 'Supplier added successfully.', RSAM_TEXT_DOMAIN );
	}

	if ( $result === false ) {
		wp_send_json_error( [ 'message' => __( 'Database error. Could not save supplier.', RSAM_TEXT_DOMAIN ) ], 500 );
	}

    // (Quick Add) ke liye naya (supplier object) (return) karein
    $new_supplier = $wpdb->get_row( $wpdb->prepare( "SELECT id, name FROM {$tables['suppliers']} WHERE id = %d", $supplier_id ) );

	wp_send_json_success( [ 
        'message' => $message, 
        'supplier' => $new_supplier // (Dropdown) ko (update) karne ke liye
    ] );
}
add_action( 'wp_ajax_rsam_save_supplier', 'rsam_ajax_save_supplier' );


/**
 * (AJAX) Handler: (Supplier) (delete) karne ke liye.
 */
function rsam_ajax_delete_supplier() {
	// (Security) checks
	check_ajax_referer( 'rsam-ajax-nonce', 'nonce' );
	if ( ! current_user_can( 'rsam_manage_suppliers' ) ) {
		wp_send_json_error( [ 'message' => __( 'You do not have permission.', RSAM_TEXT_DOMAIN ) ], 403 );
	}

	$supplier_id = isset( $_POST['supplier_id'] ) ? absint( $_POST['supplier_id'] ) : 0;

	if ( $supplier_id <= 0 ) {
		wp_send_json_error( [ 'message' => __( 'Invalid Supplier ID.', RSAM_TEXT_DOMAIN ) ], 400 );
	}

	global $wpdb;
	$tables = rsam_get_table_names();
    
    // Check karein ke (supplier) (purchases) mein (linked) to nahi
    $purchase_count = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(id) FROM {$tables['purchases']} WHERE supplier_id = %d",
        $supplier_id
    ) );
    
    if ( $purchase_count > 0 ) {
        wp_send_json_error( [ 'message' => __( 'Cannot delete. This supplier is linked to existing purchases.', RSAM_TEXT_DOMAIN ) ], 400 );
    }

	$result = $wpdb->delete( $tables['suppliers'], [ 'id' => $supplier_id ], [ '%d' ] );

	if ( $result ) {
		wp_send_json_success( [ 'message' => __( 'Supplier deleted successfully.', RSAM_TEXT_DOMAIN ) ] );
	} else {
		wp_send_json_error( [ 'message' => __( 'Could not delete supplier.', RSAM_TEXT_DOMAIN ) ], 500 );
	}
}
add_action( 'wp_ajax_rsam_delete_supplier', 'rsam_ajax_delete_supplier' );

/** Part 11 — Yahan khatam hua */
/**
 * Part 12 — Customers (Khata) (Templates + AJAX)
 */

/**
 * Customers Screen ke liye (HTML <template>)
 */
function rsam_template_customers() {
	?>
	<template id="rsam-tmpl-customers">
		<div class="rsam-screen-header">
			<h1><?php _e( 'Customers (Khata Book)', RSAM_TEXT_DOMAIN ); ?></h1>
			<button type="button" class="button button-primary" id="rsam-add-new-customer">
				<span class="dashicons dashicons-plus-alt"></span>
				<?php _e( 'Add New Customer', RSAM_TEXT_DOMAIN ); ?>
			</button>
		</div>

		<div class="rsam-list-controls">
			<input type="search" id="rsam-customer-search" class="rsam-search-field" placeholder="<?php esc_attr_e( 'Search by name or phone...', RSAM_TEXT_DOMAIN ); ?>">
		</div>

		<div class="rsam-list-table-wrapper">
			<table class="rsam-list-table" id="rsam-customers-table">
				<thead>
					<tr>
						<th><?php _e( 'Name', RSAM_TEXT_DOMAIN ); ?></th>
						<th><?php _e( 'Phone', RSAM_TEXT_DOMAIN ); ?></th>
						<th><?php _e( 'Credit Balance (Dues)', RSAM_TEXT_DOMAIN ); ?></th>
						<th><?php _e( 'Actions', RSAM_TEXT_DOMAIN ); ?></th>
					</tr>
				</thead>
				<tbody id="rsam-customers-table-body">
					<tr>
						<td colspan="4" class="rsam-list-loading">
							<span class="rsam-loader-spinner"></span> <?php _e( 'Loading customers...', RSAM_TEXT_DOMAIN ); ?>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<div class="rsam-pagination" id="rsam-customers-pagination">
			</div>

		<div id="rsam-customer-form-container" style="display: none;">
			<form id="rsam-customer-form" class="rsam-form">
				<input type="hidden" name="customer_id" value="0">
				
                <div class="rsam-form-grid">
                    <div class="rsam-form-field">
                        <label for="rsam-customer-name"><?php _e( 'Customer Name', RSAM_TEXT_DOMAIN ); ?> <span class="rsam-required">*</span></label>
                        <input type="text" id="rsam-customer-name" name="name" required>
                    </div>
                    <div class="rsam-form-field">
                        <label for="rsam-customer-phone"><?php _e( 'Phone Number', RSAM_TEXT_DOMAIN ); ?></label>
                        <input type="tel" id="rsam-customer-phone" name="phone">
                    </div>
                </div>

                <div class="rsam-form-field">
					<label for="rsam-customer-address"><?php _e( 'Address', RSAM_TEXT_DOMAIN ); ?></label>
					<textarea id="rsam-customer-address" name="address" rows="3"></textarea>
				</div>
                <p class="rsam-form-note"><?php _e( 'Note: Credit balance is automatically updated from (Unpaid) Sales and (Payment) records.', RSAM_TEXT_DOMAIN ); ?></p>
			</form>
		</div>
        
        <div id="rsam-customer-payment-form-container" style="display: none;">
			<form id="rsam-customer-payment-form" class="rsam-form">
				<input type="hidden" name="customer_id" value="0">
                
                <h3 class="rsam-payment-customer-name"></h3>
                <p class="rsam-payment-current-balance"></p>

                <hr>
                
                <div class="rsam-form-grid">
                    <div class="rsam-form-field">
                        <label for="rsam-payment-amount"><?php _e( 'Payment Amount', RSAM_TEXT_DOMAIN ); ?> <span class="rsam-required">*</span></label>
                        <input type="number" id="rsam-payment-amount" name="amount" step="0.01" min="0.01" required>
                    </div>
                    <div class="rsam-form-field">
                        <label for="rsam-payment-date"><?php _e( 'Payment Date', RSAM_TEXT_DOMAIN ); ?> <span class="rsam-required">*</span></label>
                        <input type="date" id="rsam-payment-date" name="payment_date" required>
                    </div>
                </div>
                
                <div class="rsam-form-field">
					<label for="rsam-payment-notes"><?php _e( 'Notes / Remarks', RSAM_TEXT_DOMAIN ); ?></label>
					<textarea id="rsam-payment-notes" name="notes" rows="3"></textarea>
				</div>
			</form>
		</div>
	</template>
	<?php
}

/**
 * (AJAX) Handler: (Customers) ki list (fetch) karne ke liye.
 */
function rsam_ajax_get_customers() {
	// (Security) checks
	check_ajax_referer( 'rsam-ajax-nonce', 'nonce' );
    // (Sales) (manage) karne walay (Customers) dekh sakte hain
	if ( ! current_user_can( 'rsam_manage_customers' ) && ! current_user_can( 'rsam_manage_sales' ) ) { 
		wp_send_json_error( [ 'message' => __( 'You do not have permission.', RSAM_TEXT_DOMAIN ) ], 403 );
	}

	global $wpdb;
	$tables = rsam_get_table_names();

	// (Pagination) parameters
	$page = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
    $limit = isset( $_POST['limit'] ) ? absint( $_POST['limit'] ) : 20;
	$offset = ( $page - 1 ) * $limit;

	// (Search) parameter
	$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';

	// (Query) conditions
	$where_clause = '';
	$params = [];
	if ( ! empty( $search ) ) {
        $search_like = '%' . $wpdb->esc_like( $search ) . '%';
		$where_clause = ' WHERE (name LIKE %s OR phone LIKE %s)';
		$params[] = $search_like;
        $params[] = $search_like;
	}
    
    $limit_sql = '';
    if ( $limit > 0 ) {
        $limit_sql = 'LIMIT %d OFFSET %d';
        $params[] = $limit;
        $params[] = $offset;
    }

	// (Total items) (count) karein
    $total_items = 0;
    if ( $limit > 0 ) {
        $total_items = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(id) FROM {$tables['customers']} $where_clause",
            $params
        ) );
    }

	// (Customers) (fetch) karein
	$query = $wpdb->prepare(
		"SELECT * FROM {$tables['customers']}
		$where_clause
		ORDER BY name ASC
		$limit_sql",
		$params
	);
	$customers = $wpdb->get_results( $query );
    
    // (Data) ko (format) karein
    foreach ( $customers as $customer ) {
        $customer->credit_balance_formatted = rsam_format_price( $customer->credit_balance );
    }

	wp_send_json_success( [
		'customers'  => $customers,
		'pagination' => ( $limit > 0 ) ? [
			'total_items' => (int) $total_items,
			'total_pages' => ceil( $total_items / $limit ),
			'current_page' => $page,
		] : null,
	] );
}
add_action( 'wp_ajax_rsam_get_customers', 'rsam_ajax_get_customers' );


/**
 * (AJAX) Handler: Naya (Customer) (save) ya (update) karne ke liye.
 */
function rsam_ajax_save_customer() {
	// (Security) checks
	check_ajax_referer( 'rsam-ajax-nonce', 'nonce' );
	if ( ! current_user_can( 'rsam_manage_customers' ) && ! current_user_can( 'rsam_manage_sales' ) ) {
		wp_send_json_error( [ 'message' => __( 'You do not have permission.', RSAM_TEXT_DOMAIN ) ], 403 );
	}

	// (POST data) (parse) karein
	parse_str( $_POST['form_data'], $data );

	// (Validate) aur (sanitize) karein
	$customer_id = isset( $data['customer_id'] ) ? absint( $data['customer_id'] ) : 0;
	$name = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
    $phone = isset( $data['phone'] ) ? sanitize_text_field( $data['phone'] ) : '';
    $address = isset( $data['address'] ) ? sanitize_textarea_field( $data['address'] ) : '';

	// (Validation)
	if ( empty( $name ) ) {
		wp_send_json_error( [ 'message' => __( 'Please fill the required field (Name).', RSAM_TEXT_DOMAIN ) ], 400 );
	}

	global $wpdb;
	$tables = rsam_get_table_names();

	$db_data = [
		'name'    => $name,
        'phone'   => $phone,
        'address' => $address,
	];
    $db_formats = ['%s', '%s', '%s'];

	// (Update) ya (Insert)
	if ( $customer_id > 0 ) {
		// Update
		$result = $wpdb->update(
			$tables['customers'],
			$db_data,
			[ 'id' => $customer_id ], // (WHERE)
			$db_formats,
			[ '%d' ] // (WHERE format)
		);
        $message = __( 'Customer updated successfully.', RSAM_TEXT_DOMAIN );
	} else {
		// Insert
        $db_data['credit_balance'] = 0; // Naya (customer) 0 (balance) se shuru hoga
        $db_data['created_at'] = current_time( 'mysql' );
        $db_formats[] = '%f';
        $db_formats[] = '%s';
        
		$result = $wpdb->insert(
			$tables['customers'],
			$db_data,
			$db_formats
		);
        $customer_id = $wpdb->insert_id;
        $message = __( 'Customer added successfully.', RSAM_TEXT_DOMAIN );
	}

	if ( $result === false ) {
		wp_send_json_error( [ 'message' => __( 'Database error. Could not save customer.', RSAM_TEXT_DOMAIN ) ], 500 );
	}

    // (Quick Add) ke liye naya (customer object) (return) karein
    $new_customer = $wpdb->get_row( $wpdb->prepare( "SELECT id, name, credit_balance FROM {$tables['customers']} WHERE id = %d", $customer_id ) );

	wp_send_json_success( [ 
        'message' => $message, 
        'customer' => $new_customer // (Dropdown) ko (update) karne ke liye
    ] );
}
add_action( 'wp_ajax_rsam_save_customer', 'rsam_ajax_save_customer' );


/**
 * (AJAX) Handler: (Customer) (delete) karne ke liye.
 */
function rsam_ajax_delete_customer() {
	// (Security) checks
	check_ajax_referer( 'rsam-ajax-nonce', 'nonce' );
	if ( ! current_user_can( 'rsam_manage_customers' ) ) {
		wp_send_json_error( [ 'message' => __( 'You do not have permission.', RSAM_TEXT_DOMAIN ) ], 403 );
	}

	$customer_id = isset( $_POST['customer_id'] ) ? absint( $_POST['customer_id'] ) : 0;
	if ( $customer_id <= 0 ) {
		wp_send_json_error( [ 'message' => __( 'Invalid Customer ID.', RSAM_TEXT_DOMAIN ) ], 400 );
	}

	global $wpdb;
	$tables = rsam_get_table_names();
    
    // Check karein ke (customer) ka (balance) 0 hai
    $balance = $wpdb->get_var( $wpdb->prepare(
        "SELECT credit_balance FROM {$tables['customers']} WHERE id = %d",
        $customer_id
    ) );
    
    if ( (float) $balance > 0 ) {
        wp_send_json_error( [ 'message' => __( 'Cannot delete. This customer has an outstanding balance.', RSAM_TEXT_DOMAIN ) ], 400 );
    }

    // Check karein ke (customer) (sales) se (linked) to nahi
    $sales_count = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(id) FROM {$tables['sales']} WHERE customer_id = %d",
        $customer_id
    ) );
    
    if ( $sales_count > 0 ) {
        wp_send_json_error( [ 'message' => __( 'Cannot delete. This customer has sales history. You can hide them if needed (feature not implemented).', RSAM_TEXT_DOMAIN ) ], 400 );
    }

	$result = $wpdb->delete( $tables['customers'], [ 'id' => $customer_id ], [ '%d' ] );

	if ( $result ) {
		wp_send_json_success( [ 'message' => __( 'Customer deleted successfully.', RSAM_TEXT_DOMAIN ) ] );
	} else {
		wp_send_json_error( [ 'message' => __( 'Could not delete customer.', RSAM_TEXT_DOMAIN ) ], 500 );
	}
}
add_action( 'wp_ajax_rsam_delete_customer', 'rsam_ajax_delete_customer' );


/**
 * (AJAX) Handler: (Customer) se (payment) (wasool) karna.
 */
function rsam_ajax_record_customer_payment() {
	// (Security) checks
	check_ajax_referer( 'rsam-ajax-nonce', 'nonce' );
	if ( ! current_user_can( 'rsam_manage_customers' ) ) {
		wp_send_json_error( [ 'message' => __( 'You do not have permission.', RSAM_TEXT_DOMAIN ) ], 403 );
	}
    
    // (POST data) (parse) karein
	parse_str( $_POST['form_data'], $data );
    
    $customer_id = isset( $data['customer_id'] ) ? absint( $data['customer_id'] ) : 0;
    $amount = isset( $data['amount'] ) ? floatval( $data['amount'] ) : 0;
    $payment_date = isset( $data['payment_date'] ) ? sanitize_text_field( $data['payment_date'] ) : '';
    $notes = isset( $data['notes'] ) ? sanitize_textarea_field( $data['notes'] ) : '';

    if ( $customer_id <= 0 || $amount <= 0 || empty( $payment_date ) ) {
        wp_send_json_error( [ 'message' => __( 'Invalid data. Please check amount and date.', RSAM_TEXT_DOMAIN ) ], 400 );
    }

    global $wpdb;
	$tables = rsam_get_table_names();
    
    // (Transaction) shuru karein
    $wpdb->query( 'START TRANSACTION' );
    
    try {
        // 1. (Payment) ko (record) karein
        $payment_result = $wpdb->insert(
            $tables['customer_payments'],
            [
                'customer_id'  => $customer_id,
                'payment_date' => $payment_date,
                'amount'       => $amount,
                'notes'        => $notes,
                'created_at'   => current_time( 'mysql' ),
            ],
            [ '%d', '%s', '%f', '%s', '%s' ]
        );
        
        if ( ! $payment_result ) {
            throw new Exception( __( 'Failed to save payment record.', RSAM_TEXT_DOMAIN ) );
        }
        
        // 2. (Customer) ka (balance) (update) karein (kam karein)
        $balance_result = $wpdb->query( $wpdb->prepare(
            "UPDATE {$tables['customers']} SET credit_balance = credit_balance - %f WHERE id = %d",
            $amount,
            $customer_id
        ) );
        
        if ( $balance_result === false ) {
             throw new Exception( __( 'Failed to update customer balance.', RSAM_TEXT_DOMAIN ) );
        }
        
        // (Transaction) (Commit) karein
        $wpdb->query( 'COMMIT' );
        wp_send_json_success( [ 'message' => __( 'Payment recorded successfully. Customer balance updated.', RSAM_TEXT_DOMAIN ) ] );

    } catch ( Exception $e ) {
        // (Transaction) (Rollback) karein
        $wpdb->query( 'ROLLBACK' );
        wp_send_json_error( [ 'message' => $e->getMessage() ], 500 );
    }
}
add_action( 'wp_ajax_rsam_record_customer_payment', 'rsam_ajax_record_customer_payment' );

/** Part 12 — Yahan khatam hua */
/**
 * Part 13 — Reports (Templates + AJAX)
 */

/**
 * Reports Screen ke liye (HTML <template>)
 */
function rsam_template_reports() {
	?>
	<template id="rsam-tmpl-reports">
		<div class.rsam-screen-header">
			<h1><?php _e( 'Reports', RSAM_TEXT_DOMAIN ); ?></h1>
		</div>

		<div class="rsam-card rsam-report-controls">
            <form id="rsam-report-form" class="rsam-form">
                <div class="rsam-form-grid-4col">
                    <div class="rsam-form-field">
                        <label for="rsam-report-type"><?php _e( 'Report Type', RSAM_TEXT_DOMAIN ); ?></label>
                        <select id="rsam-report-type" name="report_type">
                            <option value=""><?php _e( 'Select a report', RSAM_TEXT_DOMAIN ); ?></option>
                            <option value="pnl"><?php _e( 'Profit & Loss Statement', RSAM_TEXT_DOMAIN ); ?></option>
                            <option value="sales"><?php _e( 'Sales Summary', RSAM_TEXT_DOMAIN ); ?></option>
                            <option value="expenses"><?php _e( 'Expense Breakdown', RSAM_TEXT_DOMAIN ); ?></option>
                            <option value="stock"><?php _e( 'Inventory Valuation', RSAM_TEXT_DOMAIN ); ?></option>
                            <option value="customer_ledger"><?php _e( 'Customer Ledger (Khata)', RSAM_TEXT_DOMAIN ); ?></option>
                        </select>
                    </div>
                    <div class="rsam-form-field">
                        <label for="rsam-report-start-date"><?php _e( 'Start Date', RSAM_TEXT_DOMAIN ); ?></label>
                        <input type="date" id="rsam-report-start-date" name="start_date">
                    </div>
                    <div class="rsam-form-field">
                        <label for="rsam-report-end-date"><?php _e( 'End Date', RSAM_TEXT_DOMAIN ); ?></label>
                        <input type="date" id="rsam-report-end-date" name="end_date">
                    </div>
                    <div class="rsam-form-field" id="rsam-report-customer-field" style="display: none;">
                        <label for="rsam-report-customer-id"><?php _e( 'Customer', RSAM_TEXT_DOMAIN ); ?></label>
                        <select id="rsam-report-customer-id" name="customer_id" class="rsam-customer-search-select">
                            </select>
                    </div>
                    <div class="rsam-form-field rsam-report-action">
                        <button type="submit" class="button button-primary" id="rsam-generate-report">
                            <span class="rsam-btn-text"><?php _e( 'Generate Report', RSAM_TEXT_DOMAIN ); ?></span>
                            <span class="rsam-loader-spinner"></span>
                        </button>
                    </div>
                </div>
            </form>
		</div>

		<div id="rsam-report-results-wrapper">
            <div class="rsam-card rsam-report-placeholder">
                <p><?php _e( 'Please select a report type and date range to generate a report.', RSAM_TEXT_DOMAIN ); ?></p>
            </div>
            </div>

	</template>
	<?php
}

/**
 * (AJAX) Handler: (Report) (generate) karne ke liye.
 * Yeh (handler) (report type) ke hisab se (data) (fetch) karega.
 */
function rsam_ajax_generate_report() {
	// (Security) checks
	check_ajax_referer( 'rsam-ajax-nonce', 'nonce' );
	if ( ! current_user_can( 'rsam_view_reports' ) ) {
		wp_send_json_error( [ 'message' => __( 'You do not have permission.', RSAM_TEXT_DOMAIN ) ], 403 );
	}

    // (Inputs)
    $report_type = isset( $_POST['report_type'] ) ? sanitize_key( $_POST['report_type'] ) : '';
    $start_date = isset( $_POST['start_date'] ) && ! empty( $_POST['start_date'] ) ? sanitize_text_field( $_POST['start_date'] ) : wp_date( 'Y-m-01' ); // (Default: Month start)
    $end_date = isset( $_POST['end_date'] ) && ! empty( $_POST['end_date'] ) ? sanitize_text_field( $_POST['end_date'] ) : wp_date( 'Y-m-d' ); // (Default: Today)
    $customer_id = isset( $_POST['customer_id'] ) ? absint( $_POST['customer_id'] ) : 0;
    
    // (Dates) ko (SQL) (format) mein (adjust) karein (time ke sath)
    $start_datetime = $start_date . ' 00:00:00';
    $end_datetime = $end_date . ' 23:59:59';
    
    global $wpdb;
	$tables = rsam_get_table_names();
    $data = [
        'report_type' => $report_type,
        'start_date'  => $start_date,
        'end_date'    => $end_date,
    ];

    switch ( $report_type ) {
        case 'pnl':
            // 1. (Revenue) (Total Sales)
            $total_sales = $wpdb->get_var( $wpdb->prepare(
                "SELECT SUM(total_amount) FROM {$tables['sales']} WHERE sale_date BETWEEN %s AND %s",
                $start_datetime, $end_datetime
            ) );
            
            // 2. (COGS) (Cost of Goods Sold)
            $total_cogs = $wpdb->get_var( $wpdb->prepare(
                "SELECT SUM(total_cost) FROM {$tables['sales']} WHERE sale_date BETWEEN %s AND %s",
                $start_datetime, $end_datetime
            ) );
            
            // 3. (Expenses)
            $total_expenses = $wpdb->get_var( $wpdb->prepare(
                "SELECT SUM(amount) FROM {$tables['expenses']} WHERE expense_date BETWEEN %s AND %s",
                $start_date, $end_date // (Expense date) (column) (DATE) hai
            ) );
            
            // 4. (Expense Breakdown)
            $expense_breakdown = $wpdb->get_results( $wpdb->prepare(
                "SELECT category, SUM(amount) as total 
                 FROM {$tables['expenses']} 
                 WHERE expense_date BETWEEN %s AND %s 
                 GROUP BY category",
                $start_date, $end_date
            ) );
            
            $gross_profit = (float) $total_sales - (float) $total_cogs;
            $net_profit = $gross_profit - (float) $total_expenses;

            $data['pnl'] = [
                'total_sales'    => (float) $total_sales,
                'total_cogs'     => (float) $total_cogs,
                'gross_profit'   => $gross_profit,
                'total_expenses' => (float) $total_expenses,
                'net_profit'     => $net_profit,
                'expense_breakdown' => $expense_breakdown,
            ];
            break;
            
        case 'sales':
            // (Sales Summary)
            $sales_summary = $wpdb->get_row( $wpdb->prepare(
                "SELECT 
                    SUM(total_amount) as total_sales,
                    SUM(total_profit) as total_profit,
                    SUM(discount_amount) as total_discount,
                    COUNT(id) as total_transactions
                 FROM {$tables['sales']} 
                 WHERE sale_date BETWEEN %s AND %s",
                $start_datetime, $end_datetime
            ), ARRAY_A );
            
            // (Top Selling Products)
            $top_products = $wpdb->get_results( $wpdb->prepare(
                "SELECT 
                    p.name, 
                    p.unit_type,
                    SUM(si.quantity) as total_quantity,
                    SUM(si.quantity * si.selling_price) as total_revenue
                 FROM {$tables['sale_items']} si
                 JOIN {$tables['sales']} s ON s.id = si.sale_id
                 JOIN {$tables['products']} p ON p.id = si.product_id
                 WHERE s.sale_date BETWEEN %s AND %s
                 GROUP BY si.product_id
                 ORDER BY total_revenue DESC
                 LIMIT 10",
                 $start_datetime, $end_datetime
            ) );
            
            $data['sales_summary'] = $sales_summary;
            $data['top_products'] = $top_products;
            break;
            
        case 'expenses':
            // (Expense Breakdown)
            $total_expenses = $wpdb->get_var( $wpdb->prepare(
                "SELECT SUM(amount) FROM {$tables['expenses']} WHERE expense_date BETWEEN %s AND %s",
                $start_date, $end_date
            ) );
            
            $expense_breakdown = $wpdb->get_results( $wpdb->prepare(
                "SELECT category, SUM(amount) as total 
                 FROM {$tables['expenses']} 
                 WHERE expense_date BETWEEN %s AND %s 
                 GROUP BY category
                 ORDER BY total DESC",
                $start_date, $end_date
            ) );
            
            $data['expenses_summary'] = [
                'total_expenses' => (float) $total_expenses,
                'expense_breakdown' => $expense_breakdown,
            ];
            break;
            
        case 'stock':
            // (Inventory Valuation Report)
            // Yeh (real-time) hota hai, (date range) par (depend) nahi karta
            
            // (Total Stock Value) (Cost Price par)
            $stock_value = $wpdb->get_var(
                "SELECT SUM(cost_price * quantity_in_stock) 
                 FROM {$tables['product_batches']} 
                 WHERE quantity_in_stock > 0"
            );
            
            // (Stock Value by Category)
            $value_by_category = $wpdb->get_results(
                "SELECT 
                    p.category, 
                    SUM(b.cost_price * b.quantity_in_stock) as total_cost_value
                 FROM {$tables['product_batches']} b
                 JOIN {$tables['products']} p ON p.id = b.product_id
                 WHERE b.quantity_in_stock > 0
                 GROUP BY p.category
                 ORDER BY total_cost_value DESC"
            );
            
            // (Low Stock Items)
            $low_stock_products = $wpdb->get_results(
                "SELECT name, stock_quantity, low_stock_threshold 
                 FROM {$tables['products']} 
                 WHERE stock_quantity <= low_stock_threshold AND low_stock_threshold > 0
                 ORDER BY (stock_quantity - low_stock_threshold) ASC"
            );
            
            $data['stock_summary'] = [
                'total_stock_value' => (float) $stock_value,
                'value_by_category' => $value_by_category,
                'low_stock_products' => $low_stock_products,
            ];
            break;
            
        case 'customer_ledger':
            if ( $customer_id <= 0 ) {
                wp_send_json_error( [ 'message' => __( 'Please select a customer.', RSAM_TEXT_DOMAIN ) ], 400 );
            }
            
            $customer = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tables['customers']} WHERE id = %d", $customer_id ) );
            
            // (Ledger) (data) (fetch) karein
            // (UNION) (Sales) (debit) aur (Payments) (credit)
            
            // (Sales)
            $sales_query = $wpdb->prepare(
                "SELECT id, sale_date as date, total_amount as debit, 0 as credit, 'Sale' as type, notes
                 FROM {$tables['sales']} 
                 WHERE customer_id = %d AND payment_status = 'unpaid' 
                 AND sale_date BETWEEN %s AND %s",
                $customer_id, $start_datetime, $end_datetime
            );
            
            // (Payments)
            $payments_query = $wpdb->prepare(
                "SELECT id, payment_date as date, 0 as debit, amount as credit, 'Payment' as type, notes
                 FROM {$tables['customer_payments']} 
                 WHERE customer_id = %d
                 AND payment_date BETWEEN %s AND %s",
                 $customer_id, $start_date, $end_date
            );
            
            $ledger_entries = $wpdb->get_results( "$sales_query UNION ALL $payments_query ORDER BY date ASC" );
            
            $data['customer_ledger'] = [
                'customer' => $customer,
                'entries'  => $ledger_entries,
            ];
            break;

        default:
             wp_send_json_error( [ 'message' => __( 'Invalid report type selected.', RSAM_TEXT_DOMAIN ) ], 400 );
    }

	wp_send_json_success( $data );
}
add_action( 'wp_ajax_rsam_generate_report', 'rsam_ajax_generate_report' );

/** Part 13 — Yahan khatam hua */
/**
 * Part 14 — Settings (Templates + AJAX)
 */

/**
 * Settings Screen ke liye (HTML <template>)
 */
function rsam_template_settings() {
	?>
	<template id="rsam-tmpl-settings">
		<div class="rsam-screen-header">
			<h1><?php _e( 'Settings', RSAM_TEXT_DOMAIN ); ?></h1>
		</div>

		<div class="rsam-card rsam-settings-form-wrapper">
            <form id="rsam-settings-form" class="rsam-form">
                
                <div id="rsam-settings-loader" style="display: none;">
                    <p class="rsam-list-loading">
                        <span class="rsam-loader-spinner"></span> <?php _e( 'Loading settings...', RSAM_TEXT_DOMAIN ); ?>
                    </p>
                </div>

                <div id="rsam-settings-fields" style="display: none;">
                    <h3><?php _e( 'Shop Details', RSAM_TEXT_DOMAIN ); ?></h3>
                    <div class="rsam-form-grid">
                        <div class="rsam-form-field">
                            <label for="rsam-setting-shop-name"><?php _e( 'Shop Name', RSAM_TEXT_DOMAIN ); ?></label>
                            <input type="text" id="rsam-setting-shop-name" name="shop_name" placeholder="<?php esc_attr_e( 'My Grocery Store', RSAM_TEXT_DOMAIN ); ?>">
                        </div>
                        <div class="rsam-form-field">
                            <label for="rsam-setting-currency-symbol"><?php _e( 'Currency Symbol', RSAM_TEXT_DOMAIN ); ?></label>
                            <input type="text" id="rsam-setting-currency-symbol" name="currency_symbol" value="<?php esc_attr_e( 'Rs.', RSAM_TEXT_DOMAIN ); ?>">
                            <p class="rsam-form-note"><?php _e( 'Note: Changing symbol only affects display. (Rs.) is currently hardcoded.', RSAM_TEXT_DOMAIN ); ?></p>
                        </div>
                    </div>
                    
                    <hr>

                    <h3><?php _e( 'Inventory Settings', RSAM_TEXT_DOMAIN ); ?></h3>
                    <div class="rsam-form-field">
                        <label>
                            <input type="checkbox" name="enable_low_stock_alerts" id="rsam-setting-low-stock" value="1">
                            <?php _e( 'Enable Low Stock Alerts on Dashboard', RSAM_TEXT_DOMAIN ); ?>
                        </label>
                    </div>

                    <hr>
                    
                    <h3><?php _e( 'Tax Settings (Optional)', RSAM_TEXT_DOMAIN ); ?></h3>
                     <div class="rsam-form-field">
                        <label>
                            <input type="checkbox" name="enable_gst" id="rsam-setting-enable-gst" value="1" disabled>
                            <?php _e( 'Enable GST/VAT (Feature not yet implemented)', RSAM_TEXT_DOMAIN ); ?>
                        </label>
                    </div>

                    <div class="rsam-form-actions">
                        <button type="submit" class="button button-primary" id="rsam-save-settings-form">
                            <span class="rsam-btn-text"><?php _e( 'Save Settings', RSAM_TEXT_DOMAIN ); ?></span>
                            <span class="rsam-loader-spinner"></span>
                        </button>
                    </div>
                </div>

            </form>
		</div>

	</template>
	<?php
}

/**
 * (AJAX) Handler: (Settings) (get) karne ke liye.
 * (Settings) (WordPress options table) mein (save) ki jayengi.
 */
function rsam_ajax_get_settings() {
	// (Security) checks
	check_ajax_referer( 'rsam-ajax-nonce', 'nonce' );
	if ( ! current_user_can( 'rsam_manage_settings' ) ) {
		wp_send_json_error( [ 'message' => __( 'You do not have permission.', RSAM_TEXT_DOMAIN ) ], 403 );
	}

    // (Default) (settings)
    $defaults = [
        'shop_name' => get_bloginfo( 'name' ) . ' ' . __( 'Store', RSAM_TEXT_DOMAIN ),
        'currency_symbol' => __( 'Rs.', RSAM_TEXT_DOMAIN ),
        'enable_low_stock_alerts' => '1',
        'enable_gst' => '0',
    ];

    // (Database) se (settings) (load) karein
    $settings = get_option( 'rsam_settings', $defaults );
    // (Defaults) ke sath (merge) karein taake naye (options) bhi shamil ho jayein
    $settings = wp_parse_args( $settings, $defaults );

	wp_send_json_success( $settings );
}
add_action( 'wp_ajax_rsam_get_settings', 'rsam_ajax_get_settings' );


/**
 * (AJAX) Handler: (Settings) (save) karne ke liye.
 */
function rsam_ajax_save_settings() {
	// (Security) checks
	check_ajax_referer( 'rsam-ajax-nonce', 'nonce' );
	if ( ! current_user_can( 'rsam_manage_settings' ) ) {
		wp_send_json_error( [ 'message' => __( 'You do not have permission.', RSAM_TEXT_DOMAIN ) ], 403 );
	}

	// (POST data) (parse) karein
	parse_str( $_POST['form_data'], $data );

    // (Current settings) (load) karein
    $settings = get_option( 'rsam_settings', [] );

    // (Data) ko (Sanitize) aur (Update) karein
    if ( isset( $data['shop_name'] ) ) {
        $settings['shop_name'] = sanitize_text_field( $data['shop_name'] );
    }
    if ( isset( $data['currency_symbol'] ) ) {
        $settings['currency_symbol'] = sanitize_text_field( $data['currency_symbol'] );
    }
    
    // (Checkboxes)
    $settings['enable_low_stock_alerts'] = isset( $data['enable_low_stock_alerts'] ) ? '1' : '0';
    // $settings['enable_gst'] = isset( $data['enable_gst'] ) ? '1' : '0'; // (Abhi (disabled) hai)

	// (Database) mein (update) karein
    update_option( 'rsam_settings', $settings );

	wp_send_json_success( [ 'message' => __( 'Settings saved successfully.', RSAM_TEXT_DOMAIN ) ] );
}
add_action( 'wp_ajax_rsam_save_settings', 'rsam_ajax_save_settings' );

/** Part 14 — Yahan khatam hua */

/**
 * ===================================================================
 * === PHASE 1 (PHP) MUKAMMAL HUA (COMPLETED) ===
 * ===================================================================
 * Hum ne tamam (Menus), (Screens), (HTML Templates), (Database Tables), 
 * (Roles), aur (AJAX Handlers) (PHP) file mein mukammal kar liye hain.
 * ===================================================================
 */
