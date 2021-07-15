<?php
/*
 * Plugin name: Domains
 * Description: View and edit domains, DNS and mail forwards
 * Version: 1.1
 */

namespace Seravo;

class Domains {
  /**
   * @var \Seravo\Domains|null
   */
  public static $instance;

  public static $domains_table;
  public static $mails_table;

  /**
   * @return \Seravo\Domains|null
   */
  public static function load() {
    if ( is_null(self::$instance) ) {
      self::$instance = new Domains();
    }

    return self::$instance;
  }

  public function __construct() {
    \Seravo\Postbox\seravo_add_raw_postbox(
      'domains-management',
      __('Domains', 'seravo'),
      array( __CLASS__, 'render_domains_postbox' ),
      'tools_page_domains_page',
      'normal'
    );

    \Seravo\Postbox\seravo_add_raw_postbox(
      'mailforwards-management',
      __('Mails', 'seravo'),
      array( __CLASS__, 'render_mails_postbox' ),
      'tools_page_domains_page',
      'normal'
    );

    add_action('wp_ajax_seravo_ajax_domains', 'Seravo\seravo_ajax_domains');
    add_action('admin_enqueue_scripts', array( __CLASS__, 'register_scripts' ));

    add_thickbox();
  }

  public static function register_scripts( $page ) {

    if ( $page === 'tools_page_domains_page' ) {

      wp_enqueue_script('seravo_domains', SERAVO_PLUGIN_URL . 'js/domains.js', array( 'jquery' ), Helpers::seravo_plugin_version(), false);
      wp_enqueue_style('seravo_domains', SERAVO_PLUGIN_URL . 'style/domains.css', '', Helpers::seravo_plugin_version(), false);

      $loc_translation_domains = array(
        'ajaxurl'             => admin_url('admin-ajax.php'),
        'ajax_nonce'          => wp_create_nonce('seravo_domains'),
        'domains_load_failed' => __("Domains couldn't be loaded.", 'seravo'),
        'section_failed'      => __("Couldn't fetch the section", 'seravo'),
        'zone_update_no_data' => __('No data returned for the update request.', 'seravo'),
        'zone_update_failed'  => __('The zone update failed!', 'seravo'),
        'zone_update_success' => __('The zone was updated succesfully!', 'seravo'),
        'fetch_no_data'       => __('No data returned for the dns fetch.', 'seravo'),
        'fetch_failed'        => __('DNS fetch failed! Please refresh the page.', 'seravo'),
        'update_no_changes'   => __('The zone was updated without changes.', 'seravo'),
        'changing_primary'    => __('Changing the primary domain...', 'seravo'),
        'primary_failed'      => __('Error! Primary domain might not have been changed.', 'seravo'),
        'primary_no_sr'       => __('Primary domain was changed but was not taken in use yet.', 'seravo'),
        'no_forwards'         => __('This domain has no mail forwards', 'seravo'),
        'forwards_failed'     => __("Couldn't fetch mail forwards for this domain.", 'seravo'),
        'forwards_none'       => __('No forwards were found for this domain.', 'seravo'),
        'forwards_edit_fail'  => __('Error! The action might have failed.', 'seravo'),
        'forwards_no_source'  => __("Source field can't be empty.", 'seravo'),
        'continue_edit'       => __('Continue', 'seravo'),
        'forwards'            => __('Forwards', 'seravo'),
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
    <div id="domains-table-primary-modal" style="display:none;">
        <p id="primary-modal-text"><?php _e('Are you sure you want to change the primary domain?', 'seravo'); ?></p>
        <hr>
        <button class="button" id="primary-domain-proceed"><?php _e('Proceed', 'seravo'); ?></button>
        <button class="button" id="primary-domain-cancel"><?php _e('Cancel', 'seravo'); ?></button>
    </div>
    <?php
  }

  public static function render_mails_postbox() {
    ?>
    <div class="mail-table">
      <div class="mail-table-col">
        <p><?php _e('Mail forwards of the domains routed to this WordPress site are listed below. Fetch the forwards for a domain with the link below the domain name.', 'seravo'); ?></p>
        <div id="forwards-table-wrapper">
          <p id="forwards-table-spinner">
            <img src="/wp-admin/images/spinner.gif">
            <b><?php _e('Loading mail forwards...', 'seravo'); ?></b>
          </p>
        </div>
      </div>
      <div class="mail-table-col">
        <p><?php _e('Mailboxes assigned to this WordPress site are listed below.', 'seravo'); ?></p>
        <hr style="margin: 15px 0px;">
        <b>Coming soon...</b>
      </div>
    </div>
    <?php
  }

}
