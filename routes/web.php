<?php

use App\Http\Controllers\Admin\AdmissionController;
use App\Http\Controllers\Admin\AppointmentController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BillingController;
use App\Http\Controllers\Admin\BloodBankController;
use App\Http\Controllers\Admin\ClinicalEncounterController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\EmarController;
use App\Http\Controllers\Admin\FacilityController;
use App\Http\Controllers\Admin\HospitalProfileController;
use App\Http\Controllers\Admin\HospitalSettingController;
use App\Http\Controllers\Admin\InpatientChartController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\LaboratoryController;
use App\Http\Controllers\Admin\NumberSequenceController;
use App\Http\Controllers\Admin\PatientController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PrescriptionController;
use App\Http\Controllers\Admin\ProcurementController;
use App\Http\Controllers\Admin\PublicWebsiteController;
use App\Http\Controllers\Admin\RadiologyController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicAppointmentRequestController;
use App\Http\Controllers\PublicSiteController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [PublicSiteController::class, 'home'])->name('home');
Route::get('/about', [PublicSiteController::class, 'page'])->defaults('slug', 'about')->name('about');
Route::get('/services', [PublicSiteController::class, 'page'])->defaults('slug', 'services')->name('services');
Route::get('/departments', [PublicSiteController::class, 'page'])->defaults('slug', 'departments')->name('departments');
Route::get('/doctors', [PublicSiteController::class, 'page'])->defaults('slug', 'doctors')->name('doctors');
Route::get('/doctor', [PublicSiteController::class, 'page'])->defaults('slug', 'doctors')->name('doctor');
Route::get('/doctors/{slug}', [PublicSiteController::class, 'doctor'])->name('doctors.show');
Route::get('/news', [PublicSiteController::class, 'page'])->defaults('slug', 'news')->name('news');
Route::get('/news/{slug}', [PublicSiteController::class, 'article'])->name('news.show');
Route::get('/blog', [PublicSiteController::class, 'page'])->defaults('slug', 'news')->name('blog');
Route::get('/contact', [PublicSiteController::class, 'page'])->defaults('slug', 'contact')->name('contact');
Route::get('/appointment', [PublicSiteController::class, 'page'])->defaults('slug', 'appointment')->name('appointment');
Route::get('/appointment/request', [PublicAppointmentRequestController::class, 'create'])->name('appointment.request');
Route::post('/appointment/request', [PublicAppointmentRequestController::class, 'store'])->name('appointment.request.store');
Route::get('/policies', [PublicSiteController::class, 'page'])->defaults('slug', 'policies')->name('policies');
Route::get('/preview/public-site/{page}', [PublicSiteController::class, 'preview'])->name('public.preview');

Route::middleware(['auth', 'role:superadmin|admin|hospital-admin|receptionist|doctor|nurse|cashier|accountant|laboratory-scientist|radiology-staff|pharmacist|storekeeper|blood-bank-staff'])
    ->prefix('admin')
    ->group(function () {
        Route::get('/dashboard', function () {
            abort_unless(request()->user()->hasRole('superadmin') || request()->user()->can('hospital.view'), 403);

            return Inertia::render('Admin/Dashboard');
        })->name('admin.dashboard');

        Route::get('hospital', [HospitalProfileController::class, 'edit'])->name('admin.hospital.edit');
        Route::patch('hospital', [HospitalProfileController::class, 'update'])->name('admin.hospital.update');

        Route::get('facilities', [FacilityController::class, 'index'])->name('admin.facilities.index');
        Route::post('facilities', [FacilityController::class, 'store'])->name('admin.facilities.store');
        Route::patch('facilities/{facility}', [FacilityController::class, 'update'])->name('admin.facilities.update');
        Route::patch('facilities/{facility}/status', [FacilityController::class, 'status'])->name('admin.facilities.status');

        Route::get('departments', [DepartmentController::class, 'index'])->name('admin.departments.index');
        Route::post('departments', [DepartmentController::class, 'store'])->name('admin.departments.store');
        Route::patch('departments/{department}', [DepartmentController::class, 'update'])->name('admin.departments.update');

        Route::get('staff', [StaffController::class, 'index'])->name('admin.staff.index');
        Route::post('staff', [StaffController::class, 'store'])->name('admin.staff.store');
        Route::patch('staff/{staffProfile}', [StaffController::class, 'update'])->name('admin.staff.update');
        Route::patch('staff/{staffProfile}/status', [StaffController::class, 'status'])->name('admin.staff.status');

        Route::get('roles', [RoleController::class, 'index'])->name('admin.roles');
        Route::patch('roles/{role}', [RoleController::class, 'update'])->name('admin.roles.update');

        Route::get('audit-logs', [AuditLogController::class, 'index'])->name('admin.audit.index');
        Route::get('settings', [HospitalSettingController::class, 'edit'])->name('admin.settings.edit');
        Route::patch('settings', [HospitalSettingController::class, 'update'])->name('admin.settings.update');
        Route::get('numbering', [NumberSequenceController::class, 'index'])->name('admin.numbering.index');
        Route::patch('numbering/{sequence}', [NumberSequenceController::class, 'update'])->name('admin.numbering.update');
        Route::post('numbering/{sequence}/allocate', [NumberSequenceController::class, 'allocate'])->name('admin.numbering.allocate');

        Route::get('patients', [PatientController::class, 'index'])->name('admin.patients.index');
        Route::post('patients', [PatientController::class, 'store'])->name('admin.patients.store');
        Route::get('patients/{patient}', [PatientController::class, 'show'])->name('admin.patients.show');
        Route::patch('patients/{patient}', [PatientController::class, 'update'])->name('admin.patients.update');
        Route::patch('patients/{patient}/status', [PatientController::class, 'status'])->name('admin.patients.status');
        Route::post('patients/{patient}/allergies', [PatientController::class, 'storeAllergy'])->name('admin.patients.allergies.store');
        Route::post('patients/{patient}/alerts', [PatientController::class, 'storeAlert'])->name('admin.patients.alerts.store');

        Route::get('appointments', [AppointmentController::class, 'index'])->name('admin.appointments.index');
        Route::post('appointments', [AppointmentController::class, 'store'])->name('admin.appointments.store');
        Route::get('appointments/availability', [AppointmentController::class, 'availability'])->name('admin.appointments.availability');
        Route::patch('appointments/{appointment}/transition', [AppointmentController::class, 'transition'])->name('admin.appointments.transition');
        Route::post('appointments/{appointment}/check-in', [AppointmentController::class, 'checkIn'])->name('admin.appointments.check-in');
        Route::post('appointments/walk-ins', [AppointmentController::class, 'walkIn'])->name('admin.walk-ins.store');
        Route::get('queues', [AppointmentController::class, 'queue'])->name('admin.queues.index');
        Route::patch('queues/{queueEntry}', [AppointmentController::class, 'queueTransition'])->name('admin.queues.transition');
        Route::patch('appointment-requests/{appointmentRequest}', [AppointmentController::class, 'requestReview'])->name('admin.appointment-requests.review');
        Route::post('clinician-schedules', [AppointmentController::class, 'storeSchedule'])->name('admin.clinician-schedules.store');
        Route::post('clinician-unavailability', [AppointmentController::class, 'storeUnavailability'])->name('admin.clinician-unavailability.store');

        Route::get('clinical/worklist', [ClinicalEncounterController::class, 'worklist'])->name('admin.clinical.worklist');
        Route::post('visits/{visit}/encounter', [ClinicalEncounterController::class, 'start'])->name('admin.visits.encounter.start');
        Route::get('encounters/{encounter}', [ClinicalEncounterController::class, 'show'])->name('admin.encounters.show');
        Route::post('encounters/{encounter}/vitals', [ClinicalEncounterController::class, 'vitals'])->name('admin.encounters.vitals.store');
        Route::patch('encounters/{encounter}/assessment', [ClinicalEncounterController::class, 'assessment'])->name('admin.encounters.assessment.update');
        Route::post('encounters/{encounter}/diagnoses', [ClinicalEncounterController::class, 'diagnosis'])->name('admin.encounters.diagnoses.store');
        Route::patch('encounters/{encounter}/transition', [ClinicalEncounterController::class, 'transition'])->name('admin.encounters.transition');
        Route::post('encounters/{encounter}/amendments', [ClinicalEncounterController::class, 'amendment'])->name('admin.encounters.amendments.store');

        Route::get('admissions', [AdmissionController::class, 'index'])->name('admin.admissions.index');
        Route::post('admissions/bed-classes', [AdmissionController::class, 'storeBedClass'])->name('admin.admissions.bed-classes.store');
        Route::post('admissions/wards', [AdmissionController::class, 'storeWard'])->name('admin.admissions.wards.store');
        Route::post('admissions/rooms', [AdmissionController::class, 'storeRoom'])->name('admin.admissions.rooms.store');
        Route::post('admissions/beds', [AdmissionController::class, 'storeBed'])->name('admin.admissions.beds.store');
        Route::patch('admissions/beds/{bed}/state', [AdmissionController::class, 'bedState'])->name('admin.admissions.beds.state');
        Route::post('admissions/requests', [AdmissionController::class, 'requestAdmission'])->name('admin.admissions.requests.store');
        Route::patch('admissions/{admission}', [AdmissionController::class, 'action'])->name('admin.admissions.action');
        Route::get('emar', [EmarController::class, 'index'])->name('admin.emar.index');
        Route::get('emar/charts/{chart}', [EmarController::class, 'show'])->name('admin.emar.charts.show');
        Route::post('emar/charts/{chart}/sync', [EmarController::class, 'sync'])->name('admin.emar.charts.sync');
        Route::post('emar/schedules/{schedule}/administer', [EmarController::class, 'administer'])->name('admin.emar.schedules.administer');
        Route::post('emar/administrations/{administration}/amendments', [EmarController::class, 'amend'])->name('admin.emar.administrations.amend');
        Route::get('inpatient', [InpatientChartController::class, 'index'])->name('admin.inpatient.index');
        Route::post('inpatient/admissions/{admission}/chart', [InpatientChartController::class, 'open'])->name('admin.inpatient.open');
        Route::get('inpatient/charts/{chart}', [InpatientChartController::class, 'show'])->name('admin.inpatient.charts.show');
        Route::post('inpatient/charts/{chart}/progress-notes', [InpatientChartController::class, 'progressNote'])->name('admin.inpatient.progress-notes.store');
        Route::post('inpatient/progress-notes/{note}/sign', [InpatientChartController::class, 'signProgressNote'])->name('admin.inpatient.progress-notes.sign');
        Route::post('inpatient/progress-notes/{note}/amendments', [InpatientChartController::class, 'amendProgressNote'])->name('admin.inpatient.progress-notes.amend');
        Route::post('inpatient/charts/{chart}/nursing-notes', [InpatientChartController::class, 'nursingNote'])->name('admin.inpatient.nursing-notes.store');
        Route::post('inpatient/charts/{chart}/observations', [InpatientChartController::class, 'observation'])->name('admin.inpatient.observations.store');
        Route::post('inpatient/charts/{chart}/intake-output', [InpatientChartController::class, 'intakeOutput'])->name('admin.inpatient.intake-output.store');
        Route::post('inpatient/charts/{chart}/care-plans', [InpatientChartController::class, 'carePlan'])->name('admin.inpatient.care-plans.store');
        Route::post('inpatient/charts/{chart}/diagnoses', [InpatientChartController::class, 'diagnosis'])->name('admin.inpatient.diagnoses.store');
        Route::post('inpatient/charts/{chart}/orders', [InpatientChartController::class, 'order'])->name('admin.inpatient.orders.store');
        Route::patch('inpatient/orders/{order}', [InpatientChartController::class, 'orderTransition'])->name('admin.inpatient.orders.transition');
        Route::post('inpatient/charts/{chart}/handovers', [InpatientChartController::class, 'handover'])->name('admin.inpatient.handovers.store');
        Route::post('inpatient/handovers/{handover}/acknowledge', [InpatientChartController::class, 'acknowledgeHandover'])->name('admin.inpatient.handovers.acknowledge');
        Route::post('inpatient/charts/{chart}/discharge-summary', [InpatientChartController::class, 'dischargeSummary'])->name('admin.inpatient.discharge-summary.store');
        Route::post('inpatient/discharge-summaries/{summary}/sign', [InpatientChartController::class, 'signDischargeSummary'])->name('admin.inpatient.discharge-summary.sign');
        Route::get('billing/catalogue', [BillingController::class, 'catalogue'])->name('admin.billing.catalogue');
        Route::post('billing/categories', [BillingController::class, 'storeCategory'])->name('admin.billing.categories.store');
        Route::post('billing/services', [BillingController::class, 'storeService'])->name('admin.billing.services.store');
        Route::patch('billing/services/{service}', [BillingController::class, 'updateService'])->name('admin.billing.services.update');
        Route::post('billing/services/{service}/prices', [BillingController::class, 'storePrice'])->name('admin.billing.prices.store');
        Route::get('billing/invoices', [BillingController::class, 'invoices'])->name('admin.invoices.index');
        Route::post('billing/invoices', [BillingController::class, 'storeInvoice'])->name('admin.invoices.store');
        Route::get('billing/invoices/{invoice}', [BillingController::class, 'showInvoice'])->name('admin.invoices.show');
        Route::post('billing/invoices/{invoice}/service-lines', [BillingController::class, 'addServiceLine'])->name('admin.invoices.service-lines.store');
        Route::post('billing/invoices/{invoice}/manual-lines', [BillingController::class, 'addManualLine'])->name('admin.invoices.manual-lines.store');
        Route::post('billing/invoices/{invoice}/issue', [BillingController::class, 'issue'])->name('admin.invoices.issue');
        Route::patch('billing/invoices/{invoice}/transition', [BillingController::class, 'transition'])->name('admin.invoices.transition');
        Route::post('billing/invoices/{invoice}/replacement', [BillingController::class, 'replacement'])->name('admin.invoices.replacement');

        Route::get('payments/workbench', [PaymentController::class, 'workbench'])->name('admin.payments.workbench');
        Route::get('payments/accounting', [PaymentController::class, 'accounting'])->name('admin.payments.accounting');
        Route::post('cashier-shifts', [PaymentController::class, 'openShift'])->name('admin.cashier-shifts.open');
        Route::patch('cashier-shifts/{shift}/close', [PaymentController::class, 'closeShift'])->name('admin.cashier-shifts.close');
        Route::patch('cashier-shifts/{shift}/review', [PaymentController::class, 'reviewShift'])->name('admin.cashier-shifts.review');
        Route::post('payments', [PaymentController::class, 'postPayment'])->name('admin.payments.post');
        Route::post('payments/{payment}/allocations', [PaymentController::class, 'allocate'])->name('admin.payments.allocations.store');
        Route::get('payments/{payment}/receipt', [PaymentController::class, 'receipt'])->name('admin.payments.receipt');
        Route::patch('payments/{payment}/reverse', [PaymentController::class, 'reverse'])->name('admin.payments.reverse');
        Route::post('payments/{payment}/refunds', [PaymentController::class, 'requestRefund'])->name('admin.payments.refunds.request');
        Route::patch('refunds/{refund}/decision', [PaymentController::class, 'decideRefund'])->name('admin.refunds.decision');
        Route::patch('refunds/{refund}/process', [PaymentController::class, 'processRefund'])->name('admin.refunds.process');

        Route::get('laboratory/catalogue', [LaboratoryController::class, 'catalogue'])->name('admin.lab.catalogue');
        Route::post('laboratory/specimen-types', [LaboratoryController::class, 'storeSpecimenType'])->name('admin.lab.specimen-types.store');
        Route::post('laboratory/units', [LaboratoryController::class, 'storeUnit'])->name('admin.lab.units.store');
        Route::post('laboratory/tests', [LaboratoryController::class, 'storeTest'])->name('admin.lab.tests.store');
        Route::post('laboratory/tests/{test}/components', [LaboratoryController::class, 'storeComponent'])->name('admin.lab.components.store');
        Route::post('laboratory/components/{component}/reference-ranges', [LaboratoryController::class, 'storeReferenceRange'])->name('admin.lab.reference-ranges.store');
        Route::post('laboratory/profiles', [LaboratoryController::class, 'storeProfile'])->name('admin.lab.profiles.store');
        Route::get('laboratory/requests', [LaboratoryController::class, 'requests'])->name('admin.lab.requests.index');
        Route::post('laboratory/requests', [LaboratoryController::class, 'storeRequest'])->name('admin.lab.requests.store');
        Route::get('laboratory/requests/{labRequest}', [LaboratoryController::class, 'show'])->name('admin.lab.requests.show');
        Route::post('laboratory/requests/{labRequest}/specimens', [LaboratoryController::class, 'collect'])->name('admin.lab.specimens.collect');
        Route::patch('laboratory/specimens/{specimen}/transition', [LaboratoryController::class, 'specimenTransition'])->name('admin.lab.specimens.transition');
        Route::post('laboratory/request-tests/{requestTest}/results', [LaboratoryController::class, 'result'])->name('admin.lab.results.store');
        Route::patch('laboratory/results/{result}/transition', [LaboratoryController::class, 'resultTransition'])->name('admin.lab.results.transition');
        Route::post('laboratory/results/{result}/critical-acknowledgement', [LaboratoryController::class, 'acknowledgeCritical'])->name('admin.lab.results.critical');
        Route::post('laboratory/requests/{labRequest}/release', [LaboratoryController::class, 'release'])->name('admin.lab.requests.release');
        Route::post('laboratory/requests/{labRequest}/amendments', [LaboratoryController::class, 'amend'])->name('admin.lab.requests.amendments.store');
        Route::get('laboratory/requests/{labRequest}/report', [LaboratoryController::class, 'report'])->name('admin.lab.requests.report');

        Route::get('radiology/catalogue', [RadiologyController::class, 'catalogue'])->name('admin.radiology.catalogue');
        Route::post('radiology/modalities', [RadiologyController::class, 'storeModality'])->name('admin.radiology.modalities.store');
        Route::post('radiology/studies', [RadiologyController::class, 'storeStudy'])->name('admin.radiology.studies.store');
        Route::get('radiology/requests', [RadiologyController::class, 'requests'])->name('admin.radiology.requests.index');
        Route::post('radiology/requests', [RadiologyController::class, 'storeRequest'])->name('admin.radiology.requests.store');
        Route::get('radiology/requests/{radiologyRequest}', [RadiologyController::class, 'show'])->name('admin.radiology.requests.show');
        Route::patch('radiology/requests/{radiologyRequest}/schedule', [RadiologyController::class, 'schedule'])->name('admin.radiology.requests.schedule');
        Route::patch('radiology/requests/{radiologyRequest}/transition', [RadiologyController::class, 'transition'])->name('admin.radiology.requests.transition');
        Route::post('radiology/requests/{radiologyRequest}/report', [RadiologyController::class, 'saveReport'])->name('admin.radiology.reports.store');
        Route::patch('radiology/reports/{report}/transition', [RadiologyController::class, 'reportTransition'])->name('admin.radiology.reports.transition');
        Route::post('radiology/reports/{report}/critical-communications', [RadiologyController::class, 'communicateCritical'])->name('admin.radiology.critical-communications.store');
        Route::patch('radiology/critical-communications/{communication}/acknowledge', [RadiologyController::class, 'acknowledgeCritical'])->name('admin.radiology.critical-communications.acknowledge');
        Route::post('radiology/reports/{report}/amendments', [RadiologyController::class, 'amend'])->name('admin.radiology.reports.amendments.store');
        Route::post('radiology/requests/{radiologyRequest}/attachments', [RadiologyController::class, 'uploadAttachment'])->name('admin.radiology.attachments.store');
        Route::patch('radiology/attachments/{attachment}/clear', [RadiologyController::class, 'clearAttachment'])->name('admin.radiology.attachments.clear');
        Route::get('radiology/attachments/{attachment}/download', [RadiologyController::class, 'downloadAttachment'])->name('admin.radiology.attachments.download');
        Route::patch('radiology/attachments/{attachment}/retire', [RadiologyController::class, 'retireAttachment'])->name('admin.radiology.attachments.retire');
        Route::get('radiology/requests/{radiologyRequest}/report', [RadiologyController::class, 'report'])->name('admin.radiology.requests.report');

        Route::get('blood-bank', [BloodBankController::class, 'index'])->name('admin.blood-bank.index');
        Route::post('blood-bank/locations', [BloodBankController::class, 'storeLocation'])->name('admin.blood-bank.locations.store');
        Route::post('blood-bank/storage-units', [BloodBankController::class, 'storeStorageUnit'])->name('admin.blood-bank.storage-units.store');
        Route::post('blood-bank/categories', [BloodBankController::class, 'storeCategory'])->name('admin.blood-bank.categories.store');
        Route::post('blood-bank/component-types', [BloodBankController::class, 'storeComponentType'])->name('admin.blood-bank.component-types.store');
        Route::post('blood-bank/screening-tests', [BloodBankController::class, 'storeScreeningTest'])->name('admin.blood-bank.screening-tests.store');
        Route::post('blood-bank/donors', [BloodBankController::class, 'storeDonor'])->name('admin.blood-bank.donors.store');
        Route::get('blood-bank/donors/{donor}', [BloodBankController::class, 'showDonor'])->name('admin.blood-bank.donors.show');
        Route::post('blood-bank/donors/{donor}/screening-decisions', [BloodBankController::class, 'screeningDecision'])->name('admin.blood-bank.donors.screening-decisions.store');
        Route::post('blood-bank/appointments', [BloodBankController::class, 'scheduleAppointment'])->name('admin.blood-bank.appointments.store');
        Route::post('blood-bank/collections', [BloodBankController::class, 'collect'])->name('admin.blood-bank.collections.store');
        Route::get('blood-bank/donations/{donation}', [BloodBankController::class, 'showDonation'])->name('admin.blood-bank.donations.show');
        Route::post('blood-bank/donations/{donation}/group-results', [BloodBankController::class, 'enterGroup'])->name('admin.blood-bank.group-results.store');
        Route::post('blood-bank/group-results/{result}/verify', [BloodBankController::class, 'verifyGroup'])->name('admin.blood-bank.group-results.verify');
        Route::post('blood-bank/donations/{donation}/screening-results', [BloodBankController::class, 'screeningResult'])->name('admin.blood-bank.screening-results.store');
        Route::post('blood-bank/screening-results/{result}/verify', [BloodBankController::class, 'verifyScreening'])->name('admin.blood-bank.screening-results.verify');
        Route::post('blood-bank/donations/{donation}/components', [BloodBankController::class, 'prepareComponent'])->name('admin.blood-bank.components.store');
        Route::patch('blood-bank/components/{component}', [BloodBankController::class, 'componentAction'])->name('admin.blood-bank.components.action');
        Route::post('blood-bank/components/{component}/amendments', [BloodBankController::class, 'amend'])->name('admin.blood-bank.components.amend');
        Route::get('inventory/catalogue', [InventoryController::class, 'catalogue'])->name('admin.inventory.catalogue');
        Route::post('inventory/locations', [InventoryController::class, 'storeLocation'])->name('admin.inventory.locations.store');
        Route::post('inventory/units', [InventoryController::class, 'storeUnit'])->name('admin.inventory.units.store');
        Route::post('inventory/items', [InventoryController::class, 'storeItem'])->name('admin.inventory.items.store');
        Route::get('inventory/stock', [InventoryController::class, 'stock'])->name('admin.inventory.stock');
        Route::post('inventory/batches/receive', [InventoryController::class, 'receiveBatch'])->name('admin.inventory.batches.receive');
        Route::patch('inventory/batches/{batch}/state', [InventoryController::class, 'setBatchState'])->name('admin.inventory.batches.state');
        Route::get('inventory/transfers', [InventoryController::class, 'transfers'])->name('admin.inventory.transfers');
        Route::post('inventory/transfers', [InventoryController::class, 'storeTransfer'])->name('admin.inventory.transfers.store');
        Route::patch('inventory/transfers/{transfer}', [InventoryController::class, 'transferAction'])->name('admin.inventory.transfers.action');
        Route::get('inventory/adjustments', [InventoryController::class, 'adjustments'])->name('admin.inventory.adjustments');
        Route::post('inventory/adjustments', [InventoryController::class, 'storeAdjustment'])->name('admin.inventory.adjustments.store');
        Route::patch('inventory/adjustments/{adjustment}/approve', [InventoryController::class, 'approveAdjustment'])->name('admin.inventory.adjustments.approve');
        Route::post('inventory/movements/{movement}/reverse', [InventoryController::class, 'reverseMovement'])->name('admin.inventory.movements.reverse');
        Route::get('inventory/reports', [InventoryController::class, 'reports'])->name('admin.inventory.reports');
        Route::get('procurement', [ProcurementController::class, 'index'])->name('admin.procurement.index');
        Route::post('procurement/suppliers', [ProcurementController::class, 'storeSupplier'])->name('admin.procurement.suppliers.store');
        Route::post('procurement/approval-limits', [ProcurementController::class, 'storeLimit'])->name('admin.procurement.approval-limits.store');
        Route::post('procurement/requisitions', [ProcurementController::class, 'storeRequisition'])->name('admin.procurement.requisitions.store');
        Route::patch('procurement/requisitions/{requisition}', [ProcurementController::class, 'requisitionAction'])->name('admin.procurement.requisitions.action');
        Route::post('procurement/purchase-orders/{purchaseOrder}/receipts', [ProcurementController::class, 'receive'])->name('admin.procurement.receipts.store');
        Route::post('procurement/receipt-lines/{line}/return', [ProcurementController::class, 'returnLine'])->name('admin.procurement.receipt-lines.return');
        Route::post('procurement/receipt-lines/{line}/reverse', [ProcurementController::class, 'reverseLine'])->name('admin.procurement.receipt-lines.reverse');

        Route::get('pharmacy/prescriptions', [PrescriptionController::class, 'index'])->name('admin.prescriptions.index');
        Route::post('pharmacy/prescriptions', [PrescriptionController::class, 'store'])->name('admin.prescriptions.store');
        Route::get('pharmacy/prescriptions/{prescription}', [PrescriptionController::class, 'show'])->name('admin.prescriptions.show');
        Route::post('pharmacy/prescriptions/{prescription}/sign', [PrescriptionController::class, 'sign'])->name('admin.prescriptions.sign');
        Route::patch('pharmacy/prescriptions/{prescription}/transition', [PrescriptionController::class, 'transition'])->name('admin.prescriptions.transition');
        Route::post('pharmacy/prescriptions/{prescription}/amendments', [PrescriptionController::class, 'amend'])->name('admin.prescriptions.amendments.store');
        Route::post('pharmacy/prescriptions/{prescription}/reviews', [PrescriptionController::class, 'review'])->name('admin.prescriptions.reviews.store');
        Route::post('pharmacy/prescriptions/{prescription}/bill', [PrescriptionController::class, 'bill'])->name('admin.prescriptions.bill');
        Route::post('pharmacy/prescription-items/{item}/dispense', [PrescriptionController::class, 'dispense'])->name('admin.prescriptions.dispense');
        Route::post('pharmacy/dispenses/{dispense}/return', [PrescriptionController::class, 'returnDispense'])->name('admin.prescriptions.returns.store');
        Route::post('pharmacy/dispenses/{dispense}/reverse', [PrescriptionController::class, 'reverseDispense'])->name('admin.prescriptions.dispenses.reverse');

        Route::get('public-website', [PublicWebsiteController::class, 'index'])->name('admin.public-website.index');
        Route::get('public-website/pages/{page}', [PublicWebsiteController::class, 'edit'])->name('admin.public-website.edit');
        Route::patch('public-website/pages/{page}', [PublicWebsiteController::class, 'updatePage'])->name('admin.public-website.pages.update');
        Route::patch('public-website/pages/{page}/theme', [PublicWebsiteController::class, 'updateTheme'])->name('admin.public-website.pages.theme');
        Route::post('public-website/pages/{page}/publish', [PublicWebsiteController::class, 'publishPage'])->name('admin.public-website.pages.publish');
        Route::post('public-website/pages/{page}/unpublish', [PublicWebsiteController::class, 'unpublishPage'])->name('admin.public-website.pages.unpublish');
        Route::patch('public-website/sections/{section}', [PublicWebsiteController::class, 'updateSection'])->name('admin.public-website.sections.update');
        Route::post('public-website/pages/{page}/items', [PublicWebsiteController::class, 'storeItem'])->name('admin.public-website.items.store');
        Route::patch('public-website/items/{item}', [PublicWebsiteController::class, 'updateItem'])->name('admin.public-website.items.update');
        Route::post('public-website/items/{item}/publish', [PublicWebsiteController::class, 'publishItem'])->name('admin.public-website.items.publish');
        Route::post('public-website/items/{item}/unpublish', [PublicWebsiteController::class, 'unpublishItem'])->name('admin.public-website.items.unpublish');
        Route::post('public-website/revisions/{revision}/restore', [PublicWebsiteController::class, 'restoreRevision'])->name('admin.public-website.revisions.restore');
        Route::post('public-website/media', [PublicWebsiteController::class, 'uploadMedia'])->name('admin.public-website.media.store');
        Route::delete('public-website/media/{media}', [PublicWebsiteController::class, 'deleteMedia'])->name('admin.public-website.media.destroy');

        Route::any('pages/{archivePath?}', function () {
            abort(410, 'The legacy CMS is archived. Use Public Website for active public-site management.');
        })->where('archivePath', '.*')->name('pages.archive');

        Route::get('/users', function () {
            return redirect()->route('admin.staff.index');
        })->name('admin.users');
    });

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('password', [ProfileController::class, 'updatePassword'])->name('password.update');
    Route::delete('profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
