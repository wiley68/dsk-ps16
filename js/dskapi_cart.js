/**
 * DSK Payment Cart Widget JavaScript
 *
 * Handles the DSK Bank credit purchase widget functionality on the cart page.
 * Provides installment calculation, popup display, and checkout redirection.
 *
 * @file dskapi_cart.js
 * @author Ilko Ivanov
 * @publisher Avalon Ltd
 * @owner Банка ДСК
 * @version 1.2.0
 *
 * Compatible with PrestaShop 1.6.x
 */

/**
 * Stores the previous installment count for reverting on validation errors
 * @type {number}
 */
var old_vnoski;

/**
 * Create a cross-origin HTTP request object
 *
 * Provides compatibility for older browsers that use XDomainRequest
 * instead of XMLHttpRequest for CORS requests.
 *
 * @param {string} method - HTTP method (GET, POST, etc.)
 * @param {string} url - Request URL
 * @returns {XMLHttpRequest|XDomainRequest|null} Request object or null if unsupported
 */
function createCORSRequest(method, url) {
  var xhr = new XMLHttpRequest();
  if ('withCredentials' in xhr) {
    xhr.open(method, url, true);
  } else if (typeof XDomainRequest != 'undefined') {
    xhr = new XDomainRequest();
    xhr.open(method, url);
  } else {
    xhr = null;
  }
  return xhr;
}

/**
 * Store current installment count on dropdown focus
 *
 * Saves the current value before user changes it, allowing
 * revert to previous value if validation fails.
 *
 * @param {number} _old_vnoski - Current installment count
 * @returns {void}
 */
function dskapi_pogasitelni_vnoski_input_focus(_old_vnoski) {
  old_vnoski = _old_vnoski;
}

/**
 * Handle installment count dropdown change
 *
 * Fetches updated installment data from DSK API when user
 * selects a different number of months. Updates the popup
 * with new monthly payment, APR, and total amount.
 *
 * @returns {void}
 */
function dskapi_pogasitelni_vnoski_input_change() {
  var vnoskiInput = document.getElementById('dskapi_pogasitelni_vnoski_input');
  var priceInput = document.getElementById('dskapi_price_txt');
  var cidInput = document.getElementById('dskapi_cid');
  var urlInput = document.getElementById('DSKAPI_LIVEURL');
  var productIdInput = document.getElementById('dskapi_product_id');

  // Validate required elements exist
  if (!vnoskiInput || !priceInput || !cidInput || !urlInput || !productIdInput) {
    return;
  }

  var dskapi_vnoski = parseFloat(vnoskiInput.value);
  var dskapi_price = parseFloat(priceInput.value);
  var dskapi_cid = cidInput.value;
  var DSKAPI_LIVEURL = urlInput.value;
  var dskapi_product_id = productIdInput.value;

  // Build API request URL
  var xmlhttpro = createCORSRequest(
    'GET',
    DSKAPI_LIVEURL +
      '/function/getproductcustom.php?cid=' +
      dskapi_cid +
      '&price=' +
      dskapi_price +
      '&product_id=' +
      dskapi_product_id +
      '&dskapi_vnoski=' +
      dskapi_vnoski
  );

  if (!xmlhttpro) {
    return;
  }

  xmlhttpro.onreadystatechange = function () {
    if (this.readyState == 4 && this.status == 200) {
      try {
        var response = JSON.parse(this.response);
        var options = response.dsk_options;
        var dsk_vnoska = parseFloat(response.dsk_vnoska);
        var dsk_gpr = parseFloat(response.dsk_gpr);
        var dsk_is_visible = response.dsk_is_visible;

        if (dsk_is_visible) {
          if (options) {
            // Update popup fields with new values
            var dskapi_vnoska_input = document.getElementById('dskapi_vnoska');
            var dskapi_vnoski_txt = document.getElementById('dskapi_vnoski_txt');
            var dskapi_vnoska_txt = document.getElementById('dskapi_vnoska_txt');
            var dskapi_gpr = document.getElementById('dskapi_gpr');
            var dskapi_obshtozaplashtane_input = document.getElementById('dskapi_obshtozaplashtane');

            if (dskapi_vnoska_input) {
              dskapi_vnoska_input.value = dsk_vnoska.toFixed(2);
            }
            if (dskapi_vnoski_txt) {
              dskapi_vnoski_txt.innerHTML = dskapi_vnoski;
            }
            if (dskapi_vnoska_txt) {
              dskapi_vnoska_txt.innerHTML = dsk_vnoska.toFixed(2);
            }
            if (dskapi_gpr) {
              dskapi_gpr.value = dsk_gpr.toFixed(2);
            }
            if (dskapi_obshtozaplashtane_input) {
              dskapi_obshtozaplashtane_input.value = (dsk_vnoska * dskapi_vnoski).toFixed(2);
            }
            old_vnoski = dskapi_vnoski;
          } else {
            // Selected installment count is below minimum
            alert('Избраният брой погасителни вноски е под минималния.');
            vnoskiInput.value = old_vnoski;
          }
        } else {
          // Selected installment count exceeds maximum
          alert('Избраният брой погасителни вноски е над максималния.');
          vnoskiInput.value = old_vnoski;
        }
      } catch (e) {
        console.error('DSK API Error:', e);
      }
    }
  };
  xmlhttpro.send();
}

/**
 * Set a browser cookie
 *
 * @param {string} name - Cookie name
 * @param {string} value - Cookie value
 * @param {number} days - Expiration time in days (fractions allowed)
 * @returns {void}
 */
function dskapi_cart_setCookie(name, value, days) {
  var expires = new Date();
  expires.setTime(expires.getTime() + days * 24 * 60 * 60 * 1000);
  document.cookie =
    name +
    '=' +
    value +
    ';expires=' +
    expires.toUTCString() +
    ';path=/;SameSite=Lax';
}

/**
 * Redirect directly to DSK payment controller
 *
 * On the cart page, products are already added, so we only need
 * to redirect to the DSK payment controller. Sets a cookie to
 * remember the payment method selection.
 *
 * @returns {void}
 */
function dskapi_redirectToCheckoutWithPaymentMethod() {
  // Save payment method selection in cookie (valid for 1 hour)
  dskapi_cart_setCookie('dskpayment_selected', '1', 1 / 24);

  // Redirect directly to DSK payment controller
  var checkoutUrl = document.getElementById('dskapi_checkout_url');
  if (checkoutUrl && checkoutUrl.value) {
    window.location.href = checkoutUrl.value;
  }
}

/**
 * Flag to prevent duplicate event delegation binding
 * @type {boolean}
 */
var dskapiBuyCreditHandlerBound = false;

/**
 * Initialize the DSK cart widget
 *
 * Sets up event handlers for the credit purchase button and popup.
 * Uses event delegation to handle dynamically added elements.
 * Handles both direct checkout (button_status=1) and popup display modes.
 *
 * @returns {void}
 */
function initDskapiCartWidget() {
  // Set up event delegation for "Buy on Credit" button - only once
  if (!dskapiBuyCreditHandlerBound) {
    dskapiBuyCreditHandlerBound = true;
    document.addEventListener(
      'click',
      function (event) {
        var target = event.target;
        // Check if click is on the button or its child elements
        var isBuyCredit = false;
        if (target) {
          if (target.id === 'dskapi_buy_credit') {
            isBuyCredit = true;
          } else {
            // Check parent elements
            var parent = target.parentElement;
            while (parent) {
              if (parent.id === 'dskapi_buy_credit') {
                isBuyCredit = true;
                break;
              }
              parent = parent.parentElement;
            }
          }
        }

        if (isBuyCredit) {
          event.preventDefault();
          event.stopPropagation();

          // Hide popup before redirect
          var dskapiProductPopupContainer = document.getElementById('dskapi-product-popup-container');
          if (dskapiProductPopupContainer) {
            dskapiProductPopupContainer.style.display = 'none';
          }

          dskapi_redirectToCheckoutWithPaymentMethod();
          return false;
        }
      },
      true
    );
  }

  // Set cursor style on button if it exists
  var dskapi_buy_credit = document.getElementById('dskapi_buy_credit');
  if (dskapi_buy_credit !== null) {
    dskapi_buy_credit.style.cursor = 'pointer';
  }

  // Initialize main DSK button
  var btn_dskapi = document.getElementById('btn_dskapi');
  if (btn_dskapi !== null && btn_dskapi.getAttribute('data-dskapi-bound') !== '1') {
    btn_dskapi.setAttribute('data-dskapi-bound', '1');

    var dskapi_button_status_el = document.getElementById('dskapi_button_status');
    if (!dskapi_button_status_el) {
      return;
    }

    var dskapi_button_status = parseInt(dskapi_button_status_el.value) || 0;
    var dskapiProductPopupContainer = document.getElementById('dskapi-product-popup-container');
    var dskapi_back_credit = document.getElementById('dskapi_back_credit');

    var dskapi_price = document.getElementById('dskapi_price');
    var dskapi_maxstojnost = document.getElementById('dskapi_maxstojnost');

    if (!dskapi_price || !dskapi_maxstojnost) {
      return;
    }

    var dskapi_price1 = dskapi_price.value;
    var dskapi_quantity = 1;
    var dskapi_priceall = parseFloat(dskapi_price1) * dskapi_quantity;

    // Main button click handler
    btn_dskapi.onclick = function (event) {
      event.preventDefault();
      event.stopPropagation();

      // Direct checkout mode (button_status = 1)
      if (dskapi_button_status == 1) {
        dskapi_redirectToCheckoutWithPaymentMethod();
        return false;
      } else {
        // Popup mode - show interest rates popup
        var dskapi_eur_el = document.getElementById('dskapi_eur');
        var dskapi_currency_code_el = document.getElementById('dskapi_currency_code');

        if (!dskapi_eur_el || !dskapi_currency_code_el) {
          return false;
        }

        var dskapi_eur = parseInt(dskapi_eur_el.value) || 0;
        var dskapi_currency_code = dskapi_currency_code_el.value;

        // Apply currency conversion
        switch (dskapi_eur) {
          case 0:
            // No conversion
            break;
          case 1:
            // Convert EUR to BGN
            if (dskapi_currency_code == 'EUR') {
              dskapi_priceall = dskapi_priceall * 1.95583;
            }
            break;
          case 2:
          case 3:
            // Convert BGN to EUR
            if (dskapi_currency_code == 'BGN') {
              dskapi_priceall = dskapi_priceall / 1.95583;
            }
            break;
        }

        // Update price display
        var dskapi_price_txt = document.getElementById('dskapi_price_txt');
        if (dskapi_price_txt) {
          dskapi_price_txt.value = dskapi_priceall.toFixed(2);
        }

        // Check if price is within allowed limit
        if (dskapi_priceall <= parseFloat(dskapi_maxstojnost.value)) {
          if (dskapiProductPopupContainer) {
            dskapiProductPopupContainer.style.display = 'block';
            dskapi_pogasitelni_vnoski_input_change();
          }
        } else {
          alert(
            'Максимално позволената цена за кредит ' +
              parseFloat(dskapi_maxstojnost.value).toFixed(2) +
              ' е надвишена!'
          );
        }
      }
      return false;
    };

    // Back button click handler - close popup
    if (dskapi_back_credit) {
      dskapi_back_credit.onclick = function (event) {
        event.preventDefault();
        if (dskapiProductPopupContainer) {
          dskapiProductPopupContainer.style.display = 'none';
        }
        return false;
      };
    }
  }
}

/**
 * Initialize cart widget with retry mechanism
 *
 * Attempts to initialize the widget multiple times to handle
 * cases where DOM elements are loaded asynchronously.
 *
 * @returns {void}
 */
function initDskapiCartWidgetWithRetry() {
  var attempts = 0;
  var maxAttempts = 10;

  var tryInit = function () {
    attempts++;

    // Check if any DSK elements exist
    var btn_dskapi = document.getElementById('btn_dskapi');
    var dskapi_buy_credit = document.getElementById('dskapi_buy_credit');
    var dskapi_button_status = document.getElementById('dskapi_button_status');

    if (btn_dskapi || dskapi_buy_credit || dskapi_button_status) {
      initDskapiCartWidget();
    } else if (attempts < maxAttempts) {
      // Retry after 200ms
      setTimeout(tryInit, 200);
    }
  };

  tryInit();
}

// Initialize on DOM ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', function () {
    initDskapiCartWidgetWithRetry();
  });
} else {
  initDskapiCartWidgetWithRetry();
}

// Additional initialization on full page load
if (typeof jQuery !== 'undefined') {
  jQuery(window).load(function () {
    setTimeout(initDskapiCartWidgetWithRetry, 300);
  });
} else {
  window.onload = function () {
    setTimeout(initDskapiCartWidgetWithRetry, 300);
  };
}
