<?php

namespace App\Services;

use App\Models\Performance;
use Carbon\Carbon;
use InvalidArgumentException;

class QuotaAchievementService
{
    public function calculate(int $userId, string $periodType, string $period, ?int $productId = null): array
    {
        [$from, $to] = $this->periodRange($periodType, $period);

        $achieved = (float) Performance::query()
            ->where('created_by', $userId)
            ->where('status', Performance::STATUS_APPROVED)
            ->whereBetween('performance_date', [$from, $to])
            ->when($productId, fn ($q) => $q->where('product_id', $productId))
            ->sum('subtotal');

        return [
            'achieved_amount' => $achieved,
        ];
    }

    public function periodRange(string $periodType, string $period): array
    {
        return match ($periodType) {
            'monthly' => [
                Carbon::parse($period.'-01')->startOfMonth()->toDateString(),
                Carbon::parse($period.'-01')->endOfMonth()->toDateString(),
            ],
            'quarterly' => $this->quarterRange($period),
            'yearly' => [
                Carbon::createFromDate((int) $period, 1, 1)->startOfYear()->toDateString(),
                Carbon::createFromDate((int) $period, 1, 1)->endOfYear()->toDateString(),
            ],
            default => throw new InvalidArgumentException("Unknown period type: {$periodType}"),
        };
    }

    private function quarterRange(string $period): array
    {
        [$year, $q] = explode('-Q', $period);
        $startMonth = ((int) $q - 1) * 3 + 1;
        $start = Carbon::createFromDate((int) $year, $startMonth, 1)->startOfMonth();
        $end = $start->copy()->addMonths(2)->endOfMonth();

        return [$start->toDateString(), $end->toDateString()];
    }
}
