(function () {
  var loader = '<div>\n' +
      '  <svg version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"\n' +
      '     width="24px" height="30px" viewBox="0 0 24 30" style="enable-background:new 0 0 50 50;" xml:space="preserve">\n' +
      '    <rect x="0" y="0" width="4" height="20" fill="#333">\n' +
      '      <animate attributeName="opacity" attributeType="XML"\n' +
      '        values="1; .2; 1" \n' +
      '        begin="0s" dur="0.6s" repeatCount="indefinite" />\n' +
      '    </rect>\n' +
      '    <rect x="7" y="0" width="4" height="20" fill="#333">\n' +
      '      <animate attributeName="opacity" attributeType="XML"\n' +
      '        values="1; .2; 1" \n' +
      '        begin="0.2s" dur="0.6s" repeatCount="indefinite" />\n' +
      '    </rect>\n' +
      '    <rect x="14" y="0" width="4" height="20" fill="#333">\n' +
      '      <animate attributeName="opacity" attributeType="XML"\n' +
      '        values="1; .2; 1" \n' +
      '        begin="0.4s" dur="0.6s" repeatCount="indefinite" />\n' +
      '    </rect>\n' +
      '  </svg>\n' +
      '</div>';

  var style = document.createElement("style");
  style.type = "text/css";
  style.appendChild(document.createTextNode(
      ".js-overlay {" +
      "  z-index: 999999999;" +
      "  display: none;" +
      "  opacity: 0;" +
      "  position: fixed;" +
      "  top: 0;" +
      "  left: 0;" +
      "  right: 0;" +
      "  bottom: 0;" +
      "  background-color: rgba(0, 0, 0, .5);" +
      "  -webkit-transition: opacity .15s ease-in;" +
      "  transition: opacity .15s ease-in;" +
      "  cursor: pointer;" +
      "}" +
      ".js-popup {" +
      "  z-index: 9999999999;" +
      "  display: none;" +
      "  opacity: 0;" +
      "  position: fixed;" +
      "  left: 50%;" +
      "  top: 50%;" +
      "  width: 90%;" +
      "  max-width: 600px;" +
      "  transform: translate(-50%, -50%);" +
      "  padding: 2rem;" +
      "  text-align: center;" +
      "  background-color: white;" +
      "  -webkit-box-shadow: 0 0 8px 2px rgba(0,0,0,.2);" +
      "  -moz-box-shadow: 0 0 8px 2px rgba(0,0,0,.2);" +
      "  box-shadow: 0 0 8px 2px rgba(0,0,0,.2);" +
      "  -webkit-border-radius: 4px;" +
      "  -moz-border-radius: 4px;" +
      "  border-radius: 4px;" +
      "  font-family: 'Open Sans', sans-serif;" +
      "  color: rgba(0, 0, 0, .75);" +
      "  -webkit-transition: opacity .15s ease-in;" +
      "  transition: opacity .15s ease-in;" +
      "}" +
      ".js-popup ul {" +
      "  margin: 2rem 0;" +
      "  text-align: left;" +
      "}" +
      ".js-popup-close {" +
      "  float: right;" +
      "  line-height: 1;" +
      "  margin: -1rem -1rem 0 0;" +
      "  cursor: pointer;" +
      "}" +
      ".js-popup h3 {" +
      "  margin-bottom: 1rem;" +
      "  font-weight: 700;" +
      "}"
  ));
  document.head.appendChild(style);

  var overlay = document.createElement("div");
  overlay.className = "js-overlay";
  document.body.appendChild(overlay);
  overlay.addEventListener("click", function () {
    hidePopup();
  });

  var popup = document.createElement("div");
  popup.className = "js-popup";
  document.body.appendChild(popup);
  popupClose = document.createElement("span");
  popupClose.className = "js-popup-close";
  popupClose.title = "Закрыть";
  popupClose.innerHTML = "&#10006";
  popup.appendChild(popupClose);
  popupClose.addEventListener("click", function () {
    hidePopup();
  });
  popupContent = document.createElement("div");
  popup.appendChild(popupContent);

  function showPopup() {
    overlay.style.display = "block";
    popup.style.display = "block";
    window.setTimeout(function () {
      overlay.style.opacity = 1;
      popup.style.opacity = 1;
    });
  }

  function hidePopup() {
    overlay.style.opacity = 0;
    popup.style.opacity = 0;
    window.setTimeout(function () {
      overlay.style.display = "none";
      popup.style.display = "none";
    }, 400);
  }

  var dataLayer = [];
  for (var formIndex = 0, forms = document.querySelectorAll("form"); formIndex < forms.length; formIndex++) {
    (function (form) {
      var submitButtons = form.querySelectorAll("[type='submit']");
      form.addEventListener("submit", function (e) {
        e.preventDefault();
        for (var buttonIndex = 0; buttonIndex < submitButtons.length; buttonIndex++) {
          submitButtons[buttonIndex].disabled = true;
        }
        showPopup();
        popupContent.innerHTML = "<p>Подождите, заявка отправляется...</p>" + loader;
        var xhr = new XMLHttpRequest();
        xhr.open(form.method, form.action);
        xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
        xhr.responseType = "json";
        xhr.onload = function () {
          for (var buttonIndex = 0; buttonIndex < submitButtons.length; buttonIndex++) {
            submitButtons[buttonIndex].disabled = false;
          }
          if (xhr.status === 200 || window.location.host === 'freelandings.ru') {
            if (typeof dataLayer !== 'undefined') {
              dataLayer.push({
                'event': 'submit'
              });
            }
            if (xhr.response) {
              if (xhr.response.html) {
                popupContent.innerHTML = xhr.response.html;
              }
              if (xhr.response.redirect) {
                window.location.href = xhr.response.redirect;
              }
            } else {
              popupContent.innerHTML = "<h3>Ваша заявка принята</h3>" +
                  "<p>Мы свяжемся с вами в течение 15 минут для подтверждения заказа.</p>";
            }
          } else {
            if (xhr.response) {
              if (xhr.response.html) {
                popupContent.innerHTML = xhr.response.html;
              }
              if (xhr.response.redirect) {
                window.location.href = xhr.response.redirect;
              }
            } else {
              popupContent.innerHTML = "<h3>Ошибка отправки формы</h3><p>Попробуйте повторить попытку позднее</p>";
            }
          }
        };
        var formData = new FormData(form);
        xhr.send(formData);
      });
    })(forms[formIndex])
  }
})();