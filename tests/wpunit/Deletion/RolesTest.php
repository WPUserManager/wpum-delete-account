<?php
/**
 * Anyone with a password can delete their own account, except the last administrator: a
 * site must always keep one.
 */
class RolesTest extends AddonTestCase {

	/**
	 * @dataProvider roles
	 *
	 * @param string $role
	 */
	public function test_every_role_can_delete_their_own_account( $role ) {
		$user_id = $this->login_as_member( $role );

		$this->submit();

		$this->assertFalse( $this->user_exists( $user_id ) );
	}

	public function roles() {
		return array(
			'subscriber'    => array( 'subscriber' ),
			'contributor'   => array( 'contributor' ),
			'author'        => array( 'author' ),
			'editor'        => array( 'editor' ),
			'administrator' => array( 'administrator' ),
		);
	}

	/**
	 * Remove the site's administrators, so the next one created is the only one.
	 */
	private function remove_administrators() {
		require_once ABSPATH . 'wp-admin/includes/user.php';

		foreach ( get_users( array( 'role' => 'administrator' ) ) as $admin ) {
			wp_delete_user( $admin->ID );
		}
	}

	/**
	 * Log in as the site's only administrator.
	 *
	 * @return int
	 */
	private function login_as_last_administrator() {
		$this->remove_administrators();

		return $this->login_as_member( 'administrator' );
	}

	public function test_the_last_administrator_cannot_delete_their_account() {
		$user_id = $this->login_as_last_administrator();

		$result = $this->submit();

		$this->assertAccountKept( $user_id );
		$this->assertSame( array( self::LAST_ADMINISTRATOR_ERROR ), $result->errors );
		$this->assertNull( $result->redirect );
		$this->assertCount( 1, get_users( array( 'role' => 'administrator' ) ) );
	}

	public function test_the_last_administrator_sees_why_on_the_form() {
		$this->login_as_last_administrator();

		$result = $this->submit();

		$this->assertStringContainsString( self::LAST_ADMINISTRATOR_ERROR, $this->render( array( $result->form, 'output' ) ) );
	}

	public function test_refusing_the_last_administrator_runs_nothing() {
		$this->login_as_last_administrator();

		$fired = array();
		foreach ( array( 'wpum_before_delete_account', 'wpum_after_delete_account', 'delete_user', 'wp_logout' ) as $hook ) {
			add_action(
				$hook,
				function () use ( $hook, &$fired ) {
					$fired[] = $hook;
				}
			);
		}

		$this->submit();

		$this->assertSame( array(), $fired );
	}

	public function test_refusing_the_last_administrator_leaves_their_content_alone() {
		$user_id    = $this->login_as_last_administrator();
		$post_id    = self::factory()->post->create( array( 'post_author' => $user_id ) );
		$comment_id = $this->leave_comment( $post_id, array( 'user_id' => $user_id ) );

		$this->submit();

		$this->assertSame( 'publish', get_post_status( $post_id ) );
		$this->assertSame( 'Leaving Member', get_comment( $comment_id )->comment_author );
	}

	/**
	 * The block is on the server and no filter can lift it: a callback that always returns
	 * true must not leave a site with no administrator.
	 */
	public function test_the_allowed_filter_cannot_let_the_last_administrator_go() {
		add_filter( 'wpum_before_delete_account_allowed', '__return_true', 999 );
		$user_id = $this->login_as_last_administrator();

		$result = $this->submit();

		$this->assertAccountKept( $user_id );
		$this->assertSame( array( self::LAST_ADMINISTRATOR_ERROR ), $result->errors );
	}

	public function test_the_allowed_filter_still_runs_for_the_last_administrator() {
		add_filter(
			'wpum_before_delete_account_allowed',
			function () {
				return new WP_Error( 'support_ticket', 'Close your support tickets first.' );
			}
		);
		$user_id = $this->login_as_last_administrator();

		$result = $this->submit();

		$this->assertAccountKept( $user_id );
		$this->assertSame( array( 'Close your support tickets first.' ), $result->errors );
	}

	public function test_a_second_role_does_not_hide_the_last_administrator() {
		$user_id = $this->login_as_last_administrator();
		get_userdata( $user_id )->add_role( 'editor' );
		wp_set_current_user( 0 );
		wp_set_current_user( $user_id );

		$result = $this->submit();

		$this->assertAccountKept( $user_id );
		$this->assertSame( array( self::LAST_ADMINISTRATOR_ERROR ), $result->errors );
	}

	/**
	 * The password comes first, so a stolen session learns nothing from the form.
	 */
	public function test_the_last_administrator_with_a_wrong_password_gets_the_password_error() {
		$this->login_as_last_administrator();

		$result = $this->submit( array( 'password' => 'NotMyP@ssw0rd!' ) );

		$this->assertSame( array( self::WRONG_PASSWORD_ERROR ), $result->errors );
	}

	public function test_of_two_administrators_one_can_go_and_then_the_other_cannot() {
		$this->remove_administrators();
		$first  = $this->create_member( 'administrator' );
		$second = $this->login_as_member( 'administrator' );

		$this->submit();
		$this->assertFalse( $this->user_exists( $second ) );

		wp_set_current_user( $first );
		$result = $this->submit();

		$this->assertAccountKept( $first );
		$this->assertSame( array( self::LAST_ADMINISTRATOR_ERROR ), $result->errors );
	}

	public function test_other_roles_are_not_held_back_on_a_site_with_no_administrator() {
		$this->remove_administrators();
		$user_id = $this->login_as_member( 'editor' );

		$this->submit();

		$this->assertFalse( $this->user_exists( $user_id ) );
	}

	/**
	 * What a site can do today to protect a role, through the filter.
	 */
	public function test_a_site_can_protect_administrators_with_the_allowed_filter() {
		add_filter(
			'wpum_before_delete_account_allowed',
			function ( $allowed, $user ) {
				return in_array( 'administrator', $user->roles, true ) ? new WP_Error( 'protected', 'Administrators can\'t delete their account here.' ) : $allowed;
			},
			10,
			2
		);

		$user_id = $this->login_as_member( 'administrator' );

		$result = $this->submit();

		$this->assertAccountKept( $user_id );
		$this->assertSame( array( 'Administrators can\'t delete their account here.' ), $result->errors );
	}

	public function test_a_member_with_two_roles_is_deleted_outright() {
		$user_id = $this->login_as_member();
		get_userdata( $user_id )->add_role( 'editor' );
		wp_set_current_user( 0 );
		wp_set_current_user( $user_id );

		$this->submit();

		$this->assertFalse( $this->user_exists( $user_id ) );
	}
}
