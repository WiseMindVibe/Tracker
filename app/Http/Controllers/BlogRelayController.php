<?php

namespace App\Http\Controllers;

use App\Models\Click;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class BlogRelayController extends Controller
{
    public function enter(Request $request, string $domain, string $clickId)
    {
        $click = Click::with('offer.blog.buffers')->findOrFail($clickId);

        if ($click->blog->domain !== $domain) {
            abort(404); // click doesn't belong to this blog domain
        }

        $buffer = $click->offer->blog->buffers()->inRandomOrder()->first();
        if (!$buffer) {
            return redirect()->away($click->offer->affiliate_link); // Path A fallback
        }

        Cookie::queue(Cookie::make(
            'ct',
            json_encode(['click_id' => $click->id, 'issued_at' => now()->timestamp]),
            /* minutes */
            5,
            null,
            $domain, /* secure */
            true, /* httpOnly */
            true,
            false,
            'lax'
        ));

        return redirect()->away(
            $buffer->buffer_url . '?' . http_build_query(['r' => route('blog.return', [
                'domain' => $domain,
                'clickId' => $click->id,
            ])])
        );
    }

    public function returnFromBuffer(Request $request, string $domain, string $clickId)
    {
        $raw = $request->cookie('ct');
        $payload = $raw ? json_decode($raw, true) : null;

        if (!$payload || $payload['click_id'] !== $clickId) {
            abort(403, 'Missing or mismatched click cookie');
        }

        if (now()->timestamp - $payload['issued_at'] > 300) {
            abort(410, 'Click session expired');
        }

        $click = Click::with('offer')->findOrFail($clickId);
        Cookie::queue(Cookie::forget('ct'));

        return redirect()->away($click->offer->affiliate_link);
    }
}
