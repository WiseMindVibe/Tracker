<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BlogController extends Controller
{
    public function index(Request $request)
    {
        $blogs = Blog::with('company')->get();

        return Inertia::render('blogs/index', [
            'blogs' => $blogs,
        ]);
    }
}
