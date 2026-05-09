<?php
namespace X402_Paywall;

defined('ABSPATH') || exit;

/**
 * HTTP client for the x402 facilitator endpoints (/verify and /settle).
 *
 * The facilitator is a hosted service that handles blockchain interaction
 * so your WordPress server doesn't need to run a node or hold crypto.
 */
class X402_Facilitator_Client {

    private $base_url;

    public function __construct() {
        $this->base_url = untrailingslashit(
            get_option('x402_facilitator_url', 'https://x402.org/facilitator')
        );
    }

    /**
     * Verify a PAYMENT-SIGNATURE against the original PAYMENT-REQUIRED.
     *
     * @param string $payment_signature Base64 PAYMENT-SIGNATURE header value.
     * @param string $payment_required  Base64 PAYMENT-REQUIRED header value.
     * @return array { valid: bool, transactionHash: string }
     */
    public function verify($payment_signature, $payment_required) {
        $response = wp_remote_post($this->base_url . '/verify', [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode([
                'paymentPayload'  => $payment_signature,
                'paymentRequired' => $payment_required,
            ]),
            'timeout'  => 10,
            'blocking' => true,
        ]);

        if (is_wp_error($response)) {
            $this->log_error('Facilitator /verify failed', $response->get_error_message());
            return $this->fail_open_fallback();
        }

        $status = wp_remote_retrieve_response_code($response);
        $body   = json_decode(wp_remote_retrieve_body($response), true);

        if ($status !== 200 || empty($body)) {
            $this->log_error('Facilitator /verify bad response', "HTTP {$status}");
            return $this->fail_open_fallback();
        }

        return [
            'valid'           => !empty($body['valid']),
            'transactionHash' => $body['transactionHash'] ?? '',
        ];
    }

    /**
     * Fire-and-forget settlement (non-blocking).
     * Called after serving content to submit the transaction on-chain.
     */
    public function settle_async($payment_signature, $payment_required) {
        wp_remote_post($this->base_url . '/settle', [
            'headers'  => ['Content-Type' => 'application/json'],
            'body'     => wp_json_encode([
                'paymentPayload'  => $payment_signature,
                'paymentRequired' => $payment_required,
            ]),
            'timeout'  => 0.1,
            'blocking' => false,
        ]);
    }

    /**
     * When fail-open is enabled, return a "valid" result on facilitator errors
     * so the site doesn't break if the facilitator is unreachable.
     */
    private function fail_open_fallback() {
        $fail_open = get_option('x402_fail_open', 'yes') === 'yes';
        if ($fail_open) {
            return [
                'valid'                  => true,
                'transactionHash'        => '',
                'facilitator_unreachable' => true,
            ];
        }
        return [
            'valid'           => false,
            'transactionHash' => '',
        ];
    }

    private function log_error($message, $detail = '') {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('[x402-paywall] %s: %s', $message, $detail));
        }
    }
}
