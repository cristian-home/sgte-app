<?php

namespace App\Http\Controllers;

use App\Models\Fuec;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Public, unauthenticated FUEC verification endpoint. Scanned from
 * the QR code embedded on the PDF. Renders a plain Blade view
 * (NOT Inertia) so it works without the JS bundle + without auth.
 */
class FuecVerifyController extends Controller
{
    public function show(Request $request, string $uuid): View
    {
        $fuec = Fuec::query()
            ->where('uuid', $uuid)
            ->with([
                'service:id,service_date,vehicle_id,driver_id,contract_id,origin_municipality_id,destination_municipality_id',
                'service.vehicle:id,plate',
                'service.driver:id,first_name,first_lastname',
                'service.contract:id,contract_number,third_party_id',
                'service.contract.thirdParty:id,company_name,first_name,first_lastname,is_natural_person',
                'service.originMunicipality:id,name',
                'service.destinationMunicipality:id,name',
                'fuecNumberRange:id,resolution_number,resolution_year',
            ])
            ->firstOrFail();

        return view('fuecs.verify', [
            'fuec' => $fuec,
        ]);
    }
}
