<?php

/**
 * File for Seravo postbox component templates.
 */

namespace Seravo\Postbox;

// Deny direct access to this file
if ( ! defined('ABSPATH') ) {
  die('Access denied!');
}

if ( ! class_exists('Template') ) {
  class Template {

    /**
     * Get paragraph component for showing error messages.
     * @param string $message Error message to display.
     * @return \Seravo\Postbox\Component Error component.
     */
    public static function error_paragraph( $message ) {
      $html = sprintf('<p><b>%s</b></p>', $message);
      return Component::from_raw($html);
    }

    /**
     * Display HTML paragraph with given content.
     * @param string $content The content to display.
     * @param string $class Paragraph class to apply.
     * @return \Seravo\Postbox\Component Paragraph component.
     */
    public static function paragraph( $content, $class = 'paragraph-text' ) {
      return Component::from_raw('<p class="' . $class . '">' . $content . '</p>');
    }

    /**
     * Display basic Seravo Plugin widget text.
     * @param string $content The content to display.
     * @return \Seravo\Postbox\Component Div component using postbox-text class.
     */
    public static function text( $content ) {
      return Component::from_raw('<div class="postbox-text">' . $content . '</div>');
    }

    /**
     * Display section title on widget.
     * @param string $content The given title to display.
     * @return \Seravo\Postbox\Component Title component.
     */
    public static function section_title( $content ) {
      return Component::from_raw('<h3>' . $content . '</h3>');
    }

    /**
     * Display basic clickable / interactive button to run for example AJAX side commands
     * $content Text to display in the button.
     * @param string $id Button id.
     * @param string $class Specified button class to use.
     * @return \Seravo\Postbox\Component Button component.
     */
    public static function button( $content, $id, $class = 'button-primary' ) {
      return Component::from_raw('<button id="' . $id . '" class="' . $class . '">' . $content . '</button>');
    }

    /**
     * Display Seravo Plugin tooltip.
     * @param string $tooltiptext The given content to display on tooltip.
     * @return \Seravo\Postbox\Component Tooltip component.
     */
    public static function tooltip( $tooltiptext ) {
      return Component::from_raw(
        '<span class="tooltip dashicons dashicons-info"> <span class="tooltiptext"> ' .
        $tooltiptext . '</span></span>'
      );
    }

    /**
     * Display spinner image. In default it's hidden.
     * @param string $id Id for this spinner.
     * @param string $class Class for the div spinner component, default 'spinner'.
     * @return \Seravo\Postbox\Component Spinner component.
     */
    public static function spinner( $id, $class = 'spinner' ) {
      return Component::from_raw('<div id="' . $id . '" class="' . $class . '"><img src="/wp-admin/images/spinner.gif"></div>');
    }

    /**
     * Display status widget based on the input.
     * @param bool $status True for success and false for failure.
     * @return \Seravo\Postbox\Component Paragraph component with text.
     */
    public static function success_failure( $status ) {
      if ( $status ) {
        $color = 'green';
        $msg = sprintf('<b>%s</b>', __('Success!', 'seravo'));
      } else {
        $color = 'red';
        $msg = sprintf('<b>%s</b>', __('Failure!', 'seravo'));
      }

      return Component::from_raw('<p style="color: ' . $color . ';">' . $msg . '</p>');
    }

    /**
     * Get component for showing command output. This component
     * is not for pretty output, just for scrollable <pre></pre>.
     * @param string $id ID for the output component.
     * @param string $class Classes for the output component.
     * @param string $content Default placeholder content.
     * @return \Seravo\Postbox\Component Simple command output component.
     */
    public static function simple_command_output( $id, $class = '', $content = '' ) {
      return Component::from_raw('<pre id="' . $id . '" class="seravo-simple-command-output ' . $class . '">' . $content . '</pre>');
    }

  }
}
