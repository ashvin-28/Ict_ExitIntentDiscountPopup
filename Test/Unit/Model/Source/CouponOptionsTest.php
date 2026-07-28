<?php

declare(strict_types=1);

namespace Ict\ExitIntentDiscountPopup\Test\Unit\Model\Source;

use Ict\ExitIntentDiscountPopup\Model\Source\CouponOptions;
use Magento\SalesRule\Model\ResourceModel\Rule\Collection;
use Magento\SalesRule\Model\ResourceModel\Rule\CollectionFactory;
use Magento\SalesRule\Model\Rule;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CouponOptionsTest extends TestCase
{
    /** @var CollectionFactory|MockObject */
    private $collectionFactory;

    protected function setUp(): void
    {
        $this->collectionFactory = $this->createMock(CollectionFactory::class);
    }

    private function buildModel(): CouponOptions
    {
        return new CouponOptions(collectionFactory: $this->collectionFactory);
    }

    private function mockCollection(array $rules): void
    {
        $collection = $this->createMock(Collection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('addFieldToSelect')->willReturnSelf();
        $collection->method('getIterator')->willReturn(new \ArrayIterator($rules));

        $this->collectionFactory->method('create')->willReturn($collection);
    }

    private function mockRule(int $id, string $name): Rule
    {
        // getName() is a magic (__call) attribute getter on Rule, not a
        // declared method — addMethods() is required to mock it.
        $rule = $this->getMockBuilder(Rule::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId'])
            ->addMethods(['getName'])
            ->getMock();
        $rule->method('getId')->willReturn($id);
        $rule->method('getName')->willReturn($name);

        return $rule;
    }

    public function testToOptionArrayAlwaysLeadsWithPleaseSelect(): void
    {
        $this->mockCollection([]);

        $options = $this->buildModel()->toOptionArray();

        $this->assertCount(1, $options);
        $this->assertSame('', $options[0]['value']);
    }

    public function testToOptionArrayIncludesEachRuleFromTheFilteredCollection(): void
    {
        $this->mockCollection([
            $this->mockRule(7, 'ExitIntentPopupDiscount'),
            $this->mockRule(9, 'AnotherSpecificCoupon'),
        ]);

        $options = $this->buildModel()->toOptionArray();

        $this->assertCount(3, $options); // placeholder + 2 rules
        $this->assertSame(['value' => 7, 'label' => 'ExitIntentPopupDiscount'], $options[1]);
        $this->assertSame(['value' => 9, 'label' => 'AnotherSpecificCoupon'], $options[2]);
    }

    public function testToArrayKeysByRuleId(): void
    {
        $this->mockCollection([
            $this->mockRule(7, 'ExitIntentPopupDiscount'),
        ]);

        $this->assertSame(
            [7 => 'ExitIntentPopupDiscount'],
            $this->buildModel()->toArray()
        );
    }
}
