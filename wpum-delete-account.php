<?php
/*
Plugin Name: WPUM Delete Account
Plugin URI:  https://wpusermanager.com
Description: Allows the user to delete their own profile from the frontend account page. This is an addon for WP User Manager.
Version:     1.0.1
Author:      Alessandro Tesoro
Author URI:  https://wpusermanager.com/
License:     GPLv3+
Text Domain: wpum-delete-account
Domain Path: /languages
*/

/**
 * WPUM Delete Account.
 *
 * Copyright (c) 2018 Alessandro Tesoro
 *
 * WPUM Delete Account. is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * WPUM Delete Account. is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * @author     Alessandro Tesoro
 * @version    2.0.0
 * @copyright  (c) 2018 Alessandro Tesoro
 * @license    http://www.gnu.org/licenses/gpl-3.0.txt GNU LESSER GENERAL PUBLIC LICENSE
 * @package    wpum-delete-account
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPUM_Delete_Account' ) ) :

	/**
	 * Main WPUM_Delete_Account class.
	 */
	final class WPUM_Delete_Account {

		/**
		 * WPUMDA Instance.
		 *
		 * @var WPUMDA() the WPUM Instance
		 */
		protected static $_instance;

		/**
		 * Main WPUMDA Instance.
		 *
		 * Ensures that only one instance of WPUMDA exists in memory at any one
		 * time. Also prevents needing to define globals all over the place.
		 *
		 * @return WPUMDA
		 */
		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		/**
		 * Get things up and running.
		 */
		public function __construct() {

			// Verify the plugin can run first. If not, disable the plugin automagically.
			$this->plugin_can_run();

			// Plugin is activated now proceed.
			$this->setup_constants();
			$this->autoload();
			$this->includes();
			$this->init_hooks();

		}

		/**
		 * Autoload composer and other required classes.
		 *
		 * @return void
		 */
		private function autoload() {
			require __DIR__ . '/vendor/autoload.php';
		}

		/**
		 * Load required files for the addon.
		 *
		 * @return void
		 */
		public function includes() {

			require_once WPUMDA_PLUGIN_DIR . 'includes/settings.php';
			require_once WPUMDA_PLUGIN_DIR . 'includes/actions.php';

		}

		/**
		 * Setup plugin constants
		 *
		 * @access private
		 * @since 1.0.0
		 * @return void
		 */
		private function setup_constants() {

			// Plugin version.
			if ( ! defined( 'WPUMDA_VERSION' ) ) {
				define( 'WPUMDA_VERSION', '1.0.1' );
			}

			// Plugin Folder Path.
			if ( ! defined( 'WPUMDA_PLUGIN_DIR' ) ) {
				define( 'WPUMDA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
			}

			// Plugin Folder URL.
			if ( ! defined( 'WPUMDA_PLUGIN_URL' ) ) {
				define( 'WPUMDA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
			}

			// Plugin Root File.
			if ( ! defined( 'WPUMDA_PLUGIN_FILE' ) ) {
				define( 'WPUMDA_PLUGIN_FILE', __FILE__ );
			}

			// Plugin Slug.
			if ( ! defined( 'WPUMDA_SLUG' ) ) {
				define( 'WPUMDA_SLUG', plugin_basename( __FILE__ ) );
			}

		}

		/**
		 * Hook in our actions and filters.
		 *
		 * @return void
		 */
		private function init_hooks() {
			add_action( 'plugins_loaded', array( $this, 'load_textdomain' ), 0 );
		}

		/**
		 * Load plugin textdomain.
		 *
		 * @return void
		 */
		public function load_textdomain() {
			load_plugin_textdomain( 'wpum-delete-account', false, basename( dirname( __FILE__ ) ) . '/languages' );
		}

		/**
		 * Verify that the current environment is supported.
		 *
		 * @return boolean
		 */
		private function plugin_can_run() {

			require __DIR__ . '/vendor/autoload.php';

			$requirements_check = new WPUM_Extension_Activation(
				array(
					'title'        => 'WPUM Delete Account',
					'wpum_version' => '2.0.0',
					'file'         => __FILE__,
				)
			);

			return $requirements_check->passes();

		}

	}

endif;

/**
 * Start the addon.
 *
 * @return object
 */
function WPUMDA() {
	return WPUM_Delete_Account::instance();
}

WPUMDA();
