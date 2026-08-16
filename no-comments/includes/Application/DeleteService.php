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
		$deleted           = 0;
		$affected_post_ids = array();
		$scope             = in_array( $scope, array( 'spam', 'pending', 'trash', 'all' ), true ) ? $scope : 'spam';
		$strategy          = 'trash' === $strategy ? 'trash' : 'delete';

		$batch_delete = function ( $args ) use ( &$deleted, &$affected_post_ids, $types, $strategy ) {
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
				$post_id = (int) get_comment_post_ID( $comment_id );
				if ( wp_delete_comment( $comment_id, $force ) ) {
					++$deleted;
					++$changed;
					if ( $post_id ) {
						$affected_post_ids[] = $post_id;
					}
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

		if ( $deleted > 0 ) {
			self::purge_affected_posts( $affected_post_ids, $scope, $strategy );
		}

		return $deleted;
	}

	/**
	 * Purga el caché de los posts afectados tras un borrado real.
	 *
	 * Dispara la acción genérica `no_comments_after_delete` para que cualquier
	 * plugin de caché pueda reaccionar, e integra Tucho (hook `tucho_purge_post`)
	 * cuando está activo.
	 *
	 * @param int[]   $post_ids IDs de posts afectados (sin duplicados).
	 * @param string  $scope    Alcance ejecutado.
	 * @param string  $strategy Estrategia ejecutada.
	 */
	private static function purge_affected_posts( array $post_ids, $scope, $strategy ) {
		$post_ids = array_values( array_unique( array_filter( array_map( 'absint', $post_ids ) ) ) );
		if ( empty( $post_ids ) ) {
			return;
		}

		do_action( 'no_comments_after_delete', $post_ids, $scope, $strategy );

		if ( ! has_action( 'tucho_purge_post' ) ) {
			return;
		}
		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );
			if ( $post ) {
				do_action( 'tucho_purge_post', $post_id, $post );
			}
		}
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
