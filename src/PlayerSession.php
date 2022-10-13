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
use pocketmine\player\Player;

class PlayerSession {

	public function __construct(
		private Player $player,
		private AwaitStd $std,
		\Logger $_
	) {
		Await::f2c(function () : \Generator {
			try {
				while ($this->player->isOnline()) {
					yield from $this->mainLoop();
				}
			} catch (DisposeException $_) {
				// Player quits.
			}
		});
	}

	/**
	 * @return \Generator<mixed, mixed, mixed, void>
	 */
	public function mainLoop() : \Generator {
		yield from $this->std->awaitEvent(
			DataPacketSendEvent::class,
			function (DataPacketSendEvent $event) : bool {
				if (in_array($this->player->getNetworkSession(), $event->getTargets(), true)) {
					foreach ($event->getPackets() as $pk) {
						switch (true) {
							case $pk instanceof NpcDialoguePacket:
								return true;

							default:
								return $pk instanceof ;
						}
					}
				}

				return false; // Player is not one of the targets || packet is not form request.
			},
			false,
			EventPriority::MONITOR,
			false,
			$this->player
		);

		$event = null;
		do {
			[, $event] = yield from Await::race([
				$this->std->awaitEvent(
					DataPacketReceiveEvent::class,
					fn(DataPacketReceiveEvent $event) : bool => $event->getOrigin() === $this->player->getNetworkSession() && $event->getPacket() instanceof ModalFormResponsePacket,
					false,
					EventPriority::MONITOR,
					false,
					$this->player
				),
				$this->std->awaitEvent(
					PlayerInteractEvent::class,
					fn(PlayerInteractEvent $event) : bool => $event->getPlayer() === $this->player,
					false,
					EventPriority::LOW, // One level ahead of NORMAL.
					false,
					$this->player
				)
			]);

			$interaction = $event instanceof PlayerInteractEvent;
			if ($interaction) {
				$event->cancel();
			}
		} while ($interaction);
	}
}