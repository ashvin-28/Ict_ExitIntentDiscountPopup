<?php

declare(strict_types=1);

namespace Ict\ExitIntentDiscountPopup\Test\Unit\Observer;

use Ict\ExitIntentDiscountPopup\Model\Config;
use Ict\ExitIntentDiscountPopup\Model\ResourceModel\GuestCouponUsage;
use Ict\ExitIntentDiscountPopup\Observer\StripReusedCouponOnTotals;
use Magento\Framework\Event\Observer;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\SalesRule\Model\Coupon;
use Magento\SalesRule\Model\CouponFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class StripReusedCouponOnTotalsTest extends TestCase
{
    private const RULE_ID = 7;

    /** @var Config|MockObject */
    private $config;

    /** @var GuestCouponUsage|MockObject */
    private $guestCouponUsage;

    /** @var CouponFactory|MockObject */
    private $couponFactory;

    /** @var LoggerInterface|MockObject */
    private $logger;

    private StripReusedCouponOnTotals $observerModel;

    protected function setUp(): void
    {
        $this->config           = $this->createMock(Config::class);
        $this->guestCouponUsage = $this->createMock(GuestCouponUsage::class);
        $this->couponFactory    = $this->createMock(CouponFactory::class);
        $this->logger           = $this->createMock(LoggerInterface::class);

        $this->observerModel = new StripReusedCouponOnTotals(
            config: $this->config,
            guestCouponUsage: $this->guestCouponUsage,
            couponFactory: $this->couponFactory,
            logger: $this->logger
        );
    }

    private function mockQuote(?int $customerId, string $couponCode, string $email): Quote
    {
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->addMethods(['getCustomerId', 'getCouponCode', 'getCustomerEmail', 'setCouponCode'])
            ->onlyMethods(['getBillingAddress'])
            ->getMock();
        $quote->method('getCustomerId')->willReturn($customerId);
        $quote->method('getCouponCode')->willReturn($couponCode);
        $quote->method('getCustomerEmail')->willReturn($email);

        return $quote;
    }

    private function mockObserverWithQuote($quote): Observer
    {
        $observer = $this->createMock(Observer::class);
        $observer->method('getData')->with('quote')->willReturn($quote);

        return $observer;
    }

    private function mockCouponFactoryReturning(Coupon $coupon): void
    {
        $factoryReturn = $this->createMock(Coupon::class);
        $factoryReturn->method('loadByCode')->willReturn($coupon);
        $this->couponFactory->method('create')->willReturn($factoryReturn);
    }

    public function testNonQuoteEventDataIsIgnored(): void
    {
        $this->guestCouponUsage->expects($this->never())->method('hasUsed');

        $this->observerModel->execute($this->mockObserverWithQuote(new \stdClass()));
    }

    public function testLoggedInQuotesAreNeverTouched(): void
    {
        $quote = $this->mockQuote(42, 'SAVE10', 'shopper@example.com');

        $this->guestCouponUsage->expects($this->never())->method('hasUsed');
        $quote->expects($this->never())->method('setCouponCode');

        $this->observerModel->execute($this->mockObserverWithQuote($quote));
    }

    public function testNoAppliedCouponIsIgnored(): void
    {
        $quote = $this->mockQuote(null, '', 'shopper@example.com');

        $this->guestCouponUsage->expects($this->never())->method('hasUsed');

        $this->observerModel->execute($this->mockObserverWithQuote($quote));
    }

    public function testAppliedCouponFromADifferentRuleIsLeftAlone(): void
    {
        $this->config->method('getCouponRuleId')->willReturn(self::RULE_ID);

        $otherRuleCoupon = $this->createMock(Coupon::class);
        $otherRuleCoupon->method('getId')->willReturn(1);
        $otherRuleCoupon->method('getRuleId')->willReturn(999);
        $this->mockCouponFactoryReturning($otherRuleCoupon);

        $quote = $this->mockQuote(null, 'UNRELATED', 'shopper@example.com');

        $this->guestCouponUsage->expects($this->never())->method('hasUsed');
        $quote->expects($this->never())->method('setCouponCode');

        $this->observerModel->execute($this->mockObserverWithQuote($quote));
    }

    public function testNotYetRedeemedCouponIsLeftInPlace(): void
    {
        $this->config->method('getCouponRuleId')->willReturn(self::RULE_ID);

        $ourCoupon = $this->createMock(Coupon::class);
        $ourCoupon->method('getId')->willReturn(1);
        $ourCoupon->method('getRuleId')->willReturn(self::RULE_ID);
        $this->mockCouponFactoryReturning($ourCoupon);

        $this->guestCouponUsage->method('hasUsed')->willReturn(false);

        $quote = $this->mockQuote(null, 'SAVE10', 'shopper@example.com');
        $quote->expects($this->never())->method('setCouponCode');

        $this->observerModel->execute($this->mockObserverWithQuote($quote));
    }

    public function testAlreadyRedeemedCouponIsStrippedUsingQuoteLevelEmail(): void
    {
        $this->config->method('getCouponRuleId')->willReturn(self::RULE_ID);

        $ourCoupon = $this->createMock(Coupon::class);
        $ourCoupon->method('getId')->willReturn(1);
        $ourCoupon->method('getRuleId')->willReturn(self::RULE_ID);
        $this->mockCouponFactoryReturning($ourCoupon);

        $this->guestCouponUsage->method('hasUsed')->with('used@example.com', self::RULE_ID)->willReturn(true);

        $quote = $this->mockQuote(null, 'SAVE10', 'used@example.com');
        $quote->expects($this->once())->method('setCouponCode')->with('');

        $this->observerModel->execute($this->mockObserverWithQuote($quote));
    }

    public function testFallsBackToBillingAddressEmailWhenQuoteEmailIsMissing(): void
    {
        $this->config->method('getCouponRuleId')->willReturn(self::RULE_ID);

        $ourCoupon = $this->createMock(Coupon::class);
        $ourCoupon->method('getId')->willReturn(1);
        $ourCoupon->method('getRuleId')->willReturn(self::RULE_ID);
        $this->mockCouponFactoryReturning($ourCoupon);

        $this->guestCouponUsage->method('hasUsed')->with('billing@example.com', self::RULE_ID)->willReturn(true);

        $quote = $this->mockQuote(null, 'SAVE10', '');

        $billingAddress = $this->createMock(Address::class);
        $billingAddress->method('getEmail')->willReturn('billing@example.com');
        $quote->method('getBillingAddress')->willReturn($billingAddress);

        $quote->expects($this->once())->method('setCouponCode')->with('');

        $this->observerModel->execute($this->mockObserverWithQuote($quote));
    }

    public function testNeverFatalsOnUnexpectedError(): void
    {
        $this->config->method('getCouponRuleId')->willThrowException(new \RuntimeException('boom'));

        $quote = $this->mockQuote(null, 'SAVE10', 'shopper@example.com');

        $this->logger->expects($this->once())->method('error');

        $this->observerModel->execute($this->mockObserverWithQuote($quote));
    }
}
