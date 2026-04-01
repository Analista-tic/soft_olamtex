<?php

namespace App\Http\Controllers\Income;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CashReceiptController extends Controller
{
    public function index()
    {
        return $this->notImplemented();
    }

    public function store(Request $request)
    {
        return $this->notImplemented();
    }

    public function show($saleDocument)
    {
        return $this->notImplemented();
    }

    public function update(Request $request, $saleDocument)
    {
        return $this->notImplemented();
    }

    public function destroy($saleDocument)
    {
        return $this->notImplemented();
    }

    protected function notImplemented()
    {
        return response()->json([
            'message' => 'Income cash receipt endpoint not implemented yet.',
        ], Response::HTTP_NOT_IMPLEMENTED);
    }
}
