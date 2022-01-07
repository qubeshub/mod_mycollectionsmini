<?php
/**
 * @package    hubzero-cms
 * @copyright  Copyright (c) 2005-2020 The Regents of the University of California.
 * @license    http://opensource.org/licenses/MIT MIT
 */

namespace Modules\MyCollectionsMini;

use Hubzero\Module\Module;
use Components\Groups\Models\Recent;
use Hubzero\User\Group;
use User;

/**
 * Module class for displaying a list of groups for a user
 */
class Helper extends Module
{
	/**
	 * Get groups for a user
	 *
	 * @param   integer  $uid   User ID
	 * @param   string   $type  Membership type to return groups for
	 * @return  array
	 */
	private function _getGroups($uid, $type='all', $groups=array())
	{
		$db = \App::get('db');

		$where = '';
		if (!$this->params->get('include_archived', 1))
		{
			$where = " AND g.published != 2";
		}

		// Get all groups the user is a member of
		$query1 = "SELECT g.gidNumber, g.published, g.approved, g.description, g.cn, '1' AS registered, '0' AS regconfirmed, '0' AS manager
				   FROM `#__xgroups` AS g, `#__xgroups_applicants` AS m
				   WHERE (g.type='1' || g.type='3') AND m.gidNumber=g.gidNumber AND m.uidNumber=" . $uid . $where;

		$query2 = "SELECT g.gidNumber, g.published, g.approved, g.description, g.cn, '1' AS registered, '1' AS regconfirmed, '0' AS manager
				   FROM `#__xgroups` AS g, `#__xgroups_members` AS m
				   WHERE (g.type='1' || g.type='3') AND m.uidNumber NOT IN
						(SELECT uidNumber
						 FROM `#__xgroups_managers` AS manager
						 WHERE manager.gidNumber = m.gidNumber)
				   AND m.gidNumber=g.gidNumber AND m.uidNumber=" . $uid . $where;

		$query3 = "SELECT g.gidNumber, g.published, g.approved, g.description, g.cn, '1' AS registered, '1' AS regconfirmed, '1' AS manager
				   FROM `#__xgroups` AS g, `#__xgroups_managers` AS m
				   WHERE (g.type='1' || g.type='3') AND m.gidNumber=g.gidNumber AND m.uidNumber=" . $uid . $where;

		$query4 = "SELECT g.gidNumber, g.published, g.approved, g.description, g.cn, '0' AS registered, '1' AS regconfirmed, '0' AS manager
				   FROM `#__xgroups` AS g, `#__xgroups_invitees` AS m
				   WHERE (g.type='1' || g.type='3') AND m.gidNumber=g.gidNumber AND m.uidNumber=" . $uid . $where;

		switch ($type)
		{
			case 'all':
				$query = "( $query1 ) UNION ( $query2 ) UNION ( $query3 ) UNION ( $query4 ) ORDER BY description ASC";
			break;
			case 'applicants':
				$query = $query1;
			break;
			case 'members':
				$query = $query2;
			break;
			case 'managers':
				$query = $query3;
			break;
			case 'invitees':
				$query = $query4;
			break;
		}

		if (!empty($groups))
		{
			$query .= " WHERE g.cn IN (" . implode(',', $groups) . ")";
		}

		$db->setQuery($query);
		$db->query();

		return $db->loadObjectList();
	}

	/**
	 * Get the user's status in the gorup
	 *
	 * @param   object  $group  Group to check status in
	 * @return  string
	 */
	public function getStatus($group)
	{
		if ($group->manager)
		{
			$status = 'manager';
		}
		else
		{
			if ($group->registered)
			{
				if ($group->regconfirmed)
				{
					$status = 'member';
				}
				else
				{
					$status = 'pending';
				}
			}
			else
			{
				if ($group->regconfirmed)
				{
					$status = 'invitee';
				}
				else
				{
					$status = '';
				}
			}
		}
		return $status;
	}

	/**
	 * Display module contents
	 *
	 * @return  void
	 */
	public function display()
	{
		// Get the module parameters
		$this->moduleclass = $this->params->get('moduleclass');
		$this->limit = intval($this->params->get('limit', 100));
		$this->recentgroups = array();

		// Get the user's groups
		$this->allgroups = $this->_getGroups(User::get('id'), 'all');

		include_once \Component::path('com_groups') . DS . 'models' . DS . 'recent.php';

		$recents = Recent::all()
			->whereEquals('user_id', User::get('id'))
			->order('created', 'desc')
			->limit(5)
			->rows();

		foreach ($recents as $recent)
		{
			foreach ($this->allgroups as $group)
			{
				if ($recent->get('group_id') == $group->gidNumber)
				{
					$this->recentgroups[] = $group;
				}
			}
		}

		if (!User::authorise('core.create', 'com_groups'))
		{
			$this->params->set('button_show_add', 0);
		}

		$layout = 'default';
		if (!$this->params->get('show_recent', 1))
		{
			$layout = 'simple';
		}

		require $this->getLayoutPath($layout);
	}
}
