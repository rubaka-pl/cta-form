<?php if (!defined('ABSPATH')) exit; ?>
<?php $assets_base = plugins_url('assets/', CTA_CF_PLUGIN_FILE); ?>
<div class="modal modal--success" id="cta-cf-modal"
    aria-hidden="true" role="dialog" aria-labelledby="cfModalTitle">
    <div class="modal__panel" role="document" tabindex="-1">
        <button type="button" class="modal__close js-modal-close"><span>Закрити</span><img src="<?php echo esc_url($assets_base . 'img/close.png'); ?>"></button>

        <div class="modal__icon" aria-hidden="true">
            <img src="<?php echo esc_url($assets_base . 'img/rocket.png'); ?>"
                alt="rocket" width="34" height="34" decoding="async">
        </div>

        <p class="modal__eyebrow">Ваш запит надіслано</p>
        <h3 id="cfModalTitle" class="modal__title">Дякуємо, <br> що довіряєте!</h3>
    </div>
</div>