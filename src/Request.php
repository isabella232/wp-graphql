<?php

namespace WPGraphQL;

use GraphQL\Server\ServerConfig;
use GraphQL\Server\StandardServer;
use WPGraphQL\Server\WPHelper;

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
	 * Cached global post.
	 *
	 * @var WP_Post|null
	 */
	private $global_post = null;

	/**
	 * Constructor
	 */
	public function __construct( $request_data = [] ) {

		/**
		 * Whether it's a GraphQL Request (http or internal)
		 *
		 * @since 0.0.5
		 */
		if ( ! defined( 'GRAPHQL_REQUEST' ) ) {
			define( 'GRAPHQL_REQUEST', true );
		}

		/**
		 * Action – intentionally with no context – to indicate a GraphQL Request has started
		 * Types are not set up until this action has run!
		 */
		do_action( 'init_graphql_request' );

		/**
		 * Allow the request data to be filtered
		 *
		 * @param array $data An array containing the pieces of the data of the GraphQL request
		 */
		$request_data = apply_filters( 'graphql_request_data', $request_data );

		$this->schema = \WPGraphQL::get_schema();
		$this->app_context = \WPGraphQL::get_app_context();

		$this->query = $request_data['query'];
		$this->operation_name = $request_data['operation_name'];

		$this->extensions = $this->get_request_data( $request_data['extensions'] );
		$this->variables = $this->get_request_data( $request_data['variables'] );
	}

	private function get_request_data( $data ) {
		if ( empty( $data ) ) {
			return null;
		}

		/**
		 * If the data is already formatted as an array, sanitize and use it.
		 */
		if ( is_array( $data ) ) {
			$sanitized = [];
			foreach ( $data as $key => $value ) {
				$sanitized[ $key ] = sanitize_text_field( $value );
			}

			return $sanitized;
		}

		/**
		 * If the variables are not an array, let's attempt to safely decode them as
		 * JSON and convert them to an array.
		 */
		$data = (string) $data;
		return json_decode( wp_kses_stripslashes( $data ), true );
	}

	/**
	 * Initialize the GraphQL Request.
	 *
	 * This defines that the Request is a GraphQL Request and fires off the
	 * `init_graphql_request` hook which is a great place for plugins to hook
	 * in and modify things that should only occur in the context
	 * of a GraphQL Request.
	 */
	private function before_execute() {

		/**
		 * Store the global post so it can be reset after GraphQL execution
		 *
		 * This allows for a GraphQL query to be used in the middle of post content, such as in a Shortcode
		 * without disrupting the flow of the post as the global POST before and after GraphQL execution will be
		 * the same.
		 */
		if ( ! empty( $GLOBALS['post'] ) ) {
			$this->global_post = $GLOBALS['post'];
		}

	}

	private function do_action() {
		/**
		 * Run an action for each request.
		 *
		 * @param string $query          The GraphQL query
		 * @param string $operation_name The name of the operation
		 * @param string $variables      Variables to be passed to your GraphQL request
		 */
		do_action( 'do_graphql_request', $this->query, $this->operation, $this->variables );
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
	private function after_execute( $result, $operation_name, $request, $variables, $graphql_results ) {

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
		do_action( 'graphql_execute', $result, $this->schema, $operation_name, $request, $variables );

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
		$filtered_result = apply_filters( 'graphql_request_results', $result, $this->schema, $operation_name, $request, $variables );

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
		do_action( 'graphql_return_response', $filtered_result, $result, $this->schema, $operation_name, $request, $variables );

		/**
		 * Reset the global post after execution
		 *
		 * This allows for a GraphQL query to be used in the middle of post content, such as in a Shortcode
		 * without disrupting the flow of the post as the global POST before and after GraphQL execution will be
		 * the same.
		 */
		if ( ! empty( $this->global_post ) ) {
			$GLOBALS['post'] = $this->global_post;
		}
	}

	/**
	 * Returns all registered Schemas
	 *
	 * @return array
	 */
	public function execute() {

		/**
		 * Initialize the GraphQL Request
		 */
		$this->before_execute();

		$result = \GraphQL\GraphQL::executeQuery(
			$this->schema,
			$this->query,
			null,
			$this->app_context,
			$this->variables,
			$this->operation_name
		);

		/**
		 * Return the result of the request
		 */
		$response = $result->toArray( GRAPHQL_DEBUG );

		/**
		 * Ensure the response is returned as a proper, populated array. Otherwise add an error.
		 */
		if ( empty( $response ) || ! is_array( $response ) ) {
			$response = [
				'errors' => __( 'The GraphQL request returned an invalid response', 'wp-graphql' ),
			];
		}

		$this->after_execute( $response, $operation_name, $request, $variables, $graphql_results );

		return $response;
	}

  /**
	 * Returns all registered Schemas
	 *
	 * @return array
	 */
	public function execute_http() {

		/**
		 * Initialize the GraphQL Request
		 */
		$this->before_execute();

		/**
		 * Get the response.
		 */
		$server   = $this->get_server();
		$response = $server->executeRequest();

		$this->after_execute( $response, null, null, null, null );

		return $response;
	}

	/**
	 * @param null $request
	 *
	 * @return \GraphQL\Server\StandardServer
	 * @throws \GraphQL\Server\RequestError
	 */
	private function get_server( $request = null ) {

		/**
		 * Parse HTTP request.
		 */
		$helper = new WPHelper();
		$parsed_request = $helper->parseHttpRequest();

		/**
		 * If the request is a batch request it will come back as an array
		 */
		if ( ! is_array( $parsed_request ) ) {
			$parsed_request = [ $parsed_request ];
		}

		/**
		 * Loop through the requests.
		 */
		array_walk( $parsed_request, [ $this, 'do_action' ] );

		$config = new ServerConfig();
		$config
			->setDebug( GRAPHQL_DEBUG )
			->setSchema( $this->schema )
			->setContext( $this->app_context )
			->setQueryBatching( true );

		$server = new StandardServer( $config );

		return $server;
	}
}
