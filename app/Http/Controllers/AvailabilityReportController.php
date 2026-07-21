<?php

namespace App\Http\Controllers;

use App\Models\Facility;
use App\Services\AvailabilityReportService;
use Illuminate\Http\Request;

class AvailabilityReportController extends Controller
{
    public function index(Request $request, AvailabilityReportService $service)
    {
        return view('reports-index', [
            'report' => $service->globalReport($request->user()),
        ]);
    }

    public function facility(Request $request, Facility $facility, AvailabilityReportService $service)
    {
        return view('reports-facility', [
            'report' => $service->facilityReport($facility),
        ]);
    }
}
