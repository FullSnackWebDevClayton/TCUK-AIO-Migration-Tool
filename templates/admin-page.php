<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$active_theme      = wp_get_theme()->get_stylesheet();

$github_theme_value = '' !== $settings['github_theme_slug'] ? $settings['github_theme_slug'] : $active_theme;
$backup_count       = is_array( $backups ) ? count( $backups ) : 0;
?>
<div
    class="wrap tcuk-migrator-wrap"
    data-active-theme="<?php echo esc_attr( $active_theme ); ?>"
>
    <div class="tcuk-progress tcuk-hidden" id="tcuk-progress" role="status" aria-live="polite" aria-atomic="true">
        <div class="tcuk-progress-track">
            <span class="tcuk-progress-fill" id="tcuk-progress-fill"></span>
        </div>
        <p class="tcuk-progress-text" id="tcuk-progress-text"><?php esc_html_e( 'Working…', 'tcuk-all-in-one-migrator' ); ?></p>
    </div>

    <div class="tcuk-page-head">
        <h1><?php esc_html_e( 'TCUK All In One Migrator', 'tcuk-all-in-one-migrator' ); ?></h1>
        <p><?php esc_html_e( 'API Push migration, GitHub theme pull, and backup/restore from one dashboard.', 'tcuk-all-in-one-migrator' ); ?></p>
        <div class="tcuk-mode-controls">
            <p class="tcuk-inline-form tcuk-wizard-restore-wrap tcuk-hidden" id="tcuk-wizard-restore-wrap">
                <button type="button" class="button" id="tcuk-wizard-restore"><?php esc_html_e( 'Show Setup Wizard', 'tcuk-all-in-one-migrator' ); ?></button>
            </p>
        </div>
    </div>

    <div class="tcuk-summary-strip">
        <div class="tcuk-summary-item">
            <span class="tcuk-summary-label"><?php esc_html_e( 'License', 'tcuk-all-in-one-migrator' ); ?></span>
            <?php if ( ! empty( $is_premium ) ) : ?>
                <span class="tcuk-summary-badge tcuk-badge-premium"><?php esc_html_e( 'Premium', 'tcuk-all-in-one-migrator' ); ?></span>
            <?php else : ?>
                <span class="tcuk-summary-badge tcuk-badge-free"><?php esc_html_e( 'Free', 'tcuk-all-in-one-migrator' ); ?></span>
            <?php endif; ?>
        </div>
        <div class="tcuk-summary-item">
            <span class="tcuk-summary-label"><?php esc_html_e( 'Active Theme', 'tcuk-all-in-one-migrator' ); ?></span>
            <span class="tcuk-summary-value"><?php echo esc_html( $active_theme ); ?></span>
        </div>
        <div class="tcuk-summary-item">
            <span class="tcuk-summary-label"><?php esc_html_e( 'Stored Backups', 'tcuk-all-in-one-migrator' ); ?></span>
            <span class="tcuk-summary-value"><?php echo esc_html( (string) $backup_count ); ?></span>
        </div>
    </div>

    <?php if ( ! empty( $result ) ) : ?>
        <div class="notice <?php echo $result['success'] ? 'notice-success' : 'notice-error'; ?> is-dismissible">
            <?php foreach ( (array) $result['messages'] as $message ) : ?>
                <p><?php echo esc_html( $message ); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="notice <?php echo ! empty( $is_premium ) ? 'notice-success' : 'notice-warning'; ?>">
        <p>
            <?php if ( ! empty( $is_premium ) ) : ?>
                <?php esc_html_e( '', 'tcuk-all-in-one-migrator' ); ?>
            <?php else : ?>
                <?php esc_html_e( 'Free mode is active: only file-based Theme backup and Theme restore are available. All other features require premium.', 'tcuk-all-in-one-migrator' ); ?>
            <?php endif; ?>
            <?php if ( ! empty( $license_status['message'] ) ) : ?>
                <?php echo ' ' . esc_html( $license_status['message'] ); ?>
            <?php endif; ?>
        </p>
    </div>

    <section class="tcuk-card tcuk-card-wide tcuk-license-card">
        <h2><?php esc_html_e( 'Premium License', 'tcuk-all-in-one-migrator' ); ?></h2>
        <p class="description"><?php esc_html_e( 'Enter your license key to unlock all migration features.', 'tcuk-all-in-one-migrator' ); ?></p>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tcuk-compact-form">
            <input type="hidden" name="action" value="tcuk_migrator_save_settings">
            <input type="hidden" name="settings_scope" value="license">
            <?php wp_nonce_field( 'tcuk_migrator_save_settings' ); ?>
            <p class="tcuk-field"><label><?php esc_html_e( 'License Key', 'tcuk-all-in-one-migrator' ); ?></label><input type="text" name="premium_license_key" value="<?php echo esc_attr( $settings['premium_license_key'] ); ?>" class="widefat" placeholder="lic_xxxxxxxxx"></p>
            <p><button class="button button-primary tcuk-submit" type="submit"><?php esc_html_e( 'Activate License', 'tcuk-all-in-one-migrator' ); ?></button></p>
        </form>
    </section>

    <section class="tcuk-card tcuk-card-wide tcuk-wizard-card" id="tcuk-setup-wizard-card">
        <div class="tcuk-wizard-title-row">
            <h2><?php esc_html_e( 'Setup Wizard', 'tcuk-all-in-one-migrator' ); ?></h2>
            <button type="button" class="button-link tcuk-wizard-close" id="tcuk-wizard-close" aria-label="<?php esc_attr_e( 'Close setup wizard card', 'tcuk-all-in-one-migrator' ); ?>">&times;</button>
        </div>
        <p class="description"><?php esc_html_e( 'Runs production preflight checks for server capabilities, backup access, and API Push connectivity (including destination URL/key probe when configured). Run this before first migration.', 'tcuk-all-in-one-migrator' ); ?></p>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tcuk-compact-form tcuk-compact-form-first">
            <input type="hidden" name="action" value="tcuk_migrator_setup_wizard">
            <?php wp_nonce_field( 'tcuk_migrator_setup_wizard' ); ?>
            <p><button class="button button-primary tcuk-submit" type="submit"><?php esc_html_e( 'Run Setup Wizard Checks', 'tcuk-all-in-one-migrator' ); ?></button></p>
        </form>

        <?php if ( ! empty( $wizard_report['checks'] ) ) : ?>
            <div class="tcuk-wizard-meta">
                <span class="tcuk-wizard-count tcuk-wizard-pass"><?php echo esc_html( sprintf( __( '%d pass', 'tcuk-all-in-one-migrator' ), (int) ( $wizard_report['counts']['pass'] ?? 0 ) ) ); ?></span>
                <span class="tcuk-wizard-count tcuk-wizard-warning"><?php echo esc_html( sprintf( __( '%d warning', 'tcuk-all-in-one-migrator' ), (int) ( $wizard_report['counts']['warning'] ?? 0 ) ) ); ?></span>
                <span class="tcuk-wizard-count tcuk-wizard-fail"><?php echo esc_html( sprintf( __( '%d fail', 'tcuk-all-in-one-migrator' ), (int) ( $wizard_report['counts']['fail'] ?? 0 ) ) ); ?></span>
                <?php if ( ! empty( $wizard_report['generated_at'] ) ) : ?>
                    <span class="tcuk-wizard-generated"><?php echo esc_html( sprintf( __( 'Last run: %s', 'tcuk-all-in-one-migrator' ), wp_date( 'Y-m-d H:i:s', (int) $wizard_report['generated_at'] ) ) ); ?></span>
                <?php endif; ?>
            </div>

            <div class="tcuk-wizard-list">
                <?php foreach ( (array) $wizard_report['checks'] as $check ) : ?>
                    <div class="tcuk-wizard-row tcuk-wizard-row-<?php echo esc_attr( $check['status'] ?? 'warning' ); ?>">
                        <div class="tcuk-wizard-head">
                            <strong><?php echo esc_html( $check['label'] ?? '' ); ?></strong>
                            <span class="tcuk-wizard-badge tcuk-wizard-badge-<?php echo esc_attr( $check['status'] ?? 'warning' ); ?>"><?php echo esc_html( strtoupper( (string) ( $check['status'] ?? 'warning' ) ) ); ?></span>
                        </div>
                        <?php if ( ! empty( $check['detail'] ) ) : ?>
                            <p class="tcuk-wizard-detail"><?php echo esc_html( $check['detail'] ); ?></p>
                        <?php endif; ?>
                        <?php if ( ! empty( $check['action'] ) ) : ?>
                            <p class="tcuk-wizard-action"><?php echo esc_html( $check['action'] ); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <div class="tcuk-grid">
        <section class="tcuk-card tcuk-card-wide">
            <h2><?php esc_html_e( 'Connection Settings', 'tcuk-all-in-one-migrator' ); ?></h2>
            <p class="description"><?php esc_html_e( 'Save once, then use these defaults across API Push and GitHub actions.', 'tcuk-all-in-one-migrator' ); ?></p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="tcuk_migrator_save_settings">
                <input type="hidden" name="settings_scope" value="full">
                <?php wp_nonce_field( 'tcuk_migrator_save_settings' ); ?>

                <div class="tcuk-toolbar">
                    <button type="button" class="button tcuk-fill-active-theme"><?php esc_html_e( 'Use Active Theme Slug', 'tcuk-all-in-one-migrator' ); ?></button>
                </div>

                <h3 class="tcuk-section-title"><?php esc_html_e( 'GitHub', 'tcuk-all-in-one-migrator' ); ?></h3>
                <p class="tcuk-field"><label><?php esc_html_e( 'Repository (owner/repo)', 'tcuk-all-in-one-migrator' ); ?></label><input type="text" name="github_repo" value="<?php echo esc_attr( $settings['github_repo'] ); ?>" class="widefat" placeholder="owner/repo"></p>
                <p class="description"><?php esc_html_e( 'Use token only for private repositories or to increase GitHub API rate limits.', 'tcuk-all-in-one-migrator' ); ?></p>
                <div class="tcuk-cols-2">
                    <p class="tcuk-field"><label><?php esc_html_e( 'Branch', 'tcuk-all-in-one-migrator' ); ?></label><input type="text" name="github_branch" value="<?php echo esc_attr( $settings['github_branch'] ); ?>" class="widefat"></p>
                    <p class="tcuk-field"><label><?php esc_html_e( 'Theme Folder Slug', 'tcuk-all-in-one-migrator' ); ?></label><input type="text" name="github_theme_slug" value="<?php echo esc_attr( $github_theme_value ); ?>" class="widefat tcuk-theme-slug-field"></p>
                    <p class="tcuk-field"><label><?php esc_html_e( 'Token / PAT (optional)', 'tcuk-all-in-one-migrator' ); ?></label><input type="password" name="github_token" value="<?php echo esc_attr( $settings['github_token'] ); ?>" class="widefat"></p>
                    <p class="tcuk-field"><label><?php esc_html_e( 'Repo Subdirectory (optional)', 'tcuk-all-in-one-migrator' ); ?></label><input type="text" name="github_repo_subdir" value="<?php echo esc_attr( $settings['github_repo_subdir'] ); ?>" class="widefat" placeholder="themes/your-theme"></p>
                </div>

                <h3 class="tcuk-section-title"><?php esc_html_e( 'API Push', 'tcuk-all-in-one-migrator' ); ?></h3>
                <p class="description"><?php esc_html_e( 'Use a token-protected WordPress REST endpoint for one-click push from local to live.', 'tcuk-all-in-one-migrator' ); ?></p>
                <p class="description"><?php esc_html_e( 'Please hit save to get your Receive API Token.', 'tcuk-all-in-one-migrator' ); ?></p>
                <div class="tcuk-cols-2">
                    <p class="tcuk-field"><label><input type="checkbox" name="remote_api_enabled" value="1" <?php checked( ! empty( $settings['remote_api_enabled'] ) ); ?> <?php disabled( empty( $is_premium ) ); ?>> <?php esc_html_e( 'Enable receive endpoint on this site', 'tcuk-all-in-one-migrator' ); ?></label></p>
                    <p class="tcuk-field"><label><?php esc_html_e( 'Receive API Token', 'tcuk-all-in-one-migrator' ); ?></label><input type="text" name="remote_api_token" value="<?php echo esc_attr( $settings['remote_api_token'] ); ?>" class="widefat" placeholder="Auto-generated on save when enabled"></p>
                    <p class="tcuk-field"><label><?php esc_html_e( 'API Push Destination URL', 'tcuk-all-in-one-migrator' ); ?></label><input type="url" name="remote_push_site_url" value="<?php echo esc_attr( $settings['remote_push_site_url'] ); ?>" class="widefat" placeholder="https://live-site.com"></p>
                    <p class="tcuk-field"><label><?php esc_html_e( 'API Push Key', 'tcuk-all-in-one-migrator' ); ?></label><input type="text" name="remote_push_api_key" value="<?php echo esc_attr( $settings['remote_push_api_key'] ); ?>" class="widefat" placeholder="Use token from destination site"></p>
                    <p class="tcuk-field"><label><input type="checkbox" name="remote_push_verify_ssl" value="1" <?php checked( ! empty( $settings['remote_push_verify_ssl'] ) ); ?>> <?php esc_html_e( 'Verify SSL certificate for API Push', 'tcuk-all-in-one-migrator' ); ?></label></p>
                </div>

                <p><button class="button button-primary tcuk-submit" type="submit"><?php esc_html_e( 'Save Settings', 'tcuk-all-in-one-migrator' ); ?></button></p>
            </form>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tcuk-compact-form">
                <input type="hidden" name="action" value="tcuk_migrator_github_test">
                <?php wp_nonce_field( 'tcuk_migrator_github_test' ); ?>
                <input type="hidden" name="github_repo" value="<?php echo esc_attr( $settings['github_repo'] ); ?>">
                <input type="hidden" name="github_branch" value="<?php echo esc_attr( $settings['github_branch'] ); ?>">
                <input type="hidden" name="github_token" value="<?php echo esc_attr( $settings['github_token'] ); ?>">
                <p><button class="button tcuk-submit" type="submit" <?php disabled( empty( $is_premium ) ); ?>><?php esc_html_e( 'Test GitHub Connection', 'tcuk-all-in-one-migrator' ); ?></button></p>
            </form>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tcuk-compact-form">
                <input type="hidden" name="action" value="tcuk_migrator_remote_api_test">
                <?php wp_nonce_field( 'tcuk_migrator_remote_api_test' ); ?>
                <p><button class="button tcuk-submit" type="submit" <?php disabled( empty( $is_premium ) ); ?>><?php esc_html_e( 'Test API Push Connection', 'tcuk-all-in-one-migrator' ); ?></button></p>
            </form>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tcuk-compact-form">
                <input type="hidden" name="action" value="tcuk_migrator_repair_fse">
                <?php wp_nonce_field( 'tcuk_migrator_repair_fse' ); ?>
                <p class="description"><?php esc_html_e( 'Repairs Full Site Editing theme files and templates after a push or restore if the target theme looks broken or incomplete.', 'tcuk-all-in-one-migrator' ); ?></p>
                <p><button class="button tcuk-submit" type="submit" <?php disabled( empty( $is_premium ) ); ?>><?php esc_html_e( 'Run FSE Repair', 'tcuk-all-in-one-migrator' ); ?></button></p>
            </form>
        </section>

        <section class="tcuk-card">
            <h2><?php esc_html_e( 'API Push', 'tcuk-all-in-one-migrator' ); ?></h2>
            <p class="description"><?php esc_html_e( 'From source site, push selected components to destination site via token-auth REST API.', 'tcuk-all-in-one-migrator' ); ?></p>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tcuk-action-form">
                <input type="hidden" name="action" value="tcuk_migrator_api_push">
                <?php wp_nonce_field( 'tcuk_migrator_api_push' ); ?>
                <?php include __DIR__ . '/partials-sync-fields.php'; ?>
                <p><button class="button button-primary tcuk-submit" type="submit" <?php disabled( empty( $is_premium ) ); ?>><?php esc_html_e( 'Run API Push', 'tcuk-all-in-one-migrator' ); ?></button></p>
            </form>
        </section>

        <section class="tcuk-card">
            <h2><?php esc_html_e( 'GitHub Theme Pull', 'tcuk-all-in-one-migrator' ); ?></h2>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="tcuk_migrator_github_pull">
                <?php wp_nonce_field( 'tcuk_migrator_github_pull' ); ?>
                <p class="description"><?php esc_html_e( 'Uses the saved GitHub settings from Connection Settings.', 'tcuk-all-in-one-migrator' ); ?></p>
                <input type="hidden" name="github_repo" value="<?php echo esc_attr( $settings['github_repo'] ); ?>">
                <input type="hidden" name="github_branch" value="<?php echo esc_attr( $settings['github_branch'] ); ?>">
                <input type="hidden" name="github_theme_slug" value="<?php echo esc_attr( $github_theme_value ); ?>">
                <input type="hidden" name="github_token" value="<?php echo esc_attr( $settings['github_token'] ); ?>">
                <input type="hidden" name="github_repo_subdir" value="<?php echo esc_attr( $settings['github_repo_subdir'] ); ?>">
                <p class="tcuk-field"><label><?php esc_html_e( 'Repository', 'tcuk-all-in-one-migrator' ); ?></label><input type="text" value="<?php echo esc_attr( $settings['github_repo'] ); ?>" class="widefat tcuk-readonly-display" disabled></p>
                <div class="tcuk-cols-2">
                    <p class="tcuk-field"><label><?php esc_html_e( 'Branch', 'tcuk-all-in-one-migrator' ); ?></label><input type="text" value="<?php echo esc_attr( $settings['github_branch'] ); ?>" class="widefat tcuk-readonly-display" disabled></p>
                    <p class="tcuk-field"><label><?php esc_html_e( 'Theme Slug', 'tcuk-all-in-one-migrator' ); ?></label><input type="text" value="<?php echo esc_attr( $github_theme_value ); ?>" class="widefat tcuk-readonly-display" disabled></p>
                    <p class="tcuk-field"><label><?php esc_html_e( 'Token / PAT (optional)', 'tcuk-all-in-one-migrator' ); ?></label><input type="text" value="<?php echo ! empty( $settings['github_token'] ) ? esc_attr__( 'Configured', 'tcuk-all-in-one-migrator' ) : esc_attr__( 'Not set', 'tcuk-all-in-one-migrator' ); ?>" class="widefat tcuk-readonly-display" disabled></p>
                    <p class="tcuk-field"><label><?php esc_html_e( 'Repo Subdirectory (optional)', 'tcuk-all-in-one-migrator' ); ?></label><input type="text" value="<?php echo esc_attr( $settings['github_repo_subdir'] ); ?>" class="widefat tcuk-readonly-display" disabled></p>
                </div>
                <p><label><input type="checkbox" name="replace_existing" value="1" checked> <?php esc_html_e( 'Replace existing theme folder', 'tcuk-all-in-one-migrator' ); ?></label></p>
                <p><button class="button button-primary tcuk-submit" type="submit" <?php disabled( empty( $is_premium ) ); ?>><?php esc_html_e( 'Pull Theme from GitHub', 'tcuk-all-in-one-migrator' ); ?></button></p>
                <p class="description"><?php esc_html_e( 'To change these values, edit Connection Settings above and save first.', 'tcuk-all-in-one-migrator' ); ?></p>
            </form>
        </section>

        <section class="tcuk-card tcuk-card-wide">
            <h2><?php esc_html_e( 'Backups', 'tcuk-all-in-one-migrator' ); ?></h2>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tcuk-action-form">
                <input type="hidden" name="action" value="tcuk_migrator_backup_create">
                <?php wp_nonce_field( 'tcuk_migrator_backup_create' ); ?>

                <p><strong><?php esc_html_e( 'Create Backup', 'tcuk-all-in-one-migrator' ); ?></strong></p>
                <p class="tcuk-checkbox-row">
                    <label><input type="checkbox" name="backup_components[]" value="theme" checked> <?php esc_html_e( 'Themes', 'tcuk-all-in-one-migrator' ); ?></label>
                    <label><input type="checkbox" name="backup_components[]" value="plugins" checked <?php disabled( empty( $is_premium ) ); ?>> <?php esc_html_e( 'Plugins', 'tcuk-all-in-one-migrator' ); ?></label>
                    <label><input type="checkbox" name="backup_components[]" value="uploads" checked <?php disabled( empty( $is_premium ) ); ?>> <?php esc_html_e( 'Uploads', 'tcuk-all-in-one-migrator' ); ?></label>
                    <label><input type="checkbox" name="backup_components[]" value="mu-plugins" <?php disabled( empty( $is_premium ) ); ?>> <?php esc_html_e( 'MU Plugins', 'tcuk-all-in-one-migrator' ); ?></label>
                    <label><input type="checkbox" name="backup_components[]" value="database" checked <?php disabled( empty( $is_premium ) ); ?>> <?php esc_html_e( 'Database', 'tcuk-all-in-one-migrator' ); ?></label>
                </p>
                <p class="tcuk-field">
                    <label><?php esc_html_e( 'Themes (optional selection)', 'tcuk-all-in-one-migrator' ); ?></label>
                    <select name="backup_themes[]" multiple class="widefat tcuk-multi-select">
                        <?php foreach ( $site_themes as $slug ) : ?>
                            <option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $slug ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </p>
                <p class="tcuk-field">
                    <label><?php esc_html_e( 'Plugin mode', 'tcuk-all-in-one-migrator' ); ?></label>
                    <select name="backup_plugin_mode">
                        <option value="all"><?php esc_html_e( 'All plugins', 'tcuk-all-in-one-migrator' ); ?></option>
                        <option value="selected"><?php esc_html_e( 'Selected plugins', 'tcuk-all-in-one-migrator' ); ?></option>
                    </select>
                </p>
                <p class="tcuk-field tcuk-backup-plugin-select-wrap">
                    <label><?php esc_html_e( 'Plugins (for selected mode)', 'tcuk-all-in-one-migrator' ); ?></label>
                    <select name="backup_plugins[]" multiple class="widefat tcuk-multi-select">
                        <?php foreach ( $site_plugins as $slug ) : ?>
                            <option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $slug ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </p>
                <p class="tcuk-field">
                    <label><?php esc_html_e( 'Database mode', 'tcuk-all-in-one-migrator' ); ?></label>
                    <select name="backup_db_mode">
                        <option value="all"><?php esc_html_e( 'All tables', 'tcuk-all-in-one-migrator' ); ?></option>
                        <option value="selected"><?php esc_html_e( 'Selected groups/custom', 'tcuk-all-in-one-migrator' ); ?></option>
                    </select>
                </p>
                <p class="tcuk-checkbox-row tcuk-backup-db-groups-wrap">
                    <label><?php esc_html_e( 'DB groups', 'tcuk-all-in-one-migrator' ); ?></label><br>
                    <label><input type="checkbox" name="backup_db_groups[]" value="options"> <?php esc_html_e( 'Options', 'tcuk-all-in-one-migrator' ); ?></label>
                    <label><input type="checkbox" name="backup_db_groups[]" value="users"> <?php esc_html_e( 'Users', 'tcuk-all-in-one-migrator' ); ?></label>
                    <label><input type="checkbox" name="backup_db_groups[]" value="content"> <?php esc_html_e( 'Posts', 'tcuk-all-in-one-migrator' ); ?></label>
                    <label><input type="checkbox" name="backup_db_groups[]" value="taxonomy"> <?php esc_html_e( 'Taxonomy', 'tcuk-all-in-one-migrator' ); ?></label>
                    <label><input type="checkbox" name="backup_db_groups[]" value="comments"> <?php esc_html_e( 'Comments', 'tcuk-all-in-one-migrator' ); ?></label>
                </p>
                <p class="tcuk-field tcuk-backup-custom-tables-wrap"><label><?php esc_html_e( 'Custom DB tables (comma/new line)', 'tcuk-all-in-one-migrator' ); ?></label><textarea name="backup_custom_tables" class="widefat" rows="2"></textarea></p>

                <p><button class="button button-primary tcuk-submit" type="submit"><?php esc_html_e( 'Create Backup File', 'tcuk-all-in-one-migrator' ); ?></button></p>
            </form>

            <hr>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tcuk-action-form" enctype="multipart/form-data">
                <input type="hidden" name="action" value="tcuk_migrator_backup_upload">
                <?php wp_nonce_field( 'tcuk_migrator_backup_upload' ); ?>
                <p><strong><?php esc_html_e( 'Upload Backup Zip (from Mac/local)', 'tcuk-all-in-one-migrator' ); ?></strong></p>
                <p class="description"><?php esc_html_e( 'Use this simple method when you want manual transfer: create a backup on local, download it, then upload it here and restore.', 'tcuk-all-in-one-migrator' ); ?></p>
                <p class="tcuk-field"><label><?php esc_html_e( 'Backup .zip file', 'tcuk-all-in-one-migrator' ); ?></label><input type="file" name="backup_upload_file" accept=".zip,application/zip"></p>
                <p><button class="button tcuk-submit" type="submit"><?php esc_html_e( 'Upload Backup File', 'tcuk-all-in-one-migrator' ); ?></button></p>
            </form>

            <hr>

            <p><strong><?php esc_html_e( 'Backup Files', 'tcuk-all-in-one-migrator' ); ?></strong></p>
            <?php if ( empty( $backups ) ) : ?>
                <p><?php esc_html_e( 'No backups yet.', 'tcuk-all-in-one-migrator' ); ?></p>
            <?php else : ?>
                <div class="tcuk-table-scroll">
                <table class="widefat striped">
                    <thead>
                    <tr>
                        <th><?php esc_html_e( 'File', 'tcuk-all-in-one-migrator' ); ?></th>
                        <th><?php esc_html_e( 'Size', 'tcuk-all-in-one-migrator' ); ?></th>
                        <th><?php esc_html_e( 'Created', 'tcuk-all-in-one-migrator' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'tcuk-all-in-one-migrator' ); ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $backups as $backup ) : ?>
                        <tr>
                            <td><?php echo esc_html( $backup['name'] ); ?></td>
                            <td><?php echo esc_html( $backup['size'] ); ?></td>
                            <td><?php echo esc_html( wp_date( 'Y-m-d H:i:s', $backup['timestamp'] ) ); ?></td>
                            <td class="tcuk-actions-cell">
                                <a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tcuk_migrator_backup_download&backup_file=' . rawurlencode( $backup['name'] ) ), 'tcuk_migrator_backup_download' ) ); ?>"><?php esc_html_e( 'Download', 'tcuk-all-in-one-migrator' ); ?></a>

                                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tcuk-inline-form">
                                    <input type="hidden" name="action" value="tcuk_migrator_backup_restore">
                                    <?php wp_nonce_field( 'tcuk_migrator_backup_restore' ); ?>
                                    <input type="hidden" name="backup_file" value="<?php echo esc_attr( $backup['name'] ); ?>">
                                    <label><input type="checkbox" name="restore_components[]" value="theme" checked> <?php esc_html_e( 'Themes', 'tcuk-all-in-one-migrator' ); ?></label>
                                    <label><input type="checkbox" name="restore_components[]" value="plugins" checked <?php disabled( empty( $is_premium ) ); ?>> <?php esc_html_e( 'Plugins', 'tcuk-all-in-one-migrator' ); ?></label>
                                    <label><input type="checkbox" name="restore_components[]" value="uploads" <?php disabled( empty( $is_premium ) ); ?>> <?php esc_html_e( 'Uploads', 'tcuk-all-in-one-migrator' ); ?></label>
                                    <label><input type="checkbox" name="restore_components[]" value="mu-plugins" <?php disabled( empty( $is_premium ) ); ?>> <?php esc_html_e( 'MU Plugins', 'tcuk-all-in-one-migrator' ); ?></label>
                                    <label><input type="checkbox" name="restore_components[]" value="database" <?php disabled( empty( $is_premium ) ); ?>> <?php esc_html_e( 'Database', 'tcuk-all-in-one-migrator' ); ?></label>
                                    <button type="submit" class="button tcuk-submit"><?php esc_html_e( 'Restore', 'tcuk-all-in-one-migrator' ); ?></button>
                                </form>

                                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tcuk-inline-form">
                                    <input type="hidden" name="action" value="tcuk_migrator_backup_delete">
                                    <?php wp_nonce_field( 'tcuk_migrator_backup_delete' ); ?>
                                    <input type="hidden" name="backup_file" value="<?php echo esc_attr( $backup['name'] ); ?>">
                                    <button type="submit" class="button button-link-delete tcuk-confirm-delete tcuk-danger-text"><?php esc_html_e( 'Delete', 'tcuk-all-in-one-migrator' ); ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>
