<?php

declare(strict_types=1);

namespace NewPlugin\NewPVP\tasks;

use NewPlugin\NewPVP\Main;
use NewPlugin\NewPVP\utils\Utils;
use pocketmine\scheduler\Task;
use function time;

class PvpTask extends Task {
	public function __construct(private Main $plugin) {
	}

	public function onRun() : void {
		$plugin = $this->plugin;
		foreach ($plugin->pvp as $name => $content) {
			$tm = $content["time"] - time();
			$bar = $content["bar"];
			$bar->setTitle(Utils::format($plugin->config["messages"]["bossbar"], $tm));
			$bar->setPercentage($tm / $plugin->config["time"]);
			if ($content["time"] < time()) {
				unset($plugin->pvp[$name]);
				$bar->removeAllPlayers();
				$plugin->getServer()->getPlayerExact($name)?->sendMessage($plugin->config["messages"]["end"]);
			}
		}
	}
}
