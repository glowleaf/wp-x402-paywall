<?php
namespace X402_Paywall;

defined('ABSPATH') || exit;

/**
 * Builds HTTP 402 response with header and human-readable HTML body.
 *
 * Also provides a 403 helper for fail-closed mode.
 */
class X402_Response {

    /**
     * Send a full 402 Payment Required response.
     *
     * Sets:
     *   - HTTP 402 status
     *   - PAYMENT-REQUIRED base64 header
     *   - x402_payment_required cookie (for retry with PAYMENT-SIGNATURE)
     *   - CORS expose headers
     *   - Clean HTML body explaining the paywall
     */
    public function send_402($payment_required_base64) {
        http_response_code(402);
        status_header(402);

        // Set PAYMENT-REQUIRED header for bot clients
        if (!empty($payment_required_base64)) {
            header('PAYMENT-REQUIRED: ' . $payment_required_base64);

            // Also set a cookie so the retry request can reference it
            $ttl = (int) get_option('x402_session_ttl', 24);
            setcookie('x402_payment_required', $payment_required_base64, time() + 300, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
            $_COOKIE['x402_payment_required'] = $payment_required_base64;
        }

        header('Access-Control-Expose-Headers: PAYMENT-REQUIRED, PAYMENT-RESPONSE');
        header('Vary: User-Agent, Authorization');
        header('Cache-Control: no-store, no-cache, must-revalidate, private');
        header('Pragma: no-cache');

        $price  = esc_html(get_option('x402_price', '$0.001'));
        $site   = esc_html(get_bloginfo('name'));
        $support = esc_html(get_option('x402_contact_email', ''));
        $path   = esc_html(wp_parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
        $mode   = esc_html(get_option('x402_mode', 'bots'));

        remove_all_actions('template_redirect');

        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>402 Payment Required</title>
    <meta name="robots" content="noindex">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 600px;
            margin: 80px auto;
            padding: 20px;
            line-height: 1.6;
            color: #1a1a2e;
            background: #f8f9fa;
        }
        h1 {
            font-size: 2em;
            margin-bottom: 0.3em;
            color: #1a1a2e;
        }
        .badge {
            display: inline-block;
            background: #2563eb;
            color: white;
            font-size: 0.75em;
            padding: 2px 8px;
            border-radius: 3px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 1em;
        }
        .price {
            font-size: 1.5em;
            font-weight: bold;
            color: #2563eb;
        }
        .path {
            color: #666;
            font-family: 'SFMono-Regular', Consolas, monospace;
            font-size: 0.9em;
            word-break: break-all;
        }
        .card {
            background: white;
            border-radius: 8px;
            padding: 24px;
            margin: 20px 0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }
        footer {
            margin-top: 40px;
            font-size: 0.85em;
            color: #999;
        }
        .x402-link {
            color: #2563eb;
            text-decoration: underline;
        }
        .human-note {
            background: #f0fdf4;
            border-left: 4px solid #22c55e;
            padding: 12px 16px;
            border-radius: 4px;
            font-size: 0.9em;
            color: #166534;
            margin: 20px 0;
        }
        @media (prefers-color-scheme: dark) {
            body { background: #0f172a; color: #e2e8f0; }
            .card { background: #1e293b; }
            .price { color: #60a5fa; }
            .human-note { background: #052e16; border-color: #22c55e; color: #bbf7d0; }
            .x402-link { color: #60a5fa; }
            .path { color: #94a3b8; }
        }
    </style>
</head>
<body>
    <div class="badge">HTTP 402</div>
    <h1>Payment Required</h1>
    <p>This resource requires a micropayment of <span class="price"><?php echo $price; ?></span> for automated access to <strong><?php echo $site; ?></strong>.</p>

    <div class="card">
        <p class="path"><?php echo $path; ?></p>
        <p style="margin-top: 12px; font-size: 0.9em;">
            Use the <code>PAYMENT-REQUIRED</code> header from this response and include a <code>PAYMENT-SIGNATURE</code> header with your payment to retry.
        </p>
        <?php if (!empty($support)): ?>
        <p style="margin-top: 8px; font-size: 0.9em;">
            Questions? <a href="mailto:<?php echo $support; ?>"><?php echo $support; ?></a>
        </p>
        <?php endif; ?>
    </div>

    <?php if ($mode === 'bots'): ?>
    <div class="human-note">
        🟢 Humans browsing normally are <strong>not</strong> charged — this paywall only applies to automated agents and crawlers.
    </div>
    <?php endif; ?>

    <footer>
        <a href="https://x402.org" class="x402-link" target="_blank" rel="noopener">x402 protocol</a> — HTTP 402 Payment Required
    </footer>
</body>
</html>
        <?php
    }

    /**
     * Send a 403 Forbidden response (used in fail-closed mode).
     */
    public function send_403($message = '') {
        http_response_code(403);
        status_header(403);
        wp_die(
            !empty($message) ? esc_html($message) : 'Access denied.',
            'Forbidden',
            ['response' => 403]
        );
    }
}
