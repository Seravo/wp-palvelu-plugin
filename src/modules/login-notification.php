<?php
/*
 * Plugin name: Login Notifications
 * Description: Show notifications to admin when logging in: last WordPress login and site error count (php-error.log)
 */
namespace Seravo;

// Deny direct access to this file
if ( ! defined('ABSPATH') ) {
  die('Access denied!');
}

if ( ! class_exists('Login_Notifications') ) {
  class Login_Notifications {

    /**
     * @var mixed[]|null
     */
    private static $login;
    // How many rows will be retrieved when reading logs, affects dashboard load time
    /**
     * @var int
     */
    private static $max_rows = 200;

    public static function load() {
      add_action('load-index.php', array( __CLASS__, 'retrieve_notification_data' ));
      add_action('load-profile.php', array( __CLASS__, 'retrieve_notification_data' ));
    }

    /**
    * Retreives login notification data when loading the dashboard page.
    */
    public static function retrieve_notification_data() {
      wp_enqueue_style('login-notification', SERAVO_PLUGIN_URL . 'style/login-notification.css', '', Helpers::seravo_plugin_version());

      // Retrieve last login notification only if the user has just logged in
      if ( isset($_SERVER['HTTP_REFERER']) ) {
        if ( apply_filters('seravo_dashboard_login', true) && strpos($_SERVER['HTTP_REFERER'], 'wp-login.php') !== false ) {
          self::$login = self::retrieve_last_login();
        }
      }

      // Display logins and/or errors if retrieved succesfully
      if ( ! empty(self::$login) ) {
        add_action('admin_notices', array( __CLASS__, 'display_admin_logins_notification' ));
      }
    }

    /**
    * Get the amount of php-error.log lines that have been added this week. The
    * first day of the week is retreived from WordPress settings.
    *
    * @return int The amount of php-error.log lines appended this week
    */
    public static function retrieve_error_count() {
      // Check the first day of week from wp options, and transform to last day of week
      $wp_first_day = get_option('start_of_week');
      $last_day_int = $wp_first_day === 0 ? 6 : $wp_first_day - 1;
      $days = array(
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
      );
      $last_day_of_week = strtotime('last ' . $days[ $last_day_int ]);

      $log_read = Logs::read_log_lines_backwards('/data/log/php-error.log', -1, self::$max_rows);

      // Display an error if the log file is abnormal
      if ( $log_read['status'] === 'LARGE_LOG_FILE' ) {
        // Display admin dashboard error message
        add_action(
          'wp_dashboard_setup',
          function() {
            wp_add_dashboard_widget(
              'seravo-error-widget',
              __('Large error log', 'seravo'),
              array( __CLASS__, 'display_admin_log_read_notification' )
            );
          }
        );
      } elseif ( $log_read['status'] === 'BAD_LOG_FILE' ) {
        add_action(
          'wp_dashboard_setup',
          function() {
            wp_add_dashboard_widget(
              'seravo-error-widget',
              __('Error log read failure', 'seravo'),
              array( __CLASS__, 'display_admin_broken_log_read_notification' )
            );
          }
        );
        return 0;
      } elseif ( $log_read['status'] === 'NO_LOG_FILE' ) {
        return 0;
      }

      // Read and reverse the php error logfile
      $output_reversed = array_reverse($log_read['output']);

      $php_errors = 0;
      // Loop through all the log lines
      foreach ( $output_reversed as $line ) {
        // Split the line from spaces and retrieve the error date from line
        $output_array = explode(' ', $line);
        $date_str = substr($output_array[0], 1, strlen($output_array[0]));

        // Just jump over the lines that don't contain dates, add an error though
        if ( preg_match('/^(0[1-9]|[1-2]\d|3[0-1])-([a-z]|[A-Z]){3}-\d{4}.*$/', $date_str) ) {
          // Return the amount of errors if the date is already from the previous week
          $date = strtotime($date_str);
          if ( $date <= $last_day_of_week ) {
            break;
          }
        }
        ++$php_errors;
      }
      return $php_errors;
    }

    /**
    * Get the last login details from the current user logged into WordPress.
    *
    * @return array Previous login ip address and date, or empty array if not found
    */
    public static function retrieve_last_login() {
      // Read login log file and reverse it
      $log_read = Logs::read_log_lines_backwards('/data/log/wp-login.log', -1, self::$max_rows);
      if ( $log_read === array() ) {
        return;
      }

      $output_reversed = array_reverse($log_read['output']);

      $already_skipped = false;

      foreach ( $output_reversed as $line ) {
        preg_match('/^(?<ip>[.:0-9a-f]+) - (?<name>[\w\-_.*@ ]+) \[(?<datetime>[\d\/\w: +]+)\] .* (?<status>[A-Z]+$)/', $line, $entry);

        $is_current_user_login = (get_userdata(wp_get_current_user()->ID)->user_login === $entry['name']);

        // Handle only successful logins
        if ( $entry['status'] == 'SUCCESS' && $is_current_user_login ) {
          // Skip the first login from the current user as it is most likely the latest
          if ( ! $already_skipped ) {
            $already_skipped = true;
            continue;
          }

          // Fetch login IP and the reverse domain name
          $ip = $entry['ip'];
          $domain = gethostbyaddr($ip);

          // Fetch login date and time
          $timezone = get_option('timezone_string');
          $datetime = \DateTime::createFromFormat('d/M/Y:H:i:s T', $entry['datetime']);
          $datetime->setTimezone(new \DateTimeZone(empty($timezone) ? 'UTC' : $timezone));

          return array(
            'date'   => $datetime->format(get_option('date_format')),
            'time'   => $datetime->format(get_option('time_format')),
            'ip'     => $ip,
            'domain' => $domain,
          );
        }
      }
      return array();
    }

    /**
    * Display the latest login of the current user logged in.
    */
    public static function display_admin_logins_notification() {
      $user_data = get_userdata(wp_get_current_user()->ID);

      if ( empty(self::$login['domain']) ) {
        $msg = wp_sprintf(
          /* translators:
          * %1$s username of the current user
          * %2$s date of last login
          * %3$s time of last login
          * %4$s reverse domain of the last login
          */
          __('Welcome, %1$s! Your previous login was on %2$s at %3$s from %4$s.', 'seravo'),
          $user_data->user_firstname == '' ? $user_data->user_login : $user_data->user_firstname,
          self::$login['date'],
          self::$login['time'],
          self::$login['ip']
        );
      } else {
        $msg = wp_sprintf(
          /* translators:
          * %1$s username of the current user
          * %2$s date of last login
          * %3$s time of last login
          * %4$s IP address of the last login
          */
          __('Welcome, %1$s! Your previous login was on %2$s at %3$s from %4$s.', 'seravo'),
          $user_data->user_firstname == '' ? $user_data->user_login : $user_data->user_firstname,
          self::$login['date'],
          self::$login['time'],
          self::$login['domain']
        );
      }

      echo '<div class="seravo-last-login notice notice-info is-dismissible">' . $msg . '</div>';
    }

    /**
    * Display an error if the php-error.log file is exceptionally large
    */
    public static function display_admin_large_log_read_notification() {
      $url = '<a href="' . get_option('siteurl') . '/wp-admin/tools.php?page=logs_page&logfile=php-error.log">php-error.log</a>';
      $msg = wp_sprintf(
        __('The PHP error log is abnormally large. This is a sign that something is broken in the code. The developer of the site should be notified.', 'seravo')
      );
      echo '<div>' . $msg . '</div>';
    }

    /**
    * Display an error if there is problem reading the php-error.log file
    */
    public static function display_admin_broken_log_read_notification() {
      $url = '<a href="' . get_option('siteurl') . '/wp-admin/tools.php?page=logs_page&logfile=php-error.log">php-error.log</a>';
      $msg = wp_sprintf(
        __('There is a problem trying to read the php-error.log. This is a sign that something is broken in the code. The developer of the site should be notified.', 'seravo')
      );
      echo '<div>' . $msg . '</div>';
    }
  }
  Login_Notifications::load();
}
