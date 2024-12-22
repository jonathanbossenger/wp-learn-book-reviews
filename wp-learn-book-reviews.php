<?php
/**
 * Plugin Name: WP Learn Book Reviews
 * Description: Creates a book custom post type, with a reviews custom table.
 * Version: 1.0.0
 * License: GPL 2.0-or-later
 *
 * @package WP_Learn_Book_Reviews
 */

register_activation_hook( __FILE__, 'wp_learn_setup_book_reviews_table' );
/**
 * Create the book reviews table.
 */
function wp_learn_setup_book_reviews_table() {
	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE {$wpdb->prefix}book_reviews (
      id mediumint(9) NOT NULL AUTO_INCREMENT,
      book_id mediumint(9) NOT NULL,
      review_time datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
      email text NOT NULL,
      review_text text NOT NULL,
      star_rating tinyint(1) NOT NULL,
      PRIMARY KEY (id)
      ) {$charset_collate};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
}

add_action( 'init', 'wp_learn_register_book_post_type' );
/**
 * Register the book custom post type.
 */
function wp_learn_register_book_post_type() {
	$args = array(
		'labels'       => array(
			'name'          => 'Books',
			'singular_name' => 'Book',
			'menu_name'     => 'Books',
			'add_new'       => 'Add New Book',
			'add_new_item'  => 'Add New Book',
			'new_item'      => 'New Book',
			'edit_item'     => 'Edit Book',
			'view_item'     => 'View Book',
			'all_items'     => 'All Books',
		),
		'public'       => true,
		'has_archive'  => true,
		'show_in_rest' => true,
		'rest_base'    => 'books',
		'supports'     => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt' ),
	);

	register_post_type( 'book', $args );
}

add_action( 'rest_api_init', 'wp_learn_register_routes' );
/**
 * Register the REST API wp-learn-book-reviews/v1/book-review routes.
 */
function wp_learn_register_routes() {
	register_rest_route(
		'wp-learn-book-reviews/v1',
		'/book-review',
		array(
			'methods'             => 'POST',
			'callback'            => 'wp_learn_create_book_review',
			'permission_callback' => 'wp_learn_require_permissions',
		)
	);

	register_rest_route(
		'wp-learn-book-reviews/v1',
		'/book-reviews',
		array(
			'methods'             => 'GET',
			'callback'            => 'wp_learn_get_book_reviews',
			'permission_callback' => '__return_true',
		)
	);

	register_rest_route(
		'wp-learn-book-reviews/v1',
		'/book-review/(?P<id>\d+)',
		array(
			'methods'             => 'GET',
			'callback'            => 'wp_learn_get_review',
			'permission_callback' => '__return_true',
		)
	);
}

/**
 * Check if the current user can edit posts.
 *
 * @return bool
 */
function wp_learn_require_permissions() {
	return current_user_can( 'edit_posts' );
}

/**
 * Callback for the wp-learn-book-reviews/v1/book-review POST route
 *
 * @param WP_REST_Request $request The request object.
 *
 * @return int The number of rows inserted.
 */
function wp_learn_create_book_review( $request ) {
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
 * Callback for the wp-learn-book-reviews/v1/book-reviews GET route
 *
 * @return array|object|null
 */
function wp_learn_get_book_reviews() {
	global $wpdb;

	$results = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}book_reviews" );

	return $results;
}

/**
 * Callback for the wp-learn-book-reviews/v1/book-review GET route
 *
 * @param object $request The request object.
 *
 * @return mixed|stdClass
 */
function wp_learn_get_review( $request ) {
	global $wpdb;
	$id = $request['id'];

	$results = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}book_reviews WHERE id = %d",
			$id
		)
	);

	return $results[0];
}
