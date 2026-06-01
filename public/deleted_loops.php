<?php if (in_array("CENRO", $target_office)) : ?>

                                <?php if ($_SESSION['acl'] >= 3) : ?>
                                    <?php
                                    $loggedUserOffice = $_SESSION['logged_user_office'] ?? null;
                                    ?>

                                    <div class='label-input__container input-dynamic'>
                                        <label for='internal_office'>Internal Office</label>
                                        <div class="cc-dropdown">
                                            <div class="cc-dropdown-toggle cc_to" id="cc_to">Select Options</div>
                                            <div class="cc-dropdown-menu">
                                                <div class="group">
                                                    <strong>Please Select:</strong>
                                                    <?php
                                                    if ($loggedUserOffice) {
                                                        $sql = "SELECT designation 
                                                        FROM designation_limit 
                                                        WHERE FIND_IN_SET(:office, designated_office) > 0 
                                                        ORDER BY designation";

                                                        $stmt = $pdo->prepare($sql);
                                                        $stmt->execute(['office' => $loggedUserOffice]);

                                                        $foundAny = false;
                                                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                                            $foundAny = true;
                                                            $designation = htmlspecialchars($row['designation']);
                                                            echo "<label><input type='checkbox' name='internal_office[]' value='{$designation}'> {$designation}</label>";
                                                        }

                                                        if (!$foundAny) {
                                                            echo "<label><em>No designations available</em></label>";
                                                        }
                                                    } else {
                                                        echo "<label><em>No office context available</em></label>";
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif ?>
                            <?php endif ?>