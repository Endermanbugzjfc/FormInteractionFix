<?php

declare(strict_types=1);

namespace Endermanbugzjfc\FormInteractionFix;

use SOFe\AwaitGenerator\Await;
use SOFe\AwaitGenerator\Channel;
use SOFe\AwaitStd\AwaitStd;
use pocketmine\event\EventPriority;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;
use pocketmine\player\Player;

class PlayerSession {

	/**
	 * @var Channel<null>
	 */
	private Channel $counter;

	public function __construct(
		private Player $player,
		private AwaitStd $std,
		\Logger $log
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
		Await::f2c(function () use ($log) : \Generator {
			while ($this->player->isOnline()) {
				$count = 0;
				do {
					$new = yield from $this->logLoop($count);
				} while (is_int($new)); // Null = count unchanged in past 20 ticks.
				$log->debug($new); // Cancelled X interactions from ...
			})
		});
	}

	public function mainLoop() : \Generator {
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

				return false; // Player is not one of the targets || packet is not form request.
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
				EventPriority::LOW, // One level ahead of NORMAL.
				false,
				$this->player
			);
		}

		if ($event instanceof PlayerInteractEvent) {
			$event->cancel();
			$this->counter->sendWithoutWait(null);
		}
	}

	public function logLoop(int $count) : \Generator {
		$new = yield from $this->std->timeout($this->counter->receive(), 20, $count);
		if ($new !== null) {
			return "Cancelled $new interactions from '{$this->player->getName()}' in the past 20 ticks";
		}

		return ++$count;
	}