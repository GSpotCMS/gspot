<?php

declare(strict_types=1);

namespace GSpot\UserPlugins\Commerce;

final class TaxCalculator
{
    public function taxCents(int $taxableCents, float $ratePercent): int
    {
        if ($taxableCents <= 0 || $ratePercent <= 0) {
            return 0;
        }

        return (int) round($taxableCents * $ratePercent / 100);
    }
}
