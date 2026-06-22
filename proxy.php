<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);


/* ----------------------------
   TARGET
---------------------------- */
$rid = isset($_GET['rid']) ? $_GET['rid'] : '';
// $parts = parse_url($url);
// parse_str($parts['query'], $query);

// $rid = $query['rid'];



$target = array(
    'scheme' => 'http',
    'host'   => '115.31.150.126',
    'port'   => 80
);

/* ----------------------------
   BUILD URL
---------------------------- */

$query = '';
if ($rid != '') {
    $query = '?rid=' . urlencode($rid);
}

// if (!empty($_SERVER['QUERY_STRING'])) {
//     $query = '?' . $_SERVER['QUERY_STRING'];
// }

#$url = 'https://gophish.team.co.th/' . $query;

#$url = $target['scheme'] . '://' . $target['host'] . $query;
$url = $target['scheme'] . '://' . $target['host'] . ':' . $target['port'] . $query;

#echo $url;exit;

/* ----------------------------
   INIT CURL
---------------------------- */
$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $_SERVER['REQUEST_METHOD']);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

/* SSL (shared hosting fix) */
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

/* ----------------------------
   BUILD HEADERS (NO getallheaders)
---------------------------- */
$headers = array();

foreach ($_SERVER as $name => $value) {
    if (substr($name, 0, 5) === 'HTTP_') {

        $key = str_replace(
            ' ',
            '-',
            ucwords(strtolower(str_replace('_', ' ', substr($name, 5))))
        );

        $headers[$key] = $value;
    }
}

/* remove bad headers */
if (isset($headers['Host'])) {
    unset($headers['Host']);
}

unset($headers['Content-Length']);

/* fix content-type boundary issue */
if (isset($headers['Content-Type'])) {
    $headers['Content-Type'] = preg_replace('/; boundary=[^;]+/', '', $headers['Content-Type']);
}

/* convert to curl format */
$curlHeaders = array();

foreach ($headers as $k => $v) {
    $curlHeaders[] = $k . ': ' . $v;
}

curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);

/* ----------------------------
   BODY FORWARD
---------------------------- */
$method = $_SERVER['REQUEST_METHOD'];

if (!empty($_FILES)) {

    $files = array();

    foreach ($_FILES as $name => $file) {
        $files[$name] = curl_file_create(
            $file['tmp_name'],
            $file['type'],
            $file['name']
        );
    }

    curl_setopt($ch, CURLOPT_POSTFIELDS, array_merge($_POST, $files));

} elseif (
    $method === 'POST' ||
    $method === 'PUT' ||
    $method === 'PATCH'
) {

    curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents('php://input'));
}

/* ----------------------------
   EXEC
---------------------------- */
$result = curl_exec($ch);

if ($result === false) {
    http_response_code(500);

    echo "cURL Error: " . curl_errno($ch) . "\n";
    echo curl_error($ch);

    curl_close($ch);
    exit;
}

/* split response */
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

curl_close($ch);

$header = substr($result, 0, $headerSize);
$body   = substr($result, $headerSize);

/* ----------------------------
   SEND HEADERS
---------------------------- */
$headerLines = explode("\r\n", $header);

foreach ($headerLines as $line) {

    if (trim($line) === '') continue;
    if (stripos($line, 'Transfer-Encoding:') === 0) continue;
    if (stripos($line, 'Connection:') === 0) continue;
    if (stripos($line, 'Content-Length:') === 0) continue;
    if (stripos($line, 'HTTP/') === 0) continue;

    header($line, false);
}

/* ----------------------------
   OUTPUT
---------------------------- */
echo $body;