<?php

declare(strict_types=1);

namespace Ict\ExitIntentDiscountPopup\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;

class GuestCouponUsage
{
    private const TABLE = 'ict_exitintent_guest_coupon_usage';

    /**
     * @param ResourceConnection $resource
     */
    public function __construct(
        private readonly ResourceConnection $resource
    ) {
    }

    /**
     * Check whether a guest email has already used a coupon rule.
     *
     * @param string $email
     * @param int $ruleId
     * @return bool
     */
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

    /**
     * Record guest coupon usage for an order.
     *
     * @param string $email
     * @param int $ruleId
     * @param int $orderId
     * @return void
     */
    public function record(string $email, int $ruleId, int $orderId): void
    {
        $connection = $this->resource->getConnection();
        $table      = $this->resource->getTableName(self::TABLE);

        $data = [
            'email'    => strtolower(trim($email)),
            'rule_id'  => $ruleId,
            'order_id' => $orderId,
        ];

        $connection->insertOnDuplicate($table, $data, ['email']);
    }
}
