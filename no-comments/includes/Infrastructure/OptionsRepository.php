<?php
namespace NoComments\Infrastructure;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Centraliza lectura/escritura de opciones considerando multisite.
 */
class OptionsRepository {
    /**
     * Obtiene los ajustes de red (multisite) con defaults normalizados.
     *
     * @return array{enforce:int,enabled:int,rest:int,xmlrpc:int,woo:int}
     */
    public static function get_network_settings() : array {
        $defaults = [ 'enforce' => 0, 'enabled' => 1, 'rest' => 1, 'xmlrpc' => 1, 'woo' => 0 ];
        if ( ! is_multisite() ) {
            return $defaults;
        }
        $opt = get_site_option( \No_Comments_Plugin::OPTION_NETWORK, [] );
        if ( ! is_array( $opt ) ) { $opt = []; }
        $opt = wp_parse_args( $opt, $defaults );
        foreach ( $opt as $k => $v ) { $opt[ $k ] = $v ? 1 : 0; }
        return $opt;
    }

    /** Si la red fuerza los ajustes */
    public static function is_enforced() : bool {
        if ( ! is_multisite() ) { return false; }
        $net = self::get_network_settings();
        return ! empty( $net['enforce'] );
    }

    /** Estado efectivo del cierre global */
    public static function effective_enabled() : bool {
        if ( self::is_enforced() ) {
            $net = self::get_network_settings();
            return (bool) $net['enabled'];
        }
        return (bool) get_option( \No_Comments_Plugin::OPTION_KEY, 0 );
    }

    /** REST de comentarios deshabilitado efectivo */
    public static function effective_rest_disabled() : bool {
        if ( self::is_enforced() ) {
            $net = self::get_network_settings();
            return (bool) $net['rest'];
        }
        return (bool) get_option( \No_Comments_Plugin::OPTION_REST, 1 );
    }

    /** XML-RPC deshabilitado efectivo */
    public static function effective_xmlrpc_disabled() : bool {
        if ( self::is_enforced() ) {
            $net = self::get_network_settings();
            return (bool) $net['xmlrpc'];
        }
        return (bool) get_option( \No_Comments_Plugin::OPTION_XMLRPC, 1 );
    }

    /** Mantener reseñas de WooCommerce (efectivo) */
    public static function effective_keep_woo_reviews() : bool {
        $site_val = (bool) get_option( \No_Comments_Plugin::OPTION_WOO, 0 );
        if ( self::is_enforced() ) {
            $net = self::get_network_settings();
            $site_val = (bool) $net['woo'];
        }
        return $site_val && class_exists( 'WooCommerce' );
    }
}
