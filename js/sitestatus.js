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

  function seravo_load_report(section) {
    jQuery.post(seravo_site_status_loc.ajaxurl, {
      'action': 'seravo_ajax_site_status',
      'section': section,
      'nonce': seravo_site_status_loc.ajax_nonce,
    }, function(rawData) {
      if (rawData.length == 0) {
        jQuery('#' + section).html(seravo_site_status_loc.no_data);
      }

      if (section === 'front_cache_status') {
        var data = JSON.parse(rawData);
        var data_joined = data['test_result'].join("\n");

        if ( data['success'] === true ) {
          jQuery('.seravo_cache_tests_status').fadeIn('slow', function() {
            jQuery(this).html(seravo_site_status_loc.cache_success).fadeIn('slow');
          });
          jQuery('.seravo-cache-test-result-wrapper').css('border-left', 'solid 0.5em #038103');
        } else {
          jQuery('.seravo_cache_tests_status').fadeIn('slow', function() {
            jQuery(this).html(seravo_site_status_loc.cache_failure).fadeIn('slow');
          });
          jQuery('.seravo-cache-test-result-wrapper').css('border-left', 'solid 0.5em #e74c3c');
        }

        jQuery('#seravo_cache_tests').append(data_joined);
        jQuery('#run-cache-tests').prop('disabled', false);

        jQuery(this).fadeIn('slow', function() {
          jQuery('.seravo_cache_test_show_more_wrapper').fadeIn('slow');
        });
      } else if (section === 'redis_info') {
        var data = JSON.parse(rawData);
        var expired_keys = data[0];
        var evicted_keys = data[1];
        var keyspace_hits = data[2];
        var keyspace_misses = data[3];
        generateRedisHitChart(keyspace_hits, keyspace_misses);
        jQuery('#redis-expired-keys').text(expired_keys);
        jQuery('#redis-evicted-keys').text(evicted_keys);
      } else if (section === 'longterm_cache') {
        var data = JSON.parse(rawData);
        var hits = data[0];
        var misses = data[1];
        var stales = data[2];
        var bypasses = data[3];
        generateHTTPHitChart(hits, misses, stales);
        jQuery('#http-cache-bypass').text(bypasses);
      } else {
        var data = JSON.parse(rawData);
        jQuery('#' + section).text(data.join("\n"));
      }
      jQuery('.' + section + '_loading').fadeOut();
    }).fail(function() {
      jQuery('.' + section + '_loading').html(seravo_site_status_loc.failed);
    });
  }
  seravo_load_report('wp_core_verify');
  seravo_load_report('git_status');
  seravo_load_report('redis_info');
  seravo_load_report('longterm_cache');

  jQuery('#run-cache-tests').click(function() {
    jQuery('#seravo_cache_tests').html('');
    jQuery('.seravo-cache-test-result-wrapper').css('border-left', 'solid 0.5em #e8ba1b');
    jQuery('.seravo_cache_test_show_more_wrapper').hide();

    if ( jQuery('#seravo_arrow_cache_show_more').hasClass('dashicons-arrow-up-alt2') ) {
      jQuery('.seravo-cache-test-result').hide(function() {
        jQuery('#seravo_arrow_cache_show_more').removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
      });
    }

    jQuery('.seravo_cache_tests_status').fadeOut(400, function() {
      jQuery(this).html('<div class="front_cache_status_loading"><img src="/wp-admin/images/spinner.gif" style="display:inline-block"> ' + seravo_site_status_loc.running_cache_tests + '</div>').fadeIn(400);
    });

    jQuery(this).prop('disabled', true);
    seravo_load_report('front_cache_status');
  });

  jQuery('#enable-object-cache').click(function() {
    // Manage the props
    jQuery(this).prop('disabled', true);
    jQuery('.object_cache_loading').prop('hidden', false);

    jQuery.post(seravo_site_status_loc.ajaxurl, {
      'action': 'seravo_ajax_site_status',
      'section': 'object_cache',
      'nonce': seravo_site_status_loc.ajax_nonce,
    }, function (rawData) {
        var result = JSON.parse(rawData)

        if (result['success'] === true) {
        jQuery('.object_cache_loading').prop('hidden', true)
        jQuery('#enable-object-cache').remove()
        jQuery('#object_cache_warning').css('color', 'green')
        jQuery('#object_cache_warning').html(seravo_site_status_loc.object_cache_success)
        } else {
        jQuery('#object_cache_warning').html(seravo_site_status_loc.object_cache_failure)
        jQuery('.object_cache_loading').prop('hidden', true)
        }
    })
  });

  jQuery('.seravo_cache_test_show_more').click(function(event) {
    event.preventDefault();

    if ( jQuery('#seravo_arrow_cache_show_more').hasClass('dashicons-arrow-down-alt2') ) {
      jQuery('.seravo-cache-test-result').slideDown('fast', function() {
        jQuery('#seravo_arrow_cache_show_more').removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
      });
    } else if ( jQuery('#seravo_arrow_cache_show_more').hasClass('dashicons-arrow-up-alt2') ) {
      jQuery('.seravo-cache-test-result').hide(function() {
        jQuery('#seravo_arrow_cache_show_more').removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
      });
    }
  });

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
});
