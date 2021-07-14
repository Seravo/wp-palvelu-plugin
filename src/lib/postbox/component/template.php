<?php

namespace Seravo\Postbox;

/**
 * Class Template
 *
 * Template has static methods for building
 * components from 'templates'. Templates should be
 * used to keep the components unified.
 */
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
   * @param string $class   Paragraph class to apply.
   * @return \Seravo\Postbox\Component Paragraph component.
   */
  public static function paragraph( $content, $class = 'paragraph-text' ) {
    return Component::from_raw('<p class="' . $class . '">' . $content . '</p>');
  }

  /**
   * Display basic Seravo Plugin widget text.
   * @param string $content The content to display.
   * @param string $class Optional class for the div element.
   * @return \Seravo\Postbox\Component Div component using postbox-text class.
   */
  public static function text( $content, $class = 'postbox-text' ) {
    return Component::from_raw('<div class="' . $class . '">' . $content . '</div>');
  }

  /**
   * Display section title on widget.
   * @param string $content The given title to display.
   * @param string $class Optional class for the title element.
   * @return \Seravo\Postbox\Component Title component.
   */
  public static function section_title( $content, $class = 'title' ) {
    return Component::from_raw('<h3 class="' . $class . '">' . $content . '</h3>');
  }

  /**
   * Display HTML list view.
   * @param string|array $element Element or elements to add onto list.
   * @return \Seravo\Postbox\Component List view component.
   */
  public static function list_view( $element ) {
    $component = new Component('', '<ul class="postbox-ul">', '</ul>');

    if ( is_array($element) ) {
      foreach ( $element as $list_object ) {
        $component->add_child(Component::from_raw('<li>' . $list_object . '</li>'));
      }
    } else {
      $component->add_child(Component::from_raw('<li>' . $element . '</li>'));
    }

    return $component;
  }

  /**
   * Display elements in a specified table.
   * @param string $class Specified table class.
   * @param string $th_class Class for th elements.
   * @param string $td_class Class for td elements.
   * @param array $column_titles Column titles in array.
   * @param array $all_rows Rows as $rows => $row array.
   * @param bool $tooltip_titles Display tooltips in titles field or not.
   * @return \Seravo\Postbox\Component
   */
  public static function table_view( $class, $th_class, $td_class, $column_titles, $all_rows, $tooltip_titles = false ) {
    $main_table = new Component('', '<table class="' . $class . '">', '</table>');
    $titles = new Component('', '<tr>', '</tr>');

    foreach ( $column_titles as $title ) {
      $titles->add_child(Component::from_raw('<th class="' . $th_class . '">' . $title . '</th>'));
    }
    $main_table->add_child($titles);

    foreach ( $all_rows as $rows ) {
      $row = new Component('', '<tr>', '</tr>');

      foreach ( $rows as $row_element ) {
        $row->add_child(Component::from_raw('<td class="' . $td_class . '"' . ($tooltip_titles ? 'title="' . $row_element . '"' : '') . '>' . $row_element . '</td>'));
      }
      $main_table->add_child($row);
    }
    return $main_table;
  }

  /**
    * Display link
    * @param string $content Text to wrap the link around.
    * @param string $href    Link URL.
    * @param string $id      ID for the link element.
    * @param string $class   Optional class for the link element.
    * @param string $target  Optional link element target, default _blank.
    * @return \Seravo\Postbox\Component Link component.
    */
   public static function link( $content, $href, $id, $class = 'button', $target = '_blank' ) {
    return Component::from_raw('<a id="' . $id . '" class="' . $class . '" href="' . $href . '" target="' . $target . '" >' . $content . '</a>');
   }

  /**
   * Generates link with icon.
   * @param string $href         Link URL.
   * @param string $link_content Content to wrap the link around to.
   * @return \Seravo\Postbox\Component Button with icon.
   */
  public static function button_link_with_icon( $href, $link_content ) {
    return Component::from_raw(
      '<a href="' . $href . '" class="button" target="_blank">' . $link_content . '
      <span aria-hidden="true" class="dashicons dashicons-external"></span> </a>'
    );
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
   * Display basic clickable / interactive button to run for example AJAX side commands
   * $content Text to display in the button.
   * @param string $content  Button text.
   * @param string $id       Button id.
   * @param string $class    Specified button class to use.
   * @param bool   $disabled Whether the button is disabled.
   * @return \Seravo\Postbox\Component Button component.
   */
  public static function button( $content, $id, $class = 'button-primary', $disabled = false ) {
    $disabled = $disabled ? ' disabled' : '';
    return Component::from_raw('<button id="' . $id . '" class="' . $class . '"' . $disabled . '>' . $content . '</button>');
  }

  /**
   * Display textfield with label next to it. The component is a row (<tr>)
   * as labels and textfields often need to be aligned with others like them.
   * @param string $label The label text.
   * @param string $name  Name if the input.
   * @param string $value Default value for input.
   * @param string $placeholder Optional placeholder text for the input.
   * @return \Seravo\Postbox\Component Component with label and input field.
   */
  public static function textfield_with_label( $label, $name, $value = '', $placeholder = '' ) {
    $label = new Component($label, '<td><label for="' . $name . '">', '</label></td>');
    $input = Component::from_raw('<td><input type="text" id="' . $name . '" value="' . $value . '" name="' . $name . '" placeholder="' . $placeholder . '"></td>');

    $wrapper = new Component('', '<tr class="seravo-label-textfield">', '</tr>');
    $wrapper->add_child($label);
    $wrapper->add_child($input);
    return $wrapper;
  }

  /**
   * Display checkbox with label next to it.
   * @param string $label   The label text.
   * @param string $name    Name if the input.
   * @param bool   $checked Whether the checkbox is checked by default.
   * @return \Seravo\Postbox\Component Component with label and checkbox.
   */
  public static function checkbox_with_label( $label, $name, $checked = false ) {
    $wrapper = new Component('', '<label for="' . $name . '">', '</label>');
    $wrapper->add_child(Component::from_raw('<input type="checkbox" id="' . $name . '" name="' . $name . '"' . ($checked ? ' checked' : '') . '>'));
    $wrapper->add_child(Component::from_raw($label));
    return $wrapper;
  }

  /**
   * Display datetime picker as HTML input element.
   * @param string $label   The label text.
   * @param string $id      Unique id of the input.
   * @param string $min     Optional min date offset.
   * @param string $max     Optional max date offset.
   * @return \Seravo\Postbox\Component Component with label and datetime picker.
   */
  public static function datetime_picker( $label, $id, $min = '', $max = '' ) {
    return Component::from_raw($label . ' <input type="date" id="' . $id . '" name="' . $id . '" ' . (empty($min) ? '' : 'min="' . $min . '"') . (empty($max) ? '' : 'max="' . $max . '"') . '>');
  }

  /* Add radio_button with the given details.
   * @param string $name Name for the radio button input.
   * @param string $value Value of the radio button.
   * @param string $content Content message for the radio button.
   * @param bool $checked Check the radiobutton.
   * @return \Seravo\Postbox\Component Button component.
   */
  /**
   * @return \Seravo\Postbox\Component
   */
  public static function radio_button( $name, $value, $content, $checked = false ) {
    return Component::from_raw('<input type="radio" name="' . $name . '" value="' . $value . '"' . ($checked ? ' checked=""' : '') . '>' . $content . '<br>');
  }

  /**
   * Display spinner image. In default it's hidden.
   * @param string $id Id for this spinner.
   * @param string $class Class for the div spinner component, default 'seravo-spinner-wrapper seravo-spinner'.
   * @return \Seravo\Postbox\Component Spinner component.
   */
  public static function spinner( $id, $class = 'seravo-spinner' ) {
    $spinner = new Component('', '<div id="' . $id . '" class="seravo-spinner-wrapper ' . $class . '" style="display:none;">', '</div>');
    $spinner->set_content('<img src="/wp-admin/images/spinner.gif">');
    return $spinner;
  }

  /**
   * Display spinner image with loading text next to it. By default it's hidden.
   * @param string $id Id for this spinner.
   * @param string $text Text next to spinner.
   * @param string $spinner_class Class for the div spinner component, default 'spinner'.
   * @return \Seravo\Postbox\Component Spinner component.
   */
  public static function spinner_with_text( $id, $text, $spinner_class = 'seravo-spinner' ) {
    $spinner = new Component('<div id="' . $id . '" class="' . $spinner_class . '"><img src="/wp-admin/images/spinner.gif"></div>');
    $text = new Component($text, '<span><b>', '</b></span>');

    $wrapper = new Component('', '<div class="seravo-spinner-wrapper" style="display:none;">', '</div>');
    $wrapper->add_child(self::side_by_side($spinner, $text));
    return $wrapper;
  }

  /**
   * Display a 'show more' link.
   * @param string $class Class for the link wrapper, default 'hidden'.
   * @return \Seravo\Postbox\Component Show more component.
   */
  public static function show_more_link( $class = 'hidden' ) {
    $icon = new Component('', '<div class="dashicons dashicons-arrow-down-alt2">', '</div>');

    $link = new Component(__('Show more', 'seravo') . ' ', '<a href="" class="seravo-show-more">', '</a>');
    $link->add_child($icon);

    $wrapper = new Component('', '<div class="seravo-show-more-wrapper ' . $class . '">', '</div>');
    $wrapper->add_child($link);

    return $wrapper;
  }

  /**
   * Display status widget based on the input.
   * @param bool $status True for success and false for failure.
   * @return \Seravo\Postbox\Component Paragraph component with text.
   */
  public static function success_failure( $status ) {
    if ( $status ) {
      $class = 'success';
      $msg = sprintf('<b>%s</b>', __('Success!', 'seravo'));
    } else {
      $class = 'failure';
      $msg = sprintf('<b>%s</b>', __('Failure!', 'seravo'));
    }

    return Component::from_raw('<p class="' . $class . '">' . $msg . '</p>');
  }

  /**
   * Get wrapper component to show two components side by side.
   * @param \Seravo\Postbox\Component $left  Left component.
   * @param \Seravo\Postbox\Component $right Right component.
   * @param string $additional_class Additional CSS class for the container.
   * @return \Seravo\Postbox\Component Side-by-side component.
   */
  public static function side_by_side( Component $left, Component $right, $additional_class = '' ) {
    $component = new Component();
    $wrapper = new Component('', '<div class="side-by-side-container ' . $additional_class . '">', '</div>');

    $left_div = new Component('', '<div>', '</div>');
    $left_div->add_child($left);
    $wrapper->add_child($left_div);

    $right_div = new Component('', '<div>', '</div>');
    $right_div->add_child($right);
    $wrapper->add_child($right_div);

    $component->add_child($wrapper);
    return $component;
  }

  /**
   * Get wrapper component to show multiple components side by side.
   * @param \Seravo\Postbox\Component[] $components Components from left to right.
   * @param string $additional_class Additional CSS class for the container.
   * @return \Seravo\Postbox\Component Side-by-side component.
   */
  public static function n_by_side( $components, $additional_class = '' ) {
    $component = new Component();
    $wrapper = new Component('', '<div class="side-by-side-container ' . $additional_class . '">', '</div>');

    foreach ( $components as $child_component ) {
      $div = new Component('', '<div>', '</div>');
      $div->add_child($child_component);
      $wrapper->add_child($div);
    }

    $component->add_child($wrapper);
    return $component;
  }

  /**
   * Get wrapper component to show multiple components from up to down.
   * @param \Seravo\Postbox\Component[] $components Components from up to down.
   * @return \Seravo\Postbox\Component Side-by-side component.
   */
  public static function n_up_to_down( $components ) {
    $component = new Component();
    $wrapper = new Component('', '<div class="up-to-down-container">', '</div>');

    foreach ( $components as $child_component ) {
      $div = new Component('', '<div>', '</div>');
      $div->add_child($child_component);
      $wrapper->add_child($div);
    }

    $component->add_child($wrapper);
    return $component;
  }

  /**
   * Get nag bar with custom component in it.
   * @param \Seravo\Postbox\Component $content     Content inside the bar.
   * @param string                    $nag_type    Type of the nag, 'notice-error' by default (can be error/warning/success/info).
   * @param bool                      $dismissible Whether the nag bar can be closed with (X) button.
   * @return \Seravo\Postbox\Component A nag notice.
   */
  public static function nag_notice( Component $content, $nag_type = 'notice-error', $dismissible = true ) {
    $notice = new Component('', '<div class="seravo-nag notice ' . $nag_type . ' ' . ($dismissible ? 'is-dismissible' : '') . '">', '</div>');
    $wrapper = new Component('', '<div class="nag-content">', '</div>');
    $wrapper->add_child($content);
    $notice->add_child($wrapper);
    return $notice;
  }

}
