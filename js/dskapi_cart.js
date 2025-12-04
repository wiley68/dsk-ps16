/**
 * @File: dskapi_cart.js
 * @Author: Ilko Ivanov
 * @Publisher: Avalon Ltd
 * @Owner: Банка ДСК
 * @Version: 1.2.0
 *
 * JavaScript за количка - PrestaShop 1.6.x
 */

var old_vnoski;

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

function dskapi_pogasitelni_vnoski_input_focus(_old_vnoski) {
  old_vnoski = _old_vnoski;
}

function dskapi_pogasitelni_vnoski_input_change() {
  var vnoskiInput = document.getElementById('dskapi_pogasitelni_vnoski_input');
  var priceInput = document.getElementById('dskapi_price_txt');
  var cidInput = document.getElementById('dskapi_cid');
  var urlInput = document.getElementById('DSKAPI_LIVEURL');
  var productIdInput = document.getElementById('dskapi_product_id');

  if (!vnoskiInput || !priceInput || !cidInput || !urlInput || !productIdInput) {
    return;
  }

  var dskapi_vnoski = parseFloat(vnoskiInput.value);
  var dskapi_price = parseFloat(priceInput.value);
  var dskapi_cid = cidInput.value;
  var DSKAPI_LIVEURL = urlInput.value;
  var dskapi_product_id = productIdInput.value;

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
            alert('Избраният брой погасителни вноски е под минималния.');
            vnoskiInput.value = old_vnoski;
          }
        } else {
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
 * Функция за записване на cookie
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
 * Редиректва директно към DSK payment контролера
 * В количката продуктът вече е добавен, така че само redirect-ваме
 *
 * @return {void}
 */
function dskapi_redirectToCheckoutWithPaymentMethod() {
  // Записваме избора на платежен метод в cookie (валиден за 1 час)
  dskapi_cart_setCookie('dskpayment_selected', '1', 1 / 24);

  // Редиректваме директно към DSK payment контролера
  var checkoutUrl = document.getElementById('dskapi_checkout_url');
  if (checkoutUrl && checkoutUrl.value) {
    window.location.href = checkoutUrl.value;
  }
}

// Флаг за делегиране на събития
var dskapiBuyCreditHandlerBound = false;

function initDskapiCartWidget() {
  // Делегиране на събития за бутона "Купи на изплащане" - само веднъж
  if (!dskapiBuyCreditHandlerBound) {
    dskapiBuyCreditHandlerBound = true;
    document.addEventListener(
      'click',
      function (event) {
        var target = event.target;
        // Проверяваме дали кликването е върху бутона или неговите деца
        var isBuyCredit = false;
        if (target) {
          if (target.id === 'dskapi_buy_credit') {
            isBuyCredit = true;
          } else {
            // Проверяваме parent елементите
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

  // Задаваме cursor стил на бутона ако съществува
  var dskapi_buy_credit = document.getElementById('dskapi_buy_credit');
  if (dskapi_buy_credit !== null) {
    dskapi_buy_credit.style.cursor = 'pointer';
  }

  // Инициализираме основния бутон btn_dskapi
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

    btn_dskapi.onclick = function (event) {
      event.preventDefault();
      event.stopPropagation();

      if (dskapi_button_status == 1) {
        dskapi_redirectToCheckoutWithPaymentMethod();
        return false;
      } else {
        var dskapi_eur_el = document.getElementById('dskapi_eur');
        var dskapi_currency_code_el = document.getElementById('dskapi_currency_code');

        if (!dskapi_eur_el || !dskapi_currency_code_el) {
          return false;
        }

        var dskapi_eur = parseInt(dskapi_eur_el.value) || 0;
        var dskapi_currency_code = dskapi_currency_code_el.value;

        switch (dskapi_eur) {
          case 0:
            break;
          case 1:
            if (dskapi_currency_code == 'EUR') {
              dskapi_priceall = dskapi_priceall * 1.95583;
            }
            break;
          case 2:
          case 3:
            if (dskapi_currency_code == 'BGN') {
              dskapi_priceall = dskapi_priceall / 1.95583;
            }
            break;
        }

        var dskapi_price_txt = document.getElementById('dskapi_price_txt');
        if (dskapi_price_txt) {
          dskapi_price_txt.value = dskapi_priceall.toFixed(2);
        }

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

// Функция за инициализация с няколко опита
function initDskapiCartWidgetWithRetry() {
  var attempts = 0;
  var maxAttempts = 10;

  var tryInit = function () {
    attempts++;

    var btn_dskapi = document.getElementById('btn_dskapi');
    var dskapi_buy_credit = document.getElementById('dskapi_buy_credit');
    var dskapi_button_status = document.getElementById('dskapi_button_status');

    if (btn_dskapi || dskapi_buy_credit || dskapi_button_status) {
      initDskapiCartWidget();
    } else if (attempts < maxAttempts) {
      setTimeout(tryInit, 200);
    }
  };

  tryInit();
}

// Инициализация при зареждане
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', function () {
    initDskapiCartWidgetWithRetry();
  });
} else {
  initDskapiCartWidgetWithRetry();
}

// Допълнително при пълно зареждане
if (typeof jQuery !== 'undefined') {
  jQuery(window).load(function () {
    setTimeout(initDskapiCartWidgetWithRetry, 300);
  });
} else {
  window.onload = function () {
    setTimeout(initDskapiCartWidgetWithRetry, 300);
  };
}
