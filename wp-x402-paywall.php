<?php
/**
 * Plugin Name: x402 Micropayment Paywall
 * Plugin URI:  https://x402.org
 * Description: Charge bots and AI agents micropayments ($0.001–$0.01) for website access via HTTP 402. Supports x402 protocol with Base, Solana, Polygon, and Avalanche. Instead of blocking bots with CAPTCHAs, request a tiny payment. Settings → x402 Paywall.
 * Version:     1.0.0
 * Author:      Machine George
 * License:     GPL v2 or later
 * Text Domain: x402-paywall
 * Domain Path: /languages
 *
 * x402 Micropayment Paywall is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 */

defined('ABSPATH') || exit;

define('X402_PAYWALL_VERSION', '1.0.0');
define('X402_PAYWALL_PATH', plugin_dir_path(__FILE__));
define('X402_PAYWALL_URL', plugin_dir_url(__FILE__));

/**
 * Simple autoloader for namespaced classes.
 * Converts X402_Paywall\* to includes/ class-* files,
 * and X402_Paywall\Admin\* to includes/admin/ class-* files.
 */
spl_autoload_register(function ($class) {
    $prefix = 'X402_Paywall\\';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;

    $relative = substr($class, $len);
    $relative = str_replace('_', '-', $relative);
    $relative = str_replace('\\', '/', $relative);
    $relative = strtolower($relative);

    // Split on / so namespace segments become directories
    $parts = explode('/', $relative);
    $class_name = array_pop($parts);
    $dir_path = !empty($parts) ? implode('/', $parts) . '/' : '';

    $file = X402_PAYWALL_PATH . 'includes/' . $dir_path . 'class-' . $class_name . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

/**
 * Activation: set default options.
 */
register_activation_hook(__FILE__, function () {
    $defaults = [
        'x402_enabled'              => 'no',
        'x402_mode'                 => 'bots',
        'x402_price'                => '$0.001',
        'x402_network'              => 'eip155:8453',
        'x402_wallet'               => '',
        'x402_facilitator_url'      => 'https://x402.org/facilitator',
        'x402_session_ttl'          => 24,
        'x402_fail_open'            => 'yes',
        'x402_contact_email'        => '',
        'x402_paywalled_paths'      => '/wp-json/',
        'x402_bot_patterns'         => "bot\ncrawler\nspider\nscraper\ncurl\nwget\npython-requests\ngo-http-client\nokhttp\naxios\nhttp-client\ngooglebot\nbingbot\nslurp\nduckduckbot\nbaiduspider\nyandexbot\nfacebookexternalhit\ntwitterbot\napplebot\nanthropic\nclaude\ngpt\nchatgpt-openai\nopenai\nperplexity\nai2\nsemanticscholar\ncohere",
        'x402_excluded_paths'       => "/llms.txt\n/wp-json/x402/\n/wp-admin/\n/wp-login.php\n/robots.txt\n/sitemap.xml\n/sitemap_index.xml\n/feed/\n/comments/feed/",
        'x402_ip_whitelist'         => '',
    ];
    foreach ($defaults as $key => $value) {
        if (get_option($key) === false) {
            add_option($key, $value);
        }
    }
});

/**
 * Deactivation: clean up scheduled tasks.
 */
register_deactivation_hook(__FILE__, function () {
    wp_unschedule_hook('x402_cleanup_transients');
});

/**
 * Bootstrap: load classes and init.
 */
add_action('plugins_loaded', function () {
    load_plugin_textdomain('x402-paywall', false, dirname(plugin_basename(__FILE__)) . '/languages');

    // Settings page (admin only)
    if (is_admin()) {
        require X402_PAYWALL_PATH . 'includes/admin/class-x402-settings.php';
        new X402_Paywall\Admin\X402_Settings();
    }

    // Core classes
    require X402_PAYWALL_PATH . 'includes/class-x402-rules.php';
    require X402_PAYWALL_PATH . 'includes/class-x402-payment-required.php';
    require X402_PAYWALL_PATH . 'includes/class-x402-facilitator-client.php';
    require X402_PAYWALL_PATH . 'includes/class-x402-verifier.php';
    require X402_PAYWALL_PATH . 'includes/class-x402-session.php';
    require X402_PAYWALL_PATH . 'includes/class-x402-response.php';
    require X402_PAYWALL_PATH . 'includes/class-x402-handler.php';

    new X402_Paywall\X402_Handler();
});
