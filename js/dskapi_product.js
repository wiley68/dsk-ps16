/**
 * DSK Payment Product Widget JavaScript
 *
 * Handles the DSK Bank credit purchase widget functionality on product pages.
 * Provides dynamic price calculation, installment display, popup management,
 * and silent add-to-cart with direct redirect to DSK payment.
 *
 * @file dskapi_product.js
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
  var dskapi_vnoski_input = document.getElementById(
    'dskapi_pogasitelni_vnoski_input'
  );
  if (!dskapi_vnoski_input) {
    return;
  }

  var dskapi_vnoski = parseFloat(dskapi_vnoski_input.value);

  // Try to get price from dskapi_price_txt first, fallback to dskapi_price
  var dskapi_price_el = document.getElementById('dskapi_price_txt');
  var dskapi_price = dskapi_price_el ? parseFloat(dskapi_price_el.value) : null;

  if (!dskapi_price || isNaN(dskapi_price)) {
    var dskapi_price_hidden = document.getElementById('dskapi_price');
    if (dskapi_price_hidden) {
      dskapi_price = parseFloat(dskapi_price_hidden.value);
    }
  }

  if (!dskapi_price || isNaN(dskapi_price)) {
    return;
  }

  var dskapi_cid = document.getElementById('dskapi_cid');
  var DSKAPI_LIVEURL = document.getElementById('DSKAPI_LIVEURL');
  var dskapi_product_id = document.getElementById('dskapi_product_id');

  // Validate required elements exist
  if (!dskapi_cid || !DSKAPI_LIVEURL || !dskapi_product_id) {
    return;
  }

  // Build API request URL
  var xmlhttpro = createCORSRequest(
    'GET',
    DSKAPI_LIVEURL.value +
      '/function/getproductcustom.php?cid=' +
      dskapi_cid.value +
      '&price=' +
      dskapi_price +
      '&product_id=' +
      dskapi_product_id.value +
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
        var dskapi_vnoski_txt = document.getElementById('dskapi_vnoski_txt');

        if (dsk_is_visible) {
          if (options) {
            // Update popup fields with new values
            var dskapi_vnoska_input = document.getElementById('dskapi_vnoska');
            var dskapi_vnoska_txt = document.getElementById('dskapi_vnoska_txt');
            var dskapi_gpr = document.getElementById('dskapi_gpr');
            var dskapi_obshtozaplashtane_input = document.getElementById(
              'dskapi_obshtozaplashtane'
            );

            if (dskapi_vnoska_input) {
              dskapi_vnoska_input.value = dsk_vnoska.toFixed(2);
            }
            if (dskapi_vnoska_txt) {
              dskapi_vnoska_txt.innerHTML = dsk_vnoska.toFixed(2);
            }
            if (dskapi_gpr) {
              dskapi_gpr.value = dsk_gpr.toFixed(2);
            }
            if (dskapi_obshtozaplashtane_input) {
              dskapi_obshtozaplashtane_input.value = (
                dsk_vnoska * dskapi_vnoski
              ).toFixed(2);
            }
            old_vnoski = dskapi_vnoski;
            if (dskapi_vnoski_txt) {
              dskapi_vnoski_txt.innerHTML = dskapi_vnoski;
            }
          } else {
            // Selected installment count is below minimum
            alert('Избраният брой погасителни вноски е под минималния.');
            dskapi_vnoski_input.value = old_vnoski;
            if (dskapi_vnoski_txt) {
              dskapi_vnoski_txt.innerHTML = old_vnoski;
            }
          }
        } else {
          // Selected installment count exceeds maximum
          alert('Избраният брой погасителни вноски е над максималния.');
          dskapi_vnoski_input.value = old_vnoski;
          if (dskapi_vnoski_txt) {
            dskapi_vnoski_txt.innerHTML = old_vnoski;
          }
        }
      } catch (e) {
        console.error('DSK API Error:', e);
      }
    }
  };
  xmlhttpro.send();
}

/**
 * Calculate and update product price dynamically
 *
 * Computes the total price based on current product options and quantity.
 * Stores the result in dskapi_price_txt and updates installment display.
 * Handles currency conversion if configured.
 *
 * @param {boolean} showPopup - Whether to show popup on valid price (true) or not change it (false)
 * @returns {void}
 */
function dskapi_calculateAndUpdateProductPrice(showPopup) {
  showPopup = showPopup || false;

  // PrestaShop 1.6.x: Get price from various selectors
  var dskapi_price1;

  // Attempt 1: #our_price_display (standard for PS 1.6.x)
  var priceEl = document.getElementById('our_price_display');
  if (priceEl) {
    var priceText = priceEl.textContent || priceEl.innerText;
    dskapi_price1 = priceText.replace(/[^\d,.]/g, '').replace(',', '.');
  }

  // Attempt 2: span.price (attribute or text)
  if (!dskapi_price1) {
    priceEl = document.querySelector('span.price[itemprop="price"]');
    if (priceEl) {
      dskapi_price1 = priceEl.getAttribute('content');
      if (!dskapi_price1) {
        var priceText = priceEl.textContent || priceEl.innerText;
        dskapi_price1 = priceText.replace(/[^\d,.]/g, '').replace(',', '.');
      }
    }
  }

  // Attempt 3: itemprop="price"
  if (!dskapi_price1) {
    priceEl = document.querySelector('[itemprop="price"]');
    if (priceEl) {
      dskapi_price1 = priceEl.getAttribute('content');
      if (!dskapi_price1) {
        var priceText = priceEl.textContent || priceEl.innerText;
        dskapi_price1 = priceText.replace(/[^\d,.]/g, '').replace(',', '.');
      }
    }
  }

  // If price not found, use value from hidden field
  if (!dskapi_price1) {
    var dskapi_price = document.getElementById('dskapi_price');
    if (dskapi_price) {
      dskapi_price1 = dskapi_price.value;
    } else {
      return;
    }
  }

  // Get quantity
  var dskapi_quantity = 1;
  var qtyInput = document.getElementById('quantity_wanted');
  if (qtyInput) {
    dskapi_quantity = parseFloat(qtyInput.value) || 1;
  } else {
    var qtyInputs = document.getElementsByName('qty');
    if (qtyInputs && qtyInputs.length > 0) {
      dskapi_quantity = parseFloat(qtyInputs[0].value) || 1;
    }
  }

  // Calculate total price
  var dskapi_priceall = parseFloat(dskapi_price1) * dskapi_quantity;

  // Apply currency conversion if needed
  var dskapi_eur_el = document.getElementById('dskapi_eur');
  var dskapi_currency_code_el = document.getElementById('dskapi_currency_code');

  if (dskapi_eur_el && dskapi_currency_code_el) {
    var dskapi_eur = parseInt(dskapi_eur_el.value) || 0;
    var dskapi_currency_code = dskapi_currency_code_el.value;

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
  }

  // Store calculated price in dskapi_price_txt
  var dskapi_price_txt = document.getElementById('dskapi_price_txt');
  if (dskapi_price_txt) {
    dskapi_price_txt.value = dskapi_priceall.toFixed(2);
  }

  // Check maximum allowed value
  var dskapi_maxstojnost = document.getElementById('dskapi_maxstojnost');
  var dskapiProductPopupContainer = document.getElementById(
    'dskapi-product-popup-container'
  );

  if (!dskapi_maxstojnost) {
    return;
  }

  var maxPrice = parseFloat(dskapi_maxstojnost.value);
  var isValid = dskapi_priceall <= maxPrice;

  // Update installment data
  if (isValid) {
    // Call function to recalculate installments
    dskapi_pogasitelni_vnoski_input_change();

    // If showPopup is true and popup exists, display it
    if (showPopup && dskapiProductPopupContainer) {
      dskapiProductPopupContainer.style.display = 'block';
    }
  } else {
    // If price exceeds maximum and showPopup is true, show alert
    if (showPopup) {
      alert(
        'Максимално позволената цена за кредит ' +
          maxPrice.toFixed(2) +
          ' е надвишена!'
      );
    }
  }
}

/**
 * Set a browser cookie
 *
 * @param {string} name - Cookie name
 * @param {string} value - Cookie value
 * @param {number} days - Expiration time in days (fractions allowed)
 * @returns {void}
 */
function dskapi_setCookie(name, value, days) {
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
 * Add product to cart silently and redirect to DSK payment
 *
 * Performs a hidden AJAX add-to-cart request without showing
 * PrestaShop's default popup, then redirects directly to the
 * DSK payment controller for credit purchase.
 *
 * Adapted for PrestaShop 1.6.x compatibility.
 *
 * @returns {void}
 */
function dskapi_addToCartAndRedirectToCheckout() {
  // Save payment method selection in cookie (valid for 1 hour)
  dskapi_setCookie('dskpayment_selected', '1', 1 / 24);

  // Get URL to DSK payment controller
  var checkoutUrlEl = document.getElementById('dskapi_checkout_url');
  var checkoutUrl = checkoutUrlEl ? checkoutUrlEl.value : '';

  // Get id_product from page
  var idProductEl = document.getElementById('product_page_product_id');
  var idProduct = idProductEl ? idProductEl.value : null;

  // Fallback: try from dskapi_product_id
  if (!idProduct) {
    var dskapiProductId = document.getElementById('dskapi_product_id');
    idProduct = dskapiProductId ? dskapiProductId.value : null;
  }

  // Fallback: try from URL
  if (!idProduct) {
    var urlMatch = window.location.href.match(/id_product=(\d+)/);
    if (urlMatch) {
      idProduct = urlMatch[1];
    }
  }

  if (!idProduct) {
    console.error('DSK Payment: Cannot find product ID');
    if (checkoutUrl) {
      window.location.href = checkoutUrl;
    }
    return;
  }

  // Get quantity
  var quantity = 1;
  var qtyInput = document.getElementById('quantity_wanted');
  if (qtyInput) {
    quantity = parseInt(qtyInput.value) || 1;
  }

  // Get id_product_attribute if combination is selected
  var idProductAttribute = 0;
  var ipaInput = document.getElementById('idCombination');
  if (ipaInput) {
    idProductAttribute = parseInt(ipaInput.value) || 0;
  }
  // Fallback: groups
  if (!idProductAttribute) {
    ipaInput = document.querySelector('input[name="id_product_attribute"]');
    if (ipaInput) {
      idProductAttribute = parseInt(ipaInput.value) || 0;
    }
  }

  // Get static_token for CSRF protection (PrestaShop 1.6.x)
  var staticToken = '';
  var tokenInput = document.querySelector('input[name="token"]');
  if (tokenInput) {
    staticToken = tokenInput.value;
  }
  // Fallback: global variable
  if (!staticToken && typeof static_token !== 'undefined') {
    staticToken = static_token;
  }

  // Redirect function after adding to cart
  var doRedirect = function () {
    if (checkoutUrl) {
      window.location.href = checkoutUrl;
    }
  };

  // Silent add-to-cart via direct AJAX request
  // We don't use ajaxCart.add() because it shows a popup
  var ajaxUrl = '';
  if (typeof baseDir !== 'undefined') {
    ajaxUrl = baseDir + 'index.php';
  } else if (typeof baseUri !== 'undefined') {
    ajaxUrl = baseUri + 'index.php';
  } else {
    // Fallback: extract from current URL
    ajaxUrl = window.location.protocol + '//' + window.location.host + '/index.php';
  }

  var params =
    'controller=cart&add=1&ajax=true' +
    '&id_product=' + idProduct +
    '&qty=' + quantity +
    '&id_product_attribute=' + idProductAttribute +
    '&token=' + staticToken;

  var xhr = new XMLHttpRequest();
  xhr.open('POST', ajaxUrl, true);
  xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

  xhr.onreadystatechange = function () {
    if (xhr.readyState === 4) {
      if (xhr.status === 200) {
        // Success - redirect to DSK payment
        doRedirect();
      } else {
        // On error - still try redirect (product may already be in cart)
        console.warn('DSK Payment: AJAX response status ' + xhr.status);
        doRedirect();
      }
    }
  };

  xhr.onerror = function () {
    // On network error - still redirect
    console.error('DSK Payment: Network error when adding to cart');
    doRedirect();
  };

  xhr.send(params);

  // Fallback timeout: if AJAX doesn't respond within 3 seconds, redirect anyway
  setTimeout(function () {
    doRedirect();
  }, 3000);
}

/**
 * Initialize the DSK widget on product page
 *
 * Sets up event handlers for:
 * - Main DSK button (direct checkout or popup display)
 * - Back button to close popup
 * - "Buy on Credit" button in popup
 * - Quantity changes
 * - Product combination/attribute changes
 *
 * @returns {void}
 */
function initDskapiWidget() {
  var btn_dskapi = document.getElementById('btn_dskapi');

  if (btn_dskapi !== null && btn_dskapi.getAttribute('data-dskapi-bound') !== '1') {
    btn_dskapi.setAttribute('data-dskapi-bound', '1');

    var dskapi_button_status_el = document.getElementById('dskapi_button_status');
    var dskapi_button_status = dskapi_button_status_el
      ? parseInt(dskapi_button_status_el.value)
      : 0;

    var dskapiProductPopupContainer = document.getElementById(
      'dskapi-product-popup-container'
    );
    var dskapi_back_credit = document.getElementById('dskapi_back_credit');
    var dskapi_buy_credit = document.getElementById('dskapi_buy_credit');

    // Main button - open popup or direct add-to-cart
    btn_dskapi.onclick = function (event) {
      event.preventDefault();
      if (dskapi_button_status == 1) {
        // Direct checkout mode
        dskapi_addToCartAndRedirectToCheckout();
      } else {
        // Show popup if price is valid
        dskapi_calculateAndUpdateProductPrice(true);
      }
      return false;
    };

    // Back button - close popup
    if (dskapi_back_credit) {
      dskapi_back_credit.onclick = function (event) {
        event.preventDefault();
        if (dskapiProductPopupContainer) {
          dskapiProductPopupContainer.style.display = 'none';
        }
        return false;
      };
    }

    // "Buy on Credit" button in popup
    if (dskapi_buy_credit) {
      dskapi_buy_credit.onclick = function (event) {
        event.preventDefault();
        if (dskapiProductPopupContainer) {
          dskapiProductPopupContainer.style.display = 'none';
        }
        dskapi_addToCartAndRedirectToCheckout();
        return false;
      };
    }

    // Listen for quantity changes
    var qtyInput = document.getElementById('quantity_wanted');
    if (qtyInput) {
      qtyInput.onchange = function () {
        dskapi_calculateAndUpdateProductPrice(false);
      };
      qtyInput.onkeyup = function () {
        dskapi_calculateAndUpdateProductPrice(false);
      };
    }

    // PrestaShop 1.6.x: Listen for combination changes via jQuery
    if (typeof jQuery !== 'undefined') {
      jQuery(document).on('change', '.attribute_select, .attribute_radio', function () {
        // Wait for PrestaShop to update the price
        setTimeout(function () {
          dskapi_calculateAndUpdateProductPrice(false);
        }, 300);
      });

      // Listen for findCombination callback (if theme uses it)
      if (typeof combinationsPrices !== 'undefined') {
        var originalFindCombination = window.findCombination;
        if (typeof originalFindCombination === 'function') {
          window.findCombination = function () {
            originalFindCombination.apply(this, arguments);
            setTimeout(function () {
              dskapi_calculateAndUpdateProductPrice(false);
            }, 300);
          };
        }
      }
    }

    // Run initial calculation after page load
    setTimeout(function () {
      dskapi_calculateAndUpdateProductPrice(false);
    }, 100);
  }
}

// Initialize on page load
// PrestaShop 1.6.x uses jQuery
if (typeof jQuery !== 'undefined') {
  jQuery(document).ready(function () {
    initDskapiWidget();
  });
} else {
  // Fallback without jQuery
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDskapiWidget);
  } else {
    initDskapiWidget();
  }
}

// Additional fallback on window load
if (typeof jQuery !== 'undefined') {
  jQuery(window).load(function () {
    setTimeout(initDskapiWidget, 200);
  });
} else {
  window.onload = function () {
    setTimeout(initDskapiWidget, 200);
  };
}
