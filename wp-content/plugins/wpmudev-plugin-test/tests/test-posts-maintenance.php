<?php
/**
 * Tests for Posts Maintenance functionality.
 *
 * @package Wpmudev_Plugin_Test
 */

class Test_Posts_Maintenance extends WP_UnitTestCase {

    public function test_scan_updates_meta() {
        // Create a post
        $post_id = $this->factory->post->create( array( 'post_status' => 'publish' ) );

        // Simulate queue with single ID
        set_transient( 'wpmudev_posts_scan_queue', array( $post_id ), 10 * MINUTE_IN_SECONDS );

        // Include the class and run process
        require_once plugin_dir_path( __DIR__ ) . 'app/admin-pages/class-posts-maintenance.php';
        $pm = new \WPMUDEV\PluginTest\App\Admin_Pages\Posts_Maintenance();
        $pm->process_queue();

        $meta = get_post_meta( $post_id, 'wpmudev_test_last_scan', true );
        $this->assertNotEmpty( $meta );
        $this->assertIsNumeric( $meta );
    }

    public function test_empty_queue_does_nothing() {
        delete_transient( 'wpmudev_posts_scan_queue' );
        require_once plugin_dir_path( __DIR__ ) . 'app/admin-pages/class-posts-maintenance.php';
        $pm = new \WPMUDEV\PluginTest\App\Admin_Pages\Posts_Maintenance();
        // Should not produce PHP warnings/errors
        $this->assertNull( $pm->process_queue() );
    }
}
