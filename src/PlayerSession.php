<?php

declare(strict_types=1);

namespace Endermanbugzjfc\FormInteractionFix;

use SOFe\AwaitGenerator\Await;
use SOFe\AwaitGenerator\Channel;
use SOFe\AwaitStd\AwaitStd;
use SOFe\AwaitStd\DisposeException;
use pocketmine\player\Player;

class PlayerSession {

	private const FORM = "form";
	private const DIALOGUE = "dialogue";

	/**
	 * @var Channel<string> Enum of above constants.
	 */
	private Channel $sent;
	/**
	 * @var Channel<string> Enum of above constants.
	 */
	private Channel $responsed;

	public function __construct(
		private Player $player,
		private AwaitStd $std,
		private \Logger $log // Assertions are logged as runtime exceptions with player name specified.
	) {
		[
			$this->sent,
			$this->responsed
		] = array_fill(0, 2, new Channel());

		$this->loop($this->mainLoop());
	}

	/**
	 * @param \Generator<mixed, mixed, mixed, bool> $gen False = continue looping; True = break.
	 */
	public function loop(\Generator $gen) : void {
		Await::f2c(function () use ($gen) {
			try {
				while ($this->player->isOnline()) {
					if (yield from $gen) {
						break;
					}
				}
			} catch (DisposeException $_) {
				// Player quits.
			}
		});
	}

	/**
	 * @return \Generator<mixed, mixed, mixed, bool> This loop is supposed to only break when player quits and so returns false.
	 */
	public function mainLoop() : \Generator {
		$sent = yield from $this->sent->get();
		$unblock = $this->blockInteraction();

		$resp = yield from $this->resp->get();
		if ($resp !== $sent) {
			$this->log->logException($this->player->getName() . "'s session expects $sent resp, got $resp resp");
		}

		$unblock();
		return false;
	}
}
