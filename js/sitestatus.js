// phpcs:disable PEAR.Functions.FunctionCallSignature
'use strict';

(function($) {
  $(window).on('load', function() {
    $('#enable-optimize-images').click(function() {
      $('.max-resolution-field').each(function() {
        $(this).prop( "disabled", ! $(this).prop( "disabled" ) );
      });
    });
  });
})(jQuery);

jQuery(document).ready(function($) {
  // Speed test data for the chart
  let speedData = [];
  // Speed test data for the chart
  let speedDataCached = [];
  // Visual chart of the speed test
  let speedChart;
  // Number of curl commands the speed test uses for both cached and non-cached
  const speedNumberOfTests = 10;

  $('#run-speed-test').click(run_speed_test);

  function run_speed_test() {
    $("#speed_test_url").attr("disabled", true);
    $("#run-speed-test").prop('disabled', true);

    // If an instance of the chart already exists, wipe it out
    if (speedChart instanceof ApexCharts) {
      speedChart.destroy();
      speedData = [];
      speedDataCached = [];
    }

    var options = {
      series: [{
          name: seravo_site_status_loc.latency,
          data: speedData,
        },
        {
          name: seravo_site_status_loc.cached_latency,
          data: speedDataCached,
        }
      ],
      chart: {
        type: 'line',
        height: 350,
        zoom: {
          enabled: false
        }
      },
      plotOptions: {
        bar: {
          horizontal: false,
          endingShape: 'flat',
        },
      },
      dataLabels: {
        enabled: false
      },
      stroke: {
        show: true,
        width: 2,
        curve: 'smooth'
      },
      yaxis: {
        title: {
          text: 'ms'
        },
        min: 0
      },
      xaxis: {
        min: 1,
        max: speedNumberOfTests,
        tickAmount: speedNumberOfTests,
        tickPlacement: 'on'
      },
      fill: {
        opacity: 1
      },
      tooltip: {
        y: {
          formatter: function(val) {
            return val + " ms"
          }
        }
      },
      legend: {
        onItemClick: {
          toggleDataSeries: false
        },
      }
    };

    speedChart = new ApexCharts(document.querySelector("#speed-test-results"), options);
    speedChart.render();

    let data = {
      action: 'seravo_speed_test',
      nonce: seravo_site_status_loc.ajax_nonce,
      cached: false,
    }

    let loc = $("#speed_test_url").val();
    if (loc.length != 0) {
      data.location = loc;
    }

    // Number of tests done
    let numberOfTest = 0;

    // Sum of latencies for calculating average
    let latencySum = 0;
    // Sum of cached latencies for calculating average
    let latencySumCached = 0;
    // Result of a single speed test curl
    let testResult = 0;
    // Result of a single cached speed test curl
    let cachedTestResult = 0;
    speed_test();
    // speed_test runs up to speedNumberOfTests of ajax calls for both cached
    // and non-cached curl speed tests
    function speed_test() {
      $.post(seravo_site_status_loc.ajaxurl, data,
        function(response) {
          if (response.success) {
            if (! data.cached) {
              testResult = Math.round(response.data[4] * 1000);
              latencySum += testResult;
              speedData.push(testResult);
            } else {
              cachedTestResult = Math.round(response.data[4] * 1000);
              latencySumCached += cachedTestResult;
              speedDataCached.push(cachedTestResult);
              speedChart.appendData([
                {data: testResult},
                {data: cachedTestResult}
              ]);
            }
          } else {
            // If we're getting erorr in HTTP code, end the speed test
            $("#speed-test-error").empty();
            $("#speed-test-error").append(response.data + '<br>');
            numberOfTest = speedNumberOfTests;
          }
      }).fail(function(response) {
          // If we're getting erorr in HTTP code, end the speed test
          $("#speed-test-error").empty();
          $("#speed-test-error").append(response.status + '<br>');
          numberOfTest = speedNumberOfTests;
      }).always(function() {
          // Add to number of tests done after doing cached test
          if (data.cached) {
            numberOfTest++;
            }
          data.cached = ! data.cached;
          // setTimeOut is here to slow down the progress of the tests to avoid 429 HTTP response
          numberOfTest < speedNumberOfTests ? setTimeout(() => { speed_test(); }, 100) : end_speed_test(latencySum, latencySumCached);
      });
    }
  }

  // At the end of the speed test add average value annotations to the speed test chart
  function end_speed_test(latencySum, latencySumCached) {
    let latency = Math.round(latencySum/ speedNumberOfTests);
    let cachedLatency = Math.round(latencySumCached/ speedNumberOfTests);
    $("#run-speed-test").prop('disabled', false);
    $("#speed_test_url").attr("disabled", false);

    let latencyOffset = -4;
    let cachedOffset = -4;
    // Adjust one of the annotations to be under the line to make sure they don't overlap
    latency > cachedLatency ? cachedOffset = 14 : latencyOffset = 14;

    speedChart.addYaxisAnnotation({
      y: latency,
      borderColor: '#00B1F2',
      label: {
          borderColor: '#00B1F2',
          offsetY: latencyOffset,
          style: {
          color: '#000',
          background: '#00B1F2'
          },
          text: seravo_site_status_loc.avg_latency + latency
      }
    });

    speedChart.addYaxisAnnotation({
      y: cachedLatency,
      borderColor: '#00E396',
      label: {
          borderColor: '#00E396',
          offsetY: cachedOffset,
          style: {
          color: '#000',
          background: '#00E396'
          },
          text: seravo_site_status_loc.avg_cached_latency + cachedLatency
        }
    });
  }

  function seravo_load_report(section) {
    jQuery.post(seravo_site_status_loc.ajaxurl, {
      'action': 'seravo_ajax_site_status',
      'section': section,
      'nonce': seravo_site_status_loc.ajax_nonce,
    }, function(rawData) {
      if (rawData.length == 0) {
        jQuery('#' + section).html(seravo_site_status_loc.no_data);
      }
      jQuery('.' + section + '_loading').fadeOut();
    }).fail(function() {
      jQuery('.' + section + '_loading').html(seravo_site_status_loc.failed);
    });
  }
  seravo_load_report('wp_core_verify');
  seravo_load_report('git_status');

  $('.reset').click(function(event) {
    var shadow_id = $(this).closest('tbody').attr('id');
    var is_user_sure = confirm(seravo_site_status_loc.confirm);
    if ( ! is_user_sure) {
      return;
    }
    // Hide any old alert messages
    $('.alert').hide();
    seravo_ajax_reset_shadow(shadow_id,
      function( status ) {
        if ( status == 'progress' ) {
          event.target.disabled = true;
          // Select only row with a certain shadow id
          $('#' + shadow_id).find('#shadow-reset-status').html("<img src=\"/wp-admin/images/spinner.gif\">");
        } else if ( status == 'success' ) {
          $('#' + shadow_id).find('#shadow-reset-status').empty();
          $('.alert#alert-success').show();
          $('.closebtn').show();
          // Check if the shadow has domains and show instructions to search-replace domain if necessary
          var shadow_domain = $('#' + shadow_id).find('input[name=shadow-domain]').val();
          if ( ! (shadow_domain.length === 0) ) {
            $('.alert#alert-success').find('.shadow-reset-sr-alert').show();
            $('#shadow-primary-domain').text(shadow_domain);
          }
        } else if ( status == 'failure' ) {
          $('#' + shadow_id).find('#shadow-reset-status').empty();
          $('.alert#alert-failure').show();
          $('.closebtn').show();
        } else if ( status = 'timeout') {
          $('#' + shadow_id).find('#shadow-reset-status').empty();
          $('.alert#alert-timeout').show();
          $('.closebtn').show();
        } else {
          $('#' + shadow_id).find('#shadow-reset-status').empty();
          $('.alert#alert-error').show();
          $('.closebtn').show();
        }
      }
    );
  });

  $('.closebtn').click(function() {
    $(this).parent().hide();
  });

  // Open/fold the row containing additional information
  $('#shadow-table td.open-folded').on('click', function() {
    // Get shadow id of the row's shadow
    var shadow_id = $(this).closest('tbody').attr('id');
    if ($('#' + shadow_id).find('.fold').hasClass('open')) {
      // Fold row and turn icon
      $('#' + shadow_id).find('.fold').removeClass('open');
      $('#' + shadow_id + ' td.open-icon').addClass('closed-icon').removeClass('open-icon');
    } else {
      // Open row and turn icon
      $('#' + shadow_id).find('.fold').addClass('open');
      $('#' + shadow_id + ' td.closed-icon').addClass('open-icon').removeClass('closed-icon');
      // Get production domain and shadow domain
      var shadow_domain = $('#' + shadow_id).find('input[name=shadow-domain]').val();
      // Show the right message depending whether the shadow needs search-replace after reset or not
      // (whether it has domain or not)
      if ( ! (shadow_domain.length === 0)) {
        $('#' + shadow_id).find('.shadow-reset-sr-alert').removeClass('shadow-hidden');
      }
    }
  });

  function seravo_ajax_reset_shadow(shadow, animate) {
    animate('progress');
    $.post(
      seravo_site_status_loc.ajaxurl,
      { type: 'POST',
        'action': 'seravo_ajax_site_status',
        'section': 'seravo_reset_shadow',
        'shadow': shadow,
        'nonce': seravo_site_status_loc.ajax_nonce, },
      function( rawData ) {
        var data = JSON.parse(rawData);
        // If the last row of rawData does not begin with SUCCESS:
        if ( data[data.length - 1].search('Success') ) {
          animate('success');
        } else {
          animate('failure');
        }
      }
      ).fail(function(msg) {
        if (msg.status === 503) {
          animate('timeout');
        } else {
          animate('error');
        }
      });
  }

  // Check if there is a 'speed_test_target' key in the url
  let searchParams = new URLSearchParams(window.location.search);
  if (searchParams.has('speed_test_target')) {
    run_speed_test();
  }
});
