<?php

declare(strict_types=1);

namespace Endermanbugzjfc\FormInteractionFix_IntegratedTest;

use Closure;
use pocketmine\block\VanillaBlocks;
use pocketmine\console\ConsoleCommandSender;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\form\Form;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use RuntimeException;

/**
 * @name FormInteractionFix_IntegratedTest
 * @author Endermanbugzjfc
 * @api 4.0.0
 * @version intage
 * @main Endermanbugzjfc\FormInteractionFix_IntegratedTest\IntegratedTest
 * @depend FormInteractionFix
 *
 * 1. Run /fakeplayer $name interact every tick.
 * 2. Sends a menu form with exactly one button on first interaction.
 * 3. FormInteractionFix should block other interactions after the form opens.
 * 4. Await 5 seconds.
 * 5. Run /fakeplayer $name form button 0.
 * 6. FormInteractionFix unblock interactions after the form closes.
 * 7. The form should open again.
 * 7. Await 5 seconds.
 * 8. Signal server to shutdown.
 * 9. Check if the form has opened exactly twice.
 */
class IntegratedTest extends PluginBase implements Listener {
	protected function onEnable() : void {
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$timeout = 15 * 20;
		$this->getScheduler()->scheduleDelayedTask(new ClosureTask(fn() => throw new RuntimeException("Timeout: $timeout ticks")), $timeout);
	}

	protected function onDisable() : void {
		if ($this->sentCount !== 2) {
			throw new RuntimeException("Form should open twice but got $this->sentCount times");
		}
	}

	private Player $spammer;

	/**
	 * @priority MONITOR
	 */
	public function interactEveryTickWhenPlayerJoin(PlayerJoinEvent $event) : void {
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

		$this->getScheduler()->scheduleRepeatingTask(new ClosureTask(fn() => $this->controlSpammer("interact")), 1);
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

		// Script plugin cannot load virion so I have to write form in JSON. :/
		$event->getPlayer()->sendForm(new class(function () : void {
			$this->sent = false;
			if ($this->sentCount === 1) {
				$this->getScheduler()->scheduleDelayedTask(new ClosureTask(fn() => $this->getServer()->shutdown()), 5 * 20);
			}
		}) implements Form {
			public function __construct(private Closure $close) {
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
				($this->close)();
			}
		});

		$this->getScheduler()->scheduleDelayedTask(new ClosureTask(fn() => $this->controlSpammer("form button 0")), 5 * 20);

		$this->sent = true;
		$this->sentCount++;
		$this->getLogger()->notice("Sent form ++");
	}

	private function controlSpammer(string $subCommand) : void {
		$server = $this->getServer();
		$consoleSender = new ConsoleCommandSender($server, $server->getLanguage());

		$server->dispatchCommand($consoleSender, 'fakeplayer "' . $this->spammer->getName() . '" ' . $subCommand);
	}
}