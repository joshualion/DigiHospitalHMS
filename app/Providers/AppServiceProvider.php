<?php

namespace App\Providers;

use App\Models\Admission;
use App\Models\AuditEvent;
use App\Models\BillableService;
use App\Models\CashierShift;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Hospital;
use App\Models\HospitalSetting;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\LabRequest;
use App\Models\LabTest;
use App\Models\NumberSequence;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\PublicSiteItem;
use App\Models\PublicSiteMedia;
use App\Models\PublicSitePage;
use App\Models\PublicSiteRevision;
use App\Models\PublicSiteSection;
use App\Models\RadiologyRequest;
use App\Models\RadiologyStudy;
use App\Models\RefundRequest;
use App\Models\StaffProfile;
use App\Models\Supplier;
use App\Models\User;
use App\Policies\AdmissionPolicy;
use App\Policies\AuditEventPolicy;
use App\Policies\BillableServicePolicy;
use App\Policies\CashierShiftPolicy;
use App\Policies\DepartmentPolicy;
use App\Policies\FacilityPolicy;
use App\Policies\HospitalPolicy;
use App\Policies\HospitalSettingPolicy;
use App\Policies\InventoryItemPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\LabRequestPolicy;
use App\Policies\LabTestPolicy;
use App\Policies\NumberSequencePolicy;
use App\Policies\PaymentPolicy;
use App\Policies\PrescriptionPolicy;
use App\Policies\PublicSiteItemPolicy;
use App\Policies\PublicSiteMediaPolicy;
use App\Policies\PublicSitePagePolicy;
use App\Policies\PublicSiteRevisionPolicy;
use App\Policies\PublicSiteSectionPolicy;
use App\Policies\RadiologyRequestPolicy;
use App\Policies\RadiologyStudyPolicy;
use App\Policies\RefundRequestPolicy;
use App\Policies\StaffProfilePolicy;
use App\Policies\SupplierPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Admission::class, AdmissionPolicy::class);
        Gate::policy(AuditEvent::class, AuditEventPolicy::class);
        Gate::policy(BillableService::class, BillableServicePolicy::class);
        Gate::policy(CashierShift::class, CashierShiftPolicy::class);
        Gate::policy(Department::class, DepartmentPolicy::class);
        Gate::policy(Facility::class, FacilityPolicy::class);
        Gate::policy(Hospital::class, HospitalPolicy::class);
        Gate::policy(HospitalSetting::class, HospitalSettingPolicy::class);
        Gate::policy(InventoryItem::class, InventoryItemPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(LabRequest::class, LabRequestPolicy::class);
        Gate::policy(LabTest::class, LabTestPolicy::class);
        Gate::policy(NumberSequence::class, NumberSequencePolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(Prescription::class, PrescriptionPolicy::class);
        Gate::policy(PublicSiteItem::class, PublicSiteItemPolicy::class);
        Gate::policy(PublicSiteMedia::class, PublicSiteMediaPolicy::class);
        Gate::policy(PublicSitePage::class, PublicSitePagePolicy::class);
        Gate::policy(PublicSiteRevision::class, PublicSiteRevisionPolicy::class);
        Gate::policy(PublicSiteSection::class, PublicSiteSectionPolicy::class);
        Gate::policy(RadiologyRequest::class, RadiologyRequestPolicy::class);
        Gate::policy(RadiologyStudy::class, RadiologyStudyPolicy::class);
        Gate::policy(RefundRequest::class, RefundRequestPolicy::class);
        Gate::policy(StaffProfile::class, StaffProfilePolicy::class);
        Gate::policy(Supplier::class, SupplierPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
    }
}
