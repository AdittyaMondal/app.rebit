<?php


namespace App\Enums\Store;

enum StoreStatus: string
{
	case ACTIVE = 'active';
	case INACTIVE = 'inactive';
	case SUSPENDED = 'suspended';
}
