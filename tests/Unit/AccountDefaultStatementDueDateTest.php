<?php

namespace Tests\Unit;

use App\Models\Account;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccountDefaultStatementDueDateTest extends TestCase
{
    public function test_conta_corrente_ou_sem_dia_retorna_null(): void
    {
        $regular = new Account(['kind' => Account::KIND_REGULAR, 'credit_card_invoice_due_day' => 10]);
        $this->assertNull($regular->defaultStatementDueDate(4, 2026));

        $card = new Account(['kind' => Account::KIND_CREDIT_CARD, 'credit_card_invoice_due_day' => null]);
        $this->assertNull($card->defaultStatementDueDate(4, 2026));
    }

    #[DataProvider('dueCasesProvider')]
    public function test_calcula_mes_seguinte_e_respeita_ultimo_dia_do_mes(int $refM, int $refY, int $dueDay, string $expectedYmd): void
    {
        $account = new Account([
            'kind' => Account::KIND_CREDIT_CARD,
            'credit_card_invoice_due_day' => $dueDay,
        ]);
        $got = $account->defaultStatementDueDate($refM, $refY);

        $this->assertNotNull($got);
        $this->assertSame($expectedYmd, $got->toDateString());
    }

    public static function dueCasesProvider(): array
    {
        return [
            'abril ref → maio 10' => [4, 2026, 10, '2026-05-10'],
            'dezembro ref → janeiro seguinte' => [12, 2025, 5, '2026-01-05'],
            'dia 31 em fevereiro não leap' => [1, 2026, 31, '2026-02-28'],
            'dia 31 em fevereiro leap' => [1, 2024, 31, '2024-02-29'],
        ];
    }
}
