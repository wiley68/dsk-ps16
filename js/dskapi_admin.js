/**
 * DSK Payment Admin JavaScript
 *
 * Contains helper functions for the DSK Payment admin interface.
 *
 * @file dskapi_admin.js
 * @author Ilko Ivanov
 * @version 1.2.0
 */

/**
 * Toggle visibility of the DSK orders container
 *
 * Shows the hidden DSK orders div element when called.
 * Used in the admin panel to display additional order information.
 *
 * @returns {void}
 */
function show_dskapi_all_orders() {
    var dskapi_orders_div = document.getElementById("dskapi_orders_div");
    dskapi_orders_div.style.display = "block";
}
