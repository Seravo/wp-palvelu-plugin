<?php

namespace Seravo\Ajax;

use \Seravo\Postbox\Component;
use \Seravo\Postbox\Template;

/**
 * Class SimpleForm
 *
 * SimpleForm is pre-made AjaxHandler for building
 * forms with button for executing a function and dry-run.
 */
class SimpleForm extends AjaxHandler {

  /**
   * @var array|null  Callback for building the form.
   */
  private $build_form_func;

  /**
   * @var string|null Text to be shown on the main button.
   */
  private $button_text;

  /**
   * @var string|null Text to be shown on the dryrun button.
   */
  private $dryrun_button_text;

  /**
   * @var string|null Text to be shown next to the spinner.
   */
  private $spinner_text;

  /**
   * @var bool Whether the spinner and buttons should switch places.
   */
  private $flip_spinner = false;

  /**
   * Constructor for SimpleForm. Will be called on new instance.
   * @param string $section Unique section inside the postbox.
   */
  public function __construct( $section ) {
    parent::__construct($section);

    $this->set_build_func(
      function ( Component $base, $section ) {
        $this->build_component($base, $section);
      }
    );
  }

  /**
   * Component needed for the AJAX handler.
   * Can be gotten with get_component().
   * @param \Seravo\Postbox\Component $base    Base component.
   * @param string                    $section Unique section inside the postbox.
   */
  public function build_component( Component $base, $section ) {
    $form = new Component();
    if ( $this->build_form_func !== null ) {
      \call_user_func($this->build_form_func, $form);
    }

    if ( $this->spinner_text !== null ) {
      $spinner = Template::spinner_with_text($section . '-spinner', $this->spinner_text);
    } else {
      $spinner = Template::spinner($section . '-spinner');
    }

    if ( $this->dryrun_button_text !== null ) {
      $main = Template::button($this->dryrun_button_text, $section . '-dryrun-button', 'button-primary');
      $dryrun = Template::button($this->button_text, $section . '-button', 'button-primary', true);

      $spinner_button_components = array(
        $spinner,
        $dryrun,
        $main,
      );

      if ( $this->flip_spinner ) {
        $spinner_button_components = array_reverse($spinner_button_components);
      }

      $spinner_button = Template::n_by_side($spinner_button_components);

    } else {
      $button = Template::button($this->button_text, $section . '-button', 'button-primary');

      if ( $this->flip_spinner ) {
        $spinner_button = Template::side_by_side($spinner, $button);
      } else {
        $spinner_button = Template::side_by_side($button, $spinner);
      }
    }

    $component = new Component('', "<div class=\"seravo-ajax-simple-form\" data-section=\"{$section}\">", '</div>');
    $component->add_child($form);
    $component->add_child($spinner_button);
    $component->add_child(Component::from_raw('<div id="' . $section . '-output" class="seravo-simple-form-output"></div>'));

    $base->add_child($component);
  }

  /**
   * Set the callback function to be called for building the form.
   * @param array $build_form_func Function for building the form.
   */
  public function set_build_form_func( $build_form_func ) {
    $this->build_form_func = $build_form_func;
  }

  /**
   * Set text for the buttons.
   * @param string $text        Text on the button.
   * @param string $dryrun_text Text on the dry-run button (optional).
   */
  public function set_button_text( $text, $dryrun_text = null ) {
    $this->button_text = $text;
    $this->dryrun_button_text = $dryrun_text;
  }

  /**
   * Set text to be shown next to the spinner.
   * @param string $text Spinner text.
   */
  public function set_spinner_text( $text ) {
    $this->spinner_text = $text;
  }

  /**
   * Set whether the spinner and buttons should switch places.
   * @param bool $flip Whether to flip.
   */
  public function set_spinner_flip( $flip ) {
    $this->flip_spinner = $flip;
  }

}
