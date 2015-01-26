<?php
/*
Plugin Name: English WordPress Admin
Plugin URI: http://wordpress.org/plugins/english-wp-admin
Description: Lets users change their administration language to English
Version: 1.4
Author: khromov
Author URI: http://snippets.khromov.se
GitHub Plugin URI: khromov/wp-english-wp-admin
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

		//Adds admin bar menu
		add_action('admin_bar_menu', array(&$this, 'admin_bar'), 31);
		add_action('admin_head', array($this, 'admin_css'));

		//Init action
		add_action('init', array($this, 'init'));
	}


	function init()
	{
		//Registers GET listener to toggle setting
		$this->register_endpoints();

		//Message if WPML installed
		if($this->wpml_installed())
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
		if($this->english_admin_enabled() && !$this->request_is_frontend_ajax() && !$this->in_url_whitelist() && !$this->woocommerce_action())
		{
			//Switch locale if we are on an admin page
			if(is_admin())
				return 'en_US';
		}

		//Default return
		return $lang;
	}

	/**
	 * Attempt to identify WooCommerce actions (like sending emails)
	 *
	 * @return bool
	 */
	function woocommerce_action()
	{
		if(isset($_POST['wc_order_action']))
			return true;
		else
			return false;
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
	 * Whitelist some URL:s from translation
	 *
	 * @return bool
	 */
	function in_url_whitelist()
	{
		$whitelisted_urls = array(
			'wp-admin/update-core.php',
			'wp-admin/options-general.php'
		);

		$request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';

		foreach($whitelisted_urls as $whitelisted_url)
		{
			if(strpos($request_uri, $whitelisted_url) !== false)
				return true;
		}

		return false;
	}

	/**
	 * Checks if a version number is larger than or equal to a certain version
	 *
	 * @param $version
	 * @return bool
	 */
	function is_version($version)
	{
		global $wp_version;
		return version_compare($wp_version, $version, '>=');
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
		//$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
		$script_filename = isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : '';

		//Try to figure out if frontend AJAX request...
		if(defined('DOING_AJAX') && DOING_AJAX)
		{
			if(
				(strpos(wp_get_referer(), admin_url()) !== false) && //If AJAX referrer is an admin URL AND
				(basename($_SERVER['SCRIPT_FILENAME']) === 'admin-ajax.php') //If the script being executed has admin-ajax.php as the endpoint
			)
			{
				return true;
			}
			else
				return false;
		}
		else
			return false;
	}

	/**
	 * Checks if WordPress has a non-english language configured
	 *
	 * @return bool True if we don't have any additional language set in WPLANG
	 */
	function english_install_only()
	{
		if($this->wpml_installed())
			return false;

		//If using WPLANG, otherwise check DB
		if(defined('WPLANG'))
			return (WPLANG === 'en_US' || trim(WPLANG) === '') ? true : false;
		else
		{
			//If language not en_US and not empty in database
			if(function_exists('get_bloginfo') && (get_bloginfo('language') !== 'en_US' || trim(get_bloginfo('language')) !== '') )
				return false;
			else
				return true;
		}
	}

	/**
	 * Checks if WPML is installed
	 * @return bool
	 */
	function wpml_installed()
	{
		return defined('ICL_LANGUAGE_CODE');
	}

	/**
	 * Adds a menu item to the admin bar via the admin_bar_menu hook
	 *
	 * @param $wp_admin_bar The WP_Admin_Bar object
	 */
	function admin_bar($wp_admin_bar)
	{
		//We're in admin and this is not a WPML install
		if(is_admin() && apply_filters('english_wordpress_admin_show_admin_bar', true) === true)
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

			if(!$this->in_url_whitelist())
			{
				$wp_admin_bar->add_node($main_bar);
				$wp_admin_bar->add_node($main_bar_sub);
			}
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
			<p><?php _e( "<strong>English Wordpress Admin Error</strong> <br/>You only have English language installed, or you are using WPML. If you only have English installed, please install another language before using this plugin. <a href='http://codex.wordpress.org/Installing_WordPress_in_Your_Language' target='_blank'>Read more (WordPress codex)</a> <br/> If you are using WPML, you do not need this plugin. WPML already provides a language switcher that can be configured under the \"Profile\" tab.", 'admin-custom-language' ); ?></p>
		</div>
		<?php
	}
}

/* Init plugin */
$english_wordpress_admin_plugin = new Admin_Custom_Language();