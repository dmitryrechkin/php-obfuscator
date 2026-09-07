<?php
$dir = dirname($argv[1]);
require $dir . '/TraitHelper.php';
require $dir . '/TraitUser.php';
echo (new OneTeamSoftware\Test\User())->run(5), "\n";
