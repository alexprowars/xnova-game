<?php

namespace Xnova\Planet;

use Illuminate\Support\Facades\Auth;
use Xnova\Entity\Research;
use Xnova\Models\PlanetEntity;
use Xnova\Planet;
use Xnova\Planet\Entity\Building;
use Xnova\Exceptions\Exception;
use Xnova\Planet\Entity\Defence;
use Xnova\Planet\Entity\Ship;
use Xnova\Vars;

class EntityFactory
{
	public static function create(int $entityId, int $level = 1, ?Planet $planet = null): Planet\Entity\BaseEntity
	{
		$className = self::getEntityClassName($entityId);

		if (!$planet) {
			$planet = Auth::user()->getCurrentPlanet(true);
		}

		/** @var Planet\Entity\BaseEntity $className */
		return $className::createEntity($entityId, $level, $planet);
	}

	public static function createFromModel(PlanetEntity $entity, ?Planet $planet = null)
	{
		$className = self::getEntityClassName($entity->entity_id);

		if (!$planet) {
			$planet = Auth::user()->getCurrentPlanet(true);
		}

		/** @var Planet\Entity\BaseEntity $object */
		$object = new $className($entity->getAttributes());
		$object->exists = $entity->exists;
		$object->setPlanet($planet);
		$object->syncOriginal();

		return $object;
	}

	public static function getEntityClassName(int $entityId): string
	{
		$entityType = Vars::getItemType($entityId);

		switch ($entityType) {
			case Vars::ITEM_TYPE_BUILING:
				return Building::class;
			case Vars::ITEM_TYPE_TECH:
				return Research::class;
			case Vars::ITEM_TYPE_FLEET:
				return Ship::class;
			case Vars::ITEM_TYPE_DEFENSE:
				return Defence::class;
			default:
				throw new Exception('unknown entity');
		}
	}
}