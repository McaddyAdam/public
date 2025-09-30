<?php
/**
 * Register WP-CLI commands for this plugin.
 */

if ( defined( 'WP_CLI' ) && WP_CLI ) {
    if ( file_exists( __DIR__ . '/class-posts-maintenance-cli.php' ) ) {
        require_once __DIR__ . '/class-posts-maintenance-cli.php';
        \WP_CLI::add_command( 'wpmudev posts-scan', '\WPMUDEV\\PluginTest\\App\\CLI\\Posts_Maintenance_CLI' );
    }
}
