<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use App\Models\Page;

class PageController extends Controller
{
    public function show(Page $page)
    {
        abort_unless($page->is_published, 404);

        return view('pages.show', compact('page'));
    }

    public function faqs()
    {
        return view('pages.faqs', ['faqs' => Faq::where('is_active', true)->orderBy('sort_order')->get()]);
    }
}
