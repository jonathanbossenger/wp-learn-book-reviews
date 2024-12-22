<?php
/**
 * REST API Controller for Reviews
 *
 * This file contains the REST API controller class for handling reviews.
 *
 * @package WP_Learn_Book_Reviews
 */
class WPL_REST_Reviews_Controller {
	/**
	 * Controller namespace
	 *
	 * @var string
	 */
	public $namespace;

	/**
	 * Controller route
	 *
	 * @var string
	 */
	public $route;

    /**
	 * Reponse schema property
	 *
	 * @var array
	 */
	public $schema;

	/**
	 * Class constructor
	 */
	public function __construct() {
		$this->namespace = 'wp-learn-book-reviews/v1';
		$this->route     = '/book-reviews';
	}

	/**
	 * Registers the custom REST routes
	 *
	 * @return void
	 */
	public function register_routes() {

		register_rest_route(
			$this->namespace,
			$this->route,
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'schema'              => array( $this, 'get_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			$this->route . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'schema'              => array( $this, 'get_item_schema' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$this->route,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_item' ),
				'permission_callback' => array( $this, 'create_item_permissions_check' ),
				'schema'              => array( $this, 'get_item_schema' ),
			)
		);

	}

	/**
	 * Check permissions.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function get_items_permissions_check( $request ) {
		if ( ! current_user_can( 'read' ) ) {
			return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot view the review resource.' ), array( 'status' => $this->authorization_status_code() ) );
		}
		return true;
	}

	/**
	 * Grabs the reviews and outputs them as a rest response.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function get_items( $request ) {

		global $wpdb;
		$table_name = $wpdb->prefix . 'book_reviews';

		$reviews = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM %i', $table_name )
		);

		$data = array();

		if ( empty( $results ) ) {
			return rest_ensure_response( $data );
		}

		foreach ( $reviews as $review ) {
			$response = $this->prepare_item_for_response( $review, $request );
			$data[]   = $this->prepare_response_for_collection( $response );
		}

		return rest_ensure_response( $data );
	}

	/**
	 * Check permissions for the posts.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function get_item_permissions_check( $request ) {
		if ( ! current_user_can( 'read' ) ) {
			return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot view the review resource.' ), array( 'status' => $this->authorization_status_code() ) );
		}
		return true;
	}

	/**
	 * Gets post data of requested post id and outputs it as a rest response.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function get_item( $request ) {
		$id = (int) $request['id'];
		global $wpdb;
		$table_name = $wpdb->prefix . 'book_reviews';

		$results = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $table_name, $id )
		);

		$review = $results[0];

		if ( empty( $review ) ) {
			return rest_ensure_response( array() );
		}

		$response = $this->prepare_item_for_response( $review, $request );

		return $response;
	}

	/**
	 * Can be used to match the data to the schema we want.
	 *
	 * @param object         $review The comment object whose response is being prepared.
	 * @param WP_REST_Request $request The request object.
	 */
	public function prepare_item_for_response( $review, $request ) {
		$post_data = array();

		$schema = $this->get_item_schema( $request );

		$post_data = $review;

		return rest_ensure_response( $post_data );
	}

	/**
	 * Prepare a response for inserting into a collection of responses.
	 *
	 * This is copied from WP_REST_Controller class in the WP REST API v2 plugin.
	 *
	 * @param WP_REST_Response $response Response object.
	 * @return array Response data, ready for insertion into collection data.
	 */
	public function prepare_response_for_collection( $response ) {
		if ( ! ( $response instanceof WP_REST_Response ) ) {
			return $response;
		}

		$data   = (array) $response->get_data();
		$server = rest_get_server();

		if ( method_exists( $server, 'get_compact_response_links' ) ) {
			$links = call_user_func( array( $server, 'get_compact_response_links' ), $response );
		} else {
			$links = call_user_func( array( $server, 'get_response_links' ), $response );
		}

		if ( ! empty( $links ) ) {
			$data['_links'] = $links;
		}

		return $data;
	}

	/**
	 * Undocumented function
	 *
	 * @param [type] $request
	 * @return void
	 */
	public function create_item_permissions_check( $request ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot create a review.' ), array( 'status' => $this->authorization_status_code() ) );
		}
		return true;
	}

	/**
	 * Undocumented function
	 *
	 * @param [type] $request
	 * @return void
	 */
	public function create_item( $request ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'book_reviews';

		$book_slug   = sanitize_text_field( $request['book_slug'] );
		$email       = sanitize_email( $request['email'] );
		$review_text = sanitize_textarea_field( $request['review_text'] );
		$star_rating = intval( $request['star_rating'] );

		$rows = $wpdb->insert(
			$table_name,
			array(
				'book_slug'   => $book_slug,
				'email'       => $email,
				'review_text' => $review_text,
				'star_rating' => $star_rating,
			)
		);

		return $rows;
	}

	/**
	 * Get our sample schema for a review.
	 *
	 * @return array The sample schema for a review
	 */
	public function get_item_schema() {
		$this->schema = array(
			// This tells the spec of JSON Schema we are using which is draft 4.
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			// The title property marks the identity of the resource.
			'title'      => 'form-submission',
			'type'       => 'object',
			// In JSON Schema you can specify object properties in the properties attribute.
			'properties' => array(
				'id'    => array(
					'description' => esc_html__( 'Unique identifier for the form submission.', 'wp-learn-form-submissions' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'name'  => array(
					'description' => esc_html__( 'The name on the form submission.', 'wp-learn-form-submissions' ),
					'type'        => 'string',
				),
				'email' => array(
					'description' => esc_html__( 'The email on the form submission.', 'wp-learn-form-submissions' ),
					'type'        => 'string',
				),
			),
		);

		return $this->schema;
	}

	/**
	 * Checks and returns HTTP status codes depending on whether the user is authenticated or not
	 *
	 * @return string $status The HTTP status code.
	 */
	public function authorization_status_code() {

		$status = 401;

		if ( is_user_logged_in() ) {
			$status = 403;
		}

		return $status;
	}

}