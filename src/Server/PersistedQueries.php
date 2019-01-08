<?php

namespace WPGraphQL\Server;

/**
 * Load or save a persisted query from a custom post type. This allows users to
 * avoid sending the query over the wire, saving bandwidth. In particular, it
 * allows for moving to GET requests, which can be cached at the edge.
 *
 * @package WPGraphQL\Server
 */
class PersistedQueries {
	/**
	 * Whether query persistence is enabled. Disabled by default; filter
	 * graphql_persisted_query_enabled to enable.
	 *
	 * @var bool
	 */
	private static $enabled = false;

	/**
	 * Post type for default query persistence. Unused if query persistence is
	 * disabled. Filter with graphql_persisted_query_post_type.
	 *
	 * @var string
	 */
	private static $post_type = 'graphql_query';

	/**
	 * Filter configuration values and register the post type used to store
	 * persisted queries.
	 *
	 * @return void
	 */
	public function __construct() {
		/**
		 * Whether to enable persisted queries (using the default post object
		 * persistence). You have the option of implementing your own persistence
		 * using the graphql_persisted_query filter.
		 *
		 * @since 0.2.0
		 *
		 * @param bool $enabled Enable? (Default: false).
		 */
		self::$enabled = apply_filters( 'graphql_persisted_query_enabled', self::$enabled );

		/**
		 * Post type to use for the default persistence. Unused if default
		 * persistence is not enabled.
		 *
		 * @since 0.2.0
		 *
		 * @param string $post_type
		 */
		self::$post_type = apply_filters( 'graphql_persisted_query_post_type', self::$post_type );

		if ( ! self::$enabled || empty( self::$post_type ) ) {
			return;
		}

		// Register the persisted query post type. Filter register_post_type_args to
		// show persisted queries in GraphQL. 💅
		register_post_type(
			self::$post_type,
			[
				'public'              => false,
				'query_var'           => false,
				'rewrite'             => false,
				'show_in_rest'        => false,
				'show_in_graphql'     => false,
				'graphql_single_name' => 'persistedQuery',
				'graphql_plural_name' => 'persistedQueries',
				'show_ui'             => false,
				'supports'            => [ 'title', 'editor' ],
			]
		);
	}

	/**
	 * Get a GraphQL query corresponding to a query ID (hash). Allow result to be
	 * filtered so that users can bring their own persistence implementation.
	 *
	 * @param  string          $query_id Query ID
	 * @param  OperationParams $params   Operation parameters
	 * @return string Query
	 */
	private static function get_persisted_query( $query_id, OperationParams $params ) {
		$query = self::get_persisted_query_from_default_persistence();

		/**
		 * Filter the persisted query (or retrieve it yourself with your own
		 * persistence implementation).
		 *
		 * @since 0.2.0
		 *
		 * @param string|null     $query
		 * @param string          $query_id
		 * @param OperationParams $params
		 */
		return apply_filters( 'graphql_persisted_query', $query, $query_id, $params );
	}

	/**
	 * Get a GraphQL query corresponding to a query ID (hash) using the default
	 * persistence implementation (custom post type).
	 *
	 * @param  string $query_id Query ID
	 * @return string Query
	 */
	private static function get_persisted_query_from_default_persistence( $query_id ) {
		if ( ! self::$enabled ) {
			return null;
		}

		$post = get_page_by_path( $query_id, 'OBJECT', self::$post_type );
		return isset( $post->post_content ) ? $post->post_content : null;
	}

	/**
	 * Attempts to load a persisted query. Implementors may leave the built-in
	 * persisted query implementation disabled (it is disabled by default) and
	 * implement their own persistence using the graphql_persisted_query filter.
	 *
	 * @param  string          $query_id Query ID (hash)
	 * @param  OperationParams $params   Operation parameters
	 * @return string
	 * @throws RequestError
	 */
	public static function load( $query_id, OperationParams $params ) {
		/**
		 * Run an action when a persisted query has been requested.
		 *
		 * @since 0.2.0
		 *
		 * @param string|null     $query
		 * @param string          $query_id
		 * @param OperationParams $params
		 */
		do_action( 'graphql_load_persisted_query', $query, $query_id, $params );

		return self::get_persisted_query( $query_id, $params );
	}

	/**
	 * Save (persist) a query.
	 *
	 * @param  string          $query  GraphQL query
	 * @param  OperationParams $params Operation params
	 * @return string Query ID
	 */
	public static function save( $query, OperationParams $params ) {
		if ( empty( $query ) ) {
			return null;
		}

		// Don't save the query if it doesn't have an operation name. This gives
		// users an easy, out-of-the-box way to decide which queries get persisted
		// and which are ephemeral.
		if ( ! isset( $params->operation ) || empty( $params->operation ) ) {
			return null;
		}

		/**
		 * Filter the default query ID.
		 *
		 * @since 0.2.0
		 *
		 * @param string          $query_id
		 * @param string|null     $query
		 * @param OperationParams $params
		 */
		$query_id = apply_filters( 'graphql_persisted_query_id', wp_hash( $query ), $query, $params );

		/**
		 * Run an action when a persisted query is ready to be saved.
		 *
		 * @since 0.2.0
		 *
		 * @param string|null     $query
		 * @param string          $query_id
		 * @param OperationParams $params
		 */
		do_action( 'graphql_save_persisted_query', $query, $query_id, $params );

		// Check to see if the query has already been persisted.
		$query = self::get_persisted_query( $query_id, $params );

		// If the query is not empty, that means it has already been persisted
		// (either by the default persistence or by someone who has implemented
		// their own persistence by filtering graphql_persisted_query). Return the
		// existing query ID.
		if ( ! empty( $query ) ) {
			return $query_id;
		}

		// If we're still here, we're relying on the default persistence. Make sure
		// it is enabled.
		if ( ! self::$enabled || empty( $query_id ) ) {
			return null;
		}

		// Persist the query.
		wp_insert_post( [
			'post_content' => $query,
			'post_name'    => $query_id,
			'post_title'   => $params->operation,
			'post_status'  => 'draft',
			'post_type'    => self::$post_type,
		] );

		return $query_id;
	}
}
