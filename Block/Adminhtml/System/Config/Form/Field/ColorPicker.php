<?php

declare(strict_types=1);

namespace Ict\ExitIntentDiscountPopup\Block\Adminhtml\System\Config\Form\Field;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class ColorPicker extends Field
{
    protected function _getElementHtml(AbstractElement $element): string
    {
        $element->setType('color');
        return $element->getElementHtml();
    }
}
