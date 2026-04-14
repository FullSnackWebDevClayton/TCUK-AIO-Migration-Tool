<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TCUK_Migrator {
    private static $instance = null;

    public $filesystem;
    public $database;
    public $remote_api;
    public $github_sync;
    public $backup_manager;
    public $license;
    public $updater;
    public $admin;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        $this->load_dependencies();

        $this->filesystem     = new TCUK_Migrator_Filesystem();
        $this->database       = new TCUK_Migrator_Database();
        $this->license        = new TCUK_Migrator_License();
        $this->remote_api     = new TCUK_Migrator_Remote_API( $this );
        $this->github_sync    = new TCUK_Migrator_GitHub_Sync( $this->filesystem );
        $this->backup_manager = new TCUK_Migrator_Backup_Manager( $this->filesystem, $this->database, $this->license );
        $this->updater        = new TCUK_Migrator_Updater( TCUK_MIGRATOR_FILE );
        $this->admin          = new TCUK_Migrator_Admin( $this );
    }

    private function load_dependencies() {
        require_once TCUK_MIGRATOR_DIR . 'includes/class-tcuk-migrator-filesystem.php';
        require_once TCUK_MIGRATOR_DIR . 'includes/class-tcuk-migrator-database.php';
        require_once TCUK_MIGRATOR_DIR . 'includes/class-tcuk-migrator-license.php';
        require_once TCUK_MIGRATOR_DIR . 'includes/class-tcuk-migrator-remote-api.php';
        require_once TCUK_MIGRATOR_DIR . 'includes/class-tcuk-migrator-github-sync.php';
        require_once TCUK_MIGRATOR_DIR . 'includes/class-tcuk-migrator-backup-manager.php';
        require_once TCUK_MIGRATOR_DIR . 'includes/class-tcuk-migrator-updater.php';
        require_once TCUK_MIGRATOR_DIR . 'includes/class-tcuk-migrator-admin.php';
    }
}
