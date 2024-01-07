<?php
/**
 * Plugin Name: Domain Flow Manager
 * Plugin URI:  https://github.com/FrankyCasino/domain-flow-manager/
 * Description: A plugin to manage Cloudflare domains directly from your WordPress dashboard.
 * Version:     1.01 Beta
 * Author:      Crypto Casino
 * Author URI:  https://cryptocasino.ws
 * Text Domain: domain-flow-manager
 * Domain Path: /languages
 * License:     GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 5.6
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Load the textdomain for internationalization
function domain_flow_manager_load_textdomain() {
    load_plugin_textdomain('domain-flow-manager', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}
add_action('plugins_loaded', 'domain_flow_manager_load_textdomain');

// Include utility functions and Cloudflare integration
require_once plugin_dir_path(__FILE__) . 'includes/utility-functions.php';
require_once plugin_dir_path(__FILE__) . 'includes/cloudflare-integration.php';

// Include admin settings if in the admin area
if (is_admin()) {
    require_once plugin_dir_path(__FILE__) . 'admin/admin-settings.php';
}

// Register dashboard widget
function domain_flow_manager_register_dashboard_widget() {
    if (current_user_can('administrator')) {
        wp_add_dashboard_widget(
            'domain_flow_manager_dashboard_widget',
            __('Domain Flow Manager', 'domain-flow-manager'),
            'domain_flow_manager_widget_display'
        );
    }
}

add_action('wp_dashboard_setup', 'domain_flow_manager_register_dashboard_widget');


function domain_flow_manager_widget_display() {
    $api_key = get_option('cloudflare_api_key');
    $email = get_option('cloudflare_email');
    $is_authorized = get_option('cloudflare_authorized', false);

    // Отображение сообщений об успешной авторизации и добавлении доменов
    if (get_transient('cloudflare_auth_success_message')) {
        echo "<div class='notice notice-success is-dismissible'><p>" . get_transient('cloudflare_auth_success_message') . "</p></div>";
        delete_transient('cloudflare_auth_success_message');
    } elseif (get_transient('cloudflare_auth_error_message')) {
        echo "<div class='notice notice-error is-dismissible'><p>" . get_transient('cloudflare_auth_error_message') . "</p></div>";
        delete_transient('cloudflare_auth_error_message');
    }

    if (get_transient('cloudflare_added_domains_message')) {
        echo "<div class='notice notice-success is-dismissible'><p>" . get_transient('cloudflare_added_domains_message') . "</p></div>";
        delete_transient('cloudflare_added_domains_message');
    }

    // HTML для виджета
    ?>
    <form action="" method="post">
        <?php if (!$is_authorized || isset($_GET['change_credentials'])): ?>
            <p>
                <label for="cloudflare_email">Email:</label>
                <input id="cloudflare_email" name="cloudflare_email" type="text" value="<?php echo esc_attr($email); ?>" style="width: 100%;">
            </p>
            <p>
                <label for="cloudflare_api_key">API Key:</label>
                <input id="cloudflare_api_key" name="cloudflare_api_key" type="password" value="<?php echo esc_attr($api_key); ?>" style="width: 100%;">
            </p>
            <p>
                <input type="submit" name="cloudflare_authorize" value="Авторизоваться">
            </p>
            <?php if ($is_authorized): ?>
                <p>
                    <a href="<?php echo esc_url(add_query_arg('change_credentials', '0')); ?>">Отмена</a>
                </p>
            <?php endif; ?>
        <?php else: ?>
            <?php
                $user_info = get_cloudflare_user_info($api_key, $email);
                if ($user_info) {
                    echo "<p>Вы авторизованы как <strong>" . esc_html($user_info->email) . "</strong>.</p>";
                } else {
                    echo "<p>Не удалось получить информацию о пользователе CloudFlare.</p>";
                }
            ?>
            <p>
                <textarea name="cloudflare_domains" rows="5" style="width: 100%;" placeholder="Введите домены, по одному в строке"></textarea>
            </p>
            <p>
                <input type="submit" name="cloudflare_submit" value="Добавить Домены">
            </p>
            <p>
                <a href="<?php echo esc_url(add_query_arg('change_credentials', '1')); ?>">Изменить данные авторизации</a>
            </p>
        <?php endif; ?>
    </form>
    <?php
}


function my_cloudflare_handle_form_submit() {
    // Проверка на запрос отмены изменения учетных данных
    if (isset($_GET['change_credentials']) && $_GET['change_credentials'] == '0') {
        if (get_option('cloudflare_authorized', false)) {
            wp_redirect(remove_query_arg('change_credentials', wp_get_referer()));
            exit;
        }
    }

    // Обработка формы авторизации
    if (!empty($_POST['cloudflare_authorize'])) {
        $submitted_email = sanitize_text_field($_POST['cloudflare_email']);
        $submitted_api_key = sanitize_text_field($_POST['cloudflare_api_key']);
        update_option('cloudflare_email', $submitted_email);
        update_option('cloudflare_api_key', $submitted_api_key);
        
        if (check_cloudflare_credentials($submitted_api_key, $submitted_email)) {
            update_option('cloudflare_authorized', true);
            // Сохраняем сообщение об успешной авторизации во временной опции
            set_transient('cloudflare_auth_success_message', 'Авторизация прошла успешно.', 45);
            wp_redirect(remove_query_arg('cloudflare_auth'));
            exit;
        } else {
            update_option('cloudflare_authorized', false);
            // Сохраняем сообщение об ошибке авторизации во временной опции
            set_transient('cloudflare_auth_error_message', 'Неверные настройки CloudFlare API. Проверьте email и API Key.', 45);
            wp_redirect(remove_query_arg('cloudflare_auth'));
            exit;
        }
    }

    // Обработка формы добавления доменов
    elseif (!empty($_POST['cloudflare_submit']) && get_option('cloudflare_authorized', false)) {
        $api_key = get_option('cloudflare_api_key');
        $email = get_option('cloudflare_email');

        $domains = explode("\n", $_POST['cloudflare_domains']);
        $added_domains = [];
        foreach ($domains as $domain) {
            if (add_domain_to_cloudflare(trim($domain), $api_key, $email)) {
                $added_domains[] = $domain;
            }
        }

        if (!empty($added_domains)) {
            // Сохраняем сообщение о добавленных доменах во временной опции
            set_transient('cloudflare_added_domains_message', count($added_domains) . ' домен(ов) было добавлено.', 45);
        }

        wp_redirect(remove_query_arg('cloudflare_domains_added'));
        exit;
    }
}

add_action('admin_init', 'my_cloudflare_handle_form_submit');
