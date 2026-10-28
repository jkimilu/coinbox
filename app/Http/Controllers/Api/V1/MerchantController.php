<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;

class MerchantController extends ApiController
{
    public function me(Request $request)
    {
        $m = $this->merchant($request);
        $key = $request->attributes->get('api_key');

        return response()->json([
            'id' => 'mer_'.$m->id,
            'object' => 'merchant',
            'name' => $m->name,
            'email' => $m->email,
            'status' => $m->status,
            'api_key' => ['name' => $key->name, 'prefix' => $key->prefix, 'abilities' => $key->abilities],
        ]);
    }
}
