<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\DocumentExpirationNotification;
use App\Notifications\LicenseExpirationNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class CheckExpirations extends Command
{
    protected $signature = 'app:check-expirations';

    protected $description = 'Check vehicle documents and driver licenses for upcoming expirations and notify admins';

    public function handle(): int
    {
        $thresholds = [30, 15, 5];
        $admins = User::role([Role::SUPER_ADMIN->value, Role::ADMIN->value])->get();

        if ($admins->isEmpty()) {
            $this->info('No admin users found to notify.');

            return self::SUCCESS;
        }

        $this->checkVehicleDocuments($admins, $thresholds);
        $this->checkDriverLicenses($admins, $thresholds);

        $this->info('Expiration checks completed.');

        return self::SUCCESS;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, User>  $admins
     * @param  array<int>  $thresholds
     */
    private function checkVehicleDocuments($admins, array $thresholds): void
    {
        $documentFields = [
            'soat_due_date' => 'SOAT',
            'rtm_due_date' => 'RTM',
            'operation_card_due_date' => 'Tarjeta de Operación',
        ];

        foreach ($thresholds as $days) {
            $targetDate = today()->addDays($days);

            foreach ($documentFields as $field => $label) {
                $vehicles = Vehicle::whereDate($field, $targetDate)->get();

                foreach ($vehicles as $vehicle) {
                    Notification::send($admins, new DocumentExpirationNotification($vehicle, $label, $days));
                    $this->line("  Vehicle {$vehicle->plate}: {$label} expires in {$days} days");
                }
            }
        }
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, User>  $admins
     * @param  array<int>  $thresholds
     */
    private function checkDriverLicenses($admins, array $thresholds): void
    {
        foreach ($thresholds as $days) {
            $targetDate = today()->addDays($days);

            $drivers = Driver::whereDate('license_due_date', $targetDate)
                ->where('active', true)
                ->get();

            foreach ($drivers as $driver) {
                Notification::send($admins, new LicenseExpirationNotification($driver, $days));
                $this->line("  Driver {$driver->first_name} {$driver->first_lastname}: license expires in {$days} days");
            }
        }
    }
}
