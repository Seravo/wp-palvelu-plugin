<?php
/**
 * File for test postboxes page. No need to
 * translate anything here.
 */

namespace Seravo;

use \Seravo\Ajax;
use \Seravo\Postbox;
use \Seravo\Postbox\Component;
use \Seravo\Postbox\Template;
use \Seravo\Postbox\Toolpage;
use \Seravo\Postbox\Requirements;

class TestPage {

  /**
   * Initialize test-page.
   */
  public static function load() {
    $page = new Toolpage('tools_page_test_page');

    self::init_test_postboxes($page);

    $page->enable_ajax();
    $page->enable_charts();
    $page->register_page();
  }

  /**
   * Initialize test-page postboxes.
   * @param \Seravo\Postbox\Toolpage $page The page for postboxes.
   */
  public static function init_test_postboxes( Toolpage $page ) {
    /**
     * Polling test postbox
     */
    $poller_demo = new Postbox\SimpleForm('poller-test');
    $poller_demo->set_title('Polling Test');
    $poller_demo->set_ajax_func(array( __CLASS__, 'long_polling_operation' ));
    $poller_demo->set_requirements(array( Requirements::CAN_BE_ANY_ENV => true ));
    $poller_demo->add_paragraph('Click the button to test the poller. It will sleep for 65 seconds and restart Nginx.');
    $poller_demo->add_paragraph('See browsers dev-tools network tab to see what happens.');
    $poller_demo->set_spinner_text('This will take a while...');
    $poller_demo->set_button_text('Click me');
    $page->register_postbox($poller_demo);

    /**
     * Fancy form test postbox
     */
    $fancy_demo = new Postbox\FancyForm('fancy-form-test', 'side');
    $fancy_demo->set_title('FancyForm test');
    $fancy_demo->set_ajax_func(array( __CLASS__, 'fancy_form_test' ));
    $fancy_demo->set_requirements(array( Requirements::CAN_BE_ANY_ENV => true ));
    $fancy_demo->add_paragraph('Click the button to test the fancy form. The AJAX function executes <code>wp-test</code>.');
    $fancy_demo->set_spinner_text('Funtsaillaan for a while');
    $fancy_demo->set_title_text('Click the button NOW!');
    $fancy_demo->set_button_text('Click me');
    $page->register_postbox($fancy_demo);

    /**
     * Chart test postbox
     */
    $chart_demo = new Postbox\LazyLoader('chart-test');
    $chart_demo->set_title('Chart test');
    $chart_demo->set_requirements(array( Requirements::CAN_BE_ANY_ENV => true ));
    $chart_demo->add_paragraph('You should see a chart below.');
    $chart_demo->set_ajax_func(array( __CLASS__, 'chart_test' ));
    $page->register_postbox($chart_demo);

    /**
     * Chart test postbox
     */
    $nag_demo = new Postbox\Postbox('nag-test', 'side');
    $nag_demo->set_title('Nag test');
    $nag_demo->set_requirements(array( Requirements::CAN_BE_ANY_ENV => true ));
    $nag_demo->set_build_func(array( __CLASS__, 'build_nag_test' ));

    $purge_cache_btn = new Ajax\SimpleForm('nag-test');
    $purge_cache_btn->set_button_text('Purge Cache');
    $purge_cache_btn->set_spinner_flip(true);
    $purge_cache_btn->set_ajax_func(
      function() {
        // Use a proper function in real situation
        exec('wp-purge-cache');
        $response = new Ajax\AjaxResponse();
        $response->is_success(true);
        return $response;
      }
    );
    $purge_cache_btn2 = new Ajax\SimpleForm('nag-test-2');
    $purge_cache_btn2->set_button_text('Purge Cache');
    $purge_cache_btn2->set_spinner_flip(true);
    $purge_cache_btn2->set_ajax_func(
      function() {
        // Use a proper function in real situation
        exec('wp-purge-cache');
        $response = new Ajax\AjaxResponse();
        $response->is_success(true);
        $response->set_data(array( 'output' => Template::paragraph('This bar returned output :)')->to_html() ));
        return $response;
      }
    );

    $nag_demo->add_ajax_handler($purge_cache_btn);
    $nag_demo->add_ajax_handler($purge_cache_btn2);
    $page->register_postbox($nag_demo);
  }

  /**
   * AJAX function for testing polling. The function
   * sleeps for 65 seconds and restarts Nginx.
   * @return \Seravo\Ajax\AjaxResponse|mixed Response for long polling operation.
   */
  public static function long_polling_operation() {
    $polling = Ajax\AjaxHandler::check_polling();

    if ( $polling === true ) {
      // Done polling
      $response = new Ajax\AjaxResponse();
      $response->is_success(true);
      $response->set_data(
        array(
          'output' => '<hr><pre>' . file_get_contents('/data/log/nginx-restart-test.log') . '</pre><hr>',
        )
      );
      return $response;
    }

    if ( $polling === false ) {
      // Not polling yet
      $command = 'sleep 65 && wp-restart-nginx > /data/log/nginx-restart-test.log';
      $pid = Shell::backround_command($command);
      if ( $pid === false ) {
        return Ajax\AjaxResponse::exception_response();
      }
      return Ajax\AjaxResponse::require_polling_response($pid);
    }

    // Not done yet, keep polling
    return $polling;
  }

  /**
   * AJAX function for testing fancy form.
   * The function runs wp-test.
   * @return \Seravo\Ajax\AjaxResponse Response for wp-test.
   */
  public static function fancy_form_test() {
    $retval = null;
    $output = array();
    exec('wp-test', $output, $retval);

    if ( count($output) === 0 ) {
      return Ajax\AjaxResponse::command_error_response('wp-test');
    }

    $message = 'This is bad mmkay?';
    $status_color = Ajax\FancyForm::STATUS_RED;
    if ( count(preg_grep('/OK \(/i', $output)) >= 1 && $retval === 0 ) {
      // Success
      $message = "It's all good!";
      $status_color = Ajax\FancyForm::STATUS_GREEN;
    }

    $response = new Ajax\AjaxResponse();
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

  /**
   * AJAX function for charts. Just returns test data.
   * @return \Seravo\Ajax\AjaxResponse Response with chart data.
   */
  public static function chart_test() {
    $response = new Ajax\AjaxResponse();
    $response->is_success(true);
    $response->set_data(
      array(
        'random_data' => array(
          'First thing' => 30,
          'Second thing' => 50,
          'Third thing' => 20,
        ),
        'output' => '<div id="test-page-test-chart"></div>',
      )
    );
    return $response;
  }

  /**
   * Function for building nag-test postbox.
   * @param \Seravo\Postbox\Component $base    Component to build on.
   * @param \Seravo\Postbox\Postbox   $postbox The nag-test postbox.
   */
  public static function build_nag_test( Component $base, Postbox\Postbox $postbox ) {
    // Always true
    if ( random_int(0, 0) === 0 ) {
      $notice = new Component('', '<table><tr>', '</tr></table>');
      $notice->add_child(new Component('You have cache! Please purge it now!', '<td><b>', '</b></td>'));
      $notice->add_child($postbox->get_ajax_handler('nag-test')->get_component()->set_wrapper('<td>', '</td>'));
      $base->add_child(Template::nag_notice($notice, 'notice-error', true));

      $notice = new Component('', '<table><tr>', '</tr></table>');
      $notice->add_child(new Component('You have cache! Please purge it now! (2)', '<td><b>', '</b></td>'));
      $notice->add_child($postbox->get_ajax_handler('nag-test-2')->get_component()->set_wrapper('<td>', '</td>'));
      $base->add_child(Template::nag_notice($notice, 'notice-error', true));
    }

    $base->add_child(Template::paragraph('You should see two nags above. The second nag will return output.'));
  }

}
