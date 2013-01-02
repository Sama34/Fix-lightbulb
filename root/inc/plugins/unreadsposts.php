<?php
/*
	Plugin:		UnreadPosts Plugin
	Version:	1.4
	Author:		Lukasz "LukasAMD" Tkacz 
	Date:		17.09.2010
	
	Free for non-commercial purposes!
	License: GNU Public License
	http://opensource.org/licenses/gpl-3.0.html
*/


if (!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}


// Add hooks
$plugins->add_hook('search_start', 'unreadposts_search');
$plugins->add_hook('global_start', 'unreadposts_link');
$plugins->add_hook('member_do_register_end', 'unreadposts_updatemark');
$plugins->add_hook('misc_markread_end', 'unreadposts_updatemark');


// Stanard MyBB function with informations about plugin
function unreadsposts_info()
{
	global $lang;

	$lang->load("unreadsposts");

	return Array(
		'name' => $lang->unreadsposts_plugin_Name,
		'description' => $lang->unreadsposts_plugin_Desc,
		'website' => 'http://mybboard.pl',
		'author' => $lang->unreadsposts_plugin_Author,
		'authorsite' => 'http://lukasamd.pl',
		'version' => '1.3',
		'guid' => '2817698896addbff5ef705626b7e1a36',
		'compatibility' => '16*'
	);
}



// START - Standard MyBB installation functions
function unreadsposts_install() 
{
  global $mybb, $db;
  
  $sql = "ALTER TABLE " . TABLE_PREFIX . "users ADD COLUMN lastmark INT(10) NOT NULL DEFAULT '0'";
  $db->query($sql);
  
  $sql = "UPDATE " . TABLE_PREFIX . "users
          SET lastmark = lastvisit";
  $db->query($sql);
}

function unreadsposts_is_installed() 
{
  global $mybb;
  
  if (isset($mybb->user['lastmark']))
  {
    return true;
  }
  else
  {
    return false;
  }
}

function unreadsposts_uninstall() 
{
  global $mybb, $db;
  
  $sql = "ALTER TABLE " . TABLE_PREFIX . "users DROP lastmark";
  $db->query($sql);
}
// END - Standard MyBB installation functions


// START - Standard MyBB activation functions
function unreadsposts_activate() 
{
  global $lang;
	include MYBB_ROOT . '/inc/adminfunctions_templates.php';

  find_replace_templatesets('header_welcomeblock_member', '#' . preg_quote('{$lang->welcome_todaysposts}</a>') . '#', '{$lang->welcome_todaysposts}</a>{$unreadspost}');
}


function unreadsposts_is_activated() 
{
  global $mybb;
  
  if (isset($mybb->user['lastmark']))
  {
    return true;
  }
  else
  {
    return false;
  }
}

function unreadsposts_deactivate() 
{
  global $lang, $db;
	include MYBB_ROOT . '/inc/adminfunctions_templates.php';

  find_replace_templatesets('header_welcomeblock_member', '#' . preg_quote('{$unreadspost}') . '#', '');
}
// END - Standard MyBB activation functions



// MyBB Template functions doesn't work in previous version of this plugin (or my regex is so stupid)
// In effect, I decided to use global hook to add new link for browsing unread posts
function unreadposts_link()
{
  global $lang, $unreadspost, $mybb;
  $lang->load("unreadsposts"); 
  
  $unreadspost =  ' | <a href="' . $mybb->settings['bburl'] . '/search.php?action=unreads">' . $lang->unreadsposts_plugin_Link . '</a>';
  return true;
} 


// This function is called when users register and when click "Mark All Forums Read"
// We need lastmark timestamp to search additional posts 
function unreadposts_updatemark()
{
  global $db, $user_info, $mybb;

  $uid = (isset($user_info['uid'])) ? $user_info['uid'] : $mybb->user['uid'];

  $sql = "UPDATE " . TABLE_PREFIX . "users
          SET lastmark = '" . time() . "'
          WHERE uid = '" . $uid . "'";
  $db->query($sql);
  return true;
} 


// This function is called, when users use view unread posts function
// It search posts using threadsread forumsread (tables) and lastmark (field) 
function unreadposts_search()
{
  global $mybb, $db, $lang, $templates, $theme, $plugins, $version;

  if($mybb->input['action'] == 'unreads')
  {
    // Make a query to search topics with unread posts
    $sql = "SELECT t.tid
            FROM " . TABLE_PREFIX . "threads t
            LEFT JOIN " . TABLE_PREFIX . "threadsread tr ON (tr.uid = " . $mybb->user['uid'] . " AND t.tid = tr.tid) 
            LEFT JOIN " . TABLE_PREFIX . "forumsread fr ON (fr.uid = " . $mybb->user['uid'] . " AND t.fid = fr.fid) 
            WHERE t.visible = 1 
            AND t.closed NOT LIKE 'moved|%' 
            AND t.lastpost > IFNULL(tr.dateline," . $mybb->user['lastmark'] . ") AND t.lastpost > IFNULL(fr.dateline," . $mybb->user['lastmark'] . ") AND t.lastpost > " . $mybb->user['lastmark'] . "
            ORDER BY t.dateline DESC";
    $query = $db->query($sql);
    
    // Build a unread topics list 
    while($row = $db->fetch_array($query))
    {
      $tids[] = $row['tid']; 
    }
    
    // Get a unsearchtable forums list
    $unsearchforums = get_unsearchable_forums();
    if($unsearchforums)
    {
      $where_sql .= "t.fid NOT IN ($unsearchforums) AND ";
    }
    
    // Group permission compatibility for >= MyBB 1.6
    if ($version['version_code'] >= 1600)
    {
    	$permsql = '';
    	$onlyusfids = array();
    
    	// Check group permissions if we can't view threads not started by us
    	$group_permissions = forum_permissions();
    	foreach($group_permissions as $fid => $forum_permissions)
    	{
    		if($forum_permissions['canonlyviewownthreads'] == 1)
    		{
    			$onlyusfids[] = $fid;
    		}
    	}
    	if(!empty($onlyusfids))
    	{
    		$where_sql .= " ((t.fid IN(".implode(',', $onlyusfids).") AND t.uid='{$mybb->user['uid']}') OR t.fid NOT IN(".implode(',', $onlyusfids).")) AND "; 
    	}
    }
    
    // Decide and make a where statement
    if (count($tids) > 0)
    {
      $where_sql .= 't.tid IN (' . implode(',', $tids) . ')';
    }
    else
    {
      $where_sql .= '1 < 0';
    }
    
    // Use mybb built-in search engine system
    $sid = md5(uniqid(microtime(), 1));
    $searcharray = array(
        "sid" => $db->escape_string($sid),
        "uid" => $mybb->user['uid'],
        "dateline" => TIME_NOW,
        "ipaddress" => $db->escape_string($session->ipaddress),
        "threads" => '',
        "posts" => '',
        "resulttype" => "threads",
        "querycache" => $db->escape_string($where_sql),
        "keywords" => ''
    );

    $plugins->run_hooks("search_do_search_process");
    $db->insert_query("searchlog", $searcharray);
    redirect("search.php?action=results&sid=".$sid, $lang->redirect_searchresults);
  } 
} 
?>