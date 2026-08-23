<?php

namespace App\Services;

use App\Models\HospitalSetting;
use App\Models\Patient;

class PatientDuplicateDetector
{
    public function warnings(int $hospitalId, array $payload, ?Patient $exclude = null): array
    {
        $settings = HospitalSetting::where('hospital_id', $hospitalId)->first();
        $config = $settings?->operating_preferences['patient_duplicate_matching'] ?? [
            'match_phone' => true,
            'match_name_dob' => true,
            'match_identifier' => true,
        ];

        $query = Patient::query()->forHospital($hospitalId);

        if ($exclude) {
            $query->whereKeyNot($exclude->id);
        }

        $lookup = app(SensitiveLookup::class);
        $phoneHash = $lookup->hash($payload['phone'] ?? null);
        $identifierHashes = collect($payload['identifiers'] ?? [])
            ->pluck('value')
            ->filter()
            ->map(fn ($value) => $lookup->hash($value))
            ->values();

        $matches = $query->where(function ($inner) use ($payload, $phoneHash, $identifierHashes, $config): void {
            if (($config['match_phone'] ?? true) && $phoneHash) {
                $inner->orWhere('phone_hash', $phoneHash);
            }

            if (($config['match_name_dob'] ?? true) && filled($payload['first_name'] ?? null) && filled($payload['last_name'] ?? null)) {
                $inner->orWhere(function ($nameQuery) use ($payload): void {
                    $nameQuery->where('first_name', $payload['first_name'])
                        ->where('last_name', $payload['last_name'])
                        ->when($payload['date_of_birth'] ?? null, fn ($dobQuery, $dob) => $dobQuery->where('date_of_birth', $dob))
                        ->when(! ($payload['date_of_birth'] ?? null) && ($payload['estimated_age_years'] ?? null), fn ($ageQuery, $age) => $ageQuery->where('estimated_age_years', $age));
                });
            }

            if (($config['match_identifier'] ?? true) && $identifierHashes->isNotEmpty()) {
                $inner->orWhereHas('identifiers', fn ($identifier) => $identifier
                    ->where('is_searchable', true)
                    ->whereIn('value_hash', $identifierHashes));
            }
        })
            ->limit(5)
            ->get(['id', 'hospital_number', 'first_name', 'middle_name', 'last_name', 'date_of_birth', 'estimated_age_years', 'sex', 'status']);

        return $matches->map(fn (Patient $patient) => [
            'id' => $patient->id,
            'hospital_number' => $patient->hospital_number,
            'full_name' => $patient->full_name,
            'date_of_birth' => $patient->date_of_birth?->toDateString(),
            'estimated_age_years' => $patient->estimated_age_years,
            'sex' => $patient->sex,
            'status' => $patient->status,
        ])->all();
    }
}
