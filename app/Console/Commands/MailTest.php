<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class MailTest extends Command
{
    protected $signature = 'mail:test {email : Correo de destino para la prueba}';

    protected $description = 'Envía un correo de prueba usando el mailer configurado (Resend por defecto)';

    public function handle(): int
    {
        $email = $this->argument('email');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("El correo \"{$email}\" no es una dirección válida.");

            return self::FAILURE;
        }

        $mailer = config('mail.default');
        $resendKey = config('services.resend.key');

        if ($mailer === 'resend' && blank($resendKey)) {
            $this->error('Falta la API Key de Resend. Definí RESEND_KEY en tu archivo .env.');
            $this->info('Creala en https://resend.com/api-keys y agregala así:');
            $this->info('  RESEND_KEY=re_xxxxxxxxxxxxxxxxxxxxxxxx');

            return self::FAILURE;
        }

        $from = config('mail.from.address');
        $fromName = config('mail.from.name');

        try {
            Mail::raw(
                "Hola! Este es un correo de prueba enviado desde VendAR.\n\nSi estás leyendo esto, el envío de emails con el mailer \"{$mailer}\" funciona correctamente.",
                function ($message) use ($email) {
                    $message->to($email)->subject('Correo de prueba - VendAR');
                }
            );
        } catch (\Throwable $e) {
            $this->error('No se pudo enviar el correo: '.$e->getMessage());
            $this->info('Revisá que RESEND_KEY sea válida y que el dominio de origen esté verificado en Resend.');

            return self::FAILURE;
        }

        $this->info("Correo de prueba enviado a {$email} usando el mailer \"{$mailer}\".");

        if ($from) {
            $this->line("From: {$fromName} <{$from}>");
        }

        return self::SUCCESS;
    }
}
