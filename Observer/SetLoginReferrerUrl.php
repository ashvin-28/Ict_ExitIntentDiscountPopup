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
 * Stashes the popup's own referer query param, once validated, into a
 * dedicated customer session key ahead of the login/registration form
 * rendering.
 *
 * The value is deliberately NOT written to the session's "before auth" URL
 * here. Magento\Customer\Model\Account\Redirect::getRedirect() is called by
 * both LoginPost and CreatePost regardless of whether authentication
 * succeeded or failed, and once "before auth url" holds a non-default URL
 * it is used as the post-request redirect target even on a failed attempt
 * — sending a guest who mistyped their password straight to checkout
 * instead of back to the login form with the usual error. Committing the
 * stashed value to "before auth url" is deferred to ApplyLoginReferrerUrl,
 * which only runs on the "customer_login" event — dispatched solely on a
 * successful login or registration.
 *
 * Registered on both controller_action_predispatch_customer_account_login
 * and controller_action_predispatch_customer_account_create.
 */
class SetLoginReferrerUrl implements ObserverInterface
{
    /**
     * Customer session key the validated referer URL is stashed under,
     * pending a successful login or registration.
     */
    public const SESSION_KEY = 'ict_exitintent_pending_referer_url';

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
     * Stash the referer query param, if present and safe, pending a successful auth.
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
            $this->customerSession->setData(self::SESSION_KEY, $decoded);
        }
    }
}
