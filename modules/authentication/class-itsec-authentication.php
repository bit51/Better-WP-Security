<?php

if ( ! class_exists( 'ITSEC_Authentication' ) ) {

	class ITSEC_Authentication {

		private static $instance = null;

		private $settings, $away_file;

		private function __construct() {

			global $itsec_globals;

			$this->settings  = get_site_option( 'itsec_authentication' );
			$this->away_file = $itsec_globals['upload_dir'] . '/itsec_away.confg'; //override file

			//execute login limits
			if ( $this->settings['brute_force-enabled'] === true ) {
				add_filter( 'authenticate', array( $this, 'execute_brute_force_no_password' ), 30, 3 );
				add_action( 'wp_login_failed', array( $this, 'execute_brute_force' ), 1, 1 );
				add_filter( 'itsec_lockout_modules', array( $this, 'register_lockout' ) );
				add_filter( 'itsec_logger_modules', array( $this, 'register_logger' ) );
			}

			//require strong passwords if turned on
			if ( isset( $this->settings['strong_passwords-enabled'] ) && $this->settings['strong_passwords-enabled'] === true ) {
				add_action( 'user_profile_update_errors', array( $this, 'enforce_strong_password' ), 0, 3 );

				if ( isset( $_GET['action'] ) && ( $_GET['action'] == 'rp' || $_GET['action'] == 'resetpass' ) && isset( $_GET['login'] ) ) {
					add_action( 'login_head', array( $this, 'enforce_strong_password' ) );
				}

				add_action( 'admin_enqueue_scripts', array( $this, 'login_script_js' ) );
				add_action( 'login_enqueue_scripts', array( $this, 'login_script_js' ) );

			}

			//Execute away mode functions on admin init
			if ( isset( $this->settings['away_mode-enabled'] ) && $this->settings['away_mode-enabled'] === true ) {
				add_action( 'admin_init', array( $this, 'execute_away_mode' ) );
			}

			//Execute module functions on frontend init
			if ( $this->settings['hide_backend-enabled'] === true ) {

				add_action( 'init', array( $this, 'execute_hide_backend' ) );
				add_action( 'login_init', array( $this, 'execute_hide_backend_login' ) );

				add_filter( 'body_class', array( $this, 'remove_admin_bar' ) );
				add_filter( 'wp_redirect', array( $this, 'filter_login_url' ), 10, 2 );
				add_filter( 'site_url', array( $this, 'filter_login_url' ), 10, 2 );

				remove_action( 'template_redirect', 'wp_redirect_admin_locations', 1000 );

			}

			//Process remove login errors
			if ( $this->settings['other-login_errors'] === true ) {
				add_filter( 'login_errors', array( $this, 'empty_return_function' ) );
			}

		}

		/**
		 * Register Brute Force for lockout
		 *
		 * @param  array $lockout_modules array of lockout modules
		 *
		 * @return array                   array of lockout modules
		 */
		public function register_lockout( $lockout_modules ) {

			$lockout_modules['brute_force'] = array(
				'type'   => 'brute_force',
				'reason' => __( 'too many bad login attempts', 'ithemes-security' ),
				'host'   => $this->settings['brute_force-max_attempts_host'],
				'user'   => $this->settings['brute_force-max_attempts_user'],
				'period' => $this->settings['brute_force-check_period']
			);

			return $lockout_modules;

		}

		/**
		 * Register Brute Force for logger
		 *
		 * @param  array $logger_modules array of logger modules
		 *
		 * @return array                   array of logger modules
		 */
		public function register_logger( $logger_modules ) {

			$lockout_modules['brute_force'] = array(
				'type'   => 'brute_force',
				'nice_name' => __( 'Brute Force', 'ithemes=security' ),
			);

			return $lockout_modules;

		}

		/**
		 * Sends to lockout class when username and password are filled out and wrong
		 *
		 * @param string $username the username attempted
		 */
		public function execute_brute_force( $username ) {

			global $itsec_lockout;

			$itsec_lockout->do_lockout( 'brute_force', sanitize_text_field( $username ) );

		}

		/**
		 * Sends to lockout class when login form isn't completely filled out
		 *
		 * @param object $user     user or wordpress error
		 * @param string $username username attempted
		 * @param string $password password attempted
		 *
		 * @return user object or WordPress error
		 */
		public function execute_brute_force_no_password( $user, $username = '', $password = '' ) {

			global $itsec_lockout;

			if ( isset( $_POST['wp-submit'] ) && ( empty( $username ) || empty( $password ) ) ) {

				$itsec_lockout->do_lockout( 'brute_force', sanitize_text_field( $username ) );

			}

			return $user;

		}

		/**
		 * Returns null
		 *
		 * @return null
		 */
		public function empty_return_function() {

			return null;
		}

		/**
		 * Check if away mode is active
		 *
		 * @param bool $forms [false] Whether the call comes from the same options form
		 * @param      array  @input[NULL] Input of options to check if calling from form
		 *
		 * @return bool true if locked out else false
		 */
		public function check_away( $form = false, $input = null ) {

			if ( $form === false ) {

				$test_type  = $this->settings['away_mode-type'];
				$test_start = $this->settings['away_mode-start'];
				$test_end   = $this->settings['away_mode-end'];

			} else {

				$test_type  = $input['away_mode-type'];
				$test_start = $input['away_mode-start'];
				$test_end   = $input['away_mode-end'];

			}

			$transaway = get_site_transient( 'itsec_away' );

			//if transient indicates away go ahead and lock them out
			if ( $form === false && $transaway === true && file_exists( $this->away_file ) ) {

				return true;

			} else { //check manually

				$current_time = current_time( 'timestamp' );

				if ( $test_type == 1 ) { //set up for daily

					$start = strtotime( date( 'n/j/y', $current_time ) . ' ' . date( 'g:i a', $test_start ) );
					$end   = strtotime( date( 'n/j/y', $current_time ) . ' ' . date( 'g:i a', $test_end ) );

					if ( $start > $end ) { //starts and ends on same calendar day

						if ( strtotime( date( 'n/j/y', $current_time ) . ' ' . date( 'g:i a', $start ) ) <= $current_time ) {

							$start = strtotime( date( 'n/j/y', $current_time ) . ' ' . date( 'g:i a', $start ) );
							$end   = strtotime( date( 'n/j/y', ( $current_time + 86400 ) ) . ' ' . date( 'g:i a', $end ) );

						} else {

							$start = strtotime( date( 'n/j/y', $current_time - 86400 ) . ' ' . date( 'g:i a', $start ) );
							$end   = strtotime( date( 'n/j/y', ( $current_time ) ) . ' ' . date( 'g:i a', $end ) );

						}

					}

					if ( $end < $current_time ) { //make sure to advance the day appropriately

						$start = $start + 86400;
						$end   = $end + 86400;

					}

				} else { //one time settings

					$start = $test_start;
					$end   = $test_end;

				}

				$remaining = $end - $current_time;

				if ( $start <= $current_time && $end >= $current_time && ( $form === true || ( $this->settings['enabled'] === 1 && file_exists( $this->away_file ) ) ) ) { //if away mode is enabled continue

					if ( $form === false ) {

						if ( get_site_transient( 'itsec_away' ) === true ) {
							delete_site_transient( 'itsec_away' );
						}

						set_site_transient( 'itsec_away', true, $remaining );

					}

					return true; //time restriction is current

				}

			}

			return false; //they are allowed to log in

		}

		/**
		 * Enqueue script to check password strength
		 *
		 * @return void
		 */
		public function login_script_js() {

			global $itsec_globals;

			wp_enqueue_script( 'itsec_authentication', $itsec_globals['plugin_url'] . 'modules/authentication/js/authentication.js', 'jquery', $itsec_globals['plugin_build'] );

			//make sure the text of the warning is translatable
			wp_localize_script( 'itsec_authentication', 'strong_password_error_text', array( 'text' => __( 'Sorry, but you must enter a strong password.', 'ithemes-security' ) ) );

		}

		/**
		 * Require strong passwords
		 *
		 * Requires new passwords set are strong passwords
		 *
		 * @param object $errors WordPress errors
		 *
		 * @return object WordPress error object
		 *
		 **/
		function enforce_strong_password( $errors ) {

			//determine the minimum role for enforcement
			$minRole = $this->settings['strong_passwords-roll'];

			//all the standard roles and level equivalents
			$availableRoles = array( 'administrator' => '8', 'editor' => '5', 'author' => '2', 'contributor' => '1', 'subscriber' => '0' );

			//roles and subroles
			$rollists = array( 'administrator' => array( 'subscriber', 'author', 'contributor', 'editor' ), 'editor' => array( 'subscriber', 'author', 'contributor' ), 'author' => array( 'subscriber', 'contributor' ), 'contributor' => array( 'subscriber' ), 'subscriber' => array(), );

			$password_meets_requirements = false;
			$args                        = func_get_args();
			$userID                      = isset( $args[2]->user_login ) ? $args[2]->user_login : $_GET['login'];

			if ( $userID ) { //if updating an existing user

				if ( $userInfo = get_user_by( 'login', $userID ) ) {

					foreach ( $userInfo->roles as $capability ) {

						if ( $availableRoles[$capability] >= $availableRoles[$minRole] ) {
							$password_meets_requirements = true;
						}

					}

				} else { //a new user

					if ( ! empty( $_POST['role'] ) && ! in_array( $_POST["role"], $rollists[$minRole] ) ) {
						$password_meets_requirements = true;
					}

				}

			}

			if ( $password_meets_requirements === true ) {
				?>

				<script type="text/javascript">
					jQuery( document ).ready( function () {
						jQuery( '#resetpassform' ).submit( function () {
							if ( ! jQuery( '#pass-strength-result' ).hasClass( 'strong' ) ) {
								alert( '<?php _e( "Sorry, but you must enter a strong password", "ithemes-security" ); ?>' );
								return false;
							}
						} );
					} );
				</script>

			<?php
			}

			if ( ! isset( $_GET['action'] ) ) {

				//add to error array if the password does not meet requirements
				if ( $password_meets_requirements && ! $errors->get_error_data( 'pass' ) && isset( $_POST['pass1'] ) && isset( $_POST['password_strength'] ) && $_POST['password_strength'] != 'strong' ) {
					$errors->add( 'pass', __( '<strong>ERROR</strong>: You MUST Choose a password that rates at least <em>Strong</em> on the meter. Your setting have NOT been saved.', 'ithemes-security' ) );
				}

			}

			return $errors;
		}

		/**
		 * Execute hide backend functionality
		 *
		 * @return void
		 */
		public function execute_hide_backend() {

			global $itsec_lib;

			$url_info   = parse_url( $_SERVER['REQUEST_URI'] );
			$login_path = site_url( $this->settings['hide_backend-slug'], 'relative' );

			//redirect wp-admin and wp-register.php to 404 when not logged in
			if ( ( is_admin() && is_user_logged_in() !== true ) || ( $this->settings['hide_backend-register'] != 'wp-register.php' && strpos( $_SERVER['REQUEST_URI'], 'wp-register.php' ) !== false ) ) {
				$itsec_lib->set_404();
			}

			if ( $url_info['path'] === $login_path ) {

				status_header( 200 );

				require_once( ABSPATH . 'wp-login.php' );

			}

		}

		/**
		 * Filter the old login page out
		 *
		 * @return void
		 */
		public function execute_hide_backend_login() {

			global $itsec_lib;

			if ( strpos( $_SERVER['REQUEST_URI'], 'wp-login.php' ) ) { //are we on the login page

				$itsec_lib->set_404();

			}

		}

		/**
		 * Filters redirects for currect login URL
		 *
		 * @param  string $url  URL redirecting to
		 * @param  string $path Path or status code (depending on which call used)
		 *
		 * @return string       Correct redirect URL
		 */
		public function filter_login_url( $url, $path ) {

			if ( strpos( $url, 'wp-login.php' ) !== false ) { //only run on wp-login.php

				$pos = strpos( $path, '?' );
				$loc = $path;

				if ( $pos === false ) {
					$pos = strpos( $url, '?' );
					$loc = $url;
				}

				if ( $pos === false ) {
					$query = '';
				} else {
					$query = substr( $loc, $pos );
				}

				$login_url = site_url( $this->settings['hide_backend-slug'] ) . $query;

			} else { //not wp-login.php

				$login_url = $url;

			}

			return $login_url;

		}

		/**
		 * Removes the admin bar class from the body tag
		 *
		 * @param  array $classes body tag classes
		 *
		 * @return array          body tag classes
		 */
		function remove_admin_bar( $classes ) {

			if ( is_admin() && is_user_logged_in() !== true ) {

				foreach ( $classes as $key => $value ) {

					if ( $value == 'admin-bar' ) {
						unset( $classes[$key] );
					}

				}

			}

			return $classes;

		}

		/**
		 * Execute away mode functionality
		 *
		 * @return void
		 */
		public function execute_away_mode() {

			//execute lockout if applicable
			if ( $this->check_away() ) {

				wp_redirect( get_option( 'siteurl' ) );
				wp_clear_auth_cookie();

			}

		}

		/**
		 * Start the Authentication module
		 *
		 * @return ITSEC_Authentication                The instance of the ITSEC_Authentication class
		 */
		public static function start() {

			if ( ! isset( self::$instance ) || self::$instance === null ) {
				self::$instance = new self();
			}

			return self::$instance;

		}

	}

}