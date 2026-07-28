<?php

declare(strict_types=1);

namespace Ict\ExitIntentDiscountPopup\Test\Unit\Model\Source;

use Ict\ExitIntentDiscountPopup\Model\Source\PopupFrequency;
use PHPUnit\Framework\TestCase;

class PopupFrequencyTest extends TestCase
{
    public function testOptionArrayContainsBothFrequencyValues(): void
    {
        $values = array_column((new PopupFrequency())->toOptionArray(), 'value');

        $this->assertSame(['once_per_session', 'always'], $values);
    }
}
