/**
 *
 * @author Syuvaev Anton http://h8every1.ru
 * @copyright Copyright © 2012, Syuvaev Anton, All rights reserved.
 */

(function () {
  // Load plugin specific language pack
  tinymce.PluginManager.requireLangPack('elustrofm');
  tinymce.create('tinymce.plugins.elustroFileManager', {
    init: function (ed, url) {
      // Register commands
      ed.addCommand('mceElustroFM', function () {
        ed.windowManager.open({
          file: url + '/index.html?integration=plugin&lang=' + ed.settings.language,
          width: 700,
          height: 550,
          inline: true,
          popup_css: false,
          title: 'elustroFM'
        }, {
          plugin_url: url
        });
      });

      // Register buttons
      ed.addButton('elustrofm', {
        title: 'elustrofm.desc',
        cmd: 'mceElustroFM',
        image: url + '/img/icon.gif'
      });
    },

    getInfo: function () {
      return {
        longname: 'elustroFM: Images & File Manager',
        author: 'Anton Syuvaev',
        authorurl: 'http://h8every1.ru',
        infourl: 'https://github.com/h8every1/elustroFM',
        version: '1.0'
      };
    }
  });

  // Register plugin
  tinymce.PluginManager.add('elustrofm', tinymce.plugins.elustroFileManager);
})();