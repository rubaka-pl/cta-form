<?php

/**
 * Plugin Name: CTA Contact Form (AJAX)
 * Description: Форма-CTA з AJAX, валідацією, e-mail, CPT "Leads", UTM + Telegram.
 * Version: 1.0.0
 * Author: rgbweb.studio
 */

if (!defined('ABSPATH')) exit;

define('CTA_CF_PLUGIN_FILE', __FILE__);

class CTA_CF_Plugin
{
    const NONCE = 'cta_cf_nonce';
    const OPT   = 'cta_cf_settings';
    const CPT   = 'cta_lead';

    public function __construct()
    {
        add_action('wp_head', function () {
            echo '<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>';
            echo '<link rel="dns-prefetch" href="//cdn.jsdelivr.net">';
            echo '<link rel="preload" href="https://cdn.jsdelivr.net/npm/intl-tel-input@25.4.3/build/js/utils.js" as="script" crossorigin>';
        }, 1);

        add_action('init',                 [$this, 'register_cpt']);
        add_shortcode('cta_form',          [$this, 'shortcode']);
        add_action('wp_enqueue_scripts',   [$this, 'enqueue_assets']);

        add_action('admin_menu',           [$this, 'register_admin_pages']);
        add_action('admin_init',           [$this, 'register_settings']);

        add_action('wp_ajax_cta_cf_submit',        [$this, 'handle_submit']);
        add_action('wp_ajax_nopriv_cta_cf_submit', [$this, 'handle_submit']);

        add_filter('plugin_action_links_' . plugin_basename(CTA_CF_PLUGIN_FILE), [$this, 'plugin_action_links']);

        add_action('add_meta_boxes',                               [$this, 'add_lead_metabox']);
        add_filter('manage_edit-' . self::CPT . '_columns',        [$this, 'admin_columns']);
        add_action('manage_' . self::CPT . '_posts_custom_column', [$this, 'render_admin_columns'], 10, 2);
        add_filter('manage_edit-' . self::CPT . '_sortable_columns', [$this, 'sortable_columns']);
        add_action('pre_get_posts',                                [$this, 'handle_sorting']);
    }

    /* ===== CPT ===== */
    public function register_cpt()
    {
        register_post_type(self::CPT, [
            'label' => 'Leads',
            'public' => false,
            'show_ui' => true,
            'menu_icon' => 'dashicons-email-alt2',
            'supports' => ['title', 'custom-fields'],
        ]);
    }

    /* ===== Assets ===== */
    public function enqueue_assets()
    {
        $ver = '1.0.0';

        wp_enqueue_style(
            'iti',
            'https://cdn.jsdelivr.net/npm/intl-tel-input@25.4.3/build/css/intlTelInput.css',
            [],
            '25.4.3'
        );
        wp_enqueue_script(
            'iti',
            'https://cdn.jsdelivr.net/npm/intl-tel-input@25.4.3/build/js/intlTelInput.min.js',
            [],
            '25.4.3',
            true
        );

        wp_enqueue_style(
            'cta-cf',
            plugins_url('assets/css/style.css', CTA_CF_PLUGIN_FILE),
            ['iti'],
            $ver
        );
        wp_enqueue_script(
            'cta-cf',
            plugins_url('assets/js/form.js', CTA_CF_PLUGIN_FILE),
            ['jquery', 'iti'],
            $ver,
            true
        );

        wp_localize_script('cta-cf', 'CTA_CF', [
            'ajax'  => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(self::NONCE),
        ]);
    }

    /* ===== Shortcode ===== */
    public function shortcode()
    {
        ob_start();
        include __DIR__ . '/templates/form.php';
        return ob_get_clean();
    }

    /* ===== Валідація (бек) ===== */
    private function is_valid_name(string $v): bool
    {
        $v = (string) wp_check_invalid_utf8($v);
        $v = sanitize_text_field($v);
        $v = preg_replace('/\s{2,}/u', ' ', trim($v));
        if ($v === '' || mb_strlen($v) > 80) return false;
        return (bool) preg_match('/^[\p{L}\p{M}]+(?: [\p{L}\p{M}]+)*$/u', $v);
    }
    private function is_valid_email($v)
    {
        if ($v === '') return true;
        return (bool) filter_var($v, FILTER_VALIDATE_EMAIL);
    }
    private function is_valid_phone(string $raw, string $e164 = ''): bool
    {
        if ($e164 !== '') {
            $e164 = preg_replace('/\s+/', '', $e164);
            return (bool) preg_match('/^\+[1-9]\d{5,14}$/', $e164);
        }
        if ($raw === '' || preg_match('/[A-Za-z]/', $raw)) return false;
        $digits = preg_replace('/\D+/', '', $raw);
        $len = strlen($digits);
        return $len >= 6 && $len <= 15;
    }

    /* ===== Метабокс у картці ліда ===== */
    public function add_lead_metabox()
    {
        add_meta_box(
            'cta_lead_details',
            __('Lead details', 'cta-cf'),
            [$this, 'lead_metabox_html'],
            self::CPT,
            'normal',
            'high'
        );
    }

    public function lead_metabox_html($post)
    {
        $name  = get_post_meta($post->ID, 'name', true);
        $email = get_post_meta($post->ID, 'email', true);
        $dial  = get_post_meta($post->ID, 'dial_code', true);
        $phone = get_post_meta($post->ID, 'phone', true);
        $page  = get_post_meta($post->ID, 'page', true);
        $utm   = get_post_meta($post->ID, 'utm', true);
        $msg   = get_post_meta($post->ID, 'message', true);
        $at    = get_post_meta($post->ID, 'submitted_at', true);

        $utm  = is_array($utm) ? array_filter($utm) : [];
        $full = $page ? add_query_arg($utm, $page) : '';

        echo '<table class="widefat striped" style="max-width:820px"><tbody>';

        echo '<tr><th style="width:180px">' . esc_html__('Name', 'cta-cf') . '</th><td>' . esc_html($name) . '</td></tr>';
        echo '<tr><th>' . esc_html__('Email', 'cta-cf') . '</th><td>';
        echo $email ? '<a href="mailto=' . esc_attr($email) . '">' . esc_html($email) . '</a>' : '&mdash;';
        echo '</td></tr>';
        echo '<tr><th>' . esc_html__('Dial code', 'cta-cf') . '</th><td>' . esc_html($dial ?: '—') . '</td></tr>';
        echo '<tr><th>' . esc_html__('Phone', 'cta-cf') . '</th><td>' . esc_html($phone) . '</td></tr>';

        echo '<tr><th>' . esc_html__('Page (UTM)', 'cta-cf') . '</th><td>';
        if ($full) {
            echo '<a href="' . esc_url($full) . '" target="_blank" rel="noopener">' . esc_html($full) . '</a>';
        } elseif ($page) {
            echo esc_html($page);
        } else {
            echo '&mdash;';
        }
        if ($utm) {
            echo '<div style="margin-top:6px;font-size:12px;color:#555">';
            foreach ($utm as $k => $v) {
                if ($v === '') continue;
                echo '<code style="margin-right:6px">' . esc_html($k) . '=' . esc_html($v) . '</code>';
            }
            echo '</div>';
        }
        echo '</td></tr>';

        echo '<tr><th>' . esc_html__('Message', 'cta-cf') . '</th><td><div style="white-space:pre-wrap">' . esc_html($msg) . '</div></td></tr>';
        echo '<tr><th>' . esc_html__('Submitted at', 'cta-cf') . '</th><td>' . esc_html($at ?: '—') . '</td></tr>';

        echo '</tbody></table>';
    }

    /* ===== Відправка в Telegram ===== */
    private function notify_telegram(array $lead): void
    {
        $opt     = get_option(self::OPT, []);
        $token   = isset($opt['tg_token'])   ? trim($opt['tg_token']) : '';
        $targets = isset($opt['tg_targets']) ? trim($opt['tg_targets']) : '';

        if ($token === '' || $targets === '') return;

        // список отримувачів
        $targets = preg_split('/[\s,;]+/', $targets, -1, PREG_SPLIT_NO_EMPTY);
        if (!$targets) return;

        $e = static function ($s) {
            return strtr((string)$s, ['&' => '&amp;', '<' => '&lt;', '>' => '&gt;']);
        };

        $utm_str = '';
        if (!empty($lead['utm']) && is_array($lead['utm'])) {
            $parts = [];
            foreach ($lead['utm'] as $k => $v) {
                if ($v === '') continue;
                $parts[] = $k . '=' . $v;
            }
            $utm_str = implode(' ', $parts);
        }

        $dial  = trim((string)($lead['dial_code'] ?? ''));
        $phone = trim((string)($lead['phone'] ?? ''));
        $tel   = trim(($dial ? $dial . ' ' : '') . $phone);
        $country = strtoupper(trim((string)($lead['country_iso'] ?? '')));

        $text  = "<b>Новий лід</b>\n";
        $text .= "Ім’я: " . $e($lead['name'] ?? '') . "\n";
        $text .= "Email: " . $e($lead['email'] ?? '') . "\n";
        $text .= "Телефон: " . $e($tel) . "\n";
        if ($country) $text .= "Країна (ISO): " . $e($country) . "\n";
        if ($dial)    $text .= "Код країни: " . $e($dial) . "\n";
        if (!empty($lead['page'])) $text .= "Сторінка: " . $e($lead['page']) . "\n";
        if ($utm_str) $text .= "UTM: " . $e($utm_str) . "\n";
        if (!empty($lead['message'])) $text .= "\n" . $e($lead['message']);

        foreach ($targets as $chat) {
            wp_remote_post("https://api.telegram.org/bot{$token}/sendMessage", [
                'timeout' => 7,
                'body'    => [
                    'chat_id' => $chat, // ID або @channelusername
                    'text'    => $text,
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => 1,
                ],
            ]);
        }
    }

    // Google Sheets
    private function notify_gsheet(array $lead): void
    {
        $opt    = get_option(self::OPT, []);
        $url    = isset($opt['gs_webapp_url']) ? trim($opt['gs_webapp_url']) : '';
        $secret = isset($opt['gs_secret'])     ? trim($opt['gs_secret'])     : '';

        if ($url === '' || $secret === '') return;

        $utm = is_array($lead['utm'] ?? null) ? $lead['utm'] : [];

        $payload = [
            'secret'       => $secret,
            'site'         => home_url(),
            'lead_id'      => $lead['lead_id']     ?? 0,
            'name'         => $lead['name']        ?? '',
            'email'        => $lead['email']       ?? '',
            'dial_code'    => $lead['dial_code']   ?? '',
            'phone'        => $lead['phone']       ?? '',
            'country_iso'  => $lead['country_iso'] ?? '',
            'message'      => $lead['message']     ?? '',
            'page'         => $lead['page']        ?? '',
            'utm_source'   => $utm['utm_source']   ?? '',
            'utm_medium'   => $utm['utm_medium']   ?? '',
            'utm_campaign' => $utm['utm_campaign'] ?? '',
            'utm_term'     => $utm['utm_term']     ?? '',
            'utm_content'  => $utm['utm_content']  ?? '',
        ];

        // Отладка: логируем, что отправляем и что получаем
        error_log('GS POST payload: ' . wp_json_encode($payload, JSON_UNESCAPED_UNICODE));

        $res = wp_remote_post($url, [
            'timeout' => 10,
            'headers' => ['Content-Type: application/json'],
            'body'    => wp_json_encode($payload),
        ]);

        if (is_wp_error($res)) {
            error_log('GSHEETS: ' . $res->get_error_message());
        } else {
            $code = wp_remote_retrieve_response_code($res);
            $body = wp_remote_retrieve_body($res);
            error_log('GS RESP: ' . $code . ' ' . $body);
            if ($code >= 400) {
                error_log('GSHEETS HTTP ' . $code . ': ' . $body);
            }
        }
    }

    /* ===== AJAX обробка ===== */
    public function handle_submit()
    {
        check_ajax_referer(self::NONCE, 'nonce');

        $country_iso = isset($_POST['country_iso']) ? sanitize_text_field(wp_unslash($_POST['country_iso'])) : '';
        $dial_code   = isset($_POST['dial_code'])   ? sanitize_text_field(wp_unslash($_POST['dial_code']))   : '';
        $phone_full  = isset($_POST['phone_full'])  ? sanitize_text_field(wp_unslash($_POST['phone_full']))  : '';

        $name    = isset($_POST['name'])    ? sanitize_text_field(wp_unslash($_POST['name']))        : '';
        $email   = isset($_POST['email'])   ? sanitize_email(wp_unslash($_POST['email']))            : '';
        $phone   = isset($_POST['phone'])   ? sanitize_text_field(wp_unslash($_POST['phone']))       : '';
        $message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';

        $page = isset($_POST['page']) ? esc_url_raw(wp_unslash($_POST['page'])) : '';
        if ($page === '' && !empty($_SERVER['HTTP_REFERER'])) {
            $page = esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER']));
        }

        $utm = [
            'utm_source'   => isset($_POST['utm_source'])   ? sanitize_text_field(wp_unslash($_POST['utm_source'])) : '',
            'utm_medium'   => isset($_POST['utm_medium'])   ? sanitize_text_field(wp_unslash($_POST['utm_medium'])) : '',
            'utm_campaign' => isset($_POST['utm_campaign']) ? sanitize_text_field(wp_unslash($_POST['utm_campaign'])) : '',
            'utm_term'     => isset($_POST['utm_term'])     ? sanitize_text_field(wp_unslash($_POST['utm_term'])) : '',
            'utm_content'  => isset($_POST['utm_content'])  ? sanitize_text_field(wp_unslash($_POST['utm_content'])) : '',
        ];

        // Якщо utm_* порожні — пробуємо дістати їх з $page
        if (!array_filter($utm, fn($v) => $v !== '')) {
            if ($page) {
                $query = wp_parse_url($page, PHP_URL_QUERY);
                if ($query) {
                    parse_str($query, $qs);
                    foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as $k) {
                        if (!empty($qs[$k])) {
                            $utm[$k] = sanitize_text_field($qs[$k]);
                        }
                    }
                }
            }
        }

        $errors = [];
        if (!$this->is_valid_name($name))   $errors['name']  = 'Невірне ім’я';
        if (!$this->is_valid_email($email)) $errors['email'] = 'Невірний e-mail';
        if (!$this->is_valid_phone($phone, $phone_full)) $errors['phone'] = 'Невірний телефон';

        if ($errors) {
            wp_send_json_error(['errors' => $errors], 422);
        }

        $lead_id = wp_insert_post([
            'post_type'   => self::CPT,
            'post_status' => 'publish',
            'post_title'  => wp_trim_words("$name", 12, ''),
            'meta_input'  => [
                'name'         => $name,
                'email'        => $email,
                'phone'        => $phone,
                'phone_full'   => $phone_full,
                'message'      => $message,
                'submitted_at' => current_time('mysql'),
                'utm'          => $utm,
                'page'         => $page,
                'country_iso'  => $country_iso,
                'dial_code'    => $dial_code,
            ],
        ]);

        $opt = get_option(self::OPT, []);
        $to  = !empty($opt['emails'])
            ? array_filter(array_map('trim', explode(',', $opt['emails'])))
            : [get_option('admin_email')];

        $subject = 'Новий лід з форми';
        $body  = "Ім’я: $name\nEmail: $email\n";
        $body .= "Телефон (raw): $phone\n";
        if ($phone_full) $body .= "Телефон (E.164): $phone_full\n";
        if ($dial_code)  $body .= "Код країни: $dial_code\n";
        if ($country_iso) $body .= "Країна (ISO): " . strtoupper($country_iso) . "\n";
        $body .= "\nПовідомлення:\n$message\n\n";
        $body .= "Дата/час: " . current_time('mysql') . "\n";
        $body .= "Сторінка: $page\n";
        $body .= "UTM: " . wp_json_encode($utm, JSON_UNESCAPED_UNICODE) . "\n";
        wp_mail($to, $subject, $body);

        // Telegram
        $this->notify_telegram([
            'name'        => $name,
            'email'       => $email,
            'dial_code'   => $dial_code,
            'phone'       => $phone,
            'country_iso' => $country_iso,
            'message'     => $message,
            'page'        => $page,
            'utm'         => $utm,
            'lead_id'     => $lead_id,
        ]);

        // Google Таблиця
        $this->notify_gsheet([
            'name'        => $name,
            'email'       => $email,
            'dial_code'   => $dial_code,
            'phone'       => $phone,
            'country_iso' => $country_iso,
            'message'     => $message,
            'page'        => $page,
            'utm'         => $utm,
            'lead_id'     => $lead_id,
        ]);

        wp_send_json_success(['lead_id' => $lead_id]);
    }

    /* ===== Налаштування ===== */
    public function register_admin_pages()
    {
        add_options_page('CTA Form', 'CTA Form', 'manage_options', 'cta-cf', [$this, 'settings_html']);
        add_options_page('CTA Form — Instructions', 'CTA Form Help', 'manage_options', 'cta-cf-help', [$this, 'help_page_html']);
    }
    public function plugin_action_links($links)
    {
        $links[] = '<a href="' . esc_url(admin_url('options-general.php?page=cta-cf')) . '">Settings</a>';
        $links[] = '<a href="' . esc_url(admin_url('options-general.php?page=cta-cf-help')) . '">Instructions</a>';
        return $links;
    }
    public function register_settings()
    {
        register_setting('cta_cf_group', self::OPT);
        add_settings_section('main', '', '__return_false', 'cta-cf');

        // e-mailи
        add_settings_field('emails', 'E-mail(и) для лідів', function () {
            $o = get_option(self::OPT, []);
            $v = isset($o['emails']) ? esc_attr($o['emails']) : get_option('admin_email');
            echo '<input type="text" class="regular-text" name="' . self::OPT . '[emails]" value="' . $v . '" />';
            echo '<p class="description">Кілька адрес через кому.</p>';
        }, 'cta-cf', 'main');

        // Telegram token
        add_settings_field('tg_token', 'Telegram bot token', function () {
            $o = get_option(self::OPT, []);
            $v = isset($o['tg_token']) ? esc_attr($o['tg_token']) : '';
            echo '<input type="text" class="regular-text" name="' . self::OPT . '[tg_token]" value="' . $v . '" placeholder="123456789:ABC-DEF..." />';
            echo '<p class="description">Створіть бота через @BotFather та вставте токен.</p>';
        }, 'cta-cf', 'main');

        // Telegram отримувачі
        add_settings_field('tg_targets', 'Telegram отримувачі', function () {
            $o = get_option(self::OPT, []);
            $v = isset($o['tg_targets']) ? esc_textarea($o['tg_targets']) : '';
            echo '<textarea class="large-text" rows="3" name="' . self::OPT . '[tg_targets]" placeholder="-1001234567890, 123456789, @yourchannel">' . $v . '</textarea>';
            echo '<p class="description">Через кому або з нового рядка: <strong>chat_id</strong> користувачів/груп або <code>@channelusername</code> (для каналів). Для приватних акаунтів потрібен числовий ID (людина має написати вашому боту).</p>';
        }, 'cta-cf', 'main');

        // Google Sheets: Web App URL
        add_settings_field('gs_webapp_url', 'Google Sheets Webhook URL', function () {
            $o = get_option(self::OPT, []);
            $v = isset($o['gs_webapp_url']) ? esc_attr($o['gs_webapp_url']) : '';
            echo '<input type="url" class="regular-text" name="' . self::OPT . '[gs_webapp_url]" value="' . $v . '" placeholder="https://script.google.com/.../exec" />';
            echo '<p class="description">URL веб-додатку Apps Script (Deploy → Web app).</p>';
        }, 'cta-cf', 'main');

        // Google Sheets: Shared secret
        add_settings_field('gs_secret', 'Google Sheets secret', function () {
            $o = get_option(self::OPT, []);
            $v = isset($o['gs_secret']) ? esc_attr($o['gs_secret']) : '';
            echo '<input type="text" class="regular-text" name="' . self::OPT . '[gs_secret]" value="' . $v . '" placeholder="Довгий випадковий рядок" />';
            echo '<p class="description">Повинен збігатися з SHARED_SECRET у Apps Script. (https://script.google.com/)</p>';
        }, 'cta-cf', 'main');
    }
    public function settings_html()
    {
        if (!current_user_can('manage_options')) return; ?>
        <div class="wrap">
            <h1>CTA Contact Form</h1>
            <form method="post" action="options.php">
                <?php settings_fields('cta_cf_group');
                do_settings_sections('cta-cf');
                submit_button(); ?>
            </form>
        </div>
    <?php }
    public function help_page_html()
    {
        if (!current_user_can('manage_options')) return; ?>
        <div class="wrap">
            <h1>CTA Contact Form — Instructions</h1>
            <p><strong>Що робить плагін:</strong> шорткод вставляє блок CTA та форму з AJAX-відправкою, валідацією, листом на e-mail і збереженням у CPT <code>Leads</code>. UTM-мітки та сторінка додаються автоматично.</p>
            <h2>Як вставити на сайт</h2>
            <p>Додайте шорткод: <code id="cta-cf-shortcode">[cta_form]</code> <button class="button button-primary" id="cta-cf-copy">Copy shortcode</button></p>
            <p>У PHP: <code>&lt;?php echo do_shortcode('[cta_form]'); ?&gt;</code></p>
            <h2>Налаштування</h2>
            <ol>
                <li>Settings → CTA Form</li>
                <li>Вкажіть e-mail(и) для лідів</li>
                <li>На локалці налаштуйте SMTP (WP Mail SMTP/Mailtrap)</li>
            </ol>
            <h2>Перевірка</h2>
            <ol>
                <li>Заповніть форму: ім’я — лише літери; телефон — цифри</li>
                <li>Надіслати — з’явиться модалка, у меню Leads — запис</li>
            </ol>
            <script>
                (function() {
                    const btn = document.getElementById('cta-cf-copy');
                    if (!btn) return;
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        const txt = document.getElementById('cta-cf-shortcode').innerText;
                        navigator.clipboard.writeText(txt).then(function() {
                            btn.textContent = 'Copied!';
                            setTimeout(() => btn.textContent = 'Copy shortcode', 1200);
                        });
                    });
                })();
            </script>
        </div>
<?php }

    /* ===== Колонки у списку Leads ===== */
    public function admin_columns($cols)
    {
        return [
            'cb'           => $cols['cb'],
            'title'        => __('Lead', 'cta-cf'),
            'name'         => __('Name', 'cta-cf'),
            'email'        => __('Email', 'cta-cf'),
            'dial_code'    => __('Dial code', 'cta-cf'),
            'phone'        => __('Phone', 'cta-cf'),
            'page'         => __('Page (UTM)', 'cta-cf'),
            'message'      => __('Message', 'cta-cf'),
            'submitted_at' => __('Submitted at', 'cta-cf'),
        ];
    }
    public function render_admin_columns($column, $post_id)
    {
        switch ($column) {
            case 'name':
                echo esc_html(get_post_meta($post_id, 'name', true));
                break;
            case 'email':
                $email = get_post_meta($post_id, 'email', true);
                if ($email) echo '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>';
                break;
            case 'dial_code':
                echo esc_html(get_post_meta($post_id, 'dial_code', true));
                break;
            case 'phone':
                echo esc_html(get_post_meta($post_id, 'phone', true));
                break;
            case 'page':
                $page = get_post_meta($post_id, 'page', true);
                $utm  = get_post_meta($post_id, 'utm', true);
                $utm  = is_array($utm) ? array_filter($utm) : [];
                $full = $page ? add_query_arg($utm, $page) : '';
                if ($full) {
                    echo '<a href="' . esc_url($full) . '" target="_blank" rel="noopener">' . esc_html($full) . '</a>';
                } elseif ($page) {
                    echo esc_html($page);
                } else {
                    echo '&mdash;';
                }
                break;
            case 'message':
                $msg = (string) get_post_meta($post_id, 'message', true);
                echo esc_html(wp_trim_words($msg, 12, '…'));
                break;
            case 'submitted_at':
                echo esc_html(get_post_meta($post_id, 'submitted_at', true));
                break;
        }
    }
    public function sortable_columns($cols)
    {
        $cols['submitted_at'] = 'submitted_at';
        return $cols;
    }
    public function handle_sorting($q)
    {
        if (!is_admin() || !$q->is_main_query()) return;
        if ($q->get('post_type') !== self::CPT) return;

        if ('submitted_at' === $q->get('orderby')) {
            $q->set('meta_key', 'submitted_at');
            $q->set('meta_type', 'DATETIME');
            $q->set('orderby', 'meta_value');
            if (!$q->get('order')) $q->set('order', 'DESC');
        }
    }
}

new CTA_CF_Plugin();
