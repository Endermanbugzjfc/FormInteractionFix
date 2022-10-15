<?php

declare(strict_types=1);

namespace Endermanbugzjfc\FormInteractionFix;

use SOFe\AwaitGenerator\Await;
use SOFe\AwaitGenerator\Channel;
use SOFe\AwaitStd\AwaitStd;
use SOFe\AwaitStd\DisposeException;
use pocketmine\event\EventPriority;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\player\Player;
use pocketmine\utils\Utils;

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

	private bool $blockingInteraction = false;

	public function __construct(
		private Player $player,
		private AwaitStd $std,
		private \Logger $log // Assertions are logged as runtime exceptions with player name specified.
	) {
		[
			$this->sent,
			$this->responsed
		] = array_fill(0, 2, new Channel());

		$this->loop(function () {
			$sent = yield from $this->sent->get();
			$unblock = $this->blockInteraction();

			$resp = yield from $this->resp->get();
			if ($resp !== $sent) {
				$this->weakError($this->player->getName() . "'s session expects $sent resp, got $resp resp");
			}

			$unblock();
			return false; // This loop is supposed to only break when player quits and so returns false.
		});
	}

	private function weakError(string $msg) : void {
		$this->log->debug(implode("\n", Utils::printableExceptionInfo(new \RuntimeException($msg))));
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
	 * @return callable Call to stop blocking interaction.
	 * @throws \RuntimeException when called before unlocking the previous.
	 */
	public function blockInteraction() : callable {
		if ($this->blockInteraction) {
			throw new \RuntimeException("blockInteraction() called in " . $this->player->getName() . "'s session before unlock previous might be memory leak");
		}

		$this->blockInteraction = true;
		$until = new Loading($controller = function () {
			yield;
			$this->blockingInteraction = false;
		});

		$this->loop(function () use ($until) {
			yield from Await::race([
				$this->std->awaitEvent(
					PlayerInteractEvent::class,
					fn($event) => $event->getPlayer() === $this->player,
					false,
					EventPriority::LOW, // One level ahead of NORMAL.
					false,
					$this->plasyer
				),
				$until->get()
			]);
		});

		return fn() => $controller->rewind();
	}

}
