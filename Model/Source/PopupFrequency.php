<?php

declare(strict_types=1);

namespace Ict\ExitIntentDiscountPopup\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class PopupFrequency implements OptionSourceInterface
{
    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'once_per_session', 'label' => __('Once Per Session')],
            ['value' => 'always',           'label' => __('Always')],
        ];
    }
}
