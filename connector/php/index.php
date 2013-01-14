<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
include 'config.php';

class TinyImageManager {

  var $dir;
  var $firstAct = false;
  var $folderAct = false;
  var $ALLOWED_IMAGES;
  var $ALLOWED_FILES;
  var $SID;
  var $total_pages = 1;
  var $http_root;

  /**
   * Конструктор
   *
   * @return TinyImageManager
   */
  function TinyImageManager() {

    ob_start("ob_gzhandler");
    header('Content-Type: text/html; charset=utf-8');

    if (isset($_POST['SID'])) {
      session_id($_POST['SID']);
    }
    if (!isset($_SESSION)) {
      session_start();
    }
    $this->SID = session_id();
    require 'yoursessioncheck.php';

    if (!isset($_SESSION['tiny_image_manager_path'])) {
      $_SESSION['tiny_image_manager_path'] = '';
    }
    if (!isset($_SESSION['tiny_image_manager_type'])) {
      $_SESSION['tiny_image_manager_type'] = '';
    }
    if (!isset($_SESSION['tiny_image_manager_page'])) {
      $_SESSION['tiny_image_manager_page'] = 1;
    }

    $this->ALLOWED_IMAGES = array('jpeg', 'jpg', 'gif', 'png');
    $this->ALLOWED_FILES = array('3gp', 'avi', 'bmp', 'bz', 'cpp', 'css', 'doc', 'docx', 'exe', 'flac', 'flv', 'gz',
                                 'htm', 'html', 'm4v', 'mkv', 'mov', 'mp3', 'mp4', 'mpg', 'ogg', 'pdf', 'ppt', 'pptx',
                                 'psd', 'ptt', 'rar', 'rb', 'rtf', 'swf', 'tar', 'tiff', 'txt', 'vob', 'wav', 'wmv',
                                 'xhtml', 'xls', 'xlsx', 'xml', 'zip');

    $this->dir = array('images' => realpath(DIR_ROOT . DIR_IMAGES), 'files' => realpath(DIR_ROOT . DIR_FILES));

    $this->http_root = rtrim(HTTP_ROOT, '/');

    include WIDE_IMAGE_LIB;

    switch ($_POST['action']) {

      case 'setupData':
        $lang = !empty($_POST['lang']) && $_POST['lang'] !== 'undefined' ? $_POST['lang'] : LANG;

        $return['lang'] = '{}';
        $langFile = '../../langs/' . mb_strtolower($lang) . '_data.js';
        if (file_exists($langFile)) {
          $return['lang'] = file_get_contents($langFile);
        }

        $return['upload']['images']['allowed'] = $this->ALLOWED_IMAGES;
        $return['upload']['images']['width'] = MAX_WIDTH;
        $return['upload']['images']['height'] = MAX_HEIGHT;
        $return['upload']['files']['allowed'] = array_merge($this->ALLOWED_IMAGES, $this->ALLOWED_FILES);
        die(json_encode($return));
        break;

      //Создать папку
      case 'newfolder':

        $result = array();
        $path = $_POST['path'];
        $type = $_POST['type'];
        $name = $_POST['name'];

        $dir = $this->AccessDir($path, $type);
        if ($dir) {
          $fullName = $dir . '/' . $name;
          if (preg_match('/[a-z0-9-_]+/sim', $_POST['name'])) {
            if (is_dir($fullName)) {
              $result['error'] = 'folderExists';
            } else {
              if (!mkdir($fullName)) {
                $result['error'] = 'createFolderError';
              }
            }
          } else {
            $result['error'] = 'wrongFolderName';
          }
        } else {
          $result['error'] = 'folderAccessDenied';
        }

        die(json_encode($result));
        break;

      // Загрузка папки
      case 'openFolder':
        // здесь будем хранить результат
        $result = array();

        // чистим исходные данные
        if (!isset($_POST['path']) || $_POST['path'] == '/') {
          $_POST['path'] = '';
        }
        if (!isset($_POST['type'])) {
          $_POST['type'] = '';
        }

        // если зашли первый раз, показываем предыдущую папку
        if (
          isset($_POST['default']) && isset($_SESSION['tiny_image_manager_path'], $_SESSION['tiny_image_manager_type'])
            && $_SESSION['tiny_image_manager_path'] !== 'undefined'
            && $_SESSION['tiny_image_manager_type'] !== 'undefined'
            && $_SESSION['tiny_image_manager_type']
        ) {
          $path = $_SESSION['tiny_image_manager_path'];
          $type = $_SESSION['tiny_image_manager_type'];
        } else {
          $path = $_SESSION['tiny_image_manager_path'] = $_POST['path'];
          // если тип не задан, показываем изображения
          $type = $_POST['type'] ? $_POST['type'] : 'images';
          $_SESSION['tiny_image_manager_type'] = $type;
        }

        if (isset($_POST['default']) && $_SESSION['tiny_image_manager_page'] != 1
          && $_SESSION['tiny_image_manager_page'] != 'undefined'
        ) {
          $page = $_SESSION['tiny_image_manager_page'];
        } else {
          $page = !empty($_POST['page']) ? (int)$_POST['page'] : 1;
          $_SESSION['tiny_image_manager_page'] = $page;
        }


        // генерируем хлебные крошки
        $result['path'] = $this->DirPath($type, $this->AccessDir($path, $type));

        // генерируем дерево каталогов
        $result['tree'] = '';
        // если мы показываем файлы, а не картинки, то картинки надо пропускать
        if ($type == 'files') {
          $this->firstAct = false;
          $result['tree'] .= $this->DirStructure('images', 'first');
          $this->firstAct = $path ? false : true;
          $result['tree'] .= $this->DirStructure('files', 'first', $this->AccessDir($path, 'files'));
        } else {
          // иначе показываем каталог в разделе изображения
          $this->firstAct = $path ? false : true;
          $result['tree'] .= $this->DirStructure('images', 'first', $this->AccessDir($path, 'images'));
          $this->firstAct = false;
          $result['tree'] .= $this->DirStructure('files', 'first');
        }

        // генерируем список файлов
        $result['files'] = $this->ShowDir($path, $type, $page);
        $result['pages'] = $this->ShowPages($path, $type, $page);
        $result['totalPages'] = $this->total_pages;

        die(json_encode($result));
        break;

      //Загрузить изображение
      case 'uploadfile':
        echo $this->UploadFile($_POST['path'], $_POST['pathtype']);
        exit();
        break;

      //Удалить файл, или несколько файлов
      case 'delfile':
        $return = array();
        foreach ($_POST['files'] as $file) {
          $return[$file['filename']] = $this->DelFile($_POST['type'], $_POST['path'], $file['md5'], $file['filename']);
        }
        die(json_encode($return));
        break;

      case 'delfolder':
        die(json_encode($this->DelFolder($_POST['type'], $_POST['path'])));
        break;

      case 'renamefile':
        echo $this->RenameFile($_POST['pathtype'], $_POST['path'], $_POST['filename'], $_POST['newname']);
        exit();
        break;

      default:
        ;
        break;
    }

  }

  /**
   * Проверка на разрешение записи в папку (не системное)
   *
   * @param string $requestDirectory Запрашиваемая папка (относительно DIR_IMAGES или DIR_FILES)
   * @param (images|files) $typeDirectory Тип папки, изображения или файлы
   *
   * @return path|false
   */
  function AccessDir($requestDirectory, $typeDirectory) {
    if ($typeDirectory == 'images') {
      $full_request_images_dir = realpath($this->dir['images'] . $requestDirectory);
      if (strpos($full_request_images_dir, $this->dir['images']) === 0) {
        return $full_request_images_dir;
      } else {
        return false;
      }
    } elseif ($typeDirectory == 'files') {
      $full_request_files_dir = realpath($this->dir['files'] . $requestDirectory);
      if (strpos($full_request_files_dir, $this->dir['files']) === 0) {
        return $full_request_files_dir;
      } else {
        return false;
      }
    } else {
      return false;
    }
  }


  /**
   * Дерево каталогов
   * функция рекурсивная
   *
   * @return array
   */
  function Tree($beginFolder) {
    $struct = array();
    $handle = opendir($beginFolder);
    if ($handle) {
      $struct[$beginFolder]['path'] = str_replace(array($this->dir['files'], $this->dir['images']), '', $beginFolder);
      $tmp = preg_split('[\\/]', $beginFolder);
      $tmp = array_filter($tmp);
      end($tmp);
      $struct[$beginFolder]['name'] = current($tmp);
      $struct[$beginFolder]['count'] = 0;
      while (false !== ($file = readdir($handle))) {
        if ($file != "." && $file != ".." && $file != '.thumbs' && $file != '_thumbs') {
          if (is_dir($beginFolder . '/' . $file)) {
            $struct[$beginFolder]['childs'][] = $this->Tree($beginFolder . '/' . $file);
          } else {
            $struct[$beginFolder]['count']++;
          }
        }
      }
      closedir($handle);
      asort($struct);

      return $struct;
    }

    return false;
  }

  /**
   * Визуализация дерева каталогов
   * функция рекурсивная
   *
   * @param images|files $type
   * @param first|String $innerDirs
   * @param String       $currentDir
   * @param int          $level
   *
   * @return html
   */
  function DirStructure($type, $innerDirs = 'first', $currentDir = '', $level = 0) {
    //Пока отключим файлы
    //if($type=='files') return ;

    $currentDirArr = array();
    if (!empty($currentDir)) {
      $currentDirArr = preg_split('/([\/\\\])/', str_replace($this->dir[$type], '', realpath($currentDir)));
      $currentDirArr = array_filter($currentDirArr);
    }

    if ($innerDirs == 'first') {
      $innerDirs = $this->Tree($this->dir[$type]);
      $firstAct = '';
      if (realpath($currentDir) == $this->dir[$type] && $this->firstAct) {
        $firstAct = 'folderAct';
        $this->firstAct = false;
      }
      $ret = '';
      if ($innerDirs == false) {
        $directory_name = $type == 'images' ? DIR_IMAGES : DIR_FILES;

        return 'Wrong root directory (' . $directory_name . ')<br>';
      }
      foreach ($innerDirs as $v) {
        #TODO: language dependent root folder name
        $ret
          = '<div class="folder folder' . ucfirst($type) . ' ' . $firstAct . '" path="" pathtype="' . $type . '">' . (
        $type == 'images' ? 'Images' : 'Files') . ($v['count'] > 0 ? ' (' . $v['count'] . ')' : '')
          . '</div><div class="folderOpenSection" style="display:block;">';
        if (isset($v['childs'])) {
          $ret .= $this->DirStructure($type, $v['childs'], $currentDir, $level);
        }
        break;
      }
      $ret .= '</div>';

      return $ret;
    }

    if (sizeof($innerDirs) == 0) {
      return false;
    }
    $ret = '';
    foreach ($innerDirs as $v) {
      foreach ($v as $v) {
      }
      if (isset($v['count'])) {
        $files = 'Файлов: ' . $v['count'];
        $count_childs = isset($v['childs']) ? sizeof($v['childs']) : 0;
        if ($count_childs != 0) {
          $files .= ', папок: ' . $count_childs;
        }
      } else {
        $files = '';
      }
      if (isset($v['childs'])) {
        $folderOpen = '';
        $folderAct = '';
        $folderClass = 'folderS';
        if (isset($currentDirArr[$level + 1])) {
          if ($currentDirArr[$level + 1] == $v['name']) {
            $folderOpen = 'style="display:block;"';
            $folderClass = 'folderOpened';
            if ($currentDirArr[sizeof($currentDirArr)] == $v['name'] && !$this->folderAct) {
              $folderAct = 'folderAct';
              $this->folderAct = true;
            } else {
              $folderAct = '';
            }
          }
        }
        $folderClass .= ' folder';
        $ret .= '<div class="' . $folderClass . ' ' . $folderAct . '" path="' . $v['path'] . '" title="' . $files
          . '" pathtype="' . $type . '">' . $v['name'] . ($v['count'] > 0 ? ' (' . $v['count'] . ')' : '')
          . '</div><div class="folderOpenSection" ' . $folderOpen . '>';
        $ret .= $this->DirStructure($type, $v['childs'], $currentDir, $level + 1);
        $ret .= '</div>';
      } else {
        $folderAct = '';
        $soc = count($currentDirArr);
        if ($soc > 0 && $currentDirArr[$soc] == $v['name']) {
          $folderAct = 'folderAct';
        }
        $ret .= '<div class="folder folderClosed ' . $folderAct . '" path="' . $v['path'] . '" title="' . $files
          . '" pathtype="' . $type . '">' . $v['name'] . ($v['count'] > 0 ? ' (' . $v['count'] . ')' : '') . '</div>';
      }
    }

    return $ret;
  }

  /**
   * Путь (хлебные крошки)
   *
   * @param images|files $type
   * @param String       $path
   *
   * @return html
   */
  function DirPath($type, $path = '') {

    if (!empty($path)) {
      $path = preg_split('/([\/\\\])/', str_replace($this->dir[$type], '', realpath($path)));
      $path = array_filter($path);
    }


    $ret = '<div class="addrItem" path="" pathtype="' . $type . '" title=""><img src="img/' . (
    $type == 'images' ? 'folder_open_image' : 'folder_open_document')
      . '.png" width="16" height="16" alt="Корневая директория" /></div>';
    $i = 0;
    $addPath = '';
    if (is_array($path)) {
      foreach ($path as $v) {
        $i++;
        $addPath .= '/' . $v;
        if (sizeof($path) == $i) {
          $ret .= '<div class="addrItemEnd" path="' . $addPath . '" pathtype="' . $type . '" title=""><div>' . $v
            . '</div></div>';
        } else {
          $ret .= '<div class="addrItem" path="' . $addPath . '" pathtype="' . $type . '" title=""><div>' . $v
            . '</div></div>';
        }
      }
    }


    return $ret;
  }


  function CallDir($dir, $type, $page) {

    $files = $this->getFileList($dir, $type);
    if ($files) {
      $this->total_pages = ceil(count($files) / FILES_PER_PAGE);
      $startFile = ($page - 1) * FILES_PER_PAGE;

      return array_slice($files, $startFile, FILES_PER_PAGE);
    } else {
      return false;
    }
  }

  function getFileList($dir, $type) {
    return $this->updateDbFile($dir, $type, true);
  }

  function addFilesInfo($dir, $type, $data) {
    return $this->updateDbFile($dir, $type, false, $data);
  }

  function updateDbFile($inputDir, $type, $return, $newData = array()) {

    $dir = $this->AccessDir($inputDir, $type);

    if (!$dir) {
      return false;
    }

    if (!ini_get('safe_mode')) {
      set_time_limit(120);
    }

    if (!is_dir($dir . '/.thumbs')) {
      mkdir($dir . '/.thumbs');
    }

    $dbfile = $dir . '/.thumbs/.db';


    if (is_file($dbfile)) {
      $dbfilehandle = fopen($dbfile, "r");
      $dblength = filesize($dbfile);
      if ($dblength > 0) {
        $dbdata = fread($dbfilehandle, $dblength);
      }
      fclose($dbfilehandle);
    }
    if (!empty($dbdata)) {
      $files = unserialize($dbdata);

      // test if files were deleted
      foreach ($files as $file) {
        if (!is_file($dir . '/' . $file['filename'])) {
          // delete file from .db
          $this->DelFile($type, $inputDir, $file['md5'], $file['filename']);
          // and don't show it now
          unset($files[$file['filename']]);
        }
      }
    } else {
      $files = array();
    }


    $handle = opendir($dir);


    if ($handle) {
      $newFiles = 0;
      while (false !== ($file = readdir($handle))) {
        if ($file != "." && $file != ".." && is_file($dir . '/' . $file) && !isset($files[$file])) {
          if (!empty($newData[$file])) {
            $files[$file] = $newData[$file];
          } else {
            $file_info = $this->getFileInfo($dir, $type, $file);
            if ($file_info) {
              $files[$file] = $file_info;
            }
          }
          $newFiles++;
        }
      }
      closedir($handle);
    }

    // if there are new files in directory, re-sort and resave .db file
    if ($newFiles) {
      $this->sortFiles($files);
      // save the file
      $dbfilehandle = fopen($dbfile, "w");
      fwrite($dbfilehandle, serialize($files));
      fclose($dbfilehandle);
    }

    if ($return) {
      return $files;
    } else {
      return true;
    }
  }

  function getFileInfo($dir, $type, $file, $realname = '') {
    $fileFullPath = $dir . '/' . $file;
    $file_info = pathinfo($fileFullPath);
    $file_info['extension'] = strtolower($file_info['extension']);

    $allowed = array_merge($this->ALLOWED_IMAGES, $this->ALLOWED_FILES);

    if (!in_array(strtolower($file_info['extension']), $allowed)) {
      return false;
    } else {

      $link = str_replace(array('/\\', '//', '\\\\', '\\'), DS,
        DS . str_replace(realpath(DIR_ROOT), '', realpath($fileFullPath))
      );
      $path = pathinfo($link);
      $link = $this->http_root . $link;


      // проверяем размер загруженного изображения (только для загруженных в папку изображений)
      // и уменьшаем его
      if ($type == 'images' && in_array(strtolower($file_info['extension']), $this->ALLOWED_IMAGES) && (MAX_WIDTH || MAX_HEIGHT)) {
        $maxWidth = MAX_WIDTH ? MAX_WIDTH : '100%';
        $maxHeight = MAX_HEIGHT ? MAX_HEIGHT : '100%';
        try {
          WideImage::load($fileFullPath)->resizeDown($maxWidth, $maxHeight)->saveToFile($fileFullPath);
          $fileImageInfo = getimagesize($fileFullPath);
        } catch (WideImage_InvalidImageSourceException $e) {
          $e->getMessage();
        }
      }
      $files[$file] = array('filename' => $file, 'name' => $realname ? $realname :
        basename(mb_strtolower($file_info['basename']), '.' . $file_info['extension']),
                            'ext' => $file_info['extension'], 'path' => $path['dirname'], 'link' => $link,
                            'size' => filesize($fileFullPath), 'date' => filemtime($fileFullPath),
                            'width' => !empty($fileImageInfo[0]) ? $fileImageInfo[0] : 'N/A',
                            'height' => !empty($fileImageInfo[1]) ? $fileImageInfo[1] : 'N/A',
                            'md5' => md5_file($fileFullPath));

      return $files[$file];
    }
  }


  function UploadFile($inputDir, $type) {
    $dir = $this->AccessDir($inputDir, $type);
    if (!$dir) {
      return false;
    }

    // info about file
    $files = array();

    // HTTP headers for no cache etc
    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
    header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
    header("Cache-Control: no-store, no-cache, must-revalidate");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");

    // 5 minutes execution time
    if (!ini_get('safe_mode')) {
      set_time_limit(5 * 60);
    }


    // Get parameters
    $chunk = isset($_REQUEST["chunk"]) ? $_REQUEST["chunk"] : 0;
    $chunks = isset($_REQUEST["chunks"]) ? $_REQUEST["chunks"] : 0;
    $fileName = isset($_REQUEST["name"]) ? $_REQUEST["name"] : '';


    // get extension and filename
    if (strrpos($fileName, '.') !== false) {
      $ext = strrpos($fileName, '.');
      $extension = substr($fileName, $ext);
      $fileName = substr($fileName, 0, $ext);
    }
    $cleanFileName = $this->encodestring($fileName);
    $file = $cleanFileName . $extension;

    // Make sure the fileName is unique but only if chunking is disabled
    if ($chunks < 2 && file_exists($dir . '/' . $file)) {
      $i = 0;
      $tmpCleanFilename = $cleanFileName;
      $tmpFilename = $fileName;
      while (file_exists($dir . '/' . $file)) {
        // cleanFileName for saving on disk, filename for saving file data in .db
        $i++;
        $cleanFileName = $tmpCleanFilename . '_(' . $i . ')';
        $file = $cleanFileName . $extension;
      }
      $fileName = $tmpFilename . '_(' . $i . ')';
    }

    // Look for the content type header
    if (isset($_SERVER["HTTP_CONTENT_TYPE"])) {
      $contentType = $_SERVER["HTTP_CONTENT_TYPE"];
    }

    if (isset($_SERVER["CONTENT_TYPE"])) {
      $contentType = $_SERVER["CONTENT_TYPE"];
    }

    // Handle non multipart uploads older WebKit versions didn't support multipart in HTML5
    if (strpos($contentType, "multipart") !== false) {
      if (isset($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
        // Open temp file
        $out = fopen($dir . '/' . $file, $chunk == 0 ? "wb" : "ab");
        if ($out) {
          // Read binary input stream and append it to temp file
          $in = fopen($_FILES['file']['tmp_name'], "rb");

          if ($in) {
            while ($buff = fread($in, 4096)) {
              fwrite($out, $buff);
            }
          } else {
            die('{"jsonrpc" : "2.0", "error" : {"code": 101, "message": "Failed to open input stream."}, "id" : "id"}');
          }
          fclose($in);
          fclose($out);
          @unlink($_FILES['file']['tmp_name']);
        } else {
          die('{"jsonrpc" : "2.0", "error" : {"code": 102, "message": "Failed to open output stream."}, "id" : "id"}');
        }
      } else {
        die('{"jsonrpc" : "2.0", "error" : {"code": 103, "message": "Failed to move uploaded file."}, "id" : "id"}');
      }
    } else {
      // Open temp file
      $out = fopen($dir . '/' . $file, $chunk == 0 ? "wb" : "ab");
      if ($out) {
        // Read binary input stream and append it to temp file
        $in = fopen("php://input", "rb");

        if ($in) {
          while ($buff = fread($in, 4096)) {
            fwrite($out, $buff);
          }
        } else {
          die('{"jsonrpc" : "2.0", "error" : {"code": 101, "message": "Failed to open input stream."}, "id" : "id"}');
        }

        fclose($in);
        fclose($out);
      } else {
        die('{"jsonrpc" : "2.0", "error" : {"code": 102, "message": "Failed to open output stream."}, "id" : "id"}');
      }
    }

    chmod($dir.DS.$file, 0664);
    $files[$file] = $this->getFileInfo($dir, $type, $file, $fileName);

    $this->addFilesInfo($inputDir, $type, $files);

    return true;
  }


  function sortFiles(&$files) {
    // function for sorting files by date/filename
    function cmp_date_name($a, $b) {
      $r1 = strcmp($a['date'], $b['date']) * (-1);

      return ($r1 == 0) ? strcmp($a['filename'], $b['filename']) : $r1;
    }

    // sort array
    uasort($files, 'cmp_date_name');
  }


  function RenameFile($type, $dir, $filename, $newname) {
    $dir = $this->AccessDir($dir, $type);
    if (!$dir) {
      return false;
    }

    $filename = trim($filename);

    if (empty($filename)) {
      return 'error';
    }

    if (!is_dir($dir . '/.thumbs')) {
      return 'error';
    }


    $dbfile = $dir . '/.thumbs/.db';
    if (is_file($dbfile)) {
      $dbfilehandle = fopen($dbfile, "r");
      $dblength = filesize($dbfile);
      if ($dblength > 0) {
        $dbdata = fread($dbfilehandle, $dblength);
      }
      fclose($dbfilehandle);
    } else {
      return 'error';
    }

    $files = unserialize($dbdata);

    foreach ($files as $file => $fdata) {
      if ($file == $filename) {
        $files[$file]['name'] = $newname;
        break;
      }
    }

    $dbfilehandle = fopen($dbfile, "w");
    fwrite($dbfilehandle, serialize($files));
    fclose($dbfilehandle);

    return 'ok';
  }

  function bytes_to_str($bytes) {
    $d = '';
    if ($bytes >= 1048576) {
      $num = $bytes / 1048576;
      $d = 'Mb';
    } elseif ($bytes >= 1024) {
      $num = $bytes / 1024;
      $d = 'kb';
    } else {
      $num = $bytes;
      $d = 'b';
    }

    return number_format($num, 2, ',', ' ') . $d;
  }


  function ShowDir($inputDir, $type, $page) {

    $dir = $this->CallDir($inputDir, $type, $page);

    if (!is_array($dir)) {
      $dir = $this->CallDir($inputDir, $type, 1);
    }
    //    if (!is_array($dir)) {
    //      $dir = $this->CallDir('', $type, 1);
    //    }

    if (!is_array($dir)) {
      return '';
    }

    $ret = '';
    foreach ($dir as $v) {
      $thumb = $this->GetThumb($v['path'], $v['md5'], $v['filename'], 2, 100, 100);
      if ((WIDTH_TO_LINK > 0 && $v['width'] > WIDTH_TO_LINK) || (HEIGHT_TO_LINK > 0 && $v['height'] > HEIGHT_TO_LINK)
      ) {
        $middle_thumb = $this->GetThumb($v['path'], $v['md5'], $v['filename'], 0, WIDTH_TO_LINK, HEIGHT_TO_LINK);
        list($middle_width, $middle_height) = getimagesize($middle_thumb);
        $middle_thumb_attr
          = 'fmiddle="' . $middle_thumb . '" fmiddlewidth="' . $middle_width . '" fmiddleheight="' . $middle_height
          . '" fclass="' . CLASS_LINK . '" frel="' . REL_LINK . '"';
      } else {
        $middle_thumb = '';
        $middle_thumb_attr = '';
      }

      $img_params = '';
      $div_params = '';

      if ($type == 'files' || in_array($v['ext'], $this->ALLOWED_FILES)) {
        $img_params = '';
        //        $div_params = 'style="width: 100px; height: 100px; padding-top: 16px;"';
        $div_params = 'fileIcon';
      }

      $filename = $v['name'];

      if (mb_strlen($filename) > 30) {
        $filename = mb_substr($filename, 0, 25, 'UTF-8') . '...';
      }

      $ret .= '<div class="imageBlock0" filename="' . $v['filename'] . '" fname="' . $v['name'] . '" type="' . $type
        . '" ext="' . $v['ext'] . '" path="' . $v['path'] . '" linkto="' . $v['link'] . '" fsize="' . $v['size']
        . '" fsizetext="' . $this->bytes_to_str($v['size']) . '" date="' . date('d.m.Y H:i', $v['date']) . '" fwidth="'
        . $v['width'] . '" fheight="' . $v['height'] . '" md5="' . $v['md5'] . '" ' . $middle_thumb_attr
        . '><div class="imageBlock1"  title="' . $v['name'] . '"><div class="imageImage ' . $div_params . '"><img src="'
        . $thumb . '" ' . $img_params . ' alt="' . $v['name'] . '" /></div><div class="imageName">' . $filename
        . '</div></div></div>';
    }

    return $ret;
  }

  function showPages($path, $type, $activePage) {
    $result = '';
    if ($this->total_pages > 1) {
      $result .= '<ul>';
      for ($i = 1; $i <= $this->total_pages; $i++) {
        $class = '';
        if ($i == $activePage) {
          $class = ' class="active"';
        }
        $result
          .= '<li' . $class . '><a href="#" pathtype="' . $type . '" path="' . $path . '" data-page="' . $i . '">' . $i
          . '</a></li> ';
      }
      $result .= '</ul>';
    }

    return $result;
  }


  function GetThumb($dir, $md5, $filename, $mode, $width = 100, $height = 100) {
    $path = realpath(DIR_ROOT . DS . $dir);
    $ext = strtolower(end(explode('.', $filename))); // filename extention
    $thumbFilename = DS . '.thumbs' . DS . $md5 . '_' . $width . '_' . $height . '_' . $mode . '.' . $ext;

    // if thumb already exists
    if (is_file($path . $thumbFilename)) {
      return $this->http_root . $dir . $thumbFilename;
    } else {
      // if not an image or we are in 'files' folder
      if (in_array($ext, $this->ALLOWED_IMAGES) && strpos($dir, DIR_IMAGES) === 0) {
        //if it's an image, create thumb
        try {
          // if no width or height specified
          $width = $width ? $width : null;
          $height = $height ? $height : null;

          $thumb = WideImage::load($path . '/' . $filename)->resizeDown($width, $height);

          if ($mode == 2
          ) { // if generating small thumb for imageManager inner use - make it exactly 100x100 with white background
            $thumb = $thumb->resizeCanvas($width, $height, 'center', 'center', 0x00FFFFFF);
          }
          $thumb-> //				roundCorners(20,0x00FFFFFF,4)->
            saveToFile($path . $thumbFilename);
          // clear some memory
          unset($thumb);

          return $this->http_root . $dir . $thumbFilename;
        } catch (WideImage_InvalidImageSourceException $e) {
          $e->getMessage();
        } catch (WideImage_Operation_InvalidResizeDimensionException $e) {

        }
      }
    }


    // get path to img/fileicons folder
    $server_url = rtrim(dirname(__FILE__), '/') . '/../../';
    $server_url = realpath($server_url);
    $server_url = rtrim($server_url, '/') . '/img/fileicons/';
    $url = $this->http_root . substr($server_url, strlen(DIR_ROOT));


    // show the file-type icon
    if (!empty($ext) && file_exists($server_url . $ext . '.png')) {
      return $url . $ext . '.png';
    } else {
      return $url . 'none.png';
    }


  }


  function DelFile($pathtype, $path, $md5, $filename) {
    $path = $this->AccessDir($path, $pathtype);
    if (!$path) {
      return false;
    }

    if (is_dir($path . '/.thumbs')) {
      if ($pathtype == 'images') {
        $handle = opendir($path . '/.thumbs');
        if ($handle) {
          while (false !== ($file = readdir($handle))) {
            if ($file != "." && $file != "..") {
              if (substr($file, 0, 32) == $md5) {
                unlink($path . '/.thumbs/' . $file);
              }
            }
          }
        }
      }

      $dbfile = $path . '/.thumbs/.db';
      if (is_file($dbfile)) {
        $dbfilehandle = fopen($dbfile, "r");
        $dblength = filesize($dbfile);
        if ($dblength > 0) {
          $dbdata = fread($dbfilehandle, $dblength);
        }
        fclose($dbfilehandle);
        $dbfilehandle = fopen($dbfile, "w");
      } else {
        $dbfilehandle = fopen($dbfile, "w");
      }


      if (isset($dbdata)) {
        $files = unserialize($dbdata);
      } else {
        $files = array();
      }

      unset($files[$filename]);

      fwrite($dbfilehandle, serialize($files));
      fclose($dbfilehandle);
      @chmod($dbfile, 0664);
    }

    if (is_file($path . '/' . $filename)) {
      if (unlink($path . '/' . $filename)) {
        return true;
      }
    } else {
      return 'error';
    }

    return 'error';
  }

  function DelFolder($pathtype, $path) {
    $realPath = $this->AccessDir($path, $pathtype);
    if (!$realPath) {
      return false;
    }

    $result = array();
    $folder = ($pathtype == 'images') ? DIR_IMAGES : DIR_FILES;
    if (realpath($realPath . '/') == realpath(DIR_ROOT . $folder . '/')) {
      $result['error'] = 'rootFolder';
    } else {

      $files = array();

      $handle = opendir($realPath);
      if ($handle) {
        while (false !== ($file = readdir($handle))) {
          if ($file != "." && $file != ".." && trim($file) != "" && $file != ".thumbs") {
            if (is_dir($realPath . '/' . $file)) {
              $result['error'] = 'hasChildFolders';
              continue;
            } else {
              $files[] = $file;
            }
          }
        }
      }
      closedir($handle);

      if (empty($result['error'])) {
        $handle = opendir($realPath . '/.thumbs');
        if ($handle) {
          while (false !== ($file = readdir($handle))) {
            if ($file != "." && $file != "..") {
              if (is_file($realPath . '/.thumbs/' . $file)) {
                unlink($realPath . '/.thumbs/' . $file);
              }
            }
          }
          closedir($handle);
          rmdir($realPath . '/.thumbs');
        }

        foreach ($files as $f) {
          if (is_file($realPath . '/' . $f)) {
            unlink($realPath . '/' . $f);
          }
        }

        if (!rmdir($realPath)) {
          $result['error'] = 'cantDelete';
        }
      }
    }


    return $result;
  }

  function translit($string) {
    $cyr = array("А", "Б", "В", "Г", "Д", "Е", "Ё", "Ж", "З", "И", "Й", "К", "Л", "М", "Н", "О", "П", "Р", "С", "Т",
                 "У", "Ф", "Х", "Ц", "Ч", "Ш", "Щ", "Ъ", "Ы", "Ь", "Э", "Ю", "Я", "а", "б", "в", "г", "д", "е", "ё",
                 "ж", "з", "и", "й", "к", "л", "м", "н", "о", "п", "р", "с", "т", "у", "ф", "х", "ц", "ч", "ш", "щ",
                 "ъ", "ы", "ь", "э", "ю", "я");
    $lat = array("A", "B", "V", "G", "D", "E", "YO", "ZH", "Z", "I", "Y", "K", "L", "M", "N", "O", "P", "R", "S", "T",
                 "U", "F", "H", "TS", "CH", "SH", "SHCH", "", "YI", "", "E", "YU", "YA", "a", "b", "v", "g", "d", "e",
                 "yo", "zh", "z", "i", "y", "k", "l", "m", "n", "o", "p", "r", "s", "t", "u", "f", "h", "ts", "ch",
                 "sh", "shch", "", "yi", "", "e", "yu", "ya");
    /*    for ($i = 0; $i < count($cyr); $i++) {
      $c_cyr = $cyr[$i];
      $c_lat = $lat[$i];
    }*/
    $string = str_replace($cyr, $lat, $string);

    $string = preg_replace("/([qwrtpsdfghklzxcvbnmQWRTPSDFGHKLZXCVBNM]+)[jJ]e/", "\${1}e", $string);
    $string = preg_replace("/([qwrtpsdfghklzxcvbnmQWRTPSDFGHKLZXCVBNM]+)[jJ]/", "\${1}'", $string);
    $string = preg_replace("/([eyuioaEYUIOA]+)[Kk]h/", "\${1}h", $string);
    $string = preg_replace("/^kh/", "h", $string);
    $string = preg_replace("/^Kh/", "H", $string);

    return $string;
  }

  function encodestring($string) {
    $string = str_replace(array(" ", '"', "&", "<", ">"), ' ', $string);
    $string = preg_replace("/[_\s,?!\[\](){}]+/", "_", $string);
    $string = preg_replace("/-{2,}/", "-", $string);
    $string = preg_replace("/\.{2,}/", ".", $string);
    $string = preg_replace("/_-+_/", '-', $string);
    $string = preg_replace("/[_\-]+$/", '', $string);
    $string = $this->translit($string);
    $string = preg_replace("/j{2,}/", "j", $string);
    $string = preg_replace("/[^0-9A-Za-z_\-\.]+/", "", $string);

    return $string;
  }

}

$letsGo = new TinyImageManager();

?>