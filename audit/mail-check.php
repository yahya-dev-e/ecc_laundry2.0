<?php
$app=require __DIR__.'/bootstrap.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['mail.default'=>'smtp','mail.mailers.smtp'=>[
    'transport'=>'smtp','scheme'=>'smtp','host'=>'127.0.0.1','port'=>8766,'timeout'=>5,
], 'mail.from'=>['address'=>'audit@example.test','name'=>'Local Audit']]);
$user=\App\Models\User::where('email','student@example.test')->firstOrFail();
$user->notify(new \App\Notifications\WelcomeNotification());
$user->notify(new \App\Notifications\ResetPasswordNotification('audit-reset-token'));
echo "Two notifications sent to localhost SMTP capture only.\n";
