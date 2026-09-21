<?php

namespace App\Http\Controllers;

use App\Models\AffiliateAccount;
use Illuminate\Http\Request;

class testController extends Controller
{
    public function test(Request $request)
    {

        $affiliateAccount = AffiliateAccount::where('id', 1)->first();

        dd($affiliateAccount);
    }
}
