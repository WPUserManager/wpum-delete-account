<?php
/**
 * Handles actions and filters for the addon.
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
 * Register a new tab within the account page.
 *
 * @param array $tabs
 * @return void
 */
function wpumda_register_account_tab( $tabs ) {

	$tabs['delete-account'] = [
		'name'     => esc_html__( 'Delete account', 'wpum-delete-account' ),
		'priority' => 3,
	];

	return $tabs;

}
add_filter( 'wpum_get_account_page_tabs', 'wpumda_register_account_tab' );

/**
 * Tell WPUM to load the form for this addon from within this plugin's path.
 *
 * @param string $path
 * @param string $form_name
 * @return void
 */
function wpumda_register_form_path( $path, $form_name ) {

	if ( $form_name == 'delete-account' ) {
		$path = WPUMDA_PLUGIN_DIR . 'includes/class-wpum-form-delete-account.php';
	}

	return $path;
}
add_filter( 'wpum_load_form_path', 'wpumda_register_form_path', 20, 2 );

/**
 * Display the content for the account cancellation tab.
 *
 * @return void
 */
function wpumda_register_account_tab_content() {

	echo WPUM()->forms->get_form( 'delete-account', [] );

}
add_action( 'wpum_account_page_content_delete-account', 'wpumda_register_account_tab_content' );

/**
 * Add a new path to WPUM's template loader.
 */
function wpumda_set_template_loader_path( $file_paths ) {

	$file_paths[12] = trailingslashit( WPUMDA_PLUGIN_DIR . 'templates' );

	return $file_paths;

}
add_filter( 'wpum_template_paths', 'wpumda_set_template_loader_path' );
