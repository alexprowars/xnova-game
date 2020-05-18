<?php

namespace Xnova\Missions;

/**
 * @author AlexPro
 * @copyright 2008 - 2019 XNova Game Group
 * Telegram: @alexprowars, Skype: alexprowars, Email: alexprowars@gmail.com
 */

use Xnova\FleetEngine;
use Xnova\User;

class StayAlly extends FleetEngine implements Mission
{
	public function targetEvent()
	{
		$this->StayFleet();

		$Message = sprintf(__('fleet_engine.sys_stay_mess_user'), $this->fleet->owner_name, $this->fleet->getStartAdressLink(), $this->fleet->target_owner_name, $this->fleet->getTargetAdressLink());

		User::sendMessage($this->fleet->owner, 0, $this->fleet->start_time, 0, __('fleet_engine.sys_mess_tower'), $Message);
	}

	public function endStayEvent()
	{
		$this->ReturnFleet();
	}

	public function returnEvent()
	{
		$this->RestoreFleetToPlanet();
		$this->KillFleet();
	}
}