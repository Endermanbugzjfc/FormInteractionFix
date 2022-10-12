<?php

declare(strict_types=1);

namespace Endermanbugzjfc\FormInteractionFix;

use SOFe\AwaitGenerator\Await;
use pocketmine\event\EventPriority;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;

class PlayerSession {

	public function eventLoop() : \Generator {
		yield from $this->std->awaitEvent(
			DataPacketSendEvent::class,
			function (DataPacketSendEvent $event) : bool {
				if (in_array($this->player, $event->getTargets(), true)) {
					foreach ($event->getPackets() as $pk) {
						if ($pk instanceof ModalFormRequestPacket) {
							return true;
						}
					}
				}

				return false;
			},
			false,
			EventPriority::MONITOR,
			false,
			$this->player
		);
		[, $event] = yield from Await::race([
			$this->std->awaitEvent(
				DataPacketReceiveEvent::class,
				fn(DataPacketReceiveEvent $event) : bool => $event->getOrigin() === $this->player && $event->getPacket() instanceof ModalFormResponsePacket,
				false,
				EventPriority::MONITOR,
				false,
				$this->player
			]),
			$this->std->awaitEvent(
				PlayerInteractEvent::class,
				fn(PlayerInteractEvent $event) : bool => $event->getPlayer() === $this->player,
				false,
				EventPriority::LOW,
				false,
				$this->player
			);
		}

		if ($event instanceof PlayerInteractEvent) {
			$event->cancel();
		}
	}