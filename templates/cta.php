<?php
if (!defined('ABSPATH')) exit;

$assets_base = plugins_url('assets/', CTA_CF_PLUGIN_FILE);
?>

<section class="col-left cta-left" aria-labelledby="cta-headline">
    <div class="cta-wrapper">
        <h1 id="cta-headline" class="headline">
            Ми завжди готові запропонувати інноваційні <br>
            та альтернативні шляхи <br>
            лікування зубів
        </h1>
    </div>

    <img class="avatar avatar--tl"
        src="<?php echo esc_url($assets_base . 'img/avatar-2.png'); ?>"
        alt="аватар усміхненої жінки">

    <img class="avatar avatar--mid"
        src="<?php echo esc_url($assets_base . 'img/avatar-1.png'); ?>"
        alt="аватар усміхненої жінки">

    <img class="decor-img"
        src="<?php echo esc_url($assets_base . 'img/background-sprite.svg'); ?>"
        alt="" aria-hidden="true">
</section>
