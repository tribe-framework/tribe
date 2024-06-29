<?php
/**
 * REQUIRED OPTIONS:
 * app_name
 * app_port
 * db_port
 * junction_port
 */

function random_generator(int $size) {
    return bin2hex(random_bytes($size));
}

function to_snake_case(string $data, bool $lowercase = false) {
    $data = str_replace(" ", "_", $data);
    $data = str_replace("-", "_", $data);

    if ($lowercase) {
        $data = strtolower($data);
    }

    return $data;
}

function find_free_port($start_port) {
    exec("netstat -tnlp | grep ':{$start_port}' | wc -l", $status);

    if ($status[0] == 0) {
        return $start_port;
    }

    $start_port += 1;
    return find_free_port($start_port);
}

$APP_NAME_OG = $_GET['app_name'] ?? null;

if (!$APP_NAME_OG) {
    die("'app_name' is required");
}

$BASE_DIR = "/home/apurv/junctions";

if (!is_dir($BASE_DIR)) {
    mkdir($BASE_DIR);
}

chdir($BASE_DIR);

$bytes = random_generator(4);

// $APP_NAME_OG = "hello_world-e735fff8";

$APP_NAME = to_snake_case($APP_NAME_OG);
$APP_PATH = "{$BASE_DIR}/{$APP_NAME}-{$bytes}";
// $APP_PATH = "/var/www/junctions/hello_world-e735fff8";

// $APP_PORT = find_free_port(8080);
$APP_PORT = $_GET['app_port'];
$JUNCTION_PORT = $_GET['junction_port'];

$DOMAIN_NAME = "localhost:{$APP_PORT}";

$DATABASE_USER = "root";
$DATABASE_NAME = to_snake_case($APP_NAME_OG, true);
$DATABASE_PASS = random_generator(6);
$DATABASE_HOST = "{$APP_NAME}-db";
$DATABASE_PORT = 3306;
// $DOCKER_DB_PORT = find_free_port(3307);
$DOCKER_DB_PORT = $_GET['db_port'];

try {
    exec("git clone https://github.com/tribe-framework/docker-tribe-template.git {$APP_NAME}-{$bytes}");

    // $phar = new PharData(TRIBE_ROOT . '/tribe-template.tgz');
    // $phar->extractTo($BASE_DIR);

    // rename("$BASE_DIR/tribe-template", $APP_PATH);
} catch (\Exception $e) {
    echo "<pre style='color: red;'>";
    var_dump($e);
    echo "</pre>";
    die();
}

chdir($APP_PATH);

// update docker-compose variables
$docker_compose = file_get_contents("{$APP_PATH}/docker-compose.yml");
$docker_compose = str_replace("\$APP_NAME", $APP_NAME, $docker_compose);
$docker_compose = str_replace("\$DATABASE_NAME", $DATABASE_NAME, $docker_compose);
$docker_compose = str_replace("\$DATABASE_PASS", $DATABASE_PASS, $docker_compose);
$docker_compose = str_replace("\$APP_PORT", $APP_PORT, $docker_compose);
$docker_compose = str_replace("\$JUNCTION_PORT", $JUNCTION_PORT, $docker_compose);
$docker_compose = str_replace("\$DOCKER_DB_PORT", $DOCKER_DB_PORT, $docker_compose);

file_put_contents("{$APP_PATH}/docker-compose.yml", $docker_compose);

// update .env
copy("{$APP_PATH}/tribe/.env.sample", "{$APP_PATH}/tribe/.env");
$env_file = file_get_contents("{$APP_PATH}/tribe/.env");
$env_file = str_replace("\$DOMAIN_NAME", $DOMAIN_NAME, $env_file);
$env_file = str_replace("\$DATABASE_NAME", $DATABASE_NAME, $env_file);
$env_file = str_replace("\$DATABASE_USER", $DATABASE_USER, $env_file);
$env_file = str_replace("\$DATABASE_PASS", $DATABASE_PASS, $env_file);
$env_file = str_replace("\$DATABASE_HOST", $DATABASE_HOST, $env_file);
$env_file = str_replace("\$DATABASE_PORT", $DATABASE_PORT, $env_file);

file_put_contents("{$APP_PATH}/tribe/.env", $env_file);

exec("docker-compose up -d");

exec("sleep 10; docker exec -i {$DATABASE_HOST} mysql -u{$DATABASE_USER} -p{$DATABASE_PASS} {$DATABASE_NAME} < {$APP_PATH}/tribe/install/db.sql");
