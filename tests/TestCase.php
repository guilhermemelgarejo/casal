<?php

namespace Tests;

use App\Models\Transaction;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    /**
     * @param  array<int, array{category_id: int, amount: string}>  $splits
     */
    protected function createTransactionWithSplits(array $attributes, array $splits): Transaction
    {
        unset($attributes['category_id']);
        $t = Transaction::create($attributes);
        $t->syncCategorySplits($splits);

        return $t->fresh();
    }
}
