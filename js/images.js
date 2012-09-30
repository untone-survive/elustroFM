(function () {
  "use strict";
  var standAlone = false, ImagesDialog = {}, popup;

  var tinyMCEPopup = window.tinyMCEPopup;

  if (typeof(tinyMCEPopup) !== 'undefined') {
    popup = tinyMCEPopup;
    ImagesDialog = {
      init: function (ed) {
        tinyMCEPopup.resizeToInnerSize();
      }};

    if (integration === 'fm') {
      // as filemanager for image/link/media
      var filetype = getURLParam('filetype');
      ImagesDialog.insert = function (text) {

        var win = tinyMCEPopup.getWindowArg("window");
        // insert information now
        win.document.getElementById(tinyMCEPopup.getWindowArg("input")).value = text.src;

        var alt = win.document.getElementById('alt'), fileTitle = win.document.getElementById('title');

        (alt && fileTitle && (alt.value = text.name)) || (fileTitle && (fileTitle.value = text.name));

        // for image browsers: update image dimensions
        if (typeof(win.ImageDialog) !== "undefined" && document.URL.indexOf('type=image') != -1) {
          if (win.ImageDialog.getImageData) {
            win.ImageDialog.getImageData();
          }
          if (win.ImageDialog.showPreviewImage) {
            win.ImageDialog.showPreviewImage(text.src);
          }
        }

        tinyMCEPopup.close();
      };
      standAlone = true;
    } else {

      // as plugin
      ImagesDialog.insert = function (text) {
        var ed = tinyMCEPopup.editor;
        tinyMCEPopup.execCommand('mceInsertContent', false, text);
        tinyMCEPopup.close();
      };
      tinyMCEPopup.onInit.add(ImagesDialog.init, ImagesDialog);
    }
    // if no tinyMCEPopup
  } else {
    popup = window;
    ImagesDialog = {
      insert: function (file) {

        var el = getURLParam('elementId');

        if (popup.opener && el) {
          var op = popup.opener.document.getElementById(el), preview = popup.opener.document.getElementById(el
              + '_img');
          if (op) {
            op.value = file.src;
          }
          if (preview) {
            preview.src = file.src;
          }
        }
        popup.close();
      }
    };

    standAlone = true;
  }

  //ЗАГРУЗКА
  var $loader = $('#loader'), $addr = $('#addr'), $tree = $('#tree'), $paginator = $('#paginator'), $filesDiv = $('#files'), defaultPath = true, $newFolderInput = $('#newFolderBlock input'), ajaxPath = '', folderLoadFlag = false, ctrlState = false, $win = $(window), $filesForm = $('#filesForm'), $LANG = {}, $UPLOAD_DATA = {};

  function openFolder(type, path, callback, forceTreeReload, page) {
    if (!page) {
      page = 1;
    }
    var requestData = {action: 'openFolder'};
    if (defaultPath) {
      requestData['default'] = 1;
      forceTreeReload = true;
      defaultPath = false;
    } else {
      $.extend(requestData, {'type': type, 'path': path, 'page': page});
    }

    if (!callback) {
      callback = function () {
      };
    }

    $.ajax({
      type: "POST",
      url: ajaxPath,
      data: requestData,
      dataType: 'json',
      success: function (data) {
        $loader.hide();
        $addr.html(data.path);
        if (forceTreeReload) {
          $tree.html(data.tree);
        }
        $paginator.html(data.pages);
        $filesDiv.html(data.files);
        showFootInfo();
        callback();
      }
    });
    $loader.hide();
  }

  function init(connector) {

    if (!connector) {
      connector = getURLParam('connector');
    }

    if (!connector) {
      connector = 'php';
    }

    $win.on('resize.imagemanager',function (e) {
      e.preventDefault();
      _setFileWindowHeight();
    }).resize();
    ajaxPath = 'connector/' + connector + '/';
    $filesForm.attr('action', ajaxPath);

    $loader.show();
    $.ajax({
      type: "POST",
      url: ajaxPath,
      data: {'action': 'setupData', 'lang': getURLParam('lang')},
      dataType: 'json',
      success: function (data) {
        $LANG = eval('('+data.lang+')');
        if ($LANG['lang'] && $LANG['lang'] !== 'en') {
          $('body').append('<script type="text/javascript" src="langs/'+$LANG['lang']+'_plupload.js"><\/script>');
        }
        $("[data-translate]").each(function () {
          var self = $(this), translation = _t(self.attr('data-translate'));

          if (!translation.position) {
            self.text(translation);
          } else {
            self.attr(translation.position, translation.word);
          }
        });

        $UPLOAD_DATA = data.upload;
        openFolder();
      }
    });

  }

  function _setFileWindowHeight() {
    return $('#mainField').height($win.height() - ($('#header').outerHeight(true) + $('#foot').outerHeight(true)));
  }

  function _t(untranslated) {
    var translation = untranslated.split('||');
    return (translation[1] && {'word': $LANG[translation[0]] || translation[0], 'position': translation[1]})
               || $LANG[translation[0]] || translation[0];
  }

  init();

  //Адресная строка
  $addr.on('mouseover', '.addrItem div, .addrItem img',function () {
    $(this).parent().animate({backgroundColor: '#b1d3fa'}, 100, 'swing');
  }).on('mouseout', '.addrItem div, .addrItem img',function () {
        var self = $(this);
        self.parent().animate({backgroundColor: '#e4eaf1'}, 200, 'linear', function () {
          self.css({'background-color': 'transparent'});
        });
      }).on('mousedown', '.addrItem div, .addrItem img',function () {
        $(this).parent().css({'background-color': '#679ad3'});
      }).on('mouseup', '.addrItem div, .addrItem img', function () {
        var parent = $(this).parent();
        parent.css({'background-color': '#b1d3fa'});
        openFolder(parent.attr('pathtype'), parent.attr('path'), '', true);

      });

  $paginator.on('click', 'a', function (e) {
    var self = $(this);
    e.preventDefault();
    openFolder(self.attr('pathtype'), self.attr('path'), '', false, self.attr('data-page'));
  });

  //Кнопка "В начало"
  $('#toBeginBtn').mouseover(function () {
    $(this).children(0).attr('src', 'img/backActive.gif');
  }).mouseout(function () {
        $(this).children(0).attr('src', 'img/backEnabled.gif');
      });

  //Меню
  $tree.on('mouseenter', '.folder',function () {
    var self = $(this);
    if (!self.hasClass('folderAct')) {
      self.addClass('folderHover');
    } else {
      self.addClass('folderActHover');
    }
  }).on('mouseleave', '.folder',function () {
        var self = $(this);
        if (!self.hasClass('folderAct')) {
          self.removeClass('folderHover');
        } else {
          self.removeClass('folderActHover');
        }
      }).on('click', '.folder', function (e) {

        e.preventDefault();
        //Запрет на переключение
        if (folderLoadFlag) {
          return false;
        }
        folderLoadFlag = true;

        $loader.show();
        $('.folderAct').removeClass('folderAct');
        $(this).removeClass('folderHover');
        $(this).addClass('folderAct');

        openFolder($(this).attr('pathtype'), $(this).attr('path'), function () {
          folderLoadFlag = false;
        });
      });

  $tree.on('dblclick', '.folderImages,.folderFiles',function (e) {
    e.preventDefault();
    $(this).next().slideToggle('normal');
  }).on('dblclick', '.folderOpened,.folderS', function (e) {
        e.preventDefault();
        var self = $(this), nextFolder = self.next();
        if (!nextFolder.hasClass('folderOpenSection')) {
          return false;
        }
        if (self.hasClass('folderS')) {
          self.removeClass('folderS').addClass('folderOpened');
        } else {
          self.removeClass('folderOpened').addClass('folderS');
        }
        nextFolder.slideToggle('normal');
      });

  //ДЕЙСТВИЯ МЕНЮ
  //Открыть загрузчик файлов
  $('#menuUploadFiles').click(function () {
    var path = getCurrentPath(), filterObj = {}, resizeObj = {};
    if (path.type === 'images') {
      filterObj = {title: _t('Images'), extensions: $UPLOAD_DATA.images.allowed.join(',')};
      resizeObj = {width: $UPLOAD_DATA.images.width, height: $UPLOAD_DATA.images.height, quality: 90};
    } else if (path.type === 'files') {
      filterObj = {title: _t('All files'), extensions:$UPLOAD_DATA.files.allowed.join(',')};
      resizeObj = {width: 0, height: 0, quality: 0};
    }

    $('#normalPathVal').val(path.path);
    $('#normalPathtypeVal').val(path.type);

    $('#upload').show();

    $("#uploader").pluploadQueue({
      runtimes: 'html5,html4',
      multipart_params: {action: 'uploadfile', pathtype: path.type, path: path.path},
      headers: {action: 'uploadfile', pathtype: path.type, path: path.path},
      max_file_size: '50mb',
      url: ajaxPath,
      resize: resizeObj,
      filters: [filterObj]
    });
  });

  // Client side form validation
  $filesForm.submit(function (e) {
    var uploader = $('#uploader').pluploadQueue();

    // Validate number of uploaded files
    if (uploader.total.uploaded == 0) {
      // Files in queue upload them first
      if (uploader.files.length > 0) {
        // When all files are uploaded submit form
        uploader.bind('UploadProgress', function () {
          if (uploader.total.uploaded === uploader.files.length) {
            $('form').submit();
          }
        });

        uploader.start();
      } else {
        alert(_t('Please select at least one file for uploading.'));
      }

      e.preventDefault();
    }
  });

  //Создать папку
  var canCancelFolder = true;
  $('#menuCreateFolder').click(function () {
    $(this).hide();
    $('#menuCancelFolder,#menuSaveFolder').show();

    $('.folderAct').after('<div id="newFolderBlock"><input type="text" name="newfolder" id="newFolder" /></div>');
    $newFolderInput = $('#newFolderBlock input');
    $('#newFolderBlock').slideDown('fast', function () {
      $newFolderInput.focus().blur(cancelNewFolder).keypress(function (e) {
        if (e.which === 13) {
          saveNewFolder();
        } else if (e.which === 27) {
          cancelNewFolder();
        } else if ((e.which >= 97 && e.which <= 110) || (e.which >= 65 && e.which <= 90) || (e.which >= 48 && e.which
            <= 57) || e.which === 8 || e.which === 95 || e.which === 45 || e.keyCode === 37 /*|| e.keyCode === 39*/
            || e.keyCode === 16) {
          //Значит все верно: a-Z0-9-_ и управление вводом
          return true;
        } else {
          e.preventDefault();
          return false;
        }

      });
    });

  });
  //Отменить создание папки
  function cancelNewFolder() {
    if (!canCancelFolder) {
      canCancelFolder = true;
      return false;
    }
    $('#menuCancelFolder,#menuSaveFolder').hide();
    $('#menuCreateFolder').show();

    $('#newFolderBlock').slideUp('fast', function () {
      $(this).remove();
    });
  }

  $('#menuCancelFolder').click(cancelNewFolder);

  //Подтвердить создание папки
  function saveNewFolder() {
    canCancelFolder = false;

    if (!$newFolderInput.val()) {
      alert(_t('Enter new folder name'));
      $newFolderInput.focus();
      return false;
    }

    $loader.show();
    $('#menuCancelFolder,#menuSaveFolder').hide();
    $('#menuCreateFolder').show();
    //Запрос на создание папки
    var activeFolder = $('.folderAct'), type = activeFolder.attr('pathtype'), path = activeFolder.attr('path'), newFolderName = $newFolderInput.val(), path_will = path
                                                                                                                                                                       + '/'
        + newFolderName;
    $.ajax({
      type: "POST",
      url: ajaxPath,
      dataType: 'json',
      data: {'action': 'newfolder', 'type': type, 'path': path, 'name': newFolderName},
      success: function (data) {
        $loader.hide();
        if (data.error) {
          var errorStr;
          switch (data.error) {
            case 'folderExists':
                errorStr = 'Folder with this name already exists';
                break;
            case 'createFolderError':
                errorStr = 'Error creating folder';
                break;
            case 'wrongFolderName':
                errorStr = 'Folder name can only contain latin letters, digits and underscore';
                break;
            case 'folderAccessDenied':
                errorStr = 'Folder access denied';
                break;
          }
          alert(_t(errorStr));
          $newFolderInput.focus();
        } else {
          canCancelFolder = true;
          openFolder(type, path_will, cancelNewFolder(), true);
        }
      }
    });
  }

  $('#menuSaveFolder').click(saveNewFolder).hover(function () {
    canCancelFolder = false;
  }, function () {
    canCancelFolder = true;
  });

  //Удалить папку
  $('#menuDelFolder').click(function () {
    var path = getCurrentPath();
    if (confirm(_t('Delete folder') + ' ' + path.path + '?')) {
      $loader.show();
      $.ajax({
        type: "POST",
        url: ajaxPath,
        dataType: 'json',
        data: {'action': 'delfolder', 'type': path.type, 'path': path.path},
        success: function (data) {
          if (data.error) {
            $loader.hide();
            var errorText = '';
            switch (data.error) {
              case 'rootFolder':
                errorText = 'Root folder cannot be deleted!';
                break;
              case 'hasChildFolders':
                errorText = 'Folder cannot be delete while it has subfolders';
                break;
              case 'cantDelete':
                errorText = 'Error deleting folder';
                break;
            }
            alert(_t(errorText));
          } else {
            openFolder(path.type, getParentDir(path.path), '', true);
          }
        }
      });
    }
  });

  //Удалить файлы
  $('#menuDelFiles').click(function () {
    var files = $('.imageBlockAct'), path = getCurrentPath();

    if (!files.length) {
      alert(_t("Select files for deleting.\n\nYou can select several files by holding CTRL key while clicking them."));
    } else {
      var confirmText, requestData = {'action': 'delfile', 'type': path.type, 'path': path.path}, filesData = [];
      if (files.length === 1) {
        confirmText = _t('Delete file') + ' \"' + files.attr('filename') + '\"?';
        filesData.push({"md5": files.attr('md5'), 'filename': files.attr('filename')});
      } else {
        confirmText = _t('Files selected for deleting: %d\n\nDo you want to procced?').replace(/%d/, files.length);

        $.each(files, function (i, item) {
          var file = $(this);
          filesData.push({"md5": file.attr('md5'), 'filename': file.attr('filename')});
        });
      }
      if (confirm(confirmText)) {
        $loader.show();
        $.extend(requestData, {'files': filesData});
        $.ajax({
          type: "POST",
          url: ajaxPath,
          dataType: 'json',
          data: requestData,
          success: function (data) {
            $loader.hide();
            var errors = [];
            for (var obj in data) {
              if (data[obj] === 'error') {
                errors.push(obj);
              }
            }
            if (errors.length) {
              alert(_t('There were errors removing this files: ') + errors.join(', ') + '.');
            }
            openFolder(path.type, path.path, '', true, path.page);
          }
        });
      }
    }
  });

  //Файлы
  $filesDiv.on('mouseover', '.imageBlock0',function () {
    if (!$(this).hasClass('imageBlockAct')) {
      $(this).addClass('imageBlockHover');
    } else {
      $(this).addClass('imageBlockActHover');
    }
  }).on('mouseout', '.imageBlock0',function () {
        if (!$(this).hasClass('imageBlockAct')) {
          $(this).removeClass('imageBlockHover');
        } else {
          $(this).removeClass('imageBlockActHover');
        }
      }).on('dblclick', '.imageBlock0',function () {
        var e = $(this);
        if (e.attr('type') === 'files') {
          var filesize = e.attr('fsizetext');
          var text = '<a href="' + e.attr('linkto') + '" ' + addAttr + ' title="' + e.attr('fname') + '">';
          text += e.attr('fname');
          text += '</a> ' + ' (' + filesize + ') ';
        } else {
          if (e.attr('fmiddle')) {
            var addAttr = (e.attr('fclass') != '' ? 'class="' + e.attr('fclass') + '"' : '') + ' ' + (e.attr('frel')
                != '' ? 'rel="' + e.attr('frel') + '"' : '');
            var text = '<a href="' + e.attr('linkto') + '" ' + addAttr + ' title="' + e.attr('fname') + '">';
            text += '<img src="' + e.attr('fmiddle') + '" width="' + e.attr('fmiddlewidth') + '" height="'
                        + e.attr('fmiddleheight') + '" alt="' + e.attr('fname') + '" />';
            text += '</a> ';
          } else {
            var text = '<img src="' + e.attr('linkto') + '" width="' + e.attr('fwidth') + '" height="'
                           + e.attr('fheight') + '" alt="' + e.attr('fname') + '" /> ';
          }
        }

        if (standAlone) {
          text = {'src': e.attr('linkto'), 'name': e.attr('fname')};
        }

        ImagesDialog.insert(text);
      }).on('click', '.imageBlock0', function () {
        if (ctrlState) {
          if ($(this).hasClass('imageBlockActHover') || $(this).hasClass('imageBlockAct')) {
            $(this).removeClass('imageBlockAct');
            $(this).removeClass('imageBlockActHover');
          } else {
            $(this).removeClass('imageBlockHover');
            $(this).addClass('imageBlockAct');
          }
        } else {
          $('.imageBlockAct').removeClass('imageBlockAct');
          $(this).removeClass('imageBlockHover');
          $(this).addClass('imageBlockAct');
        }

        showFootInfo();
      });

  $('#insertImage').click(function () {
    $('.imageBlockAct').trigger('dblclick');
  });

  function selectAllFiles() {
    $('.imageBlock0', $filesDiv).addClass('imageBlockAct');
    showFootInfo();
  }

  $win.keydown(function (event) {
    if (event.keyCode === 17) {
      ctrlState = true;
    }
    if (ctrlState && event.keyCode === 65) {
      event.preventDefault();
      selectAllFiles();
    }
  }).keyup(function (event) {
        if (event.keyCode === 17) {
          ctrlState = false;
        }
      }).blur(function (event) {
        ctrlState = false;
      });

  //НИЖНЯЯ ПАНЕЛЬ
  //Показать текущую информацию
  function showFootInfo() {
    $('#fileNameEdit').show();
    $('#fileNameSave').hide();
    var file = $('.imageBlockAct');
    if (file.length > 1) {
      $('#footTableName, #footDateLabel, #footLinkLabel, #footDimLabel, #footDate, #footLink, #footDim').css('visibility',
          'hidden');
      $('#footExt').text(_t('Files selected: ') + file.length);
      var tmpSizeCount = 0;
      $.each(file, function (i, item) {
        tmpSizeCount += parseInt($(this).attr('fsize'));
      });
      $('#footSize').text(intToMb(tmpSizeCount));
    } else if (!file.length) {
      $('#footTableName, #footDateLabel, #footLinkLabel, #footDimLabel, #footDate, #footLink, #footDim').css('visibility',
          'hidden');
      var allFiles = $('.imageBlock0');

      $('#footExt').text(_t('Total files: ') + allFiles.length);
      var tmpSizeCount = 0;
      $.each(allFiles, function (i, item) {
        tmpSizeCount += parseInt($(this).attr('fsize'));
      });
      $('#footSize').text(intToMb(tmpSizeCount));
    } else {

      var dirInfo = getCurrentPath(), imgDimensions;

      $('#fileName').text(file.attr('fname'));
      $('#footExt').text(file.attr('ext'));
      $('#footDate').text(file.attr('date'));
      $('#footLink a').text(file.attr('fname').substr(0, 18)).attr('href', file.attr('linkto'));
      $('#footSize').text(intToMb(file.attr('fsize')));
      if (dirInfo.type == 'images' && file.attr('fwidth') !== 'N/A') {
        imgDimensions = file.attr('fwidth') + 'x' + file.attr('fheight');
      } else {
        imgDimensions = _t('N/A');
      }
      $('#footDim').text(imgDimensions);

      $('#footTableName, #footDateLabel, #footLinkLabel, #footDimLabel, #footDate, #footLink, #footDim').css('visibility',
          'visible');
    }
  }

  //Очистить поля информации

  //Байты в Мб и Кб
  function intToMb(i) {
    if (i < 1024) {
      return i + ' ' + _t('B');
    } else if (i < 1048576) {
      var v = i / 1024;
      v = parseInt(v * 10) / 10;
      return v + ' ' + _t('KB');
    } else {
      var v = i / 1048576;
      v = parseInt(v * 10) / 10;
      return v + ' ' + _t('MB');
    }
  }

  //Редактировать имя
  $('#fileNameEdit').click(function () {
    $('#fileName').html('<input type="text" name="fileName" id="fileNameValue" value="' + $('#fileName').html()
        + '" />');
    $('#fileNameValue').focus().keyup(function (e) {
      if (e.keyCode === 13) {
        // if enter is pressed, save new filename
        $('#fileNameSave').trigger('click');
      }
    });
    $('#fileNameEdit').hide();
    $('#fileNameSave').show();
  });
  //Сохранить имя
  $('#fileNameSave').click(function (e) {
    e.preventDefault();
    $loader.show();

    //Запрос
    var path = getCurrentPath(), newname = $('#fileNameValue').val();
    $.ajax({
      type: "POST",
      url: "connector/php/",
      data: 'action=renamefile&path=' + path.path + '&pathtype=' + path.type + '&filename='
                + $('.imageBlockAct').attr('filename') + '&newname=' + newname,
      success: function (data) {
        $loader.hide();
        if (data != 'error') {
          $('#fileName').html(newname);
          $('.imageBlockAct').attr('fname', newname);
          $('.imageBlockAct .imageName').text(newname);
        } else {
          alert(data);
        }
      }
    });

    $('#fileNameSave').hide();
    $('#fileNameEdit').show();
  });

  //Закрыть загрузчик
  $('#uploadClose').click(function () {
    $loader.show();
    var path = getCurrentPath();
    openFolder(path.type, path.path, '', true);

    $('#upload').hide();
    $('#divStatus').html('');
  });

  //Получить текущую директорию и ее тип
  function getCurrentPath() {
    var type = $('.addrItem:first').attr('pathtype'), path = $('.addrItemEnd').attr('path'), page = $('li.active a',
        $paginator).attr('data-page');

    if (!path) {
      path = '/';
    }

    return {'type': type, 'path': path, 'page': page};
  }

  function getParentDir(path) {
    var slashPos = path.lastIndexOf('/');

    if (slashPos <= 0) {
      slashPos = path.length;
    }
    return path.substring(0, slashPos)
  }

})(jQuery);