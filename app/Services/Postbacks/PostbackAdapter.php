<?php

namespace App\Services\Postbacks;

use Illuminate\Http\Request;

interface PostbackAdapter
{
    public function parse(Request $request): PostbackData;
}
