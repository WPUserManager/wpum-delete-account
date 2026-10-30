<?php
/**
 * The addon protects no role: anyone with a password can delete their own account. These
 * tests pin that down, so that changing it is a decision and not an accident.
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
	 * Unlike wp-admin, which refuses to delete the current user, the form lets the only
	 * administrator delete their account and leaves the site with none.
	 */
	public function test_the_last_administrator_is_not_protected() {
		foreach ( get_users( array( 'role' => 'administrator' ) ) as $admin ) {
			wp_delete_user( $admin->ID );
		}
		$user_id = $this->login_as_member( 'administrator' );

		$this->submit();

		$this->assertFalse( $this->user_exists( $user_id ) );
		$this->assertSame( array(), get_users( array( 'role' => 'administrator' ) ) );
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
