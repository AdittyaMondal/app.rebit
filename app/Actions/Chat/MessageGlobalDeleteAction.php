<?php


namespace App\Actions\Chat;

use App\Models\Message;

class MessageGlobalDeleteAction
{
	private Message $messageData;

	public function __construct(Message $messageData)
	{
		$this->messageData = $messageData;
	}

	public function execute()
	{
		$this->messageData->reactions()->delete();

		$this->messageData->linkSnapshot()->delete();

		$this->messageData->update([
			'content' => '',
			'is_deleted' => true
		]);
	}
}