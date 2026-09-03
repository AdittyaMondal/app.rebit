<?php


namespace App\Http\Controllers\User\Language;

use App\Http\Controllers\Controller;
use App\Support\Languages;

class LanguageController extends Controller
{
    private $appLanguages;

    public function __construct(Languages $appLanguages)
    {
        $this->appLanguages = $appLanguages;
    }

    public function switchLanguage(string $lang)
    {
        $this->appLanguages->switchLanguage($lang);

        return redirect()->back();
    }
}