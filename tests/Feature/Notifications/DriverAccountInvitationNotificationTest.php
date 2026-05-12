<?php

use App\Models\User;
use App\Notifications\DriverAccountInvitationNotification;

test('mail contains a reset link with the user email and a non-empty token', function (): void {
    $user = User::factory()->create(['email' => 'invitee@sgte.app']);

    $notification = new DriverAccountInvitationNotification;
    $mail = $notification->toMail($user);
    $array = $mail->toArray();

    expect($mail->subject)->toBe('Te invitamos a SGTE — Configura tu acceso');
    expect($array['actionText'])->toBe('Configurar contraseña');
    expect($array['actionUrl'])->toContain('/reset-password/');
    expect($array['actionUrl'])->toContain('email=invitee%40sgte.app');
});

test('mail mentions the configured expire window in minutes', function (): void {
    $user = User::factory()->create();

    $notification = new DriverAccountInvitationNotification;
    $mail = $notification->toMail($user);
    $rendered = (string) $mail->render();

    $expire = (int) config('auth.passwords.'.config('fortify.passwords').'.expire', 60);

    expect($rendered)->toContain('expira en '.$expire.' minutos');
});
