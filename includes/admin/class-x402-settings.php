<?php
namespace X402_Paywall\Admin;

defined('ABSPATH') || exit;

/**
 * Settings page for the x402 Micropayment Paywall plugin.
 *
 * Adds a page under Settings → x402 Paywall with three sections:
 *   General      — enable/disable, mode, fail-open, contact email
 *   Pricing      — price, network, wallet, facilitator URL
 *   Rules        — session TTL, bot patterns, excluded paths, paywalled paths, IP whitelist
 *
 * Also shows a status panel at the bottom.
 */
class X402_Settings {

    private $option_group = 'x402_paywall_settings';

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_filter('plugin_action_links_' . plugin_basename(X402_PAYWALL_PATH . 'wp-x402-paywall.php'), [$this, 'add_settings_link']);
    }

    /**
     * Add the settings page under Settings menu.
     */
    public function add_admin_menu() {
        add_options_page(
            __('x402 Micropayment Paywall', 'x402-paywall'),
            __('x402 Paywall', 'x402-paywall'),
            'manage_options',
            'x402-paywall',
            [$this, 'render_page']
        );
    }

    /**
     * Add a Settings link on the Plugins page.
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('options-general.php?page=x402-paywall') . '">'
            . __('Settings', 'x402-paywall') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Register all settings, sections, and fields.
     */
    public function register_settings() {
        // --- Section 1: General ---
        add_settings_section(
            'x402_section_general',
            __('General', 'x402-paywall'),
            [$this, 'section_general_cb'],
            $this->option_group
        );

        register_setting($this->option_group, 'x402_enabled', [
            'type' => 'string',
            'sanitize_callback' => [$this, 'sanitize_yesno'],
        ]);
        register_setting($this->option_group, 'x402_mode', [
            'type' => 'string',
            'sanitize_callback' => [$this, 'sanitize_mode'],
        ]);
        register_setting($this->option_group, 'x402_fail_open', [
            'type' => 'string',
            'sanitize_callback' => [$this, 'sanitize_yesno'],
        ]);
        register_setting($this->option_group, 'x402_contact_email', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_email',
        ]);

        add_settings_field(
            'x402_enabled',
            __('Enable Paywall', 'x402-paywall'),
            [$this, 'field_enabled'],
            $this->option_group,
            'x402_section_general'
        );
        add_settings_field(
            'x402_mode',
            __('Paywall Mode', 'x402-paywall'),
            [$this, 'field_mode'],
            $this->option_group,
            'x402_section_general'
        );
        add_settings_field(
            'x402_fail_open',
            __('Fail Open', 'x402-paywall'),
            [$this, 'field_fail_open'],
            $this->option_group,
            'x402_section_general'
        );
        add_settings_field(
            'x402_contact_email',
            __('Support Email', 'x402-paywall'),
            [$this, 'field_contact_email'],
            $this->option_group,
            'x402_section_general'
        );

        // --- Section 2: Pricing & Blockchain ---
        add_settings_section(
            'x402_section_pricing',
            __('Pricing & Blockchain', 'x402-paywall'),
            [$this, 'section_pricing_cb'],
            $this->option_group
        );

        register_setting($this->option_group, 'x402_price', [
            'type' => 'string',
            'sanitize_callback' => [$this, 'sanitize_price'],
        ]);
        register_setting($this->option_group, 'x402_network', [
            'type' => 'string',
            'sanitize_callback' => [$this, 'sanitize_network'],
        ]);
        register_setting($this->option_group, 'x402_wallet', [
            'type' => 'string',
            'sanitize_callback' => [$this, 'sanitize_wallet'],
        ]);
        register_setting($this->option_group, 'x402_facilitator_url', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_url',
        ]);

        add_settings_field(
            'x402_price',
            __('Price per Request', 'x402-paywall'),
            [$this, 'field_price'],
            $this->option_group,
            'x402_section_pricing'
        );
        add_settings_field(
            'x402_network',
            __('Blockchain Network', 'x402-paywall'),
            [$this, 'field_network'],
            $this->option_group,
            'x402_section_pricing'
        );
        add_settings_field(
            'x402_wallet',
            __('Your Wallet Address', 'x402-paywall'),
            [$this, 'field_wallet'],
            $this->option_group,
            'x402_section_pricing'
        );
        add_settings_field(
            'x402_facilitator_url',
            __('Facilitator URL', 'x402-paywall'),
            [$this, 'field_facilitator_url'],
            $this->option_group,
            'x402_section_pricing'
        );

        // --- Section 3: Session & Rules ---
        add_settings_section(
            'x402_section_rules',
            __('Session & Rules', 'x402-paywall'),
            [$this, 'section_rules_cb'],
            $this->option_group
        );

        register_setting($this->option_group, 'x402_session_ttl', [
            'type' => 'integer',
            'sanitize_callback' => 'absint',
        ]);
        register_setting($this->option_group, 'x402_bot_patterns', [
            'type' => 'string',
            'sanitize_callback' => [$this, 'sanitize_textarea'],
        ]);
        register_setting($this->option_group, 'x402_excluded_paths', [
            'type' => 'string',
            'sanitize_callback' => [$this, 'sanitize_textarea'],
        ]);
        register_setting($this->option_group, 'x402_paywalled_paths', [
            'type' => 'string',
            'sanitize_callback' => [$this, 'sanitize_textarea'],
        ]);
        register_setting($this->option_group, 'x402_ip_whitelist', [
            'type' => 'string',
            'sanitize_callback' => [$this, 'sanitize_textarea'],
        ]);

        add_settings_field(
            'x402_session_ttl',
            __('Session TTL (hours)', 'x402-paywall'),
            [$this, 'field_session_ttl'],
            $this->option_group,
            'x402_section_rules'
        );
        add_settings_field(
            'x402_bot_patterns',
            __('Bot User-Agent Patterns', 'x402-paywall'),
            [$this, 'field_bot_patterns'],
            $this->option_group,
            'x402_section_rules'
        );
        add_settings_field(
            'x402_excluded_paths',
            __('Excluded Paths', 'x402-paywall'),
            [$this, 'field_excluded_paths'],
            $this->option_group,
            'x402_section_rules'
        );
        add_settings_field(
            'x402_paywalled_paths',
            __('Paywalled Paths (paths mode)', 'x402-paywall'),
            [$this, 'field_paywalled_paths'],
            $this->option_group,
            'x402_section_rules'
        );
        add_settings_field(
            'x402_ip_whitelist',
            __('IP Whitelist', 'x402-paywall'),
            [$this, 'field_ip_whitelist'],
            $this->option_group,
            'x402_section_rules'
        );
    }

    // --- Sanitizers ---

    public function sanitize_yesno($value) {
        return ($value === 'yes') ? 'yes' : 'no';
    }

    public function sanitize_mode($value) {
        return in_array($value, ['bots', 'all', 'paths'], true) ? $value : 'bots';
    }

    public function sanitize_price($value) {
        $value = trim($value);
        if (preg_match('/^\$?(\d+(\.\d{1,6})?)$/', $value, $m)) {
            return '$' . $m[1];
        }
        return '$0.001';
    }

    public function sanitize_network($value) {
        $allowed = [
            'eip155:8453'    => 'eip155:8453',   // Base
            'solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp' => 'solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp', // Solana
            'eip155:137'     => 'eip155:137',     // Polygon
            'eip155:43114'   => 'eip155:43114',   // Avalanche
            'eip155:84532'   => 'eip155:84532',   // Base Sepolia testnet
            'solana:EtWTRABZaYq6iMfeYKouRu166VU2xqa1' => 'solana:EtWTRABZaYq6iMfeYKouRu166VU2xqa1', // Solana devnet
        ];
        return isset($allowed[$value]) ? $allowed[$value] : 'eip155:8453';
    }

    public function sanitize_wallet($value) {
        return sanitize_text_field(trim($value));
    }

    public function sanitize_textarea($value) {
        return sanitize_textarea_field($value);
    }

    // --- Section descriptions ---

    public function section_general_cb() {
        echo '<p>' . esc_html__(
            'Enable and configure the x402 paywall. In "bots" mode, only automated agents are charged. In "all" mode, every visitor gets a 402 until they pay.',
            'x402-paywall'
        ) . '</p>';
    }

    public function section_pricing_cb() {
        echo '<p>' . esc_html__(
            'Set your price and wallet. The facilitator handles blockchain interaction — you just need a wallet address on your chosen network.',
            'x402-paywall'
        ) . '</p>';
    }

    public function section_rules_cb() {
        echo '<p>' . esc_html__(
            'Fine-tune which requests get paywalled. llms.txt, wp-admin, and sitemaps are excluded by default so AI discoverability and admin access are never blocked.',
            'x402-paywall'
        ) . '</p>';
    }

    // --- Field renderers ---

    public function field_enabled() {
        $value = get_option('x402_enabled', 'no');
        ?>
        <label><input type="radio" name="x402_enabled" value="yes" <?php checked($value, 'yes'); ?>> <?php esc_html_e('Enabled', 'x402-paywall'); ?></label>
        <label style="margin-left:20px;"><input type="radio" name="x402_enabled" value="no" <?php checked($value, 'no'); ?>> <?php esc_html_e('Disabled', 'x402-paywall'); ?></label>
        <p class="description"><?php esc_html_e('When disabled, all requests pass through normally.', 'x402-paywall'); ?></p>
        <?php
    }

    public function field_mode() {
        $value = get_option('x402_mode', 'bots');
        ?>
        <select name="x402_mode">
            <option value="bots" <?php selected($value, 'bots'); ?>><?php esc_html_e('Bots only (default)', 'x402-paywall'); ?></option>
            <option value="all" <?php selected($value, 'all'); ?>><?php esc_html_e('All traffic', 'x402-paywall'); ?></option>
            <option value="paths" <?php selected($value, 'paths'); ?>><?php esc_html_e('Specific paths only', 'x402-paywall'); ?></option>
        </select>
        <p class="description"><?php esc_html_e('"Bots only" paywalls known crawlers/AI agents. "All" paywalls every non-excluded request. "Paths" paywalls only specific URL prefixes.', 'x402-paywall'); ?></p>
        <?php
    }

    public function field_fail_open() {
        $value = get_option('x402_fail_open', 'yes');
        ?>
        <label><input type="radio" name="x402_fail_open" value="yes" <?php checked($value, 'yes'); ?>> <?php esc_html_e('Yes — let requests through if facilitator is unreachable', 'x402-paywall'); ?></label><br>
        <label><input type="radio" name="x402_fail_open" value="no" <?php checked($value, 'no'); ?>> <?php esc_html_e('No — deny requests if facilitator is unreachable', 'x402-paywall'); ?></label>
        <p class="description"><?php esc_html_e('Strongly recommended: Yes. A broken facilitator should not break your site.', 'x402-paywall'); ?></p>
        <?php
    }

    public function field_contact_email() {
        $value = get_option('x402_contact_email', '');
        ?>
        <input type="email" name="x402_contact_email" value="<?php echo esc_attr($value); ?>" class="regular-text" placeholder="admin@example.com">
        <p class="description"><?php esc_html_e('Shown on the 402 page so bot operators can contact you.', 'x402-paywall'); ?></p>
        <?php
    }

    public function field_price() {
        $value = get_option('x402_price', '$0.001');
        ?>
        <input type="text" name="x402_price" value="<?php echo esc_attr($value); ?>" class="small-text" placeholder="$0.001">
        <p class="description"><?php esc_html_e('Price per request (e.g., $0.001, $0.01, $0.10).', 'x402-paywall'); ?></p>
        <?php
    }

    public function field_network() {
        $value = get_option('x402_network', 'eip155:8453');
        $networks = [
            'eip155:8453'    => __('Base (recommended — cheapest EVM)', 'x402-paywall'),
            'solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp' => __('Solana (even cheaper gas)', 'x402-paywall'),
            'eip155:137'     => __('Polygon', 'x402-paywall'),
            'eip155:43114'   => __('Avalanche C-Chain', 'x402-paywall'),
            'eip155:84532'   => __('Base Sepolia testnet', 'x402-paywall'),
            'solana:EtWTRABZaYq6iMfeYKouRu166VU2xqa1' => __('Solana devnet', 'x402-paywall'),
        ];
        ?>
        <select name="x402_network">
            <?php foreach ($networks as $key => $label): ?>
                <option value="<?php echo esc_attr($key); ?>" <?php selected($value, $key); ?>><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
        <p class="description"><?php esc_html_e('The blockchain where you receive payments. Base recommended — $0.001 gas, USDC, Coinbase-backed.', 'x402-paywall'); ?></p>
        <?php
    }

    public function field_wallet() {
        $value = get_option('x402_wallet', '');
        ?>
        <input type="text" name="x402_wallet" value="<?php echo esc_attr($value); ?>" class="regular-text code" placeholder="0x... or Solana address">
        <p class="description"><?php esc_html_e('Your wallet address on the selected blockchain. Payments settle here.', 'x402-paywall'); ?></p>
        <?php
    }

    public function field_facilitator_url() {
        $value = get_option('x402_facilitator_url', 'https://x402.org/facilitator');
        ?>
        <input type="url" name="x402_facilitator_url" value="<?php echo esc_attr($value); ?>" class="regular-text code">
        <p class="description"><?php esc_html_e('Default: https://x402.org/facilitator. Run your own facilitator for full control.', 'x402-paywall'); ?></p>
        <?php
    }

    public function field_session_ttl() {
        $value = get_option('x402_session_ttl', 24);
        ?>
        <input type="number" name="x402_session_ttl" value="<?php echo esc_attr($value); ?>" class="small-text" min="1" max="720">
        <p class="description"><?php esc_html_e('How long a paid session lasts (hours). Default: 24h. After expiry, visitor must pay again.', 'x402-paywall'); ?></p>
        <?php
    }

    public function field_bot_patterns() {
        $value = get_option('x402_bot_patterns', '');
        ?>
        <textarea name="x402_bot_patterns" rows="6" class="large-text code"><?php echo esc_textarea($value); ?></textarea>
        <p class="description"><?php esc_html_e('One pattern per line. Case-insensitive partial matching against User-Agent string.', 'x402-paywall'); ?></p>
        <?php
    }

    public function field_excluded_paths() {
        $value = get_option('x402_excluded_paths', '');
        ?>
        <textarea name="x402_excluded_paths" rows="8" class="large-text code"><?php echo esc_textarea($value); ?></textarea>
        <p class="description">
            <?php esc_html_e('URL paths to never paywall (one per line). Supports prefix matching.', 'x402-paywall'); ?>
            <br><strong><?php esc_html_e('llms.txt should always be excluded to maintain AI discoverability.', 'x402-paywall'); ?></strong>
        </p>
        <?php
    }

    public function field_paywalled_paths() {
        $value = get_option('x402_paywalled_paths', '/wp-json/');
        ?>
        <textarea name="x402_paywalled_paths" rows="4" class="large-text code"><?php echo esc_textarea($value); ?></textarea>
        <p class="description"><?php esc_html_e('Only used in "paths" mode. URL prefixes to paywall (one per line).', 'x402-paywall'); ?></p>
        <?php
    }

    public function field_ip_whitelist() {
        $value = get_option('x402_ip_whitelist', '');
        ?>
        <textarea name="x402_ip_whitelist" rows="4" class="large-text code"><?php echo esc_textarea($value); ?></textarea>
        <p class="description"><?php esc_html_e('IP addresses (one per line) that bypass the paywall entirely.', 'x402-paywall'); ?></p>
        <?php
    }

    // --- Page renderer ---

    public function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions.', 'x402-paywall'));
        }

        if (isset($_GET['settings-updated'])) {
            add_settings_errors('x402_messages', 'x402_updated', __('Settings saved.', 'x402-paywall'), 'updated');
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <form action="options.php" method="post">
                <?php
                settings_fields($this->option_group);
                do_settings_sections($this->option_group);
                submit_button();
                ?>
            </form>

            <hr>

            <h2><?php esc_html_e('Status', 'x402-paywall'); ?></h2>
            <?php $this->render_status(); ?>
        </div>
        <?php
    }

    /**
     * Render a quick status panel showing whether the paywall is ready to use.
     */
    private function render_status() {
        $enabled  = get_option('x402_enabled', 'no');
        $mode     = get_option('x402_mode', 'bots');
        $wallet   = get_option('x402_wallet', '');
        $network  = get_option('x402_network', 'eip155:8453');
        $facilitator = untrailingslashit(get_option('x402_facilitator_url', 'https://x402.org/facilitator'));

        ?>
        <table class="widefat striped" style="max-width:600px">
            <tr>
                <td><strong><?php esc_html_e('Status', 'x402-paywall'); ?></strong></td>
                <td><?php echo ($enabled === 'yes') ? '🟢 ' . esc_html__('Enabled', 'x402-paywall') : '🔴 ' . esc_html__('Disabled', 'x402-paywall'); ?></td>
            </tr>
            <tr>
                <td><strong><?php esc_html_e('Mode', 'x402-paywall'); ?></strong></td>
                <td><?php echo esc_html(ucfirst($mode)); ?></td>
            </tr>
            <tr>
                <td><strong><?php esc_html_e('Wallet', 'x402-paywall'); ?></strong></td>
                <td>
                    <?php if (!empty($wallet)): ?>
                        🟢 <?php esc_html_e('Configured', 'x402-paywall'); ?>
                        <code style="font-size:0.85em"><?php echo esc_html(substr($wallet, 0, 12)) . '…' . esc_html(substr($wallet, -4)); ?></code>
                    <?php else: ?>
                        🔴 <?php esc_html_e('Not set — paywall will not charge', 'x402-paywall'); ?>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td><strong><?php esc_html_e('Network', 'x402-paywall'); ?></strong></td>
                <td><code><?php echo esc_html($network); ?></code></td>
            </tr>
            <tr>
                <td><strong><?php esc_html_e('Facilitator', 'x402-paywall'); ?></strong></td>
                <td><code><?php echo esc_html($facilitator); ?></code></td>
            </tr>
        </table>

        <?php if ($enabled !== 'yes' || empty($wallet)): ?>
            <div class="notice notice-warning inline" style="max-width:600px;margin-top:10px;">
                <p>
                    <?php if ($enabled !== 'yes'): ?>
                        ⚠️ <?php esc_html_e('Paywall is currently disabled. Enable it above.', 'x402-paywall'); ?>
                    <?php endif; ?>
                    <?php if (empty($wallet)): ?>
                        <br>⚠️ <?php esc_html_e('No wallet address configured. Payments cannot be processed until you add one.', 'x402-paywall'); ?>
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <div class="notice notice-success inline" style="max-width:600px;margin-top:10px;">
                <p>✅ <?php esc_html_e('Paywall is configured and ready. AI agents hitting your site will get a 402 Payment Required response.', 'x402-paywall'); ?></p>
            </div>
        <?php endif;
    }
}
