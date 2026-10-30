<?php
/**
 * WP-CLI commands the E2E suite uses to set up the wp-env site: wp wpumda-e2e <command>.
 *
 * Only registered under WP-CLI, so it does nothing in WPUnit or on page loads.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Set up and inspect the E2E site.
 */
class WPUMDA_E2E_Command {

	/**
	 * The password every member gets.
	 */
	const PASSWORD = 'TestPass123!';

	/**
	 * Create the page the redirect spec uses. Prints the IDs as JSON.
	 */
	public function setup() {
		$slug     = 'sorry-to-see-you-go';
		$existing = get_page_by_path( $slug );
		if ( $existing ) {
			wp_delete_post( $existing->ID, true );
		}

		$goodbye = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_name'    => $slug,
				'post_title'   => 'Sorry to see you go',
				'post_content' => 'Your account has been deleted.',
			)
		);

		// wpum_get_pages() caches the list the setting offers.
		delete_transient( 'wpum_get_pages' );
		wpum_update_option( 'account_cancellation_redirect', false );

		WP_CLI::line( wp_json_encode( array( 'pages' => array( 'goodbye' => (int) $goodbye ) ) ) );
	}

	/**
	 * Create (or recreate) a member, with a post of their own. Prints the IDs as JSON.
	 *
	 * ## OPTIONS
	 *
	 * <login>
	 * : User login. The email is <login>@example.com.
	 *
	 * [--role=<role>]
	 * : Role.
	 * ---
	 * default: subscriber
	 * ---
	 */
	public function create_member( $args, $assoc_args ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';

		$login    = $args[0];
		$existing = get_user_by( 'login', $login );
		if ( $existing ) {
			wp_delete_user( $existing->ID );
		}

		$user_id = wp_insert_user(
			array(
				'user_login' => $login,
				'user_email' => $login . '@example.com',
				'user_pass'  => self::PASSWORD,
				'role'       => $assoc_args['role'],
			)
		);

		if ( is_wp_error( $user_id ) ) {
			WP_CLI::error( $user_id );
		}

		$post_id = wp_insert_post(
			array(
				'post_author'  => $user_id,
				'post_status'  => 'publish',
				'post_title'   => 'A post by ' . $login,
				'post_content' => 'Written before they left.',
			)
		);

		WP_CLI::line(
			wp_json_encode(
				array(
					'id'   => $user_id,
					'post' => $post_id,
				)
			)
		);
	}

	/**
	 * Print a member's ID and roles as JSON, or null if they don't exist.
	 *
	 * ## OPTIONS
	 *
	 * <login>
	 * : User login.
	 */
	public function member( $args ) {
		$user = get_user_by( 'login', $args[0] );

		WP_CLI::line(
			wp_json_encode(
				$user ? array(
					'id'    => $user->ID,
					'roles' => array_values( $user->roles ),
				) : null
			)
		);
	}

	/**
	 * Print a post's status.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Post ID.
	 */
	public function post_status( $args ) {
		WP_CLI::line( (string) get_post_status( (int) $args[0] ) );
	}

	/**
	 * Set the After account cancellation setting, as the settings panel saves it.
	 *
	 * ## OPTIONS
	 *
	 * <page>
	 * : Page ID, or "none" to clear it.
	 */
	public function redirect( $args ) {
		wpum_update_option( 'account_cancellation_redirect', 'none' === $args[0] ? false : array( $args[0] ) );
	}
}

WP_CLI::add_command( 'wpumda-e2e', 'WPUMDA_E2E_Command' );
