<?php

declare(strict_types=1);

namespace Ict\ExitIntentDiscountPopup\Test\Unit\Observer;

use Ict\ExitIntentDiscountPopup\Observer\SetLoginReferrerUrl;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Url\DecoderInterface;
use Magento\Framework\Url\HostChecker;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SetLoginReferrerUrlTest extends TestCase
{
    /** @var CustomerSession|MockObject */
    private $customerSession;

    /** @var DecoderInterface|MockObject */
    private $urlDecoder;

    /** @var HostChecker|MockObject */
    private $hostChecker;

    /** @var SetLoginReferrerUrl */
    private SetLoginReferrerUrl $observerModel;

    protected function setUp(): void
    {
        $this->customerSession = $this->createMock(CustomerSession::class);
        $this->urlDecoder      = $this->createMock(DecoderInterface::class);
        $this->hostChecker     = $this->createMock(HostChecker::class);

        $this->observerModel = new SetLoginReferrerUrl(
            customerSession: $this->customerSession,
            urlDecoder: $this->urlDecoder,
            hostChecker: $this->hostChecker
        );
    }

    private function mockObserverWithReferer(?string $referer): Observer
    {
        $request = $this->getMockBuilder(HttpRequest::class)
            ->disableOriginalConstructor()
            ->getMock();
        $request->method('getParam')->with('referer')->willReturn($referer);

        $event = $this->getMockBuilder(Event::class)
            ->disableOriginalConstructor()
            ->addMethods(['getRequest'])
            ->getMock();
        $event->method('getRequest')->willReturn($request);

        $observer = $this->getMockBuilder(Observer::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getEvent'])
            ->getMock();
        $observer->method('getEvent')->willReturn($event);

        return $observer;
    }

    public function testDoesNothingWhenNoRefererParamPresent(): void
    {
        $this->customerSession->expects($this->never())->method('setBeforeAuthUrl');

        $this->observerModel->execute($this->mockObserverWithReferer(null));
    }

    public function testSetsBeforeAuthUrlWhenRefererIsOwnOrigin(): void
    {
        $this->urlDecoder->method('decode')->with('ENCODED')->willReturn('https://example.com/checkout/');
        $this->hostChecker->method('isOwnOrigin')->with('https://example.com/checkout/')->willReturn(true);

        $this->customerSession->expects($this->once())
            ->method('setBeforeAuthUrl')
            ->with('https://example.com/checkout/');

        $this->observerModel->execute($this->mockObserverWithReferer('ENCODED'));
    }

    public function testDoesNotSetBeforeAuthUrlForAForeignHostReferer(): void
    {
        $this->urlDecoder->method('decode')->with('ENCODED')->willReturn('https://evil.example/phish');
        $this->hostChecker->method('isOwnOrigin')->with('https://evil.example/phish')->willReturn(false);

        $this->customerSession->expects($this->never())->method('setBeforeAuthUrl');

        $this->observerModel->execute($this->mockObserverWithReferer('ENCODED'));
    }
}
