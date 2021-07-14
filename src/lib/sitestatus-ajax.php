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

function seravo_report_redis_info() {
  $redis = new \Redis();
  $redis->connect('127.0.0.1', 6379);
  $stats = $redis->info('stats');

  return array(
    $stats['expired_keys'],
    $stats['evicted_keys'],
    $stats['keyspace_hits'],
    $stats['keyspace_misses'],
  );
}

function seravo_enable_object_cache() {
  $object_cache_url = 'https://raw.githubusercontent.com/Seravo/wordpress/master/htdocs/wp-content/object-cache.php';
  $object_cache_path = '/data/wordpress/htdocs/wp-content/object-cache.php';
  $result = array();

  // Remove all possible object-cache.php.* files
  foreach ( glob($object_cache_path . '.*') as $file ) {
    unlink($file);
  }

  // Get the newest file and write it
  $object_cache_content = file_get_contents($object_cache_url);
  $object_cache_file = fopen($object_cache_path, 'w');
  $write_object_cache = fwrite($object_cache_file, $object_cache_content);
  fclose($object_cache_file);

  $result['success'] = $write_object_cache && $object_cache_content;

  return $result;
}

function seravo_report_longterm_cache_stats() {
  $access_logs = glob('/data/slog/*_total-access.log');

  $hit = 0;
  $miss = 0;
  $stale = 0;
  $bypass = 0;

  foreach ( $access_logs as $access_log ) {
    $file = fopen($access_log, 'r');
    if ( $file ) {
      while ( ! feof($file) ) {
        $line = fgets($file);
        // " is needed to match the log file
        if ( strpos($line, '" HIT') ) {
          ++$hit;
        } elseif ( strpos($line, '" MISS') ) {
          ++$miss;
        } elseif ( strpos($line, '" STALE') ) {
          ++$stale;
        } elseif ( strpos($line, '" BYPASS') ) {
          ++$bypass;
        }
      }
    }
  }

  return array(
    $hit,
    $miss,
    $stale,
    $bypass,
  );
}

function seravo_report_front_cache_status() {
  exec('wp-check-http-cache ' . get_site_url(), $output);
  array_unshift($output, '$ wp-check-http-cache ' . get_site_url());

  return array(
    'success' => strpos(implode("\n", $output), "\nSUCCESS: ") == true,
    'test_result' => $output,
  );
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

    case 'redis_info':
      echo wp_json_encode(seravo_report_redis_info());
      break;

    case 'object_cache':
      echo wp_json_encode(seravo_enable_object_cache());
      break;

    case 'longterm_cache':
      echo wp_json_encode(seravo_report_longterm_cache_stats());
      break;

    case 'front_cache_status':
      echo wp_json_encode(seravo_report_front_cache_status());
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
