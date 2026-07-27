<?php

declare(strict_types=1);

namespace Ict\ExitIntentDiscountPopup\Observer;

use Ict\ExitIntentDiscountPopup\Model\Config;
use Ict\ExitIntentDiscountPopup\Model\ResourceModel\GuestCouponUsage;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\SalesRule\Model\RuleFactory;
use Psr\Log\LoggerInterface;

class RecordGuestCouponUsage implements ObserverInterface
{
    public function __construct(
        private readonly Config            $config,
        private readonly GuestCouponUsage  $guestCouponUsage,
        private readonly RuleFactory       $ruleFactory,
        private readonly LoggerInterface   $logger
    ) {}

    /**
     * Event: sales_order_place_after
     * Data:  order \Magento\Sales\Model\Order
     */
    public function execute(Observer $observer): void
    {
        try {
            /** @var \Magento\Sales\Model\Order $order */
            $order = $observer->getData('order');

            // Only process guest orders — logged-in orders are tracked via
            // the native salesrule_coupon_usage table.
            if ($order->getCustomerId()) {
                return;
            }

            $orderCoupon = (string) $order->getCouponCode();
            if ($orderCoupon === '') {
                return;
            }

            $ruleId = $this->config->getCouponRuleId();
            if (!$ruleId) {
                return;
            }

            // Resolve the configured rule's primary coupon code and compare.
            $configuredCode = $this->resolveCouponCode($ruleId);
            if ($configuredCode === null ||
                strtolower($orderCoupon) !== strtolower($configuredCode)) {
                return;
            }

            $email = (string) $order->getCustomerEmail();
            if ($email === '') {
                return;
            }

            $this->guestCouponUsage->record(
                $email,
                $ruleId,
                (int) $order->getEntityId()
            );
        } catch (\Exception $e) {
            // Never break order placement on observer failure.
            $this->logger->error(
                'ExitIntentDiscountPopup: failed to record guest coupon usage — ' . $e->getMessage(),
                ['order_id' => $order->getIncrementId() ?? 'unknown']
            );
        }
    }

    private function resolveCouponCode(int $ruleId): ?string
    {
        try {
            $rule = $this->ruleFactory->create()->load($ruleId);
            $code = $rule->getPrimaryCoupon()->getCode();
            return $code ?: null;
        } catch (\Exception) {
            return null;
        }
    }
}
