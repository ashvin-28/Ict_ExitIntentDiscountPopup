<?php

declare(strict_types=1);

namespace Ict\ExitIntentDiscountPopup\Model;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class EmailSender
{
    private const TEMPLATE_ID = 'exit_intent_popup_coupon_email';

    public function __construct(
        private readonly TransportBuilder     $transportBuilder,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config               $config,
        private readonly LoggerInterface      $logger
    ) {}

    public function sendCouponEmail(CustomerInterface $customer, string $couponCode): void
    {
        if (!$this->config->isEmailToLoggedInEnabled()) {
            return;
        }

        try {
            $store = $this->storeManager->getStore();

            $transport = $this->transportBuilder
                ->setTemplateIdentifier(self::TEMPLATE_ID)
                ->setTemplateOptions([
                    'area'     => \Magento\Framework\App\Area::AREA_FRONTEND,
                    'store'    => $store->getId(),
                ])
                ->setTemplateVars([
                    'customer_name' => $customer->getFirstname() . ' ' . $customer->getLastname(),
                    'coupon_code'   => $couponCode,
                    'store'         => $store,
                ])
                ->setFromByScope('general', $store->getId())
                ->addTo($customer->getEmail(), $customer->getFirstname())
                ->getTransport();

            $transport->sendMessage();
        } catch (LocalizedException|\Exception $e) {
            $this->logger->error(
                'ExitIntentDiscountPopup: failed to send coupon email — ' . $e->getMessage(),
                ['customer_id' => $customer->getId()]
            );
        }
    }
}
