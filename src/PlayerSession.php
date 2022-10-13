<?php

declare(strict_types=1);

namespace Endermanbugzjfc\FormInteractionFix;

use SOFe\AwaitGenerator\Await;
use SOFe\AwaitGenerator\Channel;
use SOFe\AwaitStd\AwaitStd;
use SOFe\AwaitStd\DisposeException;
use pocketmine\event\EventPriority;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;
use pocketmine\network\mcpe\protocol\ModalFormResponsePacket;
use pocketmine\network\mcpe\protocol\NpcDialoguePacket;
use pocketmine\network\mcpe\protocol\Packet;
use pocketmine\player\Player;

class PlayerSession {

	public function __construct(
		private Player $player,
		private AwaitStd $std,
		private \Logger $log
	) {
	}
	/**
	 * @template T of Packet
	 * @phpstan-param class-string<T> $pkType
	 * @return \Generator<mixed, mixed, mixed, T>
	 */
	public function awaitPacketReceive(string $pkType) : \Generator {
		do {
			$event = yield from $this->std->awaitEvent(
				DataPacketReceiveEvent::class,
				fn(DataPacketReceiveEvent $event) : bool => $event->getOrigin() === $this->player,
				false,
				EventPriority::MONITOR,
				false,
				$this->player
			);

			$pk = $event->getPacket();
			if ($pk instanceof $pkType) {
				return $pk;
			}
		} while (true); // Break when has suitable packet to return.
	}

	/**
	 * @template T of Packet
	 * @phpstan-param class-string<T> $pkType
	 * @return \Generator<mixed, mixed, mixed, T>
	 */
	public function awaitPacketSend(string $pkType) : \Generator {
		do {
			$event = yield from $this->std->awaitEvent(
				DataPacketSendEvent::class,
				fn(DataPacketSendEvent $event) : bool => in_array($this->player, $event->getTargets(), true),
				false,
				EventPriority::MONITOR,
				false,
				$this->player
			);

			foreach ($event->getPackets() as $pk) {
				if ($pk instanceof $pkType) {
					return $pk;
				}
			}
		} while (true); // Break when has suitable packet to return.
	}

	private const FORM_REQUEST = "form request";
	private const FORM_RESPONSE = "form response";
	private const DIALOGUE_OPEN = "dialogue open";
	private const DIALOGUE_CLOSE = "dialogue close";

	/**
	 * @return \Generator<mixed, mixed, mixed, string>
	 */
	private function awaitPacket(string ...$expect) : \Generator {
		[$got, $pk] = yield from Await::race([
			self::FORM_REQUEST => $thia->awaitPacketSend(ModalFormRequestPacket::class),
			self::FORM_RESPONSE => $thia->awaitPacketReceive(ModalFormResponsePacket::class),
			self::DIALOGUE_OPEN => $thia->awaitPacketSend(NpcDialoguePacket::class)
		]);
		if ($pk instanceof NpcDialoguePacket) {
			$action = $pk->getActionType();
			if ($action === $pk::ACTION_CLOSE) {
				$got = self::DIALOGUE_CLOSE;
			} elseif ($action !== $pk::ACTION_OPEN) {
				$got = "unsupported dialogue action '$action'";
			}
		}

		if (!in_array($got, $expect, true)) {
			$expectList = implode(" / ", $expect);
			$this->log->logException($this->player->getName() . "'s session expects $expectList, got $got");

		return $got;
	}

	/**
	 * @return \Generator<mixed, mixed, mixed, void>
	 */
	public function mainLoop() : \Generator {
		$interactRacer = match (yield from $this->awaitPacket(self::FORM_REQUEST, self::DIALOGUE_OPEN)) {
			self::FORM_REQUEST => $this->awaitPacket(self::FORM_RESPONSE),
			self::DIALOGUE_OPEN => $this->awaitPacket(self::DIALOGUE_CLOSE),
			default => null
		};
		if ($interactRacer !== null) {
			do {
				[, $event] = yield from Await::race([
					$interactRacer,
					$this->std->awaitEvent(
						PlayerInteractEvent::class,
						fn(PlayerInteractEvent $event) : bool => $event->getPlayer() === $this->player,
						false,
						EventPriority::LOW, // One level ahead of NORMAL.
						false,
						$this->player
					)
				]);

				if ($event instanceof PlayerInteractEvent) {
					$event->cancel();
				} else {
					break;
				}
			} while (true);
		}
	}
}