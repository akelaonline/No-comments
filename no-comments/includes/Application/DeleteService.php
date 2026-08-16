<?php
namespace NoComments\Application;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Encapsula las operaciones de borrado y conteo de comentarios.
 */
class DeleteService {
	/**
	 * Borra comentarios por alcance y tipos, con estrategia.
	 *
	 * @param string   $scope    spam|pending|trash|all.
	 * @param string[] $types    Tipos de post a limitar (vacío = todos).
	 * @param string   $strategy delete|trash (scope=trash siempre fuerza delete).
	 * @return int Cantidad de comentarios modificados.
	 */
	public static function delete( $scope, array $types = array(), $strategy = 'delete' ) {
		$deleted  = 0;
		$scope    = in_array( $scope, array( 'spam', 'pending', 'trash', 'all' ), true ) ? $scope : 'spam';
		$strategy = 'trash' === $strategy ? 'trash' : 'delete';

		$batch_delete = function ( $args ) use ( &$deleted, $types, $strategy ) {
			$args = wp_parse_args(
				$args,
				array(
					'number'  => 200,
					'orderby' => 'comment_ID',
					'order'   => 'ASC',
					'fields'  => 'ids',
					'status'  => 'all',
				)
			);

			if ( ! empty( $types ) ) {
				$args['post_type'] = $types;
			}

			$ids     = get_comments( $args );
			$changed = 0;
			$status  = isset( $args['status'] ) ? $args['status'] : 'all';
			$force   = ( 'delete' === $strategy ) || ( 'trash' === $status );

			foreach ( $ids as $comment_id ) {
				if ( wp_delete_comment( $comment_id, $force ) ) {
					++$deleted;
					++$changed;
				}
			}

			// Returning successful mutations instead of queried IDs prevents an
			// endless loop if another plugin vetoes deleting/trashing a comment.
			return $changed;
		};

		switch ( $scope ) {
			case 'spam':
				while ( $batch_delete( array( 'status' => 'spam' ) ) > 0 ) {
					// Process in bounded batches.
				}
				break;

			case 'pending':
				while ( $batch_delete( array( 'status' => 'hold' ) ) > 0 ) {
					// Process in bounded batches.
				}
				break;

			case 'trash':
				while ( $batch_delete( array( 'status' => 'trash' ) ) > 0 ) {
					// Emptying Trash is always a permanent delete.
				}
				break;

			case 'all':
				while ( $batch_delete( array( 'status' => 'approve' ) ) > 0 ) {
					// Process in bounded batches.
				}
				while ( $batch_delete( array( 'status' => 'hold' ) ) > 0 ) {
					// Process in bounded batches.
				}
				while ( $batch_delete( array( 'status' => 'spam' ) ) > 0 ) {
					// Process in bounded batches.
				}

				// Important: when the requested strategy is reversible, comments
				// moved to Trash above must remain there. Only permanent cleanup
				// should empty Trash in the same operation.
				if ( 'delete' === $strategy ) {
					while ( $batch_delete( array( 'status' => 'trash' ) ) > 0 ) {
						// Process in bounded batches.
					}
				}
				break;
		}

		return $deleted;
	}

	/**
	 * Cuenta cuántos comentarios serían afectados (sin borrar).
	 *
	 * @param string   $scope Alcance solicitado.
	 * @param string[] $types Tipos de post a limitar.
	 * @return int
	 */
	public static function count( $scope, array $types = array() ) {
		$map = array(
			'spam'    => 'spam',
			'pending' => 'hold',
			'trash'   => 'trash',
			'all'     => 'all',
		);

		$status = isset( $map[ $scope ] ) ? $map[ $scope ] : 'spam';

		if ( 'all' === $status && empty( $types ) ) {
			$counts = wp_count_comments();
			return (int) $counts->total_comments;
		}

		$args = array(
			'status'  => $status,
			'count'   => true,
			'orderby' => 'comment_ID',
			'order'   => 'ASC',
		);

		if ( ! empty( $types ) ) {
			$args['post_type'] = $types;
		}

		return (int) get_comments( $args );
	}
}
