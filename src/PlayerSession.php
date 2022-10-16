<?php

declare(strict_types=1);

namespace Endermanbugzjfc\FormInteractionFix;

use SOFe\AwaitGenerator\Await;
use SOFe\AwaitGenerator\Channel;
use SOFe\AwaitGenerator\Loading;
use SOFe\AwaitStd\AwaitStd;
use SOFe\AwaitStd\DisposeException;
use pocketmine\event\EventPriority;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;
use pocketmine\network\mcpe\protocol\ModalFormResponsePacket;
use pocketmine\network\mcpe\protocol\NpcDialoguePacket;
use pocketmine\network\mcpe\protocol\NpcRequestPacket;
use pocketmine\player\Player;
use pocketmine\utils\Utils;

class PlayerSession {

	private const FORM = "form";
	private const DIALOGUE = "dialogue";

	/**
	 * Enum of above constants.
	 * @var Channel<string> 
	 */
	private Channel $opened;
	/**
	 * Enum of above constants.
	 * @var Channel<string> 
	 */
	private Channel $closed;

	/**
	 * For memory safety.
	 */
	private bool $blockingInteraction = false;

	public function __construct(
		private Player $player,
		private AwaitStd $std,
		private \Logger $log // Assertions are logged as runtime exceptions with player name specified.
	) {
		[
			$this->opened,
			$this->closed
		] = array_fill(0, 2, new Channel());

		$this->loop($this->listenSend());
		$this->loop($this->listenReceive());
		$this->loop($this->mainLoop());
	}

	/**
	 * @return \Generator<mixed, mixed, mixed, bool> This loop is supposed to only break when player quits and so returns false.
	 */
	private function mainLoop() : \Generator {
		$opened = yield from $this->opened->receive();
		$unblock = $this->blockInteraction();

		$closed = yield from $this->closed->receive();
		if ($closed !== $opened) {
			$this->weakError($this->player->getName() . "'s session expects $opened resp, got $closed resp");
		}

		$unblock();
		return false; // This loop is supposed to only break when player quits and so returns false.
	}

	/**
	 * @return \Generator<mixed, mixed, mixed, bool> This loop is supposed to only break when player quits and so returns false.
	 */
	private function listenSend() : \Generator {
		$sent = yield from $this->std->awaitEvent(
			DataPacketSendEvent::class,
			fn($event) => in_array($this->player->getNetworkSession(), $event->getTargets(), true),
			false,
			EventPriority::MONITOR,
			false,
			$this->player
		);

		foreach ($sent->getPackets() as $pk) {
			$opened = $closed = null;

			if ($pk instanceof ModalFormRequestPacket) {
				$opened = self::FORM;
			} elseif ($pk instanceof NpcDialoguePacket) {
				switch ($type = $pk->getActionType()) {
					case $pk::ACTION_CLOSE:
						$closed = self::DIALOGUE;
						break;

					case $pk::ACTION_OPEN:
						$opened = self::DIALOGUE;
						break;

					default:
						// Unsupported.
						$this->weakError("Unsupported NPC action type '$type'");
						break;
				}
			}

			if ($opened !== null) {
				$this->opened->sendWithoutWait($opened);
			}
			if ($closed !== null) {
				$this->closed->sendWithoutWait($closed);
			}
		}

		return false; // This loop is supposed to only break when player quits and so returns false.
	}

	/**
	 * @return \Generator<mixed, mixed, mixed, bool> This loop is supposed to only break when player quits and so returns false.
	 */
	private function listenReceive() : \Generator {
		$received = yield from $this->std->awaitEvent(
			DataPacketReceiveEvent::class,
			fn($event) => $this->player->getNetworkSession() === $event->getOrigin(),
			false,
			EventPriority::MONITOR,
			false,
			$this->player
		);
	
		$pk = $received->getPacket();
		if ($pk instanceof ModalFormResponsePacket) {
			$this->opened->sendWithoutWait(self::FORM);
		} elseif ($pk instanceof NpcRequestPacket) {
			switch ($type = $pk->requestType) {
				case $pk::REQUEST_EXECUTE_CLOSING_COMMANDS:
					$this->closed->sendWithoutWait(self::DIALOGUE);
					break;

				case $pk::REQUEST_SET_ACTIONS:
				case $pk::REQUEST_EXECUTE_ACTION:
				case $pk::REQUEST_SET_NAME:
				case $pk::REQUEST_SET_SKIN:
				case $pk::REQUEST_SET_INTERACTION_TEXT:
				case $pk::REQUEST_EXECUTE_OPENING_COMMANDS:
					// Unhandled.
					break;

				default:
					// Unsupported.
					$this->weakError("Unsupported NPC request type '$type'");
					break;
			}
		}
			
		return false; // This loop is supposed to only break when player quits and so returns false.
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
		if ($this->blockingInteraction) {
			throw new \RuntimeException("blockInteraction() called in " . $this->player->getName() . "'s session before unlock previous might be memory leak");
		}

		$this->blockingInteraction = true;
		$f = function () {
			yield;
			$this->blockingInteraction = false;
		};
		$controller = $f();
		$until = new Loading(fn() => yield from $controller);

		$blockInteraction = function () use ($until) {
			[, $event] = yield from Await::race([
				$this->std->awaitEvent(
					PlayerInteractEvent::class,
					fn($event) => $event->getPlayer() === $this->player,
					false,
					EventPriority::LOW, // One level ahead of NORMAL.
					false,
					$this->player
				),
				$until->get()
			]);

			if ($event instanceof PlayerInteractEvent) {
				return false; // $until have not yet resolved, continue listening and cancelling PlayerInteractEvent.
			} else {
				return true; // $until have resolved, break loop.
			}
		};
		$this->loop($blockInteraction());

		return fn() => $controller->rewind();
	}

}
