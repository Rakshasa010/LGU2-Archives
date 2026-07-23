<?php
echo "Checking curl support...<br>";
if (function_exists('curl_version')) {
    echo "Curl is enabled!<br>";
    print_r(curl_version());
} else {
    echo "Curl is NOT enabled! Please enable it in php.ini!";
}
?>