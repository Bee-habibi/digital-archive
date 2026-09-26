<?php

/**
 * Front controller for running the app directly under /digital-archive
 * (XAMPP subfolder deployment). Delegates to the Laravel front controller
 * in public/; its internal paths resolve relative to its own directory.
 */

require __DIR__.'/public/index.php';
