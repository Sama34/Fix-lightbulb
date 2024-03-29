<?php
/**
 * MyBB 1.6
 * Copyright � 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 * $Id: functions_forumlist.php 4941 2010-05-15 18:17:38Z RyanGordon $
 */

/**
* Build a list of forum bits.
*
* @param int The parent forum to fetch the child forums for (0 assumes all)
* @param int The depth to return forums with.
* @return array Array of information regarding the child forums of this parent forum
*/
function build_forumbits($pid=0, $depth=1)
{
	global $fcache, $moderatorcache, $forumpermissions, $theme, $mybb, $templates, $bgcolor, $collapsed, $lang, $showdepth, $plugins, $parser, $forum_viewers;
	
	$forum_listing = '';

	// If no forums exist with this parent, do nothing
	if(!is_array($fcache[$pid]))
	{
		return;
	}

	// Foreach of the forums in this parent
	foreach($fcache[$pid] as $parent)
	{
    foreach($parent as $forum)
		{
			$forums = $subforums = $sub_forums = '';
			$lastpost_data = '';
			$counters = '';
			$forum_viewers_text = '';
			$forum_viewers_text_plain = '';
			
			
 			
      $unread = false;
		  $subforums_exist = false;
		  


			// Get the permissions for this forum
			$permissions = $forumpermissions[$forum['fid']];

			// If this user doesnt have permission to view this forum and we're hiding private forums, skip this forum
			if($permissions['canview'] != 1 && $mybb->settings['hideprivateforums'] == 1)
			{
				continue;
			}
			
			$plugins->run_hooks_by_ref("build_forumbits_forum", $forum);

			// Build the link to this forum
			$forum_url = get_forum_link($forum['fid']);

			// This forum has a password, and the user isn't authenticated with it - hide post information
			$hideinfo = false;
			$hidelastpostinfo = false;
			$showlockicon = 0;
			if($permissions['canviewthreads'] != 1)
			{
			    $hideinfo = true;
			}
			
			if($permissions['canonlyviewownthreads'] == 1)
			{
				$hidelastpostinfo = true;
			}

			if($forum['password'] != '' && $mybb->cookies['forumpass'][$forum['fid']] != md5($mybb->user['uid'].$forum['password']))
			{
			    $hideinfo = true;
			    $showlockicon = 1;
			}
			
			$lastpost_data = array(
				"lastpost" => $forum['lastpost'],
				"lastpostsubject" => $forum['lastpostsubject'],
				"lastposter" => $forum['lastposter'],
				"lastposttid" => $forum['lastposttid'],
				"lastposteruid" => $forum['lastposteruid']
			);
			
			// Fetch subforums of this forum
			if(isset($fcache[$forum['fid']]))
			{
			
			
        
			  $subforums_exist = true;
        
        
        
				$forum_info = build_forumbits($forum['fid'], $depth+1);

				// Increment forum counters with counters from child forums
				$forum['threads'] += $forum_info['counters']['threads'];
				$forum['posts'] += $forum_info['counters']['posts'];
				$forum['unapprovedthreads'] += $forum_info['counters']['unapprovedthreads'];
				$forum['unapprovedposts'] += $forum_info['counters']['unapprovedposts'];
				$forum['viewers'] += $forum_info['counters']['viewing'];

				// If the child forums' lastpost is greater than the one for this forum, set it as the child forums greatest.
				if($forum_info['lastpost']['lastpost'] > $lastpost_data['lastpost'])
				{
					$lastpost_data = $forum_info['lastpost'];
					/*
					// If our subforum is unread, then so must be our parents. Force our parents to unread as well
					if(strstr($forum_info['lightbulb']['folder'], "on") !== false)
					{
						$forum['lastread'] = 0;
					}
					// Otherwise, if we  have an explicit record in the db, we must make sure that it is explicitly set
					else
					{
						$lastpost_data['lastpost'] = $forum['lastpost'];
					}*/
				}


				
  			if ($forum_info['lightbulb']['unread'] || $forum_info['unread'])
  			{
          $unread = true; 
        }
        
        
				$sub_forums = $forum_info['forum_list'];
			}

			// If we are hiding information (lastpost) because we aren't authenticated against the password for this forum, remove them
			if($hideinfo == true || $hidelastpostinfo == true)
			{
				unset($lastpost_data);
			}

			// If the current forums lastpost is greater than other child forums of the current parent, overwrite it
			if($lastpost_data['lastpost'] > $parent_lastpost['lastpost'])
			{
				$parent_lastpost = $lastpost_data;
			}

			if(is_array($forum_viewers) && $forum_viewers[$forum['fid']] > 0)
			{
				$forum['viewers'] = $forum_viewers[$forum['fid']];
			}

			// Increment the counters for the parent forum (returned later)
			if($hideinfo != true)
			{
				$parent_counters['threads'] += $forum['threads'];
				$parent_counters['posts'] += $forum['posts'];
				$parent_counters['unapprovedposts'] += $forum['unapprovedposts'];
				$parent_counters['unapprovedthreads'] += $forum['unapprovedthreads'];
				$parent_counters['viewers'] += $forum['viewers'];
			}


			// Get the lightbulb status indicator for this forum based on the lastpost
			$lightbulb = get_forum_lightbulb($forum, $lastpost_data, $showlockicon, $unread, $subforums_exist);

      if ($lightbulb['unread'] == true)
      {
        $parent_unread = true;
        $unread = true; 
        $forum['unread'] = true;
        $forum_info['unread'] = true;
      }

			// Done with our math, lets talk about displaying - only display forums which are under a certain depth
			if($depth > $showdepth)
			{
				continue;
			}
			

			// Fetch the number of unapproved threads and posts for this forum
			$unapproved = get_forum_unapproved($forum);
			
			if($hideinfo == true)
			{
				unset($unapproved);
			}

			// Sanitize name and description of forum.
			$forum['name'] = preg_replace("#&(?!\#[0-9]+;)#si", "&amp;", $forum['name']); // Fix & but allow unicode
			$forum['description'] = preg_replace("#&(?!\#[0-9]+;)#si", "&amp;", $forum['description']); // Fix & but allow unicode
			$forum['name'] = preg_replace("#&([^\#])(?![a-z1-4]{1,10};)#i", "&#038;$1", $forum['name']);
			$forum['description'] = preg_replace("#&([^\#])(?![a-z1-4]{1,10};)#i", "&#038;$1", $forum['description']);

			// If this is a forum and we've got subforums of it, load the subforums list template
			if($depth == 2 && $sub_forums)
			{
				eval("\$subforums = \"".$templates->get("forumbit_subforums")."\";");
			}
			// A depth of three indicates a comma separated list of forums within a forum
			else if($depth == 3)
			{
				if($donecount < $mybb->settings['subforumsindex'])
				{
					$statusicon = '';

					// Showing mini status icons for this forum
					if($mybb->settings['subforumsstatusicons'] == 1)
					{
						$lightbulb['folder'] = "mini".$lightbulb['folder'];
						eval("\$statusicon = \"".$templates->get("forumbit_depth3_statusicon", 1, 0)."\";");
					}

					// Fetch the template and append it to the list
					eval("\$forum_list .= \"".$templates->get("forumbit_depth3", 1, 0)."\";");
					$comma = $lang->comma;
				}

				// Have we reached our max visible subforums? put a nice message and break out of the loop
				++$donecount;
				if($donecount == $mybb->settings['subforumsindex'])
				{
					if(subforums_count($fcache[$pid]) > $donecount)
					{
						$forum_list .= $comma.$lang->sprintf($lang->more_subforums, (subforums_count($fcache[$pid]) - $donecount));
					}
				}
				continue;
			}


			// Forum is a category, set template type
			if($forum['type'] == 'c')
			{
				$forumcat = '_cat';
			}
			// Forum is a standard forum, set template type
			else
			{
				$forumcat = '_forum';
			}

			if($forum['linkto'] == '')
			{
				// No posts have been made in this forum - show never text
				if(($lastpost_data['lastpost'] == 0 || $lastpost_data['lastposter'] == '') && $hideinfo != true)
				{
					$lastpost = "<div style=\"text-align: center;\">{$lang->lastpost_never}</div>";
				}
				elseif($hideinfo != true)
				{
					// Format lastpost date and time
					$lastpost_date = my_date($mybb->settings['dateformat'], $lastpost_data['lastpost']);
					$lastpost_time = my_date($mybb->settings['timeformat'], $lastpost_data['lastpost']);

					// Set up the last poster, last post thread id, last post subject and format appropriately
					$lastpost_profilelink = build_profile_link($lastpost_data['lastposter'], $lastpost_data['lastposteruid']);
					$lastpost_link = get_thread_link($lastpost_data['lastposttid'], 0, "lastpost");
					$lastpost_subject = $full_lastpost_subject = $parser->parse_badwords($lastpost_data['lastpostsubject']);
					if(my_strlen($lastpost_subject) > 25)
					{
						$lastpost_subject = my_substr($lastpost_subject, 0, 25)."...";
					}
					$lastpost_subject = htmlspecialchars_uni($lastpost_subject);
					$full_lastpost_subject = htmlspecialchars_uni($full_lastpost_subject);
					
					// Call lastpost template
					if($depth != 1)
					{						
						eval("\$lastpost = \"".$templates->get("forumbit_depth{$depth}_forum_lastpost")."\";");
					}
				}

				if($mybb->settings['showforumviewing'] != 0 && $forum['viewers'] > 0)
				{
					if($forum['viewers'] == 1)
					{
						$forum_viewers_text = $lang->viewing_one;
					}
					else
					{
						$forum_viewers_text = $lang->sprintf($lang->viewing_multiple, $forum['viewers']);
					}
					$forum_viewers_text_plain = $forum_viewers_text;
					$forum_viewers_text = "<span class=\"smalltext\">{$forum_viewers_text}</span>";
				}
			}
			// If this forum is a link or is password protected and the user isn't authenticated, set lastpost and counters to "-"
			if($forum['linkto'] != '' || $hideinfo == true)
			{
				$lastpost = "<div style=\"text-align: center;\">-</div>";
				$posts = "-";
				$threads = "-";
			}
			// Otherwise, format thread and post counts
			else
			{
				// If we're only hiding the last post information
				if($hidelastpostinfo == true)
				{
					$lastpost = "<div style=\"text-align: center;\">-</div>";
				}
				
				$posts = my_number_format($forum['posts']);
				$threads = my_number_format($forum['threads']);
			}

			// Moderator column is not off
			if($mybb->settings['modlist'] != 0)
			{
				$done_moderators = array(
					"users" => array(),
					"groups" => array()
				);
				$moderators = '';
				// Fetch list of moderators from this forum and its parents
				$parentlistexploded = explode(',', $forum['parentlist']);
				foreach($parentlistexploded as $mfid)
				{
					// This forum has moderators
					if(is_array($moderatorcache[$mfid]))
					{
						// Fetch each moderator from the cache and format it, appending it to the list
						foreach($moderatorcache[$mfid] as $modtype)
						{
							foreach($modtype as $moderator)
							{
								if($moderator['isgroup'])
								{
									if(in_array($moderator['id'], $done_moderators['groups']))
									{
										continue;
									}
									$moderators .= $comma.htmlspecialchars_uni($moderator['title']);
									$done_moderators['groups'][] = $moderator['id'];
								}
								else
								{
									if(in_array($moderator['id'], $done_moderators['users']))
									{
										continue;
									}
									$moderators .= "{$comma}<a href=\"".get_profile_link($moderator['id'])."\">".htmlspecialchars_uni($moderator['username'])."</a>";
									$done_moderators['users'][] = $moderator['id'];
								}
								$comma = $lang->comma;
							}
						}
					}
				}
				$comma = '';

				// If we have a moderators list, load the template
				if($moderators)
				{
					eval("\$modlist = \"".$templates->get("forumbit_moderators")."\";");
				}
				else
				{
					$modlist = '';
				}
			}

			// Descriptions aren't being shown - blank them
			if($mybb->settings['showdescriptions'] == 0)
			{
				$forum['description'] = '';
			}

			// Check if this category is either expanded or collapsed and hide it as necessary.
			$expdisplay = '';
			$collapsed_name = "cat_{$forum['fid']}_c";
			if(isset($collapsed[$collapsed_name]) && $collapsed[$collapsed_name] == "display: show;")
			{
				$expcolimage = "collapse_collapsed.gif";
				$expdisplay = "display: none;";
				$expaltext = "[+]";
			}
			else
			{
				$expcolimage = "collapse.gif";
				$expaltext = "[-]";
			}

			// Swap over the alternate backgrounds
			$bgcolor = alt_trow();

			// Add the forum to the list
			eval("\$forum_list .= \"".$templates->get("forumbit_depth$depth$forumcat")."\";");
		}
	}

	// Return an array of information to the parent forum including child forums list, counters and lastpost information
  
  return array(
		"forum_list" => $forum_list,
		"counters" => $parent_counters,
		"lastpost" => $parent_lastpost,
		"lightbulb" => $lightbulb,
		"unread" => $parent_unread,
	);
}

/**
 * Fetch the status indicator for a forum based on its last post and the read date
 *
 * @param array Array of information about the forum
 * @param array Array of information about the lastpost date
 * @return array Array of the folder image to be shown and the alt text
 */
function get_forum_lightbulb($forum, $lastpost, $locked=0, $unread = false, $subforums_exist = false)
{
	global $mybb, $lang, $db, $unread_forums;

	// This forum is closed, so override the folder icon with the "offlock" icon.
	if($forum['open'] == 0 || $locked)
	{
		$folder = "offlock";
		$altonoff = $lang->forum_locked;
	}
	else
	{
    if ($subforums_exist == true)
    {    
    	if($unread == true)
    	{
  			$unread_forums++;
  			$folder = "on";
  			$altonoff = $lang->new_posts;
    	}
    	else
    	{
  			$folder = "off";
  			$altonoff = $lang->no_new_posts;
  			
  			if ($forum['type'] == 'f')
  			{
          // Fetch the last read date for this forum
          if($forum['lastread'])
          {
          	$forum_read = $forum['lastread'];
          }
          else // Is there not a read record for this forum? It must be unread
          {
          	$forum_read = 0;
          	//$forum_read = my_get_array_cookie("forumread", $forum['fid']);
          }
  			
          if($forum['lastposteruid'] != $mybb->user['uid'] && $mybb->user['uid'] != 0 && $forum['lastpost'] > $forum_read && $forum['lastpost'] > $mybb->user['lastmark'] && $forum['lastpost'] != 0) 
          {
          	$unread_forums++;
          	$folder = "on";
          	$altonoff = $lang->new_posts;
          	$unread = true;
          }
        }
      }
    }
    else
    {
  		// Fetch the last read date for this forum
  		if($forum['lastread'])
  		{
  			$forum_read = $forum['lastread'];
  		}
  		else // Is there not a read record for this forum? It must be unread
  		{
  			$forum_read = 0;
  			//$forum_read = my_get_array_cookie("forumread", $forum['fid']);
  		}
  
  		//if(!$forum_read)
  		//{
  			//$forum_read = $mybb->user['lastvisit'];
  		//}
  		
   	    // If the lastpost is greater than the last visit and is greater than the forum read date, we have a new post 
  		if($lastpost['lastposteruid'] != $mybb->user['uid'] && $mybb->user['uid'] != 0 && $lastpost['lastpost'] > $forum_read && $lastpost['lastpost'] > $mybb->user['lastmark'] && $lastpost['lastpost'] != 0) 
  		{
  			$unread_forums++;
  			$folder = "on";
  			$altonoff = $lang->new_posts;
  			$unread = true;
  		}
  		// Otherwise, no new posts
  		else
  		{
  			$folder = "off";
  			$altonoff = $lang->no_new_posts;
  		}
  	}
	}

	return array(
		"folder" => $folder,
		"altonoff" => $altonoff,
		"unread" => $unread,
	);
}

/**
 * Fetch the number of unapproved posts, formatted, from a forum
 *
 * @param array Array of information about the forum
 * @return array Array containing formatted string for posts and string for threads
 */
function get_forum_unapproved($forum)
{
	global $lang;

	$unapproved_threads = $unapproved_posts = '';

	// If the user is a moderator we need to fetch the count
	if(is_moderator($forum['fid']))
	{
		// Forum has one or more unaproved posts, format language string accordingly
		if($forum['unapprovedposts'])
		{
			if($forum['unapprovedposts'] > 1)
			{
				$unapproved_posts_count = $lang->sprintf($lang->forum_unapproved_posts_count, $forum['unapprovedposts']);
			}
			else
			{
				$unapproved_posts_count = $lang->sprintf($lang->forum_unapproved_post_count, 1);
			}
			$unapproved_posts = " <span title=\"{$unapproved_posts_count}\">(".my_number_format($forum['unapprovedposts']).")</span>";
		}
		// Forum has one or more unapproved threads, format language string accordingly
		if($forum['unapprovedthreads'])
		{
			if($forum['unapprovedthreads'] > 1)
			{
				$unapproved_threads_count = $lang->sprintf($lang->forum_unapproved_threads_count, $forum['unapprovedthreads']);
			}
			else
			{
				$unapproved_threads_count = $lang->sprintf($lang->forum_unapproved_thread_count, 1);
			}
			$unapproved_threads = " <span title=\"{$unapproved_threads_count}\">(".my_number_format($forum['unapprovedthreads']).")</span>";
		}
	}
	return array(
		"unapproved_posts" => $unapproved_posts,
		"unapproved_threads" => $unapproved_threads
	);
}
?>
