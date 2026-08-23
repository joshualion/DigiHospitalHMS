<?php

namespace App\Services;

use App\Models\BillableService;
use App\Models\ServicePrice;
use App\Models\User;
use Illuminate\Support\Carbon;

class ServicePricingService
{
    public function createPrice(BillableService $service, array $data, User $actor): ServicePrice
    {
        $facilityId = $data['facility_id'] ?? null;
        $from = Carbon::parse($data['effective_from'])->toDateString();
        $to = filled($data['effective_to'] ?? null) ? Carbon::parse($data['effective_to'])->toDateString() : null;

        abort_if($to && $to < $from, 422, 'Price end date cannot be before start date.');

        $overlap = ServicePrice::where('hospital_id', $service->hospital_id)
            ->where('billable_service_id', $service->id)
            ->where('facility_id', $facilityId)
            ->where('is_active', true)
            ->where('effective_from', '<=', $to ?? '9999-12-31')
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>=', $from))
            ->exists();

        abort_if($overlap, 422, 'An active price already overlaps this effective period.');

        $price = ServicePrice::create([
            'hospital_id' => $service->hospital_id,
            'billable_service_id' => $service->id,
            'facility_id' => $facilityId,
            'currency' => $data['currency'],
            'amount_minor' => (int) $data['amount_minor'],
            'effective_from' => $from,
            'effective_to' => $to,
            'is_active' => true,
            'created_by' => $actor->id,
            'reason' => $data['reason'] ?? null,
        ]);

        app(AuditService::class)->record('service_prices.created', $price, null, $price->toArray(), actor: $actor, reason: $price->reason);

        return $price;
    }

    public function priceFor(BillableService $service, ?int $facilityId, ?string $date = null): ServicePrice
    {
        $date ??= now()->toDateString();

        $query = ServicePrice::where('hospital_id', $service->hospital_id)
            ->where('billable_service_id', $service->id)
            ->where('is_active', true)
            ->where('effective_from', '<=', $date)
            ->where(fn ($inner) => $inner->whereNull('effective_to')->orWhere('effective_to', '>=', $date));

        $facilityPrice = (clone $query)->where('facility_id', $facilityId)->latest('effective_from')->first();
        $defaultPrice = (clone $query)->whereNull('facility_id')->latest('effective_from')->first();

        return $facilityPrice ?? $defaultPrice ?? abort(422, 'No active price exists for this service.');
    }
}
