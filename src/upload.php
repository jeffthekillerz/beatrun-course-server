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

if ($_SERVER['REQUEST_METHOD'] != "POST" ||
    $headers["Content-Type"] != "text/plain" ||
    $headers["user-agent"] != "Valve/Steam HTTP Client 1.0 (4000)" ||
    $headers["accept-encoding"] != "gzip, deflate") { print("Rejected.\n"); return; }

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

mkdir($path);
file_put_contents($path.$course_id.".txt", $body);

print("Uploaded under the ID: ".$course_id."\n");
print($path);

?>


