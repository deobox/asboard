<?php
ini_set('error_reporting', E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);

$ast_server_ip = "127.0.0.1";
$ast_ami_port  = "5038";
$ast_mgr_user  = "user";
$ast_mgr_pass  = "pass"; // access key
$ast_queue = array(8888); // comma separated queue numbers

$socket = fsockopen($ast_server_ip, $ast_ami_port, $errno, $errstr, $timeout);
fputs($socket, "Action: Login\r\nUserName: $ast_mgr_user\r\nSecret: $ast_mgr_pass\r\n\r\n");
fputs($socket, "Action: QueueStatus\r\n\r\nAction: Logoff\r\n\r\n");
while (!feof($socket)) { $queueinfo .= fread($socket, 8192); }
fclose($socket);

$arr = explode("\r\n\r\n", $queueinfo);
?>
<!DOCTYPE html>
<html>
<head>
<meta http-equiv='refresh' content='7'>
<style>
body { background-color: #000000; }
.box { background: #000000 none repeat scroll 0 0; border-color: #CCCCCC; border-radius: 25px; border-width: 1 1px; border-style: solid; text-align:center; color: #ffffff; font-family: Calibri,Tahoma,Arial; font-size: 100px; font-weight: bold; line-height: 1.3em; margin: 0; padding: 20px 20px 20px 20px; width: 500px; }
.agents { background: #000000 none repeat scroll 0 0; border-color: #CCCCCC; border-radius: 25px; border-width: 1 1px; border-style: solid; text-align:center; color: #ffffff; font-family: Calibri,Tahoma,Arial; font-size: 20px; line-height: 2em; margin: 0; padding: 8px 4px 8px 4px; width: 900px; }
</style>
</head>
<?php

foreach ($arr as $queue) {
        preg_match('/Queue: (\d+)/', $queue, $q_name);
        if (in_array($q_name[1], $ast_queue)){
                $event = get_my_stuff($queue, 'Event: ', "\r\n");
                switch ($event){
                        case 'QueueParams':
                                preg_match('/Calls: (\d+)/', $queue, $q_waitingcalls);
                                preg_match('/Abandoned: (\d+)/', $queue, $q_abandoned);
                                preg_match('/Completed: (\d+)/', $queue, $q_taken);
                                preg_match('/Holdtime: (\d+)/', $queue, $q_holdtime);
                                preg_match('/TalkTime: (\d+)/', $queue, $q_talktime);
                                preg_match('/ServiceLevel: (\d+)/', $queue, $q_servicelevel);
                                preg_match('/ServicelevelPerf: (\d+)/', $queue, $q_servicelevelperf);
                                $queue_arr[$q_name[1]]['stats'] = "<font class='box'>Waiting<br>{$q_waitingcalls[1]}</font><br><font class='box'>Lost<br>{$q_abandoned[1]}</font><font class='box'>Taken<br>{$q_taken[1]}</font>";
                                break;
                        case 'QueueEntry':
                                preg_match('/Position: (\d+)/', $queue, $q_position );
                                preg_match('/CallerIDName: (.*)/', $queue, $q_cidname);
                                preg_match('/CallerID: (.*)/', $queue, $q_cid);
                                preg_match('/Wait: (\d+)/', $queue, $q_waittime);
                                $queue_arr[$q_name[1]]['entry'][$q_position[1]] = array('Wait' => $q_waittime[1], 'CallerID' => "{$q_cidname[1]} ({$q_cid[1]})");
                                break;
                        case 'QueueMember':
                                preg_match('/Name: (.*)/', $queue, $q_member );
                                preg_match('/CallsTaken: (\d+)/', $queue, $q_callstaken );
                                preg_match('/Status: (\d+)/', $queue, $q_status );
                                preg_match('/InCall: (\d+)/', $queue, $q_incall );
                                preg_match('/Paused: (\d+)/', $queue, $q_pause );
                                preg_match('/Location: (.*\@)/', $queue, $q_location );
                                $queue_arr[$q_name[1]]['member'][] = array('Name' => $q_member[1], 'CallsTaken' => $q_callstaken[1], 'Status' => $q_status[1], 'InCall' => $q_incall[1], 'Pause' => $q_pause[1], 'Location' => $q_location[0]);
                                break;
                }
        }
}

ksort($queue_arr);

foreach ($queue_arr as $queue => $arr) {
echo "<table><tr><td class='agents' style='text-align:left;'>\n";
        if (!empty($arr['member'])){
                foreach ($arr['member'] as $member){
                if ( $member['InCall'] == 1 ) { $InCall= "&#9990;"; } else { $InCall = "&#10026;"; }
                if ( $member['Pause'] == 1 ) { $InCall= "&#8471;"; }
echo "<font style='font-size: 36px; font-weight: bold; padding-left:20px;'>".$InCall.' '. $member['Name'] . ' '.substr($member['Location'],16,3).' <font style="font-size:32px">&#9742;</font> ' . $member['CallsTaken'] . "</font><br>\n";
                }
echo "</td>
<td class='box'>Waiting<br>".$q_waitingcalls[1]."</td>
<td class='box'>Lost<br>".$q_abandoned[1]."</td>
</tr><tr>
<td class='agents'><font style='font-size: 60px; font-weight: bold; line-height: 90%;'>
Hold Time<br>".get_display_time($q_holdtime[1])."
<br>Talk Time<br>".get_display_time($q_talktime[1])."</font></td>
<td class='box'>Taken<br>".$q_taken[1]."</td>
<td class='box'>Levels<br>".$q_servicelevelperf[1].":".$q_servicelevel[1]."</td>
</tr></table>\n";
        }
}

function get_my_stuff($str, $start_str, $slut_str = ""){
        if ($start_str == false){
                $start = 0;
        }else{
                $start = strpos($str, $start_str);
                if ($start === false)
                        return '';
                $start += strlen($start_str);
        }
        if ($slut_str == ""){
                $slut = strlen($str);
        }else{
                $slut = strpos($str, $slut_str, $start);
        }
        $res = trim(substr($str, $start, $slut - $start));
        return $res;
}

function get_display_time($dtime) {
        if($dtime < 60) { $outdtime=$dtime." secs"; } else {
                if(strlen(${dtime} % 60) == 1) { $outdtime=floor(${dtime} / 60).":0".(${dtime} % 60)." mins"; }
                else { $outdtime=floor(${dtime} / 60).":".(${dtime} % 60)." mins"; }
        }
        return $outdtime;
}
?>

