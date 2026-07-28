<?php

declare(strict_types=1);

namespace Ict\ExitIntentDiscountPopup\Test\Unit\Model;

use Ict\ExitIntentDiscountPopup\Model\Config;
use Ict\ExitIntentDiscountPopup\Model\ConfigProvider;
use Ict\ExitIntentDiscountPopup\Model\CouponValidator;
use Ict\ExitIntentDiscountPopup\Model\ResourceModel\GuestCouponUsage;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Url\EncoderInterface;
use Magento\Framework\UrlInterface;
use Magento\Quote\Model\Quote;
use Magento\SalesRule\Model\Coupon;
use Magento\SalesRule\Model\Rule;
use Magento\SalesRule\Model\RuleFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ConfigProviderTest extends TestCase
{
    private const RULE_ID = 7;

    /** @var ConfigProvider */
    private ConfigProvider $model;

    /** @var Config|MockObject */
    private $config;

    /** @var CouponValidator|MockObject */
    private $couponValidator;

    /** @var RuleFactory|MockObject */
    private $ruleFactory;

    /** @var CustomerSession|MockObject */
    private $customerSession;

    /** @var CheckoutSession|MockObject */
    private $checkoutSession;

    /** @var ResourceConnection|MockObject */
    private $resource;

    /** @var GuestCouponUsage|MockObject */
    private $guestCouponUsage;

    /** @var UrlInterface|MockObject */
    private $urlBuilder;

    /** @var EncoderInterface|MockObject */
    private $urlEncoder;

    protected function setUp(): void
    {
        $this->config           = $this->createMock(Config::class);
        $this->couponValidator  = $this->createMock(CouponValidator::class);
        $this->ruleFactory      = $this->createMock(RuleFactory::class);
        $this->customerSession  = $this->createMock(CustomerSession::class);
        $this->checkoutSession  = $this->createMock(CheckoutSession::class);
        $this->resource         = $this->createMock(ResourceConnection::class);
        $this->guestCouponUsage = $this->createMock(GuestCouponUsage::class);
        $this->urlBuilder       = $this->createMock(UrlInterface::class);
        $this->urlEncoder       = $this->createMock(EncoderInterface::class);

        $this->urlBuilder->method('getUrl')->willReturnCallback(function ($route, $params = []) {
            if ($route === 'checkout') {
                return 'https://example.com/checkout/';
            }
            return 'https://example.com/' . $route . '/?referer=' . ($params['referer'] ?? '');
        });
        $this->urlEncoder->method('encode')->willReturnCallback(function ($url) {
            return 'ENC(' . $url . ')';
        });

        // Baseline: valid rule id configured, guest (not logged-in) session.
        $this->config->method('getCouponRuleId')->willReturn(self::RULE_ID);
        $this->customerSession->method('isLoggedIn')->willReturn(false);

        $this->model = $this->buildModel();
    }

    private function buildModel(): ConfigProvider
    {
        return new ConfigProvider(
            config: $this->config,
            couponValidator: $this->couponValidator,
            ruleFactory: $this->ruleFactory,
            customerSession: $this->customerSession,
            checkoutSession: $this->checkoutSession,
            resource: $this->resource,
            guestCouponUsage: $this->guestCouponUsage,
            urlBuilder: $this->urlBuilder,
            urlEncoder: $this->urlEncoder
        );
    }

    private function mockGuestQuote(string $email): void
    {
        // getCustomerEmail() is a magic (__call) attribute getter on Quote,
        // not a declared method — addMethods() is required to mock it.
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->addMethods(['getCustomerEmail'])
            ->getMock();
        $quote->method('getCustomerEmail')->willReturn($email);
        $this->checkoutSession->method('getQuote')->willReturn($quote);
    }

    private function mockResolvedCoupon(string $code): void
    {
        $coupon = $this->createMock(Coupon::class);
        $coupon->method('getCode')->willReturn($code);

        $rule = $this->createMock(Rule::class);
        $rule->method('load')->willReturnSelf();
        $rule->method('getPrimaryCoupon')->willReturn($coupon);

        $this->ruleFactory->method('create')->willReturn($rule);
    }

    public function testDisabledConfigMeansPopupNotEnabledEvenWithValidCoupon(): void
    {
        $this->config->method('isEnabled')->willReturn(false);
        $this->couponValidator->method('isValid')->willReturn(true);
        $this->mockGuestQuote('shopper@example.com');
        $this->guestCouponUsage->method('hasUsed')->willReturn(false);
        $this->mockResolvedCoupon('SAVE10');

        $config = $this->model->getConfig()['exitIntentPopup'];

        $this->assertFalse($config['enabled']);
        // Independent of the "enabled" flag, the code itself still resolves.
        $this->assertSame('SAVE10', $config['couponCode']);
    }

    public function testInvalidCouponRuleMeansNotEnabledAndNoCode(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->couponValidator->method('isValid')->willReturn(false);

        $config = $this->model->getConfig()['exitIntentPopup'];

        $this->assertFalse($config['enabled']);
        $this->assertNull($config['couponCode']);
    }

    public function testGuestAlreadyRedeemedBlocksEvenAValidCoupon(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->couponValidator->method('isValid')->willReturn(true);
        $this->mockGuestQuote('used@example.com');
        $this->guestCouponUsage->method('hasUsed')->with('used@example.com', self::RULE_ID)->willReturn(true);

        $config = $this->model->getConfig()['exitIntentPopup'];

        $this->assertFalse($config['enabled']);
        $this->assertNull($config['couponCode']);
    }

    public function testHappyPathGuestNotRedeemed(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('getMobileDelay')->willReturn(5000);
        $this->config->method('getPopupFrequency')->willReturn('always');
        $this->config->method('getBackgroundColor')->willReturn('#ffffff');
        $this->config->method('getFontColor')->willReturn('#303030');
        $this->config->method('getButtonColor')->willReturn('#1979c3');
        $this->config->method('getButtonTextColor')->willReturn('#ffffff');
        $this->couponValidator->method('isValid')->willReturn(true);
        $this->mockGuestQuote('shopper@example.com');
        $this->guestCouponUsage->method('hasUsed')->willReturn(false);
        $this->mockResolvedCoupon('SAVE10');

        $config = $this->model->getConfig()['exitIntentPopup'];

        $this->assertTrue($config['enabled']);
        $this->assertSame('SAVE10', $config['couponCode']);
        $this->assertSame(self::RULE_ID, $config['ruleId']);
        $this->assertSame(
            ['background' => '#ffffff', 'font' => '#303030', 'button' => '#1979c3', 'buttonText' => '#ffffff'],
            $config['colors']
        );
        $this->assertArrayHasKey('badgeLabel', $config['i18n']);
        $this->assertArrayHasKey('copyConfirmText', $config['i18n']);
    }

    public function testDiscountPopupNoLongerExposesCtaOrSecondaryButtonConfig(): void
    {
        $config = $this->model->getConfig()['exitIntentPopup'];

        $this->assertArrayNotHasKey('ctaButtonText', $config);
        $this->assertArrayNotHasKey('secondaryButtonText', $config);
        $this->assertArrayNotHasKey('placeOrderAriaLabel', $config['i18n']);
        $this->assertArrayNotHasKey('continueShoppingAriaLabel', $config['i18n']);
    }

    public function testApplyButtonTextAndI18nAreExposed(): void
    {
        $this->config->method('getApplyButtonText')->willReturn('Apply Code');

        $config = $this->model->getConfig()['exitIntentPopup'];

        $this->assertSame('Apply Code', $config['applyButtonText']);
        $this->assertArrayHasKey('applyAriaLabel', $config['i18n']);
        $this->assertArrayHasKey('applyConfirmText', $config['i18n']);
        $this->assertArrayHasKey('applyGenericErrorText', $config['i18n']);
    }

    public function testCloseButtonTextIsExposed(): void
    {
        $this->config->method('getCloseButtonText')->willReturn('No Thanks');

        $config = $this->model->getConfig()['exitIntentPopup'];

        $this->assertSame('No Thanks', $config['closeButtonText']);
    }

    public function testLoggedInCustomerAlreadyRedeemedBlocksPopup(): void
    {
        $this->customerSession = $this->createMock(CustomerSession::class);
        $this->customerSession->method('isLoggedIn')->willReturn(true);
        $this->customerSession->method('getCustomerId')->willReturn(42);

        $this->model = $this->buildModel();

        $this->config->method('isEnabled')->willReturn(true);
        $this->couponValidator->method('isValid')->willReturn(true);

        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('join')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $select->method('limit')->willReturnSelf();

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchOne')->willReturn('1');

        $this->resource->method('getConnection')->willReturn($connection);
        $this->resource->method('getTableName')->willReturnArgument(0);

        $config = $this->model->getConfig()['exitIntentPopup'];

        $this->assertFalse($config['enabled']);
        $this->assertNull($config['couponCode']);
    }

    public function testGuestConfigReportsNotLoggedIn(): void
    {
        $config = $this->model->getConfig()['exitIntentPopup'];

        $this->assertFalse($config['isLoggedIn']);
    }

    public function testLoggedInConfigReportsLoggedIn(): void
    {
        $this->customerSession = $this->createMock(CustomerSession::class);
        $this->customerSession->method('isLoggedIn')->willReturn(true);
        $this->model = $this->buildModel();

        $config = $this->model->getConfig()['exitIntentPopup'];

        $this->assertTrue($config['isLoggedIn']);
    }

    public function testLoginPromptContentAndUrlsCarryAnEncodedCheckoutReferer(): void
    {
        $this->config->method('getLoginPromptFrequency')->willReturn('always');
        $this->config->method('getLoginPromptHeading')->willReturn('Unlock Your Exclusive Discount');
        $this->config->method('getLoginPromptMessage')->willReturn('Login to unlock your exclusive discount.');
        $this->config->method('getLoginButtonText')->willReturn('Login');
        $this->config->method('getRegisterLinkText')->willReturn('New customer? Create an account');

        $loginPrompt = $this->model->getConfig()['exitIntentPopup']['loginPrompt'];

        $this->assertSame('always', $loginPrompt['frequency']);
        $this->assertSame('Unlock Your Exclusive Discount', $loginPrompt['heading']);
        $this->assertSame('Login to unlock your exclusive discount.', $loginPrompt['message']);
        $this->assertSame('Login', $loginPrompt['loginButtonText']);
        $this->assertSame('New customer? Create an account', $loginPrompt['registerLinkText']);
        $this->assertSame(
            'https://example.com/customer/account/login/?referer=ENC(https://example.com/checkout/)',
            $loginPrompt['loginUrl']
        );
        $this->assertSame(
            'https://example.com/customer/account/create/?referer=ENC(https://example.com/checkout/)',
            $loginPrompt['registerUrl']
        );
    }
}
