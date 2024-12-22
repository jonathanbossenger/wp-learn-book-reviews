<?php
/**
 * Plugin Name: WP Learn Book Reviews
 * Description: Creates a book custom post type, with a reviews custom table.
 * Version: 1.0.0
 * License: GPL2
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
		'/book-review/',
		array(
			'methods'             => 'POST',
			'callback'            => 'wp_learn_create_book_review',
			'permission_callback' => '__return_true',
		)
	);
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

	$book_id     = intval( $request['book_id'] );
	$email       = sanitize_email( $request['email'] );
	$review_text = sanitize_textarea_field( $request['review_text'] );
	$star_rating = intval( $request['star_rating'] );

	$rows = $wpdb->insert(
		$table_name,
		array(
			'book_id'     => $book_id,
			'email'       => $email,
			'review_text' => $review_text,
			'star_rating' => $star_rating,
		)
	);

	return $rows;
}
