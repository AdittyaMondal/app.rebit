<?php


namespace App\Actions\User;

class TerminateUserSessionsAction
{
	private $excludeCurrent = true;

	public function withCurrent()
	{
		$this->excludeCurrent = false;

		return $this;
	}

	public function execute()
	{
		me()->devices()->when($this->excludeCurrent, function ($query) {
			return $query->where('session_id', '!=', session()->getId());
		})->update([
			'is_terminated' => true
		]);
	}
}