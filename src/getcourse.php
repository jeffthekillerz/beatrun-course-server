<?php 

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

$sanitized_map = sanitize($_GET["map"], true, true);
$sanitized_code = sanitize($_GET["sharecode"], true, true);
$path = "courses/".$sanitized_map."/".$sanitized_code.".txt";

$body = file_get_contents($path);
$decoded_body = json_decode($body, true);
if (!$decoded_body) { print("Not found.\n"); return; }

print($body);

?>


