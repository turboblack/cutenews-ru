<?php
include_once 'head.php';

$echo = cute_lang('addons/showusers');

$i = 0;
if (!$bgcolor){$bgcolor = '#f7f7f7';}
$user_status = array('', 'Admin', 'Editor', 'Journalist', 'Commenter');
$fp = c_array('users');

if ($user){
	foreach($fp as $fo){
	    $fo_arr = explode('|', $fo);

	    if (chicken_dick($user) == totranslit($fo_arr[2]) or chicken_dick($user) == $fo_arr[16]){
?>

<table width="300" border="0" cellspacing="0" cellpadding="4" align="center">

<tr style="text-align: center;">
<td>

<img src="<?php echo ($fo_arr[8] ? $config_path_userpic_upload.'/'.$fo_arr[2].'.'.$fo_arr[8] : $config_http_script_dir.'/skins/images/default/user.gif'); ?>" align="left">

<table width="300" border="0" cellspacing="0" cellpadding="4" style="border: solid 1px <?php echo $bgcolor;?>;">
<tr style="background: <?php echo $bgcolor;?>;">
<th colspan="2"><?php echo $echo['userProfile']; ?>
<tr style="background: <?php echo $bgcolor;?>;">
<th colspan="2" style="border-bottom: solid 1px <?php echo $bgcolor;?>;"><?php echo ($fo_arr[4] ? $fo_arr[4] : $fo_arr[2]); ?>

<tr>
<td align="right"><nobr><?php echo $echo['mail']; ?>:</nobr>
<td width="99%"><?php echo ($fo_arr[7] != '1' ? $fo_arr[5] : ''); ?>

<tr>
<td align="right"><?php echo $echo['url']; ?>:
<td><?php echo ($fo_arr[10] ? '<a href="'.$fo_arr[10].'">'.$fo_arr[10].'</a>' : ''); ?>

<tr>
<td align="right"><?php echo $echo['icq']; ?>:
<td><?php echo ($fo_arr[11] ? '<img src="'.$config_http_script_dir.'/skins/images/icq.gif" alt="'.$fo_arr[11].'" align="absMiddle">'.$fo_arr[11] : ''); ?>

<tr>
<td align="right"><?php echo $echo['lj']; ?>:
<td><?php echo ($fo_arr[14] ? '<a href="http://www.livejournal.com/userinfo.bml?user='.$fo_arr[14].'"><img src="'.$config_http_script_dir.'/skins/images/user.gif" alt="[info]" align="absMiddle" border="0"></a><a href="http://www.livejournal.com/users/'.$fo_arr[14].'">'.$fo_arr[14].'</a>' : ''); ?>

<tr>
<td align="right"><?php echo $echo['location']; ?>:
<td><?php echo $fo_arr[12]; ?>

<tr>
<td align="right"><?php echo $echo['about']; ?>:
<td><?php echo $fo_arr[13]; ?>

<tr style="background: <?php echo $bgcolor;?>;">
<th colspan="2"><?php echo $echo['getRSS']; ?>:

<tr>
<td colspan="2" style="line-height: 19px;">
<?php
$static['author']   = $fo_arr[2];
$static['number']   = '5';
$static['template'] = "Headlines";
include $cutepath.'/show_news.php';
?>
<tr style="background: <?php echo $bgcolor;?>;">
<th colspan="2"><a href="<?php echo cute_get_link(array('author' => $fo_arr[2], 'id' => $fo_arr[16]), 'user', 'rss'); ?>">RSS</a>
</table>

</table>

<?php
	    }
	}
?>

<?php
}
else {
?>
<img src="<?php echo $config_http_script_dir; ?>/skins/images/<?php echo ($config_skin != 'simple' ? $config_skin : 'default'); ?>/users.gif" align="left">
<table width="500" border="0" cellspacing="0" cellpadding="4" style="border: solid 1px <?php echo $bgcolor; ?>;">
<tr>
<th style="border-bottom: solid 1px <?php echo $bgcolor; ?>;"><?php echo $echo['name']; ?>
<th style="border-bottom: solid 1px <?php echo $bgcolor; ?>;"><nobr><?php echo $echo['registration']; ?></nobr>
<th style="border-bottom: solid 1px <?php echo $bgcolor; ?>;"><nobr><?php echo $echo['posts']; ?></nobr>
<th style="border-bottom: solid 1px <?php echo $bgcolor; ?>;"><?php echo $echo['level']; ?>

<?php

	function sort_users($a, $b) {
	global $fp, $sortus;

		$users_a = explode('|', $fp[$a]);
		$users_b = explode('|', $fp[$b]);

	return strnatcasecmp($users_a[$sortus], $users_b[$sortus]);
	}

    // workaround for php bug https://bugs.php.net/bug.php?id=50688
	@uksort($fp, 'sort_users');

    if ($_POST['sortus'] == '0'){
		if ($_POST['sortad'] == 'a'){sort($fp);}
		if ($_POST['sortad'] == 'd'){rsort($fp);}
    }
    else {
		if ($_POST['sortad'] == 'd'){sort($fp);}
		if ($_POST['sortad'] == 'a'){rsort($fp);}
	}

	foreach($fp as $fo){
	    $fo_arr = explode('|', $fo);

	    $i++;
	    if ($i%2 == 0){$bg = 'background: <?php echo $bgcolor; ?>;';}
?>

<tr style="text-align: center;<?php echo $bg; ?>">
<td><a href="<?php echo cute_get_link(array('author' => $fo_arr[2], 'id' => $fo_arr[16]), 'user'); ?>"><?php echo ($fo_arr[4] ? $fo_arr[4] : $fo_arr[2]); ?></a>
<td><?php echo langdate('d M Y', $fo_arr[0]); ?>
<td><?php echo $fo_arr[6]; ?>
<td><?php echo $user_status[$fo_arr[1]]; ?>

<?php
	}
?>

<tr>
<td colspan="4" style="text-align: center;">
<br>
<form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">

<select name="sortus">
<option value="2"<?php echo ($_POST['sortus'] == '2' ? ' selected' : ''); ?>><?php echo $echo['name']; ?></option>
<option value="0"<?php echo ($_POST['sortus'] == '0' ? ' selected' : ''); ?>><?php echo $echo['registration']; ?></option>
<option value="6"<?php echo ($_POST['sortus'] == '6' ? ' selected' : ''); ?>><?php echo $echo['posts']; ?></option>
<option value="1"<?php echo ($_POST['sortus'] == '1' ? ' selected' : ''); ?>><?php echo $echo['level']; ?></option>
</select>

<select name="sortad">
<option value="d"<?php echo ($_POST['sortad'] == 'd' ? ' selected' : ''); ?>><?php echo $echo['desc']; ?></option>
<option value="a"<?php echo ($_POST['sortad'] == 'a' ? ' selected' : ''); ?>><?php echo $echo['asc']; ?></option>
</select>

<input type="submit" value="<?php echo $echo['sort']; ?>">
</form>

</table>

<?php
}
?>