<?php
/**
 * Posts Maintenance admin page and background processing.
 *
 * Provides a Scan Posts UI and background processing via WP Cron + transient queue.
 */

namespace WPMUDEV\PluginTest\App\Admin_Pages;

defined( 'WPINC' ) || die;

use WPMUDEV\PluginTest\Base;

class Posts_Maintenance extends Base {
    private $page_slug = 'wpmudev_plugintest_posts_maintenance';
    private $unique_id;

    public function init() {
        $this->unique_id = 'wpmudev_plugintest_posts_maintenance';
        add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

        // AJAX endpoint for starting scan
        add_action( 'wp_ajax_wpmudev_posts_scan_start', array( $this, 'ajax_start_scan' ) );
    add_action( 'wp_ajax_wpmudev_posts_scan_status', array( $this, 'ajax_scan_status' ) );
    add_action( 'wp_ajax_wpmudev_posts_cancel', array( $this, 'ajax_cancel_scan' ) );

        // Cron hook for processing queue
        add_action( 'wpmudev_posts_maintenance_cron', array( $this, 'process_queue' ) );

        // Schedule daily event if not scheduled
        if ( ! wp_next_scheduled( 'wpmudev_posts_maintenance_cron' ) ) {
            wp_schedule_event( time(), 'daily', 'wpmudev_posts_maintenance_cron' );
        }
    }

    public function register_admin_page() {
        $page = add_submenu_page(
            'edit.php?post_type=post',
            __( 'Posts Maintenance', 'wpmudev-plugin-test' ),
            __( 'Posts Maintenance', 'wpmudev-plugin-test' ),
            'manage_options',
            $this->page_slug,
            array( $this, 'callback' )
        );

        add_action( 'load-' . $page, array( $this, 'prepare_assets' ) );
    }

    public function callback() {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Posts Maintenance', 'wpmudev-plugin-test' ) . '</h1>';
        echo '<p>' . esc_html__( 'Scan and update posts in background. Use filters to select post types.', 'wpmudev-plugin-test' ) . '</p>';

        // Post type filters (public post types)
        $post_types = get_post_types( array( 'public' => true ), 'objects' );
        echo '<div id="wpmudev-posttypes-filters" style="margin-bottom:12px;">';
        foreach ( $post_types as $pt ) {
            $checked = in_array( $pt->name, array( 'post', 'page' ), true ) ? 'checked' : '';
            echo '<label style="margin-right:10px;"><input type="checkbox" class="wpmudev-pt" value="' . esc_attr( $pt->name ) . '" ' . $checked . '> ' . esc_html( $pt->labels->singular_name ) . '</label>';
        }
        echo '</div>';

        echo '<button id="wpmudev-start-scan" class="button button-primary">' . esc_html__( 'Scan Posts', 'wpmudev-plugin-test' ) . '</button>';
        echo ' <button id="wpmudev-cancel-scan" class="button" style="display:none;">' . esc_html__( 'Cancel', 'wpmudev-plugin-test' ) . '</button>';
        echo '<div id="wpmudev-scan-status" style="margin-top:12px;"></div>';
        echo '</div>';
    }

    public function prepare_assets() {
        wp_enqueue_script( 'jquery' );

        $nonce = wp_create_nonce( 'wpmudev_posts_scan' );
        $script = <<<JS
jQuery(function($){
    var polling = null;
    function getSelectedPostTypes(){
        var arr = [];
        $('.wpmudev-pt:checked').each(function(){ arr.push($(this).val()); });
        return arr;
    }

    function renderProgressUI(){
        var html = '';
        html += '<div class="wpmudev-progress-wrap" style="max-width:640px; margin-top:8px;">';
        html += '<div class="wpmudev-progress-label" style="font-size:13px; margin-bottom:6px;">Progress: <span id="wpmudev-progress-percent">0%</span></div>';
        html += '<div style="background:#eee; height:12px; border-radius:6px; overflow:hidden;">';
        html += '<div id="wpmudev-progress-bar" style="width:0%; height:12px; background:#0073aa; transition:width 600ms ease;"></div>';
        html += '</div>';
        html += '<div id="wpmudev-progress-meta" style="font-size:12px; margin-top:6px; color:#555;">Processed: <span id="wpmudev-processed">0</span> / <span id="wpmudev-total">0</span></div>';
        html += '</div>';
        $('#wpmudev-scan-status').html(html);
    }

    function updateStatus(){
        $.get(ajaxurl, { action: 'wpmudev_posts_scan_status' }, function(resp){
            if(!resp || !resp.data) return;
            var data = resp.data;
            var total = parseInt(data.total,10) || 0;
            var processed = parseInt(data.processed,10) || 0;
            var percent = total > 0 ? Math.round((processed/total)*100) : (processed>0?100:0);

            $('#wpmudev-processed').text(processed);
            $('#wpmudev-total').text(total);
            $('#wpmudev-progress-percent').text(percent + '%');
            $('#wpmudev-progress-bar').css('width', percent + '%');

            if(total && processed >= total){
                if(polling) clearInterval(polling);
                polling = null;
                $('#wpmudev-cancel-scan').hide();
                var doneTime = new Date().toLocaleString();
                $('#wpmudev-scan-status').append('<div style="color:green; margin-top:8px; font-weight:600;">Scan complete — ' + doneTime + '</div>');
            }
        }, 'json');
    }

    $('#wpmudev-start-scan').on('click', function(){
        var pts = getSelectedPostTypes();
        renderProgressUI();
        $.post(ajaxurl, { action: 'wpmudev_posts_scan_start', nonce: '{$nonce}', post_types: pts }, function(resp){
            if(resp && resp.success){
                // start polling
                if(polling) clearInterval(polling);
                updateStatus();
                polling = setInterval(updateStatus, 2000);
                $('#wpmudev-cancel-scan').show();
            } else {
                $('#wpmudev-scan-status').html((resp && resp.data && resp.data.message) ? resp.data.message : 'Failed to start');
            }
        }, 'json');
    });

    $('#wpmudev-cancel-scan').on('click', function(){
        // Cancel by deleting transient on server
        $.post(ajaxurl, { action: 'wpmudev_posts_cancel' }, function(resp){
            if(polling) clearInterval(polling);
            polling = null;
            $('#wpmudev-cancel-scan').hide();
            $('#wpmudev-scan-status').html('<div style="color:#c00;">Scan cancelled</div>');
        }, 'json');
    });
});
JS;
        wp_add_inline_script( 'jquery', $script );
    }

    /**
     * AJAX endpoint to report current scan progress.
     */
    public function ajax_scan_status() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error();
        }

        $queue_key = 'wpmudev_posts_scan_queue';
        $ids = get_transient( $queue_key );
        $total = get_option( 'wpmudev_posts_scan_total', 0 );
        if ( empty( $total ) ) {
            // If total is not stored, attempt to estimate: total = remaining + processed
            $remaining = is_array( $ids ) ? count( $ids ) : 0;
            $processed = get_option( 'wpmudev_posts_scan_processed', 0 );
            $total = $remaining + $processed;
        }

        $processed = get_option( 'wpmudev_posts_scan_processed', 0 );

        wp_send_json_success( array( 'total' => (int) $total, 'processed' => (int) $processed ) );
    }

    public function enqueue_assets() {
        // no op; inline script added in prepare_assets
    }

    /**
     * Ajax start scan: prepare a transient-based queue of post IDs and trigger cron processing.
     */
    public function ajax_start_scan() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Forbidden', 'wpmudev-plugin-test' ) ), 403 );
        }

        check_ajax_referer( 'wpmudev_posts_scan', 'nonce' );

        $post_types = isset( $_POST['post_types'] ) ? (array) $_POST['post_types'] : array( 'post', 'page' );
        $post_types = array_map( 'sanitize_text_field', $post_types );

        // Query public posts/pages only
        $args = array(
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        );
        $ids = get_posts( $args );

        if ( empty( $ids ) ) {
            wp_send_json_error( array( 'message' => __( 'No posts found for the selected filters.', 'wpmudev-plugin-test' ) ) );
        }

        // Store queue in transient (10 minutes).
        $queue_key = 'wpmudev_posts_scan_queue';
        set_transient( $queue_key, $ids, 10 * MINUTE_IN_SECONDS );

        // Store totals for status reporting
        update_option( 'wpmudev_posts_scan_total', count( $ids ) );
        update_option( 'wpmudev_posts_scan_processed', 0 );

        // Fire the cron event immediately to start processing in background.
        do_action( 'wpmudev_posts_maintenance_cron' );

        wp_send_json_success( array( 'message' => __( 'Scan started. Processing in background.', 'wpmudev-plugin-test' ) ) );
    }

    /**
     * Cancel scan: remove transient and update processed to 0.
     */
    public function ajax_cancel_scan() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error();
        }

        delete_transient( 'wpmudev_posts_scan_queue' );
        update_option( 'wpmudev_posts_scan_processed', 0 );
        update_option( 'wpmudev_posts_scan_total', 0 );

        wp_send_json_success();
    }

    /**
     * Process queue: pick a batch and update post meta, then re-schedule if items remain.
     */
    public function process_queue() {
        $queue_key = 'wpmudev_posts_scan_queue';
        $ids = get_transient( $queue_key );
        if ( empty( $ids ) || ! is_array( $ids ) ) {
            return;
        }

        // Process in batches to avoid timeouts
        $batch_size = 20;
        $batch = array_splice( $ids, 0, $batch_size );

        foreach ( $batch as $post_id ) {
            // Perform the maintenance action: update post meta with timestamp
            update_post_meta( (int) $post_id, 'wpmudev_test_last_scan', time() );
            // Increment processed counter
            $processed = (int) get_option( 'wpmudev_posts_scan_processed', 0 );
            $processed++;
            update_option( 'wpmudev_posts_scan_processed', $processed );
        }

        if ( ! empty( $ids ) ) {
            // Save remaining IDs and schedule next run in 1 minute
            set_transient( $queue_key, $ids, 10 * MINUTE_IN_SECONDS );
            if ( ! wp_next_scheduled( 'wpmudev_posts_maintenance_cron' ) ) {
                wp_schedule_single_event( time() + 60, 'wpmudev_posts_maintenance_cron' );
            }
        } else {
            // Clear queue
            delete_transient( $queue_key );
            // Clear tracking options
            update_option( 'wpmudev_posts_scan_processed', 0 );
            update_option( 'wpmudev_posts_scan_total', 0 );
        }
    }
}
