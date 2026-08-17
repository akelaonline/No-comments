<?php
namespace NoComments\Infrastructure;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Centraliza lectura/escritura de opciones considerando multisite.
 */
class OptionsRepository {
	/**
	 * Obtiene los ajustes de red (multisite) con defaults normalizados.
	 *
	 * @return array{enforce:int,enabled:int,rest:int,xmlrpc:int,woo:int}
	 */
	public static function get_network_settings(): array {
		$defaults = array(
			'enforce' => 0,
			'enabled' => 1,
			'rest'    => 1,
			'xmlrpc'  => 1,
			'woo'     => 0,
		);
		if ( ! is_multisite() ) {
			return $defaults;
		}
		$opt = get_site_option( \No_Comments_Plugin::OPTION_NETWORK, array() );
		if ( ! is_array( $opt ) ) {
			$opt = array();
		}
		$opt = wp_parse_args( $opt, $defaults );
		foreach ( $opt as $k => $v ) {
			$opt[ $k ] = $v ? 1 : 0;
		}
		return $opt;
	}

	/** Si la red fuerza los ajustes. */
	public static function is_enforced(): bool {
		if ( ! is_multisite() ) {
			return false;
		}
		$net = self::get_network_settings();
		return ! empty( $net['enforce'] );
	}

	/** Estado efectivo del cierre global. */
	public static function effective_enabled(): bool {
		if ( self::is_enforced() ) {
			$net = self::get_network_settings();
			return (bool) $net['enabled'];
		}
		return (bool) get_option( \No_Comments_Plugin::OPTION_KEY, 0 );
	}

	/** REST de comentarios deshabilitado efectivo. */
	public static function effective_rest_disabled(): bool {
		if ( self::is_enforced() ) {
			$net = self::get_network_settings();
			return (bool) $net['rest'];
		}
		return (bool) get_option( \No_Comments_Plugin::OPTION_REST, 1 );
	}

	/** XML-RPC deshabilitado efectivo. */
	public static function effective_xmlrpc_disabled(): bool {
		if ( self::is_enforced() ) {
			$net = self::get_network_settings();
			return (bool) $net['xmlrpc'];
		}
		return (bool) get_option( \No_Comments_Plugin::OPTION_XMLRPC, 1 );
	}

	/** Mantener reseñas de WooCommerce (efectivo). */
	public static function effective_keep_woo_reviews(): bool {
		$site_val = (bool) get_option( \No_Comments_Plugin::OPTION_WOO, 0 );
		if ( self::is_enforced() ) {
			$net      = self::get_network_settings();
			$site_val = (bool) $net['woo'];
		}
		return $site_val && class_exists( 'WooCommerce' );
	}

	/**
	 * Normaliza los checkboxes del formulario Multisite antes del handler.
	 *
	 * El formulario usa hidden=0 + checkbox=1. El handler principal evalúa
	 * isset(), por lo que un hidden sin marcar también se interpretaba como 1.
	 * Dejamos presentes únicamente los valores realmente activados para que el
	 * handler existente conserve su contrato sin forzar todos los toggles a ON.
	 */
	public static function normalize_network_form_post() {
		foreach ( array( 'enforce', 'enabled', 'rest', 'xmlrpc', 'woo' ) as $key ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The main admin-post handler verifies the nonce immediately after this normalization hook.
			if ( ! isset( $_POST[ $key ] ) ) {
				continue;
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Exact scalar comparison; nonce is verified by the main handler.
			$value = $_POST[ $key ];
			if ( ! is_scalar( $value ) || '1' !== (string) wp_unslash( $value ) ) {
				unset( $_POST[ $key ] );
			}
		}
	}

	/**
	 * Restringe consultas agregadas de comentarios a los post types exceptuados.
	 *
	 * El short-circuit del plugin evita consultas cuando el bloqueo global está
	 * activo. Sin embargo, una consulta agregada (sin post_id) también quedaba
	 * vacía aunque existieran excepciones o reviews de WooCommerce. Acotamos la
	 * query a las excepciones efectivas y la marcamos para permitir la consulta
	 * normal de WordPress en el filtro posterior.
	 *
	 * @param \WP_Comment_Query $query Consulta actual.
	 */
	public static function scope_exception_comment_query( $query ) {
		if ( ! self::effective_enabled() || ! is_object( $query ) || ! isset( $query->query_vars ) || ! is_array( $query->query_vars ) ) {
			return;
		}

		$vars = &$query->query_vars;
		if ( ! empty( $vars['count'] ) || 'ids' === ( isset( $vars['fields'] ) ? $vars['fields'] : '' ) ) {
			return;
		}

		$status = isset( $vars['status'] ) ? $vars['status'] : 'all';
		if ( is_string( $status ) && in_array( $status, array( 'spam', 'hold', 'trash' ), true ) ) {
			return;
		}

		$post_id = isset( $vars['post_id'] ) ? absint( $vars['post_id'] ) : 0;
		if ( $post_id ) {
			return;
		}

		$exceptions = get_option( \No_Comments_Plugin::OPTION_EXCEPTIONS, array() );
		if ( ! is_array( $exceptions ) ) {
			$exceptions = array();
		}
		$exceptions = array_values( array_unique( array_filter( array_map( 'sanitize_key', $exceptions ) ) ) );
		if ( self::effective_keep_woo_reviews() ) {
			$exceptions[] = 'product';
			$exceptions   = array_values( array_unique( $exceptions ) );
		}
		if ( empty( $exceptions ) ) {
			return;
		}

		$requested = isset( $vars['post_type'] ) ? $vars['post_type'] : '';
		if ( empty( $requested ) || 'any' === $requested ) {
			$allowed = $exceptions;
		} else {
			$requested = is_array( $requested ) ? $requested : array( $requested );
			$requested = array_values( array_unique( array_filter( array_map( 'sanitize_key', $requested ) ) ) );
			$allowed   = array_values( array_intersect( $requested, $exceptions ) );
		}

		if ( empty( $allowed ) ) {
			return;
		}

		$vars['post_type']                     = $allowed;
		$vars['_no_comments_exception_scope'] = 1;
	}

	/**
	 * Permite que WordPress ejecute la query previamente restringida a excepciones.
	 *
	 * @param mixed             $comments Valor del filtro comments_pre_query.
	 * @param \WP_Comment_Query $query    Consulta actual.
	 * @return mixed
	 */
	public static function restore_scoped_exception_query( $comments, $query ) {
		if ( ! is_object( $query ) || ! isset( $query->query_vars['_no_comments_exception_scope'] ) ) {
			return $comments;
		}

		if ( array() === $comments ) {
			return null;
		}

		return $comments;
	}
}

// Runtime hardening for paths implemented in the legacy single-file controller.
add_action( 'admin_post_no_comments_network_save', array( OptionsRepository::class, 'normalize_network_form_post' ), 0 );
add_action( 'parse_comment_query', array( OptionsRepository::class, 'scope_exception_comment_query' ), 9 );
add_filter( 'comments_pre_query', array( OptionsRepository::class, 'restore_scoped_exception_query' ), 11, 2 );
