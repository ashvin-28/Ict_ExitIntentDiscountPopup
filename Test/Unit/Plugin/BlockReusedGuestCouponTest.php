<?php

declare(strict_types=1);

namespace Ict\ExitIntentDiscountPopup\Test\Unit\Plugin;

use Ict\ExitIntentDiscountPopup\Model\Config;
use Ict\ExitIntentDiscountPopup\Model\ResourceModel\GuestCouponUsage;
use Ict\ExitIntentDiscountPopup\Plugin\BlockReusedGuestCoupon;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\CouponManagementInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\SalesRule\Model\Coupon;
use Magento\SalesRule\Model\CouponFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class BlockReusedGuestCouponTest extends TestCase
{
    private const RULE_ID = 7;
    private const CART_ID = 123;

    /** @var CartRepositoryInterface|MockObject */
    private $cartRepository;

    /** @var CouponFactory|MockObject */
    private $couponFactory;

    /** @var Config|MockObject */
    private $config;

    /** @var GuestCouponUsage|MockObject */
    private $guestCouponUsage;

    /** @var LoggerInterface|MockObject */
    private $logger;

    /** @var CouponManagementInterface|MockObject */
    private $subject;

    /** @var BlockReusedGuestCoupon */
    private BlockReusedGuestCoupon $plugin;

    /** @var bool */
    private bool $proceedWasCalled;

    protected function setUp(): void
    {
        $this->cartRepository  = $this->createMock(CartRepositoryInterface::class);
        $this->couponFactory   = $this->createMock(CouponFactory::class);
        $this->config          = $this->createMock(Config::class);
        $this->guestCouponUsage = $this->createMock(GuestCouponUsage::class);
        $this->logger          = $this->createMock(LoggerInterface::class);
        $this->subject         = $this->createMock(CouponManagementInterface::class);
        $this->proceedWasCalled = false;

        $this->plugin = new BlockReusedGuestCoupon(
            cartRepository: $this->cartRepository,
            couponFactory: $this->couponFactory,
            config: $this->config,
            guestCouponUsage: $this->guestCouponUsage,
            logger: $this->logger
        );
    }

    private function proceed(): callable
    {
        return function () {
            $this->proceedWasCalled = true;
            return true;
        };
    }

    private function mockQuote(?int $customerId, string $email): Quote
    {
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->addMethods(['getCustomerId', 'getCustomerEmail'])
            ->onlyMethods(['getBillingAddress'])
            ->getMock();
        $quote->method('getCustomerId')->willReturn($customerId);
        $quote->method('getCustomerEmail')->willReturn($email);

        return $quote;
    }

    private function mockOurCoupon(): Coupon
    {
        $coupon = $this->createMock(Coupon::class);
        $coupon->method('loadByCode')->willReturnSelf();
        $coupon->method('getId')->willReturn(1);
        $coupon->method('getRuleId')->willReturn(self::RULE_ID);

        return $coupon;
    }

    public function testLoggedInCustomerIsNeverBlocked(): void
    {
        $quote = $this->mockQuote(42, 'shopper@example.com');
        $this->cartRepository->method('getActive')->willReturn($quote);
        $this->guestCouponUsage->expects($this->never())->method('hasUsed');

        $result = $this->plugin->aroundSet($this->subject, $this->proceed(), self::CART_ID, 'SAVE10');

        $this->assertTrue($result);
        $this->assertTrue($this->proceedWasCalled);
    }

    public function testNoConfiguredRuleFailsOpen(): void
    {
        $quote = $this->mockQuote(null, 'shopper@example.com');
        $this->cartRepository->method('getActive')->willReturn($quote);
        $this->config->method('getCouponRuleId')->willReturn(0);

        $this->plugin->aroundSet($this->subject, $this->proceed(), self::CART_ID, 'SAVE10');

        $this->assertTrue($this->proceedWasCalled);
    }

    public function testCouponFromADifferentRuleFailsOpen(): void
    {
        $quote = $this->mockQuote(null, 'shopper@example.com');
        $this->cartRepository->method('getActive')->willReturn($quote);
        $this->config->method('getCouponRuleId')->willReturn(self::RULE_ID);

        $otherCoupon = $this->createMock(Coupon::class);
        $otherCoupon->method('loadByCode')->willReturnSelf();
        $otherCoupon->method('getId')->willReturn(1);
        $otherCoupon->method('getRuleId')->willReturn(999);
        $this->couponFactory->method('create')->willReturn($otherCoupon);

        $this->plugin->aroundSet($this->subject, $this->proceed(), self::CART_ID, 'UNRELATED');

        $this->assertTrue($this->proceedWasCalled);
    }

    public function testNoEmailAvailableYetFailsOpen(): void
    {
        $quote = $this->mockQuote(null, '');
        $billing = $this->createMock(Address::class);
        $billing->method('getEmail')->willReturn('');
        $quote->method('getBillingAddress')->willReturn($billing);

        $this->cartRepository->method('getActive')->willReturn($quote);
        $this->config->method('getCouponRuleId')->willReturn(self::RULE_ID);
        $this->couponFactory->method('create')->willReturn($this->mockOurCoupon());

        $this->plugin->aroundSet($this->subject, $this->proceed(), self::CART_ID, 'SAVE10');

        $this->assertTrue($this->proceedWasCalled);
    }

    public function testNotYetRedeemedCouponIsAllowedThrough(): void
    {
        $quote = $this->mockQuote(null, 'shopper@example.com');
        $this->cartRepository->method('getActive')->willReturn($quote);
        $this->config->method('getCouponRuleId')->willReturn(self::RULE_ID);
        $this->couponFactory->method('create')->willReturn($this->mockOurCoupon());
        $this->guestCouponUsage->method('hasUsed')->willReturn(false);

        $this->plugin->aroundSet($this->subject, $this->proceed(), self::CART_ID, 'SAVE10');

        $this->assertTrue($this->proceedWasCalled);
    }

    public function testAlreadyRedeemedCouponIsBlockedWithoutCallingProceed(): void
    {
        $quote = $this->mockQuote(null, 'used@example.com');
        $this->cartRepository->method('getActive')->willReturn($quote);
        $this->config->method('getCouponRuleId')->willReturn(self::RULE_ID);
        $this->couponFactory->method('create')->willReturn($this->mockOurCoupon());
        $this->guestCouponUsage->method('hasUsed')->with('used@example.com', self::RULE_ID)->willReturn(true);

        $this->expectException(CouldNotSaveException::class);

        try {
            $this->plugin->aroundSet($this->subject, $this->proceed(), self::CART_ID, 'SAVE10');
        } finally {
            $this->assertFalse($this->proceedWasCalled);
        }
    }

    public function testUnexpectedErrorFailsOpenAndIsLogged(): void
    {
        $this->cartRepository->method('getActive')->willThrowException(new \RuntimeException('DB down'));
        $this->logger->expects($this->once())->method('error');

        $this->plugin->aroundSet($this->subject, $this->proceed(), self::CART_ID, 'SAVE10');

        $this->assertTrue($this->proceedWasCalled);
    }
}
