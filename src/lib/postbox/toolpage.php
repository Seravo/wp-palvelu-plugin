<?php

namespace Seravo\Postbox;

/**
 * Class Toolpage
 *
 * Toolpage is a simple way to handle a postbox screen as
 * it takes care of the common features like nonces.
 */
class Toolpage {

  /**
   * @var string Admin screen id where the page should be displayed in.
   */
  private $screen;

  /**
   * @var \Seravo\Postbox\Postbox[] Postboxes registered on the page.
   */
  private $postboxes = array();

  /**
   * Constructor for Toolpage. Will be called on new instance.
   * @param string $screen Admin screen id where the page should be displayed in.
   */
  public function __construct( $screen = '' ) {
    $this->screen = $screen;
  }

  /**
   * Enables AJAX features for this page. This must be called
   * on the page if there's even a single postbox using AJAX.
   */
  public function enable_ajax() {
    add_action(
      'admin_enqueue_scripts',
      function( $page ) {
        if ( $page !== $this->screen ) {
          return;
        }

        wp_enqueue_script('seravo-ajax', SERAVO_PLUGIN_URL . 'js/lib/ajax/seravo-ajax.js', array( 'jquery' ), \Seravo\Helpers::seravo_plugin_version(), false);
        wp_enqueue_script('seravo-ajax-handler', SERAVO_PLUGIN_URL . 'js/lib/ajax/ajax-handler.js', array( 'jquery' ), \Seravo\Helpers::seravo_plugin_version(), true);

        $ajax_l10n = array(
          'ajax_url' => admin_url('admin-ajax.php'),
          'server_invalid_response' => __('Error: Something unexpected happened! Server responded with invalid data.', 'seravo'),
          'server_timeout' => __("Error: Request timeout! Server didn't respond in time.", 'seravo'),
          'server_error' => __("Error: Oups, this wasn't supposed to happen! Please see the php-error.log.", 'seravo'),
          'show_more' => __('Show more', 'seravo'),
          'show_less' => __('Show less', 'seravo'),
        );
        wp_localize_script('seravo-ajax', 'seravo_ajax_l10n', $ajax_l10n);
      }
    );

    // Generates WordPress nonce for this page
    // and prints it as JavaScipt variable inside <SCRIPT>.
    add_action(
      'before_seravo_postboxes_' . $this->screen,
      function() {
        $nonce = wp_create_nonce($this->screen);
        echo "<script>SERAVO_AJAX_NONCE = \"{$nonce}\";</script>";
      }
    );
  }

  /**
   * Enables chart features for this page. This must be called
   * on the page if there's even a single postbox using charts.
   */
  public function enable_charts() {
    add_action(
      'admin_enqueue_scripts',
      function( $page ) {
        if ( $page !== $this->screen ) {
          return;
        }

        wp_enqueue_script('apexcharts-js', SERAVO_PLUGIN_URL . 'js/lib/apexcharts.js', '', \Seravo\Helpers::seravo_plugin_version(), true);
        wp_enqueue_script('seravo-charts', SERAVO_PLUGIN_URL . 'js/charts.js', array( 'jquery' ), \Seravo\Helpers::seravo_plugin_version(), false);

        $charts_l10n = array(
          'ajax_url' => admin_url('admin-ajax.php'),
          'show_more' => __('Show more', 'seravo'),
          'show_less' => __('Show less', 'seravo'),
          'used' => __('Used', 'seravo'),
          'available' => __('Available', 'seravo'),
          'keyspace_hits' => __('Keyspace hits', 'seravo'),
          'keyspace_misses' => __('Keyspace misses', 'seravo'),
          'hits' => __('Hits', 'seravo'),
          'misses' => __('Misses', 'seravo'),
          'stales' => __('Stales', 'seravo'),
        );
        wp_localize_script('seravo-charts', 'seravo_charts_l10n', $charts_l10n);
      }
    );
  }

  /**
   * Register postbox to be shown on the page. The same postbox
   * instance shouldn't be used elsewhere without clone.
   * @param \Seravo\Postbox\Postbox $postbox Postbox to be registered.
   */
  public function register_postbox( Postbox $postbox ) {
    $postbox->on_page_assign($this->screen);
    $this->postboxes[] = $postbox;
  }

  /**
   * Register the page to be rendered. This should be called once
   * the page is ready and all the postboxes are added.
   * @param \Seravo\Postbox\Postbox $postbox
   */
  public function register_page() {
    foreach ( $this->postboxes as $postbox ) {
      if ( $postbox->_is_allowed() ) {
        seravo_add_postbox($this->screen, $postbox);
      }
    }
  }

}
