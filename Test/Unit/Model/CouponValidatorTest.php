<?php

declare(strict_types=1);

namespace Ict\ExitIntentDiscountPopup\Test\Unit\Model;

use Ict\ExitIntentDiscountPopup\Model\CouponValidator;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\SalesRule\Api\Data\RuleInterface;
use Magento\SalesRule\Api\RuleRepositoryInterface;
use Magento\SalesRule\Model\Rule;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CouponValidatorTest extends TestCase
{
    /** @var CouponValidator */
    private CouponValidator $model;

    /** @var RuleRepositoryInterface|MockObject */
    private $ruleRepository;

    /** @var TimezoneInterface|MockObject */
    private $timezone;

    protected function setUp(): void
    {
        $this->ruleRepository = $this->createMock(RuleRepositoryInterface::class);
        $this->timezone       = $this->createMock(TimezoneInterface::class);

        // "Now" is returned when date() is called with no argument; when called
        // with a \DateTime (from/to conversion) it is passed straight through,
        // avoiding real timezone-conversion math in the test.
        $this->timezone->method('date')->willReturnCallback(
            static fn (?\DateTime $date = null) => $date ?? new \DateTime('2026-06-15 12:00:00')
        );

        $this->model = new CouponValidator(
            ruleRepository: $this->ruleRepository,
            timezone: $this->timezone
        );
    }

    private function mockRule(bool $isActive, $couponType, ?string $fromDate, ?string $toDate): RuleInterface
    {
        $rule = $this->createMock(RuleInterface::class);
        $rule->method('getIsActive')->willReturn($isActive);
        $rule->method('getCouponType')->willReturn($couponType);
        $rule->method('getFromDate')->willReturn($fromDate);
        $rule->method('getToDate')->willReturn($toDate);

        return $rule;
    }

    public function testRuleNotFoundIsInvalid(): void
    {
        $this->ruleRepository->method('getById')->willThrowException(
            new NoSuchEntityException(__('Rule does not exist'))
        );

        $this->assertFalse($this->model->isValid(999));
    }

    public function testInactiveRuleIsInvalid(): void
    {
        $this->ruleRepository->method('getById')->willReturn(
            $this->mockRule(false, Rule::COUPON_TYPE_SPECIFIC, null, null)
        );

        $this->assertFalse($this->model->isValid(1));
    }

    public function testNonSpecificCouponTypeIsInvalid(): void
    {
        $this->ruleRepository->method('getById')->willReturn(
            $this->mockRule(true, Rule::COUPON_TYPE_NO_COUPON, null, null)
        );

        $this->assertFalse($this->model->isValid(1));
    }

    public function testActiveSpecificCouponWithNoDateRestrictionIsValid(): void
    {
        $this->ruleRepository->method('getById')->willReturn(
            $this->mockRule(true, Rule::COUPON_TYPE_SPECIFIC, null, null)
        );

        $this->assertTrue($this->model->isValid(1));
    }

    public function testStringCouponTypeFormIsAlsoRecognizedAsSpecific(): void
    {
        $this->ruleRepository->method('getById')->willReturn(
            $this->mockRule(true, 'SPECIFIC_COUPON', null, null)
        );

        $this->assertTrue($this->model->isValid(1));
    }

    public function testRuleNotYetStartedIsInvalid(): void
    {
        $this->ruleRepository->method('getById')->willReturn(
            $this->mockRule(true, Rule::COUPON_TYPE_SPECIFIC, '2026-07-01', null)
        );

        $this->assertFalse($this->model->isValid(1));
    }

    public function testExpiredRuleIsInvalid(): void
    {
        $this->ruleRepository->method('getById')->willReturn(
            $this->mockRule(true, Rule::COUPON_TYPE_SPECIFIC, null, '2026-01-01')
        );

        $this->assertFalse($this->model->isValid(1));
    }

    public function testRuleWithinDateRangeIsValid(): void
    {
        $this->ruleRepository->method('getById')->willReturn(
            $this->mockRule(true, Rule::COUPON_TYPE_SPECIFIC, '2026-01-01', '2026-12-31')
        );

        $this->assertTrue($this->model->isValid(1));
    }
}
