<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use Illuminate\Http\Request;

abstract class ApiController extends Controller
{
    protected function merchant(Request $request): Merchant
    {
        return $request->attributes->get('merchant');
    }

    /** Accept "txn_01J..." or a raw id. */
    protected function stripPrefix(string $id, string $prefix): string
    {
        return str_starts_with($id, $prefix.'_') ? substr($id, strlen($prefix) + 1) : $id;
    }

    protected function notFound(string $what = 'Resource')
    {
        return response()->json(['error' => ['code' => 'not_found', 'message' => "{$what} not found."]], 404);
    }
}
