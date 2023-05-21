<?php

$requester_ip = $_SERVER['REMOTE_ADDR'];
$ratelimit_period = 30;
$upload_ratelimit_dir = "_internal.json";

// i used this in hvh.tf... good times.
function is_ratelimited() {
    global $requester_ip, $upload_ratelimit_dir, $ratelimit_period;
    $ratelimit_array = json_decode(file_get_contents($upload_ratelimit_dir), $associative = true);

    if ($ratelimit_array[$requester_ip] === -1) {
        return true; // shadow ban 8)
    }

    if (time() - $ratelimit_array[$requester_ip] <= $ratelimit_period) {
        return true;
    }

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
    $strip = array("~", "`", "!", "@", "#", "$", "%", "^", "&", "*", "(", ")", "_", "=", "+", "[", "{", "]",
                   "}", "\\", "|", ";", ":", "\"", "'", "&#8216;", "&#8217;", "&#8220;", "&#8221;", "&#8211;", "&#8212;",
                   "â€”", "â€“", ",", "<", ".", ">", "/", "?");
    $clean = trim(str_replace($strip, "", strip_tags($string)));
    $clean = preg_replace('/\s+/', "-", $clean);
    $clean = ($anal) ? preg_replace("/[^a-zA-Z0-9]/", "", $clean) : $clean ;
    return ($force_lowercase) ?
        (function_exists('mb_strtolower')) ?
            mb_strtolower($clean, 'UTF-8') :
            strtolower($clean) :
        $clean;
}

$headers = getallheaders();

if ($_SERVER['REQUEST_METHOD'] != "GET" ||
    $headers["user-agent"] != "Valve/Steam HTTP Client 1.0 (4000)" ||
    $headers["accept-encoding"] != "gzip, deflate") { print("Rejected\n"); return; }

if (is_ratelimited()) { print("Not member"); return; } // placeholder cuz i dont want to modify original beatrun code

$sanitized_map = sanitize($_GET["map"], true, true);
$sanitized_code = sanitize($_GET["sharecode"], true, true);
$path = "courses/".$sanitized_map."/".$sanitized_code.".txt";

$body = file_get_contents($path);
$decoded_body = json_decode($body, true);
if (!$decoded_body) { print("Bad code"); return; }

print($body);

?>


