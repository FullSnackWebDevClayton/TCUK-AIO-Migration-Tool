<?php
/**
 * Plugin Name: TCUK All In One Migrator
 * Plugin URI: https://aiomigrator.com/
 * Description: Local ↔ remote migration toolkit with selective component sync, GitHub theme pull, and backup/restore workflows.
 * Version: 1.2.0
 * Author: TCUK
 * Author URI: https://techcentreuk.co.uk
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: tcuk-all-in-one-migrator
 * Update URI: https://github.com/FullSnackWebDevClayton/TCUK-AIO-Migration-Tool
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'TCUK_MIGRATOR_VERSION', '1.2.0' );
define( 'TCUK_MIGRATOR_FILE', __FILE__ );
define( 'TCUK_MIGRATOR_DIR', plugin_dir_path( __FILE__ ) );
define( 'TCUK_MIGRATOR_URL', plugin_dir_url( __FILE__ ) );

require_once TCUK_MIGRATOR_DIR . 'includes/class-tcuk-migrator.php';

// Load AJAX handler
require_once plugin_dir_path(__FILE__) . 'includes/class-tcuk-migrator-ajax.php';
add_action('admin_enqueue_scripts', 'tcuk_migrator_enqueue_admin_assets');
function tcuk_migrator_enqueue_admin_assets() {
    wp_enqueue_script('tcuk-migrator-admin', plugins_url('assets/js/admin.js', __FILE__), ['jquery'], TCUK_MIGRATOR_VERSION, true);
    wp_localize_script('tcuk-migrator-admin', 'tcukMigratorAjax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('tcuk_migrator_nonce'),
    ]);
    wp_enqueue_style('tcuk-migrator-admin', plugins_url('assets/css/admin.css', __FILE__), [], TCUK_MIGRATOR_VERSION);
}

// Register AJAX actions
add_action('init', ['TCUK_Migrator_Ajax', 'init']);
TCUK_Migrator::instance();
