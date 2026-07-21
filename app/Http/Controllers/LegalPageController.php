<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class LegalPageController extends Controller
{
    public function privacy(): View
    {
        return view('legal.privacy');
    }

    public function rodo(): View
    {
        return view('legal.rodo');
    }

    public function terms(): View
    {
        return view('legal.terms');
    }
}
