<?php

declare(strict_types=1);

namespace Ict\ExitIntentDiscountPopup\Controller\Popup;

use Ict\ExitIntentDiscountPopup\Model\Config;
use Ict\ExitIntentDiscountPopup\Model\CouponValidator;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\SalesRule\Model\RuleFactory;

class Shown implements HttpPostActionInterface
{
    public function __construct(
        private readonly RequestInterface      $request,
        private readonly JsonFactory           $jsonFactory,
        private readonly CustomerSession       $customerSession,
        private readonly Config                $config,
        private readonly CouponValidator       $couponValidator,
        private readonly RuleFactory           $ruleFactory,
        private readonly EventManager          $eventManager
    ) {}

    public function execute()
    {
        $result = $this->jsonFactory->create();

        if (!$this->customerSession->isLoggedIn()) {
            return $result->setData(['success' => false]);
        }

        $ruleId = $this->config->getCouponRuleId();

        if (!$ruleId || !$this->couponValidator->isValid($ruleId)) {
            return $result->setData(['success' => false]);
        }

        try {
            $rule       = $this->ruleFactory->create()->load($ruleId);
            $couponCode = $rule->getPrimaryCoupon()->getCode();
        } catch (\Exception) {
            return $result->setData(['success' => false]);
        }

        if (!$couponCode) {
            return $result->setData(['success' => false]);
        }

        $this->eventManager->dispatch('ict_exitintent_popup_shown', [
            'customer'    => $this->customerSession->getCustomerData(),
            'coupon_code' => $couponCode,
        ]);

        return $result->setData(['success' => true]);
    }
}
