<?php

declare(strict_types=1);

namespace Endermanbugzjfc\FormInteractionFix;

use SOFe\AwaitStd\AwaitStd;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\plugin\PluginBase;

class Loader extends PluginBase implements Listener {

	private AwaitStd $std;

	protected function onEnable() : void {
		$this->std = AwaitStd::init($this);
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

	public function onPlayerLoginEvent(PlayerLoginEvent $event) : void {
		new PlayerSession($event->getPlayer(), $this->std, $this->getLogger());
	}
}