<?php

declare(strict_types=1);

namespace Ict\ExitIntentDiscountPopup\Test\Unit\Controller\Index;

use Ict\ExitIntentDiscountPopup\Controller\Index\CheckGuestUsage;
use Ict\ExitIntentDiscountPopup\Model\Config;
use Ict\ExitIntentDiscountPopup\Model\ResourceModel\GuestCouponUsage;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CheckGuestUsageTest extends TestCase
{
    private const RULE_ID = 7;

    /** @var HttpRequest|MockObject */
    private $request;

    /** @var JsonFactory|MockObject */
    private $jsonFactory;

    /** @var CustomerSession|MockObject */
    private $customerSession;

    /** @var Config|MockObject */
    private $config;

    /** @var GuestCouponUsage|MockObject */
    private $guestCouponUsage;

    /** @var LoggerInterface|MockObject */
    private $logger;

    /** @var Json|MockObject */
    private $jsonResult;

    private CheckGuestUsage $controller;

    protected function setUp(): void
    {
        $this->request         = $this->createMock(HttpRequest::class);
        $this->jsonFactory     = $this->createMock(JsonFactory::class);
        $this->customerSession = $this->createMock(CustomerSession::class);
        $this->config           = $this->createMock(Config::class);
        $this->guestCouponUsage = $this->createMock(GuestCouponUsage::class);
        $this->logger          = $this->createMock(LoggerInterface::class);

        $this->jsonResult = $this->createMock(Json::class);
        $this->jsonResult->method('setData')->willReturnSelf();
        $this->jsonFactory->method('create')->willReturn($this->jsonResult);

        $this->controller = new CheckGuestUsage(
            request: $this->request,
            jsonFactory: $this->jsonFactory,
            customerSession: $this->customerSession,
            config: $this->config,
            guestCouponUsage: $this->guestCouponUsage,
            logger: $this->logger
        );
    }

    public function testLoggedInCustomersAlwaysGetUsedFalse(): void
    {
        $this->customerSession->method('isLoggedIn')->willReturn(true);
        $this->guestCouponUsage->expects($this->never())->method('hasUsed');
        $this->jsonResult->expects($this->once())->method('setData')->with(['used' => false]);

        $this->controller->execute();
    }

    public function testEmptyEmailReturnsUsedFalse(): void
    {
        $this->customerSession->method('isLoggedIn')->willReturn(false);
        $this->request->method('getContent')->willReturn(json_encode(['email' => '  ']));
        $this->guestCouponUsage->expects($this->never())->method('hasUsed');
        $this->jsonResult->expects($this->once())->method('setData')->with(['used' => false]);

        $this->controller->execute();
    }

    public function testNoConfiguredRuleReturnsUsedFalse(): void
    {
        $this->customerSession->method('isLoggedIn')->willReturn(false);
        $this->request->method('getContent')->willReturn(json_encode(['email' => 'shopper@example.com']));
        $this->config->method('getCouponRuleId')->willReturn(0);
        $this->guestCouponUsage->expects($this->never())->method('hasUsed');
        $this->jsonResult->expects($this->once())->method('setData')->with(['used' => false]);

        $this->controller->execute();
    }

    public function testNormalizesEmailAndDelegatesToGuestCouponUsage(): void
    {
        $this->customerSession->method('isLoggedIn')->willReturn(false);
        $this->request->method('getContent')->willReturn(json_encode(['email' => '  Shopper@Example.com  ']));
        $this->config->method('getCouponRuleId')->willReturn(self::RULE_ID);
        $this->guestCouponUsage->method('hasUsed')
            ->with('shopper@example.com', self::RULE_ID)
            ->willReturn(true);

        $this->jsonResult->expects($this->once())->method('setData')->with(['used' => true]);

        $this->controller->execute();
    }

    public function testMalformedJsonBodyResolvesToEmptyEmailAndReturnsUsedFalse(): void
    {
        $this->customerSession->method('isLoggedIn')->willReturn(false);
        $this->request->method('getContent')->willReturn('{not-json');
        $this->guestCouponUsage->expects($this->never())->method('hasUsed');
        $this->jsonResult->expects($this->once())->method('setData')->with(['used' => false]);

        $this->controller->execute();
    }

    public function testUnexpectedExceptionFailsOpenAndLogsError(): void
    {
        $this->customerSession->method('isLoggedIn')->willReturn(false);
        $this->request->method('getContent')->willReturn(json_encode(['email' => 'shopper@example.com']));
        $this->config->method('getCouponRuleId')->willReturn(self::RULE_ID);
        $this->guestCouponUsage->method('hasUsed')->willThrowException(new \RuntimeException('DB down'));

        $this->logger->expects($this->once())->method('error');
        $this->jsonResult->expects($this->once())->method('setData')->with(['used' => false]);

        $this->controller->execute();
    }
}
