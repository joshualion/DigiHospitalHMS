<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\FacilityController;
use App\Http\Controllers\Admin\HospitalProfileController;
use App\Http\Controllers\Admin\HospitalSettingController;
use App\Http\Controllers\Admin\NumberSequenceController;
use App\Http\Controllers\Admin\PatientController;
use App\Http\Controllers\Admin\PublicWebsiteController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\ProfileController;
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
Route::get('/policies', [PublicSiteController::class, 'page'])->defaults('slug', 'policies')->name('policies');
Route::get('/preview/public-site/{page}', [PublicSiteController::class, 'preview'])->name('public.preview');

Route::middleware(['auth', 'role:superadmin|admin|hospital-admin|receptionist|doctor|nurse'])
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
