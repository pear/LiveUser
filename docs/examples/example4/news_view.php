<?php
// CREATING ENVIRONMENT
require_once 'conf.php';

$tpl->loadTemplatefile('news_view.tpl.php');

$res = $db->query('SELECT
                       DATE_FORMAT(news.created_at,"%d.%m.%Y - %H:%i") AS date,
                       news.news,
                       liveuser_users.handle
                   FROM
                       news
                   INNER JOIN
                       liveuser_perm_users
                   ON
                       news.owner_user_id = liveuser_perm_users.perm_user_id
                   INNER JOIN
                       liveuser_users
                   ON
                       liveuser_perm_users.auth_user_id = liveuser_users.auth_user_id
                   ORDER BY
                     news.created_at DESC');

while ($row = $res->fetchRow()) {
    $tpl->setCurrentBlock('row');

    $tpl->setVariable(array('time'     => $row['date'],
                            'news'     => stripslashes($row['news']),
                            'email'    => $row['handle'] . '@your-company.com',
                            'author'   => $row['handle']));

    $tpl->parseCurrentBlock();
}

    include_once 'finish.inc.php';
?>
