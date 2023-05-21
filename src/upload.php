<?php 

$requester_ip = $_SERVER['REMOTE_ADDR'];
$ratelimit_period = 30;
$upload_ratelimit_dir = "_ratelimit.json";
$upload_keys = "_internal.json";

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

function is_allowed($key) {
    global $upload_keys;
    $key_array = json_decode(file_get_contents($upload_keys), $associative = true);

    if (count($key_array) <= 0) {
        return true;
    }

    $key_sanitized = sanitize($key, true, true);

    if ($key_array[$key_sanitized]) {
        return true;
    }

    return false;
}

$headers = getallheaders();

if ($_SERVER['REQUEST_METHOD'] != "POST" ||
    $headers["Content-Type"] != "text/plain" ||
    $headers["user-agent"] != "Valve/Steam HTTP Client 1.0 (4000)" ||
    $headers["accept-encoding"] != "gzip, deflate") { print("Rejected.\n"); return; }

if (is_ratelimited()) { print("Ratelimited.\n"); return; }
if (!is_allowed($headers["authorization"])) { print("Not valid key"); return; }

$body = file_get_contents('php://input');
$decoded_body = json_decode($body, true);
if (!$decoded_body) { print("Rejected.\n"); return; }

print("Accepted.\n");

$sanitized_map = sanitize($headers["Game-Map"], true, true);
$path = "courses/".$sanitized_map."/";

$course_id = rand(1, 9999999);
$file = $path.$course_id.".txt";

$iter_limit = 500;
$iter = 0;
while (file_exists($file)) {
    if ($iter > $iter_limit) {
        print("Too many courses for this map. Try again or increase iter_limit.\n");
        return;
    }
    $course_id = rand(1, 9999999);
    $file = $path.$course_id.".txt";
    $iter++;
}

mkdir($path, 0755, true);
file_put_contents($path.$course_id.".txt", $body);

print("Uploaded under the ID: ".$course_id."\n");
print($path);

?>


