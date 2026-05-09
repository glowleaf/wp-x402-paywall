<?php
namespace X402_Paywall;

defined('ABSPATH') || exit;

/**
 * Core handler: intercepts requests and orchestrates the paywall flow.
 *
 * Hooks into parse_request (fires before any output) to:
 * 1. Check if enabled and rules match
 * 2. Check for existing paid session (cookie)
 * 3. Check for PAYMENT-SIGNATURE header (retry after payment)
 * 4. If no valid payment → send 402 with PAYMENT-REQUIRED
 */
class X402_Handler {

    private $rules;
    private $payment_required;
    private $facilitator;
    private $verifier;
    private $session;
    private $response;

    public function __construct() {
        $this->rules            = new X402_Rules();
        $this->payment_required = new X402_Payment_Required();
        $this->facilitator      = new X402_Facilitator_Client();
        $this->verifier         = new X402_Verifier($this->facilitator);
        $this->session          = new X402_Session();
        $this->response         = new X402_Response();

        // parse_request fires early, before any output or template_redirect
        add_action('parse_request', [$this, 'intercept'], 0);
    }

    /**
     * Main interception point.
     *
     * @param \WP $wp Current WordPress environment instance.
     */
    public function intercept($wp) {
        // 1. Is paywall enabled?
        if (get_option('x402_enabled', 'no') !== 'yes') {
            return;
        }

        // 2. Should this request be paywalled?
        if (!$this->rules->should_paywall($wp)) {
            return;
        }

        // 3. Does the visitor already have a valid paid session?
        $session_token = $this->get_session_token();
        if ($session_token && $this->session->is_valid($session_token)) {
            return;
        }

        // 4. Does the visitor include a PAYMENT-SIGNATURE? (retry after paying)
        $payment_signature = $this->get_payment_signature_header();
        if (!empty($payment_signature)) {
            $payment_required = $this->get_payment_required_cookie();

            if (!empty($payment_required)) {
                $result = $this->verifier->verify($payment_signature, $payment_required);

                if (!empty($result['valid'])) {
                    // Payment verified → create session, serve content
                    $token = $this->session->create();
                    $this->set_session_cookie($token);

                    // Fire-and-forget settlement
                    $this->facilitator->settle_async($payment_signature, $payment_required);

                    // Set PAYMENT-RESPONSE header
                    add_action('send_headers', function () use ($result) {
                        header('PAYMENT-RESPONSE: ' . base64_encode(wp_json_encode([
                            'status'           => 'settled',
                            'transactionHash'  => $result['transactionHash'] ?? '',
                            'network'          => get_option('x402_network', 'eip155:8453'),
                            'amount'           => get_option('x402_price', '$0.001'),
                        ])));
                    });

                    return; // Let the request through
                }
            }
        }

        // 5. No valid payment → send 402
        $payload = $this->payment_required->build();

        // If wallet is not configured, we can't charge — let the request through
        if (empty($payload)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[x402-paywall] Paywall triggered but no wallet configured — allowing request through.');
            }
            return;
        }

        $this->response->send_402($payload);
        exit;
    }

    /**
     * Get PAYMENT-SIGNATURE from request headers.
     */
    private function get_payment_signature_header() {
        return $_SERVER['HTTP_PAYMENT_SIGNATURE']
            ?? $_SERVER['REDIRECT_HTTP_PAYMENT_SIGNATURE']
            ?? '';
    }

    /**
     * Get the PAYMENT-REQUIRED cookie set during the initial 402 response.
     */
    private function get_payment_required_cookie() {
        return $_COOKIE['x402_payment_required'] ?? '';
    }

    /**
     * Get the session token from cookie.
     */
    private function get_session_token() {
        return $_COOKIE['x402_session'] ?? '';
    }

    /**
     * Set the session cookie.
     */
    private function set_session_cookie($token) {
        $ttl = (int) get_option('x402_session_ttl', 24);
        setcookie(
            'x402_session',
            $token,
            time() + ($ttl * HOUR_IN_SECONDS),
            COOKIEPATH,
            COOKIE_DOMAIN,
            is_ssl(),
            true // httponly
        );
    }
}
