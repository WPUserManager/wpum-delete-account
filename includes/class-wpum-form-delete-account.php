<?php
/**
 * Handles the form where users can delete their account from the frontend.
 *
 * @package     wpum-delete-account
 * @copyright   Copyright (c) 2018, Alessandro Tesoro
 * @license     https://opensource.org/licenses/gpl-license GNU Public License
 * @since       1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPUM_Form_Delete_Account extends WPUM_Form {

	/**
	 * Form name.
	 *
	 * @var string
	 */
	public $form_name = 'delete-account';

	/**
	 * Stores static instance of class.
	 *
	 * @access protected
	 * @var WPUM_Form_Login The single instance of the class
	 */
	protected static $_instance = null;

	/**
	 * Returns static instance of class.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp', array( $this, 'process' ) );

		$this->steps = (array) apply_filters(
			'delete_steps', array(
				'submit' => array(
					'name'     => __( 'Delete account', 'wpum-delete-account' ),
					'view'     => array( $this, 'submit' ),
					'handler'  => array( $this, 'submit_handler' ),
					'priority' => 10,
				),
			)
		);

		uasort( $this->steps, array( $this, 'sort_by_priority' ) );

		if ( isset( $_POST['step'] ) ) {
			$this->step = is_numeric( $_POST['step'] ) ? max( absint( $_POST['step'] ), 0 ) : array_search( $_POST['step'], array_keys( $this->steps ) );
		} elseif ( ! empty( $_GET['step'] ) ) {
			$this->step = is_numeric( $_GET['step'] ) ? max( absint( $_GET['step'] ), 0 ) : array_search( $_GET['step'], array_keys( $this->steps ) );
		}

	}

	/**
	 * Initializes the fields used in the form.
	 */
	public function init_fields() {
		if ( $this->fields ) {
			return;
		}

		$this->fields = apply_filters(
			'delete_form_fields', array(
				'delete' => array(
					'password' => array(
						'label'       => __( 'Current password', 'wpum-delete-account' ),
						'description' => __( 'Enter your current password to confirm cancellation of your account.', 'wpum-delete-account' ),
						'type'        => 'password',
						'required'    => true,
						'placeholder' => '',
						'priority'    => 2,
					),
				),
			)
		);

	}

	/**
	 * Show the form.
	 *
	 * @return void
	 */
	public function submit() {

		$this->init_fields();

		$data = [
			'form'      => $this->form_name,
			'action'    => $this->get_action(),
			'fields'    => $this->get_fields( 'delete' ),
			'step'      => $this->get_step(),
			'step_name' => $this->steps[ $this->get_step_key( $this->get_step() ) ]['name'],
		];

		WPUM()->templates
			->set_template_data( $data )
			->get_template_part( 'forms/delete-account-form' );

	}

	/**
	 * Handle submission of the form.
	 *
	 * @return void
	 */
	public function submit_handler() {
		try {

			$this->init_fields();

			$values = $this->get_posted_fields();

			if ( ! wp_verify_nonce( $_POST['account_delete_nonce'], 'verify_delete_account_form' ) ) {
				return;
			}

			if ( empty( $_POST['submit_delete_account'] ) ) {
				return;
			}

			if ( is_wp_error( ( $return = $this->validate_fields( $values ) ) ) ) {
				throw new Exception( $return->get_error_message() );
			}

			// Verify the submitted password is correct.
			$user = wp_get_current_user();

			if ( $user instanceof WP_User && wp_check_password( $values['delete']['password'], $user->data->user_pass, $user->ID ) && is_user_logged_in() ) {

				wp_logout();

				require_once( ABSPATH . 'wp-admin/includes/user.php' );

				wp_delete_user( $user->ID );

				$redirect_to = wpum_get_option( 'account_cancellation_redirect' );
				$redirect_to = is_array( $redirect_to ) && ! empty( $redirect_to ) ? $redirect_to[0] : false;

				if ( $redirect_to ) {
					wp_safe_redirect( get_permalink( $redirect_to ) );
				} else {
					wp_safe_redirect( home_url() );
				}
				exit;

			} else {
				throw new Exception( __( 'The password you entered is incorrect. Your account has not been deleted.', 'wpum-delete-account' ) );
			}
		} catch ( Exception $e ) {
			$this->add_error( $e->getMessage() );
			return;
		}
	}

}
