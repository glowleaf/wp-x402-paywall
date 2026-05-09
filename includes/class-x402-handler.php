<?php
namespace X402_Paywall;

defined('ABSPATH') || exit;

/**
 * Core handler: intercepts requests and orchestrates the paywall flow.
 *
 * Hooks into parse_request (fires before any output) to:
 * 1. Track request rate (for auto mode)
 * 2. Check auto activation/deactivation
 * 3. Check if enabled and rules match
 * 4. Check for existing paid session (cookie)
 * 5. Check for PAYMENT-SIGNATURE header (retry after payment)
 * 6. If no valid payment → send 402 with PAYMENT-REQUIRED
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

        // Admin bar panic button (front-end + admin)
        add_action('admin_bar_menu', [$this, 'admin_bar_button'], 100);
    }

    /**
     * Main interception point.
     */
    public function intercept($wp) {
        // Track request rate on every hit (for auto mode monitoring)
        $this->track_rate();

        $mode = get_option('x402_mode', 'bots');

        // Auto mode: check if rate exceeds threshold and auto-activate
        if ($mode === 'auto') {
            $this->check_auto_activation();
        }

        // Is the paywall active? (manually enabled OR auto-activated)
        $enabled     = get_option('x402_enabled', 'no');
        $auto_active = get_option('x402_auto_activated', 'no');
        $is_active   = ($enabled === 'yes') || ($mode === 'auto' && $auto_active === 'yes');

        if (!$is_active) {
            return;
        }

        // Bot whitelist: known good bots (googlebot, bingbot) pass through
        if ($this->rules->is_bot_whitelisted_ua()) {
            return;
        }

        // Should this request be paywalled?
        if (!$this->rules->should_paywall($wp)) {
            return;
        }

        // Does the visitor already have a valid paid session?
        $session_token = $this->get_session_token();
        if ($session_token && $this->session->is_valid($session_token)) {
            return;
        }

        // Does the visitor include a PAYMENT-SIGNATURE? (retry after paying)
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

        // No valid payment → send 402
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
     * Track request rate using a sliding window.
     * Always runs, even when paywall is off, so auto mode can detect spikes.
     */
    private function track_rate() {
        $window = max(10, (int) get_option('x402_auto_window', 60));
        $slot   = (int) (time() / $window);
        $key    = 'x402_rate_' . $slot;

        $count = (int) get_transient($key);
        $count++;
        set_transient($key, $count, $window * 2);
    }

    /**
     * Check if auto mode should activate or deactivate based on rate.
     */
    private function check_auto_activation() {
        $window              = max(10, (int) get_option('x402_auto_window', 60));
        $threshold           = (int) get_option('x402_auto_threshold', 1000);
        $deactivate_threshold = (int) get_option('x402_auto_deactivate_threshold', 500);

        // Current time-slot count
        $slot          = (int) (time() / $window);
        $current_count = (int) get_transient('x402_rate_' . $slot);

        // Previous slot (to handle window boundary edge)
        $prev_count = (int) get_transient('x402_rate_' . ($slot - 1));

        // Use the higher of the two (smoothed rate)
        $rate = max($current_count, $prev_count);

        $auto_active = get_option('x402_auto_activated', 'no');

        if ($rate >= $threshold && $auto_active !== 'yes') {
            update_option('x402_auto_activated', 'yes');
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("[x402-paywall] Auto-activated: {$rate} req/slot >= {$threshold}");
            }
        } elseif ($rate <= $deactivate_threshold && $auto_active === 'yes') {
            update_option('x402_auto_activated', 'no');
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("[x402-paywall] Auto-deactivated: {$rate} req/slot <= {$deactivate_threshold}");
            }
        }
    }

    /**
     * Admin bar panic button — shows current status, toggles manually.
     */
    public function admin_bar_button($wp_admin_bar) {
        if (!current_user_can('manage_options')) {
            return;
        }

        $enabled     = get_option('x402_enabled', 'no');
        $mode        = get_option('x402_mode', 'bots');
        $auto_active = get_option('x402_auto_activated', 'no');

        if ($mode === 'auto' && $auto_active === 'yes') {
            $label = '⚠️ Paywall (AUTO)';
            $color = '#f59e0b';
        } elseif ($enabled === 'yes') {
            $label = '🔴 Paywall ON';
            $color = '#ef4444';
        } else {
            $label = '🟢 Paywall OFF';
            $color = '#22c55e';
        }

        $wp_admin_bar->add_node([
            'id'     => 'x402-paywall-status',
            'title'  => '<span style="color:' . $color . ';font-weight:600;">' . $label . '</span>',
            'href'   => wp_nonce_url(admin_url('options-general.php?page=x402-paywall&x402_toggle=1'), 'x402_toggle'),
            'meta'   => ['title' => 'Click to toggle x402 paywall'],
        ]);
    }

    // --- Header/cookie helpers ---

    private function get_payment_signature_header() {
        return $_SERVER['HTTP_PAYMENT_SIGNATURE']
            ?? $_SERVER['REDIRECT_HTTP_PAYMENT_SIGNATURE']
            ?? '';
    }

    private function get_payment_required_cookie() {
        return $_COOKIE['x402_payment_required'] ?? '';
    }

    private function get_session_token() {
        return $_COOKIE['x402_session'] ?? '';
    }

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
