<?php

declare(strict_types=1);

namespace NewPlugin\NewPVP\listeners;

use NewPlugin\NewPVP\Main;
use NewPlugin\NewPVP\utils\BossBar;
use NewPlugin\NewPVP\utils\Utils;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\CommandEvent;
use pocketmine\player\Player;
use function time;

class EventListener implements Listener {
	public function __construct(private Main $plugin) {
	}

	/**
	 * @param PlayerQuitEvent $event
	 * @priority LOWEST
	 *
	 * @return void
	 */
	public function onQuit(PlayerQuitEvent $event) : void {
		$player = $event->getPlayer();
		if (isset($this->plugin->pvp[$name = $player->getName()])) {
			$player->kill();
			$msg = Utils::format($this->plugin->config["messages"]["kill"], $p->getDisplayName());
			foreach ($this->plugin->getServer()->getOnlinePlayers() as $p) {
				$p->sendMessage($msg);
			}
			unset($this->plugin->pvp[$name]);
		}
	}

	/**
	 * @param EntityDamageEvent $event
	 * @priority LOWEST
	 *
	 * @return void
	 */
	public function onDamage(EntityDamageEvent $event) : void {
		$player = $event->getEntity();

		if (!($player instanceof Player)) return;

		/** @var Player $damager */
		if ($event instanceof EntityDamageByEntityEvent && ($damager = $event->getDamager()) instanceof Player) {
			$time = time() + $this->plugin->config["time"];

			if (!isset($this->plugin->pvp[$player->getName()])) $this->plugin->pvp[$player->getName()] = [
				"time" => $time,
				"bar" => (new BossBar())->addPlayer($player)->setPercentage(1)->setTitle($this->plugin->config["messages"]["start"])
			];

			if (!isset($this->plugin->pvp[$damager->getName()])) $this->plugin->pvp[$damager->getName()] = [
				"time" => $time,
				"bar" => (new BossBar())->addPlayer($damager)->setPercentage(1)->setTitle($this->plugin->config["messages"]["start"])
			];

			$this->plugin->pvp[$player->getName()]["time"] = $time;
			$this->plugin->pvp[$damager->getName()]["time"] = $time;
		}
	}

	/**
	 * @param CommandEvent $event
	 * @priority LOWEST
	 *
	 * @return void
	 */
	public function onCommand(CommandEvent $event) : void {
		$sender = $event->getSender();

		if (!($sender instanceof Player)) return;

		if (isset($this->plugin->pvp[$sender->getName()])) {
			$sender->sendMessage(Utils::format($this->plugin->config["messages"]["commands"], $this->plugin->pvp[$sender->getName()]["time"] - time()));
			$event->cancel();
		}
	}
}
