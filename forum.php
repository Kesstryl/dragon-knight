<?php // forum.php :: Internal forums script for the game.

include('lib.php');
include('cookies.php');
$check = protectcsfr();
$link = opendb();
$userrow = checkcookies();
if ($userrow == false) { display("The forum is for registered players only.", "Forum"); die(); }
$controlquery = doquery($link, "SELECT * FROM {{table}} WHERE id='1' LIMIT 1", "control");
$controlrow = mysqli_fetch_array($controlquery);

// Close game.
if ($controlrow["gameopen"] == 0) { display("The game is currently closed for maintanence. Please check back later.","Game Closed"); die(); }
// Force verify if the user isn't verified yet.
if ($controlrow["verifyemail"] == 1 && $userrow["verify"] != 1) { header("Location: users.php?do=verify"); die(); }
// Block user if he/she has been banned.
if ($userrow["authlevel"] == 2) { die("Your account has been blocked. Please try back later."); }

if (isset($_GET["do"])) {

	$_GET = array_map('protectarray', $_GET);
	$do = explode(":",$_GET["do"]);
	
	if ($do[0] == "thread") { showthread($do[1], $do[2]); }
	elseif ($do[0] == "new") { newthread(); }
	elseif ($do[0] == "reply") { reply(); }
	elseif ($do[0] == "list") { donothing($do[1]); }
	
} else { donothing(0); }

function donothing($start=0) {
	
	$check = protectcsfr();
	$link = opendb();
    $query = doquery($link, "SELECT * FROM {{table}} WHERE parent='0' ORDER BY newpostdate DESC LIMIT 20", "forum");
    $page = "<table width=\"100%\"><tr><td style=\"padding:1px; background-color:black;\"><table width=\"100%\" style=\"margins:0px;\" cellspacing=\"1\" cellpadding=\"3\"><tr><th colspan=\"3\" style=\"background-color:#dddddd;\"><center><a href=\"forum.php?do=new\">New Thread</a>.......<a href=\"index.php\">Return To Game</a></center></th></tr><tr><th width=\"50%\" style=\"background-color:#dddddd;\">Thread</th><th width=\"10%\" style=\"background-color:#dddddd;\">Replies</th><th style=\"background-color:#dddddd;\">Last Post</th></tr>\n";
    $count = 1;
    if (mysqli_num_rows($query) == 0) { 
        $page .= "<tr><td style=\"background-color:#ffffff;\" colspan=\"3\"><b>No threads in forum.</b></td></tr>\n";
    } else { 
        while ($row = mysqli_fetch_array($query)) {
        	if ($count == 1) {
            	$page .= "<tr><td style=\"background-color:#ffffff;\"><a href=\"forum.php?do=thread:".$row["id"].":0\">".$row["title"]."</a></td><td style=\"background-color:#ffffff;\">".$row["replies"]."</td><td style=\"background-color:#ffffff;\">".$row["newpostdate"]."</td></tr>\n";
            	$count = 2;
            } else {
                $page .= "<tr><td style=\"background-color:#eeeeee;\"><a href=\"forum.php?do=thread:".$row["id"].":0\">".$row["title"]."</a></td><td style=\"background-color:#eeeeee;\">".$row["replies"]."</td><td style=\"background-color:#eeeeee;\">".$row["newpostdate"]."</td></tr>\n";
                $count = 1;
            }
        }
    }
    $page .= "</table></td></tr></table>";
    
    display($page, "Forum");
    
}

function showthread($id, $start) {

	$check = protectcsfr();
	$link = opendb();
    $query = doquery($link, "SELECT * FROM {{table}} WHERE id='$id' OR parent='$id' ORDER BY id LIMIT $start,15", "forum");
    $query2 = doquery($link, "SELECT title FROM {{table}} WHERE id='$id' LIMIT 1", "forum");
    $row2 = mysqli_fetch_array($query2);
    $page = "<table width=\"100%\"><tr><td style=\"padding:1px; background-color:black;\"><table width=\"100%\" style=\"margins:0px;\" cellspacing=\"1\" cellpadding=\"3\"><tr><td colspan=\"2\" style=\"background-color:#dddddd;\"><b><a href=\"forum.php\">Forum</a> :: ".$row2["title"]."</b></td></tr>\n";
    $count = 1;
    while ($row = mysqli_fetch_array($query)) {
        if ($count == 1) {
            $page .= "<tr><td width=\"25%\" style=\"background-color:#ffffff; vertical-align:top;\"><span class=\"small\"><a href=\"index.php?do=onlinechar:".$row["author"]."\"><b>".$row["author"]."</b></a><br /><br />".prettyforumdate($row["postdate"])."</td><td style=\"background-color:#ffffff; vertical-align:top;\">".nl2br($row["content"])."</td></tr>\n";
            $count = 2;
        } else {
            $page .= "<tr><td width=\"25%\" style=\"background-color:#eeeeee; vertical-align:top;\"><span class=\"small\"><a href=\"index.php?do=onlinechar:".$row["author"]."\"><b>".$row["author"]."</b></a><br /><br />".prettyforumdate($row["postdate"])."</td><td style=\"background-color:#eeeeee; vertical-align:top;\">".nl2br($row["content"])."</td></tr>\n";
            $count = 1;
        }
    }
	
	$token = formtoken();
    $page .= "</table></td></tr></table><br />";
    $page .= "<table width=\"100%\"><tr><td><b>Reply To This Thread:</b><br /><form action=\"forum.php?do=reply\" method=\"post\"><input type=\"hidden\" name=\"parent\" value=\"$id\" /><input type=\"hidden\" name=\"title\" value=\"Re: ".$row2["title"]."\" /><textarea name=\"content\" rows=\"7\" cols=\"40\"></textarea><br /><input type=\"submit\" name=\"submit\" value=\"Submit\" /> <input type=\"reset\" name=\"reset\" value=\"Reset\" /><input type=\"hidden\" name=\"token\" value=\"$token\" /></form></td></tr></table>";
    
    display($page, "Forum");
    
}

function reply() {

    global $userrow;
	$check = protectcsfr();
	$link = opendb();
	$content = protect($_POST['content']);
	$parent = protect($_POST['parent']);
	$title = protect($_POST['title']);
	$token = protect($_POST['token']);
		
	if ($_SESSION['token'] != $token) { die("Invalid request");}
	$query = doquery($link, "INSERT INTO {{table}} SET postdate=NOW(),newpostdate=NOW(),author='".$userrow["charname"]."',parent='$parent',replies='0',title='$title',content='$content'", "forum");
	$query2 = doquery($link, "UPDATE {{table}} SET newpostdate=NOW(),replies=replies+1 WHERE id='$parent' LIMIT 1", "forum");
	unset($_SESSION['token']);
	header("Location: forum.php?do=thread:$parent:0");
	die();
	
}

function newthread() {

    global $userrow;
	$check = protectcsfr();
    $link = opendb();
    if (isset($_POST["submit"])) {
		$title = protect($_POST['title']);
		$content = protect($_POST['content']);
		$token = protect($_POST['token']);
		
		if ($_SESSION['token'] != $token) { die("Invalid request");}
        $query = doquery($link, "INSERT INTO {{table}} SET postdate=NOW(),newpostdate=NOW(),author='".$userrow["charname"]."',parent='0',replies='0',title='$title',content='$content'", "forum");
        unset($_SESSION['token']);
		header("Location: forum.php");
        die();
    }
	
    $token = formtoken();
    $page = "<table width=\"100%\"><tr><td><b>Make A New Post:</b><br /><br/ ><form action=\"forum.php?do=new\" method=\"post\">Title:<br /><input type=\"text\" name=\"title\" size=\"50\" maxlength=\"50\" /><br /><br />Message:<br /><textarea name=\"content\" rows=\"7\" cols=\"40\"></textarea><br /><br /><input type=\"submit\" name=\"submit\" value=\"Submit\" /> <input type=\"reset\" name=\"reset\" value=\"Reset\" /><input type=\"hidden\" name=\"token\" value=\"{$token}\" /></form></td></tr></table>";
    display($page, "Forum");
    
}
	
?>