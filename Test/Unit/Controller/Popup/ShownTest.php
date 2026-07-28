<?php

declare(strict_types=1);

namespace Ict\ExitIntentDiscountPopup\Test\Unit\Controller\Popup;

use Ict\ExitIntentDiscountPopup\Controller\Popup\Shown;
use Ict\ExitIntentDiscountPopup\Model\Config;
use Ict\ExitIntentDiscountPopup\Model\CouponValidator;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\SalesRule\Model\Coupon;
use Magento\SalesRule\Model\Rule;
use Magento\SalesRule\Model\RuleFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ShownTest extends TestCase
{
    private const RULE_ID = 7;

    /** @var RequestInterface|MockObject */
    private $request;

    /** @var JsonFactory|MockObject */
    private $jsonFactory;

    /** @var CustomerSession|MockObject */
    private $customerSession;

    /** @var Config|MockObject */
    private $config;

    /** @var CouponValidator|MockObject */
    private $couponValidator;

    /** @var RuleFactory|MockObject */
    private $ruleFactory;

    /** @var EventManager|MockObject */
    private $eventManager;

    /** @var Json|MockObject */
    private $jsonResult;

    private Shown $controller;

    protected function setUp(): void
    {
        $this->request         = $this->createMock(RequestInterface::class);
        $this->jsonFactory     = $this->createMock(JsonFactory::class);
        $this->customerSession = $this->createMock(CustomerSession::class);
        $this->config           = $this->createMock(Config::class);
        $this->couponValidator = $this->createMock(CouponValidator::class);
        $this->ruleFactory     = $this->createMock(RuleFactory::class);
        $this->eventManager    = $this->createMock(EventManager::class);

        $this->jsonResult = $this->createMock(Json::class);
        $this->jsonResult->method('setData')->willReturnSelf();
        $this->jsonFactory->method('create')->willReturn($this->jsonResult);

        $this->controller = new Shown(
            request: $this->request,
            jsonFactory: $this->jsonFactory,
            customerSession: $this->customerSession,
            config: $this->config,
            couponValidator: $this->couponValidator,
            ruleFactory: $this->ruleFactory,
            eventManager: $this->eventManager
        );
    }

    private function mockResolvedCoupon(?string $code): void
    {
        $coupon = $this->createMock(Coupon::class);
        $coupon->method('getCode')->willReturn($code);

        $rule = $this->createMock(Rule::class);
        $rule->method('load')->willReturnSelf();
        $rule->method('getPrimaryCoupon')->willReturn($coupon);

        $this->ruleFactory->method('create')->willReturn($rule);
    }

    public function testGuestReturnsFailureWithoutDispatchingEvent(): void
    {
        $this->customerSession->method('isLoggedIn')->willReturn(false);
        $this->eventManager->expects($this->never())->method('dispatch');
        $this->jsonResult->expects($this->once())->method('setData')->with(['success' => false]);

        $this->controller->execute();
    }

    public function testNoConfiguredRuleReturnsFailure(): void
    {
        $this->customerSession->method('isLoggedIn')->willReturn(true);
        $this->config->method('getCouponRuleId')->willReturn(0);
        $this->eventManager->expects($this->never())->method('dispatch');
        $this->jsonResult->expects($this->once())->method('setData')->with(['success' => false]);

        $this->controller->execute();
    }

    public function testInvalidCouponReturnsFailure(): void
    {
        $this->customerSession->method('isLoggedIn')->willReturn(true);
        $this->config->method('getCouponRuleId')->willReturn(self::RULE_ID);
        $this->couponValidator->method('isValid')->willReturn(false);
        $this->eventManager->expects($this->never())->method('dispatch');
        $this->jsonResult->expects($this->once())->method('setData')->with(['success' => false]);

        $this->controller->execute();
    }

    public function testCouponResolutionFailureReturnsFailure(): void
    {
        $this->customerSession->method('isLoggedIn')->willReturn(true);
        $this->config->method('getCouponRuleId')->willReturn(self::RULE_ID);
        $this->couponValidator->method('isValid')->willReturn(true);
        $this->ruleFactory->method('create')->willThrowException(new \RuntimeException('load failed'));

        $this->eventManager->expects($this->never())->method('dispatch');
        $this->jsonResult->expects($this->once())->method('setData')->with(['success' => false]);

        $this->controller->execute();
    }

    public function testEmptyResolvedCouponCodeReturnsFailure(): void
    {
        $this->customerSession->method('isLoggedIn')->willReturn(true);
        $this->config->method('getCouponRuleId')->willReturn(self::RULE_ID);
        $this->couponValidator->method('isValid')->willReturn(true);
        $this->mockResolvedCoupon(null);

        $this->eventManager->expects($this->never())->method('dispatch');
        $this->jsonResult->expects($this->once())->method('setData')->with(['success' => false]);

        $this->controller->execute();
    }

    public function testHappyPathDispatchesEventAndReturnsSuccess(): void
    {
        $this->customerSession->method('isLoggedIn')->willReturn(true);
        $this->config->method('getCouponRuleId')->willReturn(self::RULE_ID);
        $this->couponValidator->method('isValid')->willReturn(true);
        $this->mockResolvedCoupon('SAVE10');

        $customerData = new \stdClass();
        $this->customerSession->method('getCustomerData')->willReturn($customerData);

        $this->eventManager->expects($this->once())
            ->method('dispatch')
            ->with('ict_exitintent_popup_shown', ['customer' => $customerData, 'coupon_code' => 'SAVE10']);

        $this->jsonResult->expects($this->once())->method('setData')->with(['success' => true]);

        $this->controller->execute();
    }
}
