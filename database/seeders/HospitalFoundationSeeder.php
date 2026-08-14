<?php

namespace Database\Seeders;

use App\Models\Facility;
use App\Models\Hospital;
use App\Models\HospitalSetting;
use App\Models\NumberSequence;
use Illuminate\Database\Seeder;

class HospitalFoundationSeeder extends Seeder
{
    public function run(): void
    {
        $hospital = Hospital::firstOrCreate(
            ['legal_name' => 'Demo Hospital'],
            [
                'display_name' => 'Demo Hospital',
                'email' => 'info@example.test',
                'phone_numbers' => ['+2340000000000'],
                'address' => 'Configure hospital address',
                'city' => 'Lagos',
                'state' => 'Lagos',
                'country' => 'Nigeria',
                'timezone' => 'Africa/Lagos',
                'default_currency' => 'NGN',
                'primary_contact_name' => 'Hospital Administrator',
                'primary_contact_email' => 'admin@example.test',
                'status' => 'active',
                'is_active' => true,
            ]
        );

        $facility = Facility::firstOrCreate(
            ['hospital_id' => $hospital->id, 'code' => 'MAIN'],
            [
                'name' => 'Main Facility',
                'facility_type' => 'main_hospital',
                'email' => $hospital->email,
                'phone' => $hospital->phone_numbers[0] ?? null,
                'address' => $hospital->address,
                'city' => $hospital->city,
                'state' => $hospital->state,
                'country' => $hospital->country,
                'is_primary' => true,
                'status' => 'active',
                'opening_hours' => [
                    'monday' => '08:00-18:00',
                    'tuesday' => '08:00-18:00',
                    'wednesday' => '08:00-18:00',
                    'thursday' => '08:00-18:00',
                    'friday' => '08:00-18:00',
                ],
            ]
        );

        HospitalSetting::firstOrCreate(
            ['hospital_id' => $hospital->id],
            [
                'default_facility_id' => $facility->id,
                'locale' => 'en',
                'timezone' => 'Africa/Lagos',
                'currency' => 'NGN',
                'date_format' => 'Y-m-d',
                'time_format' => 'H:i',
                'branding' => ['primary_color' => '#991b1b'],
                'contact_details' => ['email' => $hospital->email],
                'operating_preferences' => ['default_country' => 'Nigeria'],
                'public_site_defaults' => ['show_public_site' => true],
                'numbering_preferences' => ['separator' => '-'],
            ]
        );

        foreach ($this->sequences() as $sequence) {
            NumberSequence::firstOrCreate(
                [
                    'hospital_id' => $hospital->id,
                    'facility_id' => null,
                    'key' => $sequence['key'],
                ],
                $sequence + [
                    'hospital_id' => $hospital->id,
                    'facility_id' => null,
                    'next_value' => 1,
                    'issued_count' => 0,
                    'status' => 'active',
                ]
            );
        }
    }

    private function sequences(): array
    {
        return [
            ['key' => 'patient_number', 'label' => 'Patient hospital number', 'prefix' => 'PAT', 'date_format' => 'Y', 'padding_length' => 6],
            ['key' => 'visit_number', 'label' => 'Visit number', 'prefix' => 'VIS', 'date_format' => 'Ymd', 'padding_length' => 5],
            ['key' => 'invoice_number', 'label' => 'Invoice number', 'prefix' => 'INV', 'date_format' => 'Y', 'padding_length' => 6],
            ['key' => 'receipt_number', 'label' => 'Receipt number', 'prefix' => 'RCT', 'date_format' => 'Y', 'padding_length' => 6],
            ['key' => 'lab_request_number', 'label' => 'Laboratory request number', 'prefix' => 'LAB', 'date_format' => 'Ymd', 'padding_length' => 5],
            ['key' => 'prescription_number', 'label' => 'Prescription number', 'prefix' => 'RX', 'date_format' => 'Y', 'padding_length' => 6],
            ['key' => 'admission_number', 'label' => 'Admission number', 'prefix' => 'ADM', 'date_format' => 'Y', 'padding_length' => 6],
        ];
    }
}
