<?php 

$ratelimit_period = 5;
$upload_ratelimit_dir = "_ratelimit.json";
$upload_keys = "_internal.json";
$log_dir = "_logs.log";

$headers = getallheaders();
$authkey = sanitize($headers["Authorization"], true, true);
$map = sanitize($headers["Game-Map"], true, true);
$requester_ip = $_SERVER['REMOTE_ADDR'];

function _log($text) {
    global $log_dir, $authkey, $map, $requester_ip;
    file_put_contents($log_dir, date("D M j G:i:s T Y")." - upload.php - ".$text." (".$authkey.", ".$map.", ".$requester_ip.")\n", FILE_APPEND);
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
    $clean = ($anal) ? preg_replace("/[^a-zA-Z0-9_]/", "", $clean) : $clean ;
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
    if ($_SERVER['REQUEST_METHOD'] != "POST" ||
        $headers["Content-Type"] != "text/plain" ||
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

if (!headers_are_valid($headers)) { _log("Invalid headers."); print("Invalid headers."); return; }
if (is_ratelimited()) { _log("Ratelimited."); print("Ratelimited.\n"); return; }
if (!is_allowed($authkey)) { _log("Not valid key."); print("Not valid key"); return; }

$body = file_get_contents('php://input');
$decoded_body = json_decode($body, true);
if (!$decoded_body) { _log("Couldn't decode."); print("Rejected.\n"); return; }
if (!body_is_valid($decoded_body)) { _log("Invalid course."); print("Invalid course.\n"); return; }
print("Accepted.\n");

$path = "courses/".$map."/";

$course_id = rand(1, 9999999);
$file = $path.$course_id.".txt";

$iter_limit = 500;
$iter = 0;
while (file_exists($file)) {
    if ($iter > $iter_limit) { print("Too many courses for this map. Try again or increase iter_limit.\n"); return; }
    $course_id = rand(1, 9999999);
    $file = $path.$course_id.".txt";
    $iter++;
}

if (!is_dir($path)) { mkdir($path, 0755, true); }
file_put_contents($file, $body);

_log("Uploaded a course: ".$course_id." (name: ".sanitize($decoded_body[4], true, true).")");
print("Uploaded under the ID: ".$course_id."\n");

?>


