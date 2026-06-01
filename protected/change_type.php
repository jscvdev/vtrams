<?php
require 'core/components/security/config_session.inc.php';

function changeType()
{
    if (isset($_GET['param']))
    {
        $param = $_GET['param'];

        if ($param === "procurement")
        {
            $_SESSION["change_type"] = "procurement";
        }
        elseif ($param === "vouchers")
        {
            $_SESSION["change_type"] = "vouchers";
        }
    }
}
changeType();
exit();