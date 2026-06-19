<?php
$output = "=== GIT STATUS ===\n" . shell_exec("git status 2>&1") . "\n\n";
$output .= "=== GIT STASH LIST ===\n" . shell_exec("git stash list 2>&1") . "\n\n";
$output .= "=== GIT REFLOG ===\n" . shell_exec("git reflog -n 20 2>&1") . "\n\n";
$output .= "=== GIT LOG ===\n" . shell_exec("git log -n 5 --oneline 2>&1") . "\n\n";
file_put_contents("git_output.txt", $output);
echo "Done";
?>
