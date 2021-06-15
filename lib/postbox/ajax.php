<?php
/**
 * File for Seravo AJAX handling.
 */

namespace Seravo\Postbox;

// Deny direct access to this file
if ( ! defined('ABSPATH') ) {
  die('Access denied!');
}

if ( ! class_exists('Ajax_Handler') ) {
  class Ajax_Handler {

    /**
     * @var string String for transient key to be prefixed with.
     */
    public const CACHE_KEY_PREFIX = 'seravo_ajax_';
    /**
     * @var string String for transient key to be suffixed with.
     */
    public const CACHE_KEY_SUFFIX = '_data';


    /**
     * @var string|null Unique id/slug of the postbox handler is part of.
     */
    private $id;
    /**
     * @var string      Unique section inside the postbox.
     */
    private $section;
    /**
     * @var string|null WordPress nonce for the page.
     */
    private $ajax_nonce;


    /**
     * @var array|null Function to be called on AJAX call.
     */
    private $ajax_func;
    /**
     * @var array|null Function to be called on AJAX component render.
     */
    private $build_func;
    /**
     * @var int|null   Seconds to cache data returned by $ajax_func.
     */
    private $data_cache_time;

    /**
     * Constructor for Ajax_Handler. Will be called on new instance.
     * @param string $section Unique section inside the postbox.
     */
    public function __construct( $section ) {
      $this->section = $section;
    }

    /**
     * Initialize the Ajax handler. This will be call
     * by the postbox once the page is ready.
     * @param string $id    Unique id/slug of the postbox.
     * @param string $nonce Name of WordPress nonce for the page.
     */
    public function init( $id, $nonce ) {
      $this->id = $id;
      $this->ajax_nonce = $nonce;

      add_action(
        'wp_ajax_seravo_ajax_' . $this->id,
        function() {
          return $this->_ajax_handler();
        }
      );
    }

    /**
     * Set the AJAX function for the handler. The function will be
     * called on AJAX calls here. AJAX function should return an Ajax_Response.
     * @param array $ajax_func Function to be called on AJAX call.
     * @param int   $cache_time Seconds to cache data for (default is 0).
     */
    public function set_ajax_func( $ajax_func, $cache_time = 0 ) {
      $this->ajax_func = $ajax_func;
      $this->data_cache_time = $cache_time;
    }

    /**
     * Set the optional build function for the handler.
     * The function will be called on get_component() calls.
     * @param array $build_func Function to be called on AJAX component render.
     */
    public function set_build_func( $build_func ) {
      $this->build_func = $build_func;
    }

    /**
     * Set the time data returned by AJAX function
     * is cached in transient for.
     * @param int $cache_time Seconds to cache data for (default is 0).
     */
    public function set_cache_time( $cache_time ) {
      $this->data_cache_time = $cache_time;
    }

    /**
     * Get component this AJAX handler needs to function. Calls
     * $build_func to build the component if one exists.
     * @return \Seravo\Postbox\Component Component for this AJAX handler.
     */
    public function get_component() {
      $component = new Component();

      if ( $this->build_func !== null ) {
        \call_user_func($this->build_func, $component, $this->section);
      }

      return $component;
    }

    /**
     * This function will be called by WordPress
     * if AJAX call is made here. Either calls $ajax_func
     * or responds with error on invalid requests.
     *
     * Caching and exceptions are taken care of here.
     */
    public function _ajax_handler() {
      check_ajax_referer($this->ajax_nonce, 'nonce');

      if ( ! isset($_REQUEST['section']) ) {
        // There must always be a section
        Ajax_Response::invalid_request_response()->send();
      }

      if ( $_REQUEST['section'] !== $this->section ) {
        // This request doesn't concern us
        return;
      }

      $cache_key = self::CACHE_KEY_PREFIX . $this->section . self::CACHE_KEY_SUFFIX;

      $response = null;

      try {
        // Check if we should be using transients
        if ( $this->data_cache_time > 0 ) {
          $response = \get_transient($cache_key);
          if ( false === $response ) {
            // The data was not cached, call data_func
            $response = \call_user_func($this->ajax_func, $this->section);
            if ( null !== $response ) {
              // Cache new result unless it's null
              \set_transient($cache_key, $response, $this->data_cache_time);
            }
          }
        } else {
          // Not using cache
          $response = \call_user_func($this->ajax_func, $this->section);
        }
      } catch ( \Exception $exception ) {
        error_log('### Seravo Plugin experienced an error!');
        error_log('### Please report this on GitHub (https://github.com/Seravo/seravo-plugin) with following:');
        error_log($exception);

        $response = Ajax_Response::exception_response();
      }

      if ( $response !== null ) {
        // We got a valid response
        $response->send();
      }

      Ajax_Response::unknown_error_response()->send();
    }

  }
}

if ( ! class_exists('Ajax_Response') ) {
  class Ajax_Response {

    /**
     * @var bool[]|string[]|mixed[]|mixed Data to respond with.
     */
    private $data = array();

    public function __construct() {
      $this->data['success'] = false;
    }

    /**
     * Set whether the request was succesful or not.
     * @param bool $is_success Value for 'success' field.
     */
    public function is_success( $is_success ) {
      $this->data['success'] = $is_success;
    }

    /**
     * Set error message in 'error' field in case there was one.
     * @param string $error Error to be shown for user.
     */
    public function set_error( $error ) {
      $this->data['error'] = $error;
    }

    /**
     * Set the data to be responded with. Data is merged with
     * success and error fields.
     * @param array $data The response data.
     */
    public function set_data( $data ) {
      $this->data = array_merge($this->data, $data);
    }

    /**
     * Set raw response data overwriting all current fields.
     * @param mixed $response Raw response that won't be tampered with.
     */
    public function set_raw_response( $response ) {
      $this->data = $response;
    }

    /**
     * Jsonify the response data.
     * @return string The response data as JSON.
     */
    public function to_json() {
      $json = json_encode($this->data);

      if ( false === $json ) {
        $json = self::unknown_error_response()->to_json();
      }

      return $json;
    }

    /**
     * Send the response. No coming back from here.
     */
    public function send() {
      echo $this->to_json();
      wp_die();
    }

    /**
     * Get invalid request response that's supposed to be send for invalid requests.
     * @return \Seravo\Postbox\Ajax_Response Invalid request response.
     */
    public static function invalid_request_response() {
      $response = new Ajax_Response();
      $response->is_success(false);
      $response->set_error(__('Error: Your browser made an invalid request!', 'seravo'));
      return $response;
    }

    /**
     * Get unkown error response that's supposed to be send for unknown errors.
     * @return \Seravo\Postbox\Ajax_Response Unknown error response.
     */
    public static function unknown_error_response() {
      $response = new Ajax_Response();
      $response->is_success(false);
      $response->set_error(__('Error: Something went wrong! Please see the php-error.log', 'seravo'));
      return $response;
    }

    /**
     * Get exception response that's supposed to be send on AJAX function exceptions.
     * @return \Seravo\Postbox\Ajax_Response Exception response
     */
    public static function exception_response() {
      $response = new Ajax_Response();
      $response->is_success(false);
      $response->set_error(__("Error: Oups, this wasn't supposed to happen! Please see the php-error.log", 'seravo'));
      return $response;
    }

  }
}
