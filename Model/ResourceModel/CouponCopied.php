<?php

declare(strict_types=1);

namespace Ict\ExitIntentDiscountPopup\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;

class CouponCopied
{
    private const TABLE = 'ict_exitintent_coupon_copied';

    public function __construct(
        private readonly ResourceConnection $resource
    ) {}

    public function hasCopied(int $customerId, int $ruleId): bool
    {
        $connection = $this->resource->getConnection();
        $table      = $this->resource->getTableName(self::TABLE);

        $select = $connection->select()
            ->from($table, ['id'])
            ->where('customer_id = ?', $customerId)
            ->where('rule_id = ?', $ruleId)
            ->limit(1);

        return (bool) $connection->fetchOne($select);
    }

    public function markCopied(int $customerId, int $ruleId): void
    {
        $connection = $this->resource->getConnection();
        $table      = $this->resource->getTableName(self::TABLE);

        // INSERT IGNORE so concurrent requests don't throw on the unique key
        $connection->insertIgnore($table, [
            'customer_id' => $customerId,
            'rule_id'     => $ruleId,
        ]);
    }
}
