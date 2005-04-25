<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
    <title>LiveUser Example 5</title>
    <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
    <style type="text/css" media="screen">@import "layout_frontend.css";</style>
</head>

<body>

    <div class="content">
        <h1>LiveUser Example 5</h1>
        <p>Please edit conf.php according to your needs and run the sql dump
        contained in <br />
        <code>
            PEAR_DATA_DIRECTORY/liveuser/misc/schema/Auth_DB.sql &amp;
            PEAR_DATA_DIRECTORY/liveuser/misc/schema/perm_db_(simple|medium).sql &amp;
            PEAR_DATA_DIRECTORY/liveuser/docs/examples/examples5/create_db.sql
        </code>
        
        <br /> on a database you created beforehand.</p>
        <p>You can use the following commands :</p>
        <p><code>#mysql -uUSER -p<br />
        <span style="font-style: italic">enter password here</span><br />
        mysql> create database DATABASENAME;<br />
        mysql>exit;<br />
        #mysql -uUSER -p DATABASENAME < PEAR_DATA_DIRECTORY/liveuser/misc/schema/create_db.sql
    #mysql -uUSER -p DATABASENAME < PEAR_DATA_DIRECTORY/liveuser/docs/examples/examples5/create_db.sql   
    </code>
        <br />
        <span style="font-style: italic">enter your password here. Writing it on the same line means anybody with access
        to the server can see the password (by running #w for example)</span></p>
    </div>

    <div class="content">
        <h2><a href="home.php">Proceed to example 5</a></h2>
    </div>

</body>
</html>
