MyBB-Fix-lightbulb
=============

Fix lightbulb problem for MyBB 1.2, 1.4 and 1.6

Credits
=============
lukasamd ( http://community.mybb.com/thread-78721.html )

Instructions
=============
First, you must install the View Unread Posts ( http://community.mybb.com/thread-71986.html ) - it adds "lastmark" to users table in DB - value is used as an additional confirmation when checking if there are unread posts

Second, you must replace original file inc/functions_forumlist.php to fixed version. Before this, make a backup!