<?php

declare(strict_types=1);

namespace Ict\ExitIntentDiscountPopup\Test\Unit\Observer;

use Ict\ExitIntentDiscountPopup\Model\Config;
use Ict\ExitIntentDiscountPopup\Model\ResourceModel\GuestCouponUsage;
use Ict\ExitIntentDiscountPopup\Observer\RecordGuestCouponUsage;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;
use Magento\SalesRule\Model\Coupon;
use Magento\SalesRule\Model\Rule;
use Magento\SalesRule\Model\RuleFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class RecordGuestCouponUsageTest extends TestCase
{
    private const RULE_ID = 7;

    /** @var Config|MockObject */
    private $config;

    /** @var GuestCouponUsage|MockObject */
    private $guestCouponUsage;

    /** @var RuleFactory|MockObject */
    private $ruleFactory;

    /** @var LoggerInterface|MockObject */
    private $logger;

    /** @var RecordGuestCouponUsage */
    private RecordGuestCouponUsage $observerModel;

    protected function setUp(): void
    {
        $this->config           = $this->createMock(Config::class);
        $this->guestCouponUsage = $this->createMock(GuestCouponUsage::class);
        $this->ruleFactory      = $this->createMock(RuleFactory::class);
        $this->logger           = $this->createMock(LoggerInterface::class);

        $this->observerModel = new RecordGuestCouponUsage(
            config: $this->config,
            guestCouponUsage: $this->guestCouponUsage,
            ruleFactory: $this->ruleFactory,
            logger: $this->logger
        );
    }

    private function mockOrder(?int $customerId, string $couponCode, string $email): Order
    {
        $order = $this->createMock(Order::class);
        $order->method('getCustomerId')->willReturn($customerId);
        $order->method('getCouponCode')->willReturn($couponCode);
        $order->method('getCustomerEmail')->willReturn($email);
        $order->method('getEntityId')->willReturn(555);
        $order->method('getIncrementId')->willReturn('100000555');

        return $order;
    }

    private function mockObserverWithOrder($order): Observer
    {
        $observer = $this->createMock(Observer::class);
        $observer->method('getData')->with('order')->willReturn($order);

        return $observer;
    }

    private function mockConfiguredRuleCoupon(string $code): void
    {
        $coupon = $this->createMock(Coupon::class);
        $coupon->method('getCode')->willReturn($code);

        $rule = $this->createMock(Rule::class);
        $rule->method('load')->willReturnSelf();
        $rule->method('getPrimaryCoupon')->willReturn($coupon);

        $this->ruleFactory->method('create')->willReturn($rule);
    }

    public function testLoggedInOrdersAreNeverRecorded(): void
    {
        $this->config->method('getCouponRuleId')->willReturn(self::RULE_ID);
        $order = $this->mockOrder(42, 'SAVE10', 'shopper@example.com');

        $this->guestCouponUsage->expects($this->never())->method('record');

        $this->observerModel->execute($this->mockObserverWithOrder($order));
    }

    public function testGuestOrderWithNoCouponCodeIsIgnored(): void
    {
        $this->config->method('getCouponRuleId')->willReturn(self::RULE_ID);
        $order = $this->mockOrder(null, '', 'shopper@example.com');

        $this->guestCouponUsage->expects($this->never())->method('record');

        $this->observerModel->execute($this->mockObserverWithOrder($order));
    }

    public function testGuestOrderIgnoredWhenNoRuleConfigured(): void
    {
        $this->config->method('getCouponRuleId')->willReturn(0);
        $order = $this->mockOrder(null, 'SAVE10', 'shopper@example.com');

        $this->guestCouponUsage->expects($this->never())->method('record');

        $this->observerModel->execute($this->mockObserverWithOrder($order));
    }

    public function testGuestOrderWithDifferentCouponIsIgnored(): void
    {
        $this->config->method('getCouponRuleId')->willReturn(self::RULE_ID);
        $this->mockConfiguredRuleCoupon('OTHERCODE');
        $order = $this->mockOrder(null, 'SAVE10', 'shopper@example.com');

        $this->guestCouponUsage->expects($this->never())->method('record');

        $this->observerModel->execute($this->mockObserverWithOrder($order));
    }

    public function testGuestOrderWithMatchingCouponButNoEmailIsIgnored(): void
    {
        $this->config->method('getCouponRuleId')->willReturn(self::RULE_ID);
        $this->mockConfiguredRuleCoupon('SAVE10');
        $order = $this->mockOrder(null, 'SAVE10', '');

        $this->guestCouponUsage->expects($this->never())->method('record');

        $this->observerModel->execute($this->mockObserverWithOrder($order));
    }

    public function testGuestOrderWithMatchingCouponIsRecorded(): void
    {
        $this->config->method('getCouponRuleId')->willReturn(self::RULE_ID);
        $this->mockConfiguredRuleCoupon('SAVE10');
        $order = $this->mockOrder(null, 'save10', 'shopper@example.com'); // case-insensitive match

        $this->guestCouponUsage->expects($this->once())
            ->method('record')
            ->with('shopper@example.com', self::RULE_ID, 555);

        $this->observerModel->execute($this->mockObserverWithOrder($order));
    }

    public function testNeverFatalsWhenObserverHasNoOrderData(): void
    {
        $observer = $this->createMock(Observer::class);
        $observer->method('getData')->with('order')->willReturn(null);

        $this->logger->expects($this->once())->method('error');

        // Must not throw, even though $order is null.
        $this->observerModel->execute($observer);
    }
}
