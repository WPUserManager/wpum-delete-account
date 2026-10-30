<?php
/**
 * This addon deletes users, so everything here must leave every account alone: a wrong
 * password, a missing or forged nonce, a tampered ID, a visitor.
 */
class SecurityTest extends AddonTestCase {

	public function test_a_wrong_password_deletes_nothing() {
		$user_id = $this->login_as_member();

		$result = $this->submit( array( 'password' => 'NotMyP@ssw0rd!' ) );

		$this->assertAccountKept( $user_id );
		$this->assertSame( array( self::WRONG_PASSWORD_ERROR ), $result->errors );
		$this->assertNull( $result->redirect );
	}

	public function test_a_wrong_password_fires_no_hooks() {
		$this->login_as_member();

		$fired = array();
		foreach ( array( 'wpum_before_delete_account', 'wpum_after_delete_account', 'delete_user', 'wp_logout' ) as $hook ) {
			add_action(
				$hook,
				function () use ( $hook, &$fired ) {
					$fired[] = $hook;
				}
			);
		}

		$this->submit( array( 'password' => 'NotMyP@ssw0rd!' ) );

		$this->assertSame( array(), $fired );
	}

	/**
	 * A password is only ever compared with a hash, not the stored hash with itself.
	 */
	public function test_the_stored_hash_is_not_accepted_as_the_password() {
		$user_id = $this->login_as_member();

		$this->submit( array( 'password' => get_userdata( $user_id )->user_pass ) );

		$this->assertAccountKept( $user_id );
	}

	public function test_passwords_are_case_sensitive() {
		$user_id = $this->login_as_member();

		$this->submit( array( 'password' => strtolower( self::PASSWORD ) ) );

		$this->assertAccountKept( $user_id );
	}

	public function test_an_empty_password_deletes_nothing() {
		$user_id = $this->login_as_member();

		$result = $this->submit( array( 'password' => '' ) );

		$this->assertAccountKept( $user_id );
		$this->assertSame( array( 'Current password is a required field' ), $result->errors );
	}

	public function test_no_password_at_all_deletes_nothing() {
		$user_id = $this->login_as_member();

		$this->submit( array( 'password' => null ) );

		$this->assertAccountKept( $user_id );
	}

	public function test_a_password_posted_as_an_array_deletes_nothing() {
		$user_id = $this->login_as_member();

		$this->submit( array( 'password' => array( self::PASSWORD ) ) );

		$this->assertAccountKept( $user_id );
	}

	public function test_the_password_of_another_member_deletes_nothing() {
		$this->create_member( 'subscriber', array( 'user_pass' => 'SomeoneElse!123' ) );
		$user_id = $this->login_as_member();

		$this->submit( array( 'password' => 'SomeoneElse!123' ) );

		$this->assertAccountKept( $user_id );
	}

	public function test_no_nonce_deletes_nothing() {
		$user_id = $this->login_as_member();

		$this->submit( array( 'account_delete_nonce' => null ) );

		$this->assertAccountKept( $user_id );
	}

	/**
	 * @dataProvider bad_nonces
	 *
	 * @param mixed $nonce
	 */
	public function test_a_forged_nonce_deletes_nothing( $nonce ) {
		$user_id = $this->login_as_member();

		$result = $this->submit( array( 'account_delete_nonce' => $nonce ) );

		$this->assertAccountKept( $user_id );
		$this->assertNull( $result->redirect );
	}

	public function bad_nonces() {
		return array(
			'empty'        => array( '' ),
			'made up'      => array( 'abcdef1234' ),
			'zero'         => array( '0' ),
			'an array'     => array( array( 'abcdef1234' ) ),
			'one too long' => array( str_repeat( 'a', 200 ) ),
		);
	}

	public function test_a_nonce_for_another_action_deletes_nothing() {
		$user_id = $this->login_as_member();

		$this->submit( array( 'account_delete_nonce' => wp_create_nonce( 'verify_password_form' ) ) );

		$this->assertAccountKept( $user_id );
	}

	/**
	 * The classic CSRF: an attacker gets a nonce from their own account's form and tricks
	 * the victim into posting it.
	 */
	public function test_a_nonce_issued_to_another_member_deletes_nothing() {
		$attacker = $this->login_as_member();
		$nonce    = wp_create_nonce( 'verify_delete_account_form' );

		$victim = $this->login_as_member();

		$this->submit( array( 'account_delete_nonce' => $nonce ) );

		$this->assertAccountKept( $victim );
		$this->assertTrue( $this->user_exists( $attacker ) );
	}

	/**
	 * The password confirms the action, so a stolen session or an open laptop isn't enough,
	 * even with a good nonce from the page.
	 */
	public function test_a_valid_nonce_alone_deletes_nothing() {
		$user_id = $this->login_as_member();

		$this->submit( array( 'password' => null ) );
		$this->assertAccountKept( $user_id );

		$this->submit( array( 'password' => '' ) );
		$this->assertAccountKept( $user_id );
	}

	public function test_without_the_submit_button_nothing_is_deleted() {
		$user_id = $this->login_as_member();

		$this->submit( array( 'submit_delete_account' => null ) );

		$this->assertAccountKept( $user_id );
	}

	/**
	 * The form has one step, so any other step has no handler.
	 */
	public function test_a_step_that_does_not_exist_deletes_nothing() {
		$user_id = $this->login_as_member();

		$this->submit( array( 'step' => '1' ) );

		$this->assertAccountKept( $user_id );
	}

	/**
	 * The handler reads $_POST only, so a link can never delete an account.
	 */
	public function test_a_get_request_deletes_nothing() {
		$user_id = $this->login_as_member();

		$_GET     = $this->posted_values();
		$_REQUEST = $_GET;
		$_POST    = array();

		$this->catch_redirect( array( $this->form(), 'process' ) );

		$this->assertAccountKept( $user_id );
	}

	/**
	 * @dataProvider id_fields
	 *
	 * @param string $field
	 */
	public function test_a_tampered_id_cannot_delete_someone_else( $field ) {
		$victim   = $this->create_member();
		$attacker = $this->login_as_member();

		$this->submit( array( $field => (string) $victim ) );

		$this->assertTrue( $this->user_exists( $victim ), 'The form must only ever act on the current user.' );
		$this->assertFalse( $this->user_exists( $attacker ), 'The attacker gave their own password, so only their own account goes.' );
	}

	public function id_fields() {
		return array(
			'user_id'   => array( 'user_id' ),
			'ID'        => array( 'ID' ),
			'id'        => array( 'id' ),
			'user'      => array( 'user' ),
			'uid'       => array( 'uid' ),
			'author'    => array( 'author' ),
			'member_id' => array( 'member_id' ),
		);
	}

	public function test_a_tampered_id_and_a_wrong_password_delete_nobody() {
		$victim   = $this->create_member();
		$attacker = $this->login_as_member();

		$this->submit(
			array(
				'user_id'  => (string) $victim,
				'password' => 'NotMyP@ssw0rd!',
			)
		);

		$this->assertTrue( $this->user_exists( $victim ) );
		$this->assertAccountKept( $attacker );
	}

	public function test_an_administrator_cannot_delete_someone_else_through_the_form() {
		$victim = $this->create_member();
		$admin  = $this->login_as_member( 'administrator' );

		$this->submit( array( 'user_id' => (string) $victim ) );

		$this->assertTrue( $this->user_exists( $victim ) );
	}

	public function test_the_victims_password_in_the_attackers_session_deletes_nobody() {
		$victim   = $this->create_member( 'subscriber', array( 'user_pass' => 'VictimP@ssw0rd!' ) );
		$attacker = $this->login_as_member();

		$this->submit(
			array(
				'user_id'  => (string) $victim,
				'password' => 'VictimP@ssw0rd!',
			)
		);

		$this->assertTrue( $this->user_exists( $victim ) );
		$this->assertAccountKept( $attacker );
	}

	/**
	 * Visitors do get a valid nonce: it's only tied to the site, and to user 0.
	 */
	public function test_a_visitor_with_a_valid_nonce_deletes_nothing() {
		$member = $this->create_member();
		$before = $this->user_count();
		wp_set_current_user( 0 );

		$result = $this->submit();

		$this->assertTrue( $this->user_exists( $member ) );
		$this->assertSame( $before, $this->user_count() );
		$this->assertNull( $result->redirect );
	}

	/**
	 * The handler reads $user->data->user_pass before it checks the visitor is logged in.
	 * There is no such property for user 0, and with null as the hash wp_check_password()
	 * hits a TypeError in hash_equals() on PHP 8. Nothing is deleted, but the visitor gets a
	 * 500. Only reachable where a visitor can see the form, such as [wpum_account] outside
	 * WPUM's account page. The test's error handler turns the PHP error into a form error.
	 *
	 * @group known-bug
	 */
	public function test_a_visitor_gets_the_wrong_password_error() {
		$this->create_member();
		wp_set_current_user( 0 );

		$result = $this->submit();

		$this->assertSame( array( self::WRONG_PASSWORD_ERROR ), $result->errors );
	}

	public function test_the_error_for_a_wrong_password_is_shown_on_the_form() {
		$this->login_as_member();

		$result = $this->submit( array( 'password' => 'NotMyP@ssw0rd!' ) );

		$this->assertStringContainsString( self::WRONG_PASSWORD_ERROR, $this->render( array( $result->form, 'output' ) ) );
	}

	/**
	 * The mistyped password is usually close to the real one, and the form prints it back
	 * into the page's HTML: get_posted_fields() keeps it as the field's value.
	 *
	 * @group known-bug
	 */
	public function test_a_wrong_password_is_not_printed_back_into_the_form() {
		$this->login_as_member();

		$result = $this->submit( array( 'password' => 'CurrentP@ssw0rd!124' ) );

		$this->assertStringNotContainsString( 'CurrentP@ssw0rd!124', $this->render( array( $result->form, 'output' ) ) );
	}
}
