<?php

declare(strict_types=1);

namespace App\Shared\Transactions;

use App\Shared\Context\AuthenticatedContext;
use Closure;
use Illuminate\Support\Facades\DB;

final class TransactionalRowLock
{
    public function run(string $table, string|int $id, AuthenticatedContext $context, Closure $callback): mixed
    {
        if (
            preg_match('/\A[a-zA-Z_][a-zA-Z0-9_]*\z/', $table) !== 1
            || $context->operationId === null
            || $context->actorId === null
            || $context->purpose === null
        ) {
            throw new TransactionException('A trusted context and safe row table are required.');
        }

        return DB::transaction(function () use ($table, $id, $context, $callback): mixed {
            $row = DB::table($table)->where('id', $id)->lockForUpdate()->first();

            if ($row === null) {
                throw new TransactionException('The locked row does not exist.');
            }

            return $callback($row, $context);
        });
    }
}
