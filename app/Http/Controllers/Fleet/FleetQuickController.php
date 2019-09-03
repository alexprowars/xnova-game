<?php

namespace Xnova\Http\Controllers\Fleet;

/**
 * @author AlexPro
 * @copyright 2008 - 2018 XNova Game Group
 * Telegram: @alexprowars, Skype: alexprowars, Email: alexprowars@gmail.com
 */

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Input;
use Xnova\Controller;
use Xnova\Exceptions\Exception;
use Xnova\Exceptions\SuccessException;
use Xnova\Fleet;
use Xnova\Models;
use Xnova\Game;
use Xnova\Planet;
use Xnova\Vars;

class FleetQuickController extends Controller
{
	public function index ()
	{
		if ($this->user->vacation > 0)
			throw new Exception('Нет доступа!');

		$maxfleet = Models\Fleet::query()->where('owner', $this->user->id)->count();

		$MaxFlottes = 1 + $this->user->getTechLevel('computer');

		if ($this->user->rpg_admiral > time())
			$MaxFlottes += 2;

		$mission 	= (int) Input::query('mission', 0);
		$galaxy 	= (int) Input::query('galaxy', 0);
		$system 	= (int) Input::query('system', 0);
		$planet 	= (int) Input::query('planet', 0);
		$planetType = (int) Input::query('type', 0);
		$num 		= (int) Input::query('count', 0);

		if ($MaxFlottes <= $maxfleet)
			throw new Exception('Все слоты флота заняты');
		elseif ($galaxy > Config::get('game.maxGalaxyInWorld') || $galaxy < 1)
			throw new Exception('Ошибочная галактика!');
		elseif ($system > Config::get('game.maxSystemInGalaxy') || $system < 1)
			throw new Exception('Ошибочная система!');
		elseif ($planet > Config::get('game.maxPlanetInSystem') || $planet < 1)
			throw new Exception('Ошибочная планета!');
		elseif ($planetType != 1 && $planetType != 2 && $planetType != 3 && $planetType != 5)
			throw new Exception('Ошибочный тип планеты!');

		/** @var Planet $target */
		$target = Planet::query()
			->where('galaxy', $galaxy)
			->where('system', $system)
			->where('planet', $planet)
			->where(function (Builder $query) use ($planetType)
			{
				if ($planetType == 2)
					$query->where('planet_type', 1)->where('planet_type', 5);
				else
					$query->where('planet_type');
			})
			->get();

		if (!$target)
			throw new Exception('Цели не существует!');

		if (in_array($mission, [1, 2, 6, 9]) && Config::get('game.disableAttacks', 0) > 0 && time() < Config::get('game.disableAttacks', 0))
			throw new Exception("<span class=\"error\"><b>Посылать флот в атаку временно запрещено.<br>Дата включения атак " . Game::datezone("d.m.Y H ч. i мин.", Config::get('game.disableAttacks', 0)) . "</b></span>");

		$FleetArray = [];
		$HeDBRec = false;

		if ($mission == 6 && ($planetType == 1 || $planetType == 3 || $planetType == 5))
		{
			if ($num <= 0)
				throw new Exception('Вы были забанены за читерство!');
			if ($this->planet->getUnitCount('spy_sonde') == 0)
				throw new Exception('Нет шпионских зондов ля отправки!');
			if ($target->id_owner == $this->user->id)
				throw new Exception('Невозможно выполнить задание!');

			/** @var Models\Users $HeDBRec */
			$HeDBRec = Models\Users::query()->find($target->id_owner, ['id', 'onlinetime', 'vacation']);

			$MyGameLevel = Models\Statpoints::query()
				->select('total_points')
				->where('stat_type', 1)
				->where('stat_code', 1)
				->where('id_owner', $this->user->id)
				->value('total_points') ?? 0;

			$HeGameLevel = Models\Statpoints::query()
				->select('total_points')
				->where('stat_type', 1)
				->where('stat_code', 1)
				->where('id_owner', $HeDBRec->id)
				->value('total_points') ?? 0;

			if (!$HeGameLevel)
				$HeGameLevel = 0;

			if ($HeDBRec->onlinetime < (time() - 60 * 60 * 24 * 7))
				$NoobNoActive = 1;
			else
				$NoobNoActive = 0;

			if ($this->user->authlevel != 3)
			{
				if ($NoobNoActive == 0)
				{
					$protectionPoints = (int) Config::get('game.noobprotectionPoints');
					$protectionFactor = (int) Config::get('game.noobprotectionFactor');

					if ($HeGameLevel < $protectionPoints)
						throw new Exception('Игрок находится под защитой новичков!');

					if ($protectionFactor && $MyGameLevel > $HeGameLevel * $protectionFactor)
						throw new Exception('Этот игрок слишком слабый для вас!');
				}
			}

			if ($HeDBRec->vacation > 0)
				throw new Exception('Игрок в режиме отпуска!');

			if ($this->planet->getUnitCount('spy_sonde') < $num)
				$num = $this->planet->getUnitCount('spy_sonde');

			$FleetArray[210] = $num;

			$FleetSpeed = min(Fleet::GetFleetMaxSpeed($FleetArray, 0, $this->user));

		}
		elseif ($mission == 8 && $planetType == 2)
		{
			$DebrisSize = $target->debris_metal + $target->debris_crystal;

			if ($DebrisSize == 0)
				throw new Exception('Нет обломков для сбора!');
			if ($this->planet->getUnitCount('recycler') == 0)
				throw new Exception('Нет переработчиков для сбора обломков!');

			$RecyclerNeeded = 0;

			if ($this->planet->getUnitCount('recycler') > 0 && $DebrisSize > 0)
			{
				$fleetData = Vars::getUnitData(Vars::getIdByName('recycler'));

				$RecyclerNeeded = floor($DebrisSize / ($fleetData['capacity'])) + 1;

				if ($RecyclerNeeded > $this->planet->getUnitCount('recycler'))
					$RecyclerNeeded = $this->planet->getUnitCount('recycler');
			}

			if ($RecyclerNeeded > 0)
			{
				$FleetArray[209] = $RecyclerNeeded;

				$FleetSpeed = min(Fleet::GetFleetMaxSpeed($FleetArray, 0, $this->user));
			}
			else
				throw new Exception('Произошла какая-то непонятная ситуация');
		}
		else
			throw new Exception('Такой миссии не существует!');

		if ($FleetSpeed > 0 && count($FleetArray) > 0)
		{
			$SpeedFactor = Game::getSpeed('fleet');
			$distance = Fleet::GetTargetDistance($this->planet->galaxy, $galaxy, $this->planet->system, $system, $this->planet->planet, $planet);
			$duration = Fleet::GetMissionDuration(10, $FleetSpeed, $distance, $SpeedFactor);

			$consumption = Fleet::GetFleetConsumption($FleetArray, $SpeedFactor, $duration, $distance, $this->user);

			$shipArray = [];
			$FleetStorage = 0;

			foreach ($FleetArray as $shipId => $count)
			{
				$count = (int) $count;

				$this->planet->setUnit($shipId, -$count, true);

				$shipArray[] = [
					'id' => (int) $shipId,
					'count' => $count
				];

				$fleetData = Vars::getUnitData(Vars::getIdByName('recycler'));

				$FleetStorage += $fleetData['capacity'] * $count;
			}

			if ($FleetStorage < $consumption)
				throw new Exception('Не хватает места в трюме для топлива! (необходимо еще ' . ($consumption - $FleetStorage) . ')');
			if ($this->planet->deuterium < $consumption)
				throw new Exception('Не хватает топлива на полёт! (необходимо еще ' . ($consumption - $this->planet->deuterium) . ')');

			if (count($shipArray))
			{
				$fleet = new Models\Fleet();

				$fleet->owner = $this->user->id;
				$fleet->owner_name = $this->planet->name;
				$fleet->mission = $mission;
				$fleet->fleet_array = $shipArray;
				$fleet->start_time = $duration + time();
				$fleet->start_galaxy = $this->planet->galaxy;
				$fleet->start_system = $this->planet->system;
				$fleet->start_planet = $this->planet->planet;
				$fleet->start_type = $this->planet->planet_type;
				$fleet->end_time = ($duration * 2) + time();
				$fleet->end_galaxy = $galaxy;
				$fleet->end_system = $system;
				$fleet->end_planet = $planet;
				$fleet->end_type = $planetType;
				$fleet->create_time = time();
				$fleet->update_time = $duration + time();

				if ($mission == 6 && $HeDBRec)
				{
					$fleet->target_owner = $HeDBRec['id'];
					$fleet->target_owner_name = $target->name;
				}

				if ($fleet->save())
				{
					$this->planet->deuterium -= $consumption;
					$this->planet->update();

					$tutorial = Models\UsersQuest::query()
						->select(['id', 'quest_id'])
						->where('user_id', $this->user->getId())
						->where('finish', 0)
						->where('stage', 0)
						->first();

					if ($tutorial)
					{
						$quest = __('tutorial.tutorial', $tutorial->quest_id);

						foreach ($quest['TASK'] AS $taskKey => $taskVal)
						{
							if ($taskKey == 'FLEET_MISSION' && $taskVal == $mission)
								$tutorial->update(['stage' => 1]);
						}
					}

					throw new SuccessException("Флот отправлен на координаты [" . $galaxy . ":" . $system . ":" . $planet . "] с миссией " . __('main.type_mission.'.$mission) . " и прибудет к цели в " . Game::datezone("H:i:s", $duration + time()));
				}
			}
		}

		throw new Exception('Произошла ошибка');
	}
}