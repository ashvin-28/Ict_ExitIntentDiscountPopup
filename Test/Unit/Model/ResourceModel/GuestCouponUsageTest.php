<?php

declare(strict_types=1);

namespace Ict\ExitIntentDiscountPopup\Test\Unit\Model\ResourceModel;

use Ict\ExitIntentDiscountPopup\Model\ResourceModel\GuestCouponUsage;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GuestCouponUsageTest extends TestCase
{
    private const TABLE = 'ict_exitintent_guest_coupon_usage';

    private GuestCouponUsage $model;

    /** @var ResourceConnection|MockObject */
    private $resource;

    /** @var AdapterInterface|MockObject */
    private $connection;

    protected function setUp(): void
    {
        $this->resource   = $this->createMock(ResourceConnection::class);
        $this->connection = $this->createMock(AdapterInterface::class);

        $this->resource->method('getConnection')->willReturn($this->connection);
        $this->resource->method('getTableName')->with(self::TABLE)->willReturn(self::TABLE);

        $this->model = new GuestCouponUsage(resource: $this->resource);
    }

    public function testHasUsedReturnsTrueWhenARowExists(): void
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $select->method('limit')->willReturnSelf();

        $this->connection->method('select')->willReturn($select);
        $this->connection->method('fetchOne')->with($select)->willReturn('7');

        $this->assertTrue($this->model->hasUsed('shopper@example.com', 7));
    }

    public function testHasUsedReturnsFalseWhenNoRowExists(): void
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $select->method('limit')->willReturnSelf();

        $this->connection->method('select')->willReturn($select);
        $this->connection->method('fetchOne')->willReturn(false);

        $this->assertFalse($this->model->hasUsed('shopper@example.com', 7));
    }

    public function testRecordInsertsIgnoreWithNormalizedEmail(): void
    {
        $this->connection->expects($this->once())
            ->method('query')
            ->with(
                $this->stringContains('INSERT IGNORE INTO'),
                ['shopper@example.com', 7, 123]
            );

        $this->model->record('  Shopper@Example.com  ', 7, 123);
    }
}
