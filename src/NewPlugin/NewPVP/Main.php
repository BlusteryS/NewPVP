<?php

declare(strict_types=1);

namespace NewPlugin\NewPVP;

use NewPlugin\NewPVP\listeners\EventListener;
use NewPlugin\NewPVP\tasks\PvpTask;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;

class Main extends PluginBase {
	public array $config = [];
	public array $pvp = [];

	public function onEnable() : void {
		$this->config = (new Config($this->getDataFolder() . "config.yml", Config::YAML, [
			"time" => 15,
			"messages" => [
                "end" => "§f(§cРежим PvP§f) Вы больше не в режиме PVP.",
				"commands" => "§f(§cРежим PvP§f) Вы не можете писать команды в режиме §cPvP§f. Осталось: §c%0 сек.",
				"kill" => "§f(§cРежим PvP§f) Игрок §c%0§f вышел в режиме §cPvP§f и был убит.",
				"wait" => "§f(§cРежим PvP§f) Вы вышли из режима §сPvP§f.",
				"bossbar" => "PVP-режим активен, до конца §c%0 сек.",
				"start" => "Вы вошли в режим PVP!"
			]
		]))->getAll();

		$this->getScheduler()->scheduleRepeatingTask(new PvpTask($this), 20);
		$this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
	}
}
