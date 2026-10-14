<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $column = collect(Schema::getColumns('users'))->firstWhere('name', 'role');
        if (! $column || ! in_array($column['type_name'], ['enum', 'varchar', 'char'], true)) {
            throw new RuntimeException('Unexpected users.role definition; inspect it before changing account roles.');
        }
        preg_match('/\A(?:var)?char\((\d+)\)/i', $column['type'], $length);
        Schema::table('users', function (Blueprint $table) use ($column, $length) {
            // Preserve legacy role strings; application code still controls which roles can be assigned.
            $field = $table->string('role', max(255, (int) ($length[1] ?? 255)))
                ->nullable((bool) $column['nullable'])->default('student');
            if ($column['collation']) {
                $field->collation($column['collation']);
            }
            $field->change();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Restoring a restrictive legacy enum could reject new accounts; use a reviewed restore plan.');
    }
};
