<?php
function get_conf()
{
    $config = parse_ini_file(key_exists("CONFIG_FILE_PATH", $_ENV) ? $_ENV["CONFIG_FILE_PATH"] : "config.ini");
    $config["auth_url"] = key_exists("AUTH_URL", $_ENV) ? $_ENV["AUTH_URL"] : $config["auth_url"];
    $config["auth_realm"] = key_exists("AUTH_REALM", $_ENV) ? $_ENV["AUTH_REALM"] : $config["auth_realm"];
    $config["auth_client_id"] = key_exists("AUTH_CLIENT_ID", $_ENV) ? $_ENV["AUTH_CLIENT_ID"] : $config["auth_client_id"];
    $config["auth_username"] = key_exists("AUTH_USERNAME", $_ENV) ? $_ENV["AUTH_USERNAME"] : $config["auth_username"];
    $config["auth_password"] = key_exists("AUTH_PASSWORD", $_ENV) ? $_ENV["AUTH_PASSWORD"] : $config["auth_password"];
    $config["api_url"] = key_exists("API_URL", $_ENV) ? $_ENV["API_URL"] : $config["api_url"];
    $config["organisation_id"] = key_exists("ORGANISATION_ID", $_ENV) ? $_ENV["ORGANISATION_ID"] : $config["organisation_id"];
    $config["model_id_or_alias"] = key_exists("MODEL_ID_OR_ALIAS", $_ENV) ? $_ENV["MODEL_ID_OR_ALIAS"] : $config["model_id_or_alias"];
    return $config;
}

function getToken()
{
    $config = get_conf();
    $auth_url = $config["auth_url"];
    $auth_realm = $config["auth_realm"];
    $auth_client_id = $config["auth_client_id"];
    $auth_username = $config["auth_username"];
    $auth_password = $config["auth_password"];

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $auth_url . "/realms/" . $auth_realm . "/protocol/openid-connect/token");
    curl_setopt($curl, CURLOPT_POSTFIELDS, "grant_type=password&client_id=" . $auth_client_id . "&username=" . urlencode($auth_username) . "&password=" . urlencode($auth_password));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($curl);
    curl_errno($curl) > 0 && error_log("while getting token : " . curl_error($curl));
    curl_close($curl);

    return json_decode($response)->access_token;
}

function getFormUrl()
{
    $uuid = array_key_exists("uuid", $_GET) ? $_GET["uuid"] : uniqid();
    $email = array_key_exists("email", $_GET) ? $_GET["email"] : "nobody@exemple.com";
    $token = getToken();

    $config = get_conf();
    $api_url = $config["api_url"];
    $organisation_id = $config["organisation_id"];
    $model_id_or_alias = $config["model_id_or_alias"];

    $host = isset($_SERVER['HTTP_X_FORWARDED_HOST']) ? $_SERVER['HTTP_X_FORWARDED_HOST'] : $_SERVER['HTTP_HOST'];
    $port = isset($_SERVER['HTTP_X_FORWARDED_PORT']) ? $_SERVER['HTTP_X_FORWARDED_PORT'] : "";
    $proto = isset($_SERVER['HTTPS']) ? "https" : "http";
    $proto = isset($_SERVER['HTTP_X_FORWARDED_PROTO']) ? $_SERVER['HTTP_X_FORWARDED_PROTO'] : $proto;
    $me = $proto . "://" . $host . ($port ? ":" . $port : "") . $_SERVER['DOCUMENT_URI'];

    $context = [
        "userid" => $uuid,
        "callback" => "?uuid=" . $uuid . "&email=" . $email,
        "country" => "FR",
        "language" => "fr",
        "optoutEmail" => $email,
        "receipt" => false,
        "iframe" => true,
        "iframeEventsTargetOrigin" => $me,
        "optoutEmailLink" => $me . "?uuid=" . $uuid . "&email=" . $email,
    ];

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $api_url . "/organisations/" . $organisation_id . "/consents/" . $model_id_or_alias . "/endpoint");
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($context));
    curl_setopt($curl, CURLOPT_HTTPHEADER, array("Authorization: Bearer $token", "Content-Type: application/json"));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($curl);
    curl_errno($curl) > 0 && error_log("while getting form : " . curl_error($curl));
    curl_close($curl);

    return json_decode($response)->endpoint;
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Simple Consent Integration Example</title>
</head>
<body>
<h2 style="text-align: center">Simple Consent Integration Example</h2>
<iframe src="<?php echo getFormUrl()?>" width="100%" title="Simple Consent Integration Example Iframe" id="consent"
        name="consent"></iframe>
<script type="text/javascript"
        src="https://cdnjs.cloudflare.com/ajax/libs/iframe-resizer/4.0.4/iframeResizer.js"></script>
<script type="text/javascript">iFrameResize({
        log: false,
        checkOrigin: false,
        heightCalculationMethod: 'max'
    }, '#consent');</script>
</body>
</html>
