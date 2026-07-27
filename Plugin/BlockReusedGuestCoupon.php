<?php

declare(strict_types=1);

namespace Ict\ExitIntentDiscountPopup\Plugin;

use Ict\ExitIntentDiscountPopup\Model\Config;
use Ict\ExitIntentDiscountPopup\Model\ResourceModel\GuestCouponUsage;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\SalesRule\Model\CouponFactory;
use Psr\Log\LoggerInterface;

class BlockReusedGuestCoupon
{
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly CouponFactory           $couponFactory,
        private readonly Config                  $config,
        private readonly GuestCouponUsage        $guestCouponUsage,
        private readonly LoggerInterface         $logger
    ) {}

    /**
     * Around plugin for \Magento\Quote\Model\CouponManagement::set().
     *
     * Blocks guest customers from re-applying the exit-intent popup coupon
     * if they have already placed an order with it.
     * Logged-in customers are skipped — Magento's native "Uses per Customer"
     * already handles them.
     * Any unexpected error in our lookup logic falls through to $proceed
     * (fail-open) so we never break unrelated coupon applications.
     *
     * @throws CouldNotSaveException when the guest has already used this coupon
     */
    public function aroundSet(
        \Magento\Quote\Model\CouponManagement $subject,
        callable $proceed,
        int|string $cartId,
        string $couponCode
    ): bool {
        try {
            $quote = $this->cartRepository->getActive($cartId);

            // Logged-in: native "Uses per Customer" handles this — do not interfere.
            if ($quote->getCustomerId()) {
                return $proceed($cartId, $couponCode);
            }

            // Only enforce for our module's configured coupon.
            $configuredRuleId = $this->config->getCouponRuleId();
            if (!$configuredRuleId) {
                return $proceed($cartId, $couponCode);
            }

            // Resolve the rule_id of the coupon being applied.
            $coupon = $this->couponFactory->create()->loadByCode($couponCode);
            if (!$coupon->getId() || (int) $coupon->getRuleId() !== $configuredRuleId) {
                // Not our popup coupon — don't interfere.
                return $proceed($cartId, $couponCode);
            }

            // Resolve guest email: quote-level first, then billing address fallback.
            $email = strtolower(trim((string) $quote->getCustomerEmail()));
            if ($email === '') {
                $email = strtolower(trim((string) $quote->getBillingAddress()->getEmail()));
            }

            // No email yet (early checkout step) — fail-open, let it through.
            if ($email === '') {
                return $proceed($cartId, $couponCode);
            }

            if ($this->guestCouponUsage->hasUsed($email, $configuredRuleId)) {
                throw new CouldNotSaveException(
                    __('This coupon code has already been used and cannot be applied again.')
                );
            }
        } catch (CouldNotSaveException $e) {
            // Re-throw our intentional block — do not swallow it.
            throw $e;
        } catch (\Throwable $e) {
            // Unexpected error in our lookup logic — log and fail-open.
            $this->logger->error(
                'ExitIntentDiscountPopup BlockReusedGuestCoupon: unexpected error — ' . $e->getMessage()
            );
        }

        return $proceed($cartId, $couponCode);
    }
}
