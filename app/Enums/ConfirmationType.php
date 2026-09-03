<?php


namespace App\Enums;

enum ConfirmationType:string
{
    case EMAIL = 'email';
    case PHONE = 'phone';
}