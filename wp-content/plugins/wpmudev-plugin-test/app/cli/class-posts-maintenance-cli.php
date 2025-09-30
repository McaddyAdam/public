<?php
/**
 * WP-CLI command for Posts Maintenance
 */

namespace WPMUDEV\PluginTest\App\CLI;

defined( 'WPINC' ) || die;

use WP_CLI;

class Posts_Maintenance_CLI {
    /**
     * Scan posts and update meta wpmudev_test_last_scan.
     *
     * ## OPTIONS
     *
     * [--post_type=<type>]
     * : Post type to scan (can be passed multiple times). Defaults to post and page.
     *
     * ## EXAMPLES
     *
     * wp wpmudev posts-scan --post_type=post --post_type=page
     *
     * @when after_wp_load
     */
    public function __invoke( $args, $assoc_args ) {
        $post_types = isset( $assoc_args['post_type'] ) ? (array) $assoc_args['post_type'] : array( 'post', 'page' );
        $post_types = array_map( 'sanitize_text_field', $post_types );

        WP_CLI::log( "Starting posts scan for: " . implode( ', ', $post_types ) );

        $query = new \WP_Query( array(
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ) );

        $ids = $query->posts;
        $total = count( $ids );
        $processed = 0;

        if ( $total === 0 ) {
            WP_CLI::success( 'No posts found for the selected post types.' );
            return;
        }

        foreach ( $ids as $id ) {
            update_post_meta( (int) $id, 'wpmudev_test_last_scan', time() );
            $processed++;
            if ( $processed % 50 === 0 ) {
                WP_CLI::log( "Processed {$processed}/{$total}..." );
            }
        }

        WP_CLI::success( "Completed posts scan. Processed {$processed} posts." );
    }
}
