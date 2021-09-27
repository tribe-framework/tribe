<?php include_once __DIR__ . '/_header.php'?>

<?php
// YOU CAN REMOVE AFTER FIRST VISIT
$q = $sql->executeSQL("SELECT `id` FROM `data` WHERE `type`='user'");
if (!$q[0]['id']) {
    $usr = array();
    $usr['type'] = 'user';
    $usr['role_slug'] = 'admin';
    $usr['email'] = $_ENV['CONTACT_EMAIL'];
    $usr['password'] = $_ENV['DB_PASS'];
    $usr['user_id'] = $dash->get_unique_user_id();
    $dash->push_content($usr);
}
?>

<main class="container mt-5">
    <div class="starter-template text-center py-5 px-3">
        <h1>
            We support our farmers in their fight against big corporations.
        </h1>

        <div><img class="img-fluid" src="<?=THEME_URL;?>/assets/img/i-heart-kheti.jpg"></div>

        <p class="mt-4">
            Found this during the protest at Singhu Border. Loved the fact that this was picked up from Milton Glaser's work and used so well.
        </p>

        <p class="mt-4 text-muted">
            A Conversation with <a href="https://creativecloud.adobe.com/cc/discover/article/a-conversation-with-milton-glaser?locale=en">Milton Glaser</a>.
        </p>

    </div>
</main>

<?php include_once __DIR__ . '/_footer.php'?>
