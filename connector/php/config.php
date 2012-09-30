<?php

//Root directory of server
define('DIR_ROOT', $_SERVER['DOCUMENT_ROOT']);
//Root http of the site
define('HTTP_ROOT', 'http://' . $_SERVER["HTTP_HOST"]); // or http://example.com/blog
//Directory for saving images
define('DIR_IMAGES', '/examples/uploaded/images');
//Directory for saving files
define('DIR_FILES', '/examples/uploaded/files');
//Path to WideImage lib (used to generate thumbnails)
define('WIDE_IMAGE_LIB', 'WideImage/WideImage.php');

//Maximum width and height of uploaded images. All larger images will be shrinked. You can set only one dimension.
define('MAX_WIDTH', 800);
define('MAX_HEIGHT', 0);

//Width and height of thumbnail images. You can set only one dimension.
define('WIDTH_TO_LINK', 220);
define('HEIGHT_TO_LINK', 200);

//Attributes of link tag - class and rel (for lighbox scripts etc)
define('CLASS_LINK', 'lightview');
define('REL_LINK', 'lightbox');

// how many files to show on one page in file manager
define('FILES_PER_PAGE', 50);

//directory separator
define('DS', '/');
//file manager language. can be overriden by js
define('LANG', 'en');

date_default_timezone_set('Asia/Novosibirsk');
?>
