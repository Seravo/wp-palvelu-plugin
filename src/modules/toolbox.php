<?php

namespace Seravo;

if ( ! defined('ABSPATH') ) {
  die('Access denied!');
}

if ( ! class_exists('SeravoToolbox') ) {
  class SeravoToolbox {
    public static function load() {
      add_action('admin_menu', array( __CLASS__, 'register_toolbox_subpages' ));
    }

    public static function register_toolbox_subpages() {
      add_submenu_page(
        'tools.php',
        __('Site Status', 'seravo'),
        __('Site Status', 'seravo'),
        'manage_options',
        'site_status_page',
        'Seravo\Postbox\seravo_postboxes_page'
      );

      add_submenu_page(
        'tools.php',
        __('Upkeep', 'seravo'),
        __('Upkeep', 'seravo'),
        'manage_options',
        'upkeep_page',
        'Seravo\Postbox\seravo_two_column_postboxes_page'
      );

      add_submenu_page(
        'tools.php',
        __('Database', 'seravo'),
        __('Database', 'seravo'),
        'manage_options',
        'database_page',
        'Seravo\Postbox\seravo_postboxes_page'
      );

      // Only show the menu item on systems where wp-backup is available
      if ( exec('which wp-backup-status') !== '' ) {
        add_submenu_page(
          'tools.php',
          __('Backups', 'seravo'),
          __('Backups', 'seravo'),
          'manage_options',
          'backups_page',
          'Seravo\Postbox\seravo_two_column_postboxes_page'
        );
      }

      add_submenu_page(
        'tools.php',
        __('Security', 'seravo'),
        __('Security', 'seravo'),
        'manage_options',
        'security_page',
        'Seravo\Postbox\seravo_postboxes_page'
      );

      if ( getenv('WP_ENV') === 'production' ) {
        add_submenu_page(
          'tools.php',
          __('Domains', 'seravo'),
          __('Domains', 'seravo'),
          'manage_options',
          'domains_page',
          'Seravo\Postbox\seravo_wide_column_postboxes_page'
        );
      }

      add_submenu_page(
        'tools.php',
        __('Logs', 'seravo'),
        __('Logs', 'seravo'),
        'manage_options',
        'logs_page',
        array( Logs::load(), 'render_tools_page' )
      );

      if ( defined('SERAVO_PLUGIN_DEBUG') && SERAVO_PLUGIN_DEBUG ) {
        add_submenu_page(
          'tools.php',
          __('Test-page', 'seravo'),
          __('Test-page', 'seravo'),
          'manage_options',
          'test_page',
          'Seravo\Postbox\seravo_two_column_postboxes_page'
        );
      }
    }
  }

  SeravoToolbox::load();
}
