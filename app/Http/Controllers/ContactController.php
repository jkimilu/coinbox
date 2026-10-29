<?php

namespace App\Http\Controllers;

use App\Support\Tickets\SupportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function store(Request $request, SupportService $support): RedirectResponse
    {
        // Honeypot: real visitors never fill this hidden field.
        if (filled($request->input('website'))) {
            return redirect()->to(url('/').'#contact')->with('contact_sent', true);
        }

        $data = $request->validateWithBag('contact', [
            'name' => 'required|string|max:100',
            'email' => 'required|email:rfc|max:150',
            'phone' => 'nullable|string|max:20',
            'company' => 'nullable|string|max:150',
            'topic' => 'required|in:sales,support',
            'message' => 'required|string|min:10|max:5000',
        ]);

        $ticket = $support->fromContactForm($data);

        return redirect()->to(url('/').'#contact')->with('contact_sent', $ticket->reference());
    }
}
