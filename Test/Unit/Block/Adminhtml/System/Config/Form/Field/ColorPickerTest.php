<?php

declare(strict_types=1);

namespace Ict\ExitIntentDiscountPopup\Test\Unit\Block\Adminhtml\System\Config\Form\Field;

use Ict\ExitIntentDiscountPopup\Block\Adminhtml\System\Config\Form\Field\ColorPicker;
use Magento\Framework\App\ObjectManager as AppObjectManager;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ColorPickerTest extends TestCase
{
    /** @var ObjectManagerInterface|MockObject */
    private $appObjectManager;

    /**
     * ColorPicker extends Magento\Backend\Block\Template, whose constructor
     * unconditionally falls back to the static app ObjectManager singleton
     * for JsonHelper/DirectoryHelper when they're not supplied — neither the
     * Field nor Template constructor exposes them as injectable params, so
     * the singleton must be stubbed for the block to construct at all.
     * setInstance() takes a non-nullable ObjectManagerInterface, so this
     * stub can't be un-set afterward — a shared, real tradeoff Magento's own
     * core Template-block unit tests make too. Since it only ever returns
     * harmless mocks for whatever class is requested, it's inert for any
     * other test class that happens to run later in the same process.
     */
    protected function setUp(): void
    {
        $this->appObjectManager = $this->createMock(ObjectManagerInterface::class);
        $this->appObjectManager->method('get')->willReturnCallback(
            fn (string $class) => $this->createMock($class)
        );
        AppObjectManager::setInstance($this->appObjectManager);
    }

    public function testRendersAColorInputByForcingTheElementTypeToColor(): void
    {
        $element = $this->createMock(AbstractElement::class);
        $element->expects($this->once())->method('setType')->with('color');
        $element->method('getElementHtml')->willReturn('<input type="color" />');

        $block = (new ObjectManager($this))->getObject(ColorPicker::class);

        $method = new \ReflectionMethod(ColorPicker::class, '_getElementHtml');
        $method->setAccessible(true);

        $this->assertSame('<input type="color" />', $method->invoke($block, $element));
    }
}
