<?php

declare(strict_types=1);

namespace Ict\ExitIntentDiscountPopup\Observer;

use Ict\ExitIntentDiscountPopup\Model\Config;
use Ict\ExitIntentDiscountPopup\Model\ResourceModel\GuestCouponUsage;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\SalesRule\Model\CouponFactory;
use Psr\Log\LoggerInterface;

/**
 * Server-side backstop: fires on sales_quote_collect_totals_before.
 *
 * If a guest quote has our popup coupon applied AND the current email already
 * has a row in ict_exitintent_guest_coupon_usage, the coupon is stripped from
 * the quote before totals are collected.  This enforces the "one use per email"
 * rule regardless of what the JS layer does — it cannot be bypassed by
 * switching emails after the coupon was applied.
 *
 * The quote is NOT saved here; TotalsCollector saves it after collection when
 * the coupon code changes, or the next full save will persist the empty code.
 * We call $quote->setCouponCode('') which is enough to zero out the discount
 * in the current totals pass and triggers Magento's own coupon-removal flow.
 */
class StripReusedCouponOnTotals implements ObserverInterface
{
    /**
     * @param Config $config
     * @param GuestCouponUsage $guestCouponUsage
     * @param CouponFactory $couponFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Config           $config,
        private readonly GuestCouponUsage $guestCouponUsage,
        private readonly CouponFactory    $couponFactory,
        private readonly LoggerInterface  $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer): void
    {
        try {
            /** @var Quote $quote */
            $quote = $observer->getData('quote');

            if (!$quote instanceof Quote) {
                return;
            }

            // Only guest quotes — logged-in handled by native "Uses per Customer".
            if ($quote->getCustomerId()) {
                return;
            }

            $appliedCode = (string) $quote->getCouponCode();
            if ($appliedCode === '') {
                return;
            }

            $configuredRuleId = $this->config->getCouponRuleId();
            if (!$configuredRuleId) {
                return;
            }

            // Check that the applied coupon belongs to our configured rule.
            $coupon = $this->couponFactory->create()->loadByCode($appliedCode);
            if (!$coupon->getId() || (int) $coupon->getRuleId() !== $configuredRuleId) {
                return;
            }

            // Resolve guest email: quote level first, billing address fallback.
            $email = strtolower(trim((string) $quote->getCustomerEmail()));
            if ($email === '') {
                $billing = $quote->getBillingAddress();
                if ($billing instanceof Address) {
                    $email = strtolower(trim((string) $billing->getEmail()));
                }
            }

            if ($email === '') {
                // Email not yet entered — cannot check, leave coupon in place.
                return;
            }

            if (!$this->guestCouponUsage->hasUsed($email, $configuredRuleId)) {
                return;
            }

            // Email has already used this coupon — strip it before totals run.
            $quote->setCouponCode('');
            $this->logger->info(
                'ExitIntentDiscountPopup: stripped reused coupon for guest email ' . $email
            );
        } catch (\Throwable $e) {
            // Never break totals collection — fail-open.
            $this->logger->error(
                'ExitIntentDiscountPopup StripReusedCouponOnTotals: ' . $e->getMessage()
            );
        }
    }
}
