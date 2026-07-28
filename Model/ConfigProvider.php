<?php

declare(strict_types=1);

namespace Ict\ExitIntentDiscountPopup\Model;

use Ict\ExitIntentDiscountPopup\Model\ResourceModel\GuestCouponUsage;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\SalesRule\Model\RuleFactory;

class ConfigProvider implements ConfigProviderInterface
{
    public function __construct(
        private readonly Config              $config,
        private readonly CouponValidator     $couponValidator,
        private readonly RuleFactory         $ruleFactory,
        private readonly CustomerSession     $customerSession,
        private readonly CheckoutSession     $checkoutSession,
        private readonly ResourceConnection  $resource,
        private readonly GuestCouponUsage    $guestCouponUsage
    ) {}

    public function getConfig(): array
    {
        $ruleId  = $this->config->getCouponRuleId();
        $isValid = $ruleId && $this->couponValidator->isValid($ruleId);

        // Hard gate: coupon already redeemed by this customer/guest.
        if ($isValid && $this->couponAlreadyRedeemed($ruleId)) {
            $isValid = false;
        }

        $couponCode = $isValid ? $this->resolveCouponCode($ruleId) : null;

        return [
            'exitIntentPopup' => [
                'enabled'             => $this->config->isEnabled() && $isValid && $couponCode !== null,
                'mobileDelay'         => $this->config->getMobileDelay(),
                'popupFrequency'      => $this->config->getPopupFrequency(),
                'colors'              => [
                    'background' => $this->config->getBackgroundColor(),
                    'font'       => $this->config->getFontColor(),
                    'button'     => $this->config->getButtonColor(),
                    'buttonText' => $this->config->getButtonTextColor(),
                ],
                'ruleId'              => $ruleId,
                'couponCode'          => $couponCode,
                'copyButtonText'      => $this->config->getCopyButtonText(),
                'popupHeading'        => $this->config->getPopupHeading(),
                'popupDescription'    => $this->config->getPopupDescription(),
                'discountLabel'       => $this->config->getDiscountLabel(),
                'ctaButtonText'       => $this->config->getCtaButtonText(),
                'secondaryButtonText' => $this->config->getSecondaryButtonText(),
                'i18n'                => [
                    'badgeLabel'               => (string) __('Limited Offer'),
                    'copyConfirmText'          => (string) __('Coupon code copied!'),
                    'closeAriaLabel'           => (string) __('Close popup'),
                    'couponAriaLabel'          => (string) __('Discount coupon code'),
                    'copyAriaLabel'            => (string) __('Copy coupon code to clipboard'),
                    'placeOrderAriaLabel'      => (string) __('Place your order now'),
                    'continueShoppingAriaLabel' => (string) __('Continue shopping'),
                ],
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Redemption gate — returns true if the coupon has already been used
    // -------------------------------------------------------------------------

    private function couponAlreadyRedeemed(int $ruleId): bool
    {
        if ($this->customerSession->isLoggedIn()) {
            return $this->loggedInCustomerHasRedeemed($ruleId);
        }

        return $this->guestHasRedeemed($ruleId);
    }

    /**
     * Logged-in: query native salesrule_coupon_usage joined to salesrule_coupon
     * to find a times_used >= 1 record for this customer + rule.
     */
    private function loggedInCustomerHasRedeemed(int $ruleId): bool
    {
        $customerId = (int) $this->customerSession->getCustomerId();
        if ($customerId <= 0) {
            return false;
        }

        $connection  = $this->resource->getConnection();
        $usageTable  = $this->resource->getTableName('salesrule_coupon_usage');
        $couponTable = $this->resource->getTableName('salesrule_coupon');

        $select = $connection->select()
            ->from(['u' => $usageTable], ['times_used'])
            ->join(['c' => $couponTable], 'u.coupon_id = c.coupon_id', [])
            ->where('c.rule_id = ?', $ruleId)
            ->where('u.customer_id = ?', $customerId)
            ->where('u.times_used >= 1')
            ->limit(1);

        return (bool) $connection->fetchOne($select);
    }

    /**
     * Guest: check the custom table using the quote's email address.
     * If no email is available yet (before shipping step), returns false
     * so the popup is not suppressed prematurely.
     */
    private function guestHasRedeemed(int $ruleId): bool
    {
        try {
            $email = (string) $this->checkoutSession->getQuote()->getCustomerEmail();
        } catch (\Exception) {
            return false;
        }

        if ($email === '') {
            return false;
        }

        return $this->guestCouponUsage->hasUsed($email, $ruleId);
    }

    // -------------------------------------------------------------------------

    private function resolveCouponCode(int $ruleId): ?string
    {
        try {
            $rule = $this->ruleFactory->create()->load($ruleId);
            $code = $rule->getPrimaryCoupon()->getCode();
            return $code ?: null;
        } catch (NoSuchEntityException|\Exception) {
            return null;
        }
    }
}
