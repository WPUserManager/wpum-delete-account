<?php
/**
 * A member with the right password and a valid nonce deletes their account: the account
 * goes, they're logged out and redirected, and the hooks fire in order.
 */
class DeleteAccountTest extends AddonTestCase {

	public function test_the_right_password_and_nonce_delete_the_account() {
		$user_id = $this->login_as_member();

		$result = $this->submit();

		$this->assertFalse( $this->user_exists( $user_id ) );
		$this->assertFalse( get_userdata( $user_id ) );
		$this->assertSame( array(), $result->errors );
	}

	public function test_exactly_one_account_is_deleted() {
		$this->create_member();
		$this->create_member();
		$this->login_as_member();
		$before = $this->user_count();

		$this->submit();

		$this->assertSame( $before - 1, $this->user_count() );
	}

	public function test_the_members_meta_is_removed() {
		global $wpdb;

		$user_id = $this->login_as_member();
		update_user_meta( $user_id, 'wpum_favourite_colour', 'blue' );

		$this->submit();

		$this->assertSame( '0', $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE user_id = %d", $user_id ) ) ); // phpcs:ignore
	}

	public function test_the_member_is_logged_out() {
		$user_id = $this->login_as_member();

		$logged_out = null;
		add_action(
			'wp_logout',
			function ( $id ) use ( &$logged_out ) {
				$logged_out = $id;
			}
		);

		$this->submit();

		$this->assertSame( 0, get_current_user_id() );
		$this->assertFalse( is_user_logged_in() );
		$this->assertSame( $user_id, $logged_out, 'wp_logout() ends the session and clears the cookies.' );
	}

	public function test_the_member_is_redirected_to_the_home_page_by_default() {
		$this->login_as_member();

		$result = $this->submit();

		$this->assertSame( home_url(), $result->redirect );
	}

	public function test_the_member_is_redirected_to_the_chosen_page() {
		$page_id = $this->create_page( 'Sorry to see you go' );
		$this->set_redirect_page( $page_id );
		$this->login_as_member();

		$result = $this->submit();

		$this->assertSame( get_permalink( $page_id ), $result->redirect );
	}

	public function test_an_empty_redirect_setting_goes_to_the_home_page() {
		wpum_update_option( 'account_cancellation_redirect', array() );
		$this->login_as_member();

		$result = $this->submit();

		$this->assertSame( home_url(), $result->redirect );
	}

	/**
	 * The redirect is to a page deleted since the setting was saved. get_permalink() returns
	 * false, wp_safe_redirect() does nothing and the handler exits: a blank page, right after
	 * deleting an account.
	 *
	 * @group known-bug
	 */
	public function test_a_deleted_redirect_page_falls_back_to_the_home_page() {
		$page_id = $this->create_page( 'Gone' );
		$this->set_redirect_page( $page_id );
		wp_delete_post( $page_id, true );
		$this->login_as_member();

		$result = $this->submit();

		$this->assertSame( home_url(), $result->redirect );
	}

	public function test_no_email_is_sent() {
		$this->login_as_member();

		$this->submit();

		$this->assertSame( array(), $this->emails );
	}

	public function test_the_hooks_fire_in_order_around_the_deletion() {
		$user_id = $this->login_as_member();

		$events = array();
		$record = function ( $name ) use ( &$events ) {
			return function () use ( $name, &$events ) {
				$events[] = $name;
			};
		};

		add_action( 'wpum_before_delete_account', $record( 'before' ) );
		add_action( 'wp_logout', $record( 'logout' ) );
		add_action( 'delete_user', $record( 'delete_user' ) );
		add_action( 'deleted_user', $record( 'deleted_user' ) );
		add_action( 'wpum_after_delete_account', $record( 'after' ) );

		$this->submit();

		$this->assertSame( array( 'before', 'logout', 'delete_user', 'deleted_user', 'after' ), $events );
	}

	/**
	 * The hook exists so addons can clean up, so the user has to be there still.
	 */
	public function test_before_hook_gets_the_user_while_they_still_exist() {
		$user_id = $this->login_as_member();

		$seen = array();
		add_action(
			'wpum_before_delete_account',
			function ( $user ) use ( &$seen ) {
				$seen = array(
					'user'    => $user,
					'exists'  => $this->user_exists( $user->ID ),
					'current' => get_current_user_id(),
				);
			}
		);

		$this->submit();

		$this->assertInstanceOf( WP_User::class, $seen['user'] );
		$this->assertSame( $user_id, $seen['user']->ID );
		$this->assertTrue( $seen['exists'] );
		$this->assertSame( $user_id, $seen['current'] );
	}

	public function test_after_hook_gets_the_id_once_the_user_has_gone() {
		$user_id = $this->login_as_member();

		$seen = array();
		add_action(
			'wpum_after_delete_account',
			function ( $id ) use ( &$seen ) {
				$seen = array(
					'id'      => $id,
					'exists'  => $this->user_exists( $id ),
					'current' => get_current_user_id(),
				);
			}
		);

		$this->submit();

		$this->assertSame( $user_id, $seen['id'] );
		$this->assertFalse( $seen['exists'] );
		$this->assertSame( 0, $seen['current'] );
	}

	public function test_the_allowed_filter_gets_the_user() {
		$user_id = $this->login_as_member();

		$seen = array();
		add_filter(
			'wpum_before_delete_account_allowed',
			function ( $allowed, $user ) use ( &$seen ) {
				$seen = array( $allowed, $user->ID );

				return $allowed;
			},
			10,
			2
		);

		$this->submit();

		$this->assertSame( array( true, $user_id ), $seen );
		$this->assertFalse( $this->user_exists( $user_id ) );
	}

	public function test_a_wp_error_from_the_allowed_filter_stops_the_deletion() {
		$user_id = $this->login_as_member();

		add_filter(
			'wpum_before_delete_account_allowed',
			function () {
				return new WP_Error( 'active_subscription', 'Cancel your subscription first.' );
			}
		);

		$hooks = 0;
		add_action(
			'wpum_before_delete_account',
			function () use ( &$hooks ) {
				$hooks++;
			}
		);

		$result = $this->submit();

		$this->assertAccountKept( $user_id );
		$this->assertSame( array( 'Cancel your subscription first.' ), $result->errors );
		$this->assertNull( $result->redirect );
		$this->assertSame( 0, $hooks, 'A refused deletion must not run the cleanup hook.' );
	}

	/**
	 * A filter called _allowed that returns false deletes the account regardless: only a
	 * WP_Error is honoured.
	 *
	 * @group known-bug
	 */
	public function test_false_from_the_allowed_filter_stops_the_deletion() {
		$user_id = $this->login_as_member();

		add_filter( 'wpum_before_delete_account_allowed', '__return_false' );

		$this->submit();

		$this->assertAccountKept( $user_id );
	}

	/**
	 * WordPress never unslashes passwords: wp_signon() checks $_POST['pwd'] as it comes, so a
	 * password set through any form is stored slashed. The addon compares $_POST the same way.
	 */
	public function test_a_password_with_quotes_and_a_backslash_works_as_it_does_at_login() {
		$password = 'It\'s "my" pass\\word1!';
		$user_id  = $this->login_as_member( 'subscriber', array( 'user_pass' => wp_slash( $password ) ) );

		$this->submit( array( 'password' => $password ) );

		$this->assertFalse( $this->user_exists( $user_id ) );
	}

	/**
	 * Old accounts keep their phpass hash until the next login.
	 */
	public function test_a_password_hashed_by_an_older_wordpress_is_accepted() {
		global $wpdb;

		$user_id = $this->login_as_member();

		require_once ABSPATH . WPINC . '/class-phpass.php';
		$wpdb->update( $wpdb->users, array( 'user_pass' => ( new PasswordHash( 8, true ) )->HashPassword( self::PASSWORD ) ), array( 'ID' => $user_id ) );
		clean_user_cache( $user_id );
		wp_set_current_user( 0 );
		wp_set_current_user( $user_id );

		$this->submit();

		$this->assertFalse( $this->user_exists( $user_id ) );
	}

	public function test_the_form_runs_on_the_wp_hook() {
		$form = $this->form();

		$this->assertSame( 10, has_action( 'wp', array( $form, 'process' ) ) );
	}

	public function test_wpum_loads_the_form_when_it_is_posted() {
		$this->assertInstanceOf( WPUM_Form_Delete_Account::class, WPUM()->forms->load_posted_form( 'delete-account' ) );
	}
}
