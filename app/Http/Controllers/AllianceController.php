<?php

/**
 * @author AlexPro
 * @copyright 2008 - 2019 XNova Game Group
 * Telegram: @alexprowars, Skype: alexprowars, Email: alexprowars@gmail.com
 */

namespace Xnova\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Xnova\Exceptions\ErrorException;
use Xnova\Exceptions\PageException;
use Xnova\Exceptions\RedirectException;
use Xnova\Files;
use Xnova\Format;
use Xnova\Game;
use Xnova\Helpers;
use Xnova\Models\Alliance;
use Xnova\Models\AllianceMember;
use Xnova\Models\AllianceRequest;
use Xnova\Models;
use Xnova\User;
use Xnova\Controller;

class AllianceController extends Controller
{
	/** @var Alliance $ally */
	private $ally;

	public function __construct()
	{
		parent::__construct();

		$this->showTopPanel(false);
	}

	private function parseInfo($allyId)
	{
		/** @var Alliance $ally */
		$ally = Alliance::query()->find($allyId);

		if (!$ally) {
			Models\User::query()->where('id', $this->user->id)->update(['ally_id' => 0]);
			Models\AllianceMember::query()->where('u_id', $this->user->id)->delete();

			throw new RedirectException(__('alliance.ally_notexist'), '/alliance/');
		}

		$this->ally = $ally;
		$this->ally->getMember($this->user->id);
		$this->ally->getRanks();

		if (!$this->ally->member) {
			$member = new AllianceMember();

			$member->a_id = $this->ally->id;
			$member->u_id = $this->user->id;
			$member->time = time();
			$member->save();

			$this->ally->member = $member;
		}
	}

	private function noAlly()
	{
		if (Request::post('bcancel') && Request::post('r_id')) {
			DB::statement("DELETE FROM alliance_requests WHERE a_id = " . intval(Request::post('r_id')) . " AND u_id = " . $this->user->id);

			throw new RedirectException("Вы отозвали свою заявку на вступление в альянс", "/alliance/");
		}

		$parse = [];

		$parse['list'] = [];

		$requests = DB::select("SELECT r.*, a.name, a.tag FROM alliance_requests r LEFT JOIN alliance a ON a.id = r.a_id WHERE r.u_id = " . $this->user->id . ";");

		foreach ($requests as $request) {
			$parse['list'][] = [$request->a_id, $request->tag, $request->name, $request->time];
		}

		$parse['allys'] = [];

		$allys = DB::select("SELECT s.total_points, a.id, a.tag, a.name, a.members FROM statpoints s, alliance a WHERE s.stat_type = '2' AND s.stat_code = '1' AND a.id = s.id_owner ORDER BY s.total_points DESC LIMIT 0,15;");

		foreach ($allys as $ally) {
			$ally->total_points = Format::number($ally->total_points);
			$parse['allys'][] = (array) $ally;
		}

		$this->setTitle(__('alliance.alliance'));

		return $parse;
	}

	public function index()
	{
		if (!Auth::check()) {
			throw new ErrorException(__('alliance.Denied_access'));
		}

		if ($this->user->ally_id == 0) {
			return $this->noAlly();
		} else {
			$this->parseInfo($this->user->ally_id);

			if ($this->ally->owner == $this->user->id) {
				$range = ($this->ally->owner_range == '') ? 'Основатель' : $this->ally->owner_range;
			} elseif ($this->ally->member->rank != 0 && isset($this->ally->ranks[$this->ally->member->rank - 1]['name'])) {
				$range = $this->ally->ranks[$this->ally->member->rank - 1]['name'];
			} else {
				$range = __('alliance.member');
			}

			$parse['range'] = $range;

			$parse['diplomacy'] = false;

			if ($this->ally->canAccess(Alliance::DIPLOMACY_ACCESS)) {
				$parse['diplomacy'] = Models\AllianceDiplomacy::query()->where('d_id', $this->ally->id)->where('status', 0)->count();
			}

			$parse['requests'] = 0;

			if ($this->ally->owner == $this->user->id || $this->ally->canAccess(Alliance::REQUEST_ACCESS)) {
				$parse['requests'] = Models\AllianceDiplomacy::query()->where('a_id', $this->ally->id)->count();
			}

			$parse['alliance_admin'] = $this->ally->canAccess(Alliance::ADMIN_ACCESS);
			$parse['chat_access'] = $this->ally->canAccess(Alliance::CHAT_ACCESS);
			$parse['members_list'] = $this->ally->canAccess(Alliance::CAN_WATCH_MEMBERLIST);
			$parse['owner'] = ($this->ally->owner != $this->user->id) ? $this->MessageForm(__('alliance.Exit_of_this_alliance'), "", "/alliance/exit/", __('alliance.Continue')) : '';

			$parse['image'] = '';

			if ((int) $this->ally->image > 0) {
				$image = Files::getById($this->ally->image);

				if ($image) {
					$parse['image'] = $image['src'];
				}
			}

			$parse['description'] = str_replace(["\r\n", "\n", "\r"], '', stripslashes($this->ally->description));
			$parse['text'] = str_replace(["\r\n", "\n", "\r"], '', stripslashes($this->ally->text));

			$parse['web'] = $this->ally->web;

			if ($parse['web'] != '' && strpos($parse['web'], 'http') === false) {
				$parse['web'] = 'http://' . $parse['web'];
			}

			$parse['tag'] = $this->ally->tag;
			$parse['members'] = $this->ally->members;
			$parse['name'] = $this->ally->name;
			$parse['id'] = $this->ally->id;

			$this->setTitle('Ваш альянс');

			return $parse;
		}
	}

	public function admin()
	{
		$this->parseInfo($this->user->ally_id);

		if (!$this->ally->canAccess(Alliance::ADMIN_ACCESS)) {
			throw new ErrorException(__('alliance.Denied_access'));
		}

		$t = (int) Request::query('t', 1);

		if ($t != 1 && $t != 2 && $t != 3) {
			$t = 1;
		}

		if (Request::post('options')) {
			$this->ally->owner_range = Helpers::checkString(Request::post('owner_range', ''), true);
			$this->ally->web = Helpers::checkString(Request::post('web', ''), true);

			if (Request::instance()->hasFile('image')) {
				$file = Request::instance()->file('image');

				if ($file->isValid()) {
					$fileType = $file->getMimeType();

					if (strpos($fileType, 'image/') === false) {
						throw new ErrorException('Разрешены к загрузке только изображения');
					}

					if ($this->ally->image > 0) {
						Files::delete($this->ally->image);
					}

					$this->ally->image = Files::save($file);
				}
			}

			if (Request::post('delete_image')) {
				if (Files::delete($this->ally->image)) {
					$this->ally->image = 0;
				}
			}

			$this->ally->request_notallow = (int) Request::post('request_notallow', 0);

			if ($this->ally->request_notallow != 0 && $this->ally->request_notallow != 1) {
				throw new ErrorException("Недопустимое значение атрибута!");
			}

			$this->ally->update();
		} elseif (Request::post('t')) {
			if ($t == 3) {
				$this->ally->request = Format::text(Request::post('text', ''));
			} elseif ($t == 2) {
				$this->ally->text = Format::text(Request::post('text', ''));
			} else {
				$this->ally->description = Format::text(Request::post('text', ''));
			}

			$this->ally->update();
		}

		if ($t == 3) {
			$parse['text'] = preg_replace('!<br.*>!iU', "\n", $this->ally->request);
			$parse['Show_of_request_text'] = "Текст заявок альянса";
		} elseif ($t == 2) {
			$parse['text'] = preg_replace('!<br.*>!iU', "\n", $this->ally->text);
			$parse['Show_of_request_text'] = "Внутренний текст альянса";
		} else {
			$parse['text'] = preg_replace('!<br.*>!iU', "\n", $this->ally->description);
		}

		$parse['t'] = $t;
		$parse['owner'] = $this->ally->owner;
		$parse['web'] = $this->ally->web;

		$parse['image'] = '';

		if ((int) $this->ally->image > 0) {
			$image = Files::getById($this->ally->image);

			if ($image) {
				$parse['image'] = $image['src'];
			}
		}

		$parse['request_allow'] = $this->ally->request_notallow;
		$parse['owner_range'] = $this->ally->owner_range;

		$parse['can_view_members'] = $this->ally->canAccess(Alliance::CAN_KICK);

		if ($this->ally->owner == $this->user->id) {
			$parse['Transfer_alliance'] = $this->MessageForm("Покинуть / Передать альянс", "", "/alliance/admin/give", 'Продолжить');
		}

		if ($this->ally->canAccess(Alliance::CAN_DELETE_ALLIANCE)) {
			$parse['Disolve_alliance'] = $this->MessageForm("Расформировать альянс", "", "/alliance/admin/exit", 'Продолжить');
		}

		$this->setTitle(__('alliance.Alliance_admin'));

		return $parse;
	}

	public function adminRights()
	{
		$this->parseInfo($this->user->ally_id);

		if (!$this->ally->canAccess(Alliance::CAN_EDIT_RIGHTS) && !$this->user->isAdmin()) {
			throw new ErrorException(__('alliance.Denied_access'));
		} elseif (!empty(Request::post('newrangname'))) {
			$this->ally->ranks[] = [
				'name' => strip_tags(Request::post('newrangname')),
				Alliance::CAN_DELETE_ALLIANCE => 0,
				Alliance::CAN_KICK => 0,
				Alliance::REQUEST_ACCESS => 0,
				Alliance::CAN_WATCH_MEMBERLIST => 0,
				Alliance::CAN_ACCEPT => 0,
				Alliance::ADMIN_ACCESS => 0,
				Alliance::CAN_WATCH_MEMBERLIST_STATUS => 0,
				Alliance::CHAT_ACCESS => 0,
				Alliance::CAN_EDIT_RIGHTS => 0,
				Alliance::DIPLOMACY_ACCESS => 0,
				Alliance::PLANET_ACCESS => 0
			];

			DB::statement("UPDATE alliances SET ranks = '" . addslashes(json_encode($this->ally->ranks)) . "' WHERE id = " . $this->ally->id);
		} elseif (Request::post('id') && is_array(Request::post('id'))) {
			$ally_ranks_new = [];

			foreach (Request::post('id') as $id) {
				$name = $this->ally->ranks[$id]['name'];

				$ally_ranks_new[$id]['name'] = $name;

				$ally_ranks_new[$id][Alliance::CAN_DELETE_ALLIANCE] = ($this->ally->owner == $this->user->id ? (Request::post('u' . $id . 'r0') ? 1 : 0) : $this->ally->ranks[$id][Alliance::CAN_DELETE_ALLIANCE]);
				$ally_ranks_new[$id][Alliance::CAN_KICK] = ($this->ally->owner == $this->user->id ? (Request::post('u' . $id . 'r1') ? 1 : 0) : $this->ally->ranks[$id][Alliance::CAN_KICK]);
				$ally_ranks_new[$id][Alliance::REQUEST_ACCESS] = Request::post('u' . $id . 'r2') ? 1 : 0;
				$ally_ranks_new[$id][Alliance::CAN_WATCH_MEMBERLIST] = Request::post('u' . $id . 'r3') ? 1 : 0;
				$ally_ranks_new[$id][Alliance::CAN_ACCEPT] = Request::post('u' . $id . 'r4') ? 1 : 0;
				$ally_ranks_new[$id][Alliance::ADMIN_ACCESS] = Request::post('u' . $id . 'r5') ? 1 : 0;
				$ally_ranks_new[$id][Alliance::CAN_WATCH_MEMBERLIST_STATUS] = Request::post('u' . $id . 'r6') ? 1 : 0;
				$ally_ranks_new[$id][Alliance::CHAT_ACCESS] = Request::post('u' . $id . 'r7') ? 1 : 0;
				$ally_ranks_new[$id][Alliance::CAN_EDIT_RIGHTS] = Request::post('u' . $id . 'r8') ? 1 : 0;
				$ally_ranks_new[$id][Alliance::DIPLOMACY_ACCESS] = Request::post('u' . $id . 'r9') ? 1 : 0;
				$ally_ranks_new[$id][Alliance::PLANET_ACCESS] = Request::post('u' . $id . 'r10') ? 1 : 0;
			}

			$this->ally->ranks = $ally_ranks_new;

			DB::statement("UPDATE alliances SET ranks = '" . addslashes(json_encode($this->ally->ranks)) . "' WHERE id = " . $this->ally->id);
		} elseif (Request::query('d') && isset($this->ally->ranks[Request::query('d', 'int')])) {
			unset($this->ally->ranks[Request::query('d', 'int')]);

			DB::statement("UPDATE alliances SET ranks = '" . addslashes(json_encode($this->ally->ranks)) . "' WHERE id = " . $this->ally->id);
		}

		$parse['list'] = [];

		if (is_array($this->ally->ranks) && count($this->ally->ranks) > 0) {
			foreach ($this->ally->ranks as $a => $b) {
				$list['id'] = $a;
				$list['delete'] = '<a href="' . URL::to('alliance/admin/rights?d=' . $a . '') . '"><img src="/images/abort.gif" alt="Удалить ранг"></a>';
				$list['r0'] = $b['name'];
				$list['a'] = $a;

				if ($this->ally->owner == $this->user->id) {
					$list['r1'] = "<input type=checkbox name=\"u" . $a . "r0\"" . (($b[Alliance::CAN_DELETE_ALLIANCE] == 1) ? ' checked="checked"' : '') . ">";
				} else {
					$list['r1'] = "<b>" . (($b['delete'] == 1) ? '+' : '-') . "</b>";
				}

				if ($this->ally->owner == $this->user->id) {
					$list['r2'] = "<input type=checkbox name=\"u" . $a . "r1\"" . (($b[Alliance::CAN_KICK] == 1) ? ' checked="checked"' : '') . ">";
				} else {
					$list['r2'] = "<b>" . (($b['kick'] == 1) ? '+' : '-') . "</b>";
				}

				$list['r3']  = "<input type=checkbox name=\"u" . $a . "r2\"" .  (($b[Alliance::REQUEST_ACCESS] == 1) ? ' checked="checked"' : '') . ">";
				$list['r4']  = "<input type=checkbox name=\"u" . $a . "r3\"" .  (($b[Alliance::CAN_WATCH_MEMBERLIST] == 1) ? ' checked="checked"' : '') . ">";
				$list['r5']  = "<input type=checkbox name=\"u" . $a . "r4\"" .  (($b[Alliance::CAN_ACCEPT] == 1) ? ' checked="checked"' : '') . ">";
				$list['r6']  = "<input type=checkbox name=\"u" . $a . "r5\"" .  (($b[Alliance::ADMIN_ACCESS] == 1) ? ' checked="checked"' : '') . ">";
				$list['r7']  = "<input type=checkbox name=\"u" . $a . "r6\"" .  (($b[Alliance::CAN_WATCH_MEMBERLIST_STATUS] == 1) ? ' checked="checked"' : '') . ">";
				$list['r8']  = "<input type=checkbox name=\"u" . $a . "r7\"" .  (($b[Alliance::CHAT_ACCESS] == 1) ? ' checked="checked"' : '') . ">";
				$list['r9']  = "<input type=checkbox name=\"u" . $a . "r8\"" .  (($b[Alliance::CAN_EDIT_RIGHTS] == 1) ? ' checked="checked"' : '') . ">";
				$list['r10'] = "<input type=checkbox name=\"u" . $a . "r9\"" .  (($b[Alliance::DIPLOMACY_ACCESS] == 1) ? ' checked="checked"' : '') . ">";
				$list['r11'] = "<input type=checkbox name=\"u" . $a . "r10\"" . (($b[Alliance::PLANET_ACCESS] == 1) ? ' checked="checked"' : '') . ">";

				$parse['list'][] = $list;
			}
		}

		$this->setTitle(__('alliance.Law_settings'));

		return $parse;
	}

	public function adminRequests()
	{
		$this->parseInfo($this->user->ally_id);

		if ($this->ally->owner != $this->user->id && !$this->ally->canAccess(Alliance::CAN_ACCEPT) && !$this->ally->canAccess(Alliance::REQUEST_ACCESS)) {
			throw new ErrorException(__('alliance.Denied_access'));
		}

		$show = (int) Request::query('show', 0);

		if (($this->ally->owner == $this->user->id || $this->ally->canAccess(Alliance::CAN_ACCEPT)) && Request::post('action')) {
			if (Request::post('action') == "Принять") {
				if ($this->ally->members >= 150) {
					throw new ErrorException('Альянс не может иметь больше 150 участников');
				}

				if (Request::post('text') != '') {
					$text_ot = strip_tags(Request::post('text'));
				}

				$check = DB::selectOne("SELECT a_id FROM alliance_requests WHERE a_id = " . $this->ally->id . " AND u_id = " . $show . "");

				if ($check) {
					AllianceRequest::query()->where('u_id', $show)->delete();
					AllianceMember::query()->where('u_id', $show)->delete();

					DB::table('alliance_members')->insert(['a_id' => $this->ally->id, 'u_id' => $show, 'time' => time()]);

					DB::statement("UPDATE alliances SET members = members + 1 WHERE id = ?", [$this->ally->id]);
					DB::statement("UPDATE users SET ally_name = '" . $this->ally->name . "', ally_id = '" . $this->ally->id . "' WHERE id = '" . $show . "'");

					User::sendMessage($show, $this->user->id, 0, 2, $this->ally->tag, "Привет!<br>Альянс <b>" . $this->ally->name . "</b> принял вас в свои ряды!" . ((isset($text_ot)) ? "<br>Приветствие:<br>" . $text_ot . "" : ""));

					throw new RedirectException('Игрок принят в альянс', '/alliance/members/');
				}
			} elseif (Request::post('action') == "Отклонить") {
				if (Request::post('text') != '') {
					$text_ot = strip_tags(Request::post('text'));
				}

				AllianceRequest::query()->where('u_id', $show)->where('a_id', $this->ally->id)->delete();

				User::sendMessage($show, $this->user->id, 0, 2, $this->ally->tag, "Привет!<br>Альянс <b>" . $this->ally->name . "</b> отклонил вашу кандидатуру!" . ((isset($text_ot)) ? "<br>Причина:<br>" . $text_ot . "" : ""));
			}
		}

		$parse = [];
		$parse['list'] = [];

		$query = DB::select("SELECT u.id, u.username, r.* FROM alliance_requests r LEFT JOIN users u ON u.id = r.u_id WHERE a_id = '" . $this->ally->id . "'");

		foreach ($query as $r) {
			if (isset($show) && $r->id == $show) {
				$s = [];
				$s['username'] = $r->username;
				$s['request_text'] = nl2br($r->request);
				$s['id'] = $r->id;
			}

			$r->time = Game::datezone("Y-m-d H:i:s", $r->time);

			$parse['list'][] = $r;
		}

		if (isset($show) && $show != 0 && count($parse['list']) > 0 && isset($s)) {
			$parse['request'] = $s;
		} else {
			$parse['request'] = null;
		}

		$parse['tag'] = $this->ally->tag;

		$this->setTitle(__('alliance.Check_the_requests'));

		return $parse;
	}

	public function adminName()
	{
		$this->parseInfo($this->user->ally_id);

		if (!$this->ally->canAccess(Alliance::ADMIN_ACCESS)) {
			throw new ErrorException(__('alliance.Denied_access'));
		}

		if (Request::post('name')) {
			$name = trim(Request::post('name', ''));

			if ($name == '') {
				throw new ErrorException("Введите новое название альянса");
			}

			if (!preg_match("/^[a-zA-Zа-яА-Я0-9_.,\-!?* ]+$/u", $name)) {
				throw new ErrorException("Название альянса содержит запрещённые символы");
			}

			$this->ally->name = addslashes(htmlspecialchars($name));
			$this->ally->update();

			DB::statement("UPDATE users SET ally_name = '" . $this->ally->name . "' WHERE ally_id = '" . $this->ally->id . "';");

			throw new RedirectException('Название альянса изменено', '/alliance/admin/name');
		}

		$this->setTitle('Управление альянсом');

		return [
			'name' => $this->ally->name
		];
	}

	public function adminTag()
	{
		$this->parseInfo($this->user->ally_id);

		if (!$this->ally->canAccess(Alliance::ADMIN_ACCESS)) {
			throw new ErrorException(__('alliance.Denied_access'));
		}

		if (Request::post('tag')) {
			$tag = trim(Request::post('tag', ''));

			if ($tag == '') {
				throw new RedirectException("Введите новую абревиатуру альянса", __('alliance.make_alliance'));
			}

			if (!preg_match('/^[a-zA-Zа-яА-Я0-9_.,\-!?* ]+$/u', $tag)) {
				throw new ErrorException("Абревиатура альянса содержит запрещённые символы");
			}

			$this->ally->tag = addslashes(htmlspecialchars($tag));
			$this->ally->update();

			throw new RedirectException('Абревиатура альянса изменена', '/alliance/admin/tag');
		}

		$this->setTitle('Управление альянсом');

		return [
			'tag' => $this->ally->tag
		];
	}

	public function adminExit()
	{
		$this->parseInfo($this->user->ally_id);

		if ($this->ally->owner != $this->user->id && !$this->ally->canAccess(Alliance::CAN_DELETE_ALLIANCE)) {
			throw new ErrorException(__('alliance.Denied_access'));
		}

		$this->ally->deleteAlly();

		throw new RedirectException('Альянс удалён', '/alliance/');
	}

	public function adminGive()
	{
		$this->parseInfo($this->user->ally_id);

		if ($this->ally->owner != $this->user->id) {
			throw new RedirectException("Доступ запрещён.", "/alliance/");
		}

		if (Request::post('newleader') && $this->ally->owner == $this->user->id) {
			$info = DB::selectOne("SELECT id, ally_id FROM users WHERE id = '" . Request::post('newleader', 'int') . "'");

			if (!$info || $info->ally_id != $this->user->ally_id) {
				throw new RedirectException("Операция невозможна.", "/alliance/");
			}

			DB::statement("UPDATE alliances SET owner = '" . $info->id . "' WHERE id = " . $this->user->ally_id . " ");
			DB::statement("UPDATE alliance_members SET rank = '0' WHERE u_id = '" . $info->id . "';");

			throw new RedirectException('Правление передано', '/alliance/');
		}

		$listuser = DB::select("SELECT u.username, u.id, m.rank FROM users u LEFT JOIN alliance_members m ON m.u_id = u.id WHERE u.ally_id = '" . $this->user->ally_id . "' AND u.id != " . $this->ally->owner . " AND m.rank != 0;");

		$parse['righthand'] = '';

		foreach ($listuser as $u) {
			if ($this->ally->ranks[$u->rank - 1][Alliance::CAN_EDIT_RIGHTS] == 1) {
				$parse['righthand'] .= "<option value=\"" . $u->id . "\">" . $u->username . "&nbsp;[" . $this->ally->ranks[$u->rank - 1]['name'] . "]&nbsp;&nbsp;</option>";
			}
		}

		$parse['id'] = $this->user->id;

		$this->setTitle('Передача альянса');

		return $parse;
	}

	public function adminMembers()
	{
		$this->parseInfo($this->user->ally_id);

		if ($this->ally->owner != $this->user->id && !$this->ally->canAccess(Alliance::CAN_KICK)) {
			throw new ErrorException(__('alliance.Denied_access'));
		}

		if (Request::query('kick')) {
			$kick = Request::query('kick', 0);

			if ($this->ally->owner != $this->user->id && !$this->ally->canAccess(Alliance::CAN_KICK) && $kick > 0) {
				throw new ErrorException(__('alliance.Denied_access'));
			}

			$u = DB::selectOne("SELECT * FROM users WHERE id = '" . $kick . "' LIMIT 1");

			if ($u->ally_id == $this->ally->id && $u->id != $this->ally->owner) {
				DB::statement("UPDATE planets SET id_ally = 0 WHERE id_owner = " . $u->id . " AND id_ally = " . $this->ally->id . "");

				DB::statement("UPDATE users SET ally_id = '0', ally_name = '' WHERE id = '" . $u->id . "'");
				DB::statement("DELETE FROM alliance_members WHERE u_id = " . $u->id . ";");
			} else {
				throw new ErrorException(__('alliance.Denied_access'));
			}
		} elseif (Request::post('newrang', '') != '' && Request::input('id', 0) != 0) {
			$id = Request::input('id', 0);
			$rank = Request::post('newrang', 0);

			$q = DB::selectOne("SELECT id, ally_id FROM users WHERE id = '" . $id . "' LIMIT 1");

			if ((isset($this->ally->ranks[$rank - 1]) || $rank == 0) && $q->id != $this->ally->owner && $q->ally_id == $this->ally->id) {
				DB::statement("UPDATE alliance_members SET rank = '" . $rank . "' WHERE u_id = '" . $id . "';");
			}
		}

		return $this->members();
	}

	public function diplomacy()
	{
		$this->parseInfo($this->user->ally_id);

		if ($this->ally->owner != $this->user->id && !$this->ally->canAccess(Alliance::DIPLOMACY_ACCESS)) {
			throw new ErrorException(__('alliance.Denied_access'));
		}

		$parse['DText'] = $parse['DMyQuery'] = $parse['DQuery'] = [];

		if (Request::query('edit')) {
			if (Request::query('edit', '') == "add") {
				$st = (int) Request::post('status', 0);
				$al = DB::selectOne("SELECT id, name FROM alliances WHERE id = '" . intval(Request::post('ally')) . "'");

				if (!$al) {
					throw new RedirectException("Ошибка ввода параметров", "/alliance/diplomacy/");
				}

				$ad = DB::select("SELECT id FROM alliance_diplomacy WHERE a_id = " . $this->ally->id . " AND d_id = " . $al->id . "");

				if (count($ad)) {
					throw new RedirectException("У вас уже есть соглашение с этим альянсом. Разорвите старое соглашения прежде чем создать новое.", "/alliance/diplomacy/");
				}

				if ($st < 0 || $st > 3) {
					$st = 0;
				}

				DB::statement("INSERT INTO alliance_diplomacies VALUES (NULL, " . $this->ally->id . ", " . $al->id . ", " . $st . ", 0, 1)");
				DB::statement("INSERT INTO alliance_diplomacies VALUES (NULL, " . $al->id . ", " . $this->ally->id . ", " . $st . ", 0, 0)");

				throw new RedirectException("Отношение между вашими альянсами успешно добавлено", "/alliance/diplomacy/");
			} elseif (Request::query('edit', '') == "del") {
				$al = DB::selectOne("SELECT a_id, d_id FROM alliance_diplomacies WHERE id = '" . (int) Request::query('id') . "' AND a_id = " . $this->ally->id . "");

				if (!$al) {
					throw new RedirectException("Ошибка ввода параметров", "/alliance/diplomacy/");
				}

				DB::statement("DELETE FROM alliance_diplomacies WHERE a_id = " . $al->a_id . " AND d_id = " . $al->d_id . ";");
				DB::statement("DELETE FROM alliance_diplomacies WHERE a_id = " . $al->d_id . " AND d_id = " . $al->a_id . ";");

				throw new RedirectException("Отношение между вашими альянсами расторжено", "/alliance/diplomacy/");
			} elseif (Request::query('edit', '') == "suc") {
				$al = DB::selectOne("SELECT a_id, d_id FROM alliance_diplomacies WHERE id = '" . (int) Request::query('id') . "' AND a_id = " . $this->ally->id . "");

				if (!$al) {
					throw new RedirectException("Ошибка ввода параметров", "/alliance/diplomacy/");
				}

				DB::statement("UPDATE alliance_diplomacies SET status = 1 WHERE a_id = " . $al->a_id . " AND d_id = " . $al->d_id . ";");
				DB::statement("UPDATE alliance_diplomacies SET status = 1 WHERE a_id = " . $al->d_id . " AND d_id = " . $al->a_id . ";");

				throw new RedirectException("Отношение между вашими альянсами подтверждено", "/alliance/diplomacy/");
			}
		}

		$dp = DB::select("SELECT ad.*, a.name FROM alliance_diplomacies ad, alliances a WHERE a.id = ad.d_id AND ad.a_id = '" . $this->ally->id . "'");

		foreach ($dp as $diplo) {
			if ($diplo->status == 0) {
				if ($diplo->primary == 1) {
					$parse['DMyQuery'][] = (array) $diplo;
				} else {
					$parse['DQuery'][] = (array) $diplo;
				}
			} else {
				$parse['DText'][] = (array) $diplo;
			}
		}

		$parse['a_list'] = [];

		$ally_list = DB::select("SELECT id, name, tag FROM alliance WHERE id != " . $this->user->ally_id . " AND members > 0");

		foreach ($ally_list as $a_list) {
			$parse['a_list'][] = (array) $a_list;
		}

		$this->setTitle('Дипломатия');

		return $parse;
	}

	public function exit()
	{
		$this->parseInfo($this->user->ally_id);

		if ($this->ally->owner == $this->user->id) {
			throw new ErrorException(__('alliance.Owner_cant_go_out'));
		}

		if (Request::query('yes')) {
			$this->ally->deleteMember($this->user->id);

			$html = $this->MessageForm(__('alliance.Go_out_welldone'), "<br>", '/alliance/', __('alliance.Ok'));
		} else {
			$html = $this->MessageForm(__('alliance.Want_go_out'), "<br>", "/alliance/exit/yes/1/", "Подтвердить");
		}

		$this->setTitle('Выход их альянса');
	}

	public function members()
	{
		$this->parseInfo($this->user->ally_id);

		$parse = [];

		if (Route::currentRouteAction() == 'admin') {
			$parse['admin'] = true;
		} else {
			if ($this->ally->owner != $this->user->id && !$this->ally->canAccess(Alliance::CAN_WATCH_MEMBERLIST)) {
				throw new ErrorException(__('alliance.Denied_access'));
			}

			$parse['admin'] = false;
		}

		$sort1 = Request::query('sort1', 0);
		$sort2 = Request::query('sort2', 0);

		$rank = Request::query('rank', 0);

		$sort = "";

		if ($sort2) {
			if ($sort1 == 1) {
				$sort = " ORDER BY u.username";
			} elseif ($sort1 == 2) {
				$sort = " ORDER BY m.rank";
			} elseif ($sort1 == 3) {
				$sort = " ORDER BY s.total_points";
			} elseif ($sort1 == 4) {
				$sort = " ORDER BY m.time";
			} elseif ($sort1 == 5 && $this->ally->canAccess(Alliance::CAN_WATCH_MEMBERLIST_STATUS)) {
				$sort = " ORDER BY u.onlinetime";
			} else {
				$sort = " ORDER BY u.id";
			}

			if ($sort2 == 1) {
				$sort .= " DESC;";
			} elseif ($sort2 == 2) {
				$sort .= " ASC;";
			}
		}
		$listuser = DB::select("SELECT u.id, u.username, u.race, u.galaxy, u.system, u.planet, u.onlinetime, m.rank, m.time, s.total_points FROM users u LEFT JOIN alliance_members m ON m.u_id = u.id LEFT JOIN statpoints s ON s.id_owner = u.id AND stat_type = 1 WHERE u.ally_id = '" . $this->user->ally_id . "'" . $sort . "");

		$i = 0;
		$parse['memberslist'] = [];

		foreach ($listuser as $u) {
			$i++;
			$u->i = $i;

			if ($u->onlinetime + 60 * 10 >= time() && $this->ally->canAccess(Alliance::CAN_WATCH_MEMBERLIST_STATUS)) {
				$u->onlinetime = "<span class='positive'>" . __('alliance.On') . "</span>";
			} elseif ($u->onlinetime + 60 * 20 >= time() && $this->ally->canAccess(Alliance::CAN_WATCH_MEMBERLIST_STATUS)) {
				$u->onlinetime = "<span class='neutral'>" . __('alliance.15_min') . "</span>";
			} elseif ($this->ally->canAccess(Alliance::CAN_WATCH_MEMBERLIST_STATUS)) {
				$hours = floor((time() - $u->onlinetime) / 3600);

				$u->onlinetime = "<span class='negative'>" . __('alliance.Off') . " " . Format::time($hours * 3600) . "</span>";
			}

			if ($this->ally->owner == $u->id) {
				$u->range = ($this->ally->owner_range == '') ? "Основатель" : $this->ally->owner_range;
			} elseif (isset($this->ally->ranks[$u->rank - 1]['name'])) {
				$u->range = $this->ally->ranks[$u->rank - 1]['name'];
			} else {
				$u->range = __('alliance.Novate');
			}

			$u->points = Format::number($u->total_points);
			$u->time = ($u->time > 0) ? Game::datezone("d.m.Y H:i", $u->time) : '-';

			$parse['memberslist'][] = (array) $u;

			if ($rank == $u->id && $parse['admin']) {
				$r['Rank_for'] = 'Установить ранг для ' . $u->username;
				$r['options'] = "<option value=\"0\">Новичок</option>";

				if (is_array($this->ally->ranks) && count($this->ally->ranks)) {
					foreach ($this->ally->ranks as $a => $b) {
						$r['options'] .= "<option value=\"" . ($a + 1) . "\"";

						if ($u->rank - 1 == $a) {
							$r['options'] .= ' selected=selected';
						}

						$r['options'] .= ">" . $b['name'] . "</option>";
					}
				}

				$r['id'] = $u->id;

				$parse['memberslist'][] = $r;
			}
		}

		if ($sort2 == 1) {
			$s = 2;
		} elseif ($sort2 == 2) {
			$s = 1;
		} else {
			$s = 1;
		}

		if ($i != $this->ally->members) {
			DB::statement("UPDATE alliances SET members = '" . $i . "' WHERE id = '" . $this->ally->id . "'");
		}

		$parse['i'] = $i;
		$parse['s'] = $s;
		$parse['status'] = $this->ally->canAccess(Alliance::CAN_WATCH_MEMBERLIST_STATUS);

		$this->setTitle(__('alliance.Members_list'));

		return $parse;
	}

	public function chat()
	{
		if ($this->user->messages_ally != 0) {
			$this->user->messages_ally = 0;
			$this->user->update();
		}

		$this->parseInfo($this->user->ally_id);

		if ($this->ally->owner != $this->user->id && !$this->ally->canAccess(Alliance::CHAT_ACCESS)) {
			throw new ErrorException(__('alliance.Denied_access'));
		}

		if (Request::post('delete_type') && $this->ally->owner == $this->user->id) {
			$deleteType = Request::post('delete_type');

			if ($deleteType == 'all') {
				DB::statement("DELETE FROM alliance_chats WHERE ally_id = :ally", ['ally' => $this->user->ally_id]);
			} elseif ($deleteType == 'marked' || $deleteType == 'unmarked') {
				$messages = Request::post('delete');

				if (is_array($messages)) {
					$messages = array_map('intval', $messages);

					if (count($messages)) {
						DB::statement("DELETE FROM alliance_chats WHERE id " . (($deleteType == 'unmarked') ? 'NOT' : '') . " IN (" . implode(',', $messages) . ") AND ally_id = :ally", ['ally' => $this->user->ally_id]);
					}
				}
			}
		}

		if (Request::has('text') && Request::post('text', '') != '') {
			DB::table('alliance_chats')->insert([
				'ally_id' 	=> $this->user->ally_id,
				'user' 		=> $this->user->username,
				'user_id' 	=> $this->user->id,
				'message' 	=> Format::text(Request::post('text')),
				'timestamp'	=> time()
			]);

			DB::statement("UPDATE users SET messages_ally = messages_ally + '1' WHERE ally_id = '" . $this->user->ally_id . "' AND id != " . $this->user->id . "");

			throw new RedirectException('Сообщение отправлено', '/alliance/chat/');
		}

		$parse = [];
		$parse['items'] = [];

		$messagesCount = DB::selectOne("SELECT COUNT(*) AS num FROM alliance_chats WHERE ally_id = ?", [$this->user->ally_id])->num;

		$parse['pagination'] = [
			'total' => (int) $messagesCount,
			'limit' => 10,
			'page' => (int) Request::query('p', 1)
		];

		if ($messagesCount > 0) {
			$mess = DB::select("SELECT * FROM alliance_chat WHERE ally_id = '" . $this->user->ally_id . "' ORDER BY id DESC limit " . (($parse['pagination']['page'] - 1) * $parse['pagination']['limit']) . ", " . $parse['pagination']['limit'] . "");

			foreach ($mess as $mes) {
				$parse['items'][] = [
					'id' => (int) $mes->id,
					'user' => $mes->user,
					'user_id' => (int) $mes->user_id,
					'time' => (int) $mes->timestamp,
					'text' => str_replace(["\r\n", "\n", "\r"], '', stripslashes($mes->message)),
				];
			}
		}

		$parse['owner'] = ($this->ally->owner == $this->user->id) ? true : false;
		$parse['parser'] = $this->user->getUserOption('bb_parser') ? true : false;

		$this->setTitle('Альянс-чат');

		return $parse;
	}

	public function info($id)
	{
		if ($id != '' && !is_numeric($id)) {
			$allyrow = DB::selectOne("SELECT * FROM alliances WHERE tag = '" . addslashes(htmlspecialchars($id)) . "'");
		} elseif ($id > 0 && is_numeric($id)) {
			$allyrow = DB::selectOne("SELECT * FROM alliances WHERE id = '" . (int) $id . "'");
		} else {
			throw new ErrorException("Указанного альянса не существует в игре!");
		}

		if (!$allyrow) {
			throw new ErrorException("Указанного альянса не существует в игре!");
		}

		if ($allyrow->description == "") {
			$allyrow->description = "[center]У этого альянса ещё нет описания[/center]";
		}

		$parse['id'] = $allyrow->id;
		$parse['member_scount'] = $allyrow->members;
		$parse['name'] = $allyrow->name;
		$parse['tag'] = $allyrow->tag;
		$parse['description'] = str_replace(["\r\n", "\n", "\r"], '', stripslashes($allyrow->description));
		$parse['image'] = '';

		if ($allyrow->image > 0) {
			$file = Files::getById($allyrow->image);

			if ($file) {
				$parse['image'] = $file['src'];
			}
		}

		if ($allyrow->web != '' && strpos($allyrow->web, 'http') === false) {
			$allyrow->web = 'http://' . $allyrow->web;
		}

		$parse['web'] = $allyrow->web;
		$parse['request'] = ($this->user && $this->user->ally_id == 0);

		$this->setTitle('Альянс ' . $allyrow->name);

		return $parse;
	}

	public function make()
	{
		if (!Auth::check()) {
			throw new ErrorException(__('alliance.Denied_access'));
		}

		$ally_request = AllianceRequest::query()->where('u_id', $this->user->id)->count();

		if ($this->user->ally_id > 0 || $ally_request) {
			throw new ErrorException(__('alliance.Denied_access'));
		}

		if (Request::instance()->isMethod('post')) {
			$tag = Request::post('tag', '');
			$name = Request::post('name', '');

			if ($tag == '') {
				throw new ErrorException(__('alliance.have_not_tag'));
			}
			if ($name == '') {
				throw new ErrorException(__('alliance.have_not_name'));
			}
			if (!preg_match('/^[a-zA-Zа-яА-Я0-9_.,\-!?* ]+$/u', $tag)) {
				throw new ErrorException("Абревиатура альянса содержит запрещённые символы");
			}
			if (!preg_match('/^[a-zA-Zа-яА-Я0-9_.,\-!?* ]+$/u', $name)) {
				throw new ErrorException("Название альянса содержит запрещённые символы");
			}

			$find = Alliance::query()->where('tag', addslashes($tag))->exists();

			if ($find) {
				throw new ErrorException(str_replace('%s', $tag, __('alliance.always_exist')));
			}

			$alliance = new Alliance();

			$alliance->name = addslashes($name);
			$alliance->tag = addslashes($tag);
			$alliance->owner = $this->user->id;
			$alliance->create_time = time();

			if (!$alliance->save()) {
				throw new ErrorException('Произошла ошибка при создании альянса');
			}

			$member = new AllianceMember();

			$member->a_id = $alliance->id;
			$member->u_id = $this->user->id;
			$member->time = time();

			if (!$member->save()) {
				throw new ErrorException('Произошла ошибка при создании альянса');
			}

			$this->user->ally_id = $alliance->id;
			$this->user->ally_name = $alliance->name;
			$this->user->update();

			$this->setTitle(__('alliance.make_alliance'));

			throw new PageException(str_replace('%s', $alliance->tag, __('alliance.alliance_has_been_maked')), '/alliance/');
		} else {
			$this->setTitle(__('alliance.make_alliance'));
		}

		return [];
	}

	public function search()
	{
		if (!Auth::check()) {
			throw new ErrorException(__('alliance.Denied_access'));
		}

		$ally_request = AllianceRequest::query()->where('u_id', $this->user->id)->count();

		if ($this->user->ally_id > 0 || $ally_request) {
			throw new ErrorException(__('alliance.Denied_access'));
		}

		$parse = [];
		$parse['result'] = [];

		$text = '';

		if (Request::post('searchtext') && Request::post('searchtext') != '') {
			$text = Request::post('searchtext');

			if (!preg_match('/^[a-zA-Zа-яА-Я0-9_.,\-!?* ]+$/u', $text)) {
				throw new RedirectException("Строка поиска содержит запрещённые символы", '/alliance/search/');
			}

			$search = Alliance::query()->where('name', 'LIKE', '%' . $text . '%')
				->orWhere('tag', 'LIKE', '%' . $text . '%')
				->limit(30)->get();

			if ($search->count()) {
				foreach ($search as $s) {
					$entry = [];

					$entry['tag'] = "[<a href=\"" . URL::to('alliance/apply/allyid/' . $s->id . '/') . "\">" . $s->tag . "</a>]";
					$entry['name'] = $s->name;
					$entry['members'] = $s->members;

					$parse['result'][] = $entry;
				}
			}
		}

		$parse['searchtext'] = $text;

		$this->setTitle(__('alliance.search_alliance'));

		return $parse;
	}

	public function apply()
	{
		if (!Auth::check()) {
			throw new ErrorException(__('alliance.Denied_access'));
		}

		if ($this->user->ally_id > 0) {
			throw new ErrorException(__('alliance.Denied_access'));
		}

		$allyid = Request::query('allyid', 0);

		if ($allyid <= 0) {
			throw new ErrorException(__('alliance.it_is_not_posible_to_apply'));
		}

		$allyrow = DB::selectOne("SELECT tag, request, request_notallow FROM alliances WHERE id = '" . $allyid . "'");

		if (!$allyrow) {
			throw new ErrorException("Альянса не существует!");
		}

		if ($allyrow->request_notallow != 0) {
			throw new ErrorException("Данный альянс является закрытым для вступлений новых членов");
		}

		if (Request::post('further')) {
			$request = DB::selectOne("SELECT COUNT(*) AS num FROM alliance_requests WHERE a_id = " . $allyid . " AND u_id = " . $this->user->id . ";");

			if ($request->num == 0) {
				DB::statement("INSERT INTO alliance_requests VALUES (" . $allyid . ", " . $this->user->id . ", " . time() . ", '" . strip_tags(Request::post('text')) . "')");

				throw new RedirectException(__('alliance.apply_registered'), '/alliance/');
			} else {
				throw new RedirectException('Вы уже отсылали заявку на вступление в этот альянс!', '/alliance/');
			}
		}

		$parse = [];

		$parse['allyid'] = $allyid;
		$parse['text_apply'] = ($allyrow->request) ? str_replace(["\r\n", "\n", "\r"], '', stripslashes($allyrow->request)) : '';
		$parse['tag'] = $allyrow->tag;

		$this->setTitle('Запрос на вступление');

		return $parse;
	}

	public function stat($id)
	{
		if (!Auth::check()) {
			throw new PageException(__('alliance.Denied_access'));
		}

		$id = (int) $id;

		$allyrow = DB::selectOne("SELECT id, name FROM alliances WHERE id = '" . $id . "'");

		if (!$allyrow) {
			throw new PageException('Информация о данном альянсе не найдена');
		}

		$parse = [];
		$parse['name'] = $allyrow->name;
		$parse['points'] = [];

		$items = DB::select("SELECT * FROM log_stats WHERE object_id = " . $id . " AND time > " . (time() - 14 * 86400) . " AND type = 2 ORDER BY time ASC");

		foreach ($items as $item) {
			$parse['points'][] = [
				'date' => (int) $item->time,
				'rank' => [
					'tech' => (int) $item->tech_rank,
					'build' => (int) $item->build_rank,
					'defs' => (int) $item->defs_rank,
					'fleet' => (int) $item->fleet_rank,
					'total' => (int) $item->total_rank,
				],
				'point' => [
					'tech' => (int) $item->tech_points,
					'build' => (int) $item->build_points,
					'defs' => (int) $item->defs_points,
					'fleet' => (int) $item->fleet_points,
					'total' => (int) $item->total_points,
				]
			];
		}

		$this->setTitle('Статистика альянса');

		return $parse;
	}

	private function MessageForm($Title, $Message, $Goto = '', $Button = ' ok ', $TwoLines = false)
	{
		$Form = "<form action=\"" . URL::to(ltrim($Goto, '/')) . "\" method=\"post\">";
		$Form .= "<table width=\"100%\"><tr>";
		$Form .= "<td class=\"c\">" . $Title . "</td>";
		$Form .= "</tr><tr>";

		if ($TwoLines == true) {
			$Form .= "<th >" . $Message . "</th>";
			$Form .= "</tr><tr>";
			$Form .= "<th align=\"center\"><input type=\"submit\" value=\"" . $Button . "\"></th>";
		} else {
			$Form .= "<th>" . $Message . "<input type=\"submit\" value=\"" . $Button . "\"></th>";
		}

		$Form .= "</tr></table></form>";

		return $Form;
	}
}