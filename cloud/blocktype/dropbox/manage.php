<?php
/**
 *
 * @package    mahara
 * @subpackage blocktype-dropbox
 * @author     Gregor Anzelj
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2012-2015 Gregor Anzelj, gregor.anzelj@gmail.com
 *
 */

define('INTERNAL', 1);
define('MENUITEM', 'content/clouds');
define('SECTION_PLUGINTYPE', 'artefact');
define('SECTION_PLUGINNAME', 'cloud');
define('SECTION_PAGE', 'index');

require(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/init.php');
require_once(get_config('libroot') . 'view.php');
require_once('lib.php');


$manageform = pieform(array(
    'name'       => 'manageform',
    'renderer'   => 'datatables',
    'plugintype' => 'artefact',
    'pluginname' => 'cloud',
    'configdirs' => array(get_config('libroot') . 'form/', get_config('docroot') . 'artefact/cloud/form/'),
    'elements'   => array(
        'manage' => array(
            'type'     => 'datatables',
            'title'    => '', //get_string('selectfiles','blocktype.cloud/dropbox'),
            'service'  => 'dropbox',
            'block'    => 0,
            'fullpath' => null,
            'options'  => array(
                'manageButtons'  => true,
                'showFolders'    => true,
                'showFiles'      => true,
                'selectFolders'  => false,
                'selectFiles'    => false,
                'selectMultiple' => false
            ),
        ),
    ),
));


$smarty = smarty();
$smarty->assign('SERVICE', 'dropbox');
$smarty->assign('manageform', $manageform);
$smarty->display('blocktype:dropbox:manage.tpl');

?>