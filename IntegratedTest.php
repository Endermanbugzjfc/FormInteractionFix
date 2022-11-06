<?php

declare(strict_types=1);

namespace Endermanbugzjfc\FormInteractionFix_IntegratedTest;

use Closure;
use Logger;
use muqsit\fakeplayer\network\FakePlayerNetworkSession;
use pocketmine\block\VanillaBlocks;
use pocketmine\console\ConsoleCommandSender;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\form\Form;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\ModalFormResponsePacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use RuntimeException;
use function mt_rand;

/**
 * @name FormInteractionFix_IntegratedTest
 * @author Endermanbugzjfc
 * @api 4.0.0
 * @version intage
 * @main Endermanbugzjfc\FormInteractionFix_IntegratedTest\IntegratedTest
 * @depend FormInteractionFix
 *
 * 0.  Kick BlahCoast30765 after 1 tick.
 * 1.  Run /fakeplayer $name interact every 2 ticks.
 * 2.  Sends a menu form with exactly one button on first interaction.
 * 3.  FormInteractionFix should block other interactions after the form opens.
 * 4.  Await 1 second.
 * 5.  Run /fakeplayer $name form button 0.
 * 6.  FormInteractionFix unblock interactions after the form closes.
 * 7.  The form should open again.
 * 8.  Await 1 second.
 * 9.  Check if the form has opened exactly twice.
 * 10. Kick the spammer.
 * 11. Await 1 second.
 * 12. Signal server to shutdown.
 */
class IntegratedTest extends PluginBase implements Listener {
	protected function onEnable() : void {
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$timeout = 15 * 20;
		$this->getScheduler()->scheduleDelayedTask(new ClosureTask(fn() => throw new RuntimeException("Timeout: $timeout ticks")), $timeout);
		$this->getScheduler()->scheduleRepeatingTask(new ClosureTask(fn() => $this->sudo("status")), 3 * 20);
	}

	private Player $spammer;

	private int $formIdCounter = 0;

	/**
	 * @priority MONITOR
	 */
	public function interactEveryTickWhenPlayerJoin(PlayerJoinEvent $event) : void {
		if ($event->getPlayer()->getName() === "BlahCoast30765") {
			$this->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($event) : void {
				$event->getPlayer()->kick();
			}), 1); // Cancelling PlayerLoginEvent crashes FakePlayer...
			return;
		}

		if (isset($this->spammer)) {
			return;
		}
		$this->spammer = $event->getPlayer();
		$this->spammer->getInventory()->setItemInHand(VanillaItems::DIAMOND_SWORD());
		$pos = $this->spammer->getPosition();
		for ($x = $pos->getFloorX() - 2; $x <= $pos->getFloorX() + 2; $x++) {
			for ($y = $pos->getFloorY() - 2; $y <= $pos->getFloorY() + 2; $y++) {
				for ($z = $pos->getFloorZ() - 2; $z <= $pos->getFloorZ() + 2; $z++) {
					$pos->getWorld()->setBlockAt($x, $y, $z, VanillaBlocks::BEDROCK());
				}
			}
		}

		$this->getScheduler()->scheduleRepeatingTask(new ClosureTask(fn() => $this->controlSpammer("interact")), 2);
	}

	private bool $sent = false;

	private int $sentCount = 0;

	/**
	 * @priority NORMAL
	 */
	public function sendFormWhenInteract(PlayerInteractEvent $event) : void {
		if ($event->getPlayer() !== $this->spammer) {
			return;
		}

		if ($this->sent) {
			throw new RuntimeException("Form interaction fix failed");
		}

		if ($this->sentCount >= 2) {
			$this->getLogger()->info("Not opening form because sent count $this->sentCount >= 2");
			return;
		}

		// Script plugin cannot load virion so I have to write form in JSON. :/
		$event->getPlayer()->sendForm(new class(function () : void {
			$this->sent = false;
			if ($this->sentCount === 1) {
				$this->getScheduler()->scheduleDelayedTask(new ClosureTask(function () : void {
					if ($this->sentCount !== 2) {
						throw new RuntimeException("Form should open twice but got $this->sentCount times");
					}
					$this->spammer->kick();

					$this->getScheduler()->scheduleDelayedTask(new ClosureTask(fn() => $this->getServer()->shutdown()), 20);
				}), 20);
			}
		}, $this->getLogger()) implements Form {
			public function __construct(private Closure $close, private Logger $log) {
			}

			/**
			 * @return array{type: string, title: string, content: string, buttons: array{text: string}}
			 */
			public function jsonSerialize() : array {
				return [
					"type" => "form", // Menu form.
					"title" => "",
					"content" => "",
					"buttons" => ["text" => ""],
				];
			}

			public function handleResponse(Player $_, $__) : void {
				$this->log->notice("Closing form");
				($this->close)();
			}
		});

		$session = $this->spammer->getNetworkSession();
		if (!$session instanceof FakePlayerNetworkSession) {
			throw new RuntimeException("Network Session of " . $this->spammer->getName() . " is not under FakePlayer");
		}

		// https://github.com/pmmp/BedrockProtocol/commit/c2778039544fa0c7c5bd3af7963149e7552f4215#diff-f314d4f2858bb33c6ee1be30031ed2a3598ed87fd041d34e9321aea68bb0b1e5
		$cancelParams = [
			$formIdCount = $this->formIdCounter++,
			ModalFormResponsePacket::CANCEL_REASON_CLOSED
		];
		$currentProtocol = ProtocolInfo::CURRENT_PROTOCOL;
		$currentProtocol = mt_rand($currentProtocol, $currentProtocol); // Blame PHPStan.
		$response = new DataPacketReceiveEvent($session, $currentProtocol >= 544
			? ModalFormResponsePacket::cancel(...$cancelParams)
			: ModalFormResponsePacket::create($formIdCount, "null") // @phpstan-ignore-line Call to private static method create() of class pocketmine\network\mcpe\protocol\ModalFormResponsePacket.
		);
		$this->getScheduler()->scheduleDelayedTask(new ClosureTask(fn() => $response->call()), 20);

		$this->sent = true;
		$this->sentCount++;
		$this->getLogger()->notice("Sent form ++");
	}

	private function controlSpammer(string $subCommand) : void {
		$this->sudo('fakeplayer "' . $this->spammer->getName() . '" ' . $subCommand);
	}

	private function sudo(string $cmd) : void {
		$this->getLogger()->info("Sudo: $cmd");

		$server = $this->getServer();
		$consoleSender = new ConsoleCommandSender($server, $server->getLanguage());
		$this->getServer()->dispatchCommand($consoleSender, $cmd);
	}
}