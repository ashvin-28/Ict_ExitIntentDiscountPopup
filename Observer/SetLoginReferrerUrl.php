<?php

declare(strict_types=1);

namespace Ict\ExitIntentDiscountPopup\Observer;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Customer\Model\Url as CustomerUrl;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Url\DecoderInterface;
use Magento\Framework\Url\HostChecker;

/**
 * Sets the customer session's "before auth" URL from the popup's own referer
 * query param before the login/registration form renders, so a successful
 * login or registration redirects the guest back to checkout instead of the
 * default My Account dashboard.
 *
 * Registered on both controller_action_predispatch_customer_account_login
 * and controller_action_predispatch_customer_account_create.
 */
class SetLoginReferrerUrl implements ObserverInterface
{
    /**
     * @param CustomerSession $customerSession
     * @param DecoderInterface $urlDecoder
     * @param HostChecker $hostChecker
     */
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly DecoderInterface $urlDecoder,
        private readonly HostChecker $hostChecker
    ) {
    }

    /**
     * Apply the referer query param, if present and safe, as the before-auth URL.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $request = $observer->getEvent()->getRequest();
        $referer = $request->getParam(CustomerUrl::REFERER_QUERY_PARAM_NAME);

        if (!$referer) {
            return;
        }

        $decoded = $this->urlDecoder->decode($referer);

        // Guard against an open redirect via a forged referer param.
        if ($decoded && $this->hostChecker->isOwnOrigin($decoded)) {
            $this->customerSession->setBeforeAuthUrl($decoded);
        }
    }
}
