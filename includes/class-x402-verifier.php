<?php
namespace X402_Paywall;

defined('ABSPATH') || exit;

/**
 * Thin verifier wrapper. Delegates actual verification to the facilitator client.
 */
class X402_Verifier {

    private $facilitator;

    public function __construct(X402_Facilitator_Client $facilitator) {
        $this->facilitator = $facilitator;
    }

    /**
     * Verify a PAYMENT-SIGNATURE against the PAYMENT-REQUIRED payload.
     *
     * @param string $payment_signature Base64 from PAYMENT-SIGNATURE header.
     * @param string $payment_required  Base64 from PAYMENT-REQUIRED header/cookie.
     * @return array { valid: bool, transactionHash: string }
     */
    public function verify($payment_signature, $payment_required) {
        if (empty($payment_signature) || empty($payment_required)) {
            return ['valid' => false, 'transactionHash' => ''];
        }

        return $this->facilitator->verify($payment_signature, $payment_required);
    }
}
