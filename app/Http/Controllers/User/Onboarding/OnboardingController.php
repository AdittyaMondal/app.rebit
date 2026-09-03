<?php


namespace App\Http\Controllers\User\Onboarding;

use App\Http\Controllers\Controller;

class OnboardingController extends Controller
{
    public function index($step)
    {
        $stepMap = ['one' => 1, 'two' => 2, 'three' => 3, 'four' => 4];

        return view('onboarding::index', [
            'step' => $step,
            'stepNumber' => (isset($stepMap[$step]) ? $stepMap[$step] : 1),
            'totalSteps' => count($stepMap)
        ]);
    }
}