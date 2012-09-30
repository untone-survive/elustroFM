elustroFM
=========

elustro File Manager - file and image manager for TinyMCE

Works both as standalone plugin and as filemanager for image/media/link windows.

Based on Image Manager by Antonov Andrey http://dustweb.ru/projects/tinymce_images/

elustroFm now only works with PHP.

##Installation
1. Copy 'elustrofm' directory to `{path_to_TinyMCE}`/plugins
3. Make changes in your elustrofm/connector/php/config.php and set the upload folders. All other options are self-explanatory.

###As plugin
In your tinyMCE.init function add 'elustrofm' to plugins line and add 'elustrofm' button to one of your buttons bar like this:
```javascript
tinyMCE.init({
  ...
  plugins : "autolink,lists,advimage,inlinepopups,...,elustrofm",
  ...
  theme_advanced_buttons1 : "newdocument,|,bold,italic,underline,strikethrough,...,elustrofm",
  ...
});
```

###As file manager for image/media/link popups
In your tinyMCE.init function add line:
`file_browser_callback : "elustroFileManager"`
And then add this function right after `tinyMce.init()` function:
```javascript
function elustroFileManager (field_name, url, type, win) {
  var ed = tinyMCE.activeEditor,
  cmsURL = "{path_to_tinyMCE}/plugins/elustrofm/index.html?integration=fm&lang="+ed.settings.language+"&filetype="+type;
  ed.windowManager.open({
    file : cmsURL,
    title : 'elustroFM',
    width : 700,
    height : 550,
    resizable : "yes",
    scrollbars : "no",
    inline : "yes",
    close_previous : "no",
    popup_css : false
  }, {
    window : win,
    input : field_name
  });
  return false;
  }
```

Just change `{path_to_tinyMCE}` to an absolute path to TinyMce directory on your site and you're ready to go.

