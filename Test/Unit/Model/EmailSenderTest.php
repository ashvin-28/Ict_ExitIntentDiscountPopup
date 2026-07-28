<?php

declare(strict_types=1);

namespace Ict\ExitIntentDiscountPopup\Test\Unit\Model;

use Ict\ExitIntentDiscountPopup\Model\Config;
use Ict\ExitIntentDiscountPopup\Model\EmailSender;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Mail\TransportInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class EmailSenderTest extends TestCase
{
    private EmailSender $model;

    /** @var TransportBuilder|MockObject */
    private $transportBuilder;

    /** @var StoreManagerInterface|MockObject */
    private $storeManager;

    /** @var Config|MockObject */
    private $config;

    /** @var LoggerInterface|MockObject */
    private $logger;

    /** @var CustomerInterface|MockObject */
    private $customer;

    protected function setUp(): void
    {
        $this->transportBuilder = $this->createMock(TransportBuilder::class);
        $this->storeManager     = $this->createMock(StoreManagerInterface::class);
        $this->config           = $this->createMock(Config::class);
        $this->logger           = $this->createMock(LoggerInterface::class);
        $this->customer         = $this->createMock(CustomerInterface::class);

        $this->customer->method('getFirstname')->willReturn('Jane');
        $this->customer->method('getLastname')->willReturn('Doe');
        $this->customer->method('getEmail')->willReturn('jane@example.com');
        $this->customer->method('getId')->willReturn(99);

        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(1);
        $this->storeManager->method('getStore')->willReturn($store);

        $this->model = new EmailSender(
            transportBuilder: $this->transportBuilder,
            storeManager: $this->storeManager,
            config: $this->config,
            logger: $this->logger
        );
    }

    public function testDoesNothingWhenEmailToLoggedInIsDisabled(): void
    {
        $this->config->method('isEmailToLoggedInEnabled')->willReturn(false);
        $this->transportBuilder->expects($this->never())->method('setTemplateIdentifier');

        $this->model->sendCouponEmail($this->customer, 'SAVE10');
    }

    public function testSendsEmailWhenEnabled(): void
    {
        $this->config->method('isEmailToLoggedInEnabled')->willReturn(true);

        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())->method('sendMessage');

        $this->transportBuilder->method('setTemplateIdentifier')->willReturnSelf();
        $this->transportBuilder->method('setTemplateOptions')->willReturnSelf();
        $this->transportBuilder->method('setTemplateVars')->willReturnSelf();
        $this->transportBuilder->method('setFromByScope')->willReturnSelf();
        $this->transportBuilder->method('addTo')->willReturnSelf();
        $this->transportBuilder->method('getTransport')->willReturn($transport);

        $this->model->sendCouponEmail($this->customer, 'SAVE10');
    }

    public function testLogsAndSwallowsExceptionOnSendFailure(): void
    {
        $this->config->method('isEmailToLoggedInEnabled')->willReturn(true);

        $this->transportBuilder->method('setTemplateIdentifier')->willReturnSelf();
        $this->transportBuilder->method('setTemplateOptions')->willReturnSelf();
        $this->transportBuilder->method('setTemplateVars')->willReturnSelf();
        $this->transportBuilder->method('setFromByScope')->willReturnSelf();
        $this->transportBuilder->method('addTo')->willReturnSelf();
        $this->transportBuilder->method('getTransport')->willThrowException(
            new LocalizedException(__('SMTP unavailable'))
        );

        $this->logger->expects($this->once())->method('error');

        // Must not throw — failures are swallowed and logged.
        $this->model->sendCouponEmail($this->customer, 'SAVE10');
    }
}
