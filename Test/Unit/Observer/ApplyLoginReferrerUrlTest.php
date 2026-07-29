<?php

declare(strict_types=1);

namespace Ict\ExitIntentDiscountPopup\Test\Unit\Observer;

use Ict\ExitIntentDiscountPopup\Observer\ApplyLoginReferrerUrl;
use Ict\ExitIntentDiscountPopup\Observer\SetLoginReferrerUrl;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Event\Observer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ApplyLoginReferrerUrlTest extends TestCase
{
    /** @var CustomerSession|MockObject */
    private $customerSession;

    /** @var ApplyLoginReferrerUrl */
    private ApplyLoginReferrerUrl $observerModel;

    protected function setUp(): void
    {
        // setData()/unsetData() are magic (__call) methods on Session, not
        // declared ones — addMethods() is required to mock them.
        $this->customerSession = $this->getMockBuilder(CustomerSession::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getData', 'setBeforeAuthUrl'])
            ->addMethods(['unsetData'])
            ->getMock();

        $this->observerModel = new ApplyLoginReferrerUrl(customerSession: $this->customerSession);
    }

    public function testDoesNothingWhenNoRefererWasStashed(): void
    {
        $this->customerSession->method('getData')
            ->with(SetLoginReferrerUrl::SESSION_KEY)
            ->willReturn(null);

        $this->customerSession->expects($this->never())->method('setBeforeAuthUrl');
        $this->customerSession->expects($this->never())->method('unsetData');

        $this->observerModel->execute($this->createMock(Observer::class));
    }

    public function testPromotesTheStashedRefererToBeforeAuthUrlOnSuccessfulLogin(): void
    {
        $this->customerSession->method('getData')
            ->with(SetLoginReferrerUrl::SESSION_KEY)
            ->willReturn('https://example.com/checkout/');

        $this->customerSession->expects($this->once())
            ->method('unsetData')
            ->with(SetLoginReferrerUrl::SESSION_KEY);
        $this->customerSession->expects($this->once())
            ->method('setBeforeAuthUrl')
            ->with('https://example.com/checkout/');

        $this->observerModel->execute($this->createMock(Observer::class));
    }
}
