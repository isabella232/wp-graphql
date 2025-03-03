<?php

class UserConnectionPaginationTest extends \Codeception\TestCase\WPTestCase {

	public $user_ids;
	public $admin;

	public function setUp(): void {
		parent::setUp();

		$this->admin = $this->factory()->user->create(['role' => 'administrator' ] );
		$this->user_ids = $this->create_users( 20 );
		WPGraphQL::clear_schema();
	}

	public function tearDown(): void {
		parent::tearDown(); // TODO: Change the autogenerated stub
	}

	/**
	 * Creates several users for use in cursor query tests
	 *
	 * @param  int $count Number of posts to create.
	 *
	 * @return array
	 */
	public function create_users($count = 20)
	{

		// Create users
		$created_users = [];
		for ($i = 1; $i <= $count; $i ++) {
			$created_users[] = $this->factory()->user->create([
				'role' => 'editor',
			]);
		}

		return $created_users;
	}

	public function testPaginateForwardAndBackward() {

		$users = new WP_User_Query([
			'number' => 20,
			'fields' => 'ids'
		]);

		$query = '
		query getUsers($first: Int, $after: String, $last: Int, $before: String) {
		  users(first: $first, last: $last, before: $before, after: $after) {
		    pageInfo {
		      endCursor
		      startCursor
		      hasPreviousPage
		      hasNextPage
		    }
		    nodes {
		      databaseId
		      id
		    }
		  }
		}
		';

		codecept_debug( $users );

		$actual = graphql( [
			'query'     => $query,
			'variables' => [
				'first'  => 2,
				'after'  => null,
				'last'   => null,
				'before' => null,
			]
		] );

		codecept_debug( $actual );
		return;

		$this->assertArrayNotHasKey( 'errors', $actual );

		$latest_ids_first = array_reverse( $users );

		// assert there are 2 items in the query
		$this->assertCount( 2, $actual['data']['users']['nodes'] );

		// Assert the first item is the most recent post
		$this->assertSame( $latest_ids_first[0], $actual['data']['users']['nodes'][0]['databaseId'] );

		// Assert the 2nd item is the 2nd most recent post
		$this->assertSame( $latest_ids_first[1], $actual['data']['users']['nodes'][1]['databaseId'] );

		// Query the next page
		$actual = graphql( [
			'query'     => $query,
			'variables' => [
				'first'  => 2,
				'after'  => $actual['data']['posts']['pageInfo']['endCursor'],
				'last'   => null,
				'before' => null,
			]
		] );

		// assert there are 2 items in the query
		$this->assertCount( 2, $actual['data']['users']['nodes'] );

		// Assert the first item is the 3rd most recent post
		$this->assertSame( $latest_ids_first[2], $actual['data']['users']['nodes'][0]['databaseId'] );

		// Assert the 2nd item is the 4th most recent post
		$this->assertSame( $latest_ids_first[3], $actual['data']['users']['nodes'][1]['databaseId'] );

		// Query the next page
		$actual = graphql( [
			'query'     => $query,
			'variables' => [
				'first'  => 2,
				'after'  => $actual['data']['posts']['pageInfo']['endCursor'],
				'last'   => null,
				'before' => null,
			]
		] );

		// assert there are 2 items in the query
		$this->assertCount( 2, $actual['data']['users']['nodes'] );

		// Assert the first item is the 5th most recent post
		$this->assertSame( $latest_ids_first[4], $actual['data']['posts']['nodes'][0]['databaseId'] );

		// Assert the 2nd item is the 6th most recent post
		$this->assertSame( $latest_ids_first[5], $actual['data']['posts']['nodes'][1]['databaseId'] );

		// Query the previous page
		$actual = graphql( [
			'query'     => $query,
			'variables' => [
				'first'  => null,
				'after'  => null,
				'last'   => 2,
				'before' => $actual['data']['posts']['pageInfo']['startCursor'],
			]
		] );

		// assert there are 2 items in the query
		$this->assertCount( 2, $actual['data']['posts']['nodes'] );

		// Assert the first item is the 3rd most recent post
		$this->assertSame( $latest_ids_first[2], $actual['data']['posts']['nodes'][0]['databaseId'] );

		// Assert the 2nd item is the 4th most recent post
		$this->assertSame( $latest_ids_first[3], $actual['data']['posts']['nodes'][1]['databaseId'] );

		// Query the previous page
		$actual = graphql( [
			'query'     => $query,
			'variables' => [
				'first'  => null,
				'after'  => null,
				'last'   => 2,
				'before' => $actual['data']['posts']['pageInfo']['startCursor'],
			]
		] );

		// assert there are 2 items in the query
		$this->assertCount( 2, $actual['data']['posts']['nodes'] );

		// Assert the first item is the 3rd most recent post
		$this->assertSame( $latest_ids_first[0], $actual['data']['users']['nodes'][0]['databaseId'] );

		// Assert the 2nd item is the 4th most recent post
		$this->assertSame( $latest_ids_first[1], $actual['data']['users']['nodes'][1]['databaseId'] );
	}

	public function testPaginateForwardAndBackwardOrderedByLogin() {

		$user_query = new WP_User_Query([
			'number' => 20,
			'orderby' => 'login',
			'order' => 'DESC',
			'fields' => 'ids'
		]);

		$users = $user_query->get_results();

		$users = array_map( function( $user ) {
			return absint( $user );
		}, $users );

		codecept_debug( $users );

		wp_set_current_user( $this->admin );

		$query = '
		query getUsers($first: Int, $after: String, $last: Int, $before: String) {
		  users(first: $first, last: $last, before: $before, after: $after) {
		    pageInfo {
		      endCursor
		      startCursor
		      hasPreviousPage
		      hasNextPage
		    }
		    nodes {
		      databaseId
		      id
		    }
		  }
		}
		';

		$actual = graphql( [
			'query'     => $query,
			'variables' => [
				'first'  => 2,
				'after'  => null,
				'last'   => null,
				'before' => null,
				'where' => [
					'orderby' => [
						'field' => 'LOGIN',
						'order' => 'DESC',
					],
				],
			],
		] );

		$this->assertArrayNotHasKey( 'errors', $actual );


		// assert there are 2 items in the query
		$this->assertCount( 2, $actual['data']['users']['nodes'] );

		// Assert the first item is the most recent post
		$this->assertSame( $users[0], $actual['data']['users']['nodes'][0]['databaseId'] );

		// Assert the 2nd item is the 2nd most recent post
		$this->assertSame( $users[1], $actual['data']['users']['nodes'][1]['databaseId'] );

		// Query the next page
		$actual = graphql( [
			'query'     => $query,
			'variables' => [
				'first'  => 2,
				'after'  => $actual['data']['users']['pageInfo']['endCursor'],
				'last'   => null,
				'before' => null,
			]
		] );

		// assert there are 2 items in the query
		$this->assertCount( 2, $actual['data']['users']['nodes'] );

		// Assert the first item is the 3rd most recent user
		$this->assertSame( $users[2], $actual['data']['users']['nodes'][0]['databaseId'] );

		// Assert the 2nd item is the 4th most recent user
		$this->assertSame( $users[3], $actual['data']['users']['nodes'][1]['databaseId'] );

		// Query the next page
		$actual = graphql( [
			'query'     => $query,
			'variables' => [
				'first'  => 2,
				'after'  => $actual['data']['users']['pageInfo']['endCursor'],
				'last'   => null,
				'before' => null,
			]
		] );

		// assert there are 2 items in the query
		$this->assertCount( 2, $actual['data']['users']['nodes'] );

		// Assert the first item is the 5th most recent user
		$this->assertSame( $users[4], $actual['data']['users']['nodes'][0]['databaseId'] );

		// Assert the 2nd item is the 6th most recent user
		$this->assertSame( $users[5], $actual['data']['users']['nodes'][1]['databaseId'] );

		// Query the previous page
		$actual = graphql( [
			'query'     => $query,
			'variables' => [
				'first'  => null,
				'after'  => null,
				'last'   => 2,
				'before' => $actual['data']['users']['pageInfo']['startCursor'],
			]
		] );

		// assert there are 2 items in the query
		$this->assertCount( 2, $actual['data']['users']['nodes'] );

		// Assert the first item is the 3rd most recent user
		$this->assertSame( $users[2], $actual['data']['users']['nodes'][0]['databaseId'] );

		// Assert the 2nd item is the 4th most recent user
		$this->assertSame( $users[3], $actual['data']['users']['nodes'][1]['databaseId'] );

		// Query the previous page
		$actual = graphql( [
			'query'     => $query,
			'variables' => [
				'first'  => null,
				'after'  => null,
				'last'   => 2,
				'before' => $actual['data']['users']['pageInfo']['startCursor'],
			]
		] );

		// assert there are 2 items in the query
		$this->assertCount( 2, $actual['data']['users']['nodes'] );

		// Assert the first item is the 3rd most recent user
		$this->assertSame( $users[0], $actual['data']['users']['nodes'][0]['databaseId'] );

		// Assert the 2nd item is the 4th most recent user
		$this->assertSame( $users[1], $actual['data']['users']['nodes'][1]['databaseId'] );
	}
}
