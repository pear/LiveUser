<?php
    $tpl->setVariable(array('user'         => $LU->getProperty('handle'),
                          'lastLogin'    => date('d.m.Y H:i', $LU->getProperty('lastLogin'))
                        )
                    );

    $tpl->show();
?>