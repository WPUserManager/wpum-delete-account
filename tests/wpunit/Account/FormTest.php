<?php
/**
 * The delete account form's markup and what it posts.
 */
class FormTest extends AddonTestCase {

	/**
	 * The form on the tab, for a logged-in member.
	 *
	 * @return \DOMXPath
	 */
	private function rendered_form() {
		$this->login_as_member();

		return $this->xpath( $this->render_form() );
	}

	public function test_the_form_has_the_addons_name() {
		$this->assertSame( 'delete-account', $this->form()->get_form_name() );
	}

	public function test_it_has_one_step_named_delete_account() {
		$steps = $this->form()->get_steps();

		$this->assertSame( array( 'submit' ), array_keys( $steps ) );
		$this->assertSame( 'Delete account', $steps['submit']['name'] );
	}

	public function test_the_heading_names_the_step() {
		$xpath = $this->rendered_form();

		$this->assertSame( 'Delete account', $xpath->query( '//div[contains(@class, "wpum-delete-account-form")]/h2' )->item( 0 )->textContent );
	}

	public function test_the_form_asks_for_the_current_password() {
		$xpath = $this->rendered_form();

		$input = $xpath->query( '//input[@name="password"]' )->item( 0 );

		$this->assertNotNull( $input );
		$this->assertSame( 'password', $input->getAttribute( 'type' ) );
		$this->assertTrue( $input->hasAttribute( 'required' ) );
		$this->assertSame( '', $input->getAttribute( 'value' ) );
		$this->assertStringContainsString( 'Current password', $xpath->query( '//label[@for="password"]' )->item( 0 )->textContent );
		$this->assertStringContainsString( 'Enter your current password', $xpath->query( '//small[@class="description"]' )->item( 0 )->textContent );
	}

	public function test_the_form_posts() {
		$form = $this->rendered_form()->query( '//form[@id="wpum-submit-delete-account-form"]' )->item( 0 );

		$this->assertSame( 'post', $form->getAttribute( 'method' ) );
	}

	public function test_the_hidden_fields_are_what_the_handler_expects() {
		$xpath = $this->rendered_form();

		$this->assertSame( 'delete-account', $xpath->query( '//input[@name="wpum_form"]' )->item( 0 )->getAttribute( 'value' ) );
		$this->assertSame( '0', $xpath->query( '//input[@name="step"]' )->item( 0 )->getAttribute( 'value' ) );
		$this->assertSame( 'Delete account', $xpath->query( '//input[@name="submit_delete_account"]' )->item( 0 )->getAttribute( 'value' ) );
	}

	public function test_the_nonce_is_valid_for_the_current_member() {
		$xpath = $this->rendered_form();

		$nonce = $xpath->query( '//input[@name="account_delete_nonce"]' )->item( 0 )->getAttribute( 'value' );

		$this->assertNotFalse( wp_verify_nonce( $nonce, 'verify_delete_account_form' ) );
	}

	/**
	 * The form must never name a user: the handler only ever acts on the current one.
	 */
	public function test_the_form_posts_nothing_that_identifies_a_user() {
		$xpath = $this->rendered_form();

		$names = array();
		foreach ( $xpath->query( '//form[@id="wpum-submit-delete-account-form"]//*[@name]' ) as $element ) {
			$names[] = $element->getAttribute( 'name' );
		}
		sort( $names );

		$this->assertSame(
			array( '_wp_http_referer', 'account_delete_nonce', 'password', 'step', 'submit_delete_account', 'wpum_form' ),
			$names
		);
	}

	public function test_submitting_asks_for_confirmation() {
		$form = $this->rendered_form()->query( '//form[@id="wpum-submit-delete-account-form"]' )->item( 0 );

		$this->assertSame(
			"return confirm('Are you sure you want to delete your account? This action cannot be reversed.');",
			$form->getAttribute( 'onsubmit' )
		);
	}

	/**
	 * esc_html_e() in an attribute turns an apostrophe into &#039;, which the browser turns
	 * back before it runs the handler. In a language with an apostrophe in the question
	 * (French: l'action) that's a syntax error, so confirm() never runs and the form posts
	 * without asking.
	 *
	 * @group known-bug
	 */
	public function test_an_apostrophe_in_a_translation_keeps_the_confirmation_working() {
		add_filter(
			'gettext_wpum-delete-account',
			function ( $translation, $text ) {
				return 0 === strpos( $text, 'Are you sure' ) ? "Voulez-vous vraiment supprimer votre compte ? Impossible d'annuler." : $translation;
			},
			10,
			2
		);

		$form = $this->rendered_form()->query( '//form[@id="wpum-submit-delete-account-form"]' )->item( 0 );

		$this->assertMatchesRegularExpression( "/^return confirm\\('(?:[^'\\\\]|\\\\.)*'\\);$/", $form->getAttribute( 'onsubmit' ) );
	}

	public function test_a_translated_label_is_escaped() {
		add_filter(
			'gettext_wpum-delete-account',
			function ( $translation, $text ) {
				return 'Delete account' === $text ? '<b>Delete</b>' : $translation;
			},
			10,
			2
		);

		$html = $this->render_account_page( 'delete-account' );

		$this->assertStringNotContainsString( '<b>Delete</b>', $html );
	}

	public function test_the_hooks_around_the_form_fire() {
		$this->login_as_member();

		add_action(
			'wpum_before_delete_account_form',
			function () {
				echo '<i id="before-form"></i>';
			}
		);
		add_action(
			'wpum_after_delete_account_form',
			function () {
				echo '<i id="after-form"></i>';
			}
		);

		$xpath = $this->xpath( $this->render_form() );

		$this->assertSame( 1, $xpath->query( '//i[@id="before-form"]' )->length );
		$this->assertSame( 1, $xpath->query( '//form//i[@id="after-form"]' )->length );
	}

	public function test_the_fields_can_be_extended() {
		$this->login_as_member();

		add_filter(
			'delete_form_fields',
			function ( $fields ) {
				$fields['delete']['reason'] = array(
					'label'    => 'Why are you leaving?',
					'type'     => 'text',
					'required' => false,
					'priority' => 3,
				);

				return $fields;
			}
		);

		$xpath = $this->xpath( $this->render_form() );

		$this->assertSame( 1, $xpath->query( '//input[@name="reason"]' )->length );
	}

	/**
	 * Adding a field mustn't get in the way of the password check.
	 */
	public function test_an_extra_required_field_is_enforced() {
		$user_id = $this->login_as_member();

		add_filter(
			'delete_form_fields',
			function ( $fields ) {
				$fields['delete']['reason'] = array(
					'label'    => 'Why are you leaving?',
					'type'     => 'text',
					'required' => true,
					'priority' => 3,
				);

				return $fields;
			}
		);

		$result = $this->submit();

		$this->assertAccountKept( $user_id );
		$this->assertSame( array( 'Why are you leaving? is a required field' ), $result->errors );
	}
}
