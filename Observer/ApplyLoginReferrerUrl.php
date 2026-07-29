<?php

declare(strict_types=1);

namespace Ict\ExitIntentDiscountPopup\Observer;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Commits the referer URL stashed by SetLoginReferrerUrl as the customer
 * session's "before auth" URL, once authentication has actually succeeded.
 *
 * Registered on the "customer_login" event, which Magento dispatches only
 * after a successful login or registration-with-auto-login — never on a
 * failed attempt — so a wrong password never redirects the guest to
 * checkout instead of showing the usual sign-in error.
 */
class ApplyLoginReferrerUrl implements ObserverInterface
{
    /**
     * @param CustomerSession $customerSession
     */
    public function __construct(
        private readonly CustomerSession $customerSession
    ) {
    }

    /**
     * Promote the stashed referer URL to the session's before-auth URL.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $url = $this->customerSession->getData(SetLoginReferrerUrl::SESSION_KEY);

        if (!$url) {
            return;
        }

        $this->customerSession->unsetData(SetLoginReferrerUrl::SESSION_KEY);
        $this->customerSession->setBeforeAuthUrl($url);
    }
}
