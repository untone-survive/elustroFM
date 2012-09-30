<?php
$tbpath = pathinfo($_SERVER['SCRIPT_NAME']);
$tbmain = $tbpath['dirname'] . '/index.html';
header("Content-type: application/x-javascript");
?>
function elustroFm_standalone(elementId) {
  tburl = "<?php echo $tbmain; ?>" + "?integration=standalone";
  if (elementId !== undefined) {
    tburl += "&elementId="+elementId;
  }
  newwindow=window.open(tburl,'elustrofm_window','height=550,width=700,scrollbars=no,resizable=yes');
  if (window.focus) {
    newwindow.focus()
  }
  return false;
}
