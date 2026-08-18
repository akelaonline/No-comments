<?php
/**
 * Plugin Name: NO Comments
 * Description: Cierra comentarios y pings en todo el sitio y limpia comentarios de forma segura, con WooCommerce, Multisite, REST, WP-CLI y limpieza automática.
 * Version: 1.14.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Alejandro Daniel José · Akela
 * Plugin URI: https://github.com/akelaonline/No-comments
 * Author URI: https://mktmarketingdigital.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI: https://github.com/akelaonline/No-comments
 * Text Domain: no-comments
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Salir si se accede directamente.
}

define( 'NO_COMMENTS_VERSION', '1.14.0' );
define( 'NO_COMMENTS_FILE', __FILE__ );
define( 'NO_COMMENTS_PATH', plugin_dir_path( __FILE__ ) );
define( 'NO_COMMENTS_URL', plugin_dir_url( __FILE__ ) );

// Cargas internas (refactor progresivo)
if ( file_exists( __DIR__ . '/includes/Application/DeleteService.php' ) ) {
    require_once __DIR__ . '/includes/Application/DeleteService.php';
}
if ( file_exists( __DIR__ . '/includes/Infrastructure/OptionsRepository.php' ) ) {
    require_once __DIR__ . '/includes/Infrastructure/OptionsRepository.php';
}

if ( ! class_exists( 'No_Comments_Plugin' ) ) :

final class No_Comments_Plugin {
    const OPTION_KEY     = 'no_comments_enabled';
    const SETTINGS_GROUP = 'no_comments_settings_group';
    const PAGE_SLUG      = 'no-comments';

    public function __construct() {
        // Note: disabled by default; admins can enable from Settings > NO Comments.
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_menu', array( $this, 'admin_menu' ) );
        add_action( 'admin_init', array( $this, 'maybe_apply_admin_policy' ), 1 );
        add_action( 'init', array( $this, 'maybe_apply_frontend_policy' ), 1 );
        add_action( 'wp_loaded', array( $this, 'maybe_apply_cleanup_schedule' ) );
        add_action( 'no_comments_cleanup_event', array( $this, 'run_scheduled_cleanup' ) );
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
    }

    public function is_enabled() {
        return (bool) get_option( self::OPTION_KEY, false );
    }

    public function get_settings() {
        return array(
            'enabled'              => $this->is_enabled(),
            'rest_api'             => (bool) get_option( 'no_comments_disable_rest_api', false ),
            'xmlrpc'               => (bool) get_option( 'no_comments_disable_xmlrpc', false ),
            'keep_wc_reviews'      => (bool) get_option( 'no_comments_keep_wc_reviews', true ),
            'exceptions'           => (array) get_option( 'no_comments_post_type_exceptions', array() ),
            'auto_close_days'      => max( 0, (int) get_option( 'no_comments_auto_close_days', 0 ) ),
            'cleanup_enabled'      => (bool) get_option( 'no_comments_cleanup_enabled', false ),
            'cleanup_interval'     => (string) get_option( 'no_comments_cleanup_interval', 'daily' ),
            'cleanup_scope'        => (string) get_option( 'no_comments_cleanup_scope', 'spam' ),
            'cleanup_strategy'     => (string) get_option( 'no_comments_cleanup_strategy', 'delete' ),
            'cleanup_post_types'   => (array) get_option( 'no_comments_cleanup_post_types', array() ),
        );
    }

    public function register_settings() {
        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_KEY,
            array(
                'type'              => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default'           => false,
            )
        );

        $boolean_options = array(
            'no_comments_disable_rest_api' => false,
            'no_comments_disable_xmlrpc'   => false,
            'no_comments_keep_wc_reviews'  => true,
            'no_comments_cleanup_enabled'  => false,
        );

        foreach ( $boolean_options as $option => $default ) {
            register_setting(
                self::SETTINGS_GROUP,
                $option,
                array(
                    'type'              => 'boolean',
                    'sanitize_callback' => 'rest_sanitize_boolean',
                    'default'           => $default,
                )
            );
        }

        register_setting(
            self::SETTINGS_GROUP,
            'no_comments_post_type_exceptions',
            array(
                'type'              => 'array',
                'sanitize_callback' => array( $this, 'sanitize_post_types' ),
                'default'           => array(),
            )
        );

        register_setting(
            self::SETTINGS_GROUP,
            'no_comments_auto_close_days',
            array(
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
                'default'           => 0,
            )
        );

        register_setting(
            self::SETTINGS_GROUP,
            'no_comments_cleanup_interval',
            array(
                'type'              => 'string',
                'sanitize_callback' => array( $this, 'sanitize_cleanup_interval' ),
                'default'           => 'daily',
            )
        );

        register_setting(
            self::SETTINGS_GROUP,
            'no_comments_cleanup_scope',
            array(
                'type'              => 'string',
                'sanitize_callback' => array( $this, 'sanitize_cleanup_scope' ),
                'default'           => 'spam',
            )
        );

        register_setting(
            self::SETTINGS_GROUP,
            'no_comments_cleanup_strategy',
            array(
                'type'              => 'string',
                'sanitize_callback' => array( $this, 'sanitize_cleanup_strategy' ),
                'default'           => 'delete',
            )
        );

        register_setting(
            self::SETTINGS_GROUP,
            'no_comments_cleanup_post_types',
            array(
                'type'              => 'array',
                'sanitize_callback' => array( $this, 'sanitize_post_types' ),
                'default'           => array(),
            )
        );
    }

    public function sanitize_post_types( $value ) {
        if ( ! is_array( $value ) ) {
            return array();
        }

        return array_values( array_unique( array_filter( array_map( 'sanitize_key', $value ) ) ) );
    }

    public function sanitize_cleanup_interval( $value ) {
        $allowed = array( 'daily', 'twicedaily', 'weekly' );
        return in_array( $value, $allowed, true ) ? $value : 'daily';
    }

    public function sanitize_cleanup_scope( $value ) {
        $allowed = array( 'spam', 'pending', 'trash', 'all' );
        return in_array( $value, $allowed, true ) ? $value : 'spam';
    }

    public function sanitize_cleanup_strategy( $value ) {
        return 'trash' === $value ? 'trash' : 'delete';
    }

    public function admin_menu() {
        add_options_page(
            'NO Comments',
            'NO Comments',
            'manage_options',
            self::PAGE_SLUG,
            array( $this, 'render_settings_page' )
        );
    }

    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $settings = $this->get_settings();
        ?>
        <div class="wrap">
            <h1>NO Comments</h1>
            <p>Deshabilitá comentarios y pings de forma global sin romper WooCommerce.</p>

            <form method="post" action="options.php">
                <?php settings_fields( self::SETTINGS_GROUP ); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">Deshabilitar comentarios</th>
                        <td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>" value="1" <?php checked( $settings['enabled'] ); ?>> Activado</label></td>
                    </tr>
                    <tr>
                        <th scope="row">Bloquear REST API de comentarios</th>
                        <td><label><input type="checkbox" name="no_comments_disable_rest_api" value="1" <?php checked( $settings['rest_api'] ); ?>> Bloquear endpoints de comentarios</label></td>
                    </tr>
                    <tr>
                        <th scope="row">Bloquear XML-RPC comments</th>
                        <td><label><input type="checkbox" name="no_comments_disable_xmlrpc" value="1" <?php checked( $settings['xmlrpc'] ); ?>> Bloquear wp.newComment</label></td>
                    </tr>
                    <tr>
                        <th scope="row">WooCommerce</th>
                        <td><label><input type="checkbox" name="no_comments_keep_wc_reviews" value="1" <?php checked( $settings['keep_wc_reviews'] ); ?>> Mantener reseñas de productos</label></td>
                    </tr>
                    <tr>
                        <th scope="row">Cerrar automáticamente</th>
                        <td><input type="number" min="0" name="no_comments_auto_close_days" value="<?php echo esc_attr( $settings['auto_close_days'] ); ?>"> días (0 = desactivado)</td>
                    </tr>
                    <tr>
                        <th scope="row">Limpieza automática</th>
                        <td>
                            <label><input type="checkbox" name="no_comments_cleanup_enabled" value="1" <?php checked( $settings['cleanup_enabled'] ); ?>> Activar</label><br>
                            <select name="no_comments_cleanup_interval">
                                <option value="daily" <?php selected( $settings['cleanup_interval'], 'daily' ); ?>>Diaria</option>
                                <option value="twicedaily" <?php selected( $settings['cleanup_interval'], 'twicedaily' ); ?>>Dos veces al día</option>
                                <option value="weekly" <?php selected( $settings['cleanup_interval'], 'weekly' ); ?>>Semanal</option>
                            </select>
                            <select name="no_comments_cleanup_scope">
                                <option value="spam" <?php selected( $settings['cleanup_scope'], 'spam' ); ?>>Spam</option>
                                <option value="pending" <?php selected( $settings['cleanup_scope'], 'pending' ); ?>>Pendientes</option>
                                <option value="trash" <?php selected( $settings['cleanup_scope'], 'trash' ); ?>>Papelera</option>
                                <option value="all" <?php selected( $settings['cleanup_scope'], 'all' ); ?>>Todos</option>
                            </select>
                            <select name="no_comments_cleanup_strategy">
                                <option value="delete" <?php selected( $settings['cleanup_strategy'], 'delete' ); ?>>Borrar definitivamente</option>
                                <option value="trash" <?php selected( $settings['cleanup_strategy'], 'trash' ); ?>>Mover a Papelera</option>
                            </select>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function maybe_apply_admin_policy() {
        if ( ! $this->is_enabled() ) {
            return;
        }

        add_action( 'admin_menu', array( $this, 'remove_comment_admin_menu' ), 999 );
        add_action( 'admin_bar_menu', array( $this, 'remove_comment_admin_bar' ), 999 );
        add_action( 'current_screen', array( $this, 'redirect_comment_admin_screens' ) );
    }

    public function maybe_apply_frontend_policy() {
        if ( ! $this->is_enabled() ) {
            return;
        }

        add_filter( 'comments_open', array( $this, 'filter_comments_open' ), 20, 2 );
        add_filter( 'pings_open', array( $this, 'filter_comments_open' ), 20, 2 );
        add_filter( 'comments_array', '__return_empty_array', 20, 2 );
        add_filter( 'comments_template', array( $this, 'comments_template' ), 20 );
        add_filter( 'feed_links_show_comments_feed', '__return_false' );
        add_filter( 'rest_endpoints', array( $this, 'filter_rest_endpoints' ) );
        add_filter( 'xmlrpc_methods', array( $this, 'filter_xmlrpc_methods' ) );
    }

    public function filter_comments_open( $open, $post_id = 0 ) {
        if ( $this->is_exception_post_type( $post_id ) ) {
            return $open;
        }

        return false;
    }

    public function comments_template( $template ) {
        return plugin_dir_path( __FILE__ ) . 'includes/empty-comments.php';
    }

    public function filter_rest_endpoints( $endpoints ) {
        if ( ! get_option( 'no_comments_disable_rest_api', false ) ) {
            return $endpoints;
        }

        foreach ( array_keys( $endpoints ) as $route ) {
            if ( 0 === strpos( $route, '/wp/v2/comments' ) ) {
                unset( $endpoints[ $route ] );
            }
        }

        return $endpoints;
    }

    public function filter_xmlrpc_methods( $methods ) {
        if ( get_option( 'no_comments_disable_xmlrpc', false ) ) {
            unset( $methods['wp.newComment'] );
        }
        return $methods;
    }

    public function remove_comment_admin_menu() {
        remove_menu_page( 'edit-comments.php' );
        remove_submenu_page( 'options-general.php', 'options-discussion.php' );
    }

    public function remove_comment_admin_bar( $wp_admin_bar ) {
        $wp_admin_bar->remove_node( 'comments' );
    }

    public function redirect_comment_admin_screens() {
        $screen = get_current_screen();
        if ( ! $screen ) {
            return;
        }

        if ( in_array( $screen->base, array( 'edit-comments', 'comment' ), true ) ) {
            wp_safe_redirect( admin_url() );
            exit;
        }
    }

    public function is_exception_post_type( $post_id ) {
        $post_type = get_post_type( $post_id );
        if ( ! $post_type ) {
            return false;
        }

        $exceptions = (array) get_option( 'no_comments_post_type_exceptions', array() );
        if ( get_option( 'no_comments_keep_wc_reviews', true ) && 'product' === $post_type ) {
            return true;
        }

        return in_array( $post_type, $exceptions, true );
    }

    public function maybe_apply_cleanup_schedule() {
        if ( get_option( 'no_comments_cleanup_enabled', false ) ) {
            if ( ! wp_next_scheduled( 'no_comments_cleanup_event' ) ) {
                wp_schedule_event( time() + HOUR_IN_SECONDS, get_option( 'no_comments_cleanup_interval', 'daily' ), 'no_comments_cleanup_event' );
            }
        } else {
            wp_clear_scheduled_hook( 'no_comments_cleanup_event' );
        }
    }

    public function run_scheduled_cleanup() {
        $scope    = (string) get_option( 'no_comments_cleanup_scope', 'spam' );
        $strategy = (string) get_option( 'no_comments_cleanup_strategy', 'delete' );
        $types    = (array) get_option( 'no_comments_cleanup_post_types', array() );

        if ( class_exists( 'NoComments\\Application\\DeleteService' ) ) {
            $service = new NoComments\Application\DeleteService();
            $service->execute( $scope, $types, $strategy, false );
        }
    }

    public function register_rest_routes() {
        register_rest_route(
            'no-comments/v1',
            '/settings',
            array(
                array(
                    'methods'             => 'GET',
                    'callback'            => array( $this, 'rest_get_settings' ),
                    'permission_callback' => array( $this, 'rest_can_manage' ),
                ),
                array(
                    'methods'             => 'POST',
                    'callback'            => array( $this, 'rest_update_settings' ),
                    'permission_callback' => array( $this, 'rest_can_manage' ),
                ),
            )
        );
    }

    public function rest_can_manage() {
        return current_user_can( 'manage_options' );
    }

    public function rest_get_settings() {
        return rest_ensure_response( $this->get_settings() );
    }

    public function rest_update_settings( WP_REST_Request $request ) {
        $payload = (array) $request->get_json_params();
        $allowed = array(
            self::OPTION_KEY                  => 'rest_sanitize_boolean',
            'no_comments_disable_rest_api'    => 'rest_sanitize_boolean',
            'no_comments_disable_xmlrpc'      => 'rest_sanitize_boolean',
            'no_comments_keep_wc_reviews'     => 'rest_sanitize_boolean',
            'no_comments_auto_close_days'     => 'absint',
        );

        foreach ( $allowed as $key => $sanitizer ) {
            if ( array_key_exists( $key, $payload ) ) {
                update_option( $key, call_user_func( $sanitizer, $payload[ $key ] ) );
            }
        }

        if ( array_key_exists( 'no_comments_post_type_exceptions', $payload ) ) {
            update_option( 'no_comments_post_type_exceptions', $this->sanitize_post_types( $payload['no_comments_post_type_exceptions'] ) );
        }

        return rest_ensure_response( $this->get_settings() );
    }
}

new No_Comments_Plugin();

endif;
