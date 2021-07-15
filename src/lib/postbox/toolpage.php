<?php

namespace Seravo\Postbox;

/**
 * Class Toolpage
 *
 * Toolpage is a simple way to handle a postbox screen as
 * it takes care of the common features like nonces.
 */
abstract class Toolpage {

  /**
   * @var string Admin screen id where the page should be displayed in.
   */
  private $screen;
  /**
   * @var string Slug name to refer to this page.
   */
  private $slug;
  /**
   * @var string The text to be displayed in the title tags of the page and menu.
   */
  private $title;
  /**
   * TODO: Check if this is used for wrong purpose in Seravo Plugin!
   * @var string The position in the menu order this item should appear
   */
  private $position;

  /**
   * @var \Seravo\Postbox\Postbox[] Postboxes registered on the page.
   */
  private $postboxes = array();

  /**
   * @var \Seravo\Postbox\Requirements Requirements for this page.
   */
  private $requirements;

  public abstract function init_page();

  public abstract function set_requirements(Requirements $requirements);

  /**
   * Constructor for Toolpage. Will be called on new instance.
   * @param string $screen Admin screen id where the page should be displayed in.
   */
  public function __construct( $title, $screen, $slug, $position ) {
    $this->title = $title;
    $this->screen = $screen;
    $this->slug = $slug;
    $this->position = $position;

    $this->requirements = new Requirements();
    $this->set_requirements($this->requirements);

    if ( ! $this->is_allowed() ) {
      return;
    }

    $this->init_page();
    $this->register_page();

    add_action('admin_menu', function() {
      $this->register_submenu();
    });
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
        );
        wp_localize_script('seravo_ajax', 'seravo_charts_l10n', $charts_l10n);
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

  public function is_allowed() {
    if ( ! $this->requirements->is_allowed() ) {
      return false;
    }

    if ( getenv('CONTAINER') === false ) {
      // Not Seravo environment
      return false;
    }

    return (bool) apply_filters('seravo_show_' . $this->slug, true);
  }

  /**
   * Register the page to be rendered. This should be called once
   * the page is ready and all the postboxes are added.
   * @param \Seravo\Postbox\Postbox $postbox
   */
  private function register_page() {
    foreach ( $this->postboxes as $postbox ) {
      if ( $postbox->_is_allowed() ) {
        seravo_add_postbox($this->screen, $postbox);
      }
    }
  }

  private function register_submenu() {
    add_submenu_page(
      'tools.php',
      $this->title,
      $this->title,
      'manage_options',
      $this->slug,
      $this->position,
    );
  }

}
