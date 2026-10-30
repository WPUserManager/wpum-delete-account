<?php
require_once __DIR__ . '/AddonTestCase.php';

// Load the form classes without creating the form, which hooks its handler: WPUM loads
// them the first time the form is shown or posted.
if ( defined( 'WPUM_PLUGIN_DIR' ) && defined( 'WPUMDA_PLUGIN_DIR' ) ) {
	if ( ! class_exists( 'WPUM_Form' ) ) {
		require_once WPUM_PLUGIN_DIR . 'includes/abstracts/class-wpum-form.php';
	}
	if ( ! class_exists( 'WPUM_Form_Delete_Account' ) ) {
		require_once WPUMDA_PLUGIN_DIR . 'includes/class-wpum-form-delete-account.php';
	}
}
