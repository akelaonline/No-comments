<?php
/**
 * Focused regression checks for audit-hardening fixes.
 *
 * Runs without booting WordPress: only the small surface used by the two
 * refactored services is stubbed. The goal is to catch semantic regressions
 * that syntax/PHPCS cannot detect.
 */

define( 'ABSPATH', __DIR__ );
define( 'EMPTY_TRASH_DAYS', 30 );

class No_Comments_Plugin {
	const OPTION_KEY        = 'no_comments_enabled';
	const OPTION_REST       = 'no_comments_disable_rest';
	const OPTION_XMLRPC     = 'no_comments_disable_xmlrpc';
	const OPTION_WOO        = 'no_comments_keep_woo_reviews';
	const OPTION_NETWORK    = 'no_comments_network_settings';
	const OPTION_EXCEPTIONS = 'no_comments_exceptions';
}

class WooCommerce {}

$GLOBALS['nc_test_options'] = array();
$GLOBALS['nc_test_network'] = array();
$GLOBALS['nc_test_comments'] = array();
$GLOBALS['nc_test_multisite'] = false;

function is_multisite() {
	return (bool) $GLOBALS['nc_test_multisite'];
}

function get_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['nc_test_options'] ) ? $GLOBALS['nc_test_options'][ $key ] : $default;
}

function get_site_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['nc_test_network'] ) ? $GLOBALS['nc_test_network'][ $key ] : $default;
}

function wp_parse_args( $args, $defaults = array() ) {
	return array_merge( $defaults, is_array( $args ) ? $args : array() );
}

function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}

function wp_unslash( $value ) {
	if ( is_array( $value ) ) {
		return array_map( 'wp_unslash', $value );
	}
	return is_string( $value ) ? stripslashes( $value ) : $value;
}

function absint( $value ) {
	return abs( (int) $value );
}

function add_action() {}
function add_filter() {}
function has_action() { return false; }
function do_action() {}

function get_comments( $args = array() ) {
	$status = isset( $args['status'] ) ? $args['status'] : 'all';
	$ids    = array();
	foreach ( $GLOBALS['nc_test_comments'] as $id => $comment ) {
		if ( 'all' !== $status && $comment['status'] !== $status ) {
			continue;
		}
		if ( ! empty( $args['post_type'] ) ) {
			$types = (array) $args['post_type'];
			if ( ! in_array( $comment['post_type'], $types, true ) ) {
				continue;
			}
		}
		$ids[] = (int) $id;
	}
	sort( $ids );
	if ( ! empty( $args['number'] ) ) {
		$ids = array_slice( $ids, 0, (int) $args['number'] );
	}
	if ( ! empty( $args['count'] ) ) {
		return count( $ids );
	}
	return $ids;
}

function get_comment_post_ID( $comment_id ) {
	return isset( $GLOBALS['nc_test_comments'][ $comment_id ] ) ? (int) $GLOBALS['nc_test_comments'][ $comment_id ]['post_id'] : 0;
}

function wp_trash_comment( $comment_id ) {
	if ( ! isset( $GLOBALS['nc_test_comments'][ $comment_id ] ) ) {
		return false;
	}
	$GLOBALS['nc_test_comments'][ $comment_id ]['status'] = 'trash';
	return true;
}

function wp_delete_comment( $comment_id, $force_delete = false ) {
	if ( ! isset( $GLOBALS['nc_test_comments'][ $comment_id ] ) ) {
		return false;
	}
	if ( ! $force_delete && ! in_array( $GLOBALS['nc_test_comments'][ $comment_id ]['status'], array( 'spam', 'trash' ), true ) ) {
		return wp_trash_comment( $comment_id );
	}
	unset( $GLOBALS['nc_test_comments'][ $comment_id ] );
	return true;
}

function get_post( $post_id ) {
	return (object) array( 'ID' => (int) $post_id );
}

require_once dirname( __DIR__, 2 ) . '/no-comments/includes/Infrastructure/OptionsRepository.php';
require_once dirname( __DIR__, 2 ) . '/no-comments/includes/Application/DeleteService.php';

function nc_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
	echo "PASS: {$message}\n";
}

// 1. Multisite network form: hidden=0 must not become enabled through isset().
$_POST = array(
	'enforce' => '0',
	'enabled' => '1',
	'rest'    => '0',
	'xmlrpc'  => '1',
	'woo'     => '0',
);
\NoComments\Infrastructure\OptionsRepository::normalize_network_form_post();
$normalized = array(
	'enforce' => isset( $_POST['enforce'] ) ? 1 : 0,
	'enabled' => isset( $_POST['enabled'] ) ? 1 : 0,
	'rest'    => isset( $_POST['rest'] ) ? 1 : 0,
	'xmlrpc'  => isset( $_POST['xmlrpc'] ) ? 1 : 0,
	'woo'     => isset( $_POST['woo'] ) ? 1 : 0,
);
nc_assert(
	$normalized === array( 'enforce' => 0, 'enabled' => 1, 'rest' => 0, 'xmlrpc' => 1, 'woo' => 0 ),
	'Multisite toggle normalization preserves checked and unchecked values'
);

// 2. Reversible strategy: Spam must move to Trash instead of being deleted.
$GLOBALS['nc_test_comments'] = array(
	1 => array( 'status' => 'spam', 'post_id' => 10, 'post_type' => 'post' ),
);
$changed = \NoComments\Application\DeleteService::delete( 'spam', array(), 'trash' );
nc_assert( 1 === $changed, 'Spam reversible cleanup reports one changed comment' );
nc_assert( isset( $GLOBALS['nc_test_comments'][1] ), 'Spam reversible cleanup does not permanently delete the comment' );
nc_assert( 'trash' === $GLOBALS['nc_test_comments'][1]['status'], 'Spam reversible cleanup moves the comment to Trash' );

// Explicit Trash scope must still be permanent even if strategy=trash was requested.
$changed = \NoComments\Application\DeleteService::delete( 'trash', array(), 'trash' );
nc_assert( 1 === $changed && empty( $GLOBALS['nc_test_comments'] ), 'Explicit Trash scope permanently empties Trash' );

// "All + Trash" must keep moved comments in Trash instead of emptying them.
$GLOBALS['nc_test_comments'] = array(
	2 => array( 'status' => 'approve', 'post_id' => 20, 'post_type' => 'post' ),
	3 => array( 'status' => 'hold', 'post_id' => 21, 'post_type' => 'post' ),
	4 => array( 'status' => 'spam', 'post_id' => 22, 'post_type' => 'post' ),
	5 => array( 'status' => 'trash', 'post_id' => 23, 'post_type' => 'post' ),
);
$changed = \NoComments\Application\DeleteService::delete( 'all', array(), 'trash' );
nc_assert( 3 === $changed, 'All + Trash changes only non-trash comments' );
nc_assert( 4 === count( $GLOBALS['nc_test_comments'] ), 'All + Trash keeps existing and newly trashed comments' );
foreach ( $GLOBALS['nc_test_comments'] as $comment ) {
	nc_assert( 'trash' === $comment['status'], 'All + Trash leaves every retained comment in Trash' );
}

// 3. Aggregate queries must be scoped to configured exceptions + Woo products.
$GLOBALS['nc_test_options'] = array(
	No_Comments_Plugin::OPTION_KEY        => 1,
	No_Comments_Plugin::OPTION_WOO        => 1,
	No_Comments_Plugin::OPTION_EXCEPTIONS => array( 'page' ),
);
$query = (object) array(
	'query_vars' => array(
		'count'     => false,
		'fields'    => 'all',
		'status'    => 'approve',
		'post_id'   => 0,
		'post_type' => 'any',
	),
);
\NoComments\Infrastructure\OptionsRepository::scope_exception_comment_query( $query );
nc_assert( array( 'page', 'product' ) === $query->query_vars['post_type'], 'Aggregate query is limited to effective exception post types' );
nc_assert( ! empty( $query->query_vars['_no_comments_exception_scope'] ), 'Aggregate exception query is marked for restoration' );
$result = \NoComments\Infrastructure\OptionsRepository::restore_scoped_exception_query( array(), $query );
nc_assert( null === $result, 'Exception query restores normal WordPress querying after the blocker short-circuit' );

echo "All NO Comments regression checks passed.\n";
