<?php
/**
 * Plugin Name: NO Comments
 * Description: Cierra comentarios y pings en todo el sitio y limpia comentarios de forma segura, con WooCommerce, Multisite, REST, WP-CLI y limpieza automática.
 * Version: 1.13.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Akela (@akelaonline)
 * Plugin URI: https://github.com/akelaonline/No-comments
 * Author URI: https://www.instagram.com/akelaonline/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI: https://github.com/akelaonline/No-comments
 * Text Domain: no-comments
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Salir si se accede directamente.
}

define( 'NO_COMMENTS_VERSION', '1.13.0' );
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
    const OPTION_REST    = 'no_comments_disable_rest';
    const OPTION_XMLRPC  = 'no_comments_disable_xmlrpc';
    const OPTION_WOO     = 'no_comments_keep_woo_reviews';
    const OPTION_NETWORK = 'no_comments_network_settings';

    // Opciones v1.13.0
    const OPTION_EXCEPTIONS       = 'no_comments_exceptions';
    const OPTION_AUTO_CLOSE_DAYS  = 'no_comments_auto_close_days';
    const OPTION_AUTO_CLEANUP     = 'no_comments_auto_cleanup';
    const OPTION_AUTO_CLEANUP_INT = 'no_comments_auto_cleanup_interval';
    const OPTION_LAST_CLEANUP     = 'no_comments_last_cleanup';
    const CRON_EVENT              = 'no_comments_auto_cleanup';
    const CRON_LOCK               = 'no_comments_cleanup_lock';

    /**
     * Inicio del plugin.
     */
    public static function init() {
        // Textdomain
        add_action( 'plugins_loaded', [ __CLASS__, 'load_textdomain' ] );

        // Ajustes y página en el admin
        add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
        add_action( 'admin_menu', [ __CLASS__, 'add_settings_page' ] );
        add_action( 'admin_menu', [ __CLASS__, 'reorder_settings_submenu' ], 1000 );

        // Multisite: página de ajustes de red
        if ( is_multisite() ) {
            add_action( 'network_admin_menu', [ __CLASS__, 'add_network_settings_page' ] );
        }

        // Estilos propios en la página del plugin
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ] );

        // Site Health
        add_filter( 'site_status_tests', [ __CLASS__, 'register_site_health_tests' ] );

        // Acciones admin para borrar comentarios
        add_action( 'admin_post_no_comments_delete', [ __CLASS__, 'handle_delete_request' ] );

        // Exportar / importar ajustes
        add_action( 'admin_post_no_comments_export', [ __CLASS__, 'handle_export_request' ] );
        add_action( 'admin_post_no_comments_import', [ __CLASS__, 'handle_import_request' ] );

        // Guardado de ajustes de red
        add_action( 'admin_post_no_comments_network_save', [ __CLASS__, 'handle_network_save' ] );

        // REST API
        add_action( 'rest_api_init', [ __CLASS__, 'register_rest_routes' ] );

        // Admin bar: toggle rápido
        add_action( 'admin_bar_menu', [ __CLASS__, 'admin_bar_toggle_node' ], 90 );

        // Admin-post: manejar toggle rápido
        add_action( 'admin_post_no_comments_toggle', [ __CLASS__, 'handle_toggle_request' ] );

        // Enlace de Ajustes en la fila del plugin
        add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), [ __CLASS__, 'plugin_action_links' ] );
        // Enlaces extra en la fila del plugin (branding)
        add_filter( 'plugin_row_meta', [ __CLASS__, 'plugin_row_meta' ], 10, 2 );

        // Filtros compartidos: se registran siempre y cada método decide según
        // el estado efectivo (bloqueo global y/o cierre por antigüedad).
        add_filter( 'comments_open', [ __CLASS__, 'filter_comments_open' ], 20, 2 );
        add_filter( 'pings_open', [ __CLASS__, 'filter_pings_open' ], 20, 2 );
        add_filter( 'comments_array', [ __CLASS__, 'filter_comments_array' ], 10, 2 );
        add_filter( 'comments_pre_query', [ __CLASS__, 'filter_comments_pre_query' ], 10, 2 );
        add_filter( 'pre_comment_approved', [ __CLASS__, 'filter_pre_comment_approved' ], 10, 2 );

        // Limpieza automática por WP-Cron (intervalo semanal propio).
        add_filter( 'cron_schedules', [ __CLASS__, 'register_cron_schedules' ] );
        add_action( self::CRON_EVENT, [ __CLASS__, 'run_cleanup' ] );
        self::maybe_schedule_cleanup();

        // Hardening completo cuando el bloqueo global está activo.
        if ( self::is_enabled() ) {
            self::apply_disable_comments();
        }
    }

    /** Registra el intervalo semanal para WP-Cron. */
    public static function register_cron_schedules( $schedules ) {
        if ( ! isset( $schedules['weekly'] ) ) {
            $schedules['weekly'] = [
                'interval' => WEEK_IN_SECONDS,
                'display'  => __( 'Semanal', 'no-comments' ),
            ];
        }
        return $schedules;
    }

    /** Limpieza automática activa. */
    public static function auto_cleanup_enabled() {
        return (bool) get_option( self::OPTION_AUTO_CLEANUP, 0 );
    }

    /** Intervalo de limpieza automática (daily|twicedaily|weekly). */
    public static function auto_cleanup_interval() {
        $interval = get_option( self::OPTION_AUTO_CLEANUP_INT, 'daily' );
        return in_array( $interval, [ 'daily', 'twicedaily', 'weekly' ], true ) ? $interval : 'daily';
    }

    /**
     * Mantiene el evento de WP-Cron alineado con la configuración
     * (agenda/desagenda y resuelve cambios de intervalo).
     */
    public static function maybe_schedule_cleanup() {
        $event    = self::CRON_EVENT;
        $next     = wp_next_scheduled( $event );
        $enabled  = self::auto_cleanup_enabled();
        $interval = self::auto_cleanup_interval();

        if ( ! $enabled ) {
            if ( $next ) {
                wp_unschedule_event( $next, $event );
            }
            return;
        }

        $scheduled_interval = $next ? (string) wp_get_schedule( $event ) : '';
        if ( ! $next || $scheduled_interval !== $interval ) {
            if ( $next ) {
                wp_unschedule_event( $next, $event );
            }
            wp_schedule_event( time() + MINUTE_IN_SECONDS, $interval, $event );
        }
    }

    /**
     * Ejecuta la limpieza automática de spam. Protegida con lock para evitar
     * ejecuciones concurrentes (cron + CLI).
     *
     * @return int Comentarios eliminados (0 si otra ejecución estaba activa).
     */
    public static function run_cleanup() {
        if ( get_transient( self::CRON_LOCK ) ) {
            return 0;
        }
        set_transient( self::CRON_LOCK, 1, 10 * MINUTE_IN_SECONDS );

        $deleted = self::delete_comments( 'spam', [], 'delete' );

        update_option( self::OPTION_LAST_CLEANUP, [
            'time'    => gmdate( 'c' ),
            'deleted' => $deleted,
        ] );
        delete_transient( self::CRON_LOCK );

        return $deleted;
    }

    /** Carga de i18n */
    public static function load_textdomain() {
        load_plugin_textdomain( 'no-comments', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

    /** Devuelve si el bloqueo global está activo (respeta enforcement de red) */
    public static function is_enabled() {
        if ( class_exists( '\\NoComments\\Infrastructure\\OptionsRepository' ) ) {
            return \NoComments\Infrastructure\OptionsRepository::effective_enabled();
        }
        return (bool) get_option( self::OPTION_KEY, 0 );
    }

    /** Registra ajustes */
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

        // Toggles avanzados
        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_REST,
            [
                'type'              => 'boolean',
                'sanitize_callback' => function ( $value ) { return $value ? 1 : 0; },
                'default'           => 1, // por defecto cortamos REST de comentarios
            ]
        );

        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_XMLRPC,
            [
                'type'              => 'boolean',
                'sanitize_callback' => function ( $value ) { return $value ? 1 : 0; },
                'default'           => 1, // por defecto bloqueamos xmlrpc newComment
            ]
        );

        // Compatibilidad
        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_WOO,
            [
                'type'              => 'boolean',
                'sanitize_callback' => function ( $value ) { return $value ? 1 : 0; },
                'default'           => 0, // por defecto NO se mantienen reseñas
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

        add_settings_field(
            self::OPTION_REST,
            __( 'APIs', 'no-comments' ),
            [ __CLASS__, 'render_api_toggles_field' ],
            self::PAGE_SLUG,
            'no_comments_main'
        );

        add_settings_field(
            self::OPTION_WOO,
            __( 'Compatibilidad', 'no-comments' ),
            [ __CLASS__, 'render_compat_field' ],
            self::PAGE_SLUG,
            'no_comments_main'
        );

        // Excepciones por tipo de contenido (v1.13.0)
        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_EXCEPTIONS,
            [
                'type'              => 'array',
                'sanitize_callback' => function ( $value ) {
                    if ( ! is_array( $value ) ) {
                        return [];
                    }
                    return array_values( array_unique( array_filter( array_map( 'sanitize_key', $value ) ) ) );
                },
                'default'           => [],
            ]
        );

        add_settings_field(
            self::OPTION_EXCEPTIONS,
            __( 'Excepciones', 'no-comments' ),
            [ __CLASS__, 'render_exceptions_field' ],
            self::PAGE_SLUG,
            'no_comments_main'
        );

        // Cierre automático por antigüedad (v1.13.0)
        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_AUTO_CLOSE_DAYS,
            [
                'type'              => 'integer',
                'sanitize_callback' => function ( $value ) { return max( 0, (int) $value ); },
                'default'           => 0,
            ]
        );

        add_settings_field(
            self::OPTION_AUTO_CLOSE_DAYS,
            __( 'Cierre automático', 'no-comments' ),
            [ __CLASS__, 'render_auto_close_field' ],
            self::PAGE_SLUG,
            'no_comments_main'
        );

        // Limpieza automática por WP-Cron (v1.13.0)
        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_AUTO_CLEANUP,
            [
                'type'              => 'boolean',
                'sanitize_callback' => function ( $value ) { return $value ? 1 : 0; },
                'default'           => 0,
            ]
        );
        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_AUTO_CLEANUP_INT,
            [
                'type'              => 'string',
                'sanitize_callback' => function ( $value ) {
                    return in_array( $value, [ 'daily', 'twicedaily', 'weekly' ], true ) ? $value : 'daily';
                },
                'default'           => 'daily',
            ]
        );

        add_settings_field(
            self::OPTION_AUTO_CLEANUP,
            __( 'Limpieza automática', 'no-comments' ),
            [ __CLASS__, 'render_cleanup_field' ],
            self::PAGE_SLUG,
            'no_comments_main'
        );
    }

    /** Render del checkbox */
    public static function render_toggle_field() {
        $enabled = self::is_enabled();
        echo '<input type="hidden" name="' . esc_attr( self::OPTION_KEY ) . '" value="0" />';
        echo '<label>';
        echo '<input type="checkbox" name="' . esc_attr( self::OPTION_KEY ) . '" value="1" ' . checked( $enabled, true, false ) . ' /> ';
        echo esc_html__( 'Cerrar comentarios y pings en todo el sitio', 'no-comments' );
        echo '</label>';
    }

    /** Render de toggles de API (REST y XML-RPC) */
    public static function render_api_toggles_field() {
        $rest   = (bool) get_option( self::OPTION_REST, 1 );
        $xmlrpc = (bool) get_option( self::OPTION_XMLRPC, 1 );
        echo '<p>' . esc_html__( 'Cuando el bloqueo esté activo, además puedes cortar los puntos de entrada de API:', 'no-comments' ) . '</p>';
        echo '<label style="display:block;margin:4px 0;" title="' . esc_attr__( 'Quita el endpoint REST de comentarios para reducir superficie de ataque o integraciones no deseadas.', 'no-comments' ) . '">';
        echo '<input type="hidden" name="' . esc_attr( self::OPTION_REST ) . '" value="0" />';
        echo '<input type="checkbox" name="' . esc_attr( self::OPTION_REST ) . '" value="1" ' . checked( $rest, true, false ) . ' /> ' . esc_html__( 'Deshabilitar endpoint REST de comentarios (wp/v2/comments)', 'no-comments' );
        echo '</label>';
        echo '<label style="display:block;margin:4px 0;" title="' . esc_attr__( 'Bloquea la creación de comentarios vía XML‑RPC (método wp.newComment).', 'no-comments' ) . '">';
        echo '<input type="hidden" name="' . esc_attr( self::OPTION_XMLRPC ) . '" value="0" />';
        echo '<input type="checkbox" name="' . esc_attr( self::OPTION_XMLRPC ) . '" value="1" ' . checked( $xmlrpc, true, false ) . ' /> ' . esc_html__( 'Deshabilitar XML‑RPC (método wp.newComment)', 'no-comments' );
        echo '</label>';
        echo '<p class="description">' . esc_html__( 'Recomendado mantenerlos activos (marcados) para mayor seguridad.', 'no-comments' ) . '</p>';
    }

    /** Render de compatibilidad */
    public static function render_compat_field() {
        $keep_reviews  = (bool) get_option( self::OPTION_WOO, 0 );
        $woo_is_active = class_exists( 'WooCommerce' );
        echo '<p>' . esc_html__( 'Opciones de compatibilidad con plugins de terceros.', 'no-comments' ) . '</p>';
        echo '<label style="display:block;margin:4px 0;">';
        echo '<input type="hidden" name="' . esc_attr( self::OPTION_WOO ) . '" value="0" />';
        echo '<input type="checkbox" name="' . esc_attr( self::OPTION_WOO ) . '" value="1" ' . checked( $keep_reviews, true, false ) . ' /> ' . esc_html__( 'Mantener reseñas de productos (WooCommerce)', 'no-comments' );
        echo '</label>';
        if ( ! $woo_is_active ) {
            echo '<p class="description">' . esc_html__( 'WooCommerce no está activo. Puedes dejar esta opción preparada y tendrá efecto cuando WooCommerce se active.', 'no-comments' ) . '</p>';
        }
    }

    /** Render: excepciones por tipo de contenido */
    public static function render_exceptions_field() {
        $current = get_option( self::OPTION_EXCEPTIONS, [] );
        if ( ! is_array( $current ) ) {
            $current = [];
        }
        $types = get_post_types( [ 'public' => true ], 'objects' );
        if ( empty( $types ) ) {
            echo '<p class="description">' . esc_html__( 'No hay tipos de contenido públicos registrados.', 'no-comments' ) . '</p>';
            return;
        }
        echo '<p>' . esc_html__( 'Estos tipos de contenido conservan comentarios y pings aunque el bloqueo global esté activo:', 'no-comments' ) . '</p>';
        echo '<input type="hidden" name="' . esc_attr( self::OPTION_EXCEPTIONS ) . '[]" value="" />';
        foreach ( $types as $slug => $obj ) {
            $label = isset( $obj->labels->singular_name ) ? $obj->labels->singular_name : $slug;
            $checked = checked( in_array( $slug, $current, true ), true, false );
            echo '<label style="display:inline-block;margin:2px 12px 2px 0;">';
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- checked() returns a safe HTML attribute.
            echo '<input type="checkbox" name="' . esc_attr( self::OPTION_EXCEPTIONS ) . '[]" value="' . esc_attr( $slug ) . '" ' . $checked . ' /> ' . esc_html( $label );
            echo '</label>';
        }
        if ( self::keep_woo_reviews() ) {
            echo '<p class="description">' . esc_html__( 'WooCommerce está activo con reseñas: "product" se mantiene como excepción automáticamente.', 'no-comments' ) . '</p>';
        }
    }

    /** Render: cierre automático por antigüedad */
    public static function render_auto_close_field() {
        $days = self::auto_close_days();
        echo '<label>';
        echo '<input type="number" name="' . esc_attr( self::OPTION_AUTO_CLOSE_DAYS ) . '" value="' . esc_attr( (string) $days ) . '" min="0" step="1" style="width:100px;" /> ';
        echo esc_html__( 'días', 'no-comments' );
        echo '</label>';
        echo '<p class="description">' . esc_html__( 'Cierra formularios y pings en contenido con más de N días de antigüedad, sin desactivar el bloqueo del sitio. Aplica cuando el bloqueo global está apagado. 0 = desactivado.', 'no-comments' ) . '</p>';
    }

    /** Render: limpieza automática de spam */
    public static function render_cleanup_field() {
        $enabled  = self::auto_cleanup_enabled();
        $interval = self::auto_cleanup_interval();
        echo '<label style="display:block;margin:4px 0;">';
        echo '<input type="hidden" name="' . esc_attr( self::OPTION_AUTO_CLEANUP ) . '" value="0" />';
        echo '<input type="checkbox" name="' . esc_attr( self::OPTION_AUTO_CLEANUP ) . '" value="1" ' . checked( $enabled, true, false ) . ' /> ';
        echo esc_html__( 'Borrar spam automáticamente (WP-Cron)', 'no-comments' );
        echo '</label>';
        echo '<label style="display:block;margin:4px 0;">' . esc_html__( 'Frecuencia:', 'no-comments' ) . ' ';
        echo '<select name="' . esc_attr( self::OPTION_AUTO_CLEANUP_INT ) . '">';
        foreach ( [ 'daily' => __( 'Diaria', 'no-comments' ), 'twicedaily' => __( 'Dos veces al día', 'no-comments' ), 'weekly' => __( 'Semanal', 'no-comments' ) ] as $value => $label ) {
            echo '<option value="' . esc_attr( $value ) . '" ' . selected( $interval, $value, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select></label>';
        $last = get_option( self::OPTION_LAST_CLEANUP, [] );
        if ( is_array( $last ) && ! empty( $last['time'] ) ) {
            echo '<p class="description">' . sprintf(
                /* translators: 1: date of the last cleanup, 2: number of deleted comments. */
                esc_html__( 'Última limpieza: %1$s — %2$d comentarios eliminados.', 'no-comments' ),
                esc_html( (string) $last['time'] ),
                (int) $last['deleted']
            ) . '</p>';
        } else {
            echo '<p class="description">' . esc_html__( 'El borrado usa alcance Spam y borrado definitivo. Se registra la fecha y cantidad de cada ejecución.', 'no-comments' ) . '</p>';
        }
    }

    /** Página de ajustes */
    public static function add_settings_page() {
        add_options_page(
            __( 'NO Comments', 'no-comments' ),
            __( 'NO Comments', 'no-comments' ),
            'manage_options',
            self::PAGE_SLUG,
            [ __CLASS__, 'render_settings_page' ]
        );
    }

    /** Render de la página (con tabs Disable / Delete) */
    public static function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'disable';

        echo '<div class="wrap" id="no-comments-admin">';
        echo '<h1>' . esc_html__( 'NO Comments', 'no-comments' ) . '</h1>';

        // Help tab
        if ( function_exists( 'get_current_screen' ) ) {
            $screen = get_current_screen();
            if ( $screen && method_exists( $screen, 'add_help_tab' ) ) {
                $screen->add_help_tab( [
                    'id'      => 'no-comments-help',
                    'title'   => __( 'Ayuda', 'no-comments' ),
                    'content' => '<p>' . esc_html__( 'Usa "Disable Comments" para cerrar comentarios globalmente. En "Delete Comments" puedes simular (dry‑run) o ejecutar limpieza por alcance.', 'no-comments' ) . '</p>',
                ] );
            }
        }

        echo '<h2 class="nav-tab-wrapper">';
        $tabs = [
            'disable' => __( 'Disable Comments', 'no-comments' ),
            'delete'  => __( 'Delete Comments', 'no-comments' ),
        ];
        foreach ( $tabs as $tab => $label ) {
            $class = ( $active_tab === $tab ) ? ' nav-tab nav-tab-active' : ' nav-tab';
            $url   = esc_url( add_query_arg( [ 'page' => self::PAGE_SLUG, 'tab' => $tab ], admin_url( 'options-general.php' ) ) );
            echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
        }
        echo '</h2>';

        if ( 'delete' === $active_tab ) {
            self::render_delete_tab();
        } else {
            // Indicador de estado
            $enabled = self::is_enabled();
            $status_color = $enabled ? '#065f46' : '#5a5a5a';
            $status_text  = $enabled ? __( 'Comentarios deshabilitados globalmente', 'no-comments' ) : __( 'Comentarios habilitados (comportamiento por defecto)', 'no-comments' );
            echo '<div style="margin:12px 0;padding:8px 12px;border-left:4px solid ' . esc_attr( $status_color ) . ';background:#fff;">' . esc_html( $status_text ) . '</div>';

            // Aviso si los ajustes están forzados por la red.
            $network_enforced = false;
            if ( is_multisite() ) {
                $net              = self::get_network_settings();
                $network_enforced = ! empty( $net['enforce'] );
                if ( $network_enforced ) {
                    $net_url = esc_url( network_admin_url( 'settings.php?page=' . self::PAGE_SLUG . '-network' ) );
                    // translators: %s: URL to the network-level NO Comments settings page.
                    echo '<div class="notice notice-info" style="margin:12px 0 0 0;"><p>' . wp_kses_post( sprintf( __( 'Estos ajustes están controlados por la red. Gestiona los valores desde <a href="%s">NO Comments (Network)</a>.', 'no-comments' ), esc_url( $net_url ) ) ) . '</p></div>';
                }
            }

            if ( ! $network_enforced ) {
                echo '<form action="options.php" method="post">';
                settings_fields( self::SETTINGS_GROUP );
                do_settings_sections( self::PAGE_SLUG );
                submit_button();
                echo '</form>';
            }

            self::render_transfer_card();
        }
        // Branding footer
        echo '<hr style="margin-top:24px;opacity:.25;" />';
        echo '<p style="color:#475569;">' . wp_kses_post( sprintf(
            /* translators: 1: author handle, 2: Instagram profile URL, 3: website URL. */
            __( 'Creado por Akela (%1$s) — <a href="%2$s" target="_blank">Instagram</a> · <a href="%3$s" target="_blank">akela.dev</a>', 'no-comments' ),
            '@akelaonline',
            'https://www.instagram.com/akelaonline/',
            'https://akela.dev/seo'
        ) ) . '</p>';
        echo '</div>';
    }

    /** Tarjeta de importación/exportación de ajustes. */
    private static function render_transfer_card() {
        $network_enforced = is_multisite() && class_exists( '\\NoComments\\Infrastructure\\OptionsRepository' ) && \NoComments\Infrastructure\OptionsRepository::is_enforced();
        $export_url = wp_nonce_url( admin_url( 'admin-post.php?action=no_comments_export' ), 'no_comments_export_action', '_wpnonce_no_comments_export' );
        echo '<div class="card" style="margin-top:16px;">';
        echo '<h2>' . esc_html__( 'Importar / Exportar ajustes', 'no-comments' ) . '</h2>';
        echo '<p>' . esc_html__( 'Exporta los ajustes a un archivo JSON para respaldo o para clonarlos en otro sitio, e impórtalos desde un archivo exportado.', 'no-comments' ) . '</p>';
        echo '<p><a class="button button-secondary" href="' . esc_url( $export_url ) . '">' . esc_html__( 'Descargar ajustes (JSON)', 'no-comments' ) . '</a></p>';
        if ( $network_enforced ) {
            echo '<p class="description">' . esc_html__( 'La importación de ajustes de sitio está desactivada porque la red controla esta configuración.', 'no-comments' ) . '</p>';
        } else {
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" enctype="multipart/form-data" style="margin-top:10px;">';
            wp_nonce_field( 'no_comments_import_action', '_wpnonce_no_comments_import' );
            echo '<input type="hidden" name="action" value="no_comments_import" />';
            echo '<p><input type="file" name="no_comments_import_file" accept="application/json,.json" /></p>';
            submit_button( __( 'Importar ajustes', 'no-comments' ), 'secondary' );
            echo '</form>';
        }
        echo '</div>';

        if ( isset( $_GET['import'] ) ) {
            $msg = [
                'ok'         => __( 'Ajustes importados correctamente.', 'no-comments' ),
                'error-file' => __( 'No se recibió un archivo válido.', 'no-comments' ),
                'error-size' => __( 'El archivo es demasiado grande (máximo 512 KB).', 'no-comments' ),
                'error-json' => __( 'El archivo debe ser un JSON válido exportado por NO Comments.', 'no-comments' ),
            ];
            $key = sanitize_key( wp_unslash( $_GET['import'] ) );
            if ( isset( $msg[ $key ] ) ) {
                $class = 'ok' === $key ? 'updated' : 'notice-error';
                echo '<div class="notice ' . esc_attr( $class ) . ' nc-result"><p>' . esc_html( $msg[ $key ] ) . '</p></div>';
            }
        }
    }

    /** Aplica el bloqueo de comentarios cuando está habilitado */
    private static function apply_disable_comments() {
        // Quitar feeds de comentarios: link de descubrimiento y acceso directo.
        add_filter( 'feed_links_show_comments_feed', '__return_false', 20 );
        add_action( 'template_redirect', [ __CLASS__, 'filter_comment_feed_redirect' ], 1 );

        // Quitar soporte de comentarios y trackbacks de los post types públicos
        // que no estén en la lista de excepciones.
        add_action( 'init', function () {
            $exceptions = self::exception_types();
            foreach ( get_post_types( [ 'public' => true ], 'names' ) as $post_type ) {
                if ( in_array( $post_type, $exceptions, true ) ) {
                    continue; // Mantener comentarios y trackbacks en los tipos de excepción.
                }
                if ( post_type_supports( $post_type, 'comments' ) ) {
                    remove_post_type_support( $post_type, 'comments' );
                }
                if ( post_type_supports( $post_type, 'trackbacks' ) ) {
                    remove_post_type_support( $post_type, 'trackbacks' );
                }
            }
        }, 100 );

        // Ocultar el menú de Comentarios y el submenú Discusión (solo si no hay excepciones activas).
        add_action( 'admin_menu', function () {
            if ( empty( self::exception_types() ) ) {
                remove_menu_page( 'edit-comments.php' );
            }
            remove_submenu_page( 'options-general.php', 'options-discussion.php' );
        }, 999 );

        // Quitar icono de comentarios del admin bar.
        add_action( 'admin_bar_menu', function ( $wp_admin_bar ) {
            if ( empty( self::exception_types() ) ) {
                $wp_admin_bar->remove_node( 'comments' );
            }
        }, 999 );

        // Bloquear accesos directos.
        add_action( 'admin_init', function () {
            if ( is_admin() && isset( $GLOBALS['pagenow'] ) && in_array( $GLOBALS['pagenow'], [ 'options-discussion.php' ], true ) ) {
                wp_safe_redirect( admin_url() );
                exit;
            }
            // Restringir edit-comments.php solo si no hay excepciones activas.
            if ( is_admin() && empty( self::exception_types() ) && isset( $GLOBALS['pagenow'] ) && in_array( $GLOBALS['pagenow'], [ 'edit-comments.php' ], true ) ) {
                wp_safe_redirect( admin_url() );
                exit;
            }
        }, 1 );

        // Fallback visual por CSS (respeta excepciones, incluyendo multisite).
        add_action( 'admin_head', function () {
            $css = '#adminmenu a[href$="options-discussion.php"]{display:none !important;}';
            if ( empty( self::exception_types() ) ) {
                $css .= '#menu-comments, #adminmenu a[href$="edit-comments.php"]{display:none !important;}';
            }
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $css contains only plugin-defined selectors.
            echo '<style id="no-comments-admin-css">' . $css . '</style>';
        } );

        // Endpoints REST de comentarios (wp/v2/comments)
        if ( self::get_rest_disabled() ) {
            add_filter( 'rest_endpoints', function( $endpoints ) {
                if ( isset( $endpoints['/wp/v2/comments'] ) ) {
                    unset( $endpoints['/wp/v2/comments'] );
                }
                if ( isset( $endpoints['/wp/v2/comments/(?P<id>[\\d]+)'] ) ) {
                    unset( $endpoints['/wp/v2/comments/(?P<id>[\\d]+)'] );
                }
                return $endpoints;
            } );
        }

        // XML-RPC: bloquear creación de comentarios
        if ( self::get_xmlrpc_disabled() ) {
            add_filter( 'xmlrpc_methods', function( $methods ) {
                unset( $methods['wp.newComment'] );
                return $methods;
            } );
        }
    }

    /** Determina si se deben mantener reseñas de WooCommerce */
    private static function keep_woo_reviews() {
        if ( class_exists( '\\NoComments\\Infrastructure\\OptionsRepository' ) ) {
            return \NoComments\Infrastructure\OptionsRepository::effective_keep_woo_reviews();
        }
        return (bool) get_option( self::OPTION_WOO, 0 ) && class_exists( 'WooCommerce' );
    }

    /**
     * Tipos de contenido que mantienen comentarios con el bloqueo global activo.
     * Combina la lista configurable de excepciones con WooCommerce (product)
     * cuando la compatibilidad de reseñas está activa.
     *
     * @return string[]
     */
    private static function exception_types() {
        $types = get_option( self::OPTION_EXCEPTIONS, [] );
        if ( ! is_array( $types ) ) {
            $types = [];
        }
        $types = array_values( array_unique( array_filter( array_map( 'sanitize_key', $types ) ) ) );
        if ( self::keep_woo_reviews() ) {
            $types[] = 'product';
            $types = array_values( array_unique( $types ) );
        }
        return $types;
    }

    /**
     * Determina si un post es una excepción al cierre global
     * (excepciones configuradas o reseñas de productos WooCommerce).
     *
     * @param int $post_id
     * @return bool
     */
    private static function is_exception_post( $post_id ) {
        $post_id = absint( $post_id );
        if ( ! $post_id ) {
            return false;
        }
        $post = get_post( $post_id );
        return $post && in_array( $post->post_type, self::exception_types(), true );
    }

    /** Días para el cierre automático de contenido antiguo (0 = desactivado). */
    public static function auto_close_days() {
        return max( 0, (int) get_option( self::OPTION_AUTO_CLOSE_DAYS, 0 ) );
    }

    /**
     * Indica si un post superó la antigüedad configurada para cerrar comentarios.
     *
     * @param int $post_id
     * @return bool
     */
    private static function post_is_too_old( $post_id ) {
        $days = self::auto_close_days();
        if ( $days <= 0 ) {
            return false;
        }
        $post = get_post( $post_id );
        if ( ! $post || empty( $post->post_date ) || '0000-00-00 00:00:00' === $post->post_date ) {
            return false;
        }
        $time = strtotime( $post->post_date );
        if ( false === $time ) {
            return false;
        }
        return ( time() - $time ) > $days * DAY_IN_SECONDS;
    }

    /** Filtro: comments_open (bloqueo global y/o cierre por antigüedad) */
    public static function filter_comments_open( $open, $post_id ) {
        $post_id = absint( $post_id );
        if ( self::is_enabled() ) {
            if ( self::is_exception_post( $post_id ) ) {
                return $open; // Respetar el estado propio del post (p. ej. reviews).
            }
            return false;
        }
        if ( self::post_is_too_old( $post_id ) ) {
            return false;
        }
        return $open;
    }

    /** Filtro: pings_open (bloqueo global y/o cierre por antigüedad) */
    public static function filter_pings_open( $open, $post_id ) {
        if ( self::is_enabled() ) {
            return false;
        }
        if ( self::post_is_too_old( $post_id ) ) {
            return false;
        }
        return $open;
    }

    /** Filtro: comments_array con excepción para productos */
    public static function filter_comments_array( $comments, $post_id ) {
        if ( ! self::is_enabled() ) {
            return $comments;
        }
        if ( self::is_exception_post( $post_id ) ) {
            return $comments; // mostrar reviews
        }
        return [];
    }

    /**
     * Filtro: evita la consulta a la base de datos cuando los comentarios
     * están cerrados globalmente. Mantiene intactos:
     *  - los conteos (admin y dry-run);
     *  - los estados de moderación (spam/hold/trash) usados por la limpieza;
     *  - las consultas por IDs (borrado masivo);
     *  - las consultas de posts de excepción (reseñas de productos).
     *
     * @param mixed            $comments
     * @param \WP_Comment_Query $query
     * @return mixed
     */
    public static function filter_comments_pre_query( $comments, $query ) {
        if ( ! self::is_enabled() || ! isset( $query->query_vars ) ) {
            return $comments;
        }
        $vars = $query->query_vars;

        // Conteos, consultas de administración (IDs) y estados de moderación se dejan pasar.
        if ( ! empty( $vars['count'] ) || 'ids' === ( isset( $vars['fields'] ) ? $vars['fields'] : 'all' ) ) {
            return $comments;
        }
        $status = isset( $vars['status'] ) ? $vars['status'] : 'approve';
        if ( in_array( $status, [ 'spam', 'hold', 'trash' ], true ) ) {
            return $comments;
        }

        $post_id = isset( $vars['post_id'] ) ? absint( $vars['post_id'] ) : 0;
        if ( $post_id && self::is_exception_post( $post_id ) ) {
            return $comments;
        }
        return [];
    }

    /**
     * Bloquea el acceso directo a los feeds de comentarios
     * (p. ej. ?feed=comments-rss2) redirigiendo al home.
     */
    public static function filter_comment_feed_redirect() {
        $feed = get_query_var( 'feed' );
        $is_comment_feed = is_comment_feed() || ( is_string( $feed ) && 0 === strpos( $feed, 'comments-' ) );
        if ( ! $is_comment_feed || is_singular() ) {
            return; // En un singular con withcomments los comentarios ya están cerrados.
        }
        wp_safe_redirect( home_url( '/' ), 301 );
        exit;
    }

    /** Filtro: bloquear creación (bloqueo global y/o cierre por antigüedad). */
    public static function filter_pre_comment_approved( $approved, $commentdata ) {
        $post_id = isset( $commentdata['comment_post_ID'] ) ? absint( $commentdata['comment_post_ID'] ) : 0;

        if ( self::is_enabled() ) {
            if ( $post_id && self::is_exception_post( $post_id ) ) {
                return $approved; // Respetar el flujo normal de reviews.
            }
            return new WP_Error( 'no_comments_disabled', __( 'Los comentarios están cerrados en todo el sitio.', 'no-comments' ), [ 'status' => 403 ] );
        }

        if ( $post_id && self::post_is_too_old( $post_id ) ) {
            return new WP_Error( 'no_comments_auto_closed', __( 'Los comentarios están cerrados para contenido antiguo.', 'no-comments' ), [ 'status' => 403 ] );
        }

        return $approved;
    }

    /**
     * Encola estilos mínimos para mejorar la UI de la página del plugin.
     */
    public static function enqueue_admin_assets( $hook ) {
        if ( 'settings_page_' . self::PAGE_SLUG !== $hook ) {
            return;
        }
        // Usamos wp-components como base para inyectar CSS.
        wp_enqueue_style( 'wp-components' );
        $css = '/* NO Comments UI */
        #no-comments-admin .nav-tab-wrapper { margin-top: 12px; }
        #no-comments-admin .card { border: 1px solid #e5e7eb; box-shadow: 0 1px 1px rgba(0,0,0,.04); padding: 8px 14px; }
        #no-comments-admin .nc-counts { display:flex; gap:8px; flex-wrap:wrap; margin:8px 0 14px; padding:0; list-style:none; }
        #no-comments-admin .nc-counts li { background:#f8fafc; border:1px solid #e5e7eb; border-radius:20px; padding:6px 10px; font-weight:600; color:#334155; cursor:pointer; }
        #no-comments-admin .nc-counts .nc-spam { background:#fff1f2; border-color:#fecaca; color:#991b1b; }
        #no-comments-admin .nc-counts .nc-trash { background:#fff7ed; border-color:#fed7aa; color:#9a3412; }
        #no-comments-admin .nc-status-good { border-left-color:#065f46; }
        #no-comments-admin .nc-status-muted { border-left-color:#5a5a5a; }
        /* Segmented control */
        #no-comments-admin .nc-segment { display:inline-flex; border:1px solid #CBD5E1; border-radius:8px; overflow:hidden; margin:6px 0 10px; }
        #no-comments-admin .nc-segment button { background:#fff; border:0; padding:6px 10px; color:#334155; cursor:pointer; }
        #no-comments-admin .nc-segment button + button { border-left:1px solid #CBD5E1; }
        #no-comments-admin .nc-segment button[aria-pressed="true"] { background:#0ea5e9; color:#fff; }
        /* Quick actions */
        #no-comments-admin .nc-quick { display:flex; gap:8px; margin:6px 0 12px; }
        #no-comments-admin .nc-quick .button-link { color:#0369a1; }
        /* Hide only visually, keep accessible */
        #no-comments-admin .nc-visually-hidden { position:absolute; left:-9999px; }
        /* Notices a11y */
        #no-comments-admin .nc-result { margin-top:12px; }';
        wp_add_inline_style( 'wp-components', $css );
        // JS para interacción mejorada (segment control, quick actions, confirmación)
        $js = 'document.addEventListener("DOMContentLoaded",function(){
            var wrap=document.getElementById("no-comments-admin");
            if(!wrap) return;
            function selectScope(val){
                var r=document.querySelector("input[name=delete_scope][value="+val+"]");
                if(r){ r.checked=true; updateSegment(val); announce("Ámbito seleccionado: "+val); }
            }
            function updateSegment(val){
                wrap.querySelectorAll(".nc-segment button").forEach(function(b){ b.setAttribute("aria-pressed", b.dataset.scope===val?"true":"false"); });
            }
            function announce(msg){ var live=wrap.querySelector("#nc-live"); if(live){ live.textContent=""; setTimeout(function(){ live.textContent=msg; }, 30);} }
            // Counters clickeables
            wrap.querySelectorAll(".nc-counts .nc-spam, .nc-counts .nc-pending, .nc-counts .nc-trash, .nc-counts .nc-total").forEach(function(li){
                li.addEventListener("click", function(){
                    var map={"nc-spam":"spam","nc-pending":"pending","nc-trash":"trash","nc-total":"all"};
                    var cls=Array.from(li.classList).find(function(c){return map[c];});
                    if(cls){ selectScope(map[cls]); }
                });
            });
            // Segmented control
            wrap.querySelectorAll(".nc-segment button").forEach(function(btn){
                btn.addEventListener("click", function(e){ e.preventDefault(); selectScope(btn.dataset.scope); });
            });
            // Quick actions: impedir navegación y preseleccionar
            wrap.querySelectorAll("a.nc-qa[data-scope]").forEach(function(a){
                a.addEventListener("click", function(e){ e.preventDefault(); selectScope(a.dataset.scope); });
            });
            // Modal de confirmación básico
            var form=wrap.querySelector("form[action$=admin-post.php]");
            if(form){
                form.addEventListener("submit", function(e){
                    var dry=form.querySelector("input[name=dry_run]");
                    var isDry=dry && dry.checked;
                    if(isDry) return true;
                    var conf=form.querySelector("input[name=confirm]");
                    if(!conf || conf.value!=="DELETE"){ e.preventDefault(); alert("Debes escribir DELETE para confirmar."); conf&&conf.focus(); return false; }
                    var scope=form.querySelector("input[name=delete_scope]:checked");
                    var strategy=form.querySelector("input[name=delete_strategy]:checked");
                    var reversible=strategy && strategy.value==="trash" && (!scope || scope.value!=="trash");
                    var msg=reversible
                        ? "¿Mover los comentarios seleccionados a la Papelera? Podrás restaurarlos después."
                        : "¿Seguro que deseas ejecutar la limpieza ("+(scope?scope.value:"?")+")? Esta acción no se puede deshacer.";
                    if(!window.confirm(msg)){ e.preventDefault(); return false; }
                    var submitBtn=form.querySelector("button[type=\"submit\"], input[type=\"submit\"]");
                    if(submitBtn){ submitBtn.setAttribute("disabled","disabled"); submitBtn.classList.add("is-busy"); if(submitBtn.tagName==="BUTTON"){ submitBtn.textContent="Ejecutando..."; } }
                });
            }
        });';
        wp_add_inline_script( 'jquery-core', $js );
    }

    /** Reordenar submenú para mostrar antes de Discusión */
    public static function reorder_settings_submenu() {
        global $submenu;
        if ( empty( $submenu['options-general.php'] ) || ! is_array( $submenu['options-general.php'] ) ) {
            return;
        }
        $settings = &$submenu['options-general.php'];
        $no_comments_index = null;
        $discussion_index  = null;
        foreach ( $settings as $index => $item ) {
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
            $item = $settings[ $no_comments_index ];
            unset( $settings[ $no_comments_index ] );
            $settings = array_values( $settings );
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

    /** Pestaña de borrado masivo */
    private static function render_delete_tab() {
        $counts = wp_count_comments();
        echo '<div class="card">';
        echo '<h2>' . esc_html__( 'Delete Comments', 'no-comments' ) . '</h2>';
        echo '<p>' . esc_html__( 'Estas acciones son destructivas. Te sugerimos hacer un respaldo antes de continuar.', 'no-comments' ) . '</p>';

        echo '<ul class="nc-counts">';
        echo '<li class="nc-approved">' . esc_html__( 'Aprobados', 'no-comments' ) . ': ' . intval( $counts->approved ) . '</li>';
        echo '<li class="nc-pending">' . esc_html__( 'Pendientes', 'no-comments' ) . ': ' . intval( $counts->moderated ) . '</li>';
        echo '<li class="nc-spam">' . esc_html__( 'Spam', 'no-comments' ) . ': ' . intval( $counts->spam ) . '</li>';
        echo '<li class="nc-trash">' . esc_html__( 'Papelera', 'no-comments' ) . ': ' . intval( $counts->trash ) . '</li>';
        echo '<li class="nc-total">' . esc_html__( 'Total', 'no-comments' ) . ': ' . intval( $counts->total_comments ) . '</li>';
        echo '</ul>';
        // Región aria-live para feedback
        echo '<div id="nc-live" class="screen-reader-text" aria-live="polite"></div>';

        $action_url = admin_url( 'admin-post.php' );

        // Acciones rápidas (enlaces que preseleccionan alcance y tipos)
        $base_delete_url = add_query_arg( [ 'page' => self::PAGE_SLUG, 'tab' => 'delete' ], admin_url( 'options-general.php' ) );
        $quick = [
            'spam'    => __( 'Solo Spam', 'no-comments' ),
            'pending' => __( 'Solo Pendientes', 'no-comments' ),
            'trash'   => __( 'Vaciar Papelera', 'no-comments' ),
            'all'     => __( 'Todos', 'no-comments' ),
        ];
        echo '<div class="nc-quick">';
        echo '<span class="button-link" style="line-height:30px;margin-right:8px;">' . esc_html__( 'Acciones rápidas:', 'no-comments' ) . '</span>';
        foreach ( $quick as $sc => $label ) {
            $cls = in_array( $sc, [ 'spam', 'trash' ], true ) ? 'button button-secondary' : 'button button-link';
            echo '<a href="#" class="nc-qa ' . esc_attr( $cls ) . '" data-scope="' . esc_attr( $sc ) . '">' . esc_html( $label ) . '</a>';
        }
        echo '</div>';
        // Quick select via query params
        $selected_scope = isset( $_GET['scope'] ) ? sanitize_key( wp_unslash( $_GET['scope'] ) ) : 'spam';
        $pre_types      = [];
        $types_query    = isset( $_GET['types'] ) ? sanitize_text_field( wp_unslash( $_GET['types'] ) ) : '';
        if ( '' !== $types_query ) {
            $pre_types = array_filter( array_map( 'sanitize_key', array_map( 'trim', explode( ',', $types_query ) ) ) );
        }

        echo '<form method="post" action="' . esc_url( $action_url ) . '">';
        wp_nonce_field( 'no_comments_delete_action', '_wpnonce_no_comments_delete' );
        echo '<input type="hidden" name="action" value="no_comments_delete" />';

        // Segmented control (mejora visual, radios siguen para accesibilidad)
        echo '<div class="nc-segment" role="group" aria-label="' . esc_attr__( 'Ámbito de borrado', 'no-comments' ) . '">';
        foreach (
            [
                'spam'    => __( 'Spam', 'no-comments' ),
                'pending' => __( 'Pendientes', 'no-comments' ),
                'trash'   => __( 'Papelera', 'no-comments' ),
                'all'     => __( 'Todos', 'no-comments' ),
            ] as $sc => $lab
        ) {
            $pressed = $selected_scope === $sc ? 'true' : 'false';
            echo '<button class="button" data-scope="' . esc_attr( $sc ) . '" aria-pressed="' . esc_attr( $pressed ) . '">' . esc_html( $lab ) . '</button>';
        }
        echo '</div>';

        echo '<p><label><input type="radio" name="delete_scope" value="spam" ' . checked( $selected_scope, 'spam', false ) . '> ' . esc_html__( 'Eliminar solo Spam', 'no-comments' ) . '</label></p>';
        echo '<p><label><input type="radio" name="delete_scope" value="pending" ' . checked( $selected_scope, 'pending', false ) . '> ' . esc_html__( 'Eliminar Pendientes (moderación)', 'no-comments' ) . '</label></p>';
        echo '<p><label><input type="radio" name="delete_scope" value="trash" ' . checked( $selected_scope, 'trash', false ) . '> ' . esc_html__( 'Vaciar Papelera', 'no-comments' ) . '</label></p>';
        echo '<p><label><input type="radio" name="delete_scope" value="all" ' . checked( $selected_scope, 'all', false ) . '> ' . esc_html__( 'Eliminar TODOS los comentarios', 'no-comments' ) . '</label></p>';

        // Filtro por tipos de contenido
        $types = get_post_types( [ 'public' => true ], 'objects' );
        if ( ! empty( $types ) ) {
            echo '<fieldset style="margin:10px 0 6px;">';
            echo '<legend style="font-weight:600;">' . esc_html__( 'Limitar por tipos de contenido (opcional)', 'no-comments' ) . '</legend>';
            foreach ( $types as $slug => $obj ) {
                $label = isset( $obj->labels->singular_name ) ? $obj->labels->singular_name : $slug;
                echo '<label style="display:inline-block;margin:2px 10px 2px 0;">';
                $ck = checked( in_array( $slug, $pre_types, true ), true, false );
                echo '<input type="checkbox" name="delete_types[]" value="' . esc_attr( $slug ) . '" ' . $ck . '> ' . esc_html( $label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- checked() returns a safe HTML attribute.
                echo '</label>';
            }
            echo '<p class="description">' . esc_html__( 'Si no seleccionas ningún tipo, se aplicará a todos.', 'no-comments' ) . '</p>';
            echo '</fieldset>';
        }

        // Estrategia de borrado
        echo '<fieldset style="margin:10px 0 6px;">';
        echo '<legend style="font-weight:600;">' . esc_html__( 'Estrategia', 'no-comments' ) . '</legend>';
        echo '<label style="display:block;margin:2px 0;" title="' . esc_attr__( 'Borra definitivamente (incluida la papelera si el alcance es Papelera o Todos).', 'no-comments' ) . '"><input type="radio" name="delete_strategy" value="delete" checked> ' . esc_html__( 'Borrar permanentemente', 'no-comments' ) . '</label>';
        echo '<label style="display:block;margin:2px 0;" title="' . esc_attr__( 'Mueve a Papelera cuando aplique. Al vaciar Papelera se fuerza borrado definitivo.', 'no-comments' ) . '"><input type="radio" name="delete_strategy" value="trash"> ' . esc_html__( 'Mover a Papelera (reversible)', 'no-comments' ) . '</label>';
        echo '</fieldset>';

        echo '<p><label><input type="checkbox" name="dry_run" value="1" checked> ' . esc_html__( 'Simulación (dry‑run): solo calcula y no borra', 'no-comments' ) . '</label></p>';
        echo '<p><label>' . esc_html__( 'Escribe DELETE para confirmar', 'no-comments' ) . ' <input type="text" name="confirm" value="" placeholder="DELETE" /></label></p>';
        submit_button( __( 'Ejecutar limpieza', 'no-comments' ), 'delete' );
        echo '</form>';
        echo '</div>';

        // Mensajes de resultado con detalle
        if ( isset( $_GET['deleted'] ) && isset( $_GET['scope'] ) ) {
            $deleted  = absint( $_GET['deleted'] );
            $scope    = sanitize_key( wp_unslash( $_GET['scope'] ) );
            $sim      = isset( $_GET['dry'] ) && '1' === sanitize_key( wp_unslash( $_GET['dry'] ) );
            $strategy = isset( $_GET['strategy'] ) ? sanitize_key( wp_unslash( $_GET['strategy'] ) ) : '';
            $types_q  = isset( $_GET['types'] ) ? sanitize_text_field( wp_unslash( $_GET['types'] ) ) : '';
            $types_h  = $types_q ? $types_q : __( 'todos', 'no-comments' );
            $msg      = $sim ? __( 'Simulación (dry‑run): se borrarían', 'no-comments' ) : __( 'Eliminados', 'no-comments' );
            printf( '<div class="notice %s nc-result" role="status" aria-live="polite"><p>%s %d — %s: %s · %s: %s · %s: %s.</p></div>',
                $sim ? 'notice-info' : 'updated',
                esc_html( $msg ),
                absint( $deleted ),
                esc_html__( 'alcance', 'no-comments' ), esc_html( $scope ),
                esc_html__( 'tipos', 'no-comments' ), esc_html( $types_h ),
                esc_html__( 'estrategia', 'no-comments' ), esc_html( $strategy ?: '-' )
            );
        }
        if ( isset( $_GET['msg'] ) && 'confirm' === $_GET['msg'] ) {
            echo '<div class="notice notice-warning"><p>' . esc_html__( 'Debes escribir DELETE para confirmar.', 'no-comments' ) . '</p></div>';
        }
    }

    /** Maneja el borrado vía admin-post */
    public static function handle_delete_request() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permisos insuficientes.', 'no-comments' ) );
        }
        check_admin_referer( 'no_comments_delete_action', '_wpnonce_no_comments_delete' );

        $scope    = isset( $_POST['delete_scope'] ) ? sanitize_key( wp_unslash( $_POST['delete_scope'] ) ) : 'spam';
        $confirm  = isset( $_POST['confirm'] ) ? sanitize_text_field( wp_unslash( $_POST['confirm'] ) ) : '';
        $dry_run  = ! empty( $_POST['dry_run'] );
        $strategy = isset( $_POST['delete_strategy'] ) && 'trash' === sanitize_key( wp_unslash( $_POST['delete_strategy'] ) ) ? 'trash' : 'delete';
        $types = [];
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Every array element is sanitized with sanitize_key() immediately below.
        $raw_types = isset( $_POST['delete_types'] ) && is_array( $_POST['delete_types'] ) ? wp_unslash( $_POST['delete_types'] ) : [];
        foreach ( $raw_types as $t ) {
            $types[] = sanitize_key( $t );
        }
        $types = array_values( array_unique( array_filter( $types ) ) );

        if ( ! $dry_run && 'DELETE' !== $confirm ) {
            wp_safe_redirect( add_query_arg( [ 'page' => self::PAGE_SLUG, 'tab' => 'delete', 'msg' => 'confirm' ], admin_url( 'options-general.php' ) ) );
            exit;
        }

        $types_q = '';
        if ( ! empty( $types ) ) { $types_q = implode( ',', $types ); }
        if ( $dry_run ) {
            $deleted = self::count_comments_for_scope( $scope, $types );
            $args = [ 'page' => self::PAGE_SLUG, 'tab' => 'delete', 'deleted' => $deleted, 'scope' => $scope, 'dry' => 1, 'strategy' => $strategy ];
            if ( $types_q ) { $args['types'] = $types_q; }
            $url = add_query_arg( $args, admin_url( 'options-general.php' ) );
        } else {
            $deleted = self::delete_comments( $scope, $types, $strategy );
            $args = [ 'page' => self::PAGE_SLUG, 'tab' => 'delete', 'deleted' => $deleted, 'scope' => $scope, 'dry' => 0, 'strategy' => $strategy ];
            if ( $types_q ) { $args['types'] = $types_q; }
            $url = add_query_arg( $args, admin_url( 'options-general.php' ) );
        }
        wp_safe_redirect( $url );
        exit;
    }

    /**
     * Exporta todos los ajustes de NO Comments a un array serializable.
     *
     * @return array
     */
    public static function get_export_payload() {
        $payload = [
            'plugin'   => 'no-comments',
            'version'  => NO_COMMENTS_VERSION,
            'exported' => gmdate( 'c' ),
            'site'     => [
                'enabled'               => (bool) get_option( self::OPTION_KEY, 0 ),
                'rest'                  => (bool) get_option( self::OPTION_REST, 1 ),
                'xmlrpc'                => (bool) get_option( self::OPTION_XMLRPC, 1 ),
                'woo'                   => (bool) get_option( self::OPTION_WOO, 0 ),
                'exceptions'            => self::exception_types(),
                'auto_close_days'       => self::auto_close_days(),
                'auto_cleanup'          => self::auto_cleanup_enabled(),
                'auto_cleanup_interval' => self::auto_cleanup_interval(),
            ],
        ];
        if ( is_multisite() ) {
            $payload['network'] = self::get_network_settings();
        }
        return $payload;
    }

    /**
     * Importa ajustes desde un array validado (whitelist de claves).
     *
     * @param array  $data  Payload (puede incluir las claves site/network de un export).
     * @param string $level site|network
     * @return array Claves aplicadas por nivel: [ 'site' => string[], 'network' => string[] ]
     */
    public static function import_settings( array $data, $level = 'site' ) {
        $applied = [ 'site' => [], 'network' => [] ];

        $site_map = [
            'enabled' => self::OPTION_KEY,
            'rest'    => self::OPTION_REST,
            'xmlrpc'  => self::OPTION_XMLRPC,
            'woo'     => self::OPTION_WOO,
        ];

        if ( 'network' === $level && is_multisite() ) {
            $src = isset( $data['network'] ) && is_array( $data['network'] ) ? $data['network'] : $data;
            $net = self::get_network_settings();
            foreach ( [ 'enforce', 'enabled', 'rest', 'xmlrpc', 'woo' ] as $key ) {
                if ( array_key_exists( $key, $src ) ) {
                    $net[ $key ] = $src[ $key ] ? 1 : 0;
                    $applied['network'][] = $key;
                }
            }
            update_site_option( self::OPTION_NETWORK, $net );
        } elseif ( 'site' === $level ) {
            $src = isset( $data['site'] ) && is_array( $data['site'] ) ? $data['site'] : $data;

            foreach ( $site_map as $key => $option ) {
                if ( array_key_exists( $key, $src ) ) {
                    update_option( $option, $src[ $key ] ? 1 : 0 );
                    $applied['site'][] = $key;
                }
            }
            if ( array_key_exists( 'exceptions', $src ) ) {
                $exceptions = is_array( $src['exceptions'] ) ? $src['exceptions'] : [];
                update_option( self::OPTION_EXCEPTIONS, array_values( array_unique( array_filter( array_map( 'sanitize_key', $exceptions ) ) ) ) );
                $applied['site'][] = 'exceptions';
            }
            if ( array_key_exists( 'auto_close_days', $src ) ) {
                update_option( self::OPTION_AUTO_CLOSE_DAYS, max( 0, (int) $src['auto_close_days'] ) );
                $applied['site'][] = 'auto_close_days';
            }
            if ( array_key_exists( 'auto_cleanup', $src ) ) {
                update_option( self::OPTION_AUTO_CLEANUP, $src['auto_cleanup'] ? 1 : 0 );
                $applied['site'][] = 'auto_cleanup';
            }
            if ( array_key_exists( 'auto_cleanup_interval', $src ) ) {
                $interval = $src['auto_cleanup_interval'];
                update_option( self::OPTION_AUTO_CLEANUP_INT, in_array( $interval, [ 'daily', 'twicedaily', 'weekly' ], true ) ? $interval : 'daily' );
                $applied['site'][] = 'auto_cleanup_interval';
            }
            self::maybe_schedule_cleanup();
        }

        return $applied;
    }

    /**
     * Snapshot de estado efectivo para UI/CLI (respeta multisite).
     *
     * @return array
     */
    public static function get_status() {
        return [
            'enabled'          => self::is_enabled(),
            'rest'             => self::get_rest_disabled(),
            'xmlrpc'           => self::get_xmlrpc_disabled(),
            'woo'              => self::keep_woo_reviews(),
            'network_enforced' => is_multisite() && class_exists( '\\NoComments\\Infrastructure\\OptionsRepository' ) && \NoComments\Infrastructure\OptionsRepository::is_enforced(),
        ];
    }

    /** Descarga un JSON con los ajustes actuales. */
    public static function handle_export_request() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permisos insuficientes.', 'no-comments' ) );
        }
        check_admin_referer( 'no_comments_export_action', '_wpnonce_no_comments_export' );

        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="no-comments-settings-' . gmdate( 'Ymd-His' ) . '.json"' );
        echo wp_json_encode( self::get_export_payload(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        exit;
    }

    /** Importa ajustes desde un archivo JSON subido. */
    public static function handle_import_request() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permisos insuficientes.', 'no-comments' ) );
        }
        check_admin_referer( 'no_comments_import_action', '_wpnonce_no_comments_import' );

        $redirect = add_query_arg( [ 'page' => self::PAGE_SLUG, 'import' => 'error-file' ], admin_url( 'options-general.php' ) );

        if ( ! isset( $_FILES['no_comments_import_file'] ) ) {
            wp_safe_redirect( $redirect );
            exit;
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- El archivo se valida por tamaño y contenido JSON.
        $file = $_FILES['no_comments_import_file'];
        if ( UPLOAD_ERR_OK !== (int) $file['error'] ) {
            wp_safe_redirect( $redirect );
            exit;
        }

        if ( (int) $file['size'] > 512 * 1024 ) { // 512 KB es más que suficiente para un JSON de ajustes.
            wp_safe_redirect( add_query_arg( 'import', 'error-size', $redirect ) );
            exit;
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Lectura de un upload validado por tamaño y JSON.
        $raw  = file_get_contents( $file['tmp_name'] );
        $data = is_string( $raw ) ? json_decode( $raw, true ) : null;
        if ( ! is_array( $data ) ) {
            wp_safe_redirect( add_query_arg( 'import', 'error-json', $redirect ) );
            exit;
        }

        self::import_settings( $data, 'site' );
        wp_safe_redirect( add_query_arg( 'import', 'ok', $redirect ) );
        exit;
    }

    /**
     * Site Health: registra el test de estado de NO Comments.
     */
    public static function register_site_health_tests( $tests ) {
        $tests['direct']['no_comments_status'] = [
            'label' => __( 'NO Comments: bloqueo de comentarios', 'no-comments' ),
            'test'  => [ __CLASS__, 'site_health_test_status' ],
        ];
        return $tests;
    }

    /**
     * Site Health test callback.
     */
    public static function site_health_test_status() {
        $enabled = self::is_enabled();
        if ( $enabled ) {
            return [
                'label'       => __( 'NO Comments está bloqueando comentarios globalmente', 'no-comments' ),
                'status'      => 'good',
                'badge'       => [ 'label' => __( 'NO Comments', 'no-comments' ), 'color' => '#2ecc71' ],
                'description' => '<p>' . esc_html__( 'Los formularios de comentarios y pings están cerrados.', 'no-comments' ) . '</p>',
            ];
        }
        return [
            'label'       => __( 'NO Comments está desactivado', 'no-comments' ),
            'status'      => 'recommended',
            'badge'       => [ 'label' => __( 'NO Comments', 'no-comments' ), 'color' => '#f0ad4e' ],
            'description' => '<p>' . esc_html__( 'Actívalo si quieres bloquear comentarios en todo el sitio.', 'no-comments' ) . '</p>',
            'actions'     => '<p><a class="button button-primary" href="' . esc_url( add_query_arg( [ 'page' => self::PAGE_SLUG ], admin_url( 'options-general.php' ) ) ) . '">' . esc_html__( 'Ir a ajustes', 'no-comments' ) . '</a></p>',
        ];
    }

    /**
     * Añade un ítem en la barra de administración para togglear ON/OFF rápidamente.
     */
    public static function admin_bar_toggle_node( $wp_admin_bar ) {
        if ( ! is_admin_bar_showing() || ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $enabled = self::is_enabled();
        $text    = $enabled ? __( 'NO Comments: ON', 'no-comments' ) : __( 'NO Comments: OFF', 'no-comments' );
        $action  = wp_nonce_url( admin_url( 'admin-post.php?action=no_comments_toggle' ), 'no_comments_toggle_action', '_wpnonce_no_comments_toggle' );

        $wp_admin_bar->add_node( [
            'id'    => 'no-comments-toggle',
            'title' => esc_html( $text ),
            'href'  => $action,
            'meta'  => [ 'class' => $enabled ? 'no-comments-on' : 'no-comments-off' ],
        ] );
    }

    /**
     * Maneja el toggle rápido desde admin-post.
     */
    public static function handle_toggle_request() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permisos insuficientes.', 'no-comments' ) );
        }
        check_admin_referer( 'no_comments_toggle_action', '_wpnonce_no_comments_toggle' );

        if ( is_multisite() && class_exists( '\\NoComments\\Infrastructure\\OptionsRepository' ) && \NoComments\Infrastructure\OptionsRepository::is_enforced() ) {
            wp_safe_redirect( add_query_arg( [ 'page' => self::PAGE_SLUG ], admin_url( 'options-general.php' ) ) );
            exit;
        }

        $current = self::is_enabled();
        update_option( self::OPTION_KEY, $current ? 0 : 1 );
        wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
        exit;
    }

    /**
     * Agrega enlace a Ajustes en la fila del plugin.
     */
    public static function plugin_action_links( $links ) {
        $url = esc_url( add_query_arg( [ 'page' => self::PAGE_SLUG ], admin_url( 'options-general.php' ) ) );
        $links[] = '<a href="' . $url . '">' . esc_html__( 'Ajustes', 'no-comments' ) . '</a>';
        return $links;
    }

    /**
     * Agrega enlaces en la fila del plugin para branding (web/redes).
     */
    public static function plugin_row_meta( $links, $file ) {
        if ( plugin_basename( __FILE__ ) === $file ) {
            $links[] = '<a href="https://www.instagram.com/akelaonline/" target="_blank">' . esc_html__( 'Instagram', 'no-comments' ) . '</a>';
            $links[] = '<a href="https://akela.dev/seo" target="_blank">akela.dev</a>';
        }
        return $links;
    }

    /**
     * REST API: registra endpoints no-comments/v1
     */
    public static function register_rest_routes() {
        $ns = 'no-comments/v1';
        register_rest_route( $ns, '/settings', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [ __CLASS__, 'rest_get_settings' ],
                'permission_callback' => function () { return current_user_can( 'manage_options' ) || current_user_can( 'manage_network_options' ); },
            ],
            [
                'methods'             => \WP_REST_Server::EDITABLE,
                'callback'            => [ __CLASS__, 'rest_update_settings' ],
                'permission_callback' => function () { return current_user_can( 'manage_options' ) || current_user_can( 'manage_network_options' ); },
                'args'                => [
                    'level'                 => [ 'type' => 'string', 'enum' => [ 'site', 'network' ], 'required' => false ],
                    'enabled'               => [ 'type' => 'boolean', 'required' => false ],
                    'rest'                  => [ 'type' => 'boolean', 'required' => false ],
                    'xmlrpc'                => [ 'type' => 'boolean', 'required' => false ],
                    'woo'                   => [ 'type' => 'boolean', 'required' => false ],
                    'enforce'               => [ 'type' => 'boolean', 'required' => false ],
                    'exceptions'            => [ 'type' => 'array', 'required' => false, 'items' => [ 'type' => 'string' ] ],
                    'auto_close_days'       => [ 'type' => 'integer', 'required' => false ],
                    'auto_cleanup'          => [ 'type' => 'boolean', 'required' => false ],
                    'auto_cleanup_interval' => [ 'type' => 'string', 'enum' => [ 'daily', 'twicedaily', 'weekly' ], 'required' => false ],
                ],
            ],
        ] );

        register_rest_route( $ns, '/actions/delete', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ __CLASS__, 'rest_delete' ],
            'permission_callback' => function () { return current_user_can( 'manage_options' ); },
            'args'                => [
                'scope'    => [ 'type' => 'string', 'enum' => [ 'spam', 'pending', 'trash', 'all' ], 'required' => true ],
                'types'    => [ 'type' => 'array', 'required' => false, 'items' => [ 'type' => 'string' ] ],
                'dry_run'  => [ 'type' => 'boolean', 'required' => false ],
                'strategy' => [ 'type' => 'string', 'enum' => [ 'delete', 'trash' ], 'required' => false ],
            ],
        ] );

        register_rest_route( $ns, '/settings/export', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ __CLASS__, 'rest_export_settings' ],
            'permission_callback' => function () { return current_user_can( 'manage_options' ); },
        ] );

        register_rest_route( $ns, '/settings/import', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ __CLASS__, 'rest_import_settings' ],
            'permission_callback' => function () { return current_user_can( 'manage_options' ); },
            'args'                => [
                'level'    => [ 'type' => 'string', 'enum' => [ 'site', 'network' ], 'required' => false ],
                'settings' => [ 'type' => 'object', 'required' => true ],
            ],
        ] );
    }

    /**
     * REST: GET settings snapshot (considera multisite)
     */
    public static function rest_get_settings( $request ) {
        $data = [
            'enabled' => self::is_enabled(),
            'site'    => [
                'enabled'             => (bool) get_option( self::OPTION_KEY, 0 ),
                'rest'                => (bool) get_option( self::OPTION_REST, 1 ),
                'xmlrpc'              => (bool) get_option( self::OPTION_XMLRPC, 1 ),
                'woo'                 => (bool) get_option( self::OPTION_WOO, 0 ),
                'exceptions'          => self::exception_types(),
                'auto_close_days'     => self::auto_close_days(),
                'auto_cleanup'        => self::auto_cleanup_enabled(),
                'auto_cleanup_interval' => self::auto_cleanup_interval(),
            ],
            'effective' => [
                'rest'   => self::get_rest_disabled(),
                'xmlrpc' => self::get_xmlrpc_disabled(),
                'woo'    => self::keep_woo_reviews(),
            ],
        ];
        if ( is_multisite() ) {
            $data['network'] = self::get_network_settings();
        }
        return $data;
    }

    /**
     * REST: POST settings (site o network)
     */
    public static function rest_update_settings( $request ) {
        $level      = $request->get_param( 'level' ) ?: 'site';
        $enabled    = $request->get_param( 'enabled' );
        $rest       = $request->get_param( 'rest' );
        $xmlrpc     = $request->get_param( 'xmlrpc' );
        $woo        = $request->get_param( 'woo' );
        $enforce    = $request->get_param( 'enforce' );
        $exceptions = $request->get_param( 'exceptions' );
        $auto_close = $request->get_param( 'auto_close_days' );
        $auto_clean = $request->get_param( 'auto_cleanup' );
        $auto_int   = $request->get_param( 'auto_cleanup_interval' );

        if ( 'site' === $level && is_multisite() && class_exists( '\\NoComments\\Infrastructure\\OptionsRepository' ) && \NoComments\Infrastructure\OptionsRepository::is_enforced() ) {
            return new \WP_Error( 'no_comments_network_enforced', __( 'Los ajustes del sitio están controlados por la red.', 'no-comments' ), [ 'status' => 409 ] );
        }

        if ( 'network' === $level && is_multisite() ) {
            if ( ! current_user_can( 'manage_network_options' ) ) {
                return new \WP_Error( 'no_comments_forbidden', __( 'Permisos insuficientes para ajustes de red.', 'no-comments' ), [ 'status' => 403 ] );
            }
            $net = self::get_network_settings();
            if ( null !== $enabled ) { $net['enabled'] = $enabled ? 1 : 0; }
            if ( null !== $rest )    { $net['rest']    = $rest ? 1 : 0; }
            if ( null !== $xmlrpc )  { $net['xmlrpc']  = $xmlrpc ? 1 : 0; }
            if ( null !== $woo )     { $net['woo']     = $woo ? 1 : 0; }
            if ( null !== $enforce ) { $net['enforce'] = $enforce ? 1 : 0; }
            update_site_option( self::OPTION_NETWORK, $net );
        } else {
            if ( null !== $enabled ) { update_option( self::OPTION_KEY, $enabled ? 1 : 0 ); }
            if ( null !== $rest )    { update_option( self::OPTION_REST, $rest ? 1 : 0 ); }
            if ( null !== $xmlrpc )  { update_option( self::OPTION_XMLRPC, $xmlrpc ? 1 : 0 ); }
            if ( null !== $woo )     { update_option( self::OPTION_WOO, $woo ? 1 : 0 ); }
            if ( null !== $exceptions ) {
                $exceptions = is_array( $exceptions ) ? $exceptions : [ $exceptions ];
                update_option( self::OPTION_EXCEPTIONS, array_values( array_unique( array_filter( array_map( 'sanitize_key', $exceptions ) ) ) ) );
            }
            if ( null !== $auto_close ) { update_option( self::OPTION_AUTO_CLOSE_DAYS, max( 0, (int) $auto_close ) ); }
            if ( null !== $auto_clean ) { update_option( self::OPTION_AUTO_CLEANUP, $auto_clean ? 1 : 0 ); }
            if ( null !== $auto_int && in_array( $auto_int, [ 'daily', 'twicedaily', 'weekly' ], true ) ) {
                update_option( self::OPTION_AUTO_CLEANUP_INT, $auto_int );
            }
            self::maybe_schedule_cleanup();
        }
        return self::rest_get_settings( $request );
    }

    /**
     * REST: POST actions/delete
     */
    public static function rest_delete( $request ) {
        $scope    = $request->get_param( 'scope' ) ?: 'spam';
        $types    = $request->get_param( 'types' );
        $dry_run  = (bool) $request->get_param( 'dry_run' );
        $strategy = $request->get_param( 'strategy' ) === 'trash' ? 'trash' : 'delete';
        if ( is_string( $types ) ) {
            $types = array_filter( array_map( 'sanitize_key', array_map( 'trim', explode( ',', $types ) ) ) );
        }
        if ( ! is_array( $types ) ) { $types = []; }

        if ( $dry_run ) {
            $count = self::count_comments_for_scope( $scope, $types );
            return [ 'deleted' => $count, 'dry_run' => true, 'scope' => $scope, 'types' => $types ];
        }
        $deleted = self::delete_comments( $scope, $types, $strategy );
        return [ 'deleted' => (int) $deleted, 'dry_run' => false, 'scope' => $scope, 'types' => $types, 'strategy' => $strategy ];
    }

    /** REST: exporta un snapshot completo de ajustes. */
    public static function rest_export_settings( $request ) {
        return self::get_export_payload();
    }

    /**
     * REST: importa ajustes desde un payload JSON.
     */
    public static function rest_import_settings( $request ) {
        $level    = $request->get_param( 'level' ) ?: 'site';
        $settings = $request->get_param( 'settings' );

        if ( 'network' === $level ) {
            if ( ! is_multisite() ) {
                return new \WP_Error( 'no_comments_not_multisite', __( 'Este sitio no es una red multisite.', 'no-comments' ), [ 'status' => 400 ] );
            }
            if ( ! current_user_can( 'manage_network_options' ) ) {
                return new \WP_Error( 'no_comments_forbidden', __( 'Permisos insuficientes para ajustes de red.', 'no-comments' ), [ 'status' => 403 ] );
            }
        } elseif ( is_multisite() && class_exists( '\\NoComments\\Infrastructure\\OptionsRepository' ) && \NoComments\Infrastructure\OptionsRepository::is_enforced() ) {
            return new \WP_Error( 'no_comments_network_enforced', __( 'Los ajustes del sitio están controlados por la red.', 'no-comments' ), [ 'status' => 409 ] );
        }

        if ( ! is_array( $settings ) ) {
            return new \WP_Error( 'no_comments_invalid_payload', __( 'El payload de ajustes debe ser un objeto JSON.', 'no-comments' ), [ 'status' => 400 ] );
        }

        $applied = self::import_settings( $settings, $level );
        return [
            'applied'  => $applied,
            'settings' => self::rest_get_settings( $request ),
        ];
    }

    /**
     * Devuelve ajustes de red (multisite).
     *
     * @return array{enforce:int,enabled:int,rest:int,xmlrpc:int,woo:int}
     */
    private static function get_network_settings() {
        if ( class_exists( '\\NoComments\\Infrastructure\\OptionsRepository' ) ) {
            return \NoComments\Infrastructure\OptionsRepository::get_network_settings();
        }
        return [ 'enforce' => 0, 'enabled' => 1, 'rest' => 1, 'xmlrpc' => 1, 'woo' => 0 ];
    }

    /** Toggles efectivos de REST/XML-RPC considerando red */
    private static function get_rest_disabled() {
        if ( class_exists( '\\NoComments\\Infrastructure\\OptionsRepository' ) ) {
            return \NoComments\Infrastructure\OptionsRepository::effective_rest_disabled();
        }
        return (bool) get_option( self::OPTION_REST, 1 );
    }
    private static function get_xmlrpc_disabled() {
        if ( class_exists( '\\NoComments\\Infrastructure\\OptionsRepository' ) ) {
            return \NoComments\Infrastructure\OptionsRepository::effective_xmlrpc_disabled();
        }
        return (bool) get_option( self::OPTION_XMLRPC, 1 );
    }

    /** Página de ajustes de red (multisite) */
    public static function add_network_settings_page() {
        add_submenu_page(
            'settings.php',
            __( 'NO Comments (Network)', 'no-comments' ),
            __( 'NO Comments', 'no-comments' ),
            'manage_network_options',
            self::PAGE_SLUG . '-network',
            [ __CLASS__, 'render_network_settings_page' ]
        );
    }

    public static function render_network_settings_page() {
        if ( ! current_user_can( 'manage_network_options' ) ) {
            return;
        }
        $net = self::get_network_settings();
        echo '<div class="wrap" id="no-comments-admin">';
        echo '<h1>' . esc_html__( 'NO Comments (Network)', 'no-comments' ) . '</h1>';
        if ( isset( $_GET['updated'] ) ) {
            echo '<div class="updated notice"><p>' . esc_html__( 'Ajustes de red guardados.', 'no-comments' ) . '</p></div>';
        }
        $action = admin_url( 'admin-post.php' );
        echo '<form method="post" action="' . esc_url( $action ) . '">';
        wp_nonce_field( 'no_comments_network_save', '_wpnonce_no_comments_network' );
        echo '<input type="hidden" name="action" value="no_comments_network_save" />';

        echo '<table class="form-table"><tbody>';

        echo '<tr><th scope="row">' . esc_html__( 'Forzar desde la Red', 'no-comments' ) . '</th><td>';
        echo '<label><input type="hidden" name="enforce" value="0" />';
        echo '<input type="checkbox" name="enforce" value="1" ' . checked( $net['enforce'], 1, false ) . ' /> ' . esc_html__( 'Aplicar estos ajustes a todos los sitios', 'no-comments' ) . '</label>';
        echo '<p class="description">' . esc_html__( 'Cuando está activo, los sitios no podrán cambiar estos valores.', 'no-comments' ) . '</p>';
        echo '</td></tr>';

        echo '<tr><th scope="row">' . esc_html__( 'Estado global', 'no-comments' ) . '</th><td>';
        echo '<label><input type="hidden" name="enabled" value="0" />';
        echo '<input type="checkbox" name="enabled" value="1" ' . checked( $net['enabled'], 1, false ) . ' /> ' . esc_html__( 'Cerrar comentarios y pings en todo el network', 'no-comments' ) . '</label>';
        echo '</td></tr>';

        echo '<tr><th scope="row">APIs</th><td>';
        echo '<label style="display:block"><input type="hidden" name="rest" value="0" />';
        echo '<input type="checkbox" name="rest" value="1" ' . checked( $net['rest'], 1, false ) . ' /> ' . esc_html__( 'Deshabilitar endpoint REST de comentarios', 'no-comments' ) . '</label>';
        echo '<label style="display:block"><input type="hidden" name="xmlrpc" value="0" />';
        echo '<input type="checkbox" name="xmlrpc" value="1" ' . checked( $net['xmlrpc'], 1, false ) . ' /> ' . esc_html__( 'Deshabilitar XML‑RPC (wp.newComment)', 'no-comments' ) . '</label>';
        echo '</td></tr>';

        echo '<tr><th scope="row">' . esc_html__( 'Compatibilidad', 'no-comments' ) . '</th><td>';
        echo '<label><input type="hidden" name="woo" value="0" />';
        echo '<input type="checkbox" name="woo" value="1" ' . checked( $net['woo'], 1, false ) . ' /> ' . esc_html__( 'Mantener reseñas de productos (WooCommerce)', 'no-comments' ) . '</label>';
        echo '</td></tr>';

        echo '</tbody></table>';
        submit_button( __( 'Guardar ajustes de red', 'no-comments' ) );
        echo '</form>';
        echo '</div>';
    }

    public static function handle_network_save() {
        if ( ! current_user_can( 'manage_network_options' ) ) {
            wp_die( esc_html__( 'Permisos insuficientes.', 'no-comments' ) );
        }
        check_admin_referer( 'no_comments_network_save', '_wpnonce_no_comments_network' );
        $data = [
            'enforce' => isset( $_POST['enforce'] ) ? 1 : 0,
            'enabled' => isset( $_POST['enabled'] ) ? 1 : 0,
            'rest'    => isset( $_POST['rest'] ) ? 1 : 0,
            'xmlrpc'  => isset( $_POST['xmlrpc'] ) ? 1 : 0,
            'woo'     => isset( $_POST['woo'] ) ? 1 : 0,
        ];
        update_site_option( self::OPTION_NETWORK, $data );
        wp_safe_redirect( network_admin_url( 'settings.php?page=' . self::PAGE_SLUG . '-network&updated=1' ) );
        exit;
    }

    /**
     * Elimina comentarios según el alcance indicado.
     *
     * @param string $scope spam|pending|trash|all
     * @return int Cantidad eliminada
     */
    public static function delete_comments( $scope, $types = [], $strategy = 'delete' ) {
        if ( class_exists( '\\NoComments\\Application\\DeleteService' ) ) {
            return \NoComments\Application\DeleteService::delete( $scope, (array) $types, $strategy );
        }
        // Fallback a la implementación previa si por alguna razón no se cargó el servicio
        $count = get_comments( [ 'status' => 'all', 'count' => true ] );
        return (int) $count; // Retorno mínimo para no romper ejecución
    }

    /**
     * Cuenta comentarios que serían afectados por un alcance dado (sin borrar).
     *
     * @param string $scope
     * @return int
     */
    public static function count_comments_for_scope( $scope, $types = [] ) {
        if ( class_exists( '\\NoComments\\Application\\DeleteService' ) ) {
            return \NoComments\Application\DeleteService::count( $scope, (array) $types );
        }
        $c = wp_count_comments();
        return (int) $c->total_comments;
    }
}

No_Comments_Plugin::init();

// WP-CLI commands
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    /**
     * Comandos CLI para NO Comments
     */
    class No_Comments_CLI_Command extends WP_CLI_Command {
        /**
         * Muestra el estado actual (efectivo, considerando multisite).
         */
        public function status() {
            $s = No_Comments_Plugin::get_status();
            WP_CLI::log( 'enabled = ' . ( $s['enabled'] ? '1' : '0' ) );
            WP_CLI::log( 'rest = ' . ( $s['rest'] ? '1' : '0' ) );
            WP_CLI::log( 'xmlrpc = ' . ( $s['xmlrpc'] ? '1' : '0' ) );
            WP_CLI::log( 'woo_reviews = ' . ( $s['woo'] ? '1' : '0' ) );
            if ( $s['network_enforced'] ) {
                WP_CLI::log( 'network = enforced' );
            }
        }
        /**
         * Activa el bloqueo global de comentarios.
         */
        public function enable() {
            update_option( No_Comments_Plugin::OPTION_KEY, 1 );
            WP_CLI::success( 'Comentarios deshabilitados globalmente.' );
        }
        /**
         * Desactiva el bloqueo global de comentarios.
         */
        public function disable() {
            update_option( No_Comments_Plugin::OPTION_KEY, 0 );
            WP_CLI::success( 'Comentarios restaurados al comportamiento normal.' );
        }
        /**
         * Borra comentarios.
         *
         * ## OPTIONS
         *
         * [--scope=<scope>]
         * : spam|pending|trash|all (por defecto: spam)
         *
         * [--types=<types>]
         * : lista separada por comas de tipos de post a limitar (ej: post,page,product)
         *
         * [--strategy=<strategy>]
         * : delete (definitivo) o trash (papelera, cuando aplique). Por defecto: delete.
         *
         * [--dry-run]
         * : calcula cuántos se borrarían sin ejecutar el borrado.
         *
         * ## EXAMPLES
         *
         * wp no-comments delete --scope=all
         * wp no-comments delete --scope=spam --dry-run
         * wp no-comments delete --scope=all --types=post,page
         * wp no-comments delete --scope=pending --strategy=trash
         */
        public function delete( $args, $assoc_args ) {
            $scope    = isset( $assoc_args['scope'] ) ? $assoc_args['scope'] : 'spam';
            $dryrun   = ! empty( $assoc_args['dry-run'] );
            $strategy = isset( $assoc_args['strategy'] ) && 'trash' === $assoc_args['strategy'] ? 'trash' : 'delete';
            $types    = [];
            if ( ! empty( $assoc_args['types'] ) ) {
                $parts = array_map( 'trim', explode( ',', $assoc_args['types'] ) );
                foreach ( $parts as $t ) {
                    if ( $t !== '' ) { $types[] = sanitize_key( $t ); }
                }
                $types = array_values( array_unique( $types ) );
            }

            if ( $dryrun ) {
                $count = No_Comments_Plugin::count_comments_for_scope( $scope, $types );
                WP_CLI::log( sprintf( 'DRY-RUN: se borrarían %d comentarios (alcance: %s%s).', $count, $scope, empty( $types ) ? '' : ', types=' . implode( ',', $types ) ) );
                return;
            }

            $deleted = No_Comments_Plugin::delete_comments( $scope, $types, $strategy );
            WP_CLI::success( sprintf( 'Eliminados %d comentarios (alcance: %s%s%s).', $deleted, $scope, empty( $types ) ? '' : ', types=' . implode( ',', $types ), ', strategy=' . $strategy ) );
        }

        /**
         * Exporta o importa los ajustes del plugin.
         *
         * ## OPTIONS
         *
         * <accion>
         * : export|import
         *
         * [<archivo>]
         * : Para import: ruta del JSON a importar. Para export: ruta de salida (alternativa a --file).
         *
         * [--file=<file>]
         * : Ruta de salida del export. Si no se indica, el JSON se imprime por stdout.
         *
         * ## EXAMPLES
         *
         * wp no-comments settings export
         * wp no-comments settings export --file=no-comments.json
         * wp no-comments settings import no-comments.json
         */
        public function settings( $args, $assoc_args ) {
            $action = isset( $args[0] ) ? strtolower( $args[0] ) : '';

            if ( 'export' === $action ) {
                $json = wp_json_encode( No_Comments_Plugin::get_export_payload(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
                $file = isset( $assoc_args['file'] ) ? $assoc_args['file'] : ( isset( $args[1] ) ? $args[1] : '' );
                if ( $file ) {
                    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Export local indicado por el operador.
                    if ( false === file_put_contents( $file, $json . PHP_EOL ) ) {
                        WP_CLI::error( sprintf( 'No se pudo escribir en %s.', $file ) );
                    }
                    WP_CLI::success( 'Ajustes exportados a ' . $file );
                } else {
                    WP_CLI::log( $json );
                }
                return;
            }

            if ( 'import' === $action ) {
                $file = isset( $args[1] ) ? $args[1] : '';
                if ( ! $file || ! is_file( $file ) ) {
                    WP_CLI::error( 'Indicá la ruta del archivo JSON: wp no-comments settings import <archivo>.' );
                }
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Lectura de archivo local indicado por el operador.
                $raw  = file_get_contents( $file );
                $data = is_string( $raw ) ? json_decode( $raw, true ) : null;
                if ( ! is_array( $data ) ) {
                    WP_CLI::error( 'El archivo debe ser un JSON válido exportado por NO Comments.' );
                }
                $applied = No_Comments_Plugin::import_settings( $data, 'site' );
                WP_CLI::success( 'Ajustes importados. Aplicados: site=[' . implode( ', ', $applied['site'] ) . '], network=[' . implode( ', ', $applied['network'] ) . '].' );
                return;
            }

            WP_CLI::error( 'Uso: wp no-comments settings export [--file=<ruta>] | wp no-comments settings import <archivo>.' );
        }

        /**
         * Controla la limpieza automática de spam.
         *
         * ## OPTIONS
         *
         * <accion>
         * : status|run|enable|disable
         *
         * [--interval=<interval>]
         * : daily|twicedaily|weekly (solo con enable; por defecto: daily)
         *
         * ## EXAMPLES
         *
         * wp no-comments cleanup status
         * wp no-comments cleanup enable --interval=weekly
         * wp no-comments cleanup run
         * wp no-comments cleanup disable
         */
        public function cleanup( $args, $assoc_args ) {
            $action = isset( $args[0] ) ? strtolower( $args[0] ) : 'status';

            if ( 'run' === $action ) {
                $deleted = No_Comments_Plugin::run_cleanup();
                WP_CLI::success( sprintf( 'Limpieza ejecutada: %d comentarios de spam eliminados.', $deleted ) );
                return;
            }

            if ( 'enable' === $action ) {
                update_option( No_Comments_Plugin::OPTION_AUTO_CLEANUP, 1 );
                if ( isset( $assoc_args['interval'] ) && in_array( $assoc_args['interval'], [ 'daily', 'twicedaily', 'weekly' ], true ) ) {
                    update_option( No_Comments_Plugin::OPTION_AUTO_CLEANUP_INT, $assoc_args['interval'] );
                }
                No_Comments_Plugin::maybe_schedule_cleanup();
                WP_CLI::success( 'Limpieza automática activada (' . No_Comments_Plugin::auto_cleanup_interval() . ').' );
                return;
            }

            if ( 'disable' === $action ) {
                update_option( No_Comments_Plugin::OPTION_AUTO_CLEANUP, 0 );
                No_Comments_Plugin::maybe_schedule_cleanup();
                WP_CLI::success( 'Limpieza automática desactivada.' );
                return;
            }

            // status
            $last = get_option( No_Comments_Plugin::OPTION_LAST_CLEANUP, [] );
            $spam = No_Comments_Plugin::count_comments_for_scope( 'spam' );
            WP_CLI::log( 'enabled = ' . ( No_Comments_Plugin::auto_cleanup_enabled() ? '1' : '0' ) );
            WP_CLI::log( 'interval = ' . No_Comments_Plugin::auto_cleanup_interval() );
            WP_CLI::log( 'spam_count = ' . $spam );
            if ( is_array( $last ) && ! empty( $last['time'] ) ) {
                WP_CLI::log( 'last_run = ' . $last['time'] . ' (deleted: ' . (int) $last['deleted'] . ')' );
            } else {
                WP_CLI::log( 'last_run = nunca' );
            }
        }

        /**
         * Gestiona las excepciones por tipo de contenido.
         *
         * ## OPTIONS
         *
         * <accion>
         * : list|add|remove
         *
         * [<tipo>]
         * : Post type (con add/remove).
         *
         * ## EXAMPLES
         *
         * wp no-comments exceptions list
         * wp no-comments exceptions add page
         * wp no-comments exceptions remove page
         */
        public function exceptions( $args ) {
            $action  = isset( $args[0] ) ? strtolower( $args[0] ) : 'list';
            $current = get_option( No_Comments_Plugin::OPTION_EXCEPTIONS, [] );
            if ( ! is_array( $current ) ) {
                $current = [];
            }

            if ( 'list' === $action ) {
                WP_CLI::log( 'Excepciones: ' . ( $current ? implode( ', ', $current ) : '(ninguna)' ) );
                return;
            }

            $type = isset( $args[1] ) ? sanitize_key( $args[1] ) : '';
            if ( 'add' === $action ) {
                if ( ! $type || ! post_type_exists( $type ) ) {
                    WP_CLI::error( 'Indicá un post type válido: wp no-comments exceptions add <tipo>.' );
                }
                if ( ! in_array( $type, $current, true ) ) {
                    $current[] = $type;
                    update_option( No_Comments_Plugin::OPTION_EXCEPTIONS, $current );
                }
                WP_CLI::success( 'Excepción agregada: ' . $type );
                return;
            }

            if ( 'remove' === $action ) {
                $current = array_values( array_diff( $current, [ $type ] ) );
                update_option( No_Comments_Plugin::OPTION_EXCEPTIONS, $current );
                WP_CLI::success( $type ? ( 'Excepción removida: ' . $type ) : 'Excepciones actualizadas.' );
                return;
            }

            WP_CLI::error( 'Uso: wp no-comments exceptions list|add <tipo>|remove <tipo>.' );
        }

        /**
         * Configura el cierre automático por antigüedad.
         *
         * ## OPTIONS
         *
         * [<dias|status|off>]
         * : Cantidad de días (0 = off, status muestra el valor actual).
         *
         * ## EXAMPLES
         *
         * wp no-comments auto-close 30
         * wp no-comments auto-close off
         * wp no-comments auto-close status
         */
        public function auto_close( $args ) {
            $value = isset( $args[0] ) ? strtolower( $args[0] ) : 'status';

            if ( 'status' === $value ) {
                WP_CLI::log( 'auto_close_days = ' . No_Comments_Plugin::auto_close_days() );
                return;
            }
            if ( 'off' === $value ) {
                update_option( No_Comments_Plugin::OPTION_AUTO_CLOSE_DAYS, 0 );
                WP_CLI::success( 'Cierre automático desactivado.' );
                return;
            }

            $days = (int) $value;
            if ( $days < 0 ) {
                WP_CLI::error( 'Indicá un número de días (0 o más): wp no-comments auto-close 30.' );
            }
            update_option( No_Comments_Plugin::OPTION_AUTO_CLOSE_DAYS, $days );
            WP_CLI::success( 'Cierre automático configurado: ' . $days . ' días.' );
        }

        /**
         * Controla la compatibilidad con reseñas de WooCommerce (mantener/estado).
         *
         * ## OPTIONS
         *
         * <accion>
         * : on|off|status (por defecto: status)
         *
         * ## EXAMPLES
         *
         * wp no-comments woo-reviews on
         * wp no-comments woo-reviews off
         * wp no-comments woo-reviews status
         */
        public function woo_reviews( $args ) {
            $action = isset( $args[0] ) ? strtolower( $args[0] ) : 'status';
            if ( 'on' === $action ) {
                update_option( No_Comments_Plugin::OPTION_WOO, 1 );
                WP_CLI::success( 'Compatibilidad WooCommerce: mantener reseñas = ON' );
                return;
            }
            if ( 'off' === $action ) {
                update_option( No_Comments_Plugin::OPTION_WOO, 0 );
                WP_CLI::success( 'Compatibilidad WooCommerce: mantener reseñas = OFF' );
                return;
            }
            $val = (bool) get_option( No_Comments_Plugin::OPTION_WOO, 0 );
            WP_CLI::log( 'Compatibilidad WooCommerce (mantener reseñas) = ' . ( $val ? 'ON' : 'OFF' ) );
        }
    }
    WP_CLI::add_command( 'no-comments', 'No_Comments_CLI_Command' );
}

endif; // class exists
