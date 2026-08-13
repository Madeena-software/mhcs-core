<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const MAX_ATTEMPTS = 5;

    public function up(): void
    {
        Schema::table('shift_schedules', function (Blueprint $table): void {
            $table->string('display_reference', 12)->nullable()->unique();
        });
        Schema::table('image_gateway_studies', function (Blueprint $table): void {
            $table->string('display_reference', 12)->nullable()->unique();
        });

        $this->backfill('shift_schedules', 'JAD-', false);
        $this->backfill('image_gateway_studies', 'DCM-', true);

        Schema::table('shift_schedules', function (Blueprint $table): void {
            $table->string('display_reference', 12)->nullable(false)->change();
        });
        Schema::table('image_gateway_studies', function (Blueprint $table): void {
            $table->string('display_reference', 12)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('image_gateway_studies', function (Blueprint $table): void {
            $table->dropUnique('image_gateway_studies_display_reference_unique');
            $table->dropColumn('display_reference');
        });
        Schema::table('shift_schedules', function (Blueprint $table): void {
            $table->dropUnique('shift_schedules_display_reference_unique');
            $table->dropColumn('display_reference');
        });
    }

    private function backfill(string $table, string $prefix, bool $study): void
    {
        foreach (DB::table($table)->select('id')->orderBy('id')->cursor() as $row) {
            for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
                $reference = $prefix.Str::upper(Str::random(8));
                try {
                    $values = ['display_reference' => $reference];
                    if ($study) {
                        $values['filename'] = $reference.'.dcm';
                    }
                    DB::table($table)->where('id', $row->id)->update($values);

                    continue 2;
                } catch (QueryException $exception) {
                    $message = strtolower($exception->getMessage());
                    if (! str_contains($message, 'display_reference') || (! str_contains($message, 'unique') && ! str_contains($message, 'duplicate')) || $attempt === self::MAX_ATTEMPTS - 1) {
                        throw $exception;
                    }
                }
            }
        }
    }
};
