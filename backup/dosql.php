<?php
/////////////////////////////////////////////////////////////////////
///                                                                //
// dosql.php - dumps data back into a new MySQL database from      //
// backup generated by backupDB or phpMyAdmin, or simply           //
// batch-runs any number of SQL commands.                          //
//                                                                 //
// Requires:                                                       //
//   PHP 4.1.0 (or higher)                                         //
//   MySQL 3.22 (or higher)                                        //
//                                                                 //
// doSQL() by James Heinrich <info@silisoftware.com>               //
// available at http://www.silisoftware.com                        //
//                                                                 //
// This code is released under the GNU GPL:                        //
// http://www.gnu.org/copyleft/gpl.html                            //
//                                                                 //
// If you do use this code somewhere, send me an email and tell me //
// how/where you used it.                                          //
//                                                                 //
/////////////////////////////////////////////////////////////////////
//                                                                 //
// Notes:                                                          //
//   * Commented-out lines must begin with # or --                 //
//   * SQL statements must end with ;                              //
//                                                                 //
/////////////////////////////////////////////////////////////////////
//                                                                 //
// Configuration:                                                  //
//                                                                 //
//   You MUST modify these values:                                 //

$RowCounterInterval = 500;      // Display a progress indication
                                // every ___ SQL statements
$DHTMLenabled       = true;     // Displays progress with DHTML
                                // set to FALSE for use with Netscape
$SpeedGraphenabled  = true;     // Displays graph of current rate of
                                // query processing. Of no effect if
                                // $DHTMLenabled == FALSE
$SpeedGraphBars     = 20;       // Number of bars in query speed
                                // graph
$SpeedGraphWidth    = 300;      // Width of query speed graph
$SpeedGraphHeight   = 100;      // Height of query speed graph


//////////////////////////////////////////////////////////////////////
///                                                                 //
define('doSQLversion', '1.2.11');

if (!empty($_REQUEST['pixel'])) {
	// output a single-pixel, 2-color GIF
	$PixelRed   = round(0xFF - (( 0xFF - 0x33) * ($_REQUEST['pixel'] / 100)));
	$PixelGreen = round(0xFF - (( 0xFF - 0x99) * ($_REQUEST['pixel'] / 100)));
	$PixelBlue  = round(0xFF - (( 0xFF - 0x66) * ($_REQUEST['pixel'] / 100)));

	header('Content-type: image/gif');
	echo "\x47\x49\x46\x38\x39\x61";                      // version (GIF89a)
	echo "\x01\x00";                                      // width (1px)
	echo "\x01\x00";                                      // height (1px)
	echo "\x80";                                          // flags
	echo "\x00";                                          // background color index
	echo "\x00";                                          // aspect ratio
	echo chr($PixelRed).chr($PixelGreen).chr($PixelBlue); // Color-0
	echo "\xFF\xFF\xFF";                                  // Color-1
	echo "\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x01\x44\x00\x3B";
	exit;
}


echo '<HTML><HEAD><TITLE>doSQL() v'.doSQLversion.' (www.silisoftware.com)</TITLE></HEAD><BODY><FONT FACE="sans-serif" SIZE="-1">';
echo '<DIV ALIGN="CENTER"><B>doSQL() v'.doSQLversion.' by James Heinrich (<A HREF="http://www.silisoftware.com">www.silisoftware.com</A>)</B></DIV><HR><BR>';
define('BUFFER_SIZE', 32768);
if (!@mysql_connect(DB_HOST, DB_USER, DB_PASS)) {
	die('<B>Could not connect to database - check username and password.<BR>MySQL said:<BLOCKQUOTE STYLE="background-color: #FFCC33; padding: 10px;">'.mysql_error().'</BLOCKQUOTE></B>');
}

if (isset($_REQUEST['filename']) && file_exists($_REQUEST['filename'])) {

	if ($DHTMLenabled) {
		echo '<SPAN ID="statusinfo"></SPAN><BR>';

		if ($SpeedGraphenabled) {
			echo '<TABLE BORDER="3"><TR><TD>';
			echo '<TABLE BORDER="0"><TR><TD VALIGN="TOP" ALIGN="RIGHT"><SPAN ID="maxrate"></SPAN></TD>';
			echo '<TD ROWSPAN="2"><TABLE BORDER="0"><TR HEIGHT="'.$SpeedGraphHeight.'">';
			for ($i = 0; $i < $SpeedGraphBars; $i++) {
				echo '<TD VALIGN="BOTTOM"><IMG ID="Graph'.str_pad($i, 2, '0', STR_PAD_LEFT).'" SRC="'.$_SERVER['PHP_SELF'].'?pixel='.round((($SpeedGraphBars - $i) / $SpeedGraphBars) * 100).'" WIDTH="'.round($SpeedGraphWidth / $SpeedGraphBars).'" HEIGHT="0"></TD>';
			}
			echo '</TR></TABLE></TD></TR><TR><TD VALIGN="BOTTOM" ALIGN="RIGHT">0</TD></TR></TABLE>';
			echo '</TD></TR></TABLE><BR>';
		}
	}

	if ($fp = gzopen($_REQUEST['filename'], 'rb')) {
		$totalfilesize  = filesize($_REQUEST['filename']);
		$processedbytes = 0;
		$buffer         = '';
		$rowcounter     = 0;
		$starttime      = getmicrotime();
		$lastinterval   = getmicrotime();
		if ($DHTMLenabled) {
			$statusline  = '<TABLE BORDER=0>';
			$statusline .= '<TR><TD><B>Record #</B></TD><TD><I>(calculating)</I></TD></TR>';
			$statusline .= '<TR><TD><B>Completion</B></TD><TD><I>(calculating)</I>%</TD></TR>';
			$statusline .= '<TR><TD><B>Elapsed Time</B></TD><TD><I>(calculating)</I> minutes</TD></TR>';
			$statusline .= '<TR><TD><B>Remaining Time</B></TD><TD><I>(calculating)</I> minutes</TD></TR>';
			$statusline .= '<TR><TD><B>Rate (now)</B></TD><TD><I>(calculating)</I> queries / second</TD></TR>';
			$statusline .= '<TR><TD><B>Rate (average)</B></TD><TD><I>(calculating)</I> queries / second</TD></TR>';
			$statusline .= '<TR><TD><B>Current Query</B></TD><TD></TD></TR>';
			$statusline .= '</TABLE>';
			echo '<SCRIPT>statusinfo.innerHTML="'.$statusline.'"</SCRIPT>';
			flush();
		}

		while (!gzeof($fp)) {
			set_time_limit(10);
			unset($SQLquery);

			GetMoreBuffer($buffer, $fp);

			$escape      = false;
			$backtick    = false;
			$singlequote = false;
			$doublequote = false;
			$parentheses = false;
			$i = 0;
			while (true) {
				if ($i >= (strlen($buffer) - 1)) {
					GetMoreBuffer($buffer, $fp);
				}
				if ($i >= (strlen($buffer) - 1)) {
					gzclose($fp);
					if ($DHTMLenabled) {

						echo '<SCRIPT>statusinfo.innerHTML = "'.StatusDisplay().'<BR><H3>File processing complete (<I>'.$_REQUEST['filename'].'</I>)</H3>"</SCRIPT>';

					} else {

						echo '<HR><BR><B>Complete!</B></FONT></BODY></HTML>';

					}
					exit;
				}
				switch ($buffer{$i}) {
					case '\\':
						if ($escape === false) {
							$escape = $i;
						}
						break;

					case '"':
						if (!$singlequote && ($escape === false)) {
							$doublequote = BooleanNot($doublequote);
						}
						break;

					case '\'':
						if (!$doublequote && ($escape === false)) {
							$singlequote = BooleanNot($singlequote);
						}
						break;

					case '`':
						if (!$parentheses && !$doublequote && !$singlequote && ($escape === false)) {
							$backtick = BooleanNot($backtick);
						}
						break;

					case '(':
						if (!$parentheses && !$doublequote && !$singlequote && !$backtick && ($escape === false)) {
							$parentheses = true;
						}
						break;

					case ')':
						if ($parentheses && !$doublequote && !$singlequote && !$backtick && ($escape === false)) {
							$parentheses = false;
						}
						break;

					case ';':
						if (!$parentheses && !$doublequote && !$singlequote && !$backtick && ($escape === false)) {
							$SQLquery = substr($buffer, 0, $i);
							$buffer   = substr($buffer, $i + 1);
							break 2;
						}
						break;

					default:
						// skip over
						break;
				}
				if ($escape < $i) {
					$escape = false;
				}
				$i++;
			}
			$processedbytes += $i;

			$SQLquery = trim($SQLquery);
			if ($SQLquery) {
				if ((++$rowcounter % $RowCounterInterval) == 0) {
					if ($DHTMLenabled) {

						echo '<SCRIPT>statusinfo.innerHTML = "'.StatusDisplay().'"</SCRIPT>';

					} else {

						$statusline  = '['.number_format($rowcounter).'] ('.number_format($percentdone * 100, 1).'% complete, ';
						$statusline .= FormattedTimeRemaining($elapsedtime).' elapsed, ';
						$statusline .= FormattedTimeRemaining($totaltime - $elapsedtime).' remaining)';

						echo $statusline.'<BR>';

					}
					flush();
				}
				if (defined('DB_NAME')) {
					$dbname = DB_NAME;
					if (!empty($_REQUEST['OverrideDB'])) {
						$dotpos = strpos($SQLquery, '.');
						$parpos = strpos($SQLquery, '(');
						if (substr($SQLquery, 0, strlen('CREATE TABLE')) == 'CREATE TABLE') {
							if (($dotpos !== false) && ($parpos !== false) && ($parpos > $dotpos)) {
								$extracteddbname = str_replace('`', '', substr($SQLquery, strlen('CREATE TABLE') + 1, $dotpos - strlen('CREATE TABLE') - 1));
								if (substr($SQLquery, 0, strlen('CREATE TABLE `')) == 'CREATE TABLE `') {
									$SQLquery = str_replace('CREATE TABLE `'.$extracteddbname.'.', 'CREATE TABLE `', $SQLquery);
								} else {
									$SQLquery = str_replace('CREATE TABLE '.$extracteddbname.'.', 'CREATE TABLE ', $SQLquery);
								}
							} else {
								die('<B>ERROR: Cannot override database name:</B><PRE>'.$SQLquery.'</PRE>');
							}
						} elseif (substr($SQLquery, 0, strlen('INSERT INTO')) == 'INSERT INTO') {
							$extracteddbname = str_replace('`', '', substr($SQLquery, strlen('INSERT INTO') + 1, $dotpos - strlen('INSERT INTO') - 1));
							if (substr($SQLquery, 0, strlen('INSERT INTO `')) == 'INSERT INTO `') {
								$SQLquery = str_replace('INSERT INTO `'.$extracteddbname.'.', 'INSERT INTO `', $SQLquery);
							} else {
								$SQLquery = str_replace('INSERT INTO '.$extracteddbname.'.', 'INSERT INTO ', $SQLquery);
							}
						}
					}
				} elseif (substr($SQLquery, 0, strlen('CREATE TABLE')) == 'CREATE TABLE') {
					$dotpos = strpos($SQLquery, '.');
					$parpos = strpos($SQLquery, '(');
					if (($dotpos !== false) && ($parpos !== false) && ($parpos > $dotpos)) {
						$dbname = str_replace('`', '', substr($SQLquery, strlen('CREATE TABLE') + 1, $dotpos - strlen('CREATE TABLE') - 1));
					} else {
						die('<B>ERROR: <I>DB_NAME</I> not defined, and cannot extract database name from SQL query:</B><PRE>'.$SQLquery.'</PRE>');
					}
				} elseif (substr($SQLquery, 0, strlen('INSERT INTO')) == 'INSERT INTO') {
					$dotpos = strpos($SQLquery, '.');
					$parpos = strpos($SQLquery, '(');
					if (($dotpos !== false) && ($parpos !== false) && ($parpos > $dotpos)) {
						$dbname = str_replace('`', '', substr($SQLquery, strlen('INSERT INTO') + 1, $dotpos - strlen('INSERT INTO') - 1));
					} else {
						die('<B>ERROR: <I>DB_NAME</I> not defined, and cannot extract database name from SQL query:</B><PRE>'.$SQLquery.'</PRE>');
					}
				} else {
					die('<B>ERROR: <I>DB_NAME</I> not defined, and cannot extract database name from SQL query.</B>');
				}
				if (!@mysql_select_db($dbname)) {
					die('<B>Could not select database "'.$dbname.'".<BR>MySQL said:<BLOCKQUOTE STYLE="background-color: #FFCC33; padding: 10px;">'.mysql_error().'</BLOCKQUOTE></B>');
				}
				@mysql_query($SQLquery);
				if (mysql_error()) {
					die('<HR>'.mysql_error().'<HR><XMP>'.$SQLquery.'</XMP>');
				}
				flush();
			}
		}
		gzclose($fp);
		if ($DHTMLenabled) {
			echo '<SCRIPT>statusinfo.innerHTML = "'.StatusDisplay().'<BR><H3>File processing complete (<I>'.$_REQUEST['filename'].'</I>)</H3>"</SCRIPT>';
		} else {
			echo '<HR><BR><B>Complete!</B></FONT></BODY></HTML>';
		}

	} else {

		echo 'Error: could not open <B>'.$_REQUEST['filename'].'</B></FONT></BODY></HTML>';
		exit;

	}

} else {

	echo 'Syntax: <PRE>dosql.php?filename=[whatever.sql]</PRE>';
	if ($dir = @opendir('.')) {
		while (($file = readdir($dir)) !== false) {
			if (strtolower(fileextension($file)) == 'sql') {
				$possiblefiles[] = $file;
			}
		}
		if (isset($possiblefiles) && is_array($possiblefiles)) {
			echo '<HR>SQL files in this directory:<UL>';
			foreach ($possiblefiles as $file) {
				echo '<LI><A HREF="'.$_SERVER['PHP_SELF'].'?filename='.urlencode($file).'">'.$file.'</A>';
				if (defined('DB_NAME')) {
					echo ' (<A HREF="'.$_SERVER['PHP_SELF'].'?filename='.urlencode($file).'&OverrideDB=1">override to use DB "'.DB_NAME.'"</A>)';
				}
				echo '</LI>';
			}
			echo '</UL></BODY></HTML>';
		}
		closedir($dir);
	}

}


function GetMoreBuffer(&$buffer, &$fp) {
	do {
		$thisline = gzgets($fp, BUFFER_SIZE);
	} while (($thisline == '#') || (substr($thisline, 0, 2) == '--'));
	$buffer .= $thisline;
}

if (!function_exists('array_fill')) {
	function array_fill($start_index, $num, $value) {
		// Fill an array with values (PHP 4 >= 4.2.0)
		// array array_fill ( int start_index, int num, mixed value )
		// array_fill() fills an array with num entries of the value of the value parameter, keys starting at the start_index parameter.
		$newarray = array();
		for ($i = 0; $i < $num; $i++) {
			$newarray[$start_index + $i] = $value;
		}
		return $newarray;
	}
}

function StatusDisplay() {
	global $processedbytes, $totalfilesize, $starttime, $lastinterval, $rowcounter, $RowCounterInterval, $SQLquery, $SpeedGraphBars, $SpeedGraphHeight, $SpeedGraphenabled;

	static $ProcessingRateMax = 0;
	static $ProcessingRateHistory = array();
	if (count($ProcessingRateHistory) == 0) {
		$ProcessingRateHistory = array_fill(0, $SpeedGraphBars, 0);
	}

	$percentdone  = $processedbytes / $totalfilesize;
	$elapsedtime  = getmicrotime() - $starttime;
	$intervaltime = getmicrotime() - $lastinterval;
	$lastinterval = getmicrotime();
	$totaltime    = (($percentdone > 0) ? ($elapsedtime / $percentdone) : 0);
	$currentrate  = $RowCounterInterval / max($intervaltime, 1);
	$ProcessingRateMax = max($ProcessingRateMax, $currentrate);
	$currentquery = explode(' ', $SQLquery);
	if (count($currentquery) >= 3) {
		$currentquery = $currentquery[0].' '.$currentquery[1].' '.$currentquery[2].' ...';
	} else {
		$currentquery = '';
	}

	$statusline  = '<TABLE BORDER=0>';
	$statusline .= '<TR><TD><B>Record #</B></TD><TD>'.number_format($rowcounter).'</TD></TR>';
	$statusline .= '<TR><TD><B>Completion</B></TD><TD>'.number_format($percentdone * 100, 1).'%</TD></TR>';
	$statusline .= '<TR><TD><B>Elapsed Time</B></TD><TD>'.FormattedTimeRemaining($elapsedtime).'</TD></TR>';
	$statusline .= '<TR><TD><B>Remaining Time</B></TD><TD>'.FormattedTimeRemaining($totaltime - $elapsedtime).'</TD></TR>';
	$statusline .= '<TR><TD><B>Rate (now)</B></TD><TD>'.number_format($currentrate, 1).' queries / second</TD></TR>';
	$statusline .= '<TR><TD><B>Rate (average)</B></TD><TD>'.number_format($rowcounter / max($elapsedtime, 1), 1).' queries / second</TD></TR>';
	$statusline .= '<TR><TD><B>Current Query</B></TD><TD STYLE=\'font-family: monospace;\'>'.$currentquery.'</TD></TR>';
	$statusline .= '</TABLE>';

	if ($SpeedGraphenabled) {
		for ($i = ($SpeedGraphBars - 1); $i > 0; $i--) {
			$ProcessingRateHistory[$i] = $ProcessingRateHistory[($i - 1)];
		}
		$ProcessingRateHistory[0]  = $currentrate;
		foreach ($ProcessingRateHistory as $key => $value) {
			echo '<SCRIPT>Graph'.str_pad($key, 2, '0', STR_PAD_LEFT).'.height = "'.round(($value / $ProcessingRateMax) * $SpeedGraphHeight).'"</SCRIPT>';
		}
		echo '<SCRIPT>maxrate.innerHTML =  "'.number_format($ProcessingRateMax).'"</SCRIPT>';
	}

	return $statusline;
}

function BooleanNot($value) {
	if ($value) {
		return false;
	}
	return true;
}

function fileextension($filename) {
	if (strstr($filename, '.')) {
		return substr(basename($filename), strrpos(basename($filename), '.') + 1);
	}
	return '';
}

function getmicrotime() {
	list($usec, $sec) = explode(' ', microtime());
	return ((float) $usec + (float) $sec);
}

function FormattedTimeRemaining($seconds, $precision=1) {
	if ($seconds > 86400) {
		return number_format($seconds / 3600, $precision).' days';
	} elseif ($seconds > 3600) {
		return number_format($seconds / 3600, $precision).' hours';
	} elseif ($seconds > 60) {
		return number_format($seconds / 60, $precision).' minutes';
	}
	return number_format($seconds, $precision).' seconds';
}

?>