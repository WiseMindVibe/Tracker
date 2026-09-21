<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class BufferRelayController extends Controller
{
    public function bounce(Request $request, string $domain, string $clickId)
    {
        // Buffer holds no state — it just relays straight back to the blog's
        // return URL, which was passed in as a query param by BlogRelayController.
        $returnUrl = $request->query('r');

        abort_unless(
            $returnUrl && str_starts_with($returnUrl, config('app.url')),
            400
        );

        return redirect()->away($returnUrl);
    }
}
