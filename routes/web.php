<?php

use App\Http\Controllers\Admin\AppointmentController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BillingController;
use App\Http\Controllers\Admin\ClinicalEncounterController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\FacilityController;
use App\Http\Controllers\Admin\HospitalProfileController;
use App\Http\Controllers\Admin\HospitalSettingController;
use App\Http\Controllers\Admin\LaboratoryController;
use App\Http\Controllers\Admin\NumberSequenceController;
use App\Http\Controllers\Admin\PatientController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PublicWebsiteController;
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

Route::middleware(['auth', 'role:superadmin|admin|hospital-admin|receptionist|doctor|nurse|cashier|accountant|laboratory-scientist'])
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
