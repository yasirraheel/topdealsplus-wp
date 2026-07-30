<?php

namespace Blocksy;

if (! defined('ABSPATH')) {
	exit;
}

class CaptchaToolsIntegration {
	public function __construct() {
		// https://github.com/Creative-Themes/blocksy/commit/7cb8c7704
		add_action('blocksy:account:user-flow:before-lostpassword', function() {
			add_filter('secupress.plugins.move_login.deny_bypass', '__return_true');
		});

		add_action('blocksy:account:user-flow:before-registration', function() {
			add_filter('secupress.plugins.move_login.deny_bypass', '__return_true');
		});

		add_action('blocksy:account:user-flow:before-login', function() {
			add_filter('secupress.plugins.move_login.deny_bypass', '__return_true');
		});

		// https://wordpress.org/plugins/two-factor/
		add_action('blocksy:account:user-flow:before-login', function () {
			if (! class_exists('Two_Factor_Core')) {
				return;
			}

			remove_action(
				'wp_login',
				array('Two_Factor_Core', 'wp_login'),
				PHP_INT_MAX
			);

			add_action('wp_login', function ($user_login, $user) {
				if (! \Two_Factor_Core::is_user_using_two_factor($user->ID)) {
					return;
				}

				\Two_Factor_Core::destroy_current_session_for_user($user);
				wp_clear_auth_cookie();

				ob_start();
				\Two_Factor_Core::show_two_factor_login($user);
				$html = ob_get_clean();

				wp_send_json_success([
					'html' => $html,
					'redirect_to' => ''
				]);
			}, PHP_INT_MAX, 2);
		});

		add_action('wp', function() {
			if (! \Blocksy\Plugin::instance()->header->has_account_modal()) {
				return;
			}

			ob_start();
			do_action('anr_captcha_form_field');
			ob_get_clean();
		});

		add_action('after_setup_theme', function () {
			if (! class_exists('NextendSocialLogin')) {
				return;
			}

			remove_action('wp_print_scripts', 'NextendSocialLogin::nslDOMReady');

			add_action('wp_print_scripts', function () {
				if (! \Blocksy\Plugin::instance()->header->has_account_modal()) {
					\NextendSocialLogin::nslDOMReady();
					return;
				}

				echo '<script type="text/javascript">
					window._nslDOMReady = function (callback) {
						window.nslReinit = callback

						if ( document.readyState === "complete" || document.readyState === "interactive" ) {
							callback();
						} else {
							document.addEventListener( "DOMContentLoaded", callback );
						}
					};
				</script>';
			});
		}, 100);

		add_action('wp_enqueue_scripts', function () {
			global $aio_wp_security;

			if (! function_exists('get_plugin_data')){
				require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			}

			if (is_admin()) return;

			if (! function_exists('blocksy_media')) {
				return;
			}

			if (! \Blocksy\Plugin::instance()->header->has_account_modal()) {
				return;
			}

			if (class_exists('WordfenceLS\Controller_WordfenceLS')) {
				$ctl = \WordfenceLS\Controller_WordfenceLS::shared();
				$ctl->_login_enqueue_scripts();
			}

			if (class_exists('LoginNocaptcha')) {
				add_action(
					'lostpassword_errors',
					['LoginNocaptcha', 'authenticate'],
					10, 1
				);

				\LoginNocaptcha::enqueue_scripts_css();

				wp_enqueue_script('login_nocaptcha_google_api');
				wp_enqueue_style('login_nocaptcha_css');
			}

			if (function_exists('anr_login_enqueue_scripts')) {
				anr_login_enqueue_scripts();
			}

			if (isset($aio_wp_security) && isset($aio_wp_security->captcha_obj)) {
				// This is a WordPress core hook, not a plugin-defined one.
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
				do_action('login_enqueue_scripts');
			}
		}, 50);
	}
}
