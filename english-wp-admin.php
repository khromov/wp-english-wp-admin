<?php
/*
Plugin Name: English WordPress Admin
Plugin URI: http://wordpress.org/plugins/english-wp-admin
Description: Lets users change their administration language to English
Version: 1.3.1
Author: khromov
Author URI: http://snippets.khromov.se
License: GPL2
*/

/*
 * Main plugin class
 */
class Admin_Custom_Language
{
	/* Constructor for adding hooks */
	function __construct()
	{
		//Locale filter
		add_filter('locale', array(&$this, 'set_locale'));

		//Registers GET listener to toggle setting
		add_action('init', array(&$this, 'register_endpoints'));

		//Adds admin bar menu
		add_action('admin_bar_menu', array(&$this, 'admin_bar'), 31);
		add_action('admin_head', array($this, 'admin_css'));

		if($this->english_install_only())
			add_action( 'admin_notices', array($this, 'admin_notices'));
	}

	/**
	 * This function is responsible fo setting the locale via the locale filter
	 *
	 * @param $lang the current locale
	 * @return string the locale that should be used
	 */
	function set_locale($lang)
	{
		//If cookie is set and enabled, and we are not doing frontend AJAX, we should switch the locale
		if($this->english_admin_enabled() && !$this->request_is_frontend_ajax())
		{
			//Switch locale if we are on an admin page
			if(is_admin())
				return 'en_US';
		}

		//Default return
		return $lang;
	}

	/**
	 * This plugin listens for the GET variable that toggles the current setting
	 */
	function register_endpoints()
	{
		//We're in admin
		if(is_admin())
		{
			//Is the GET variable set?
			if(isset($_GET['admin_custom_language_toggle']))
			{
				//Cast variable
				$cookie_value = intval($_GET['admin_custom_language_toggle']);

				//Set cookie
				$cookie_value === 1 ? $this->set_cookie(1) : $this->set_cookie(0);

				if(isset($_GET['admin_custom_language_return_url']))
					wp_redirect(urldecode($_GET['admin_custom_language_return_url']));
				else
					wp_redirect(admin_url());
			}
		}
	}

	/**
	 * Sets the cookie. (1 year expiry)
	 */
	function set_cookie($value = '1')
	{
		setcookie('wordpress_admin_default_language_'. COOKIEHASH, $value, strtotime('+1 year'), COOKIEPATH, COOKIE_DOMAIN, false);
	}

	/**
	 * Check so that we are not doing a frontend AJAX request
	 *
	 * @return bool
	 */
	function request_is_frontend_ajax()
	{
		return defined('DOING_AJAX') && DOING_AJAX && false === strpos( wp_get_referer(), '/wp-admin/' );
	}

	/**
	 * Checks if WordPress has a non-english language configured
	 *
	 * @return bool True if we don't have any additional language set in WPLANG
	 */
	function english_install_only()
	{
		if(defined('WPLANG'))
			return (WPLANG === 'en_US' || WPLANG === '') ? true : false;
		else
		{
			if(function_exists('get_bloginfo') && get_bloginfo('language') !== 'en_US')
				return false;
			else
				return true;
		}
	}

	/**
	 * Adds a menu item to the admin bar via the admin_bar_menu hook
	 *
	 * @param $wp_admin_bar The WP_Admin_Bar object
	 */
	function admin_bar($wp_admin_bar)
	{
		//We're in admin and this is not an english-only install
		if(is_admin() && !$this->english_install_only() && apply_filters('english_wordpress_admin_show_admin_bar', true) === true)
		{
			//Sets up the toggle link
			$toggle_href = admin_url('?admin_custom_language_toggle=' . ($this->english_admin_enabled() ? '0' : '1') . '&admin_custom_language_return_url=' . urlencode((is_ssl() ? 'https' : 'http') . '://' . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"]));

			$message_on = __('Switch to native', 'admin-custom-language');
			$message_off = __('Switch to English', 'admin-custom-language');

			//Add main menu
			$main_bar = array(
				'id' => 'admin-custom-language-icon',
				'title' => $this->admin_bar_title(),
				'href' => $toggle_href,
				'meta' => array(
					'class' => 'admin-custom-language-icon'
				)
			);

			//Add sub menu
			$main_bar_sub = array(
				'id' => 'admin-custom-language-icon-submenu',
				'title' => ($this->english_admin_enabled() ? $message_on : $message_off),
				'href' => $toggle_href,
				'parent' => 'admin-custom-language-icon'
			);

			$wp_admin_bar->add_node($main_bar);
			$wp_admin_bar->add_node($main_bar_sub);

		}

	}

	/**
	 * Sets the admin bar title
	 *
	 * @return string
	 */
	function admin_bar_title()
	{
		return get_locale();
	}

	/**
	 * Gets the cookie settin value, or null if there is no cookie set
	 *
	 * @return int|null
	 */
	function cookie_setting_value()
	{
		if(defined('COOKIEHASH') && isset($_COOKIE['wordpress_admin_default_language_'. COOKIEHASH]))
			return intval($_COOKIE['wordpress_admin_default_language_'. COOKIEHASH]);
		else
			return null;
	}

	/**
	 * Checks if the functionality is enabled
	 *
	 * @return bool True if we should show the english admin
	 */
	function english_admin_enabled()
	{
		return $this->cookie_setting_value() === 1 ? true : false;
	}

	/**
	 * Adds a little icon to the admin bar for later WordPress versions
	 */
	function admin_css()
	{
		if($this->wp_version_at_least('3.8'))
		{
			echo '
			<style type="text/css">
				#wpadminbar #wp-admin-bar-admin-custom-language-icon > .ab-item:before
				{
					/* admin globe - content:"\f319"; */
					/* translate icon */
					content:"\f326";
					top: 2px;
				}
         	</style>
         ';
		}
	}

	/**
	 * Version checker function
	 *
	 * @param $version The version we want to check against the current one
	 * @return bool True if the current WP version is at least as new as $version
	 */
	function wp_version_at_least($version)
	{
		if (version_compare(get_bloginfo('version'), $version, '>='))
			return true;
		else
			return false;
	}

	/*
	 * Handles admin notices
	 */
	function admin_notices()
	{
		?>
		<div class="error">
			<p><?php _e( "<strong>English Wordpress Admin Error</strong> <br/>You only have English language installed. Please install another language before using this plugin. <a href='http://codex.wordpress.org/Installing_WordPress_in_Your_Language' target='_blank'>Read more (WordPress codex)</a> <br/> This plugin is not compatible with WPML, as WPML already provides this functionality under the \"Profile\" tab.", 'admin-custom-language' ); ?></p>
		</div>
		<?php
	}
}

/* Init plugin */
$english_wordpress_admin_plugin = new Admin_Custom_Language();