<?php

namespace App\Http\Controllers;

use App\Services\InboxService;
use Inertia\Inertia;
use Inertia\Response;

class InboxController extends Controller
{
    public function __construct(private InboxService $inbox) {}

    public function index(): Response
    {
        return Inertia::render('Inbox', [
            'notifications' => $this->inbox->build(auth()->user()),
        ]);
    }
}
