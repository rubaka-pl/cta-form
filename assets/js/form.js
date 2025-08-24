(function ($) {
  const $form = $('#cta-cf');
  if (!$form.length) return;

  const $modal = $('#cta-cf-modal');
  const $close = $modal.find('.js-modal-close');

  const $btn = $('#submitBtn');
  const $name = $('#name');
  const $email = $('#email');
  const $phone = $('#phone');
  const $full = $('#phone_full');
  const $iso = $('#country_iso');
  const $dial = $('#dial_code');

  function toggleBtn() {
    $btn.prop('disabled', !($name.val().trim() && $phone.val().trim()));
  }

  const UTILS_URL =
    'https://cdn.jsdelivr.net/npm/intl-tel-input@25.4.3/build/js/utils.js';
  const utilsPromise = import(UTILS_URL).catch(() => null);

  // default
  let iti = null;
  if (window.intlTelInput && $phone[0]) {
    iti = window.intlTelInput($phone[0], {
      initialCountry: 'ua',
      separateDialCode: true,
      preferredCountries: ['ua', 'pl', 'de', 'gb', 'us'],
      autoPlaceholder: 'aggressive',
      formatAsYouType: true,
      loadUtils: () => utilsPromise,
    });
  }

  let lockedIso = iti ? (iti.getSelectedCountryData() || {}).iso2 : null;
  let userPicking = false;
  let reentryGuard = false;

  $phone.on('open:countrydropdown', () => {
    userPicking = true;
  });
  $phone.on('close:countrydropdown', () => {
    userPicking = false;
    if (iti) lockedIso = (iti.getSelectedCountryData() || {}).iso2;
  });

  function syncHidden() {
    if (!iti) return;
    const d = iti.getSelectedCountryData() || {};
    if ($iso.length) $iso.val(d.iso2 || '');
    if ($dial.length) $dial.val(d.dialCode ? `+${d.dialCode}` : '');
  }

  $phone.on('countrychange', () => {
    if (!iti) return;
    const curIso = (iti.getSelectedCountryData() || {}).iso2;

    if (
      !reentryGuard &&
      lockedIso &&
      curIso &&
      curIso !== lockedIso &&
      $phone.val().trim() !== ''
    ) {
      reentryGuard = true;
      iti.setCountry(lockedIso);
      syncHidden();
      reentryGuard = false;
    }
  });

  if (iti) syncHidden();

  // === name === //
  const NAME_RE = /^[\p{L}]+(?: [\p{L}]+)*$/u;
  $name
    .on('input', function () {
      let v = this.value;
      const hadIllegal = /[^\p{L} ]/u.test(v);
      v = v
        .replace(/^\s+/, '')
        .replace(/\s{2,}/g, ' ')
        .replace(/[^\p{L} ]/gu, '');
      if (this.value !== v) this.value = v;

      const ok = NAME_RE.test(v) && v.length >= 2 && v.length <= 80;
      $('#nameHelp').text(
        hadIllegal
          ? 'Ввести можна тільки літери'
          : ok
          ? ''
          : 'Лише літери. Один пробіл між словами.'
      );
      $(this).toggleClass('is-invalid', !ok).attr('aria-invalid', !ok);

      toggleBtn();
    })
    .on('blur', function () {
      this.value = this.value.replace(/\s+$/, '');
      const v = this.value;
      const ok = NAME_RE.test(v) && v.length >= 2 && v.length <= 80;
      $(this).toggleClass('is-invalid', !ok).attr('aria-invalid', !ok);
      $('#nameHelp').text(ok ? '' : 'Лише літери. Один пробіл між словами.');
    });

  // === phone === //
  function validatePhone(showMsg = true) {
    const $help = $('#phoneHelp');
    let ok = false,
      msg = '';

    if (!iti) {
      const digits = ($phone.val().match(/\d/g) || []).length;
      ok = digits >= 6;
      msg = ok ? '' : digits ? 'Номер закороткий' : 'Це не номер телефону';
    } else {
      ok = iti.isValidNumber();
      if (!ok) {
        const err = iti.getValidationError();
        const MAP = {
          1: 'Невірний код країни',
          2: 'Номер закороткий',
          3: 'Номер задовгий',
          4: 'Неправильна довжина',
          5: 'Невідома помилка',
        };
        msg = MAP[err] || 'Перевірте номер телефону';
      }
    }

    $phone.toggleClass('is-invalid', !ok).attr('aria-invalid', !ok);

    if (showMsg) {
      if (ok) {
        $help.text('').hide();
      } else {
        $help.text(msg).show();
      }
    }
    return ok;
  }

  $phone.on('input', function () {
    const raw = this.value;
    const filtered = raw.replace(/[^0-9\- ()]/g, '');
    if (raw !== filtered) this.value = filtered;
    validatePhone(true);
    toggleBtn();
  });

  $phone.on('blur', () => validatePhone(true));

  // === email === //
  function validateEmail(showMsg = true) {
    const $help = $('#emailHelp');
    const v = $email.val().trim();
    const ok = v === '' ? true : $email[0].checkValidity();
    $email.toggleClass('is-invalid', !ok).attr('aria-invalid', !ok);
    if (showMsg) $help.text(ok ? '' : 'Будь ласка, введіть коректний e-mail');
    return ok;
  }
  $email.on('input blur', () => validateEmail(true));
  $email.on('input change', toggleBtn);
  toggleBtn();

  // === Modal === //

  function openModal() {
    $('body').addClass('modal-open');
    $modal.attr('aria-hidden', 'false').addClass('is-open');
    $close.trigger('focus');
  }
  function closeModal() {
    $modal.attr('aria-hidden', 'true').removeClass('is-open');
    $('body').removeClass('modal-open');
    $form.find('input,textarea,button').first().trigger('focus');
  }

  $close.on('click', closeModal);
  $(document).on('keydown', function (e) {
    if (e.key === 'Escape') closeModal();
  });
  $modal.on('click', function (e) {
    if (e.target === this) closeModal();
  });

  function resetForm() {
    $form[0].reset();

    $form
      .find('.is-invalid')
      .removeClass('is-invalid')
      .attr('aria-invalid', 'false');
    $('#phoneHelp, #emailHelp, #nameHelp').text('');

    if (window.iti && typeof iti.setNumber === 'function') {
      iti.setNumber('');
    }
    $('#country_iso,#dial_code,#phone_full').val('');

    $('#submitBtn')
      .prop('disabled', true)
      .removeClass('loading')
      .find('.spinner')
      .addClass('is-hidden');
  }

  // === submit === //

  document.addEventListener('DOMContentLoaded', function () {
    var f =
      document.querySelector('form[data-cta-form]') ||
      document.querySelector('form');
    if (!f) return;
    var inp = f.querySelector('input[name="page"]');
    if (inp) inp.value = window.location.href;
  });

  $form.on('submit', function (e) {
    e.preventDefault();
    const ok =
      $('#name').val().trim().length >= 2 &&
      !$('#name').hasClass('is-invalid') &&
      validatePhone(true) &&
      (function () {
        const v = $email.val().trim();
        return !v || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
      })();

    if (!ok) return;

    if (iti && $full.length) $full.val(iti.getNumber()); // E.164

    $btn.prop('disabled', true).addClass('loading');
    $form.find('.spinner').removeClass('is-hidden');
    $.post(CTA_CF.ajax, $form.serialize())
      .done(function (resp) {
        if (resp && resp.success) {
          resetForm();
          openModal();
        } else if (resp && resp.data && resp.data.errors) {
          if (resp.data.errors.name)
            $name.addClass('is-invalid').attr('aria-invalid', true);
          if (resp.data.errors.email)
            $email.addClass('is-invalid').attr('aria-invalid', true);
          if (resp.data.errors.phone)
            $phone.addClass('is-invalid').attr('aria-invalid', true);
        } else {
          alert('Помилка надсилання.');
        }
      })
      .fail(function () {
        alert('Помилка надсилання.');
      })
      .always(function () {
        $form.find('.spinner').addClass('is-hidden');
        $btn.removeClass('loading').prop('disabled', false);
      });
  });
})(jQuery);
