<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Signed link in the daily report. GET only shows a confirm button (mail scanners open links),
 * POST turns the report off (also used by Gmail/Apple one-click unsubscribe).
 */
class DailyReportUnsubscribeController extends Controller
{
    public function show(Request $request, User $user)
    {
        return response($this->page($user->daily_report
            ? '<p>Stop the daily collections email for <strong>'.e($user->email).'</strong>?</p>
               <form method="POST" action="'.e($request->fullUrl()).'"><button type="submit">Stop daily reports</button></form>'
            : '<p>Daily reports are already off for <strong>'.e($user->email).'</strong>.</p>'));
    }

    public function store(User $user)
    {
        $user->forceFill(['daily_report' => false])->save();

        return response($this->page('<p>Done. <strong>'.e($user->email).'</strong> will no longer get the daily collections email.</p>'));
    }

    protected function page(string $body): string
    {
        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
            <title>Daily reports · CoinBox</title>
            <style>body{margin:0;background:#f4f2ea;font-family:Inter,-apple-system,Segoe UI,Roboto,sans-serif;color:#333e37}
            main{max-width:440px;margin:12vh auto;padding:32px;background:#fff;border:1px solid #e3e7e2;border-radius:16px;border-top:5px solid #2ca01c}
            h1{font-size:20px;color:#141b16;margin:0 0 12px}button{background:#227f15;color:#fff;border:0;border-radius:999px;padding:12px 24px;font-weight:700;font-size:15px;cursor:pointer}</style>
            </head><body><main><h1>CoinBox daily reports</h1>'.$body.'</main></body></html>';
    }
}
