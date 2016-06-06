<?php
/**
 * Class for managing technical support components
 */

// Don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

if ( ! class_exists( 'Tribe__Support' ) ) {

	class Tribe__Support {

		public static $support;
		public        $rewrite_rules_purged = false;

		/**
		 * @var Tribe__Support__Obfuscator
		 */
		protected $obfuscator;

		/**
		 * Fields listed here contain HTML and should be escaped before being
		 * printed.
		 *
		 * @var array
		 */
		protected $must_escape = array(
			'tribeEventsAfterHTML',
			'tribeEventsBeforeHTML',
		);

		/**
		 * Field prefixes here should be partially obfuscated before being printed.
		 *
		 * @var array
		 */
		protected $must_obfuscate_prefixes = array(
			'pue_install_key_',
		);

		private function __construct() {
			$this->must_escape = (array) apply_filters( 'tribe_help_must_escape_fields', $this->must_escape );
			add_action( 'tribe_help_pre_get_sections', array( $this, 'append_system_info' ), 10 );
			add_action( 'delete_option_rewrite_rules', array( $this, 'log_rewrite_rule_purge' ) );

			add_action( 'rest_api_init', array( $this, 'create_sysinfo_endpoint' ) );
			add_action( 'wp_ajax_tribe_toggle_sysinfo_optin', [ $this, 'ajax_sysinfo_optin' ] );
		}

		/**
		 * Display help tab info in events settings
		 *
		 * @param Tribe__Admin__Help_Page $help The Help Page Instance
		 */
		public function append_system_info( Tribe__Admin__Help_Page $help ) {
			$help->add_section_content( 'system-info', $this->formattedSupportStats(), 10 );
		}

		/**
		 * Collect system information for support
		 *
		 * @return array of system data for support
		 */
		public function getSupportStats() {
			$user = wp_get_current_user();

			$plugins = array();
			if ( function_exists( 'get_plugin_data' ) ) {
				$plugins_raw = wp_get_active_and_valid_plugins();
				foreach ( $plugins_raw as $k => $v ) {
					$plugin_details = get_plugin_data( $v );
					$plugin         = $plugin_details['Name'];
					if ( ! empty( $plugin_details['Version'] ) ) {
						$plugin .= sprintf( ' version %s', $plugin_details['Version'] );
					}
					if ( ! empty( $plugin_details['Author'] ) ) {
						$plugin .= sprintf( ' by %s', $plugin_details['Author'] );
					}
					if ( ! empty( $plugin_details['AuthorURI'] ) ) {
						$plugin .= sprintf( '(%s)', $plugin_details['AuthorURI'] );
					}
					$plugins[] = $plugin;
				}
			}

			$network_plugins = array();
			if ( is_multisite() && function_exists( 'get_plugin_data' ) ) {
				$plugins_raw = wp_get_active_network_plugins();
				foreach ( $plugins_raw as $k => $v ) {
					$plugin_details = get_plugin_data( $v );
					$plugin         = $plugin_details['Name'];
					if ( ! empty( $plugin_details['Version'] ) ) {
						$plugin .= sprintf( ' version %s', $plugin_details['Version'] );
					}
					if ( ! empty( $plugin_details['Author'] ) ) {
						$plugin .= sprintf( ' by %s', $plugin_details['Author'] );
					}
					if ( ! empty( $plugin_details['AuthorURI'] ) ) {
						$plugin .= sprintf( '(%s)', $plugin_details['AuthorURI'] );
					}
					$network_plugins[] = $plugin;
				}
			}

			$mu_plugins = array();
			if ( function_exists( 'get_mu_plugins' ) ) {
				$mu_plugins_raw = get_mu_plugins();
				foreach ( $mu_plugins_raw as $k => $v ) {
					$plugin = $v['Name'];
					if ( ! empty( $v['Version'] ) ) {
						$plugin .= sprintf( ' version %s', $v['Version'] );
					}
					if ( ! empty( $v['Author'] ) ) {
						$plugin .= sprintf( ' by %s', $v['Author'] );
					}
					if ( ! empty( $v['AuthorURI'] ) ) {
						$plugin .= sprintf( '(%s)', $v['AuthorURI'] );
					}
					$mu_plugins[] = $plugin;
				}
			}

			$keys = apply_filters( 'tribe-pue-install-keys', array() );

			$systeminfo = array(
				'Home URL'               => get_home_url(),
				'Site URL'               => get_site_url(),
				'name'                   => $user->display_name,
				'email'                  => $user->user_email,
				'install keys'           => $keys,
				'WordPress version'      => get_bloginfo( 'version' ),
				'PHP version'            => phpversion(),
				'plugins'                => $plugins,
				'network plugins'        => $network_plugins,
				'mu plugins'             => $mu_plugins,
				'theme'                  => wp_get_theme()->get( 'Name' ),
				'multisite'              => is_multisite(),
				'settings'               => Tribe__Settings_Manager::get_options(),
				'WordPress timezone'     => get_option( 'timezone_string', esc_html__( 'Unknown or not set', 'tribe-common' ) ),
				'server timezone'        => date_default_timezone_get(),
				'common library dir'     => $GLOBALS['tribe-common-info']['dir'],
				'common library version' => $GLOBALS['tribe-common-info']['version'],
			);

			if ( $this->rewrite_rules_purged ) {
				$systeminfo['rewrite rules purged'] = esc_html__( 'Rewrite rules were purged on load of this help page. Chances are there is a rewrite rule flush occurring in a plugin or theme!', 'tribe-common' );
			}

			$systeminfo = apply_filters( 'tribe-events-pro-support', $systeminfo );

			return $systeminfo;
		}

		/**
		 * Render system information into a pretty output
		 *
		 * @return string pretty HTML
		 */
		public function formattedSupportStats() {
			$systeminfo = $this->getSupportStats();
			$output     = '';
			$output .= '<dl class="support-stats">';
			foreach ( $systeminfo as $k => $v ) {

				switch ( $k ) {
					case 'name' :
					case 'email' :
						continue 2;
						break;
					case 'url' :
						$v = sprintf( '<a href="%s">%s</a>', $v, $v );
						break;
				}

				if ( is_array( $v ) ) {
					$keys             = array_keys( $v );
					$key              = array_shift( $keys );
					$is_numeric_array = is_numeric( $key );
					unset( $keys );
					unset( $key );
				}

				$output .= sprintf( '<dt>%s</dt>', $k );
				if ( empty( $v ) ) {
					$output .= '<dd class="support-stats-null">-</dd>';
				} elseif ( is_bool( $v ) ) {
					$output .= sprintf( '<dd class="support-stats-bool">%s</dd>', $v );
				} elseif ( is_string( $v ) ) {
					$output .= sprintf( '<dd class="support-stats-string">%s</dd>', $v );
				} elseif ( is_array( $v ) && $is_numeric_array ) {
					$output .= sprintf( '<dd class="support-stats-array"><ul><li>%s</li></ul></dd>', join( '</li><li>', $v ) );
				} else {
					$formatted_v = array();
					foreach ( $v as $obj_key => $obj_val ) {
						if ( in_array( $obj_key, $this->must_escape ) ) {
							$obj_val = esc_html( $obj_val );
						}

						$obj_val = $this->obfuscator->obfuscate( $obj_key, $obj_val );

						if ( is_array( $obj_val ) ) {
							$formatted_v[] = sprintf( '<li>%s = <pre>%s</pre></li>', $obj_key, print_r( $obj_val, true ) );
						} else {
							$formatted_v[] = sprintf( '<li>%s = %s</li>', $obj_key, $obj_val );
						}
					}
					$v = join( "\n", $formatted_v );
					$output .= sprintf( '<dd class="support-stats-object"><ul>%s</ul></dd>', print_r( $v, true ) );
				}
			}
			$output .= '</dl>';

			return $output;
		}

		/**
		 * Logs the occurence of rewrite rule purging
		 */
		public function log_rewrite_rule_purge() {
			$this->rewrite_rules_purged = true;
		}//end log_rewrite_rule_purge

		/**
		 * Sets the obfuscator to be used.
		 *
		 * @param Tribe__Support__Obfuscator $obfuscator
		 */
		public function set_obfuscator( Tribe__Support__Obfuscator $obfuscator ) {
			$this->obfuscator = $obfuscator;
		}

		/**
		 * Creates Fields in Help Tab to Opt In to System Info
		 *
		 * @return string
		 */
		public static function opt_in() {

			$checked   = '';
			$optin_key = get_option( 'tribe_systeminfo_optin' );
			if ( $optin_key ) {
				$checked = 'checked';
			}

			$opt_in = '<input name="tribe_auto_sysinfo_opt_in" id="tribe_auto_sysinfo_opt_in" type="checkbox" value="optin" ' . esc_attr( $checked ) . '/>';
			$opt_in .= '<label for="tribe_auto_sysinfo_opt_in">' . esc_html__( 'Yes, automatically share my system information with the Modern Tribe support team', 'tribe-common' ) . '</label>';
			$opt_in .= '<p class="tooltip description">' . esc_html__( 'Your system information will only be used by the Modern Tribe support team. All information is stored securely. We do not share this information with any third parties.', 'tribe-common' ) . '</p>';

			$opt_in .= '<script>
				jQuery( function ( $ ) {
					$( "#tribe_auto_sysinfo_opt_in" ).change(function() {
						if(this.checked) {
							console.log( "genrate and send" );
							do_optin_change( "generate" );
						} else {
							console.log( "delete" );
							do_optin_change();
						}
					});

					/**
					 * Handle Opt-in Change
					 */
					function do_optin_change( generate=null ) {
						var request = {
							"action": "tribe_toggle_sysinfo_optin",
							"confirm": "' . wp_create_nonce( "sysinfo_optin" ) . '",
							"generate_key": generate
						};

						// Send our request
						$.post( ajaxurl, request, function() {

						});
					}

				});
				</script>';


			return $opt_in;
		}

		/**
		 * Method to send back sysinfo
		 *
		 * @param $query
		 *
		 * @return string|void
		 *
		 */
		public function sysinfo_query( $query ) {

			$optin_key = get_option( 'tribe_systeminfo_optin' );

			if ( ! $optin_key ) {
				return __( 'Invalid Opt-in Key', 'tribe-common' );
			}

			$key = $query['key'];
			if ( $key != $optin_key ) {
				return __( 'Invalid System Info Key', 'tribe-common' );
			}

			$support    = Tribe__Support::getInstance();
			$systeminfo = $support->formattedSupportStats();

			return $systeminfo;
		}

		/*
		 * Create Unique Enpoint Per Site
		 */
		public static function create_sysinfo_endpoint() {
			$optin_key = get_option( 'tribe_systeminfo_optin' );
			if ( $optin_key ) {
				register_rest_route( 'tribe_events/v2', '/(?P<key>[a-z0-9\-]+)/sysinfo/', array(
					'methods'  => 'GET',
					'callback' => array( 'Tribe__Support', 'sysinfo_query' ),
				) );
			}
		}

		/**
		 * Ajax Method to Create Unique Key and send to tec.com
		 */
		public static function ajax_sysinfo_optin() {

			if ( ! isset( $_POST['confirm'] ) || ! wp_verify_nonce( $_POST['confirm'], 'sysinfo_optin' ) ) {
				exit( '-1' );
			}

			if ( $_POST['generate_key'] ) {

				$random    = base_convert( rand( 0, getrandmax() ), 10, 36 );
				$optin_key = hash( 'sha1', $random );
				update_option( 'tribe_systeminfo_optin', $optin_key );

				//Only Connect If a License Exists
				$keys = apply_filters( 'tribe-pue-install-keys', array() );
				if ( is_array( $keys ) ) {
					$url      = urlencode( str_replace( array( 'http://', 'https://' ), '', get_site_url() ) );
					$pue      = new Tribe__PUE__Checker( 'http://tri.be/', 'events-calendar' );
					$query    = $pue->get_pue_update_url() . 'wp-json/tribe_system/v2/customer-info/' . $optin_key . '/' . $url;
					$response = wp_remote_get( esc_url( $query ) );
				}
				exit( '1' );

			}

			delete_option( 'tribe_systeminfo_optin' );

			exit( '1' );

		}

		/****************** SINGLETON GUTS ******************/

		/**
		 * Enforce Singleton Pattern
		 */
		private static $instance;


		public static function getInstance() {
			if ( null == self::$instance ) {
				$instance = new self;
				$instance->set_obfuscator( new Tribe__Support__Obfuscator( $instance->must_obfuscate_prefixes ) );
				self::$instance = $instance;
			}

			return self::$instance;
		}
	}

}
