<?php
/**
 * Handles actions and filters for the addon.
 *
 * @package     wpum-delete-account
 * @copyright   Copyright (c) 2018, Alessandro Tesoro
 * @license     https://opensource.org/licenses/gpl-license GNU Public License
 * @since       1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register a new tab within the account page.
 *
 * @param array $tabs
 * @return array
 */
function wpumda_register_account_tab( $tabs ) {

	$tabs['delete-account'] = [
		'name'     => esc_html__( 'Delete account', 'wpum-delete-account' ),
		'priority' => 3,
	];

	return $tabs;

}
add_filter( 'wpum_get_account_page_tabs', 'wpumda_register_account_tab' );

/**
 * Tell WPUM to load the form for this addon from within this plugin's path.
 *
 * @param string $path
 * @param string $form_name
 * @return string
 */
function wpumda_register_form_path( $path, $form_name ) {

	if ( $form_name == 'delete-account' ) {
		$path = WPUMDA_PLUGIN_DIR . 'includes/class-wpum-form-delete-account.php';
	}

	return $path;
}
add_filter( 'wpum_load_form_path', 'wpumda_register_form_path', 20, 2 );

/**
 * Display the content for the account cancellation tab.
 *
 * @return void
 */
function wpumda_register_account_tab_content() {

	echo WPUM()->forms->get_form( 'delete-account', [] );

}
add_action( 'wpum_account_page_content_delete-account', 'wpumda_register_account_tab_content' );

/**
 * Whether a user is the only administrator of the site.
 *
 * @param WP_User $user
 * @return bool
 */
function wpumda_is_last_administrator( $user ) {

	if ( ! in_array( 'administrator', (array) $user->roles, true ) ) {
		return false;
	}

	$others = get_users(
		array(
			'role'    => 'administrator',
			'exclude' => array( $user->ID ),
			'number'  => 1,
			'fields'  => 'ID',
		)
	);

	return empty( $others );

}

/**
 * Remove a deleted user's personal details from their comments, keeping the comments.
 *
 * Follows wp_comments_personal_data_eraser(), with two differences: it also finds comments by
 * user ID, since the user's email may have changed since, and it leaves no part of the IP.
 *
 * @param WP_User $user The deleted user, whose object still holds their ID and email.
 * @return void
 */
function wpumda_anonymize_comments( $user ) {

	global $wpdb;

	// The empty check stops a user with no email matching every guest comment without one.
	$comment_ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT comment_ID FROM {$wpdb->comments} WHERE user_id = %d OR ( comment_author_email != '' AND comment_author_email = %s )",
			$user->ID,
			$user->user_email
		)
	);

	$anonymized = array(
		'comment_agent'        => '',
		/* translators: Name of a comment's author after being anonymized. */
		'comment_author'       => __( 'Anonymous' ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- WordPress's own string.
		'comment_author_email' => '',
		'comment_author_IP'    => '',
		'comment_author_url'   => '',
		'user_id'              => 0,
	);

	foreach ( $comment_ids as $comment_id ) {

		/** This filter is documented in wp-includes/comment.php */
		if ( true !== apply_filters( 'wp_anonymize_comment', true, get_comment( $comment_id ), $anonymized ) ) {
			continue;
		}

		$wpdb->update( $wpdb->comments, $anonymized, array( 'comment_ID' => $comment_id ) );

		clean_comment_cache( $comment_id );

	}

}

/**
 * Add a new path to WPUM's template loader.
 */
function wpumda_set_template_loader_path( $file_paths ) {

	$file_paths[12] = trailingslashit( WPUMDA_PLUGIN_DIR . 'templates' );

	return $file_paths;

}
add_filter( 'wpum_template_paths', 'wpumda_set_template_loader_path' );
