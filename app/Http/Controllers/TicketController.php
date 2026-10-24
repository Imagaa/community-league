<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class TicketController extends Controller
{
    public function index(Request $request): View
    {
        $bookings = $request->user()->bookings()
            ->with('match.venue')
            ->latest()
            ->get();

        return view('pages.tiket-saya', ['bookings' => $bookings]);
    }
}
