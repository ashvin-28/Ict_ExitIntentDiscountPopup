<?php

declare(strict_types=1);

namespace Ict\ExitIntentDiscountPopup\Model\Source;

use Magento\Framework\Option\ArrayInterface;
use Magento\SalesRule\Model\ResourceModel\Rule\CollectionFactory;
use Magento\SalesRule\Model\Rule;

class CouponOptions implements ArrayInterface
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory
    ) {}

    public function toOptionArray(): array
    {
        $options = [['value' => '', 'label' => __('-- Please Select --')]];

        foreach ($this->getCollection() as $rule) {
            $options[] = ['value' => $rule->getId(), 'label' => $rule->getName()];
        }

        return $options;
    }

    public function toArray(): array
    {
        $options = [];

        foreach ($this->getCollection() as $rule) {
            $options[$rule->getId()] = $rule->getName();
        }

        return $options;
    }

    private function getCollection()
    {
        return $this->collectionFactory->create()
            ->addFieldToFilter('is_active', 1)
            ->addFieldToFilter('coupon_type', Rule::COUPON_TYPE_SPECIFIC)
            ->addFieldToSelect(['rule_id', 'name']);
    }
}
