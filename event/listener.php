<?php
/**
*
* @package phpBB Extension - New Topic
* @copyright (c) 2015 dmzx - http://www.dmzx-web.net
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace dmzx\newtopic\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
* Event listener
*/
class listener implements EventSubscriberInterface
{
	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\auth\auth */
	protected $auth;

	/** @var \phpbb\user */
	protected $user;

	/**
	* Constructor
	*
	* @param \phpbb\template\template			$template
	* @param \phpbb\db\driver\driver_interface	$db
	* @param \phpbb\auth\auth					$auth
	* @param \phpbb\user						$user
	*
	*/

	public function __construct(\phpbb\template\template $template, \phpbb\db\driver\driver_interface $db, \phpbb\auth\auth $auth, \phpbb\user $user)
	{
		$this->template				= $template;
		$this->db					= $db;
		$this->auth 				= $auth;
		$this->user 				= $user;
	}

	static public function getSubscribedEvents()
	{
		return array(
			'core.page_header'		=> 'page_header',
		);
	}

	public function page_header($event)
	{
		// add lang file
		$this->user->add_lang_ext('dmzx/newtopic', 'common');

		$forum_box = $this->make_forum_select2(false, false, false, false);

		$this->template->assign_vars(array(
			'EXT_NEW_TOPIC'		=> $forum_box,
		));
	}

	private function make_forum_select2($select_id = false, $ignore_id = false, $ignore_acl = false, $ignore_nonpost = false, $ignore_emptycat = true, $only_acl_post = false, $return_array = false)
	{
		$sql = 'SELECT forum_id, forum_name, parent_id, forum_type, forum_flags, forum_options, left_id, right_id
			FROM ' . FORUMS_TABLE . '
			ORDER BY left_id ASC';
		$result = $this->db->sql_query($sql, 600);

		$right = 0;
		$padding_store = array('0' => '');
		$padding = '';
		$forum_list = ($return_array) ? array() : '';

		while ($row = $this->db->sql_fetchrow($result))
		{
			if ($row['left_id'] < $right)
			{
				$padding .= '&nbsp; &nbsp;';
				$padding_store[$row['parent_id']] = $padding;
			}
			else if ($row['left_id'] > $right + 1)
			{
				$padding = (isset($padding_store[$row['parent_id']])) ? $padding_store[$row['parent_id']] : '';
			}

			$right = $row['right_id'];
			$disabled = false;

			if (!$ignore_acl && $this->auth->acl_get('f_list', $row['forum_id']))
			{
				if ($only_acl_post && !$this->auth->acl_get('f_post', $row['forum_id']) || (!$this->auth->acl_get('m_approve', $row['forum_id']) && !$this->auth->acl_get('f_noapprove', $row['forum_id'])))
				{
					$disabled = true;
				}
				else if (!$only_acl_post && !$this->auth->acl_gets(array('f_list', 'a_forum', 'a_forumadd', 'a_forumdel'), $row['forum_id']))
				{
					$disabled = true;
				}
			}
			else if (!$ignore_acl)
			{
				continue;
			}

			if (((is_array($ignore_id) && in_array($row['forum_id'], $ignore_id)) || $row['forum_id'] == $ignore_id) ||	($row['forum_type'] == FORUM_CAT && ($row['left_id'] + 1 == $row['right_id']) && $ignore_emptycat) || ($row['forum_type'] != FORUM_POST && $ignore_nonpost))
			{
				$disabled = true;
			}

			if ($return_array)
			{
				$selected = (is_array($select_id)) ? ((in_array($row['forum_id'], $select_id)) ? true : false) : (($row['forum_id'] == $select_id) ? true : false);
				$forum_list[$row['forum_id']] = array_merge(array('padding' => $padding, 'selected' => ($selected && !$disabled), 'disabled' => $disabled), $row);
			}
			else
			{
				$selected = (is_array($select_id)) ? ((in_array($row['forum_id'], $select_id)) ? ' selected="selected"' : '') : (($row['forum_id'] == $select_id) ? ' selected="selected"' : '');
				$forum_list .= '<option value="posting.php?mode=post&amp;f=' . $row['forum_id'] . '"' . (($disabled) ? ' disabled="disabled" class="disabled-option"' : $selected) . '>' . $padding . $row['forum_name'] . '</option>';
			}
		}
		return $forum_list;
	}
}
