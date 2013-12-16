<?php

if ( ! class_exists( 'Ithemes_BWPS_Utilities' ) ) {

	final class Ithemes_BWPS_Utilities {

		private static $instance = NULL; //instantiated instance of this plugin

		public
			$plugin;

		private
			$lock_file;

		/**
		 * Loads core functionality across both admin and frontend.
		 *
		 * @param Ithemes_BWPS $plugin
		 */
		private function __construct( $plugin ) {

			global $bwps_globals;

			$this->plugin = $plugin; //Allow us to access plugin defaults throughout

			$this->lock_file = $bwps_globals['upload_dir'] . '/config.lock';

		}

		/**
		 * Gets location of wp-config.php
		 *
		 * Finds and returns path to wp-config.php
		 *
		 * @return string path to wp-config.php
		 *
		 **/
		public function get_config() {

			if ( file_exists( trailingslashit( ABSPATH ) . 'wp-config.php' ) ) {

				return trailingslashit( ABSPATH ) . 'wp-config.php';

			} else {

				return trailingslashit( dirname( ABSPATH ) ) . 'wp-config.php';

			}

		}

		/**
		 * Attempt to get a lock for atomic operations
		 *
		 * @param string $lock the lock file to acquire (if not standard)
		 *
		 * @return bool true if lock was achieved, else false
		 */
		public function get_lock( $lock = NULL ) {

			//all to override the lock file
			if ( $lock === NULL ) {
				$lock_file = $lock;
			} else {
				$lock_file = $this->lock_file;
			}

			if ( file_exists( $lock_file ) ) {

				$pid = @file_get_contents( $lock_file );

				if ( @posix_getsid( $pid ) !== false ) {

					return false; //file is locked for writing

				}

			}

			@file_put_contents( $this->lock_file, getmypid() );

			return true; //file lock was achieved

		}

		/**
		 * Determine whether we're on the login page or not
		 *
		 * @return bool true if is login page else false
		 */
		public function is_login_page() {

			return in_array( $GLOBALS['pagenow'], array( 'wp-login.php', 'wp-register.php' ) );

		}

		/**
		 * Release the lock
		 *
		 * @param string $lock the lock file to release (if not standard)
		 *
		 * @return bool true if released, false otherwise
		 */
		public function release_lock( $lock = NULL ) {

			//all to override the lock file
			if ( $lock === NULL ) {
				$lock_file = $lock;
			} else {
				$lock_file = $this->lock_file;
			}

			if ( @unlink( $lock_file ) ) {
				return true;
			}

			return false;

		}

		/**
		 * Gets location of .htaccess
		 *
		 * Finds and returns path to .htaccess
		 *
		 * @return string path to .htaccess
		 *
		 **/
		public function get_htaccess() {

			return ABSPATH . '.htaccess';

		}

		/**
		 * Returns the actual IP address of the user
		 *
		 * @return  String The IP address of the user
		 *
		 * */
		public function get_ip() {

			//Just get the headers if we can or else use the SERVER global
			if ( function_exists( 'apache_request_headers' ) ) {

				$headers = apache_request_headers();

			} else {

				$headers = $_SERVER;

			}

			//Get the forwarded IP if it exists
			if ( array_key_exists( 'X-Forwarded-For', $headers ) && ( filter_var( $headers['X-Forwarded-For'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) || filter_var( $headers['X-Forwarded-For'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) ) {

				$the_ip = $headers['X-Forwarded-For'];

			} else {

				$the_ip = $_SERVER['REMOTE_ADDR'];

			}

			return $the_ip;

		}

		/**
		 * Start the global utilities instance
		 *
		 * @param  [plugin_class]  $plugin       Instance of main plugin class
		 *
		 * @return Ithemes_BWPS_Utilities          The instance of the Ithemes_BWPS_Utilities class
		 */
		public static function start( $plugin ) {

			if ( ! isset( self::$instance ) || self::$instance === NULL ) {
				self::$instance = new self( $plugin );
			}

			return self::$instance;

		}

	}

}