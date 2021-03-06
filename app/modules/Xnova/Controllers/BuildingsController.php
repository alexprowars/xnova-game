<?php

namespace Xnova\Controllers;

/**
 * @author AlexPro
 * @copyright 2008 - 2018 XNova Game Group
 * Telegram: @alexprowars, Skype: alexprowars, Email: alexprowars@gmail.com
 */

use Xnova\Construction;
use Friday\Core\Lang;
use Xnova\Controller;
use Xnova\Exceptions\PageException;
use Xnova\Request;

/**
 * @RoutePrefix("/buildings")
 * @Route("/")
 * @Route("/{action}/")
 * @Route("/{action}{params:(/.*)*}")
 * @Private
 */
class BuildingsController extends Controller
{
	public function initialize ()
	{
		parent::initialize();

		if ($this->dispatcher->wasForwarded())
			return;

		Lang::includeLang('buildings', 'xnova');

		$this->user->loadPlanet();

		if ($this->user->vacation > 0)
			throw new PageException("Нет доступа!");
	}

	public function indexAction ()
	{
		$construction = new Construction($this->user, $this->planet);
		$parse = $construction->pageBuilding();

		Request::addData('page', $parse);

		$this->tag->setTitle('Постройки');
	}
}