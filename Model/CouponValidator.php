<?php

declare(strict_types=1);

namespace Ict\ExitIntentDiscountPopup\Model;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\SalesRule\Api\RuleRepositoryInterface;
use Magento\SalesRule\Model\Rule;

class CouponValidator
{
    /**
     * @param RuleRepositoryInterface $ruleRepository
     * @param TimezoneInterface $timezone
     */
    public function __construct(
        private readonly RuleRepositoryInterface $ruleRepository,
        private readonly TimezoneInterface $timezone
    ) {
    }

    /**
     * Check whether the given Cart Price Rule is a currently-valid Specific Coupon Code rule.
     *
     * @param int $ruleId
     * @return bool
     */
    public function isValid(int $ruleId): bool
    {
        try {
            $rule = $this->ruleRepository->getById($ruleId);
        } catch (NoSuchEntityException) {
            return false;
        }

        if (!$rule->getIsActive()) {
            return false;
        }

        // RuleRepositoryInterface returns the string label (e.g. "SPECIFIC_COUPON"),
        // not the integer constant — compare both forms defensively.
        $couponType = $rule->getCouponType();
        $isSpecific  = $couponType == Rule::COUPON_TYPE_SPECIFIC
            || $couponType === 'SPECIFIC_COUPON';

        if (!$isSpecific) {
            return false;
        }

        return $this->isWithinDateRange($rule->getFromDate(), $rule->getToDate());
    }

    /**
     * Check whether "now" falls within the given from/to date range.
     *
     * @param string|null $fromDate
     * @param string|null $toDate
     * @return bool
     */
    private function isWithinDateRange(?string $fromDate, ?string $toDate): bool
    {
        $now = $this->timezone->date();

        if ($fromDate) {
            $from = $this->timezone->date(new \DateTime($fromDate, new \DateTimeZone('UTC')));
            if ($now < $from) {
                return false;
            }
        }

        if ($toDate) {
            $to = $this->timezone->date(new \DateTime($toDate, new \DateTimeZone('UTC')));
            if ($now > $to) {
                return false;
            }
        }

        return true;
    }
}
