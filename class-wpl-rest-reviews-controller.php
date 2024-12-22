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
			$data[]   = $response;
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

		$post_data['id'] = $review->id;
		$post_data['email'] = $review->email;

		$schema = $this->get_item_schema( $request );

		if ( isset( $schema['properties']['slug'] ) ) {
			$post_data['slug'] = (int) $review->book_slug;
		}

		if ( isset( $schema['properties']['time'] ) ) {
			$post_data['time'] = (int) $review->review_time;
		}

		if ( isset( $schema['properties']['review'] ) ) {
			$post_data['review'] = apply_filters( 'the_review_content', $review->review_text, $review );
		}

		if ( isset( $schema['properties']['rating'] ) ) {
			$post_data['rating'] = (int) $review->star_rating;
		}

		return rest_ensure_response( $post_data );
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
			'title'      => 'review',
			'type'       => 'object',
			// In JSON Schema you can specify object properties in the properties attribute.
			'properties' => array(
				'id'    => array(
					'description' => esc_html__( 'Unique identifier for the review.', 'wp-learn-book-reviews' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'slug' => array(
					'description' => esc_html__( 'The book slug on the review.', 'wp-learn-book-reviews' ),
					'type'        => 'string',
				),
				'time' => array(
					'description' => esc_html__( 'The review time on the review.', 'wp-learn-book-reviews' ),
					'type'        => 'string',
				),
				'email' => array(
					'description' => esc_html__( 'The email on the review.', 'wp-learn-book-reviews' ),
					'type'        => 'string',
				),
				'review' => array(
					'description' => esc_html__( 'The review text on the review.', 'wp-learn-book-reviews' ),
					'type'        => 'string',
				),
				'rating' => array(
					'description' => esc_html__( 'The star rating on the review.', 'wp-learn-book-reviews' ),
					'type'        => 'integer',
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