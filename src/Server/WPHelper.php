<?php

namespace WPGraphQL\Server;

use GraphQL\Server\Helper;

/**
 * Class WPHelper
 *
 * @package WPGraphQL\Server
 */
class WPHelper extends Helper {
	/**
	 * Parses normalized request params and returns instance of OperationParams
	 * or array of OperationParams in case of batch operation.
	 *
	 * Returned value is a suitable input for `executeOperation` or `executeBatch` (if array)
	 *
	 * @api
	 * @param string $method
	 * @param array $bodyParams
	 * @param array $queryParams
	 * @return OperationParams|OperationParams[]
	 * @throws RequestError
	 */
	public function parseRequestParams( $method, array $bodyParams, array $queryParams ) {
		$parsed_body_params = $this->parse_params( $bodyParams );
		$parsed_query_params = $this->parse_params( $queryParams );

		return parent::parseRequestParams( $method, $parsed_body_params, $parsed_query_params );
	}

	private function parse_params( $params ) {
		if ( isset( $params[0] ) ) {
			return array_map( [ $this, 'parse_extensions' ], $params );
		}

		return $this->parse_extensions( $params );
	}

	private function parse_extensions( $params ) {
		if (is_string($params['extensions'])) {
			$tmp = json_decode(stripslashes( $params['extensions'] ), true);
			if (! json_last_error()) {
				$params['extensions'] = $tmp;
			}
		}

		if ( isset( $params['extensions']['persistedQuery']['sha256Hash'] ) && ! isset( $params['query'] ) ) {
			$params['queryId'] = $params['extensions']['persistedQuery']['sha256Hash'];
		}

		return $params;
	}
}
