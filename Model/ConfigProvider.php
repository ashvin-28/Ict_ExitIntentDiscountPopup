<?php

declare(strict_types=1);

namespace Ict\ExitIntentDiscountPopup\Model;

use Ict\ExitIntentDiscountPopup\Model\ResourceModel\GuestCouponUsage;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Customer\Model\Url as CustomerUrl;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Url\EncoderInterface;
use Magento\Framework\UrlInterface;
use Magento\SalesRule\Model\RuleFactory;

class ConfigProvider implements ConfigProviderInterface
{
    /**
     * @param Config $config
     * @param CouponValidator $couponValidator
     * @param RuleFactory $ruleFactory
     * @param CustomerSession $customerSession
     * @param CheckoutSession $checkoutSession
     * @param ResourceConnection $resource
     * @param GuestCouponUsage $guestCouponUsage
     * @param UrlInterface $urlBuilder
     * @param EncoderInterface $urlEncoder
     */
    public function __construct(
        private readonly Config              $config,
        private readonly CouponValidator     $couponValidator,
        private readonly RuleFactory         $ruleFactory,
        private readonly CustomerSession     $customerSession,
        private readonly CheckoutSession     $checkoutSession,
        private readonly ResourceConnection  $resource,
        private readonly GuestCouponUsage    $guestCouponUsage,
        private readonly UrlInterface        $urlBuilder,
        private readonly EncoderInterface    $urlEncoder
    ) {
    }

    /**
     * Build the exit-intent popup checkout config.
     *
     * @return array
     */
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
                'isLoggedIn'          => $this->customerSession->isLoggedIn(),
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
                'applyButtonText'     => $this->config->getApplyButtonText(),
                'popupHeading'        => $this->config->getPopupHeading(),
                'popupDescription'    => $this->config->getPopupDescription(),
                'discountLabel'       => $this->config->getDiscountLabel(),
                'closeButtonText'     => $this->config->getCloseButtonText(),
                'loginPrompt'         => [
                    'frequency'        => $this->config->getLoginPromptFrequency(),
                    'heading'          => $this->config->getLoginPromptHeading(),
                    'message'          => $this->config->getLoginPromptMessage(),
                    'loginButtonText'  => $this->config->getLoginButtonText(),
                    'registerLinkText' => $this->config->getRegisterLinkText(),
                    'loginUrl'         => $this->buildAccountUrl('customer/account/login'),
                    'registerUrl'      => $this->buildAccountUrl('customer/account/create'),
                ],
                'i18n'                => [
                    'badgeLabel'            => (string) __('Limited Offer'),
                    'copyConfirmText'       => (string) __('Coupon code copied!'),
                    'closeAriaLabel'        => (string) __('Close popup'),
                    'couponAriaLabel'       => (string) __('Discount coupon code'),
                    'copyAriaLabel'         => (string) __('Copy coupon code to clipboard'),
                    'applyAriaLabel'        => (string) __('Apply coupon code to cart'),
                    'applyConfirmText'      => (string) __('Coupon applied!'),
                    'applyGenericErrorText' => (string) __('Unable to apply this coupon code. Please try again.'),
                ],
            ],
        ];
    }

    /**
     * Build a customer account URL carrying the current checkout page as its
     * encoded referer, so a successful login or registration redirects the
     * guest back to checkout (see Observer\SetLoginReferrerUrl).
     *
     * @param string $route
     * @return string
     */
    private function buildAccountUrl(string $route): string
    {
        $checkoutUrl = $this->urlBuilder->getUrl('checkout', ['_secure' => true]);

        return $this->urlBuilder->getUrl($route, [
            CustomerUrl::REFERER_QUERY_PARAM_NAME => $this->urlEncoder->encode($checkoutUrl),
        ]);
    }

    // -------------------------------------------------------------------------
    // Redemption gate — returns true if the coupon has already been used
    // -------------------------------------------------------------------------

    /**
     * Check whether the coupon has already been redeemed by the current customer or guest.
     *
     * @param int $ruleId
     * @return bool
     */
    private function couponAlreadyRedeemed(int $ruleId): bool
    {
        if ($this->customerSession->isLoggedIn()) {
            return $this->loggedInCustomerHasRedeemed($ruleId);
        }

        return $this->guestHasRedeemed($ruleId);
    }

    /**
     * Check native coupon usage for the logged-in customer.
     *
     * Queries native salesrule_coupon_usage joined to salesrule_coupon
     * to find a times_used >= 1 record for this customer + rule.
     *
     * @param int $ruleId
     * @return bool
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
     * Check guest coupon usage via the quote's email address.
     *
     * If no email is available yet (before the shipping step), returns false
     * so the popup is not suppressed prematurely.
     *
     * @param int $ruleId
     * @return bool
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

    /**
     * Resolve the coupon code for the given rule.
     *
     * @param int $ruleId
     * @return string|null
     */
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
