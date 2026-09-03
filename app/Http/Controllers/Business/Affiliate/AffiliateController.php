<?php


namespace App\Http\Controllers\Business\Affiliate;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AffiliateController extends Controller
{
    public function index()
    {
        return view('business::coming.index');
    }
}