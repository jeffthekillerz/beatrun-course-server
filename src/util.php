<?php

$log_dir = "data/_logs.log";
$authkeys_dir = "data/_keys.json";
$ratelimit_dir = "data/_ratelimit.json";
$account_record_dir = "data/_record.json";
$lock_dir = "data/_locked.json";

$ratelimit_period = 5;
$ip_list_refresh = 21600; // how fast can a person change their ip on their account. this is 6 hours

$authkey = "";
$map = "";
$code = "";
$ip = $_SERVER['REMOTE_ADDR'];
$headers = getallheaders();

function sanitize($string, $force_lowercase = true, $anal = false) {
    $strip = array("~", "`", "!", "@", "#", "$", "%", "^", "&", "*", "(", ")", "=", "+", "[", "{", "]",
                   "}", "\\", "|", ";", ":", "\"", "'", "&#8216;", "&#8217;", "&#8220;", "&#8221;", "&#8211;", "&#8212;",
                   "â€”", "â€“", ",", "<", ".", ">", "/", "?");
    $clean = trim(str_replace($strip, "", strip_tags($string)));
    $clean = preg_replace('/\s+/', "-", $clean);
    $clean = ($anal) ? preg_replace("/[^a-zA-Z0-9_\-]/", "", $clean) : $clean ;
    return ($force_lowercase) ?
        (function_exists('mb_strtolower')) ?
            mb_strtolower($clean, 'UTF-8') :
            strtolower($clean) :
        $clean;
}

function getcourse_set_params() {
    global $headers, $authkey, $map, $code;
	$authkey = sanitize($_GET["key"], false, true);
	$map = sanitize($_GET["map"], false, true);
	$code = sanitize($_GET["sharecode"], false, true);
}

function upload_set_params() {
    global $headers, $authkey, $map;
	$authkey = sanitize($headers["Authorization"], false, true);
	$map = sanitize($headers["Game-Map"], false, true);
}

function upload_headers_are_valid() {
	global $headers;
    if ($_SERVER['REQUEST_METHOD'] != "POST" ||
        $headers["Content-Type"] != "text/plain" ||
        $headers["user-agent"] != "Valve/Steam HTTP Client 1.0 (4000)") { return false; } else { return true; }
}

function getcourse_headers_are_valid() {
	global $headers;
    if ($_SERVER['REQUEST_METHOD'] != "GET" ||
        $headers["user-agent"] != "Valve/Steam HTTP Client 1.0 (4000)") { return false; } else { return true; }
}

function is_ratelimited() {
	global $ip, $ratelimit_dir, $ratelimit_period;

    $ratelimit_array = json_decode(file_get_contents($ratelimit_dir), true);

    if (time() - $ratelimit_array[$ip] <= $ratelimit_period) { return true; }

    foreach ($ratelimit_array as $uid => $time) {
        if (time() - $ratelimit_array[$uid] > $ratelimit_period) {
            unset($ratelimit_array[$uid]);
        }
    }

    $ratelimit_array[$ip] = time();

    file_put_contents($ratelimit_dir, json_encode($ratelimit_array, JSON_PRETTY_PRINT));

    return false;
}

function is_authkey_valid($key) {
    global $authkeys_dir;
    $key_array = json_decode(file_get_contents($authkeys_dir), true);

    if (count($key_array) <= 0) { return true; }

    if ($key_array[$key]) { return true; }

    return false;
}

function body_is_valid($body) {
    if (count($body) != 6) { return false; }

    if (!is_array($body[0]) || 
        !is_array($body[1]) || 
        !is_string($body[2]) || 
        !is_float($body[3]) || 
        !is_string($body[4]) || 
        !is_array($body[5])) { return false; }

    return true;
}

function generate_code() {
    $code = "";
    for ($i = 0; $i < 3; $i++) {
        $code .= substr(str_shuffle(str_repeat('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', mt_rand(1,10))), 1, 4);
        if ($i == 0 || $i == 1) {$code .= "-";}
    } 
    return strtoupper($code);
}

function generateRandomString($length = 32) {
    return substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length/strlen($x)) )),1,$length);
}

function debug_to_console($data) {
    $output = $data;
    if (is_array($output))
        $output = implode(',', $output);

    echo "<script>console.log('Debug Objects: " . $output . "' );</script>";
}

function account_owns_gmod($userid) {
    require ('steamauth/SteamConfig.php'); // here cuz of scope bullshit

    $url = file_get_contents("http://api.steampowered.com/IPlayerService/GetOwnedGames/v0001/?key=".$steamauth['apikey']."&steamid=".$userid."&format=json");
    $content = json_decode($url, true);

    if (!$content["response"]) { return false; }
    if (!$content["response"]["games"]) { return false; }

    foreach ($content["response"]["games"] as $game) {
        if ($game["appid"] == 4000) { return true; }
    }

    return false;
}

function lock_account($id) {
    global $lock_dir;
    $locks = json_decode(file_get_contents($lock_dir), true);
    $locks[$id] = true;
    file_put_contents($lock_dir, json_encode($locks, JSON_PRETTY_PRINT));
}

function get_userid_from_authkey($authkey) {
    global $authkeys_dir;
    $key_array = json_decode(file_get_contents($authkeys_dir), true);

    if (count($key_array) <= 0) { return ""; }

    if (!isset($key_array[$authkey])) { return ""; }

    return $key_array[$authkey];
}

function get_authkey_from_userid($userid) {
    global $authkeys_dir;

    $key_array = json_decode(file_get_contents($authkeys_dir), true);
    foreach ($key_array as $authkey => $authsteamid) {
        if ($authsteamid === $userid) {
            return $authkey;
        }
    }

    return "";
}

function is_multiaccount($userid) {
    global $account_record_dir, $ip_list_refresh, $ip, $lock_dir, $authkeys_dir;

    $locks = json_decode(file_get_contents($lock_dir), true);
    $authkey = get_authkey_from_userid($userid);

    if (isset($locks[$ip]) || isset($locks[$authkey]) || isset($locks[$userid])) {
        lock_account($ip);
        return true; 
    }

    $record = json_decode(file_get_contents($account_record_dir), true);

    // sanity... that drains my sanity.
    if (!isset($record[$userid])) { $record[$userid] = []; }
    if (!isset($record[$userid]["ips"])) { $record[$userid]["ips"] = []; }
    if (!isset($record[$userid]["lastchanged"])) { $record[$userid]["lastchanged"] = 0; }

    if ($record[$userid]["lastchanged"] < $ip_list_refresh) {
        $record[$userid]["ips"] = [];
    }

    if (!isset($record[$userid]["ips"][$ip])) {
        $record[$userid]["ips"][$ip] = true;
        $record[$userid]["lastchanged"] = time();
    }

    if (count($record[$userid]["ips"]) > 2) {
        lock_account($userid);
        foreach ($record[$userid]["ips"] as $aip => $booolll) {
            lock_account($aip);
        }
        lock_account($authkey);
        return true;
    }

    file_put_contents($account_record_dir, json_encode($record, JSON_PRETTY_PRINT));

    return false;
}

function register_steam_account($userid, $timecreated) {
    global $authkeys_dir;

    if (time() - $timecreated < 7890000) { return "Account too young. Needs to be at least 3 months old."; }
    if (!account_owns_gmod($userid)) { return "Account doesn't have Garry's mod. Make sure your game details are public if you think this is wrong."; }
    if (is_multiaccount($userid)) { return "Your account is locked. Contact site administration."; }

    $keys = json_decode(file_get_contents($authkeys_dir), true);
    foreach ($keys as $akey => $value) {
        if ($value === $userid) {
            return $akey;
        }
    }

    $key = generateRandomString(32);
    while ($keys[$key]) {
        $key = generateRandomString(32);
    }
    $keys[$key] = $userid;

    file_put_contents($authkeys_dir, json_encode($keys, JSON_PRETTY_PRINT));

    return $key;
}

function _log($text) {
    global $log_dir, $authkey, $map, $ip;
    file_put_contents($log_dir, date("D M j G:i:s T Y")." - ".$text." (".$authkey.", ".$map.", ".$ip.", ".get_userid_from_authkey($authkey).")\n", FILE_APPEND);
}

function _error($reason) {
    print($reason);
    http_response_code(400);
    _log($reason);
    exit;
}

?>