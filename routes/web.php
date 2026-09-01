<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ManualTimeEntryController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProjectManagement\ProjectBugController;
use App\Http\Controllers\ProjectManagement\ProjectController;
use App\Http\Controllers\ProjectManagement\ProjectFileController;
use App\Http\Controllers\ProjectManagement\ProjectCommentController;
use App\Http\Controllers\ProjectManagement\ProjectTaskController;
use App\Http\Controllers\ProductivityRuleController;
use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CustomerAuthController;
use App\Http\Controllers\CustomerDashboardController;
use App\Http\Controllers\EmployeeAuthController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');
// routes/api.php


Route::middleware('guest:customer')->group(function () {
    Route::get('/customerpannel', [CustomerAuthController::class, 'showLogin'])->name('customer.login');
    Route::get('/customerpannel/register', [CustomerAuthController::class, 'showRegister'])->name('customer.register');
    Route::post('/customerpannel/register', [CustomerAuthController::class, 'register'])->name('customer.register.store');
    Route::post('/customerpannel/login', [CustomerAuthController::class, 'login'])->name('customer.login.store');
    Route::get('/customerpannel/reset-password/{token}', [CustomerAuthController::class, 'showResetPasswordForm'])->name('customer.password.reset');
    Route::post('/customerpannel/reset-password', [CustomerAuthController::class, 'resetPassword'])->name('customer.password.update');
});

Route::middleware('guest:employee')->group(function () {
    Route::get('/employeepanel', [EmployeeAuthController::class, 'showLogin'])->name('employee.login');
    Route::post('/employeepanel/login', [EmployeeAuthController::class, 'login'])->name('employee.login.store');
});

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.store');
Route::get('/setup', [SetupController::class, 'show'])->name('setup');
Route::post('/setup', [SetupController::class, 'store'])->name('setup.store');

// Employee private chat routes
Route::middleware('auth')->group(function () {
    Route::get('/employeepanel/chat', [\App\Http\Controllers\EmployeeMessageController::class, 'show'])->name('employee.chat');
    Route::get('/employeepanel/messages', [\App\Http\Controllers\EmployeeMessageController::class, 'index'])->name('employee.messages.index');
    Route::post('/employeepanel/messages', [\App\Http\Controllers\EmployeeMessageController::class, 'store'])->name('employee.messages.store');
});

Route::middleware(['auth', 'role:admin,manager'])->group(function () {
    Route::get('/admin/chat', [\App\Http\Controllers\AdminMessageController::class, 'show'])->name('admin.chat');
    Route::get('/admin/messages', [\App\Http\Controllers\AdminMessageController::class, 'index'])->name('admin.messages.index');
    Route::post('/admin/messages', [\App\Http\Controllers\AdminMessageController::class, 'store'])->name('admin.messages.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/employees', [DashboardController::class, 'employees'])->name('employees.index');
    Route::get('/live-monitoring', [DashboardController::class, 'liveMonitoring'])->name('live-monitoring.index');
    Route::get('/live-monitoring/{employee}', [DashboardController::class, 'liveMonitoringEmployee'])->name('live-monitoring.show');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    // Reports route moved below to support both web and customer guards
    Route::get('/settings', [DashboardController::class, 'settings'])->name('settings.index');
    Route::get('/reports/export/daily', [ReportExportController::class, 'dailyCsv'])->name('reports.export.daily');
    Route::get('/reports/export/daily-json', [ReportExportController::class, 'dailyJson'])->name('reports.export.daily-json');
    Route::get('/reports/export/apps', [ReportExportController::class, 'appUsageCsv'])->name('reports.export.apps');
    Route::get('/reports/export/manual-time', [ReportExportController::class, 'manualEntriesCsv'])->name('reports.export.manual-time');
});

Route::middleware('auth:customer')->group(function () {
    Route::post('/customerpannel/logout', [CustomerAuthController::class, 'logout'])->name('customer.logout');
    Route::get('/customerpannel/dashboard', [CustomerDashboardController::class, 'index'])->name('customer.dashboard');
    // Customer private chat
    Route::get('/customerpannel/chat', [\App\Http\Controllers\CustomerMessageController::class, 'show'])->name('customer.chat');
    Route::get('/customerpannel/messages', [\App\Http\Controllers\CustomerMessageController::class, 'index'])->name('customer.messages.index');
    Route::post('/customerpannel/messages', [\App\Http\Controllers\CustomerMessageController::class, 'store'])->name('customer.messages.store');
});

Route::get('/reports', [DashboardController::class, 'reports'])
    ->middleware(['auth', 'role:admin,manager'])
    ->name('reports.index');
Route::get('/reports/employees/{employee}', [DashboardController::class, 'reportEmployee'])
    ->middleware(['auth', 'role:admin,manager'])
    ->name('reports.show');
Route::post('/reports/comments', [DashboardController::class, 'storeReportComment'])
    ->middleware(['auth', 'role:admin,manager'])
    ->name('reports.comments.store');

Route::middleware('auth:employee')->group(function () {
    Route::post('/employeepanel/logout', [EmployeeAuthController::class, 'logout'])->name('employee.logout');
    Route::get('/employeepanel/reports', [DashboardController::class, 'reports'])->name('employee.reports.index');
    Route::get('/employeepanel/reports/employees/{employee}', [DashboardController::class, 'reportEmployee'])->name('employee.reports.show');
});

Route::middleware('auth:customer')->group(function () {
    Route::get('/customerpannel/reports', [DashboardController::class, 'reports'])->name('customer.reports.index');
    Route::get('/customerpannel/reports/employees/{employee}', [DashboardController::class, 'reportEmployee'])->name('customer.reports.show');
});

Route::middleware(['auth', 'role:admin,manager'])->group(function () {
    Route::get('/employees/manage', [EmployeeController::class, 'index'])->name('employees.manage');
    Route::get('/employees/create', [EmployeeController::class, 'create'])->name('employees.create');
    Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
    Route::get('/employees/{employee}/edit', [EmployeeController::class, 'edit'])->name('employees.edit');
    Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
    Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy'])->name('employees.destroy');
    Route::get('/employees/{employee}/quick-login', [EmployeeAuthController::class, 'quickLogin'])->name('employees.quick-login');
    Route::get('/devices', [DeviceController::class, 'index'])->name('devices.index');
    Route::get('/devices/{device}/edit', [DeviceController::class, 'edit'])->name('devices.edit');
    Route::put('/devices/{device}', [DeviceController::class, 'update'])->name('devices.update');
    Route::get('/manual-time', [ManualTimeEntryController::class, 'index'])->name('manual-time.index');
    Route::get('/manual-time/create', [ManualTimeEntryController::class, 'create'])->name('manual-time.create');
    Route::post('/manual-time', [ManualTimeEntryController::class, 'store'])->name('manual-time.store');
    Route::get('/manual-time/{manualTimeEntry}/edit', [ManualTimeEntryController::class, 'edit'])->name('manual-time.edit');
    Route::put('/manual-time/{manualTimeEntry}', [ManualTimeEntryController::class, 'update'])->name('manual-time.update');
    Route::delete('/manual-time/{manualTimeEntry}', [ManualTimeEntryController::class, 'destroy'])->name('manual-time.destroy');
    Route::get('/productivity-rules', [ProductivityRuleController::class, 'index'])->name('productivity-rules.index');
    Route::get('/productivity-rules/create', [ProductivityRuleController::class, 'create'])->name('productivity-rules.create');
    Route::post('/productivity-rules', [ProductivityRuleController::class, 'store'])->name('productivity-rules.store');
    Route::get('/productivity-rules/{productivityRule}/edit', [ProductivityRuleController::class, 'edit'])->name('productivity-rules.edit');
    Route::put('/productivity-rules/{productivityRule}', [ProductivityRuleController::class, 'update'])->name('productivity-rules.update');
    Route::delete('/productivity-rules/{productivityRule}', [ProductivityRuleController::class, 'destroy'])->name('productivity-rules.destroy');
});

Route::middleware(['auth', 'role:admin,manager'])->group(function () {
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
	Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
	Route::post('/customer/users', [UserController::class, 'customerstore'])->name('customer.store');
	Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
	Route::get('/customer/{customer}/edit', [UserController::class, 'customeredit'])->name('customer.edit');
	Route::get('/customer/create', [UserController::class, 'customercreate'])->name('customer.create');
	Route::get('/customer', [UserController::class, 'customerIndex'])->name('customer.index');
	Route::put('/customer/{customer}', [UserController::class, 'customerupdate'])->name('customer.update');
	Route::delete('/customer/{customer}', [UserController::class, 'customerdestroy'])->name('customer.destroy');
    Route::get('/customer/{customer}/quick-login', [CustomerAuthController::class, 'quickLogin'])->name('customer.quick-login');
    Route::post('/customer/{customer}/send-reset-password', [UserController::class, 'sendCustomerResetPassword'])->name('customer.send-reset-password');
});

Route::middleware(['auth', 'role:admin,manager'])->prefix('pm')->name('pm.')->group(function () {
    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->whereNumber('project')->name('projects.show');

    Route::post('/projects/{project}/tasks', [ProjectTaskController::class, 'store'])->whereNumber('project')->name('tasks.store');
    Route::put('/tasks/{task}', [ProjectTaskController::class, 'update'])->whereNumber('task')->name('tasks.update');
    Route::post('/tasks/{task}/updates', [ProjectTaskController::class, 'storeUpdate'])->whereNumber('task')->name('tasks.updates.store');

    Route::post('/projects/{project}/bugs', [ProjectBugController::class, 'store'])->whereNumber('project')->name('bugs.store');
    Route::put('/bugs/{bug}', [ProjectBugController::class, 'update'])->whereNumber('bug')->name('bugs.update');

    Route::post('/projects/{project}/files', [ProjectFileController::class, 'store'])->whereNumber('project')->name('files.store');
    Route::get('/files/{file}/download', [ProjectFileController::class, 'download'])->whereNumber('file')->name('files.download');
    Route::get('/projects/{project}/comments', [ProjectCommentController::class, 'index'])->whereNumber('project')->name('projects.comments.index');
    Route::post('/projects/{project}/comments', [ProjectCommentController::class, 'store'])->whereNumber('project')->name('projects.comments.store');
    Route::get('/projects/{project}/comments/draft', [ProjectCommentController::class, 'showDraft'])->whereNumber('project')->name('projects.comments.draft.show');
    Route::post('/projects/{project}/comments/draft', [ProjectCommentController::class, 'saveDraft'])->whereNumber('project')->name('projects.comments.draft.save');
    Route::delete('/projects/{project}/comments/{comment}', [ProjectCommentController::class, 'destroy'])
        ->whereNumber('project')
        ->whereNumber('comment')
        ->name('projects.comments.destroy');
    Route::get('/projects/{project}/comments/{comment}/attachments/{attachmentIndex}', [ProjectCommentController::class, 'attachment'])
        ->whereNumber('project')
        ->whereNumber('comment')
        ->whereNumber('attachmentIndex')
        ->name('projects.comments.attachments.show');
});

Route::middleware('auth:employee')->prefix('employeepanel')->name('employee.')->group(function () {
    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->whereNumber('project')->name('projects.show');
    Route::get('/projects/{project}/comments', [ProjectCommentController::class, 'index'])->whereNumber('project')->name('projects.comments.index');
    Route::post('/projects/{project}/comments', [ProjectCommentController::class, 'store'])->whereNumber('project')->name('projects.comments.store');
    Route::get('/projects/{project}/comments/draft', [ProjectCommentController::class, 'showDraft'])->whereNumber('project')->name('projects.comments.draft.show');
    Route::post('/projects/{project}/comments/draft', [ProjectCommentController::class, 'saveDraft'])->whereNumber('project')->name('projects.comments.draft.save');
    Route::get('/projects/{project}/comments/{comment}/attachments/{attachmentIndex}', [ProjectCommentController::class, 'attachment'])
        ->whereNumber('project')
        ->whereNumber('comment')
        ->whereNumber('attachmentIndex')
        ->name('projects.comments.attachments.show');
});

Route::middleware('auth:customer')->prefix('customerpannel')->name('customer.')->group(function () {
    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->whereNumber('project')->name('projects.show');
    Route::get('/projects/{project}/comments', [ProjectCommentController::class, 'index'])->whereNumber('project')->name('projects.comments.index');
    Route::post('/projects/{project}/comments', [ProjectCommentController::class, 'store'])->whereNumber('project')->name('projects.comments.store');
    Route::get('/projects/{project}/comments/draft', [ProjectCommentController::class, 'showDraft'])->whereNumber('project')->name('projects.comments.draft.show');
    Route::post('/projects/{project}/comments/draft', [ProjectCommentController::class, 'saveDraft'])->whereNumber('project')->name('projects.comments.draft.save');
    Route::get('/projects/{project}/comments/{comment}/attachments/{attachmentIndex}', [ProjectCommentController::class, 'attachment'])
        ->whereNumber('project')
        ->whereNumber('comment')
        ->whereNumber('attachmentIndex')
        ->name('projects.comments.attachments.show');
});

Route::middleware(['auth', 'role:admin,manager'])->prefix('pm')->name('pm.')->group(function () {
    Route::get('/projects/create', [ProjectController::class, 'create'])->name('projects.create');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('/projects/{project}/edit', [ProjectController::class, 'edit'])->whereNumber('project')->name('projects.edit');
    Route::put('/projects/{project}', [ProjectController::class, 'update'])->whereNumber('project')->name('projects.update');
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->whereNumber('project')->name('projects.destroy');

    Route::delete('/files/{file}', [ProjectFileController::class, 'destroy'])->whereNumber('file')->name('files.destroy');
});
