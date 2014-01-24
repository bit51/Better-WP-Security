<?php

if ( ! class_exists( 'ITSEC_Intrusion_Detection' ) ) {

	class ITSEC_Intrusion_Detection {

		private static $instance = null;

		private
			$settings;

		private function __construct() {

			$this->settings  = get_site_option( 'itsec_intrusion_detection' );

			add_filter( 'itsec_lockout_modules', array( $this, 'register_lockout' ) );
			add_filter( 'itsec_logger_modules', array( $this, 'register_logger' ) );

			add_action( 'wp_head', array( $this,'check_404' ) );

		}

		public function check_404() {

			global $itsec_lib, $itsec_logger, $itsec_lockout;

			if ( $this->settings['four_oh_four-enabled'] === true && is_404() ) {

				$uri = explode( '?', $_SERVER['REQUEST_URI'] );

				if ( in_array( $uri[0], $this->settings['four_oh_four-white_list'] ) === false ) {

					$itsec_logger->log_event(
						'four_oh_four',
						3,
						array(
							'query_string' => isset( $uri[1] ) ? esc_sql( $uri[1] ) : '',
						),
						$itsec_lib->get_ip(),
						'',
						'',
						esc_sql( $uri[0] ),
						isset( $_SERVER['HTTP_REFERER'] ) ? esq_sql( $_SERVER['HTTP_REFERER'] ) : ''
					);

					$itsec_lockout->do_lockout( 'four_oh_four' );

				}

			}

		}

		/**
		 * Register 404 detection for lockout
		 *
		 * @param  array $lockout_modules array of lockout modules
		 *
		 * @return array                   array of lockout modules
		 */
		public function register_lockout( $lockout_modules ) {

			$lockout_modules['four_oh_four'] = array(
				'type'   => 'four_oh_four',
				'reason' => __( 'too many attempts to access a file that doesn not exist', 'ithemes-security' ),
				'host'   => $this->settings['four_oh_four-error_threshold'],
				'period' => $this->settings['four_oh_four-check_period']
			);

			return $lockout_modules;

		}

		/**
		 * Register 404 and file change detection for logger
		 *
		 * @param  array $logger_modules array of logger modules
		 *
		 * @return array                   array of logger modules
		 */
		public function register_logger( $logger_modules ) {

			$logger_modules['four_oh_four'] = array(
				'type'      => 'four_oh_four',
				'function' => __( '404 Error', 'ithemes-security' ),
			);

			return $logger_modules;

		}

		/**
		 * Start the Intrusion Detection module
		 *
		 * @return 'ITSEC_Intrusion_Detection'                The instance of the 'ITSEC_Intrusion_Detection' class
		 */
		public static function start() {

			if ( ! isset( self::$instance ) || self::$instance === null ) {
				self::$instance = new self();
			}

			return self::$instance;

		}

	}

}