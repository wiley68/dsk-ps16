/**
 * DSK Payment Homepage Advertisement JavaScript
 *
 * Handles the visibility toggle functionality for the DSK Bank advertisement
 * container on the homepage. Provides smooth fade in/out animation when
 * toggling the advertisement display.
 *
 * @file dskapi_rek.js
 * @author Ilko Ivanov
 * @publisher Avalon Ltd
 * @owner Банка ДСК
 * @version 1.2.0
 */

/**
 * Toggle visibility of the DSK advertisement container
 *
 * Switches the visibility state of the advertisement container with
 * a smooth fade animation. If visible, hides it with fade-out effect.
 * If hidden, shows it with fade-in effect.
 *
 * @returns {void}
 */
function DskapiChangeContainer() {
  var dskapi_label_container = document.getElementsByClassName(
    'dskapi-label-container'
  )[0];
  if (dskapi_label_container.style.visibility == 'visible') {
    // Hide container with fade-out animation
    dskapi_label_container.style.visibility = 'hidden';
    dskapi_label_container.style.opacity = 0;
    dskapi_label_container.style.transition =
      'visibility 0s, opacity 0.5s ease';
  } else {
    // Show container with fade-in animation
    dskapi_label_container.style.visibility = 'visible';
    dskapi_label_container.style.opacity = 1;
  }
}
