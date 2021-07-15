<?php

namespace Seravo;

use Seravo\Ajax\AjaxResponse;
use \Seravo\Postbox\Toolpage;
use \Seravo\Postbox;
use \Seravo\Postbox\Component;
use \Seravo\Postbox\Template;
use \Seravo\Postbox\Requirements;

class Site_Status extends Toolpage {

  // Default maximum resolution for images
  /**
   * @var int
   */
  private static $max_width_default = 2560;
  /**
   * @var int
   */
  private static $max_height_default = 2560;

  // Minimum resolution for images. Can't be set any lower by user.
  /**
   * @var int
   */
  private static $min_width = 500;
  /**
   * @var int
   */
  private static $min_height = 500;

  // Object-cache file location
  /**
   * @var string
   */
  const OBJECT_CACHE_PATH = '/data/wordpress/htdocs/wp-content/object-cache.php';

  public function __construct() {
    parent::__construct(
      __('Site Status', 'seravo'),
      'tools_page_site_status_page', 
      'site_status_page',
      'Seravo\Postbox\seravo_postboxes_page',
    );
  }

  public function init_page() {
    self::init_postboxes($this);

    add_action('admin_init', array( __CLASS__, 'register_optimize_image_settings' ));
    add_action('admin_init', array( __CLASS__, 'register_sanitize_uploads_settings' ));
    self::check_default_settings();
    add_action('admin_enqueue_scripts', array( __CLASS__, 'enqueue_site_status_scripts' ));
    add_action('wp_ajax_seravo_ajax_site_status', 'Seravo\seravo_ajax_site_status');
    add_action('wp_ajax_seravo_speed_test', 'Seravo\seravo_speed_test');

    $this->enable_charts();
    $this->enable_ajax();
  }

  public function set_requirements(Requirements $requirements) {
    $requirements->can_be_production = \true;
    $requirements->can_be_staging = \true;
    $requirements->can_be_development = \true;
  }

  /**
   * Init postboxes.
   * @param Toolpage $page Page to init postboxes to.
   */
  public static function init_postboxes( Toolpage $page ) {
    // Add cache status postbox
    \Seravo\Postbox\seravo_add_raw_postbox(
      'cache-status',
      __('Cache Status', 'seravo'),
      array( __CLASS__, 'seravo_cache_status' ),
      'tools_page_site_status_page',
      'normal'
    );

    if ( getenv('WP_ENV') === 'production' ) {
      \Seravo\Postbox\seravo_add_raw_postbox(
        'shadows',
        __('Shadows', 'seravo'),
        array( __CLASS__, 'seravo_shadows_postbox' ),
        'tools_page_site_status_page',
        'side'
      );
    }

    \Seravo\Postbox\seravo_add_raw_postbox(
      'optimize-images',
      __('Optimize Images', 'seravo'),
      array( __CLASS__, 'optimize_images_postbox' ),
      'tools_page_site_status_page',
      'side'
    );

    \Seravo\Postbox\seravo_add_raw_postbox(
      'speed-test',
      __('Speed test', 'seravo'),
      array( __CLASS__, 'speed_test' ),
      'tools_page_site_status_page',
      'side'
    );

    \Seravo\Postbox\seravo_add_raw_postbox(
      'sanitize-uploads',
      __('Sanitize uploads', 'seravo'),
      array( __CLASS__, 'sanitize_uploads' ),
      'tools_page_site_status_page',
      'side'
    );

    /**
     * Site info postbox
     */
    $site_info = new Postbox\Postbox('site-info');
    $site_info->set_title(__('Site Information', 'seravo'));
    $site_info->set_data_func(array( __CLASS__, 'get_site_info' ), 300);
    $site_info->set_build_func(array( __CLASS__, 'build_site_info' ));
    $site_info->set_requirements(array( Requirements::CAN_BE_PRODUCTION => true ));
    $page->register_postbox($site_info);

    /**
     * HTTP Request Statistics  postbox
     */
    $http_stats = new Postbox\LazyLoader('http-request-statistics');
    $http_stats->set_title(__('HTTP Request Statistics', 'seravo'));
    $http_stats->set_build_func(array( __CLASS__, 'build_http_statistics' ));
    $http_stats->set_requirements(array( Requirements::CAN_BE_PRODUCTION => true ));
    $http_stats->set_ajax_func(array( __CLASS__, 'get_http_statistics' ));
    $page->register_postbox($http_stats);

    /**
     * Site checks postbox
     */
    $site_checks = new Postbox\FancyForm('site-checks');
    $site_checks->set_title(__('Site checks', 'seravo'));
    $site_checks->set_requirements(array( Requirements::CAN_BE_ANY_ENV => true ));
    $site_checks->set_ajax_func(array( __CLASS__, 'run_site_checks' ));
    $site_checks->set_button_text(__('Run site checks', 'seravo'));
    $site_checks->set_spinner_text(__(' Running site checks', 'seravo'));
    $site_checks->set_title_text(__(' Click "Run site checks" to run the tests', 'seravo'));
    $site_checks->add_paragraph(__('Site checks provide a report about your site health and show potential issues. Checks include for example php related errors, inactive themes and plugins.', 'seravo'));
    $page->register_postbox($site_checks);

    /**
     * Disk Usage postbox
     */
    $disk_usage = new Postbox\LazyLoader('disk-usage');
    $disk_usage->set_build_func(array( __CLASS__, 'build_disk_usage' ));
    $disk_usage->use_hr(false);
    $disk_usage->set_title(__('Disk Usage', 'seravo'));
    $disk_usage->set_ajax_func(array( __CLASS__, 'get_disk_usage' ));
    $disk_usage->set_requirements(
      array(
        Requirements::CAN_BE_PRODUCTION => true,
        Requirements::CAN_BE_STAGING => true,
      )
    );
    $page->register_postbox($disk_usage);
  }

  public static function register_optimize_image_settings() {
    add_settings_section(
      'seravo-optimize-images-settings',
      '',
      array( __CLASS__, 'optimize_images_settings_description' ),
      'optimize_images_settings'
    );

    register_setting('seravo-optimize-images-settings-group', 'seravo-enable-optimize-images');
    register_setting('seravo-optimize-images-settings-group', 'seravo-enable-strip-image-metadata');
    register_setting(
      'seravo-optimize-images-settings-group',
      'seravo-image-max-resolution-width',
      array( 'sanitize_callback' => array( __CLASS__, 'sanitize_image_width' ) )
    );
    register_setting(
      'seravo-optimize-images-settings-group',
      'seravo-image-max-resolution-height',
      array( 'sanitize_callback' => array( __CLASS__, 'sanitize_image_height' ) )
    );

    add_settings_field(
      'seravo-images-enabled-field',
      __('Optimize Images', 'seravo'),
      array( __CLASS__, 'seravo_image_enabled_field' ),
      'optimize_images_settings',
      'seravo-optimize-images-settings'
    );
    add_settings_field(
      'seravo-strip-image-metadata-field',
      __('Strip Image Metadata', 'seravo'),
      array( __CLASS__, 'seravo_image_metadata_enabled_field' ),
      'optimize_images_settings',
      'seravo-optimize-images-settings'
    );
    add_settings_field(
      'seravo-images-max-width-field',
      __('Maximum Image Width (px)', 'seravo'),
      array( __CLASS__, 'seravo_image_max_width_field' ),
      'optimize_images_settings',
      'seravo-optimize-images-settings'
    );
    add_settings_field(
      'seravo-images-max-height-field',
      __('Maximum Image Height (px)', 'seravo'),
      array( __CLASS__, 'seravo_image_max_height_field' ),
      'optimize_images_settings',
      'seravo-optimize-images-settings'
    );

  }

  public static function enqueue_site_status_scripts( $page ) {
    wp_register_script('apexcharts-js', SERAVO_PLUGIN_URL . 'js/lib/apexcharts.js', null, Helpers::seravo_plugin_version(), true);
    wp_register_script('seravo_site_status', SERAVO_PLUGIN_URL . 'js/sitestatus.js', '', Helpers::seravo_plugin_version());
    wp_register_style('seravo_site_status', SERAVO_PLUGIN_URL . 'style/sitestatus.css', '', Helpers::seravo_plugin_version());
    if ( $page === 'tools_page_site_status_page' ) {
      wp_enqueue_style('seravo_site_status');
      wp_enqueue_script('apexcharts-js');
      wp_enqueue_script('color-hash', SERAVO_PLUGIN_URL . 'js/lib/color-hash.js', array( 'jquery' ), Helpers::seravo_plugin_version(), false);
      wp_enqueue_script('reports-chart', SERAVO_PLUGIN_URL . 'js/reports-chart.js', array( 'jquery' ), Helpers::seravo_plugin_version(), false);
      wp_enqueue_script('cache-status-charts', SERAVO_PLUGIN_URL . 'js/cache-status-charts.js', array( 'jquery' ), Helpers::seravo_plugin_version(), false);
      wp_enqueue_script('seravo_site_status');

      $loc_translation = array(
        'no_data'             => __('No data returned for the section.', 'seravo'),
        'failed'              => __('Failed to load. Please try again.', 'seravo'),
        'no_reports'          => __('No reports found at /data/slog/html/. Reports should be available within a month of the creation of a new site.', 'seravo'),
        'view_report'         => __('View report', 'seravo'),
        'object_cache_success'=> __('Object cache is now enabled', 'seravo'),
        'object_cache_failure'=> __('Enabling object cache failed', 'seravo'),
        'running_cache_tests' => __('Running cache tests...', 'seravo'),
        'cache_success'       => __('HTTP cache working', 'seravo'),
        'cache_failure'       => __('HTTP cache not working', 'seravo'),
        'success'             => __('Success!', 'seravo'),
        'failure'             => __('Failure!', 'seravo'),
        'error'               => __('Error!', 'seravo'),
        'confirm'             => __('Are you sure? This replaces all information in the selected environment.', 'seravo'),
        'avg_latency'         => __('Avg latency: ', 'seravo'),
        'avg_cached_latency'  => __('Avg cached latency: ', 'seravo'),
        'latency'             => __('Latency', 'seravo'),
        'cached_latency'      => __('Cached latency', 'seravo'),
        'keyspace_hits'       => __('Keyspace hits', 'seravo'),
        'keyspace_misses'     => __('Keyspace misses', 'seravo'),
        'hits'                => __('Hits', 'seravo'),
        'misses'              => __('Misses', 'seravo'),
        'stales'              => __('Stales', 'seravo'),
        'ajaxurl'             => admin_url('admin-ajax.php'),
        'ajax_nonce'          => wp_create_nonce('seravo_site_status'),
      );
      wp_localize_script('seravo_site_status', 'seravo_site_status_loc', $loc_translation);
    }
  }

  public static function seravo_cache_status() {
    ?>
    <?php
      if ( ! file_exists(self::OBJECT_CACHE_PATH) ) {
      ?>
    <h3 id='object_cache_warning' style='color: red' > <?php _e('Object cache is currently disabled!', 'seravo'); ?> <h3>
    <button type='button' class='button-primary' id='enable-object-cache'> <?php _e('Enable', 'seravo'); ?> </button>
    <div class='object_cache_loading' hidden>
      <img src='/wp-admin/images/spinner.gif'>
    </div>
      <?php
    }
    ?>
    <p><?php _e('Caching decreases the load time of the website. The cache hit rate represents the efficiency of cache usage. Read about caching from the <a href="https://help.seravo.com/article/36-how-does-caching-work/" target="_BLANK">documentation</a> or <a href="https://seravo.com/tag/cache/" target="_BLANK">blog</a>.', 'seravo'); ?></p>
    <h3><?php _e('Object Cache in Redis', 'seravo'); ?></h3>
    <p><?php _e('Persistent object cache implemented with <a href="https://seravo.com/blog/faster-wordpress-with-transients/" target="_BLANK">transients</a> can be stored in Redis. Instructions on how to activate the object cache can be found from the <a href="https://help.seravo.com/article/38-active-object-cache/" target="_BLANK">documentation</a>.', 'seravo'); ?></p>
    <h4><?php _e('Cache hit rate', 'seravo'); ?></h4>
    <div class='redis_info_loading'>
      <img src='/wp-admin/images/spinner.gif'>
    </div>
    <div id='redis-hit-rate-chart'></div>
    <p>
      <?php _e('Expired keys: ', 'seravo'); ?>
      <span id='redis-expired-keys'></span>
      <span class='tooltip dashicons dashicons-info'>
        <span class='tooltiptext'>
          <?php _e('The number of keys deleted.', 'seravo'); ?>
        </span>
      </span>
      <?php _e('<br>Evicted keys: ', 'seravo'); ?>
      <span id='redis-evicted-keys'></span>
      <span class='tooltip dashicons dashicons-info'>
        <span class='tooltiptext'>
          <?php _e("The number of keys being deleted because the memory usage has hit it's limit.", 'seravo'); ?>
        </span>
      </span>
    </p>
    <h3><?php _e('HTTP Cache', 'seravo'); ?></h3>
    <p><?php _e("The HTTP cache hit rate is calculated from all Nginx's access logs. It describes the long-term cache usage situation.", 'seravo'); ?></p>
    <h4><?php _e('Cache hit rate', 'seravo'); ?></h4>
    <div class='longterm_cache_loading'>
      <img src='/wp-admin/images/spinner.gif'>
    </div>
    <div id='http-hit-rate-chart'></div>
    <p><?php _e("There are <span id='http-cache-bypass'></span> bypasses.", 'seravo'); ?></p>
    <h3><?php _e('Nginx HTTP Cache', 'seravo'); ?></h3>
    <div id='front_cache_status'>
      <p><?php _e("Test the functionality of your site's front cache. This can also be done via command line with command <code>wp-check-http-cache</code>.", 'seravo'); ?></p>
      <button type='button' class='button-primary' id='run-cache-tests'><?php _e('Run cache tests', 'seravo'); ?></button>
      <div class='seravo-cache-test-result-wrapper'>
        <div class='seravo_cache_tests_status front_cache_status'>
          <?php _e('Click "Run cache tests" to run the cache tests', 'seravo'); ?>
        </div>
        <div class='seravo-cache-test-result'>
          <pre id='seravo_cache_tests'></pre>
        </div>
        <div class='seravo_cache_test_show_more_wrapper hidden'>
          <a href='' class='seravo_cache_test_show_more'><?php _e('Toggle Details', 'seravo'); ?>
            <div class='dashicons dashicons-arrow-down-alt2' id='seravo_arrow_cache_show_more'>
            </div>
          </a>
        </div>
      </div>
    </div>
    <?php
  }

  /**
   * Build function for the disk usage postbox.
   * @param Component $base Postbox base component to add elements.
   * @param Postbox $postbox The postbox the func is building.
   */
  public static function build_disk_usage( Component $base, Postbox\Postbox $postbox ) {
    $base->add_child(Template::side_by_side(Component::from_raw('<div id="disk-usage-donut" style="width: 100px;"></div>'), $postbox->get_ajax_handler('disk-usage')->get_component(), 'evenly'));
    $base->add_child(Component::from_raw('<span id="disk-use-notification" style="display: none;">' . __('Disk space low! ', 'seravo') . '<a href="https://help.seravo.com/article/280-seravo-plugin-site-status#diskusage" target="_BLANK">' . __('Read more.', 'seravo') . '</a></span>'));
    $base->add_child(Component::from_raw('<hr><b>' . __('Disk usage by directory', 'seravo') . '</b>'));
    $base->add_child(Component::from_raw('<div id="disk-bars-single" style="width: 100%"></div><hr>'));
    $base->add_child(Template::paragraph(__("Logs and automatic backups don't count against your quota.", 'seravo') . '<br>' . __('Use <a href="tools.php?page=security_page#cruftfiles_tool">cruft remover</a> to remove unnecessary files.', 'seravo')));
  }

  /**
   * Helper function for the disk usage AJAX.
   * @return array<string, array<array<string, float|int|string>|string>>
   */
  public static function report_disk_usage() {
    $dir_max_limit = 1000000;
    $dir_threshold = 100000000;

    // Directories not counted against plan's quota but can be visible
    // in the front end
    $exclude_dirs = array(
      '--exclude=/data/backups',
      '--exclude=/data/log',
      '--exclude=/data/slog',
    );
    // Directories not shown in the front-end even if their size
    // exceed $dir_threshold. Produces a list string of the directories
    // in a format accepted by grep:  /data/dir_1\|/data/dir_1\| ...
    $hidden_dirs = implode(
      '\|',
      array(
        '/data/backups',
      )
    );

    // Get total disk usage
    $cached_usage = get_transient('disk_space_usage');

    if ( ! $cached_usage ) {
      exec('du -sb /data ' . implode(' ', $exclude_dirs), $data_folder);
      set_transient('disk_space_usage', $data_folder, Dashboard_Widgets::DISK_SPACE_CACHE_TIME);
    } else {
      $data_folder = $cached_usage;
    }

    list($data_size, $data_name) = preg_split('/\s+/', $data_folder[0]);

    // Get the sizes of certain directories and directories with the
    // size larger than $dir_threshold, ones in $hidden_dirs will be
    // excluded from the output using grep
    exec(
      '(
      du --separate-dirs -b --threshold=' . $dir_threshold . ' /data/*/ &&
      du -sb /data/wordpress/htdocs/wp-content/uploads/ &&
      du -sb /data/wordpress/htdocs/wp-content/themes/ &&
      du -sb /data/wordpress/htdocs/wp-content/plugins/ &&
      du -sb /data/wordpress/htdocs/wordpress/wp-includes/ &&
      du -sb /data/wordpress/htdocs/wordpress/wp-admin/ &&
      du -sb /data/redis/ &&
      du -sb /data/reports/ &&
      du -sb /data/db/
      ) | grep -v "' . $hidden_dirs . '" | sort -hr',
      $data_sub
    );

    // Generate sub folder array
    $data_folders = array();
    foreach ( $data_sub as $folder ) {
      list($folder_size, $folder_name) = preg_split('/\s+/', $folder);
      $folder_name = str_replace('/data/wordpress/htdocs/wordpress/wp-', '.../wp-', $folder_name);
      $folder_name = str_replace('/data/wordpress/htdocs/wp-content/', '.../wp-content/', $folder_name);

      if ( $folder_size > $dir_max_limit ) {
        $data_folders[$folder_name] = array(
          'percentage' => (($folder_size / $data_size) * 100),
          'human'      => Helpers::human_file_size($folder_size),
          'size'       => $folder_size,
        );
      }
    }
    // Create output array
    $output = array(
      'data'        => array(
        'human' => Helpers::human_file_size($data_size),
        'size'  => $data_size,
      ),
      'dataFolders' => $data_folders,
    );
    return $output;
  }

  /**
   * AJAX function for Disk Usage postbox.
   * @return \Seravo\Ajax\AjaxResponse
   */
  public static function get_disk_usage() {
    $response = new AjaxResponse();
    $api_response = API::get_site_data();

    if ( is_wp_error($api_response) ) {
      error_log($api_response->get_error_message());
      $response->is_success(false);
      $response->set_error(__('An API error occured, please try again later.', 'seravo'));
      return $response;
    }

    $disk_usage = self::report_disk_usage();
    $disk_usage['data']['disk_limit'] = $api_response['plan']['disklimit'];
    $output = Template::text(__('Disk space in your plan: ', 'seravo') . $disk_usage['data']['disk_limit'] . 'GB <br>' . __('Space in use: ', 'seravo') . $disk_usage['data']['human'], 'space-info')->to_html();

    $response->is_success(true);
    $response->set_data(
      array(
        'data' => $disk_usage['data'],
        'folders' => $disk_usage['dataFolders'],
        'output' => $output,
      )
    );

    return $response;
  }

  /**
   * Build the HTTP Request Statistics postbox.
   * @param Component $base Postbox's base element to add children to.
   * @param Postbox $postbox Postbox The box.
   */
  public static function build_http_statistics( Component $base, Postbox\Postbox $postbox ) {
    $base->add_child(Template::paragraph(__('These monthly reports are generated from the HTTP access logs of your site. All HTTP requests for the site are included, with traffic from both humans and bots. Requests blocked at the firewall level (for example during a DDOS attack) are not logged. The log files can also be accessed directly on the server at <code>/data/slog/html/goaccess-*.html</code>.', 'seravo')));
    $base->add_child($postbox->get_ajax_handler('http-request-statistics')->get_component());
  }

  /**
   * AJAX function for HTTP Request Statistics postbox.
   * @return \Seravo\Ajax\AjaxResponse
   */
  public static function get_http_statistics() {
    $response = new AjaxResponse();
    $reports = glob('/data/slog/html/goaccess-*.html');

    if ( $reports !== array() ) {
      $column_titles = array( __('Month', 'seravo'), __('HTTP Requests', 'seravo'), __('Report', 'seravo') );

      // Track max request value to calculate relative bar widths
      $max_requests = 0;
      $months = array();

      foreach ( array_reverse($reports) as $report ) {
        $total_requests_string = exec("grep -oE 'total_requests\": ([0-9]+),' {$report}");
        preg_match('/(\d+)/', $total_requests_string, $total_requests_match);
        $total_requests = (int) $total_requests_match[1];
        if ( $total_requests > $max_requests ) {
          $max_requests = $total_requests;
        }

        $month = substr($report, 25, 7);
        $stats_link = 'tools.php?x-accel-redirect&report=' . $month . '.html';
        $min_width = ($max_requests > 0 ? $total_requests / $max_requests * 100 : 1);

        $months[] = array(
          'month' => Template::link($month, $stats_link, $month, 'link')->to_html(),
          'requests' => '<div class="statistics" style="min-width: ' . $min_width . '%;">' . $total_requests . '</div>',
          'span' => Template::button_link_with_icon($stats_link, __('View report', 'seravo'))->to_html(),
        );
      }

      $output = Template::table_view('widefat striped', 'th', 'td', $column_titles, $months)->to_html();
    } else {
      $output = Template::error_paragraph(__('The site has no HTTP requests statistics yet.', 'seravo'))->to_html();
    }

    $response->is_success(true);
    $response->set_data(
      array(
        'output' => $output,
      )
    );

    return $response;
  }

  /**
   * Build the site-info postbox.
   * @param Component $base Postbox's base element to add children to.
   * @param Postbox $postbox Postbox The box.
   * @param mixed $data Data returned by data func.
   */
  public static function build_site_info( Component $base, Postbox\Postbox $postbox, $data ) {
    if ( isset($data['error']) ) {
      $base->add_child(Template::error_paragraph($data['error']));
      return;
    }

    $base->add_children(
      array(
        isset($data['site_name']) ? Template::paragraph($data['site_name']) : null,
        isset($data['site_created']) ? Template::paragraph($data['site_created']) : null,
        isset($data['termination']) ? Template::paragraph($data['termination']) : null,
        isset($data['country']) ? Template::paragraph($data['country']) : null,
        isset($data['plan_type']) ? Template::paragraph($data['plan_type']) : null,
        isset($data['account_manager']) ? Template::paragraph($data['account_manager']) : null,
        isset($data['contacts']) ? Template::paragraph($data['contacts']) : null,
      )
    );
  }

  /**
   * Fetch the plan details. This is a data func for site-info postbox.
   * @return array<string, mixed>
   */
  public static function get_site_info() {
    $info = \Seravo\Upkeep::seravo_admin_get_site_info();

    if ( is_wp_error($info) ) {
      $data['error'] = __('An API error occured. Please try again later', 'seravo');
      error_log($info->get_error_message());
      return $data;
    }

    $plans = array(
      'demo'       => __('Demo', 'seravo'),
      'mini'       => __('WP Mini', 'seravo'),
      'pro'        => __('WP Pro', 'seravo'),
      'business'   => __('WP Business', 'seravo'),
      'enterprise' => __('WP Enterprise', 'seravo'),
    );
    $countries = array(
      'fi'       => __('Finland', 'seravo'),
      'se'       => __('Sweden', 'seravo'),
      'de'       => __('Germany', 'seravo'),
      'us'       => __('USA', 'seravo'),
      'anywhere' => __('No preference', 'seravo'),
    );

    // These values always exists
    $data = array(
      'site_name' => __('Site Name') . ': ' . $info['name'],
      'site_created' => __('Site Created') . ': ' . date('Y-m-d', strtotime($info['created'])),
      'plan_type' => __('Plan Type') . ': ' . $plans[$info['plan']['type']],
    );

    // Check for account manger
    if ( isset($info['account_manager']) ) {
      $data['account_manager'] = __('Account Manager', 'seravo') . ': ' . $info['account_manager'];
    } else {
      $data['account_manager'] = __('No Account Manager found. Account Manager is only included in Seravo Enterprise plans.', 'seravo');
    }
    // Check for termination date (hide 1970-01-01)
    if ( ! empty($info['termination']) ) {
      if ( date('Y-m-d', strtotime($info['termination'])) !== '1970-01-01' ) {
        $data['termination'] = __('Plan Termination', 'seravo') . ': ' . date('Y-m-d', strtotime($info['termination']));
      }
    }
    // Check for location
    if ( ! empty($info['country']) ) {
      $data['country'] = __('Site Location', 'seravo') . ': ' . $countries[$info['country']];
    }
    // Check for contacts
    $contacts = isset($info['contact_emails']) ? implode(', ', $info['contact_emails']) : __('No contacts found', 'seravo');
    $data['contacts'] = '<a href="tools.php?page=upkeep_page#contacts">' . __('Technical Contacts', 'seravo') . '</a>: ' . $contacts;

    return $data;
  }

  public static function seravo_shadows_postbox() {
    ?>
    <div class="seravo-section">
      <div>
        <p><?php _e('Manage the site shadows.', 'seravo'); ?></p>
        <p><?php _e('<strong>Warning: </strong>Resetting a shadow copies the state of the production site to the shadow. All files under <code>/data/wordpress/</code> will be replaced and the production database imported. For more information, visit our  <a href="https://seravo.com/docs/deployment/shadows/" target="_BLANK">Developer documentation</a>.', 'seravo'); ?></p>
      </div>
      <div>
        <?php
        // Get a list of site shadows
        $api_query = '/shadows';
        $shadow_list = API::get_site_data($api_query);
        if ( is_wp_error($shadow_list) ) {
          die($shadow_list->get_error_message());
        }
        ?>
        <!-- Alerts after shadow reset -->
        <div class="alert" id="alert-success">
          <button class="closebtn">&times;</button>
          <p><?php _e('Success!', 'seravo'); ?></p>
          <!-- Search-replace info -->
          <div class="shadow-reset-sr-alert alert">
            <p><?php _e('Because this shadow uses a custom domain, <strong>please go to the shadow and run search-replace there with the values below</strong> for the shadow to be accessible after reset: ', 'seravo'); ?></p>
            <p>
              <?php
                _e('<strong>From:</strong> ', 'seravo');
                echo str_replace(array( 'https://', 'http://' ), '://', get_home_url());
                _e('<br><strong>To:</strong> ', 'seravo');
              ?>
              ://<span id="shadow-primary-domain"></span>
            </p>
            <p><?php _e('When you\'re in the shadow, run search-replace either on "Tools --> Database --> Search-Replace Tool" or with wp-cli. Instructions can be found from <a href="https://help.seravo.com/en/docs/151" target="_BLANK">documentation</a>.', 'seravo'); ?></p>
          </div>
        </div>
        <div class="alert" id="alert-failure"><button class="closebtn">&times;</button><p><?php _e('Failure!', 'seravo'); ?></p></div>
        <div class="alert" id="alert-timeout"><button class="closebtn">&times;</button><p><?php _e('The shadow reset is still running on the background. You should check the status of the shadow after a few minutes. If there are problems with the shadow, see the documentation from the link above.', 'seravo'); ?></p></div>
        <div class="alert" id="alert-error"><button class="closebtn">&times;</button><p><?php _e('Error!', 'seravo'); ?></p></div>
        <?php
        if ( ! empty($shadow_list) ) {
          ?>
          <table id="shadow-table">
            <?php
            foreach ( $shadow_list as $shadow_data ) {
              $primary_domain = '';
              // Find primary domain of the shadow
              foreach ( $shadow_data['domains'] as $domain ) {
                $primary_domain = $domain['primary'] === $shadow_data['name'] ? $domain['domain'] : '';
              }
            ?>
              <!-- Two rows per shadow: by default, one visibe and another hidden -->
              <tbody id="<?php echo ($shadow_data['name']); ?>">
                <tr class="view" >
                  <td class="open-folded"><?php echo $shadow_data['name']; ?></td>
                  <td><button class="button reset"><?php _e('Reset', 'seravo'); ?></button></td>
                  <td class="open-folded" id="shadow-reset-status"></td>
                  <td class="open-folded closed-icon"><span></span></td>
                </tr>
                <tr class="fold">
                  <td colspan="4">
                    <!-- More info of the shadow -->
                    <p><?php _e('Port: ', 'seravo'); ?> <?php echo $shadow_data['ssh']; ?></p>
                    <p><?php _e('Creation Date: ', 'seravo'); ?> <?php echo $shadow_data['created']; ?></p>
                    <p><?php _e('Information: ', 'seravo'); ?> <?php echo $shadow_data['info']; ?></p>
                    <p><?php _e('Domain: ', 'seravo'); ?> <?php echo (empty($primary_domain) ? '-' : $primary_domain); ?></p>
                    <!-- Search-replace info -->
                    <form>
                      <div class="shadow-reset-sr-alert shadow-hidden">
                        <p><?php _e('This shadow uses a custom domain. <strong>For the shadow to be accessible after reset, please run search-replace in the shadow with the values below:</strong>', 'seravo'); ?></p>
                        <p>
                          <?php
                            _e('<strong>From:</strong> ', 'seravo');
                            echo str_replace(array( 'https://', 'http://' ), '://', get_home_url());
                            _e('<br><strong>To:</strong> ://', 'seravo');
                            echo $primary_domain;
                          ?>
                        </p>
                        <p><?php _e('When you\'re in the shadow, you can run search-replace either on "Tools --> Database --> Search-Replace Tool" or with wp-cli. Instructions can be found from <a href="https://help.seravo.com/en/docs/151" target="_BLANK">documentation</a>.', 'seravo'); ?></p>
                      </div>
                      <input type="hidden" name="shadow-reset-production" value="<?php echo str_replace(array( 'https://', 'http://' ), '://', get_home_url()); ?>">
                      <input type="hidden" name="shadow-domain" value="<?php echo ($primary_domain); ?>">
                      <table class="shadow-rs-table shadow-hidden">
                        <tr><td><input type="text" name="shadow-reset-sr-from" disabled></td></tr>
                        <tr><td><input type="text" name="shadow-reset-sr-to" disabled></td></tr>
                      </table>
                    </form>
                  </td>
                </tr>
              </tbody>
              <?php
            }
            ?>
          </table>
          <?php
        } else {
          ?>
          <p style="padding: 15px 15px 0 15px;">
            <?php _e('No shadows found. If your plan is WP Pro or higher, you can request a shadow instance from Seravo admins at <a href="mailto:help@seravo.com">help@seravo.com</a>.', 'seravo'); ?>
          </p>
          <?php
        }
        ?>
      </div>
    </div>
    <?php
  }

  public static function seravo_data_integrity() {
    ?>
    <h3>
      <?php _e('WordPress core', 'seravo'); ?>
    </h3>
    <div class="wp_core_verify_loading">
      <img src="/wp-admin/images/spinner.gif">
    </div>
    <pre id="wp_core_verify"></pre>
    <h3>Git</h3>
    <div class="git_status_loading">
      <img src="/wp-admin/images/spinner.gif">
    </div>
    <pre id="git_status"></pre>
    <?php
  }

  public static function check_default_settings() {
    // Set the default settings for the user if the settings don't exist in database
    if ( get_option('seravo-image-max-resolution-width') === false || get_option('seravo-image-max-resolution-height') === false ) {
      update_option('seravo-image-max-resolution-width', self::$max_width_default);
      update_option('seravo-image-max-resolution-height', self::$max_height_default);
    }
    if ( get_option('seravo-enable-optimize-images') === false ) {
      update_option('seravo-enable-optimize-images', '');
    }
    if ( get_option('seravo-enable-strip-image-metadata') === false ) {
      update_option('seravo-enable-strip-image-metadata', '');
    }
    if ( get_option('seravo-enable-sanitize-uploads') === false ) {
      update_option('seravo-enable-sanitize-uploads', 'off');
    }

  }

  public static function seravo_image_max_width_field() {
    $image_max_width = get_option('seravo-image-max-resolution-width');
    echo '<input type="number" class="' . self::get_input_field_attributes()[0] . '" name="seravo-image-max-resolution-width"' . self::get_input_field_attributes()[1] . '
      placeholder="' . __('Width', 'seravo') . '" value="' . $image_max_width . '">';
  }

  public static function seravo_image_max_height_field() {
    $image_max_height = get_option('seravo-image-max-resolution-height');
    echo '<input type="number" class="' . self::get_input_field_attributes()[0] . '" name="seravo-image-max-resolution-height" ' . self::get_input_field_attributes()[1] . ' placeholder="'
      . __('Height', 'seravo') . '" value="' . $image_max_height . '">';
  }

  public static function seravo_image_enabled_field() {
    echo '<input type="checkbox" name="seravo-enable-optimize-images" id="enable-optimize-images" ' . checked('on', get_option('seravo-enable-optimize-images'), false) . '>';
  }

  public static function seravo_image_metadata_enabled_field() {
    echo '<input type="checkbox" name="seravo-enable-strip-image-metadata" id="enable-strip-image-metadata" ' . checked('on', get_option('seravo-enable-strip-image-metadata'), false) . '>';
  }

  public static function optimize_images_settings_description() {
    echo '<p>' . __('Optimization reduces image file size. This improves the performance and browsing experience of your site.', 'seravo') . '</p>' .
      '<p>' . __('By setting the maximum image resolution, you can determine the maximum allowed dimensions for images.', 'seravo') . '</p>' .
      '<p>' . __('By enabling metadata stripping, you can further reduce image sizes by removing metadata. Please note that occasionally metadata can be useful.', 'seravo') . '</p>' .
      '<p>' . __('For further information, refer to our <a href="https://help.seravo.com/article/28-seravo-plugin-optimize-images" target="_BLANK">knowledgebase article</a>.', 'seravo') . '</p>';
  }

  /**
   * @return int|mixed
   */
  public static function sanitize_image_width( $width ) {
    if ( get_option('seravo-enable-optimize-images') === 'on' && $width !== null ) {
      if ( ! is_numeric($width) || $width < self::$min_width ) {
        add_settings_error(
          'optimize_images_error',
          'invalid-width',
          sprintf(
            // translators: %s numeric value for the minimum image width
            __('The minimum width for image optimisation is %1$s px. Setting suggested width of %2$s px.', 'seravo'),
            self::$min_width,
            self::$max_width_default
          )
        );
        return self::$max_width_default;
      }
    }
    // A settings error for succesful settings change
    add_settings_error(
      'optimize_images_error',
      'optimize_images_width_ok',
      __('Width setting saved', 'seravo'),
      'success'
    );
    return $width;
  }

  /**
   * @return int|mixed
   */
  public static function sanitize_image_height( $height ) {
    if ( get_option('seravo-enable-optimize-images') === 'on' && $height !== null ) {
      if ( ! is_numeric($height) || $height < self::$min_height ) {
        add_settings_error(
          'optimize_images_error',
          'invalid-height',
          // translators: %s numeric value for the minimum image height
          sprintf(__('The minimum height for image optimisation is %1$s px. Setting suggested height of %2$s px.', 'seravo'), self::$min_height, self::$max_height_default)
        );
        return self::$max_height_default;
      }
    }
    // A settings error for succesful settings change
    add_settings_error(
      'optimize_images_error',
      'optimize_images_height_ok',
      __('Height setting saved', 'seravo'),
      'success'
    );
    return $height;
  }

  /**
   * @return string[]
   */
  public static function get_input_field_attributes() {
    if ( get_option('seravo-enable-optimize-images') === 'on' ) {
      return array( 'max-resolution-field', '' );
    }
    return array( 'max-resolution-field', 'disabled=""' );
  }

  public static function optimize_images_postbox() {
    settings_errors('optimize_images_error');
    echo '<form method="post" action="options.php" class="seravo-general-form">';
    settings_fields('seravo-optimize-images-settings-group');
    do_settings_sections('optimize_images_settings');
    submit_button(__('Save', 'seravo'), 'primary', 'btnSubmitOptimize');
    echo '</form>';
  }

  public static function register_sanitize_uploads_settings() {
    add_settings_section(
      'seravo-sanitize-uploads-settings',
      '',
      array( __CLASS__, 'sanitize_uploads_description' ),
      'sanitize_uploads_settings'
    );

    register_setting(
      'seravo-sanitize-uploads-settings-group',
      'seravo-enable-sanitize-uploads',
      array(
        'sanitize_callback' => function ( $setting ) {
          // A settings error for succesful settings change
          add_settings_error(
            'sanitize_uploads_error',
            'sanitize_uploads_ok',
            __('Settings saved', 'seravo'),
            'success'
          );
          return $setting;
      },
      )
    );

    add_settings_field(
      'seravo-sanitize-uploads-enabled-field',
      __('Sanitize uploads', 'seravo'),
      array( __CLASS__, 'seravo_sanitize_uploads_enabled_field' ),
      'sanitize_uploads_settings',
      'seravo-sanitize-uploads-settings'
    );
  }

  public static function sanitize_uploads() {
    settings_errors('sanitize_uploads_error');
    echo '<form method="post" action="options.php" class="seravo-general-form">';
    settings_fields('seravo-sanitize-uploads-settings-group');
    do_settings_sections('sanitize_uploads_settings');
    submit_button(__('Save', 'seravo'), 'primary', 'btnSubmitSanitize');
    echo '</form>';
  }

  public static function sanitize_uploads_description() {
    echo '<p>' . __('Special characters in filenames, such as ä, ö or æ, may cause problems with the site.', 'seravo') . '</p>';
    echo '<p>' . __('Toggling this on replaces such characters with other standard letters, like ä -> a, ö -> o or æ -> a when a file is uploaded.', 'seravo') . '</p>';
  }

  public static function seravo_sanitize_uploads_enabled_field() {
    echo '<input type="checkbox" name="seravo-enable-sanitize-uploads" id="seravo-enable-sanitize-uploads" ' . checked('on', get_option('seravo-enable-sanitize-uploads'), false) . '>';
  }

  public static function speed_test() {
    $target_location = isset($_GET['speed_test_target']) ? $_GET['speed_test_target'] : '';
    echo ('<p>' . __('Speed test measures the time how long it takes for PHP to produce the HTML output for the WordPress page.', 'seravo') . '</p>');
    echo('<br><label for="speed_test_url" class="speed_test_form" for="sr-from"> ' . get_home_url() . '/</label> <input class="speed_test_input" type="text" placeholder="' . __('Front Page by Default', 'seravo') . '" id="speed_test_url" value="' . $target_location . '"><br>');
    echo('<button type="button" class="button-primary" id="run-speed-test">' . __('Run Test', 'seravo') . '</button>');
    echo('<div id="speed-test-results"></div>');
    echo('<div id="speed-test-error"></div>');
  }

  /**
   * AJAX function for Site checks postbox.
   * @return \Seravo\Ajax\AjaxResponse
   */
  public static function run_site_checks() {
    $response = new AjaxResponse();
    $results = Site_Health::check_site_status(true);
    $output = $results[0];
    $title = $results[1];
    $status_color = $results[2];

    $response->is_success(true);
    $response->set_data(
      array(
        'output' => $output,
        'title' => $title,
        'color' => $status_color,
      )
    );

    return $response;
  }
}
