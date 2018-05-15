<?php
/**
 * Register new settings for the addon.
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
 * Register new settings for the addon.
 *
 * @param array $settings
 * @return void
 */
function wpumda_register_settings( $settings ) {

	$settings['redirects'][] = array(
		'id'      => 'account_cancellation_redirect',
		'name'    => __( 'After account cancellation', 'wpum-delete-account' ),
		'desc'    => __( 'Select the page where you want to redirect users after they\'ve deleted their account.', 'wpum-delete-account' ),
		'type'    => 'multiselect',
		'options' => wpum_get_pages(),
	);

	return $settings;

}
add_action( 'wpum_registered_settings', 'wpumda_register_settings' );
