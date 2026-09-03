<?php


namespace App\Events\User\Auth;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserPasswordChangedEvent
{
    /**
     * Currently I am not using this event, but in future I will need it
     * so, I can add any listener to this event.
     * 
     * Now, it does not have any listener and Application just ignores it.
     * 
     * (c) Team Rebit
     */

    use Dispatchable, SerializesModels;

    public array $data;

    /**
     * Create a new event instance.
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }
}