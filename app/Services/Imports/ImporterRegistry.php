<?php

namespace App\Services\Imports;

use App\Enums\DataImportType;

class ImporterRegistry
{
    public function __construct(
        private readonly UserImporter $users,
        private readonly ThirdPartyImporter $thirdParties,
        private readonly DriverImporter $drivers,
        private readonly VehicleImporter $vehicles,
    ) {}

    public function for(DataImportType $type): AbstractImporter
    {
        return match ($type) {
            DataImportType::Users => $this->users,
            DataImportType::ThirdParties => $this->thirdParties,
            DataImportType::Drivers => $this->drivers,
            DataImportType::Vehicles => $this->vehicles,
        };
    }
}
