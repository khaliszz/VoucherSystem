<?php
session_start();
//branch
//test
session_destroy();
header("Location: login.php");
exit;
