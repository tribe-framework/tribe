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

<section class="flame-bg d-flex align-items-center justify-content-center">
  <div class="py-6 container px-0 text-center text-dark">
    <img src="/theme/assets/img/flame.png" width="200">
  </div>
</section>

<?php include_once __DIR__ . '/_footer.php'?>
