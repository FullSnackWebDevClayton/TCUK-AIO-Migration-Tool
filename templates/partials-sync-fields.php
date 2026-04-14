<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$default_theme_slug = '' !== $settings['github_theme_slug'] ? $settings['github_theme_slug'] : wp_get_theme()->get_stylesheet();
?>
<p class="tcuk-checkbox-row">
    <label><input type="checkbox" name="components[]" value="theme" checked> <?php esc_html_e( 'Theme', 'tcuk-all-in-one-migrator' ); ?></label>
    <label><input type="checkbox" name="components[]" value="plugins" checked <?php disabled( empty( $is_premium ) ); ?>> <?php esc_html_e( 'Plugins', 'tcuk-all-in-one-migrator' ); ?></label>
    <label><input type="checkbox" name="components[]" value="uploads" <?php disabled( empty( $is_premium ) ); ?>> <?php esc_html_e( 'Uploads', 'tcuk-all-in-one-migrator' ); ?></label>
    <label><input type="checkbox" name="components[]" value="mu-plugins" <?php disabled( empty( $is_premium ) ); ?>> <?php esc_html_e( 'MU Plugins', 'tcuk-all-in-one-migrator' ); ?></label>
    <label><input type="checkbox" name="components[]" value="database" <?php disabled( empty( $is_premium ) ); ?>> <?php esc_html_e( 'Database', 'tcuk-all-in-one-migrator' ); ?></label>
</p>

<div class="tcuk-toolbar">
    <button type="button" class="button tcuk-fill-active-theme"><?php esc_html_e( 'Use Active Theme Slug', 'tcuk-all-in-one-migrator' ); ?></button>
</div>

<div class="tcuk-cols-2">
    <p class="tcuk-field"><label><?php esc_html_e( 'Source Theme Slug', 'tcuk-all-in-one-migrator' ); ?></label><input type="text" name="source_theme_slug" class="widefat tcuk-theme-slug-field" value="<?php echo esc_attr( $default_theme_slug ); ?>"></p>
    <p class="tcuk-field"><label><?php esc_html_e( 'Target Theme Slug', 'tcuk-all-in-one-migrator' ); ?></label><input type="text" name="target_theme_slug" class="widefat tcuk-theme-slug-field" value="<?php echo esc_attr( $default_theme_slug ); ?>"></p>
</div>

<p class="tcuk-field">
    <label><?php esc_html_e( 'Plugin mode', 'tcuk-all-in-one-migrator' ); ?></label>
    <select name="plugin_mode" class="tcuk-plugin-mode-select" <?php disabled( empty( $is_premium ) ); ?>>
        <option value="all"><?php esc_html_e( 'All plugins', 'tcuk-all-in-one-migrator' ); ?></option>
        <option value="selected"><?php esc_html_e( 'Selected plugins', 'tcuk-all-in-one-migrator' ); ?></option>
    </select>
</p>

<p class="tcuk-field tcuk-plugin-select-wrap">
    <label><?php esc_html_e( 'Select plugins', 'tcuk-all-in-one-migrator' ); ?></label>
    <select name="selected_plugins[]" multiple class="widefat tcuk-multi-select" <?php disabled( empty( $is_premium ) ); ?>>
        <?php foreach ( array_unique( array_merge( $local_plugins, $site_plugins ) ) as $slug ) : ?>
            <option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $slug ); ?></option>
        <?php endforeach; ?>
    </select>
</p>

<p class="tcuk-field">
    <label><?php esc_html_e( 'Database mode', 'tcuk-all-in-one-migrator' ); ?></label>
    <select name="db_mode" class="tcuk-db-mode-select" <?php disabled( empty( $is_premium ) ); ?>>
        <option value="all"><?php esc_html_e( 'All tables', 'tcuk-all-in-one-migrator' ); ?></option>
        <option value="selected"><?php esc_html_e( 'Selected groups/custom', 'tcuk-all-in-one-migrator' ); ?></option>
    </select>
</p>

<p class="tcuk-checkbox-row tcuk-db-groups-wrap">
    <label><?php esc_html_e( 'DB groups', 'tcuk-all-in-one-migrator' ); ?></label>
    <label><input type="checkbox" name="db_groups[]" value="options" <?php disabled( empty( $is_premium ) ); ?>> <?php esc_html_e( 'Options', 'tcuk-all-in-one-migrator' ); ?></label>
    <label><input type="checkbox" name="db_groups[]" value="users" <?php disabled( empty( $is_premium ) ); ?>> <?php esc_html_e( 'Users', 'tcuk-all-in-one-migrator' ); ?></label>
    <label><input type="checkbox" name="db_groups[]" value="content" <?php disabled( empty( $is_premium ) ); ?>> <?php esc_html_e( 'Posts', 'tcuk-all-in-one-migrator' ); ?></label>
    <label><input type="checkbox" name="db_groups[]" value="taxonomy" <?php disabled( empty( $is_premium ) ); ?>> <?php esc_html_e( 'Taxonomy', 'tcuk-all-in-one-migrator' ); ?></label>
    <label><input type="checkbox" name="db_groups[]" value="comments" <?php disabled( empty( $is_premium ) ); ?>> <?php esc_html_e( 'Comments', 'tcuk-all-in-one-migrator' ); ?></label>
</p>

<p class="tcuk-field tcuk-custom-tables-wrap tcuk-advanced"><label><?php esc_html_e( 'Custom DB tables (comma/new line)', 'tcuk-all-in-one-migrator' ); ?></label><textarea name="custom_tables" class="widefat" rows="2" <?php disabled( empty( $is_premium ) ); ?>></textarea></p>
<p class="tcuk-checkbox-row tcuk-advanced"><label><input type="checkbox" name="replace_existing" value="1" checked <?php disabled( empty( $is_premium ) ); ?>> <?php esc_html_e( 'Replace existing theme/plugins directories on target', 'tcuk-all-in-one-migrator' ); ?></label></p>
