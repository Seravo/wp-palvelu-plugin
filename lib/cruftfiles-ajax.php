<?php

namespace Seravo;

// Deny direct access to this file
if ( ! defined('ABSPATH') ) {
  die('Access denied!');
}

function seravo_add_file_information( $file ) {
  if ( $file !== Helpers::sanitize_full_path($file) ) {
    return array(
      'size' => 0,
      'mod_date' => null,
      'filename' => $file,
    );
  }

  exec('du ' . $file . ' -h --time', $output);
  $size = explode("\t", $output[0]);

  return array(
    'size' => $size[0],
    'mod_date' => $size[1],
    'filename' => $file,
  );
}

function seravo_find_cruft_file( $name ) {
  $user = getenv('WP_USER');
  exec('find /data/wordpress -name ' . $name, $data_files);
  exec('find /home/' . $user . ' -maxdepth 1 -name ' . $name, $home_files);
  return array_merge($data_files, $home_files);
}

function seravo_find_cruft_dir( $name ) {
  $user = getenv('WP_USER');
  exec('find /data/wordpress -type d -name ' . $name, $data_dirs);
  exec('find /home/' . $user . ' -maxdepth 1 -type d -name ' . $name, $home_dirs);
  return array_merge($data_dirs, $home_dirs);
}

function seravo_only_has_whitelisted_content( $dir, $wl_files, $wl_dirs ) {
  exec('find ' . $dir, $content);
  foreach ( $content as $path ) {
    if ( $path !== $dir ) {
      if ( (! in_array($path, $wl_files)) && (! in_array($path, $wl_dirs)) ) {
        // The file was not whitelisted
        return false;
      }
    }
  }
  return true;
}

function seravo_find_cruft_core() {
  $output = array();
  $handle = popen('wp core verify-checksums 2>&1', 'r');
  $temp = stream_get_contents($handle);
  pclose($handle);
  // Lines beginning with: "Warning: File should not exist: "
  $temp = explode("\n", $temp);
  foreach ( $temp as $line ) {
    if ( strpos($line, 'Warning: File should not exist: ') !== false ) {
      $line = '/data/wordpress/htdocs/wordpress/' . substr($line, 32);
      $output[] = $line;
    }
  }
  return $output;
}
function seravo_list_known_cruft_file( $name ) {
  exec('ls ' . $name, $output);
  return $output;
}

function seravo_list_known_cruft_dir( $name ) {
  exec('ls -d ' . $name, $output);
  return $output;
}

function seravo_rmdir_recursive( $dir, $recursive ) {
  foreach ( scandir($dir) as $file ) {
    if ( '.' === $file || '..' === $file ) {
      continue; // Skip current and upper level directories
    }
    if ( is_dir("{$dir}/{$file}") ) {
      seravo_rmdir_recursive("{$dir}/{$file}", 1);
    } else {
      unlink("{$dir}/{$file}");
    }
  }
  rmdir($dir);
  if ( $recursive === 0 ) {
    return true; // when not called recursively
  }
}

function seravo_ajax_list_cruft_files() {
  check_ajax_referer('seravo_cruftfiles', 'nonce');
  if ( $_REQUEST['section'] == 'cruftfiles_status' ) {
      // List of known types of cruft files
      $list_files = array(
        '*.sql',
        '.hhvm.hhbc',
        '*.wpress',
        'core',
        '*.bak',
        '*deleteme*',
        '*.deactivate',
        '.DS_Store',
        '*.tmp',
        '*.old',
      );
      // List of known cruft directories
      $list_dirs = array(
        'siirto',
        '*palautus*',
        'before*',
        'vanha',
        '*.old',
        '*-old',
        '*-OLD',
        '*-copy',
        '*-2',
        '*.bak',
        'migration',
        '*_BAK',
        '_mu-plugins',
        '*.orig',
        '-backup',
        '*.backup',
        '*deleteme*',
        getenv('WP_USER') . '_20*',
      );
      $list_known_files = array(
        '/data/wordpress/htdocs/wp-content/.htaccess',
        '/data/wordpress/htdocs/wp-content/db.php',
        '/data/wordpress/htdocs/wp-content/object-cache.php.off',
        '/data/wordpress/htdocs/wp-content/wp-login.log',
        '/data/wordpress/htdocs/wp-content/adminer.php',
        '/data/wordpress/htdocs/wp-content/advanced-cache.php',
        '/data/wordpress/htdocs/wp-content/._index.php',
        '/data/wordpress/htdocs/wp-content/siteground-migrator.log',
        '/data/wordpress/htdocs/wp-content/ari-adminer-config.php',
      );
      $list_known_dirs = array(
        '/data/wordpress/htdocs/wp-content/plugins/all-in-one-wp-migration/storage',
        '/data/wordpress/htdocs/wp-content/ai1wm-backups',
        '/data/wordpress/htdocs/wp-content/uploads/backupbuddy_backups',
        '/data/wordpress/htdocs/wp-content/updraft',
        '/data/wordpress/htdocs/wp-content/._plugins',
        '/data/wordpress/htdocs/wp-content/._themes',
        '/data/wordpress/htdocs/wp-content/wflogs',
      );
      $white_list_dirs = array(
        '/data/wordpress/htdocs/wp-content/plugins',
        '/data/wordpress/htdocs/wp-content/mu-plugins',
        '/data/wordpress/htdocs/wp-content/themes',
        '/data/wordpress/node_modules',
      );
      $white_list_files = array(
        '/data/wordpress/vagrant-base.sql',
        '/data/wordpress/htdocs/wp-content/plugins/all-in-one-wp-migration/storage/index.php',
        '/data/wordpress/htdocs/wp-content/plugins/all-in-one-wp-migration/storage/index.html',
        '/data/wordpress/htdocs/wp-content/ai1wm-backups/index.html',
        '/data/wordpress/htdocs/wp-content/ai1wm-backups/index.php',
        '/data/wordpress/htdocs/wp-content/ai1wm-backups/.htaccess',
        '/data/wordpress/htdocs/wp-content/ai1wm-backups/web.config',
      );
      $crufts = array();
      $crufts = array_merge($crufts, seravo_find_cruft_core());
      foreach ( $list_files as $filename ) {
        $cruft_found = seravo_find_cruft_file($filename);
        if ( ! empty($cruft_found) ) {
          $crufts = array_merge($crufts, $cruft_found);
        }
      }
      foreach ( $list_dirs as $dirname ) {
        $cruft_found = seravo_find_cruft_dir($dirname);
        if ( ! empty($cruft_found) ) {
          $crufts = array_merge($crufts, $cruft_found);
        }
      }
      // This should be performed right after cruftfile search and before wp core
      foreach ( $white_list_dirs as $dirname ) {
        // Some directories are whitelisted and their files should not be deleted
        $keep = array();
        foreach ( $crufts as $filename ) {
          if ( strpos($filename, $dirname) !== false ) {
            $keep[] = $filename;
          }
        }
        $crufts = array_diff($crufts, $keep);
      }
      foreach ( $white_list_files as $filename ) {
        // Some files are whitelisted as it is not necessary to delete them
        $keep = array();
        foreach ( $crufts as $cruftname ) {
          if ( strpos($cruftname, $filename) !== false ) {
            $keep[] = $cruftname;
          }
        }
        $crufts = array_diff($crufts, $keep);
      }
      foreach ( $list_known_files as $dirname ) {
        $cruft_found = seravo_list_known_cruft_file($dirname);
        if ( ! empty($cruft_found) ) {
          $crufts = array_merge($crufts, $cruft_found);
        }
      }
      foreach ( $list_known_dirs as $dirname ) {
        $cruft_found = seravo_list_known_cruft_dir($dirname);
        if ( ! empty($cruft_found) ) {
          foreach ( $cruft_found as $key => $cruft_dir ) {
            if ( seravo_only_has_whitelisted_content($cruft_dir, $white_list_files, $white_list_dirs) ) {
              unset($cruft_found[$key]);
            }
          }
          $crufts = array_merge($crufts, $cruft_found);
        }
      }
      $crufts = array_filter(
        $crufts,
        function( $item ) use ( $crufts ) {
          foreach ( $crufts as $substring ) {
            if ( strpos($item, $substring) === 0 && $item !== $substring ) {
              return false;
            }
          }
          return true;
        }
      );
      $crufts = array_unique($crufts);
      set_transient('cruft_files_found', $crufts, 600);
      $crufts = array_map('Seravo\seravo_add_file_information', $crufts);
      echo wp_json_encode($crufts);
  } else {
      error_log('ERROR: Section ' . $_REQUEST['section'] . ' not defined');
  }

  wp_die();
}

/**
 * $_POST['deletefile'] is either a string denoting only one file
 * or it can contain an array containing strings denoting files.
 */
function seravo_ajax_delete_cruft_files() {
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
        $legit_cruft_files = get_transient('cruft_files_found'); // Check first that given file or directory is legitimate
        if ( in_array($file, $legit_cruft_files, true) ) {
          $unlink_result = is_dir($file) ? seravo_rmdir_recursive($file, 0) : unlink($file);
          // else - Backwards compatible with old UI
          $result['success'] = (bool) $unlink_result;
          $result['filename'] = $file;
          $results[] = $result;
        }
      }
      echo wp_json_encode($results);
    }
  }
  wp_die();
}
