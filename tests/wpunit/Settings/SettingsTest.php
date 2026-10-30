<?php
/**
 * The After account cancellation setting, under Users, Settings, Redirects.
 */
class SettingsTest extends AddonTestCase {

	/**
	 * The addon's setting from the registered settings.
	 *
	 * @return array
	 */
	private function setting() {
		$settings = apply_filters( 'wpum_registered_settings', array() );

		foreach ( $settings['redirects'] as $setting ) {
			if ( 'account_cancellation_redirect' === $setting['id'] ) {
				return $setting;
			}
		}

		$this->fail( 'The setting is not registered under redirects.' );
	}

	public function test_the_setting_is_a_multiselect_under_redirects() {
		$setting = $this->setting();

		$this->assertSame( 'After account cancellation', $setting['name'] );
		$this->assertSame( 'multiselect', $setting['type'] );
		$this->assertStringContainsString( 'deleted their account', $setting['desc'] );
	}

	public function test_it_offers_the_sites_pages() {
		delete_transient( 'wpum_get_pages' );
		$page_id = $this->create_page( 'Goodbye' );

		$options = $this->setting()['options'];

		$this->assertContains(
			array(
				'value' => $page_id,
				'label' => 'Goodbye',
			),
			$options
		);
	}

	public function test_the_settings_of_other_sections_are_kept() {
		$settings = wpumda_register_settings(
			array(
				'redirects' => array( array( 'id' => 'login_redirect' ) ),
				'general'   => array( array( 'id' => 'login_page' ) ),
			)
		);

		$this->assertSame( 'login_redirect', $settings['redirects'][0]['id'] );
		$this->assertSame( 'account_cancellation_redirect', $settings['redirects'][1]['id'] );
		$this->assertSame( 'login_page', $settings['general'][0]['id'] );
	}
}
