<?php
/*
 * Description: Class for checking some general settings and potential issues
 * regarding Seravo WordPress installation.
 */

namespace Seravo;

if ( ! defined('ABSPATH') ) {
    die('Access denied!');
}

if ( ! class_exists('Site_Health') ) {
  class Site_Health {
    /**
     * @var string[]|mixed[]|int[]|null
     */
    private static $potential_issues;
    /**
     * @var string[]|mixed[]|int[]|null
     */
    private static $no_issues;
    /**
     * @var string[]
     */
    private static $bad_plugins = array(
      'https-domain-alias',
      'wp-palvelu-plugin',
    );

    /**
     * Helper method for the class to execute given command & wrapping the return value
     * automatically as result array.
     */
    private static function exec_command( $command ) {
        $output = array();
        exec($command, $output);

        return $output;
    }

    /**
     * Check siteurl & home HTTPS usage status
     * Logic is from check-https-module
     */
    private static function check_https() {
      $siteurl = get_option('siteurl');
      $home = get_option('home');
      $https_tooltip = __('Read more about HTTPS on our <a href="https://seravo.com/blog/https-is-not-optional/" target="_blank">blog</a>', 'seravo');

      if ( strpos($siteurl, 'https') !== 0 || strpos($home, 'https') !== 0 ) {
        self::$potential_issues[__('HTTPS is disabled', 'seravo')] = $https_tooltip;
      } else {
        self::$no_issues[__('HTTPS is enabled', 'seravo')] = $https_tooltip;
      }
    }

    /**
     * Check that some recaptcha plugin is installed and active on WordPress.
     */
    private static function check_recaptcha() {
      $output = self::exec_command('wp plugin list');
      $captcha_found = false;
      $captcha_tooltip = __('Recaptcha is recommended as it can help protect your site from spam and abuse.', 'seravo');

      foreach ( $output as $plugin ) {
        // check that captcha is found and it's not inactive
        if ( strpos($plugin, 'captcha') && strpos($plugin, 'inactive') === false ) {
          self::$no_issues[__('Recaptcha is enabled', 'seravo')] = $captcha_tooltip;
          $captcha_found = true;
          break;
        }
      }

      if ( ! $captcha_found ) {
        self::$potential_issues[__('Recaptcha is disabled', 'seravo')] = $captcha_tooltip;
      }
    }

    /**
     * Count the inactive themes.
     */
    private static function check_inactive_themes() {
      $output = self::exec_command('wp theme list');
      $inactive_themes = 0;
      $theme_tooltip = __('It is recommended to remove inactive themes.', 'seravo');

      foreach ( $output as $line ) {
        if ( strpos($line, 'inactive') ) {
          ++$inactive_themes;
        }
      }

      if ( $inactive_themes > 0 ) {
        /* translators:
        * %1$s number of inactive themes
        */
        $themes_msg = wp_sprintf(_n('Found %1$s inactive theme', 'Found %1$s inactive themes', $inactive_themes, 'seravo'), number_format_i18n($inactive_themes));
        self::$potential_issues[$themes_msg] = $theme_tooltip;
      } else {
        self::$no_issues[__('No inactive themes', 'seravo')] = $theme_tooltip;
      }
    }

    /**
     * Check potential bad and deprecated plugins specified by @bad_plugins array.
     */
    private static function check_plugins() {
      // check inactive plugins and all plugin related issues
      $output = self::exec_command('wp plugin list');
      $inactive_plugins = 0;
      $bad_plugins_found = 0;
      $plugin_tooltip = __('It is recommended to remove inactive plugins and features.', 'seravo');
      $deprecated_tooltip = __('Deprecated plugins and features are obsolete and should no longer be used.', 'seravo');

      foreach ( $output as $line ) {

        if ( strpos($line, 'inactive') ) {
          ++$inactive_plugins;
        }

        foreach ( self::$bad_plugins as $plugin ) {
          if ( strpos($line, $plugin) !== false ) {
            $error_msg = '<b>' . $plugin . '</b> ' . __('is deprecated');
            self::$potential_issues[$error_msg] = $deprecated_tooltip;
            ++$bad_plugins_found;
          }
        }
      }

      if ( $bad_plugins_found === 0 ) {
        self::$no_issues[__('No deprecated features or plugins', 'seravo')] = $deprecated_tooltip;
      }

      if ( $inactive_plugins > 0 ) {
        /* translators:
        * %1$s number of inactive plugins
        */
        $plugins_msg = wp_sprintf(_n('Found %1$s inactive plugin', 'Found %1$s inactive plugins', $inactive_plugins, 'seravo'), number_format_i18n($inactive_plugins));
        self::$potential_issues[$plugins_msg] = $plugin_tooltip;
      } else {
        self::$no_issues[__('No inactive plugins', 'seravo')] = $plugin_tooltip;
      }
    }

    /**
     * Fetch the PHP error count by using the error count of Login_Notifications module.
     */
    private static function check_php_errors() {
      $php_info = '<a href="' . get_option('siteurl') . '/wp-admin/tools.php?page=logs_page&logfile=php-error.log" target="_blank">php-error.log</a>';
      $php_errors = Login_Notifications::retrieve_error_count();
      $error_tooltip = __('PHP related errors are usually a sign of something being broken on the code.', 'seravo');

      if ( $php_errors > 0 ) {
        /* translators:
        * %1$s number of errors in the log
        * %2$s url to php-error.log */
        $php_errors_msg = wp_sprintf(_n('%1$s error on %2$s', '%1$s errors on %2$s', $php_errors, 'seravo'), number_format_i18n($php_errors), $php_info);
        self::$potential_issues[$php_errors_msg] = $error_tooltip;
      } else {
        self::$no_issues[__('No php errors on log', 'seravo')] = $error_tooltip;
      }
    }

    /**
     * Execute command wp-test and wrap up whether it runs successfully or not.
     */
    private static function check_wp_test() {
      exec('wp-test', $output, $return_variable);
      $wp_test_tooltip = __('<code>wp-test</code> checks if the site works normally. It also checks whether automatic updates can continue.', 'seravo');

      if ( $return_variable === 0 ) {
        self::$no_issues[__('Command <code>wp-test</code> runs successfully', 'seravo')] = $wp_test_tooltip;
      } else {
        self::$potential_issues[__('Command <code>wp-test</code> fails', 'seravo')] = $wp_test_tooltip;
      }
    }

    /**
     * Run the test set and return results.
     * @return array<int, array<string, int>>
     */
    public static function check_site_status() {
      self::$potential_issues = array();
      self::$no_issues = array();

      self::check_https();
      self::check_recaptcha();

      self::check_inactive_themes();
      self::check_plugins();

      self::check_php_errors();
      self::check_wp_test();

      self::$potential_issues['length'] = count(self::$potential_issues);
      self::$no_issues['length'] = count(self::$no_issues);
      return array( self::$potential_issues, self::$no_issues );
    }
  }
}

