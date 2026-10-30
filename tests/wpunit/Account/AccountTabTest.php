<?php
/**
 * The Delete account tab on WPUM's account page.
 */
class AccountTabTest extends AddonTestCase {

	public function test_the_tab_is_registered() {
		$this->login_as_member();

		$tabs = wpum_get_account_page_tabs();

		$this->assertArrayHasKey( 'delete-account', $tabs );
		$this->assertSame( 'Delete account', $tabs['delete-account']['name'] );
		$this->assertSame( 3, $tabs['delete-account']['priority'] );
	}

	public function test_the_tab_comes_after_settings_and_keeps_the_core_tabs() {
		$this->login_as_member();

		$keys = array_keys( wpum_get_account_page_tabs() );

		$this->assertSame( array( 'settings', 'delete-account' ), array_slice( $keys, 0, 2 ) );
		$this->assertContains( 'password', $keys );
		$this->assertContains( 'logout', $keys );
	}

	public function test_a_logged_in_member_sees_the_tab_link() {
		$this->login_as_member();

		$xpath = $this->xpath( $this->render_account_page() );

		$this->assertSame( 1, $xpath->query( '//nav//li[contains(@class, "tab-delete-account")]//a' )->length );
	}

	public function test_a_logged_in_member_sees_the_form_on_the_tab() {
		$this->login_as_member();

		$xpath = $this->xpath( $this->render_account_page( 'delete-account' ) );

		$this->assertSame( 1, $xpath->query( '//form[@id="wpum-submit-delete-account-form"]' )->length );
	}

	public function test_the_form_is_not_on_the_other_tabs() {
		$this->login_as_member();

		$html = $this->render_account_page( 'password' );

		$this->assertStringNotContainsString( 'wpum-submit-delete-account-form', $html );
	}

	/**
	 * What keeps the tab from visitors: WPUM sends them from the account page to the login page.
	 */
	public function test_visitors_are_sent_from_the_account_page_to_the_login_page() {
		$account_id = $this->create_page( 'Account', '[wpum_account]' );
		$login_id   = $this->create_page( 'Login', '[wpum_login_form]' );
		wpum_update_option( 'account_page', array( (string) $account_id ) );
		wpum_update_option( 'login_page', array( (string) $login_id ) );

		$this->go_to( get_permalink( $account_id ) );
		wp_set_current_user( 0 );

		$redirect = $this->catch_redirect( 'wpum_restrict_account_page' );

		$this->assertStringStartsWith( get_permalink( $login_id ), (string) $redirect );
	}

	/**
	 * The tab and the form are added for everyone. That only stays hidden on WPUM's account
	 * page with a login page set: [wpum_account] on any other page shows both to visitors.
	 *
	 * @group known-bug
	 */
	public function test_visitors_never_see_the_tab_or_the_form() {
		wp_set_current_user( 0 );

		$html = $this->render_account_page( 'delete-account' );

		$this->assertArrayNotHasKey( 'delete-account', wpum_get_account_page_tabs() );
		$this->assertStringNotContainsString( 'wpum-submit-delete-account-form', $html );
	}

	public function test_wpum_loads_the_addons_form_file() {
		$this->assertSame(
			WPUMDA_PLUGIN_DIR . 'includes/class-wpum-form-delete-account.php',
			apply_filters( 'wpum_load_form_path', '/core/delete-account.php', 'delete-account' )
		);
	}

	public function test_other_forms_keep_their_file() {
		$this->assertSame( '/core/login.php', apply_filters( 'wpum_load_form_path', '/core/login.php', 'login' ) );
	}

	public function test_the_addons_templates_are_on_wpums_template_path() {
		$paths = apply_filters( 'wpum_template_paths', array( 10 => '/theme/' ) );

		$this->assertSame( WPUMDA_PLUGIN_DIR . 'templates/', $paths[12] );
		$this->assertSame( '/theme/', $paths[10] );
	}
}
