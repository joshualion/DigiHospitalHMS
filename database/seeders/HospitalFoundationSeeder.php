<?php

namespace Database\Seeders;

use App\Models\AppointmentType;
use App\Models\BillableService;
use App\Models\BillableServiceCategory;
use App\Models\BloodComponentType;
use App\Models\BloodDonorCategory;
use App\Models\BloodScreeningTest;
use App\Models\Facility;
use App\Models\Hospital;
use App\Models\HospitalSetting;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryUnit;
use App\Models\LabSpecimenType;
use App\Models\LabTest;
use App\Models\LabTestComponent;
use App\Models\LabTestProfile;
use App\Models\LabUnit;
use App\Models\NumberSequence;
use App\Models\PaymentMethod;
use App\Models\RadiologyModality;
use App\Models\RadiologyStudy;
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

        AppointmentType::firstOrCreate(
            ['hospital_id' => $hospital->id, 'code' => 'CONSULT'],
            ['name' => 'Consultation', 'duration_minutes' => 30, 'is_active' => true]
        );

        $category = BillableServiceCategory::firstOrCreate(
            ['hospital_id' => $hospital->id, 'code' => 'CONSULT'],
            ['name' => 'Consultation Services', 'description' => 'Default outpatient billing category.', 'is_active' => true]
        );

        $service = BillableService::firstOrCreate(
            ['hospital_id' => $hospital->id, 'code' => 'GEN-CONSULT'],
            [
                'billable_service_category_id' => $category->id,
                'name' => 'General consultation',
                'description' => 'Default billable outpatient consultation service.',
                'is_tax_exempt' => true,
                'tax_rate_basis_points' => 0,
                'is_discount_eligible' => true,
                'is_active' => true,
            ]
        );
        $service->facilities()->syncWithoutDetaching([$facility->id]);

        foreach ($this->paymentMethods() as $method) {
            PaymentMethod::firstOrCreate(
                ['hospital_id' => $hospital->id, 'code' => $method['code']],
                $method + ['hospital_id' => $hospital->id]
            );
        }

        // Clinical, pharmacy and blood-bank catalogues must be configured by authorized staff.
        // Do not prepopulate live installations with structural example items.

    }

    private function sequences(): array
    {
        return [
            ['key' => 'patient_number', 'label' => 'Patient hospital number', 'prefix' => 'PAT', 'date_format' => 'Y', 'padding_length' => 6],
            ['key' => 'visit_number', 'label' => 'Visit number', 'prefix' => 'VIS', 'date_format' => 'Ymd', 'padding_length' => 5],
            ['key' => 'invoice_number', 'label' => 'Invoice number', 'prefix' => 'INV', 'date_format' => 'Y', 'padding_length' => 6],
            ['key' => 'receipt_number', 'label' => 'Receipt number', 'prefix' => 'RCT', 'date_format' => 'Y', 'padding_length' => 6],
            ['key' => 'lab_request_number', 'label' => 'Laboratory request number', 'prefix' => 'LAB', 'date_format' => 'Ymd', 'padding_length' => 5],
            ['key' => 'lab_accession_number', 'label' => 'Laboratory accession number', 'prefix' => 'ACC', 'date_format' => 'Ymd', 'padding_length' => 5],
            ['key' => 'lab_specimen_number', 'label' => 'Laboratory specimen label', 'prefix' => 'SPC', 'date_format' => 'Ymd', 'padding_length' => 5],
            ['key' => 'radiology_request_number', 'label' => 'Radiology request number', 'prefix' => 'RAD', 'date_format' => 'Ymd', 'padding_length' => 5],
            ['key' => 'radiology_accession_number', 'label' => 'Radiology accession number', 'prefix' => 'RAC', 'date_format' => 'Ymd', 'padding_length' => 5],
            ['key' => 'prescription_number', 'label' => 'Prescription number', 'prefix' => 'RX', 'date_format' => 'Y', 'padding_length' => 6],
            ['key' => 'purchase_order_number', 'label' => 'Purchase order number', 'prefix' => 'PO', 'date_format' => 'Y', 'padding_length' => 6],
            ['key' => 'goods_receipt_number', 'label' => 'Goods receipt number', 'prefix' => 'GRN', 'date_format' => 'Y', 'padding_length' => 6],
            ['key' => 'admission_number', 'label' => 'Admission number', 'prefix' => 'ADM', 'date_format' => 'Y', 'padding_length' => 6],
            ['key' => 'blood_donor_number', 'label' => 'Blood donor number', 'prefix' => 'BDN', 'date_format' => 'Y', 'padding_length' => 6],
            ['key' => 'blood_donation_number', 'label' => 'Blood donation number', 'prefix' => 'DON', 'date_format' => 'Y', 'padding_length' => 6],
            ['key' => 'blood_collection_number', 'label' => 'Blood collection bag number', 'prefix' => 'BAG', 'date_format' => 'Y', 'padding_length' => 6],
            ['key' => 'blood_component_number', 'label' => 'Blood component number', 'prefix' => 'BCP', 'date_format' => 'Y', 'padding_length' => 6],
            ['key' => 'blood_request_number', 'label' => 'Patient blood request number', 'prefix' => 'BTR', 'date_format' => 'Y', 'padding_length' => 6],
            ['key' => 'blood_specimen_label', 'label' => 'Patient blood specimen label', 'prefix' => 'BSP', 'date_format' => 'Ymd', 'padding_length' => 6],
            ['key' => 'blood_issue_number', 'label' => 'Blood component issue number', 'prefix' => 'BIS', 'date_format' => 'Y', 'padding_length' => 6],
        ];
    }

    private function paymentMethods(): array
    {
        return [
            ['code' => 'CASH', 'name' => 'Cash', 'type' => 'cash', 'reference_fields' => [], 'requires_open_shift' => true, 'is_active' => true],
            ['code' => 'TRANSFER', 'name' => 'Bank transfer', 'type' => 'transfer', 'reference_fields' => [['key' => 'reference', 'label' => 'Transfer reference', 'required' => true]], 'requires_open_shift' => false, 'is_active' => true],
            ['code' => 'POS', 'name' => 'POS / card', 'type' => 'card', 'reference_fields' => [['key' => 'terminal_reference', 'label' => 'Terminal reference', 'required' => true]], 'requires_open_shift' => false, 'is_active' => true],
            ['code' => 'OTHER', 'name' => 'Other approved method', 'type' => 'other', 'reference_fields' => [['key' => 'reference', 'label' => 'Reference', 'required' => false]], 'requires_open_shift' => false, 'is_active' => true],
        ];
    }
}
