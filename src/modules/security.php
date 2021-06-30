<?php
/*
 * Plugin name: Security settings
 * Description: Enable users to set the maximum security settings for their site.
 * Version: 1.0
 *
 * NOTE! For more fine-grained XML-RPC control, use https://wordpress.org/plugins/manage-xml-rpc/
 */

namespace Seravo;

use Seravo\Ajax;
use \Seravo\Postbox;
use Seravo\Postbox\Component;
use \Seravo\Postbox\Template;
use \Seravo\Postbox\Toolpage;
use \Seravo\Postbox\Requirements;

require_once SERAVO_PLUGIN_SRC . 'lib/security-ajax.php';
require_once SERAVO_PLUGIN_SRC . 'lib/cruftfiles-ajax.php';
require_once SERAVO_PLUGIN_SRC . 'lib/cruftplugins-ajax.php';
require_once SERAVO_PLUGIN_SRC . 'lib/cruftthemes-ajax.php';

// Deny direct access to this file
if ( ! defined('ABSPATH') ) {
  die('Access denied!');
}

if ( ! class_exists('Security') ) {
  class Security {

    public static function load() {
      add_action('admin_notices', array( __CLASS__, '_seravo_check_security_options' ));

      add_action('admin_init', array( __CLASS__, 'register_security_settings' ));
      add_action('admin_enqueue_scripts', array( __CLASS__, 'register_security_scripts' ));

      add_action('wp_ajax_seravo_security', 'Seravo\seravo_ajax_security');

      add_action('wp_ajax_seravo_check_passwords', 'seravo_ajax_check_passwords');

      // AJAX functionality for listing and deleting files
      add_action('wp_ajax_seravo_cruftfiles', 'Seravo\seravo_ajax_list_cruft_files');
      add_action('wp_ajax_seravo_delete_file', 'Seravo\seravo_ajax_delete_cruft_files');

      // AJAX functionality for listing and removing plugins
      add_action('wp_ajax_seravo_list_cruft_plugins', 'Seravo\seravo_ajax_list_cruft_plugins');
      add_action('wp_ajax_seravo_remove_plugins', 'Seravo\seravo_ajax_remove_plugins');

      // AJAX functionality for listing and removing themess
      add_action('wp_ajax_seravo_list_cruft_themes', 'Seravo\seravo_ajax_list_cruft_themes');
      add_action('wp_ajax_seravo_remove_themes', 'Seravo\seravo_ajax_remove_themes');

      $page = new Toolpage('tools_page_security_page');
      self::init_security_postboxes($page);

      $page->enable_ajax();
      $page->register_page();

      \Seravo\Postbox\seravo_add_raw_postbox(
        'security_info',
        __('Security', 'seravo'),
        array( __CLASS__, 'security_info_postbox' ),
        'tools_page_security_page',
        'normal'
      );

      \Seravo\Postbox\seravo_add_raw_postbox(
        'logins_info',
        __('Last successful logins', 'seravo'),
        array( __CLASS__, 'logins_info_postbox' ),
        'tools_page_security_page',
        'side'
      );

      \Seravo\Postbox\seravo_add_raw_postbox(
        'cruft-files',
        __('Cruft Files', 'seravo'),
        array( __CLASS__, 'cruftfiles_postbox' ),
        'tools_page_security_page',
        'column3'
      );

      \Seravo\Postbox\seravo_add_raw_postbox(
        'cruft-plugins',
        __('Unnecessary plugins', 'seravo'),
        array( __CLASS__, 'cruftplugins_postbox' ),
        'tools_page_security_page',
        'column4'
      );

      \Seravo\Postbox\seravo_add_raw_postbox(
        'cruft-themes',
        __('Unnecessary themes', 'seravo'),
        array( __CLASS__, 'cruftthemes_postbox' ),
        'tools_page_security_page',
        'column4'
      );

    }

    /**
     * Init postboxes on Security page.
     * @param Toolpage $page Page to init postboxes.
     */
    public static function init_security_postboxes( Toolpage $page ) {
      /**
       * Check passwords postbox (Beta)
       */
      $passwords = new Postbox\SimpleCommand('check-passwords');
      $passwords->set_title(__('Check passwords (Beta)', 'seravo'));
      $passwords->set_requirements(array( Requirements::CAN_BE_PRODUCTION => true ));
      $passwords->set_button_text(__('Run', 'seravo'));
      $passwords->set_spinner_text(__('Running the password check', 'seravo'));
      $passwords->set_command('wp-check-passwords');
      $passwords->add_paragraph(__('This tool can be used to run command <code>wp-check-passwords</code> which finds weak passwords from the users of the site. Note: This may fail, if there are many users.', 'seravo'));
      $page->register_postbox($passwords);
    }

    /**
     * Register scripts
     *
     * @param string $page hook name
     */
    public static function register_security_scripts( $page ) {
      wp_register_script('seravo_security', SERAVO_PLUGIN_URL . 'js/security.js', '', Helpers::seravo_plugin_version());
      wp_register_style('seravo_security', SERAVO_PLUGIN_URL . 'style/security.css', '', Helpers::seravo_plugin_version());

      wp_register_style('seravo_cruftfiles', SERAVO_PLUGIN_URL . 'style/cruftfiles.css', '', Helpers::seravo_plugin_version());
      wp_register_script('seravo_cruftfiles', SERAVO_PLUGIN_URL . 'js/cruftfiles.js', '', Helpers::seravo_plugin_version());
      wp_register_script('seravo_cruftplugins', SERAVO_PLUGIN_URL . 'js/cruftplugins.js', '', Helpers::seravo_plugin_version());
      wp_register_script('seravo_cruftthemes', SERAVO_PLUGIN_URL . 'js/cruftthemes.js', '', Helpers::seravo_plugin_version());

      if ( $page === 'tools_page_security_page' ) {
        wp_enqueue_script('seravo_security');
        wp_enqueue_style('seravo_security');
        wp_enqueue_style('seravo_cruftfiles');
        wp_enqueue_script('seravo_cruftfiles');
        wp_enqueue_script('seravo_cruftplugins');
        wp_enqueue_script('seravo_cruftthemes');

        $loc_translation_security = array(
          'ajaxurl'    => admin_url('admin-ajax.php'),
          'ajax_nonce' => wp_create_nonce('seravo_security'),
        );
        $loc_translation_files = array(
          'no_data'       => __('No data returned for the section.', 'seravo'),
          'confirm'       => __('Are you sure you want to proceed? Deleted files can not be recovered.', 'seravo'),
          'fail'          => __('Failed to load. Please try again.', 'seravo'),
          'no_cruftfiles' => __('Congratulations! You have do not have any unnecessary files around.', 'seravo'),
          'delete'        => __('Delete', 'seravo'),
          'bytes'         => __('b', 'seravo'),
          'mod_date'      => __('Last modified', 'seravo'),
          'select_all'    => __('Select all files', 'seravo'),
          'filesize'      => __('File size', 'seravo'),
          'ajaxurl'       => admin_url('admin-ajax.php'),
          'ajax_nonce'    => wp_create_nonce('seravo_cruftfiles'),
        );
        $loc_translation_plugins = array(
          'inactive'              => __('Inactive Plugins:', 'seravo'),
          'inactive_desc'         => __('These plugins are currently not in use. They can be removed to save disk storage space.', 'seravo'),
          'cache_plugins'         => __('Unnecessary Cache Plugins:', 'seravo'),
          'cache_plugins_desc'    => __('Your website is running on a server that does takes care of caching automatically. Any additional plugins that do caching will not improve the service.', 'seravo'),
          'security_plugins'      => __('Unnecessary Security Plugins:', 'seravo'),
          'security_plugins_desc' => __('Your website runs on a server that is designed to provide a high level of security. Any plugins providing additional security measures will likely just slow down your website.', 'seravo'),
          'db_plugins'            => __('Unnecessary Database Manipulation Plugins:', 'seravo'),
          'db_plugins_desc'       => __('These plugins may cause issues with your database.', 'seravo'),
          'backup_plugins'        => __('Unnecessary Backup Plugins:', 'seravo'),
          'backup_plugins_desc'   => __('Backups of your website are automatically run on the server on a daily basis. Any plugins creating additional backups are redundant and will unnecessesarily fill up your data storage space.', 'seravo'),
          'poor_security'         => __('Unsecure Plugins:', 'seravo'),
          'poor_security_desc'    => __('These plugins have known issues with security.', 'seravo'),
          'bad_code'              => __('Bad Code:', 'seravo'),
          'bad_code_desc'         => __('These plugins code are hard to differentiate from actual malicious codes.', 'seravo'),
          'foolish_plugins'       => __('Foolish Plugins:', 'seravo'),
          'foolish_plugins_desc'  => __('These plugins are known to do foolish things.', 'seravo'),
          'no_cruftplugins'       => __('All the plugins that were found are currently active and do not have any known issues.', 'seravo'),
          'cruftplugins'          => __('The following plugins were found and are suggested to be removed:', 'seravo'),
          'confirm'               => __('Are you sure you want to remove this plugin?', 'seravo'),
          'failure'               => __('Failed to remove plugin', 'seravo'),
          'ajaxurl'               => admin_url('admin-ajax.php'),
          'ajax_nonce'            => wp_create_nonce('seravo_cruftplugins'),
        );
        $loc_translation_themes = array(
          'isparentto'     => __('is parent to: ', 'seravo'),
          'confirm'        => __('Are you sure you want to remove this theme?', 'seravo'),
          'failure'        => __('Failed to remove some themes!', 'seravo'),
          'no_cruftthemes' => __('There are currently no unused themes on the website.', 'seravo'),
          'cruftthemes'    => __('The following themes are inactive and can be removed.', 'seravo'),
          'ajaxurl'        => admin_url('admin-ajax.php'),
          'ajax_nonce'     => wp_create_nonce('seravo_cruftthemes'),
        );
        wp_localize_script('seravo_security', 'seravo_security_loc', $loc_translation_security);
        wp_localize_script('seravo_cruftfiles', 'seravo_cruftfiles_loc', $loc_translation_files);
        wp_localize_script('seravo_cruftplugins', 'seravo_cruftplugins_loc', $loc_translation_plugins);
        wp_localize_script('seravo_cruftthemes', 'seravo_cruftthemes_loc', $loc_translation_themes);
      }

    }

    public static function register_security_settings() {
      add_settings_section(
        'seravo_security_settings',
        '', // Empty on purpose, postbox title is enough
        array( __CLASS__, 'security_settings_description' ),
        'tools_page_security_page'
      );

      /* Dummy settings that cannot be changed */
      add_settings_field(
        'seravo-automatic-backups',
        __('Automatic backups', 'seravo'),
        array( __CLASS__, 'seravo_security_checked_field' ),
        'tools_page_security_page',
        'seravo_security_settings'
      );

      add_settings_field(
        'seravo-security-updates',
        __('Quick security updates', 'seravo'),
        array( __CLASS__, 'seravo_security_checked_field' ),
        'tools_page_security_page',
        'seravo_security_settings'
      );

      add_settings_field(
        'seravo-malicious-code-monitoring',
        __('Monitoring of malicius code and database contents', 'seravo'),
        array( __CLASS__, 'seravo_security_checked_field' ),
        'tools_page_security_page',
        'seravo_security_settings'
      );

      add_settings_field(
        'seravo-dos-protection',
        __('Denial-of-service protection', 'seravo'),
        array( __CLASS__, 'seravo_security_checked_field' ),
        'tools_page_security_page',
        'seravo_security_settings'
      );

      add_settings_field(
        'seravo-brute-force-protection',
        __('Brute-force login protection', 'seravo'),
        array( __CLASS__, 'seravo_security_checked_field' ),
        'tools_page_security_page',
        'seravo_security_settings'
      );

      /* Real settings below */
      self::add_settings_field_with_desc(
        'seravo-disable-xml-rpc',
        __('Disable authenticated XML-RPC', 'seravo'),
        __("Prevent brute-force attempts via XML-RPC. Disables e.g. using the WordPress mobile app. Doesn't affect the Jetpack plugin as its IPs are whitelisted.", 'seravo'),
        array( __CLASS__, 'seravo_security_xmlrpc_field' ),
        'tools_page_security_page',
        'seravo_security_settings'
      );

      self::add_settings_field_with_desc(
        'seravo-disable-xml-rpc-all-methods',
        __('Completely disable XML-RPC', 'seravo'),
        __('Prevent XML-RPC from responding to any methods at all. Disables e.g. pingbacks.', 'seravo'),
        array( __CLASS__, 'seravo_security_xmlrpc_completely_field' ),
        'tools_page_security_page',
        'seravo_security_settings'
      );

      add_settings_field(
        'seravo-disable-json-user-enumeration',
        __('Disable WP-JSON user enumeration', 'seravo'),
        array( __CLASS__, 'seravo_security_json_user_enum_field' ),
        'tools_page_security_page',
        'seravo_security_settings'
      );

      add_settings_field(
        'seravo-disable-get-author-enumeration',
        __('Disable GET author enumeration', 'seravo'),
        array( __CLASS__, 'seravo_security_get_author_enum_field' ),
        'tools_page_security_page',
        'seravo_security_settings'
      );

      register_setting('seravo_security_settings', 'seravo-disable-xml-rpc');
      register_setting('seravo_security_settings', 'seravo-disable-xml-rpc-all-methods');
      register_setting('seravo_security_settings', 'seravo-disable-json-user-enumeration');
      register_setting('seravo_security_settings', 'seravo-disable-get-author-enumeration');
    }

    public static function _seravo_check_security_options() {
      $options = array(
        'seravo-disable-xml-rpc',
        'seravo-disable-xml-rpc-all-methods',
        'seravo-disable-json-user-enumeration',
        'seravo-disable-get-author-enumeration',
      );

      foreach ( $options as $option ) {
        if ( get_option($option) === false ) {
          ?>
          <div class="notice notice-error">
            <p>
              <?php
              printf(
                // translators: user's website url
                __('Please enable all possible <a href="%s/wp-admin/tools.php?page=security_page">security features</a>. Save settings even if no changes were made to get rid of this notice.', 'seravo'),
                esc_url(get_option('siteurl'))
              );
              ?>
            </p>
          </div>
          <?php
          break;
        }
      }
    }

    public static function security_settings_description() {
      $msg = __(
        'Seravo has security built-in. There are however a few extra measures
        that the site owner can choose to do if their site will not miss any functionality
        because of it.',
        'seravo'
      );
      echo '<p>' . $msg . '</p>';
    }

    public static function seravo_security_checked_field() {
      echo '<input type="checkbox" checked="on" disabled="disabled">';
    }

    public static function seravo_security_xmlrpc_field() {
      echo '<input type="checkbox" name="seravo-disable-xml-rpc" id="disable-xmlrpc" ' . checked('on', get_option('seravo-disable-xml-rpc'), false) . '>';
    }

    public static function seravo_security_xmlrpc_completely_field() {
      echo '<input type="checkbox" name="seravo-disable-xml-rpc-all-methods" id="complete-disable-xmlrpc" ' . checked('on', get_option('seravo-disable-xml-rpc-all-methods'), false) . '>';
    }

    public static function seravo_security_json_user_enum_field() {
      echo '<input type="checkbox" name="seravo-disable-json-user-enumeration" id="disable-json-user-enumaration" ' . checked('on', get_option('seravo-disable-json-user-enumeration'), false) . '>';
    }

    public static function seravo_security_get_author_enum_field() {
      echo '<input type="checkbox" name="seravo-disable-get-author-enumeration" id="disable-get-author-enumeration" ' . checked('on', get_option('seravo-disable-get-author-enumeration'), false) . '>';
    }

    public static function security_info_postbox() {
      settings_errors();
      echo '<form method="post" action="options.php">';
      settings_fields('seravo_security_settings');
      do_settings_sections('tools_page_security_page');
      submit_button(__('Save', 'seravo'), 'primary', 'btnSubmit');
      echo '</form>';
    }
    public static function logins_info_postbox() {
      ?>

      <div id="logins_info_loading">
        <img src="/wp-admin/images/spinner.gif">
      </div>

      <div id="logins_info"></div>

      <?php
    }

    public static function check_passwords_postbox() {
      ?>
      <p>
      <?php
      _e(
        'This tool can be used to run command <code>wp-check-passwords</code>
        which finds weak passwords from the users of the site. Note: This may fail, if
        there are many users.',
        'seravo'
      );
      ?>
      </p>
      <p>
      <button id='run_check_passwords' class='button'><?php _e('Run', 'seravo'); ?></button>
      </p>
      <div id="wp_check_passwords_loading">
        <img class="hidden" src="/wp-admin/images/spinner.gif">
      </div>
      <p id='wp_check_passwords'></p>
      <?php
    }

    public static function cruftfiles_postbox() {
      ?>
      <p id="cruftfiles_tool">
        <?php _e('Find and delete any extraneous and potentially harmful files taking up space in the file system. Note that not everything is necessarily safe to delete.', 'seravo'); ?>
      </p>
      <p>
      <div id="cruftfiles_status">
        <table>
          <tbody id="cruftfiles_entries">
          </tbody>
        </table>
        <div id="cruftfiles_status_loading">
          <?php _e('Searching for files...', 'seravo'); ?>
          <img src="/wp-admin/images/spinner.gif">
        </div>
      </div>
      </p>
      <?php
    }

    public static function cruftplugins_postbox() {
      ?>
      <p>
        <?php _e('Find and remove any plugins that are currently inactive or otherwise potentially harmful. For more information, please read our <a href="https://help.seravo.com/article/165-recommended-plugins" target="_BLANK">recommendations for plugins in our environment</a>.', 'seravo'); ?>
      </p>
      <p>
      <div id="cruftplugins_status">
        <div id="cruftplugins_status_loading">
          <?php _e('Searching for plugins...', 'seravo'); ?>
          <img src="/wp-admin/images/spinner.gif">
        </div>
        <!-- Filled by JS -->
        <div id="cruftplugins_status_loading" style="display: none;">
          <?php _e('Searching for files...', 'seravo'); ?>
          <img src="/wp-admin/images/spinner.gif">
        </div>
      </div>
      </p>
      <?php
    }

    public static function cruftthemes_postbox() {
      ?>
      <p>
        <?php _e('Find and remove themes that are inactive. For more information, please read our <a href="https://help.seravo.com/article/70-can-i-install-my-own-plugins-and-themes-on-the-website" target="_BLANK">documentation concerning themes and plugins</a>.', 'seravo'); ?>
      </p>
      <p>
      <div id="cruftthemes_status">
        <div id="cruftthemes_status_loading">
          <?php _e('Searching for themes...', 'seravo'); ?>
          <img src="/wp-admin/images/spinner.gif">
        </div>
      </div>
      </p>
      <?php
    }

    /**
     * $_POST['deletefile'] is either a string denoting only one file
     * or it can contain an array containing strings denoting files.
     */
    public static function ajax_delete_file() {
      check_ajax_referer('seravo_cruftfiles', 'nonce');
      if ( isset($_POST['deletefile']) && ! empty($_POST['deletefile']) ) {
        $files = $_POST['deletefile'];
        if ( is_string($files) ) {
          $files = array( $files );
        }
        if ( ! empty($files) ) {
          $result = array();
          $results = array();
          foreach ( $files as $file ) {
            $legit_cruft_files = get_transient('cruft_files_found'); // Check first that the given file or directory is legitimate
            if ( in_array($file, $legit_cruft_files, true) ) {
              $unlink_result = is_dir($file) ? self::rmdir_recursive($file, 0) : unlink($file);
              // else - Backwards compatible with old UI
              $result['success'] = (bool) $unlink_result;
              $result['filename'] = $file;
              $results[] = $result;
            }
          }
          echo json_encode($results);
        }
      }
      wp_die();
    }

    /**
     * @return bool|void
     */
    public static function rmdir_recursive( $dir, $recursive ) {
      foreach ( scandir($dir) as $file ) {
        if ( '.' === $file || '..' === $file ) {
          continue; // Skip current and upper level directories
        }
        if ( is_dir("{$dir}/{$file}") ) {
          rmdir_recursive("{$dir}/{$file}", 1);
        } else {
          unlink("{$dir}/{$file}");
        }
      }
      rmdir($dir);
      if ( $recursive == 0 ) {
        return true; // when not called recursively
      }
    }

    public static function add_settings_field_with_desc( $id, $title, $description, $callback, $page, $section = 'default', $args = array() ) {
      add_settings_field(
        $id,
        $title . '<br><i class="seravo_field_description">' . $description . '</i>',
        $callback,
        $page,
        $section,
        $args
      );
    }
  }
  Security::load();
}
