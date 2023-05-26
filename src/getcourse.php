<?php

$ratelimit_period = 5;
$upload_ratelimit_dir = "_ratelimit.json";
$upload_keys = "_internal.json";
$log_dir = "_logs.log";

$headers = getallheaders();
$authkey = sanitize($_GET["key"], true, true);
$map = sanitize($_GET["map"], true, true);
$code = sanitize($_GET["sharecode"], true, true);
$requester_ip = $_SERVER['REMOTE_ADDR'];

function _log($text) {
    global $log_dir, $authkey, $map, $requester_ip, $code;
    file_put_contents($log_dir, date("D M j G:i:s T Y")." - getcourse.php - ".$text." (".$authkey.", ".$map.", ".$code.", ".$requester_ip.")\n", FILE_APPEND);
}

function _error($reason) {
    print($reason);
    http_response_code(400);
    _log($reason);
    exit;
}

// i used this in hvh.tf... good times.
function is_ratelimited() {
    global $requester_ip, $upload_ratelimit_dir, $ratelimit_period;
    $ratelimit_array = json_decode(file_get_contents($upload_ratelimit_dir), $associative = true);

    if ($ratelimit_array[$requester_ip] === -1) { return true; } // you can shadowban ip's like that!

    if (time() - $ratelimit_array[$requester_ip] <= $ratelimit_period) { return true; }

    foreach ($ratelimit_array as $uid => $time) {
        if (time() - $ratelimit_array[$uid] > $ratelimit_period) {
            unset($ratelimit_array[$uid]);
        }
    }

    $ratelimit_array[$requester_ip] = time();

    $ratelimit_json = json_encode($ratelimit_array, JSON_PRETTY_PRINT);
    $fp = fopen($upload_ratelimit_dir, 'w');
    fwrite($fp, $ratelimit_json);
    fclose($fp);

    return false;
}

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

function is_allowed($key) {
    global $upload_keys;
    $key_array = json_decode(file_get_contents($upload_keys), $associative = true);

    if (count($key_array) <= 0) { return true; }

    if ($key_array[$key]) { return true; }

    return false;
}

function headers_are_valid($headers) {
    if ($_SERVER['REQUEST_METHOD'] != "GET" ||
        $headers["user-agent"] != "Valve/Steam HTTP Client 1.0 (4000)" ||
        $headers["accept-encoding"] != "gzip, deflate") { return false; } else { return true; }
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

if (!headers_are_valid($headers)) { _error("Invalid headers."); }
if (is_ratelimited()) { _error("Ratelimited."); }
if (!is_allowed($authkey)) { _error("Invalid key."); }

$path = "courses/".$map."/".$code.".txt";
$body = file_get_contents($path);
$decoded_body = json_decode($body, true);
if (!$decoded_body) {_error("Invalid course (not json)"); }
if (!body_is_valid($decoded_body)) { _error("Invalid course (invalid signature)"); }

print($body);
_log("Loaded a course under the name: ".sanitize($decoded_body[4], true, true));

?>


