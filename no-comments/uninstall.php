<?php
/**
 * Uninstall for NO Comments.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

$option_keys = [
    'no_comments_enabled',
    'no_comments_disable_rest',
    'no_comments_disable_xmlrpc',
    'no_comments_keep_woo_reviews',
];

if ( is_multisite() ) {
    // number => 0 is required here: get_sites() otherwise defaults to 100 sites.
    $site_ids = get_sites(
        [
            'fields' => 'ids',
            'number' => 0,
        ]
    );

    foreach ( $site_ids as $site_id ) {
        switch_to_blog( (int) $site_id );
        foreach ( $option_keys as $key ) {
            delete_option( $key );
        }
        restore_current_blog();
    }

    delete_site_option( 'no_comments_network_settings' );
} else {
    foreach ( $option_keys as $key ) {
        delete_option( $key );
    }
}
