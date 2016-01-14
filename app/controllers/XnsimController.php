<?php

namespace App\Controllers;

use App\Battle\Core\Battle;
use App\Battle\Core\Round;
use App\Battle\Models\Defense;
use App\Battle\Models\Fleet;
use App\Battle\Models\Player;
use App\Battle\Models\PlayerGroup;
use App\Battle\Models\Ship;
use App\Battle\Models\ShipType;
use App\CombatReport;
use App\Fleet AS FleetObject;
use Phalcon\Mvc\View;

class XnsimController extends ApplicationController
{
	private $usersInfo = [];

	public function initialize ()
	{
		$this->disableCollections();
		$this->view->disableLevel(View::LEVEL_MAIN_LAYOUT);

		parent::initialize();
	}

	public function indexAction ()
	{
		$techList = array(109, 110, 111, 120, 121, 122);

		$css = $this->assets->collection('css');
		$css->addCss('/assets/css/xnsim.css');

		$js = $this->assets->collection('js');
		$js->addJs('//yastatic.net/jquery/1.11.3/jquery.min.js');

		$this->view->setVar('techList', $techList);
	}

	public function reportAction ()
	{
		$css = $this->assets->collection('css');
		$css->addCss('/assets/css/report.css');

		if ($this->request->hasQuery('sid'))
		{
			$log = $this->db->query("SELECT * FROM game_log_sim WHERE sid = '".addslashes(htmlspecialchars($this->request->getQuery('sid', 'string', '')))."' LIMIT 1")->fetch();

			if (!isset($log['id']))
				die('Лога не существует');

			$result = json_decode($log['data'], true);

			$sid = $log['sid'];
		}
		else
		{
			$r = explode("|", $this->request->get('r', 'string', ''));

			if (!isset($r[0]) || !isset($r[10]))
				die('Нет данных для симуляции боя');

			define('MAX_SLOTS', $this->config->game->get('maxSlotsInSim', 5));

			include_once(APP_PATH."/app/config/battle.php");

			$attackers = $this->getAttackers(0, $r);
			$defenders = $this->getAttackers(MAX_SLOTS, $r);

			$engine = new Battle($attackers, $defenders);

			$report = $engine->getReport();

			$result = array();
			$result[0] = array('time' => time(), 'rw' => array());

			$result[1] = $this->convertPlayerGroupToArray($report->getResultAttackersFleetOnRound('START'));
			$result[2] = $this->convertPlayerGroupToArray($report->getResultDefendersFleetOnRound('START'));

			for ($_i = 0; $_i <= $report->getLastRoundNumber(); $_i++)
			{
				$result[0]['rw'][] = $this->convertRoundToArray($report->getRound($_i));
			}

			if ($report->attackerHasWin())
				$result[0]['won'] = 1;
			if ($report->defenderHasWin())
				$result[0]['won'] = 2;
			if ($report->isAdraw())
				$result[0]['won'] = 0;

			$result[0]['lost'] = array('att' => $report->getTotalAttackersLostUnits(), 'def' => $report->getTotalDefendersLostUnits());

			$debris = $report->getDebris();

			$result[0]['debree']['att'] = $debris;
			$result[0]['debree']['def'] = array(0, 0);

			$result[3] = array('metal' => 0, 'crystal' => 0, 'deuterium' => 0);
			$result[4] = $report->getMoonProb();
			$result[5] = '';

			$result[6] = array();

			foreach ($report->getDefendersRepaired() as $_id => $_player)
			{
				foreach ($_player as $_idFleet => $_fleet)
				{
					/**
					 * @var ShipType $_ship
					 */
					foreach ($_fleet as $_shipID => $_ship)
					{
						$result[6][$_idFleet][$_shipID] = $_ship->getCount();
					}
				}
			}

			$statistics = array();

			for ($i = 0; $i < 50; $i++)
			{
				$engine = new Battle($attackers, $defenders);

				$report = $engine->getReport();

				$statistics[] = array('att' => $report->getTotalAttackersLostUnits(), 'def' => $report->getTotalDefendersLostUnits());

				unset($report);
				unset($engine);
			}

			uasort($statistics, function($a, $b)
			{
				return ($a['att'] > $b['att'] ? 1 : -1);
			});

			$sid = md5(time().$this->request->getClientAddress());

			$check = $this->db->fetchColumn("SELECT COUNT(*) AS NUM FROM game_log_sim WHERE sid = '".$sid."'");

			if ($check == 0)
			{
				$this->db->insertAsDict('game_log_sim',
				[
					'sid' => $sid,
					'time' => time(),
					'data' => json_encode($result)
				]);
			}

			$this->view->setVar('statistics', $statistics);
		}

		$report = new CombatReport($result[0], $result[1], $result[2], $result[3], $result[4], $result[5], $result[6]);
		$report = $report->report();

		$this->view->setVar('report', $report);
		$this->view->setVar('sid', $sid);
	}

	private function convertPlayerGroupToArray (PlayerGroup $_playerGroup)
	{
		$result = array();

		foreach ($_playerGroup as $_player)
		{
			$result[$_player->getId()] = array
			(
				'username' => $_player->getName(),
				'fleet' => array($_player->getId() => array('galaxy' => 1, 'system' => 1, 'planet' => 1)),
				'tech' => array
				(
					'military_tech' => isset($this->usersInfo[$_player->getId()][109]) ? $this->usersInfo[$_player->getId()][109] : 0,
					'shield_tech' 	=> isset($this->usersInfo[$_player->getId()][110]) ? $this->usersInfo[$_player->getId()][110] : 0,
					'defence_tech' 	=> isset($this->usersInfo[$_player->getId()][111]) ? $this->usersInfo[$_player->getId()][111] : 0,
					'laser_tech'	=> isset($this->usersInfo[$_player->getId()][120]) ? $this->usersInfo[$_player->getId()][120] : 0,
					'ionic_tech'	=> isset($this->usersInfo[$_player->getId()][121]) ? $this->usersInfo[$_player->getId()][121] : 0,
					'buster_tech'	=> isset($this->usersInfo[$_player->getId()][122]) ? $this->usersInfo[$_player->getId()][122] : 0
				),
				'flvl' => $this->usersInfo[$_player->getId()],
			);
		}

		return $result;
	}

	private function convertRoundToArray(Round $round)
	{
		$result = array
		(
				'attackers' 	=> array(),
				'defenders' 	=> array(),
				'attack'		=> array('total' => $round->getAttackersFirePower()),
				'defense' 		=> array('total' => $round->getDefendersFirePower()),
				'attackA' 		=> array('total' => $round->getAttackersFireCount()),
				'defenseA' 		=> array('total' => $round->getDefendersFireCount())
		);

		$attackers = $round->getAfterBattleAttackers();
		$defenders = $round->getAfterBattleDefenders();

		foreach ($attackers as $_player)
		{
			foreach ($_player as $_idFleet => $_fleet)
			{
				/**
				 * @var ShipType $_ship
				 */
				foreach($_fleet as $_shipID => $_ship)
				{
					$result['attackers'][$_idFleet][$_shipID] = $_ship->getCount();

					if (!isset($result['attackA'][$_idFleet]['total']))
						$result['attackA'][$_idFleet]['total'] = 0;

					$result['attackA'][$_idFleet]['total'] += $_ship->getCount();
				}
			}
		}

		foreach ($defenders as $_player)
		{
			foreach ($_player as $_idFleet => $_fleet)
			{
				/**
				 * @var ShipType $_ship
				 */
				foreach($_fleet as $_shipID => $_ship)
				{
					$result['defenders'][$_idFleet][$_shipID] = $_ship->getCount();

					if (!isset($result['defenseA'][$_idFleet]['total']))
						$result['defenseA'][$_idFleet]['total'] = 0;

					$result['defenseA'][$_idFleet]['total'] += $_ship->getCount();
				}
			}
		}

		$result['attackShield'] = $round->getAttachersAssorbedDamage();
		$result['defShield'] 	= $round->getDefendersAssorbedDamage();

		return $result;
	}

	private function getAttackers($s = 0, $r)
	{
		$playerGroupObj = new PlayerGroup();

		for ($i = $s; $i < MAX_SLOTS * 2; $i++)
		{
			if ($i <= MAX_SLOTS && $i < (MAX_SLOTS + $s) && $r[$i] != "")
			{
				$res = array();
				$fleets = array();

				$fleetData = FleetObject::unserializeFleet($r[$i]);

				foreach ($fleetData as $shipId => $shipArr)
				{
					if ($shipId > 200)
						$fleets[$shipId] = array($shipArr['cnt'], $shipArr['lvl']);

					$res[$shipId] = $shipArr['cnt'];

					if ($shipArr['lvl'] > 0)
						$res[($shipId > 400 ? ($shipId - 50) : ($shipId + 100))] = $shipArr['lvl'];
				}

				$fleetId = $i;
				$playerId = $i;

				$playerObj = new Player($playerId);
				$playerObj->setName('Игрок ' . ($playerId + 1));
				$playerObj->setTech(0, 0, 0);

				$this->usersInfo[$playerId] = $res;

				$fleetObj = new Fleet($fleetId);

				foreach ($fleets as $id => $count)
				{
					$id = floor($id);

					if ($count[0] > 0 && $id > 0)
						$fleetObj->addShipType($this->getShipType($id, $count, $res));
				}

				if (!$fleetObj->isEmpty())
					$playerObj->addFleet($fleetObj);

				if (!$playerGroupObj->existPlayer($playerId))
					$playerGroupObj->addPlayer($playerObj);
			}
		}

		return $playerGroupObj;
	}

	private function getShipType($id, $count, $res)
	{
		$attDef 	= ($count[1] * ($this->game->CombatCaps[$id]['power_armour'] / 100)) + (isset($res[111]) ? $res[111] : 0) * 0.05;
		$attTech 	= (isset($res[109]) ? $res[109] : 0) * 0.05 + ($count[1] * ($this->game->CombatCaps[$id]['power_up'] / 100));

		if ($this->game->CombatCaps[$id]['type_gun'] == 1)
			$attTech += (isset($res[120]) ? $res[120] : 0) * 0.05;
		elseif ($this->game->CombatCaps[$id]['type_gun'] == 2)
			$attTech += (isset($res[121]) ? $res[121] : 0) * 0.05;
		elseif ($this->game->CombatCaps[$id]['type_gun'] == 3)
			$attTech += (isset($res[122]) ? $res[122] : 0) * 0.05;

		$cost = array($this->game->pricelist[$id]['metal'], $this->game->pricelist[$id]['crystal']);

		if (in_array($id, $this->game->reslist['fleet']))
			return new Ship($id, $count[0], $this->game->CombatCaps[$id]['sd'], $this->game->CombatCaps[$id]['shield'], $cost, $this->game->CombatCaps[$id]['attack'], $attTech, ((isset($res[110]) ? $res[110] : 0) * 0.05), $attDef);

		return new Defense($id, $count[0], $this->game->CombatCaps[$id]['sd'], $this->game->CombatCaps[$id]['shield'], $cost, $this->game->CombatCaps[$id]['attack'], $attTech, ((isset($res[110]) ? $res[110] : 0) * 0.05), $attDef);
	}
}

?>