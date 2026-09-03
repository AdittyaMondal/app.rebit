<?php


namespace App\Events\Media;

use Illuminate\Foundation\Events\Dispatchable;


class MediaDeletedEvent
{
    use Dispatchable;

    public $mediaItem;

    /**
     * Create a new event instance.
     */
    public function __construct($mediaItem)
    {
        $this->mediaItem = $mediaItem;
    }
}