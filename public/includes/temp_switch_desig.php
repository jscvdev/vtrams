<?php if (
    $_SESSION['acl'] >= 8 or in_array("ICU", $target) or in_array("Planning Section", $target) or in_array("Budget Unit", $target) or in_array("Accounting Unit", $target)
    or in_array("Office of the PENRO", $target) or in_array("Cashiers Unit", $target)
) : ?>
                        <?php endif ?>
                        <?php if (
                            $_SESSION['acl'] >= 8 or in_array("ICU", $target) or in_array("Budget Unit", $target) or in_array("Accounting Unit", $target)
                            or in_array("Office of the PENRO", $target) or in_array("Cashiers Unit", $target)
                        ) : ?>
                        <?php endif ?>