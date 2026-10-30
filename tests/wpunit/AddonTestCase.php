<?php
/**
 * Base test case for the Delete Account addon tests.
 *
 * Provides common setup: bootstraps WPUM, creates its custom tables, and helpers to create
 * members, post the form the way a real request does, and check what happened to them.
 */

/**
 * Thrown from the wp_redirect filter, so the handler's redirect and exit can be tested.
 *
 * An Error rather than an Exception: the form redirects inside a try { } catch ( Exception $e ),
 * which would swallow an Exception and report the redirect as a form error.
 */
class AddonRedirectException extends \Error {

	/**
	 * @var string
	 */
	public $location;

	public function __construct( $location ) {
		parent::__construct( 'Redirected to ' . $location );
		$this->location = $location;
	}
}

abstract class AddonTestCase extends \Codeception\TestCase\WPTestCase {

	/**
	 * The password every member gets, strong enough for WPUM's password check.
	 */
	const PASSWORD = 'CurrentP@ssw0rd!123';

	/**
	 * The error shown when the password doesn't match.
	 */
	const WRONG_PASSWORD_ERROR = 'The password you entered is incorrect. Your account has not been deleted.';

	/**
	 * Emails sent during the test.
	 *
	 * @var array
	 */
	protected $emails = array();

	public function _setUp() {
		parent::_setUp();

		// Reset the $wpum_options global: WP rolls back the DB between tests but PHP globals persist.
		global $wpum_options;
		$wpum_options = get_option( 'wpum_settings' ) ?: array();

		// Fail, not skip: a skipped suite would still pass the release gate.
		if ( ! function_exists( 'WPUM' ) ) {
			$this->fail( 'WPUM plugin is not active.' );
		}

		if ( ! class_exists( 'WPUM_Delete_Account' ) ) {
			$this->fail( 'WPUM Delete Account addon is not active.' );
		}

		$this->ensure_tables();

		// wp_logout() would otherwise try to send headers.
		add_filter( 'send_auth_cookies', '__return_false' );

		$this->emails = array();
		add_filter(
			'pre_wp_mail',
			function ( $return, $atts ) {
				$this->emails[] = $atts;

				return true;
			},
			999,
			2
		);

		$_SERVER['REQUEST_URI'] = '/account/delete-account';

		$this->reset_form_instance();
	}

	public function _tearDown() {
		$_POST    = array();
		$_REQUEST = array();
		$_GET     = array();
		wp_set_current_user( 0 );

		global $post;
		$post = null;
		set_query_var( 'tab', '' );

		$this->reset_form_instance();

		parent::_tearDown();
	}

	/**
	 * Forget the form singleton, so the next form starts from scratch.
	 */
	protected function reset_form_instance() {
		if ( ! class_exists( 'WPUM_Form_Delete_Account' ) ) {
			return;
		}

		$prop = new \ReflectionProperty( 'WPUM_Form_Delete_Account', '_instance' );
		$prop->setAccessible( true );
		$current = $prop->getValue();

		if ( $current ) {
			remove_action( 'wp', array( $current, 'process' ) );
		}

		$prop->setValue( null, null );
	}

	/**
	 * A fresh form, the way WPUM loads it for a request.
	 *
	 * @return \WPUM_Form_Delete_Account
	 */
	protected function form() {
		$this->reset_form_instance();

		return \WPUM_Form_Delete_Account::instance();
	}

	/**
	 * Create a member with a known password.
	 *
	 * @param string $role
	 * @param array  $args
	 *
	 * @return int
	 */
	protected function create_member( $role = 'subscriber', $args = array() ) {
		return self::factory()->user->create(
			array_merge(
				array(
					'role'      => $role,
					'user_pass' => self::PASSWORD,
				),
				$args
			)
		);
	}

	/**
	 * Create a member and log in as them.
	 *
	 * @param string $role
	 * @param array  $args
	 *
	 * @return int
	 */
	protected function login_as_member( $role = 'subscriber', $args = array() ) {
		$user_id = $this->create_member( $role, $args );
		wp_set_current_user( $user_id );

		return $user_id;
	}

	/**
	 * What the form posts, with overrides. An override of null leaves that value out.
	 *
	 * Slashed, as WordPress slashes all request data before handlers see it.
	 *
	 * @param array $overrides
	 *
	 * @return array
	 */
	protected function posted_values( $overrides = array() ) {
		$values = array_merge(
			array(
				'wpum_form'             => 'delete-account',
				'step'                  => '0',
				'password'              => self::PASSWORD,
				'account_delete_nonce'  => wp_create_nonce( 'verify_delete_account_form' ),
				'submit_delete_account' => 'Delete account',
			),
			$overrides
		);

		return wp_slash(
			array_filter(
				$values,
				function ( $value ) {
					return null !== $value;
				}
			)
		);
	}

	/**
	 * Post the form through the handler WPUM runs for a real request.
	 *
	 * @param array $overrides See posted_values().
	 *
	 * @return object {
	 *     @type \WPUM_Form_Delete_Account $form
	 *     @type string|null               $redirect Where the handler redirected to, or null.
	 *     @type array                     $errors   The form's error messages.
	 * }
	 */
	protected function submit( $overrides = array() ) {
		$_POST    = $this->posted_values( $overrides );
		$_REQUEST = $_POST;

		$form     = $this->form();
		$redirect = $this->catch_redirect( array( $form, 'process' ) );

		return (object) array(
			'form'     => $form,
			'redirect' => $redirect,
			'errors'   => $this->get_form_errors( $form ),
		);
	}

	/**
	 * Whether the user is really in the database, past any object cache.
	 *
	 * @param int $user_id
	 *
	 * @return bool
	 */
	protected function user_exists( $user_id ) {
		global $wpdb;

		return '1' === $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->users} WHERE ID = %d", $user_id ) ); // phpcs:ignore
	}

	/**
	 * The number of users in the database.
	 *
	 * @return int
	 */
	protected function user_count() {
		global $wpdb;

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users}" ); // phpcs:ignore
	}

	/**
	 * Assert the account is untouched and its owner still logged in.
	 *
	 * @param int $user_id
	 */
	protected function assertAccountKept( $user_id ) {
		$this->assertTrue( $this->user_exists( $user_id ), 'The account should not have been deleted.' );
		$this->assertSame( $user_id, get_current_user_id(), 'The member should still be logged in.' );
	}

	/**
	 * Set the After account cancellation setting, as the settings panel saves it.
	 *
	 * @param int $page_id
	 */
	protected function set_redirect_page( $page_id ) {
		wpum_update_option( 'account_cancellation_redirect', array( (string) $page_id ) );
	}

	/**
	 * Get a form's errors.
	 *
	 * @param \WPUM_Form $form
	 *
	 * @return array
	 */
	protected function get_form_errors( $form ) {
		$prop = new \ReflectionProperty( 'WPUM_Form', 'errors' );
		$prop->setAccessible( true );

		return $prop->getValue( $form );
	}

	/**
	 * Call a protected or private method.
	 *
	 * @param object $object
	 * @param string $method
	 * @param array  $args
	 *
	 * @return mixed
	 */
	protected function call_method( $object, $method, $args = array() ) {
		$ref = new \ReflectionMethod( $object, $method );
		$ref->setAccessible( true );

		return $ref->invokeArgs( $object, $args );
	}

	/**
	 * Run a callback that redirects, and return where it redirected to.
	 *
	 * @param callable $callback
	 *
	 * @return string|null The redirect location, or null if it didn't redirect.
	 */
	protected function catch_redirect( $callback ) {
		$filter = function ( $location ) {
			throw new AddonRedirectException( (string) $location );
		};
		add_filter( 'wp_redirect', $filter, PHP_INT_MAX );

		try {
			call_user_func( $callback );
		} catch ( AddonRedirectException $e ) {
			return $e->location;
		} finally {
			remove_filter( 'wp_redirect', $filter, PHP_INT_MAX );
		}

		return null;
	}

	/**
	 * Render a callback's output.
	 *
	 * @param callable $callback
	 *
	 * @return string
	 */
	protected function render( $callback ) {
		ob_start();
		try {
			call_user_func( $callback );
		} finally {
			$output = ob_get_clean();
		}

		return $output;
	}

	/**
	 * Render the delete account form the way the account tab does.
	 *
	 * @return string
	 */
	protected function render_form() {
		return $this->render(
			function () {
				do_action( 'wpum_account_page_content_delete-account' );
			}
		);
	}

	/**
	 * Create the account page and render it on a tab.
	 *
	 * @param string $tab
	 *
	 * @return string
	 */
	protected function render_account_page( $tab = '' ) {
		$page_id = $this->create_page( 'Account', '[wpum_account]' );
		wpum_update_option( 'account_page', array( (string) $page_id ) );

		global $post;
		$post = get_post( $page_id );
		set_query_var( 'tab', $tab );

		return do_shortcode( '[wpum_account]' );
	}

	/**
	 * Parse HTML for XPath queries.
	 *
	 * @param string $html
	 *
	 * @return \DOMXPath
	 */
	protected function xpath( $html ) {
		$doc      = new \DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="utf-8" ?><html><body>' . $html . '</body></html>' );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		return new \DOMXPath( $doc );
	}

	/**
	 * Create a published page.
	 *
	 * @param string $title
	 * @param string $content
	 *
	 * @return int
	 */
	protected function create_page( $title, $content = '' ) {
		return self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_content' => $content,
			)
		);
	}

	/**
	 * Ensure all WPUM custom tables exist.
	 */
	protected function ensure_tables() {
		$tables = array(
			new \WPUM_DB_Table_Fields(),
			new \WPUM_DB_Table_Field_Meta(),
			new \WPUM_DB_Table_Fields_Groups(),
			new \WPUM_DB_Table_Registration_Forms(),
			new \WPUM_DB_Table_Registration_Forms_Meta(),
			new \WPUM_DB_Table_Search_Fields(),
		);

		foreach ( $tables as $table ) {
			if ( ! $table->exists() ) {
				$table->create();
			}
		}
	}
}
