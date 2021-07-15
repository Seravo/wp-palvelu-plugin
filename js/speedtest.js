// phpcs:disable PEAR.Functions.FunctionCallSignature
'use strict';

// Number of curl commands the speed test uses for both cached and non-cached
const speedNumberOfTests = 10;
// Speed test data for the chart
let speedData = [];
// Speed test data for the chart
let speedDataCached = [];
// Visual chart of the speed test
let speedChart;

jQuery(document).ready(function ($) {
  // Check if there is a 'speed_test_target' key in the url
  let searchParams = new URLSearchParams(window.location.search);
  if (searchParams.has('speed_test_target')) {
    run_speed_test();
  }

  $('#speed-test-button').click(run_speed_test);
  $('#clear-url').click(
    function() {
      $("#speed-test-url").val('');
  });

  function run_speed_test() {
    $("#speed-test-url").attr("disabled", true);
    $("#speed-test-error").empty();
    let loc = $("#speed-test-url").val();

    // Init the chart and the data
    let speedChartData = generate_speed_test_chart(speedChart, speedData, speedDataCached);
    speedChart = speedChartData['chart'];
    speedData = speedChartData['data'];
    speedDataCached = speedChartData['data_cached'];

    let data = {
      cached: false,
    }
    // Use the specified page / URL for speed test
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

    // speed_test runs up to speedNumberOfTests of ajax calls for both cached and non-cached curl speed tests
    function speed_test() {

      function on_success(response) {
        if (! data.cached) {
          testResult = Math.round(response.data['starttransfer_time'] * 1000);
          latencySum += testResult;
          speedData.push(testResult);
        } else {
          cachedTestResult = Math.round(response.data['starttransfer_time'] * 1000);
          latencySumCached += cachedTestResult;
          speedDataCached.push(cachedTestResult);
          speedChart.appendData([{ data: testResult }, { data: cachedTestResult }]);
        }
        if (data.cached) {
          numberOfTest++;
        }
        data.cached = ! data.cached;
        numberOfTest < speedNumberOfTests ? setTimeout(() => { execute(); }, 100) : end_speed_test(latencySum, latencySumCached);
      }

      function on_error(error) {
        // End the test on HTTP / invalid domain error
        $("#speed-test-error").html('<b>' + error + '</b>');
        numberOfTest = speedNumberOfTests;
        end_speed_test(latencySum, latencySumCached);
      }

      function execute() {
        seravo_ajax_request('post', 'speed-test', 'speed-test', on_success, on_error, data);
        $("#speed-test-button").prop('disabled', true);
      }

      execute();
    }
  }

  // At the end of the speed test add average value annotations to the speed test chart
  function end_speed_test(latencySum, latencySumCached) {
    $("#speed-test-button").prop('disabled', false);
    $("#speed-test-url").attr("disabled", false);

    let latency = Math.round(latencySum / speedNumberOfTests);
    let cachedLatency = Math.round(latencySumCached / speedNumberOfTests);
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
        text: seravo_charts_l10n.avg_latency + latency
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
        text: seravo_charts_l10n.avg_cached_latency + cachedLatency
      }
    });
  }
});
