<?php
/**
 * Administration page
 */
require_once 'conf.php';
require_once 'liveuser_rights.php';
require_once 'HTML/Template/IT.php';

if (!$usr->isLoggedIn() || !$usr->checkRight(EDITNEWS)) {
    echo 'Sorry but you cannot access this page';
    exit;
}

if (isset($_POST['news'])) {
    if (!$usr->checkRight(MODIFYNEWS)) {
        echo 'You are trying to modify a news but do not have the right to do so !';
        exit;
    }
    if (isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $title = htmlspecialchars(strip_tags($_POST['title']));
        $newscontent = htmlspecialchars(strip_tags($_POST['newscontent']));
        if ($id == 0) {
            insertNews($db, $title, $newscontent, $usr->getProperty('perm_user_id'));
        } else {
            updateNewsContent($db, $id, $title, $newscontent, $usr->getProperty('perm_user_id'));
        }
    }
}

$category = 'general';

if (isset($_GET['mode']) && $_GET['mode'] == 'edit') {
    if (!isset($_GET['id']) && !is_numeric($_GET['id'])) {
        die('Missing news id');
    }

    $id = (int)$_GET['id'];
    $news = getNewsContent($db, $id);
} elseif (isset($_GET['mode']) && $_GET['mode'] == 'insert') {
    $news = getNewsContent($db);
} else {
    $news = getNewsList($db, $category);
}

$tpl =& new HTML_Template_IT('./');
$tpl->loadTemplatefile('admin.tpl');

// assign the content to the vars
$tpl->setVariable('USER', $usr->getProperty('handle'));
$tpl->setVariable('NEWS', $news);

$tpl->show();

/**
 * Returns news list
 * for a given category
 *
 * @param  object  &$db      a reference to a db connection object
 * @param  string  $category news category
 */
function getNewsList(&$db, $category)
{
    $query = "
        SELECT
            news_id      AS assockey,
            news_id      AS id,
            DATE_FORMAT(news_date, '%D %b %Y at %H:%I:%S') AS date,
            news_title   AS title
        FROM
            news
        WHERE
            news_category = ". $db->quote($category, 'text');

    $news = $db->queryAll($query, null, MDB2_FETCHMODE_ASSOC, true);

    if (PEAR::isError($news)) {
        die($news->getMessage() . ' ' . $news->getUserinfo());
    } else {
        $tpl =& new HTML_Template_IT('./');

        $tpl->loadTemplatefile('news_list.tpl', false, false);

        $tpl->setVariable('CATEGORY', ucfirst($category));

        foreach ($news as $id => $name) {
            foreach ($name as $cell) {
                // Assign data to the inner block
                $tpl->setCurrentBlock('cell');
                $tpl->setVariable("ID",   $id);
                $tpl->setVariable("DATA", nl2br($cell));
                $tpl->parseCurrentBlock('cell');
            }
            // Assign data and the inner block to the
            // outer block
            $tpl->setCurrentBlock('row');
            $tpl->parseCurrentBlock('row');
        }
        return $tpl->get();
    }

}

/**
 * Get a news content.
 *
 * @param  object  &$db     a reference to a db connection object
 * @param  int     $id      news id
 * @param  string  $content the new content
 * @return mixed   content as a string or error
 */
function getNewsContent(&$db, $news = null)
{
    if (!is_null($news)) {
        $query = "
            SELECT
                news_id      AS id,
                news_title   AS title,
                DATE_FORMAT(news_date, '%D %b %Y at %H:%I:%S') AS date,
                news_content AS content
            FROM
                news
            WHERE
                news_id = $news";
    
        $news = $db->queryRow( $query );
    }

    if  (PEAR::isError($news)) {
        die($news->getMessage() . ' ' . $news->getUserinfo());
    } else {
        $tpl =& new HTML_Template_IT('./');

        $tpl->loadTemplatefile('news_edit.tpl', false, false);

        $tpl->setVariable('ID',      $news['id']);
        $tpl->setVariable('TITLE',   $news['title']);
        $tpl->setVariable('DATE',    $news['date']);
        $tpl->setVariable('CONTENT', $news['content']);

        return $tpl->get();
    }

}

/**
 * Update a news content
 *
 * @param  object  &$db     a reference to a db connection object
 * @param  int     $id      news id
 * @param  string  $content the new content
 * @return mixed   content as a string or error
 */
function updateNewsContent(&$db, &$id, $title, $content, $user)
{
    $content = strip_tags($content);
    $query = '
        UPDATE
            news
        SET
            news_content = ' . $db->quote($content, 'text') . ',
            news_title = ' . $db->quote($title, 'text') . '
        WHERE
            news_id = "' . $id . '"';

    $db->query($query);
}

/**
 * Insert news in database
 *
 * @param  object  &$db     a reference to a db connection object
 * @param  string  $title   news title
 * @param  string  $content the new content
 * @return mixed   content as a string or error
 */
function insertNews(&$db, $title, $content, $user)
{
    $content = strip_tags($content);
    $query = '
        INSERT INTO
            news
        (news_id, news_date,
        news_title, news_content)
        VALUES
        ("' . $db->nextId('news') . '", "' . date('Y-m-d H:i:s') . '",
        ' . $db->quote($title, 'text') . ', ' . $db->quote($content, 'text') . ')';

    $db->query($query);
}
?>
