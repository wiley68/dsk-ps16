/**
 * @File: dskapi_product.js
 * @Author: Ilko Ivanov
 * @Publisher: Avalon Ltd
 * @Owner: Банка ДСК
 * @Version: 1.2.0
 *
 * JavaScript за продуктова страница - PrestaShop 1.6.x
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
  var dskapi_vnoski_input = document.getElementById(
    'dskapi_pogasitelni_vnoski_input'
  );
  if (!dskapi_vnoski_input) {
    return;
  }

  var dskapi_vnoski = parseFloat(dskapi_vnoski_input.value);

  // Първо опитваме да вземем цената от dskapi_price_txt, ако не съществува - от dskapi_price
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

  if (!dskapi_cid || !DSKAPI_LIVEURL || !dskapi_product_id) {
    return;
  }

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
            alert('Избраният брой погасителни вноски е под минималния.');
            dskapi_vnoski_input.value = old_vnoski;
            if (dskapi_vnoski_txt) {
              dskapi_vnoski_txt.innerHTML = old_vnoski;
            }
          }
        } else {
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
 * Изчислява и актуализира динамично цената на продукта въз основа на текущите опции и количество.
 * Записва резултата в dskapi_price_txt и актуализира показваните данни за вноските.
 *
 * @param {boolean} showPopup - Дали да показва попъпа при валидна цена (true) или да не го променя (false)
 * @return {void}
 */
function dskapi_calculateAndUpdateProductPrice(showPopup) {
  showPopup = showPopup || false;

  // PrestaShop 1.6.x: Взимаме цената от различни селектори
  var dskapi_price1;

  // Опит 1: #our_price_display (стандартно за PS 1.6.x)
  var priceEl = document.getElementById('our_price_display');
  if (priceEl) {
    var priceText = priceEl.textContent || priceEl.innerText;
    dskapi_price1 = priceText.replace(/[^\d,.]/g, '').replace(',', '.');
  }

  // Опит 2: span.price (атрибут или текст)
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

  // Опит 3: itemprop="price"
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

  // Ако не сме успели да намерим цена, използваме стойността от скритото поле
  if (!dskapi_price1) {
    var dskapi_price = document.getElementById('dskapi_price');
    if (dskapi_price) {
      dskapi_price1 = dskapi_price.value;
    } else {
      return;
    }
  }

  // Взимаме количеството
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

  // Изчисляваме общата цена
  var dskapi_priceall = parseFloat(dskapi_price1) * dskapi_quantity;

  // Прилагаме валутни конверсии ако е необходимо
  var dskapi_eur_el = document.getElementById('dskapi_eur');
  var dskapi_currency_code_el = document.getElementById('dskapi_currency_code');

  if (dskapi_eur_el && dskapi_currency_code_el) {
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
  }

  // Записваме изчислената цена в dskapi_price_txt
  var dskapi_price_txt = document.getElementById('dskapi_price_txt');
  if (dskapi_price_txt) {
    dskapi_price_txt.value = dskapi_priceall.toFixed(2);
  }

  // Проверяваме максималното позволено
  var dskapi_maxstojnost = document.getElementById('dskapi_maxstojnost');
  var dskapiProductPopupContainer = document.getElementById(
    'dskapi-product-popup-container'
  );

  if (!dskapi_maxstojnost) {
    return;
  }

  var maxPrice = parseFloat(dskapi_maxstojnost.value);
  var isValid = dskapi_priceall <= maxPrice;

  // Актуализираме данните за вноските
  if (isValid) {
    // Извикваме функцията за преизчисляване на вноските
    dskapi_pogasitelni_vnoski_input_change();

    // Ако showPopup е true и попъпът съществува, показваме го
    if (showPopup && dskapiProductPopupContainer) {
      dskapiProductPopupContainer.style.display = 'block';
    }
  } else {
    // Ако цената е над максималната и showPopup е true, показваме alert
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
 * Функция за записване на cookie
 *
 * @param {string} name
 * @param {string} value
 * @param {number} days
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
 * Добавя продукта в количката СКРИТО (без попъп) и пренасочва директно към DSK payment
 * Адаптирано за PrestaShop 1.6.x
 *
 * @return {void}
 */
function dskapi_addToCartAndRedirectToCheckout() {
  // Записваме избора на платежен метод в cookie (валиден за 1 час)
  dskapi_setCookie('dskpayment_selected', '1', 1 / 24);

  // Вземаме URL към DSK payment контролера
  var checkoutUrlEl = document.getElementById('dskapi_checkout_url');
  var checkoutUrl = checkoutUrlEl ? checkoutUrlEl.value : '';

  // Вземаме id_product от страницата
  var idProductEl = document.getElementById('product_page_product_id');
  var idProduct = idProductEl ? idProductEl.value : null;

  // Fallback: опитваме от dskapi_product_id
  if (!idProduct) {
    var dskapiProductId = document.getElementById('dskapi_product_id');
    idProduct = dskapiProductId ? dskapiProductId.value : null;
  }

  // Fallback: опитваме от URL
  if (!idProduct) {
    var urlMatch = window.location.href.match(/id_product=(\d+)/);
    if (urlMatch) {
      idProduct = urlMatch[1];
    }
  }

  if (!idProduct) {
    console.error('DSK Payment: Не може да се намери ID на продукта');
    if (checkoutUrl) {
      window.location.href = checkoutUrl;
    }
    return;
  }

  // Вземаме количеството
  var quantity = 1;
  var qtyInput = document.getElementById('quantity_wanted');
  if (qtyInput) {
    quantity = parseInt(qtyInput.value) || 1;
  }

  // Вземаме id_product_attribute ако има избрана комбинация
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

  // Вземаме static_token за CSRF защита (PrestaShop 1.6.x)
  var staticToken = '';
  var tokenInput = document.querySelector('input[name="token"]');
  if (tokenInput) {
    staticToken = tokenInput.value;
  }
  // Fallback: глобална променлива
  if (!staticToken && typeof static_token !== 'undefined') {
    staticToken = static_token;
  }

  // Функция за redirect след добавяне
  var doRedirect = function () {
    if (checkoutUrl) {
      window.location.href = checkoutUrl;
    }
  };

  // СКРИТО добавяне в количката чрез директна AJAX заявка
  // Не използваме ajaxCart.add() защото тя показва попъп
  var ajaxUrl = '';
  if (typeof baseDir !== 'undefined') {
    ajaxUrl = baseDir + 'index.php';
  } else if (typeof baseUri !== 'undefined') {
    ajaxUrl = baseUri + 'index.php';
  } else {
    // Fallback: опитваме да извлечем от текущия URL
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
        // Успешно добавяне - redirect към DSK payment
        doRedirect();
      } else {
        // При грешка - пак опитваме redirect (може продуктът вече да е в количката)
        console.warn('DSK Payment: AJAX отговор със статус ' + xhr.status);
        doRedirect();
      }
    }
  };

  xhr.onerror = function () {
    // При network грешка - пак redirect
    console.error('DSK Payment: Network грешка при добавяне в количката');
    doRedirect();
  };

  xhr.send(params);

  // Fallback timeout: ако AJAX не отговори в рамките на 3 секунди, redirect
  setTimeout(function () {
    doRedirect();
  }, 3000);
}

/**
 * Инициализира DSK widget-а на продуктовата страница
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

    // Бутон за отваряне на попъпа или директно добавяне
    btn_dskapi.onclick = function (event) {
      event.preventDefault();
      if (dskapi_button_status == 1) {
        dskapi_addToCartAndRedirectToCheckout();
      } else {
        // При клик на бутона показваме попъпа ако цената е валидна
        dskapi_calculateAndUpdateProductPrice(true);
      }
      return false;
    };

    // Бутон за затваряне на попъпа
    if (dskapi_back_credit) {
      dskapi_back_credit.onclick = function (event) {
        event.preventDefault();
        if (dskapiProductPopupContainer) {
          dskapiProductPopupContainer.style.display = 'none';
        }
        return false;
      };
    }

    // Бутон "Купи на изплащане" в попъпа
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

    // Слушаме за промяна на количеството
    var qtyInput = document.getElementById('quantity_wanted');
    if (qtyInput) {
      qtyInput.onchange = function () {
        dskapi_calculateAndUpdateProductPrice(false);
      };
      qtyInput.onkeyup = function () {
        dskapi_calculateAndUpdateProductPrice(false);
      };
    }

    // PrestaShop 1.6.x: Слушаме за промяна на комбинации чрез jQuery
    if (typeof jQuery !== 'undefined') {
      jQuery(document).on('change', '.attribute_select, .attribute_radio', function () {
        // Изчакваме PrestaShop да обнови цената
        setTimeout(function () {
          dskapi_calculateAndUpdateProductPrice(false);
        }, 300);
      });

      // Слушаме за findCombination callback (ако темата го използва)
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

    // Изпълняваме процедурата веднъж след зареждане на страницата
    setTimeout(function () {
      dskapi_calculateAndUpdateProductPrice(false);
    }, 100);
  }
}

// Инициализация при зареждане на страницата
// PrestaShop 1.6.x използва jQuery
if (typeof jQuery !== 'undefined') {
  jQuery(document).ready(function () {
    initDskapiWidget();
  });
} else {
  // Fallback без jQuery
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDskapiWidget);
  } else {
    initDskapiWidget();
  }
}

// Допълнителен fallback при load
if (typeof jQuery !== 'undefined') {
  jQuery(window).load(function () {
    setTimeout(initDskapiWidget, 200);
  });
} else {
  window.onload = function () {
    setTimeout(initDskapiWidget, 200);
  };
}
