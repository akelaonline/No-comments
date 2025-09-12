<?php
/**
 * Plugin Name: NO Comments
 * Description: Un toggle simple para habilitar o deshabilitar los comentarios (y pings) en todo el sitio.
 * Version: 1.1.1
 * Author: Cascade
 * License: GPLv2 or later
 * Text Domain: no-comments
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Salir si se accede directamente.
}

if ( ! class_exists( 'No_Comments_Plugin' ) ) :

final class No_Comments_Plugin {
    const OPTION_KEY     = 'no_comments_enabled';
    const SETTINGS_GROUP = 'no_comments_settings_group';
    const PAGE_SLUG      = 'no-comments';

    public static function init() {
        // Ajustes y página en el admin
        add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
        add_action( 'admin_menu', [ __CLASS__, 'add_settings_page' ] );
        // Reordenar para mostrar antes de "Comentarios/Discusión" en Ajustes
        add_action( 'admin_menu', [ __CLASS__, 'reorder_settings_submenu' ], 1000 );

        // Aplicar bloqueo de comentarios si está activado
        if ( self::is_enabled() ) {
            self::apply_disable_comments();
        }
    }

    public static function is_enabled() {
        return (bool) get_option( self::OPTION_KEY, 0 );
    }

    public static function register_settings() {
        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_KEY,
            [
                'type'              => 'boolean',
                'sanitize_callback' => function ( $value ) { return $value ? 1 : 0; },
                'default'           => 0,
            ]
        );

        add_settings_section(
            'no_comments_main',
            __( 'Ajustes', 'no-comments' ),
            function () {
                echo '<p>' . esc_html__( 'Activa o desactiva los comentarios (y pings) en todo el sitio.', 'no-comments' ) . '</p>';
            },
            self::PAGE_SLUG
        );

        add_settings_field(
            self::OPTION_KEY,
            __( 'Deshabilitar comentarios', 'no-comments' ),
            [ __CLASS__, 'render_toggle_field' ],
            self::PAGE_SLUG,
            'no_comments_main'
        );
    }

    public static function render_toggle_field() {
        $enabled = self::is_enabled();
        // Campo oculto para asegurar que al desmarcar se envíe "0"
        echo '<input type="hidden" name="' . esc_attr( self::OPTION_KEY ) . '" value="0" />';
        echo '<label>';
        echo '<input type="checkbox" name="' . esc_attr( self::OPTION_KEY ) . '" value="1" ' . checked( $enabled, true, false ) . ' /> ';
        echo esc_html__( 'Cerrar comentarios y pings en todo el sitio', 'no-comments' );
        echo '</label>';
    }

    public static function add_settings_page() {
        // Añadir bajo Ajustes
        add_options_page(
            __( 'NO Comments', 'no-comments' ),
            __( 'NO Comments', 'no-comments' ),
            'manage_options',
            self::PAGE_SLUG,
            [ __CLASS__, 'render_settings_page' ]
        );
    }

    public static function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'NO Comments', 'no-comments' ) . '</h1>';
        echo '<form action="options.php" method="post">';
        settings_fields( self::SETTINGS_GROUP );
        do_settings_sections( self::PAGE_SLUG );
        submit_button();
        echo '</form>';
        echo '</div>';
    }

    private static function apply_disable_comments() {
        // Cerrar formularios de comentarios y pings en frontend y REST
        add_filter( 'comments_open', '__return_false', 20 );
        add_filter( 'pings_open', '__return_false', 20 );

        if ( function_exists( '__return_empty_array' ) ) {
            add_filter( 'comments_array', '__return_empty_array', 10, 2 );
        } else {
            add_filter( 'comments_array', function( $comments ) { return []; }, 10, 2 );
        }

        // Quitar soporte de comentarios y trackbacks de todos los post types públicos
        add_action( 'init', function () {
            foreach ( get_post_types( [ 'public' => true ], 'names' ) as $post_type ) {
                if ( post_type_supports( $post_type, 'comments' ) ) {
                    remove_post_type_support( $post_type, 'comments' );
                }
                if ( post_type_supports( $post_type, 'trackbacks' ) ) {
                    remove_post_type_support( $post_type, 'trackbacks' );
                }
            }
        }, 100 );

        // Ocultar el menú de Comentarios en el admin cuando está deshabilitado
        add_action( 'admin_menu', function () {
            // Menú principal de Comentarios
            remove_menu_page( 'edit-comments.php' );
            // Submenú de Ajustes → Comentarios (Discusión)
            remove_submenu_page( 'options-general.php', 'options-discussion.php' );
        }, 999 );

        // Quitar el ícono de comentarios del admin bar
        add_action( 'admin_bar_menu', function ( $wp_admin_bar ) {
            $wp_admin_bar->remove_node( 'comments' );
        }, 999 );

        // Bloquear accesos directos a Comentarios y Discusión
        add_action( 'admin_init', function () {
            if ( is_admin() && isset( $GLOBALS['pagenow'] ) && in_array( $GLOBALS['pagenow'], [ 'edit-comments.php', 'options-discussion.php' ], true ) ) {
                wp_safe_redirect( admin_url() );
                exit;
            }
        }, 1 );

        // Fallback visual por CSS en caso de que otro plugin reordene/añada el menú después
        add_action( 'admin_head', function () {
            echo '<style id="no-comments-admin-css">#menu-comments, #adminmenu a[href$="edit-comments.php"], #adminmenu a[href$="options-discussion.php"]{display:none !important;}</style>';
        } );
    }

    public static function reorder_settings_submenu() {
        // Mover "NO Comments" antes de la página de "Comentarios/Discusión"
        global $submenu;
        if ( empty( $submenu['options-general.php'] ) || ! is_array( $submenu['options-general.php'] ) ) {
            return;
        }

        $settings = &$submenu['options-general.php'];
        $no_comments_index = null;
        $discussion_index  = null;

        foreach ( $settings as $index => $item ) {
            // $item[2] es el slug
            if ( isset( $item[2] ) && $item[2] === self::PAGE_SLUG ) {
                $no_comments_index = $index;
            }
            if ( isset( $item[2] ) && $item[2] === 'options-discussion.php' ) {
                $discussion_index = $index;
            }
        }

        if ( $no_comments_index === null || $discussion_index === null ) {
            return;
        }

        if ( $no_comments_index > $discussion_index ) {
            // Extraer el item y reinsertarlo antes de Discusión
            $item = $settings[ $no_comments_index ];
            unset( $settings[ $no_comments_index ] );

            // Re-indexar para mantener orden
            $settings = array_values( $settings );

            // Encontrar nueva posición de Discusión tras reindexar
            $discussion_index_new = null;
            foreach ( $settings as $i => $it ) {
                if ( isset( $it[2] ) && $it[2] === 'options-discussion.php' ) {
                    $discussion_index_new = $i;
                    break;
                }
            }
            if ( $discussion_index_new !== null ) {
                array_splice( $settings, $discussion_index_new, 0, [ $item ] );
            }
        }
    }
}

No_Comments_Plugin::init();

endif; // class exists
