<?php

namespace WPGraphQL;

/**
 * Class Request
 *
 * Proxies a request to graphql-php, applying filters and transforming request
 * data as needed.
 *
 * @package WPGraphQL
 */
class Request {

	/**
	 * Constructor
	 */
	public function __construct() {
		/**
		 * This action can be hooked to to enable various debug tools,
		 * such as enableValidation from the GraphQL Config.
		 *
		 * @since 0.0.4
		 */
		do_action( 'graphql_process_http_request' );
	}

	private static function before_execute() {
		/**
		 * Store the global post so it can be reset after GraphQL execution
		 *
		 * This allows for a GraphQL query to be used in the middle of post content, such as in a Shortcode
		 * without disrupting the flow of the post as the global POST before and after GraphQL execution will be
		 * the same.
		 */
		self::$global_post = ! empty( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
	}

	/**
	 * Apply filters and do actions after GraphQL Execution
	 *
	 * @param array              $result          The result of your GraphQL request
	 * @param string             $operation_name  The name of the operation
	 * @param string             $request         The request that GraphQL executed
	 * @param array|null         $variables       Variables to passed to your GraphQL query,
	 * @param mixed|array|object $graphql_results The results of the GraphQL Execution
	 */
	protected static function after_execute( $result, $operation_name, $request, $variables, $graphql_results ) {

		/**
		 * Run an action. This is a good place for debug tools to hook in to log things, etc.
		 *
		 * @since 0.0.4
		 *
		 * @param array               $result         The result of your GraphQL request
		 * @param \WPGraphQL\WPSchema $schema         The schema object for the root request
		 * @param string              $operation_name The name of the operation
		 * @param string              $request        The request that GraphQL executed
		 * @param array|null          $variables      Variables to passed to your GraphQL query
		 */
		do_action( 'graphql_execute', $result, \WPGraphQL::get_schema(), $operation_name, $request, $variables );

		/**
		 * Filter the $result of the GraphQL execution. This allows for the response to be filtered before
		 * it's returned, allowing granular control over the response at the latest point.
		 *
		 * POSSIBLE USAGE EXAMPLES:
		 * This could be used to ensure that certain fields never make it to the response if they match
		 * certain criteria, etc. For example, this filter could be used to check if a current user is
		 * allowed to see certain things, and if they are not, the $result could be filtered to remove
		 * the data they should not be allowed to see.
		 *
		 * Or, perhaps some systems want the result to always include some additional piece of data in
		 * every response, regardless of the request that was sent to it, this could allow for that
		 * to be hooked in and included in the $result
		 *
		 * @since 0.0.5
		 *
		 * @param array               $result         The result of your GraphQL query
		 * @param \WPGraphQL\WPSchema $schema         The schema object for the root query
		 * @param string              $operation_name The name of the operation
		 * @param string              $request        The request that GraphQL executed
		 * @param array|null          $variables      Variables to passed to your GraphQL request
		 */
		$filtered_result = apply_filters( 'graphql_request_results', $result, \WPGraphQL::get_schema(), $operation_name, $request, $variables );

		/**
		 * Run an action after the result has been filtered, as the response is being returned.
		 * This is a good place for debug tools to hook in to log things, etc.
		 *
		 * @param array               $filtered_result The filtered_result of the GraphQL request
		 * @param array               $result          The result of your GraphQL request
		 * @param \WPGraphQL\WPSchema $schema          The schema object for the root request
		 * @param string              $operation_name  The name of the operation
		 * @param string              $request         The request that GraphQL executed
		 * @param array|null          $variables       Variables to passed to your GraphQL query
		 */
		do_action( 'graphql_return_response', $filtered_result, $result, \WPGraphQL::get_schema(), $operation_name, $request, $variables );

		/**
		 * Reset the global post after execution
		 *
		 * This allows for a GraphQL query to be used in the middle of post content, such as in a Shortcode
		 * without disrupting the flow of the post as the global POST before and after GraphQL execution will be
		 * the same.
		 */
		if ( ! empty( self::$global_post ) ) {
			$GLOBALS['post'] = self::$global_post;
		}

		/**
		 * Run an action after the HTTP Response is ready to be sent back. This might be a good place for tools
		 * to hook in to track metrics, such as how long the process took from `graphql_process_http_request`
		 * to here, etc.
		 *
		 * @param array  $result          The result of the GraphQL Query
		 * @param array  $filtered_result The result, passed through filters
		 * @param string $operation_name  The name of the operation
		 * @param string $request         The request that GraphQL executed
		 * @param array  $variables       Variables to passed to your GraphQL query
		 *
		 * @since 0.0.5
		 */
		do_action( 'graphql_process_http_request_response', $filtered_result, $result, $operation_name, $request, $variables );

	}

	/**
	 * Returns all registered Schemas
	 *
	 * @return array
	 */
	public static function process() {
		/**
		 * If the variables are already formatted as an array use them.
		 *
		 * Example:
		 * ?query=query getPosts($first:Int){posts(first:$first){edges{node{id}}}}&variables[first]=1
		 */
		if ( is_array( $data['variables'] ) ) {
			$sanitized_variables = [];
			foreach ( $data['variables'] as $key => $value ) {
				$sanitized_variables[ $key ] = sanitize_text_field( $value );
			}
			$decoded_variables = $sanitized_variables;

			/**
			 * If the variables are not an array, let's attempt to decode them and convert them to an array for
			 * use in the executor.
			 */
		} else {
			$decoded_variables = json_decode( wp_kses_stripslashes( $data['variables'] ), true );
		}

		if ( false === headers_sent() ) {
			self::prepare_headers( $response, $graphql_results, $request, $operation_name, $variables, $user );
		}



		/**
		 * Ensure the $graphql_request is returned as a proper, populated array,
		 * otherwise add an error to the result
		 */
		if ( ! empty( $graphql_results ) && is_array( $graphql_results ) ) {
			$response = $graphql_results;
		} else {
			$response['errors'] = __( 'The GraphQL request returned an invalid response', 'wp-graphql' );
		}

		self::after_execute( $response, $operation_name, $request, $variables, $graphql_results );

		/**
		 * Allow the data to be filtered
		 *
		 * @param array $data An array containing the pieces of the data of the GraphQL request
		 */
		$data = apply_filters( 'graphql_request_data', $data );

		/**
		 * Send the JSON response
		 */
		$server   = \WPGraphQL::server();
		$response = $server->executeRequest();

		$helper = $server->getHelper();
		$request = $helper->parseHttpRequest();

		self::after_execute( $response, $operation_name, $request, $variables, $graphql_results );
	}

	/**
	 * Given a Schema Name, returns the Schema associated with it
	 *
	 * @param string $schema_name The name of the Schema to return
	 *
	 * @return array|mixed
	 */
	public static function get_schema( $schema_name ) {
		return ! empty( self::$schemas[ $schema_name ] ) && is_array( self::$schemas[ $schema_name ] ) ? self::$schemas[ $schema_name ] : [];
	}

	/**
	 * Given a Schema Name and an array of Schema Config, this adds a Schema to the registry
	 *
	 * Schemas must be registered with a unique name. A Schema registered with an existing Schema
	 * name will not be registered.
	 *
	 * @param string $schema_name The name of the Schema to register
	 * @param array  $config      The config for the Schema to register
	 */
	public static function register_schema( $schema_name, $config ) {
		if ( isset( $schema_name ) && is_string( $schema_name ) && ! empty( $config ) && is_array( $config ) && ! isset( self::$schemas[ $schema_name ] ) ) {
			self::$schemas[ $schema_name ] = self::prepare_schema_config( $config );
		}
	}

	/**
	 * Given a Schema Name, this removes it from the registry
	 *
	 * @param string $schema_name The name of the Schema to remove from the Registry
	 */
	public static function deregister_schema( $schema_name ) {
		if ( isset( self::$schemas[ $schema_name ] ) ) {
			unset( self::$schemas[ $schema_name ] );
		}
	}

	/**
	 * Given the name of a Schema and Config, this prepares it for use in the Registry
	 *
	 * @param array $config The config for the Schema to register
	 *
	 * @return array
	 */
	protected static function prepare_schema_config( $config ) {

		$prepared_schema = [];

		if ( ! empty( $config ) && is_array( $config ) ) {
			foreach ( $config as $field => $type ) {
				if ( is_string( $type ) ) {
					$type = TypeRegistry::get_type( $type );
					if ( ! empty( $type ) ) {
						$prepared_schema[ $field ] = TypeRegistry::get_type( $type );
					}
				} else {
					$prepared_schema[ $field ] = TypeRegistry::get_type( $type );
				}
			}
		}

		return $prepared_schema;

	}

}