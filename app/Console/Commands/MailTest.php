<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends one plain message right now (not through the queue) and says what happened, so the mail settings of a
 * server (`MAIL_*` in .env) can be checked without placing an order or resetting a password.
 */
class MailTest extends Command
{
    protected $signature = 'mail:test {to : Alamat email penerima}';

    protected $description = 'Kirim satu email uji dengan pengaturan MAIL_* saat ini dan tampilkan hasilnya';

    public function handle(): int
    {
        $to = (string) $this->argument('to');

        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->components->error('Alamat email tidak valid.');

            return self::FAILURE;
        }

        $mailer = config('mail.default');
        $this->components->info("Mailer: {$mailer}, host: ".config("mail.mailers.{$mailer}.host", '-').', port: '.config("mail.mailers.{$mailer}.port", '-').', dari: '.config('mail.from.address'));

        if ($mailer === 'log' || $mailer === 'array') {
            $this->components->warn("MAIL_MAILER={$mailer}: email hanya ditulis ke log, tidak benar-benar dikirim. Ubah ke smtp di .env.");
        }

        try {
            Mail::raw('Ini email uji dari '.config('app.name').'. Kalau Anda menerimanya, pengiriman email sudah berfungsi.', function ($message) use ($to) {
                $message->to($to)->subject('Email uji '.config('app.name'));
            });
        } catch (Throwable $e) {
            $this->components->error('Gagal mengirim: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Email uji dikirim ke {$to}. Periksa kotak masuk (dan folder spam).");

        return self::SUCCESS;
    }
}
