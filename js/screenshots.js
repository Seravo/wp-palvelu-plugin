/**
 * Screenshots.js contains logic for upkeep page image sliders.
 */

function drags(dragElement, resizeElement, container) {
    // Initialize the dragging event on mousedown.
    dragElement.on(
      'mousedown touchstart',
      function (e) {

        dragElement.addClass('draggable');
        resizeElement.addClass('resizable');

        // Check if it's a mouse or touch event and pass along the correct value
        var startX = (e.pageX) ? e.pageX : e.originalEvent.touches[0].pageX;

        // Get the initial position
        var dragWidth = dragElement.outerWidth(),
            posX = dragElement.offset().left + dragWidth - startX,
            containerOffset = container.offset().left,
            containerWidth = container.outerWidth();

        // Set limits
        var minLeft = containerOffset + 10;
        var maxLeft = containerOffset + containerWidth - dragWidth - 10;

        // Calculate the dragging distance on mousemove.
        dragElement.parents().on(
          "mousemove touchmove",
          function (e) {

            // Check if it's a mouse or touch event and pass along the correct value
            var moveX = (e.pageX) ? e.pageX : e.originalEvent.touches[0].pageX;

            var leftValue = moveX + posX - dragWidth;

            // Prevent going off limits
            if (leftValue < minLeft) {
              leftValue = minLeft;
            } else if (leftValue > maxLeft) {
              leftValue = maxLeft;
            }

            // Translate the handle's left value to masked divs width.
            var widthValue = (leftValue + dragWidth / 2 - containerOffset) * 100 / containerWidth + '%';

            // Set the new values for the slider and the handle.
            // Bind mouseup events to stop dragging.
            jQuery('.draggable').css('left', widthValue).on(
              'mouseup touchend touchcancel',
              function () {
                jQuery(this).removeClass('draggable');
                resizeElement.removeClass('resizable');
              }
            );
            jQuery('.resizable').css('width', widthValue);
          }
        ).on(
          'mouseup touchend touchcancel',
          function () {
            dragElement.removeClass('draggable');
            resizeElement.removeClass('resizable');
          }
        );
        e.preventDefault();
      }
    ).on(
      'mouseup touchend touchcancel',
      function (e) {
        dragElement.removeClass('draggable');
        resizeElement.removeClass('resizable');
      }
    );
}

jQuery(window).load(
  function () {
    jQuery('.ba-slider').each(
      function () {
        var cur = jQuery(this);
        // Adjust the slider
        var width = cur.width() + 'px';
        cur.find('.ba-resize img').css('width', width);
        // Bind dragging events
        drags(cur.find('.ba-handle'), cur.find('.ba-resize'), cur);
      }
    );

  }
);

// Update sliders on resize.
// Because we all do this: i.imgur.com/YkbaV.gif
jQuery(window).resize(
  function () {
    jQuery('.ba-slider').each(
      function () {
        var cur = jQuery(this);
        var width = cur.width() + 'px';
        cur.find('.ba-resize img').css('width', width);
      }
    );
  }
);
