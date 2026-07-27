<?php

declare(strict_types=1);

namespace Ict\ExitIntentDiscountPopup\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;

class GuestCouponUsage
{
    private const TABLE = 'ict_exitintent_guest_coupon_usage';

    public function __construct(
        private readonly ResourceConnection $resource
    ) {}

    public function hasUsed(string $email, int $ruleId): bool
    {
        $connection = $this->resource->getConnection();
        $table      = $this->resource->getTableName(self::TABLE);

        $select = $connection->select()
            ->from($table, ['entity_id'])
            ->where('email = ?', strtolower(trim($email)))
            ->where('rule_id = ?', $ruleId)
            ->limit(1);

        return (bool) $connection->fetchOne($select);
    }

    public function record(string $email, int $ruleId, int $orderId): void
    {
        $connection = $this->resource->getConnection();
        $table      = $this->resource->getTableName(self::TABLE);

        $connection->query(
            'INSERT IGNORE INTO ' . $table . ' (email, rule_id, order_id) VALUES (?, ?, ?)',
            [strtolower(trim($email)), $ruleId, $orderId]
        );
    }
}
