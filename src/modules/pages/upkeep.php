<?php

namespace Seravo;

use Seravo\Ajax\AjaxResponse;
use \Seravo\Postbox;
use Seravo\Postbox\Component;
use \Seravo\Postbox\Template;
use \Seravo\Postbox\Toolpage;
use \Seravo\Postbox\Requirements;

class Upkeep extends Toolpage {

  public function __construct() {
    parent::__construct(
      __('Upkeep', 'seravo'),
      'tools_page_upkeep_page', 
      'upkeep_page',
      'Seravo\Postbox\seravo_two_column_postboxes_page',
    );
  }

  public function init_page() {
    self::init_postboxes($this);

    add_action('admin_enqueue_scripts', array( __CLASS__, 'register_scripts' ));
    add_action('admin_post_toggle_seravo_updates', array( __CLASS__, 'seravo_admin_toggle_seravo_updates' ), 20);

    // TODO: check if this hook actually ever fires for mu-plugins
    register_activation_hook(__FILE__, array( __CLASS__, 'register_view_updates_capability' ));

    $this->enable_ajax();
  }

  public function set_requirements(Requirements $requirements) {
    $requirements->can_be_production = \true;
    $requirements->can_be_staging = \true;
    $requirements->can_be_development = \true;
  }

  public static function init_postboxes( Toolpage $page ) {
    if ( getenv('WP_ENV') === 'production' ) {
      \Seravo\Postbox\seravo_add_raw_postbox(
        'seravo-updates',
        __('Seravo Updates', 'seravo'),
        array( __CLASS__, 'seravo_updates_postbox' ),
        'tools_page_upkeep_page',
        'normal'
      );

      \Seravo\Postbox\seravo_add_raw_postbox(
        'screenshots',
        __('Screenshots', 'seravo'),
        array( __CLASS__, 'screenshots_postbox' ),
        'tools_page_upkeep_page',
        'side'
      );
    }

    /**
     * Seravo Plugin Updater
     */
    $seravo_plugin_update = new Postbox\Postbox('seravo-plugin-updater');
    $seravo_plugin_update->set_title(__('Seravo Plugin Updater', 'seravo'));
    $seravo_plugin_update->set_requirements(array( Requirements::CAN_BE_ANY_ENV => true ));

    $update_button = new Ajax\SimpleCommand('seravo-plugin-update', 'wp-seravo-plugin-update');
    $update_button->set_button_text(__('Update plugin now', 'seravo'));
    $update_button->set_spinner_text(__('Updating Seravo Plugin...', 'seravo'));
    $seravo_plugin_update->add_ajax_handler($update_button);

    $seravo_plugin_update->set_data_func(array( __CLASS__, 'get_seravo_plugin_update' ), 0);
    $seravo_plugin_update->set_build_func(array( __CLASS__, 'build_seravo_plugin_update_postbox' ));
    $page->register_postbox($seravo_plugin_update);

    /**
     * PHP Version Tool
     */
    $php_version_tool = new Postbox\SimpleForm('change-php-version');

    // Init AJAX
    $php_compatibility = new Ajax\SimpleForm('check-php-compatibility');
    $php_compatibility->set_button_text(__('Check PHP compatibility', 'seravo'));
    $php_compatibility->set_spinner_text(__('Running PHP compatibility check. This may take up to tens of minutes.', 'seravo'));
    $php_compatibility->set_ajax_func(array( __CLASS__, 'run_php_compatibility' ));
    $php_version_tool->add_ajax_handler($php_compatibility);

    $php_version_tool->set_title(__('Change PHP Version', 'seravo'));
    $php_version_tool->set_build_form_func(array( __CLASS__, 'build_php_version_form' ));
    $php_version_tool->set_spinner_text(__('Activating... Please wait up to 30 seconds', 'seravo'));
    $php_version_tool->set_button_text(__('Change version', 'seravo'));
    $php_version_tool->set_ajax_func(array( __CLASS__, 'set_php_version' ));
    $php_version_tool->set_requirements(array( Requirements::CAN_BE_ANY_ENV => true ));
    $php_version_tool->set_build_func(array( __CLASS__, 'build_change_php_version_postbox' ));
    $page->register_postbox($php_version_tool);

    /**
     * Tests Status postbox
     */
    $tests_status = new Postbox\Postbox('tests-status');
    $tests_status->set_title(__('Tests Status', 'seravo'));
    $tests_status->set_data_func(array( __CLASS__, 'get_tests_status' ), 300);
    $tests_status->set_build_func(array( __CLASS__, 'build_tests_status' ));
    $tests_status->set_requirements(array( Requirements::CAN_BE_PRODUCTION => true ));
    $page->register_postbox($tests_status);

    /**
     * Update Status postbox
     */
    $update_status = new Postbox\Postbox('update-status');
    $update_status->set_title(__('Update Status', 'seravo'));
    $update_status->set_data_func(array( __CLASS__, 'get_update_status' ), 0);
    $update_status->set_build_func(array( __CLASS__, 'build_update_status' ));
    $update_status->set_requirements(array( Requirements::CAN_BE_PRODUCTION => true ));
    $page->register_postbox($update_status);

    /**
     * Changes Status postbox
     */
    $changes_status = new Postbox\FancyForm('backup-list-changes');
    $changes_status->set_title(__('Changes Status', 'seravo'));
    $changes_status->set_requirements(array( Requirements::CAN_BE_ANY_ENV => true ));
    $changes_status->set_ajax_func(array( __CLASS__, 'fetch_backup_list_changes' ));
    $changes_status->add_paragraph(__('This tool can be used to run command <code>wp-backup-list-changes-since</code> which finds folder and file changes in backup data since the given date. For example if you have started to have issues on your site, you can track down what folders or files have changed.', 'seravo'));
    $changes_status->add_paragraph(__('Backups are stored for 30 days which is also the maximum since offset.', 'seravo'));
    $changes_status->set_build_form_func(array( __CLASS__, 'build_backup_list_changes' ));
    $changes_status->set_button_text(__('Run', 'seravo'));
    $changes_status->set_title_text(__('Click "Run" to see changes', 'seravo'));
    $changes_status->set_spinner_text(__('Fetching changes...', 'seravo'));
    $page->register_postbox($changes_status);

    /**
     * Update Tests postbox
     */
    $update_tests = new Postbox\FancyForm('update-tests');
    $update_tests->set_title(__('Update tests', 'seravo'));
    $update_tests->set_requirements(array( Requirements::CAN_BE_ANY_ENV => true ));
    $update_tests->set_ajax_func(array( __CLASS__, 'run_update_tests' ));
    $update_tests->set_button_text(__('Run Tests', 'seravo'));
    $update_tests->set_spinner_text(__('Running rspec tests...', 'seravo'));
    $update_tests->set_title_text(__('Click "Run Tests" to run the Codeception tests', 'seravo'));
    $update_tests->add_paragraph(__('Here you can test the core functionality of your WordPress installation. Same results can be achieved via command line by running <code>wp-test</code> there. For further information, please refer to <a href="https://seravo.com/docs/tests/ng-integration-tests/" target="_BLANK"> Seravo Developer Documentation</a>.', 'seravo'));
    $page->register_postbox($update_tests);
  }

  /**
   * Builder function for tests status postbox.
   * @param Component $base Base element for the postbox.
   * @param Postbox $postbox The current postbox.
   * @param mixed $data Data returned by data function.
   */
  public static function build_update_status( Component $base, Postbox\Postbox $postbox, $data ) {
    if ( isset($data['error']) ) {
      $base->add_child(Template::error_paragraph($data['error']));
    } else {
      $base->add_children(
        array(
          isset($data['created']) ? Template::paragraph(__('Site created: ', 'seravo') . $data['created']) : null,
          isset($data['no_latest_log']) ? Template::paragraph($data['no_latest_log']) : null,
          isset($data['over_month_warning']) ? Template::paragraph($data['over_month_warning']) : null,
          isset($data['latest_update_log']) ? Component::from_raw('<p><b>' . __('Latest update.log:', 'seravo') . '</b></p>') : null,
          isset($data['latest_update_log']) ? Component::from_raw('<p>' . $data['latest_update_log'] . '</p>') : null,
          isset($data['see_logs_page_for_more']) ? Template::paragraph($data['see_logs_page_for_more']) : null,
          isset($data['latest_successful_update']) ? Template::paragraph(__('Latest successful full update: ') . $data['latest_successful_update']) : null,
          isset($data['latest_update_attempt']) ? Template::paragraph($data['latest_update_attempt']) : null,
          Template::section_title(__('Last 5 partial or attempted updates:', 'seravo')),
          isset($data['update_attempts']) ? Template::list_view($data['update_attempts']) : null,
          isset($data['for_details']) ? Template::paragraph($data['for_details']) : null,
        )
      );
    }
  }

  /**
   * Builder function for tests status postbox.
   * @param Component $base Base element for the postbox.
   * @param Postbox $postbox The current postbox.
   * @param mixed $data Data returned by data function.
   */
  public static function build_tests_status( Component $base, Postbox\Postbox $postbox, $data ) {
    $base->add_children(
      array(
        isset($data['status']) ? Template::paragraph($data['status'])->set_wrapper('<b>', '</b>') : null,
        isset($data['success']) ? Template::success_failure($data['success']) : null,
        isset($data['msg']) ? Template::paragraph($data['msg']) : null,
      )
    );
  }

  /**
   * Register scripts
   *
   * @param string $page hook name
   */
  public static function register_scripts( $page ) {
    wp_register_style('seravo_upkeep', SERAVO_PLUGIN_URL . 'style/upkeep.css', '', Helpers::seravo_plugin_version());
    wp_register_script('seravo_upkeep', SERAVO_PLUGIN_URL . 'js/upkeep.js', 'jquery', Helpers::seravo_plugin_version());

    if ( $page === 'tools_page_upkeep_page' ) {
      wp_enqueue_style('seravo_upkeep');
      wp_enqueue_script('seravo_upkeep');
    }
  }

  /**
   * Build Change PHP version postbox.
   * @param Component $base Postbox base component.
   * @param Postbox $postbox Current postbox to build for.
   */
  public static function build_change_php_version_postbox( Component $base, Postbox\Postbox $postbox ) {
    $base->add_child(Template::section_title(__('Check PHP compatibility', 'seravo')));
    $base->add_child(Template::paragraph(__('With this tool you can run command <code>wp-php-compatibility-check</code>. Check <a href="tools.php?page=logs_page&logfile=php-compatibility.log">compatibility scan results.</a>', 'seravo')));
    $base->add_child($postbox->get_ajax_handler('check-php-compatibility')->get_component());
    $base->add_child(Template::section_title(__('Change PHP version', 'seravo')));
    $base->add_child(Template::paragraph(__('Latest version is recommended if all plugins and theme support it. See also <a target="_blank" href="https://help.seravo.com/article/41-set-your-site-to-use-newest-php-version">more information on PHP version upgrades</a>.', 'seravo')));
    $base->add_child($postbox->get_ajax_handler('change-php-version')->get_component());
  }

  /**
   * Build function for Change PHP Version postbox radiobuttons.
   * @param Component $base Base component to add child components.
   */
  public static function build_php_version_form( Component $base ) {
    $data = array(
      '7.2' => array(
        'value' => '7.2',
        'name'  => 'PHP 7.2 (EOL 30 Nov 2020)',
        'checked' => false,
      ),
      '7.3' => array(
        'value' => '7.3',
        'name'  => 'PHP 7.3 (EOL 6 Dec 2021)',
        'checked' => false,
      ),
      '7.4' => array(
        'value' => '7.4',
        'name'  => 'PHP 7.4',
        'checked' => false,
      ),
      '8.0' => array(
        'value' => '8.0',
        'name'  => 'PHP 8.0',
        'checked' => false,
      ),
    );

    $current_version = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
    $data[$current_version]['checked'] = true;

      foreach ( $data as $version ) {
      $base->add_child(Template::radio_button('php-version', $version['value'], $version['name'], $version['checked']));
    }
  }

  /**
   * Run Check PHP compatibility AJAX call.
   * @return \Seravo\Ajax\AjaxResponse|mixed
   */
  public static function run_php_compatibility() {
    $polling = Ajax\AjaxHandler::check_polling();
    $response = new AjaxResponse();

    if ( $polling === true ) {
      $response->is_success(true);
      $response->set_data(
        array(
          'output' => '<hr>' . Template::paragraph(__('PHP compatibility check has been run. See full details on <a href="tools.php?page=logs_page&logfile=php-compatibility.log" target="_blank">compatibility scan results.</a>', 'seravo'))->to_html() . '<hr>',
        )
      );
      return $response;
    }

    if ( $polling === false ) {
      $command = 'wp-php-compatibility-check';
      $pid = Shell::backround_command($command);

      if ( $pid === false ) {
        return Ajax\AjaxResponse::exception_response();
      }

      return Ajax\AjaxResponse::require_polling_response($pid);
    }

    return $polling;
  }

  /**
   * Data function for Change PHP Version AJAX.
   * @return \Seravo\Ajax\AjaxResponse|mixed
   */
  public static function set_php_version() {
    $polling = Ajax\AjaxHandler::check_polling();
    $response = new AjaxResponse();
    $php_version = isset($_REQUEST['php-version']) ? sanitize_text_field($_REQUEST['php-version']) : '';
    $current_version = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
    $php_version_array = array(
      '7.2' => '7.2',
      '7.3' => '7.3',
      '7.4' => '7.4',
      '8.0' => '8.0',
    );

    if ( $polling === true ) {
      $response->is_success(true);
      $response->set_data(
        array(
          'output' => '<hr>' . Template::success_failure(true)->to_html() .
          Template::paragraph(__('PHP version has been changed succesfully! Please check <a href="tools.php?page=logs_page&logfile=php-error.log" target="_blank">php-error.log</a> for regressions.', 'seravo'))->to_html() . '<hr>',
        )
      );
      return $response;
    }

    if ( $polling === false ) {
      if ( array_key_exists($php_version, $php_version_array) ) {

        if ( $php_version === $current_version ) {
          $response = new AjaxResponse();
          $response->is_success(true);
          $response->set_data(
            array(
              'output' => '<hr>' . Template::error_paragraph(__('The selected PHP version is already in use.', 'seravo'))->to_html() . '<hr>',
              'error' => __('Shit happens', 'seravo'),
            )
          );
          return $response;
        }
        file_put_contents('/data/wordpress/nginx/php.conf', 'set $mode php' . $php_version_array[$php_version] . ';' . PHP_EOL);
        // NOTE! The exec below must end with '&' so that subprocess is sent to the
        // background and the rest of the PHP execution continues. Otherwise the Nginx
        // restart will kill this PHP file, and when this PHP files dies, the Nginx
        // restart will not complete, leaving the server state broken so it can only
        // recover if wp-restart-nginx is run manually.
        exec('echo "--> Setting to mode ' . $php_version_array[$php_version] . '" >> /data/log/php-version-change.log');
        //exec('wp-restart-nginx >> /data/log/php-version-change.log 2>&1 &');
        $restart_nginx = 'wp-restart-nginx >> /data/log/php-version-change.log 2>&1 &';
        $pid = Shell::backround_command($restart_nginx);

        if ( $pid === false ) {
          return Ajax\AjaxResponse::exception_response();
        }

        if ( is_executable('/usr/local/bin/s-git-commit') && file_exists('/data/wordpress/.git') ) {
          exec('cd /data/wordpress/ && git add nginx/*.conf && /usr/local/bin/s-git-commit -m "Set new PHP version" && cd /data/wordpress/htdocs/wordpress/wp-admin');
        }

        return Ajax\AjaxResponse::require_polling_response($pid);
      }
    }
    return $polling;
  }

  /**
   * Build Seravo Plugin Update postbox.
   * @param Component $base Postbox base component.
   * @param Postbox $postbox Current postbox to build for.
   * @param mixed $data Data returned by data function.
   */
  public static function build_seravo_plugin_update_postbox( Component $base, Postbox\Postbox $postbox, $data ) {
    if ( ! isset($data['current_version']) || ! isset($data['upstream_version']) ) {
      $base->add_child(Template::error_paragraph(__('No upstream or current Seravo Plugin version available, please try again later', 'seravo')));
      return;
    }

    $base->add_child(Template::paragraph(__('Seravo automatically updates your site and the Seravo Plugin as well. If you want to immediately update to the latest Seravo Plugin version, you can do it here.', 'seravo')));
    $base->add_child(Template::paragraph(__('Current version: ', 'seravo') . $data['current_version']));
    $base->add_child(Template::paragraph(__('Upstream version: ', 'seravo') . $data['upstream_version']));

    if ( $data['current_version'] == $data['upstream_version'] ) {
      $base->add_child(Template::paragraph(__('The currently installed version is the same as the latest available version.', 'seravo'), 'success'));
    } else {
      $base->add_child(Template::paragraph(__('There is a new version available', 'seravo'), 'warning'));
      $base->add_child($postbox->get_ajax_handler('seravo-plugin-update')->get_component());
    }
  }

  /**
   * Fetch data for Seravo Plugin Update postbox.
   * @return array<string, mixed>
   */
  public static function get_seravo_plugin_update() {
    $data['current_version'] = Helpers::seravo_plugin_version();

    $upstream_version = get_transient('seravo_plugin_upstream_version');
    if ( $upstream_version === false || empty($upstream_version) ) {
      $upstream_version = exec('curl -s https://api.github.com/repos/seravo/seravo-plugin/tags | grep "name" -m 1 | awk \'{gsub("\"","")}; {gsub(",","")}; {print $2}\'');
      set_transient('seravo_plugin_upstream_version', $upstream_version, 10800);
    }

    $data['upstream_version'] = $upstream_version;

    return $data;
  }

  /**
   * Fetch the site data from API
   */
  public static function seravo_admin_get_site_info() {
    return API::get_site_data();
  }

  /**
   * @return mixed|void
   */
  public static function seravo_updates_postbox() {

    $site_info = self::seravo_admin_get_site_info();

    if ( is_wp_error($site_info) ) {
      error_log($site_info->get_error_message());
      return _e('An API error occured. Please try again later', 'seravo');
    }

    // WP_error-object
    if ( gettype($site_info) === 'array' ) {
      ?>
      <h3><?php _e('Opt-out from updates by Seravo', 'seravo'); ?></h3>
      <?php
      $checked = $site_info['seravo_updates'] === true ? 'checked="checked"' : '';

      if ( isset($site_info['notification_webhooks'][0]['url']) &&
        $site_info['notification_webhooks'][0]['type'] === 'slack' ) {
        $slack_webhook = $site_info['notification_webhooks'][0]['url'];
      } else {
        $slack_webhook = '';
      }

      $contact_emails = array();
      if ( isset($site_info['contact_emails']) ) {
        $contact_emails = $site_info['contact_emails'];
      }

      ?>
      <p><?php _e("The Seravo upkeep service includes core and plugin updates to your WordPress site, keeping your site current with security patches and frequent tested updates to both the WordPress core and plugins. If you want full control of updates to yourself, you should opt out from Seravo's updates by unchecking the checkbox below.", 'seravo'); ?></p>
      <form name="seravo_updates_form" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
        <?php wp_nonce_field('seravo-updates-nonce'); ?>
        <input type="hidden" name="action" value="toggle_seravo_updates">
        <div class="checkbox allow_updates_checkbox">
          <input id="seravo_updates" name="seravo_updates" type="checkbox" <?php echo $checked; ?>> <?php _e('Seravo updates enabled', 'seravo'); ?><br>
        </div>

        <hr class="seravo-updates-hr">
        <h3><?php _e('Update Notifications with a Slack Webhook', 'seravo'); ?></h3>
        <p><?php _e('By defining a Slack webhook address below, Seravo can send you notifications about every update attempt, whether successful or not, to the Slack channel you have defined in your webhook. <a href="https://api.slack.com/incoming-webhooks" target="_BLANK">Read more about webhooks</a>.', 'seravo'); ?></p>
        <input name="slack_webhook" type="url" size="30" placeholder="https://hooks.slack.com/services/..." value="<?php echo $slack_webhook; ?>">
        <button type="button" class="button" id="slack_webhook_test"><?php _e('Send a Test Notification', 'seravo'); ?></button>

        <hr class="seravo-updates-hr">
        <h3 id='contacts'><?php _e('Contacts', 'seravo'); ?></h3>
        <p><?php _e('Seravo may use the email addresses defined here to send automatic notifications about technical problems with you site. Remember to use a properly formatted email address.', 'seravo'); ?></p>
        <input class="technical_contacts_input" type="email" multiple size="30" placeholder="<?php _e('example@example.com', 'seravo'); ?>" value="" data-emails="<?php echo htmlspecialchars(json_encode($contact_emails)); ?>">
        <button type="button" class="technical_contacts_add button"><?php _e('Add', 'seravo'); ?></button>
        <span class="technical_contacts_error"><?php _e('Email must be formatted as name@domain.com', 'seravo'); ?></span>
        <input name="technical_contacts" type="hidden">
        <div class="technical_contacts_buttons"></div>
        <br>
        <input type="submit" id="save_settings_button" class="button button-primary" value="<?php _e('Save settings', 'seravo'); ?>">
        <p><small class="seravo-developer-letter-hint">
            <?php
            // translators: %1$s link to Newsletter for WordPress developers
            printf(__('P.S. Subscribe to our %1$sNewsletter for WordPress Developers%2$s to get up-to-date information about our new features.', 'seravo'), '<a href="https://seravo.com/newsletter-for-wordpress-developers/" target="_BLANK">', '</a>');
            ?>
          </small></p>
        <br>
      </form>
      <?php
    } else {
      echo $site_info->get_error_message();
    }
  }

  /**
   * Data function for update status postbox
   * @return array<string, string>
   */
  public static function get_update_status() {
    $site_info = self::seravo_admin_get_site_info();

    if ( is_wp_error($site_info) ) {
      error_log($site_info->get_error_message());
      $data['error'] = __('An API error occured. Please try again later', 'seravo');
      return $data;
    }

    // Calculate the approx. amount of days since last succesful FULL update
    // 86400 is used to get days out of seconds (60*60*24)
    $interval = round((strtotime(date('Y-m-d')) - strtotime($site_info['update_success'])) / 86400);

    // Check if update.log exists and if not fetch the name of the rotated log instead
    // for linking to correct log on the logs page as well as fetching the failed lines
    // from the log if needed in the update notification
    $update_logs_arr = glob('/data/log/update.log');
    if ( empty($update_logs_arr) ) {
      $update_logs_arr = preg_grep('/(\d){8}$/', glob('/data/log/update.log-*'));
    }
    $update_log_name = substr(end($update_logs_arr), 10);

    $data['created'] = date('Y-m-d', strtotime($site_info['created']));

    if ( $site_info['seravo_updates'] === true && $interval >= 30 ) {
      if ( empty($update_logs_arr) ) {
        $data['no_latest_log'] = __('Unable to fetch the latest update log.', 'seravo');
      } else {
        // Get last item from logs array
        $update_log_contents = array();
        $update_log_output = '';
        $update_log_fp = fopen(end($update_logs_arr), 'r');
        if ( $update_log_fp != false ) {
          $index = 0;
          while ( ! feof($update_log_fp) ) {
            // Strip timestamps from log lines
            // Show only lines with 'Updates failed!'
            $buffer = substr(fgets($update_log_fp), 28);
            if ( substr($buffer, 0, 15) === 'Updates failed!' ) {
              $update_log_contents[ $index ] = $buffer;
              ++$index;
            }
          }
          fclose($update_log_fp);
          $update_log_output = implode('<br>', $update_log_contents);
        }

        $data['over_month_warning'] = __('Last succesful full site update was over a month ago. A developer should take a look at the update log and fix the issue preventing the site from updating.', 'seravo');

        if ( ! empty($update_log_contents) ) {
          $data['latest_update_log'] = $update_log_output;
        }
        $data['see_logs_page_for_more'] = '<a href="tools.php?page=logs_page&logfile=update.log&max_num_of_rows=50">' . __('See the logs page for more info.', 'seravo') . '</a>';
      }
    }

    $data['latest_successful_update'] = date('Y-m-d', strtotime($site_info['update_success']));

    if ( ! empty($site_info['update_attempt']) ) {
      $data['latest_update_attempt'] = date('Y-m-d', strtotime($site_info['update_attempt']));
    }

    exec('zgrep -h -e "Started updates for" -e "Installing urgent security" /data/log/update.log* | sort -r', $output);
    // Only match the date, hours and minutes are irrelevant
    if ( preg_match_all('/\d{4}-\d{2}-\d{2}/', implode(' ', $output), $matches) ) {
      $updates = array_slice($matches[0], 0, 5);
      $data['update_attempts'] = $updates;
    } else {
      $data['update_attempts'] = __('No update attempts yet', 'seravo');
    }

    $data['for_details'] = sprintf(
      // translators: event count and update.log filename and updates.log and security.log paths
      __('For details about last %1$s update attempts by Seravo, see %2$s.', 'seravo'),
      count($output),
      '<a href="tools.php?page=logs_page&logfile=' . $update_log_name . '&max_num_of_rows=50"><code>update.log*</code></a>'
    );

    return $data;
  }

  /**
   * Data function for tests status postbox.
   * @return array<string, string>|array<string, bool>
   */
  public static function get_tests_status() {
    exec('zgrep -h -A 1 "Running initial tests in production" /data/log/update.log-* /data/log/update.log | tail -n 1 | cut -d " " -f 4-8', $test_status);
    $data = array();

    if ( count($test_status) === 0 ) {
      $data['status'] = __('Unknown!', 'seravo');
      $data['msg'] = __("No tests have been ran yet. They will be ran during upcoming updates. You can try beforehand if the tests will be succesful or not with the 'Update tests' feature below.", 'seravo');
    } elseif ( $test_status[0] == 'Success! Initial tests have passed.' ) {
      $data['success'] = true;
      $data['msg'] = __('Site baseline tests have passed and updates can run normally.', 'seravo');
    } else {
      $data['success'] = false;
      $data['msg'] = __('Site baseline tests are failing and needs to be fixed before further updates are run.', 'seravo');
    }

    return $data;
  }

  public static function build_backup_list_changes( Component $base ) {
    $base->add_child(Template::datetime_picker(__('Choose a since date', 'seravo'), 'datepicker', date('Y-m-d', strtotime('-30 days')), date('Y-m-d')));
    $base->add_child(Component::from_raw('<br>'));
  }

  /**
   * Fetch 2 days offset date
   * @return array With formatted date and message.
   */
  public static function get_offset_date() {
    $datenow = getdate();
    $y = $datenow['year'];
    $m = $datenow['mon'];

    if ( $datenow['mday'] >= 3 ) {
      $d = $datenow['mday'] - 2;
      $message = __('Invalid date, using 2 days offset <br><br>', 'seravo');
    } else {
      // Show since the month beginning
      $d = 1;
      $message = __('Invalid date, showing since month beginning <br><br>', 'seravo');
    }
    $date = $y . '-' . $m . '-' . $d;

    return array( $date, $message );
  }

  /**
   * AJAX function for backup list changes postbox.
   * @return \Seravo\Ajax\AjaxResponse
   */
  public static function fetch_backup_list_changes() {
    $response = new AjaxResponse();
    $date = $_REQUEST['datepicker'];
    $message = '';

    if ( empty($date) ) {
      $offset_date = self::get_offset_date();
      $date = $offset_date[0];
      $message = $offset_date[1];
    }

    // Check whether the date is a proper date or not
    try {
      $formal_date = new \DateTime($date);
      unset($formal_date);
    } catch ( \Exception $exception ) {
      $offset_date = self::get_offset_date();
      $date = $offset_date[0];
      $message = $offset_date[1];
    }

    $cmd = 'wp-backup-list-changes-since ' . $date;
    $message .= exec($cmd . ' | wc -l') . ' ' . __('rows affected', 'seravo');
    $color = Ajax\FancyForm::STATUS_GREEN;
    exec($cmd, $output);

    $response->is_success(true);
    $response->set_data(
      array(
        'output' => '<pre>' . implode("\n", $output) . '</pre>',
        'title' => $message,
        'color' => $color,
      )
    );

    return $response;
  }

  public static function screenshots_postbox() {

    $screenshots = glob('/data/reports/tests/debug/*.png');
    $showing = 0;
    # Shows a comparison of any and all image pair of *.png and *.shadow.png found.
    if ( count($screenshots) > 3 ) {

      echo '
        <table>
          <tr>
            <th>' . __('The Difference', 'seravo') . '</th>
          </tr>
          <tbody  style="vertical-align: top; text-align: center;">';

      foreach ( $screenshots as $screenshot ) {
        // Skip *.shadow.png files from this loop
        if ( strpos($screenshot, '.shadow.png') || strpos($screenshot, '.diff.png') ) {
          continue;
        }

        $name = substr(basename($screenshot), 0, -4);

        // Check whether the *.shadow.png exists in the set
        // Do not show the comparison if both images are not found.
        $exists_shadow = false;
        foreach ( $screenshots as $screenshotshadow ) {
          // Increment over the known images. Stop when match found
          if ( strpos($screenshotshadow, $name . '.shadow.png') !== false ) {
            $exists_shadow = true;
            break;
          }
        }
        // Only shot the comparison if both images are available
        if ( ! $exists_shadow ) {
          continue;
        }

        $diff_txt = file_get_contents(substr($screenshot, 0, -4) . '.diff.txt');
        if ( preg_match('/Total: ([0-9.]+)/', $diff_txt, $matches) ) {
          $diff = (float) $matches[1];
        }

        echo '
          <tr>
            <td>
            <hr class="seravo-updates-hr">
            <a href="?x-accel-redirect&screenshot=' . $name . '.diff.png" class="diff-img-title">' . $name . '</a>
            <span';
        // Make the difference number stand out if it is non-zero
        if ( $diff > 0.011 ) {
          echo ' style="background-color: yellow;color: red;"';
        }
        echo '>' . round($diff * 100, 2) . ' %</span>';

        echo self::seravo_admin_image_comparison_slider(
          array(
            'difference' => $diff,
            'img_right'  => "?x-accel-redirect&screenshot={$name}.shadow.png",
            'img_left'   => "?x-accel-redirect&screenshot={$name}.png",
          )
        );
        echo '
            </td>
          </tr>';
        ++$showing;
      }
      echo '
      </tbody>
    </table>';

    }

    if ( $showing == 0 ) {
      echo __('No screenshots found. They will become available during the next attempted update.', 'seravo');
    }
  }

  /**
   * @return string|bool
   */
  public static function seravo_admin_image_comparison_slider( $atts = array(), $content = null, $tag = 'seravo_admin_image_comparison_slider' ) {

    // normalize attribute keys, lowercase
    $atts = array_change_key_case((array) $atts, CASE_LOWER);

    $img_comp_atts = shortcode_atts(
      array(
        'difference'           => '',
        'img_left'             => '',
        'img_right'            => '',
        'desc_left'            => __('Current State', 'seravo'),
        'desc_right'           => __('Update Attempt', 'seravo'),
        'desc_left_bg_color'   => 'green',
        'desc_right_bg_color'  => 'red',
        'desc_left_txt_color'  => 'white',
        'desc_right_txt_color' => 'white',
      ),
      $atts,
      $tag
    );
    $knob_style = floatval($img_comp_atts['difference']) > 0.011 ? 'difference' : '';
    ob_start();
    ?>
    <div class="ba-slider <?php echo $knob_style; ?>">
      <img src="<?php echo $img_comp_atts['img_right']; ?>">
      <div class="ba-text-block" style="background-color:<?php echo $img_comp_atts['desc_right_bg_color']; ?>;color:<?php echo $img_comp_atts['desc_right_txt_color']; ?>;">
        <?php echo $img_comp_atts['desc_right']; ?>
      </div>
      <div class="ba-resize">
        <img src="<?php echo $img_comp_atts['img_left']; ?>">
        <div class="ba-text-block" style="background-color:<?php echo $img_comp_atts['desc_left_bg_color']; ?>;color:<?php echo $img_comp_atts['desc_left_txt_color']; ?>;">
          <?php echo $img_comp_atts['desc_left']; ?>
        </div>
      </div>
      <span class="ba-handle"></span>
    </div>
    <?php

    return ob_get_clean();
  }

  public static function seravo_admin_toggle_seravo_updates() {
    check_admin_referer('seravo-updates-nonce');

    $seravo_updates = isset($_POST['seravo_updates']) && $_POST['seravo_updates'] === 'on' ? 'true' : 'false';
    $data = array( 'seravo_updates' => $seravo_updates );

    // Webhooks is an anonymous array of named arrays with type/url pairs
    $data['notification_webhooks'] = array(
      array(
        'type' => 'slack',
        'url'  => $_POST['slack_webhook'],
      ),
    );

    // Handle site technical contact email addresses
    if ( isset($_POST['technical_contacts']) ) {
      $validated_addresses = array();

      // There must be at least one contact email
      if ( ! empty($_POST['technical_contacts']) ) {

        // Only unique emails are valid
        $contact_addresses = array_unique(explode(',', $_POST['technical_contacts']));

        // Perform email validation before making API request
        foreach ( $contact_addresses as $contact_address ) {
          $address = trim($contact_address);

          if ( ! empty($address) && filter_var($address, FILTER_VALIDATE_EMAIL) ) {
            $validated_addresses[] = $address;
          }
        }
      }

      // Only update addresses if any valid ones were found
      if ( ! empty($validated_addresses) ) {
        $data['contact_emails'] = $validated_addresses;
      }
    }

    $response = API::update_site_data($data);
    if ( is_wp_error($response) ) {
      die($response->get_error_message());
    }

    wp_redirect(admin_url('tools.php?page=upkeep_page&settings-updated=true'));
    die();
  }

  /**
   * AJAX function for Update tests postbox
   * @return \Seravo\Ajax\AjaxResponse
   */
  public static function run_update_tests() {
    $response = new AjaxResponse();
    $retval = null;
    $output = array();
    exec('wp-test', $output, $retval);

    if ( empty($output) ) {
      return Ajax\AjaxResponse::command_error_response('wp-test');
    }

    $message = __('At least one of the tests failed.', 'seravo');
    $status_color = Ajax\FancyForm::STATUS_RED;

    if ( count(preg_grep('/OK \(/i', $output)) >= 1 && $retval === 0 ) {
      // Success
      $message = __('Tests were run without any errors!', 'seravo');
      $status_color = Ajax\FancyForm::STATUS_GREEN;
    }

    // Format the output
    $pattern = '/\x1b\[[0-9;]*m/';
    $output = preg_replace($pattern, '', $output);
    $response->is_success(true);
    $response->set_data(
      array(
        'output' => '<pre>' . implode("\n", $output) . '</pre>',
        'title' => $message,
        'color' => $status_color,
      )
    );
    return $response;
  }
}
