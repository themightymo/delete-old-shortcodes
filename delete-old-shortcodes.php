<?php
/**
 * Plugin Name: Delete Old Shortcodes
 * Plugin URI: https://themightymo.com
 * Description: Permanently deletes Fusion Builder and Visual Composer shortcodes from all post content. Use the admin page under "Tools" to trigger the deletion.
 * Version: 1.0.0
 * Author: The Mighty Mo! Design Co. LLC
 * Author URI: https://themightymo.com
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: delete-old-shortcodes
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class Delete_Old_Shortcodes {

    private $shortcodes = [
        'vc_row',
        'vc_column',
        'vc_button',
        'fusion_builder_container',
        'fusion_builder_row',
        'fusion_builder_column',
        'fusion_text',
        'fsn_row',
        'fsn_column',
        'fsn_text'
    ];

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_page' ] );
        add_action( 'admin_init', [ $this, 'handle_deletion_request' ] );
    }

    public function add_admin_page() {
        add_management_page(
            __( 'Delete Old Shortcodes', 'delete-old-shortcodes' ),
            __( 'Delete Old Shortcodes', 'delete-old-shortcodes' ),
            'manage_options',
            'delete-old-shortcodes',
            [ $this, 'render_admin_page' ]
        );
    }

    public function render_admin_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Delete Old Shortcodes', 'delete-old-shortcodes' ); ?></h1>
            <p><?php esc_html_e( 'Click the button below to permanently remove all specified shortcodes from all posts. The inner content will be preserved, but the shortcode tags themselves will be removed. This action cannot be undone, so ensure you have a backup.', 'delete-old-shortcodes' ); ?></p>
            <form method="post">
                <?php wp_nonce_field( 'delete_old_shortcodes_action', 'delete_old_shortcodes_nonce' ); ?>
                <input type="hidden" name="delete_old_shortcodes_action" value="delete_shortcodes" />
                <input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Delete Shortcodes Now', 'delete-old-shortcodes' ); ?>" />
            </form>
        </div>
        <?php
    }

    public function handle_deletion_request() {
        // Check if our form was submitted
        if ( isset( $_POST['delete_old_shortcodes_action'] ) && $_POST['delete_old_shortcodes_action'] === 'delete_shortcodes' ) {
            // Check nonce and capabilities
            if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'delete_old_shortcodes_action', 'delete_old_shortcodes_nonce' ) ) {
                wp_die( __( 'You are not allowed to do this.', 'delete-old-shortcodes' ) );
            }

            // Perform the deletion
            $this->delete_shortcodes_from_all_posts();

            // Redirect to the same page with a success message
            wp_redirect( add_query_arg( 'delete_old_shortcodes_done', '1', admin_url( 'tools.php?page=delete-old-shortcodes' ) ) );
            exit;
        }

        // If redirected back with success
        if ( isset( $_GET['delete_old_shortcodes_done'] ) && $_GET['delete_old_shortcodes_done'] == '1' ) {
            add_action( 'admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Shortcodes have been removed successfully, preserving the inner content.', 'delete-old-shortcodes' ) . '</p></div>';
            } );
        }
    }

    private function delete_shortcodes_from_all_posts() {
        // Get all posts (including pages, custom post types if needed)
        $args = [
            'post_type'      => 'any',
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'fields'         => 'ids', // We only need IDs
        ];

        $post_ids = get_posts( $args );

        foreach ( $post_ids as $post_id ) {
            $content = get_post_field( 'post_content', $post_id );
            $new_content = $this->remove_shortcodes_preserving_content( $content );

            // Update the post if changes were made
            if ( $new_content !== $content ) {
                wp_update_post( [
                    'ID'           => $post_id,
                    'post_content' => $new_content
                ] );
            }
        }
    }

    private function remove_shortcodes_preserving_content( $content ) {
        foreach ( $this->shortcodes as $shortcode ) {
            $shortcode_escaped = preg_quote( $shortcode, '/' );

            // Remove paired shortcodes while preserving inner content
            // [shortcode ...]Inner Content[/shortcode] -> Inner Content
            $pattern_with_closing = '/\[' . $shortcode_escaped . '[^\]]*\](.*?)\[\/' . $shortcode_escaped . '\]/s';
            $content = preg_replace( $pattern_with_closing, '$1', $content );

            // Remove standalone shortcodes without closing tags
            // [shortcode ...] -> (removed)
            $pattern_standalone = '/\[' . $shortcode_escaped . '[^\]]*\]/';
            $content = preg_replace( $pattern_standalone, '', $content );
        }

        return $content;
    }
}

new Delete_Old_Shortcodes();
