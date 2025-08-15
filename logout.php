<?php
require __DIR__.'/core.php';
session_destroy();
header('Location: login.php');