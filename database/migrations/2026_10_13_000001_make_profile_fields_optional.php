<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['student_id', 'room_number'] as $name) {
            $column = collect(Schema::getColumns('users'))->firstWhere('name', $name);
            if (! $column) {
                Schema::table('users', function (Blueprint $table) use ($name) {
                    $field = $table->string($name)->nullable();
                    if ($name === 'student_id') {
                        $field->unique();
                    }
                });

                continue;
            }
            if ($column['nullable']) {
                continue;
            }
            if (! in_array($column['type_name'], ['varchar', 'char'], true)) {
                throw new RuntimeException('Unexpected users.'.$name.' type; inspect the existing schema before upgrading.');
            }
            preg_match('/\((\d+)\)/', $column['type'], $length);
            Schema::table('users', function (Blueprint $table) use ($name, $column, $length) {
                $field = $column['type_name'] === 'char'
                    ? $table->char($name, (int) ($length[1] ?? 255))
                    : $table->string($name, (int) ($length[1] ?? 255));
                if ($column['collation']) {
                    $field->collation($column['collation']);
                }
                $field->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Restoring NOT NULL would reject newly registered accounts; use a reviewed restore plan.');
    }
};
