/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	/************************************************************************/
var __webpack_exports__ = {};
/*!*********************************************!*\
  !*** ./mod_dharma_universal_filter/es6/ajax.es6 ***!
  \*********************************************/
/*
 * @package     Dharma Universal Filter Module
 * @subpackage  mod_dharma_universal_filter
 * @version     0.1.0
 * @author      Dharma Design
 * @copyright   Copyright (c) 2026 Dharma Design. All rights reserved.
 * @license     GNU/GPL license: https://www.gnu.org/copyleft/gpl.html
 * @link
 */

function closeFilterOffcanvas(form) {
  var offcanvas = form.closest('.duf-offcanvas');
  if (!offcanvas) {
    return;
  }

  if (window.bootstrap && window.bootstrap.Offcanvas) {
    var offcanvasInstance = window.bootstrap.Offcanvas.getInstance(offcanvas) || new window.bootstrap.Offcanvas(offcanvas);
    offcanvasInstance.hide();
    return;
  }

  var dismiss = offcanvas.querySelector('[data-bs-dismiss="offcanvas"]');
  if (dismiss) {
    dismiss.click();
    return;
  }

  offcanvas.classList.remove('show');
  offcanvas.style.visibility = 'hidden';
  document.body.classList.remove('modal-open');
  document.body.style.overflow = '';
  document.body.style.paddingRight = '';
  document.querySelectorAll('.offcanvas-backdrop').forEach(function (backdrop) {
    backdrop.remove();
  });
}

function updateCheckselect(root) {
  var count = root.querySelectorAll('input[type="checkbox"]:checked').length;
  var text = root.querySelector('.duf-checkselect__text');
  var badge = root.querySelector('.duf-checkselect__count');
  var clear = root.querySelector('.duf-checkselect__clear');
  var summary = root.querySelector('.duf-checkselect__button');

  if (text) {
    text.textContent = count > 0 ? root.dataset.label : root.dataset.empty;
  }

  if (count > 0) {
    if (!badge && summary) {
      badge = document.createElement('span');
      badge.className = 'duf-checkselect__count';
      summary.appendChild(badge);
    }
    badge.textContent = count;

    if (!clear && summary) {
      clear = document.createElement('span');
      clear.className = 'duf-checkselect__clear';
      clear.setAttribute('role', 'button');
      clear.setAttribute('tabindex', '0');
      clear.setAttribute('aria-label', root.dataset.reset || 'Reset');
      clear.setAttribute('data-duf-checkselect-clear', '');
      clear.innerHTML = '&times;';
      summary.appendChild(clear);
    }
  } else if (badge) {
    badge.remove();
    if (clear) {
      clear.remove();
    }
  }
}

document.addEventListener('toggle', function (event) {
  var current = event.target;
  if (!current.matches || !current.matches('[data-duf-checkselect]') || !current.open) {
    return;
  }

  current.closest('form').querySelectorAll('[data-duf-checkselect][open]').forEach(function (details) {
    if (details !== current) {
      details.removeAttribute('open');
    }
  });
}, true);

document.addEventListener('change', function (event) {
  var root = event.target.closest ? event.target.closest('[data-duf-checkselect]') : null;
  if (root && event.target.matches('input[type="checkbox"]')) {
    updateCheckselect(root);

    if (root.dataset.submitMode === 'instant' && window.DharmaUniversalFilter) {
      var form = root.closest('form');
      if (form) {
        window.DharmaUniversalFilter.ajaxSubmit({
          target: form,
          preventDefault: function preventDefault() {}
        });
      }
    }
  }
});

document.addEventListener('click', function (event) {
  if (event.target.closest && !event.target.closest('[data-duf-checkselect]')) {
    document.querySelectorAll('[data-duf-checkselect][open]').forEach(function (details) {
      details.removeAttribute('open');
    });
  }

  var clear = event.target.closest ? event.target.closest('[data-duf-checkselect-clear]') : null;
  if (clear && window.DharmaUniversalFilter) {
    event.preventDefault();
    event.stopPropagation();

    var clearRoot = clear.closest('[data-duf-checkselect]');
    var clearForm = clear.closest('form');
    if (clearRoot) {
      clearRoot.querySelectorAll('input[type="checkbox"]:checked').forEach(function (input) {
        input.checked = false;
      });
      clearRoot.removeAttribute('open');
      updateCheckselect(clearRoot);
    }

    if (clearForm) {
      window.DharmaUniversalFilter.ajaxSubmit({
        target: clearForm,
        preventDefault: function preventDefault() {}
      });
    }
    return;
  }

  var button = event.target.closest ? event.target.closest('[data-duf-checkselect-apply]') : null;
  if (!button || !window.DharmaUniversalFilter) {
    return;
  }

  var details = button.closest('[data-duf-checkselect]');
  var form = button.closest('form');
  if (details) {
    details.removeAttribute('open');
  }

  if (form) {
    window.DharmaUniversalFilter.ajaxSubmit({
      target: form,
      preventDefault: function preventDefault() {}
    });
  }
});

window.DharmaUniversalFilter = {
  ajaxSubmit: function ajaxSubmit(event) {
    var ajaxProducts = document.querySelector('[data-radicalmart-ajax="products"],[radicalmart-ajax="products"]');
    if (ajaxProducts) {
      var form = event.target.tagName.toLowerCase() === 'form' ? event.target : event.target.closest('form');
      if (form) {
		if (event.target.tagName.toLowerCase() === 'form') {
		  event.preventDefault();
		}
		closeFilterOffcanvas(form);
		var loading = document.querySelector('[radicalmart-ajax="loading"],[data-radicalmart-ajax="loading"]');
        if (loading) {
          loading.style.display = '';
        }
		var formData = new FormData(form);
		form.querySelectorAll('[data-dharma-price-slider] input[name^="filter[price]"]').forEach(function (input) {
		  if (!input.dataset.dharmaTouched) {
		    formData.delete(input.name);
		  }
		});
		formData.set('tmpl', 'radicalmart_ajax');
        var url = form.getAttribute('action');
        if (url.indexOf('?') === -1) {
          url += '?';
        }
        url += decodeURI(new URLSearchParams(formData).toString());
        Joomla.request({
          url: url,
          method: 'GET',
          onSuccess: function onSuccess(response) {
            var newHtml = document.createElement('div');
            newHtml.innerHTML = response;
            newHtml.querySelectorAll('[data-dharma-filter-ajax], [radicalmart-ajax], [data-radicalmart-ajax]').forEach(function (replace) {
              var selector = replace.getAttribute('data-dharma-filter-ajax');
              if (!selector) selector = replace.getAttribute('radicalmart-ajax');
              if (!selector) selector = replace.getAttribute('data-radicalmart-ajax');
              if (selector) {
                var htmlSelector = '[data-dharma-filter-ajax="' + selector + '"]',
                  search = document.querySelector(htmlSelector);
                if (!search) {
                  htmlSelector = '[radicalmart-ajax="' + selector + '"]';
                  search = document.querySelector(htmlSelector);
                }
                if (!search) {
                  htmlSelector = '[data-radicalmart-ajax="' + selector + '"]';
                  search = document.querySelector(htmlSelector);
                }
                if (search) {
                  var innerHtml = replace.innerHTML;
                  innerHtml = innerHtml.replace(new RegExp('tmpl=radicalmart_ajax', 'g'), '');
                  search.innerHTML = innerHtml;
                  if (selector === 'products') {
                    try {
                      window.RadicalMartCart().loadActions(htmlSelector);
                    } catch (e) {}
                  }
                }
              }
            });
            var pageTitleEl = newHtml.querySelector('#pageTitle'),
              pageUrlEl = newHtml.querySelector('#pageUrl');
            var pateTitle = pageTitleEl ? pageTitleEl.getAttribute('content') : '',
              pageUrl = pageUrlEl ? pageUrlEl.getAttribute('content') : '';
            if (pateTitle) {
              document.title = pateTitle;
              window.history.pushState('FormData', pateTitle, pageUrl);
            }
            if (loading) {
              loading.style.display = 'none';
            }
            document.dispatchEvent(new CustomEvent('onDharmaUniversalFilterAfterAjax', {
              detail: null
            }));
          },
          onError: function onError(e) {
            if (e.message && e.message !== '' && e.message !== 'Request aborted') {
              console.error(e.message);
            }
            if (loading) {
              loading.style.display = 'none';
            }
          }
        });
      }
    }
  }
};
window.addEventListener('popstate', function () {
  window.location.href = location.href;
});
/******/ })()
;
//# sourceMappingURL=data:application/json;charset=utf-8;base64,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L0I7VUFDRDtRQUNELENBQUMsQ0FBQztNQUNIO0lBQ0Q7RUFDRDtBQUNELENBQUM7QUFDRGYsTUFBTSxDQUFDd0QsZ0JBQWdCLENBQUMsVUFBVSxFQUFFLFlBQU07RUFDekN4RCxNQUFNLENBQUN5RCxRQUFRLENBQUNDLElBQUksR0FBR0QsUUFBUSxDQUFDQyxJQUFJO0FBQ3JDLENBQUMsQ0FBQyxDIiwic291cmNlcyI6WyJ3ZWJwYWNrOi8vbW9kX3JhZGljYWxtYXJ0X2ZpbHRlci8uL21vZF9yYWRpY2FsbWFydF9maWx0ZXIvZXM2L2FqYXguZXM2Il0sInNvdXJjZXNDb250ZW50IjpbIi8qXHJcbiAqIEBwYWNrYWdlICAgICBSYWRpY2FsTWFydCBGaWx0ZXIgTW9kdWxlXHJcbiAqIEBzdWJwYWNrYWdlICBtb2RfcmFkaWNhbG1hcnRfZmlsdGVyXHJcbiAqIEB2ZXJzaW9uICAgICAxLjIuMlxuICogQGF1dGhvciAgICAgIFJhZGljYWxNYXJ0IFRlYW0gLSByYWRpY2FsbWFydC5ydVxyXG4gKiBAY29weXJpZ2h0ICAgQ29weXJpZ2h0IChjKSAyMDI0IFJhZGljYWxNYXJ0LiBBbGwgcmlnaHRzIHJlc2VydmVkLlxyXG4gKiBAbGljZW5zZSAgICAgR05VL0dQTCBsaWNlbnNlOiBodHRwczovL3d3dy5nbnUub3JnL2NvcHlsZWZ0L2dwbC5odG1sXHJcbiAqIEBsaW5rICAgICAgICBodHRwczovL3JhZGljYWxtYXJ0LnJ1L1xyXG4gKi9cclxuXHJcblwidXNlIHN0cmljdFwiO1xyXG5cclxud2luZG93LlJhZGljYWxtYXJ0RmlsdGVyID0ge1xyXG5cdGFqYXhTdWJtaXQ6IChldmVudCkgPT4ge1xyXG5cdFx0bGV0IGFqYXhQcm9kdWN0cyA9IGRvY3VtZW50LnF1ZXJ5U2VsZWN0b3IoJ1tkYXRhLXJhZGljYWxtYXJ0LWFqYXg9XCJwcm9kdWN0c1wiXSxbcmFkaWNhbG1hcnQtYWpheD1cInByb2R1Y3RzXCJdJyk7XHJcblx0XHRpZiAoYWpheFByb2R1Y3RzKSB7XHJcblx0XHRcdGxldCBmb3JtID0gKGV2ZW50LnRhcmdldC50YWdOYW1lLnRvTG93ZXJDYXNlKCkgPT09ICdmb3JtJykgPyBldmVudC50YXJnZXQgOiBldmVudC50YXJnZXQuY2xvc2VzdCgnZm9ybScpO1xyXG5cdFx0XHRpZiAoZm9ybSkge1xyXG5cdFx0XHRcdGlmIChldmVudC50YXJnZXQudGFnTmFtZS50b0xvd2VyQ2FzZSgpID09PSAnZm9ybScpIHtcclxuXHRcdFx0XHRcdGV2ZW50LnByZXZlbnREZWZhdWx0KCk7XHJcblx0XHRcdFx0fVxyXG5cclxuXHRcdFx0XHRsZXQgbG9hZGluZyA9IGRvY3VtZW50LnF1ZXJ5U2VsZWN0b3IoJ1tyYWRpY2FsbWFydC1hamF4PVwibG9hZGluZ1wiXSxbZGF0YS1yYWRpY2FsbWFydC1hamF4PVwibG9hZGluZ1wiXScpO1xyXG5cdFx0XHRcdGlmIChsb2FkaW5nKSB7XHJcblx0XHRcdFx0XHRsb2FkaW5nLnN0eWxlLmRpc3BsYXkgPSAnJztcclxuXHRcdFx0XHR9XHJcblxyXG5cdFx0XHRcdGxldCBmb3JtRGF0YSA9IG5ldyBGb3JtRGF0YShmb3JtKTtcclxuXHRcdFx0XHRmb3JtRGF0YS5zZXQoJ3RtcGwnLCAncmFkaWNhbG1hcnRfYWpheCcpO1xyXG5cclxuXHRcdFx0XHRsZXQgdXJsID0gZm9ybS5nZXRBdHRyaWJ1dGUoJ2FjdGlvbicpO1xyXG5cdFx0XHRcdGlmICh1cmwuaW5kZXhPZignPycpID09PSAtMSkge1xyXG5cdFx0XHRcdFx0dXJsICs9ICc/JztcclxuXHRcdFx0XHR9XHJcblx0XHRcdFx0dXJsICs9IGRlY29kZVVSSShuZXcgVVJMU2VhcmNoUGFyYW1zKGZvcm1EYXRhKS50b1N0cmluZygpKTtcclxuXHJcblx0XHRcdFx0Sm9vbWxhLnJlcXVlc3Qoe1xyXG5cdFx0XHRcdFx0dXJsOiB1cmwsXHJcblx0XHRcdFx0XHRtZXRob2Q6ICdHRVQnLFxyXG5cdFx0XHRcdFx0b25TdWNjZXNzOiAocmVzcG9uc2UpID0+IHtcclxuXHRcdFx0XHRcdFx0bGV0IG5ld0h0bWwgPSBkb2N1bWVudC5jcmVhdGVFbGVtZW50KCdkaXYnKTtcclxuXHRcdFx0XHRcdFx0bmV3SHRtbC5pbm5lckhUTUwgPSByZXNwb25zZTtcclxuXHJcblx0XHRcdFx0XHRcdG5ld0h0bWwucXVlcnlTZWxlY3RvckFsbCgnW3JhZGljYWxtYXJ0LWFqYXhdLCBbZGF0YS1yYWRpY2FsbWFydC1hamF4XScpXHJcblx0XHRcdFx0XHRcdFx0LmZvckVhY2goKHJlcGxhY2UpID0+IHtcclxuXHRcdFx0XHRcdFx0XHRcdGxldCBzZWxlY3RvciA9IHJlcGxhY2UuZ2V0QXR0cmlidXRlKCdyYWRpY2FsbWFydC1hamF4Jyk7XHJcblx0XHRcdFx0XHRcdFx0XHRpZiAoIXNlbGVjdG9yKSBzZWxlY3RvciA9IHJlcGxhY2UuZ2V0QXR0cmlidXRlKCdkYXRhLXJhZGljYWxtYXJ0LWFqYXgnKTtcclxuXHJcblx0XHRcdFx0XHRcdFx0XHRpZiAoc2VsZWN0b3IpIHtcclxuXHRcdFx0XHRcdFx0XHRcdFx0bGV0IGh0bWxTZWxlY3RvciA9ICdbcmFkaWNhbG1hcnQtYWpheD1cIicgKyBzZWxlY3RvciArICdcIl0nLFxyXG5cdFx0XHRcdFx0XHRcdFx0XHRcdHNlYXJjaCA9IGRvY3VtZW50LnF1ZXJ5U2VsZWN0b3IoaHRtbFNlbGVjdG9yKTtcclxuXHRcdFx0XHRcdFx0XHRcdFx0aWYgKCFzZWFyY2gpIHtcclxuXHRcdFx0XHRcdFx0XHRcdFx0XHRodG1sU2VsZWN0b3IgPSAnW2RhdGEtcmFkaWNhbG1hcnQtYWpheD1cIicgKyBzZWxlY3RvciArICdcIl0nO1xyXG5cdFx0XHRcdFx0XHRcdFx0XHRcdHNlYXJjaCA9IGRvY3VtZW50LnF1ZXJ5U2VsZWN0b3IoaHRtbFNlbGVjdG9yKTtcclxuXHRcdFx0XHRcdFx0XHRcdFx0fVxyXG5cdFx0XHRcdFx0XHRcdFx0XHRpZiAoc2VhcmNoKSB7XHJcblx0XHRcdFx0XHRcdFx0XHRcdFx0bGV0IGlubmVySHRtbCA9IHJlcGxhY2UuaW5uZXJIVE1MO1xyXG5cdFx0XHRcdFx0XHRcdFx0XHRcdGlubmVySHRtbCA9IGlubmVySHRtbC5yZXBsYWNlKG5ldyBSZWdFeHAoJ3RtcGw9cmFkaWNhbG1hcnRfYWpheCcsICdnJyksICcnKTtcclxuXHRcdFx0XHRcdFx0XHRcdFx0XHRzZWFyY2guaW5uZXJIVE1MID0gaW5uZXJIdG1sO1xyXG5cdFx0XHRcdFx0XHRcdFx0XHRcdGlmIChzZWxlY3RvciA9PT0gJ3Byb2R1Y3RzJykge1xyXG5cdFx0XHRcdFx0XHRcdFx0XHRcdFx0dHJ5IHtcclxuXHRcdFx0XHRcdFx0XHRcdFx0XHRcdFx0d2luZG93LlJhZGljYWxNYXJ0Q2FydCgpLmxvYWRBY3Rpb25zKGh0bWxTZWxlY3Rvcik7XHJcblx0XHRcdFx0XHRcdFx0XHRcdFx0XHR9IGNhdGNoIChlKSB7XHJcblxyXG5cdFx0XHRcdFx0XHRcdFx0XHRcdFx0fVxyXG5cdFx0XHRcdFx0XHRcdFx0XHRcdH1cclxuXHRcdFx0XHRcdFx0XHRcdFx0fVxyXG5cdFx0XHRcdFx0XHRcdFx0fVxyXG5cdFx0XHRcdFx0XHRcdH0pXHJcblxyXG5cdFx0XHRcdFx0XHRsZXQgcGF0ZVRpdGxlID0gbmV3SHRtbC5xdWVyeVNlbGVjdG9yKCcjcGFnZVRpdGxlJykuZ2V0QXR0cmlidXRlKCdjb250ZW50JyksXHJcblx0XHRcdFx0XHRcdFx0cGFnZVVybCA9IG5ld0h0bWwucXVlcnlTZWxlY3RvcignI3BhZ2VVcmwnKS5nZXRBdHRyaWJ1dGUoJ2NvbnRlbnQnKTtcclxuXHRcdFx0XHRcdFx0aWYgKHBhdGVUaXRsZSkge1xyXG5cdFx0XHRcdFx0XHRcdGRvY3VtZW50LnRpdGxlID0gcGF0ZVRpdGxlO1xyXG5cdFx0XHRcdFx0XHRcdHdpbmRvdy5oaXN0b3J5LnB1c2hTdGF0ZSgnRm9ybURhdGEnLCBwYXRlVGl0bGUsIHBhZ2VVcmwpO1xyXG5cdFx0XHRcdFx0XHR9XHJcblxyXG5cdFx0XHRcdFx0XHRpZiAobG9hZGluZykge1xyXG5cdFx0XHRcdFx0XHRcdGxvYWRpbmcuc3R5bGUuZGlzcGxheSA9ICdub25lJztcclxuXHRcdFx0XHRcdFx0fVxyXG5cclxuXHRcdFx0XHRcdFx0ZG9jdW1lbnQuZGlzcGF0Y2hFdmVudChuZXcgQ3VzdG9tRXZlbnQoJ29uUmFkaWNhbE1hcnRGaWx0ZXJBZnRlckFqYXgnLCB7ZGV0YWlsOiBudWxsfSkpO1xyXG5cdFx0XHRcdFx0fSxcclxuXHRcdFx0XHRcdG9uRXJyb3I6IChlKSA9PiB7XHJcblx0XHRcdFx0XHRcdGlmIChlLm1lc3NhZ2UgJiYgZS5tZXNzYWdlICE9PSAnJyAmJiBlLm1lc3NhZ2UgIT09ICdSZXF1ZXN0IGFib3J0ZWQnKSB7XHJcblx0XHRcdFx0XHRcdFx0Y29uc29sZS5lcnJvcihlLm1lc3NhZ2UpO1xyXG5cdFx0XHRcdFx0XHR9XHJcblx0XHRcdFx0XHRcdGlmIChsb2FkaW5nKSB7XHJcblx0XHRcdFx0XHRcdFx0bG9hZGluZy5zdHlsZS5kaXNwbGF5ID0gJ25vbmUnO1xyXG5cdFx0XHRcdFx0XHR9XHJcblx0XHRcdFx0XHR9XHJcblx0XHRcdFx0fSk7XHJcblx0XHRcdH1cclxuXHRcdH1cclxuXHR9LFxyXG59O1xyXG53aW5kb3cuYWRkRXZlbnRMaXN0ZW5lcigncG9wc3RhdGUnLCAoKSA9PiB7XHJcblx0d2luZG93LmxvY2F0aW9uLmhyZWYgPSBsb2NhdGlvbi5ocmVmO1xyXG59KTsiXSwibmFtZXMiOlsid2luZG93IiwiUmFkaWNhbG1hcnRGaWx0ZXIiLCJhamF4U3VibWl0IiwiZXZlbnQiLCJhamF4UHJvZHVjdHMiLCJkb2N1bWVudCIsInF1ZXJ5U2VsZWN0b3IiLCJmb3JtIiwidGFyZ2V0IiwidGFnTmFtZSIsInRvTG93ZXJDYXNlIiwiY2xvc2VzdCIsInByZXZlbnREZWZhdWx0IiwibG9hZGluZyIsInN0eWxlIiwiZGlzcGxheSIsImZvcm1EYXRhIiwiRm9ybURhdGEiLCJzZXQiLCJ1cmwiLCJnZXRBdHRyaWJ1dGUiLCJpbmRleE9mIiwiZGVjb2RlVVJJIiwiVVJMU2VhcmNoUGFyYW1zIiwidG9TdHJpbmciLCJKb29tbGEiLCJyZXF1ZXN0IiwibWV0aG9kIiwib25TdWNjZXNzIiwicmVzcG9uc2UiLCJuZXdIdG1sIiwiY3JlYXRlRWxlbWVudCIsImlubmVySFRNTCIsInF1ZXJ5U2VsZWN0b3JBbGwiLCJmb3JFYWNoIiwicmVwbGFjZSIsInNlbGVjdG9yIiwiaHRtbFNlbGVjdG9yIiwic2VhcmNoIiwiaW5uZXJIdG1sIiwiUmVnRXhwIiwiUmFkaWNhbE1hcnRDYXJ0IiwibG9hZEFjdGlvbnMiLCJlIiwicGF0ZVRpdGxlIiwicGFnZVVybCIsInRpdGxlIiwiaGlzdG9yeSIsInB1c2hTdGF0ZSIsImRpc3BhdGNoRXZlbnQiLCJDdXN0b21FdmVudCIsImRldGFpbCIsIm9uRXJyb3IiLCJtZXNzYWdlIiwiY29uc29sZSIsImVycm9yIiwiYWRkRXZlbnRMaXN0ZW5lciIsImxvY2F0aW9uIiwiaHJlZiJdLCJzb3VyY2VSb290IjoiIn0=
