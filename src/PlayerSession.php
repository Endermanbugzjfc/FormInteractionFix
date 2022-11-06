<?php

declare(strict_types=1);

namespace Endermanbugzjfc\FormInteractionFix;

use Generator;
use Logger;
use pocketmine\event\EventPriority;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;
use pocketmine\player\Player;
use SOFe\AwaitGenerator\Await;
use SOFe\AwaitGenerator\Traverser;
use SOFe\AwaitStd\AwaitStd;
use SOFe\AwaitStd\DisposeException;
use function in_array;

class PlayerSession {
	public function __construct(
		private Player $player,
		private AwaitStd $std,
		Logger $_
	) {
		unset($_); // Blame PHPStan.

		Await::f2c(function () : Generator {
			try {
				yield from $this->mainLoop();
			} catch (DisposeException) {
			}
		});
	}

	/**
	 * @return Generator<mixed, mixed, mixed, never>
	 * @throws DisposeException
	 */
	public function packetSendIterator() : Generator {
		while (true) { // @phpstan-ignore-line
			$event = yield from $this->std->awaitEvent(
				DataPacketSendEvent::class,
				fn($event) => in_array($this->player->getNetworkSession(), $event->getTargets(), true),
				false,
				EventPriority::MONITOR,
				false,
				$this->player
			);

			foreach ($event->getPackets() as $pk) {
				yield $pk => Traverser::VALUE;
			}
		}
	}

	/**
	 * @return Generator<mixed, mixed, mixed, never>
	 * @throws DisposeException
	 */
	public function packetReceiveIterator() : Generator {
		while (true) { // @phpstan-ignore-line
			$event = yield from $this->std->awaitEvent(
				DataPacketReceiveEvent::class,
				fn($event) => $this->player->getNetworkSession() === $event->getOrigin(),
				false,
				EventPriority::MONITOR,
				false,
				$this->player
			);

			yield $event->getPacket() => Traverser::VALUE;
		}
	}

	public function mainLoop() : Generator {
		$send = new Traverser($this->packetSendIterator());
		$receive = new Traverser($this->packetReceiveIterator());

		while (true) { // @phpstan-ignore-line
			// Await receive either one of the packet below.
			// Filter out other that are useless to this plugin.
			do {
				$sent = null;
				yield from $send->next($sent);
			} while (match (true) {
				$sent instanceof ModalFormRequestPacket => false,
				default => true,
			});

			$closed = false;
			Await::f2c(function () use ($receive, &$closed) : Generator {
				try {
					do {
						$received = null;
						yield from $receive->next($received);
					} while (match (true) {
						$received instanceof ModalFormRequestPacket => false,
						default => true,
					});

					$closed = true;
				} catch (DisposeException) {
				}
			});

			$event = null;
			do {
				$event?->cancel();
				$event = yield from $this->std->awaitEvent(
					PlayerInteractEvent::class,
					fn($event) => $event->getPlayer() === $this->player,
					false,
					EventPriority::LOW, // One level below NORMAL.
					false,
					$this->player
				);
			} while (!$closed);
		}
	}
}