<?php
/**
 * Plugin Name: TCUK All In One Migrator
 * Plugin URI: https://aiomigrator.com/
 * Description: Local ↔ remote migration toolkit with selective component sync, GitHub theme pull, and backup/restore workflows.
 * Version: 1.0.9
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

define( 'TCUK_MIGRATOR_VERSION', '1.0.9' );
define( 'TCUK_MIGRATOR_FILE', __FILE__ );
define( 'TCUK_MIGRATOR_DIR', plugin_dir_path( __FILE__ ) );
define( 'TCUK_MIGRATOR_URL', plugin_dir_url( __FILE__ ) );

require_once TCUK_MIGRATOR_DIR . 'includes/class-tcuk-migrator.php';

TCUK_Migrator::instance();
