<?php
/* Copyright (C) 2009-2010 Laurent Destailleur  <eldy@users.sourceforge.net>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 * or see http://www.gnu.org/
 */

/**
 *  \file		htdocs/core/lib/memory.lib.php
 *  \brief		Set of function for memory/cache management
 */




/**
 * 	Regenerate files .class.php
 *
 *  @param	string      $destdir		Directory
 * 	@param	string		$module			Module name
 *  @param	string      $objectname		Name of object
 * 	@param	string		$newmask		New mask
 *  @param	string      $readdir		Directory source (use $destdir when not defined)
 *  @param	string		$addfieldentry	Array of the field entry to add array('key'=>,'type'=>,''label'=>,'visible'=>,'enabled'=>,'position'=>,'notnull'=>','index'=>,'searchall'=>,'comment'=>,'help'=>,'isameasure')
 *  @param	string		$delfieldentry	Id of field to remove
 * 	@return	int							<=0 if KO, >0 if OK
 */
function rebuildObjectClass($destdir, $module, $objectname, $newmask, $readdir='', $addfieldentry=array() ,$delfieldentry='')
{
    global $db, $langs;

    if (empty($objectname)) return -1;
    if (empty($readdir)) $readdir=$destdir;

    // Check parameters
    if (count($addfieldentry) > 0)
    {
    	if (! preg_match('/^(integer|date|timestamp|varchar|double)/', $addfieldentry['type']))
    	{
    		setEventMessages($langs->trans('FilesForObjectUpdated', $objectname), null, 'errors');
    		return -1;
    	}
    }

    $pathoffiletoeditsrc=$readdir.'/class/'.strtolower($objectname).'.class.php';
    $pathoffiletoedittarget=$destdir.'/class/'.strtolower($objectname).'.class.php'.($readdir != $destdir ? '.new' : '');
    if (! dol_is_file($pathoffiletoeditsrc))
    {
    	$langs->load("errors");
        setEventMessages($langs->trans("ErrorFileNotFound", $pathoffiletoeditsrc), null, 'errors');
        return -1;
    }

    //$pathoffiletoedittmp=$destdir.'/class/'.strtolower($objectname).'.class.php.tmp';
    //dol_delete_file($pathoffiletoedittmp, 0, 1, 1);

    try
    {
        include_once $pathoffiletoeditsrc;
        if (class_exists($objectname)) $object=new $objectname($db);
        else return -1;

        // Backup old file
        dol_copy($pathoffiletoedittarget, $pathoffiletoedittarget.'.back', $newmask, 1);

        // Edit class files
        $contentclass = file_get_contents(dol_osencode($pathoffiletoeditsrc), 'r');

	    // Update ->fields (add or remove entries)
        if (count($object->fields))
        {
        	if (is_array($addfieldentry) && count($addfieldentry))
        	{
        		$name=$addfieldentry['name'];
        		unset($addfieldentry['name']);
        		$object->fields[$name]=$addfieldentry;
        	}
        	if (! empty($delfieldentry))
        	{
        		$name=$delfieldentry;
        		unset($object->fields[$name]);
        	}
        }

        dol_sort_array($object->fields, 'position');

        $i=0;
        $texttoinsert = '// BEGIN MODULEBUILDER PROPERTIES'."\n";
        $texttoinsert.= "\t".'/**'."\n";
        $texttoinsert.= "\t".' * @var array  Array with all fields and their property. Do not use it as a static var. It may be modified by constructor.'."\n";
        $texttoinsert.= "\t".' */'."\n";
        $texttoinsert.= "\t".'public $fields=array('."\n";

        if (count($object->fields))
        {
            foreach($object->fields as $key => $val)
            {
                $i++;
                $texttoinsert.= "\t\t'".$key."' => array('type'=>'".$val['type']."', 'label'=>'".$val['label']."',";
                $texttoinsert.= " 'visible'=>".($val['visible']!=''?$val['visible']:-1).",";
                $texttoinsert.= " 'enabled'=>".($val['enabled']!=''?$val['enabled']:1).",";
                $texttoinsert.= " 'position'=>".($val['position']!=''?$val['position']:50).",";
                $texttoinsert.= " 'notnull'=>".($val['notnull']!=''?$val['notnull']:-1).",";
                if ($val['index']) $texttoinsert.= " 'index'=>".$val['index'].",";
                if ($val['searchall']) $texttoinsert.= " 'searchall'=>".$val['searchall'].",";
                if ($val['comment']) $texttoinsert.= " 'comment'=>'".$val['comment']."',";
                if ($val['isameasure']) $texttoinsert.= " 'isameasure'=>'".$val['isameasure']."',";
                if ($val['help']) $texttoinsert.= " 'help'=>'".$val['help']."',";
                $texttoinsert.= "),\n";
            }
        }

        $texttoinsert.= "\t".');'."\n";

        if (count($object->fields))
        {
        	$typetotypephp=array('integer'=>'integer', 'varchar'=>'string');

        	foreach($object->fields as $key => $val)
            {
                $i++;
                //$typephp=$typetotypephp[$val['type']];
                $texttoinsert.= "\t".'public $'.$key.";";
                //if ($key == 'rowid')  $texttoinsert.= ' AUTO_INCREMENT PRIMARY KEY';
                //if ($key == 'entity') $texttoinsert.= ' DEFAULT 1';
                //$texttoinsert.= ($val['notnull']?' NOT NULL':'');
                //if ($i < count($object->fields)) $texttoinsert.=";";
                $texttoinsert.= "\n";
            }
        }

        $texttoinsert.= "\t".'// END MODULEBUILDER PROPERTIES';

        //print($texttoinsert);exit;

        $contentclass = preg_replace('/\/\/ BEGIN MODULEBUILDER PROPERTIES.*END MODULEBUILDER PROPERTIES/ims', $texttoinsert, $contentclass);

        dol_mkdir(dirname($pathoffiletoedittarget));

        //file_put_contents($pathoffiletoedittmp, $contentclass);
        file_put_contents(dol_osencode($pathoffiletoedittarget), $contentclass);
        @chmod($pathoffiletoedittarget, octdec($newmask));

        return 1;
    }
    catch(Exception $e)
    {
        print $e->getMessage();
        return -1;
    }
}

/**
 * 	Save data into a memory area shared by all users, all sessions on server
 *
 *  @param	string      $destdir		Directory
 * 	@param	string		$module			Module name
 *  @param	string      $objectname		Name of object
 * 	@param	string		$newmask		New mask
 *  @param	string      $readdir		Directory source (use $destdir when not defined)
 * 	@return	int							<=0 if KO, >0 if OK
 */
function rebuildObjectSql($destdir, $module, $objectname, $newmask, $readdir='')
{
    global $db, $langs;

    if (empty($objectname)) return -1;
    if (empty($readdir)) $readdir=$destdir;

    $pathoffiletoclasssrc=$readdir.'/class/'.strtolower($objectname).'.class.php';

    // Edit .sql file
    $pathoffiletoeditsrc=$readdir.'/sql/llx_'.strtolower($objectname).'.sql';
    $pathoffiletoedittarget=$destdir.'/sql/llx_'.strtolower($objectname).'.sql'.($readdir != $destdir ? '.new' : '');
	if (! dol_is_file($pathoffiletoeditsrc))
    {
    	$langs->load("errors");
    	setEventMessages($langs->trans("ErrorFileNotFound", $pathoffiletoeditsrc), null, 'errors');
    	return -1;
    }

    try
    {
    	include_once $pathoffiletoclasssrc;
        if (class_exists($objectname)) $object=new $objectname($db);
        else return -1;
    }
    catch(Exception $e)
    {
        print $e->getMessage();
    }

    // Backup old file
    dol_copy($pathoffiletoedittarget, $pathoffiletoedittarget.'.back', $newmask, 1);

    $contentsql = file_get_contents(dol_osencode($pathoffiletoeditsrc), 'r');

    $i=0;
    $texttoinsert = '-- BEGIN MODULEBUILDER FIELDS'."\n";
    if (count($object->fields))
    {
        foreach($object->fields as $key => $val)
        {
            $i++;
            $texttoinsert.= "\t".$key." ".$val['type'];
            if ($key == 'rowid')  $texttoinsert.= ' AUTO_INCREMENT PRIMARY KEY';
            if ($key == 'entity') $texttoinsert.= ' DEFAULT 1';
            $texttoinsert.= (($val['notnull'] > 0)?' NOT NULL':'');
            if ($i < count($object->fields)) $texttoinsert.=", ";
            $texttoinsert.= "\n";
        }
    }
    $texttoinsert.= "\t".'-- END MODULEBUILDER FIELDS';

    $contentsql = preg_replace('/-- BEGIN MODULEBUILDER FIELDS.*END MODULEBUILDER FIELDS/ims', $texttoinsert, $contentsql);

    file_put_contents($pathoffiletoedittarget, $contentsql);
    @chmod($pathoffiletoedittarget, octdec($newmask));


    // Edit .key.sql file
    $pathoffiletoeditsrc=$destdir.'/sql/llx_'.strtolower($objectname).'.key.sql';
    $pathoffiletoedittarget=$destdir.'/sql/llx_'.strtolower($objectname).'.key.sql'.($readdir != $destdir ? '.new' : '');

    $contentsql = file_get_contents(dol_osencode($pathoffiletoeditsrc), 'r');

    $i=0;
    $texttoinsert = '-- BEGIN MODULEBUILDER INDEXES'."\n";
    if (count($object->fields))
    {
        foreach($object->fields as $key => $val)
        {
            $i++;
            if ($val['index'])
            {
                $texttoinsert.= "ALTER TABLE llx_".strtolower($objectname)." ADD INDEX idx_".strtolower($objectname)."_".$key." (".$key.");";
                $texttoinsert.= "\n";
            }
        }
    }
    $texttoinsert.= '-- END MODULEBUILDER INDEXES';

    $contentsql = preg_replace('/-- BEGIN MODULEBUILDER INDEXES.*END MODULEBUILDER INDEXES/ims', $texttoinsert, $contentsql);

    dol_mkdir(dirname($pathoffiletoedittarget));

    file_put_contents($pathoffiletoedittarget, $contentsql);
    @chmod($pathoffiletoedittarget, octdec($newmask));

    return 1;
}


