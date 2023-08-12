<?php

declare(strict_types=1);

namespace NewPlugin\NewPVP\utils;

use InvalidArgumentException;
use pocketmine\entity\Attribute;
use pocketmine\entity\AttributeFactory;
use pocketmine\entity\AttributeMap;
use pocketmine\entity\Entity;
use pocketmine\network\mcpe\protocol\BossEventPacket;
use pocketmine\network\mcpe\protocol\RemoveActorPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\UpdateAttributesPacket;
use pocketmine\player\Player;
use pocketmine\Server;

class BossBar {
	public ?int $actorId = NULL;
	protected EntityMetadataCollection $propertyManager;
	private array $players = [];
	private string $title = "";
	private string $subTitle = "";
	private AttributeMap $attributeMap;

	public function __construct() {
		$this->attributeMap = new AttributeMap();
		$this->getAttributeMap()->add(AttributeFactory::getInstance()->mustGet(Attribute::HEALTH)->setMaxValue(100.0)->setMinValue(0.0)->setDefaultValue(100.0));
		$this->propertyManager = new EntityMetadataCollection();
		$this->propertyManager->setLong(EntityMetadataProperties::FLAGS, 0
			^ 1 << EntityMetadataFlags::SILENT
			^ 1 << EntityMetadataFlags::INVISIBLE
			^ 1 << EntityMetadataFlags::NO_AI
			^ 1 << EntityMetadataFlags::FIRE_IMMUNE
		);
		$this->propertyManager->setShort(EntityMetadataProperties::MAX_AIR, 400);
		$this->propertyManager->setString(EntityMetadataProperties::NAMETAG, $this->getFullTitle());
		$this->propertyManager->setLong(EntityMetadataProperties::LEAD_HOLDER_EID, -1);
		$this->propertyManager->setFloat(EntityMetadataProperties::SCALE, 0);
		$this->propertyManager->setFloat(EntityMetadataProperties::BOUNDING_BOX_WIDTH, 0.0);
		$this->propertyManager->setFloat(EntityMetadataProperties::BOUNDING_BOX_HEIGHT, 0.0);
	}

	public function getPlayers() : array {
		return $this->players;
	}

	public function addPlayer(Player $player) : BossBar {
		if (isset($this->players[$player->getId()])) return $this;
		$this->sendBossPacket([$player]);
		$this->players[$player->getId()] = $player;
		return $this;
	}

	public function removePlayer(Player $player) : BossBar {
		if (!isset($this->players[$player->getId()])) return $this;
		$this->sendRemoveBossPacket([$player]);
		unset($this->players[$player->getId()]);
		return $this;
	}

	public function removeAllPlayers() : BossBar {
		foreach ($this->getPlayers() as $player) $this->removePlayer($player);
		return $this;
	}

	public function setTitle(string $title = "") : BossBar {
		$this->title = $title;
		$this->sendBossTextPacket($this->getPlayers());
		return $this;
	}

	public function setSubTitle(string $subTitle = "") : BossBar {
		$this->subTitle = $subTitle;
		$this->sendBossTextPacket($this->getPlayers());
		return $this;
	}

	public function getFullTitle() : string {
		$text = $this->title;
		if (!empty($this->subTitle)) {
			$text .= "\n\n" . $this->subTitle;
		}
		return mb_convert_encoding($text, 'UTF-8');
	}

	public function setPercentage(float $percentage) : BossBar {
		$percentage = (float) min(1.0, max(0.0, $percentage));
		$this->getAttributeMap()->get(Attribute::HEALTH)->setValue($percentage * $this->getAttributeMap()->get(Attribute::HEALTH)->getMaxValue(), TRUE, TRUE);
		$this->sendBossHealthPacket($this->getPlayers());
		return $this;
	}

	public function getPercentage() : float {
		return $this->getAttributeMap()->get(Attribute::HEALTH)->getValue() / 100;
	}

	public function hideFrom(array $players) : void {
		$pk = new BossEventPacket();
		$pk->eventType = BossEventPacket::TYPE_HIDE;
		foreach ($players as $player) {
			if (!$player->isConnected()) continue;
			$pk->bossActorUniqueId = $this->actorId ?? $player->getId();
			$player->getNetworkSession()->sendDataPacket($this->addDefaults($pk));
		}
	}

	public function hideFromAll() : void {
		$this->hideFrom($this->getPlayers());
	}

	public function showTo(array $players) : void {
		$pk = new BossEventPacket();
		$pk->eventType = BossEventPacket::TYPE_SHOW;
		foreach ($players as $player) {
			if (!$player->isConnected()) continue;
			$pk->bossActorUniqueId = $this->actorId ?? $player->getId();
			$player->getNetworkSession()->sendDataPacket($this->addDefaults($pk));
		}
	}

	public function showToAll() : void {
		$this->showTo($this->getPlayers());
	}

	public function getEntity() : ?Entity {
		if ($this->actorId === NULL) return NULL;
		return Server::getInstance()->getWorldManager()->findEntity($this->actorId);
	}

	public function setEntity(?Entity $entity = NULL) : BossBar {
		if ($entity instanceof Entity && ($entity->isClosed() || $entity->isFlaggedForDespawn())) throw new InvalidArgumentException("Игрок $entity не в сети!");
		if ($this->getEntity() instanceof Entity && !($entity instanceof Player)) {
			$this->getEntity()->flagForDespawn();
		} else {
			$pk = new RemoveActorPacket();
			$pk->actorUniqueId = $this->actorId;
			foreach ($this->getPlayers() as $player) {
				/** @var Player $player */
				$player->getNetworkSession()->sendDataPacket($pk);
			}
		}
		if ($entity instanceof Entity) {
			$this->actorId = $entity->getId();
			$this->attributeMap = $entity->getAttributeMap();
			$this->getAttributeMap()->add($entity->getAttributeMap()->get(Attribute::HEALTH));
			$this->propertyManager = $entity->getNetworkProperties();
			if (!$entity instanceof Player) $entity->despawnFromAll();
		} else {
			$this->actorId = Entity::nextRuntimeId();
		}
		$this->sendBossPacket($this->getPlayers());
		return $this;
	}

	public function resetEntity(bool $removeEntity = FALSE) : BossBar {
		if ($removeEntity && $this->getEntity() instanceof Entity && !$this->getEntity() instanceof Player) $this->getEntity()->close();
		return $this->setEntity();
	}

	public function getAttributeMap() : AttributeMap {
		return $this->attributeMap;
	}

	protected function sendBossPacket(array $players) : void {
		$pk = new BossEventPacket();
		$pk->eventType = BossEventPacket::TYPE_SHOW;
		foreach ($players as $player) {
			if (!$player->isConnected()) continue;
			$pk->bossActorUniqueId = $this->actorId ?? $player->getId();
			$player->getNetworkSession()->sendDataPacket($this->addDefaults($pk));
		}
	}

	protected function sendRemoveBossPacket(array $players) : void {
		$pk = new BossEventPacket();
		$pk->eventType = BossEventPacket::TYPE_HIDE;
		foreach ($players as $player) {
			if (!$player->isConnected()) continue;
			$pk->bossActorUniqueId = $this->actorId ?? $player->getId();
			$player->getNetworkSession()->sendDataPacket($pk);
		}
	}

	protected function sendBossTextPacket(array $players) : void {
		$pk = new BossEventPacket();
		$pk->eventType = BossEventPacket::TYPE_TITLE;
		$pk->title = $this->getFullTitle();
		foreach ($players as $player) {
			if (!$player->isConnected()) continue;
			$pk->bossActorUniqueId = $this->actorId ?? $player->getId();
			$player->getNetworkSession()->sendDataPacket($pk);
		}
	}

	protected function sendAttributesPacket(array $players) : void {
		if ($this->actorId === NULL) return;
		$pk = new UpdateAttributesPacket();
		$pk->actorRuntimeId = $this->actorId;
		$pk->entries = $this->getAttributeMap()->needSend();
		foreach ($players as $player) {
			/** @var Player $player */
			$player->getNetworkSession()->sendDataPacket($pk);
		}
	}

	protected function sendBossHealthPacket(array $players) : void {
		$pk = new BossEventPacket();
		$pk->eventType = BossEventPacket::TYPE_HEALTH_PERCENT;
		$pk->healthPercent = $this->getPercentage();
		foreach ($players as $player) {
			if (!$player->isConnected()) continue;
			$pk->bossActorUniqueId = $this->actorId ?? $player->getId();
			$player->getNetworkSession()->sendDataPacket($pk);
		}
	}

	protected function getPropertyManager() : EntityMetadataCollection {
		return $this->propertyManager;
	}

	private function addDefaults(BossEventPacket $pk) : BossEventPacket {
		$pk->title = $this->getFullTitle();
		$pk->healthPercent = $this->getPercentage();
		$pk->unknownShort = 1;
		$pk->color = 0;
		$pk->overlay = 0;
		return $pk;
	}

	private function broadcastPacket(array $players, BossEventPacket $pk) : void {
		foreach ($players as $player) {
			if (!$player->isConnected()) continue;
			$pk->bossActorUniqueId = $player->getId();
			$player->getNetworkSession()->sendDataPacket($pk);
		}
	}
}