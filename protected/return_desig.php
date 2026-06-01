<?php
require 'core/components/security/config_session.inc.php';

function returnDesignation()
{
   $_SESSION["change_type"] = "documents";
}
returnDesignation();
exit();
