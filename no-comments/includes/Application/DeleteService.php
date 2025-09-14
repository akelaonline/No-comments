<?php
namespace NoComments\Application;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Encapsula las operaciones de borrado y conteo de comentarios.
 */
class DeleteService {
    /**
     * Borra comentarios por alcance y tipos, con estrategia.
     *
     * @param string   $scope    spam|pending|trash|all
     * @param string[] $types    Tipos de post a limitar (vacío = todos)
     * @param string   $strategy delete|trash (si scope=trash, siempre fuerza delete)
     * @return int
     */
    public static function delete( $scope, array $types = [], $strategy = 'delete' ) {
        $deleted = 0;

        $batch_delete = function( $args ) use ( &$deleted, $types, $strategy ) {
            $args = wp_parse_args( $args, [
                'number'  => 200,
                'orderby' => 'comment_ID',
                'order'   => 'ASC',
                'fields'  => 'ids',
                'status'  => 'all',
            ] );
            if ( ! empty( $types ) ) {
                $args['post_type'] = $types;
            }
            $ids = get_comments( $args );
            foreach ( $ids as $cid ) {
                $status = isset( $args['status'] ) ? $args['status'] : 'all';
                $force  = ( 'delete' === $strategy ) || ( 'trash' === $status );
                if ( wp_delete_comment( $cid, $force ) ) {
                    $deleted++;
                }
            }
            return count( $ids );
        };

        switch ( $scope ) {
            case 'spam':
                while ( $batch_delete( [ 'status' => 'spam' ] ) > 0 ) {}
                break;
            case 'pending':
                while ( $batch_delete( [ 'status' => 'hold' ] ) > 0 ) {}
                break;
            case 'trash':
                while ( $batch_delete( [ 'status' => 'trash' ] ) > 0 ) {}
                break;
            case 'all':
            default:
                while ( $batch_delete( [ 'status' => 'approve' ] ) > 0 ) {}
                while ( $batch_delete( [ 'status' => 'hold' ] ) > 0 ) {}
                while ( $batch_delete( [ 'status' => 'spam' ] ) > 0 ) {}
                while ( $batch_delete( [ 'status' => 'trash' ] ) > 0 ) {}
                break;
        }
        return $deleted;
    }

    /**
     * Cuenta cuántos comentarios serían afectados (sin borrar).
     *
     * @param string   $scope
     * @param string[] $types
     * @return int
     */
    public static function count( $scope, array $types = [] ) {
        $map = [
            'spam'    => 'spam',
            'pending' => 'hold',
            'trash'   => 'trash',
            'all'     => 'all',
        ];
        $status = isset( $map[ $scope ] ) ? $map[ $scope ] : 'all';
        if ( 'all' === $status && empty( $types ) ) {
            $c = wp_count_comments();
            return (int) $c->total_comments;
        }
        $args = [ 'status' => $status, 'count' => true, 'orderby' => 'comment_ID', 'order' => 'ASC' ];
        if ( ! empty( $types ) ) {
            $args['post_type'] = $types;
        }
        return (int) get_comments( $args );
    }
}
