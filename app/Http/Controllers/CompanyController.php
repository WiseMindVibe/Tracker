<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Company;
use Inertia\Inertia;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('companies/index', [
            'companies' => Company::all(),
        ]);
    }
}
