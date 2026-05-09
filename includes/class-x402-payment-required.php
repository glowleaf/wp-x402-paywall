<?php
namespace X402_Paywall;

defined('ABSPATH') || exit;

/**
 * Builds the PAYMENT-REQUIRED header payload per the x402 protocol.
 */
class X402_Payment_Required {

    /**
     * Build and return a base64-encoded PAYMENT-REQUIRED payload.
     *
     * @return string Base64-encoded JSON, or empty string if wallet not configured.
     */
    public function build() {
        $wallet = get_option('x402_wallet', '');
        if (empty($wallet)) {
            return '';
        }

        $network = get_option('x402_network', 'eip155:8453');
        $price   = get_option('x402_price', '$0.001');

        $payload = [
            'accepts' => [
                [
                    'scheme'  => 'exact',
                    'price'   => $price,
                    'network' => $network,
                    'payTo'   => $wallet,
                ],
            ],
            'description' => get_bloginfo('name') . ' — ' . wp_parse_url(home_url(), PHP_URL_HOST),
            'mimeType'    => 'text/html',
            'extensions'  => [],
        ];

        return base64_encode(wp_json_encode($payload));
    }
}
