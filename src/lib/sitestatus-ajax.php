<?php

namespace Seravo;

// Deny direct access to this file
if ( ! defined('ABSPATH') ) {
  die('Access denied!');
}

function seravo_report_wp_core_verify() {
  exec('wp core verify-checksums 2>&1', $output);
  array_unshift($output, '$ wp core verify-checksums');
  return $output;
}

function seravo_report_git_status() {
  exec('git -C /data/wordpress status', $output);

  if ( empty($output) ) {
    return array(
      'Git is not used on this site. To start using it,
      read our documentation for WordPress developers at
      <a href="https://seravo.com/docs/" target="_BLANK">seravo.com/docs</a>.',
    );
  }

  array_unshift($output, '$ git status');
  return $output;
}

function seravo_reset_shadow() {
  if ( isset($_POST['shadow']) && ! empty($_POST['shadow']) ) {
    $shadow = $_POST['shadow'];
    $output = array();
    // Check if the shadow is known
    foreach ( API::get_site_data('/shadows') as $data ) {
      if ( $data['name'] == $shadow ) {
        exec('wp-shadow-reset ' . $shadow . ' --force 2>&1', $output);
        echo json_encode($output);
        wp_die();
      }
    }
  }
  wp_die();
}

function seravo_ajax_site_status() {
  check_ajax_referer('seravo_site_status', 'nonce');

  switch ( sanitize_text_field($_REQUEST['section']) ) {

    case 'wp_core_verify':
      echo wp_json_encode(seravo_report_wp_core_verify());
      break;

    case 'git_status':
      echo wp_json_encode(seravo_report_git_status());
      break;

    case 'seravo_reset_shadow':
      seravo_reset_shadow();
      break;

    default:
      error_log('ERROR: Section ' . $_REQUEST['section'] . ' not defined');
      break;
  }

  wp_die();

}

function seravo_speed_test() {
  check_ajax_referer('seravo_site_status', 'nonce');
  // Take location for the speed test from the ajax call. If there is not one, use WP home
  $url = isset($_POST['location']) ? get_home_url() . '/' . trim($_POST['location']) : get_home_url();

  // Make sure there is one / at the end of the url
  $url = rtrim($url, '/') . '/';

  // use filter_var to make sure the resulting url is a valid url
  if ( ! filter_var($url, FILTER_VALIDATE_URL) ) {
    wp_send_json_error(__('Error: Invalid url', 'seravo'));
  }

  // Check whether to test cached version or not. Default not.
  $cached = isset($_POST['cached']) && $_POST['cached'] === 'true';

  // Prepare curl settings which are same for all requests
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); // equals the command line -k option

  if ( ! $cached ) {
    curl_setopt($ch, CURLOPT_HTTPHEADER, array( 'Pragma: no-cache' ));
  }

  $response = curl_exec($ch);
  $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  if ( curl_error($ch) || $httpcode !== 200 ) {
    $error = __('Error! HTTP response code: ', 'seravo');
    wp_send_json_error($error . $httpcode);
  }
  $curl_info_arr = curl_getinfo($ch);

  $result= array(
    $curl_info_arr['total_time'],
    $curl_info_arr['namelookup_time'],
    $curl_info_arr['connect_time'],
    $curl_info_arr['pretransfer_time'],
    $curl_info_arr['starttransfer_time'],
  );

  // abort if total duration already over 120 seconds?

  wp_send_json_success($result);
  wp_die();
}
