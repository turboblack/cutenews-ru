<?php
include_once 'head.php';

add_filter('news-allow-commentform', 'comment_form');

function comment_form(){return false;}

header('Content-type: text/xml');
echo '<?xml version="1.0" encoding="'.$echo['charset'].'" ?>';
?>

<rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/">
<channel>

<?php
if (!$id){
?>

<title><?php echo $config_home_title; ?></title>
<link><?php echo $config_http_home_url; ?></link>
<description><?php echo $config_home_title; ?></description>

<?php
}
?>

<language>ru</language>
<generator><?php echo $config_version_name.' '.$config_version_id; ?></generator>

<?php
$template = 'rss';
$number = 12;
include $cutepath.'/show_news.php';
?>

</channel>
</rss>