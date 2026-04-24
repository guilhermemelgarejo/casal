<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountTransferController;
use App\Http\Controllers\Admin\SubscriptionAdminController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CoupleController;
use App\Http\Controllers\CreditCardStatementController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FinancialProjectController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RecurringTransactionController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth', 'has-couple', 'couple-billing'])->group(function () {
    Route::post('/onboarding/dismiss', [OnboardingController::class, 'dismiss'])->name('onboarding.dismiss');
    Route::post('/onboarding/restart', [OnboardingController::class, 'restart'])->name('onboarding.restart');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/relatorios', [ReportController::class, 'index'])->name('reports.index');

    Route::resource('cofrinhos', FinancialProjectController::class)
        ->only(['index', 'store', 'update', 'destroy']);
    Route::post('/cofrinhos/{cofrinho}/juros', [FinancialProjectController::class, 'storeInterest'])
        ->name('cofrinhos.interest.store');
    Route::delete('/cofrinhos/juros/{entry}', [FinancialProjectController::class, 'destroyInterest'])
        ->name('cofrinhos.interest.destroy');

    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

    Route::post('/transactions/credit-limit-precheck', [TransactionController::class, 'creditLimitPrecheck'])->name('transactions.credit-limit-precheck');
    Route::post('/transactions/{transaction}/credit-limit-precheck-update', [TransactionController::class, 'creditLimitPrecheckUpdate'])->name('transactions.credit-limit-precheck-update');
    Route::post('/transactions', [TransactionController::class, 'store'])->name('transactions.store');
    Route::put('/transactions/{transaction}', [TransactionController::class, 'update'])->name('transactions.update');
    Route::delete('/transactions/{transaction}', [TransactionController::class, 'destroy'])->name('transactions.destroy');
    Route::post('/transactions/{transaction}/skip-installment-month', [TransactionController::class, 'skipInstallmentMonth'])
        ->name('transactions.skip-installment-month');

    Route::get('/recorrentes', [RecurringTransactionController::class, 'index'])->name('recurring-transactions.index');
    Route::post('/recorrentes', [RecurringTransactionController::class, 'store'])->name('recurring-transactions.store');
    Route::put('/recorrentes/{recurringTransaction}', [RecurringTransactionController::class, 'update'])->name('recurring-transactions.update');
    Route::delete('/recorrentes/{recurringTransaction}', [RecurringTransactionController::class, 'destroy'])->name('recurring-transactions.destroy');
    Route::get('/accounts', [AccountController::class, 'index'])->name('accounts.index');
    Route::post('/accounts/transfer', [AccountTransferController::class, 'store'])->name('accounts.transfer');
    Route::post('/accounts', [AccountController::class, 'store'])->name('accounts.store');
    Route::put('/accounts/{account}', [AccountController::class, 'update'])->name('accounts.update');
    Route::delete('/accounts/{account}', [AccountController::class, 'destroy'])->name('accounts.destroy');

    Route::get('/faturas-cartao', [CreditCardStatementController::class, 'index'])->name('credit-card-statements.index');
    Route::post('/faturas-cartao/{account}/avulsa', [CreditCardStatementController::class, 'storeAvulsa'])
        ->name('credit-card-statements.store-avulsa');
    Route::put('/faturas-cartao/{account}/{referenceYear}/{referenceMonth}', [CreditCardStatementController::class, 'update'])
        ->whereNumber('referenceYear')
        ->whereNumber('referenceMonth')
        ->name('credit-card-statements.update');
    Route::delete('/faturas-cartao/{account}/{referenceYear}/{referenceMonth}', [CreditCardStatementController::class, 'destroy'])
        ->whereNumber('referenceYear')
        ->whereNumber('referenceMonth')
        ->name('credit-card-statements.destroy');
    Route::post('/faturas-cartao/{account}/{referenceYear}/{referenceMonth}/pagamento', [CreditCardStatementController::class, 'attachPayment'])
        ->whereNumber('referenceYear')
        ->whereNumber('referenceMonth')
        ->name('credit-card-statements.attach-payment');

    Route::get('/budgets', [BudgetController::class, 'index'])->name('budgets.index');
    Route::post('/budgets', [BudgetController::class, 'store'])->name('budgets.store');
    Route::post('/budgets/income', [BudgetController::class, 'updateIncome'])->name('budgets.income');
});

Route::middleware(['auth', 'has-couple'])->group(function () {
    Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
    Route::post('/billing/checkout', [BillingController::class, 'checkout'])->name('billing.checkout');
    Route::get('/billing/success', [BillingController::class, 'success'])->name('billing.success');
    Route::get('/billing/portal', [BillingController::class, 'portal'])->name('billing.portal');
});

Route::middleware(['auth', 'duozen-admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/assinaturas', [SubscriptionAdminController::class, 'index'])->name('subscriptions.index');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/couple', [CoupleController::class, 'index'])->name('couple.index');
    Route::post('/couple/create', [CoupleController::class, 'create'])->name('couple.create');
    Route::post('/couple/join', [CoupleController::class, 'join'])->name('couple.join');
    Route::post('/couple/invite', [CoupleController::class, 'sendInvite'])->name('couple.invite');
    Route::put('/couple/update', [CoupleController::class, 'update'])->name('couple.update');
    Route::post('/couple/transfer-billing-owner', [CoupleController::class, 'transferBillingOwner'])->name('couple.transfer-billing-owner');
    Route::post('/couple/leave', [CoupleController::class, 'leave'])->name('couple.leave');
});

require __DIR__.'/auth.php';
