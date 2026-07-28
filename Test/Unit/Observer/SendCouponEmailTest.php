<?php

declare(strict_types=1);

namespace Ict\ExitIntentDiscountPopup\Test\Unit\Observer;

use Ict\ExitIntentDiscountPopup\Model\EmailSender;
use Ict\ExitIntentDiscountPopup\Observer\SendCouponEmail;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Event\Observer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SendCouponEmailTest extends TestCase
{
    /** @var EmailSender|MockObject */
    private $emailSender;

    private SendCouponEmail $observerModel;

    protected function setUp(): void
    {
        $this->emailSender = $this->createMock(EmailSender::class);
        $this->observerModel = new SendCouponEmail(emailSender: $this->emailSender);
    }

    private function mockObserver(?CustomerInterface $customer, ?string $couponCode): Observer
    {
        $observer = $this->createMock(Observer::class);
        $observer->method('getData')->willReturnMap([
            ['customer', null, $customer],
            ['coupon_code', null, $couponCode],
        ]);

        return $observer;
    }

    public function testSendsEmailWhenBothCustomerAndCouponCodePresent(): void
    {
        $customer = $this->createMock(CustomerInterface::class);

        $this->emailSender->expects($this->once())
            ->method('sendCouponEmail')
            ->with($customer, 'SAVE10');

        $this->observerModel->execute($this->mockObserver($customer, 'SAVE10'));
    }

    public function testDoesNothingWhenCustomerIsMissing(): void
    {
        $this->emailSender->expects($this->never())->method('sendCouponEmail');

        $this->observerModel->execute($this->mockObserver(null, 'SAVE10'));
    }

    public function testDoesNothingWhenCouponCodeIsMissing(): void
    {
        $customer = $this->createMock(CustomerInterface::class);

        $this->emailSender->expects($this->never())->method('sendCouponEmail');

        $this->observerModel->execute($this->mockObserver($customer, null));
    }
}
