<?php
// $Id$

// CREATING ENVIRONMENT
require_once 'conf.php';

// If the user hasn't the right to write news -> access denied.
if (!$LU->checkRight(RIGHT_NEWS_NEW)) {
    $tpl->loadTemplatefile('news_notallowed.tpl.php', false, false);
    include_once 'finish.inc.php';
    exit();
}

$tpl->loadTemplatefile('news_new.tpl.php');

// Read form data.
$news     = isset($_POST['news'])     ? $_POST['news'] : '';
$valid_to = isset($_POST['valid_to']) ? (int)$_POST['valid_to'] : '';
$group    = isset($_POST['group_id']) ? (int)$_POST['group_id'] : '';


// If $news is not empty, we have something to work.
if (!empty($news)) {

    if (!ereg('^[1-9][0-9]?$', $valid_to)) {
        $tpl->setVariable('script_msg', '<p style="color: red;">Only numbers between 1 and 99 are allowed here.</p>');
    } elseif (!$LU->checkRightLevel(RIGHT_NEWS_NEW, $LU->getProperty('permUserId'), $group)) {
        $tpl->setVariable('script_msg', '<p style="color: red;">You don\'t have the right to post news for this group.</p>');
    } else {
    // Form seems to be correct. Write data into the db.
    $news = str_replace("\r\n",'<br />',$news);

    $result = $db->query('INSERT INTO
                  news (
                      news_id,
                      created_at,
                      valid_to,
                      news,
                      owner_user_id,
                      owner_group_id
                  )
                  VALUES (
                      ' . $db->nextId('news') . ',
                      NOW(),
                      ' . $db->quote( date('Y.m.d H:i:s', time()+60*60*24*7*$valid_to), 'timestamp' ) . ',
                      ' . $db->quote( addslashes( $news ), 'text' ).',
                      ' . $db->quote( $LU->getProperty('permUserId'), 'integer' ) . ',
                      ' . $group . ')');

        $tpl->setVariable('script_msg', '<p><b>News has been added.</b></p>');

        // null form data.
        $news     = '';
        $valid_to = '';
        $group    = '';
    }
}

$tpl->setVariable('form_action', 'news_new.php');

if (!empty($news)) {
    $tpl->setVariable('message', $news);
}

if (!empty($valid_to)) {
    $tpl->setVariable('valid', $valid_to);
} else {
    $tpl->setVariable('valid', '2');
}

// If the user is member in more than one group, show them.
if (count($LU->getProperty('groupIds')) > 1) {
    $res = $db->query('SELECT
                         section_id AS group_id,
                         description AS group_comment
                     FROM
                         liveuser_translations
                     WHERE
                         section_type = 3
                         AND section_id IN (' . implode(', ', $LU->getProperty('groupIds')) . ')
                     ORDER BY
                         group_id');

    while ($row = $res->fetchRow()) {
        $tpl->setCurrentBlock('choose_group');
        $tpl->setVariable(array('value' => $row['group_id'],
                              'label' => $row['group_comment']));
        if ($group == $row['group_id']) {
            $tpl->setVariable('selected', 'selected');
        }
        $tpl->parseCurrentBlock();
    }

} else {
    $tpl->setCurrentBlock('set_group');
    $tpl->setVariable('group_id', current($LU->getProperty('groupIds')));
    $tpl->parseCurrentBlock();
}

include_once 'finish.inc.php';
?>