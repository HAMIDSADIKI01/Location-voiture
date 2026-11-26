<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('#/(\.|database\.sql|README|LICENSE)#i', $path)) {
    http_response_code(403);
    exit('403 Interdit');
}
return false;