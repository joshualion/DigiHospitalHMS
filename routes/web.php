<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\FacilityController;
use App\Http\Controllers\Admin\HospitalProfileController;
use App\Http\Controllers\Admin\HospitalSettingController;
use App\Http\Controllers\Admin\NumberSequenceController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Cms\PageController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Public/Home');
})->name('home');

Route::get('/about', function () {
    return Inertia::render('Public/About');
})->name('about');

Route::get('/doctor', function () {
    return Inertia::render('Public/Doctor');
})->name('doctor');

Route::get('/appointment', function () {
    return Inertia::render('Public/Appointment');
})->name('appointment');

Route::get('/blog', function () {
    return Inertia::render('Public/Blog');
})->name('blog');

Route::get('/contact', function () {
    return Inertia::render('Public/Contact');
})->name('contact');

Route::get('/policies', function () {
    return Inertia::render('Public/Policies');
})->name('policies');

Route::middleware(['auth', 'role:superadmin|admin|hospital-admin'])
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

        Route::get('pages', [PageController::class, 'index'])->name('pages.index');
        Route::get('pages/{page}/edit', [PageController::class, 'edit'])->name('pages.edit');
        Route::put('pages/{page}', [PageController::class, 'update'])->name('pages.update');

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
