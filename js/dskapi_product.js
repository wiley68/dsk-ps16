let old_vnoski;

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
  const dskapi_vnoski_input = document.getElementById(
    'dskapi_pogasitelni_vnoski_input'
  );
  if (!dskapi_vnoski_input) {
    return;
  }

  const dskapi_vnoski = parseFloat(dskapi_vnoski_input.value);

  // Първо опитваме да вземем цената от dskapi_price_txt, ако не съществува - от dskapi_price
  let dskapi_price_el = document.getElementById('dskapi_price_txt');
  let dskapi_price = dskapi_price_el ? parseFloat(dskapi_price_el.value) : null;

  if (!dskapi_price || isNaN(dskapi_price)) {
    const dskapi_price_hidden = document.getElementById('dskapi_price');
    if (dskapi_price_hidden) {
      dskapi_price = parseFloat(dskapi_price_hidden.value);
    }
  }

  if (!dskapi_price || isNaN(dskapi_price)) {
    return;
  }

  const dskapi_cid = document.getElementById('dskapi_cid');
  const DSKAPI_LIVEURL = document.getElementById('DSKAPI_LIVEURL');
  const dskapi_product_id = document.getElementById('dskapi_product_id');

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
        const dskapi_vnoski_txt = document.getElementById('dskapi_vnoski_txt');
        if (dsk_is_visible) {
          if (options) {
            const dskapi_vnoska_input =
              document.getElementById('dskapi_vnoska');
            const dskapi_vnoska_txt =
              document.getElementById('dskapi_vnoska_txt');
            const dskapi_gpr = document.getElementById('dskapi_gpr');
            const dskapi_obshtozaplashtane_input = document.getElementById(
              'dskapi_obshtozaplashtane'
            );
            dskapi_vnoska_input.value = dsk_vnoska.toFixed(2);
            dskapi_vnoska_txt.innerHTML = dsk_vnoska.toFixed(2);
            dskapi_gpr.value = dsk_gpr.toFixed(2);
            dskapi_obshtozaplashtane_input.value = (
              dsk_vnoska * dskapi_vnoski
            ).toFixed(2);
            old_vnoski = dskapi_vnoski;
            dskapi_vnoski_txt.innerHTML = dskapi_vnoski;
          } else {
            alert('Избраният брой погасителни вноски е под минималния.');
            var dskapi_vnoski_input = document.getElementById(
              'dskapi_pogasitelni_vnoski_input'
            );
            dskapi_vnoski_input.value = old_vnoski;
            dskapi_vnoski_txt.innerHTML = old_vnoski;
          }
        } else {
          alert('Избраният брой погасителни вноски е над максималния.');
          var dskapi_vnoski_input = document.getElementById(
            'dskapi_pogasitelni_vnoski_input'
          );
          dskapi_vnoski_input.value = old_vnoski;
          dskapi_vnoski_txt.innerHTML = old_vnoski;
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
function dskapi_calculateAndUpdateProductPrice(showPopup = false) {
  // Взимаме цената с опциите
  let dskapi_price1;
  let el_dskapi_price1 = document.querySelector('span.current-price-value');

  if (el_dskapi_price1 !== null) {
    dskapi_price1 = el_dskapi_price1.getAttribute('content');
  } else {
    el_dskapi_price1 = document.querySelector('[itemprop=price]');
    if (el_dskapi_price1 !== null) {
      el_dskapi_price1 = el_dskapi_price1.innerHTML.replace(/[^\d,-]/g, '');
      if (el_dskapi_price1 !== null) {
        if (el_dskapi_price1.indexOf('.') !== -1) {
          dskapi_price1 = el_dskapi_price1.replace(/[^\d.-]/g, '');
        } else {
          dskapi_price1 = el_dskapi_price1.replace(/,/g, '.');
        }
      }
    }
  }

  // Ако не сме успели да намерим цена, използваме стойността от скритото поле
  if (!dskapi_price1) {
    const dskapi_price = document.getElementById('dskapi_price');
    if (dskapi_price) {
      dskapi_price1 = dskapi_price.value;
    } else {
      return;
    }
  }

  // Взимаме количеството
  let dskapi_quantity = 1;
  if (
    document.getElementsByName('qty') !== null &&
    document.getElementsByName('qty').length > 0
  ) {
    dskapi_quantity =
      parseFloat(document.getElementsByName('qty')[0].value) || 1;
  }

  // Изчисляваме общата цена
  let dskapi_priceall = parseFloat(dskapi_price1) * dskapi_quantity;

  // Прилагаме валутни конверсии ако е необходимо
  const dskapi_eur_el = document.getElementById('dskapi_eur');
  const dskapi_currency_code_el = document.getElementById(
    'dskapi_currency_code'
  );

  if (dskapi_eur_el && dskapi_currency_code_el) {
    const dskapi_eur = parseInt(dskapi_eur_el.value) || 0;
    const dskapi_currency_code = dskapi_currency_code_el.value;

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
  const dskapi_price_txt = document.getElementById('dskapi_price_txt');
  if (dskapi_price_txt) {
    dskapi_price_txt.value = dskapi_priceall.toFixed(2);
  }

  // Проверяваме максималното позволено
  const dskapi_maxstojnost = document.getElementById('dskapi_maxstojnost');
  const dskapiProductPopupContainer = document.getElementById(
    'dskapi-product-popup-container'
  );

  if (!dskapi_maxstojnost) {
    return;
  }

  const maxPrice = parseFloat(dskapi_maxstojnost.value);
  const isValid = dskapi_priceall <= maxPrice;

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
 * Добавя продукта в количката и пренасочва към checkout с избран платежен метод DSK
 *
 * @return {void}
 */
function dskapi_addToCartAndRedirectToCheckout() {
  // Функция за записване на cookie
  const setCookie = function (name, value, days) {
    const expires = new Date();
    expires.setTime(expires.getTime() + days * 24 * 60 * 60 * 1000);
    document.cookie =
      name +
      '=' +
      value +
      ';expires=' +
      expires.toUTCString() +
      ';path=/;SameSite=Lax';
  };

  // Записваме избора на платежен метод в cookie (валиден за 1 час)
  setCookie('dskpayment_selected', '1', 1 / 24);

  // Намираме стандартния бутон за добавяне в количката
  const addToCartButton = document.querySelector(
    'button[data-button-action=add-to-cart]'
  );

  if (addToCartButton) {
    // Регистрираме event listener преди да кликнем на бутона
    let cartUpdated = false;
    const checkoutRedirect = function () {
      if (!cartUpdated) {
        cartUpdated = true;
        const checkoutUrl = document.getElementById(
          'dskapi_checkout_url'
        ).value;
        if (checkoutUrl) {
          window.location.href = checkoutUrl;
        }
      }
    };

    // Слушаме за успешно добавяне в количката
    // PrestaShop 1.7.x използва jQuery събития
    if (typeof jQuery !== 'undefined') {
      jQuery(document).on('updatedCart', checkoutRedirect);
      jQuery(document).on('cartUpdated', checkoutRedirect);
      jQuery(document).on('updateCart', checkoutRedirect);
    }
    // Fallback за PrestaShop 8.x (ако се използва prestashop глобален обект)
    if (typeof prestashop !== 'undefined' && prestashop.on) {
      prestashop.on('updateCart', checkoutRedirect);
      prestashop.on('updatedCart', checkoutRedirect);
    }

    // Добавяме продукта в количката чрез кликване на стандартния бутон
    addToCartButton.click();

    // Fallback: ако след 1 секунда не е настъпило събитие, редиректваме
    setTimeout(checkoutRedirect, 1000);
  } else {
    // Ако няма стандартен бутон, редиректваме директно към чекаута
    const checkoutUrl = document.getElementById('dskapi_checkout_url').value;
    if (checkoutUrl) {
      window.location.href = checkoutUrl;
    }
  }
}

function initDskapiWidget() {
  const btn_dskapi = document.getElementById('btn_dskapi');
  if (btn_dskapi !== null && btn_dskapi.dataset.dskapiBound !== '1') {
    btn_dskapi.dataset.dskapiBound = '1';
    const dskapi_button_status = parseInt(
      document.getElementById('dskapi_button_status').value
    );
    const dskapiProductPopupContainer = document.getElementById(
      'dskapi-product-popup-container'
    );
    const dskapi_back_credit = document.getElementById('dskapi_back_credit');
    const dskapi_buy_credit = document.getElementById('dskapi_buy_credit');

    btn_dskapi.addEventListener('click', (event) => {
      if (dskapi_button_status == 1) {
        event.preventDefault();
        dskapi_addToCartAndRedirectToCheckout();
      } else {
        // При клик на бутона показваме попъпа ако цената е валидна
        dskapi_calculateAndUpdateProductPrice(true);
      }
    });

    dskapi_back_credit.addEventListener('click', (event) => {
      dskapiProductPopupContainer.style.display = 'none';
    });

    dskapi_buy_credit.addEventListener('click', (event) => {
      event.preventDefault();
      dskapiProductPopupContainer.style.display = 'none';
      dskapi_addToCartAndRedirectToCheckout();
    });

    // Слушаме за промяна на количеството
    const qtyInputs = document.getElementsByName('qty');
    if (qtyInputs.length > 0) {
      qtyInputs[0].addEventListener('change', function () {
        // При промяна на количеството актуализираме данните без да показваме попъпа
        dskapi_calculateAndUpdateProductPrice(false);
      });
      qtyInputs[0].addEventListener('input', function () {
        // При въвеждане на количеството актуализираме данните без да показваме попъпа
        dskapi_calculateAndUpdateProductPrice(false);
      });
    }

    // Слушаме за промяна на цената чрез MutationObserver
    const priceObserver = new MutationObserver(function (mutations) {
      mutations.forEach(function (mutation) {
        if (mutation.type === 'attributes' || mutation.type === 'childList') {
          // При промяна на цената актуализираме данните без да показваме попъпа
          dskapi_calculateAndUpdateProductPrice(false);
        }
      });
    });

    // Наблюдаваме промени в елементите с цена
    const priceElements = [
      document.querySelector('span.current-price-value'),
      document.querySelector('[itemprop=price]'),
    ];

    priceElements.forEach(function (element) {
      if (element) {
        priceObserver.observe(element, {
          attributes: true,
          attributeFilter: ['content'],
          childList: true,
          subtree: true,
          characterData: true,
        });
      }
    });

    // Слушаме и за PrestaShop събития за промяна на продукта
    // PrestaShop 1.7.x използва jQuery събития
    if (typeof jQuery !== 'undefined') {
      jQuery(document).on('updatedProduct', function () {
        dskapi_calculateAndUpdateProductPrice(false);
      });
      jQuery(document).on('updatedProductCombination', function () {
        dskapi_calculateAndUpdateProductPrice(false);
      });
      jQuery(document).on('updatedProductAttributes', function () {
        dskapi_calculateAndUpdateProductPrice(false);
      });
      jQuery(document).on('productUpdated', function () {
        dskapi_calculateAndUpdateProductPrice(false);
      });
    }
    // Fallback за PrestaShop 8.x (ако се използва prestashop глобален обект)
    if (typeof prestashop !== 'undefined' && prestashop.on) {
      prestashop.on('updatedProduct', function () {
        dskapi_calculateAndUpdateProductPrice(false);
      });
      prestashop.on('updatedProductCombination', function () {
        dskapi_calculateAndUpdateProductPrice(false);
      });
      prestashop.on('updatedProductAttributes', function () {
        dskapi_calculateAndUpdateProductPrice(false);
      });
    }

    // Изпълняваме процедурата веднъж след зареждане на страницата
    // за да изчислим цената с текущите опции и количество (включително след refresh)
    setTimeout(function () {
      dskapi_calculateAndUpdateProductPrice(false);
    }, 100);
  }
}

// Инициализация при зареждане на страницата - PrestaShop 1.7.x стандартен подход
// Използваме jQuery ready като последен ред, както в работещите модули за PS 1.7.x
jQuery(document).ready(function ($) {
  initDskapiWidget();

  // Слушаме за PrestaShop събития за реинициализация при промяна на продукта
  // PrestaShop 1.7.x използва jQuery събития
  $(document).on('updatedProduct', function () {
    initDskapiWidget();
  });
  $(document).on('updatedProductCombination', function () {
    initDskapiWidget();
  });
  $(document).on('updatedProductAttributes', function () {
    initDskapiWidget();
  });
  $(document).on('productUpdated', function () {
    initDskapiWidget();
  });

  // Fallback за PrestaShop 8.x (ако се използва prestashop глобален обект)
  if (typeof prestashop !== 'undefined' && prestashop.on) {
    prestashop.on('updatedProduct', initDskapiWidget);
    prestashop.on('updatedProductCombination', initDskapiWidget);
    prestashop.on('updatedProductAttributes', initDskapiWidget);
  }
});
