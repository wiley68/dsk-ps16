/**
 * @File: dskapi_payment.js
 * @Author: Ilko Ivanov
 * @Author e-mail: ilko.iv@gmail.com
 * @Publisher: Avalon Ltd
 * @Publisher e-mail: home@avalonbg.com
 * @Owner: Банка ДСК
 * @Version: 1.2.0
 *
 * JavaScript за попъп с лихвени схеми в страницата за плащане
 */

var dskapi_payment_old_vnoski;

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

function dskapi_payment_pogasitelni_vnoski_input_focus(_old_vnoski) {
  dskapi_payment_old_vnoski = _old_vnoski;
}

function dskapi_payment_pogasitelni_vnoski_input_change() {
  var dskapi_vnoski_el = document.getElementById(
    'dskapi_payment_pogasitelni_vnoski_input'
  );
  if (!dskapi_vnoski_el) return;

  var dskapi_vnoski = parseFloat(dskapi_vnoski_el.value);
  var dskapi_price_el = document.getElementById('dskapi_payment_price_txt');
  if (!dskapi_price_el) return;

  var dskapi_price = parseFloat(dskapi_price_el.value);
  var dskapi_cid_el = document.getElementById('dskapi_payment_cid');
  var DSKAPI_LIVEURL_el = document.getElementById('dskapi_payment_LIVEURL');
  var dskapi_product_id_el = document.getElementById(
    'dskapi_payment_product_id'
  );

  if (!dskapi_cid_el || !DSKAPI_LIVEURL_el || !dskapi_product_id_el) return;

  var dskapi_cid = dskapi_cid_el.value;
  var DSKAPI_LIVEURL = DSKAPI_LIVEURL_el.value;
  var dskapi_product_id = dskapi_product_id_el.value;

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

  xmlhttpro.onreadystatechange = function () {
    if (this.readyState == 4) {
      try {
        var response = JSON.parse(this.response);
        var options = response.dsk_options;
        var dsk_vnoska = parseFloat(response.dsk_vnoska);
        var dsk_gpr = parseFloat(response.dsk_gpr);
        var dsk_is_visible = response.dsk_is_visible;

        if (dsk_is_visible) {
          if (options) {
            var dskapi_vnoska_input = document.getElementById(
              'dskapi_payment_vnoska'
            );
            var dskapi_gpr = document.getElementById('dskapi_payment_gpr');
            var dskapi_obshtozaplashtane_input = document.getElementById(
              'dskapi_payment_obshtozaplashtane'
            );

            if (dskapi_vnoska_input) {
              dskapi_vnoska_input.value = dsk_vnoska.toFixed(2);
            }
            if (dskapi_gpr) {
              dskapi_gpr.value = dsk_gpr.toFixed(2);
            }
            if (dskapi_obshtozaplashtane_input) {
              dskapi_obshtozaplashtane_input.value = (
                dsk_vnoska * dskapi_vnoski
              ).toFixed(2);
            }
            dskapi_payment_old_vnoski = dskapi_vnoski;
          } else {
            alert('Избраният брой погасителни вноски е под минималния.');
            dskapi_vnoski_el.value = dskapi_payment_old_vnoski;
          }
        } else {
          alert('Избраният брой погасителни вноски е над максималния.');
          dskapi_vnoski_el.value = dskapi_payment_old_vnoski;
        }
      } catch (e) {
        console.error('Error parsing API response:', e);
      }
    }
  };
  xmlhttpro.send();
}

function initDskapiPaymentPopup() {
  var interestRatesLink = document.getElementById(
    'dskapi_checkout_interest_rates_link'
  );
  var popupContainer = document.getElementById(
    'dskapi-payment-popup-container'
  );
  var closeButton = document.getElementById('dskapi_payment_close');

  if (!interestRatesLink || !popupContainer) {
    return;
  }

  // Отваряне на попъпа при клик върху линка
  interestRatesLink.addEventListener('click', function (event) {
    event.preventDefault();
    event.stopPropagation();

    var dskapi_price_el = document.getElementById('dskapi_payment_price');
    var dskapi_maxstojnost_el = document.getElementById(
      'dskapi_payment_maxstojnost'
    );
    var dskapi_price_txt = document.getElementById('dskapi_payment_price_txt');

    if (dskapi_price_el && dskapi_price_txt) {
      var dskapi_price = parseFloat(dskapi_price_el.value);

      // Конвертиране на валута ако е необходимо
      var dskapi_eur_el = document.getElementById('dskapi_payment_eur');
      var dskapi_currency_code_el = document.getElementById(
        'dskapi_payment_currency_code'
      );

      if (dskapi_eur_el && dskapi_currency_code_el) {
        var dskapi_eur = parseInt(dskapi_eur_el.value) || 0;
        var dskapi_currency_code = dskapi_currency_code_el.value;

        switch (dskapi_eur) {
          case 1:
            if (dskapi_currency_code == 'EUR') {
              dskapi_price = dskapi_price * 1.95583;
            }
            break;
          case 2:
          case 3:
            if (dskapi_currency_code == 'BGN') {
              dskapi_price = dskapi_price / 1.95583;
            }
            break;
        }
      }

      dskapi_price_txt.value = dskapi_price.toFixed(2);

      // Проверка за максимална стойност
      if (
        dskapi_maxstojnost_el &&
        dskapi_price > parseFloat(dskapi_maxstojnost_el.value)
      ) {
        alert(
          'Максимално позволената цена за кредит ' +
            parseFloat(dskapi_maxstojnost_el.value).toFixed(2) +
            ' е надвишена!'
        );
        return false;
      }
    }

    popupContainer.style.display = 'block';
    dskapi_payment_pogasitelni_vnoski_input_change();
    return false;
  });

  // Затваряне на попъпа при клик върху бутона "Затвори"
  if (closeButton) {
    closeButton.addEventListener('click', function (event) {
      event.preventDefault();
      popupContainer.style.display = 'none';
      return false;
    });
  }

  // Затваряне на попъпа при клик извън него
  popupContainer.addEventListener('click', function (event) {
    if (event.target === popupContainer) {
      popupContainer.style.display = 'none';
    }
  });

  // Затваряне с Escape
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && popupContainer.style.display === 'block') {
      popupContainer.style.display = 'none';
    }
  });
}

// Инициализация при зареждане на DOM
document.addEventListener('DOMContentLoaded', function () {
  initDskapiPaymentPopup();
});

// Също опитваме се след пълно зареждане на страницата
window.addEventListener('load', function () {
  setTimeout(initDskapiPaymentPopup, 100);
});

