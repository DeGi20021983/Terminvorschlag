<?php

namespace App\Console\Commands;

use App\Services\RetentionService;
use Illuminate\Console\Command;

class PurgeExpiredData extends Command
{
    protected $signature = 'terminvorschlag:loeschen';

    protected $description = 'Löscht personenbezogene Buchungsdaten nach Terminende und alte Protokolleinträge';

    public function handle(RetentionService $retention): int
    {
        $run = $retention->run(now()->toImmutable());

        if ($run->status !== 'success') {
            $this->error('Löschlauf fehlgeschlagen: '.$run->message);

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Löschlauf erfolgreich: %d Buchungen entfernt, %d Termine als belegt markiert, %d Wunschdatensätze, %d Protokolleinträge, %d Konten.',
            $run->deleted_bookings,
            $run->anonymized_slots,
            $run->deleted_wish_records,
            $run->deleted_audit_entries,
            $run->deleted_users,
        ));

        return self::SUCCESS;
    }
}
