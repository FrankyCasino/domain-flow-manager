<?php
// This file will handle the Cloudflare API integration.
function check_cloudflare_credentials($api_key, $email) {
    $url = 'https://api.cloudflare.com/client/v4/zones';

    $args = array(
        'headers' => array(
            'X-Auth-Email' => $email,
            'X-Auth-Key' => $api_key,
            'Content-Type' => 'application/json'
        ),
        'method' => 'GET'
    );

    $response = wp_remote_get($url, $args);

    if (is_wp_error($response)) {
        error_log('Ошибка при проверке авторизации CloudFlare: ' . $response->get_error_message());
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body);

    // Проверка успешности запроса и наличия зон
    if (isset($data->success) && $data->success) {
        // Если успешно и список зон не пуст, считаем что авторизация валидна
        return !empty($data->result);
    }

    return false;
}

function get_cloudflare_user_info($api_key, $email) {
    $url = 'https://api.cloudflare.com/client/v4/user';

    $args = array(
        'headers' => array(
            'X-Auth-Email' => $email,
            'X-Auth-Key' => $api_key,
            'Content-Type' => 'application/json'
        ),
        'method' => 'GET'
    );

    $response = wp_remote_get($url, $args);

    if (is_wp_error($response)) {
        // Обработка ошибки
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body);

    if (isset($data->success) && $data->success) {
        // Возвращаем информацию о пользователе
        return $data->result;
    }

    return false;
}

function add_domain_to_cloudflare($domain, $api_key, $email) {
    // URL для API запроса к CloudFlare для добавления нового домена
    $url = 'https://api.cloudflare.com/client/v4/zones';

    // Подготовка данных для отправки
    $data = array(
        'name' => $domain,
        // Дополнительные параметры, если они требуются API CloudFlare
    );

    // Аргументы для запроса
    $args = array(
        'method' => 'POST',
        'headers' => array(
            'X-Auth-Email' => $email,
            'X-Auth-Key' => $api_key,
            'Content-Type' => 'application/json'
        ),
        'body' => json_encode($data)
    );

    // Отправка запроса к CloudFlare API
    $response = wp_remote_post($url, $args);

    // Проверка ответа
    if (is_wp_error($response)) {
        // Обработка ошибки
        error_log('Ошибка при добавлении домена в CloudFlare: ' . $response->get_error_message());
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body);

    if (isset($data->success) && $data->success) {
        // Успешное добавление домена
        return true;
    } else {
        // Ошибка при добавлении домена
        error_log('Ошибка при добавлении домена: ' . print_r($body, true));
        return false;
    }
}