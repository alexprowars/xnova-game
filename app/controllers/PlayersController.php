<?php

namespace App\Controllers;

use App\Helpers;

class PlayersController extends ApplicationController
{
	public function initialize ()
	{
		parent::initialize();
	}
	
	public function indexAction ()
	{
		global $session;
		
		$parse = array();
		
		$playerid = htmlspecialchars(request::R('id', ''));

		if (!$playerid)
			$this->message('Профиль не найден');

		$ownid = ($session->isAuthorized()) ? $this->user->id : 0;
		
		$PlayerCard = $this->db->query("SELECT u.*, ui.about, ui.image FROM game_users u LEFT JOIN game_users_info ui ON ui.id = u.id WHERE ".(is_numeric($playerid) ? "u.id" : "u.username")." = '" . $playerid . "';");
		
		if ($daten = $PlayerCard->fetch())
		{
			if ($daten['image'] != '')
			{
				$parse['avatar'] = RPATH."images/avatars/upload/".$daten['image'];
			}
			elseif ($daten['avatar'] != 0)
			{
				if ($daten['avatar'] != 99)
					$parse['avatar'] = RPATH."images/faces/".$daten['sex']."/" . $daten['avatar'] . "s.png";
				else
					$parse['avatar'] = RPATH."images/avatars/upload/upload_" . $daten['id'] . ".jpg";
			}
			else
				$parse['avatar'] = RPATH.'images/no_photo.gif';

			$gesamtkaempfe = $daten['raids_win'] + $daten['raids_lose'];

			if ($gesamtkaempfe == 0)
			{
				$siegprozent = 0;
				$loosprozent = 0;
			}
			else
			{
				$siegprozent = 100 / $gesamtkaempfe * $daten['raids_win'];
				$loosprozent = 100 / $gesamtkaempfe * $daten['raids_lose'];
			}
		
			$planets = $this->db->query("SELECT * FROM game_planets WHERE `galaxy` = '" . $daten['galaxy'] . "' and `system` = '" . $daten['system'] . "' and `planet_type` = '1' and `planet` = '" . $daten['planet'] . "';")->fetch();
			$parse['userplanet'] = $planets['name'];
		
			$points = $this->db->query("SELECT * FROM game_statpoints WHERE `stat_type` = '1' AND `stat_code` = '1' AND `id_owner` = '" . $daten['id'] . "';")->fetch();
			$parse['tech_rank'] = Helpers::pretty_number($points['tech_rank']);
			$parse['tech_points'] = Helpers::pretty_number($points['tech_points']);
			$parse['build_rank'] = Helpers::pretty_number($points['build_rank']);
			$parse['build_points'] = Helpers::pretty_number($points['build_points']);
			$parse['fleet_rank'] = Helpers::pretty_number($points['fleet_rank']);
			$parse['fleet_points'] = Helpers::pretty_number($points['fleet_points']);
			$parse['defs_rank'] = Helpers::pretty_number($points['defs_rank']);
			$parse['defs_points'] = Helpers::pretty_number($points['defs_points']);
			$parse['total_rank'] = Helpers::pretty_number($points['total_rank']);
			$parse['total_points'] = Helpers::pretty_number($points['total_points']);
		
			if ($ownid != 0)
				$parse['player_buddy'] = "<a href=\"?set=buddy&a=2&amp;u=" . $playerid . "\" title=\"Добавить в друзья\">Добавить в друзья</a>";
			else
				$parse['player_buddy'] = "";
		
			if ($ownid != 0)
				$parse['player_mes'] = "<a href=\"?set=messages&mode=write&id=" . $playerid . "\">Написать сообщение</a>";
			else
				$parse['player_mes'] = "";
		
			if ($daten['sex'] == 2)
				$parse['sex'] = "Женский";
			else
				$parse['sex'] = "Мужской";

			$parse['ingame'] = ($ownid != 0) ? true : false;
			$parse['id'] = $daten['id'];
			$parse['username'] = $daten['username'];
			$parse['race'] = $daten['race'];
			$parse['galaxy'] = $daten['galaxy'];
			$parse['system'] = $daten['system'];
			$parse['planet'] = $daten['planet'];
			$parse['ally_id'] = $daten['ally_id'];
			$parse['ally_name'] = $daten['ally_name'];
			$parse['about'] = $daten['about'];
			$parse['wons'] = Helpers::pretty_number($daten['raids_win']);
			$parse['loos'] = Helpers::pretty_number($daten['raids_lose']);
			$parse['siegprozent'] = round($siegprozent, 2);
			$parse['loosprozent'] = round($loosprozent, 2);
			$parse['total'] = $daten['raids'];
			$parse['totalprozent'] = 100;
			$parse['m'] = $this->user->getRankId($daten['lvl_minier']);
			$parse['f'] = $this->user->getRankId($daten['lvl_raid']);
		}
		else
			$this->message('Параметр задан неверно', 'Ошибка');
		
		$this->view->pick('player');
		$this->view->setVar('parse', $parse);

		$this->tag->setTitle('Информация о игроке');
		$this->showTopPanel(false);
		$this->showLeftPanel($session->isAuthorized());
	}

	public function stat ()
	{
		global $session;

		if (!$session->isAuthorized())
			$this->show();

		$playerid = request::R('id', 0);

		$player = $this->db->query("SELECT id, username FROM game_users WHERE id = ".$playerid."")->fetch();

		if (!isset($player['id']))
			$this->message('Информация о данном игроке не найдена');

		$parse = array();
		$parse['name'] = $player['username'];
		$parse['data'] = $this->db->extractResult($this->db->query("SELECT * FROM game_log_stats WHERE id = ".$playerid." AND type = 1 AND time > ".(time() - 14 * 86400)." ORDER BY time ASC"));

		$this->view->pick('player_stat');
		$this->view->setVar('parse', $parse);

		$this->tag->setTitle('Статистика игрока');
		$this->showTopPanel(false);
		$this->showLeftPanel($session->isAuthorized());
	}
}

?>