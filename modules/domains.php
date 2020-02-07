<?php
/*
 * Plugin name: Domains
 * Description: View and edit domains, DNS and mail forwards
 * Version: 1.1
 */

namespace Seravo;

require_once dirname(__FILE__) . '/../lib/domains-ajax.php';
require_once dirname(__FILE__) . '/../lib/domain-tables.php';

// Deny direct access to this file
if ( ! defined('ABSPATH') ) {
  die('Access denied!');
}

if ( ! class_exists('Domains') ) {
  class Domains {
    private $capability_required;

    public static $instance;

    public static $domains_table;
    public static $mails_table;

    public static function init() {
      if ( is_null(self::$instance) ) {
        self::$instance = new Domains();
      }

      return self::$instance;
    }

    public function __construct() {
      $this->capability_required = 'activate_plugins';

      if ( is_multisite() ) {
        $this->capability_required = 'manage_network';
      }

      seravo_add_postbox(
        'domains-management',
        __('Domains', 'seravo'),
        array( __CLASS__, 'render_domains_postbox' ),
        'tools_page_domains_page',
        'normal'
      );

      seravo_add_postbox(
        'mailforwards-management',
        __('Mails', 'seravo'),
        array( __CLASS__, 'render_mails_postbox' ),
        'tools_page_domains_page',
        'normal'
      );

      add_action('wp_ajax_seravo_ajax_domains', 'seravo_ajax_domains');
      add_action('admin_enqueue_scripts', array( __CLASS__, 'register_scripts' ));
    }

    public static function register_scripts( $page ) {

      if ( $page === 'tools_page_domains_page' ) {

        wp_enqueue_script('seravo_domains', plugins_url('../js/domains.js', __FILE__), array( 'jquery' ), Helpers::seravo_plugin_version(), false);
        wp_enqueue_style('seravo_domains', plugins_url('../style/domains.css', __FILE__), '', Helpers::seravo_plugin_version(), false);

        $loc_translation_domains = array(
          'ajaxurl'             => admin_url('admin-ajax.php'),
          'ajax_nonce'          => wp_create_nonce('seravo_domains'),
          'domains_load_failed' => __('Domains couldn\'t be loaded.', 'seravo'),
          'section_failed'      => __('Couldn\'t fetch the section', 'seravo'),
          'zone_update_no_data' => __('No data returned for the update request.', 'seravo'),
          'zone_update_failed'  => __('The zone update failed!', 'seravo'),
          'zone_update_success' => __('The zone was updated succesfully!', 'seravo'),
          'zone_modifications'  => __('The following modifications were done for the zone: ', 'seravo'),
          'fetch_no_data'       => __('No data returned for the dns fetch.', 'seravo'),
          'fetch_failed'        => __('DNS fetch failed! Please refresh the page.', 'seravo'),
        );

        wp_localize_script('seravo_domains', 'seravo_domains_loc', $loc_translation_domains);
      }

    }

    public static function render_domains_postbox() {
      ?>
      <p><?php _e('Domains routed to this WordPress site are listed below.', 'seravo'); ?></p>
      <div id="domains-table-wrapper">
        <p id="domains-table-spinner">
          <img src="/wp-admin/images/spinner.gif">
          <b><?php _e('Loading domains...', 'seravo'); ?></b>
        </p>
      </div>
      <?php
    }

    public static function render_mails_postbox() {
      echo 'Coming soon...';
    }

  }

  /* Only show domains page in production */
  if ( Helpers::is_production() ) {
    Domains::init();
  }
}
