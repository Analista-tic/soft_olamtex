<?php

use App\Models\Production\Operation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('operation_audit_logs') || !Schema::hasTable('audit_logs')) {
            return;
        }

        $now = now();

        DB::table('operation_audit_logs')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($now) {
                $userIds = collect($rows)
                    ->pluck('changed_by')
                    ->filter()
                    ->unique()
                    ->values();

                $usersById = $userIds->isEmpty()
                    ? collect()
                    : DB::table('users')->whereIn('id', $userIds)->pluck('name', 'id');

                $payload = [];

                foreach ($rows as $row) {
                    $description = 'Cambio de operación en campo ' . $row->field_changed . '.';
                    if (!empty($row->motivo)) {
                        $description .= ' Motivo: ' . $row->motivo;
                    }

                    $payload[] = [
                        'user_id' => $row->changed_by,
                        'user_name' => $row->changed_by ? ($usersById[$row->changed_by] ?? null) : null,
                        'action' => 'updated',
                        'auditable_type' => Operation::class,
                        'auditable_id' => $row->operation_id,
                        'module' => 'Production',
                        'description' => $description,
                        'company_id' => null,
                        'old_values' => json_encode([$row->field_changed => $row->old_value], JSON_UNESCAPED_UNICODE),
                        'new_values' => json_encode([$row->field_changed => $row->new_value], JSON_UNESCAPED_UNICODE),
                        'ip_address' => null,
                        'user_agent' => null,
                        'created_at' => $row->created_at ?? $now,
                        'updated_at' => $row->updated_at ?? $now,
                    ];
                }

                if (!empty($payload)) {
                    DB::table('audit_logs')->insert($payload);
                }
            });

        Schema::dropIfExists('operation_audit_logs');
    }

    public function down(): void
    {
        if (Schema::hasTable('operation_audit_logs') || !Schema::hasTable('audit_logs')) {
            return;
        }

        Schema::create('operation_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('operation_id');
            $table->string('field_changed', 50);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->text('motivo')->nullable();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->timestamps();

            $table->foreign('operation_id')->references('id')->on('operations')->onDelete('cascade');
            $table->foreign('changed_by')->references('id')->on('users')->onDelete('set null');
            $table->index('operation_id');
        });
    }
};
