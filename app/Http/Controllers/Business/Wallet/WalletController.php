<?php


namespace App\Http\Controllers\Business\Wallet;

use App\Http\Controllers\Controller;

class WalletController extends Controller
{
    public function index()
    {
        return view('business::wallet.index', [
            'walletData' => me()->wallet
        ]);
    }
}