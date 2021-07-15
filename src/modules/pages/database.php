<?php
/*
 * Plugin name: Database
 * Description: View database credentials and link to Adminer
 * Version: 1.0
 */

namespace Seravo;

use Seravo\Ajax;
use \Seravo\Postbox;
use Seravo\Postbox\Component;
use \Seravo\Postbox\Template;
use \Seravo\Postbox\Toolpage;
use \Seravo\Postbox\Requirements;

class Database extends Toolpage {

  public function __construct() {
    parent::__construct(
      __('Database', 'seravo'),
      'tools_page_database_page', 
      'database_page',
      'Seravo\Postbox\seravo_postboxes_page',
    );
  }

  public function init_page() {
    self::init_postboxes($this);

    // TODO: Remove these after all the postboxes are done.
    add_action('admin_enqueue_scripts', array( __CLASS__, 'enqueue_database_scripts' ));
    add_action('wp_ajax_seravo_wp_db_info', 'Seravo\seravo_ajax_get_wp_db_info');

    $this->enable_ajax();
  }

  public function set_requirements(Requirements $requirements) {
    $requirements->can_be_production = \true;
    $requirements->can_be_staging = \true;
    $requirements->can_be_development = \true;
  }

  public static function init_postboxes( Toolpage $page ) {
    /**
     * Database access info postbox
     */
    $database_access = new Postbox\InfoBox('database-access');
    $database_access->set_title(__('Database Access', 'seravo'));
    $database_access->set_requirements(array( Requirements::CAN_BE_PRODUCTION => true ));
    $database_access->add_paragraph(__('You can find the database credentials by connecting to your site with SSH and running the command <code>wp-list-env</code>. These credentials can be used to connect to the server with an SSH tunnel. You can also use the web-based Adminer available on this page.', 'seravo'));
    $database_access->add_paragraph(__('When you have established an SSH connection you can use WP-CLI that features powerful database tools for example exports and imports. <a href="https://developer.wordpress.org/cli/commands/db/" target="_BLANK">Read the documentation for wp db</a>.', 'seravo'));
    $page->register_postbox($database_access);

    /**
     * Database adminer info postbox
     */
    $adminer = new Postbox\Postbox('database-adminer');
    $adminer->set_title(__('Manage the Database with Adminer', 'seravo'));
    $adminer->set_requirements(array( Requirements::CAN_BE_PRODUCTION => true ));
    $adminer->set_build_func(array( __CLASS__, 'build_adminer_postbox' ));
    $page->register_postbox($adminer);

    /**
     * Search & Replace tool postbox
     */
    $search_replace = new Postbox\SimpleForm('database-search-replace');
    $search_replace->set_title(__('Search-Replace Tool', 'seravo'));
    $search_replace->set_button_text(__('Run wp search-replace', 'seravo'), __('Do a dry run', 'seravo'));
    $search_replace->set_requirements(array( Requirements::CAN_BE_ANY_ENV => true ));
    $search_replace->set_ajax_func(array( __CLASS__, 'execute_search_replace' ));
    $search_replace->set_build_form_func(array( __CLASS__, 'build_search_replace_postbox' ));
    $page->register_postbox($search_replace);

    /**
     * Database cleanup tool postbox
     */
    $cleanup = new Postbox\Postbox('database-cleanup');
    $cleanup->set_title(__('Database Cleanup Tool', 'seravo'));
    $cleanup->set_requirements(array( Requirements::CAN_BE_PRODUCTION => true ));
    $cleanup->set_build_func(array( __CLASS__, 'build_database_cleanup' ));
    self::init_cleanup_ajax_scripts($cleanup);
    $page->register_postbox($cleanup);

    /**
     * Database size postbox
     * To be implemented...
     */
    $size = new Postbox\Postbox('database-size');
    $size->set_title(__('Database Size', 'seravo'));
    $size->set_requirements(array( Requirements::CAN_BE_PRODUCTION => true ));
    $size->set_build_func(array( __CLASS__, 'database_size_postbox' ));
    $page->register_postbox($size);
  }

  /**
   * Register scripts
   *
   * @param string $page hook name
   */
  public static function enqueue_database_scripts( $page ) {

    wp_register_style('seravo_database', SERAVO_PLUGIN_URL . 'style/database.css', '', Helpers::seravo_plugin_version());
    wp_register_script('apexcharts-js', SERAVO_PLUGIN_URL . 'js/lib/apexcharts.js', '', Helpers::seravo_plugin_version(), true);

    if ( $page === 'tools_page_database_page' ) {
      wp_enqueue_style('seravo_database');
      wp_enqueue_script('apexcharts-js');
      wp_enqueue_script('color-hash', SERAVO_PLUGIN_URL . 'js/lib/color-hash.js', array( 'jquery' ), Helpers::seravo_plugin_version(), false);
      wp_enqueue_script('reports-chart', SERAVO_PLUGIN_URL . 'js/reports-chart.js', array( 'jquery' ), Helpers::seravo_plugin_version(), false);
      wp_enqueue_script('seravo_database', SERAVO_PLUGIN_URL . 'js/database.js', array( 'jquery' ), Helpers::seravo_plugin_version(), false);

      $loc_translation_database = array(
        'ajaxurl'    => admin_url('admin-ajax.php'),
        'ajax_nonce' => wp_create_nonce('seravo_database'),
      );
      wp_localize_script('seravo_database', 'seravo_database_loc', $loc_translation_database);
    }

  }

  /**
   * Helper method for initializing cleanup & optimize postbox tools.
   * @param \Seravo\Postbox\Postbox $cleanup Postbox on which the commands and ajax are initialized.
   */
  public static function init_cleanup_ajax_scripts( Postbox\Postbox $cleanup ) {
    // Initialize optimize section
    $optimize = new Ajax\SimpleCommand('optimize-db');

    // define the db credentials for wp-db-optimize
    $db_info = defined('DB_NAME') ? 'DB_NAME=' . DB_NAME . ' ' : '';
    $db_info .= defined('DB_HOST') ? 'DB_HOST=' . DB_HOST . ' ' : '';
    $db_info .= defined('DB_USER') ? 'DB_USER=' . DB_USER . ' ' : '';
    $db_info .= defined('DB_PASSWORD') ? 'DB_PASSWORD=' . DB_PASSWORD . ' ' : '';
    $db_optimize_command = $db_info . 'wp-db-optimize 2>&1';

    $optimize->set_command($db_optimize_command, null, true);
    $optimize->set_button_text(__('Optimize', 'seravo'));
    $optimize->set_spinner_text(__('Optimizing database...', 'seravo'));

    // Initialize cleanup section
    $cleanup_command = new Ajax\SimpleCommand('cleanup-db');
    $cleanup_command->set_command('wp-backup && wp-db-cleanup 2>&1', 'wp-db-cleanup --dry-run', false);
    $cleanup_command->set_button_text(__('Run wp-db-cleanup', 'seravo'), __('Do a dry run', 'seravo'));
    $cleanup_command->set_empty_message(__('Nothing to be cleaned up', 'seravo'));

    $cleanup->add_ajax_handler($optimize);
    $cleanup->add_ajax_handler($cleanup_command);
  }

  /**
   * Build the database cleanup and optimize postbox.
   * @param \Seravo\Postbox\Component $base The postbox base component.
   * @param \Seravo\Postbox\Postbox $postbox To fetch the AJAX components from.
   */
  public static function build_database_cleanup( Component $base, Postbox\Postbox $postbox ) {
    $base->add_child(Template::section_title(__('Optimization', 'seravo')));
    $base->add_child(Template::paragraph(__('You can use this tool to run <code>wp-db-optimize</code>. The command optimizes WordPress database and thus reduces database disk usage.', 'seravo')));
    $base->add_child($postbox->get_ajax_handler('optimize-db')->get_component());

    $base->add_child(Template::section_title(__('Cleanup', 'seravo')));
    $base->add_child(Template::paragraph(__('You can use this tool to run <code>wp-db-cleanup</code>. For safety reason a dry run is compulsory before the actual cleanup can be done.', 'seravo')));
    $base->add_child($postbox->get_ajax_handler('cleanup-db')->get_component());
  }

  /**
   * Build the Adminer info postbox.
   * @param \Seravo\Postbox\Component $base The base component to add content.
   */
  public static function build_adminer_postbox( Component $base ) {
    $base->add_child(Template::paragraph(__('<a href="https://www.adminer.org" target="_BLANK">Adminer</a> is a visual database management tool, which is simpler and safer than its competitor phpMyAdmin.', 'seravo')));
    $base->add_child(Template::paragraph(__('At Seravo it can always be accessed at <code>example.com/.seravo/adminer</code>', 'seravo')));

    $button = Template::button_link_with_icon(Helpers::adminer_link(), __('Open Adminer', 'seravo'));
    $button->set_wrapper('<p class="adminer_button">', '</p>');
    $base->add_child($button);
  }

  /**
   * Build the search-replace postbox.
   * @param \Seravo\Postbox\Component $base The base component to add content.
   */
  public static function build_search_replace_postbox( Component $base ) {
    $base->add_child(Template::paragraph(__('You can use this tool to run <code>wp search-replace</code>. For safety reason a dry run is compulsory before the actual search-replace can be done.', 'seravo')));

    $from_to = new Component('', '<table>', '</table>');
    $from_to->add_child(Template::textfield_with_label('<b>' . __('FROM:', 'seravo') . '</b>', 'sr-from'));
    $from_to->add_child(Template::textfield_with_label('<b>' . __('TO:', 'seravo') . '</b>', 'sr-to'));
    $base->add_child($from_to);

    $base->add_child(
      Template::n_by_side(
        array(
          Template::checkbox_with_label(__('Skip backups', 'seravo'), 'skip-backup'),
          (! is_multisite() || current_user_can('manage_network')) ? Template::checkbox_with_label(__('All tables', 'seravo'), 'all-tables') : null,
          (is_multisite() && current_user_can('manage_network')) ? Template::checkbox_with_label(__('Network', 'seravo'), 'network') : null,
        )
      )
    );
  }

  /**
   * AJAX function for search-replace postbox. Executes
   * the search-replace itself.
   * @return \Seravo\Ajax\AjaxResponse Response with the result.
   */
  public static function execute_search_replace() {
    // Check that both to and from are set
    if ( ! isset($_REQUEST['sr-from']) || empty($_REQUEST['sr-from']) ||
          ! isset($_REQUEST['sr-to']) || empty($_REQUEST['sr-to']) ) {
      return Ajax\AjaxResponse::form_input_error(__('Error: Both <code>from</code> and <code>to</code> needs to be set', 'seravo'));
    }

    $from = $_REQUEST['sr-from'];
    $to = $_REQUEST['sr-to'];

    // Make sure the are not the same
    if ( $from === $to ) {
      // translators: Search replace 'from' value and 'to' value
      $message = __('Error: Value %1$s is identical to %2$s', 'seravo');
      return Ajax\AjaxResponse::form_input_error(sprintf($message, '<code>' . $to . '</code>', '<code>' . $from . '</code>'));
    }

    // Get arguments
    $dryrun = isset($_REQUEST['dryrun']) && $_REQUEST['dryrun'] === 'true';
    $backup = isset($_REQUEST['skip-backup']) && $_REQUEST['skip-backup'] === 'true' ? false : ! $dryrun;
    $all_tables = isset($_REQUEST['all-tables']) && $_REQUEST['all-tables'] === 'true' && (! is_multisite() || current_user_can('manage_network'));
    $network = isset($_REQUEST['network']) && $_REQUEST['network'] === 'true' && (is_multisite() && current_user_can('manage_network'));

    $args = array(
      $dryrun ? '--dry-run' : null,
      $all_tables ? '--all-tables' : null,
      $network ? '--network' : '--url=' . get_site_url(),
      $from,
      $to,
    );

    $output = array();

    // Take backup
    if ( $backup ) {
      $output[] = "<b>$ wp-backup 2>&1\n</b>";
      exec('wp-backup 2>&1', $output, $return_code);

      if ( $return_code !== 0 ) {
        return Ajax\AjaxResponse::command_error_response('wp-backup 2>&1');
      }
    }

    // Execute search-replace
    $output = array();
    $command = Shell::sanitize_command('wp search-replace', $args);
    exec($command . ' --format=table 2>&1', $sr_output, $return_code);

    // Convert output as table
    $output[] = '<div class="result-table-wrapper"><table class="result-table">';
    foreach ( $sr_output as $i => $line ) {
      if ( $i === 0 ) {
        $output[] = '<td><b>Table</b></td><td><b>Column</b></td><td><b>Count</b></td>';
        continue;
      }

      $columns = preg_split('/\s+/', $line, -1, PREG_SPLIT_NO_EMPTY);
      $row = "<tr><td class=\"seravo-ellipsis\" title=\"{$columns[0]}\">{$columns[0]}</td>";
      $row .= "<td class=\"seravo-ellipsis\" title=\"{$columns[1]}\">{$columns[1]}</td>";
      $row .= "<td class=\"seravo-ellipsis\" title=\"{$columns[2]}\">{$columns[2]}</td></tr>";
      $output[] = $row;
    }
    \array_pop($output);
    $output[] = '</table></div>';

    // Send the response
    $response = new Ajax\AjaxResponse();
    $response->is_success(true);
    $response->set_data(
      array(
        'output' => '<hr>' . implode("\n", $output) . '<hr>',
        'dryrun-only' => false,
      )
    );
    return $response;
  }

  public static function database_size_postbox() {
    ?>
    <?php if ( exec('which wp') !== '' ) : ?>
      <div class="section_chart_mobile">
        <p>
          <div class="seravo_wp_db_info_loading"><img src="/wp-admin/images/spinner.gif"></div>
          <div id="seravo_wp_db_info"></div>
          <hr>
          <b>
            <?php _e('Table sizes', 'seravo'); ?>
          </b>
          <div class="seravo_wp_db_info_loading"><img src="/wp-admin/images/spinner.gif"></div>
          <div class="chart_container">
            <div id="bars_single"></div>
          </div>
        </p>
      </div>

      <div class='seravo-database-detail-wrapper'>
        <div class='seravo_database_detail'>
          <?php _e('Details about database table sizes', 'seravo'); ?>
        </div>
        <div class='seravo-database-detail hidden'>
          <table class="database_detail_table" id="long_postmeta_values">
            <?php _e('Longest wp_postmeta values:', 'seravo'); ?>
          </table>
          <hr>
          <table class="database_detail_table" id="cumulative_postmeta_sizes">
            <?php _e('Cumulative size of meta_value per meta_key:', 'seravo'); ?>
          </table>
          <hr>
          <table class="database_detail_table" id="common_postmeta_values">
            <?php _e('Rows per meta_key:', 'seravo'); ?>
          </table>
          <hr>
          <table class="database_detail_table" id="autoload_option_count">
            <?php _e('Autoload options count (read to memory on each WP page load):', 'seravo'); ?>
          </table>
          <hr>
          <table class="database_detail_table" id="total_autoload_option_size">
            <?php _e('Autoload options total size of values:', 'seravo'); ?>
          </table>
          <hr>
          <table class="database_detail_table" id="long_autoload_option_values">
            <?php _e('Longest autoloaded wp_option values:', 'seravo'); ?>
          </table>
          <hr>
          <table class="database_detail_table" id="common_autoload_option_values">
            <?php _e('Most common autoloaded wp_option values:', 'seravo'); ?>
          </table>
        </div>
        <div class='seravo_database_detail_show_more_wrapper'>
          <a href='#' class='seravo_database_detail_show_more'><?php _e('Toggle Details', 'seravo'); ?>
            <div class='dashicons dashicons-arrow-down-alt2' id='seravo_arrow_database_detail_show_more'></div>
          </a>
        </div>
      </div>
      <?php
    endif; // end database info
  }
}
