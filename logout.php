<?php
session_start();
//branch
session_destroy();
header("Location: login.php");
exit;
