<?php

declare(strict_types=1);

namespace Ict\ExitIntentDiscountPopup\Controller\Index;

use Ict\ExitIntentDiscountPopup\Model\Config;
use Ict\ExitIntentDiscountPopup\Model\ResourceModel\GuestCouponUsage;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Psr\Log\LoggerInterface;

class CheckGuestUsage implements HttpPostActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory      $jsonFactory,
        private readonly CustomerSession  $customerSession,
        private readonly Config           $config,
        private readonly GuestCouponUsage $guestCouponUsage,
        private readonly LoggerInterface  $logger
    ) {}

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();

        // Logged-in customers are handled via native salesrule_coupon_usage — not this endpoint.
        if ($this->customerSession->isLoggedIn()) {
            return $result->setData(['used' => false]);
        }

        try {
            $body  = (string) $this->request->getContent();
            $data  = json_decode($body, true);
            $email = strtolower(trim((string) ($data['email'] ?? '')));

            if ($email === '') {
                return $result->setData(['used' => false]);
            }

            $ruleId = $this->config->getCouponRuleId();
            if (!$ruleId) {
                return $result->setData(['used' => false]);
            }

            $used = $this->guestCouponUsage->hasUsed($email, $ruleId);

            return $result->setData(['used' => $used]);
        } catch (\Throwable $e) {
            $this->logger->error(
                'ExitIntentDiscountPopup: CheckGuestUsage error — ' . $e->getMessage()
            );
            return $result->setData(['used' => false]);
        }
    }
}
