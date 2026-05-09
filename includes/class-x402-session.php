<?php
namespace X402_Paywall;

defined('ABSPATH') || exit;

/**
 * Session management for paid visitors.
 *
 * Uses WordPress transients (stored in wp_options table with auto-expiry)
 * so no custom database tables are needed.
 *
 * A "session" is a random token stored in a cookie + transient cache.
 * Once a visitor pays, they bypass the paywall for N hours.
 */
class X402_Session {

    private $prefix = 'x402_sesh_';
    private $ttl;

    public function __construct() {
        $this->ttl = (int) get_option('x402_session_ttl', 24) * HOUR_IN_SECONDS;
    }

    /**
     * Create a new paid session token.
     *
     * @return string 48-char hex token.
     */
    public function create() {
        $token = bin2hex(random_bytes(24)); // 48 hex chars
        set_transient($this->prefix . $token, [
            'paid'    => true,
            'created' => time(),
        ], $this->ttl);
        return $token;
    }

    /**
     * Check if a session token is valid (has been paid).
     *
     * @param string $token The session token.
     * @return bool True if valid.
     */
    public function is_valid($token) {
        if (empty($token) || strlen($token) !== 48) {
            return false;
        }
        $data = get_transient($this->prefix . $token);
        return !empty($data['paid']);
    }

    /**
     * Revoke a session (force re-pay).
     *
     * @param string $token The session token.
     * @return bool True on success.
     */
    public function revoke($token) {
        return delete_transient($this->prefix . $token);
    }
}
