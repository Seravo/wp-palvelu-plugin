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

// Use debug mode only in development
define('SERAVO_PLUGIN_DEBUG', false);
if ( defined('SERAVO_PLUGIN_DEBUG') && SERAVO_PLUGIN_DEBUG ) {
  nocache_headers();
}

if ( ! defined('SERAVO_PLUGIN_URL') ) {
  define('SERAVO_PLUGIN_URL', plugin_dir_url(__FILE__));
}
if ( ! defined('SERAVO_PLUGIN_DIR') ) {
  define('SERAVO_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
if ( ! defined('SERAVO_PLUGIN_SRC') ) {
  define('SERAVO_PLUGIN_SRC', SERAVO_PLUGIN_DIR . 'src/');
}

// Use Postbox::class for now to see if autoload needs to be required
if ( ! class_exists(\Seravo\Postbox\Postbox::class) && is_file(SERAVO_PLUGIN_DIR . 'vendor/autoload.php') ) {
  require_once SERAVO_PLUGIN_DIR . 'vendor/autoload.php';
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

/**
 * Load Seravo postbox functionalities
 */
require_once SERAVO_PLUGIN_SRC . 'modules/postbox-init.php';

/*
 * Load Canonical Domain and HTTPS. Check first that WP CLI is not defined so the module will not
 * perform any redirections locally.
 */
if ( ! defined('WP_CLI') ) {
  require_once SERAVO_PLUGIN_SRC . 'lib/canonical-domain-and-https.php';
}

/*
 * Restrict XML-RPC and/or REST-API user enumeration
 */
require_once SERAVO_PLUGIN_SRC . 'lib/security-restrictions.php';

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
    load_muplugin_textdomain('seravo', basename(SERAVO_PLUGIN_DIR) . '/languages');
  }

  public static function load_all_modules() {
    /*
     * Helpers for hiding useless notifications and small fixes in logging
     */
    require_once SERAVO_PLUGIN_SRC . 'modules/fixes.php';

    /*
     * Helpers for fixing issues with third-party code (plugins etc.)
     */
    require_once SERAVO_PLUGIN_SRC . 'modules/thirdparty-fixes.php';

    /*
     * Add a cache purge button to the WP adminbar
     */
    require_once SERAVO_PLUGIN_SRC . 'modules/purge-cache.php';

    /*
     * Add a speed test button to the WP adminbar
     */
    require_once SERAVO_PLUGIN_SRC . 'modules/speed-test.php';

    /*
     * Hide the domain alias from search engines
     */
    require_once SERAVO_PLUGIN_SRC . 'modules/noindex-domain-alias.php';

    /*
     * Allow automated login for user 'seravotest' if necessary
     */
    require_once SERAVO_PLUGIN_SRC . 'modules/seravotest-auth-bypass.php';

    /*
     * Log all login attempts, failed or successful. Use no filters, as this should be mandatory
     */
    require_once SERAVO_PLUGIN_SRC . 'modules/wp-login-log.php';

    /*
     * Log plugin and theme activations, deactivations, installations and deletions
     */
    require_once SERAVO_PLUGIN_SRC . 'modules/wp-plugin-log.php';

    /*
     * Log important user changes in user such as roles, passwords and emails
     */
    require_once SERAVO_PLUGIN_SRC . 'modules/wp-user-log.php';

    /*
     * Enforce strong passwords
     */
    require_once SERAVO_PLUGIN_SRC . 'modules/passwords.php';

    /*
     * Instance switcher
     */
    if ( apply_filters('seravo_show_instance_switcher', true) && getenv('WP_ENV') !== 'development' ) {
      require_once SERAVO_PLUGIN_SRC . 'modules/instance-switcher.php';
    }

    /*
     * Check that https is enabled in siteurl
     */
    if ( current_user_can('administrator') ) {
      require_once SERAVO_PLUGIN_SRC . 'modules/check-https.php';
    }

    /*
     * Notify that a newer PHP version is available
     */
    if ( current_user_can('administrator') ) {
      require_once SERAVO_PLUGIN_SRC . 'modules/check-php-version.php';
    }

    /*
     * Check that user has changed admin email to something else from no-reply@seravo
     */
    if ( current_user_can('administrator') ) {
      require_once SERAVO_PLUGIN_SRC . 'modules/check-default-email.php';
    }

    /*
     * Optimize images on upload. Only logged in users make uploads.
     */
    if ( is_user_logged_in() ) {
      require_once SERAVO_PLUGIN_SRC . 'modules/optimize-on-upload.php';
    }

    /*
     * Sanitize a filename on upload to remove special characters.
     * Only logged in users make uploads.
     */
    if ( is_user_logged_in() ) {
      require_once SERAVO_PLUGIN_SRC . 'modules/sanitize-on-upload.php';
    }

    // OLD AJAX FILES
    require_once SERAVO_PLUGIN_SRC . 'lib/database-ajax.php';
    require_once SERAVO_PLUGIN_SRC . 'lib/domains-ajax.php';
    require_once SERAVO_PLUGIN_SRC . 'lib/domain-tables.php';
    require_once SERAVO_PLUGIN_SRC . 'lib/cruftfiles-ajax.php';
    require_once SERAVO_PLUGIN_SRC . 'lib/cruftplugins-ajax.php';
    require_once SERAVO_PLUGIN_SRC . 'lib/cruftthemes-ajax.php';
    require_once SERAVO_PLUGIN_SRC . 'lib/sitestatus-ajax.php';
    require_once SERAVO_PLUGIN_SRC . 'modules/check-site-health.php';

    /**
     * Seravo tool pages
     */

    // Site Status page
    new Site_Status();
    // Upkeep page
    new Upkeep();
    // Database page 
    new Database();
    // Backups page
    new Backups();
    // Security page
    new Security();
    // Domains page
    new Domains();
    // Logs page
    new Logs();

    if ( defined('SERAVO_PLUGIN_DEBUG') && SERAVO_PLUGIN_DEBUG ) {
      // Test page
      new TestPage();
    }

    /*
      * Notification with last WordPress login date and error count. This module handles its own
      * capability checks.
      */
    require_once SERAVO_PLUGIN_SRC . 'modules/login-notification.php';

    /*
      * Show notification stylish wp-admin dashboard widgets
      */
    require_once SERAVO_PLUGIN_SRC . 'modules/dashboard-widgets.php';

    // Load WP-CLI module 'wp seravo'
    if ( defined('WP_CLI') && WP_CLI ) {
      require_once SERAVO_PLUGIN_SRC . 'modules/wp-cli.php';
    }

    /*
     * Hide Users
     * Hides prespecified and given users from a WordPress page
     */
    require_once SERAVO_PLUGIN_SRC . 'modules/hide-users.php';

    /*
     * Add support for SVG images
     * Allow users to upload SVG
     */
    require_once SERAVO_PLUGIN_SRC . 'modules/svg-support.php';
  }
}

new Loader();
