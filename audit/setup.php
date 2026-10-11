<?php
$app = require __DIR__.'/bootstrap.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\Illuminate\Support\Facades\Artisan::call('migrate', ['--force'=>true]);
echo \Illuminate\Support\Facades\Artisan::output();
foreach (['student', 'admin'] as $role) {
    \App\Models\User::updateOrCreate(['email'=>"$role@example.test"], [
        'name'=>"Audit $role", 'student_id'=>"AUDIT-$role", 'room_number'=>'TEST-1',
        'password'=>\Illuminate\Support\Facades\Hash::make('Audit-only-password-42!'), 'role'=>$role,
    ]);
}
\App\Models\Machine::updateOrCreate(['name'=>'AUDIT-WASHER'], ['type'=>'washing-machine','status'=>'available','color'=>'#4338ca']);
echo "Synthetic accounts and machine prepared.\n";
