<?php

namespace Xnova\Http\Controllers;

/**
 * @author AlexPro
 * @copyright 2008 - 2018 XNova Game Group
 * Telegram: @alexprowars, Skype: alexprowars, Email: alexprowars@gmail.com
 */

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;
use Xnova\Controller;
use Xnova\Exceptions\ErrorException;

class ContentController extends Controller
{
	function index ()
	{
		if (!Input::query('article'))
			throw new ErrorException('Страница не найдена!');

		$content = DB::selectOne("SELECT * FROM content WHERE alias = '".Input::query('article')."'");

		if (!$content)
			throw new ErrorException('Страница не найдена!');

		$this->setTitle($content->title);
		$this->showTopPanel(false);

		return [
			'html' => stripslashes($content->html)
		];
	}
}