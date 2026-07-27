<?php

declare(strict_types=1);

namespace Ict\ExitIntentDiscountPopup\Observer;

use Ict\ExitIntentDiscountPopup\Model\EmailSender;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class SendCouponEmail implements ObserverInterface
{
    public function __construct(
        private readonly EmailSender $emailSender
    ) {}

    /**
     * Expected event data:
     *   - customer  \Magento\Customer\Api\Data\CustomerInterface
     *   - coupon_code string
     */
    public function execute(Observer $observer): void
    {
        $customer   = $observer->getData('customer');
        $couponCode = (string) $observer->getData('coupon_code');

        if (!$customer || !$couponCode) {
            return;
        }

        $this->emailSender->sendCouponEmail($customer, $couponCode);
    }
}
