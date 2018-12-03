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