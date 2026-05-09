<?php
namespace X402_Paywall;

defined('ABSPATH') || exit;

/**
 * Rules engine: determines if a request should be paywalled.
 *
 * Modes:
 *   bots  — paywall known crawlers/AI agents only (default)
 *   all   — paywall everything except excluded paths
 *   paths — paywall only specific URL prefixes
 */
class X402_Rules {

    /**
     * Main check: should this request get a 402?
     */
    public function should_paywall($wp) {
        // Never paywall logged-in editors/admins
        if (is_user_logged_in() && current_user_can('edit_posts')) {
            return false;
        }

        // IP whitelist bypass
        if ($this->is_ip_whitelisted()) {
            return false;
        }

        $mode = get_option('x402_mode', 'bots');

        switch ($mode) {
            case 'all':
                return !$this->is_path_excluded();

            case 'paths':
                return $this->is_path_matched() && !$this->is_path_excluded();

            case 'bots':
            default:
                // In bot mode, excluded paths always bypass
                if ($this->is_path_excluded()) {
                    return false;
                }
                return $this->is_bot();
        }
    }

    /**
     * Detect if the request is from a bot / AI agent.
     */
    public function is_bot() {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // No user-agent → likely a bot
        if (empty($ua)) {
            return true;
        }

        // Legitimate browsers: Mozilla/5.0 + Chrome/Safari/Firefox/Edge/Opera
        if (preg_match('/(mozilla|chrome|safari|firefox|edge|opera)\/[\d.]+/i', $ua)) {
            // But still flag AI-specific user-agents even with browser-like strings
            $ai_patterns = '/anthropic|claude|gpt-|chatgpt|openai|perplexity|cohere|ai2|semanticscholar/i';
            if (preg_match($ai_patterns, $ua)) {
                return true;
            }
            return false;
        }

        // Check configured bot patterns
        $patterns_raw = get_option('x402_bot_patterns', '');
        if (empty($patterns_raw)) {
            return false;
        }

        $parts = array_map('trim', explode("\n", $patterns_raw));
        $parts = array_filter($parts, function ($p) { return !empty($p); });

        if (empty($parts)) {
            return false;
        }

        // Escape regex special chars in each pattern
        $escaped = array_map(function ($p) {
            return preg_quote($p, '/');
        }, $parts);

        $regex = '/(' . implode('|', $escaped) . ')/i';
        return (bool) preg_match($regex, $ua);
    }

    /**
     * Check if current path is in the exclusion list.
     */
    public function is_path_excluded() {
        $current = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $current = rtrim($current, '/') ?: '/';

        $excluded_raw = get_option('x402_excluded_paths', '');
        $excluded = array_map('trim', array_filter(explode("\n", $excluded_raw)));

        foreach ($excluded as $path) {
            if (empty($path)) {
                continue;
            }
            $path = rtrim($path, '/') ?: '/';

            // Wildcard suffix matching (e.g., /wp-json/*)
            if (strpos($path, '*') !== false) {
                $prefix = rtrim(str_replace('*', '', $path), '/');
                if (strpos($current, $prefix) === 0) {
                    return true;
                }
            }

            // Exact match or prefix match
            if ($current === $path || strpos($current, $path) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if current path matches paywalled paths (for 'paths' mode).
     */
    public function is_path_matched() {
        $current = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $current = rtrim($current, '/') ?: '/';

        $matched_raw = get_option('x402_paywalled_paths', '/wp-json/');
        $matched = array_map('trim', array_filter(explode("\n", $matched_raw)));

        foreach ($matched as $path) {
            if (empty($path)) {
                continue;
            }
            $path = rtrim($path, '/') ?: '/';
            if (strpos($current, $path) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the User-Agent matches the bot whitelist (googlebot, bingbot, etc.)
     * Known good bots always pass through, even under auto-activated paywall.
     */
    public function is_bot_whitelisted_ua() {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if (empty($ua)) {
            return false;
        }

        $patterns_raw = get_option('x402_bot_whitelist_patterns', '');
        if (empty($patterns_raw)) {
            return false;
        }

        $parts = array_map('trim', array_filter(explode("\n", $patterns_raw)));
        if (empty($parts)) {
            return false;
        }

        $escaped = array_map(function ($p) {
            return preg_quote($p, '/');
        }, $parts);

        $regex = '/(' . implode('|', $escaped) . ')/i';
        return (bool) preg_match($regex, $ua);
    }

    /**
     * Check if visitor IP is whitelisted.
     */
    public function is_ip_whitelisted() {
        $whitelist_raw = get_option('x402_ip_whitelist', '');
        if (empty($whitelist_raw)) {
            return false;
        }

        $whitelist = array_map('trim', array_filter(explode("\n", $whitelist_raw)));
        if (empty($whitelist)) {
            return false;
        }

        $remote_addr = $_SERVER['REMOTE_ADDR'] ?? '';
        $x_forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';

        // Check both direct IP and forwarded IP
        $ips_to_check = [$remote_addr];
        if (!empty($x_forwarded)) {
            $ips_to_check[] = trim(explode(',', $x_forwarded)[0]);
        }

        foreach ($ips_to_check as $ip) {
            if (in_array($ip, $whitelist, true)) {
                return true;
            }
        }

        return false;
    }
}
