<?php
// templates/form.php
if (!defined('ABSPATH')) exit;

$utm_keys     = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
$current_page = esc_url(
    (is_ssl() ? 'https' : 'http') . '://' .
        $_SERVER['HTTP_HOST'] .
        wp_unslash($_SERVER['REQUEST_URI'])
); ?>
<div id="cta-cf-wrap">

    <div class="container">
        <div class="grid">

            <?php include __DIR__ . '/cta.php'; ?>

            <aside class="col-right" aria-label="Форма зворотного зв’язку">
                <div class="cta-card">
                    <h2 class="card-title">Заповніть форму та отримайте професійну консультацію</h2>

                    <form id="cta-cf" novalidate>
                        <div class="form-group">
                            <label for="name" class="form-label">Ваше ім’я</label>
                            <input
                                type="text"
                                class="form-control"
                                id="name"
                                name="name"
                                placeholder="Вкажіть Ваше ім’я"
                                required
                                autocomplete="name"
                                inputmode="text"
                                spellcheck="false"
                                aria-describedby="nameHelp"
                                maxlength="80" />
                            <div
                                id="nameHelp"
                                class="invalid-feedback">
                                Вкажіть Ваше ім’я
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="phone" class="form-label">Ваш телефон</label>
                            <div class="input-group">
                                <input
                                    type="tel"
                                    class="form-control"
                                    id="phone"
                                    name="phone"
                                    required
                                    autocomplete="tel"
                                    inputmode="tel"
                                    maxlength="15">

                                <input type="hidden" name="country_iso" id="country_iso">
                                <input type="hidden" name="dial_code" id="dial_code">
                                <input type="hidden" name="phone_full" id="phone_full">
                            </div>
                            <div id="phoneHelp" class="invalid-feedback">

                            </div>
                        </div>

                        <div class="form-group">
                            <label for="email" class="form-label">Ваш e-mail</label>
                            <input type="email" class="form-control" id="email" name="email"
                                placeholder="email@gmail.com" autocomplete="email" aria-describedby="emailHelp">
                            <div id="emailHelp" class="invalid-feedback">Невірний формат e-mail</div>
                        </div>

                        <div class="form-group">
                            <textarea class="form-control" id="message" name="message" rows="3"
                                placeholder="Коротко опишіть проблему, яку хочете вирішити"></textarea>
                            <div class="invalid-feedback">Перевірте поле</div>
                        </div>

                        <?php foreach ($utm_keys as $k): ?>
                            <input type="hidden" name="<?php echo esc_attr($k); ?>"
                                value="<?php echo isset($_GET[$k]) ? esc_attr($_GET[$k]) : ''; ?>">
                        <?php endforeach; ?>
                        <input type="hidden" name="page" value="<?php echo esc_attr($current_page); ?>">
                        <input type="hidden" name="action" value="cta_cf_submit">
                        <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce(CTA_CF_Plugin::NONCE)); ?>">
                        <button id="submitBtn" class="btn btn-accent btn-block" disabled type="submit" aria-live="polite">
                            <div class="btn-wrapper">
                                <span class="ping" aria-hidden="true"></span>
                                <span class="btn-text">Надіслати</span>
                                <span class="spinner is-hidden" role="status" aria-hidden="true"></span>
                            </div>
                        </button>

                        <p class="card-note">
                            Натискаючи на кнопку, я даю згоду <br>
                            на <span>обробку персональних даних</span>
                        </p>
                    </form>
                </div>
            </aside>
        </div>
    </div>

    <?php include __DIR__ . '/modal.php'; ?>

</div>