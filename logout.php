<?php
require_once 'includes/auth.php';

$_SESSION = [];
session_destroy();
redirect('index.php');
