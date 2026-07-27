<?php

declare(strict_types=1);

namespace Ict\ExitIntentDiscountPopup\Controller\Index;

use Ict\ExitIntentDiscountPopup\Model\Config;
use Ict\ExitIntentDiscountPopup\Model\ResourceModel\CouponCopied;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;

class MarkUsed implements HttpPostActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory      $jsonFactory,
        private readonly CustomerSession  $customerSession,
        private readonly Config           $config,
        private readonly CouponCopied     $couponCopied
    ) {}

    public function execute()
    {
        $result = $this->jsonFactory->create();

        if (!$this->customerSession->isLoggedIn()) {
            // Guests are handled client-side via localStorage — nothing to do.
            return $result->setData(['success' => false, 'reason' => 'guest']);
        }

        $customerId = (int) $this->customerSession->getCustomerId();
        $ruleId     = $this->config->getCouponRuleId();

        if (!$customerId || !$ruleId) {
            return $result->setData(['success' => false, 'reason' => 'invalid']);
        }

        $this->couponCopied->markCopied($customerId, $ruleId);

        return $result->setData(['success' => true]);
    }

    // -------------------------------------------------------------------------
    // CsrfAwareActionInterface — validate form_key manually so we can return
    // a clean JSON error instead of a redirect on CSRF failure.
    // -------------------------------------------------------------------------

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null; // handled in validateForCsrf
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        // Magento validates form_key automatically for POST when this returns null.
        return null;
    }
}
