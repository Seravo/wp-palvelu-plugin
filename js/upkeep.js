// phpcs:disable PEAR.Functions.FunctionCallSignature
'use strict';
//Email script
function generateButtons (emails) {
  var html = [];

  if ( ! emails ) {
    return;
  }

  emails.forEach(function (email) {
    if (email) {
      html.push('<button type="button" class="button email-button"><span class="email-button-content">' + email + '</span><span class="delete-icon dashicons dashicons-no"></span></button>')
    }
  });
  jQuery('[name="technical_contacts"]').val(emails.join(', '))
  return html.join(' ');
}

function validateEmail ($emailInput) {
  var $form = jQuery('[name="seravo_updates_form"]');
  var regex = /^([ÆØÅæøåõäöüa-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,4})$/;
  var result = regex.test($emailInput.val());
  if ( ! result ) {
    $form.addClass('has-errors');
  } else {
    $form.removeClass('has-errors');
  }
  return result;
}

jQuery(document).ready(function($) {
  var $emailInput = jQuery('.technical_contacts_input');
  var emails = $emailInput.data('emails');
  var $buttonsDiv = jQuery('.technical_contacts_buttons');
  $buttonsDiv.html(generateButtons(emails));

  // Disable form submit using enter
  jQuery('[name="seravo_updates_form"]').on('keyup keypress', function(event) {
    var keyCode = event.keyCode || event.which;
    if (keyCode === 13) {
      event.preventDefault();
      return false;
    }
  });

  jQuery('#slack_webhook_test').click(function() {
    $.ajax({
      data: 'payload=' + JSON.stringify({"text": "Seravo update notification test sent from " + window.location.href }),
      dataType: 'json',
      processData: false,
      type: 'POST',
      url: document.getElementsByName('slack_webhook')[0].value
    });
  });

  function insertEmail() {
    if (validateEmail($emailInput)) {
      emails.push($emailInput.val())
      $buttonsDiv.html(generateButtons(emails));
      $emailInput.val("");
    }
  }

  $emailInput.keypress(function (event) {
    if (event.which == 13) {
      insertEmail();
      event.preventDefault();
      return false;
    }
  });

  jQuery('.technical_contacts_add').click(function() {
    insertEmail();
  });

  jQuery('.technical_contacts_buttons').on('click', '.email-button', function () {
    var text = jQuery(this).find('.email-button-content').html();
    emails.splice(jQuery.inArray(text, emails), 1);
    $buttonsDiv.html(generateButtons(emails));

    if (emails.length === 0) {
      $buttonsDiv.html("<p style='color:red;'>" + seravo_ajax_l10n.email_fail + "</p>");
    }
  });

});
