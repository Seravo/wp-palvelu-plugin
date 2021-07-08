<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName
/**
 * Plugin Name: Seravo Plugin
 * Version: 1.9.30
 * Plugin URI: https://github.com/Seravo/seravo-plugin
 * Description: Enhances WordPress with Seravo.com specific features and integrations.
 * Author: Seravo Oy
 * Author URI: https://seravo.com/
 * Text Domain: seravo
 * Domain Path: /languages/
 * License: GPL v2 or later
 */

namespace Seravo;

// Deny direct access to this file
if ( ! defined('ABSPATH') ) {
  die('Access denied!');
}

/*
 * This plugin should be installed in all WordPress instances at Seravo.com.
 * If you don't want to use some features you can disable any of the modules
 * by adding correct filter into your theme or plugin.
 * For example:
 *
 * add_filter('seravo_show_instance_switcher', '__return_false');
 *
 */

/*
 * Load helpers so that these functions can be used in modules
 */
require_once dirname(__FILE__) . '/lib/helpers.php';

/*
 * Load Seravo API module
 */
require_once dirname(__FILE__) . '/lib/api.php';

/**
 * Load Seravo postbox functionalities
 */
require_once dirname(__FILE__) . '/lib/postbox/component.php';
require_once dirname(__FILE__) . '/lib/postbox/template.php';
require_once dirname(__FILE__) . '/lib/postbox/ajax.php';
require_once dirname(__FILE__) . '/lib/postbox/postbox.php';
require_once dirname(__FILE__) . '/lib/postbox/handler.php';

/*
 * Load Canonical Domain and HTTPS. Check first that WP CLI is not defined so the module will not
 * perform any redirections locally.
 */
if ( ! defined('WP_CLI') ) {
  require_once dirname(__FILE__) . '/lib/canonical-domain-and-https.php';
}

/*
 * Restrict XML-RPC and/or REST-API user enumeration
 */
require_once dirname(__FILE__) . '/lib/security-restrictions.php';

class Loader {
  /**
   * @var $this
   */
  private static $_single; // Let's make this a singleton.
  /**
   * @var string
   */
  private static $domain = 'seravo';

  public function __construct() {
    if ( isset(self::$_single) ) {
      return;
    }
    self::$_single = $this; // Singleton set.

    /*
     * On site init, ensure we have good default for cache key
     */
    add_action('init', array( $this, 'cache_key' ), 1);

    /*
     * Load translations
     */
    add_action(
      'plugins_loaded',
      function () {
        return $this->load_textdomain();
      }
    );

    /*
     * Register early on the direct download add_action as it must trigger
     * before anything is sent to the output buffer.
     */
    add_action(
      'plugins_loaded',
      function () {
        return $this->protected_downloads();
      }
    );

    /*
     * It is important to load plugins in init hook so that themes and plugins can override the functionality
     * Use smaller priority so that all plugins and themes are run first.
     */
    add_action(
      'init',
      function () {
        return $this->load_all_modules();
      },
      20
    );
  }

  /**
   * Pass file download to Nginx with X-Accel-Redirect headers
   * @param string $file Path to file on filesystem, or URL with .seravo prefix
   */
  public static function x_accel_redirect( $file ) {
    // If a real file path was given, send out MIME type and file size headers
    if ( file_exists($file) ) {
      header('Content-Type: ' . mime_content_type($file));
      // phpcs:ignore Security.BadFunctions.FilesystemFunctions.WarnFilesystem
      header('Content-Length: ' . filesize($file));
    }

    // If the filename contains a path component that looks like a filesystem
    // path (has /data/ in it), it will automatically be replaced with the
    // correct URL that Nginx can handle in a X-Accel-Redirect.
    $paths = array(
      '/data/slog/html/',
      '/data/reports/',
    );
    $urls = array(
      '/.seravo/goaccess/',
      '/.seravo/codeception/',
    );
    $file = str_replace($paths, $urls, $file);

    // Send X-Accel-Redirect header (Nginx version of X-Sendfile)
    header('X-Accel-Redirect: ' . $file);

    // Stop executing PHP once a file has been sent and let Nginx handle the rest
    exit();
  }

  /**
   * Configure WP_CACHE_KEY_SALT
   *
   * This makes sure that we always try our best to cache contents as user
   * expects us - no content in wrong languages etc. Value is consumed by the
   * object-cache.php that's distributed with Seravo WordPress template
   * project.
   *
   * You can modify the response by hooking to `seravo_cache_key` filter.
   * Filter accepts $key as parameter, which is an array of key => value
   * pairs which will then be used to construct the actual key string.
   *
   * @see <https://developer.wordpress.org/reference/functions/get_current_site/>
   * @see <https://developer.wordpress.org/reference/functions/apply_filters/>
   **/
  public function cache_key() {
    // If cache key salt is already defined, stop here
    if ( defined('WP_CACHE_KEY_SALT') ) {return;}

    $key = array();

    // If this is a multisite, ensure site_id is part of the cache key to
    // prevent mixing up content from different sites.
    if ( is_multisite() ) {
        $current_site = get_current_site();
        $id = $current_site->id;
        $key['site_id'] = $current_site->id;
    }

    // If WPML language code has been defined, append it to the cache
    // key to prevent content for different languages from being mixed
    // up. This is also supported by Polylang.
    if ( defined('ICL_LANGUAGE_CODE') ) {
        $key['language'] = ICL_LANGUAGE_CODE;
    }

    // Apply filters, you can hook to this filter if you want to add your
    // own config, or modify the default values.
    $key = apply_filters('seravo_cache_key', $key);

    // Sort items, we'd like to have consistent results
    // even if some functions get executed in random order
    ksort($key);

    if ( ! defined('WP_CACHE_KEY_SALT') ) {
      // So, let's set they key as nothing has done so far.
      $ckey = 's_' . str_replace('=', ':', http_build_query($key, '', ':'));
      define('WP_CACHE_KEY_SALT', $ckey);
    }
  }


  /**
   * Pass report file on to admin users
   */
  public static function protected_downloads() {
    global $pagenow;

    // This check fires on every page load, so keep the scope small
    // phpcs:ignore WordPress.CSRF.NonceVerification.NoNonceVerification
    if ( $pagenow === 'tools.php' && isset($_GET['x-accel-redirect']) ) {

      // This URL uses authentication, thus don't cache anything from it
      nocache_headers();

      // User must be an administrator at access these files or
      // if WP Network, then must be super-admin
      if ( ! current_user_can('administrator') ||
           is_multisite() && ! current_user_can('manage_network') ) {
        status_header(401);
        die('Access denied!');
      }

      // Filename must be of correct form, e.g. 2016-09.html or home.png
      // phpcs:ignore WordPress.CSRF.NonceVerification.NoNonceVerification
      if ( isset($_GET['report']) && preg_match('/^\d{4}-\d{2}\.html$/', $_GET['report'], $matches) ) {
        self::x_accel_redirect('/data/slog/html/goaccess-' . $matches[0]);
      // phpcs:ignore WordPress.CSRF.NonceVerification.NoNonceVerification
      } elseif ( isset($_GET['screenshot']) && preg_match('/^[a-z-.]+\.png$/', $_GET['screenshot'], $matches) ) {
        self::x_accel_redirect('/data/reports/tests/debug/' . $matches[0]);
      } else {
        // Yield an error if a file was requested, but with wrong filename.
        status_header(404);
        die('File could not be found.');
      }
    }
  }

  public static function load_textdomain() {

    // Load translations first from the languages directory
    $locale = apply_filters('plugin_locale', get_locale(), self::$domain);

    load_textdomain(
      self::$domain,
      WP_LANG_DIR . '/seravo-plugin/' . self::$domain . '-' . $locale . '.mo'
    );

    // And then from this plugin folder
    load_muplugin_textdomain('seravo', basename(dirname(__FILE__)) . '/languages');
  }

  public static function load_all_modules() {

    /*
     * Helpers for hiding useless notifications and small fixes in logging
     */
    require_once dirname(__FILE__) . '/modules/fixes.php';

    /*
     * Helpers for fixing issues with third-party code (plugins etc.)
     */
    require_once dirname(__FILE__) . '/modules/thirdparty-fixes.php';

    /*
     * Add a cache purge button to the WP adminbar
     */
    require_once dirname(__FILE__) . '/modules/purge-cache.php';

    /*
     * Add a speed test button to the WP adminbar
     */
    require_once dirname(__FILE__) . '/modules/speed-test.php';

    /*
     * Hide the domain alias from search engines
     */
    require_once dirname(__FILE__) . '/modules/noindex-domain-alias.php';

    /*
     * Allow automated login for user 'seravotest' if necessary
     */
    require_once dirname(__FILE__) . '/modules/seravotest-auth-bypass.php';

    /*
     * Log all login attempts, failed or successful. Use no filters, as this should be mandatory
     */
    require_once dirname(__FILE__) . '/modules/wp-login-log.php';

    /*
     * Log plugin and theme activations, deactivations, installations and deletions
     */
    require_once dirname(__FILE__) . '/modules/wp-plugin-log.php';

    /*
     * Log important user changes in user such as roles, passwords and emails
     */
    require_once dirname(__FILE__) . '/modules/wp-user-log.php';

    /*
     * Enforce strong passwords
     */
    require_once dirname(__FILE__) . '/modules/passwords.php';

    /*
     * Instance switcher
     */
    if ( apply_filters('seravo_show_instance_switcher', true) && getenv('WP_ENV') !== 'development' ) {
      require_once dirname(__FILE__) . '/modules/instance-switcher.php';
    }

    /*
     * Check that https is enabled in siteurl
     */
    if ( current_user_can('administrator') ) {
      require_once dirname(__FILE__) . '/modules/check-https.php';
    }

    /*
     * Notify that a newer PHP version is available
     */
    if ( current_user_can('administrator') ) {
      require_once dirname(__FILE__) . '/modules/check-php-version.php';
    }

    /*
     * Check that user has changed admin email to something else from no-reply@seravo
     */
    if ( current_user_can('administrator') ) {
      require_once dirname(__FILE__) . '/modules/check-default-email.php';
    }

    /*
     * Optimize images on upload. Only logged in users make uploads.
     */
    if ( is_user_logged_in() ) {
      require_once dirname(__FILE__) . '/modules/optimize-on-upload.php';
    }

    /*
     * Sanitize a filename on upload to remove special characters.
     * Only logged in users make uploads.
     */
    if ( is_user_logged_in() ) {
      require_once dirname(__FILE__) . '/modules/sanitize-on-upload.php';
    }

    /*
     * Hide some functionality in multisites from normal admins
     */
    if ( ! is_multisite() || current_user_can('manage_network') ) {
      if ( current_user_can('administrator') ) {
        require_once dirname(__FILE__) . '/modules/toolbox.php';
      }

      /*
       * Backups view for Seravo customers
       */
      if ( apply_filters('seravo_show_backups_page', true) && getenv('CONTAINER') ) {
        require_once dirname(__FILE__) . '/modules/backups.php';
      }

      /*
       * Allow Seravo customers to manage their domains & emails
       */
      if ( apply_filters('seravo_show_domains_page', true) && current_user_can('administrator') && getenv('CONTAINER') ) {
        require_once dirname(__FILE__) . '/modules/domains.php';
      }

      /*
       * Show logs from /data/log/*.log in WP-admin
       */
      if ( current_user_can('administrator') ) {
        require_once dirname(__FILE__) . '/modules/logs.php';
      }

      /*
       * Notification with last WordPress login date and error count. This module handles its own
       * capability checks.
       */
      require_once dirname(__FILE__) . '/modules/login-notification.php';

      /*
       * Show notification stylish wp-admin dashboard widgets
       */
      require_once dirname(__FILE__) . '/modules/dashboard-widgets.php';
      /*
       * Upkeep page
       */
      if ( apply_filters('seravo_show_upkeep_page', true) && current_user_can('administrator') ) {
        require_once dirname(__FILE__) . '/modules/upkeep.php';
      }

      /*
       * Site Status page
       */
      if ( apply_filters('seravo_show_site_status_page', true) && current_user_can('administrator') ) {
        require_once dirname(__FILE__) . '/modules/sitestatus.php';
      }

      /*
       * Security page
       */
      if ( apply_filters('seravo_show_security_page', true) && current_user_can('administrator') ) {
        require_once dirname(__FILE__) . '/modules/security.php';
      }
    }

    /*
     * Database and search & replace
     * This module handels it's Network Admin and other permission checks in its own load().
     */
    if ( apply_filters('seravo_show_database_page', true) && current_user_can('administrator') ) {
      require_once dirname(__FILE__) . '/modules/database.php';
    }

    // Load WP-CLI module 'wp seravo'
    if ( defined('WP_CLI') && WP_CLI ) {
      require_once dirname(__FILE__) . '/modules/wp-cli.php';
    }

    /*
     * Hide Users
     * Hides prespecified and given users from a WordPress page
     */
    require_once dirname(__FILE__) . '/modules/hide-users.php';

    /*
     * Add support for SVG images
     * Allow users to upload SVG
     */
    require_once dirname(__FILE__) . '/modules/svg-support.php';
  }
}

new Loader();
