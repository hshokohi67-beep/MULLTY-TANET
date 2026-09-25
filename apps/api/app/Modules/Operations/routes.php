<?php

use App\Modules\Identity\Support\PermissionCatalog as P;
use App\Modules\Operations\Http\Controllers\ExpenseController;
use App\Modules\Operations\Http\Controllers\StaffController;
use App\Modules\Operations\Http\Controllers\TimeClockController;
use Illuminate\Support\Facades\Route;

Route::middleware(['tenant', 'auth:sanctum', 'actor:staff', 'tenant.member', 'feature:operations'])->group(function () {
    Route::middleware('can:'.P::EXPENSES_MANAGE)->group(function () {
        Route::get('expense-categories', [ExpenseController::class, 'categories'])->name('expense-categories.index');
        Route::post('expense-categories', [ExpenseController::class, 'storeCategory'])->name('expense-categories.store');
        Route::put('expense-categories/{expenseCategory}', [ExpenseController::class, 'updateCategory'])->name('expense-categories.update');
        Route::delete('expense-categories/{expenseCategory}', [ExpenseController::class, 'destroyCategory'])->name('expense-categories.destroy');
        Route::get('expenses/summary', [ExpenseController::class, 'summary'])->name('expenses.summary');
        Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::put('expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
        Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');
    });

    Route::middleware('can:'.P::STAFF_MANAGE)->prefix('staff')->name('staff.')->group(function () {
        Route::get('employees', [StaffController::class, 'employees'])->name('employees.index');
        Route::post('employees', [StaffController::class, 'storeEmployee'])->name('employees.store');
        Route::put('employees/{employee}', [StaffController::class, 'updateEmployee'])->name('employees.update');
        Route::get('shifts', [StaffController::class, 'shifts'])->name('shifts.index');
        Route::post('shifts', [StaffController::class, 'storeShift'])->name('shifts.store');
        Route::post('shifts/copy-week', [StaffController::class, 'copyWeek'])->name('shifts.copy');
        Route::put('shifts/{shift}', [StaffController::class, 'updateShift'])->name('shifts.update');
        Route::delete('shifts/{shift}', [StaffController::class, 'destroyShift'])->name('shifts.destroy');
        Route::get('attendance', [StaffController::class, 'attendance'])->name('attendance.index');
        Route::post('attendance', [StaffController::class, 'storeAttendance'])->name('attendance.store');
        Route::put('attendance/{attendanceRecord}', [StaffController::class, 'updateAttendance'])->name('attendance.update');
        Route::delete('attendance/{attendanceRecord}', [StaffController::class, 'destroyAttendance'])->name('attendance.destroy');
        Route::get('payroll', [StaffController::class, 'payroll'])->name('payroll');
    });

    Route::middleware('can:'.P::ATTENDANCE_SELF)->prefix('time-clock')->name('time-clock.')->group(function () {
        Route::get('me', [TimeClockController::class, 'me'])->name('me');
        Route::post('in', [TimeClockController::class, 'clockIn'])->name('in');
        Route::post('out', [TimeClockController::class, 'clockOut'])->name('out');
    });
});
