<?php

declare(strict_types=1);

namespace Ict\ExitIntentDiscountPopup\Model;

use Ict\ExitIntentDiscountPopup\Model\ResourceModel\CouponCopied;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\SalesRule\Api\RuleRepositoryInterface;
use Magento\SalesRule\Model\RuleFactory;

class ConfigProvider implements ConfigProviderInterface
{
    public function __construct(
        private readonly Config                  $config,
        private readonly CouponValidator         $couponValidator,
        private readonly RuleRepositoryInterface $ruleRepository,
        private readonly RuleFactory             $ruleFactory,
        private readonly CustomerSession         $customerSession,
        private readonly CouponCopied            $couponCopied
    ) {}

    public function getConfig(): array
    {
        $ruleId  = $this->config->getCouponRuleId();
        $enabled = $this->config->isEnabled()
            && $ruleId
            && $this->couponValidator->isValid($ruleId)
            && !$this->customerHasAlreadyCopied($ruleId);

        $couponCode = $enabled ? $this->resolveCouponCode($ruleId) : null;

        // If the code could not be resolved, disable the popup.
        if ($enabled && $couponCode === null) {
            $enabled = false;
        }

        return [
            'exitIntentPopup' => [
                'enabled'             => $enabled,
                'ruleId'              => $ruleId,   // exposed so JS can scope the localStorage key
                'mobileDelay'         => $this->config->getMobileDelay(),
                'popupFrequency'      => $this->config->getPopupFrequency(),
                'colors'              => [
                    'background' => $this->config->getBackgroundColor(),
                    'font'       => $this->config->getFontColor(),
                    'button'     => $this->config->getButtonColor(),
                    'buttonText' => $this->config->getButtonTextColor(),
                ],
                'couponCode'          => $couponCode,
                'copyButtonText'      => $this->config->getCopyButtonText(),
                'popupHeading'        => $this->config->getPopupHeading(),
                'popupDescription'    => $this->config->getPopupDescription(),
                'discountLabel'       => $this->config->getDiscountLabel(),
                'ctaButtonText'       => $this->config->getCtaButtonText(),
                'secondaryButtonText' => $this->config->getSecondaryButtonText(),
                'isLoggedIn'          => $this->customerSession->isLoggedIn(),
            ],
        ];
    }

    /**
     * Returns true if the logged-in customer has already copied this coupon.
     * Always false for guests (no server-side identity).
     */
    private function customerHasAlreadyCopied(int $ruleId): bool
    {
        if (!$this->customerSession->isLoggedIn()) {
            return false;
        }

        $customerId = (int) $this->customerSession->getCustomerId();

        return $customerId > 0 && $this->couponCopied->hasCopied($customerId, $ruleId);
    }

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
