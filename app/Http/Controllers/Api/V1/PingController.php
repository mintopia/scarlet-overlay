<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\PingResource;
use Illuminate\Http\Request;

class PingController extends Controller
{
    public function ping(): PingResource
    {
        return new PingResource([]);
    }
}
