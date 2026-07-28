<?php

declare(strict_types=1);

namespace Ict\ExitIntentDiscountPopup\Test\Unit\Model;

use Ict\ExitIntentDiscountPopup\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    /** @var ScopeConfigInterface|MockObject */
    private $scopeConfig;

    /** @var StoreManagerInterface|MockObject */
    private $storeManager;

    protected function setUp(): void
    {
        $this->scopeConfig  = $this->createMock(ScopeConfigInterface::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
    }

    private function buildModel(): Config
    {
        return new Config(scopeConfig: $this->scopeConfig, storeManager: $this->storeManager);
    }

    public function testIsEnabledUsesTheGivenStoreScope(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('isSetFlag')
            ->with('exitintentpopup/general/enable', ScopeInterface::SCOPE_STORE, '3')
            ->willReturn(true);

        $this->assertTrue($this->buildModel()->isEnabled('3'));
    }

    public function testGetMobileDelayFallsBackToCurrentStoreWhenNoScopeGiven(): void
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(5);
        $this->storeManager->method('getStore')->willReturn($store);

        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with('exitintentpopup/general/mobile_delay', ScopeInterface::SCOPE_STORE, '5')
            ->willReturn('5000');

        $this->assertSame(5000, $this->buildModel()->getMobileDelay());
    }

    public function testGetCouponRuleIdAlwaysUsesDefaultScopeRegardlessOfStore(): void
    {
        // coupon_rule_id is showInWebsite="0" showInStore="0" — a global-only
        // field, so this must never be queried per-store.
        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with('exitintentpopup/promotion/coupon_rule_id', ScopeConfigInterface::SCOPE_TYPE_DEFAULT)
            ->willReturn('7');

        $this->assertSame(7, $this->buildModel()->getCouponRuleId());
    }

    public function testIsEmailToLoggedInEnabledUsesStoreScope(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('isSetFlag')
            ->with('exitintentpopup/promotion/send_email_logged_in', ScopeInterface::SCOPE_STORE, '2')
            ->willReturn(false);

        $this->assertFalse($this->buildModel()->isEmailToLoggedInEnabled('2'));
    }

    /**
     * All remaining getters are identically-shaped string passthroughs over
     * scopeConfig->getValue() at store scope — verified together so each path
     * is checked without one near-duplicate test method per getter.
     */
    public function testSimpleStringGettersQueryTheirExpectedPath(): void
    {
        $cases = [
            'getPopupFrequency'      => 'exitintentpopup/general/popup_frequency',
            'getBackgroundColor'     => 'exitintentpopup/general/background_color',
            'getFontColor'           => 'exitintentpopup/general/font_color',
            'getButtonColor'         => 'exitintentpopup/general/button_color',
            'getButtonTextColor'     => 'exitintentpopup/general/button_text_color',
            'getCopyButtonText'      => 'exitintentpopup/promotion/copy_button_text',
            'getPopupHeading'        => 'exitintentpopup/content/popup_heading',
            'getPopupDescription'    => 'exitintentpopup/content/popup_description',
            'getDiscountLabel'       => 'exitintentpopup/content/discount_label',
            'getCtaButtonText'       => 'exitintentpopup/content/cta_button_text',
            'getSecondaryButtonText' => 'exitintentpopup/content/secondary_button_text',
        ];

        foreach ($cases as $method => $expectedPath) {
            $scopeConfig = $this->createMock(ScopeConfigInterface::class);
            $scopeConfig->expects($this->once())
                ->method('getValue')
                ->with($expectedPath, ScopeInterface::SCOPE_STORE, '9')
                ->willReturn('expected-value');

            $config = new Config(scopeConfig: $scopeConfig, storeManager: $this->storeManager);

            $this->assertSame(
                'expected-value',
                $config->$method('9'),
                "$method did not query the expected config path"
            );
        }
    }
}
